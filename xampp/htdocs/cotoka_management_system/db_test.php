<?php
// エラー表示を最大限に有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// エラーログの設定
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error.log');

echo "<h1>Supabaseデータベース接続テスト</h1>";

// 設定ファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';

// Supabase設定値のデバッグ
echo '<h2>Supabase設定値検証</h2>';
echo '<p>SUPABASE_URL: ' . (defined('SUPABASE_URL') ? SUPABASE_URL : '未定義') . '</p>';
echo '<p>SUPABASE_SERVICE_ROLE_KEY: ' . (defined('SUPABASE_SERVICE_ROLE_KEY') ? '設定済み' : '未定義') . '</p>';
echo '<p>SUPABASE_ANON_KEY: ' . (defined('SUPABASE_ANON_KEY') ? '設定済み' : '未定義') . '</p>';

// Supabaseデータベース接続情報の表示
echo "<h2>Supabaseデータベース設定</h2>";
echo "<ul>";
echo "<li>Supabase URL: " . (defined('SUPABASE_URL') ? SUPABASE_URL : '未設定') . "</li>";
echo "<li>Service Role Key: " . (defined('SUPABASE_SERVICE_ROLE_KEY') ? '設定済み' : '未設定') . "</li>";
echo "<li>Anonymous Key: " . (defined('SUPABASE_ANON_KEY') ? '設定済み' : '未設定') . "</li>";
echo "</ul>";

// Supabaseデータベース接続テスト
echo "<h2>Supabase接続テスト</h2>";
try {
    $database = new Database();
    $pdo = $database->getConnection();
    echo "<p style='color:green'>✓ Supabaseデータベース接続に成功しました！</p>";
    
    // 現在のスキーマ確認
    $stmt = $pdo->query("SELECT current_schema() as schema_name");
    $result = $stmt->fetch();
    echo "<p>現在のスキーマ: {$result['schema_name']}</p>";
    
    // usersテーブルの存在確認
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema IN ('cotoka', 'public') AND table_name = 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green'>✓ usersテーブルが存在します</p>";
        
        // usersテーブルの構造確認
        echo "<h3>usersテーブルの構造</h3>";
        $stmt = $pdo->query("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_schema IN ('cotoka', 'public') AND table_name = 'users' ORDER BY ordinal_position");
        echo "<table border='1'>";
        echo "<tr><th>カラム名</th><th>データ型</th><th>NULL許可</th><th>デフォルト値</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['column_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['data_type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['is_nullable']) . "</td>";
            echo "<td>" . htmlspecialchars($row['column_default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // ユーザーデータの確認
        echo "<h3>登録ユーザー数</h3>";
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM cotoka.users");
        $count = $stmt->fetch()['count'];
        echo "<p>登録ユーザー数: {$count}</p>";
        
        if ($count > 0) {
            echo "<h3>ユーザー一覧（最大5件）</h3>";
            $stmt = $pdo->query("SELECT id, user_id, email, name, status FROM cotoka.users LIMIT 5");
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>ユーザーID</th><th>メール</th><th>名前</th><th>ステータス</th></tr>";
            while ($row = $stmt->fetch()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['user_id'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($row['email'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($row['name'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($row['status'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color:red'>✗ usersテーブルが存在しません</p>";
        echo "<p>Supabaseテーブルのセットアップが必要です。create_supabase_tables.phpを実行してください。</p>";
    }
    
    // rolesテーブルの存在確認
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema IN ('cotoka', 'public') AND table_name = 'roles'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green'>✓ rolesテーブルが存在します</p>";
    } else {
        echo "<p style='color:red'>✗ rolesテーブルが存在しません</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Supabaseデータベース接続に失敗しました</p>";
    echo "<p>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    echo "<h3>トラブルシューティング</h3>";
    echo "<ul>";
    echo "<li>config.phpのSupabase URLとキーが正しいことを確認してください</li>";
    echo "<li>Supabaseプロジェクトがアクティブであることを確認してください</li>";
    echo "<li>ネットワーク接続を確認してください</li>";
    echo "<li>PDO PostgreSQLドライバーがインストールされていることを確認してください</li>";
    echo "<li>create_supabase_tables.phpを実行してテーブルを作成してください</li>";
    echo "</ul>";
}
?>

<p><a href="login.php">ログインページに戻る</a></p>