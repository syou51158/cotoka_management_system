<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'today' => 0,
    'week' => 0,
    'month' => 0,
    'pending' => 0
];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = '認証エラー：ログインしていません';
    echo json_encode($response);
    exit;
}

$salon_id = isset($_GET['salon_id']) ? intval($_GET['salon_id']) : (isset($_SESSION['salon_id']) ? (int)$_SESSION['salon_id'] : 0);
if (!$salon_id) {
    $response['message'] = 'サロンIDが指定されていません';
    echo json_encode($response);
    exit;
}

try {
    $rpc = supabaseRpcCall('appointments_stats', ['p_salon_id' => $salon_id]);
    if (!$rpc['success']) {
        $response['message'] = 'Supabaseエラー: ' . ($rpc['message'] ?? '');
    } else {
        $row = is_array($rpc['data']) ? ($rpc['data'][0] ?? null) : null;
        if ($row) {
            $response['today'] = (int)($row['today'] ?? 0);
            $response['week'] = (int)($row['week'] ?? 0);
            $response['month'] = (int)($row['month'] ?? 0);
            $response['pending'] = (int)($row['pending'] ?? 0);
            $response['success'] = true;
            $response['message'] = '統計情報の取得に成功しました';
        } else {
            $response['message'] = '統計データがありません';
        }
    }
} catch (Exception $e) {
    $response['message'] = 'サーバーエラー: ' . $e->getMessage();
}

echo json_encode($response); 