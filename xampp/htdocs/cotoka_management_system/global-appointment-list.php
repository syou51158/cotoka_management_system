<?php
/**
 * グローバル管理者用：全予約一覧ページ
 */

require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';

if (!isLoggedIn()) { redirect('login.php'); }
if (!isGlobalAdmin()) { redirect('dashboard.php'); }

// フィルター用の日付範囲（デフォルト: 1週間）
$start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$end_date = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d', strtotime('+7 days'));

// テナントフィルター
$tenant_filter = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;

// Supabaseから全予約一覧を取得
$appointmentsList = [];
$rpcParams = [
    'p_start_date' => $start_date,
    'p_end_date' => $end_date
];
if ($tenant_filter) {
    $rpcParams['p_tenant_id'] = $tenant_filter;
}

$rpcResult = supabaseRpcCall('appointments_list_admin', $rpcParams, SUPABASE_SERVICE_ROLE_KEY);
if ($rpcResult['success']) {
    $appointmentsList = is_array($rpcResult['data']) ? $rpcResult['data'] : [];
}

// テナント一覧を取得（フィルター用）
$tenantsList = [];
$tenantsRpc = supabaseRpcCall('tenants_list_admin', [], SUPABASE_SERVICE_ROLE_KEY);
if ($tenantsRpc['success']) {
    $tenantsList = is_array($tenantsRpc['data']) ? $tenantsRpc['data'] : [];
}

$pageTitle = "全予約一覧";
$csrf_token = generateCSRFToken();

// header.php が $conn を参照するためダミーを用意
$conn = null;
include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-calendar-check me-2"></i>全予約一覧</h1>
                <a href="global-admin-dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>グローバル管理者ダッシュボードに戻る
                </a>
            </div>
        </div>
    </div>

    <!-- フィルター -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">フィルター</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="start" class="form-label">開始日</label>
                            <input type="date" class="form-control" id="start" name="start" 
                                   value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end" class="form-label">終了日</label>
                            <input type="date" class="form-control" id="end" name="end" 
                                   value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="tenant_id" class="form-label">テナント</label>
                            <select class="form-select" id="tenant_id" name="tenant_id">
                                <option value="">すべてのテナント</option>
                                <?php foreach ($tenantsList as $tenant): ?>
                                    <option value="<?php echo $tenant['tenant_id']; ?>" 
                                            <?php echo ($tenant_filter == $tenant['tenant_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tenant['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i>検索
                            </button>
                            <a href="global-appointment-list.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>リセット
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 予約一覧 -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        システム全体の予約一覧
                        <span class="badge bg-primary ms-2"><?php echo count($appointmentsList); ?>件</span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($appointmentsList)): ?>
                        <div class="alert alert-info">
                            指定された期間の予約がありません。
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>予約日時</th>
                                        <th>サロン名</th>
                                        <th>テナント</th>
                                        <th>顧客名</th>
                                        <th>スタッフ</th>
                                        <th>サービス</th>
                                        <th>ステータス</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointmentsList as $appointment): ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                $appointmentDate = $appointment['appointment_date'] ?? '';
                                                $appointmentTime = $appointment['appointment_time'] ?? '';
                                                if ($appointmentDate && $appointmentTime) {
                                                    echo date('m/d', strtotime($appointmentDate)) . ' ' . 
                                                         date('H:i', strtotime($appointmentTime));
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($appointment['salon_name'] ?? ''); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($appointment['company_name'] ?? ''); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($appointment['customer_name'] ?? ''); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($appointment['staff_name'] ?? ''); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($appointment['service_name'] ?? ''); ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $status = $appointment['status'] ?? '';
                                                $statusClass = 'secondary';
                                                switch ($status) {
                                                    case 'confirmed': $statusClass = 'success'; break;
                                                    case 'pending': $statusClass = 'warning'; break;
                                                    case 'cancelled': $statusClass = 'danger'; break;
                                                    case 'completed': $statusClass = 'info'; break;
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $statusClass; ?>">
                                                    <?php echo htmlspecialchars($status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="appointment-details.php?id=<?php echo $appointment['appointment_id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye me-1"></i>詳細
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
