<?php
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'includes/functions.php';

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// セッションチェック
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (PDOException $e) {
    error_log('データベース接続エラー: ' . $e->getMessage());
    die('システムエラーが発生しました。しばらく経ってからやり直してください。');
}

// サロンID取得（複数店舗の場合）
$salon_id = getCurrentSalonId();

// ユーザーがアクセス可能なサロンを取得
$user_id = $_SESSION['user_id'];
$user = new User($conn);
$accessible_salons = $user->getAccessibleSalons($user_id);

// サロンIDが設定されていない場合、最初のアクセス可能なサロンを選択
if (!$salon_id && count($accessible_salons) > 0) {
    $salon_id = $accessible_salons[0]['salon_id'];
    $_SESSION['salon_id'] = $salon_id;
}

// アクセス可能なサロンがない場合はエラーメッセージを表示
if (count($accessible_salons) === 0) {
    echo '<div class="alert alert-danger">アクセス可能なサロンがありません。管理者にお問い合わせください。</div>';
    include 'includes/footer.php';
    exit;
}

// 現在のサロンIDがアクセス可能なサロンのリストに含まれているか確認
$salon_accessible = false;
foreach ($accessible_salons as $salon) {
    if ($salon['salon_id'] == $salon_id) {
        $salon_accessible = true;
        break;
    }
}

// アクセス可能でない場合は最初のアクセス可能なサロンに切り替え
if (!$salon_accessible && count($accessible_salons) > 0) {
    $salon_id = $accessible_salons[0]['salon_id'];
    $_SESSION['salon_id'] = $salon_id;
}

// レポートタイプ（デフォルトは売上）
$report_type = isset($_GET['type']) ? $_GET['type'] : 'sales';

// 期間設定（デフォルトは今月）
$today = new DateTime();
$start_of_month = new DateTime('first day of this month');
$start_date = isset($_GET['start_date']) ? new DateTime($_GET['start_date']) : $start_of_month;
$end_date = isset($_GET['end_date']) ? new DateTime($_GET['end_date']) : $today;

// 期間表示用のフォーマット
$period_display = '';
if ($start_date->format('Y-m') == $end_date->format('Y-m')) {
    // 同じ月内
    $period_display = $start_date->format('Y年m月');
} else {
    // 異なる月
    $period_display = $start_date->format('Y年m月d日') . ' 〜 ' . $end_date->format('Y年m月d日');
}

// スタッフリスト取得
$staff_query = "SELECT staff_id, name AS staff_name FROM staff WHERE salon_id = :salon_id AND status = 'active'";
$staff_stmt = $conn->prepare($staff_query);
$staff_stmt->bindParam(':salon_id', $salon_id, PDO::PARAM_INT);
$staff_stmt->execute();
$staff_list = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

