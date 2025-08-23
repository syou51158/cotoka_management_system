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

// デバッグログを保存（開発時のみコメント解除）
function debug_log($message) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log($message);
        file_put_contents(__DIR__ . '/debug.txt', date('Y-m-d H:i:s') . ' - ' . $message . "\n", FILE_APPEND);
    }
}

// デバッグモード設定
define('DEBUG_MODE', true);

// サロンIDと選択されたサービスの取得と検証
$salon_id = $_SESSION['booking_salon_id'] ?? null;
$services = $_SESSION['booking_services'] ?? null;

if (!$salon_id) {
    $_SESSION['error_message'] = "セッションが切れました。最初からやり直してください。";
    header('Location: index.php');
    exit;
}

if (empty($services)) {
    $_SESSION['error_message'] = "サービスが選択されていません。";
    header('Location: select_service.php');
    exit;
}
    
    // データベース接続
    try {
        $db = new Database();
        $conn = $db->getConnection();
    } catch (Exception $e) {
    error_log("データベース接続エラー：" . $e->getMessage());
    $_SESSION['error_message'] = "システムエラーが発生しました。しばらく時間をおいて再度お試しください。";
    header('Location: error.php');
    exit;
}

// サロン情報の取得
try {
    $stmt = $conn->prepare("
        SELECT 
            s.salon_id,
            s.name as salon_name,
            s.address,
            s.phone,
            s.business_hours
        FROM salons s
        WHERE s.salon_id = :salon_id AND s.status = 'active'
    ");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $salon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$salon) {
        throw new Exception("サロン情報が見つかりません。");
    }
    
    // サロンの設定情報を取得（時間間隔など）
    $stmt = $conn->prepare("
        SELECT setting_key, setting_value
        FROM tenant_settings
        WHERE setting_key = :time_interval_key
    ");
    $time_interval_key = 'salon_' . $salon_id . '_time_interval';
    $stmt->bindParam(':time_interval_key', $time_interval_key);
    $stmt->execute();
    $time_interval_setting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // デフォルト時間間隔（設定がない場合は30分）
    $time_interval = 30;
    
    if ($time_interval_setting && !empty($time_interval_setting['setting_value'])) {
        $time_interval = (int)$time_interval_setting['setting_value'];
        debug_log("サロンID: {$salon_id} の時間間隔設定: {$time_interval}分");
    } else {
        debug_log("サロンID: {$salon_id} の時間間隔設定が見つからないため、デフォルト値（{$time_interval}分）を使用します");
    }
    
    // サロンの営業時間をデータベースから取得
    $stmt = $conn->prepare("
        SELECT 
            day_of_week,
            open_time,
            close_time,
            is_closed
        FROM salon_business_hours
        WHERE salon_id = :salon_id
        ORDER BY day_of_week
    ");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $db_business_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 営業時間の初期化（0: 日曜日 〜 6: 土曜日）
    $business_hours = [];
    
    // データベースから取得した営業時間を設定
    if (!empty($db_business_hours)) {
        foreach ($db_business_hours as $hour) {
            $day = $hour['day_of_week']; // 0-6の曜日表記（0: 日曜日 - 6: 土曜日）
            if ($hour['is_closed']) {
                $business_hours[$day] = ["start" => "", "end" => ""];
            } else {
                $business_hours[$day] = [
                    "start" => substr($hour['open_time'], 0, 5),
                    "end" => substr($hour['close_time'], 0, 5)
                ];
            }
        }
        debug_log("データベースから営業時間を取得しました：" . json_encode($business_hours));
    } else {
        // データベースに情報がない場合はデフォルト値を設定
        debug_log("営業時間データがないためデフォルト値を使用");
        $business_hours = [
            0 => ["start" => "10:00", "end" => "18:00"], // 日曜日
            1 => ["start" => "10:00", "end" => "19:00"], // 月曜日
            2 => ["start" => "10:00", "end" => "19:00"], // 火曜日
            3 => ["start" => "10:00", "end" => "19:00"], // 水曜日
            4 => ["start" => "10:00", "end" => "19:00"], // 木曜日
            5 => ["start" => "10:00", "end" => "19:00"], // 金曜日
            6 => ["start" => "10:00", "end" => "19:00"]  // 土曜日
        ];
    }
    
} catch (Exception $e) {
    error_log("サロン情報取得エラー：" . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: error.php');
    exit;
}

// 選択されたサービス情報の確認
$service_ids = array_column($services, 'service_id');
$service_total_duration = array_sum(array_column($services, 'duration'));
$service_total_price = array_sum(array_column($services, 'price'));

// デバッグ情報
debug_log('予約処理開始...');
debug_log('サロンID: ' . $salon_id);
debug_log('選択サービス: ' . json_encode($services));
debug_log('サービス総時間: ' . $service_total_duration . '分');

// 現在の日付から7日間の日付配列を作成
$dates = [];
$weekdays = ['日', '月', '火', '水', '木', '金', '土']; // 日本語の曜日（0: 日曜日 - 6: 土曜日）
$today = new DateTime();

// 日付オフセットをセッションから取得または初期化
if (isset($_GET['offset'])) {
    $_SESSION['date_offset'] = intval($_GET['offset']);
} elseif (!isset($_SESSION['date_offset'])) {
    $_SESSION['date_offset'] = 0;
}

$date_offset = $_SESSION['date_offset'];
debug_log("現在の日付オフセット: " . $date_offset);

// 表示開始日を計算（オフセットを適用）
$start_date = clone $today;
if ($date_offset > 0) {
    $start_date->modify("+" . $date_offset . " days");
} elseif ($date_offset < 0) {
    $start_date->modify($date_offset . " days");
}

// 開始日と終了日の設定
$earliest_date = $start_date->format('Y-m-d');
$latest_date = (clone $start_date)->modify('+6 days')->format('Y-m-d');

for ($i = 0; $i < 7; $i++) {
    $date = clone $start_date;
    $date->modify("+$i days");
    $dateKey = $date->format('Y-m-d');
    
    // PHPの曜日表記 w = 0(日)〜6(土)
    $day_of_week = (int)$date->format('w');
    
    // 日付が過去かどうかを確認
    $is_past = $date < $today;
    
    $dates[$dateKey] = [
        'date' => $dateKey,
        'day' => $date->format('j'),
        'month' => $date->format('n'),
        'weekday' => $weekdays[$day_of_week], // 日本の曜日表記（0: 日 - 6: 土）
        'is_today' => ($date->format('Y-m-d') === $today->format('Y-m-d')),
        'is_business_day' => isset($business_hours[$day_of_week]) && !empty($business_hours[$day_of_week]['start']),
        'is_past' => $is_past
    ];
}

// スタッフ情報を取得
    $stmt = $conn->prepare("
    SELECT DISTINCT 
        s.staff_id,
        CONCAT(s.first_name, ' ', s.last_name) as name,
        s.color_code,
        s.position
    FROM staff s
    WHERE s.salon_id = :salon_id
    AND s.status = 'active'
    ORDER BY s.staff_id
    ");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
$staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// スタッフIDのみの配列を作成
$staff_ids = array_column($staffs, 'staff_id');
debug_log("取得したスタッフ: " . json_encode($staff_ids));

// スタッフのシフト情報と予約時間枠を取得
$staff_availability = [];
$booked_time_slots = [];

// 予約済み時間枠の情報を取得
    $stmt = $conn->prepare("
    SELECT 
        a.appointment_date,
        a.start_time,
        a.staff_id,
        TIMESTAMPDIFF(MINUTE, a.start_time, a.end_time) as duration
    FROM appointments a
    WHERE a.salon_id = :salon_id
    AND a.status IN ('confirmed', 'pending')
    AND a.appointment_date BETWEEN :start_date AND :end_date
    ");
    $stmt->bindParam(':salon_id', $salon_id);
$stmt->bindParam(':start_date', $earliest_date);
$stmt->bindParam(':end_date', $latest_date);
    $stmt->execute();

    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
// 予約情報を時間枠ごとに整理
    foreach ($appointments as $appointment) {
    $date = $appointment['appointment_date'];
    $time = $appointment['start_time'];
        $staff_id = $appointment['staff_id'];
    $duration = $appointment['duration'];
    
    // 時間枠を30分単位で区切る
    $start_time = new DateTime($time);
    $end_time = clone $start_time;
    $end_time->modify("+" . $duration . " minutes");
    
    $current = clone $start_time;
    
    while ($current < $end_time) {
        $slot = $current->format('H:i');
        $booked_time_slots[$date][$staff_id][$slot] = true;
        $current->modify('+' . $time_interval . ' minutes');  // 設定された時間間隔を使用
    }
}

// 各スタッフ、各日付ごとのシフト情報を確認
foreach ($staff_ids as $staff_id) {
    foreach ($dates as $date => $dateInfo) {
        if (!$dateInfo['is_business_day']) {
            debug_log("日付 {$date} は営業日ではありません");
            continue;
        }
        
        $date_obj = new DateTime($date);
        $day_of_week = (int)$date_obj->format('w'); // 0(日)～6(土)
        
        // その日のシフト情報を確認
        $stmt = $conn->prepare("
            SELECT start_time, end_time 
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
        
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // シフトがある場合のみ処理
        if ($shift) {
            if (isset($business_hours[$day_of_week]) && !empty($business_hours[$day_of_week]['start'])) {
                $salon_open_time = $business_hours[$day_of_week]['start'];
                $salon_close_time = $business_hours[$day_of_week]['end'];
                
                debug_log("曜日 " . $day_of_week . " の営業時間: " . $salon_open_time . " - " . $salon_close_time);
                
                // シフト時間を営業時間に合わせて調整
                $shift_start = max($shift['start_time'], $salon_open_time);
                $shift_end = min($shift['end_time'], $salon_close_time);
                
                $staff_availability[$date][$staff_id] = [
                    'start_time' => $shift_start,
                    'end_time' => $shift_end
                ];
            }
        }
    }
}

// 時間枠生成のための関数
function generateTimeSlots($start_time, $end_time, $service_duration, $interval = 30) {
    global $time_interval; // グローバル変数として時間間隔を取得
    
    // 設定された時間間隔を使用
    $interval = $time_interval;
    
    $time_slots = [];
    
    $current_time = new DateTime($start_time);
    $end_time_obj = new DateTime($end_time);
    
    // サービス時間分を引いて、最終開始可能時間を計算
    $max_start_time = clone $end_time_obj;
    $max_start_time->modify('-' . $service_duration . ' minutes');
    
    while ($current_time <= $max_start_time) {
        $time_key = $current_time->format('H:i');
        $time_slots[] = $time_key;
        $current_time->modify('+' . $interval . ' minutes');
    }
    
    return $time_slots;
}

// 各日付のスタッフ別利用可能時間枠を生成
$available_time_slots = [];
$no_available_slots = true; // 利用可能な時間枠がないかどうかを追跡

foreach ($dates as $date => $day_info) {
    if (!$day_info['is_business_day']) {
        continue;
    }
    
    $day_of_week = (int)(new DateTime($date))->format('w'); // 0(日)～6(土)
    $available_time_slots[$date] = [];
    
    foreach ($staffs as $staff) {
        $staff_id = $staff['staff_id'];
        $available_time_slots[$date][$staff_id] = [];
        
        // 1. スタッフのシフトが設定されている場合はそれを優先
        // 2. シフトがなければサロンの営業時間を使用
        if (isset($staff_availability[$date][$staff_id])) {
            // スタッフのシフトに基づいて時間枠を生成
            $shift_start = $staff_availability[$date][$staff_id]['start_time'];
            $shift_end = $staff_availability[$date][$staff_id]['end_time'];
            
            debug_log("スタッフID " . $staff_id . " の " . $date . " のシフト: " . $shift_start . " - " . $shift_end);
            
            $time_slots_to_check = generateTimeSlots(
                substr($shift_start, 0, 5), 
                substr($shift_end, 0, 5), 
                $service_total_duration
            );
        } else {
            // シフトがない場合はスタッフ不在として空の時間枠を設定
            debug_log("スタッフID " . $staff_id . " の " . $date . " のシフトはありません");
            $time_slots_to_check = [];
        }
        
        // 今日の場合は、現在時刻より後の時間枠のみを利用可能にする
        if ($date === date('Y-m-d')) {
            $current_hour = date('H');
            $current_minute = date('i');
            $filtered_time_slots = [];
            
            foreach ($time_slots_to_check as $time) {
                list($hour, $minute) = explode(':', $time);
                // 現在時刻より1時間後以降の時間枠のみを含める
                if ((int)$hour > ((int)$current_hour + 1) || 
                    ((int)$hour === ((int)$current_hour + 1) && (int)$minute >= (int)$current_minute)) {
                    $filtered_time_slots[] = $time;
                }
            }
            
            $time_slots_to_check = $filtered_time_slots;
            debug_log("今日の利用可能時間枠（現在時刻以降）: " . json_encode($time_slots_to_check));
        }
        
        // 各時間枠について、予約済みでないかチェック
        foreach ($time_slots_to_check as $time) {
            $is_available = true;
            
            // 予約済みスロットかチェック
            $time_obj = new DateTime($time);
            $end_time_obj = clone $time_obj;
            $end_time_obj->modify('+' . $service_total_duration . ' minutes');
            
            $current_check = clone $time_obj;
            
            // 開始時間から終了時間までの全ての30分枠をチェック
            while ($current_check < $end_time_obj) {
                $check_key = $current_check->format('H:i');
                
                if (isset($booked_time_slots[$date][$staff_id][$check_key])) {
                    $is_available = false;
                    break;
                }
                
                $current_check->modify('+30 minutes');
            }
            
            if ($is_available) {
                $available_time_slots[$date][$staff_id][] = $time;
                $no_available_slots = false; // 少なくとも1つの利用可能な時間枠がある
            }
        }
        
        debug_log("スタッフID " . $staff_id . " の " . $date . " の利用可能時間枠: " . 
                 json_encode($available_time_slots[$date][$staff_id]));
    }
}

// 選択中のサービス情報をセッションから取得
$selected_services = $_SESSION['booking_services'] ?? [];

// タイトル設定
$salon_name = $salon['salon_name'] ?? 'サロン';
$page_title = $salon_name . " - 日時選択";

// 追加CSSファイルの設定
$additional_css = ['css/select_datetime.css'];

// 追加JSファイルの設定
$additional_js = ['js/select_datetime.js'];

// アクティブなステップを設定
$active_step = 'datetime';

// ヘッダーを読み込み
include 'includes/header.php';
include 'includes/booking_steps.php';

// 日付に応じた予約枠を取得する処理
if (isset($_GET['date']) && !empty($_GET['date']) && isset($_GET['staff_id'])) {
    $date = $_GET['date'];
    $staff_id = $_GET['staff_id'];
    
    // サービス時間の合計を計算
    $total_duration = array_sum(array_column($selected_services, 'duration'));
    
    // 営業時間を取得
    $hours = getDateBusinessHours($conn, $salon_id, $date);
    
    $time_slots = [];
    
    if (!empty($hours)) {
        // 新しい関数を使って、利用可能な時間枠を取得
        $time_slots = getAvailableTimeSlots($conn, $salon_id, $staff_id, $date, $total_duration);
    }
    
    // JSONとして返す
    header('Content-Type: application/json');
    echo json_encode($time_slots);
    exit;
}

// 営業時間を取得する関数
function getDateBusinessHours($conn, $salon_id, $date) {
    try {
        // 曜日番号を取得 (0=日曜, 1=月曜, ..., 6=土曜)
        $dayOfWeek = date('w', strtotime($date));
        
        // 営業時間を取得
        $stmt = $conn->prepare("
            SELECT open_time, close_time 
            FROM business_hours 
            WHERE salon_id = :salon_id AND day_of_week = :day_of_week
        ");
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->bindParam(':day_of_week', $dayOfWeek);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("営業時間取得エラー: " . $e->getMessage());
        return [];
    }
}

// 休業日かどうかをチェックする関数
function isHoliday($conn, $salon_id, $date) {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM holidays 
            WHERE salon_id = :salon_id 
            AND holiday_date = :date
        ");
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->bindParam(':date', $date);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['count'] > 0);
    } catch (Exception $e) {
        error_log("休業日チェックエラー: " . $e->getMessage());
        return false;
    }
}

// 利用可能な時間帯を取得
function getAvailableTimeSlots($conn, $salon_id, $staff_id, $date, $service_duration) {
    // 営業時間を取得
    $hours = getDateBusinessHours($conn, $salon_id, $date);
    if (empty($hours)) {
        return [];
    }
    
    // 利用可能な時間枠を計算
    $open_time = $hours['open_time'];
    $close_time = $hours['close_time'];
    
    // 時間帯の配列を生成
    $time_slots = [];
    $current_time = $open_time;
    
    while ($current_time < $close_time) {
        // 終了時間を計算
        $end_time_obj = new DateTime($date . ' ' . $current_time);
        $end_time_obj->modify('+' . $service_duration . ' minutes');
        $end_time = $end_time_obj->format('H:i');
        
        // 終了時間が営業時間内かチェック
        if ($end_time <= $close_time) {
            // 予約済みかどうかチェック
            if (isTimeSlotAvailable($conn, $salon_id, $staff_id, $date, $current_time, $end_time)) {
                $time_slots[] = $current_time;
            }
        }
        
        // 15分単位で進める
        $time_obj = new DateTime($date . ' ' . $current_time);
        $time_obj->modify('+15 minutes');
        $current_time = $time_obj->format('H:i');
    }
    
    return $time_slots;
}

// 時間枠が予約可能かチェック（より厳密なチェック）
function isTimeSlotAvailable($conn, $salon_id, $staff_id, $date, $start_time, $end_time) {
    // すでに予約されているかチェック
    $query = "
        SELECT COUNT(*) as overlap_count 
        FROM appointments 
        WHERE staff_id = :staff_id 
        AND appointment_date = :date 
        AND (
            (start_time <= :start_time AND end_time > :start_time) OR
            (start_time < :end_time AND end_time >= :end_time) OR
            (start_time >= :start_time AND end_time <= :end_time)
        )
        AND status NOT IN ('cancelled', 'no_show')
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':staff_id', $staff_id);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':start_time', $start_time);
    $stmt->bindParam(':end_time', $end_time);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 0なら利用可能
    return ($result['overlap_count'] == 0);
}
?>

<div class="booking-container">
    <!-- ヘッダー部分 -->
    <div class="booking-header">
        <h1 class="booking-title">日時を選択</h1>
        <p class="booking-subtitle">ご希望の日付、スタッフ、時間をお選びください</p>
    </div>

    <?php if (DEBUG_MODE): ?>
    <!-- デバッグセクション -->
    <div id="debug-section" class="debug-section mb-4">
        <h5>🐞 デバッグ情報</h5>
        <div>選択した日付: <span id="debug-selected-date">未選択</span></div>
        <div>選択したスタッフ: <span id="debug-selected-staff">未選択</span></div>
        <div>選択した時間: <span id="debug-selected-time">未選択</span></div>
        <div class="mt-2">
            <button onclick="toggleDebugMode()" class="btn btn-sm btn-secondary">デバッグモード切替</button>
            <button onclick="testCalendarClick()" class="btn btn-sm btn-info ml-2">テスト選択実行</button>
            <a href="update_staff_services.php?salon_id=<?= $salon_id ?>" class="btn btn-sm btn-warning ml-2" target="_blank">スタッフ・サービス確認</a>
        </div>
        <div class="mt-2">
            <p><strong>システム情報:</strong></p>
            <ul>
                <li>サービス総時間: <?= $service_total_duration ?>分</li>
                <li>サービス総額: <?= number_format($service_total_price) ?>円</li>
                <li>サロンID: <?= $salon_id ?></li>
                <li>スタッフ数: <?= count($staffs) ?></li>
                <li>営業時間情報: <?= !empty($business_hours) ? '読み込み済み' : '未設定' ?></li>
                <li>シフト情報: <?= !empty($staff_availability) ? '読み込み済み' : '未設定' ?></li>
            </ul>
        </div>
        <div class="mt-2">
            <p><strong>選択可能日付:</strong></p>
            <ul>
                <?php foreach($dates as $dateKey => $dateInfo): ?>
                    <li><?= $dateKey ?> (<?= $dateInfo['weekday'] ?>): <?= $dateInfo['is_business_day'] ? '営業日' : '休業日' ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- エラーメッセージ表示 -->
    <div id="error-message" class="alert alert-danger" style="display: none;">
        <i class="fas fa-exclamation-circle"></i>
        <span id="error-text"></span>
    </div>

    <!-- 日付選択部分 -->
    <section class="booking-section">
        <div class="section-title">
            <h2>来店日時を選択してください</h2>
        </div>
        
        <div class="date-navigation">
            <a href="?offset=<?= $date_offset - 7 ?>" class="date-nav-button prev-button" title="前の週を表示">
                <i class="fas fa-chevron-left"></i> 前へ
            </a>
            <div class="date-range">
                <?= date('Y年n月', strtotime($earliest_date)) ?>
                <?php if (date('m', strtotime($earliest_date)) != date('m', strtotime($latest_date))): ?>
                    - <?= date('n月', strtotime($latest_date)) ?>
                <?php endif; ?>
            </div>
            <a href="?offset=<?= $date_offset + 7 ?>" class="date-nav-button next-button" title="次の週を表示">
                次へ <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        
        <div class="date-tabs">
            <?php if (!empty($dates)): ?>
                <?php foreach ($dates as $dateStr => $dateInfo): ?>
                    <?php 
                        $isDisabled = !$dateInfo['is_business_day'] || $dateInfo['is_past']; 
                        $className = 'date-tab' . ($isDisabled ? ' disabled' : '') . ($dateInfo['is_past'] ? ' past-date' : '');
                    ?>
                    <div class="<?= $className ?>" data-date="<?= $dateStr ?>" data-is-past="<?= $dateInfo['is_past'] ? '1' : '0' ?>">
                        <div class="date-tab-month"><?= $dateInfo['month'] ?>月</div>
                        <div class="date-tab-day"><?= $dateInfo['day'] ?></div>
                        <div class="date-tab-weekday"><?= $dateInfo['weekday'] ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-warning w-100">
                    営業日情報が取得できませんでした。<br>
                    しばらくしてから再度お試しいただくか、お電話でのご予約をお願いいたします。
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- スタッフ選択部分 -->
    <section id="staff-selection" class="booking-section" style="display: none;">
        <div class="section-title">
            <h2>2. スタッフを選択してください</h2>
        </div>
        
        <div class="staff-list">
            <?php if (!empty($staffs)): ?>
                <div class="staff-card" data-staff-id="0">
                    <div class="staff-header">
                        <div class="staff-avatar" style="background-color: #f5f5f5; color: #757575">
                            指定
                        </div>
                        <div class="staff-name">指名なし</div>
                    </div>
                    <div class="staff-details">
                        <div class="available-slots-count">
                            <i class="far fa-clock"></i> どのスタッフでも可
                        </div>
                    </div>
                </div>
                
                <?php foreach ($staffs as $staff): ?>
                    <div class="staff-card" data-staff-id="<?= $staff['staff_id'] ?>">
                        <div class="staff-header">
                            <div class="staff-avatar" style="background-color: #<?= substr(md5($staff['name']), 0, 6); ?>">
                                <?= mb_substr($staff['name'], 0, 1, 'UTF-8'); ?>
                            </div>
                            <div class="staff-name"><?= htmlspecialchars($staff['name']) ?></div>
                                </div>
                        <div class="staff-details">
                            <?php if (DEBUG_MODE): ?>
                            <div class="staff-id">スタッフID: <?= $staff['staff_id'] ?></div>
                            <?php endif; ?>
                            <div class="available-slots-count">
                                <i class="far fa-clock"></i> 指名料 1,000円
                            </div>
                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                <div class="no-staff-message">スタッフが見つかりませんでした。別の日を選択してください。</div>
                                            <?php endif; ?>
                                        </div>
    </section>
    
    <!-- 時間選択部分 -->
    <section id="time-selection" class="booking-section" style="display: none;">
        <div class="section-title">
            <h2>3. 来店日時を選択してください</h2>
        </div>
        
        <div class="time-slot-description">
            <p>ご希望の来店日時を選択してください</p>
                                    </div>
        
        <?php if ($no_available_slots): ?>
        <div class="alert alert-warning" role="alert">
            現在、予約可能な時間枠がありません。別の日付やスタッフを選択するか、お電話でのご予約をお願いいたします。
            <br>
            電話番号: <?= htmlspecialchars($salon['phone'] ?? '未設定') ?>
                            </div>
                        <?php endif; ?>
        
        <div class="time-slot-container">
            <div class="time-slot-wrapper">
                <div class="time-slots-grid" id="time-slots-grid">
                    <!-- JavaScriptで時間枠が表示されます -->
                </div>
            </div>
        </div>
    </section>
    
    <!-- アクションボタン -->
    <div class="action-buttons">
        <a href="select_service.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> 戻る
        </a>
        <button id="next-btn" class="btn btn-primary" disabled>
            次へ <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

<style>
/* 時間枠のグリッド表示用スタイル */
.time-slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
    margin-top: 20px;
}

/* 日付ナビゲーションのスタイル */
.date-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 5px 0;
}

.date-nav-button {
    display: inline-flex;
    align-items: center;
    padding: 8px 15px;
    border-radius: 4px;
    background-color: #f0f0f0;
    color: #333;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s ease;
}

.date-nav-button:hover {
    background-color: var(--primary-light);
    color: var(--primary-color);
}

.date-range {
    font-size: 1.1rem;
    font-weight: 500;
}

/* 過去日付のスタイル */
.date-tab.past-date {
    opacity: 0.6;
    pointer-events: auto; /* クリックは可能にするが視覚的にグレーアウト */
    background-color: #f5f5f5;
    border-color: #ddd;
}

.date-tab.past-date:hover {
    cursor: not-allowed;
}

.date-tab.past-date::after {
    content: "×";
    position: absolute;
    top: 5px;
    right: 5px;
    color: #d32f2f;
    font-size: 0.8rem;
}

.date-tab-month {
    font-size: 0.8rem;
    color: #666;
    margin-bottom: 2px;
}

.time-slot {
    background-color: #f9f9f9;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.time-slot:hover {
    background-color: var(--primary-light);
    border-color: var(--primary-color);
    transform: translateY(-2px);
}

.time-slot.selected {
    background-color: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 3px 5px rgba(0,0,0,0.1);
}

.no-time-slots {
    grid-column: 1 / -1;
    text-align: center;
    padding: 30px;
    background-color: #f9f9f9;
    border-radius: 8px;
    color: var(--text-secondary);
}
</style>

<!-- JavaScriptの変数設定 -->
<script>
// PHPの配列をJavaScriptのグローバル変数として定義
var availableTimeSlots = <?= json_encode($available_time_slots) ?>;
var staffData = <?= json_encode($staffs) ?>;
var dateData = <?= json_encode($dates) ?>;
var serviceData = <?= json_encode($services) ?>;
var calendarDays = <?= json_encode($dates) ?>; // カレンダーデータを追加

// 選択状態を追跡する変数
var selectedDate = null;
var selectedStaffId = null;
var selectedTime = null;

// デバッグモード設定
var debugMode = true;

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM読み込み完了');
    console.log('availableTimeSlots:', availableTimeSlots);
    console.log('staffData:', staffData);
    console.log('dateData:', dateData);
    console.log('serviceData:', serviceData);
    
    // サロンID・スタッフ・営業時間が正しく設定されているか確認
    if (!staffData || staffData.length === 0) {
        showError('スタッフ情報が取得できませんでした。ページを再読み込みするか、別の日付をお試しください。');
        console.error('スタッフデータが空です');
    }
    
    // デバッグセクションの表示設定
    if (debugMode) {
        var debugSection = document.getElementById('debug-section');
        if (debugSection) {
            debugSection.style.display = 'block';
            console.log('デバッグモード有効');
        } else {
            console.error('debug-section要素が見つかりません');
        }
    }
});

