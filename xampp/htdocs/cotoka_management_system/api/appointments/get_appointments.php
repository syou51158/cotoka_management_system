<?php
/**
 * 予約データ取得API（Supabase RPC版）
 * 
 * メソッド: GET, POST, HEAD
 * クエリ/ボディ:
 *   - salon_id: サロンID
 *   - start_date: 開始日（YYYY-MM-DD）
 *   - end_date: 終了日（YYYY-MM-DD）
 */

// デバッグモード
define('DEBUG_MODE', true);

// エラーログへデバッグ情報を出力
function debug_log($message, $data = null) {
    if (DEBUG_MODE) {
        $log_message = '[Appointments API] ' . $message;
        if ($data !== null) {
            $log_message .= ': ' . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        error_log($log_message);
    }
}

// HEADリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('HTTP/1.1 200 OK');
    exit;
}

// ヘッダー
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once '../../config/config.php';
require_once '../../includes/functions.php';

session_start();

debug_log('API開始: get_appointments.php method=' . $_SERVER['REQUEST_METHOD']);

try {
    // 入力取得（GET優先、POSTはJSONボディを想定）
    $salon_id = null;
    $start_date = null;
    $end_date = null;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $salon_id = isset($_GET['salon_id']) ? (int)$_GET['salon_id'] : null;
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : (isset($_GET['date_from']) ? $_GET['date_from'] : null);
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : (isset($_GET['date_to']) ? $_GET['date_to'] : null);
    } else {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];
        $salon_id = isset($data['salon_id']) ? (int)$data['salon_id'] : null;
        $start_date = $data['start_date'] ?? null;
        $end_date = $data['end_date'] ?? null;
    }

    if (!$salon_id) {
        throw new Exception('サロンIDが指定されていないか、無効です');
    }

    // デフォルト日付
    $start_date = (isset($start_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) ? $start_date : date('Y-m-01');
    $end_date = (isset($end_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) ? $end_date : date('Y-m-t');

    debug_log('パラメータ', ['salon_id' => $salon_id, 'start_date' => $start_date, 'end_date' => $end_date]);

    // Supabase RPC
    $rpcRes = supabaseRpcCall('appointments_list_with_tasks', [
        'p_salon_id' => $salon_id,
        'p_start_date' => $start_date,
        'p_end_date' => $end_date
    ]);

    if (!$rpcRes['success']) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Supabaseエラー: ' . ($rpcRes['message'] ?? 'unknown')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $appointments = is_array($rpcRes['data']) ? $rpcRes['data'] : [];
    $total = count($appointments);

    echo json_encode([
        'success' => true,
        'appointments' => $appointments,
        'total_count' => $total
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
} 