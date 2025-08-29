<?php
/**
 * Supabase接続テスト
 * 
 * 新しいSupabase接続が正常に動作するかテストします
 */

// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Supabase接続テスト</h1>";

// 設定ファイルとDatabaseクラスを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';

echo "<h2>設定情報</h2>";
echo "<p>Supabase URL: " . SUPABASE_URL . "</p>";
echo "<p>Anonymous Key: " . substr(SUPABASE_ANON_KEY, 0, 20) . "...（省略）</p>";

try {
    echo "<h2>データベース接続テスト</h2>";
    
    // データベース接続
    $db = new Database();
    echo "<p style='color:green'>✓ Supabaseデータベースに正常に接続しました</p>";
    
    // スキーマ確認
    echo "<h3>スキーマ確認</h3>";
    $schemas = $db->fetchAll("SELECT schema_name FROM information_schema.schemata WHERE schema_name IN ('cotoka', 'public')");
    foreach ($schemas as $schema) {
        echo "<p style='color:green'>✓ スキーマ '{$schema['schema_name']}' が存在します</p>";
    }
    
    // テーブル一覧取得
    echo "<h3>cotokaスキーマのテーブル一覧</h3>";
    $tables = $db->fetchAll("SELECT table_name FROM information_schema.tables WHERE table_schema = 'cotoka' ORDER BY table_name");
    
    if (count($tables) > 0) {
        echo "<p style='color:green'>✓ " . count($tables) . "個のテーブルが見つかりました</p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table['table_name']) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:orange'>⚠ cotokaスキーマにテーブルが見つかりません</p>";
    }
    
    // 基本的なクエリテスト
    echo "<h3>基本クエリテスト</h3>";
    
    // rolesテーブルのデータ確認
    $roles = $db->fetchAll("SELECT * FROM roles LIMIT 5");
    echo "<p style='color:green'>✓ rolesテーブルから " . count($roles) . " 件のデータを取得しました</p>";
    
    // tenantsテーブルのデータ確認
    $tenants = $db->fetchAll("SELECT * FROM tenants LIMIT 5");
    echo "<p style='color:green'>✓ tenantsテーブルから " . count($tenants) . " 件のデータを取得しました</p>";
    
    echo "<h2 style='color:green'>✓ 全てのテストが正常に完了しました</h2>";
    echo "<p>Supabaseへの移行が正常に完了しています。</p>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red'>✗ エラーが発生しました</h2>";
    echo "<p style='color:red'>エラー内容: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    echo "<h3>トラブルシューティング</h3>";
    echo "<ul>";
    echo "<li>config.phpのSupabase設定を確認してください</li>";
    echo "<li>Supabaseプロジェクトが正常に動作していることを確認してください</li>";
    echo "<li>PostgreSQL PDOドライバーがインストールされていることを確認してください</li>";
    echo "</ul>";
}
?>