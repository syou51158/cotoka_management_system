<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';

// セッション開始（未開始の場合に備える）
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Userクラス経由でRemember Meトークンを含め完全ログアウト
try {
    $db = new Database();
    $user = new User($db);
    $user->logout();
} catch (Exception $e) {
    // フォールバック: セッションとクッキーを最低限クリア
    $_SESSION = [];
    if (isset($_COOKIE['remember_token'])) {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('remember_token', '', time() - 86400, '/', '', $secure, true);
    }
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// ログインページへ
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/login.php');
exit;