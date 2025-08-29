<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/Tenant.php';

// マルチテナント機能が無効の場合はアクセス不可
if (!MULTI_TENANT_ENABLED) {
    setFlashMessage('error', 'マルチテナント機能が有効になっていません。');
    redirect('dashboard.php');
}

// ログインチェックとスーパー管理者のみアクセス可能
if (!isLoggedIn()) {
    setFlashMessage('error', 'この機能を利用するにはログインが必要です。');
    redirect('login.php');
}

if (!isLoggedInAsSuperAdmin()) {
    setFlashMessage('error', 'この機能にアクセスする権限がありません。スーパー管理者としてログインしてください。');
    redirect('super-admin-login.php');
}

// テナントIDの取得
$tenantId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($tenantId <= 0) {
    setFlashMessage('error', '無効なテナントIDです。');
    redirect('tenant-management.php');
}

// データベース接続
$db = new Database();
$tenantObj = new Tenant();

// テナント情報の取得
$tenant = $tenantObj->getById($tenantId);

if (!$tenant) {
    setFlashMessage('error', '指定されたテナントが見つかりません。');
    redirect('tenant-management.php');
}

// 追加: CSRFトークン生成
$csrf_token = generateCSRFToken();

// 追加: サロン追加のPOST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'add_salon')) {
    // CSRF検証
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'セキュリティトークンが無効です。');
        redirect('tenant_detail.php?id=' . urlencode($tenant['tenant_id']));
    }

    // 入力値の取得・検証
    $salon_name = trim($_POST['salon_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    if ($salon_name === '') {
        setFlashMessage('error', 'サロン名は必須です。');
        redirect('tenant_detail.php?id=' . urlencode($tenant['tenant_id']));
    }

    // リソース上限チェック（サロン数）
    if (!$tenantObj->checkResourceLimit($tenant['tenant_id'], 'salons')) {
        setFlashMessage('error', 'サロンの上限数に達しています。プランの見直しをご検討ください。');
        redirect('tenant_detail.php?id=' . urlencode($tenant['tenant_id']));
    }

    try {
        // ローカルDBへ挿入（サロン作成）
        $sql = "INSERT INTO salons (tenant_id, name, address, phone, email, status) VALUES (?, ?, ?, ?, ?, ?)";
        $db->query($sql, [
            $tenant['tenant_id'],
            $salon_name,
            ($address !== '' ? $address : null),
            ($phone !== '' ? $phone : null),
            ($email !== '' ? $email : null),
            ($status !== '' ? $status : 'active')
        ]);

        setFlashMessage('success', 'サロンを追加しました。');
    } catch (Exception $e) {
        setFlashMessage('error', 'サロンの追加に失敗しました: ' . $e->getMessage());
    }

    redirect('tenant_detail.php?id=' . urlencode($tenant['tenant_id']));
}

// テナント営業時間設定の取得
$sql = "SELECT setting_value FROM tenant_settings 
        WHERE tenant_id = ? AND setting_key = 'business_hours'";
$businessHoursSetting = $db->fetchOne($sql, [$tenantId]);

$businessHours = null;
if ($businessHoursSetting) {
    $businessHours = json_decode($businessHoursSetting['setting_value'], true);
}

// テナントのサロン情報を取得
$sql = "SELECT *, name as salon_name FROM salons WHERE tenant_id = ? ORDER BY name";
$salons = $db->fetchAll($sql, [$tenantId]);

// テナントのユーザー情報を取得
$sql = "SELECT u.* FROM users u 
        JOIN user_salons us ON u.id = us.user_id 
        JOIN salons s ON us.salon_id = s.salon_id 
        WHERE s.tenant_id = ? 
        GROUP BY u.id 
        ORDER BY u.name";
$users = $db->fetchAll($sql, [$tenantId]);

// ページタイトルとCSS設定
$page_title = 'テナント詳細';
$page_css = '<link rel="stylesheet" href="tenant-management.css">';

// フラッシュメッセージの取得
$flashMessage = getFlashMessage();

// ヘッダーを含める
require_once 'includes/header.php';

// 曜日の日本語名
$weekdayNames = [
    'monday' => '月曜日',
    'tuesday' => '火曜日',
    'wednesday' => '水曜日',
    'thursday' => '木曜日',
    'friday' => '金曜日',
    'saturday' => '土曜日',
    'sunday' => '日曜日'
];

// サブスクリプションプランのバッジカラー
$planColors = [
    'free' => 'secondary',
    'basic' => 'info',
    'standard' => 'primary',
    'premium' => 'success'
];

