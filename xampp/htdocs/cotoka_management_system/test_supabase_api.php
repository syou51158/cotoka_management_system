<?php
require_once 'config.php';
require_once 'classes/SupabaseClient.php';

// HTMLヘッダー
echo "<!DOCTYPE html>";
echo "<html lang='ja'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Supabase API接続テスト</title>";
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

echo "<h1>Supabase API接続テスト</h1>";

// 設定情報の表示
echo "<h2>1. 設定情報</h2>";
echo "<p><strong>Supabase URL:</strong> " . SUPABASE_URL . "</p>";
echo "<p><strong>Anonymous Key:</strong> " . substr(SUPABASE_ANON_KEY, 0, 20) . "...（省略）</p>";

// Supabase API接続テスト
echo "<h2>2. Supabase API接続テスト</h2>";

try {
    $supabase = new SupabaseClient();
    
    echo "<p class='info'>Supabase APIへの接続を試みています...</p>";
    
    // 接続テスト
    $connectionTest = $supabase->testConnection();
    
    if ($connectionTest['success']) {
        echo "<p class='success'>✓ Supabase API接続に成功しました</p>";
        
        // テーブル一覧の取得
        echo "<h3>cotoka スキーマのテーブル一覧</h3>";
        if (!empty($connectionTest['tables'])) {
            echo "<p class='success'>✓ " . count($connectionTest['tables']) . " 個のテーブルが見つかりました</p>";
            echo "<ul>";
            foreach ($connectionTest['tables'] as $table) {
                echo "<li>" . htmlspecialchars($table['table_name']) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='error'>✗ テーブルが見つかりませんでした</p>";
        }
        
        // 基本的なクエリテスト
        echo "<h3>基本クエリテスト</h3>";
        
        // rolesテーブルのテスト
        try {
            $roles = $supabase->select('roles', '*');
            echo "<p class='success'>✓ rolesテーブルの読み取りに成功: " . count($roles) . " 件のレコード</p>";
            if (!empty($roles)) {
                echo "<pre>" . json_encode($roles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ rolesテーブルの読み取りエラー: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
        // tenantsテーブルのテスト
        try {
            $tenants = $supabase->select('tenants', '*');
            echo "<p class='success'>✓ tenantsテーブルの読み取りに成功: " . count($tenants) . " 件のレコード</p>";
            if (!empty($tenants)) {
                echo "<pre>" . json_encode($tenants, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ tenantsテーブルの読み取りエラー: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
    } else {
        echo "<p class='error'>✗ Supabase API接続に失敗しました</p>";
        echo "<p class='error'>エラー: " . htmlspecialchars($connectionTest['message']) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ 予期しないエラーが発生しました</p>";
    echo "<p class='error'>エラー内容: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// トラブルシューティング情報
echo "<h2>3. トラブルシューティング</h2>";
echo "<ul>";
echo "<li>cURL拡張機能が有効になっていることを確認してください</li>";
echo "<li>インターネット接続が正常であることを確認してください</li>";
echo "<li>Supabaseプロジェクトが正常に動作していることを確認してください</li>";
echo "<li>config.phpのSupabase設定が正しいことを確認してください</li>";
echo "</ul>";

// cURL情報の表示
echo "<h3>cURL情報</h3>";
if (extension_loaded('curl')) {
    echo "<p class='success'>✓ cURL拡張機能は有効です</p>";
    $curlVersion = curl_version();
    echo "<p>cURLバージョン: " . $curlVersion['version'] . "</p>";
} else {
    echo "<p class='error'>✗ cURL拡張機能が無効です</p>";
}

echo "</body>";
echo "</html>";
?>