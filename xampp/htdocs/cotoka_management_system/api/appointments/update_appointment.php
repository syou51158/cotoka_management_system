<?php
/**
 * 予約更新API
 *
 * 予約の詳細（日時、担当、サービス、メモ、顧客など）を更新します。
 */

require_once '../../config/config.php';
require_once '../../classes/Database.php';
require_once '../../classes/Appointment.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '認証エラー：ログインが必要です。']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '不正なリクエストです。']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'セキュリティエラー：不正なリクエストです。']);
    exit;
}

$appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
if ($appointment_id <= 0) {
    echo json_encode(['success' => false, 'message' => '予約IDが不正です。']);
    exit;
}

// 受け取り可能なフィールドを整形
$allowed_fields = [
    'salon_id', 'tenant_id', 'customer_id', 'staff_id', 'service_id', 'appointment_date',
    'start_time', 'end_time', 'status', 'notes', 'appointment_type', 'duration_minutes',
];

$data = [];
foreach ($allowed_fields as $f) {
    if (isset($_POST[$f])) {
        $data[$f] = $_POST[$f];
    }
}

// 日付と時間形式の簡易バリデーション（詳細は Appointment::updateAppointment 内で正規化）
if (isset($data['appointment_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['appointment_date'])) {
    echo json_encode(['success' => false, 'message' => '日付形式が不正です（YYYY-MM-DD）。']);
    exit;
}
if (isset($data['start_time']) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['start_time'])) {
    echo json_encode(['success' => false, 'message' => '開始時刻形式が不正です（HH:MM）。']);
    exit;
}
if (isset($data['end_time']) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['end_time'])) {
    echo json_encode(['success' => false, 'message' => '終了時刻形式が不正です（HH:MM）。']);
    exit;
}

try {
    $appointment = new Appointment();

    // 更新対象の予約が属するサロンの整合性チェック
    $salon_id = isset($_SESSION['salon_id']) ? (int)$_SESSION['salon_id'] : null;
    $current = $appointment->getAppointmentById($appointment_id);
    if (!$current) {
        echo json_encode(['success' => false, 'message' => '対象の予約が見つかりません。']);
        exit;
    }
    if ($salon_id !== null && isset($current['salon_id']) && (int)$current['salon_id'] !== $salon_id) {
        echo json_encode(['success' => false, 'message' => 'このサロンの予約ではありません。']);
        exit;
    }

    // 更新実行（シフト/重複チェックはメソッド内で実施）
    $ok = $appointment->updateAppointment($appointment_id, $data);

    if ($ok) {
        echo json_encode([
            'success' => true,
            'message' => '予約を更新しました。',
            'appointment_id' => $appointment_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '予約の更新に失敗しました。'
        ]);
    }
} catch (Exception $e) {
    error_log('予約更新エラー: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '内部エラー：予約の更新に失敗しました。']);
}