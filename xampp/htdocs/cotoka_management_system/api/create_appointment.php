<?php
/**
 * API: 予約作成
 * 
 * 新しい予約を作成するAPIエンドポイント
 */

// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Appointment.php';
require_once '../includes/functions.php';

// JSONリクエストを受け取る設定
header('Content-Type: application/json');

// セッション確認
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '認証されていません。']);
    exit;
}

// CSRFトークン検証
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'セキュリティトークンが無効です。']);
    exit;
}

// 現在のサロンIDを取得
$salon_id = isset($_SESSION['salon_id']) ? (int)$_SESSION['salon_id'] : 0;

if (!$salon_id) {
    echo json_encode(['success' => false, 'message' => 'サロンが選択されていません。']);
    exit;
}

// リクエストパラメータのバリデーション
// 予約タイプを取得（デフォルトは customer）
$appointment_type = isset($_POST['appointment_type']) ? $_POST['appointment_type'] : 'customer';
$data['appointment_type'] = $appointment_type;

// 編集モードかどうかを確認
$is_edit_mode = isset($_POST['appointment_action']) && $_POST['appointment_action'] === 'edit' && 
               isset($_POST['appointment_id']) && !empty($_POST['appointment_id']);

if ($is_edit_mode) {
    $appointment_id = (int)$_POST['appointment_id'];
}

// 予約タイプによって必須フィールドを変更
$required_fields = [
    'staff_id',
    'appointment_date', 
    'start_time', 
    'end_time'
];

// 顧客予約の場合は顧客IDとサービスIDが必須
if ($appointment_type === 'customer') {
    $required_fields[] = 'customer_id';
    $required_fields[] = 'service_id';
} 
// 業務の場合は業務内容が必須
else if ($appointment_type === 'task') {
    $required_fields[] = 'task_description';
}

$data = [];
$errors = [];

// 編集モードでtask_descriptionが送信されてこない場合でも処理を続行
if ($is_edit_mode && $appointment_type === 'task') {
    if (!isset($_POST['task_description']) || empty($_POST['task_description'])) {
        error_log("task_description is missing, setting to default value");
        $_POST['task_description'] = '未指定の業務';
    }
}

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        $errors[] = "「{$field}」は必須項目です。";
    } else {
        // 基本的なサニタイズ
        $data[$field] = htmlspecialchars($_POST[$field]);
    }
}

// 予約タイプをデータに追加
$data['appointment_type'] = $appointment_type;

// 任意フィールドも取得
foreach ($_POST as $key => $value) {
    if (!isset($data[$key]) && $key !== 'csrf_token') {
        $data[$key] = htmlspecialchars($value);
    }
}

// 日付と時間のバリデーション
if (isset($data['appointment_date'])) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['appointment_date'])) {
        $errors[] = "日付の形式が無効です。YYYY-MM-DD形式で入力してください。";
    }
}

if (isset($data['start_time']) && isset($data['end_time'])) {
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['start_time']) || 
        !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['end_time'])) {
        $errors[] = "時間形式が無効です。HH:MM(:SS)形式で入力してください。";
    }
    
    // 開始時間が終了時間より前かチェック
    if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
        $errors[] = "開始時間は終了時間よりも前でなければなりません。";
    }
}

// エラーがあれば返す
if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'message' => '入力エラーがあります。',
        'errors' => $errors
    ]);
    exit;
}

// Appointmentクラスのインスタンス作成
$appointmentObj = new Appointment();

try {
    // データにサロンIDを追加
    $data['salon_id'] = $salon_id;
    
    // メモや追加情報を取得（任意項目）
    if (isset($_POST['notes'])) {
        $data['notes'] = htmlspecialchars($_POST['notes']);
    }
    
    // ステータスが指定されていなければデフォルト値を設定
    if (!isset($_POST['status']) || empty($_POST['status'])) {
        $data['status'] = 'scheduled';
    } else {
        $data['status'] = htmlspecialchars($_POST['status']);
    }
    
    // 業務タイプの場合、customer_idとservice_idが空の場合はデフォルト値を設定
    if ($appointment_type === 'task') {
        if (empty($data['customer_id'])) {
            $data['customer_id'] = 0;
            error_log("業務タイプのため、customer_idにデフォルト値0を設定");
        }
        if (empty($data['service_id'])) {
            $data['service_id'] = 0;
            error_log("業務タイプのため、service_idにデフォルト値0を設定");
        }
    }
    
    // 編集モードか新規作成モードかによって処理を分岐
    if ($is_edit_mode) {
        // デバッグ情報をログに記録
        error_log("予約更新処理開始 - ID: $appointment_id, タイプ: " . $data['appointment_type']);
        error_log("更新データ: " . json_encode($data));
        
        // 予約を更新
        $success = $appointmentObj->updateAppointment($appointment_id, $data);
        
        if ($success) {
            // 更新した予約の詳細を取得
            $appointment = $appointmentObj->getById($appointment_id);
            
            error_log("予約更新成功 - ID: $appointment_id");
            
            echo json_encode([
                'success' => true,
                'message' => '予約が正常に更新されました。',
                'appointment_id' => $appointment_id,
                'appointment' => $appointment
            ]);
        } else {
            error_log("予約更新失敗 - ID: $appointment_id");
            
            // 失敗の原因を特定するための追加情報
            $lastError = '';
            if ($appointmentObj->getLastErrorMessage()) {
                $lastError = $appointmentObj->getLastErrorMessage();
                error_log("エラー詳細: " . $lastError);
            }
            
            echo json_encode([
                'success' => false,
                'message' => '予約の更新に失敗しました。',
                'error_detail' => $lastError
            ]);
        }
    } else {
        // 予約を作成
        $appointment_id = $appointmentObj->create($data);
        
        if ($appointment_id) {
            // 作成した予約の詳細を取得
            $appointment = $appointmentObj->getById($appointment_id);
            
            echo json_encode([
                'success' => true,
                'message' => '予約が正常に作成されました。',
                'appointment_id' => $appointment_id,
                'appointment' => $appointment
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => '予約の作成に失敗しました。'
            ]);
        }
    }
    
} catch (Exception $e) {
    error_log('API 予約作成エラー: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラーが発生しました。'
    ]);
}
?>
