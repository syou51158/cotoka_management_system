<?php
/**
 * グローバル管理者ダッシュボード（Supabase版）
 */

require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';

if (!isLoggedIn()) { redirect('login.php'); }
if (!isGlobalAdmin()) { redirect('dashboard.php'); }

// サロン更新（Supabase RPC）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_salon') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'セキュリティトークンが無効です。');
        redirect('global-admin-dashboard.php');
    }
    $payload = [
        'p_salon_id' => (int)($_POST['salon_id'] ?? 0),
        'p_name' => trim($_POST['salon_name'] ?? ''),
        'p_address' => trim($_POST['address'] ?? ''),
        'p_phone' => trim($_POST['phone'] ?? ''),
        'p_email' => trim($_POST['email'] ?? ''),
        'p_status' => trim($_POST['status'] ?? 'active'),
        'p_max_storage_mb' => ($_POST['max_storage_mb'] === '' ? null : (int)$_POST['max_storage_mb'])
    ];
    if ($payload['p_salon_id'] <= 0 || $payload['p_name'] === '') {
        setFlashMessage('error', 'サロンIDとサロン名は必須です。');
        redirect('global-admin-dashboard.php');
    }
    $apiKey = (defined('SUPABASE_SERVICE_ROLE_KEY') && SUPABASE_SERVICE_ROLE_KEY !== '') ? SUPABASE_SERVICE_ROLE_KEY : null;
    $res = supabaseRpcCall('salons_update_admin', $payload, $apiKey);
    if (!empty($res['success'])) {
        setFlashMessage('success', 'サロン情報を更新しました。');
    } else {
        setFlashMessage('error', '更新に失敗しました: ' . ($res['message'] ?? ''));
    }
    redirect('global-admin-dashboard.php');
}

// Supabaseから集計・一覧を取得
$counts = fetchGlobalCountsFromSupabase();
$tenantsList = fetchTenantsListAdminFromSupabase();

$tenants = $tenantsList; // 下位互換
$salonCount = $counts['salons_count'] ?? 0;
$userCount = $counts['users_count'] ?? 0;
$appointmentCount = $counts['appointments_count'] ?? 0;

// テナント別サロン数（チャート用）
$salonsByTenant = array_map(function($row){
    return [
        'tenant_id' => $row['tenant_id'] ?? null,
        'tenant_name' => $row['company_name'] ?? '',
        'salon_count' => $row['salon_count'] ?? 0
    ];
}, $tenantsList);

$pageTitle = "グローバル管理ダッシュボード";
$today = date('Y年m月d日');
$csrf_token = generateCSRFToken();

