<?php
// エラー表示を最大限に有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// エラーログの設定
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error.log');

echo "<h1>データベースセットアップツール</h1>";

// 設定ファイルを読み込み
require_once 'config/config.php';

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
    
    // テーブルの作成
    echo "<h2>テーブルの作成</h2>";
    
    // rolesテーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS `roles` (
      `role_id` int(11) NOT NULL AUTO_INCREMENT,
      `role_name` varchar(50) NOT NULL COMMENT 'ロール名',
      `description` text DEFAULT NULL COMMENT '説明',
      PRIMARY KEY (`role_id`),
      UNIQUE KEY `role_name` (`role_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    echo "<p style='color:green'>✓ rolesテーブルを作成しました</p>";
    
    // tenantsテーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tenants` (
      `tenant_id` int(11) NOT NULL AUTO_INCREMENT,
      `company_name` varchar(100) NOT NULL COMMENT '会社名',
      `contact_name` varchar(100) DEFAULT NULL COMMENT '担当者名',
      `email` varchar(255) DEFAULT NULL COMMENT 'メールアドレス',
      `phone` varchar(20) DEFAULT NULL COMMENT '電話番号',
      `address` text DEFAULT NULL COMMENT '住所',
      `subscription_plan` varchar(50) DEFAULT 'basic' COMMENT 'サブスクリプションプラン',
      `subscription_status` enum('active','inactive','trial') NOT NULL DEFAULT 'trial' COMMENT 'サブスクリプション状態',
      `trial_ends_at` date DEFAULT NULL COMMENT 'トライアル終了日',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`tenant_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    echo "<p style='color:green'>✓ tenantsテーブルを作成しました</p>";
    
    // salonsテーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS `salons` (
      `salon_id` int(11) NOT NULL AUTO_INCREMENT,
      `tenant_id` int(11) NOT NULL COMMENT 'テナントID',
      `salon_name` varchar(100) NOT NULL COMMENT 'サロン名',
      `address` text DEFAULT NULL COMMENT '住所',
      `phone` varchar(20) DEFAULT NULL COMMENT '電話番号',
      `email` varchar(255) DEFAULT NULL COMMENT 'メールアドレス',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`salon_id`),
      KEY `tenant_id` (`tenant_id`),
      CONSTRAINT `salons_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    echo "<p style='color:green'>✓ salonsテーブルを作成しました</p>";
    
    // usersテーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` varchar(50) NOT NULL COMMENT 'ユーザーID（ログイン用）',
      `tenant_id` int(11) DEFAULT NULL COMMENT '所属テナントID（全体管理者の場合はNULL）',
      `email` varchar(255) NOT NULL COMMENT 'メールアドレス',
      `password` varchar(255) NOT NULL COMMENT 'パスワードハッシュ',
      `name` varchar(100) NOT NULL COMMENT '名前',
      `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT '状態',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      `last_login` timestamp NULL DEFAULT NULL COMMENT '最終ログイン日時',
      `role_id` int(11) DEFAULT NULL COMMENT 'ロールID',
      PRIMARY KEY (`id`),
      UNIQUE KEY `user_id` (`user_id`),
      UNIQUE KEY `email` (`email`),
      KEY `tenant_id` (`tenant_id`),
      KEY `role_id` (`role_id`),
      CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE SET NULL,
      CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    echo "<p style='color:green'>✓ usersテーブルを作成しました</p>";
    
    // remember_tokensテーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS `remember_tokens` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` varchar(50) NOT NULL COMMENT 'ユーザーID',
      `token` varchar(255) NOT NULL COMMENT 'トークン',
      `expires_at` datetime NOT NULL COMMENT '有効期限',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    echo "<p style='color:green'>✓ remember_tokensテーブルを作成しました</p>";
    
    // 初期データの挿入
    echo "<h2>初期データの挿入</h2>";
    
    // rolesテーブルに初期データを挿入
    $roles = [
        ['admin', '全体管理者'],
        ['tenant_admin', 'テナント管理者'],
        ['salon_manager', 'サロン管理者'],
        ['staff', 'スタッフ']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO roles (role_name, description) VALUES (?, ?)");
    foreach ($roles as $role) {
        $stmt->execute($role);
    }
    echo "<p style='color:green'>✓ ロールの初期データを挿入しました</p>";
    
    // 管理者ユーザーの存在確認
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role_id = 1");
    $adminCount = $stmt->fetch()['count'];
    
    if ($adminCount == 0) {
        // テナントの作成
        $pdo->exec("INSERT INTO tenants (company_name, contact_name, email, subscription_status) 
                   VALUES ('システム管理', 'システム管理者', 'admin@example.com', 'active')");
        $tenantId = $pdo->lastInsertId();
        
        // 管理者ユーザーの作成
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (user_id, email, password, name, role_id) 
                             VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@example.com', $adminPassword, 'システム管理者', 1]);
        
        echo "<p style='color:green'>✓ 管理者ユーザーを作成しました</p>";
        echo "<p>ログイン情報：</p>";
        echo "<ul>";
        echo "<li>ユーザーID: admin</li>";
        echo "<li>パスワード: admin123</li>";
        echo "</ul>";
    } else {
        echo "<p style='color:green'>✓ 管理者ユーザーは既に存在します</p>";
    }
    
    echo "<h2>セットアップ完了</h2>";
    echo "<p>データベースのセットアップが完了しました。<a href='login.php'>ログインページ</a>からログインしてください。</p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ エラー: " . $e->getMessage() . "</p>";
}
?>