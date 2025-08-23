<?php
/**
 * 予約ステータス更新API
 * 
 * 予約の状態（確定、キャンセルなど）を更新するためのAPIエンドポイント
 * パラメータ：
 * - appointment_id: 予約ID
 * - status: 新しいステータス（confirmed、cancelled、pending、no-showなど）
 * - csrf_token: CSRFトークン
 * 
 * 戻り値：
 * - success: 処理結果 (true/false)
 * - message: メッセージ
 */

// 必要なファイルの読み込み
require_once '../../config/config.php';
require_once '../../classes/Database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

// JSON形式でレスポンス
header('Content-Type: application/json');

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => '認証エラー：ログインが必要です。'
    ]);
    exit;
}

// CSRFトークンの検証
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'success' => false,
        'message' => 'セキュリティエラー：不正なリクエストです。'
    ]);
    exit;
}

// パラメータの取得と検証
if (!isset($_POST['appointment_id']) || !is_numeric($_POST['appointment_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'パラメータエラー：予約IDが正しくありません。'
    ]);
    exit;
}

if (!isset($_POST['status']) || empty($_POST['status'])) {
    echo json_encode([
        'success' => false,
        'message' => 'パラメータエラー：ステータスが指定されていません。'
    ]);
    exit;
}

// パラメータの取得
$appointment_id = (int)$_POST['appointment_id'];
$status = $_POST['status'];

// 有効なステータスかチェック
$valid_statuses = ['confirmed', 'cancelled', 'pending', 'no-show'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode([
        'success' => false,
        'message' => '無効なステータスです。有効なステータス: ' . implode(', ', $valid_statuses)
    ]);
    exit;
}

try {
    // データベース接続
    $db = new Database();
    $conn = $db->getConnection();
    
    // 現在のサロンIDを取得
    $salon_id = isset($_SESSION['salon_id']) ? $_SESSION['salon_id'] : 1;
    
    // 指定された予約が存在するか、かつ現在のサロンに属しているか確認
    $stmt = $conn->prepare("
        SELECT appointment_id 
        FROM appointments 
        WHERE appointment_id = ? AND salon_id = ?
    ");
    $stmt->execute([$appointment_id, $salon_id]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => '指定された予約が見つからないか、このサロンに属していません。'
        ]);
        exit;
    }
    
    // 予約ステータスの更新
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET status = ?, updated_at = NOW() 
        WHERE appointment_id = ?
    ");
    $stmt->execute([$status, $appointment_id]);
    
    // 更新が成功したかどうかを確認
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => '予約ステータスが正常に更新されました。',
            'appointment_id' => $appointment_id,
            'status' => $status
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '予約ステータスの更新に失敗しました。'
        ]);
    }
    
} catch (PDOException $e) {
    // エラーログ
    error_log('予約ステータス更新エラー: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー：予約ステータスの更新に失敗しました。',
        'error_detail' => DEBUG_MODE ? $e->getMessage() : null
    ]);
} 