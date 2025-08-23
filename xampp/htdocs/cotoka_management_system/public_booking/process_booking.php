<?php
// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../includes/functions.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// セッション開始（セッションがまだ開始されていない場合のみ）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// サロンIDと予約IDを取得
$salon_id = $_SESSION['booking_salon_id'] ?? null;
$appointment_id = $_SESSION['booking_appointment_id'] ?? null;

// セッションチェック
if (!$salon_id || !$appointment_id) {
    $_SESSION['error_message'] = "予約情報が見つかりません。最初からやり直してください。";
    header('Location: index.php');
    exit;
}

// POSTリクエストのみ受け付ける
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: confirm.php');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // トランザクション開始
    $conn->beginTransaction();
    
    // 予約情報を取得
    $stmt = $conn->prepare("
        SELECT a.*, c.email as customer_email 
        FROM appointments a
        JOIN customers c ON a.customer_id = c.customer_id
        WHERE a.appointment_id = :appointment_id 
        AND a.salon_id = :salon_id
        AND a.status = 'pending'
    ");
    $stmt->bindParam(':appointment_id', $appointment_id);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        throw new Exception("予約情報が見つからないか、既に確定済みです。");
    }
    
    // 予約ステータスを更新
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET 
            status = 'confirmed',
            is_confirmed = 1,
            confirmation_sent_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE appointment_id = :appointment_id
    ");
    $stmt->bindParam(':appointment_id', $appointment_id);
    $stmt->execute();
    
    // 予約確認メールの送信（実装は省略）
    // send_confirmation_email($appointment['customer_email'], $appointment);
    
    // トランザクションをコミット
    $conn->commit();
    
    // 予約完了ページへリダイレクト
    header('Location: complete.php');
    exit;
    
} catch (Exception $e) {
    // エラーが発生した場合はロールバック
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    // エラーメッセージをセッションに保存
    $_SESSION['error_message'] = "予約確定エラー：" . $e->getMessage();
    
    // 確認ページへ戻る
    header('Location: confirm.php');
    exit;
} 