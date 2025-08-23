<?php
/**
 * テナント営業時間取得API
 * 
 * テナントIDを指定して、そのテナントの営業時間設定を取得するAPI
 */

// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../includes/functions.php';

// JSONヘッダー設定
header('Content-Type: application/json');

// CORSヘッダー設定（必要に応じて）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// 共通レスポンス関数
function sendResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    
    echo json_encode($response);
    exit;
}

// GETリクエスト以外は拒否
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Only GET method is allowed');
}

// テナントIDが指定されていない場合
if (!isset($_GET['tenant_id']) || empty($_GET['tenant_id'])) {
    sendResponse(false, 'Tenant ID is required');
}

// テナントIDを取得
$tenantId = (int)$_GET['tenant_id'];

try {
    // データベース接続
    $db = new Database();
    
    // テナント情報の確認
    $sql = "SELECT tenant_id, company_name FROM tenants WHERE tenant_id = ?";
    $tenant = $db->fetchOne($sql, [$tenantId]);
    
    if (!$tenant) {
        sendResponse(false, 'Tenant not found');
    }
    
    // 営業時間設定を取得
    $sql = "SELECT setting_value FROM tenant_settings 
            WHERE tenant_id = ? AND setting_key = 'business_hours'";
    $setting = $db->fetchOne($sql, [$tenantId]);
    
    // デフォルトの営業時間設定
    $defaultBusinessHours = [
        'monday'    => ['is_open' => true, 'open_time' => '09:00', 'close_time' => '18:00'],
        'tuesday'   => ['is_open' => true, 'open_time' => '09:00', 'close_time' => '18:00'],
        'wednesday' => ['is_open' => true, 'open_time' => '09:00', 'close_time' => '18:00'],
        'thursday'  => ['is_open' => true, 'open_time' => '09:00', 'close_time' => '18:00'],
        'friday'    => ['is_open' => true, 'open_time' => '09:00', 'close_time' => '18:00'],
        'saturday'  => ['is_open' => true, 'open_time' => '10:00', 'close_time' => '17:00'],
        'sunday'    => ['is_open' => false, 'open_time' => null, 'close_time' => null]
    ];
    
    if ($setting) {
        // 既存の設定を返す
        $businessHours = json_decode($setting['setting_value'], true);
        sendResponse(true, 'Business hours retrieved successfully', [
            'tenant_id' => $tenantId,
            'tenant_name' => $tenant['company_name'],
            'business_hours' => $businessHours
        ]);
    } else {
        // デフォルト設定を返す
        sendResponse(true, 'Default business hours returned', [
            'tenant_id' => $tenantId,
            'tenant_name' => $tenant['company_name'],
            'business_hours' => $defaultBusinessHours,
            'is_default' => true
        ]);
    }
} catch (Exception $e) {
    // エラーログ記録
    error_log('API Error: ' . $e->getMessage());
    sendResponse(false, 'An error occurred: ' . $e->getMessage());
}
?>
