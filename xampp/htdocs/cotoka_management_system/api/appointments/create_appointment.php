<?php
// API: 予約作成（Supabase/Databaseクラス＆Appointmentクラス利用版）

require_once '../../config/config.php';
require_once '../../classes/Database.php';
require_once '../../classes/Appointment.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// 認証チェック
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '認証されていません。']);
    exit;
}

// メソッドチェック（JSON想定のPOST）
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '不正なリクエストメソッドです。']);
    exit;
}

// JSON入力の取得
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => '無効なJSONデータです: ' . json_last_error_msg()]);
    exit;
}

// CSRFトークン検証（JSONでも送られてくる想定）
if (!isset($data['csrf_token']) || !validateCSRFToken($data['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'セキュリティトークンが無効です。']);
    exit;
}

// サロン・テナント情報
$salon_id = isset($_SESSION['salon_id']) ? (int)$_SESSION['salon_id'] : 0;
$tenant_id = getCurrentTenantId();
if ($salon_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'サロンが選択されていません。']);
    exit;
}

// 入力項目
$title            = isset($data['title']) ? trim($data['title']) : null; // Schemaには保存しない（将来利用時のため保持）
$resource_id      = isset($data['resource_id']) ? trim($data['resource_id']) : null; // staff_3 / 3 など
$startISO         = isset($data['start']) ? trim($data['start']) : null; // 例: 2025-01-01T10:00:00+09:00
$endISO           = isset($data['end']) ? trim($data['end']) : null;   // 例: 2025-01-01T10:30:00+09:00
$client_id        = isset($data['client_id']) ? (int)$data['client_id'] : null; // customer_idにマップ
$appointment_type = isset($data['appointment_type']) ? trim($data['appointment_type']) : 'customer';
$status           = isset($data['status']) ? trim($data['status']) : 'scheduled';
$task_description = isset($data['task_description']) ? trim($data['task_description']) : null;
$notes            = isset($data['notes']) ? trim($data['notes']) : null;
$service_id       = isset($data['service_id']) ? (int)$data['service_id'] : null; // 任意

// staff_idの抽出（resource_id => staff_id）
$staff_id = null;
if ($resource_id !== null && $resource_id !== '') {
    if (is_numeric($resource_id)) {
        $staff_id = (int)$resource_id;
    } else {
        // 想定形式: "staff_3"
        $parts = explode('_', $resource_id);
        if (isset($parts[1]) && is_numeric($parts[1])) {
            $staff_id = (int)$parts[1];
        }
    }
}

if (!$staff_id) {
    echo json_encode(['success' => false, 'message' => '担当スタッフの指定が不正です。']);
    exit;
}

// 日付・時刻の抽出
if (!$startISO) {
    echo json_encode(['success' => false, 'message' => '開始日時が指定されていません。']);
    exit;
}

try {
    $startDT = new DateTime($startISO);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '開始日時の形式が不正です。']);
    exit;
}

$appointment_date = $startDT->format('Y-m-d');
$start_time       = $startDT->format('H:i:s');
$end_time         = null;

if ($endISO) {
    try {
        $endDT = new DateTime($endISO);
        $end_time = $endDT->format('H:i:s');
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '終了日時の形式が不正です。']);
        exit;
    }
}

if ($end_time && strtotime($start_time) >= strtotime($end_time)) {
    echo json_encode(['success' => false, 'message' => '開始時間は終了時間より前でなければなりません。']);
    exit;
}

// Appointmentクラスを利用して作成（内部でSupabase/Databaseを使用）
$appointment = new Appointment();

// createAppointmentに渡すデータを整形
$payload = [
    'salon_id'         => $salon_id,
    'tenant_id'        => $tenant_id,
    'staff_id'         => $staff_id,
    'appointment_date' => $appointment_date,
    'start_time'       => $start_time,
    'end_time'         => $end_time, // 未指定ならクラス側で30分補完
    'status'           => $status ?: 'scheduled',
    'notes'            => $notes,
    'appointment_type' => $appointment_type ?: 'customer',
    'task_description' => $task_description,
];

// 顧客予約(customer)の場合はcustomer_idとservice_idを付加
if ($payload['appointment_type'] !== 'task') {
    if ($client_id !== null) { $payload['customer_id'] = (int)$client_id; }
    if ($service_id !== null) { $payload['service_id']  = (int)$service_id; }
}

try {
    $newId = $appointment->createAppointment($payload);
    if ($newId) {
        echo json_encode([
            'success' => true,
            'message' => '予約が正常に作成されました。',
            'appointment_id' => (int)$newId
        ]);
        exit;
    }

    $err = method_exists($appointment, 'getLastErrorMessage') ? $appointment->getLastErrorMessage() : null;
    echo json_encode([
        'success' => false,
        'message' => $err ?: '予約の作成に失敗しました。'
    ]);
    exit;
} catch (Exception $e) {
    error_log('予約作成APIエラー: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'サーバーエラーが発生しました。']);
    exit;
}