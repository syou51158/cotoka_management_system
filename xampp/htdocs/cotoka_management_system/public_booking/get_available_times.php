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

// パラメータの取得と検証
$date = $_POST['date'] ?? null;
$staff_id = $_POST['staff_id'] ?? null;

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '日付の形式が無効です。']);
    exit;
}

if ($staff_id === null || (!is_numeric($staff_id) && $staff_id !== '0')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'スタッフIDの形式が無効です。']);
    exit;
}

// 選択したサービスの合計時間を計算
$total_duration = array_sum(array_column($services, 'duration'));

// 指定された時間範囲から時間枠を生成する関数
function generateTimeSlotsFromRange($start_time, $end_time, $duration, $interval = 30) {
    $slots = [];
    $current = strtotime($start_time);
    $end = strtotime($end_time);
    
    // 施術に必要な時間を考慮して終了時間を調整
    $max_start_time = strtotime('-' . $duration . ' minutes', $end);
    
    while ($current <= $max_start_time) {
        $slots[] = date('H:i', $current);
        $current = strtotime('+' . $interval . ' minutes', $current);
    }
    
    return $slots;
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
    
    $available_slots = [];
    
    // 指名ありの場合
    if ($staff_id !== '0') {
        // スタッフのシフト情報を取得
        $stmt = $conn->prepare("
            SELECT 
                start_time,
                end_time
            FROM staff_shifts
            WHERE staff_id = :staff_id
            AND salon_id = :salon_id
            AND shift_date = :shift_date
            AND status = 'active'
        ");
        $stmt->bindParam(':staff_id', $staff_id);
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->bindParam(':shift_date', $date);
        $stmt->execute();
        $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // シフトがない場合
        if (empty($shifts)) {
            // 営業時間を使用
            $open_time = substr($business_hours['open_time'], 0, 5);
            $close_time = substr($business_hours['close_time'], 0, 5);
            
            // 今日の場合、現在時刻以降の時間枠のみを考慮
            if ($date == date('Y-m-d')) {
                $current_time = date('H:i');
                if ($current_time > $open_time) {
                    $open_time = date('H:i', strtotime($current_time . ' +30 minutes'));
                }
            }
            
            $time_slots = generateTimeSlotsFromRange($open_time, $close_time, $total_duration);
        } else {
            // 各シフトの時間枠を生成して統合
            $time_slots = [];
            foreach ($shifts as $shift) {
                $shift_start = substr($shift['start_time'], 0, 5);
                $shift_end = substr($shift['end_time'], 0, 5);
                
                // 今日の場合、現在時刻以降の時間枠のみを考慮
                if ($date == date('Y-m-d')) {
                    $current_time = date('H:i');
                    if ($current_time > $shift_start) {
                        $shift_start = date('H:i', strtotime($current_time . ' +30 minutes'));
                    }
                }
                
                // サロンの営業時間内に制限
                $open_time = substr($business_hours['open_time'], 0, 5);
                $close_time = substr($business_hours['close_time'], 0, 5);
                
                $effective_start = max($shift_start, $open_time);
                $effective_end = min($shift_end, $close_time);
                
                if ($effective_start < $effective_end) {
                    $slots = generateTimeSlotsFromRange($effective_start, $effective_end, $total_duration);
                    $time_slots = array_merge($time_slots, $slots);
                }
            }
            
            // 重複を削除
            $time_slots = array_unique($time_slots);
            sort($time_slots);
        }
        
        // 予約済みの時間枠を取得
        $stmt = $conn->prepare("
            SELECT 
                start_time,
                end_time
            FROM appointments
            WHERE staff_id = :staff_id
            AND salon_id = :salon_id
            AND appointment_date = :appointment_date
            AND status NOT IN ('cancelled', 'no-show')
        ");
        $stmt->bindParam(':staff_id', $staff_id);
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->bindParam(':appointment_date', $date);
        $stmt->execute();
        $booked_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 予約済みの時間枠を除外
        foreach ($time_slots as $time_slot) {
            $is_available = true;
            
            // サービス終了時間を計算
            $end_time_obj = new DateTime($time_slot);
            $end_time_obj->modify('+' . $total_duration . ' minutes');
            $end_time = $end_time_obj->format('H:i');
            
            foreach ($booked_appointments as $appointment) {
                $appt_start = substr($appointment['start_time'], 0, 5);
                $appt_end = substr($appointment['end_time'], 0, 5);
                
                // 時間が重複しているかチェック
                if (($time_slot >= $appt_start && $time_slot < $appt_end) || 
                    ($end_time > $appt_start && $end_time <= $appt_end) || 
                    ($time_slot <= $appt_start && $end_time >= $appt_end)) {
                    $is_available = false;
                    break;
                }
            }
            
            if ($is_available) {
                $available_slots[] = $time_slot;
            }
        }
    }
    // 指名なしの場合（全スタッフから利用可能な時間枠を探す）
    else {
        // 営業時間を取得
        $open_time = substr($business_hours['open_time'], 0, 5);
        $close_time = substr($business_hours['close_time'], 0, 5);
        
        // 今日の場合、現在時刻以降の時間枠のみを考慮
        if ($date == date('Y-m-d')) {
            $current_time = date('H:i');
            if ($current_time > $open_time) {
                $open_time = date('H:i', strtotime($current_time . ' +30 minutes'));
            }
        }
        
        // すべての可能な時間枠を生成
        $all_time_slots = generateTimeSlotsFromRange($open_time, $close_time, $total_duration);
        
        // サービスを提供できるスタッフを取得
        $stmt = $conn->prepare("
            SELECT DISTINCT s.staff_id
            FROM staff s
            JOIN staff_services ss ON s.staff_id = ss.staff_id
            WHERE s.salon_id = :salon_id
            AND s.status = 'active'
            AND ss.service_id IN (" . implode(',', array_column($services, 'service_id')) . ")
            AND ss.is_active = 1
        ");
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->execute();
        $service_staff = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($service_staff)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'サービスを提供できるスタッフが見つかりません。']);
            exit;
        }
        
        // 各時間枠について、利用可能なスタッフが少なくとも1人いるかチェック
        foreach ($all_time_slots as $time_slot) {
            // サービス終了時間を計算
            $end_time_obj = new DateTime($time_slot);
            $end_time_obj->modify('+' . $total_duration . ' minutes');
            $end_time = $end_time_obj->format('H:i');
            
            foreach ($service_staff as $staff_id) {
                $is_available = true;
                
                // スタッフのシフトをチェック
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as shift_count
                    FROM staff_shifts
                    WHERE staff_id = :staff_id
                    AND salon_id = :salon_id
                    AND shift_date = :shift_date
                    AND status = 'active'
                    AND start_time <= :time_slot
                    AND end_time >= :end_time
                ");
                $stmt->bindParam(':staff_id', $staff_id);
                $stmt->bindParam(':salon_id', $salon_id);
                $stmt->bindParam(':shift_date', $date);
                $stmt->bindParam(':time_slot', $time_slot);
                $stmt->bindParam(':end_time', $end_time);
                $stmt->execute();
                $shift_result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ((int)$shift_result['shift_count'] === 0) {
                    continue; // シフトがないのでスキップ
                }
                
                // 予約済みの時間枠をチェック
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as booked_count
                    FROM appointments
                    WHERE staff_id = :staff_id
                    AND salon_id = :salon_id
                    AND appointment_date = :appointment_date
                    AND status NOT IN ('cancelled', 'no-show')
                    AND (
                        (start_time <= :time_slot AND end_time > :time_slot)
                        OR (start_time < :end_time AND end_time >= :end_time)
                        OR (start_time >= :time_slot AND end_time <= :end_time)
                    )
                ");
                $stmt->bindParam(':staff_id', $staff_id);
                $stmt->bindParam(':salon_id', $salon_id);
                $stmt->bindParam(':appointment_date', $date);
                $stmt->bindParam(':time_slot', $time_slot);
                $stmt->bindParam(':end_time', $end_time);
                $stmt->execute();
                $booked_result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ((int)$booked_result['booked_count'] === 0) {
                    // この時間枠に予約可能なスタッフが見つかった
                    $available_slots[] = $time_slot;
                    break; // 次の時間枠へ
                }
            }
        }
    }
    
    // 結果をソート
    sort($available_slots);
    
    // 成功レスポンスを返す
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => '利用可能な時間枠を取得しました。',
        'data' => $available_slots
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