<?php
// データベース接続情報
require_once 'config/config.php';
require_once 'classes/Database.php';

try {
    // データベースに接続
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "<h2>テーブル構造の確認</h2>";
    
    // service_categoriesテーブルの構造を確認
    echo "<h3>service_categoriesテーブルの構造</h3>";
    $query = "DESCRIBE service_categories";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    echo "<pre>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    echo "</pre>";
    
    // servicesテーブルの構造を確認
    echo "<h3>servicesテーブルの構造</h3>";
    $query = "DESCRIBE services";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    echo "<pre>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    echo "</pre>";
    
    // salonsテーブルの構造も確認
    echo "<h3>salonsテーブルの構造</h3>";
    $query = "DESCRIBE salons";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    echo "<pre>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    echo "</pre>";
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage();
}
?> 