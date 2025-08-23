<?php
/**
 * 予約管理システムの時間設定を更新するためのAPI
 * 
 * このスクリプトは、予約カレンダーの表示設定（表示モード、時間間隔、営業時間など）を
 * 更新するためのエンドポイントを提供します。
 */

// 必要なファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'includes/functions.php';

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '認証が必要です']);
    exit;
}

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '不正なリクエストメソッドです']);
    exit;
}

// パラメータを取得（XSS対策）
$view_mode = isset($_POST['view_mode']) ? filter_var($_POST['view_mode'], FILTER_SANITIZE_STRING) : 'week';
$time_interval = isset($_POST['time_interval']) ? (int)$_POST['time_interval'] : 30;
$business_hours_start = isset($_POST['business_hours_start']) ? filter_var($_POST['business_hours_start'], FILTER_SANITIZE_STRING) : '09:00';
$business_hours_end = isset($_POST['business_hours_end']) ? filter_var($_POST['business_hours_end'], FILTER_SANITIZE_STRING) : '18:00';

// バリデーション
$valid_view_modes = ['day', 'week'];
$valid_time_intervals = [5, 10, 15, 30, 60];

if (!in_array($view_mode, $valid_view_modes)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '無効な表示モードです']);
    exit;
}

if (!in_array($time_interval, $valid_time_intervals)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '無効な時間間隔です']);
    exit;
}

// 営業時間のフォーマットをチェック
if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $business_hours_start) || 
    !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $business_hours_end)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '営業時間のフォーマットが無効です']);
    exit;
}

// サロンID取得
$salon_id = getCurrentSalonId();

// データベース接続
$db = new Database();
$conn = $db->getConnection();

// ユーザーがアクセス可能なサロンを取得
$user_id = $_SESSION['user_id'];
$user = new User($conn);
$accessible_salons = $user->getAccessibleSalons($user_id);

// サロンIDが設定されていない場合、最初のアクセス可能なサロンを選択
if (!$salon_id && count($accessible_salons) > 0) {
    $salon_id = $accessible_salons[0]['salon_id'];
    $_SESSION['salon_id'] = $salon_id;
}

// アクセス可能なサロンがない場合はエラーを返す
if (count($accessible_salons) === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'アクセス可能なサロンがありません。管理者にお問い合わせください。']);
    exit;
}

// 現在のサロンIDがアクセス可能なサロンのリストに含まれているか確認
$salon_accessible = false;
foreach ($accessible_salons as $salon) {
    if ($salon['salon_id'] == $salon_id) {
        $salon_accessible = true;
        break;
    }
}

// アクセス可能でない場合は最初のアクセス可能なサロンに切り替え
if (!$salon_accessible && count($accessible_salons) > 0) {
    $salon_id = $accessible_salons[0]['salon_id'];
    $_SESSION['salon_id'] = $salon_id;
}

try {
    // 既存の設定を取得
    $stmt = $conn->prepare("SELECT * FROM salon_settings WHERE salon_id = ?");
    $stmt->execute([$salon_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($settings) {
        // 既存の設定を更新
        $stmt = $conn->prepare("
            UPDATE salon_settings 
            SET 
                view_mode = ?,
                time_interval = ?,
                business_hours_start = ?,
                business_hours_end = ?,
                updated_at = NOW()
            WHERE salon_id = ?
        ");
        $stmt->execute([
            $view_mode,
            $time_interval,
            $business_hours_start,
            $business_hours_end,
            $salon_id
        ]);
    } else {
        // 新しい設定を挿入
        $stmt = $conn->prepare("
            INSERT INTO salon_settings 
            (salon_id, view_mode, time_interval, business_hours_start, business_hours_end, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $salon_id,
            $view_mode,
            $time_interval,
            $business_hours_start,
            $business_hours_end
        ]);
    }
    
    // セッションに設定を保存
    $_SESSION['time_settings'] = [
        'view_mode' => $view_mode,
        'time_interval' => $time_interval,
        'business_hours_start' => $business_hours_start,
        'business_hours_end' => $business_hours_end
    ];
    
    // 成功レスポンス
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => '設定が更新されました']);
    
} catch (Exception $e) {
    // エラーログに記録
    error_log('時間設定更新エラー: ' . $e->getMessage());
    
    // エラーレスポンス
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'データベースエラーが発生しました: ' . $e->getMessage()]);
}
