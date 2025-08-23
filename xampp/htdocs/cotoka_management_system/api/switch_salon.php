<?php
/**
 * サロン切り替えAPI
 * 
 * ユーザーのアクセス可能なサロン間を切り替えるためのAPI
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';

// レスポンス用のヘッダー設定
header('Content-Type: application/json; charset=UTF-8');

// JSONレスポンスを出力する関数
function outputJSON($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// エラーレスポンス
function errorResponse($message, $code = 400)
{
    http_response_code($code);
    outputJSON([
        'success' => false,
        'message' => $message
    ]);
}

// 認証チェック
if (!isLoggedIn()) {
    errorResponse('ログインが必要です', 401);
}

// サロンIDが提供されているか確認
if (!isset($_GET['salon_id']) || empty($_GET['salon_id'])) {
    errorResponse('サロンIDが指定されていません');
}

$salon_id = intval($_GET['salon_id']);
$user_id = $_SESSION['user_id'];

// Supabaseでアクセス権とサロン存在を確認
// 1) アクセス可能サロン一覧から検証
$rpcSalons = isset($_SESSION['user_unique_id'])
    ? supabaseRpcCall('user_accessible_salons_by_uid', ['p_user_uid' => (string)$_SESSION['user_unique_id']])
    : supabaseRpcCall('user_accessible_salons', ['p_user_id' => (int)$user_id]);
if (!$rpcSalons['success']) {
    errorResponse('アクセス権確認に失敗しました: ' . ($rpcSalons['message'] ?? 'RPCエラー'));
}
$accessible = $rpcSalons['data'] ?? [];
$ids = array_column($accessible, 'salon_id');
if (!in_array($salon_id, $ids)) {
    errorResponse('このサロンにアクセスする権限がありません', 403);
}

// 2) サロン情報取得
$rpcSalon = supabaseRpcCall('salon_get', ['p_salon_id' => (int)$salon_id]);
if (!$rpcSalon['success'] || empty($rpcSalon['data'])) {
    errorResponse('指定されたサロンが見つかりません', 404);
}
$salonRow = is_array($rpcSalon['data']) ? ($rpcSalon['data'][0] ?? null) : null;
if (!$salonRow) {
    errorResponse('指定されたサロンが見つかりません', 404);
}

// 現在のサロンを更新
setCurrentSalon($salon_id);

// 成功レスポンス
outputJSON([
    'success' => true,
    'message' => 'サロンを切り替えました',
    'salon' => [
        'id' => (int)$salon_id,
        'name' => ($salonRow['name'] ?? $salonRow['salon_name'] ?? '')
    ]
]);