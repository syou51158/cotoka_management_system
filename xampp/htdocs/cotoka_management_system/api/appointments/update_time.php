<?php
// 必要なファイルを読み込み
require_once '../../config/config.php';
require_once '../../classes/Database.php';
require_once '../../includes/functions.php';

// タイムゾーンを設定
date_default_timezone_set('Asia/Tokyo');

// ヘッダー設定
header('Content-Type: application/json');

// セッションチェック
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '認証されていません。']);
    exit;
}

// POSTのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '不正なリクエストメソッドです。']);
    exit;
}

// CSRF対策
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'セキュリティトークンが無効です。']);
    exit;
}

// セッションからサロンID
$salon_id = isset($_SESSION['salon_id']) ? (int)$_SESSION['salon_id'] : 0;

// 入力取得
$appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
$staff_id = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : 0;
$start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
$end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
$appointment_date = isset($_POST['appointment_date']) ? trim($_POST['appointment_date']) : '';

// 必須チェック
if (!$salon_id || !$appointment_id || !$staff_id || !$start_time || !$appointment_date) {
    echo json_encode(['success' => false, 'message' => '必須パラメータが不足しています。']);
    exit;
}

// フォーマットチェック
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time)) {
    echo json_encode(['success' => false, 'message' => '無効な開始時間形式です。']);
    exit;
}
if ($end_time && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end_time)) {
    echo json_encode(['success' => false, 'message' => '無効な終了時間形式です。']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
    echo json_encode(['success' => false, 'message' => '無効な日付形式です。']);
    exit;
}

// 終了時間の自動補完（未指定時）
if (!$end_time) {
    $end_time_obj = new DateTime($start_time);
    $end_time_obj->modify('+30 minutes');
    $end_time = $end_time_obj->format('H:i:s');
}

// 時間の前後関係
$start_dt = strtotime($appointment_date . ' ' . $start_time);
$end_dt = strtotime($appointment_date . ' ' . $end_time);
if ($start_dt >= $end_dt) {
    echo json_encode(['success' => false, 'message' => '開始時間は終了時間より前でなければなりません。']);
    exit;
}

// Supabase RPC 呼び出し（検証と更新をサーバー側で実施）
$rpc = supabaseRpcCall('appointment_update_time', [
    'p_appointment_id' => $appointment_id,
    'p_salon_id' => $salon_id,
    'p_staff_id' => $staff_id,
    'p_appointment_date' => $appointment_date,
    'p_start_time' => $start_time,
    'p_end_time' => $end_time,
    'p_updated_by' => (int)$_SESSION['user_id']
]);

if ($rpc['success']) {
    echo json_encode([
        'success' => true,
        'message' => '予約時間が正常に更新されました。',
        'appointment_id' => $appointment_id,
        'new_start_time' => $start_time,
        'new_end_time' => $end_time
    ]);
} else {
    // エラーメッセージを整形
    $msg = $rpc['message'] ?? '更新に失敗しました';
    echo json_encode(['success' => false, 'message' => $msg]);
}
exit; 