// エラー表示関数を追加
function showError(message) {
    console.error('エラー:', message);
    var errorElement = document.getElementById('error-message');
    var errorText = document.getElementById('error-text');
    
    if (errorElement && errorText) {
        errorText.textContent = message;
        errorElement.style.display = 'block';
        setTimeout(function() {
            errorElement.style.display = 'none';
        }, 5000);
    } else {
        alert(message);
    }
}

// 日付選択の処理を更新
document.addEventListener('DOMContentLoaded', function() {
    // 日付タブのクリックイベント
    const dateTabs = document.querySelectorAll('.date-tab');
    dateTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const dateStr = this.getAttribute('data-date');
            const isPast = this.getAttribute('data-is-past') === '1';
            
            // 過去の日付は選択不可
            if (isPast) {
                showError('過去の日付は選択できません');
                return;
            }
            
            // 無効な日付は選択不可
            if (this.classList.contains('disabled')) {
                showError('この日は営業日ではないか、予約できません');
                return;
            }
            
            // 選択状態の更新
            dateTabs.forEach(t => t.classList.remove('selected'));
            this.classList.add('selected');
            selectedDate = dateStr;
            
            // デバッグ表示の更新
            if (document.getElementById('debug-selected-date')) {
                document.getElementById('debug-selected-date').textContent = selectedDate;
            }
            
            // スタッフ選択セクションを表示
            document.getElementById('staff-selection').style.display = 'block';
            
            // 次のステップに進むために選択状態を確認
            checkSelectionStatus();
        });
    });
});

// セレクションのステータス確認関数
function checkSelectionStatus() {
    const nextBtn = document.getElementById('next-btn');
    
    // 日付、スタッフ、時間がすべて選択されているか確認
    if (selectedDate && selectedStaffId && selectedTime) {
        nextBtn.disabled = false;
    } else {
        nextBtn.disabled = true;
    }
}

// 日付をYYYY-MM-DD形式にフォーマット
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// 日付を日本語形式にフォーマット
function formatDateJP(date) {
    const year = date.getFullYear();
    const month = date.getMonth() + 1;
    const day = date.getDate();
    const dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date.getDay()];
    return `${year}年${month}月${day}日(${dayOfWeek})`;
}
</script>

<!-- 外部JavaScriptファイルを読み込み -->
<script src="js/select_datetime.js"></script>

<?php
// フッターを読み込み
include 'includes/footer.php';
?> 
</html> 