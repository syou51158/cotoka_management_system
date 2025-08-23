<?php
// セッション開始
session_start();

// 正しいconfigファイルのパスを設定
require_once 'config/config.php';
require_once 'includes/functions.php';

// 設定ファイルが読み込めたかを再確認
if (!defined('DB_HOST')) {
    die("設定ファイルが正しく読み込めませんでした。");
}

// ログイン済みの場合はダッシュボードにリダイレクト
if (isLoggedIn()) {
    redirect('dashboard.php');
} else {
    // 未ログインの場合はログインページにリダイレクト
    redirect('login.php');
}
// この行以降は実行されない
?>