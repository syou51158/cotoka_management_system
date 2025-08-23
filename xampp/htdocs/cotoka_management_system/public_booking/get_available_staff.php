<?php
// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// セッション開始（セッションがまだ開始されていない場合のみ）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// デバッグログ関数
function debug_log($message) {
    error_log($message);
    $logFile = __DIR__ . '/debug.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "$timestamp - $message\n", FILE_APPEND);
}

// POSTリクエストのみを受け付ける
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '無効なリクエストメソッドです。']);
    exit;
}

// サロンIDとサービスをセッションから取得
$salon_id = $_SESSION['booking_salon_id'] ?? null;
$services = $_SESSION['booking_services'] ?? [];

// パラメータの検証
if (!$salon_id || empty($services)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'セッション情報が不足しています。']);
    exit;
}

// 日付の取得と検証
$date = $_POST['date'] ?? null;
if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '日付の形式が無効です。']);
    exit;
}

try {
    // データベース接続
    $db = new Database();
    $conn = $db->getConnection();
    
    // 日付の曜日を取得（0:日曜日 - 6:土曜日）
    $day_of_week = date('w', strtotime($date));
    
    // サロンの営業時間をチェック
    $stmt = $conn->prepare("
        SELECT 
            open_time,
            close_time,
            is_closed
        FROM salon_business_hours
        WHERE salon_id = :salon_id
        AND day_of_week = :day_of_week
    ");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->bindParam(':day_of_week', $day_of_week);
    $stmt->execute();
    $business_hours = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 営業日でない場合
    if (!$business_hours || $business_hours['is_closed']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '選択された日付は営業日ではありません。']);
        exit;
    }
    
    // 選択したサービスの合計時間を計算
    $total_duration = array_sum(array_column($services, 'duration'));
    
    // スタッフシフト情報を取得
    $stmt = $conn->prepare("
        SELECT 
            s.staff_id,
            s.first_name,
            s.last_name,
            COALESCE(s.profile_image, 'default-profile.jpg') AS profile_image,
            sh.start_time,
            sh.end_time
        FROM staff s
        JOIN staff_shifts sh ON s.staff_id = sh.staff_id
        JOIN staff_services ss ON s.staff_id = ss.staff_id
        WHERE s.salon_id = :salon_id
        AND s.status = 'active'
        AND sh.salon_id = :salon_id
        AND sh.shift_date = :shift_date
        AND sh.status = 'active'
        AND ss.service_id IN (" . implode(',', array_column($services, 'service_id')) . ")
        AND ss.is_active = 1
        GROUP BY s.staff_id
        ORDER BY s.staff_id
    ");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->bindParam(':shift_date', $date);
    $stmt->execute();
    $staff_shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // スタッフが存在しない場合
    if (empty($staff_shifts)) {
        // フォールバック：サービスを提供できる全スタッフを取得
        $stmt = $conn->prepare("
            SELECT 
                s.staff_id,
                s.first_name,
                s.last_name,
                COALESCE(s.profile_image, 'default-profile.jpg') AS profile_image
            FROM staff s
            JOIN staff_services ss ON s.staff_id = ss.staff_id
            WHERE s.salon_id = :salon_id
            AND s.status = 'active'
            AND ss.service_id IN (" . implode(',', array_column($services, 'service_id')) . ")
            AND ss.is_active = 1
            GROUP BY s.staff_id
            ORDER BY s.staff_id
        ");
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->execute();
        $staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 結果の整形
        $available_staff = array_map(function($staff) {
            return [
                'staff_id' => $staff['staff_id'],
                'name' => $staff['last_name'] . ' ' . $staff['first_name'],
                'profile_image' => $staff['profile_image']
            ];
        }, $staff_list);
        
        // 指名なしオプションを追加
        array_unshift($available_staff, [
            'staff_id' => '0',
            'name' => '指名なし',
            'profile_image' => 'default-profile.jpg'
        ]);
    } else {
        // 結果の整形
        $available_staff = array_map(function($staff) {
            return [
                'staff_id' => $staff['staff_id'],
                'name' => $staff['last_name'] . ' ' . $staff['first_name'],
                'profile_image' => $staff['profile_image']
            ];
        }, $staff_shifts);
        
        // 指名なしオプションを追加
        array_unshift($available_staff, [
            'staff_id' => '0',
            'name' => '指名なし',
            'profile_image' => 'default-profile.jpg'
        ]);
    }
    
    // 成功レスポンスを返す
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => '利用可能なスタッフを取得しました。',
        'data' => $available_staff
    ]);
    
} catch (PDOException $e) {
    debug_log("PDOエラー: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'データベースエラーが発生しました。']);
    exit;
} catch (Exception $e) {
    debug_log("エラー: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
} 