<?php
// 徹底的なデバッグ情報
error_log("goto_categories.php 実行開始: " . date('Y-m-d H:i:s'), 3, "logs/app_debug.log");

// セッション開始（もし開始されていない場合）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// セッション情報をログに記録
$session_info = "セッション情報: ";
foreach ($_SESSION as $key => $value) {
    if (is_array($value)) {
        $session_info .= $key . "=[配列], ";
    } else {
        $session_info .= $key . "=" . $value . ", ";
    }
}
error_log($session_info, 3, "logs/app_debug.log");

// リダイレクトする前のリクエスト情報を記録
error_log("リクエスト情報: " . $_SERVER['REQUEST_URI'], 3, "logs/app_debug.log");

// ヘッダー出力前に出力バッファをクリア
ob_clean();

// 明示的なリダイレクト
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Location: service_categories.php");
exit; 