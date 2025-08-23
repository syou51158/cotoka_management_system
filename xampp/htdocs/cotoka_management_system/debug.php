<?php
// デバッグ表示設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

// PHPの環境情報表示
echo "<h2>PHP環境情報</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

// データベース接続テスト
echo "<h2>データベース接続テスト</h2>";
require_once 'config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    echo "<p style='color:green'>✓ データベース接続成功</p>";
    
    // データベーステーブル一覧表示
    $stmt = $pdo->query("SHOW TABLES");
    echo "<h3>テーブル一覧</h3>";
    echo "<ul>";
    while ($row = $stmt->fetch()) {
        echo "<li>" . $row["Tables_in_" . DB_NAME] . "</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ データベース接続エラー: " . $e->getMessage() . "</p>";
}

// セッション情報表示
echo "<h2>セッション情報</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . "<br>";
if (isset($_SESSION)) {
    echo "<h3>セッション変数</h3>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
}

// 拡張モジュール情報
echo "<h2>読み込まれている拡張モジュール</h2>";
echo "<pre>";
print_r(get_loaded_extensions());
echo "</pre>";
?>