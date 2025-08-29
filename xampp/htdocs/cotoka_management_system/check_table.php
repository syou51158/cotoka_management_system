<?php
require_once 'includes/init.php';

try {
    $db = Database::getInstance();
    echo "=== tenantsテーブル構造 ===\n";
    
    // カラム情報を取得
    $columns = $db->query("SHOW COLUMNS FROM tenants");
    foreach ($columns as $column) {
        echo "Field: {$column['Field']}, Type: {$column['Type']}, Null: {$column['Null']}, Key: {$column['Key']}, Default: {$column['Default']}\n";
    }
    
    echo "\n=== salonsテーブル構造 ===\n";
    $columns = $db->query("SHOW COLUMNS FROM salons");
    foreach ($columns as $column) {
        echo "Field: {$column['Field']}, Type: {$column['Type']}, Null: {$column['Null']}, Key: {$column['Key']}, Default: {$column['Default']}\n";
    }
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
}
?>