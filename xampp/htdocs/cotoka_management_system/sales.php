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

// 現在のサロンIDを取得
$salon_id = isset($_SESSION['salon_id']) ? $_SESSION['salon_id'] : 1;

// 現在のテナントIDを取得
$tenant_id = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : 1;

// 期間設定（デフォルト：今月）
$current_month = date('Y-m');
$year_month = isset($_GET['month']) ? $_GET['month'] : $current_month;
list($year, $month) = explode('-', $year_month);
$start_date = $year . '-' . $month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

// 月間売上データ（Supabase RPC）
$monthly_sales = [];
$rpcDaily = supabaseRpcCall('report_sales_daily', [
    'p_salon_id' => (int)$salon_id,
    'p_start' => $start_date,
    'p_end' => $end_date
]);
if ($rpcDaily['success']) {
    $rows = is_array($rpcDaily['data']) ? $rpcDaily['data'] : [];
    foreach ($rows as $r) {
        $monthly_sales[] = [
            'date' => isset($r['sale_date']) ? (new DateTime($r['sale_date']))->format('Y-m-d') : null,
            'total_amount' => (float)($r['total_sales'] ?? 0),
            'customer_count' => (int)($r['customer_count'] ?? 0),
            'transaction_count' => (int)($r['transaction_count'] ?? 0)
        ];
    }
    // デバッグ情報を追加
    $debug_info = [
        'salon_id' => $salon_id,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'sales_count' => count($monthly_sales)
    ];
} else {
    $error_message = '売上データ取得エラー（Supabase）: ' . ($rpcDaily['message'] ?? '');
}

// 売上サマリーの取得（Supabase RPC）
$summary = [
    'total_sales' => 0,
    'total_transactions' => 0,
    'total_customers' => 0,
    'average_transaction' => 0
];
$rpcSummary = supabaseRpcCall('report_sales_summary', [
    'p_salon_id' => (int)$salon_id,
    'p_start' => $start_date,
    'p_end' => $end_date
]);
if ($rpcSummary['success']) {
    $row = is_array($rpcSummary['data']) ? ($rpcSummary['data'][0] ?? null) : null;
    if ($row) {
        $summary['total_sales'] = (float)($row['total_sales'] ?? 0);
        $summary['total_transactions'] = (int)($row['transaction_count'] ?? 0);
        $summary['total_customers'] = (int)($row['customer_count'] ?? 0);
        $summary['average_transaction'] = (int)($row['average_sales'] ?? 0);
    }
}

// サービスごとの売上データ（未対応のため空配列で表示）
$service_sales = [];

// スタッフごとの売上データ（未対応のため空配列で表示）
$staff_sales = [];

// 最近の取引履歴（未対応のため空配列で表示）
$recent_transactions = [];

// 月間サマリーの計算
if (!empty($monthly_sales)) {
    foreach ($monthly_sales as $sale) {
        $summary['total_sales'] += $sale['total_amount'];
        $summary['total_transactions'] += $sale['transaction_count'];
        $summary['total_customers'] += $sale['customer_count'];
    }
    
    // 平均取引額の計算
    if ($summary['total_transactions'] > 0) {
        $summary['average_transaction'] = $summary['total_sales'] / $summary['total_transactions'];
    }
}

// 来月・前月のリンク用の日付計算
$prev_month = date('Y-m', strtotime($start_date . ' -1 month'));
$next_month = date('Y-m', strtotime($start_date . ' +1 month'));

// ページ固有のCSS
$page_css = <<<EOT
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="assets/css/sales/sales.css">
EOT;

// ページタイトル
$page_title = "売上管理";

// ヘッダーの読み込み
require_once 'includes/header.php';

// デバッグ情報を表示（開発環境のみ）
if (isset($debug_info) && !empty($debug_info)) {
    echo '<div class="alert alert-info m-3 debug-info">';
    echo '<h5>デバッグ情報</h5>';
    echo '<pre>' . print_r($debug_info, true) . '</pre>';
    echo '<p>売上データ件数: ' . count($monthly_sales) . '</p>';
    echo '</div>';
}
?>

