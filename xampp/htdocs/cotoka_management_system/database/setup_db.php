<?php
require_once __DIR__ . '/../config/config.php';

try {
    // データベース接続
    $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // データベースを作成
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "データベースを作成しました: " . DB_NAME . "<br>";
    
    // 作成したデータベースを選択
    $pdo->exec("USE `" . DB_NAME . "`");
    
    // 外部キーチェックを無効化
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // テーブルを作成
    
    // テナントテーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenants (
        tenant_id INT AUTO_INCREMENT PRIMARY KEY,
        company_name VARCHAR(100) NOT NULL,
        owner_name VARCHAR(100),
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        address TEXT,
        subscription_plan ENUM('free', 'basic', 'premium', 'enterprise') NOT NULL DEFAULT 'free',
        subscription_status ENUM('active', 'trial', 'expired', 'cancelled') NOT NULL DEFAULT 'trial',
        trial_ends_at DATETIME,
        subscription_ends_at DATETIME,
        max_salons INT NOT NULL DEFAULT 1,
        max_users INT NOT NULL DEFAULT 3,
        max_storage_mb INT NOT NULL DEFAULT 100,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "テナントテーブルを作成しました<br>";
    
    // サロンテーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS salons (
        salon_id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        address TEXT,
        phone VARCHAR(20),
        email VARCHAR(100),
        business_hours TEXT,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "サロンテーブルを作成しました<br>";
    
    // ユーザーテーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        username VARCHAR(50) NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL,
        first_name VARCHAR(50),
        last_name VARCHAR(50),
        role ENUM('tenant_admin', 'manager', 'staff') NOT NULL DEFAULT 'staff',
        last_login TIMESTAMP NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "ユーザーテーブルを作成しました<br>";
    
    // スーパー管理者テーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS super_admins (
        admin_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE,
        first_name VARCHAR(50),
        last_name VARCHAR(50),
        status ENUM('active', 'inactive') DEFAULT 'active',
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "スーパー管理者テーブルを作成しました<br>";
    
    // ユーザーとサロンの関連付けテーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_salons (
        user_id INT NOT NULL,
        salon_id INT NOT NULL,
        role ENUM('manager', 'staff') NOT NULL DEFAULT 'staff',
        PRIMARY KEY (user_id, salon_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "ユーザー・サロン関連テーブルを作成しました<br>";
    
    // 顧客テーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        customer_id INT AUTO_INCREMENT PRIMARY KEY,
        salon_id INT NOT NULL,
        tenant_id INT NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        address TEXT,
        birthday DATE,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "顧客テーブルを作成しました<br>";
    
    // スタッフテーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS staff (
        staff_id INT AUTO_INCREMENT PRIMARY KEY,
        salon_id INT NOT NULL,
        tenant_id INT NOT NULL,
        user_id INT,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        position VARCHAR(50),
        hire_date DATE,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "スタッフテーブルを作成しました<br>";
    
    // サービステーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS services (
        service_id INT AUTO_INCREMENT PRIMARY KEY,
        salon_id INT NOT NULL,
        tenant_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        duration INT NOT NULL COMMENT '分単位',
        price DECIMAL(10, 2) NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "サービステーブルを作成しました<br>";
    
    // 予約テーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
        appointment_id INT AUTO_INCREMENT PRIMARY KEY,
        salon_id INT NOT NULL,
        tenant_id INT NOT NULL,
        customer_id INT NOT NULL,
        staff_id INT NOT NULL,
        service_id INT NOT NULL,
        appointment_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        status ENUM('scheduled', 'completed', 'cancelled', 'no-show') DEFAULT 'scheduled',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "予約テーブルを作成しました<br>";
    
    // 支払いテーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        payment_id INT AUTO_INCREMENT PRIMARY KEY,
        salon_id INT NOT NULL,
        tenant_id INT NOT NULL,
        appointment_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        payment_method ENUM('cash', 'credit_card', 'debit_card', 'transfer', 'other') NOT NULL,
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "支払いテーブルを作成しました<br>";
    
    // テナント設定テーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_settings (
        setting_id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        setting_key VARCHAR(50) NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_setting_key_tenant (setting_key, tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "テナント設定テーブルを作成しました<br>";
    
    // サブスクリプション履歴テーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_history (
        history_id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        plan ENUM('free', 'basic', 'premium', 'enterprise') NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        payment_status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
        payment_method VARCHAR(50),
        transaction_id VARCHAR(100),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "サブスクリプション履歴テーブルを作成しました<br>";
    
    // システムログテーブル
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50),
        entity_id INT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "システムログテーブルを作成しました<br>";
    
    // 外部キーチェックを有効化
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // 初期データの挿入
    
    // デモテナント
    $pdo->exec("INSERT INTO tenants (company_name, owner_name, email, phone, subscription_plan, subscription_status, max_salons, max_users)
    VALUES ('デモサロン株式会社', '山田太郎', 'demo@example.com', '03-1234-5678', 'premium', 'active', 3, 10)");
    echo "デモテナントを作成しました<br>";
    
    // デモサロン
    $pdo->exec("INSERT INTO salons (tenant_id, name, address, phone, email, business_hours, description)
    VALUES (1, 'デモサロン渋谷店', '東京都渋谷区○○ 1-2-3', '03-1234-5678', 'shibuya@example.com', '平日: 10:00-20:00, 土日祝: 10:00-18:00', 'デモ用サロン')");
    echo "デモサロンを作成しました<br>";
    
    // デモユーザー（管理者）
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (tenant_id, username, password, email, first_name, last_name, role, status)
    VALUES (1, 'demo_admin', :password, 'demo@example.com', '山田', '太郎', 'tenant_admin', 'active')");
    $stmt->bindParam(':password', $hashedPassword);
    $stmt->execute();
    echo "デモユーザー（管理者）を作成しました<br>";
    
    // デモスタッフ
    $pdo->exec("INSERT INTO staff (salon_id, tenant_id, first_name, last_name, email, phone, position, status)
    VALUES (1, 1, '佐藤', '健太', 'kenta@example.com', '090-1234-5678', 'スタイリスト', 'active')");
    echo "デモスタッフを作成しました<br>";
    
    // デモサービス
    $pdo->exec("INSERT INTO services (salon_id, tenant_id, name, description, duration, price, status)
    VALUES (1, 1, 'カット', '髪のカットのみ', 30, 4000, 'active'),
    (1, 1, 'カラー', 'ヘアカラーリング', 60, 8000, 'active'),
    (1, 1, 'パーマ', 'パーマをかける施術', 90, 12000, 'active')");
    echo "デモサービスを作成しました<br>";
    
    // 各SQLファイルを実行する関数
    function executeSQLFile($pdo, $filepath) {
        echo "Executing SQL file: $filepath<br>";
        $sql = file_get_contents($filepath);
        
        // 複数のSQL文を分割して実行
        $queries = explode(';', $sql);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                try {
                    $pdo->exec($query);
                    echo "Query executed successfully<br>";
                } catch (PDOException $e) {
                    echo "Error executing query: " . $e->getMessage() . "<br>";
                }
            }
        }
    }
    
    // 売上テーブルのセットアップ
    try {
        // 売上テーブルの作成
        executeSQLFile($pdo, __DIR__ . '/migrations/create_sales_table.sql');
        echo "売上テーブルが正常に作成されました<br>";
    } catch (PDOException $e) {
        echo "売上テーブルの作成中にエラーが発生しました: " . $e->getMessage() . "<br>";
    }
    
    echo "<br>データベースのセットアップが完了しました！";
    
} catch (PDOException $e) {
    die("データベースエラー: " . $e->getMessage());
} 