<?php
// 必要なファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'includes/functions.php';
require_once 'includes/auth_middleware.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// ログインしていない場合はログインページにリダイレクト
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ヘッダーを読み込み
require_once 'includes/header.php';

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    $error_message = "データベース接続エラー：" . $e->getMessage();
    echo '<div class="alert alert-danger m-3">' . $error_message . '</div>';
    require_once 'includes/footer.php';
    exit;
}

// 現在のサロンIDを取得
$salon_id = isset($_SESSION['salon_id']) ? $_SESSION['salon_id'] : 1;

// 現在のテナントIDを取得
$tenant_id = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : 1;

// パラメータから日付を取得（指定がなければ今日の日付）
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// 曜日の日本語名配列
$days_of_week_jp = ['日', '月', '火', '水', '木', '金', '土'];
$selected_day_of_week = date('w', strtotime($selected_date));
$formatted_date = date('Y年n月j日', strtotime($selected_date)) . '（' . $days_of_week_jp[$selected_day_of_week] . '）';
// 予約台帳のヘッダーで使用するフォーマット
$selected_date_formatted = $formatted_date;

// 店舗設定から営業時間と時間間隔を取得（Supabase RPC）
// デフォルト値
$opening_time = '09:00:00';
$closing_time = '19:00:00';
$time_interval = 30;

// 営業時間
$bhRes = supabaseRpcCall('salon_business_hours_get', ['p_salon_id' => (int)$salon_id]);
if ($bhRes['success']) {
    $rows = is_array($bhRes['data']) ? $bhRes['data'] : [];
    foreach ($rows as $row) {
        // day_of_weekが一致する行を採用（0=日〜6=土の前提）
        if ((int)($row['day_of_week'] ?? -1) === (int)$selected_day_of_week) {
            if (!($row['is_closed'] ?? false)) {
                $opening_time = $row['open_time'] ?? $opening_time;
                $closing_time = $row['close_time'] ?? $closing_time;
            }
            break;
        }
    }
}

// 時間間隔
$tsRes = supabaseRpcCall('salon_time_settings_get', ['p_salon_id' => (int)$salon_id]);
if ($tsRes['success']) {
    $row = is_array($tsRes['data']) ? ($tsRes['data'][0] ?? null) : null;
    if ($row) {
        $time_interval = (int)($row['time_interval_minutes'] ?? ($row['time_interval'] ?? $time_interval));
    }
}

// 営業開始時間から時間と分を抽出（予約の時間計算用）
$opening_hour = (int)substr($opening_time, 0, 2);
$opening_minute = (int)substr($opening_time, 3, 2);

// 営業時間の総分数
$total_operational_minutes = (strtotime($closing_time) - strtotime($opening_time)) / 60;

// 時間枠を生成
$time_slots = [];

// 5分単位や10分単位の場合、開始時間の前も表示するために、
// 時間の単位が小さい場合は、営業開始時間の前の時間スロットも1つ追加する
$start_before = false;
$before_opening_time = strtotime($opening_time) - ($time_interval * 60);
$before_opening_time_formatted = date('H:i', $before_opening_time);

// 時間間隔が30分以下の場合のみ、営業開始前の時間スロットを追加
if ($time_interval <= 30) {
    $time_slots[] = $before_opening_time_formatted;
    $start_before = true;
}

$start_time = strtotime($opening_time);
$end_time = strtotime($closing_time);

// 営業終了時間も含めて表示するために終了時間を調整
$adjusted_end_time = $end_time;
$last_slot_time = 0;

while ($start_time <= $adjusted_end_time) {
    $current_time = date('H:i', $start_time);
    $time_slots[] = $current_time;
    $last_slot_time = $start_time;
    $start_time += $time_interval * 60;
}

// 最後の時間枠が営業終了時間と一致していない場合は、営業終了時間も追加
$last_time = end($time_slots);
$closing_time_formatted = date('H:i', strtotime($closing_time));
if ($last_time !== $closing_time_formatted) {
    $time_slots[] = $closing_time_formatted;
}

// 営業時間の開始・終了時間（フォーマット済み）
$opening_time_formatted = date('H:i', strtotime($opening_time));
$closing_time_formatted = date('H:i', strtotime($closing_time));

// 作業スタッフ（当日の予約・業務に登場するスタッフを採用）
$working_staff = [];
$has_staff_shifts = false;