// サブスクリプションステータスのバッジカラー
$statusColors = [
    'trial' => 'warning',
    'active' => 'success',
    'expired' => 'danger',
    'suspended' => 'danger',
    'cancelled' => 'secondary'
];
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="page-title mb-4">
                <i class="fas fa-building"></i> <?php echo $page_title; ?>
            </h1>
            
            <?php if ($flashMessage): ?>
                <div class="alert alert-<?php echo $flashMessage['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $flashMessage['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">ダッシュボード</a></li>
                    <li class="breadcrumb-item"><a href="tenant-management.php">テナント管理</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo sanitize($tenant['company_name']); ?></li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle"></i> 基本情報
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <h2><?php echo sanitize($tenant['company_name']); ?></h2>
                            <span class="badge bg-<?php echo $planColors[$tenant['subscription_plan']] ?? 'secondary'; ?>">
                                <?php echo sanitize($tenant['subscription_plan']); ?>
                            </span>
                            <span class="badge bg-<?php echo $statusColors[$tenant['subscription_status']] ?? 'secondary'; ?>">
                                <?php echo sanitize($tenant['subscription_status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <table class="table table-striped">
                        <tr>
                            <th>テナントID:</th>
                            <td><?php echo $tenant['tenant_id']; ?></td>
                        </tr>
                        <tr>
                            <th>オーナー名:</th>
                            <td><?php echo sanitize($tenant['owner_name']); ?></td>
                        </tr>
                        <tr>
                            <th>メールアドレス:</th>
                            <td><?php echo sanitize($tenant['email']); ?></td>
                        </tr>
                        <tr>
                            <th>電話番号:</th>
                            <td><?php echo sanitize($tenant['phone']); ?></td>
                        </tr>
                        <tr>
                            <th>住所:</th>
                            <td><?php echo sanitize($tenant['address'] ?? 'なし'); ?></td>
                        </tr>
                        <tr>
                            <th>サブスクリプション:</th>
                            <td>
                                <span class="badge bg-<?php echo $planColors[$tenant['subscription_plan']] ?? 'secondary'; ?>">
                                    <?php echo sanitize($tenant['subscription_plan']); ?>
                                </span>
                                <span class="badge bg-<?php echo $statusColors[$tenant['subscription_status']] ?? 'secondary'; ?>">
                                    <?php echo sanitize($tenant['subscription_status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php if ($tenant['subscription_status'] === 'trial'): ?>
                        <tr>
                            <th>トライアル終了日:</th>
                            <td><?php echo date('Y年m月d日', strtotime($tenant['trial_ends_at'])); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>サブスクリプション終了日:</th>
                            <td>
                                <?php echo $tenant['subscription_ends_at'] ? date('Y年m月d日', strtotime($tenant['subscription_ends_at'])) : '無期限'; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>最大サロン数:</th>
                            <td><?php echo $tenant['max_salons']; ?></td>
                        </tr>
                        <tr>
                            <th>最大ユーザー数:</th>
                            <td><?php echo $tenant['max_users']; ?></td>
                        </tr>
                        <tr>
                            <th>最大ストレージ容量:</th>
                            <td><?php echo $tenant['max_storage_mb']; ?> MB</td>
                        </tr>
                        <tr>
                            <th>作成日:</th>
                            <td><?php echo date('Y年m月d日 H:i', strtotime($tenant['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <th>更新日:</th>
                            <td><?php echo date('Y年m月d日 H:i', strtotime($tenant['updated_at'])); ?></td>
                        </tr>
                    </table>
                    
                    <div class="mt-3">
                        <a href="tenant-management.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> 戻る
                        </a>
                        <a href="tenant_edit.php?id=<?php echo $tenant['tenant_id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> 編集
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clock"></i> 営業時間
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($businessHours): ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>曜日</th>
                                    <th>営業状態</th>
                                    <th>営業時間</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($businessHours as $day => $hours): ?>
                                <tr>
                                    <td><?php echo $weekdayNames[$day] ?? $day; ?></td>
                                    <td>
                                        <?php if ($hours['is_open']): ?>
                                            <span class="badge bg-success">営業日</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">休業日</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($hours['is_open']): ?>
                                            <?php echo $hours['open_time']; ?> - <?php echo $hours['close_time']; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="mt-3">
                            <a href="tenant-management.php?action=business_hours&id=<?php echo $tenant['tenant_id']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> 営業時間を編集
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            営業時間が設定されていません。
                        </div>
                        <div class="mt-3">
                            <a href="tenant-management.php?action=business_hours&id=<?php echo $tenant['tenant_id']; ?>" class="btn btn-primary">
                                <i class="fas fa-plus"></i> 営業時間を設定
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-store"></i> サロン一覧
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (count($salons) > 0): ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>サロン名</th>
                                    <th>ステータス</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salons as $salon): ?>
                                <tr>
                                    <td><?php echo $salon['salon_id']; ?></td>
                                    <td><?php echo sanitize($salon['salon_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $salon['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo $salon['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info">
                            サロンが登録されていません。
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <!-- 変更: モーダル起動ボタンに置換 -->
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSalonModal">
                            <i class="fas fa-plus"></i> サロンを追加
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users"></i> ユーザー一覧
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (count($users) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>ユーザー名</th>
                                        <th>メールアドレス</th>
                                        <th>役割</th>
                                        <th>ステータス</th>
                                        <th>最終ログイン</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo sanitize($user['name']); ?></td>
                                        <td><?php echo sanitize($user['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'manager' ? 'warning' : 'info'); ?>">
                                                <?php echo sanitize($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo sanitize($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $user['last_login'] ? date('Y/m/d H:i', strtotime($user['last_login'])) : '未ログイン'; ?>
                                        </td>
                                        <td>
                                            <a href="#" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            ユーザーが登録されていません。
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <a href="#" class="btn btn-primary">
                            <i class="fas fa-plus"></i> ユーザーを追加
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 追加: サロン追加モーダル -->
<div class="modal fade" id="addSalonModal" tabindex="-1" aria-labelledby="addSalonModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addSalonModalLabel">サロンを追加</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
      </div>
      <form method="post" action="tenant_detail.php?id=<?php echo urlencode($tenant['tenant_id']); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="action" value="add_salon">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">サロン名 <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="salon_name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">ステータス</label>
              <select class="form-select" name="status">
                <option value="active" selected>active</option>
                <option value="inactive">inactive</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">住所</label>
              <input type="text" class="form-control" name="address">
            </div>
            <div class="col-md-3">
              <label class="form-label">電話</label>
              <input type="text" class="form-control" name="phone">
            </div>
            <div class="col-md-3">
              <label class="form-label">メール</label>
              <input type="email" class="form-control" name="email">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
          <button type="submit" class="btn btn-primary">追加する</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- フッターを含める -->
<?php require_once 'includes/footer.php'; ?>
