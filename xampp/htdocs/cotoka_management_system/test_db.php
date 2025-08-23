<?php
// 設定ファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';

// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>データベース接続テスト</h1>";

try {
    // データベース接続
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "<p style='color: green;'>データベース接続成功！</p>";
    
    // テーブル一覧を取得
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>データベーステーブル一覧</h2>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>" . htmlspecialchars($table) . "</li>";
    }
    echo "</ul>";
    
    // ユーザーテーブルの内容を確認
    if (in_array('users', $tables)) {
        $stmt = $conn->query("SELECT user_id, name, email, role, status FROM users LIMIT 10");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>ユーザーテーブルのデータ（最大10件）</h2>";
        if (count($users) > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>名前</th><th>メール</th><th>役割</th><th>状態</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['user_id']) . "</td>";
                echo "<td>" . htmlspecialchars($user['name']) . "</td>";
                echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                echo "<td>" . htmlspecialchars($user['status']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>ユーザーテーブルにデータがありません。</p>";
        }
    }
    
    // サロンテーブルの内容を確認
    if (in_array('salons', $tables)) {
        $stmt = $conn->query("SELECT salon_id, name, status FROM salons LIMIT 10");
        $salons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>サロンテーブルのデータ（最大10件）</h2>";
        if (count($salons) > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>名前</th><th>状態</th></tr>";
            foreach ($salons as $salon) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($salon['salon_id']) . "</td>";
                echo "<td>" . htmlspecialchars($salon['name']) . "</td>";
                echo "<td>" . htmlspecialchars($salon['status']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>サロンテーブルにデータがありません。</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>エラーの詳細:</p>";
    echo "<pre>";
    print_r($e);
    echo "</pre>";
}
?> 