<?php
/**
 * グローバル管理者用：全サロン一覧ページ
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
        redirect('global-salon-list.php');
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
        redirect('global-salon-list.php');
    }
    $apiKey = (defined('SUPABASE_SERVICE_ROLE_KEY') && SUPABASE_SERVICE_ROLE_KEY !== '') ? SUPABASE_SERVICE_ROLE_KEY : null;
    $res = supabaseRpcCall('salons_update_admin', $payload, $apiKey);
    if (!empty($res['success'])) {
        setFlashMessage('success', 'サロン情報を更新しました。');
    } else {
        setFlashMessage('error', '更新に失敗しました: ' . ($res['message'] ?? ''));
    }
    redirect('global-salon-list.php');
}
// Supabaseから全サロン一覧を取得
$salonsList = [];
$rpcResult = supabaseRpcCall('salons_list_admin', [], SUPABASE_SERVICE_ROLE_KEY);
if ($rpcResult['success']) {
    $salonsList = is_array($rpcResult['data']) ? $rpcResult['data'] : [];
}

$pageTitle = "全サロン一覧";
$csrf_token = generateCSRFToken();

// header.php が $conn を参照するためダミーを用意
$conn = null;
include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-store me-2"></i>全サロン一覧</h1>
                <a href="global-admin-dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>ダッシュボードに戻る
                </a>
            </div>
            <?php $flash = getFlashMessage(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                <?= $flash['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">システム全体のサロン一覧</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($salonsList)): ?>
                        <div class="alert alert-info">
                            サロンが登録されていません。
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>サロン名</th>
                                        <th>テナント</th>
                                        <th>住所</th>
                                        <th>電話番号</th>
                                        <th>ステータス</th>
                                        <th>登録日</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($salonsList as $salon): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($salon['salon_name'] ?? ''); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($salon['company_name'] ?? ''); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($salon['address'] ?? ''); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($salon['phone'] ?? ''); ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $status = $salon['is_active'] ?? false;
                                                $statusClass = $status ? 'success' : 'secondary';
                                                $statusText = $status ? 'アクティブ' : '非アクティブ';
                                                ?>
                                                <span class="badge bg-<?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $createdAt = $salon['created_at'] ?? null;
                                                if ($createdAt) {
                                                    echo date('Y/m/d H:i', strtotime($createdAt));
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="salon-details.php?id=<?php echo $salon['salon_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                                            data-bs-toggle="modal" data-bs-target="#editSalonModal"
                                                            data-salon-id="<?php echo (int)$salon['salon_id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
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

<!-- サロン編集モーダル -->
<div class="modal fade" id="editSalonModal" tabindex="-1" aria-labelledby="editSalonModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editSalonModalLabel">サロン編集</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <form method="post" action="global-salon-list.php">
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
