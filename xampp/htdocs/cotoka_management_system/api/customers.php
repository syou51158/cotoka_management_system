<?php
/**
 * 顧客リスト取得API（Supabase RPC版）
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

try {
    $rpc = supabaseRpcCall('customers_list_by_salon', ['p_salon_id' => $salon_id]);
    if (!$rpc['success']) {
        http_response_code(500);
        echo json_encode(['error' => 'Supabaseエラー: ' . ($rpc['message'] ?? '')]);
        exit;
    }
    $customers = is_array($rpc['data']) ? $rpc['data'] : [];
    echo json_encode($customers);
} catch (Exception $e) {
    error_log('顧客リスト取得エラー: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'サーバーエラーが発生しました']);
} 