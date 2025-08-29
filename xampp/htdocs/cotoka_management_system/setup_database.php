<?php
// エラー表示を最大限に有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>データベースセットアップツール（非推奨）</h1>";
echo "<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
echo "<h2 style='color: #856404; margin-top: 0;'>⚠️ 重要な通知</h2>";
echo "<p style='color: #856404;'>このスクリプトはMySQLベースの古いセットアップツールです。</p>";
echo "<p style='color: #856404;'>現在のシステムはSupabaseデータベースを使用しています。</p>";
echo "</div>";

echo "<h2>Supabase対応ツール</h2>";
echo "<ul>";
echo "<li><a href='create_supabase_tables.php'>Supabaseテーブル作成ツール</a> - 新しいテーブルを作成</li>";
echo "<li><a href='database_check.php'>データベース診断ツール</a> - 接続とテーブル状態の確認</li>";
echo "<li><a href='index.php'>メインページ</a> - システムのメインページに戻る</li>";
echo "</ul>";

echo "<hr>";
echo "<h2>参考：以前のMySQLセットアップコード</h2>";
echo "<p style='color: #666;'>以下は参考用の古いMySQLセットアップコードです（実行されません）：</p>";
echo "<div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid #dee2e6;'>";
echo "<pre style='color: #666;'>";

// 以下は参考用の古いMySQLセットアップコード（実行されません）
/*
// データベース接続
try {
    $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    echo "<p style='color:green'>✓ データベースサーバーに接続しました</p>";
    
    // データベースの存在確認と作成
    $dbname = DB_NAME;
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$dbname}'");
    
    if ($stmt->rowCount() === 0) {
        // データベースが存在しない場合は作成
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        echo "<p style='color:green'>✓ データベース '{$dbname}' を作成しました</p>";
    } else {
        echo "<p style='color:green'>✓ データベース '{$dbname}' は既に存在します</p>";
    }
    
    // 作成したデータベースを選択
    $pdo->exec("USE `{$dbname}`");
    echo "<p style='color:green'>✓ データベース '{$dbname}' を選択しました</p>";
    
    // [以下、テーブル作成とデータ挿入のコードが続く...]
    // 詳細は省略
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ エラー: " . $e->getMessage() . "</p>";
}
*/

echo "</pre>";
echo "</div>";
echo "<p style='color: #666; font-style: italic;">上記のコードは参考用です。実際のテーブル作成にはSupabase対応ツールをご利用ください。</p>";
?>