// サービスリスト取得
$service_query = "SELECT service_id, name AS service_name FROM services WHERE salon_id = :salon_id AND status = 'active'";
$service_stmt = $conn->prepare($service_query);
$service_stmt->bindParam(':salon_id', $salon_id, PDO::PARAM_INT);
$service_stmt->execute();
$service_list = $service_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>レポート分析 - サロン管理システム</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Flatpickr (Date picker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
    <!-- Chart.js -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/reports/reports.css">
</head>
<body>

<!-- ヘッダーとサイドバーのインクルード -->
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- メインコンテンツ -->
<div class="main-content">
    <div class="container-fluid">
        <!-- ページヘッダー -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2 id="report-title">
                    <?php
                    $report_titles = [
                        'sales' => '売上レポート',
                        'customers' => '顧客レポート',
                        'services' => 'サービスレポート',
                        'staff' => 'スタッフレポート',
                        'comparison' => '比較レポート'
                    ];
                    echo $report_titles[$report_type] ?? 'レポート分析';
                    ?>
                </h2>
            </div>
            <div class="col-md-6">
                <div class="btn-toolbar float-end" role="toolbar">
                    <div class="btn-group me-2" role="group">
                        <button type="button" id="export-csv" class="btn btn-outline-secondary">
                            <i class="fas fa-file-csv me-1"></i> CSV
                        </button>
                        <button type="button" id="export-pdf" class="btn btn-outline-secondary">
                            <i class="fas fa-file-pdf me-1"></i> PDF
                        </button>
                        <button type="button" id="print-report" class="btn btn-outline-secondary">
                            <i class="fas fa-print me-1"></i> 印刷
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- レポートタイプ選択ナビゲーション -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="report-type-nav">
                    <a href="#" class="nav-link <?php echo $report_type === 'sales' ? 'active' : ''; ?>" data-report-type="sales">
                        <i class="fas fa-chart-line"></i> 売上レポート
                    </a>
                    <a href="#" class="nav-link <?php echo $report_type === 'customers' ? 'active' : ''; ?>" data-report-type="customers">
                        <i class="fas fa-users"></i> 顧客レポート
                    </a>
                    <a href="#" class="nav-link <?php echo $report_type === 'services' ? 'active' : ''; ?>" data-report-type="services">
                        <i class="fas fa-cut"></i> サービスレポート
                    </a>
                    <a href="#" class="nav-link <?php echo $report_type === 'staff' ? 'active' : ''; ?>" data-report-type="staff">
                        <i class="fas fa-user-tie"></i> スタッフレポート
                    </a>
                    <a href="#" class="nav-link <?php echo $report_type === 'comparison' ? 'active' : ''; ?>" data-report-type="comparison">
                        <i class="fas fa-chart-bar"></i> 比較レポート
                    </a>
                </div>
            </div>
        </div>
        
        <!-- 期間選択とフィルター -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="date-navigation">
                    <a href="#" id="prev-period" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <span id="current-period" class="current-period"><?php echo $period_display; ?></span>
                    <a href="#" id="next-period" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card filter-card">
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label for="date-range-picker" class="form-label">期間</label>
                                <input type="text" id="date-range-picker" class="form-control form-control-sm" placeholder="期間を選択" value="<?php echo $start_date->format('Y-m-d') . ' to ' . $end_date->format('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="staff-filter" class="form-label">スタッフ</label>
                                <select id="staff-filter" name="staff_id" class="form-select form-select-sm filter-select">
                                    <option value="">すべて</option>
                                    <?php foreach ($staff_list as $staff): ?>
                                    <option value="<?php echo $staff['staff_id']; ?>"><?php echo htmlspecialchars($staff['staff_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="service-filter" class="form-label">サービス</label>
                                <select id="service-filter" name="service_id" class="form-select form-select-sm filter-select">
                                    <option value="">すべて</option>
                                    <?php foreach ($service_list as $service): ?>
                                    <option value="<?php echo $service['service_id']; ?>"><?php echo htmlspecialchars($service['service_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="btn-group w-100" role="group">
                                    <button type="button" id="apply-filter" class="btn btn-sm btn-primary">適用</button>
                                    <button type="button" id="reset-filter" class="btn btn-sm btn-outline-secondary">リセット</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- レポートコンテナ：売上レポート -->
        <div id="sales-report-container" class="report-container" style="display: <?php echo $report_type === 'sales' ? 'block' : 'none'; ?>">
            <!-- サマリーカード -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card summary-card" id="total_sales-summary">
                        <div class="card-body">
                            <h6 class="summary-title">総売上</h6>
                            <div class="summary-value">¥0</div>
                            <div class="summary-change">+0%</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card" id="transaction_count-summary">
                        <div class="card-body">
                            <h6 class="summary-title">取引数</h6>
                            <div class="summary-value">0</div>
                            <div class="summary-change">+0%</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card" id="customer_count-summary">
                        <div class="card-body">
                            <h6 class="summary-title">顧客数</h6>
                            <div class="summary-value">0</div>
                            <div class="summary-change">+0%</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card" id="average_sales-summary">
                        <div class="card-body">
                            <h6 class="summary-title">平均客単価</h6>
                            <div class="summary-value">¥0</div>
                            <div class="summary-change">+0%</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- チャートと表 -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>売上推移</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="sales-trend-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>売上構成</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="service-sales-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 売上データテーブル -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>売上詳細</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="sales-data-table" class="table table-hover data-table">
                                    <thead>
                                        <tr>
                                            <th>日付</th>
                                            <th>売上</th>
                                            <th>取引数</th>
                                            <th>平均客単価</th>
                                            <th>新規顧客</th>
                                            <th>リピート顧客</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- データがここに読み込まれます -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- レポートコンテナ：顧客レポート -->
        <div id="customers-report-container" class="report-container" style="display: <?php echo $report_type === 'customers' ? 'block' : 'none'; ?>">
            <!-- サマリーカード -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card summary-card" id="total_customers-summary">
                        <div class="card-body">
                            <h6 class="summary-title">総顧客数</h6>
                            <div class="summary-value">0</div>
                            <div class="summary-change">+0%</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card" id="new_customers-summary">
                        <div class="card-body">
                            <h6 class="summary-title">新規顧客</h6>
                            <div class="summary-value">0</div>
                            <div class="summary-change">+0%</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card" id="repeat_rate-summary">
                        <div class="card-body">
                            <h6 class="summary-title">リピート率</h6>
                            <div class="summary-value">0%</div>
                            <div class="summary-change">+0%</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card" id="average_customer_value-summary">
                        <div class="card-body">
                            <h6 class="summary-title">顧客生涯価値</h6>
                            <div class="summary-value">¥0</div>
                            <div class="summary-change">+0%</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- チャートと表 -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>顧客推移</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="customer-analysis-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>KPI指標</h5>
                        </div>
                        <div class="card-body">
                            <div class="kpi-container">
                                <div class="kpi-card" id="customer_acquisition-kpi">
                                    <div class="kpi-title">新規獲得率</div>
                                    <div class="kpi-value">0%</div>
                                    <div class="kpi-description">目標: 20%</div>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                                <div class="kpi-card" id="customer_retention-kpi">
                                    <div class="kpi-title">顧客維持率</div>
                                    <div class="kpi-value">0%</div>
                                    <div class="kpi-description">目標: 70%</div>
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 顧客データテーブル -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>顧客詳細</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="customers-data-table" class="table table-hover data-table">
                                    <thead>
                                        <tr>
                                            <th>顧客名</th>
                                            <th>来店回数</th>
                                            <th>最終来店日</th>
                                            <th>累計売上</th>
                                            <th>平均客単価</th>
                                            <th>好みのサービス</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- データがここに読み込まれます -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- レポートコンテナ：サービスレポート -->
        <div id="services-report-container" class="report-container" style="display: <?php echo $report_type === 'services' ? 'block' : 'none'; ?>">
            <!-- サマリーカード -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card summary-card" id="total_service_sales-summary">
                        <div class="card-body">
                            <h6 class="summary-title">サービス総売上</h6>
                            <div class="summary-value">¥0</div>
                            <div class="summary-change">+0%</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card" id="service_count-summary">
                        <div class="card-body">
                            <h6 class="summary-title">サービス提供数</h6>
                            <div class="summary-value">0</div>
                            <div class="summary-change">+0%</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card" id="top_service-summary">
                        <div class="card-body">
                            <h6 class="summary-title">最人気サービス</h6>
                            <div class="summary-value">-</div>
                            <div class="summary-change">-</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card" id="average_service_price-summary">
                        <div class="card-body">
                            <h6 class="summary-title">平均サービス単価</h6>
                            <div class="summary-value">¥0</div>
                            <div class="summary-change">+0%</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- サービスデータテーブル -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>サービス詳細</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="services-data-table" class="table table-hover data-table">
                                    <thead>
                                        <tr>
                                            <th>サービス名</th>
                                            <th>売上</th>
                                            <th>提供回数</th>
                                            <th>売上割合</th>
                                            <th>利用顧客数</th>
                                            <th>平均単価</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- データがここに読み込まれます -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- レポートコンテナ：スタッフレポート -->
        <div id="staff-report-container" class="report-container" style="display: <?php echo $report_type === 'staff' ? 'block' : 'none'; ?>">
            <!-- サマリーカード -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card summary-card" id="total_staff_sales-summary">
                        <div class="card-body">
                            <h6 class="summary-title">スタッフ総売上</h6>
                            <div class="summary-value">¥0</div>
                            <div class="summary-change">+0%</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card" id="staff_customer_count-summary">
                        <div class="card-body">
                            <h6 class="summary-title">接客顧客数</h6>
                            <div class="summary-value">0</div>
                            <div class="summary-change">+0%</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card" id="top_staff-summary">
                        <div class="card-body">
                            <h6 class="summary-title">売上トップ</h6>
                            <div class="summary-value">-</div>
                            <div class="summary-change">-</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card" id="average_staff_sales-summary">
                        <div class="card-body">
                            <h6 class="summary-title">平均スタッフ売上</h6>
                            <div class="summary-value">¥0</div>
                            <div class="summary-change">+0%</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- スタッフデータテーブル -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>スタッフ詳細</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="staff-data-table" class="table table-hover data-table">
                                    <thead>
                                        <tr>
                                            <th>スタッフ名</th>
                                            <th>売上</th>
                                            <th>顧客数</th>
                                            <th>サービス提供数</th>
                                            <th>平均売上</th>
                                            <th>得意サービス</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- データがここに読み込まれます -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- レポートコンテナ：比較レポート -->
        <div id="comparison-report-container" class="report-container" style="display: <?php echo $report_type === 'comparison' ? 'block' : 'none'; ?>">
            <!-- 比較期間選択 -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card filter-card">
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label for="base-period" class="form-label">基準期間</label>
                                    <input type="text" id="base-period" class="form-control form-control-sm" placeholder="基準期間を選択" value="<?php echo $start_date->format('Y-m-d') . ' to ' . $end_date->format('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="comparison-period" class="form-label">比較期間</label>
                                    <input type="text" id="comparison-period" class="form-control form-control-sm" placeholder="比較期間を選択">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="button" id="apply-comparison" class="btn btn-sm btn-primary w-100">比較を適用</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 比較サマリー -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>比較サマリー</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="comparison-summary-table" class="table table-hover data-table">
                                    <thead>
                                        <tr>
                                            <th>指標</th>
                                            <th>基準期間</th>
                                            <th>比較期間</th>
                                            <th>差分</th>
                                            <th>変化率</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>総売上</td>
                                            <td>¥0</td>
                                            <td>¥0</td>
                                            <td>¥0</td>
                                            <td>0%</td>
                                        </tr>
                                        <tr>
                                            <td>顧客数</td>
                                            <td>0</td>
                                            <td>0</td>
                                            <td>0</td>
                                            <td>0%</td>
                                        </tr>
                                        <tr>
                                            <td>平均客単価</td>
                                            <td>¥0</td>
                                            <td>¥0</td>
                                            <td>¥0</td>
                                            <td>0%</td>
                                        </tr>
                                        <tr>
                                            <td>新規顧客</td>
                                            <td>0</td>
                                            <td>0</td>
                                            <td>0</td>
                                            <td>0%</td>
                                        </tr>
                                        <tr>
                                            <td>リピート率</td>
                                            <td>0%</td>
                                            <td>0%</td>
                                            <td>0%</td>
                                            <td>0%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- フッター -->
<?php include 'includes/footer.php'; ?>

<!-- JavaScript Dependencies -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ja.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<!-- Custom JS -->
<script src="assets/js/script.js"></script>
<script src="assets/js/reports/reports.js"></script>

</body>
</html>
