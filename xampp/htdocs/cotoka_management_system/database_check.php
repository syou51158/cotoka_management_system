<?php
// エラー表示を最大限に有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// エラーログの設定
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error.log');

echo "<h1>Supabaseデータベース診断ツール</h1>";

// 設定ファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';

// PHPバージョン情報
echo "<h2>PHPバージョン情報</h2>";
echo "<p>PHP バージョン: " . phpversion() . "</p>";
echo "<p>PDO サポート: " . (extension_loaded('pdo') ? '有効' : '無効') . "</p>";
echo "<p>PDO PostgreSQL サポート: " . (extension_loaded('pdo_pgsql') ? '有効' : '無効') . "</p>";

// Supabase接続情報の表示
echo "<h2>Supabaseデータベース設定</h2>";
echo "<ul>";
echo "<li>Supabase URL: " . (defined('SUPABASE_URL') ? SUPABASE_URL : '未設定') . "</li>";
echo "<li>Supabase Service Role Key: " . (defined('SUPABASE_SERVICE_ROLE_KEY') ? '設定済み' : '未設定') . "</li>";
echo "<li>Supabase Anon Key: " . (defined('SUPABASE_ANON_KEY') ? '設定済み' : '未設定') . "</li>";
echo "</ul>";

// Supabaseデータベース接続テスト
echo "<h2>Supabase接続テスト</h2>";
try {
    $database = new Database();
    $pdo = $database->getConnection();
    echo "<p style='color:green'>✓ Supabaseデータベース接続に成功しました！</p>";
    
    // 現在のスキーマ確認
    echo "<h3>現在のスキーマ確認</h3>";
    $stmt = $pdo->query("SELECT current_schema() as schema_name");
    $result = $stmt->fetch();
    echo "<p>現在のスキーマ: {$result['schema_name']}</p>";
    
    // 利用可能なスキーマ確認
    $stmt = $pdo->query("SELECT schema_name FROM information_schema.schemata WHERE schema_name IN ('cotoka', 'public')");
    $schemas = $stmt->fetchAll();
    echo "<p>利用可能なスキーマ: " . implode(', ', array_column($schemas, 'schema_name')) . "</p>";
    
    // 必要なテーブルの存在確認
    $required_tables = ['users', 'roles', 'tenants', 'salons', 'remember_tokens', 'appointments', 'customers', 'services', 'service_categories', 'staff'];
    echo "<h3>必要なテーブルの確認</h3>";
    echo "<ul>";
    foreach ($required_tables as $table) {
        $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema IN ('cotoka', 'public') AND table_name = '{$table}'");
        if ($stmt->rowCount() > 0) {
            echo "<li style='color:green'>✓ {$table}テーブルが存在します</li>";
            
            // テーブルのレコード数を確認
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM cotoka.{$table}");
            $count = $stmt->fetch()['count'];
            echo "<ul><li>レコード数: {$count}</li></ul>";
        } else {
            echo "<li style='color:red'>✗ {$table}テーブルが存在しません</li>";
        }
    }
    echo "</ul>";
    
    // usersテーブルの構造確認（存在する場合）
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema IN ('cotoka', 'public') AND table_name = 'users'");
    if ($stmt->rowCount() > 0) {
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
    }
    
    // rolesテーブルの構造確認（存在する場合）
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema IN ('cotoka', 'public') AND table_name = 'roles'");
    if ($stmt->rowCount() > 0) {
        echo "<h3>rolesテーブルの構造</h3>";
        $stmt = $pdo->query("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_schema IN ('cotoka', 'public') AND table_name = 'roles' ORDER BY ordinal_position");
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
    }
    
    // Supabaseセットアップ方法を表示
    echo "<h3>Supabaseデータベースセットアップ方法</h3>";
    echo "<p>Supabaseデータベースが正しく設定されていることを確認してください：</p>";
    echo "<ol>";
    echo "<li>config/config.phpでSupabase設定が正しく設定されている</li>";
    echo "<li>SUPABASE_URL、SUPABASE_SERVICE_ROLE_KEY、SUPABASE_ANON_KEYが設定されている</li>";
    echo "<li>Supabaseプロジェクトでcotokaスキーマが作成されている</li>";
    echo "<li>必要なテーブルがcotokaスキーマに作成されている</li>";
    echo "</ol>";
    
    // Supabaseテーブル作成スクリプトの実行方法
    echo "<h3>Supabaseテーブル作成</h3>";
    echo "<p>テーブルが存在しない場合は、以下のリンクからSupabaseテーブル作成スクリプトを実行してください：</p>";
    echo "<p><a href='create_supabase_tables.php' class='btn btn-primary'>Supabaseテーブル作成を実行</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Supabaseデータベース接続エラー: " . $e->getMessage() . "</p>";
    echo "<h3>考えられる原因:</h3>";
    echo "<ul>";
    echo "<li>SUPABASE_URLが正しく設定されていない</li>";
    echo "<li>SUPABASE_SERVICE_ROLE_KEYが正しく設定されていない</li>";
    echo "<li>Supabaseプロジェクトがアクティブでない</li>";
    echo "<li>ネットワーク接続に問題がある</li>";
    echo "<li>PDO PostgreSQLドライバーがインストールされていない</li>";
    echo "</ul>";
    
    // Supabase設定確認方法を表示
    echo "<h3>Supabase設定確認方法</h3>";
    echo "<p>Supabase接続に問題がある場合は、以下を確認してください：</p>";
    echo "<ol>";
    echo "<li>config/config.phpでSupabase設定を確認</li>";
    echo "<li>SupabaseダッシュボードでプロジェクトURLとAPIキーを確認</li>";
    echo "<li>PostgreSQLドライバーがインストールされているか確認</li>";
    echo "<li>ネットワーク接続を確認</li>";
    echo "</ol>";
}
?>

<p><a href="login.php">ログインページに戻る</a></p>