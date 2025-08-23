<?php
/**
 * ダッシュボードカードコンポーネント
 * 
 * ダッシュボードに表示するさまざまな統計カードのコンポーネント
 */

// デバッグ出力
echo "<!-- dashboard_cards.php が読み込まれました -->";

// 一時的にエラーを表示する
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<div class='alert alert-info'>ダッシュボードカードコンポーネントをロード中...</div>";

// 必要なデータをパラメータとして受け取る
$statistics = $statistics ?? [];
$salon_id = $salon_id ?? null;
$role_name = $_SESSION['role_name'] ?? '';

// カード用のアイコンとクラスのマッピング
$card_settings = [
    'today_appointments' => [
        'icon' => 'bi-calendar-check',
        'color' => 'appointments',
        'label' => '本日の予約',
        'link' => 'appointment_manager.php?date=' . date('Y-m-d')
    ],
    'monthly_total' => [
        'icon' => 'bi-currency-yen',
        'color' => 'revenue',
        'label' => '今月の売上',
        'link' => 'sales_report.php'
    ],
    'total_customers' => [
        'icon' => 'bi-people',
        'color' => 'customers',
        'label' => '顧客数',
        'link' => 'customer_manager.php'
    ],
    'pending_appointments' => [
        'icon' => 'bi-clock-history',
        'color' => 'pending',
        'label' => '未確認予約',
        'link' => 'appointment_manager.php?status=scheduled'
    ]
];

// ボックス状のカードを表示（管理者向け）
if ($role_name === 'admin' || $role_name === 'tenant_admin') {
    // 一時的にコメントアウト
    echo "<div class='alert alert-warning'>管理者向けダッシュボードカードは一時的に無効化されています</div>";
    // include 'dashboard_cards_admin.php';
} else {
    // スタッフ向けの表示
?>

<div class="stats-cards">
    <?php foreach ($card_settings as $key => $card): ?>
        <?php if (isset($statistics[$key]) || $key === 'monthly_total'): ?>
            <div class="stats-card">
                <a href="<?php echo $card['link']; ?>" class="card-link"></a>
                <div class="card-icon card-<?php echo $card['color']; ?>">
                    <i class="bi <?php echo $card['icon']; ?>"></i>
                </div>
                <div class="card-content">
                    <h3 class="card-number">
                        <?php
                        if ($key === 'monthly_total' && isset($statistics[$key])) {
                            echo '¥' . number_format($statistics[$key]);
                        } else {
                            echo isset($statistics[$key]) ? number_format($statistics[$key]) : '<i class="bi bi-dash"></i>';
                        }
                        ?>
                    </h3>
                    <p class="card-label"><?php echo $card['label']; ?></p>
                    
                    <?php if (!isset($statistics[$key]) && $key !== 'monthly_total'): ?>
                        <div class="card-error">
                            <i class="bi bi-exclamation-circle"></i> データがありません
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php
}

// サロン別スタッフの予定（マネージャーとテナント管理者用）
if ($role_name === 'manager' || $role_name === 'tenant_admin' || $role_name === 'admin'):
?>

<div class="dashboard-section mb-4">
    <div class="dashboard-section-header">
        <h3 class="dashboard-section-title">
            <i class="bi bi-people-fill"></i> スタッフの予定
        </h3>
        <a href="staff_schedule.php" class="dashboard-section-link">
            すべて表示 <i class="bi bi-arrow-right"></i>
        </a>
    </div>
    <div class="dashboard-section-body p-3">
        <?php if (isset($staff_schedules) && !empty($staff_schedules)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>スタッフ</th>
                            <th>現在の状態</th>
                            <th>次の予約</th>
                            <th>本日の予約数</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_schedules as $staff): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($staff['profile_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($staff['profile_image']); ?>" alt="<?php echo htmlspecialchars($staff['name']); ?>" class="avatar-sm me-2">
                                        <?php else: ?>
                                            <div class="avatar-placeholder-sm me-2"><?php echo mb_substr($staff['name'], 0, 1); ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <?php echo htmlspecialchars($staff['name']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $staff['status'] === 'available' ? 'success' : ($staff['status'] === 'busy' ? 'danger' : 'warning'); ?>">
                                        <?php 
                                        echo $staff['status'] === 'available' ? '対応可能' : 
                                            ($staff['status'] === 'busy' ? '施術中' : '休憩中'); 
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($staff['next_appointment'])): ?>
                                        <div class="small text-muted"><?php echo date('H:i', strtotime($staff['next_appointment']['time'])); ?></div>
                                        <?php echo htmlspecialchars($staff['next_appointment']['customer_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">予約なし</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge rounded-pill bg-info"><?php echo $staff['today_count']; ?>件</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                本日のスタッフスケジュールはありません。
            </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<!-- 今日のスケジュール概要 (すべてのユーザー向け) -->
<div class="dashboard-section mb-4">
    <div class="dashboard-section-header">
        <h3 class="dashboard-section-title">
            <i class="bi bi-calendar3"></i> 本日のスケジュール
        </h3>
        <a href="appointment_manager.php" class="dashboard-section-link">
            すべての予約を表示 <i class="bi bi-arrow-right"></i>
        </a>
    </div>
    <div class="dashboard-section-body p-0">
        <?php if (isset($todays_appointments) && !empty($todays_appointments)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>時間</th>
                            <th>顧客名</th>
                            <th>サービス</th>
                            <th>担当者</th>
                            <th>状態</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todays_appointments as $appointment): ?>
                            <tr class="appointment-row <?php echo $appointment['status'] === 'cancelled' ? 'table-danger' : ''; ?>">
                                <td><?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></td>
                                <td>
                                    <a href="customer_details.php?id=<?php echo $appointment['customer_id']; ?>" class="fw-medium text-decoration-none">
                                        <?php echo htmlspecialchars($appointment['customer_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['staff_name']); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $appointment['status'] === 'completed' ? 'success' : 
                                            ($appointment['status'] === 'cancelled' ? 'danger' : 
                                                ($appointment['status'] === 'confirmed' ? 'primary' : 'warning')); 
                                    ?>">
                                        <?php echo formatStatus($appointment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            操作
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="appointment_manager.php?action=view&id=<?php echo $appointment['appointment_id']; ?>">
                                                    <i class="bi bi-eye me-2"></i>詳細
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="appointment_manager.php?action=edit&id=<?php echo $appointment['appointment_id']; ?>">
                                                    <i class="bi bi-pencil me-2"></i>編集
                                                </a>
                                            </li>
                                            <?php if ($appointment['status'] !== 'completed' && $appointment['status'] !== 'cancelled'): ?>
                                                <li>
                                                    <a class="dropdown-item" href="dashboard.php?action=update_status&id=<?php echo $appointment['appointment_id']; ?>&status=completed">
                                                        <i class="bi bi-check-circle me-2"></i>完了にする
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="dashboard.php?action=update_status&id=<?php echo $appointment['appointment_id']; ?>&status=cancelled">
                                                        <i class="bi bi-x-circle me-2"></i>キャンセル
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info m-3">
                <i class="bi bi-info-circle me-2"></i>
                本日の予約はありません。
            </div>
        <?php endif; ?>
    </div>
</div> 