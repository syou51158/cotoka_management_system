<?php
// データベース接続情報
require_once 'config/config.php';
require_once 'classes/Database.php';

try {
    // データベースに接続
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "===== テーブル構造の確認 =====\n\n";
    
    // service_categoriesテーブルの構造を確認
    echo "===== service_categoriesテーブルの構造 =====\n";
    $query = "DESCRIBE service_categories";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Field: " . $row['Field'] . ", Type: " . $row['Type'] . ", Null: " . $row['Null'] . ", Key: " . $row['Key'] . ", Default: " . $row['Default'] . "\n";
    }
    echo "\n";
    
    // servicesテーブルの構造を確認
    echo "===== servicesテーブルの構造 =====\n";
    $query = "DESCRIBE services";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Field: " . $row['Field'] . ", Type: " . $row['Type'] . ", Null: " . $row['Null'] . ", Key: " . $row['Key'] . ", Default: " . $row['Default'] . "\n";
    }
    echo "\n";
    
    // salonsテーブルの構造も確認
    echo "===== salonsテーブルの構造 =====\n";
    $query = "DESCRIBE salons";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Field: " . $row['Field'] . ", Type: " . $row['Type'] . ", Null: " . $row['Null'] . ", Key: " . $row['Key'] . ", Default: " . $row['Default'] . "\n";
    }
    echo "\n";
    
    // staffテーブルの構造を確認
    echo "===== staffテーブルの構造 =====\n";
    $query = "DESCRIBE staff";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Field: " . $row['Field'] . ", Type: " . $row['Type'] . ", Null: " . $row['Null'] . ", Key: " . $row['Key'] . ", Default: " . $row['Default'] . "\n";
    }
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
}
?> 