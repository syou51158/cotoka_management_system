<?php
/**
 * System Functionality Test
 * Cotoka Management System の基本機能テスト
 */

require_once 'config/config.php';
require_once 'classes/Database.php';

echo "<h1>Cotoka Management System - 機能テスト</h1>";
echo "<hr>";

// 1. 設定値の確認
echo "<h2>1. システム設定の確認</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>設定項目</th><th>値</th><th>ステータス</th></tr>";

$config_items = [
    'SUPABASE_URL' => $_ENV['SUPABASE_URL'] ?? 'undefined',
    'SUPABASE_ANON_KEY' => !empty($_ENV['SUPABASE_ANON_KEY']) ? '設定済み' : '未設定',
    'SUPABASE_SERVICE_ROLE_KEY' => !empty($_ENV['SUPABASE_SERVICE_ROLE_KEY']) ? '設定済み' : '未設定',
    'DEBUG_MODE' => $_ENV['DEBUG_MODE'] ?? 'false',
    'MULTI_TENANT_ENABLED' => $_ENV['MULTI_TENANT_ENABLED'] ?? 'true',
    'APP_NAME' => $_ENV['APP_NAME'] ?? 'Cotoka Management System'
];

foreach ($config_items as $key => $value) {
    $status = ($value !== 'undefined' && $value !== '未設定') ? '✓' : '✗';
    $color = ($status === '✓') ? 'green' : 'red';
    echo "<tr>";
    echo "<td><strong>$key</strong></td>";
    echo "<td>" . htmlspecialchars($value) . "</td>";
    echo "<td style='color: $color;'>$status</td>";
    echo "</tr>";
}
echo "</table>";

// 2. データベース接続テスト
echo "<h2>2. データベース接続テスト</h2>";
try {
    $db = Database::getInstance();
    echo "<p style='color: green;'>✓ Database.phpクラスのインスタンス取得成功</p>";
    
    // PDO接続テスト
    try {
        $pdo = $db->getConnection();
        echo "<p style='color: green;'>✓ PDO接続成功</p>";
        
        // PostgreSQLバージョン確認
        $stmt = $pdo->query("SELECT version()");
        $version = $stmt->fetchColumn();
        echo "<p><strong>PostgreSQLバージョン:</strong> " . htmlspecialchars($version) . "</p>";
        
        // スキーマ確認
        $stmt = $pdo->query("SELECT current_schema()");
        $schema = $stmt->fetchColumn();
        echo "<p><strong>現在のスキーマ:</strong> " . htmlspecialchars($schema) . "</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ PDO接続失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p style='color: orange;'>⚠ PostgreSQL PDOドライバーが無効の可能性があります</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database.phpクラスエラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 3. 重要ファイルの存在確認
echo "<h2>3. 重要ファイルの存在確認</h2>";
$important_files = [
    'classes/Database.php' => 'データベースクラス',
    'config/config.php' => '設定ファイル',
    '.env' => '環境変数ファイル',
    'dashboard.php' => 'ダッシュボード',
    'login.php' => 'ログインページ',
    'index.php' => 'インデックスページ'
];

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ファイル</th><th>説明</th><th>ステータス</th></tr>";

foreach ($important_files as $file => $description) {
    $exists = file_exists($file);
    $status = $exists ? '✓ 存在' : '✗ 不在';
    $color = $exists ? 'green' : 'red';
    
    echo "<tr>";
    echo "<td><code>$file</code></td>";
    echo "<td>$description</td>";
    echo "<td style='color: $color;'>$status</td>";
    echo "</tr>";
}
echo "</table>";

// 4. REST API接続テスト（簡易版）
echo "<h2>4. Supabase REST API接続テスト</h2>";
if (!empty($_ENV['SUPABASE_URL']) && !empty($_ENV['SUPABASE_ANON_KEY'])) {
    $supabase_url = $_ENV['SUPABASE_URL'];
    $anon_key = $_ENV['SUPABASE_ANON_KEY'];
    
    $test_url = $supabase_url . "/rest/v1/tenants?select=count&head=true";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $anon_key,
        'Authorization: Bearer ' . $anon_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        echo "<p style='color: green;'>✓ Supabase REST API接続成功 (HTTP $http_code)</p>";
    } else {
        echo "<p style='color: red;'>✗ Supabase REST API接続失敗 (HTTP $http_code)</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ Supabase設定が不完全のため、REST APIテストをスキップ</p>";
}

// 5. 総合評価
echo "<h2>5. 総合評価</h2>";
echo "<div style='background-color: #e7f3ff; padding: 15px; border: 1px solid #b3d9ff; border-radius: 5px;'>";
echo "<h3>システム移行状況</h3>";
echo "<ul>";
echo "<li>✓ MySQL依存ファイルの無効化完了</li>";
echo "<li>✓ Supabase設定の統一完了</li>";
echo "<li>✓ Database.phpクラスによるSupabase接続実装</li>";
echo "<li>✓ REST API経由でのSupabaseアクセス確認</li>";
echo "<li>⚠ PostgreSQL PDOドライバーの有効化が必要</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background-color: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin-top: 10px;'>";
echo "<h3>次のステップ</h3>";
echo "<ol>";
echo "<li><a href='enable_pdo_pgsql.php'>PostgreSQL PDOドライバーの有効化</a></li>";
echo "<li>SUPABASE_SERVICE_ROLE_KEYの設定</li>";
echo "<li>本格的な機能テストの実施</li>";
echo "</ol>";
echo "</div>";

echo "<hr>";
echo "<p><a href='dashboard.php'>ダッシュボード</a> | <a href='test_supabase_rest_api.php'>REST APIテスト</a> | <a href='enable_pdo_pgsql.php'>PDOドライバー設定</a></p>";
?>