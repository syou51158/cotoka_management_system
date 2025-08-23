<?php
// 共通の設定ファイルを読み込む
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/role_permissions.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';

// データベース接続
$db = new Database();
$conn = $db->getConnection();

// ログインチェック
if (!isset($_SESSION['user_id']) && !isLoginPage()) {
    header('Location: login.php');
    exit;
}

// CSRFトークンを生成
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// マルチサロン対応
$current_salon_id = isset($_SESSION['current_salon_id']) ? $_SESSION['current_salon_id'] : (isset($_SESSION['salon_id']) ? $_SESSION['salon_id'] : null);
$salon_name = '';

if (isset($_SESSION['user_id'])) {
    // サロンIDが設定されていない場合、ユーザーがアクセス可能なサロンから選択
    if ($current_salon_id === null) {
        $user_id = $_SESSION['user_id'];
        $user_uid = $_SESSION['user_unique_id'] ?? null;
        $rpcSalons = $user_uid
            ? supabaseRpcCall('user_accessible_salons_by_uid', ['p_user_uid' => (string)$user_uid])
            : supabaseRpcCall('user_accessible_salons', ['p_user_id' => (int)$user_id]);
        $accessibleSalons = $rpcSalons['success'] ? ($rpcSalons['data'] ?? []) : [];
        if (!empty($accessibleSalons)) {
            $current_salon_id = $accessibleSalons[0]['salon_id'];
            $_SESSION['current_salon_id'] = $current_salon_id;
            $_SESSION['salon_id'] = $current_salon_id;
        }
    } else {
        // 現在選択されているサロンにアクセス権があるか確認
        $user_id = $_SESSION['user_id'];
        $user_uid = $_SESSION['user_unique_id'] ?? null;
        $rpcSalons = $user_uid
            ? supabaseRpcCall('user_accessible_salons_by_uid', ['p_user_uid' => (string)$user_uid])
            : supabaseRpcCall('user_accessible_salons', ['p_user_id' => (int)$user_id]);
        $accessibleSalons = $rpcSalons['success'] ? ($rpcSalons['data'] ?? []) : [];
        $accessibleSalonIds = array_column($accessibleSalons, 'salon_id');
        // アクセス権がない場合は、アクセス可能なサロンの中から最初のものを選択
        if (!in_array($current_salon_id, $accessibleSalonIds) && !empty($accessibleSalonIds)) {
            $current_salon_id = $accessibleSalons[0]['salon_id'];
            $_SESSION['current_salon_id'] = $current_salon_id;
            $_SESSION['salon_id'] = $current_salon_id;
        }
    }
    
    // 現在のサロン名を取得（RPC）
    if ($current_salon_id) {
        $rpcSalon = supabaseRpcCall('salon_get', ['p_salon_id' => (int)$current_salon_id]);
        if ($rpcSalon['success'] && !empty($rpcSalon['data'])) {
            $row = is_array($rpcSalon['data']) ? $rpcSalon['data'][0] : null;
            if ($row) {
                if (isset($row['name']) && $row['name'] !== '') {
                    $salon_name = $row['name'];
                } elseif (isset($row['salon_name']) && $row['salon_name'] !== '') {
                    $salon_name = $row['salon_name'];
                }
            }
        }
    }
    
    // セッション変数を統一（salon_idとcurrent_salon_idの両方を同じ値に設定）
    $_SESSION['salon_id'] = $current_salon_id;
    $_SESSION['current_salon_id'] = $current_salon_id;
}

// 現在の日付
$today = date('Y年m月d日');

// フラッシュメッセージ
$flash_message = getFlashMessage();

