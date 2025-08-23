<?php
// 必要なファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'includes/functions.php';

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

// 統計情報の初期値を設定（エラー防止のため）
$total_count = 0;
$month_count = 0;
$repeat_rate = 0;
$avg_value = 0;
$total_filtered_customers = 0;
$total_pages = 1;
$current_page = 1;
$customers = [];
$vip_customers = [];

// 今月の開始日と終了日を設定
$month_start = date('Y-m-01 00:00:00');
$month_end = date('Y-m-t 23:59:59');

// 現在のサロンIDを取得
$salon_id = getCurrentSalonId();

// 現在のテナントIDを取得
$tenant_id = getCurrentTenantId();

// アクセス可能なサロン（Supabase RPC）を取得
$user_id = $_SESSION['user_id'];
$user_uid = $_SESSION['user_unique_id'] ?? null;
$rpcSalons = $user_uid
    ? supabaseRpcCall('user_accessible_salons_by_uid', ['p_user_uid' => (string)$user_uid])
    : supabaseRpcCall('user_accessible_salons', ['p_user_id' => (int)$user_id]);
$accessibleSalons = $rpcSalons['success'] ? ($rpcSalons['data'] ?? []) : [];
$accessibleSalonIds = array_column($accessibleSalons, 'salon_id');

