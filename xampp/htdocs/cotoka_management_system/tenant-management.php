<?php
/**
 * テナント管理ページ（Supabase版）
 */
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';

if (!isLoggedIn()) { redirect('login.php'); }
if (!isGlobalAdmin()) { redirect('dashboard.php'); }

$csrf_token = generateCSRFToken();

// 追加処理（RPC）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_tenant') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'セキュリティトークンが無効です。もう一度お試しください。');
        redirect('tenant-management.php');
    }
    $payload = [
        'p_company_name' => trim($_POST['company_name'] ?? ''),
        'p_owner_name' => trim($_POST['owner_name'] ?? ''),
        'p_email' => trim($_POST['email'] ?? ''),
        'p_phone' => trim($_POST['phone'] ?? ''),
        'p_subscription_plan' => trim($_POST['subscription_plan'] ?? 'free'),
        'p_max_salons' => (int)($_POST['max_salons'] ?? 1),
        'p_max_users' => (int)($_POST['max_users'] ?? 3),
        'p_max_storage_mb' => (int)($_POST['max_storage_mb'] ?? 100)
    ];
    if ($payload['p_company_name'] === '' || $payload['p_owner_name'] === '' || $payload['p_email'] === '') {
        setFlashMessage('error', '会社名、オーナー名、メールアドレスは必須項目です。');
        redirect('tenant-management.php');
    }
    $res = supabaseRpcCall('tenants_add', $payload);
    if ($res['success']) {
        setFlashMessage('success', 'テナントを追加しました。');
    } else {
        setFlashMessage('error', 'テナントの追加に失敗しました: ' . ($res['message'] ?? '')); 
    }
    redirect('tenant-management.php');
}

// 削除処理（RPC）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_tenant') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'セキュリティトークンが無効です。もう一度お試しください。');
        redirect('tenant-management.php');
    }
    $tenant_id = (int)($_POST['tenant_id'] ?? 0);
    if ($tenant_id > 0) {
        $res = supabaseRpcCall('tenant_delete_cascade', ['p_tenant_id' => $tenant_id]);
        if ($res['success']) {
            setFlashMessage('success', 'テナントとその関連データをすべて削除しました。');
        } else {
            setFlashMessage('error', 'テナントの削除に失敗しました: ' . ($res['message'] ?? ''));
        }
    }
    redirect('tenant-management.php');
}

// 更新処理（RPC）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_tenant') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'セキュリティトークンが無効です。もう一度お試しください。');
        redirect('tenant-management.php');
    }

    $tenant_id = (int)($_POST['tenant_id'] ?? 0);
    $payload = [
        'p_tenant_id' => $tenant_id,
        'p_company_name' => trim($_POST['company_name'] ?? ''),
        'p_owner_name' => trim($_POST['owner_name'] ?? ''),
        'p_email' => trim($_POST['email'] ?? ''),
        'p_phone' => trim($_POST['phone'] ?? ''),
        'p_subscription_plan' => trim($_POST['subscription_plan'] ?? 'free'),
        'p_subscription_status' => trim($_POST['subscription_status'] ?? 'active'),
        'p_max_salons' => (int)($_POST['max_salons'] ?? 1),
        'p_max_users' => (int)($_POST['max_users'] ?? 3),
        'p_max_storage_mb' => (int)($_POST['max_storage_mb'] ?? 100)
    ];

    if ($tenant_id <= 0 || $payload['p_company_name'] === '' || $payload['p_owner_name'] === '' || $payload['p_email'] === '') {
        setFlashMessage('error', '入力値が不足しています。会社名、オーナー名、メールは必須です。');
        redirect('tenant-management.php');
    }

    $apiKey = (defined('SUPABASE_SERVICE_ROLE_KEY') && SUPABASE_SERVICE_ROLE_KEY !== '') ? SUPABASE_SERVICE_ROLE_KEY : null;
    $res = supabaseRpcCall('tenants_update_admin', $payload, $apiKey);
    if ($res['success']) {
        setFlashMessage('success', 'テナント情報を更新しました。');
    } else {
        setFlashMessage('error', '更新に失敗しました: ' . ($res['message'] ?? ''));
    }
    redirect('tenant-management.php');
}

// 一覧取得（RPC）
$tenants = fetchTenantsListAdminFromSupabase();

