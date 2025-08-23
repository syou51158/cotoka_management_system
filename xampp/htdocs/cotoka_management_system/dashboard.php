<?php
// 出力バッファリングを開始
ob_start();

// キャッシュ制御
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 初期出力確認
echo "<!-- デバッグ: PHPの実行が開始されました -->";

// 致命的エラーハンドラーを登録
function fatal_error_handler() {
    $error = error_get_last();
    if ($error !== null && $error['type'] === E_ERROR) {
        echo "致命的なエラーが発生しました: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'];
    }
}
register_shutdown_function('fatal_error_handler');

/**
 * 改良版ダッシュボード
 * 
 * ユーザーの権限に基づいて表示内容を最適化した新しいダッシュボード
 */

// エラー表示を有効にする
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// PHPの実行時間制限を増やす（最大120秒）
set_time_limit(120);

// デバッグフラグ - 必要に応じて有効/無効
$debug = false;

// 必要なファイルを読み込み
require_once 'config/config.php';
if ($debug) echo "<!-- デバッグ: config.php を読み込みました -->";

require_once 'classes/Database.php';
if ($debug) echo "<!-- デバッグ: Database.php を読み込みました -->";

require_once 'includes/functions.php';
if ($debug) echo "<!-- デバッグ: functions.php を読み込みました -->";

require_once 'classes/User.php';
if ($debug) echo "<!-- デバッグ: User.php を読み込みました -->";

require_once 'classes/Salon.php';
if ($debug) echo "<!-- デバッグ: Salon.php を読み込みました -->";

require_once 'includes/auth_middleware.php';
if ($debug) echo "<!-- デバッグ: auth_middleware.php を読み込みました -->";


// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');
if ($debug) echo "<!-- デバッグ: タイムゾーンを設定しました -->";

// ログインチェック
if ($debug) echo "<!-- デバッグ: ログインチェック開始 -->";
requireLogin();
if ($debug) echo "<!-- デバッグ: ログインチェック完了 -->";