// サロンIDがない場合は、アクセス可能なサロンの中から最初のものを選択
if (!$salon_id && !empty($accessibleSalonIds)) {
    $salon_id = $accessibleSalons[0]['salon_id'];
    setCurrentSalon($salon_id);
    $_SESSION['salon_id'] = $salon_id; // 互換性のため
    
    // リダイレクトして、セッションを更新
    header('Location: customer_manager.php' . (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

// 現在選択されているサロンがアクセス可能かチェック
if (!in_array($salon_id, $accessibleSalonIds)) {
    // アクセス可能なサロンがある場合は最初のサロンに切り替え
    if (!empty($accessibleSalonIds)) {
        $salon_id = $accessibleSalons[0]['salon_id'];
        setCurrentSalon($salon_id);
        $_SESSION['salon_id'] = $salon_id;
        
        // リダイレクトして、セッションを更新
        header('Location: customer_manager.php' . (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
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

// ページネーションの設定
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 25; // 1ページあたりの表示数
$offset = ($current_page - 1) * $items_per_page;

// 検索フィルター処理
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : '';

// フィルターに基づいたWHERE句の構築
$where_clauses = ["c.salon_id = :salon_id"];
$params = [':salon_id' => $salon_id];

if (!empty($search_term)) {
    $where_clauses[] = "(c.first_name LIKE :search OR c.last_name LIKE :search OR c.email LIKE :search OR c.phone LIKE :search)";
    $params[':search'] = "%$search_term%";
}

if (!empty($status_filter)) {
    $where_clauses[] = "c.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($gender_filter)) {
    $where_clauses[] = "c.gender = :gender";
    $params[':gender'] = $gender_filter;
}

if (!empty($date_range)) {
    $dates = explode(' to ', $date_range);
    if (count($dates) == 2) {
        $start_date = $dates[0] . ' 00:00:00';
        $end_date = $dates[1] . ' 23:59:59';
        $where_clauses[] = "c.created_at BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $start_date;
        $params[':end_date'] = $end_date;
    }
}

// WHERE句の構築
$where_clause = implode(' AND ', $where_clauses);

// 総顧客数の取得（フィルター適用）
try {
    $count_sql = "SELECT COUNT(*) FROM customers c WHERE $where_clause";
    $count_stmt = $conn->prepare($count_sql);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_filtered_customers = $count_stmt->fetchColumn();
    
    // ページ総数の計算
    $total_pages = ceil($total_filtered_customers / $items_per_page);
    
    // 現在のページが範囲外の場合は調整
    if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
        $offset = ($current_page - 1) * $items_per_page;
    }
} catch (PDOException $e) {
    error_log("顧客カウントエラー: " . $e->getMessage());
    $total_filtered_customers = 0;
    $total_pages = 0;
}

// 顧客情報の取得（フィルター、ソート、ページネーション適用）
$customers = [];
try {
    $sql = "
        SELECT 
            c.customer_id,
            c.first_name,
            c.last_name,
            c.phone,
            c.email,
            c.birthday,
            c.gender,
            c.address,
            c.status,
            c.created_at,
            c.updated_at,
            c.notes,
            c.last_visit_date,
            c.visit_count,
            c.total_spent,
            CONCAT(c.first_name, ' ', c.last_name) AS full_name,
            (SELECT COUNT(a.appointment_id) FROM appointments a WHERE a.customer_id = c.customer_id AND a.salon_id = c.salon_id) as appointment_count,
            (SELECT MAX(a.appointment_date) FROM appointments a WHERE a.customer_id = c.customer_id AND a.salon_id = c.salon_id) as latest_appointment_date
        FROM 
            customers c
        WHERE 
            $where_clause
        ORDER BY 
            c.created_at DESC
        LIMIT :offset, :limit
    ";
    
    $stmt = $conn->prepare($sql);
    
    // パラメータをバインド
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
    
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 各顧客の追加情報を取得（最近の予約等）
    foreach ($customers as &$customer) {
        // 最近の予約を取得
        $appointment_sql = "
            SELECT 
                a.appointment_id,
                a.appointment_date,
                a.start_time,
                a.status,
                s.first_name AS staff_first_name,
                s.last_name AS staff_last_name,
                srv.name AS service_name
            FROM 
                appointments a
                LEFT JOIN staff s ON a.staff_id = s.staff_id
                LEFT JOIN services srv ON a.service_id = srv.service_id
            WHERE 
                a.customer_id = :customer_id
                AND a.salon_id = :salon_id
            ORDER BY 
                a.appointment_date DESC, a.start_time DESC
            LIMIT 3
        ";
        
        $appointment_stmt = $conn->prepare($appointment_sql);
        $appointment_stmt->bindParam(':customer_id', $customer['customer_id']);
        $appointment_stmt->bindParam(':salon_id', $salon_id);
        $appointment_stmt->execute();
        $customer['recent_appointments'] = $appointment_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 統計情報の取得
    // 顧客総数
    $stats_stmt = $conn->prepare("SELECT COUNT(*) as total_count FROM customers WHERE salon_id = :salon_id");
    $stats_stmt->bindParam(':salon_id', $salon_id);
    $stats_stmt->execute();
    $total_count = $stats_stmt->fetch(PDO::FETCH_ASSOC)['total_count'];
    
    // 今月の新規顧客
    $stats_stmt = $conn->prepare("
        SELECT COUNT(*) as month_count 
        FROM customers 
        WHERE salon_id = :salon_id 
        AND created_at BETWEEN :month_start AND :month_end
    ");
    $stats_stmt->bindParam(':salon_id', $salon_id);
    $stats_stmt->bindParam(':month_start', $month_start);
    $stats_stmt->bindParam(':month_end', $month_end);
    $stats_stmt->execute();
    $month_count = $stats_stmt->fetch(PDO::FETCH_ASSOC)['month_count'];
    
    // リピート率（2回以上来店した顧客の割合）
    $stats_stmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN visit_count >= 2 THEN 1 END) as repeat_customers,
            COUNT(*) as total_customers
        FROM customers
        WHERE salon_id = :salon_id
    ");
    $stats_stmt->bindParam(':salon_id', $salon_id);
    $stats_stmt->execute();
    $repeat_data = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    $repeat_rate = $repeat_data['total_customers'] > 0 
        ? round(($repeat_data['repeat_customers'] / $repeat_data['total_customers']) * 100) 
        : 0;
    
    // 顧客単価
    $stats_stmt = $conn->prepare("
        SELECT AVG(total_spent) as avg_value
        FROM customers
        WHERE salon_id = :salon_id AND total_spent > 0
    ");
    $stats_stmt->bindParam(':salon_id', $salon_id);
    $stats_stmt->execute();
    $avg_result = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    $avg_value = $avg_result['avg_value'] ? round($avg_result['avg_value']) : 0;
    
    // VIP顧客（売上上位10名）
    $vip_stmt = $conn->prepare("
        SELECT 
            customer_id, 
            first_name, 
            last_name, 
            total_spent,
            visit_count,
            last_visit_date
        FROM 
            customers
        WHERE 
            salon_id = :salon_id
            AND total_spent > 0
        ORDER BY 
            total_spent DESC
        LIMIT 10
    ");
    $vip_stmt->bindParam(':salon_id', $salon_id);
    $vip_stmt->execute();
    $vip_customers = $vip_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // デバッグ情報を追加
    $debug_info = [
        'salon_id' => $salon_id,
        'customer_count' => count($customers),
        'total_filtered' => $total_filtered_customers,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'total_count' => $total_count,
        'month_count' => $month_count,
        'repeat_rate' => $repeat_rate,
        'avg_value' => $avg_value
    ];
    
} catch (PDOException $e) {
    error_log("顧客情報取得エラー: " . $e->getMessage());
    $error_message = "顧客情報取得エラー：" . $e->getMessage();
    $customers = []; // エラー時に空の配列を設定
    $total_filtered_customers = 0;
    $total_pages = 0;
    $current_page = 1;
}

// ページ固有のCSS
$page_css = <<<EOT
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="assets/css/customer_manager/customer_manager.css">
EOT;

// ページタイトル
$page_title = "顧客管理";

// ヘッダーの読み込み
require_once 'includes/header.php';

// デバッグ情報を表示（開発環境のみ）
if (isset($debug_info) && !empty($debug_info)) {
    echo '<div class="alert alert-info m-3 debug-info">';
    echo '<h5>デバッグ情報</h5>';
    echo '<pre>' . print_r($debug_info, true) . '</pre>';
    echo '<p>顧客データ件数: ' . count($customers) . '</p>';
    echo '</div>';
}
?>

<div class="container-fluid px-0">
    <div class="row g-0">
        <!-- メインコンテンツエリア -->
        <main class="col-md-12 col-lg-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">顧客管理</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button id="new-customer-btn" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> 新規顧客登録
                        </button>
                        <button id="import-customers-btn" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-file-import"></i> インポート
                        </button>
                        <button id="export-customers-btn" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-file-export"></i> エクスポート
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
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">顧客総数</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-count"><?php echo $total_count; ?></div>
                                    <div class="small text-danger mt-1">
                                        <strong>デバッグ情報:</strong><br>
                                        クエリ: SELECT COUNT(*) as total_count FROM customers WHERE salon_id = <?php echo $salon_id; ?><br>
                                        結果: <?php echo $total_count; ?><br>
                                        サロンID: <?php echo $salon_id; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">今月の新規顧客</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="month-count"><?php echo $month_count; ?></div>
                                    <div class="small text-danger mt-1">
                                        <strong>デバッグ情報:</strong><br>
                                        期間: <?php echo $month_start; ?> 〜 <?php echo $month_end; ?><br>
                                        クエリ: SELECT COUNT(*) FROM customers WHERE salon_id = <?php echo $salon_id; ?> AND created_at BETWEEN '<?php echo $month_start; ?>' AND '<?php echo $month_end; ?>'<br>
                                        結果: <?php echo $month_count; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-plus fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">リピート率</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="repeat-rate"><?php echo $repeat_rate; ?>%</div>
                                    <div class="small text-danger mt-1">
                                        <strong>デバッグ情報:</strong><br>
                                        リピート顧客数: <?php echo isset($repeat_data['repeat_customers']) ? $repeat_data['repeat_customers'] : '0'; ?><br>
                                        全顧客数: <?php echo isset($repeat_data['total_customers']) ? $repeat_data['total_customers'] : '0'; ?><br>
                                        計算: <?php echo isset($repeat_data['repeat_customers']) && isset($repeat_data['total_customers']) && $repeat_data['total_customers'] > 0 ? "(" . $repeat_data['repeat_customers'] . " / " . $repeat_data['total_customers'] . ") * 100 = " . $repeat_rate : "計算できません"; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-sync fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">顧客単価</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800" id="avg-value">¥<?php echo number_format($avg_value); ?></div>
                                    <div class="small text-danger mt-1">
                                        <strong>デバッグ情報:</strong><br>
                                        クエリ: SELECT AVG(total_spent) as avg_value FROM customers WHERE salon_id = <?php echo $salon_id; ?> AND total_spent > 0<br>
                                        結果: <?php echo isset($avg_result['avg_value']) ? $avg_result['avg_value'] : '0'; ?><br>
                                        四捨五入値: <?php echo $avg_value; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-yen-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- フィルターとソート -->
            <div class="card filter-card mb-4 p-3">
                <form action="customer_manager.php" method="get">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <label for="search-term" class="form-label">検索</label>
                            <input type="text" class="form-control" id="search-term" name="search" placeholder="名前、電話番号、メールなど" value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="status-filter" class="form-label">ステータス</label>
                            <select class="form-select" id="status-filter" name="status">
                                <option value="">すべて</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>アクティブ</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>非アクティブ</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="gender-filter" class="form-label">性別</label>
                            <select class="form-select" id="gender-filter" name="gender">
                                <option value="">すべて</option>
                                <option value="male" <?php echo $gender_filter === 'male' ? 'selected' : ''; ?>>男性</option>
                                <option value="female" <?php echo $gender_filter === 'female' ? 'selected' : ''; ?>>女性</option>
                                <option value="other" <?php echo $gender_filter === 'other' ? 'selected' : ''; ?>>その他</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="date-filter" class="form-label">登録日</label>
                            <input type="text" class="form-control" id="date-filter" name="date_range" placeholder="日付を選択" value="<?php echo htmlspecialchars($date_range); ?>">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary btn-sm">適用</button>
                            <a href="customer_manager.php" class="btn btn-outline-secondary btn-sm">リセット</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 顧客リスト -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-users me-1"></i>
                    顧客一覧
                </div>
                <div class="alert alert-info">
                    <strong>デバッグ情報:</strong><br>
                    <details>
                        <summary><strong>クエリ情報 (クリックで展開)</strong></summary>
                        <pre>
サロンID: <?php echo $salon_id; ?>
顧客総数: <?php echo isset($total_count) ? $total_count : '0'; ?>
フィルター適用後の顧客数: <?php echo isset($total_filtered_customers) ? $total_filtered_customers : '0'; ?>
現在のページ: <?php echo isset($current_page) ? $current_page : '1'; ?>
総ページ数: <?php echo isset($total_pages) ? $total_pages : '0'; ?>
取得した顧客データ数: <?php echo isset($customers) ? count($customers) : '0'; ?>

SQL: 
SELECT 
    c.customer_id,
    c.first_name,
    c.last_name,
    c.phone,
    c.email,
    c.birthday,
    c.gender,
    c.address,
    c.status,
    c.created_at,
    c.updated_at,
    c.notes,
    c.last_visit_date,
    c.visit_count,
    c.total_spent,
    CONCAT(c.first_name, ' ', c.last_name) AS full_name,
    (SELECT COUNT(a.appointment_id) FROM appointments a WHERE a.customer_id = c.customer_id AND a.salon_id = c.salon_id) as appointment_count,
    (SELECT MAX(a.appointment_date) FROM appointments a WHERE a.customer_id = c.customer_id AND a.salon_id = c.salon_id) as latest_appointment_date
FROM 
    customers c
WHERE 
    <?php echo isset($where_clause) ? $where_clause : 'c.salon_id = ' . $salon_id; ?>
ORDER BY 
    c.created_at DESC
LIMIT <?php echo isset($offset) ? $offset : '0'; ?>, <?php echo isset($items_per_page) ? $items_per_page : '10'; ?>
                        </pre>
                        <strong>パラメータ:</strong>
                        <pre><?php if(isset($params)) print_r($params); ?></pre>
                    </details>
                    <hr>
                    <strong>統計:</strong><br>
                    総顧客数: <?php echo $total_count; ?><br>
                    フィルター後件数: <?php echo $total_filtered_customers; ?><br>
                    ページ数: <?php echo $total_pages; ?><br>
                    現在ページ: <?php echo $current_page; ?><br>
                    表示件数: <?php echo count($customers); ?><br>
                    オフセット: <?php echo $offset; ?><br>
                    ページサイズ: <?php echo $items_per_page; ?><br>
                    <hr>
                    <strong>顧客データサンプル (最初の3件):</strong><br>
                    <pre><?php print_r(array_slice($customers, 0, 3)); ?></pre>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="customers-table">
                            <thead>
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">顧客名</th>
                                    <th scope="col">電話番号</th>
                                    <th scope="col">メールアドレス</th>
                                    <th scope="col">性別</th>
                                    <th scope="col">最終来店日</th>
                                    <th scope="col">来店回数</th>
                                    <th scope="col">ステータス</th>
                                    <th scope="col">操作</th>
                                </tr>
                            </thead>
                            <tbody id="customers-list">
                                <?php if (empty($customers)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <?php if (isset($error_message)): ?>
                                                <div class="alert alert-danger">
                                                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i> 条件に一致する顧客データはありません
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    try {
                                        foreach ($customers as $customer): 
                                    ?>
                                        <tr data-customer-id="<?php echo $customer['customer_id']; ?>">
                                            <td><?php echo $customer['customer_id']; ?></td>
                                            <td>
                                                <a href="#" class="customer-link text-decoration-none" data-customer-id="<?php echo $customer['customer_id']; ?>">
                                                    <?php echo htmlspecialchars($customer['last_name'] . ' ' . $customer['first_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($customer['phone'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($customer['email'] ?: '-'); ?></td>
                                            <td>
                                                <?php
                                                switch($customer['gender']) {
                                                    case 'male':
                                                        echo '男性';
                                                        break;
                                                    case 'female':
                                                        echo '女性';
                                                        break;
                                                    case 'other':
                                                        echo 'その他';
                                                        break;
                                                    default:
                                                        echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo !empty($customer['last_visit_date']) ? date('Y-m-d', strtotime($customer['last_visit_date'])) : '-'; ?></td>
                                            <td><?php echo intval($customer['visit_count']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $customer['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $customer['status'] == 'active' ? 'アクティブ' : '非アクティブ'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary view-customer-btn" data-customer-id="<?php echo $customer['customer_id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary edit-customer-btn" data-customer-id="<?php echo $customer['customer_id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php 
                                        endforeach;
                                    } catch (Exception $e) {
                                        echo '<tr><td colspan="9" class="text-center py-4"><div class="alert alert-danger">';
                                        echo '<i class="fas fa-exclamation-circle me-2"></i> 顧客データの表示中にエラーが発生しました: ' . htmlspecialchars($e->getMessage());
                                        echo '</div></td></tr>';
                                    }
                                    ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- ページネーション -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            表示: <?php echo count($customers); ?> / <?php echo $total_filtered_customers; ?> 件
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0">
                                <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($gender_filter) ? '&gender=' . urlencode($gender_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>" aria-label="First">
                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($gender_filter) ? '&gender=' . urlencode($gender_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php
                                // ページネーションの範囲計算
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                // 最初のページが表示されていない場合は省略記号を表示
                                if ($start_page > 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                
                                // ページ番号を表示
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    $active_class = ($i == $current_page) ? 'active' : '';
                                    echo '<li class="page-item ' . $active_class . '">';
                                    echo '<a class="page-link" href="?page=' . $i . (!empty($search_term) ? '&search=' . urlencode($search_term) : '') . (!empty($status_filter) ? '&status=' . urlencode($status_filter) : '') . (!empty($gender_filter) ? '&gender=' . urlencode($gender_filter) : '') . (!empty($date_range) ? '&date_range=' . urlencode($date_range) : '') . '">' . $i . '</a>';
                                    echo '</li>';
                                }
                                
                                // 最後のページが表示されていない場合は省略記号を表示
                                if ($end_page < $total_pages) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                ?>
                                
                                <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($gender_filter) ? '&gender=' . urlencode($gender_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($gender_filter) ? '&gender=' . urlencode($gender_filter) : ''; ?><?php echo !empty($date_range) ? '&date_range=' . urlencode($date_range) : ''; ?>" aria-label="Last">
                                        <span aria-hidden="true">&raquo;&raquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- 顧客編集モーダル -->
<div class="modal fade" id="customer-modal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerModalLabel">顧客情報</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <form id="customer-form">
                    <input type="hidden" id="customer_id" name="customer_id">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">姓</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">名</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">電話番号</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">メールアドレス</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="birthday" class="form-label">生年月日</label>
                            <input type="date" class="form-control" id="birthday" name="birthday">
                        </div>
                        <div class="col-md-6">
                            <label for="gender" class="form-label">性別</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="">選択してください</option>
                                <option value="male">男性</option>
                                <option value="female">女性</option>
                                <option value="other">その他</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">住所</label>
                        <input type="text" class="form-control" id="address" name="address">
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="status" class="form-label">ステータス</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">アクティブ</option>
                                <option value="inactive">非アクティブ</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">備考</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger me-auto" id="delete-customer-btn" style="display: none;">削除</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="button" class="btn btn-primary" id="save-customer-btn">保存</button>
            </div>
        </div>
    </div>
</div>

<!-- 顧客詳細モーダル -->
<div class="modal fade" id="customer-details-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="z-index: 1050;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">顧客詳細</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body" id="customer-details-content">
                <!-- 顧客詳細の内容はJavaScriptで動的に追加 -->
                <div class="customer-profile mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="customer-avatar me-3">
                            <i class="fas fa-user-circle fa-5x text-primary"></i>
                        </div>
                        <div>
                            <h3 id="customer-name">顧客名</h3>
                            <div id="customer-basic-info">
                                <p class="mb-1"><i class="fas fa-phone me-2"></i><span id="customer-phone"></span></p>
                                <p class="mb-1"><i class="fas fa-envelope me-2"></i><span id="customer-email"></span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header">基本情報</div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item"><strong>性別：</strong><span id="customer-gender"></span></li>
                                    <li class="list-group-item"><strong>生年月日：</strong><span id="customer-birthday"></span></li>
                                    <li class="list-group-item"><strong>住所：</strong><span id="customer-address"></span></li>
                                    <li class="list-group-item"><strong>ステータス：</strong><span id="customer-status"></span></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header">来店情報</div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item"><strong>初回来店日：</strong><span id="customer-first-visit"></span></li>
                                    <li class="list-group-item"><strong>最終来店日：</strong><span id="customer-last-visit"></span></li>
                                    <li class="list-group-item"><strong>来店回数：</strong><span id="customer-visit-count"></span></li>
                                    <li class="list-group-item"><strong>総利用金額：</strong><span id="customer-total-spent"></span></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">備考</div>
                    <div class="card-body">
                        <p id="customer-notes">備考情報がありません。</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">最近の予約履歴</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>日付</th>
                                        <th>時間</th>
                                        <th>サービス</th>
                                        <th>担当者</th>
                                        <th>状態</th>
                                    </tr>
                                </thead>
                                <tbody id="customer-appointments">
                                    <!-- 予約履歴はJavaScriptで動的に追加 -->
                                    <tr>
                                        <td colspan="5" class="text-center">予約履歴がありません。</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                <button type="button" class="btn btn-primary" id="edit-customer-btn">編集</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ja.js"></script>
<!-- customer_manager.jsの読み込みを削除し、必要なコードはすべてインラインで記述 -->
<!-- <script src="assets/js/customer_manager/customer_manager.js"></script> -->

<script>
    // ページが読み込まれた時の処理
    document.addEventListener('DOMContentLoaded', function() {
        // 日付選択のFlatpickr初期化
        if (document.getElementById('date-filter')) {
            flatpickr('#date-filter', {
                locale: 'ja',
                mode: 'range',
                dateFormat: 'Y-m-d',
                defaultDate: <?php echo !empty($date_range) ? json_encode(explode(' to ', $date_range)) : 'null'; ?>
            });
        }
        
        // 顧客詳細表示のイベントリスナー
        document.querySelectorAll('.customer-link, .view-customer-btn').forEach(function(element) {
            element.addEventListener('click', function(e) {
                e.preventDefault();
                const customerId = this.getAttribute('data-customer-id');
                if (customerId) {
                    window.location.href = 'customer_details.php?id=' + customerId;
                }
            });
        });
        
        // 顧客編集のイベントリスナー
        document.querySelectorAll('.edit-customer-btn').forEach(function(element) {
            element.addEventListener('click', function(e) {
                e.preventDefault();
                const customerId = this.getAttribute('data-customer-id');
                if (customerId) {
                    window.location.href = 'customer_edit.php?id=' + customerId;
                }
            });
        });
        
        // 新規顧客登録ボタン
        const newCustomerBtn = document.getElementById('new-customer-btn');
        if (newCustomerBtn) {
            newCustomerBtn.addEventListener('click', function() {
                window.location.href = 'customer_edit.php';
            });
        }
        
        // インポートボタン
        const importBtn = document.getElementById('import-customers-btn');
        if (importBtn) {
            importBtn.addEventListener('click', function() {
                alert('インポート機能は準備中です');
            });
        }
        
        // エクスポートボタン
        const exportBtn = document.getElementById('export-customers-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', function() {
                alert('エクスポート機能は準備中です');
            });
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?> 