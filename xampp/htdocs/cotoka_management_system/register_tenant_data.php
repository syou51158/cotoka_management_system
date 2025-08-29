<?php
/**
 * テナント初期データ登録スクリプト
 * 
 * 運営会社: Trend Company株式会社
 * サロン: 古都華 Cotoka Relax & Beauty SPA
 * スタッフ: チョウ, みどり, ねね, はる, はな, みー
 * メール: info@cotoka.jp
 */

require_once 'includes/init.php';

// 定数定義
if (!defined('DEFAULT_SUBSCRIPTION_TRIAL_DAYS')) {
    define('DEFAULT_SUBSCRIPTION_TRIAL_DAYS', 14);
}

// データベース接続を確認
try {
    $db = Database::getInstance();
    echo "データベース接続成功\n";
} catch (Exception $e) {
    die("データベース接続エラー: " . $e->getMessage() . "\n");
}

// テナントクラスを初期化
$tenant = new Tenant();
$staff = new Staff();
$salon = new Salon();

try {
        echo "=== テナント登録開始 ===\n";
        
        // 1. テナント（運営会社）を登録
        $tenantData = [
            'company_name' => 'Trend Company株式会社',
            'owner_name' => 'Trend Company',
            'email' => 'info@cotoka.jp',
            'phone' => '03-1234-5678',
            'address' => '東京都渋谷区',
            'subscription_plan' => 'premium',
            'subscription_status' => 'active',
            'max_salons' => 5,
            'max_users' => 20,
            'max_storage_mb' => 1000,
            'default_salon_name' => '古都華 Cotoka Relax & Beauty SPA'
        ];
        
        $tenantId = $tenant->add($tenantData);
        echo "✓ テナント登録完了: ID = $tenantId\n";
    
    // 2. サロン情報を取得（テナント作成時に自動作成されたもの）
    $salonSql = "SELECT salon_id FROM salons WHERE tenant_id = ? ORDER BY salon_id ASC LIMIT 1";
    $salonResult = $db->fetchOne($salonSql, [$tenantId]);
    $salonId = $salonResult['salon_id'];
    
    echo "✓ サロンID取得: $salonId\n";
    
    // 3. スタッフを登録
    $staffNames = ['チョウ', 'みどり', 'ねね', 'はる', 'はな', 'みー'];
    $staffIds = [];
    
    foreach ($staffNames as $index => $name) {
        $staffData = [
            'tenant_id' => $tenantId,
            'salon_id' => $salonId,
            'name' => $name,
            'email' => strtolower($name) . '@cotoka.jp',
            'phone' => sprintf('090-%04d-%04d', 1000 + $index, 1000 + $index),
            'specialty' => '美容全般',
            'is_active' => 1
        ];
        
        $staffSql = "INSERT INTO staff (tenant_id, salon_id, name, email, phone, specialty, is_active, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $db->query($staffSql, [
            $staffData['tenant_id'],
            $staffData['salon_id'],
            $staffData['name'],
            $staffData['email'],
            $staffData['phone'],
            $staffData['specialty'],
            $staffData['is_active']
        ]);
        
        $staffIds[] = $db->lastInsertId();
        echo "✓ スタッフ登録: $name\n";
    }
    
    // 4. デフォルトのサービスカテゴリを登録
    $categories = [
        ['カット', 'ヘアカット関連'],
        ['カラー', 'ヘアカラー関連'],
        ['パーマ', 'パーマ関連'],
        ['トリートメント', 'ヘアケア関連'],
        ['ヘッドスパ', '頭皮ケア関連']
    ];
    
    foreach ($categories as $category) {
        $catSql = "INSERT INTO service_categories (tenant_id, name, description, created_at) 
                   VALUES (?, ?, ?, NOW())";
        $db->query($catSql, [$tenantId, $category[0], $category[1]]);
        echo "✓ サービスカテゴリ登録: {$category[0]}\n";
    }
    
    // 5. デフォルトのサービスを登録
    $services = [
        ['カット', 'カット', 5000, 60, 1],
        ['カラー', 'カラー', 8000, 90, 2],
        ['パーマ', 'パーマ', 10000, 120, 3],
        ['トリートメント', 'トリートメント', 3000, 45, 4],
        ['ヘッドスパ', 'ヘッドスパ', 4000, 60, 5]
    ];
    
    foreach ($services as $service) {
        $serviceSql = "INSERT INTO services (tenant_id, salon_id, name, description, price, duration_minutes, category_id, is_active, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())";
        $db->query($serviceSql, [
            $tenantId, 
            $salonId, 
            $service[0], 
            $service[1], 
            $service[2], 
            $service[3], 
            $service[4]
        ]);
        echo "✓ サービス登録: {$service[0]}\n";
    }
    
    // 6. 営業時間を設定
    $businessHours = [
        ['monday', '09:00', '20:00', 1],
        ['tuesday', '09:00', '20:00', 1],
        ['wednesday', '09:00', '20:00', 1],
        ['thursday', '09:00', '20:00', 1],
        ['friday', '09:00', '20:00', 1],
        ['saturday', '09:00', '19:00', 1],
        ['sunday', '10:00', '18:00', 1]
    ];
    
    foreach ($businessHours as $hours) {
        $hoursSql = "INSERT INTO salon_business_hours (salon_id, day_of_week, open_time, close_time, is_closed, created_at) 
                     VALUES (?, ?, ?, ?, ?, NOW())";
        $db->query($hoursSql, [
            $salonId, 
            $hours[0], 
            $hours[1], 
            $hours[2], 
            $hours[3]
        ]);
    }
    
    echo "\n=== 登録完了 ===\n";
    echo "テナントID: $tenantId\n";
    echo "サロンID: $salonId\n";
    echo "スタッフ数: " . count($staffIds) . "人\n";
    echo "\nログイン情報:\n";
    echo "メールアドレス: info@cotoka.jp\n";
    echo "パスワード: syou108810\n";
    echo "\nシステムにアクセスできるようになりました！\n";
    
} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    die;
}
?>