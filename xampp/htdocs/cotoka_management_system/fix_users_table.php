<?php
// エラー表示設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Supabase usersテーブル修正ツール</h1>";

// 設定ファイルとDatabaseクラスを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';

try {
    // Supabaseデータベース接続
    $database = new Database();
    $pdo = $database->getConnection();
    echo "<p style='color:green'>✓ Supabaseデータベースに接続しました</p>";
    
    // usersテーブルの構造を確認
    $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema IN ('cotoka', 'public') AND table_name = 'users'");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>現在のusersテーブル構造</h2>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // role_idカラムが存在するか確認
    $hasRoleIdColumn = in_array('role_id', $columns);
    
    if (!$hasRoleIdColumn) {
        // role_idカラムを追加（PostgreSQL構文）
        $pdo->exec("ALTER TABLE cotoka.users ADD COLUMN role_id INTEGER DEFAULT NULL");
        $pdo->exec("ALTER TABLE cotoka.users ADD CONSTRAINT users_role_id_fkey FOREIGN KEY (role_id) REFERENCES cotoka.roles(role_id)");
        
        echo "<p style='color:green'>✓ usersテーブルにrole_idカラムを追加しました</p>";
        
        // 修正後のテーブル構造を表示
        $stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema IN ('cotoka', 'public') AND table_name = 'users'");
        $updatedColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h2>修正後のusersテーブル構造</h2>";
        echo "<pre>";
        print_r($updatedColumns);
        echo "</pre>";
    } else {
        echo "<p style='color:blue'>ℹ role_idカラムはすでに存在しています</p>";
    }
    
    echo "<p><a href='database_check.php'>データベース診断ツールに戻る</a></p>";
    echo "<p><a href='index.php'>メインページに戻る</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ エラー: " . $e->getMessage() . "</p>";
}
?>