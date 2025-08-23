<?php
/**
 * アプリケーション初期化ファイル
 * 
 * システムの基本設定と共通のインクルードを行います
 */

// 設定ファイルの読み込み
require_once 'config/config.php';

// 共通の関数の読み込み
require_once 'includes/functions.php';

// 必要なクラスの読み込み
require_once 'classes/Database.php';
require_once 'classes/User.php';
require_once 'classes/Salon.php';
require_once 'classes/Tenant.php';

// オートロード設定
spl_autoload_register(function($class) {
    $file = 'classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// エラーハンドラの設定
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        // エラーレポーティング設定に合致しない場合は処理しない
        return;
    }
    
    // エラーログに記録
    logError($errstr, [
        'file' => $errfile,
        'line' => $errline,
        'type' => $errno
    ]);
    
    // プロダクション環境ではエラーは表示しない
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        if ($errno == E_USER_ERROR) {
            header('Location: ' . BASE_URL . '/error.php');
            exit;
        }
    }
    
    return true;
});

// 例外ハンドラの設定
set_exception_handler(function($exception) {
    // 例外をログに記録
    logError($exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    // プロダクション環境では例外詳細は表示しない
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        header('Location: ' . BASE_URL . '/error.php');
        exit;
    } else {
        // 開発環境では例外を表示
        echo '<h1>システムエラー</h1>';
        echo '<p>' . htmlspecialchars($exception->getMessage()) . '</p>';
        echo '<p>File: ' . htmlspecialchars($exception->getFile()) . ' Line: ' . $exception->getLine() . '</p>';
        echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
    }
}); 