<?php
/**
 * 予約・業務データ取得API
 * 
 * メソッド: GET
 * パラメータ:
 *   - salon_id: サロンID
 *   - date: 日付（YYYY-MM-DD）
 */

// 必要なファイルを読み込み
require_once '../../config/config.php';
require_once '../../classes/Database.php';
require_once '../../includes/functions.php';

// セッション確認
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '認証されていません。']);
    exit;
}

// CSRFトークン検証（GETリクエストでは省略できる場合もあり）

// データベース接続
$db = new Database();
$conn = $db->getConnection();

// レスポンス用の配列
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // リクエストパラメータの検証
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $salonId = isset($_GET['salon_id']) ? (int)$_GET['salon_id'] : 0;
    
    // サロンIDのバリデーション
    if ($salonId <= 0) {
        throw new Exception('サロンIDが指定されていないか、無効です。');
    }
    
    // 日付のバリデーション
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('日付の形式が無効です。YYYY-MM-DD形式で指定してください。');
    }
    
    // 予約データの取得
    $appointmentsSql = "
        SELECT 
            a.appointment_id AS id,
            'appointment' AS item_type,
            a.customer_id,
            CONCAT(c.last_name, ' ', c.first_name) AS customer_name,
            a.staff_id,
            CONCAT(s.last_name, ' ', s.first_name) AS staff_name,
            a.service_id,
            sv.name AS service_name,
            a.appointment_date AS event_date,
            a.start_time,
            a.end_time,
            a.status,
            a.notes,
            a.appointment_type,
            a.task_description,
            a.created_at,
            a.updated_at
        FROM 
            appointments a
        LEFT JOIN 
            customers c ON a.customer_id = c.customer_id
        LEFT JOIN 
            staff s ON a.staff_id = s.staff_id
        LEFT JOIN 
            services sv ON a.service_id = sv.service_id
        WHERE 
            a.salon_id = ?
            AND a.appointment_date = ?
    ";
    
    $appointmentsStmt = $conn->prepare($appointmentsSql);
    $appointmentsStmt->execute([$salonId, $date]);
    $appointments = $appointmentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 業務データの取得
    $tasksSql = "
        SELECT 
            t.task_id AS id,
            'task' AS item_type,
            0 AS customer_id,
            t.task_description AS customer_name,
            t.staff_id,
            CONCAT(s.last_name, ' ', s.first_name) AS staff_name,
            0 AS service_id,
            '業務' AS service_name,
            t.task_date AS event_date,
            t.start_time,
            t.end_time,
            t.status,
            NULL AS notes,
            'task' AS appointment_type,
            t.task_description,
            t.created_at,
            t.updated_at
        FROM 
            staff_tasks t
        LEFT JOIN 
            staff s ON t.staff_id = s.staff_id
        WHERE 
            t.salon_id = ?
            AND t.task_date = ?
    ";
    
    $tasksStmt = $conn->prepare($tasksSql);
    $tasksStmt->execute([$salonId, $date]);
    $tasks = $tasksStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 予約と業務のデータを統合
    $allAppointments = array_merge($appointments, $tasks);
    
    // 開始時間でソート
    usort($allAppointments, function($a, $b) {
        return strtotime($a['start_time']) - strtotime($b['start_time']);
    });
    
    // レスポンスの設定
    $response['success'] = true;
    $response['message'] = count($allAppointments) . '件のデータを取得しました。';
    $response['data'] = $allAppointments;
    
} catch (PDOException $e) {
    $response['message'] = 'データベースエラーが発生しました: ' . $e->getMessage();
    error_log('予約・業務データ取得エラー: ' . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('予約・業務データ取得エラー: ' . $e->getMessage());
}

// JSONとして結果を返す
header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit; 