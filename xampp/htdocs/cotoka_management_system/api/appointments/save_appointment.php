<?php
// 予約保存API（Supabase RPC版）

// 必要なファイルを読み込み
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// JSON形式のレスポンスを返すようにヘッダーを設定
header('Content-Type: application/json');

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => '認証が必要です。'
    ]);
    exit;
}

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => '不正なリクエストメソッドです。'
    ]);
    exit;
}

// 現在のサロンIDとテナントIDを取得
$salon_id = isset($_SESSION['salon_id']) ? (int)$_SESSION['salon_id'] : 0;
$tenant_id = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;

// POSTデータの取得と検証
$appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
$appointment_type = isset($_POST['appointment_type']) ? $_POST['appointment_type'] : 'customer';
$customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
$staff_id = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : null;
$service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : null;
$appointment_date = isset($_POST['appointment_date']) ? $_POST['appointment_date'] : null;
$start_time = isset($_POST['start_time']) ? $_POST['start_time'] : null;
$end_time = isset($_POST['end_time']) ? $_POST['end_time'] : null;
$status = isset($_POST['status']) ? $_POST['status'] : 'scheduled';
$notes = isset($_POST['notes']) ? $_POST['notes'] : null;
$task_description = isset($_POST['task_description']) ? $_POST['task_description'] : null;

// 基本的なバリデーション
$errors = [];
if (empty($staff_id)) $errors[] = 'スタッフを選択してください。';
if (empty($appointment_date) || empty($start_time) || empty($end_time)) $errors[] = '日付と時間は必須です。';
if ($appointment_type === 'customer' && (empty($customer_id) || empty($service_id))) $errors[] = 'お客様予約の場合、お客様とサービスの選択は必須です。';
if ($appointment_type === 'internal' && empty($task_description)) $errors[] = '業務内容を入力してください。';

// 時間の妥当性
if (!empty($start_time) && !empty($end_time)) {
    $start_datetime = strtotime($appointment_date . ' ' . $start_time);
    $end_datetime = strtotime($appointment_date . ' ' . $end_time);
    if ($start_datetime >= $end_datetime) $errors[] = '開始時間は終了時間より前である必要があります。';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Supabase RPC 呼び出し（作成/更新）
$rpc = supabaseRpcCall('appointment_upsert_admin', [
    'p_appointment_id' => $appointment_id,
    'p_salon_id' => $salon_id,
    'p_tenant_id' => $tenant_id ?: null,
    'p_customer_id' => $customer_id ?: null,
    'p_staff_id' => $staff_id,
    'p_service_id' => $service_id ?: null,
    'p_appointment_date' => $appointment_date,
    'p_start_time' => $start_time,
    'p_end_time' => $end_time,
    'p_status' => $status,
    'p_notes' => $notes,
    'p_appointment_type' => $appointment_type,
    'p_task_description' => $task_description,
    'p_user_id' => (int)$_SESSION['user_id']
]);

if ($rpc['success']) {
    // REST/RPCはスカラ値や配列で返ることがある
    $newId = null;
    if (is_array($rpc['data'])) {
        // e.g. [123]
        $first = array_values($rpc['data'])[0] ?? null;
        $newId = is_array($first) ? ($first['appointment_upsert_admin'] ?? null) : $first;
    } else {
        $newId = $rpc['data'];
    }
    $finalId = $appointment_id ?: (int)$newId;
    echo json_encode([
        'success' => true,
        'appointment_id' => $finalId,
        'message' => '予約が保存されました。'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => '保存エラー: ' . ($rpc['message'] ?? '')]);
}
exit; 