<?php
/**
 * テナント編集ページ（Supabase版）
 */
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';

if (!isLoggedIn()) { redirect('login.php'); }
if (!isGlobalAdmin()) { redirect('dashboard.php'); }

$tenant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($tenant_id <= 0) { redirect('tenant-management.php'); }

$csrf_token = generateCSRFToken();

// 更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_tenant') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'セキュリティトークンが無効です。もう一度お試しください。');
        redirect('tenant-edit.php?id=' . $tenant_id);
    }

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

    if ($payload['p_company_name'] === '' || $payload['p_owner_name'] === '' || $payload['p_email'] === '') {
        setFlashMessage('error', '会社名、オーナー名、メールアドレスは必須項目です。');
        redirect('tenant-edit.php?id=' . $tenant_id);
    }

    $apiKey = defined('SUPABASE_SERVICE_ROLE_KEY') && SUPABASE_SERVICE_ROLE_KEY !== '' ? SUPABASE_SERVICE_ROLE_KEY : null;
    $res = supabaseRpcCall('tenants_update_admin', $payload, $apiKey);

    if ($res['success']) {
        setFlashMessage('success', 'テナント情報を更新しました。');
        redirect('tenant-management.php');
    } else {
        setFlashMessage('error', '更新に失敗しました: ' . ($res['message'] ?? ''));
        redirect('tenant-edit.php?id=' . $tenant_id);
    }
}

// 表示用 取得
$apiKey = defined('SUPABASE_SERVICE_ROLE_KEY') && SUPABASE_SERVICE_ROLE_KEY !== '' ? SUPABASE_SERVICE_ROLE_KEY : null;
$detail = supabaseRpcCall('tenant_get_admin', ['p_tenant_id' => $tenant_id], $apiKey);
$tenant = [];
if ($detail['success']) {
    $tenant = is_array($detail['data']) ? ($detail['data'][0] ?? []) : [];
}

$pageTitle = 'テナント編集';
$conn = null; // header互換
include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0"><i class="fas fa-edit me-2"></i>テナント編集</h1>
            <div>
                <a href="tenant-management.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>戻る</a>
            </div>
        </div>
    </div>

    <?php $flash = getFlashMessage(); if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <?= $flash['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">基本情報</h5>
        </div>
        <div class="card-body">
            <form method="post" action="tenant-edit.php?id=<?= $tenant_id ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="update_tenant">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">会社名<span class="text-danger">*</span></label>
                        <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($tenant['company_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">オーナー名<span class="text-danger">*</span></label>
                        <input type="text" name="owner_name" class="form-control" value="<?= htmlspecialchars($tenant['owner_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">メール<span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($tenant['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">電話</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($tenant['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">プラン</label>
                        <select name="subscription_plan" class="form-select">
                            <?php 
                            $plans = ['free','basic','premium','enterprise'];
                            $selectedPlan = $tenant['subscription_plan'] ?? 'free';
                            foreach ($plans as $plan) {
                                $sel = $selectedPlan === $plan ? 'selected' : '';
                                echo "<option value=\"{$plan}\" {$sel}>{$plan}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ステータス</label>
                        <select name="subscription_status" class="form-select">
                            <?php 
                            $statuses = ['active','trial','suspended','expired'];
                            $selectedStatus = $tenant['subscription_status'] ?? 'active';
                            foreach ($statuses as $st) {
                                $sel = $selectedStatus === $st ? 'selected' : '';
                                echo "<option value=\"{$st}\" {$sel}>{$st}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">最大ストレージ(MB)</label>
                        <input type="number" name="max_storage_mb" class="form-control" min="10" value="<?= isset($tenant['max_storage_mb']) ? (int)$tenant['max_storage_mb'] : '' ?>" placeholder="例: 100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">最大サロン</label>
                        <input type="number" name="max_salons" class="form-control" min="1" value="<?= (int)($tenant['max_salons'] ?? 1) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">最大ユーザー</label>
                        <input type="number" name="max_users" class="form-control" min="1" value="<?= (int)($tenant['max_users'] ?? 3) ?>">
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="tenant-management.php" class="btn btn-outline-secondary">キャンセル</a>
                    <button type="submit" class="btn btn-primary">更新する</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
