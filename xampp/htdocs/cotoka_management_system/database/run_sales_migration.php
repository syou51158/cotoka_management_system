<?php
// Supabase売上テーブル移行スクリプト
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

// 実行状況を報告する関数
function report($message) {
    echo $message . "<br>";
    // ブラウザにすぐに表示するためにバッファをフラッシュ
    if (ob_get_level() > 0) {
        ob_flush();
        flush();
    }
}

try {
    // Supabaseデータベース接続
    $db = new Database();
    $pdo = $db->getConnection();
    report("Supabaseデータベースに接続しました");
    
    // SQLファイルを実行する関数
    function executeSQLFile($pdo, $filepath) {
        report("SQLファイルを実行: " . basename($filepath));
        $sql = file_get_contents($filepath);
        
        if (!$sql) {
            report("エラー: ファイル " . basename($filepath) . " を読み込めませんでした");
            return false;
        }
        
        // 複数のSQL文を分割して実行
        $queries = explode(';', $sql);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                try {
                    $pdo->exec($query);
                    report("クエリが正常に実行されました");
                } catch (PDOException $e) {
                    report("クエリ実行中にエラーが発生しました: " . $e->getMessage());
                }
            }
        }
        return true;
    }
    
    // 売上テーブルを作成
    $result = executeSQLFile($pdo, __DIR__ . '/create_sales_table.sql');
    
    if ($result) {
        report("売上テーブルが正常に作成されました");
    } else {
        report("売上テーブルの作成中に問題が発生しました");
    }
    
    report("sales テーブル移行が完了しました！");
    
} catch (PDOException $e) {
    report("データベースエラー: " . $e->getMessage());
} catch (Exception $e) {
    report("エラーが発生しました: " . $e->getMessage());
}
?>