$pageTitle = "テナント管理";
$today = date('Y年m月d日');
$conn = null; // header互換
include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4"><i class="fas fa-building me-2"></i>テナント管理</h1>
            <?php $flash = getFlashMessage(); if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                <?= $flash['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
            </div>
            <?php endif; ?>
            <div class="text-end mb-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTenantModal">
                    <i class="fas fa-plus me-1"></i> 新規テナント追加
                </button>
                <form method="post" action="tenant-management.php" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="add_tenant">
                    <input type="hidden" name="company_name" value="Sample Co <?= date('YmdHis') ?>">
                    <input type="hidden" name="owner_name" value="Sample Owner">
                    <input type="hidden" name="email" value="sample<?= date('YmdHis') ?>@example.com">
                    <input type="hidden" name="phone" value="">
                    <input type="hidden" name="subscription_plan" value="free">
                    <input type="hidden" name="max_salons" value="3">
                    <input type="hidden" name="max_users" value="5">
                    <input type="hidden" name="max_storage_mb" value="500">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="fas fa-magic me-1"></i> サンプルテナント作成
                    </button>
                </form>
            </div>
            <!-- 分数表示の説明 -->
            <div class="alert alert-info d-flex align-items-start" role="alert">
                <i class="fas fa-info-circle me-2 mt-1"></i>
                <div>
                    <strong>数値の見方:</strong>
                    サロン数・ユーザー数は「現在の登録数 / 契約上限」を表します。例: <code>2 / 5</code> は現在2件・上限5件です。上限は各テナントの契約プランで設定されています。
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>テナント一覧</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped" id="tenantTable">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>会社名</th>
                                    <th>オーナー</th>
                                    <th>連絡先</th>
                                    <th>プラン</th>
                                    <th>ステータス</th>
                                    <th>サロン数（現在/上限）</th>
                                    <th>ユーザー数（現在/上限）</th>
                                    <th>ストレージ(MB)</th>
                                    <th>作成日</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tenants as $tenant): ?>
                                <tr>
                                    <td><?php echo $tenant['tenant_id']; ?></td>
                                    <td><?php echo htmlspecialchars($tenant['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($tenant['owner_name'] ?? ($tenant['contact_name'] ?? '')); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($tenant['email']); ?></div>
                                        <?php if (!empty($tenant['phone'])): ?>
                                        <div class="text-muted"><?php echo htmlspecialchars($tenant['phone']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $planBadgeClass = 'bg-secondary';
                                        if (($tenant['subscription_plan'] ?? '') === 'premium') {
                                            $planBadgeClass = 'bg-primary';
                                        } elseif (($tenant['subscription_plan'] ?? '') === 'standard') {
                                            $planBadgeClass = 'bg-success';
                                        } elseif (($tenant['subscription_plan'] ?? '') === 'basic') {
                                            $planBadgeClass = 'bg-info';
                                        } elseif (($tenant['subscription_plan'] ?? '') === 'free') {
                                            $planBadgeClass = 'bg-light text-dark';
                                        }
                                        ?>
                                        <span class="badge <?php echo $planBadgeClass; ?>">
                                            <?php echo ucfirst(htmlspecialchars($tenant['subscription_plan'] ?? '')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusBadgeClass = 'bg-success';
                                        if (($tenant['subscription_status'] ?? '') === 'trial') {
                                            $statusBadgeClass = 'bg-warning text-dark';
                                        } elseif (($tenant['subscription_status'] ?? '') === 'expired') {
                                            $statusBadgeClass = 'bg-danger';
                                        } elseif (($tenant['subscription_status'] ?? '') === 'suspended') {
                                            $statusBadgeClass = 'bg-secondary';
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusBadgeClass; ?>">
                                            <?php echo ucfirst(htmlspecialchars($tenant['subscription_status'] ?? '')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <?php echo (int)($tenant['salon_count'] ?? 0); ?> / <?php echo (int)($tenant['max_salons'] ?? 0); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <?php echo (int)($tenant['user_count'] ?? 0); ?> / <?php echo (int)($tenant['max_users'] ?? 0); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo isset($tenant['max_storage_mb']) ? (int)$tenant['max_storage_mb'] : '-'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo isset($tenant['created_at']) ? date('Y/m/d', strtotime($tenant['created_at'])) : ''; ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#tenantSalonsModal"
                                                data-tenant-id="<?php echo (int)$tenant['tenant_id']; ?>"
                                                data-company-name="<?php echo htmlspecialchars($tenant['company_name']); ?>"
                                                data-tenant-storage="<?php echo isset($tenant['max_storage_mb']) ? (int)$tenant['max_storage_mb'] : ''; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button"
                                                class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editTenantModal"
                                                data-tenant-id="<?php echo (int)$tenant['tenant_id']; ?>"
                                                data-company-name="<?php echo htmlspecialchars($tenant['company_name']); ?>"
                                                data-owner-name="<?php echo htmlspecialchars($tenant['owner_name'] ?? ($tenant['contact_name'] ?? '')); ?>"
                                                data-email="<?php echo htmlspecialchars($tenant['email']); ?>"
                                                data-phone="<?php echo htmlspecialchars($tenant['phone'] ?? ''); ?>"
                                                data-subscription-plan="<?php echo htmlspecialchars($tenant['subscription_plan'] ?? 'free'); ?>"
                                                data-subscription-status="<?php echo htmlspecialchars($tenant['subscription_status'] ?? 'active'); ?>"
                                                data-max-salons="<?php echo (int)($tenant['max_salons'] ?? 1); ?>"
                                                data-max-users="<?php echo (int)($tenant['max_users'] ?? 3); ?>"
                                                data-max-storage-mb="<?php echo (int)($tenant['max_storage_mb'] ?? 100); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteTenantModal" data-tenant-id="<?php echo $tenant['tenant_id']; ?>" data-tenant-name="<?php echo htmlspecialchars($tenant['company_name']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 削除確認モーダル / 追加モーダルは元のまま -->
<?php include 'includes/footer.php'; ?>
<!-- 編集モーダル -->
<div class="modal fade" id="editTenantModal" tabindex="-1" aria-labelledby="editTenantModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editTenantModalLabel">テナントを編集</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <form method="post" action="tenant-management.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="action" value="update_tenant">
        <input type="hidden" name="tenant_id" id="edit_tenant_id" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">会社名<span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="edit_company_name" name="company_name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">オーナー名<span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="edit_owner_name" name="owner_name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">メール<span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="edit_email" name="email" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">電話</label>
              <input type="text" class="form-control" id="edit_phone" name="phone">
            </div>
            <div class="col-md-4">
              <label class="form-label">プラン</label>
              <select class="form-select" id="edit_subscription_plan" name="subscription_plan">
                <option value="free">free</option>
                <option value="basic">basic</option>
                <option value="premium">premium</option>
                <option value="enterprise">enterprise</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">ステータス</label>
              <select class="form-select" id="edit_subscription_status" name="subscription_status">
                <option value="active">active</option>
                <option value="trial">trial</option>
                <option value="suspended">suspended</option>
                <option value="expired">expired</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">最大ストレージ(MB)</label>
              <input type="number" class="form-control" id="edit_max_storage_mb" name="max_storage_mb" min="10" value="100">
            </div>
            <div class="col-md-4">
              <label class="form-label">最大サロン</label>
              <input type="number" class="form-control" id="edit_max_salons" name="max_salons" min="1" value="1">
            </div>
            <div class="col-md-4">
              <label class="form-label">最大ユーザー</label>
              <input type="number" class="form-control" id="edit_max_users" name="max_users" min="1" value="3">
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
document.addEventListener('DOMContentLoaded', function() {
  var editModal = document.getElementById('editTenantModal');
  if (!editModal) return;
  editModal.addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    if (!button) return;
    document.getElementById('edit_tenant_id').value = button.getAttribute('data-tenant-id') || '';
    document.getElementById('edit_company_name').value = button.getAttribute('data-company-name') || '';
    document.getElementById('edit_owner_name').value = button.getAttribute('data-owner-name') || '';
    document.getElementById('edit_email').value = button.getAttribute('data-email') || '';
    document.getElementById('edit_phone').value = button.getAttribute('data-phone') || '';
    document.getElementById('edit_subscription_plan').value = button.getAttribute('data-subscription-plan') || 'free';
    document.getElementById('edit_subscription_status').value = button.getAttribute('data-subscription-status') || 'active';
    document.getElementById('edit_max_salons').value = button.getAttribute('data-max-salons') || 1;
    document.getElementById('edit_max_users').value = button.getAttribute('data-max-users') || 3;
    document.getElementById('edit_max_storage_mb').value = button.getAttribute('data-max-storage-mb') || 100;
  });
});
</script>

<style>
/* サイドメニュー幅に合わせてモーダル領域を右側に限定 */
:root { --sidebar-width: 250px; }
body.modal-tenant-salons .modal-backdrop.show {
  left: var(--sidebar-width);
  width: calc(100vw - var(--sidebar-width));
  z-index: 1990;
}
body.modal-tenant-salons #tenantSalonsModal .modal-dialog {
  margin-left: var(--sidebar-width);
  max-width: calc(100vw - (var(--sidebar-width) + 20px));
}
body.modal-tenant-salons #tenantSalonsModal.modal {
  z-index: 2000;
}
@media (max-width: 991.98px) {
  body.modal-tenant-salons .modal-backdrop.show {
    left: 0;
    width: 100vw;
  }
  body.modal-tenant-salons #tenantSalonsModal .modal-dialog {
    margin-left: 0;
    max-width: 100vw;
  }
}
</style>

<!-- テナントのサロン一覧モーダル -->
<div class="modal fade" id="tenantSalonsModal" tabindex="-1" aria-labelledby="tenantSalonsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tenantSalonsModalLabel">サロン一覧</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <div class="d-flex align-items-center gap-2">
            <span class="text-muted">テナントの最大ストレージ(MB):</span>
            <span id="tenantStorageBadge" class="badge bg-secondary">-</span>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-striped" id="tenantSalonsTable">
            <thead>
              <tr>
                <th>サロン名</th>
                <th>住所</th>
                <th>電話</th>
                <th>メール</th>
                <th>最大ストレージ(MB)</th>
                <th>ステータス</th>
                <th>登録日</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
      </div>
    </div>
  </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var salonsModal = document.getElementById('tenantSalonsModal');
  if (!salonsModal) return;
  salonsModal.addEventListener('show.bs.modal', async function (event) {
    // サイドバー幅を検出してCSS変数に反映
    var sidebarEl = document.querySelector('#sidebar') || document.querySelector('.sidebar');
    var sw = sidebarEl && sidebarEl.offsetWidth ? sidebarEl.offsetWidth : 250;
    document.documentElement.style.setProperty('--sidebar-width', sw + 'px');
    document.body.classList.add('modal-tenant-salons');
    var button = event.relatedTarget;
    var tenantId = button.getAttribute('data-tenant-id');
    var companyName = button.getAttribute('data-company-name') || '';
    var tenantStorage = button.getAttribute('data-tenant-storage');
    document.getElementById('tenantSalonsModalLabel').textContent = companyName + ' のサロン一覧';
    var badge = document.getElementById('tenantStorageBadge');
    if (badge) { badge.textContent = (tenantStorage && tenantStorage !== '') ? tenantStorage : '-'; }
    var tbody = document.querySelector('#tenantSalonsTable tbody');
    tbody.innerHTML = '<tr><td colspan="6">読み込み中...</td></tr>';
    try {
      // Supabase RPC を直接呼び出し
      const res = await fetch('<?php echo rtrim(SUPABASE_URL, '/'); ?>/rest/v1/rpc/salons_list_by_tenant', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'apikey': '<?php echo SUPABASE_ANON_KEY; ?>',
          'Authorization': 'Bearer <?php echo SUPABASE_ANON_KEY; ?>'
        },
        body: JSON.stringify({ p_tenant_id: parseInt(tenantId, 10) })
      });
      const data = await res.json();
      if (!Array.isArray(data) || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6">サロンはありません。</td></tr>';
        return;
      }
      tbody.innerHTML = '';
      data.forEach(function(row){
        var statusBadge = row.is_active ? '<span class="badge bg-success">アクティブ</span>' : '<span class="badge bg-secondary">非アクティブ</span>';
        var created = row.created_at ? new Date(row.created_at).toLocaleString('ja-JP') : '';
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td>' + (row.salon_name || '') + '</td>' +
          '<td>' + (row.address || '') + '</td>' +
          '<td>' + (row.phone || '') + '</td>' +
          '<td>' + (row.email || '') + '</td>' +
          '<td>' + (row.max_storage_mb != null ? row.max_storage_mb : '-') + '</td>' +
          '<td>' + statusBadge + '</td>' +
          '<td>' + created + '</td>';
        tbody.appendChild(tr);
      });
    } catch(e) {
      tbody.innerHTML = '<tr><td colspan="6">読み込みに失敗しました。</td></tr>';
    }
  });
  salonsModal.addEventListener('hidden.bs.modal', function(){
    document.body.classList.remove('modal-tenant-salons');
  });
});
</script>
<!-- 追加: テナント追加モーダル -->
<div class="modal fade" id="addTenantModal" tabindex="-1" aria-labelledby="addTenantModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addTenantModalLabel">新規テナント追加</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <form method="post" action="tenant-management.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="action" value="add_tenant">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">会社名<span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="company_name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">オーナー名<span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="owner_name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">メール<span class="text-danger">*</span></label>
            <input type="email" class="form-control" name="email" required>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">電話</label>
              <input type="text" class="form-control" name="phone">
            </div>
            <div class="col-6">
              <label class="form-label">プラン</label>
              <select class="form-select" name="subscription_plan">
                <option value="free">free</option>
                <option value="basic">basic</option>
                <option value="premium">premium</option>
                <option value="enterprise">enterprise</option>
              </select>
            </div>
          </div>
          <div class="row g-2 mt-2">
            <div class="col-4">
              <label class="form-label">最大サロン</label>
              <input type="number" class="form-control" name="max_salons" value="1" min="1">
            </div>
            <div class="col-4">
              <label class="form-label">最大ユーザー</label>
              <input type="number" class="form-control" name="max_users" value="3" min="1">
            </div>
            <div class="col-4">
              <label class="form-label">最大ストレージ(MB)</label>
              <input type="number" class="form-control" name="max_storage_mb" value="100" min="10">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="submit" class="btn btn-primary">追加</button>
        </div>
      </form>
    </div>
  </div>
  </div>