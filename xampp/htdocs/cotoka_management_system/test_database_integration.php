<?php
/**
 * Database Integration Test
 * Tests various database connection methods for Supabase
 */

// Load configuration
require_once 'config/config.php';

echo "<!DOCTYPE html>";
echo "<html lang='ja'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>データベース統合テスト</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; }";
echo "h1, h2 { color: #333; }";
echo ".success { color: green; }";
echo ".error { color: red; }";
echo ".info { color: blue; }";
echo "pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<h1>データベース統合テスト</h1>";

// Test 1: Check PHP extensions
echo "<h2>1. PHP拡張機能チェック</h2>";
echo "<p><strong>PDO:</strong> " . (extension_loaded('pdo') ? "<span class='success'>✓ 有効</span>" : "<span class='error'>✗ 無効</span>") . "</p>";
echo "<p><strong>PDO PostgreSQL:</strong> " . (extension_loaded('pdo_pgsql') ? "<span class='success'>✓ 有効</span>" : "<span class='error'>✗ 無効</span>") . "</p>";
echo "<p><strong>cURL:</strong> " . (extension_loaded('curl') ? "<span class='success'>✓ 有効</span>" : "<span class='error'>✗ 無効</span>") . "</p>";

// Test 2: Configuration check
echo "<h2>2. 設定確認</h2>";
echo "<p><strong>Supabase URL:</strong> " . (defined('SUPABASE_URL') ? SUPABASE_URL : "<span class='error'>未定義</span>") . "</p>";
echo "<p><strong>Supabase Project ID:</strong> " . (defined('SUPABASE_PROJECT_ID') ? SUPABASE_PROJECT_ID : "<span class='error'>未定義</span>") . "</p>";
echo "<p><strong>Anonymous Key:</strong> " . (defined('SUPABASE_ANON_KEY') ? "設定済み" : "<span class='error'>未設定</span>") . "</p>";

// Test 3: Try PDO connection (if available)
echo "<h2>3. PDO接続テスト</h2>";
if (extension_loaded('pdo_pgsql')) {
    try {
        require_once 'classes/Database.php';
        $db = Database::getInstance();
        echo "<p class='success'>✓ Database.phpクラスの初期化に成功しました</p>";
        
        // Try a simple query
        $result = $db->fetchOne("SELECT 1 as test");
        if ($result) {
            echo "<p class='success'>✓ データベースクエリテストに成功しました</p>";
        } else {
            echo "<p class='error'>✗ データベースクエリテストに失敗しました</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ PDO接続エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p class='error'>✗ PostgreSQL PDOドライバーが利用できません</p>";
}

// Test 4: Try SupabaseClient (HTTP API)
echo "<h2>4. Supabase HTTP API接続テスト</h2>";
if (file_exists('classes/SupabaseClient.php')) {
    try {
        require_once 'classes/SupabaseClient.php';
        $client = new SupabaseClient();
        $result = $client->testConnection();
        
        if ($result['success']) {
            echo "<p class='success'>✓ " . htmlspecialchars($result['message']) . "</p>";
        } else {
            echo "<p class='error'>✗ " . htmlspecialchars($result['message']) . "</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ SupabaseClient エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p class='error'>✗ SupabaseClient.phpが見つかりません</p>";
}

// Test 5: System recommendations
echo "<h2>5. システム推奨事項</h2>";
echo "<ul>";

if (!extension_loaded('pdo_pgsql')) {
    echo "<li class='error'>PostgreSQL PDOドライバーをインストールしてください</li>";
    echo "<li class='info'>または、HTTP API経由でSupabaseにアクセスしてください</li>";
}

if (extension_loaded('curl')) {
    echo "<li class='success'>cURLが利用可能なため、HTTP API接続が可能です</li>";
}

echo "<li class='info'>本番環境では、MCPサーバー経由でのSupabase接続を推奨します</li>";
echo "</ul>";

// Test 6: Available connection methods
echo "<h2>6. 利用可能な接続方法</h2>";
echo "<ul>";

if (extension_loaded('pdo_pgsql')) {
    echo "<li class='success'>✓ 直接PostgreSQL接続（Database.phpクラス）</li>";
} else {
    echo "<li class='error'>✗ 直接PostgreSQL接続（PDOドライバー不足）</li>";
}

if (extension_loaded('curl')) {
    echo "<li class='success'>✓ HTTP API接続（SupabaseClient.phpクラス）</li>";
} else {
    echo "<li class='error'>✗ HTTP API接続（cURL不足）</li>";
}

echo "<li class='info'>○ MCP経由接続（推奨、開発環境で利用可能）</li>";
echo "</ul>";

echo "</body>";
echo "</html>";
?>