<?php
/**
 * 予約管理ページ（レガシー）
 * 
 * このファイルは互換性のために残されています。
 * 新しい予約管理機能は appointment_manager.php に移行されました。
 * 
 * このファイルへのアクセスは自動的に appointment_manager.php にリダイレクトされます。
 */

// セッションを開始
session_start();

// クエリパラメータを保持してリダイレクト
$query = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: appointment_manager.php' . $query);
exit;
?>
