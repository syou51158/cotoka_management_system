<?php
// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/AvailableSlot.php';
require_once '../includes/functions.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// セッション開始（セッションがまだ開始されていない場合のみ）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// URLからサロンIDを取得（デフォルトはセッションから）
$salon_id = isset($_GET['salon_id']) ? intval($_GET['salon_id']) : ($_SESSION['booking_salon_id'] ?? 1);

// 選択されたサービスIDを取得（配列）
// POSTとGETの両方からサービスIDを取得
$selected_services = [];
if (isset($_POST['services']) && is_array($_POST['services'])) {
    $selected_services = $_POST['services'];
} elseif (isset($_GET['services'])) {
    $selected_services = $_GET['services'];
} elseif (isset($_SESSION['booking_services']) && !empty($_SESSION['booking_services'])) {
    $selected_services = $_SESSION['booking_services'];
}

// セッションに保存
$_SESSION['booking_salon_id'] = $salon_id;
$_SESSION['booking_services'] = $selected_services;

// サービスが選択されていない場合はサービス選択画面にリダイレクト
if (empty($selected_services)) {
    header('Location: select_service.php?salon_id=' . $salon_id);
    exit;
}

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    $error_message = "データベース接続エラー：" . $e->getMessage();
    exit($error_message);
}

// サロン情報を取得
try {
    $stmt = $conn->prepare("SELECT salon_id, name as salon_name FROM salons WHERE salon_id = :salon_id");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $salon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$salon) {
        throw new Exception("サロンが見つかりません");
    }
} catch (Exception $e) {
    $error_message = "サロン情報取得エラー：" . $e->getMessage();
    exit($error_message);
}

// 選択されたサービスの詳細を取得
$total_duration = 0;
$total_price = 0;
$selected_service_details = [];

