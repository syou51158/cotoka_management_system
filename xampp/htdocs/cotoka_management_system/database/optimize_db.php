<?php
/**
 * Cotoka Management System - Supabaseデータベース最適化スクリプト
 * 
 * このスクリプトは、サロン管理システムのSupabaseデータベースを最適化します。
 * - 必要なインデックスの追加
 * - 外部キー制約の修正
 * - 新しいテーブルの作成
 * - テーブル構造の改善
 */

// 設定ファイルの読み込み
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// 出力をバッファリング
ob_start();

echo "==================================================\n";
echo "Cotoka Management System - Supabaseデータベース最適化ツール\n";
echo "==================================================\n\n";

// Supabaseデータベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
    echo "Supabaseデータベース接続成功\n";
} catch (Exception $e) {
    die("Supabaseデータベース接続エラー: " . $e->getMessage() . "\n");
}

// 最適化スクリプトを実行する関数
function executeOptimizationScript($conn, $scriptPath, $description) {
    echo "\n実行中: $description...\n";
    
    try {
        // SQLファイルの内容を読み込み
        $sql = file_get_contents($scriptPath);
        if (!$sql) {
            echo "エラー: $scriptPath ファイルを読み込めませんでした。\n";
            return false;
        }
        
        // セミコロンで分割して個別のクエリとして実行
        $queries = explode(';', $sql);
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (empty($query)) continue;
            
            try {
                $conn->exec($query);
                $successCount++;
            } catch (PDOException $e) {
                echo "クエリエラー: " . $e->getMessage() . "\n";
                echo "問題のクエリ: " . substr($query, 0, 100) . "...\n";
                $errorCount++;
            }
        }
        
        echo "処理完了: $successCount 件のクエリが成功、$errorCount 件のエラー\n";
        return ($errorCount === 0);
    } catch (Exception $e) {
        echo "スクリプト実行エラー: " . $e->getMessage() . "\n";
        return false;
    }
}

// データベース構造を確認し、問題点をレポート
function checkDatabaseStructure($conn) {
    echo "\nデータベース構造チェック中...\n";
    
    $issues = [];
    
    // 主要なテーブルの存在確認
    $requiredTables = [
        'tenants', 'salons', 'users', 'staff', 'customers', 
        'services', 'appointments', 'sales'
    ];
    
    $query = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'cotoka' OR table_schema = 'public'";
    $stmt = $conn->query($query);
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($requiredTables as $table) {
        if (!in_array($table, $existingTables)) {
            $issues[] = "必須テーブルが見つかりません: $table";
        }
    }
    
    // インデックスチェック
    $tablesWithMissingIndices = [];
    $checkIndexQueries = [
        'appointments' => [
            "SELECT indexname FROM pg_indexes WHERE schemaname IN ('cotoka', 'public') AND tablename = 'appointments' AND indexname = 'idx_appointment_date'",
            "SELECT indexname FROM pg_indexes WHERE schemaname IN ('cotoka', 'public') AND tablename = 'appointments' AND indexname = 'idx_appointment_staff_date'"
        ],
        'customers' => [
            "SELECT indexname FROM pg_indexes WHERE schemaname IN ('cotoka', 'public') AND tablename = 'customers' AND indexname = 'idx_customer_name'",
            "SELECT indexname FROM pg_indexes WHERE schemaname IN ('cotoka', 'public') AND tablename = 'customers' AND indexname = 'idx_customer_email'"
        ]
    ];
    
    foreach ($checkIndexQueries as $table => $queries) {
        if (in_array($table, $existingTables)) {
            $missingIndices = false;
            foreach ($queries as $query) {
                $stmt = $conn->query($query);
                if ($stmt->rowCount() === 0) {
                    $missingIndices = true;
                    break;
                }
            }
            if ($missingIndices) {
                $tablesWithMissingIndices[] = $table;
            }
        }
    }
    
    if (!empty($tablesWithMissingIndices)) {
        $issues[] = "インデックスが不足しているテーブル: " . implode(', ', $tablesWithMissingIndices);
    }
    
    // カラム存在チェック
    try {
        $stmt = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_schema IN ('cotoka', 'public') AND table_name = 'appointments'");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredColumns = ['is_confirmed', 'confirmation_sent_at', 'reminder_sent_at'];
        $missingColumns = [];
        
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $columns)) {
                $missingColumns[] = $column;
            }
        }
        
        if (!empty($missingColumns)) {
            $issues[] = "予約テーブルに必要なカラムがありません: " . implode(', ', $missingColumns);
        }
    } catch (PDOException $e) {
        if (in_array('appointments', $existingTables)) {
            $issues[] = "予約テーブルの構造確認中にエラー: " . $e->getMessage();
        }
    }
    
    // 結果レポート
    if (empty($issues)) {
        echo "データベース構造は良好です。最適化は不要かもしれません。\n";
        return true;
    } else {
        echo "以下の問題が見つかりました:\n";
        foreach ($issues as $issue) {
            echo "- $issue\n";
        }
        echo "最適化スクリプトを実行することで解決できます。\n";
        return false;
    }
}

