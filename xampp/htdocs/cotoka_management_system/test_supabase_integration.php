<?php
/**
 * Supabase統合テスト
 * 
 * Database.phpクラスを使用してSupabase接続をテストします
 */

// 設定ファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';

echo "<h1>Supabase統合テスト</h1>";
echo "<hr>";

// 1. 設定値の確認
echo "<h2>1. 設定値の確認</h2>";
echo "<p><strong>SUPABASE_URL:</strong> " . (defined('SUPABASE_URL') ? SUPABASE_URL : '未定義') . "</p>";
echo "<p><strong>SUPABASE_ANON_KEY:</strong> " . (defined('SUPABASE_ANON_KEY') ? '設定済み' : '未定義') . "</p>";
echo "<p><strong>SUPABASE_SERVICE_ROLE_KEY:</strong> " . (defined('SUPABASE_SERVICE_ROLE_KEY') && !empty(SUPABASE_SERVICE_ROLE_KEY) ? '設定済み' : '未定義') . "</p>";
echo "<hr>";

// 2. Database.phpクラスの接続テスト
echo "<h2>2. Database.phpクラスの接続テスト</h2>";
try {
    $db = Database::getInstance();
    echo "<p style='color: green;'>✓ Database.phpクラスのインスタンス作成成功</p>";
    
    // 3. 基本的なクエリテスト
    echo "<h2>3. 基本的なクエリテスト</h2>";
    
    // PostgreSQLバージョン確認
    $version = $db->fetchOne("SELECT version()");
    echo "<p><strong>PostgreSQLバージョン:</strong> " . $version['version'] . "</p>";
    
    // 現在のスキーマ確認
    $schema = $db->fetchOne("SELECT current_schema()");
    echo "<p><strong>現在のスキーマ:</strong> " . $schema['current_schema'] . "</p>";
    
    // search_path確認
    $search_path = $db->fetchOne("SHOW search_path");
    echo "<p><strong>Search Path:</strong> " . $search_path['search_path'] . "</p>";
    
    echo "<hr>";
    
    // 4. cotokaスキーマのテーブル一覧取得
    echo "<h2>4. cotokaスキーマのテーブル一覧</h2>";
    $tables = $db->fetchAll("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'cotoka' 
        ORDER BY table_name
    ");
    
    if (!empty($tables)) {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table['table_name']) . "</li>";
        }
        echo "</ul>";
        echo "<p style='color: green;'>✓ cotokaスキーマに " . count($tables) . " 個のテーブルが見つかりました</p>";
    } else {
        echo "<p style='color: orange;'>⚠ cotokaスキーマにテーブルが見つかりませんでした</p>";
    }
    
    echo "<hr>";
    
    // 5. サンプルテーブルのデータ確認（tenantsテーブル）
    echo "<h2>5. サンプルデータ確認（tenantsテーブル）</h2>";
    try {
        $tenants = $db->fetchAll("SELECT tenant_id, name, created_at FROM tenants LIMIT 5");
        if (!empty($tenants)) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>Tenant ID</th><th>Name</th><th>Created At</th></tr>";
            foreach ($tenants as $tenant) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($tenant['tenant_id']) . "</td>";
                echo "<td>" . htmlspecialchars($tenant['name']) . "</td>";
                echo "<td>" . htmlspecialchars($tenant['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "<p style='color: green;'>✓ tenantsテーブルからデータを正常に取得できました</p>";
        } else {
            echo "<p style='color: orange;'>⚠ tenantsテーブルにデータがありません</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ tenantsテーブルの確認でエラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<hr>";
    echo "<h2>テスト結果</h2>";
    echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✓ Supabase統合テストが正常に完了しました！</p>";
    echo "<p>Database.phpクラスを使用してSupabaseに正常に接続し、データの取得ができることを確認しました。</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database.phpクラスの接続エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<hr>";
    echo "<h2>エラー詳細</h2>";
    echo "<p>以下の点を確認してください:</p>";
    echo "<ul>";
    echo "<li>SUPABASE_SERVICE_ROLE_KEYが正しく設定されているか</li>";
    echo "<li>Supabaseプロジェクトが稼働しているか</li>";
    echo "<li>ネットワーク接続に問題がないか</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='dashboard.php'>ダッシュボードに戻る</a></p>";
?>