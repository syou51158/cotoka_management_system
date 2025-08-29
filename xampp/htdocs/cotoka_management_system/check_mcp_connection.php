<?php
/**
 * MCP Supabaseサーバー接続確認スクリプト
 */
require_once 'config/config.php';
require_once 'classes/SupabaseClient.php';

echo "=== MCP Supabase接続確認 ===\n\n";

// 設定情報の表示
echo "=== Supabase設定情報 ===\n";
echo "SUPABASE_URL: " . SUPABASE_URL . "\n";
echo "SUPABASE_ANON_KEY: " . substr(SUPABASE_ANON_KEY, 0, 20) . "...\n";
echo "SUPABASE_SERVICE_ROLE_KEY: " . (SUPABASE_SERVICE_ROLE_KEY ? substr(SUPABASE_SERVICE_ROLE_KEY, 0, 20) . "..." : "未設定") . "\n\n";

// プロジェクトIDの抽出
$projectRef = '';
if (preg_match('/https:\/\/([a-zA-Z0-9]+)\.supabase\.co/', SUPABASE_URL, $matches)) {
    $projectRef = $matches[1];
    echo "プロジェクトリファレンス: {$projectRef}\n\n";
} else {
    echo "プロジェクトリファレンスの抽出に失敗\n\n";
}

// Supabaseクライアントの初期化
$supabase = new SupabaseClient();

// 接続テスト
echo "=== 接続テスト ===\n";
$connectionTest = $supabase->testConnection();
if ($connectionTest['success']) {
    echo "✓ Supabase API接続成功\n";
} else {
    echo "✗ Supabase API接続失敗: " . $connectionTest['message'] . "\n";
}

// プロジェクト情報の取得
echo "\n=== プロジェクト情報 ===\n";
try {
    // プロジェクトの基本情報を取得
    $projectInfo = $supabase->makeRequest('GET', '/rest/v1/');
    if ($projectInfo) {
        echo "✓ REST APIアクセス成功\n";
    } else {
        echo "✗ REST APIアクセス失敗\n";
    }
} catch (Exception $e) {
    echo "✗ プロジェクト情報取得エラー: " . $e->getMessage() . "\n";
}

// スキーマ情報の取得
echo "\n=== スキーマ情報 ===\n";
try {
    // 利用可能なスキーマを確認
    $schemas = $supabase->select('information_schema.schemata', [
        'schema_name'
    ]);
    
    if ($schemas && is_array($schemas)) {
        echo "利用可能なスキーマ:\n";
        foreach ($schemas as $schema) {
            echo "  - " . $schema['schema_name'] . "\n";
        }
    } else {
        echo "スキーマ情報の取得に失敗\n";
    }
} catch (Exception $e) {
    echo "スキーマ情報取得エラー: " . $e->getMessage() . "\n";
}

// テーブル一覧の取得
echo "\n=== テーブル一覧 ===\n";
try {
    $tables = $supabase->select('information_schema.tables', [
        'table_name',
        'table_schema',
        'table_type'
    ], [
        'table_schema' => 'eq.public'
    ]);
    
    if ($tables && is_array($tables)) {
        echo "publicスキーマのテーブル数: " . count($tables) . "\n";
        if (count($tables) > 0) {
            echo "テーブル一覧:\n";
            foreach ($tables as $table) {
                echo "  - " . $table['table_name'] . " (" . $table['table_type'] . ")\n";
            }
        } else {
            echo "テーブルが見つかりません\n";
        }
    } else {
        echo "テーブル一覧の取得に失敗\n";
    }
} catch (Exception $e) {
    echo "テーブル一覧取得エラー: " . $e->getMessage() . "\n";
}

// MCP設定ファイルの確認
echo "\n=== MCP設定ファイル確認 ===\n";
$mcpConfigPath = '.cursor/mcp.json';
if (file_exists($mcpConfigPath)) {
    echo "✓ MCP設定ファイルが存在: {$mcpConfigPath}\n";
    $mcpConfig = json_decode(file_get_contents($mcpConfigPath), true);
    if ($mcpConfig) {
        echo "設定内容:\n";
        if (isset($mcpConfig['mcpServers']['supabase-cotoka'])) {
            $serverConfig = $mcpConfig['mcpServers']['supabase-cotoka'];
            echo "  - コマンド: " . $serverConfig['command'] . "\n";
            echo "  - 引数: " . implode(' ', $serverConfig['args']) . "\n";
            echo "  - 環境変数: " . (isset($serverConfig['env']) ? 'あり' : 'なし') . "\n";
        } else {
            echo "  supabase-cotokaサーバー設定が見つかりません\n";
        }
    } else {
        echo "✗ MCP設定ファイルの解析に失敗\n";
    }
} else {
    echo "✗ MCP設定ファイルが見つかりません: {$mcpConfigPath}\n";
}

// 推奨事項
echo "\n=== 推奨事項 ===\n";
echo "1. Supabaseダッシュボードでプロジェクトの状態を確認\n";
echo "2. 必要に応じてテーブルを作成 (create_supabase_tables.phpを実行)\n";
echo "3. MCP接続にはSupabaseパーソナルアクセストークンが必要\n";
echo "4. 環境変数SUPABASE_ACCESS_TOKENを設定\n";

echo "\n=== 完了 ===\n";
?>