<?php
/**
 * サロン切り替えセレクター（Supabase RPC版）
 */

// 現在のサロンとユーザーの情報取得
$current_salon_id = getCurrentSalonId();
$user_id = $_SESSION['user_id'] ?? null;
$role_name = $_SESSION['role_name'] ?? '';

if (!$user_id) {
    return; // ユーザーがログインしていない場合は何も表示しない
}

// ユーザーがアクセス可能なサロン一覧をSupabase RPCで取得
$rpcRes = isset($_SESSION['user_unique_id'])
    ? supabaseRpcCall('user_accessible_salons_by_uid', ['p_user_uid' => (string)$_SESSION['user_unique_id']])
    : supabaseRpcCall('user_accessible_salons', ['p_user_id' => (int)$user_id]);
$accessible_salons = $rpcRes['success'] ? ($rpcRes['data'] ?? []) : [];

// 現在のサロン情報を取得
$current_salon = null;
foreach ($accessible_salons as $salon) {
    if ((int)$salon['salon_id'] === (int)$current_salon_id) {
        $current_salon = $salon;
        break;
    }
}

// サロンがない場合
if (empty($accessible_salons)) {
    echo '<div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        アクセス可能なサロンがありません。管理者に連絡してください。
    </div>';
    return;
}

// 現在のサロンが設定されていない場合は最初のサロンを選択
if (!$current_salon && !empty($accessible_salons)) {
    $current_salon = $accessible_salons[0];
    setCurrentSalon($current_salon['salon_id']);
}
?>

<div class="salon-selector mb-4">
    <div class="card">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <i class="bi bi-shop fs-3 me-2 text-gold"></i>
                    <div>
                        <h6 class="mb-0">現在のサロン</h6>
                        <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($current_salon['name'] ?? '未選択'); ?></h5>
                    </div>
                </div>
                
                <?php if (count($accessible_salons) > 1): ?>
                <div class="salon-select-container">
                    <select id="salon-switcher" class="form-select form-select-sm">
                        <?php foreach ($accessible_salons as $salon): ?>
                            <option value="<?php echo $salon['salon_id']; ?>" 
                                    <?php echo $salon['salon_id'] == $current_salon_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($salon['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($role_name === 'admin' || $role_name === 'tenant_admin'): ?>
            <div class="mt-2 d-flex justify-content-end">
                <a href="salon_management.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-gear-fill me-1"></i> サロン設定
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const salonSwitcher = document.getElementById('salon-switcher');
    if (salonSwitcher) {
        salonSwitcher.addEventListener('change', function() {
            const selectedSalonId = this.value;
            // ローディングインジケーターを表示
            document.body.classList.add('loading');
            
            // サロン切り替えのリクエストを送信
            fetch('api/switch_salon.php?salon_id=' + selectedSalonId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 成功したらページをリロード
                        window.location.reload();
                    } else {
                        alert('サロンの切り替えに失敗しました: ' + data.message);
                        document.body.classList.remove('loading');
                    }
                })
                .catch(error => {
                    console.error('サロン切り替えエラー:', error);
                    alert('サロン切り替え中にエラーが発生しました');
                    document.body.classList.remove('loading');
                });
        });
    }
});
</script> 