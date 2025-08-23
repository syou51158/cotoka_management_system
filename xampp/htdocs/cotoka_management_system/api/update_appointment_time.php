<?php
/**
 * API: 予約時間更新
 * 
 * 予約の時間を更新するAPIエンドポイント
 * ドラッグアンドドロップ操作からのリクエストにも対応
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

// リクエストタイプを判断（JSONかフォームデータか）
$isJsonRequest = false;
$requestData = [];

// リクエストヘッダーのContent-Typeを確認
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    // JSONリクエストの場合
    $jsonInput = file_get_contents('php://input');
    $requestData = json_decode($jsonInput, true);
    $isJsonRequest = true;
    
    // JSONデータの検証
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => '無効なJSONデータです: ' . json_last_error_msg()]);
        exit;
    }
} else {
    // 通常のフォームデータの場合
    $requestData = $_POST;
}

// CSRFトークン検証（JSONリクエストの場合は省略可能）
if (!$isJsonRequest && (!isset($requestData['csrf_token']) || !validateCSRFToken($requestData['csrf_token']))) {
    echo json_encode(['success' => false, 'message' => 'セキュリティトークンが無効です。']);
    exit;
}

// パラメータの取得と検証
if (!isset($requestData['appointment_id']) || !is_numeric($requestData['appointment_id'])) {
    echo json_encode(['success' => false, 'message' => '予約IDが無効です。']);
    exit;
}

// 時間情報の検証
$hasTimeInfo = isset($requestData['start_time']) && isset($requestData['end_time']);
$hasDateInfo = isset($requestData['appointment_date']) && !empty($requestData['appointment_date']);

if (!$hasTimeInfo && !$hasDateInfo) {
    echo json_encode(['success' => false, 'message' => '更新する情報（日付または時間）が指定されていません。']);
    exit;
}

// 時間形式の検証（指定されている場合）
if ($hasTimeInfo) {
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $requestData['start_time']) || 
        !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $requestData['end_time'])) {
        echo json_encode(['success' => false, 'message' => '時間形式が無効です。HH:MM(:SS)形式で入力してください。']);
        exit;
    }

    // 開始時間が終了時間より前かチェック
    if (strtotime($requestData['start_time']) >= strtotime($requestData['end_time'])) {
        echo json_encode(['success' => false, 'message' => '開始時間は終了時間よりも前でなければなりません。']);
        exit;
    }
}

// 予約日の検証（指定されている場合）
$appointment_date = null;
if ($hasDateInfo) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestData['appointment_date'])) {
        echo json_encode(['success' => false, 'message' => '日付の形式が無効です。YYYY-MM-DD形式で入力してください。']);
        exit;
    }
    $appointment_date = htmlspecialchars($requestData['appointment_date']);
}

// バリデーション通過後のデータを準備
$appointment_id = (int)$requestData['appointment_id'];
$start_time = $hasTimeInfo ? htmlspecialchars($requestData['start_time']) : null;
$end_time = $hasTimeInfo ? htmlspecialchars($requestData['end_time']) : null;

// スタッフIDの変更（オプション）
$staff_id = null;
if (isset($requestData['staff_id']) && is_numeric($requestData['staff_id'])) {
    $staff_id = (int)$requestData['staff_id'];
}

// Appointmentクラスのインスタンス作成
$appointmentObj = new Appointment();

try {
    // 予約の存在チェック
    $appointment = $appointmentObj->getById($appointment_id);
    
    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => '指定された予約が見つかりません。']);
        exit;
    }
    
    // 更新データの準備
    $updateData = [];
    
    // 時間情報を追加（指定されている場合）
    if ($hasTimeInfo) {
        $updateData['start_time'] = $start_time;
        $updateData['end_time'] = $end_time;
    }
    
    // 日付情報を追加（指定されている場合）
    if ($appointment_date) {
        $updateData['appointment_date'] = $appointment_date;
    }
    
    // スタッフ情報を追加（指定されている場合）
    if ($staff_id) {
        $updateData['staff_id'] = $staff_id;
    }

    // 更新データが空の場合は処理を終了
    if (empty($updateData)) {
        echo json_encode(['success' => false, 'message' => '更新するデータがありません。']);
        exit;
    }
    
    // 予約が競合していないかチェック
    $conflictCheck = $appointmentObj->checkTimeConflict(
        $appointment_date ?? $appointment['appointment_date'],
        $start_time ?? $appointment['start_time'],
        $end_time ?? $appointment['end_time'],
        $staff_id ?? $appointment['staff_id'],
        $appointment_id
    );
    
    if ($conflictCheck) {
        echo json_encode([
            'success' => false, 
            'message' => '選択した時間帯は既に予約があります。別の時間を選択してください。',
            'conflict' => $conflictCheck
        ]);
        exit;
    }
    
    // 予約時間を更新
    $result = $appointmentObj->updateTime($appointment_id, $updateData);
    
    if ($result) {
        // 更新後の予約データを取得
        $updatedAppointment = $appointmentObj->getById($appointment_id);
        
        echo json_encode([
            'success' => true,
            'message' => '予約が正常に更新されました。',
            'appointment' => $updatedAppointment
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '予約の更新に失敗しました。']);
    }
    
} catch (Exception $e) {
    error_log('API 予約時間更新エラー: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラーが発生しました。' . ($isJsonRequest ? ' ' . $e->getMessage() : '')
    ]);
}
?>
