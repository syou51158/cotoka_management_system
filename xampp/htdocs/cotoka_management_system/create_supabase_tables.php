<?php
/**
 * Supabaseプロジェクトにテーブルを作成するスクリプト
 */
require_once 'config/config.php';
require_once 'classes/SupabaseClient.php';

echo "=== Supabaseテーブル作成スクリプト ===\n\n";

// Supabaseクライアントの初期化
$supabase = new SupabaseClient();

// SQLファイルの読み込み
$sqlFile = 'supabase_schema.sql';
if (!file_exists($sqlFile)) {
    die("エラー: {$sqlFile} が見つかりません\n");
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    die("エラー: {$sqlFile} の読み込みに失敗しました\n");
}

echo "SQLファイルを読み込みました: {$sqlFile}\n";
echo "SQLファイルサイズ: " . strlen($sql) . " バイト\n\n";

// SQLを実行（複数のステートメントに分割）
$statements = explode(';', $sql);
$successCount = 0;
$errorCount = 0;

foreach ($statements as $index => $statement) {
    $statement = trim($statement);
    
    // 空のステートメントやコメントのみの行をスキップ
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue;
    }
    
    echo "実行中 (" . ($index + 1) . "/" . count($statements) . "): ";
    echo substr($statement, 0, 50) . "...\n";
    
    try {
        // Supabase REST APIでSQLを実行
        $result = $supabase->rpc('exec_sql', ['sql' => $statement]);
        
        if ($result && !isset($result['error'])) {
            echo "  ✓ 成功\n";
            $successCount++;
        } else {
            echo "  ✗ エラー: " . (isset($result['error']) ? $result['error']['message'] : '不明なエラー') . "\n";
            $errorCount++;
        }
    } catch (Exception $e) {
        echo "  ✗ 例外: " . $e->getMessage() . "\n";
        $errorCount++;
    }
    
    // 少し待機（API制限対策）
    usleep(100000); // 0.1秒
}

echo "\n=== 実行結果 ===\n";
echo "成功: {$successCount} 件\n";
echo "エラー: {$errorCount} 件\n";

if ($errorCount === 0) {
    echo "\n✓ 全てのテーブルが正常に作成されました！\n";
} else {
    echo "\n⚠ 一部のテーブル作成でエラーが発生しました。\n";
    echo "Supabaseダッシュボードで詳細を確認してください。\n";
}

// 作成されたテーブルの確認
echo "\n=== 作成されたテーブルの確認 ===\n";
try {
    $tables = $supabase->select('information_schema.tables', [
        'table_name'
    ], [
        'table_schema' => 'eq.public',
        'table_type' => 'eq.BASE TABLE'
    ]);
    
    if ($tables && is_array($tables)) {
        echo "作成されたテーブル数: " . count($tables) . "\n";
        foreach ($tables as $table) {
            echo "  - " . $table['table_name'] . "\n";
        }
    } else {
        echo "テーブル一覧の取得に失敗しました\n";
    }
} catch (Exception $e) {
    echo "テーブル確認エラー: " . $e->getMessage() . "\n";
}

echo "\n=== 完了 ===\n";
?>