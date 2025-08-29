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

// データベース接続（Supabase REST）
$db = Database::getInstance();

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
    $tenantId = getCurrentTenantId();
    
    // サロンIDのバリデーション
    if ($salonId <= 0) {
        throw new Exception('サロンIDが指定されていないか、無効です。');
    }
    
    // 日付のバリデーション
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('日付の形式が無効です。YYYY-MM-DD形式で指定してください。');
    }

    // Supabase（Databaseクラス）で予約データ取得（関連を埋め込み）
    $appointmentRows = $db->fetchAll(
        'appointments',
        [
            'salon_id' => $salonId,
            'tenant_id' => $tenantId,
            'appointment_date' => $date,
        ],
        'appointment_id,customer_id,staff_id,service_id,appointment_date,start_time,end_time,status,notes,appointment_type,task_description,created_at,updated_at,customers(first_name,last_name),staff(first_name,last_name),services(name)',
        [
            'order' => 'start_time.asc',
            'limit' => 2000,
        ]
    );

    $appointments = array_map(function ($r) {
        $customer_last = isset($r['customers']['last_name']) ? $r['customers']['last_name'] : '';
        $customer_first = isset($r['customers']['first_name']) ? $r['customers']['first_name'] : '';
        $staff_last = isset($r['staff']['last_name']) ? $r['staff']['last_name'] : '';
        $staff_first = isset($r['staff']['first_name']) ? $r['staff']['first_name'] : '';
        $service_name = isset($r['services']['name']) ? $r['services']['name'] : null;
        return [
            'id' => $r['appointment_id'] ?? null,
            'item_type' => 'appointment',
            'customer_id' => $r['customer_id'] ?? null,
            'customer_name' => trim($customer_last . ' ' . $customer_first),
            'staff_id' => $r['staff_id'] ?? null,
            'staff_name' => trim($staff_last . ' ' . $staff_first),
            'service_id' => $r['service_id'] ?? null,
            'service_name' => $service_name,
            'event_date' => $r['appointment_date'] ?? null,
            'start_time' => $r['start_time'] ?? null,
            'end_time' => $r['end_time'] ?? null,
            'status' => $r['status'] ?? null,
            'notes' => $r['notes'] ?? null,
            'appointment_type' => $r['appointment_type'] ?? 'customer',
            'task_description' => $r['task_description'] ?? null,
            'created_at' => $r['created_at'] ?? null,
            'updated_at' => $r['updated_at'] ?? null,
        ];
    }, $appointmentRows);

    // Supabase（Databaseクラス）で業務（タスク）データ取得（関連を埋め込み）
    $taskRows = $db->fetchAll(
        'staff_tasks',
        [
            'salon_id' => $salonId,
            'tenant_id' => $tenantId,
            'task_date' => $date,
        ],
        'task_id,staff_id,task_date,start_time,end_time,status,task_description,created_at,updated_at,staff(first_name,last_name)',
        [
            'order' => 'start_time.asc',
            'limit' => 2000,
        ]
    );

    $tasks = array_map(function ($r) {
        $staff_last = isset($r['staff']['last_name']) ? $r['staff']['last_name'] : '';
        $staff_first = isset($r['staff']['first_name']) ? $r['staff']['first_name'] : '';
        return [
            'id' => $r['task_id'] ?? null,
            'item_type' => 'task',
            'customer_id' => 0,
            'customer_name' => $r['task_description'] ?? '',
            'staff_id' => $r['staff_id'] ?? null,
            'staff_name' => trim($staff_last . ' ' . $staff_first),
            'service_id' => 0,
            'service_name' => '業務',
            'event_date' => $r['task_date'] ?? null,
            'start_time' => $r['start_time'] ?? null,
            'end_time' => $r['end_time'] ?? null,
            'status' => $r['status'] ?? null,
            'notes' => null,
            'appointment_type' => 'task',
            'task_description' => $r['task_description'] ?? null,
            'created_at' => $r['created_at'] ?? null,
            'updated_at' => $r['updated_at'] ?? null,
        ];
    }, $taskRows);

    // 予約と業務のデータを統合
    $allAppointments = array_merge($appointments, $tasks);

    // 開始時間でソート
    usort($allAppointments, function ($a, $b) {
        return strtotime($a['start_time']) <=> strtotime($b['start_time']);
    });

    // レスポンスの設定
    $response['success'] = true;
    $response['message'] = count($allAppointments) . '件のデータを取得しました。';
    $response['data'] = $allAppointments;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('予約・業務データ取得エラー: ' . $e->getMessage());
}

// JSONとして結果を返す
header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;