try {
    // サービスIDをカンマ区切りの文字列に変換
    $service_ids = implode(',', array_map('intval', $selected_services));
    
    $stmt = $conn->prepare("
        SELECT service_id, name, duration, price 
        FROM services 
        WHERE service_id IN ($service_ids) AND salon_id = :salon_id
    ");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $selected_service_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 合計所要時間と価格を計算
    foreach ($selected_service_details as $service) {
        $total_duration += (int)$service['duration'];
        $total_price += (int)$service['price'];
    }
} catch (Exception $e) {
    $error_message = "サービス詳細取得エラー：" . $e->getMessage();
    exit($error_message);
}

// 日付パラメータ（指定がなければ今日）
$start_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// カレンダー用の日付計算
$today = date('Y-m-d');
$current_date = new DateTime($start_date);
$month_start = clone $current_date;
$month_start->modify('first day of this month');
$month_end = clone $current_date;
$month_end->modify('last day of this month');

// 1ヶ月後の日付（予約可能期間）
$max_date = date('Y-m-d', strtotime('+1 month'));

// 選択された日付のスタッフと空き時間を取得
$selected_date = $current_date->format('Y-m-d');
$available_staff = [];
$date_formatted = '';

// 日本語の曜日
$days_of_week_jp = ['日', '月', '火', '水', '木', '金', '土'];

try {
    // シフト登録されているスタッフを取得
    $stmt = $conn->prepare("
        SELECT s.staff_id, s.first_name, s.last_name, s.color_code, ss.start_time, ss.end_time
        FROM staff_shifts ss
        JOIN staff s ON ss.staff_id = s.staff_id
        WHERE ss.salon_id = :salon_id 
        AND ss.shift_date = :shift_date
        AND ss.status = 'active'
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->bindParam(':shift_date', $selected_date);
    $stmt->execute();
    $available_staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 日付のフォーマット（例: 2023年4月1日（土））
    $date_obj = new DateTime($selected_date);
    $weekday_num = $date_obj->format('w'); // 0（日）～ 6（土）
    $date_formatted = $date_obj->format('Y年n月j日') . '（' . $days_of_week_jp[$weekday_num] . '）';
    
} catch (Exception $e) {
    $error_message = "スタッフ情報取得エラー：" . $e->getMessage();
    exit($error_message);
}

// 選択された日付の予約済み時間を取得
$booked_slots = [];
try {
    $stmt = $conn->prepare("
        SELECT staff_id, start_time, end_time
        FROM appointments
        WHERE salon_id = :salon_id AND appointment_date = :appointment_date
    ");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->bindParam(':appointment_date', $selected_date);
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($appointments as $appointment) {
        $staff_id = $appointment['staff_id'];
        if (!isset($booked_slots[$staff_id])) {
            $booked_slots[$staff_id] = [];
        }
        
        // 予約時間をスロット単位で記録
        $start = strtotime($appointment['start_time']);
        $end = strtotime($appointment['end_time']);
        
        // 30分単位でスロットを作成
        $slot_time = $start;
        while ($slot_time < $end) {
            $slot_key = date('H:i', $slot_time);
            $booked_slots[$staff_id][] = $slot_key;
            $slot_time += 30 * 60; // 30分追加
        }
    }
} catch (Exception $e) {
    $error_message = "予約情報取得エラー：" . $e->getMessage();
    exit($error_message);
}

// 各スタッフの空き時間を計算
$available_slots = [];
foreach ($available_staff as $staff) {
    $staff_id = $staff['staff_id'];
    $shift_start = strtotime($staff['start_time']);
    $shift_end = strtotime($staff['end_time']);
    
    // シフト時間内の30分単位のスロットを生成
    $available_slots[$staff_id] = [];
    $slot_time = $shift_start;
    
    while ($slot_time <= ($shift_end - $total_duration * 60)) {
        $is_available = true;
        $slot_key = date('H:i', $slot_time);
        
        // このスロットと必要時間分のスロットが全て空いているか確認
        $check_time = $slot_time;
        $required_slots = ceil($total_duration / 30); // 必要なスロット数
        
        for ($i = 0; $i < $required_slots; $i++) {
            $check_key = date('H:i', $check_time);
            
            // このスロットが予約済みか確認
            if (isset($booked_slots[$staff_id]) && in_array($check_key, $booked_slots[$staff_id])) {
                $is_available = false;
                break;
            }
            
            // シフト終了時間を超えるか確認
            if ($check_time + 30 * 60 > $shift_end) {
                $is_available = false;
                break;
            }
            
            $check_time += 30 * 60; // 次の30分
        }
        
        if ($is_available) {
            $end_time = $slot_time + $total_duration * 60;
            $available_slots[$staff_id][] = [
                'start' => $slot_key,
                'end' => date('H:i', $end_time),
                'start_timestamp' => $slot_time,
                'end_timestamp' => $end_time
            ];
        }
        
        $slot_time += 30 * 60; // 次の30分スロット
    }
}

$page_title = $salon['salon_name'] . " - 日時選択";
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #e91e63;
            --primary-dark: #c2185b;
            --primary-light: #f48fb1;
            --secondary-color: #f8bbd0;
        }
        
        body {
            font-family: 'Hiragino Kaku Gothic Pro', 'メイリオ', sans-serif;
            background-color: #f8f9fc;
        }
        
        .booking-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 0;
            text-align: center;
        }
        
        .booking-logo {
            max-height: 60px;
            margin-bottom: 10px;
        }
        
        .booking-steps {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            padding: 0;
            list-style: none;
        }
        
        .booking-steps li {
            padding: 10px 15px;
            border-radius: 20px;
            margin: 0 5px;
            background-color: #f1f1f1;
            color: #777;
            font-size: 0.9rem;
        }
        
        .booking-steps li.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .booking-steps li i {
            margin-right: 5px;
        }
        
        .datetime-container {
            margin-bottom: 30px;
        }
        
        .service-summary {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            padding: 20px;
        }
        
        .service-summary h3 {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 15px;
        }
        
        .service-list {
            list-style: none;
            padding: 0;
            margin-bottom: 15px;
        }
        
        .service-list li {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #eee;
        }
        
        .service-total {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #eee;
        }
        
        .calendar-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .calendar-header {
            background-color: var(--primary-dark);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .calendar-title {
            font-size: 1.2rem;
            margin: 0;
        }
        
        .calendar-navigation {
            display: flex;
            align-items: center;
        }
        
        .calendar-navigation button {
            background: none;
            border: none;
            color: white;
            font-size: 1.1rem;
            cursor: pointer;
            padding: 0 10px;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        
        .calendar-navigation button:hover {
            opacity: 1;
        }
        
        .calendar-body {
            padding: 20px;
        }
        
        .calendar-week {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            margin-bottom: 5px;
        }
        
        .calendar-weekday {
            text-align: center;
            font-size: 0.85rem;
            color: #777;
            padding: 5px 0;
        }
        
        .calendar-day {
            text-align: center;
            padding: 10px 0;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .calendar-day.today {
            border: 1px solid var(--primary-color);
        }
        
        .calendar-day.selected {
            background-color: var(--primary-color);
            color: white;
        }
        
        .calendar-day.disabled {
            color: #ccc;
            cursor: not-allowed;
        }
        
        .calendar-day:hover:not(.disabled) {
            background-color: #f5f5f5;
        }
        
        .staff-list {
            padding: 0;
            margin-top: 20px;
        }
        
        .staff-item {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .staff-header {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #eee;
        }
        
        .staff-name {
            font-weight: bold;
            font-size: 1.1rem;
            margin-left: 10px;
        }
        
        .staff-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: bold;
        }
        
        .staff-slots {
            padding: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .time-slot {
            padding: 8px 15px;
            border-radius: 20px;
            border: 1px solid #e0e0e0;
            background-color: white;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        
        .time-slot:hover {
            background-color: #f5f5f5;
        }
        
        .time-slot.selected {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .time-slot.disabled {
            color: #ccc;
            cursor: not-allowed;
        }
        
        .no-slots-message {
            color: #777;
            text-align: center;
            padding: 15px;
            font-style: italic;
        }
        
        .action-buttons {
            position: sticky;
            bottom: 0;
            background-color: white;
            padding: 15px 0;
            box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }
        
        .back-btn {
            color: var(--primary-color);
            background-color: transparent;
            border: 1px solid var(--primary-color);
        }
        
        .back-btn:hover {
            background-color: rgba(233, 30, 99, 0.1);
            color: var(--primary-dark);
        }
        
        .next-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .next-btn:hover {
            background-color: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .next-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        @media (max-width: 767px) {
            .booking-steps {
                flex-direction: column;
                align-items: center;
                margin-bottom: 30px;
            }
            
            .booking-steps li {
                margin-bottom: 10px;
                width: 80%;
                text-align: center;
            }
            
            .calendar-week {
                gap: 2px;
            }
            
            .calendar-day {
                padding: 5px 0;
                font-size: 0.85rem;
            }
            
            .staff-slots {
                gap: 8px;
            }
            
            .time-slot {
                padding: 6px 12px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <header class="booking-header">
        <div class="container">
            <h1 class="m-0">
                <img src="../assets/images/logo.png" alt="<?php echo htmlspecialchars($salon['salon_name']); ?>" class="booking-logo">
                ONLINE BOOKING SERVICE
            </h1>
        </div>
    </header>

    <div class="container py-4">
        <ul class="booking-steps">
            <li><i class="fas fa-list"></i> コース選択</li>
            <li class="active"><i class="far fa-calendar-alt"></i> 日時選択</li>
            <li><i class="fas fa-user"></i> 情報入力</li>
            <li><i class="fas fa-check"></i> 予約確認</li>
            <li><i class="fas fa-check-circle"></i> 予約完了</li>
        </ul>
        
        <form id="dateTimeForm" action="input_info.php" method="post">
            <input type="hidden" name="salon_id" value="<?php echo $salon_id; ?>">
            <input type="hidden" name="selected_date" value="<?php echo $selected_date; ?>">
            <input type="hidden" name="selected_staff_id" id="selectedStaffId" value="">
            <input type="hidden" name="selected_time" id="selectedTime" value="">
            
            <div class="row">
                <div class="col-md-4 order-md-2">
                    <!-- サービス選択内容のサマリー -->
                    <div class="service-summary">
                        <h3>選択内容</h3>
                        <ul class="service-list">
                            <?php foreach ($selected_service_details as $service): ?>
                                <li>
                                    <span><?php echo htmlspecialchars($service['name']); ?></span>
                                    <span>¥<?php echo number_format($service['price']); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="service-total">
                            <span>合計時間: <?php echo $total_duration; ?>分</span>
                            <span>¥<?php echo number_format($total_price); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8 order-md-1">
                    <div class="datetime-container">
                        <h2 class="mb-4">日時を選択してください</h2>
                        
                        <!-- カレンダー -->
                        <div class="calendar-card">
                            <div class="calendar-header">
                                <h3 class="calendar-title"><?php echo $current_date->format('Y年n月'); ?></h3>
                                <div class="calendar-navigation">
                                    <button type="button" id="prevMonth" <?php echo ($current_date->format('Y-m') <= date('Y-m')) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <button type="button" id="nextMonth">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="calendar-body">
                                <div class="calendar-week">
                                    <?php foreach (['日', '月', '火', '水', '木', '金', '土'] as $weekday): ?>
                                        <div class="calendar-weekday"><?php echo $weekday; ?></div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php
                                // 月の初日の曜日を取得（0:日曜日 - 6:土曜日）
                                $first_day_weekday = (int)$month_start->format('w');
                                
                                // 月の日数を取得
                                $days_in_month = (int)$month_end->format('t');
                                
                                // カレンダーの行数を計算
                                $weeks = ceil(($days_in_month + $first_day_weekday) / 7);
                                
                                // カレンダーの日付を表示
                                $day_count = 1;
                                
                                for ($week = 0; $week < $weeks; $week++) {
                                    echo '<div class="calendar-week">';
                                    
                                    for ($i = 0; $i < 7; $i++) {
                                        if (($week == 0 && $i < $first_day_weekday) || ($day_count > $days_in_month)) {
                                            // 空のセル
                                            echo '<div class="calendar-day"></div>';
                                        } else {
                                            $date = $current_date->format('Y-m') . '-' . str_pad($day_count, 2, '0', STR_PAD_LEFT);
                                            $is_today = $date == date('Y-m-d');
                                            $is_selected = $date == $selected_date;
                                            $is_past = $date < $today;
                                            $is_future = $date > $max_date;
                                            
                                            $classes = ['calendar-day'];
                                            if ($is_today) $classes[] = 'today';
                                            if ($is_selected) $classes[] = 'selected';
                                            if ($is_past || $is_future) $classes[] = 'disabled';
                                            
                                            echo '<div class="' . implode(' ', $classes) . '" data-date="' . $date . '">' . $day_count . '</div>';
                                            
                                            $day_count++;
                                        }
                                    }
                                    
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <!-- 選択した日付の表示 -->
                        <h4 class="mb-3"><?php echo $date_formatted; ?>の予約枠</h4>
                        
                        <?php if (empty($available_staff)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                この日はスタッフがシフトに入っていないため、予約できません。
                            </div>
                        <?php else: ?>
                            <!-- スタッフと時間枠の表示 -->
                            <div class="staff-list">
                                <?php foreach ($available_staff as $staff): ?>
                                    <?php
                                    $staff_id = $staff['staff_id'];
                                    $has_slots = !empty($available_slots[$staff_id]);
                                    ?>
                                    <div class="staff-item">
                                        <div class="staff-header">
                                            <div class="staff-avatar" style="background-color: <?php echo $staff['color_code'] ?: '#4e73df'; ?>">
                                                <?php echo mb_substr($staff['last_name'], 0, 1); ?>
                                            </div>
                                            <div class="staff-name"><?php echo htmlspecialchars($staff['last_name'] . ' ' . $staff['first_name']); ?></div>
                                        </div>
                                        
                                        <div class="staff-slots">
                                            <?php if ($has_slots): ?>
                                                <?php foreach ($available_slots[$staff_id] as $slot): ?>
                                                    <div class="time-slot" 
                                                         data-staff-id="<?php echo $staff_id; ?>" 
                                                         data-time="<?php echo $slot['start']; ?>">
                                                        <?php echo $slot['start']; ?>～<?php echo $slot['end']; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="no-slots-message">空き枠がありません</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons text-center">
                <a href="select_service.php?salon_id=<?php echo $salon_id; ?>" class="btn back-btn mr-2">
                    <i class="fas fa-chevron-left mr-1"></i> 戻る
                </a>
                <button type="submit" class="btn next-btn" id="nextBtn" disabled>
                    次へ進む <i class="fas fa-chevron-right ml-1"></i>
                </button>
            </div>
        </form>
    </div>

    <footer class="bg-light py-3 mt-5">
        <div class="container">
            <div class="text-center">
                <small>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($salon['salon_name']); ?> All Rights Reserved.</small>
            </div>
        </div>
    </footer>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // 日付選択のクリックイベント
            $('.calendar-day:not(.disabled)').on('click', function() {
                const date = $(this).data('date');
                if (date) {
                    window.location.href = 'select_datetime.php?salon_id=<?php echo $salon_id; ?>&date=' + date + '&services[]=<?php echo implode('&services[]=', $selected_services); ?>';
                }
            });
            
            // 前月ボタンのクリックイベント
            $('#prevMonth').on('click', function() {
                if ($(this).prop('disabled')) return;
                
                const currentDate = new Date('<?php echo $current_date->format('Y-m-01'); ?>');
                currentDate.setMonth(currentDate.getMonth() - 1);
                const newDate = currentDate.toISOString().slice(0, 10);
                
                window.location.href = 'select_datetime.php?salon_id=<?php echo $salon_id; ?>&date=' + newDate + '&services[]=<?php echo implode('&services[]=', $selected_services); ?>';
            });
            
            // 次月ボタンのクリックイベント
            $('#nextMonth').on('click', function() {
                const currentDate = new Date('<?php echo $current_date->format('Y-m-01'); ?>');
                currentDate.setMonth(currentDate.getMonth() + 1);
                const newDate = currentDate.toISOString().slice(0, 10);
                
                window.location.href = 'select_datetime.php?salon_id=<?php echo $salon_id; ?>&date=' + newDate + '&services[]=<?php echo implode('&services[]=', $selected_services); ?>';
            });
            
            // 時間枠選択のクリックイベント
            $('.time-slot').on('click', function() {
                $('.time-slot').removeClass('selected');
                $(this).addClass('selected');
                
                const staffId = $(this).data('staff-id');
                const time = $(this).data('time');
                
                $('#selectedStaffId').val(staffId);
                $('#selectedTime').val(time);
                
                // 次へボタンを有効化
                $('#nextBtn').prop('disabled', false);
            });
            
            // フォーム送信時の処理
            $('#dateTimeForm').on('submit', function(e) {
                const staffId = $('#selectedStaffId').val();
                const time = $('#selectedTime').val();
                
                if (!staffId || !time) {
                    e.preventDefault();
                    alert('スタッフと時間を選択してください');
                    return false;
                }
                
                return true;
            });
        });
    </script>
</body>
</html> 