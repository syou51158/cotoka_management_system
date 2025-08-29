<?php
/**
 * Supabase REST API接続テスト
 * 
 * PostgreSQL PDOドライバーが利用できない場合の代替テスト
 */

// 設定ファイルを読み込み
require_once 'config/config.php';

echo "<h1>Supabase REST API接続テスト</h1>";
echo "<hr>";

// 1. 設定値の確認
echo "<h2>1. 設定値の確認</h2>";
echo "<p><strong>SUPABASE_URL:</strong> " . (defined('SUPABASE_URL') ? SUPABASE_URL : '未定義') . "</p>";
echo "<p><strong>SUPABASE_ANON_KEY:</strong> " . (defined('SUPABASE_ANON_KEY') ? '設定済み' : '未定義') . "</p>";
echo "<p><strong>SUPABASE_SERVICE_ROLE_KEY:</strong> " . (defined('SUPABASE_SERVICE_ROLE_KEY') && !empty(SUPABASE_SERVICE_ROLE_KEY) ? '設定済み' : '未定義') . "</p>";
echo "<hr>";

// 2. cURL拡張の確認
echo "<h2>2. cURL拡張の確認</h2>";
if (extension_loaded('curl')) {
    echo "<p style='color: green;'>✓ cURL拡張が利用可能です</p>";
    $curl_version = curl_version();
    echo "<p><strong>cURLバージョン:</strong> " . $curl_version['version'] . "</p>";
} else {
    echo "<p style='color: red;'>✗ cURL拡張が見つかりません</p>";
    echo "<p>REST API接続テストを実行できません。</p>";
    exit;
}
echo "<hr>";

// 3. Supabase REST API接続テスト
echo "<h2>3. Supabase REST API接続テスト</h2>";

function makeSupabaseRequest($endpoint, $method = 'GET', $data = null, $useServiceRole = false) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    $headers = [
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ];
    
    if ($useServiceRole && defined('SUPABASE_SERVICE_ROLE_KEY') && !empty(SUPABASE_SERVICE_ROLE_KEY)) {
        $headers[] = 'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY;
        $headers[] = 'apikey: ' . SUPABASE_SERVICE_ROLE_KEY;
    } else {
        $headers[] = 'Authorization: Bearer ' . SUPABASE_ANON_KEY;
        $headers[] = 'apikey: ' . SUPABASE_ANON_KEY;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'response' => $response,
        'http_code' => $httpCode,
        'error' => $error
    ];
}

// テナントテーブルへのアクセステスト
echo "<h3>3.1 テナントテーブルアクセステスト</h3>";
$result = makeSupabaseRequest('tenants?select=tenant_id,company_name,created_at&limit=5');

if ($result['error']) {
    echo "<p style='color: red;'>✗ cURLエラー: " . htmlspecialchars($result['error']) . "</p>";
} elseif ($result['http_code'] === 200) {
    echo "<p style='color: green;'>✓ テナントテーブルへの接続成功 (HTTP " . $result['http_code'] . ")</p>";
    $tenants = json_decode($result['response'], true);
    if (!empty($tenants)) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Tenant ID</th><th>Company Name</th><th>Created At</th></tr>";
        foreach ($tenants as $tenant) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($tenant['tenant_id'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($tenant['company_name'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($tenant['created_at'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p>取得したレコード数: " . count($tenants) . "</p>";
    } else {
        echo "<p style='color: orange;'>⚠ テナントテーブルにデータがありません</p>";
    }
} else {
    echo "<p style='color: red;'>✗ HTTP エラー: " . $result['http_code'] . "</p>";
    echo "<p>レスポンス: " . htmlspecialchars($result['response']) . "</p>";
}

echo "<hr>";

// 4. スキーマ情報の取得テスト
echo "<h2>4. スキーマ情報取得テスト</h2>";
$schemaResult = makeSupabaseRequest('', 'GET'); // OpenAPI仕様を取得

if ($schemaResult['error']) {
    echo "<p style='color: red;'>✗ スキーマ情報取得エラー: " . htmlspecialchars($schemaResult['error']) . "</p>";
} elseif ($schemaResult['http_code'] === 200) {
    echo "<p style='color: green;'>✓ Supabase REST APIスキーマ情報取得成功</p>";
    $schema = json_decode($schemaResult['response'], true);
    if (isset($schema['definitions'])) {
        $tables = array_keys($schema['definitions']);
        echo "<p>利用可能なテーブル数: " . count($tables) . "</p>";
        echo "<details><summary>テーブル一覧を表示</summary>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
        echo "</ul>";
        echo "</details>";
    }
} else {
    echo "<p style='color: orange;'>⚠ スキーマ情報取得: HTTP " . $schemaResult['http_code'] . "</p>";
}

echo "<hr>";
echo "<h2>テスト結果サマリー</h2>";
if ($result['http_code'] === 200) {
    echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✓ Supabase REST API接続テストが正常に完了しました！</p>";
    echo "<p>PostgreSQL PDOドライバーは利用できませんが、REST APIを使用してSupabaseにアクセスできることを確認しました。</p>";
    echo "<p><strong>推奨事項:</strong></p>";
    echo "<ul>";
    echo "<li>PostgreSQL PDOドライバーを有効にするか、REST APIベースのデータアクセス層を実装</li>";
    echo "<li>SUPABASE_SERVICE_ROLE_KEYを設定して管理機能を有効化</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red; font-size: 18px; font-weight: bold;'>✗ Supabase接続に問題があります</p>";
    echo "<p>設定を確認してください。</p>";
}

echo "<hr>";
echo "<p><a href='dashboard.php'>ダッシュボードに戻る</a> | <a href='check_pdo_drivers.php'>PDOドライバー確認</a></p>";
?>