// CSRFトークン生成
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>Cotoka管理システム</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/common/variables.css">
    <link rel="stylesheet" href="assets/css/common/global.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <!-- フッター専用CSS -->
    <link rel="stylesheet" href="assets/css/footer.css">
    <?php if (basename($_SERVER['PHP_SELF']) === 'dashboard.php'): ?>
    <!-- ダッシュボード専用CSS -->
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <?php else: ?>
    <!-- 他のページ用CSS -->
    <!-- 存在しないCSSファイルの参照を削除 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <?php endif; ?>
    
    <!-- ページ固有のCSSがあれば読み込む -->
    <?php if (isset($page_css)) echo $page_css; ?>

    <!-- JS ライブラリ -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- 共通スクリプト -->
    <script src="assets/js/common/utils.js"></script>
    <script src="assets/js/header.js"></script>
    <?php if (basename($_SERVER['PHP_SELF']) === 'dashboard.php'): ?>
    <!-- ダッシュボード専用JS -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/dashboard.js" defer></script>
    <?php endif; ?>
    <style>
        /* 基本スタイル - インライン最小限に保持 */
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        /* モバイル対応の基本サイドバー設定のみインラインに残す */
        @media (max-width: 992px) {
            .sidebar {
                margin-left: -260px;
                z-index: 1000 !important;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-collapsed .sidebar {
                margin-left: 0;
            }
        }

        /* ヘッダーサロンセレクター用スタイル */
        .header-salon-selector {
            display: flex;
            align-items: center;
            margin-right: 15px;
        }
        .header-salon-selector select {
            min-width: 160px;
            max-width: 240px;
            width: auto;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            text-overflow: ellipsis;
            overflow: hidden;
            white-space: nowrap;
        }
        .header-salon-selector label {
            color: #fff;
            margin-right: 8px;
            font-size: 0.85rem;
            white-space: nowrap;
        }

        /* セレクトボックスのオプションスタイル改善 */
        #header-salon-switcher option {
            white-space: normal;
            padding: 5px;
            width: 100%;
            overflow: visible;
            text-overflow: initial;
            color: #333;
        }
        
        /* ドロップダウンメニューのスタイル改善 */
        .dropdown-menu {
            min-width: 200px;
            width: auto;
            max-width: 300px;
        }
        
        .dropdown-menu .dropdown-item {
            white-space: normal;
            word-break: break-word;
            padding: 8px 16px;
        }
    </style>
</head>
<body>