// 予約・業務データを取得（Supabase RPC）
$appointments = [];
$rpcAppts = supabaseRpcCall('appointments_list_with_tasks', [
    'p_salon_id' => (int)$salon_id,
    'p_start_date' => $selected_date,
    'p_end_date' => $selected_date
]);
if ($rpcAppts['success']) {
    $appointments = is_array($rpcAppts['data']) ? $rpcAppts['data'] : [];
    // 当日のみ対象のため、開始時間で整列
    usort($appointments, function($a, $b) {
        return strtotime($a['start_time'] ?? '00:00:00') <=> strtotime($b['start_time'] ?? '00:00:00');
    });
}

// 予約から稼働スタッフを構築（シフト代替）
$staffIdToInfo = [];
foreach ($appointments as $appt) {
    if (!empty($appt['staff_id'])) {
        $sid = (int)$appt['staff_id'];
        if (!isset($staffIdToInfo[$sid])) {
            $staffIdToInfo[$sid] = [
                'staff_id' => $sid,
                'first_name' => $appt['staff_first_name'] ?? '',
                'last_name' => $appt['staff_last_name'] ?? '',
                'color_code' => '#4e73df',
                'start_time' => $appt['start_time'] ?? $opening_time,
                'end_time' => $appt['end_time'] ?? $closing_time,
                'shift_id' => null,
                'shift_date' => $selected_date
            ];
        } else {
            // シフト時間を予約の範囲で拡張
            $existingStart = $staffIdToInfo[$sid]['start_time'];
            $existingEnd = $staffIdToInfo[$sid]['end_time'];
            $staffIdToInfo[$sid]['start_time'] = min($existingStart, ($appt['start_time'] ?? $existingStart));
            $staffIdToInfo[$sid]['end_time'] = max($existingEnd, ($appt['end_time'] ?? $existingEnd));
        }
    }
}
$working_staff = array_values($staffIdToInfo);
$has_staff_shifts = !empty($working_staff);

// 予約をスタッフごとに整理
$staff_appointments = [];
foreach ($appointments as $appointment) {
    $staff_id = $appointment['staff_id'];
    if (!isset($staff_appointments[$staff_id])) {
        $staff_appointments[$staff_id] = [];
    }
    $staff_appointments[$staff_id][] = $appointment;
}

