<?php
/**
 * スタッフリスト取得API（サロン配属優先）
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => '認証エラー: ログインしていません']);
    exit;
}

$salon_id = isset($_SESSION['salon_id']) ? (int)$_SESSION['salon_id'] : 0;
if (!$salon_id) {
    http_response_code(400);
    echo json_encode(['error' => 'サロンIDが未設定です']);
    exit;
}

$tenant_id = getCurrentTenantId();
if (!$tenant_id) {
    http_response_code(400);
    echo json_encode(['error' => 'テナントIDが未設定です']);
    exit;
}

try {
    $sql = "SELECT s.staff_id, s.first_name, s.last_name, s.email, s.phone, s.position, s.status\n              FROM cotoka.staff s\n              JOIN cotoka.staff_salons ss\n                ON ss.staff_id = s.staff_id\n               AND ss.tenant_id = s.tenant_id\n             WHERE ss.salon_id = $salon_id\n               AND s.tenant_id = $tenant_id\n          ORDER BY s.staff_id DESC";
    $rpc = supabaseRpcCall('execute_sql', ['query' => $sql]);
    if (!$rpc['success']) {
        // フォールバック: テナント全体
        $sql_fb = "SELECT staff_id, first_name, last_name, email, phone, position, status\n                     FROM cotoka.staff\n                    WHERE tenant_id = $tenant_id\n                 ORDER BY staff_id DESC";
        $rpc = supabaseRpcCall('execute_sql', ['query' => $sql_fb]);
    }

    if (!$rpc['success']) {
        http_response_code(500);
        echo json_encode(['error' => 'Supabaseエラー: ' . ($rpc['message'] ?? '')]);
        exit;
    }

    $staff = is_array($rpc['data']) ? $rpc['data'] : [];
    echo json_encode($staff);
} catch (Exception $e) {
    error_log('スタッフリスト取得エラー: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'サーバーエラーが発生しました']);
}