// ユーザーの権限と情報を取得
$role_name = $_SESSION['role_name'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if ($debug) echo "<!-- デバッグ: ユーザー情報取得完了 (role: $role_name, user: $user_id, tenant: $tenant_id) -->";

// データベース接続
try {
    if ($debug) echo "<!-- デバッグ: DB接続開始 -->";
    $db = new Database();
    $conn = $db->getConnection();
    $user = new User($db);
    if ($debug) echo "<!-- デバッグ: DB接続完了 -->";
    
    // サロンID取得
    if ($debug) echo "<!-- デバッグ: サロンID取得開始 -->";
    $salon_id = getCurrentSalonId();
    
    // サロンIDが取得できなかった場合にもう一度試行
    if (!$salon_id && isset($_SESSION['user_id'])) {
        error_log("ダッシュボード: サロンIDが取得できなかったため再取得を試みます");
        
        // ユーザーのアクセス可能なサロンを再取得
        $accessibleSalons = $user->getAccessibleSalons($_SESSION['user_id']);
        
        if (!empty($accessibleSalons)) {
            $salon_id = $accessibleSalons[0]['salon_id'];
            setCurrentSalon($salon_id);
            error_log("ダッシュボード: サロンIDを再設定しました: " . $salon_id);
        }
    }
    
    if ($debug) echo "<!-- デバッグ: サロンID取得完了: " . ($salon_id ?? 'null') . " -->";
    
    // 統計情報（空の値を設定）
    $statistics = [
        'today_appointments' => 0,
        'monthly_total' => 0,
        'total_customers' => 0,
        'pending_appointments' => 0
    ];
    
    // 最近の顧客一覧のための変数
    $recent_customers = [];
    $top_customers = [];
    $upcoming_appointments = [];
    
    // 管理者向け統計情報
    $admin_stats = [
        'total_appointments' => 0,
        'total_revenue' => 0,
        'salon_count' => 0,
        'user_count' => 0
    ];
    
    // 部分的に統計情報を取得
    if ($salon_id) {
        try {
            error_log("ダッシュボード: サロンID " . $salon_id . " の統計情報取得を開始します");
            
            // RPC: 予約統計
            $today = date('Y-m-d');
            error_log("ダッシュボード - 使用している日付変数: today=" . $today);
            error_log("ダッシュボード - サロンID: " . $salon_id);

            $statsRes = supabaseRpcCall('appointments_stats', ['p_salon_id' => (int)$salon_id]);
            if ($statsRes['success']) {
                $row = is_array($statsRes['data']) ? ($statsRes['data'][0] ?? null) : null;
                if ($row) {
                    $statistics['today_appointments'] = (int)($row['today'] ?? 0);
                    $statistics['pending_appointments'] = (int)($row['pending'] ?? 0);
                }
            }
            if ($debug) echo "<!-- デバッグ: 予約統計取得完了: today=" . $statistics['today_appointments'] . ", pending=" . $statistics['pending_appointments'] . " -->";

            // RPC: 顧客数
            $custCntRes = supabaseRpcCall('customers_count_by_salon', ['p_salon_id' => (int)$salon_id]);
            if ($custCntRes['success']) {
                $row = is_array($custCntRes['data']) ? ($custCntRes['data'][0] ?? null) : null;
                if ($row && isset($row['count'])) {
                    $statistics['total_customers'] = (int)$row['count'];
                }
            }
            if ($debug) echo "<!-- デバッグ: 顧客数取得完了: " . $statistics['total_customers'] . " -->";

            // RPC: 今月の売上（paymentsベース）
            $month_start = date('Y-m-01');
            $month_end = date('Y-m-t');
            $salesRes = supabaseRpcCall('sales_month_total_by_payments', [
                'p_salon_id' => (int)$salon_id,
                'p_start' => $month_start,
                'p_end' => $month_end
            ]);
            if ($salesRes['success']) {
                $row = is_array($salesRes['data']) ? ($salesRes['data'][0] ?? null) : null;
                if ($row && isset($row['total'])) {
                    $statistics['monthly_total'] = (float)$row['total'];
                }
            }
            if ($debug) echo "<!-- デバッグ: 今月の売上取得完了: " . $statistics['monthly_total'] . " -->";

            // RPC: 最近の顧客
            $recentRes = supabaseRpcCall('customers_recent_by_salon', ['p_salon_id' => (int)$salon_id, 'p_limit' => 5]);
            if ($recentRes['success']) {
                $recent_customers = is_array($recentRes['data']) ? $recentRes['data'] : [];
            }
            if ($debug) echo "<!-- デバッグ: 最近の顧客取得完了: " . count($recent_customers) . "件 -->";

            // RPC: 売上上位顧客
            $topRes = supabaseRpcCall('customers_top_by_spent_by_salon', ['p_salon_id' => (int)$salon_id, 'p_limit' => 5]);
            if ($topRes['success']) {
                $top_customers = is_array($topRes['data']) ? $topRes['data'] : [];
            }
            if ($debug) echo "<!-- デバッグ: 売上上位顧客取得完了: " . count($top_customers) . "件 -->";

            // RPC: 今後の予約（7日間）
            $today = date('Y-m-d');
            $next_week = date('Y-m-d', strtotime('+7 days'));
            $upcomingRes = supabaseRpcCall('appointments_upcoming', [
                'p_salon_id' => (int)$salon_id,
                'p_start' => $today,
                'p_end' => $next_week,
                'p_limit' => 10
            ]);
            if ($upcomingRes['success']) {
                $upcoming_appointments = is_array($upcomingRes['data']) ? $upcomingRes['data'] : [];
            }
            if ($debug) echo "<!-- デバッグ: 今後の予約取得完了: " . count($upcoming_appointments) . "件 -->";
            
            // すべての統計情報が正しく取得できたかログに記録
            error_log("ダッシュボード統計: サロンID=" . $salon_id 
                . ", 顧客数=" . $statistics['total_customers']
                . ", 今月の売上=" . $statistics['monthly_total']
                . ", 本日の予約=" . $statistics['today_appointments']
                . ", 未確認予約=" . $statistics['pending_appointments']
                . ", 最近の顧客=" . count($recent_customers)
                . ", 上位顧客=" . count($top_customers)
                . ", 予定予約=" . count($upcoming_appointments));
            
        } catch (PDOException $e) {
            // エラーが発生しても処理を続行
            error_log("統計データ取得エラー: " . $e->getMessage());
            if ($debug) echo "<!-- デバッグ: 統計データ取得エラー: " . $e->getMessage() . " -->";
        }
    }
} catch (Exception $e) {
    // 致命的なエラー（接続できない場合）
    $error_message = "データベース接続エラー：" . $e->getMessage();
    echo "<div style='color:red; font-weight:bold;'>エラー: " . $error_message . "</div>";
    if ($debug) echo "<!-- デバッグ: 例外が発生しました: " . $e->getMessage() . " -->";
    exit;
}

// サロンIDが設定されていない場合の警告
if (!$salon_id) {
    $warning_message = "サロンが選択されていないか、アクセス可能なサロンがありません。";
    error_log("ダッシュボードエラー: " . $warning_message);
}

// ページタイトル
$page_title = "ダッシュボード";

// ヘッダーの読み込み（元のレイアウトに戻す）
require_once 'includes/header.php';
?>

<div class="container-fluid px-4 py-3">
    <h1 class="h3 mb-3 text-gray-800">ダッシュボード</h1>
    
    <?php if (!$salon_id): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle mr-2"></i> サロンが選択されていないか、アクセス可能なサロンがありません。サロンセレクターから選択してください。
    </div>
    <?php endif; ?>
    
    <!-- 統計カードの代わりに簡易表示 -->
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">本日の予約</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($statistics['today_appointments']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">今月の売上</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">¥<?php echo number_format($statistics['monthly_total']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-yen-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">顧客数</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($statistics['total_customers']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">未確認予約</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($statistics['pending_appointments']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- 最近登録された顧客 -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">最近登録された顧客</h6>
                    <a href="customer_manager.php" class="btn btn-sm btn-primary">すべて表示</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_customers)): ?>
                        <p class="text-center my-3">顧客データがありません</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>氏名</th>
                                        <th>連絡先</th>
                                        <th>登録日</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recent_customers as $customer): ?>
                                    <tr>
                                        <td>
                                            <a href="customer_manager.php?id=<?php echo $customer['customer_id']; ?>">
                                                <?php echo htmlspecialchars($customer['last_name'] . ' ' . $customer['first_name']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php echo $customer['phone'] ? htmlspecialchars($customer['phone']) : 
                                                ($customer['email'] ? htmlspecialchars($customer['email']) : '未登録'); ?>
                                        </td>
                                        <td><?php echo date('Y/m/d', strtotime($customer['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- 売上上位の顧客 -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">売上上位の顧客</h6>
                    <a href="customer_manager.php?sort=total_spent" class="btn btn-sm btn-primary">すべて表示</a>
                </div>
                <div class="card-body">
                    <?php if (empty($top_customers)): ?>
                        <p class="text-center my-3">顧客データがありません</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>氏名</th>
                                        <th>来店回数</th>
                                        <th>売上合計</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($top_customers as $customer): ?>
                                    <tr>
                                        <td>
                                            <a href="customer_manager.php?id=<?php echo $customer['customer_id']; ?>">
                                                <?php echo htmlspecialchars($customer['last_name'] . ' ' . $customer['first_name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo number_format($customer['visit_count']); ?>回</td>
                                        <td>¥<?php echo number_format($customer['total_spent']); ?></td>
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
    
    <!-- 今後の予約一覧 -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">今後の予約（7日間）</h6>
                    <a href="appointment_ledger.php" class="btn btn-sm btn-primary">すべての予約を表示</a>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_appointments)): ?>
                        <p class="text-center my-3">次の7日間の予約はありません</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>日付</th>
                                        <th>時間</th>
                                        <th>顧客</th>
                                        <th>スタッフ</th>
                                        <th>サービス</th>
                                        <th>状態</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($upcoming_appointments as $appt): ?>
                                    <tr class="<?php echo $appt['is_confirmed'] ? '' : 'table-warning'; ?>">
                                        <td><?php echo date('Y/m/d', strtotime($appt['appointment_date'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($appt['start_time'])) . '〜' . date('H:i', strtotime($appt['end_time'])); ?></td>
                                        <td>
                                            <a href="customer_manager.php?id=<?php echo $appt['customer_id']; ?>">
                                                <?php echo htmlspecialchars($appt['customer_last_name'] . ' ' . $appt['customer_first_name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($appt['staff_last_name'] . ' ' . $appt['staff_first_name']); ?></td>
                                        <td><?php echo htmlspecialchars($appt['service_name']); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo ($appt['status'] == 'scheduled' && !$appt['is_confirmed']) ? 'bg-warning text-dark' : 
                                                    (($appt['status'] == 'scheduled' && $appt['is_confirmed']) ? 'bg-success' : 
                                                    ($appt['status'] == 'cancelled' ? 'bg-danger' : 'bg-info')); 
                                            ?>">
                                                <?php 
                                                    echo formatStatus($appt['status']) . 
                                                        (($appt['status'] == 'scheduled' && !$appt['is_confirmed']) ? '（未確認）' : ''); 
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="appointment_edit.php?id=<?php echo $appt['appointment_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($appt['status'] == 'scheduled' && !$appt['is_confirmed']): ?>
                                            <a href="javascript:void(0);" onclick="confirmAppointment(<?php echo $appt['appointment_id']; ?>)" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <?php endif; ?>
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
    
    <div class="alert alert-success mt-4">
        <p><strong>情報:</strong> ダッシュボードの基本機能が復元されました。統計情報が正常に表示されています。</p>
    </div>
</div>

<?php
// フッターの読み込み
require_once 'includes/footer.php';

/**
 * 予約状態を日本語表示に変換する
 */
function formatStatus($status) {
    $status_labels = [
        'scheduled' => '予約済',
        'confirmed' => '確認済',
        'completed' => '完了',
        'cancelled' => 'キャンセル',
        'no_show' => '無断キャンセル'
    ];
    
    return $status_labels[$status] ?? $status;
}

// 出力バッファをフラッシュして送信
ob_end_flush();
?> 

<!-- 予約確認のためのモーダル -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmModalLabel">予約確認</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        この予約を確認済みとしてマークしますか？
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
        <button type="button" class="btn btn-primary" id="confirmAppointmentBtn">確認する</button>
      </div>
    </div>
  </div>
</div>

<script>
// 予約確認関数
let currentAppointmentId = null;

function confirmAppointment(appointmentId) {
    currentAppointmentId = appointmentId;
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();
}

// モーダルの確認ボタンにイベントリスナーを追加
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('confirmAppointmentBtn').addEventListener('click', function() {
        if (currentAppointmentId) {
            // 予約確認API呼び出し
            fetch('api/appointments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'confirm',
                    appointment_id: currentAppointmentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 成功時の処理
                    const modal = bootstrap.Modal.getInstance(document.getElementById('confirmModal'));
                    modal.hide();
                    
                    // ページをリロード
                    window.location.reload();
                } else {
                    // エラー処理
                    alert('予約の確認に失敗しました: ' + data.message);
                }
            })
            .catch(error => {
                console.error('エラー:', error);
                alert('予約の確認中にエラーが発生しました。');
            });
        }
    });
});
</script> 