// Supabaseデータベースのバックアップを作成
function backupDatabase($conn, $dbName) {
    echo "\nSupabaseデータベースのバックアップを作成中...\n";
    
    // バックアップディレクトリを確認
    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0755, true)) {
            echo "バックアップディレクトリを作成できません: $backupDir\n";
            return false;
        }
    }
    
    // バックアップファイル名を生成
    $backupFile = $backupDir . '/supabase_backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    try {
        // Supabaseの場合、pg_dumpを使用するか、手動でテーブルデータをエクスポート
        echo "注意: Supabaseのバックアップは手動で行う必要があります。\n";
        echo "Supabaseダッシュボードからデータベースのバックアップを作成してください。\n";
        echo "または、pg_dumpコマンドを使用してバックアップを作成してください。\n";
        
        // 簡易的なスキーマ情報をファイルに保存
        $schemaInfo = "-- Supabase Schema Backup Created: " . date('Y-m-d H:i:s') . "\n";
        $schemaInfo .= "-- Project: Cotoka Management System\n";
        $schemaInfo .= "-- Schema: cotoka\n\n";
        
        // テーブル一覧を取得
        $stmt = $conn->query("SELECT table_name FROM information_schema.tables WHERE table_schema IN ('cotoka', 'public') ORDER BY table_name");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $schemaInfo .= "-- Tables in database:\n";
        foreach ($tables as $table) {
            $schemaInfo .= "-- - $table\n";
        }
        
        file_put_contents($backupFile, $schemaInfo);
        echo "スキーマ情報をバックアップファイルに保存しました: $backupFile\n";
        return true;
        
    } catch (Exception $e) {
        echo "バックアップ作成中にエラーが発生しました: " . $e->getMessage() . "\n";
        return false;
    }
}

// メイン処理
echo "データベース最適化プロセスを開始します...\n";

// データベース構造チェック
$needsOptimization = !checkDatabaseStructure($conn);

if ($needsOptimization) {
    // 続行確認
    echo "\n警告: 最適化を実行すると、データベース構造が変更されます。\n";
    echo "続行する前にバックアップを作成することを強く推奨します。\n";
    echo "続行しますか？[y/N]: ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) !== 'y') {
        echo "操作がキャンセルされました。\n";
        exit;
    }
    
    // バックアップ作成
    if (!backupDatabase($conn, DB_NAME)) {
        echo "バックアップに失敗しました。安全のため、最適化を中止します。\n";
        exit;
    }
    
    // 最適化スクリプト実行
    $optimizationScripts = [
        __DIR__ . '/db_optimization.sql' => 'データベース最適化スクリプト'
    ];
    
    foreach ($optimizationScripts as $scriptPath => $description) {
        if (!file_exists($scriptPath)) {
            echo "エラー: $scriptPath ファイルが見つかりません。\n";
            continue;
        }
        
        if (executeOptimizationScript($conn, $scriptPath, $description)) {
            echo "$description が正常に実行されました。\n";
        } else {
            echo "$description の実行中にエラーが発生しました。\n";
        }
    }
    
    // 最適化後の確認
    checkDatabaseStructure($conn);
    
    echo "\n最適化処理が完了しました。\n";
} else {
    echo "データベースは既に最適な状態です。最適化は必要ありません。\n";
}

// バッファの内容を出力
$output = ob_get_clean();
echo $output;

// ログファイルに記録
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
file_put_contents(
    $logDir . '/db_optimization_' . date('Y-m-d_H-i-s') . '.log',
    $output
);

echo "処理が完了しました。詳細なログは logs ディレクトリに保存されています。\n";