// header.php が $conn を参照するためダミーを用意
$conn = null;
include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4"><i class="fas fa-globe me-2"></i>グローバル管理ダッシュボード</h1>
            <?php $flash = getFlashMessage(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                <?= $flash['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-building me-2"></i>テナント数</h5>
                    <h2 class="display-4"><?php echo count($tenants); ?></h2>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a href="tenant-management.php" class="text-white">詳細を見る</a>
                    <div><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-store me-2"></i>サロン数</h5>
                    <h2 class="display-4"><?php echo $salonCount; ?></h2>
                </div>
                <div class="card-footer d-flex align-items-center justify-between">
                    <a href="global-salon-list.php" class="text-white">詳細を見る</a>
                    <div><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-users me-2"></i>ユーザー数</h5>
                    <h2 class="display-4"><?php echo $userCount; ?></h2>
                </div>
                <div class="card-footer d-flex align-items-center justify-between">
                    <a href="user-management.php" class="text-white">詳細を見る</a>
                    <div><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-calendar-check me-2"></i>予約数</h5>
                    <h2 class="display-4"><?php echo $appointmentCount; ?></h2>
                </div>
                <div class="card-footer d-flex align-items-center justify-between">
                    <a href="global-appointment-list.php" class="text-white">詳細を見る</a>
                    <div><i class="fas fa-angle-right"></i></div>
                </div>
            </div>
        </div>
    </div>

    <?php
    // 最近のサロン一覧（Supabase）
    $recentSalons = [];
    try {
        $apiKey = (defined('SUPABASE_SERVICE_ROLE_KEY') && SUPABASE_SERVICE_ROLE_KEY !== '') ? SUPABASE_SERVICE_ROLE_KEY : null;
        $rpc = supabaseRpcCall('salons_list_recent_admin', ['p_limit' => 5], $apiKey);
        if (!empty($rpc['success'])) {
            $recentSalons = is_array($rpc['data']) ? $rpc['data'] : [];
        }
    } catch (Exception $e) {}
    ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-store me-2"></i>最近のサロン一覧</h5>
                    <a href="global-salon-list.php" class="btn btn-outline-primary btn-sm">すべて表示</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>サロン名</th>
                                    <th>テナント</th>
                                    <th>住所</th>
                                    <th>連絡先</th>
                                    <th>ステータス</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentSalons)): ?>
                                    <tr><td colspan="6" class="text-muted">データがありません。</td></tr>
                                <?php else: foreach ($recentSalons as $row): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['salon_name'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['company_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['address'] ?? ''); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['phone'] ?? ''); ?>
                                            <?php if (!empty($row['email'])): ?><div class="text-muted small"><?php echo htmlspecialchars($row['email']); ?></div><?php endif; ?>
                                        </td>
                                        <td>
                                            <?php $status = $row['status'] ?? 'active'; $cls = $status==='active'?'success':'secondary'; ?>
                                            <span class="badge bg-<?php echo $cls; ?>"><?php echo htmlspecialchars($status); ?></span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                    data-bs-toggle="modal" data-bs-target="#editSalonModal"
                                                    data-salon-id="<?php echo (int)($row['salon_id'] ?? 0); ?>">
                                                <i class="fas fa-edit"></i> 編集
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-building me-2"></i>テナント一覧</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>テナント名</th>
                                    <th>サロン数</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salonsByTenant as $tenant): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tenant['tenant_name']); ?></td>
                                    <td><?php echo $tenant['salon_count']; ?></td>
                                    <td>
                                        <a href="tenant_detail.php?id=<?php echo urlencode($tenant['tenant_id']); ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> 詳細
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="tenant-management.php" class="btn btn-outline-primary">すべてのテナントを表示</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>テナント別サロン数</h5>
                </div>
                <div class="card-body">
                    <canvas id="tenantSalonChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js 読み込み（このページ用） -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('tenantSalonChart').getContext('2d');
    const tenantData = <?php $chartData = ['labels'=>array_column($salonsByTenant,'tenant_name'), 'counts'=>array_column($salonsByTenant,'salon_count')]; echo json_encode($chartData); ?>;
    new Chart(ctx, { type: 'bar', data: { labels: tenantData.labels, datasets: [{ label: 'サロン数', data: tenantData.counts, backgroundColor: 'rgba(54, 162, 235, 0.6)', borderColor: 'rgba(54, 162, 235, 1)', borderWidth: 1 }] }, options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: false } } } });
});
</script>

<!-- サロン編集モーダル -->
<div class="modal fade" id="editSalonModal" tabindex="-1" aria-labelledby="editSalonModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editSalonModalLabel">サロン編集</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <form method="post" action="global-admin-dashboard.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="action" value="update_salon">
        <input type="hidden" id="edit_salon_id" name="salon_id" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">サロン名<span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="edit_salon_name" name="salon_name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">ステータス</label>
              <select class="form-select" id="edit_status" name="status">
                <option value="active">active</option>
                <option value="inactive">inactive</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">住所</label>
              <input type="text" class="form-control" id="edit_address" name="address">
            </div>
            <div class="col-md-3">
              <label class="form-label">電話</label>
              <input type="text" class="form-control" id="edit_phone" name="phone">
            </div>
            <div class="col-md-3">
              <label class="form-label">メール</label>
              <input type="email" class="form-control" id="edit_email" name="email">
            </div>
            <div class="col-md-4">
              <label class="form-label">最大ストレージ(MB)</label>
              <input type="number" class="form-control" id="edit_max_storage_mb" name="max_storage_mb" min="0" placeholder="例: 100">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="submit" class="btn btn-primary">更新する</button>
        </div>
      </form>
    </div>
  </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  var modal = document.getElementById('editSalonModal');
  if (!modal) return;
  modal.addEventListener('show.bs.modal', async function (event) {
    var btn = event.relatedTarget;
    var salonId = btn && btn.getAttribute('data-salon-id');
    if (!salonId) return;
    document.getElementById('edit_salon_id').value = salonId;
    try {
      const res = await fetch('<?= rtrim(SUPABASE_URL, '/') ?>/rest/v1/rpc/salon_get_admin', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'apikey': '<?= SUPABASE_ANON_KEY ?>',
          'Authorization': 'Bearer <?= SUPABASE_ANON_KEY ?>'
        },
        body: JSON.stringify({ p_salon_id: parseInt(salonId, 10) })
      });
      const data = await res.json();
      const row = Array.isArray(data) ? (data[0] || {}) : {};
      document.getElementById('edit_salon_name').value = row.salon_name || '';
      document.getElementById('edit_status').value = row.status || 'active';
      document.getElementById('edit_address').value = row.address || '';
      document.getElementById('edit_phone').value = row.phone || '';
      document.getElementById('edit_email').value = row.email || '';
      document.getElementById('edit_max_storage_mb').value = (row.max_storage_mb != null ? row.max_storage_mb : '');
    } catch (e) {}
  });
});
</script>
<?php include 'includes/footer.php'; ?> 