<?php if (isset($_SESSION['user_id'])): ?>
<div class="sidebar-layout">
    <!-- サイドバー -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="brand">
                <i class="fas fa-spa"></i>
                <span>Beauty Management</span>
            </div>
            <button class="sidebar-toggle-btn d-lg-none" type="button" aria-label="閉じる">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="sidebar-menu">
            <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
            <!-- デバッグ情報表示（?debug=1のクエリパラメータがある場合のみ表示） -->
            <div style="background: rgba(255, 255, 255, 0.2); padding: 10px; margin: 10px; border-radius: 5px; font-size: 12px;">
                <p style="margin: 0;">ユーザーID: <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '未設定'; ?></p>
                <p style="margin: 0;">ユーザー名: <?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '未設定'; ?></p>
                <p style="margin: 0;">権限: <?php echo isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '未設定'; ?></p>
                <p style="margin: 0;">管理者権限: <?php echo isAdmin() ? 'はい' : 'いいえ'; ?></p>
                <p style="margin: 0;">getUserRole()結果: '<?php echo getUserRole(); ?>'</p>
                <p style="margin: 0;">定数比較ROLE_TENANT_ADMIN: '<?php echo ROLE_TENANT_ADMIN; ?>' - 一致: <?php echo (getUserRole() === ROLE_TENANT_ADMIN) ? 'はい' : 'いいえ'; ?></p>
                <p style="margin: 0;">定数比較ROLE_MANAGER: '<?php echo ROLE_MANAGER; ?>' - 一致: <?php echo (getUserRole() === ROLE_MANAGER) ? 'はい' : 'いいえ'; ?></p>
                <p style="margin: 0;">定数比較admin: '<?php echo 'admin'; ?>' - 一致: <?php echo (getUserRole() === 'admin') ? 'はい' : 'いいえ'; ?></p>
            </div>
            <?php endif; ?>
            
            <ul>
                <?php 
                // shouldShowMenuItemの結果をデバッグ表示する
                $menuItems = [
                    'appointments' => 'appointments',
                    'customers' => 'customers',
                    'services' => 'services',
                    'staff' => 'staff',
                    'sales' => 'sales',
                    'reports' => 'reports',
                    'settings' => 'settings',
                    'system_settings' => 'system_settings',
                    'tenant_management' => 'tenant_management',
                    'file_management' => 'file_management',
                    'user_management' => 'user_management'
                ];
                
                // 管理者ロールの場合、強制的にすべて表示するフラグを設定
                $forceShowAllMenus = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
                ?>
                
                <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
                <div style="background: rgba(255, 255, 255, 0.2); padding: 10px; margin: 10px; border-radius: 5px; font-size: 12px;">
                    <p style="margin: 0;">強制表示フラグ: <?php echo $forceShowAllMenus ? 'はい' : 'いいえ'; ?></p>
                    <?php foreach ($menuItems as $id => $name): ?>
                    <p style="margin: 0;"><?php echo $name; ?>: <?php echo (shouldShowMenuItem($id) || $forceShowAllMenus) ? '表示' : '非表示'; ?></p>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- グローバル管理者専用メニュー -->
                <?php if (isGlobalAdmin()): ?>
                <li class="<?php echo isActive('global-admin-dashboard.php'); ?>">
                    <a href="global-admin-dashboard.php">
                        <i class="fas fa-globe"></i>
                        <span>グローバル管理ダッシュボード</span>
                    </a>
                </li>
                <li class="<?php echo isActive('tenant-management.php'); ?>">
                    <a href="tenant-management.php">
                        <i class="fas fa-building"></i>
                        <span>テナント管理</span>
                    </a>
                </li>
                <li class="<?php echo isActive('user-management.php'); ?>">
                    <a href="user-management.php">
                        <i class="fas fa-users-cog"></i>
                        <span>グローバルユーザー管理</span>
                    </a>
                </li>
                <li class="<?php echo isActive('system-settings.php'); ?>">
                    <a href="system-settings.php">
                        <i class="fas fa-sliders-h"></i>
                        <span>システム設定</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- テナント管理者/マネージャー/スタッフ共通メニュー（adminも表示） -->
                <?php if (true): ?>
                <li class="<?php echo isActive('dashboard.php'); ?>">
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>ダッシュボード</span>
                    </a>
                </li>
                
                <?php if (shouldShowMenuItem('appointments')): ?>
                <li class="<?php echo isActive('appointment_manager.php'); ?>">
                    <a href="appointment_manager.php">
                        <i class="fas fa-calendar-alt"></i>
                        <span>予約管理</span>
                    </a>
                </li>
                <li class="<?php echo isActive('appointment_ledger.php'); ?>">
                    <a href="appointment_ledger.php">
                        <i class="fas fa-calendar-check"></i>
                        <span>予約台帳</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (shouldShowMenuItem('customers')): ?>
                <li class="<?php echo isActive('customer_manager.php'); ?>">
                    <a href="customer_manager.php">
                        <i class="fas fa-users"></i>
                        <span>顧客管理</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (shouldShowMenuItem('services')): ?>
                <li class="<?php echo isActive('services.php'); ?>">
                    <a href="services.php">
                        <i class="fas fa-concierge-bell"></i>
                        <span>サービス管理</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (shouldShowMenuItem('staff')): ?>
                <li class="<?php echo isActive('staff_management.php'); ?>">
                    <a href="staff_management.php">
                        <i class="fas fa-user-tie"></i>
                        <span>スタッフ管理</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (shouldShowMenuItem('sales')): ?>
                <li class="<?php echo isActive('sales.php'); ?>">
                    <a href="sales.php">
                        <i class="fas fa-yen-sign"></i>
                        <span>売上管理</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (shouldShowMenuItem('reports')): ?>
                <li class="<?php echo isActive('reports.php'); ?>">
                    <a href="reports.php">
                        <i class="fas fa-chart-line"></i>
                        <span>レポート</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (shouldShowMenuItem('settings')): ?>
                <li class="<?php echo isActive('settings.php'); ?>">
                    <a href="settings.php">
                        <i class="fas fa-cog"></i>
                        <span>設定</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (shouldShowMenuItem('settings') && (getUserRole() === ROLE_MANAGER || getUserRole() === ROLE_TENANT_ADMIN || getUserRole() === 'admin')): ?>
                <li class="<?php echo isActive('salon_management.php'); ?>">
                    <a href="salon_management.php">
                        <i class="fas fa-store"></i>
                        <span>店舗管理</span>
                    </a>
                </li>
                
                <li class="<?php echo isActive('booking_url_management.php'); ?>">
                    <a href="booking_url_management.php">
                        <i class="fas fa-link"></i>
                        <span>予約URL管理</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (shouldShowMenuItem('system_settings')): ?>
                <li class="<?php echo isActive('system-settings.php'); ?>">
                    <a href="system-settings.php">
                        <i class="fas fa-sliders-h"></i>
                        <span>システム設定</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (shouldShowMenuItem('file_management')): ?>
                <li class="<?php echo isActive('file-management.php'); ?>">
                    <a href="file-management.php">
                        <i class="fas fa-file-alt"></i>
                        <span>ファイル管理</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (shouldShowMenuItem('user_management')): ?>
                <li class="<?php echo isActive('user-management.php'); ?>">
                    <a href="user-management.php">
                        <i class="fas fa-users-cog"></i>
                        <span>ユーザー管理</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>
                
                <!-- サロン切り替え（adminも表示） -->
                <?php if (true): ?>
                <li>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#salonSwitchModal">
                        <i class="fas fa-exchange-alt"></i>
                        <span>サロン切り替え</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- ログアウトリンク -->
                <li class="<?php echo isActive('logout.php'); ?>">
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>ログアウト</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- メインコンテンツ -->
    <div class="main-content">
        <!-- ヘッダー -->
        <div class="main-header">
            <div class="toggle-sidebar">
                <button class="sidebar-toggle-btn" type="button" aria-label="メニュー">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="current-date">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo $today; ?></span>
                </div>
                <div class="salon-page-info">
                    <?php 
                    // 現在のページ名を取得
                    $current_page = basename($_SERVER['PHP_SELF']);
                    $page_name = '';
                    
                    // ページ名の日本語表記を設定
                    switch($current_page) {
                        case 'dashboard.php':
                            $page_name = 'ダッシュボード';
                            break;
                        case 'appointment_manager.php':
                            $page_name = '予約管理';
                            break;
                        case 'customers.php':
                            $page_name = '顧客管理';
                            break;
                        case 'staff.php':
                            $page_name = 'スタッフ管理';
                            break;
                        case 'sales.php':
                            $page_name = '売上管理';
                            break;
                        case 'settings.php':
                            $page_name = '設定';
                            break;
                        default:
                            $page_name = $current_page;
                    }
                    ?>
                    <span><?php echo htmlspecialchars($salon_name); ?> &gt; <?php echo $page_name; ?></span>
                </div>
            </div>
            
            <?php if (!isGlobalAdmin() && isset($_SESSION['user_id'])): ?>
            <!-- ヘッダーサロンセレクター -->
            <div class="header-salon-selector">
                <?php
                // ユーザーのアクセス可能なサロンを取得（RPC）
                $user_id = $_SESSION['user_id'];
                $user_uid = $_SESSION['user_unique_id'] ?? null;
                $rpcSalons2 = $user_uid
                    ? supabaseRpcCall('user_accessible_salons_by_uid', ['p_user_uid' => (string)$user_uid])
                    : supabaseRpcCall('user_accessible_salons', ['p_user_id' => (int)$user_id]);
                $accessibleSalons2 = $rpcSalons2['success'] ? ($rpcSalons2['data'] ?? []) : [];
                
                // 現在のサロンID
                $current_salon_id = getCurrentSalonId();
                
                // 複数のサロンにアクセス可能な場合のみセレクターを表示
                if (count($accessibleSalons2) > 1):
                ?>
                <label for="header-salon-switcher">サロン:</label>
                <select id="header-salon-switcher" class="form-select form-select-sm salon-dropdown" title="<?php echo htmlspecialchars($salon_name); ?>">
                    <?php foreach ($accessibleSalons2 as $salon): ?>
                        <option value="<?php echo $salon['salon_id']; ?>" 
                                <?php echo $salon['salon_id'] == $current_salon_id ? 'selected' : ''; ?>
                                title="<?php echo htmlspecialchars($salon['name']); ?>">
                            <?php echo htmlspecialchars($salon['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="user-menu dropdown">
                <button class="dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="ユーザーメニュー">
                    <div class="user-initial">
                        <?php echo isset($_SESSION['user_name']) ? substr($_SESSION['user_name'], 0, 1) : 'U'; ?>
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User'; ?></span>
                        <span class="user-role"><?php echo isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Role'; ?></span>
                    </div>
                </button>
                <ul class="dropdown-menu" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle"></i> プロフィール</a></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> ログアウト</a></li>
                </ul>
            </div>
        </div>
        
        <!-- メインコンテナ -->
        <div class="main-container">
            <?php if ($flash_message): ?>
            <div class="alert alert-<?php echo $flash_message['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $flash_message['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- サロン切り替えモーダル -->
            <div class="modal fade" id="salonSwitchModal" tabindex="-1" aria-labelledby="salonSwitchModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="salonSwitchModalLabel">サロン切り替え</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                        </div>
                        <div class="modal-body">
                            <?php
                            // ユーザーがアクセス可能なサロン一覧を取得（RPC）
                            $rpcSalons3 = isset($_SESSION['user_unique_id'])
                                ? supabaseRpcCall('user_accessible_salons_by_uid', ['p_user_uid' => (string)$_SESSION['user_unique_id']])
                                : supabaseRpcCall('user_accessible_salons', ['p_user_id' => (int)$_SESSION['user_id']]);
                            $accessibleSalons3 = $rpcSalons3['success'] ? ($rpcSalons3['data'] ?? []) : [];
                            
                            if (empty($accessibleSalons3)) {
                                echo '<div class="alert alert-warning">アクセス可能なサロンがありません</div>';
                            } else {
                            ?>
                            <div class="list-group">
                                <?php foreach ($accessibleSalons3 as $salon): ?>
                                <a href="javascript:void(0);" onclick="switchSalon(<?php echo $salon['salon_id']; ?>)" 
                                   class="list-group-item list-group-item-action <?php echo $salon['salon_id'] == $current_salon_id ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($salon['name']); ?>
                                    <?php if ($salon['salon_id'] == $current_salon_id): ?>
                                    <span class="badge bg-primary rounded-pill float-end">現在</span>
                                    <?php endif; ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ヘッダー用スクリプト -->
            <script>
            // サロン切り替え関数
            function switchSalon(salonId) {
                // ローディングインジケーターを表示
                document.body.classList.add('loading');
                
                // API呼び出し
                fetch('api/switch_salon.php?salon_id=' + salonId)
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
            }

            // ヘッダーのサロンセレクター処理
            document.addEventListener('DOMContentLoaded', function() {
                const headerSalonSwitcher = document.getElementById('header-salon-switcher');
                if (headerSalonSwitcher) {
                    // ツールチップ機能を追加 - テキストが切れる場合にホバーで全文表示
                    headerSalonSwitcher.addEventListener('mouseover', function(e) {
                        if (e.target.tagName === 'OPTION') {
                            e.target.title = e.target.text;
                        }
                    });
                    
                    headerSalonSwitcher.addEventListener('change', function() {
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

<?php else: ?>
<div class="auth-background">
    <div class="container">
<?php endif; ?>
