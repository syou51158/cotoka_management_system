<?php
/**
 * API: 営業時間データ取得
 * 
 * サロンの営業時間データを JSON 形式で返す
 */

// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Salon.php';
require_once '../includes/functions.php';

// セッション確認
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '認証されていません。']);
    exit;
}

// CSRFトークン検証
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'セキュリティトークンが無効です。']);
    exit;
}

// 現在のサロンIDを取得
$salon_id = isset($_SESSION['salon_id']) ? (int)$_SESSION['salon_id'] : 0;

if (!$salon_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'サロンが選択されていません。']);
    exit;
}

// Salonクラスのインスタンス作成
$salonObj = new Salon();

try {
    // サロン情報を取得
    $salon = $salonObj->getById($salon_id);
    
    if (!$salon) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'サロン情報が見つかりません。']);
        exit;
    }
    
    // 営業時間の解析
    $business_hours = parseSalonBusinessHours($salon['business_hours']);
    
    // JSON形式で返す
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'business_hours' => $business_hours
    ]);
    
} catch (Exception $e) {
    error_log('API 営業時間データ取得エラー: ' . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラーが発生しました。'
    ]);
}

/**
 * サロンの営業時間文字列を解析する
 * 
 * @param string $business_hours_str 営業時間文字列（例: '平日: 10:00-20:00, 土日祝: 10:00-18:00'）
 * @return array 営業時間の連想配列（開始時間、終了時間）
 */
function parseSalonBusinessHours($business_hours_str) {
    // デフォルト値
    $result = [
        'start' => '10:00',
        'end' => '20:00'
    ];
    
    if (empty($business_hours_str)) {
        return $result;
    }
    
    // 曜日ごとの営業時間を分解
    $time_ranges = [];
    $parts = explode(',', $business_hours_str);
    
    foreach ($parts as $part) {
        if (preg_match('/(\S+):\s*(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})/', $part, $matches)) {
            $day_type = trim($matches[1]);
            $start_hour = (int)$matches[2];
            $start_minute = (int)$matches[3];
            $end_hour = (int)$matches[4];
            $end_minute = (int)$matches[5];
            
            $time_ranges[$day_type] = [
                'start' => sprintf('%02d:%02d', $start_hour, $start_minute),
                'end' => sprintf('%02d:%02d', $end_hour, $end_minute)
            ];
        }
    }
    
    // 今日の曜日に該当する時間帯を取得
    $today = date('w'); // 0=日, 1=月, ..., 6=土
    $is_holiday = false; // 祝日判定（実際には祝日APIなどを使用）
    
    if ($today == 0 || $today == 6 || $is_holiday) {
        // 土日祝
        if (isset($time_ranges['土日祝'])) {
            $result = $time_ranges['土日祝'];
        }
    } else {
        // 平日
        if (isset($time_ranges['平日'])) {
            $result = $time_ranges['平日'];
        }
    }
    
    return $result;
}
