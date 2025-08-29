<?php
// 必要なファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'includes/functions.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// ログインしていない場合はログインページにリダイレクト
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    $error_message = "データベース接続エラー：" . $e->getMessage();
    require_once 'includes/header.php';
    echo '<div class="alert alert-danger m-3">' . $error_message . '</div>';
    require_once 'includes/footer.php';
    exit;
}

// 現在のサロンIDを取得
$salon_id = getCurrentSalonId();
if (!$salon_id) {
    // サロンIDがない場合は、利用可能なサロンを取得して自動的にセット
    $user_uid = $_SESSION['user_unique_id'] ?? null;
    $user_id = $_SESSION['user_id'];
    $rpcSalons = $user_uid
        ? supabaseRpcCall('user_accessible_salons_by_uid', ['p_user_uid' => (string)$user_uid])
        : supabaseRpcCall('user_accessible_salons', ['p_user_id' => (int)$user_id]);
    $available_salons = $rpcSalons['success'] ? ($rpcSalons['data'] ?? []) : [];
    
    if (!empty($available_salons)) {
        $salon_id = $available_salons[0]['salon_id'];
        setCurrentSalon($salon_id);
        $_SESSION['salon_id'] = $salon_id; // 互換性のため
        
        // リダイレクトして、セッションを更新
        header('Location: staff_management.php' . (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
        exit;
    } else {
        // アクセス可能なサロンがない場合
        $error_message = "アクセス可能なサロンがありません。管理者に連絡してください。";
        require_once 'includes/header.php';
        echo '<div class="alert alert-danger m-3">' . $error_message . '</div>';
        require_once 'includes/footer.php';
        exit;
    }
}

// 現在選択されているサロンへのアクセス権をチェック
$user_uid = $_SESSION['user_unique_id'] ?? null;
$user_id = $_SESSION['user_id'];
$rpcSalons2 = $user_uid
    ? supabaseRpcCall('user_accessible_salons_by_uid', ['p_user_uid' => (string)$user_uid])
    : supabaseRpcCall('user_accessible_salons', ['p_user_id' => (int)$user_id]);
$accessibleSalons = $rpcSalons2['success'] ? ($rpcSalons2['data'] ?? []) : [];
$accessibleSalonIds = array_column($accessibleSalons, 'salon_id');

if (!in_array($salon_id, $accessibleSalonIds)) {
    // アクセス可能なサロンがある場合は最初のサロンに切り替え
    if (!empty($accessibleSalonIds)) {
        $salon_id = $accessibleSalons[0]['salon_id'];
        setCurrentSalon($salon_id);
        $_SESSION['salon_id'] = $salon_id;
        
        // リダイレクトして、セッションを更新
        header('Location: staff_management.php' . (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
        exit;
    } else {
        // アクセス可能なサロンがない場合
        $error_message = "アクセス可能なサロンがありません。管理者に連絡してください。";
        require_once 'includes/header.php';
        echo '<div class="alert alert-danger m-3">' . $error_message . '</div>';
        require_once 'includes/footer.php';
        exit;
    }
}

// 現在のテナントIDを取得
$tenant_id = getCurrentTenantId();

// スタッフ追加処理
$add_success = false;
$add_error = '';

if (isset($_POST['add_staff'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $position = trim($_POST['position']);
    $hire_date = trim($_POST['hire_date']);
    
    if (empty($first_name) || empty($last_name)) {
        $add_error = '姓と名は必須項目です。';
    } else {
        $rpc = supabaseRpcCall('staff_add_admin', [
            'p_tenant_id' => (int)$tenant_id,
            'p_first_name' => $first_name,
            'p_last_name' => $last_name,
            'p_email' => $email,
            'p_phone' => $phone,
            'p_position' => $position,
            'p_hire_date' => $hire_date,
            'p_status' => 'active'
        ]);
        if ($rpc['success']) {
            $add_success = true;

            // 追加したスタッフを現在のサロンに関連付け（中間テーブル: cotoka.staff_salons）
            $new_staff_id = null;
            if (isset($rpc['data']) && is_array($rpc['data'])) {
                $firstRow = $rpc['data'][0] ?? null;
                if ($firstRow && isset($firstRow['staff_id'])) {
                    $new_staff_id = (int)$firstRow['staff_id'];
                }
            }
            if (!$new_staff_id && !empty($email)) {
                $email_esc = str_replace("'", "''", $email);
                $findSql = "SELECT staff_id FROM cotoka.staff WHERE tenant_id = $tenant_id AND email = '$email_esc' ORDER BY staff_id DESC LIMIT 1";
                $findRes = supabaseRpcCall('execute_sql', ['query' => $findSql]);
                if ($findRes['success'] && is_array($findRes['data']) && !empty($findRes['data'][0]['staff_id'])) {
                    $new_staff_id = (int)$findRes['data'][0]['staff_id'];
                }
            }
            if ($new_staff_id) {
                $mapSql = "INSERT INTO cotoka.staff_salons (tenant_id, staff_id, salon_id, is_primary) VALUES ($tenant_id, $new_staff_id, $salon_id, true) ON CONFLICT (tenant_id, staff_id, salon_id) DO NOTHING";
                supabaseRpcCall('execute_sql', ['query' => $mapSql]);
            }
            
            // 成功メッセージをフラッシュメッセージとして保存
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'スタッフが正常に追加されました。'];
            
            // リダイレクトして再読み込み（サロン優先の取得ロジック）
            header('Location: staff_management.php');
            exit;
        } else {
            $add_error = 'Supabaseエラー: ' . ($rpc['message'] ?? '');
        }
    }
}

// スタッフ編集処理
$edit_success = false;
$edit_error = '';

if (isset($_POST['edit_staff'])) {
    $staff_id = filter_var($_POST['staff_id'], FILTER_VALIDATE_INT);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $position = trim($_POST['position']);
    $hire_date = trim($_POST['hire_date']);
    $status = $_POST['status'];
    
    // 基本的なバリデーション
    if (empty($staff_id) || empty($first_name) || empty($last_name)) {
        $edit_error = 'スタッフID、姓と名は必須項目です。';
    } else {
        $rpc = supabaseRpcCall('staff_update_admin', [
            'p_staff_id' => (int)$staff_id,
            'p_first_name' => $first_name,
            'p_last_name' => $last_name,
            'p_email' => $email,
            'p_phone' => $phone,
            'p_position' => $position,
            'p_hire_date' => $hire_date,
            'p_status' => $status
        ]);
        if ($rpc['success']) {
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'スタッフ情報が正常に更新されました。'];
            header('Location: staff_management.php');
            exit;
        } else {
            $edit_error = 'Supabaseエラー: ' . ($rpc['message'] ?? '');
        }
    }
}

// スタッフ削除処理
$delete_success = false;
$delete_error = '';

if (isset($_POST['delete_staff'])) {
    $staff_id = filter_var($_POST['staff_id'], FILTER_VALIDATE_INT);
    
    if (!$staff_id) {
        $delete_error = '無効なスタッフIDです。';
    } else {
        $rpc = supabaseRpcCall('staff_delete_admin', ['p_staff_id' => (int)$staff_id]);
        if ($rpc['success']) {
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'スタッフが正常に削除されました。'];
            header('Location: staff_management.php');
            exit;
        } else {
            $delete_error = 'Supabaseエラー: ' . ($rpc['message'] ?? '');
        }
    }
}

// スタッフ一覧を取得
// スタッフ一覧（Supabase RPC）
$staff_list = [];
$debug_message = "テナントID: $tenant_id, サロンID: $salon_id";

// デバッグ用：テナントIDが正しいか確認
if (!$tenant_id || $tenant_id <= 0) {
    $error_message = "無効なテナントID: $tenant_id";
} else {
    // サロン配属優先でスタッフを取得（中間テーブル: cotoka.staff_salons 結合）
    $salonSql = "SELECT s.staff_id, s.tenant_id, s.user_id, s.first_name, s.last_name, s.email, s.phone, s.position, s.bio, s.status, s.created_at, s.updated_at\n                 FROM cotoka.staff s\n                 JOIN cotoka.staff_salons ss\n                   ON ss.staff_id = s.staff_id\n                  AND ss.tenant_id = s.tenant_id\n                WHERE ss.salon_id = $salon_id\n                  AND s.tenant_id = $tenant_id\n             ORDER BY s.staff_id DESC";
    $salonResult = supabaseRpcCall('execute_sql', ['query' => $salonSql]);

    if ($salonResult['success'] && is_array($salonResult['data']) && count($salonResult['data']) > 0) {
        $staff_list = $salonResult['data'];
        $debug_message .= " | サロン配属: " . count($staff_list) . "件";
    } else {
        // フォールバック: tenant_idベースでスタッフを取得
        $directSql = "SELECT staff_id, tenant_id, user_id, first_name, last_name, email, phone, position, bio, status, created_at, updated_at FROM cotoka.staff WHERE tenant_id = $tenant_id ORDER BY staff_id DESC";
        $directResult = supabaseRpcCall('execute_sql', ['query' => $directSql]);
        
        if ($directResult['success']) {
            $staff_list = is_array($directResult['data']) ? $directResult['data'] : [];
            $debug_message .= " | フォールバック(テナント): " . count($staff_list) . "件";
        } else {
            // エラーハンドリング
            $staff_list = [];
            $error_message = "スタッフ一覧取得エラー（Supabase）: " . ($directResult['message'] ?? '不明なエラー');
            $debug_message .= " | エラー詳細: " . ($directResult['message'] ?? '不明なエラー');
        }
    }

    // 予約数は未集計のため0で初期化
    foreach ($staff_list as $key => $s) {
        $staff_list[$key]['appointment_count'] = 0;
    }
}

// ページタイトルとCSS
$page_title = "スタッフ管理";
$page_css = <<<EOT
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="assets/css/staff_management/staff_management.css">
EOT;

// ヘッダーの読み込み
require_once 'includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="row g-0">
        <!-- メインコンテンツエリア -->
        <main class="col-md-12 col-lg-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">スタッフ管理</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                        <i class="fas fa-plus"></i> スタッフ追加
                    </button>
                </div>
            </div>

            <!-- フラッシュメッセージの表示 -->
            <?php displayFlashMessages(); ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
                </div>
            <?php endif; ?>

            <!-- デバッグ情報 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">デバッグ情報</h5>
                </div>
                <div class="card-body">
                    <p><strong>現在のサロンID:</strong> <?php echo htmlspecialchars($salon_id ?? '未設定'); ?></p>
                    <p><strong>現在のテナントID:</strong> <?php echo htmlspecialchars($tenant_id ?? '未設定'); ?></p>
                    <?php if (isset($debug_message)): ?>
                        <p><strong>デバッグメッセージ:</strong> <?php echo htmlspecialchars($debug_message); ?></p>
                    <?php endif; ?>
                    <p><strong>セッション情報:</strong></p>
                    <pre><?php echo htmlspecialchars(json_encode($_SESSION, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                </div>
            </div>

            <!-- デバッグ情報 -->
            <?php if (isset($debug_message)): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <strong>デバッグ情報:</strong> <?php echo htmlspecialchars($debug_message); ?>
                    <?php if (!empty($staff_list)): ?>
                        <br>最初のスタッフID: <?php echo htmlspecialchars($staff_list[0]['staff_id']); ?>
                        <br>サロンID: <?php echo htmlspecialchars($salon_id); ?>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
                </div>
            <?php endif; ?>

            <!-- スタッフ一覧テーブル -->
            <div class="table-responsive" style="overflow-x: auto; width: 100%;">
                <table class="table table-striped table-bordered" style="min-width: 1000px;">
                    <thead>
                        <tr>
                            <th width="5%">ID</th>
                            <th width="15%">名前</th>
                            <th width="15%">メール</th>
                            <th width="10%">電話</th>
                            <th width="10%">役職</th>
                            <th width="10%">入社日</th>
                            <th width="8%">ステータス</th>
                            <th width="7%">予約数</th>
                            <th width="20%">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($staff_list)): ?>
                            <?php foreach ($staff_list as $staff): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($staff['staff_id']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['last_name'] . ' ' . $staff['first_name']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['position']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['hire_date']); ?></td>
                                    <td>
                                        <?php if ($staff['status'] == 'active'): ?>
                                            <span class="badge bg-success">有効</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">無効</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(isset($staff['appointment_count']) ? $staff['appointment_count'] : 0); ?></td>
                                    <td class="actions-column">
                                        <div class="d-flex gap-1 justify-content-start">
                                            <button type="button" class="btn btn-sm btn-info edit-staff" 
                                                    data-staff-id="<?php echo $staff['staff_id']; ?>"
                                                    data-first-name="<?php echo htmlspecialchars($staff['first_name']); ?>"
                                                    data-last-name="<?php echo htmlspecialchars($staff['last_name']); ?>"
                                                    data-email="<?php echo htmlspecialchars($staff['email']); ?>"
                                                    data-phone="<?php echo htmlspecialchars($staff['phone']); ?>"
                                                    data-position="<?php echo htmlspecialchars($staff['position']); ?>"
                                                    data-hire-date="<?php echo htmlspecialchars($staff['hire_date']); ?>"
                                                    data-status="<?php echo htmlspecialchars($staff['status']); ?>"
                                                    data-bs-toggle="modal" data-bs-target="#editStaffModal">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="staff_shifts.php?staff_id=<?php echo $staff['staff_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-calendar-alt"></i> シフト
                                            </a>
                                            <button type="button" class="btn btn-sm btn-success manage-services" 
                                                    data-staff-id="<?php echo $staff['staff_id']; ?>"
                                                    data-staff-name="<?php echo htmlspecialchars($staff['last_name'] . ' ' . $staff['first_name']); ?>"
                                                    data-bs-toggle="modal" data-bs-target="#manageServicesModal">
                                                <i class="fas fa-spa"></i> 施術
                                            </button>
                                            <?php if (!isset($staff['appointment_count']) || intval($staff['appointment_count']) == 0): ?>
                                                <button type="button" class="btn btn-sm btn-danger delete-staff" 
                                                        data-staff-id="<?php echo $staff['staff_id']; ?>"
                                                        data-staff-name="<?php echo htmlspecialchars($staff['last_name'] . ' ' . $staff['first_name']); ?>"
                                                        data-bs-toggle="modal" data-bs-target="#deleteStaffModal">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                            <td colspan="9" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="fas fa-user-slash fa-2x mb-3"></i><br>
                                    このサロンに登録されているスタッフはいません<br>
                                    <small class="text-muted">サロンID: <?php echo htmlspecialchars($salon_id); ?></small>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 表示トラブルシューティングガイド -->
            <div class="alert alert-info mt-3">
                <h5><i class="fas fa-info-circle"></i> 表示に関する注意</h5>
                <p>テーブルの内容が全て表示されていない場合：</p>
                <ul>
                    <li>画面を横にスクロールして、操作ボタンを表示してください。</li>
                    <li>より大きい画面サイズのデバイスでご覧いただくことをお勧めします。</li>
                    <li>ブラウザの表示を100%にリセットしてください（Ctrl+0またはCmd+0）。</li>
                </ul>
            </div>
        </main>
    </div>
</div>

<!-- スタッフ追加モーダル -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStaffModalLabel">新規スタッフ追加</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <form action="staff_management.php" method="post">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">名</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">姓</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">メールアドレス</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">電話番号</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="position" class="form-label">役職</label>
                        <input type="text" class="form-control" id="position" name="position">
                    </div>
                    <div class="mb-3">
                        <label for="hire_date" class="form-label">入社日</label>
                        <input type="date" class="form-control date-picker" id="hire_date" name="hire_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="default_shift_pattern" name="default_shift_pattern" value="1" checked>
                        <label class="form-check-label" for="default_shift_pattern">デフォルトのシフトパターンを作成</label>
                        <small class="form-text text-muted">平日：9:00-18:00、土日：10:00-17:00</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" name="add_staff" class="btn btn-primary">追加</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- スタッフ編集モーダル -->
<div class="modal fade" id="editStaffModal" tabindex="-1" aria-labelledby="editStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editStaffModalLabel">スタッフ情報編集</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <form action="staff_management.php" method="post">
                <div class="modal-body">
                    <input type="hidden" id="edit_staff_id" name="staff_id">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_first_name" class="form-label">名</label>
                            <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_last_name" class="form-label">姓</label>
                            <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">メールアドレス</label>
                        <input type="email" class="form-control" id="edit_email" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">電話番号</label>
                        <input type="tel" class="form-control" id="edit_phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="edit_position" class="form-label">役職</label>
                        <input type="text" class="form-control" id="edit_position" name="position">
                    </div>
                    <div class="mb-3">
                        <label for="edit_hire_date" class="form-label">入社日</label>
                        <input type="date" class="form-control date-picker" id="edit_hire_date" name="hire_date">
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">ステータス</label>
                        <select class="form-select" id="edit_status" name="status">
                            <option value="active">有効</option>
                            <option value="inactive">非活性</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" name="edit_staff" class="btn btn-primary">更新</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- スタッフ削除確認モーダル -->
<div class="modal fade" id="deleteStaffModal" tabindex="-1" aria-labelledby="deleteStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteStaffModalLabel">スタッフ削除確認</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <p id="delete-confirm-text">このスタッフを削除してもよろしいですか？</p>
                <p class="text-danger">この操作は取り消せません。</p>
            </div>
            <div class="modal-footer">
                <form action="staff_management.php" method="post">
                    <input type="hidden" id="delete_staff_id" name="staff_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" name="delete_staff" class="btn btn-danger">削除</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ja.js"></script>

<!-- 施術可能なサービス管理モーダル -->
<div class="modal fade" id="manageServicesModal" tabindex="-1" aria-labelledby="manageServicesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manageServicesModalLabel">施術可能なサービス管理</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="service_staff_id" value="">
                <h6 id="service_staff_name" class="mb-3"></h6>
                
                <div id="loading-services" class="text-center my-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">読み込み中...</span>
                    </div>
                    <p>サービスを読み込んでいます...</p>
                </div>
                
                <div id="services-container" class="d-none">
                    <div class="mb-3">
                        <input type="text" id="service-search" class="form-control" placeholder="サービス名で検索...">
                    </div>
                    
                    <form id="staff-services-form">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>選択</th>
                                        <th>サービス名</th>
                                        <th>カテゴリ</th>
                                        <th>時間</th>
                                        <th>料金</th>
                                        <th>習熟度</th>
                                    </tr>
                                </thead>
                                <tbody id="services-table-body">
                                    <!-- サービス一覧がここに動的に挿入されます -->
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
                
                <div id="no-services-message" class="alert alert-info d-none">
                    サービスが見つかりませんでした。
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="button" id="save-staff-services" class="btn btn-primary">保存</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 日付ピッカーの初期化
    flatpickr(".date-picker", {
        locale: "ja",
        dateFormat: "Y-m-d",
        allowInput: true
    });
    
    // スタッフ編集モーダルのデータ設定
    document.querySelectorAll('.edit-staff').forEach(function(button) {
        button.addEventListener('click', function() {
            const staffId = this.getAttribute('data-staff-id');
            const firstName = this.getAttribute('data-first-name');
            const lastName = this.getAttribute('data-last-name');
            const email = this.getAttribute('data-email');
            const phone = this.getAttribute('data-phone');
            const position = this.getAttribute('data-position');
            const hireDate = this.getAttribute('data-hire-date');
            const status = this.getAttribute('data-status');
            
            document.getElementById('edit_staff_id').value = staffId;
            document.getElementById('edit_first_name').value = firstName;
            document.getElementById('edit_last_name').value = lastName;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_position').value = position;
            document.getElementById('edit_hire_date').value = hireDate;
            document.getElementById('edit_status').value = status;
        });
    });
    
    // スタッフ削除モーダルのデータ設定
    document.querySelectorAll('.delete-staff').forEach(function(button) {
        button.addEventListener('click', function() {
            const staffId = this.getAttribute('data-staff-id');
            const staffName = this.getAttribute('data-staff-name');
            
            document.getElementById('delete_staff_id').value = staffId;
            document.getElementById('delete-confirm-text').textContent = `スタッフ「${staffName}」を削除してもよろしいですか？`;
        });
    });

    // 施術可能なサービス管理モーダルの処理
    document.querySelectorAll('.manage-services').forEach(function(button) {
        button.addEventListener('click', function() {
            const staffId = this.getAttribute('data-staff-id');
            const staffName = this.getAttribute('data-staff-name');
            
            document.getElementById('service_staff_id').value = staffId;
            document.getElementById('service_staff_name').textContent = `${staffName} のサービス設定`;
            
            // サービス一覧を取得する
            loadStaffServices(staffId);
        });
    });

    // サービス検索フィルター
    document.getElementById('service-search').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#services-table-body tr');
        
        rows.forEach(row => {
            const serviceName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const category = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            
            if (serviceName.includes(searchTerm) || category.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // サービス設定を保存
    document.getElementById('save-staff-services').addEventListener('click', function() {
        const staffId = document.getElementById('service_staff_id').value;
        const formData = new FormData();
        
        formData.append('staff_id', staffId);
        formData.append('action', 'save_staff_services');
        
        // 選択されたサービスを取得
        const serviceCheckboxes = document.querySelectorAll('input[name="service[]"]:checked');
        serviceCheckboxes.forEach(checkbox => {
            const serviceId = checkbox.value;
            const proficiencySelect = document.querySelector(`select[name="proficiency_${serviceId}"]`);
            
            formData.append('service_ids[]', serviceId);
            formData.append(`proficiency_${serviceId}`, proficiencySelect.value);
        });
        
        // 非同期リクエストでサービス設定を保存
        fetch('staff_services_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('サービス設定が保存されました。');
                // モーダルを閉じる
                const modal = bootstrap.Modal.getInstance(document.getElementById('manageServicesModal'));
                modal.hide();
            } else {
                alert('エラーが発生しました: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('保存中にエラーが発生しました。');
        });
    });
});

// スタッフの施術可能なサービス一覧を読み込む
function loadStaffServices(staffId) {
    const loadingElement = document.getElementById('loading-services');
    const servicesContainer = document.getElementById('services-container');
    const noServicesMessage = document.getElementById('no-services-message');
    
    // 表示状態をリセット
    loadingElement.classList.remove('d-none');
    servicesContainer.classList.add('d-none');
    noServicesMessage.classList.add('d-none');
    
    // 非同期リクエストでサービス一覧を取得
    fetch(`staff_services_handler.php?staff_id=${staffId}&action=get_services`)
        .then(response => response.json())
        .then(data => {
            loadingElement.classList.add('d-none');
            
            if (data.services && data.services.length > 0) {
                servicesContainer.classList.remove('d-none');
                renderServicesTable(data.services, data.staffServices || []);
            } else {
                noServicesMessage.classList.remove('d-none');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            loadingElement.classList.add('d-none');
            alert('サービス情報の取得中にエラーが発生しました。');
        });
}

// サービス一覧テーブルを描画
function renderServicesTable(services, staffServices) {
    const tableBody = document.getElementById('services-table-body');
    tableBody.innerHTML = ''; // テーブルをクリア
    
    // スタッフが提供可能なサービスIDを取得
    const activeServiceIds = staffServices.map(service => parseInt(service.service_id));
    
    // 各サービスの行を生成
    services.forEach(service => {
        const isActive = activeServiceIds.includes(parseInt(service.service_id));
        let proficiencyLevel = '中級';
        
        // すでに登録済みのサービスの場合、習熟度を取得
        const staffService = staffServices.find(s => parseInt(s.service_id) === parseInt(service.service_id));
        if (staffService) {
            proficiencyLevel = staffService.proficiency_level;
        }
        
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="service[]" value="${service.service_id}" id="service_${service.service_id}" ${isActive ? 'checked' : ''}>
                </div>
            </td>
            <td>${service.name}</td>
            <td>${service.category || '-'}</td>
            <td>${service.duration}分</td>
            <td>¥${parseFloat(service.price).toLocaleString()}</td>
            <td>
                <select class="form-select form-select-sm" name="proficiency_${service.service_id}">
                    <option value="初級" ${proficiencyLevel === '初級' ? 'selected' : ''}>初級</option>
                    <option value="中級" ${proficiencyLevel === '中級' ? 'selected' : ''}>中級</option>
                    <option value="上級" ${proficiencyLevel === '上級' ? 'selected' : ''}>上級</option>
                    <option value="マスター" ${proficiencyLevel === 'マスター' ? 'selected' : ''}>マスター</option>
                </select>
            </td>
        `;
        tableBody.appendChild(row);
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>