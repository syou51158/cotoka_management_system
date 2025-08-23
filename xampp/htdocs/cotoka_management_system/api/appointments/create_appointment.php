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

// POSTデータのチェック
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '不正なリクエストメソッドです。']);
    exit;
}

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'データベース接続エラー: ' . $e->getMessage()]);
    exit;
}

// 現在のサロンIDとテナントIDを取得
$salon_id = isset($_SESSION['salon_id']) ? $_SESSION['salon_id'] : 1;
$tenant_id = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : 1;

// POSTデータを取得
$data = json_decode(file_get_contents('php://input'), true);

// 必須パラメータの検証
$required_fields = ['title', 'resource_id', 'start', 'end', 'client_id', 'appointment_type', 'status'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => "{$field}は必須です。"]);
        exit;
    }
}

// データの取得と検証
$title = trim($data['title']);
$resource_id = trim($data['resource_id']);
$client_id = intval($data['client_id']);
$appointment_type = trim($data['appointment_type']);
$task_description = isset($data['task_description']) ? trim($data['task_description']) : '';
$status = trim($data['status']);
$notes = isset($data['notes']) ? trim($data['notes']) : '';

// 日付と時間の分割と検証
$start_parts = explode('T', $data['start']);
$end_parts = explode('T', $data['end']);

if (count($start_parts) !== 2 || count($end_parts) !== 2) {
    echo json_encode(['success' => false, 'message' => '無効な日付/時間形式です。']);
    exit;
}

$appointment_date = $start_parts[0];
$start_time = $start_parts[1];
$end_time = $end_parts[1];

// 日付形式の検証
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
    echo json_encode(['success' => false, 'message' => '無効な日付形式です。']);
    exit;
}

// 時間形式の検証
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end_time)) {
    echo json_encode(['success' => false, 'message' => '無効な時間形式です。']);
    exit;
}

// 開始時間が終了時間より前かチェック
if (strtotime($appointment_date . ' ' . $start_time) >= strtotime($appointment_date . ' ' . $end_time)) {
    echo json_encode(['success' => false, 'message' => '開始時間は終了時間より前でなければなりません。']);
    exit;
}

// シフト時間チェックを追加
// スタッフIDから数値部分を抽出
$staff_id_parts = explode('_', $resource_id);
$staff_id = isset($staff_id_parts[1]) ? intval($staff_id_parts[1]) : 0;

// スタッフのシフト時間を確認
$shift_stmt = $conn->prepare("
    SELECT start_time, end_time
    FROM staff_shifts
    WHERE staff_id = :staff_id
        AND salon_id = :salon_id
        AND shift_date = :appointment_date
        AND status = 'active'
");
$shift_stmt->bindParam(':staff_id', $staff_id);
$shift_stmt->bindParam(':salon_id', $salon_id);
$shift_stmt->bindParam(':appointment_date', $appointment_date);
$shift_stmt->execute();
$shift = $shift_stmt->fetch(PDO::FETCH_ASSOC);

// 特定のシフトがない場合は曜日パターンを確認
if (!$shift) {
    $day_of_week = date('w', strtotime($appointment_date));
    $pattern_stmt = $conn->prepare("
        SELECT start_time, end_time
        FROM staff_shift_patterns
        WHERE staff_id = :staff_id
            AND salon_id = :salon_id
            AND day_of_week = :day_of_week
            AND is_active = 1
    ");
    $pattern_stmt->bindParam(':staff_id', $staff_id);
    $pattern_stmt->bindParam(':salon_id', $salon_id);
    $pattern_stmt->bindParam(':day_of_week', $day_of_week);
    $pattern_stmt->execute();
    $shift = $pattern_stmt->fetch(PDO::FETCH_ASSOC);
}

// シフトが存在しない場合、予約を許可しない
if (!$shift) {
    echo json_encode([
        'success' => false,
        'message' => 'スタッフはこの日にシフトに入っていません。'
    ]);
    exit;
}

// 予約時間がシフト時間内かチェック
$shift_start = strtotime($appointment_date . ' ' . $shift['start_time']);
$shift_end = strtotime($appointment_date . ' ' . $shift['end_time']);
$appt_start = strtotime($appointment_date . ' ' . $start_time);
$appt_end = strtotime($appointment_date . ' ' . $end_time);

if ($appt_start < $shift_start || $appt_end > $shift_end) {
    echo json_encode([
        'success' => false,
        'message' => 'スタッフの勤務時間（' . date('H:i', $shift_start) . '～' . date('H:i', $shift_end) . '）内で予約してください。'
    ]);
    exit;
}

// 既存の予約と重複しないかチェック
try {
    $stmt = $conn->prepare("
        SELECT appointment_id
        FROM appointments
        WHERE salon_id = :salon_id
        AND resource_id = :resource_id
        AND appointment_date = :appointment_date
        AND ((start_time <= :start_time AND end_time > :start_time)
            OR (start_time < :end_time AND end_time >= :end_time)
            OR (start_time >= :start_time AND end_time <= :end_time))
        AND status NOT IN ('cancelled', 'no-show')
    ");
    
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->bindParam(':resource_id', $resource_id);
    $stmt->bindParam(':appointment_date', $appointment_date);
    $stmt->bindParam(':start_time', $start_time);
    $stmt->bindParam(':end_time', $end_time);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'この時間帯には既に予約が入っています。']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '重複チェックエラー: ' . $e->getMessage()]);
    exit;
}

// 新しい予約を作成
try {
    $stmt = $conn->prepare("
        INSERT INTO appointments (
            title,
            resource_id,
            appointment_date,
            start_time,
            end_time,
            client_id,
            appointment_type,
            task_description,
            status,
            notes,
            salon_id,
            tenant_id,
            created_by
        ) VALUES (
            :title,
            :resource_id,
            :appointment_date,
            :start_time,
            :end_time,
            :client_id,
            :appointment_type,
            :task_description,
            :status,
            :notes,
            :salon_id,
            :tenant_id,
            :created_by
        )
    ");
    
    $created_by = $_SESSION['user_id'];
    
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':resource_id', $resource_id);
    $stmt->bindParam(':appointment_date', $appointment_date);
    $stmt->bindParam(':start_time', $start_time);
    $stmt->bindParam(':end_time', $end_time);
    $stmt->bindParam(':client_id', $client_id);
    $stmt->bindParam(':appointment_type', $appointment_type);
    $stmt->bindParam(':task_description', $task_description);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':notes', $notes);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->bindParam(':tenant_id', $tenant_id);
    $stmt->bindParam(':created_by', $created_by);
    
    $result = $stmt->execute();
    $appointment_id = $conn->lastInsertId();
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => '予約が正常に作成されました。',
            'appointment_id' => $appointment_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '予約の作成に失敗しました。']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
} 