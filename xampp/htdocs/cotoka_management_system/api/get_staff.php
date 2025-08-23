<?php
/**
 * API: スタッフデータ取得
 * 
 * サロンに所属するスタッフデータを JSON 形式で返す
 */

// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Staff.php';
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

// Staffクラスのインスタンス作成
$staffObj = new Staff();

try {
    // 日付パラメータ（指定された日に出勤しているスタッフを取得するため）
    $date = isset($_POST['date']) ? htmlspecialchars($_POST['date']) : date('Y-m-d');

    // スタッフデータを取得
    $staff = $staffObj->getActiveStaffBySalonId($salon_id, $date);
    
    // JSON形式で返す
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'staff' => $staff
    ]);
    
} catch (Exception $e) {
    error_log('API スタッフデータ取得エラー: ' . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラーが発生しました。'
    ]);
}