<div class="container-fluid px-0">
    <div class="row g-0">
        <!-- メインコンテンツエリア -->
        <main class="col-md-12 col-lg-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">売上管理</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button id="export-csv-btn" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-file-csv"></i> CSVエクスポート
                        </button>
                        <button id="export-pdf-btn" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-file-pdf"></i> PDFエクスポート
                        </button>
                        <button id="print-report-btn" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-print"></i> 印刷
                        </button>
                    </div>
                    
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="periodDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-calendar"></i> 期間
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="periodDropdown">
                            <li><a class="dropdown-item period-option" data-period="day" href="#">日次</a></li>
                            <li><a class="dropdown-item period-option" data-period="week" href="#">週次</a></li>
                            <li><a class="dropdown-item period-option" data-period="month" href="#">月次</a></li>
                            <li><a class="dropdown-item period-option" data-period="year" href="#">年次</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item period-option" data-period="custom" href="#">カスタム期間</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 期間表示と移動ナビゲーション -->
            <div class="date-navigation mb-4">
                <div class="row align-items-center justify-content-between">
                    <div class="col-auto">
                        <a href="?month=<?php echo $prev_month; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-chevron-left"></i> 前月
                        </a>
                    </div>
                    <div class="col text-center">
                        <h3 class="current-period"><?php echo date('Y年m月', strtotime($start_date)); ?></h3>
                    </div>
                    <div class="col-auto">
                        <a href="?month=<?php echo $next_month; ?>" class="btn btn-outline-primary">
                            次月 <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- 売上サマリー -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card sale-stats h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">総売上</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">¥<?php echo number_format($summary['total_sales']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-yen-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card sale-stats h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">総顧客数</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($summary['total_customers']); ?>人</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card sale-stats h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">取引数</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($summary['total_transactions']); ?>件</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card sale-stats h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">平均客単価</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">¥<?php echo number_format($summary['average_transaction']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-coins fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- グラフとテーブルのコンテナ -->
            <div class="row mb-4">
                <!-- 売上推移グラフ -->
                <div class="col-xl-8 col-lg-7">
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-chart-line me-1"></i>
                            売上推移
                        </div>
                        <div class="card-body">
                            <canvas id="salesChart" width="100%" height="40"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- 売上構成円グラフ -->
                <div class="col-xl-4 col-lg-5">
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-chart-pie me-1"></i>
                            売上構成
                        </div>
                        <div class="card-body">
                            <canvas id="salesCompositionChart" width="100%" height="50"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- フィルターとソート -->
            <div class="card filter-card mb-4 p-3">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label for="date-range" class="form-label">期間</label>
                        <input type="text" class="form-control" id="date-range" placeholder="期間を選択">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="staff-filter" class="form-label">スタッフ</label>
                        <select class="form-select" id="staff-filter">
                            <option value="">すべて</option>
                            <?php foreach ($staff_sales as $staff): ?>
                            <option value="<?php echo $staff['staff_id']; ?>"><?php echo htmlspecialchars($staff['staff_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="service-filter" class="form-label">サービス</label>
                        <select class="form-select" id="service-filter">
                            <option value="">すべて</option>
                            <?php foreach ($service_sales as $service): ?>
                            <option value="<?php echo $service['service_id']; ?>"><?php echo htmlspecialchars($service['service_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label for="payment-method-filter" class="form-label">支払方法</label>
                        <select class="form-select" id="payment-method-filter">
                            <option value="">すべて</option>
                            <option value="cash">現金</option>
                            <option value="credit_card">クレジットカード</option>
                            <option value="qr_code">QRコード決済</option>
                            <option value="bank_transfer">銀行振込</option>
                            <option value="other">その他</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12 text-end">
                        <button id="apply-filters" class="btn btn-primary btn-sm">適用</button>
                        <button id="reset-filters" class="btn btn-outline-secondary btn-sm">リセット</button>
                    </div>
                </div>
            </div>

            <!-- 売上明細タブ -->
            <div class="card mb-4">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="salesTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="daily-tab" data-bs-toggle="tab" data-bs-target="#daily" type="button" role="tab" aria-controls="daily" aria-selected="true">日別売上</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="service-tab" data-bs-toggle="tab" data-bs-target="#service" type="button" role="tab" aria-controls="service" aria-selected="false">サービス別売上</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff" type="button" role="tab" aria-controls="staff" aria-selected="false">スタッフ別売上</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab" aria-controls="transactions" aria-selected="false">取引履歴</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="salesTabContent">
                        <!-- 日別売上タブ -->
                        <div class="tab-pane fade show active" id="daily" role="tabpanel" aria-labelledby="daily-tab">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>日付</th>
                                            <th>売上</th>
                                            <th>顧客数</th>
                                            <th>取引数</th>
                                            <th>平均客単価</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($monthly_sales)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">売上データがありません</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($monthly_sales as $sale): ?>
                                        <tr class="sale-row" data-date="<?php echo $sale['date']; ?>">
                                            <td><?php echo date('Y年m月d日', strtotime($sale['date'])); ?></td>
                                            <td>¥<?php echo number_format($sale['total_amount']); ?></td>
                                            <td><?php echo number_format($sale['customer_count']); ?></td>
                                            <td><?php echo number_format($sale['transaction_count']); ?></td>
                                            <td>¥<?php echo $sale['transaction_count'] > 0 ? number_format($sale['total_amount'] / $sale['transaction_count']) : 0; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="fw-bold">
                                            <td>合計</td>
                                            <td>¥<?php echo number_format($summary['total_sales']); ?></td>
                                            <td><?php echo number_format($summary['total_customers']); ?></td>
                                            <td><?php echo number_format($summary['total_transactions']); ?></td>
                                            <td>¥<?php echo number_format($summary['average_transaction']); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        
                        <!-- サービス別売上タブ -->
                        <div class="tab-pane fade" id="service" role="tabpanel" aria-labelledby="service-tab">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>サービス名</th>
                                            <th>売上</th>
                                            <th>売上割合</th>
                                            <th>提供数</th>
                                            <th>平均単価</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($service_sales)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">サービス別売上データがありません</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($service_sales as $service): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                            <td>¥<?php echo number_format($service['total_amount']); ?></td>
                                            <td><?php echo $summary['total_sales'] > 0 ? number_format($service['total_amount'] / $summary['total_sales'] * 100, 1) : 0; ?>%</td>
                                            <td><?php echo number_format($service['sale_count']); ?></td>
                                            <td>¥<?php echo $service['sale_count'] > 0 ? number_format($service['total_amount'] / $service['sale_count']) : 0; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- スタッフ別売上タブ -->
                        <div class="tab-pane fade" id="staff" role="tabpanel" aria-labelledby="staff-tab">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>スタッフ名</th>
                                            <th>売上</th>
                                            <th>売上割合</th>
                                            <th>担当数</th>
                                            <th>平均単価</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($staff_sales)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">スタッフ別売上データがありません</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($staff_sales as $staff): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($staff['staff_name']); ?></td>
                                            <td>¥<?php echo number_format($staff['total_amount']); ?></td>
                                            <td><?php echo $summary['total_sales'] > 0 ? number_format($staff['total_amount'] / $summary['total_sales'] * 100, 1) : 0; ?>%</td>
                                            <td><?php echo number_format($staff['sale_count']); ?></td>
                                            <td>¥<?php echo $staff['sale_count'] > 0 ? number_format($staff['total_amount'] / $staff['sale_count']) : 0; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- 取引履歴タブ -->
                        <div class="tab-pane fade" id="transactions" role="tabpanel" aria-labelledby="transactions-tab">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>日時</th>
                                            <th>顧客名</th>
                                            <th>サービス</th>
                                            <th>スタッフ</th>
                                            <th>金額</th>
                                            <th>支払方法</th>
                                            <th>ステータス</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_transactions)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">取引履歴がありません</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($recent_transactions as $transaction): ?>
                                        <tr class="transaction-row" data-id="<?php echo $transaction['payment_id']; ?>">
                                            <td><?php echo date('Y/m/d H:i', strtotime($transaction['payment_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['customer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['service_name']); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['staff_name']); ?></td>
                                            <td>¥<?php echo number_format($transaction['amount']); ?></td>
                                            <td><?php echo getPaymentMethodText($transaction['payment_method']); ?></td>
                                            <td><?php echo getPaymentStatusBadge($transaction['status']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- 取引詳細モーダル -->
<div class="modal fade" id="transaction-details-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" style="z-index: 1050;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">取引詳細</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body" id="transaction-details-content">
                <!-- 取引詳細の内容はJavaScriptで動的に追加 -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                <button type="button" class="btn btn-primary" id="print-receipt-btn">領収書印刷</button>
            </div>
        </div>
    </div>
</div>

<!-- APIエンドポイントのデータをJSに渡す -->
<script>
    const API_ENDPOINTS = {
        GET_SALES: 'api/sales/get_sales.php',
        GET_TRANSACTION: 'api/sales/get_transaction.php',
        EXPORT_CSV: 'api/sales/export_csv.php',
        EXPORT_PDF: 'api/sales/export_pdf.php'
    };
    
    const CONFIG = {
        salon_id: <?php echo $salon_id; ?>,
        tenant_id: <?php echo $tenant_id; ?>,
        year_month: '<?php echo $year_month; ?>',
        start_date: '<?php echo $start_date; ?>',
        end_date: '<?php echo $end_date; ?>',
        monthly_sales: <?php echo json_encode($monthly_sales); ?>,
        service_sales: <?php echo json_encode($service_sales); ?>,
        staff_sales: <?php echo json_encode($staff_sales); ?>,
        debug: <?php echo json_encode(isset($debug_info) ? $debug_info : []); ?>
    };
    
    // デバッグ情報をコンソールに出力
    console.log('デバッグ情報:', CONFIG.debug);
    console.log('月間売上データ:', CONFIG.monthly_sales);
</script>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ja.js"></script>
<script src="assets/js/sales/sales.js"></script>

<?php 
// 支払い方法のテキスト取得関数
function getPaymentMethodText($method) {
    switch ($method) {
        case 'cash': return '現金';
        case 'credit_card': return 'クレジットカード';
        case 'qr_code': return 'QRコード決済';
        case 'bank_transfer': return '銀行振込';
        default: return 'その他';
    }
}

// 支払いステータスのバッジHTML取得関数
function getPaymentStatusBadge($status) {
    switch ($status) {
        case 'completed':
            return '<span class="badge bg-success">完了</span>';
        case 'pending':
            return '<span class="badge bg-warning">保留中</span>';
        case 'cancelled':
            return '<span class="badge bg-danger">キャンセル</span>';
        case 'refunded':
            return '<span class="badge bg-info">返金済</span>';
        default:
            return '<span class="badge bg-secondary">不明</span>';
    }
}

require_once 'includes/footer.php'; 
?> 