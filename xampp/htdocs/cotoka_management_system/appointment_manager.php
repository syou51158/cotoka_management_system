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

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    $error_message = "データベース接続エラー：" . $e->getMessage();
    require_once 'includes/header.php';
    echo '<div class="alert alert-danger m-3">' . $error_message . '</div>';
    require_once 'includes/footer.php';
    exit;
}

// 現在のサロンIDを取得
$salon_id = getCurrentSalonId();
if (!$salon_id) {
    // サロンIDがない場合は、利用可能なサロンを取得して自動的にセット
    require_once 'classes/User.php';
    $userObj = new User($db);
    $available_salons = $userObj->getAccessibleSalons($_SESSION['user_id']);
    
    if (!empty($available_salons)) {
        $salon_id = $available_salons[0]['salon_id'];
        setCurrentSalon($salon_id);
        $_SESSION['salon_id'] = $salon_id; // 互換性のため
        
        // リダイレクトして、セッションを更新
        header('Location: appointment_manager.php' . (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
        exit;
    } else {
        // アクセス可能なサロンがない場合
        $error_message = "アクセス可能なサロンがありません。管理者に連絡してください。";
        require_once 'includes/header.php';
        echo '<div class="alert alert-danger m-3">' . $error_message . '</div>';
        require_once 'includes/footer.php';
        exit;
    }
}

// 現在のテナントIDを取得
$tenant_id = getCurrentTenantId();

// パラメータから日付を取得（指定がなければ今日の日付）
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// フィルター用の日付範囲（デフォルト: 1週間）
$start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$end_date = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d', strtotime('+7 days'));

// 指定された期間内の予約を取得（Supabase RPC）
$upcoming_appointments = [];
$rpcAppointments = supabaseRpcCall('appointments_list_with_tasks', [
    'p_salon_id' => (int)$salon_id,
    'p_start_date' => $start_date,
    'p_end_date' => $end_date
]);
if ($rpcAppointments['success']) {
    $upcoming_appointments = is_array($rpcAppointments['data']) ? $rpcAppointments['data'] : [];
} else {
    error_log("予約データ取得エラー(Supabase): " . ($rpcAppointments['message'] ?? ''));
}

// 統計情報（Supabase RPC）
$today_count = 0;
$week_count = 0;
$pending_count = 0;
$month_count = 0;
$statsRpc = supabaseRpcCall('appointments_stats', ['p_salon_id' => (int)$salon_id]);
if ($statsRpc['success']) {
    $row = is_array($statsRpc['data']) ? ($statsRpc['data'][0] ?? null) : null;
    if ($row) {
        $today_count = (int)($row['today'] ?? 0);
        $week_count = (int)($row['week'] ?? 0);
        $pending_count = (int)($row['pending'] ?? 0);
        $month_count = (int)($row['month'] ?? 0);
    }
}

// 顧客一覧（初期表示はJSでAPIから取得するため空で可）
$customers = [];

// スタッフ一覧（Supabase RPCで初期オプションを用意）
$staff = [];
$staffSql = "SELECT s.staff_id, s.first_name, s.last_name, s.email, s.phone, s.position, s.status\n               FROM cotoka.staff s\n               JOIN cotoka.staff_salons ss\n                 ON ss.staff_id = s.staff_id\n                AND ss.tenant_id = s.tenant_id\n              WHERE ss.salon_id = $salon_id\n                AND s.tenant_id = $tenant_id\n           ORDER BY s.staff_id DESC";
$staffRes = supabaseRpcCall('execute_sql', ['query' => $staffSql]);
if ($staffRes['success']) {
    $staff = is_array($staffRes['data']) ? $staffRes['data'] : [];
} else {
    // フォールバック: テナント全体のスタッフ
    $staffSql2 = "SELECT staff_id, first_name, last_name, email, phone, position, status\n                    FROM cotoka.staff\n                   WHERE tenant_id = $tenant_id\n                ORDER BY staff_id DESC";
    $staffRes2 = supabaseRpcCall('execute_sql', ['query' => $staffSql2]);
    if ($staffRes2['success']) {
        $staff = is_array($staffRes2['data']) ? $staffRes2['data'] : [];
    }
}

// サービス一覧（初期表示はJSでAPIから取得するため空で可）
$services = [];

// サービスカテゴリ（初期表示は空。JS側でサービス取得時に補完）
$service_categories = [];

// ページ固有のCSS
$page_css = <<<EOT
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="assets/css/appointment_manager/appointment_manager.css">
EOT;

// ページタイトル
$page_title = "予約管理";

// ヘッダーの読み込み
require_once 'includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="row g-0">
        <!-- メインコンテンツエリア -->
        <main class="col-md-12 col-lg-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">予約管理</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button id="view-toggle-btn" class="btn btn-sm btn-outline-secondary" data-view="list">
                            <i class="fas fa-calendar-alt"></i> カレンダー表示
                        </button>
                        <a href="appointment_ledger.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-calendar-alt"></i> 予約台帳を表示
                        </a>
                        <button id="new-appointment-btn" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> 新規予約
                        </button>
                    </div>
                </div>
            </div>

            <!-- クイック統計情報 -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card quick-stats h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">本日の予約</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="today-count"><?php echo $today_count; ?></div>
                                    <small class="text-muted"><?php echo date('Y-m-d'); ?></small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card quick-stats h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">今週の予約</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="week-count"><?php echo $week_count; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-week fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card quick-stats h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">未確認予約</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="pending-count"><?php echo $pending_count; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card quick-stats h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">今月の予約数</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="month-count"><?php echo $month_count; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- フィルターとソート -->
            <div class="card filter-card mb-4 p-3">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label for="date-filter" class="form-label">日付</label>
                        <input type="text" class="form-control" id="date-filter" placeholder="日付を選択">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="status-filter" class="form-label">ステータス</label>
                        <select class="form-select" id="status-filter">
                            <option value="">すべて</option>
                            <option value="scheduled">予約済み</option>
                            <option value="confirmed">確定</option>
                            <option value="completed">完了</option>
                            <option value="cancelled">キャンセル</option>
                            <option value="no-show">無断キャンセル</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="staff-filter" class="form-label">スタッフ</label>
                        <select class="form-select" id="staff-filter">
                            <option value="">すべて</option>
                            <?php foreach ($staff as $staff_member): ?>
                            <option value="<?php echo $staff_member['staff_id']; ?>">
                                <?php echo $staff_member['last_name'] . ' ' . $staff_member['first_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="search-term" class="form-label">検索</label>
                        <input type="text" class="form-control" id="search-term" placeholder="顧客名、サービス名など">
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12 text-end">
                        <button id="apply-filters" class="btn btn-primary btn-sm">適用</button>
                        <button id="reset-filters" class="btn btn-outline-secondary btn-sm">リセット</button>
                    </div>
                </div>
            </div>

            <!-- 表示切り替えコンテナ -->
            <div class="view-container">
                <!-- カレンダービュー -->
                <div id="calendar-view" class="view-content d-none">
                    <div class="card mb-4">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0" id="calendar-title">予約カレンダー</h5>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary" id="prev-week">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" id="today-btn">今日</button>
                                    <button class="btn btn-sm btn-outline-secondary" id="next-week">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="calendar-container">
                                <div class="calendar-header">
                                    <div class="time-column"></div>
                                    <div class="day-headers" id="day-headers"></div>
                                </div>
                                <div class="calendar-body" id="calendar-body">
                                    <!-- カレンダーコンテンツはJSで動的に生成 -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- リストビュー -->
                <div id="list-view" class="view-content">
                    <!-- 予約リスト -->
                    <div class="row" id="appointment-list">
                        <!-- 予約のロード中表示 -->
                        <div class="col-12 text-center py-4" id="loading-appointments">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">読み込み中...</span>
                            </div>
                            <p class="mt-2">予約データを読み込んでいます...</p>
                        </div>

                        <!-- 予約なしの表示 -->
                        <div class="col-12 text-center py-4 d-none" id="no-appointments">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> 条件に一致する予約はありません
                            </div>
                        </div>

                        <!-- 予約カードが動的に追加される場所 -->
                        <?php if (!empty($upcoming_appointments)): ?>
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card appointment-card h-100" data-appointment-id="<?php echo $appointment['appointment_id']; ?>">
                                        <div class="card-header appointment-header status-<?php echo strtolower($appointment['status']); ?>">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="appointment-date">
                                                    <?php echo date('m/d (D)', strtotime($appointment['appointment_date'])); ?>
                                                </span>
                                                <span class="appointment-time">
                                                    <?php echo date('H:i', strtotime($appointment['start_time'])); ?> - <?php echo date('H:i', strtotime($appointment['end_time'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <?php if ($appointment['appointment_type'] === 'customer'): ?>
                                                <h5 class="card-title mb-1 appointment-customer">
                                                    <?php echo isset($appointment['customer_last_name']) ? $appointment['customer_last_name'] . ' ' . $appointment['customer_first_name'] : '顧客未設定'; ?>
                                                </h5>
                                                <p class="card-text appointment-service mb-2">
                                                    <small class="text-muted">
                                                        <?php echo isset($appointment['service_name']) ? $appointment['service_name'] : '未設定'; ?>
                                                        (<?php echo isset($appointment['duration']) ? $appointment['duration'] . '分' : '-'; ?>)
                                                    </small>
                                                </p>
                                            <?php else: ?>
                                                <h5 class="card-title mb-1 appointment-task">
                                                    <?php echo htmlspecialchars($appointment['appointment_type']); ?>
                                                </h5>
                                                <p class="card-text mb-2">
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($appointment['task_description'] ?? ''); ?>
                                                    </small>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <p class="card-text appointment-staff mb-2">
                                                <i class="fas fa-user me-1"></i> 
                                                <?php echo isset($appointment['staff_last_name']) ? $appointment['staff_last_name'] . ' ' . $appointment['staff_first_name'] : '担当者未設定'; ?>
                                            </p>
                                            
                                            <?php if (!empty($appointment['notes'])): ?>
                                                <p class="card-text appointment-notes">
                                                    <small><i class="fas fa-sticky-note me-1"></i> <?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></small>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer bg-transparent d-flex justify-content-between">
                                            <span class="appointment-status badge 
                                                <?php
                                                    switch ($appointment['status']) {
                                                        case 'scheduled':
                                                            echo 'bg-secondary';
                                                            break;
                                                        case 'confirmed':
                                                            echo 'bg-primary';
                                                            break;
                                                        case 'completed':
                                                            echo 'bg-success';
                                                            break;
                                                        case 'cancelled':
                                                            echo 'bg-danger';
                                                            break;
                                                        case 'no-show':
                                                            echo 'bg-warning';
                                                            break;
                                                        default:
                                                            echo 'bg-secondary';
                                                    }
                                                ?>">
                                                <?php
                                                    switch ($appointment['status']) {
                                                        case 'scheduled':
                                                            echo '予約済み';
                                                            break;
                                                        case 'confirmed':
                                                            echo '確定';
                                                            break;
                                                        case 'completed':
                                                            echo '完了';
                                                            break;
                                                        case 'cancelled':
                                                            echo 'キャンセル';
                                                            break;
                                                        case 'no-show':
                                                            echo '無断キャンセル';
                                                            break;
                                                        default:
                                                            echo $appointment['status'];
                                                    }
                                                ?>
                                            </span>
                                            <div class="appointment-actions">
                                                <button class="btn btn-sm btn-outline-primary me-1 view-appointment-btn" data-appointment-id="<?php echo $appointment['appointment_id']; ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-secondary edit-appointment-btn" data-appointment-id="<?php echo $appointment['appointment_id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- ページネーション -->
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center" id="pagination">
                            <!-- ページネーションが動的に追加される場所 -->
                        </ul>
                    </nav>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- 予約モーダル -->
<div class="modal fade" id="appointment-modal" tabindex="-1" aria-labelledby="appointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appointmentModalLabel">予約登録</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <form id="appointment-form">
                    <input type="hidden" id="appointment_id" name="appointment_id">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="appointment_type" class="form-label">予約タイプ</label>
                            <select class="form-select" id="appointment_type" name="appointment_type" required>
                                <option value="customer">お客様</option>
                                <option value="internal">業務</option>
                                <option value="break">休憩</option>
                                <option value="other">その他</option>
                            </select>
                        </div>
                        <div class="col-md-6 customer-field">
                            <label for="customer_id" class="form-label">お客様</label>
                            <select class="form-select" id="customer_id" name="customer_id">
                                <option value="">-- 選択してください --</option>
                                <!-- AJAXで顧客データを取得 -->
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="staff_id" class="form-label">担当スタッフ</label>
                            <select class="form-select" id="staff_id" name="staff_id" required>
                                <option value="">-- 選択してください --</option>
                                <!-- スタッフ情報はJSで動的に追加 -->
                            </select>
                        </div>
                        <div class="col-md-6 customer-field">
                            <label for="service_id" class="form-label">サービス</label>
                            <select class="form-select" id="service_id" name="service_id">
                                <option value="">-- 選択してください --</option>
                                <!-- サービス情報はJSで動的に追加 -->
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="appointment_date" class="form-label">日付</label>
                            <input type="date" class="form-control" id="appointment_date" name="appointment_date" required>
                        </div>
                        <div class="col-md-3">
                            <label for="start_time" class="form-label">開始時間</label>
                            <input type="text" class="form-control" id="start_time" name="start_time" required>
                        </div>
                        <div class="col-md-3">
                            <label for="end_time" class="form-label">終了時間</label>
                            <input type="text" class="form-control" id="end_time" name="end_time" required>
                        </div>
                    </div>
                    <div class="mb-3 internal-field" style="display: none;">
                        <label for="task_description" class="form-label">業務内容</label>
                        <input type="text" class="form-control" id="task_description" name="task_description" placeholder="業務内容を入力">
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">メモ</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                    <div class="row mb-3 customer-field">
                        <div class="col-md-6">
                            <label for="status" class="form-label">ステータス</label>
                            <select class="form-select" id="status" name="status">
                                <option value="scheduled">予約済み</option>
                                <option value="confirmed">確定</option>
                                <option value="completed">完了</option>
                                <option value="cancelled">キャンセル</option>
                                <option value="no-show">無断キャンセル</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger me-auto" id="delete-appointment-btn" style="display: none;">削除</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="button" class="btn btn-primary" id="save-appointment-btn">保存</button>
            </div>
        </div>
    </div>
</div>

<!-- 予約詳細モーダル -->
<div class="modal fade" id="appointment-details-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="z-index: 1050;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">予約詳細</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body" id="appointment-details-content">
                <!-- 予約詳細の内容はJavaScriptで動的に追加 -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                <button type="button" class="btn btn-primary" id="edit-appointment-btn">編集</button>
            </div>
        </div>
    </div>
</div>

<!-- APIエンドポイントのデータをJSに渡す -->
<script>
    const API_ENDPOINTS = {
        GET_APPOINTMENTS: 'api/appointments/get_appointments.php',
        SAVE_APPOINTMENT: 'api/appointments/save_appointment.php',
        DELETE_APPOINTMENT: 'api/appointments/delete_appointment.php',
        GET_CUSTOMERS: 'api/customers.php',
        GET_STAFF: 'api/staff.php',
        GET_SERVICES: 'api/services.php',
        GET_APPOINTMENT_STATS: 'api/appointments/get_stats.php'
    };
    
    // クエリから取得した統計データをJavaScriptに渡す
    const INITIAL_STATS = {
        today: <?php echo $today_count; ?>,
        week: <?php echo $week_count; ?>,
        month: <?php echo $month_count; ?>,
        pending: <?php echo $pending_count; ?>
    };
    
    const CONFIG = {
        salon_id: <?php echo $salon_id; ?>,
        tenant_id: <?php echo $tenant_id; ?>,
        today: '<?php echo date('Y-m-d'); ?>',
        appointments: <?php echo json_encode($upcoming_appointments); ?>,
        staff_members: <?php echo json_encode($staff); ?>,
        service_categories: <?php echo json_encode($service_categories); ?>,
        appointments_by_date: <?php echo json_encode($upcoming_appointments); ?>
    };
    
    // 統計情報を更新する関数
    function updateStatistics(stats) {
        document.getElementById('today-count').textContent = stats.today || '0';
        document.getElementById('week-count').textContent = stats.week || '0';
        document.getElementById('pending-count').textContent = stats.pending || '0';
        document.getElementById('month-count').textContent = stats.month || '0';
    }
    
    // 初期統計情報（サーバーサイドから取得した値で初期化）
    <?php
    // 統計情報の取得
    $stats = [
        'today' => 0,
        'week' => 0,
        'pending' => 0,
        'month' => 0
    ];
    
    try {
        // 本日の予約数
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM appointments
            WHERE salon_id = :salon_id
            AND appointment_date = :today
        ");
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->bindParam(':today', $today);
        $stmt->execute();
        $stats['today'] = (int)$stmt->fetchColumn();
        
        // 今週の予約数
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM appointments
            WHERE salon_id = :salon_id
            AND appointment_date BETWEEN :week_start AND :week_end
        ");
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->bindParam(':week_start', $week_start);
        $stmt->bindParam(':week_end', $week_end);
        $stmt->execute();
        $stats['week'] = (int)$stmt->fetchColumn();
        
        // 未確認予約数
        $scheduled_status = 'scheduled';
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM appointments
            WHERE salon_id = :salon_id
            AND status = :status
            AND appointment_date >= :today
        ");
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->bindParam(':status', $scheduled_status);
        $stmt->bindParam(':today', $today);
        $stmt->execute();
        $stats['pending'] = (int)$stmt->fetchColumn();
        
        // 今月の予約数
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM appointments
            WHERE salon_id = :salon_id
            AND appointment_date BETWEEN :month_start AND :month_end
        ");
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->bindParam(':month_start', $month_start);
        $stmt->bindParam(':month_end', $month_end);
        $stmt->execute();
        $stats['month'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("統計情報取得エラー: " . $e->getMessage());
    }
    ?>
    
    // 初期統計情報を設定
    const initialStats = <?php echo json_encode($stats); ?>;
    updateStatistics(initialStats);
</script>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ja.js"></script>
<script src="assets/js/appointment_manager/appointment_manager.js"></script>

<?php require_once 'includes/footer.php'; ?>