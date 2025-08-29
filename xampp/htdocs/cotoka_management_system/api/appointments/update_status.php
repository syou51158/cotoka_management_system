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
require_once '../../classes/Appointment.php';
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
if (!in_array($status, $valid_statuses, true)) {
    echo json_encode([
        'success' => false,
        'message' => '無効なステータスです。有効なステータス: ' . implode(', ', $valid_statuses)
    ]);
    exit;
}

try {
    $appointment = new Appointment();
    $salon_id = isset($_SESSION['salon_id']) ? (int)$_SESSION['salon_id'] : 1;

    // 予約の存在と所属サロン確認
    $record = $appointment->getAppointmentById($appointment_id);
    if (!$record || (isset($record['salon_id']) && (int)$record['salon_id'] !== $salon_id)) {
        echo json_encode([
            'success' => false,
            'message' => '指定された予約が見つからないか、このサロンに属していません。'
        ]);
        exit;
    }

    // ステータス更新
    $ok = $appointment->updateAppointmentStatus($appointment_id, $status);

    if ($ok) {
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
} catch (Exception $e) {
    // エラーログ
    error_log('予約ステータス更新エラー: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => '内部エラー：予約ステータスの更新に失敗しました。'
    ]);
}