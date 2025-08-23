<?php
/**
 * ダッシュボードカード - 管理者向け
 * 
 * 管理者またはテナント管理者向けのダッシュボードカードコンポーネント
 */

// 統計データの確認（エラー回避）
$admin_stats = $admin_stats ?? [];
$monthly_revenue_data = $monthly_revenue_data ?? null;
$salon_revenue_data = $salon_revenue_data ?? null;

/**
 * チャートの色を取得する関数
 * 
 * @param int $index 色のインデックス
 * @return string カラーコード
 */
if (!function_exists('getChartColor')) {
    function getChartColor($index) {
        $colors = [
            '#4e73df', // 青
            '#1cc88a', // 緑
            '#36b9cc', // 水色
            '#f6c23e', // 黄色
            '#e74a3b', // 赤
            '#5a5c69', // グレー
            '#6f42c1', // 紫
            '#fd7e14', // オレンジ
            '#20c997', // ターコイズ
            '#e83e8c'  // ピンク
        ];
        
        // インデックスが範囲外の場合は循環させる
        return $colors[$index % count($colors)];
    }
}

// テナントIDの取得
$tenant_id = $_SESSION['tenant_id'] ?? null;

// 管理者タイプによって表示を切り替え
$is_global_admin = ($role_name === 'admin');
$is_tenant_admin = ($role_name === 'tenant_admin');

// global_adminの場合はシステム全体の統計
// tenant_adminの場合は自社のテナント配下のサロン全体の統計
?>

<div class="row mb-4">
    <!-- 予約数カード -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            <?php echo $is_global_admin ? '全体の予約数' : '自社サロンの予約数'; ?></div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo isset($admin_stats['total_appointments']) ? number_format($admin_stats['total_appointments']) : '<i class="bi bi-dash"></i>'; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-calendar-check fs-2 text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer p-2">
                <a href="appointment_manager.php" class="small text-primary stretched-link">詳細を見る</a>
            </div>
        </div>
    </div>

    <!-- 総売上カード -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            <?php echo $is_global_admin ? '全体の売上（今月）' : '自社の売上（今月）'; ?></div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo isset($admin_stats['total_revenue']) ? '¥' . number_format($admin_stats['total_revenue']) : '<i class="bi bi-dash"></i>'; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-currency-yen fs-2 text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer p-2">
                <a href="sales_report.php" class="small text-success stretched-link">詳細を見る</a>
            </div>
        </div>
    </div>

    <!-- サロン数カード（管理者専用） -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            <?php echo $is_global_admin ? '登録サロン数' : '自社のサロン数'; ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo isset($admin_stats['salon_count']) ? number_format($admin_stats['salon_count']) : '<i class="bi bi-dash"></i>'; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-shop fs-2 text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer p-2">
                <a href="salon_management.php" class="small text-info stretched-link">詳細を見る</a>
            </div>
        </div>
    </div>

    <!-- ユーザー数カード -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            <?php echo $is_global_admin ? '登録ユーザー数' : '自社のスタッフ数'; ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo isset($admin_stats['user_count']) ? number_format($admin_stats['user_count']) : '<i class="bi bi-dash"></i>'; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-people fs-2 text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer p-2">
                <a href="user_management.php" class="small text-warning stretched-link">詳細を見る</a>
            </div>
        </div>
    </div>
</div>

<!-- チャート表示（管理者向け） -->
<?php if ($is_global_admin || $is_tenant_admin): ?>
<div class="row mb-4">
    <!-- 月間売上推移 -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">月間売上推移</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bi bi-three-dots-vertical text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end shadow animated--fade-in">
                        <a class="dropdown-item" href="sales_report.php">詳細レポートを表示</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="sales_report.php?period=year">年間レポートを表示</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="monthlyRevenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- サロン別売上分布 -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">サロン別売上分布</h6>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="salonRevenuePieChart"></canvas>
                </div>
                <div class="mt-4 text-center small">
                    <?php if (isset($salon_revenue_summary) && !empty($salon_revenue_summary)): ?>
                        <?php foreach ($salon_revenue_summary as $index => $salon): ?>
                            <span class="me-2">
                                <i class="fas fa-circle" style="color: <?php echo getChartColor($index); ?>"></i> <?php echo htmlspecialchars($salon['name']); ?>
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted">データがありません</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- サロン一覧テーブル（テナント管理者向け） -->
<?php if ($is_tenant_admin): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">サロン一覧</h6>
            </div>
            <div class="card-body">
                <?php if (isset($tenant_salons) && !empty($tenant_salons)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>サロン名</th>
                                    <th>住所</th>
                                    <th>連絡先</th>
                                    <th>今月の売上</th>
                                    <th>予約数</th>
                                    <th>ステータス</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tenant_salons as $salon): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($salon['name']); ?></td>
                                        <td><?php echo htmlspecialchars($salon['address']); ?></td>
                                        <td><?php echo htmlspecialchars($salon['phone']); ?></td>
                                        <td>¥<?php echo number_format($salon['monthly_revenue'] ?? 0); ?></td>
                                        <td><?php echo number_format($salon['appointments_count'] ?? 0); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $salon['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo $salon['status'] === 'active' ? '営業中' : '休業中'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="salon_details.php?id=<?php echo $salon['salon_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="salon_edit.php?id=<?php echo $salon['salon_id']; ?>" class="btn btn-sm btn-warning">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        サロンデータがありません。
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; // if ($is_global_admin || $is_tenant_admin) ?>

<script>
// サイドバーのダッシュボードメニューをアクティブにする
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.nav-item').forEach(function(navItem) {
        if (navItem.querySelector('a').getAttribute('href').includes('dashboard')) {
            navItem.classList.add('active');
        }
    });

    // チャートのデータと描画（管理者向け）
    <?php if (($is_global_admin || $is_tenant_admin) && isset($monthly_revenue_data)): ?>
    // 月間売上推移チャート
    const revenueCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthly_revenue_data['labels'] ?? []); ?>,
            datasets: [{
                label: '売上',
                backgroundColor: 'rgba(212, 175, 55, 0.1)',
                borderColor: 'rgba(212, 175, 55, 1)',
                borderWidth: 2,
                pointBackgroundColor: '#fff',
                pointBorderColor: 'rgba(212, 175, 55, 1)',
                pointRadius: 4,
                data: <?php echo json_encode($monthly_revenue_data['data'] ?? []); ?>
            }]
        },
        options: {
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '¥' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '売上: ¥' + context.raw.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>

    <?php if (($is_global_admin || $is_tenant_admin) && isset($salon_revenue_data)): ?>
    // サロン別売上分布チャート
    const pieCtx = document.getElementById('salonRevenuePieChart').getContext('2d');
    new Chart(pieCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($salon_revenue_data['labels'] ?? []); ?>,
            datasets: [{
                data: <?php echo json_encode($salon_revenue_data['data'] ?? []); ?>,
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                    '#5a5c69', '#6f42c1', '#fd7e14', '#20c997', '#17a2b8'
                ],
                hoverBackgroundColor: [
                    '#2e59d9', '#17a673', '#2c9faf', '#f4b619', '#e02d1b',
                    '#4e4f52', '#59339e', '#e96b00', '#16a085', '#138496'
                ],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ¥' + context.raw.toLocaleString() + 
                                ' (' + Math.round(context.parsed) + '%)';
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
});

// チャートの色を取得する関数
function getChartColor(index) {
    const colors = [
        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
        '#5a5c69', '#6f42c1', '#fd7e14', '#20c997', '#17a2b8'
    ];
    return colors[index % colors.length];
}
</script> 