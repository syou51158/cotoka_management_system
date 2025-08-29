<?php
// Supabaseデータベース接続テスト専用スクリプト

// エラー表示を最大限に有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Supabaseデータベース接続診断</h1>";

// Supabase接続確認
echo "<h2>1. Supabase接続状態確認</h2>";
echo "<p style='color:blue'>ℹ Supabaseはクラウドサービスのため、インターネット接続が必要です</p>";

// 設定ファイルの内容を確認
echo "<h2>2. Supabaseデータベース設定確認</h2>";

// config.phpファイルの存在確認
$config_file = __DIR__ . '/config/config.php';
if (file_exists($config_file)) {
    echo "<p style='color:green'>✓ 設定ファイルが存在します: {$config_file}</p>";
    
    // 設定ファイルを読み込み
    require_once $config_file;
    
    // 設定値を表示
    echo "<p>データベースホスト: <strong>" . DB_HOST . "</strong></p>";
    echo "<p>データベース名: <strong>" . DB_NAME . "</strong></p>";
    echo "<p>データベースユーザー: <strong>" . DB_USER . "</strong></p>";
    echo "<p>データベースパスワード: <strong>" . (empty(DB_PASS) ? "(空)" : "設定済み") . "</strong></p>";
} else {
    echo "<p style='color:red'>✗ 設定ファイルが見つかりません: {$config_file}</p>";
}

// Supabaseデータベース接続テスト
echo "<h2>3. Supabaseデータベース接続テスト</h2>";

try {
    require_once 'classes/Database.php';
    
    echo "<p>Supabaseデータベースへの接続を試みています...</p>";
    $database = new Database();
    $pdo = $database->getConnection();
    echo "<p style='color:green'>✓ Supabaseデータベースへの接続に成功しました</p>";
    
    // スキーマの存在確認
    $stmt = $pdo->query("SELECT schema_name FROM information_schema.schemata WHERE schema_name = 'cotoka'");
    $schema_exists = $stmt->rowCount() > 0;
    
    if ($schema_exists) {
        echo "<p style='color:green'>✓ スキーマ 'cotoka' は存在します</p>";
        
        // テーブル一覧を取得
        $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'cotoka' ORDER BY table_name");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($tables) > 0) {
            echo "<p style='color:green'>✓ cotoka スキーマ内に " . count($tables) . " 個のテーブルが存在します</p>";
            echo "<ul>";
            foreach ($tables as $table) {
                echo "<li>" . htmlspecialchars($table) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color:red'>✗ cotoka スキーマ内にテーブルが存在しません</p>";
            echo "<p>Supabaseマイグレーションを実行する必要があります。</p>";
        }
    } else {
        echo "<p style='color:red'>✗ スキーマ 'cotoka' が存在しません</p>";
        echo "<p>Supabaseでスキーマを作成する必要があります。</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Supabaseデータベース接続エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // エラーの種類に応じた解決策を提案
    if (strpos($e->getMessage(), "could not connect to server") !== false) {
        echo "<p>Supabaseサーバーに接続できません。インターネット接続とconfig.phpの設定を確認してください。</p>";
    } elseif (strpos($e->getMessage(), "authentication failed") !== false) {
        echo "<p>Supabaseの認証に失敗しました。config.phpのデータベース設定を確認してください。</p>";
    } elseif (strpos($e->getMessage(), "database") !== false && strpos($e->getMessage(), "does not exist") !== false) {
        echo "<p>Supabaseプロジェクトまたはデータベースが見つかりません。config.phpの設定を確認してください。</p>";
    }
}

// 解決策の提案
echo "<h2>4. Supabase接続の問題解決ステップ</h2>";
echo "<ol>";
echo "<li>インターネット接続が正常であることを確認してください。</li>";
echo "<li>config.phpのSupabase設定（ホスト、ポート、データベース名、ユーザー名、パスワード）が正しいことを確認してください。</li>";
echo "<li>Supabaseプロジェクトが正常に動作していることを確認してください。</li>";
echo "<li>必要に応じて、test_supabase_connection.phpでより詳細なテストを実行してください。</li>";
echo "</ol>";

echo "<p><a href='test_supabase_connection.php'>Supabase接続テストページに移動</a></p>";
?>