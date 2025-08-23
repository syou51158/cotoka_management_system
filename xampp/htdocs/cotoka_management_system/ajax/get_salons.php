<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Salon.php';

// JSONレスポンスのヘッダー設定
header('Content-Type: application/json');

// テナントIDが指定されていない場合は空の配列を返す
if (!isset($_GET['tenant_id']) || !is_numeric($_GET['tenant_id'])) {
    echo json_encode([]);
    exit;
}

$tenant_id = (int)$_GET['tenant_id'];

try {
    // サロン情報を取得
    $salon = new Salon();
    $salons = $salon->getSalonsByTenantId($tenant_id);
    
    // フロントエンド用にデータ形式を調整
    $formatted_salons = [];
    foreach ($salons as $salon) {
        $formatted_salons[] = [
            'salon_id' => $salon['salon_id'],
            'salon_name' => $salon['name'], // nameをsalon_nameとして提供
            'address' => $salon['address'] ?? '',
            'phone' => $salon['phone'] ?? '',
            'email' => $salon['email'] ?? ''
        ];
    }
    
    // JSONで返す
    echo json_encode($formatted_salons);
} catch (Exception $e) {
    // エラー時は空の配列を返す
    error_log('Error in get_salons.php: ' . $e->getMessage());
    echo json_encode([]);
}
?> 