<?php
// 予約削除API（Supabase RPC版）

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

// JSONリクエストを受け取る
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// 予約IDの検証
if (!isset($data['appointment_id']) || !filter_var($data['appointment_id'], FILTER_VALIDATE_INT)) {
    echo json_encode([
        'success' => false,
        'message' => '有効な予約IDが指定されていません。'
    ]);
    exit;
}

$appointment_id = (int)$data['appointment_id'];
$salon_id = isset($_SESSION['salon_id']) ? (int)$_SESSION['salon_id'] : 0;

if (!$salon_id) {
    echo json_encode(['success' => false, 'message' => 'サロンが未選択です。']);
    exit;
}

// Supabase RPC 呼び出し（削除）
$res = supabaseRpcCall('appointment_delete_admin', [
    'p_appointment_id' => $appointment_id,
    'p_salon_id' => $salon_id
]);

if ($res['success']) {
    echo json_encode([
        'success' => true,
        'message' => '予約が削除されました。'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => '削除エラー: ' . ($res['message'] ?? '')
    ]);
}
exit; 