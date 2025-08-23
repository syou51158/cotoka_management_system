<?php
// エラー表示設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>usersテーブル修正ツール</h1>";

// 設定ファイルを読み込み
require_once 'config.php';

try {
    // データベース接続
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    echo "<p style='color:green'>✓ データベースに接続しました</p>";
    
    // usersテーブルの構造を確認
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>現在のusersテーブル構造</h2>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // role_idカラムが存在するか確認
    $hasRoleIdColumn = in_array('role_id', $columns);
    
    if (!$hasRoleIdColumn) {
        // role_idカラムを追加
        $pdo->exec("ALTER TABLE users ADD COLUMN role_id INT DEFAULT NULL AFTER last_login");
        $pdo->exec("ALTER TABLE users ADD CONSTRAINT users_ibfk_2 FOREIGN KEY (role_id) REFERENCES roles(role_id)");
        
        echo "<p style='color:green'>✓ usersテーブルにrole_idカラムを追加しました</p>";
        
        // 修正後のテーブル構造を表示
        $stmt = $pdo->query("DESCRIBE users");
        $updatedColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h2>修正後のusersテーブル構造</h2>";
        echo "<pre>";
        print_r($updatedColumns);
        echo "</pre>";
    } else {
        echo "<p style='color:blue'>ℹ role_idカラムはすでに存在しています</p>";
    }
    
    echo "<p><a href='setup_database.php'>データベースセットアップツールに戻る</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ エラー: " . $e->getMessage() . "</p>";
}
?> 