<?php
// 必要なファイルを読み込み
require_once '../config/config.php';

// エラー表示を抑制（本番環境用）
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 0);

// セッション開始（セッションがまだ開始されていない場合のみ）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// デバッグログを保存
function debug_log($message) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log($message);
        file_put_contents(__DIR__ . '/debug.txt', date('Y-m-d H:i:s') . ' - ' . $message . "\n", FILE_APPEND);
    }
}

// デバッグモード設定
define('DEBUG_MODE', false);

// リクエストパラメータの検証
if (!isset($_POST['selected_date']) || !isset($_POST['selected_staff_id']) || !isset($_POST['selected_time'])) {
    echo json_encode(['success' => false, 'message' => '必要なパラメータが不足しています。']);
    exit;
}

// 値を取得
$selected_date = $_POST['selected_date'];
$selected_staff_id = $_POST['selected_staff_id'];
$selected_time = $_POST['selected_time'];

// 値の検証（基本的な検証のみ）
if (empty($selected_date) || empty($selected_time)) {
    echo json_encode(['success' => false, 'message' => '日付と時間は必須です。']);
    exit;
}

// 日付形式チェック
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    echo json_encode(['success' => false, 'message' => '日付の形式が正しくありません。']);
    exit;
}

// 時間形式チェック
if (!preg_match('/^\d{2}:\d{2}$/', $selected_time)) {
    echo json_encode(['success' => false, 'message' => '時間の形式が正しくありません。']);
    exit;
}

// セッションに保存
$_SESSION['booking_selected_date'] = $selected_date;
$_SESSION['booking_selected_time'] = $selected_time;
$_SESSION['booking_selected_staff_id'] = $selected_staff_id;

// 選択したサービス情報を確認（既にセッションに保存されている前提）
if (!isset($_SESSION['booking_services']) || empty($_SESSION['booking_services'])) {
    echo json_encode(['success' => false, 'message' => 'サービス情報が見つかりません。サービス選択ページからやり直してください。']);
    exit;
}

// 予約時間の終了時刻を計算
$services = $_SESSION['booking_services'];
$total_duration = array_sum(array_column($services, 'duration'));

$start_time = new DateTime($selected_time);
$end_time = clone $start_time;
$end_time->modify("+{$total_duration} minutes");

$_SESSION['booking_selected_end_time'] = $end_time->format('H:i');

// デバッグ情報
debug_log('日時選択情報をセッションに保存: ' . 
          '日付=' . $selected_date . 
          ', 時間=' . $selected_time . 
          ', 終了時間=' . $_SESSION['booking_selected_end_time'] . 
          ', スタッフID=' . $selected_staff_id);

// 成功レスポンスを返す
echo json_encode([
    'success' => true, 
    'message' => '予約情報を保存しました。',
    'data' => [
        'date' => $selected_date,
        'time' => $selected_time,
        'end_time' => $_SESSION['booking_selected_end_time'],
        'staff_id' => $selected_staff_id,
        'duration' => $total_duration
    ]
]); 