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
    
    echo "===== サロンID=$salon_id のデータ確認 =====\n\n";
    
    // サロン情報を確認
    echo "===== サロン情報 =====\n";
    $query = "SELECT * FROM salons WHERE salon_id = :salon_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $salon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($salon) {
        echo "サロンID: " . $salon['salon_id'] . "\n";
        echo "サロン名: " . $salon['name'] . "\n";
        echo "テナントID: " . $salon['tenant_id'] . "\n";
        echo "ステータス: " . $salon['status'] . "\n\n";
    } else {
        echo "サロンID=$salon_id のサロンは存在しません。\n\n";
    }
    
    // サービスカテゴリを確認
    echo "===== サービスカテゴリ =====\n";
    $query = "SELECT * FROM service_categories WHERE salon_id = :salon_id ORDER BY display_order ASC";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($categories) > 0) {
        echo "サロンID=$salon_id のカテゴリ数: " . count($categories) . "\n";
        foreach ($categories as $index => $category) {
            echo ($index + 1) . ". カテゴリID: " . $category['category_id'] . ", 名前: " . $category['name'] . "\n";
        }
        echo "\n";
    } else {
        echo "サロンID=$salon_id のカテゴリは存在しません。\n\n";
    }
    
    // サービスを確認
    echo "===== サービス =====\n";
    if (count($categories) > 0) {
        foreach ($categories as $category) {
            echo "カテゴリID=" . $category['category_id'] . " (" . $category['name'] . ") のサービス:\n";
            
            $query = "SELECT * FROM services WHERE category_id = :category_id AND salon_id = :salon_id AND status = 'active' ORDER BY display_order ASC";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':category_id', $category['category_id']);
            $stmt->bindParam(':salon_id', $salon_id);
            $stmt->execute();
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($services) > 0) {
                foreach ($services as $index => $service) {
                    echo "  " . ($index + 1) . ". サービスID: " . $service['service_id'] . ", 名前: " . $service['name'] . ", 価格: " . $service['price'] . ", ステータス: " . $service['status'] . "\n";
                }
            } else {
                echo "  このカテゴリにはアクティブなサービスがありません。\n";
            }
            echo "\n";
        }
    }
    
    // 直接サービスも確認
    $query = "SELECT * FROM services WHERE salon_id = :salon_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $all_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "サロンID=$salon_id の全サービス数: " . count($all_services) . "\n";
    if (count($all_services) > 0) {
        echo "ステータス別サービス数:\n";
        $status_counts = [];
        foreach ($all_services as $service) {
            $status = $service['status'] ?? 'ステータスなし';
            if (!isset($status_counts[$status])) {
                $status_counts[$status] = 0;
            }
            $status_counts[$status]++;
        }
        
        foreach ($status_counts as $status => $count) {
            echo "  $status: $count\n";
        }
    }
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
}
?> 