<?php
/**
 * API: 予約データ取得
 * 
 * 指定された日付の予約データを JSON 形式で返す
 */

// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Appointment.php';
require_once '../includes/functions.php';

// セッション確認
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '認証されていません。']);
    exit;
}

// CSRFトークン検証
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'セキュリティトークンが無効です。']);
    exit;
}

// 現在のサロンIDを取得
$salon_id = isset($_SESSION['salon_id']) ? (int)$_SESSION['salon_id'] : 0;

if (!$salon_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'サロンが選択されていません。']);
    exit;
}

// パラメータ取得
$date = isset($_POST['date']) ? htmlspecialchars($_POST['date']) : date('Y-m-d');
$staff_ids = isset($_POST['staff_ids']) && is_array($_POST['staff_ids']) ? array_map('intval', $_POST['staff_ids']) : [];

// 日付のバリデーション
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '無効な日付形式です。']);
    exit;
}

// Appointmentクラスのインスタンス作成
$appointmentObj = new Appointment();

try {
    // 予約データを取得
    $filters = ['date' => $date];
    
    // スタッフIDが指定されている場合はフィルタリング
    if (!empty($staff_ids)) {
        // 複数のスタッフIDに対応するため、カスタムフィルタリングを行う
        $appointments = [];
        foreach ($staff_ids as $staff_id) {
            $filters['staff_id'] = $staff_id;
            $staff_appointments = $appointmentObj->getAllAppointments($salon_id, $filters);
            $appointments = array_merge($appointments, $staff_appointments);
        }
    } else {
        // 全スタッフの予約を取得
        $appointments = $appointmentObj->getAllAppointments($salon_id, $filters);
    }
    
    // JSON形式で返す
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'date' => $date,
        'appointments' => $appointments
    ]);
    
} catch (Exception $e) {
    error_log('API 予約データ取得エラー: ' . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラーが発生しました。'
    ]);
}
