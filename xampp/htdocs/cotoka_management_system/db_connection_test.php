<?php
// データベース接続テスト専用スクリプト

// エラー表示を最大限に有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>データベース接続診断</h1>";

// MySQLサーバーが起動しているか確認
echo "<h2>1. MySQLサーバー状態確認</h2>";
$mysql_running = false;

if (function_exists('exec')) {
    // Windowsでのプロセス確認
    exec('tasklist /FI "IMAGENAME eq mysqld.exe" /FO LIST', $output, $return_var);
    if (isset($output[0]) && strpos($output[0], 'mysqld.exe') !== false) {
        echo "<p style='color:green'>✓ MySQLサーバー(mysqld.exe)は実行中です</p>";
        $mysql_running = true;
    } else {
        echo "<p style='color:red'>✗ MySQLサーバー(mysqld.exe)が実行されていません</p>";
        echo "<p>XAMPPコントロールパネルからMySQLを起動してください</p>";
    }
} else {
    echo "<p style='color:orange'>⚠ サーバー状態を確認できません (exec関数が無効)</p>";
}

// 設定ファイルの内容を確認
echo "<h2>2. データベース設定確認</h2>";

// config.phpファイルの存在確認
$config_file = __DIR__ . '/config/config.php';
if (file_exists($config_file)) {
    echo "<p style='color:green'>✓ 設定ファイルが存在します: {$config_file}</p>";
    
    // 設定ファイルを読み込み
    require_once $config_file;
    
    // 設定値を表示
    echo "<p>データベースホスト: <strong>" . DB_HOST . "</strong></p>";
    echo "<p>データベース名: <strong>" . DB_NAME . "</strong></p>";
    echo "<p>データベースユーザー: <strong>" . DB_USER . "</strong></p>";
    echo "<p>データベースパスワード: <strong>" . (empty(DB_PASS) ? "(空)" : "設定済み") . "</strong></p>";
} else {
    echo "<p style='color:red'>✗ 設定ファイルが見つかりません: {$config_file}</p>";
}

// データベース接続テスト
echo "<h2>3. データベース接続テスト</h2>";

try {
    // PDOを使用して直接接続
    $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    echo "<p>MySQLサーバーへの接続を試みています...</p>";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    echo "<p style='color:green'>✓ MySQLサーバーへの接続に成功しました</p>";
    
    // データベースの存在確認
    $stmt = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "';");
    $database_exists = $stmt->rowCount() > 0;
    
    if ($database_exists) {
        echo "<p style='color:green'>✓ データベース '" . DB_NAME . "' は存在します</p>";
        
        // データベースを選択
        $pdo->exec("USE " . DB_NAME);
        echo "<p style='color:green'>✓ データベース '" . DB_NAME . "' を選択しました</p>";
        
        // テーブル一覧を取得
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($tables) > 0) {
            echo "<p style='color:green'>✓ データベース内に " . count($tables) . " 個のテーブルが存在します</p>";
            echo "<ul>";
            foreach ($tables as $table) {
                echo "<li>" . htmlspecialchars($table) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color:red'>✗ データベース内にテーブルが存在しません</p>";
            echo "<p>データベーススキーマをインポートする必要があります。README.mdの手順に従ってください。</p>";
        }
    } else {
        echo "<p style='color:red'>✗ データベース '" . DB_NAME . "' が存在しません</p>";
        echo "<p>データベースを作成する必要があります。README.mdの手順に従ってください。</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ データベース接続エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // エラーの種類に応じたヒントを表示
    if (strpos($e->getMessage(), "Unknown database") !== false) {
        echo "<p>データベース '" . DB_NAME . "' が存在しません。データベースを作成してください。</p>";
        echo "<p>XAMPPのphpMyAdminで新しいデータベースを作成するか、以下のSQLコマンドを実行してください:</p>";
        echo "<pre>CREATE DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</pre>";
    } elseif (strpos($e->getMessage(), "Access denied") !== false) {
        echo "<p>データベースユーザー名またはパスワードが正しくありません。config.phpの設定を確認してください。</p>";
    } elseif (strpos($e->getMessage(), "Connection refused") !== false) {
        echo "<p>MySQLサーバーに接続できません。XAMPPコントロールパネルでMySQLが起動しているか確認してください。</p>";
    }
}

// 解決策の提案
echo "<h2>4. 問題解決のためのステップ</h2>";
echo "<ol>";
echo "<li>XAMPPコントロールパネルを開き、Apache と MySQL が実行中であることを確認してください。</li>";
echo "<li>データベース '" . DB_NAME . "' が存在しない場合は、phpMyAdminで作成してください。</li>";
echo "<li>データベーススキーマをインポートしてください。<code>database/db_schema.sql</code> ファイルを使用します。</li>";
echo "<li>config.phpのデータベース設定が正しいことを確認してください。</li>";
echo "</ol>";

echo "<p><a href='test_db.php'>標準のデータベーステストページに移動</a></p>";
?>