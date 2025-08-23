<?php
// データベース接続情報
require_once 'config/config.php';
require_once 'classes/Database.php';

try {
    // データベースに接続
    $db = new Database();
    $conn = $db->getConnection();
    
    // 対象のサロンID
    $salon_id = 1;
    
    echo "===== サロンID=$salon_id のサービス詳細確認 =====\n\n";
    
    // すべてのサービスを取得し、カテゴリIDとcategoryカラムを確認
    $query = "SELECT service_id, name, category_id, category, status FROM services WHERE salon_id = :salon_id AND status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "サロンID=$salon_id のアクティブなサービス: " . count($services) . "件\n\n";
    
    // カテゴリ別にサービスを集計
    $services_by_category_id = [];
    $services_by_category_name = [];
    
    foreach ($services as $service) {
        $category_id = $service['category_id'] ?? 'NULL';
        $category_name = $service['category'] ?? 'NULL';
        
        if (!isset($services_by_category_id[$category_id])) {
            $services_by_category_id[$category_id] = [];
        }
        $services_by_category_id[$category_id][] = $service;
        
        if (!isset($services_by_category_name[$category_name])) {
            $services_by_category_name[$category_name] = [];
        }
        $services_by_category_name[$category_name][] = $service;
    }
    
    // カテゴリIDによる分類結果
    echo "===== カテゴリIDによるサービス分類 =====\n";
    foreach ($services_by_category_id as $category_id => $cat_services) {
        echo "カテゴリID: $category_id - " . count($cat_services) . "件のサービス\n";
        // 各カテゴリIDが実際にservice_categoriesテーブルに存在するか確認
        if ($category_id !== 'NULL') {
            $query = "SELECT category_id, name FROM service_categories WHERE category_id = :category_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->execute();
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($category) {
                echo "  カテゴリ名: " . $category['name'] . "\n";
            } else {
                echo "  注意: このカテゴリIDはservice_categoriesテーブルに存在しません\n";
            }
        }
        
        // 最初の5つのサービスを表示
        $displayed = 0;
        foreach ($cat_services as $service) {
            if ($displayed < 5) {
                echo "  - サービスID: " . $service['service_id'] . ", 名前: " . $service['name'] . "\n";
                $displayed++;
            }
        }
        if (count($cat_services) > 5) {
            echo "  ... 他 " . (count($cat_services) - 5) . " 件\n";
        }
        echo "\n";
    }
    
    // カテゴリ名による分類結果
    echo "===== categoryカラムによるサービス分類 =====\n";
    foreach ($services_by_category_name as $category_name => $cat_services) {
        echo "カテゴリ名: $category_name - " . count($cat_services) . "件のサービス\n";
        
        // 最初の5つのサービスを表示
        $displayed = 0;
        foreach ($cat_services as $service) {
            if ($displayed < 5) {
                echo "  - サービスID: " . $service['service_id'] . ", 名前: " . $service['name'] . "\n";
                $displayed++;
            }
        }
        if (count($cat_services) > 5) {
            echo "  ... 他 " . (count($cat_services) - 5) . " 件\n";
        }
        echo "\n";
    }
    
    // サービスカテゴリのリストを取得
    echo "===== サービスカテゴリ一覧 =====\n";
    $query = "SELECT category_id, name FROM service_categories WHERE salon_id = :salon_id ORDER BY display_order ASC";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($categories as $category) {
        echo "カテゴリID: " . $category['category_id'] . ", 名前: " . $category['name'] . "\n";
    }
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
}
?> 