// ページのタイトル設定
$page_title = "予約台帳";
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?> - COTOKA管理システム</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- jQuery UI CSS -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <!-- 予約台帳用カスタムCSS -->
    <link rel="stylesheet" href="assets/css/appointment_ledger.css?v=<?php echo time(); ?>">
    
   
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-md-7 col-sm-12">
                <h1 class="h3 mb-0 text-gray-800">予約台帳</h1>
                <p class="mb-0 text-muted"><?php echo $formatted_date; ?></p>
            </div>
            <div class="col-md-5 col-sm-12">
                <div class="d-flex justify-content-end mb-2 flex-wrap">
                    <div class="btn-group d-none d-md-flex">
                        <a href="?date=<?php echo date('Y-m-d', strtotime('-1 day', strtotime($selected_date))); ?>" class="btn btn-outline-primary">
                            <i class="fas fa-chevron-left"></i> 前日
                        </a>
                        <a href="?date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-primary">今日</a>
                        <a href="?date=<?php echo date('Y-m-d', strtotime('+1 day', strtotime($selected_date))); ?>" class="btn btn-outline-primary">
                            翌日 <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                    <!-- スマホ用の日付ナビゲーション -->
                    <div class="d-flex d-md-none w-100 mb-2 justify-content-between">
                        <a href="?date=<?php echo date('Y-m-d', strtotime('-1 day', strtotime($selected_date))); ?>" class="btn btn-outline-primary">
                            <i class="fas fa-chevron-left"></i> 前日
                        </a>
                        <a href="?date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-primary">今日</a>
                        <a href="?date=<?php echo date('Y-m-d', strtotime('+1 day', strtotime($selected_date))); ?>" class="btn btn-outline-primary">
                            翌日 <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                    <button type="button" class="btn btn-primary ml-2 mb-2" data-toggle="modal" data-target="#datePickerModal">
                        <i class="fas fa-calendar-alt"></i> <span class="d-none d-md-inline">日付選択</span>
                    </button>
                </div>
                <div class="d-flex justify-content-end flex-wrap">
                    <div class="d-md-flex d-none">
                        <!-- 予約追加と業務追加を1つのドロップダウンに統合 -->
                        <div class="dropdown mb-2">
                            <button class="btn btn-success dropdown-toggle" type="button" id="addDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-plus"></i> 追加
                            </button>
                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="addDropdown">
                                <a class="dropdown-item" href="#" id="addAppointmentBtn"><i class="fas fa-user-clock"></i> 予約追加</a>
                                <a class="dropdown-item" href="#" id="addTaskBtn"><i class="fas fa-tasks"></i> 業務追加</a>
                            </div>
                        </div>
                        <div class="dropdown ml-2">
                            <button class="btn btn-secondary dropdown-toggle mb-2" type="button" id="optionsDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-cog"></i> オプション
                            </button>
                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="optionsDropdown">
                                <a class="dropdown-item" href="#" id="refreshBtn"><i class="fas fa-sync-alt"></i> 更新</a>
                                <a class="dropdown-item" href="#" id="printBtn"><i class="fas fa-print"></i> 印刷</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" id="settingsBtn"><i class="fas fa-wrench"></i> 表示設定</a>
                            </div>
                        </div>
                    </div>
                    <!-- スマホ用のボタングループ -->
                    <div class="mobile-btn-group d-md-none w-100">
                        <!-- モバイル版の予約追加と業務追加ボタンの統合 -->
                        <div class="dropdown w-100 mb-2">
                            <button class="btn btn-success dropdown-toggle w-100" type="button" id="addDropdownMobile" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-plus"></i> 追加
                            </button>
                            <div class="dropdown-menu dropdown-menu-right w-100" aria-labelledby="addDropdownMobile">
                                <a class="dropdown-item" href="#" id="addAppointmentBtnMobile"><i class="fas fa-user-clock"></i> 予約追加</a>
                                <a class="dropdown-item" href="#" id="addTaskBtnMobile"><i class="fas fa-tasks"></i> 業務追加</a>
                            </div>
                        </div>
                        <button class="btn btn-secondary dropdown-toggle w-100" type="button" id="optionsDropdownMobile" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-cog"></i> オプション
                        </button>
                        <div class="dropdown-menu dropdown-menu-right w-100" aria-labelledby="optionsDropdownMobile">
                            <a class="dropdown-item" href="#" id="refreshBtnMobile"><i class="fas fa-sync-alt"></i> 更新</a>
                            <a class="dropdown-item" href="#" id="printBtnMobile"><i class="fas fa-print"></i> 印刷</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#" id="settingsBtnMobile"><i class="fas fa-wrench"></i> 表示設定</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">予約タイムテーブル</h6>
                        <div>
                            <!-- 表示スタッフ数 -->
                            <span class="badge badge-pill badge-info mr-2">スタッフ: <?php echo count($working_staff); ?>人</span>
                            <!-- 表示モード表示 -->
                            <span class="badge badge-pill badge-primary view-mode-badge">標準表示</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- デバッグ情報 -->
                        <div class="alert alert-secondary" id="debug-info">
                            <p><strong>デバッグ情報:</strong></p>
                            <p>選択日: <?php echo $selected_date; ?></p>
                            <p>スタッフ数: <?php echo count($working_staff); ?></p>
                            <p>時間枠数: <?php echo count($time_slots); ?></p>
                            <p>予約数: <?php echo count($appointments); ?></p>
                            <p>時間間隔: <?php echo $time_interval; ?>分</p>
                            <button id="toggleDebug" class="btn btn-sm btn-secondary">デバッグ情報を隠す</button>
                        </div>
                        
                        <!-- 隠しパラメーター -->
                        <input type="hidden" id="selected-date-value" value="<?php echo $selected_date; ?>">
                        <input type="hidden" id="opening-time" value="<?php echo substr($opening_time, 0, 5); ?>">
                        <input type="hidden" id="closing-time" value="<?php echo substr($closing_time, 0, 5); ?>">
                        <input type="hidden" id="time-interval" value="<?php echo $time_interval; ?>">
                        <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

                        <?php if (empty($working_staff)) : ?>
                            <div class="alert alert-warning text-center my-4">
                                <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                                <h4><strong><?php echo $formatted_date; ?>のシフトが設定されていません</strong></h4>
                                <p>この日に勤務予定のスタッフがいないため、予約を受け付けることができません。</p>
                                <p>スタッフ管理画面からシフトを設定してください。</p>
                                <a href="staff_shifts.php" class="btn btn-primary mt-2">
                                    <i class="fas fa-calendar-alt mr-1"></i> シフト管理へ
                                </a>
                            </div>
                        <?php else : ?>
                            <div class="timetable-container">
                                <div class="timetable-header d-flex justify-content-between align-items-center">
                                    <h2 class="table-title mb-0"><?php echo $selected_date_formatted; ?>の予約</h2>
                                    <a href="staff_shifts.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-calendar-alt mr-1"></i> シフト管理
                                    </a>
                                </div>
                                
                                <!-- 現在時刻インジケーター -->
                                <div id="current-time-indicator"></div>
                                <div id="current-time-label"></div>
                                
                                <div class="timetable-scroll-wrapper">
                                    <table class="timetable">
                                        <thead>
                                            <tr>
                                                <th class="timetable-time">時間</th>
                                                <?php foreach ($working_staff as $staff) : 
                                                    // シフト時間をフォーマット
                                                    $shift_start = date('H:i', strtotime($staff['start_time']));
                                                    $shift_end = date('H:i', strtotime($staff['end_time']));
                                                ?>
                                                    <th class="staff-header" style="border-left: 4px solid <?php echo $staff['color_code'] ?: '#4e73df'; ?>">
                                                        <div class="staff-name"><?php echo htmlspecialchars($staff['last_name'] . ' ' . $staff['first_name']); ?></div>
                                                        <div class="staff-shift-time small text-muted">
                                                            <i class="far fa-clock mr-1"></i><?php echo $shift_start; ?>-<?php echo $shift_end; ?>
                                                        </div>
                                                    </th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($time_slots as $index => $time_slot) : ?>
                                                <?php 
                                                // 営業時間外（営業開始前または営業終了時間）かどうかを判定
                                                $is_before_opening = ($start_before && $index === 0);
                                                $is_closing_time = ($index === count($time_slots) - 1) || ($time_slot === $closing_time_formatted);
                                                $is_business_hours_outside = $is_before_opening || $is_closing_time;
                                                
                                                // スタイルクラス
                                                $time_cell_class = $is_business_hours_outside ? 'closing-time-cell' : '';
                                                $time_header_class = $is_business_hours_outside ? 'closing-time' : '';
                                                ?>
                                                <tr>
                                                    <td class="timetable-time <?php echo $time_header_class; ?>">
                                                        <span><?php echo $time_slot; ?></span>
                                                    </td>
                                                    <?php foreach ($working_staff as $staff_index => $staff) : 
                                                        $staff_id = $staff['staff_id']; 
                                                        $cell_id = 'cell-' . $staff_id . '-' . str_replace(':', '-', $time_slot);
                                                        
                                                        // スタッフのシフト時間を取得
                                                        $staff_shift_start = date('H:i', strtotime($staff['start_time']));
                                                        $staff_shift_end = date('H:i', strtotime($staff['end_time']));
                                                        
                                                        // 現在の時間枠がスタッフのシフト時間内かどうかを判定
                                                        $is_within_shift = ($time_slot >= $staff_shift_start && $time_slot < $staff_shift_end);
                                                        
                                                        // セルのクラス設定
                                                        $cell_classes = ['time-cell'];
                                                        if ($time_cell_class) $cell_classes[] = $time_cell_class;
                                                        if (!$is_within_shift) $cell_classes[] = 'outside-shift';
                                                        $cell_class_str = implode(' ', $cell_classes);
                                                    ?>
                                                        <td class="<?php echo $cell_class_str; ?>" 
                                                            id="<?php echo $cell_id; ?>"
                                                            data-staff-id="<?php echo $staff_id; ?>" 
                                                            data-time-slot="<?php echo $time_slot; ?>"
                                                            <?php if (!$is_within_shift) : ?>
                                                            data-outside-shift="true"
                                                            <?php endif; ?>>
                                                            
                                                            <?php if (substr($time_slot, 3, 2) == '30') : ?>
                                                                <div class="half-hour-line"></div>
                                                            <?php endif; ?>
                                                            
                                                            <?php 
                                                            // シフト時間内の場合のみ予約を表示
                                                            if ($is_within_shift) {
                                                                // この時間枠とスタッフの予約を探す
                                                                if (isset($staff_appointments[$staff_id])) {
                                                                    foreach ($staff_appointments[$staff_id] as $appointment) {
                                                                        $start_time = substr($appointment['start_time'], 0, 5);
                                                                        $end_time = substr($appointment['end_time'], 0, 5);
                                                                        
                                                                        // 現在の時間枠が予約の開始時間と一致する場合のみ表示
                                                                        if ($start_time === $time_slot) {
                                                                            // 開始時間と終了時間を分単位に変換して差分を計算
                                                                            list($start_hour, $start_minute) = explode(':', $start_time);
                                                                            list($end_hour, $end_minute) = explode(':', $end_time);
                                                                            
                                                                            // 開始時間と終了時間を分単位に変換して差分を計算
                                                                            $start_minutes_total = ((int)$start_hour * 60) + (int)$start_minute;
                                                                            $end_minutes_total = ((int)$end_hour * 60) + (int)$end_minute;
                                                                            $duration_minutes = $end_minutes_total - $start_minutes_total;
                                                                            
                                                                            // 予約の高さを計算（時間間隔に基づいて）
                                                                            $duration_cells = ceil($duration_minutes / $time_interval);
                                                                            $cell_count = $duration_cells - 1; // 開始セルを含むため-1
                                                                            $height_style = "height: calc(" . (($cell_count * 100) + 100) . "% + " . $cell_count . "px);";
                                                                            
                                                                            // 各予約タイプに応じたクラスを設定
                                                                            $appointment_class = 'appointment-item ' . $appointment['appointment_type'];
                                                                            $is_confirmed = isset($appointment['is_confirmed']) ? $appointment['is_confirmed'] : 0;
                                                                            
                                                                            if ($is_confirmed) {
                                                                                $appointment_class .= ' confirmed';
                                                                            }
                                                                            if (isset($appointment['status'])) {
                                                                                if ($appointment['status'] == 'cancelled') {
                                                                                    $appointment_class .= ' cancelled';
                                                                                }
                                                                                if ($appointment['status'] == 'no-show') {
                                                                                    $appointment_class .= ' no-show';
                                                                                }
                                                                            }
                                                                            
                                                                            // 顧客名とサービス名の表示内容を設定
                                                                            if ($appointment['appointment_type'] == 'customer') {
                                                                                $customer_name = isset($appointment['customer_last_name']) && isset($appointment['customer_first_name']) 
                                                                                    ? htmlspecialchars($appointment['customer_last_name'] . ' ' . $appointment['customer_first_name'])
                                                                                    : '未設定の顧客';
                                                                                $service_name = isset($appointment['service_name']) 
                                                                                    ? htmlspecialchars($appointment['service_name'])
                                                                                    : '未設定のサービス';
                                                                            } else {
                                                                                $customer_name = $appointment['appointment_type'] == 'task' ? '業務' : '休憩';
                                                                                $service_name = isset($appointment['task_description']) 
                                                                                    ? htmlspecialchars($appointment['task_description'])
                                                                                    : '未設定の業務';
                                                                            }
                                                                            ?>
                                                                            <div class="<?php echo $appointment_class; ?>"
                                                                                 style="<?php echo $height_style; ?>" 
                                                                                 data-appointment-id="<?php echo $appointment['appointment_id']; ?>"
                                                                                 data-staff-id="<?php echo $staff_id; ?>"
                                                                                 data-staff-index="<?php echo $staff_index; ?>"
                                                                                 data-duration-cells="<?php echo $duration_cells; ?>">
                                                                                <div class="appointment-customer"><?php echo $customer_name; ?></div>
                                                                                <div class="appointment-service"><?php echo $service_name; ?></div>
                                                                                <div class="appointment-time"><?php echo $start_time; ?>～<?php echo $end_time; ?></div>
                                                                            </div>
                                                                            <?php
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                            ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 表示モード切替ボタン -->
    <button type="button" class="view-mode-toggle" id="viewModeToggle" title="表示切替">
        <i class="fas fa-expand-alt"></i>
    </button>

    <!-- 日付選択モーダル -->
    <div class="modal fade" id="datePickerModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">日付を選択</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="datePickerForm" action="appointment_ledger.php" method="get">
                        <div class="form-group">
                            <input type="date" class="form-control" name="date" value="<?php echo $selected_date; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">選択した日付に移動</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">キャンセル</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 予約追加モーダル -->
    <div class="modal fade" id="addAppointmentModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">予約追加</h5>
                    <button type="button" class="close" id="closeModalBtn" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="addAppointmentForm" action="api/create_appointment.php" method="post">
                        <input type="hidden" name="appointment_date" value="<?php echo $selected_date; ?>">
                        <input type="hidden" name="appointment_type" id="appointment_type" value="customer">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="appointment_action" id="appointment_action" value="create">
                        <input type="hidden" name="appointment_id" id="appointment_id" value="">
                        
                        <div class="form-group">
                            <label for="staff_id">担当スタッフ</label>
                            <select class="form-control" id="staff_id" name="staff_id" required>
                                <option value="">選択してください</option>
                                <?php foreach ($working_staff as $staff) : ?>
                                    <option value="<?php echo $staff['staff_id']; ?>">
                                        <?php echo htmlspecialchars($staff['last_name'] . ' ' . $staff['first_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="start_time">開始時間</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="end_time">終了時間</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                            </div>
                        </div>
                        
                        <div id="customer_section">
                            <div class="form-group">
                                <label for="customer_id">お客様</label>
                                <select class="form-control" id="customer_id" name="customer_id">
                                    <option value="">選択してください</option>
                                    <!-- ここにAjaxでロードする顧客リスト -->
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="service_id">施術内容</label>
                                <select class="form-control" id="service_id" name="service_id">
                                    <option value="">選択してください</option>
                                    <!-- ここにAjaxでロードするサービスリスト -->
                                </select>
                            </div>
                        </div>
                        
                        <div id="task_section" style="display: none;">
                            <div class="form-group">
                                <label for="task_description">業務内容</label>
                                <input type="text" class="form-control" id="task_description" name="task_description" placeholder="業務内容を入力">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">備考</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">登録</button>
                            <button type="button" class="btn btn-secondary modal-cancel-btn" id="cancelModalBtn">キャンセル</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 予約詳細モーダル -->
    <div class="modal fade" id="appointmentDetailsModal" tabindex="-1" role="dialog" aria-labelledby="appointmentDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="appointmentDetailsModalLabel">予約詳細</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="閉じる">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- 予約詳細はJavaScriptで動的に生成 -->
                </div>
                <div class="modal-footer">
                    <!-- 操作ボタンはJavaScriptで動的に生成 -->
                </div>
            </div>
        </div>
    </div>

    <!-- コンテンツの最後にフローティング情報と操作ヒントを追加 -->
    <div id="selection-info" style="display:none;"></div>
    <div class="mobile-tap-hint">セルを選択→もう一度タップで予約追加</div>
    <div class="tap-again-hint bouncing">もう一度タップ</div>

    <!-- 削除確認モーダル -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">削除確認</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="閉じる">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>この予約を削除してもよろしいですか？</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-danger confirm-delete-btn" data-id="" data-is-task="false">削除する</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Bootstrap JS & Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- jQuery UI JS -->
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <!-- 予約台帳用カスタムJS -->
    <script src="assets/js/appointment_ledger.js?v=<?php echo time(); ?>"></script>
    
    <!-- モーダルの初期化 -->
    <script>
        // デバッグ情報の表示/非表示
        $(function() {
            // ブラウザコンソールログを表示するための関数
            function checkJsEnvironment() {
                console.log('============ ブラウザ環境チェック ============');
                // jQuery/UI のバージョンチェック
                console.log('jQuery バージョン:', $.fn.jquery);
                if ($.ui) {
                    console.log('jQuery UI バージョン:', $.ui.version);
                } else {
                    console.error('jQuery UI が読み込まれていません');
                }
                
                // APIエンドポイントをチェック
                console.log('予約ステータス更新APIエンドポイント:', './api/appointments/update_status.php');
                
                // 予約アイテムの数をチェック
                const appointmentItems = $('.appointment-item').length;
                console.log('予約/業務アイテム数:', appointmentItems);
                
                // 最初の予約アイテムの詳細を表示（存在する場合）
                if (appointmentItems > 0) {
                    const $firstItem = $('.appointment-item').first();
                    console.log('最初の予約アイテム:');
                    console.log('- クラス:', $firstItem.attr('class'));
                    console.log('- ID:', $firstItem.data('appointment-id'));
                    console.log('- スタイル:', $firstItem.attr('style'));
                    console.log('- HTML:', $firstItem.html());
                    console.log('- クリックイベント:', typeof $._data === 'function' ? $._data($firstItem[0], 'events') : '確認不可');
                }
                
                // クリックイベントが適切に設定されているか確認
                console.log('ドキュメントイベント:', typeof $._data === 'function' ? $._data(document, 'events') : 'イベント確認不可');
                console.log('============================================');
                
                // デバッグ: モーダルボタンイベントチェック
                console.log('モーダルボタンデバッグ:');
                setTimeout(function() {
                    // モーダル要素内の編集ボタンの数をチェック
                    console.log('編集ボタン数:', $('.edit-appointment-btn').length);
                    console.log('確定ボタン数:', $('.confirm-appointment-btn').length);
                    console.log('キャンセルボタン数:', $('.cancel-appointment-btn').length);
                    console.log('削除ボタン数:', $('.delete-appointment-btn').length);
                    
                    // ボタンクリックハンドラが設定されているかチェック
                    console.log('編集ボタンイベント:', typeof $._data === 'function' ? $._data($('.edit-appointment-btn')[0], 'events') : '確認不可');
                    console.log('確定ボタンイベント:', typeof $._data === 'function' ? $._data($('.confirm-appointment-btn')[0], 'events') : '確認不可');
                    console.log('キャンセルボタンイベント:', typeof $._data === 'function' ? $._data($('.cancel-appointment-btn')[0], 'events') : '確認不可');
                    
                    // document委任イベントをチェック
                    console.log('document委任イベント:', $._data);
                    
                    // CSRFトークンを表示
                    console.log('CSRFトークン:', $('#csrf_token').val());
                }, 3000);
            }
            
            // デバッグ情報があれば表示
            var content = $('#debug-info');
            $('#toggleDebug').click(function() {
                if (content.is(':visible')) {
                    content.hide();
                    $(this).text('デバッグ情報を表示');
                } else {
                    content.show();
                    $(this).text('デバッグ情報を隠す');
                    
                    // デバッグ情報表示時に環境チェックも実行
                    checkJsEnvironment();
                }
            });
            
            // ページ読み込み時に一度チェック実行
            setTimeout(checkJsEnvironment, 1000);
            
            // スマホ表示時、予約台帳ページの余分なマージンやパディングを調整
            function optimizeAppointmentLedgerForMobile() {
                if (window.innerWidth <= 767) {
                    // ページ全体を画面いっぱいに表示するための調整
                    $('body').css({
                        'padding': '0',
                        'margin': '0',
                        'overflow-x': 'hidden',
                        'width': '100vw'
                    });
                    
                    // ヘッダー部分の調整
                    $('.container-fluid').css({
                        'padding-left': '0',
                        'padding-right': '0',
                        'width': '100vw',
                        'max-width': '100%'
                    });
                    
                    // カードの調整
                    $('.card').css({
                        'border-radius': '0',
                        'margin-bottom': '0'
                    });
                    
                    // カードボディの調整
                    $('.card-body').css({
                        'padding': '0.5rem'
                    });
                    
                    // ページのメインコンテンツ部分の調整
                    $('.main-content').css({
                        'padding': '0',
                        'margin': '0',
                        'width': '100vw'
                    });
                } else {
                    // PC表示時は元に戻す
                    $('body').css({
                        'padding': '',
                        'margin': '',
                        'overflow-x': '',
                        'width': ''
                    });
                    
                    $('.container-fluid').css({
                        'padding-left': '',
                        'padding-right': '',
                        'width': '',
                        'max-width': ''
                    });
                    
                    $('.card').css({
                        'border-radius': '',
                        'margin-bottom': ''
                    });
                    
                    $('.card-body').css({
                        'padding': ''
                    });
                    
                    $('.main-content').css({
                        'padding': '',
                        'margin': '',
                        'width': ''
                    });
                }
            }
            
            // 初期表示時にモバイル最適化を実行
            optimizeAppointmentLedgerForMobile();
            
            // ウィンドウサイズ変更時にも実行
            $(window).resize(function() {
                optimizeAppointmentLedgerForMobile();
            });
        });
    </script>
    
    <!-- 現在時間インジケーター（HTMLとして直接埋め込み） -->
    <div id="current-time-indicator" style="display:none;"></div>
    <div id="current-time-label" style="display:none;"></div>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>
