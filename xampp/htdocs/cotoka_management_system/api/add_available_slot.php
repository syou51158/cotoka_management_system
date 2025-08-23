<?php
/**
 * 空き予約枠を追加するAPI
 */

// 共通ファイルの読み込み
require_once '../includes/init.php';

// JSON レスポンスを設定
header('Content-Type: application/json');

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です']);
    exit;
}

// CSRF対策
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => '不正なリクエストです']);
    exit;
}

// POSTデータのチェック
if (!isset($_POST['date']) || !isset($_POST['start_time']) || !isset($_POST['end_time'])) {
    echo json_encode(['success' => false, 'message' => '必須項目が入力されていません']);
    exit;
}

// 入力値を取得
$date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
$start_time = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_STRING);
$end_time = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_STRING);
$staff_id = filter_input(INPUT_POST, 'staff_id', FILTER_SANITIZE_NUMBER_INT);
$repeat_type = filter_input(INPUT_POST, 'repeat_type', FILTER_SANITIZE_STRING);
$repeat_until = filter_input(INPUT_POST, 'repeat_until', FILTER_SANITIZE_STRING);
$salon_id = $_SESSION['salon_id'];

// 日付のバリデーション
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => '日付の形式が正しくありません']);
    exit;
}

// 時間のバリデーション
if (!preg_match('/^\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
    echo json_encode(['success' => false, 'message' => '時間の形式が正しくありません']);
    exit;
}

// 時間の範囲チェック
if (strtotime("$date $end_time") <= strtotime("$date $start_time")) {
    echo json_encode(['success' => false, 'message' => '終了時間は開始時間より後にしてください']);
    exit;
}

// 繰り返し設定の処理
$dates = [];

if ($repeat_type === 'none' || empty($repeat_type)) {
    $dates[] = $date;
} else {
    // 繰り返し終了日のバリデーション
    if (empty($repeat_until) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $repeat_until)) {
        $repeat_until = date('Y-m-d', strtotime('+1 month', strtotime($date)));
    }
    
    // 開始日と終了日の範囲をチェック
    if (strtotime($repeat_until) < strtotime($date)) {
        echo json_encode(['success' => false, 'message' => '繰り返し終了日は開始日より後にしてください']);
        exit;
    }
    
    // 日付の配列を生成
    $current = strtotime($date);
    $end = strtotime($repeat_until);
    
    while ($current <= $end) {
        $current_date = date('Y-m-d', $current);
        
        // 繰り返しタイプに応じて日付を追加
        $weekday = date('w', $current);
        $add = false;
        
        switch ($repeat_type) {
            case 'daily':
                $add = true;
                break;
            case 'weekly':
                if (date('w', $current) === date('w', strtotime($date))) {
                    $add = true;
                }
                break;
            case 'weekdays':
                if ($weekday >= 1 && $weekday <= 5) {
                    $add = true;
                }
                break;
        }
        
        if ($add) {
            $dates[] = $current_date;
        }
        
        $current = strtotime('+1 day', $current);
    }
}

// データベースに保存
try {
    // トランザクション開始
    $conn->beginTransaction();
    
    $success_count = 0;
    $total_count = count($dates);
    
    foreach ($dates as $insert_date) {
        $query = "INSERT INTO available_slots (salon_id, staff_id, date, start_time, end_time, created_at) 
                 VALUES (:salon_id, :staff_id, :date, :start_time, :end_time, NOW())";
                 
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':salon_id', $salon_id, PDO::PARAM_INT);
        $stmt->bindParam(':staff_id', $staff_id, PDO::PARAM_INT);
        $stmt->bindParam(':date', $insert_date, PDO::PARAM_STR);
        $stmt->bindParam(':start_time', $start_time, PDO::PARAM_STR);
        $stmt->bindParam(':end_time', $end_time, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            $success_count++;
        }
    }
    
    // コミット
    $conn->commit();
    
    if ($success_count > 0) {
        echo json_encode([
            'success' => true, 
            'message' => "{$success_count}件の空き予約枠を追加しました。",
            'total' => $total_count,
            'success_count' => $success_count
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '空き予約枠の追加に失敗しました']);
    }
    
} catch (PDOException $e) {
    // ロールバック
    $conn->rollBack();
    
    // エラーログ
    error_log('空き予約枠追加エラー: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'データベースエラーが発生しました']);
}
?> 