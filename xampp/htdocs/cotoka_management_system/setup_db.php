<?php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=".DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // データベース作成
    $pdo->exec("CREATE DATABASE IF NOT EXISTS ".DB_NAME." CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    
    // テーブル作成
    $pdo->exec("USE ".DB_NAME);
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        password VARCHAR(255) NOT NULL
    )");

    echo 'データベースとテーブルが正常に作成されました';
} catch(PDOException $e) {
    die("エラーが発生しました: " . $e->getMessage());
}
?>