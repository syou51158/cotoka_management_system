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

// エラー表示を無効化（本番環境用）
ini_set('display_errors', 0);
error_reporting(0);

// データベース接続エラーをキャッチ
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    // 致命的なエラー（接続できない場合）
    $error_message = "データベース接続エラー：" . $e->getMessage();
    // ヘッダーの読み込み
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
        header('Location: services.php' . (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
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
        header('Location: services.php' . (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
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

$tenant_id = getCurrentTenantId();

// アクション処理
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// サービス追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $name = trim($_POST['name']);
    $category = trim($_POST['category'] ?? '');
    $duration = (int)$_POST['duration'];
    $price = (int)$_POST['price'];
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'];
    
    // 入力値の検証
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "サービス名を入力してください。";
    }
    
    if ($duration <= 0) {
        $errors[] = "有効な所要時間を入力してください。";
    }
    
    if ($price < 0) {
        $errors[] = "有効な料金を入力してください。";
    }
    
    // エラーがなければデータベースに保存
    if (empty($errors)) {
        $categoryId = null; // 任意: カテゴリーIDに変換する場合はここで対応
        $rpc = supabaseRpcCall('service_add_admin', [
            'p_tenant_id' => (int)$tenant_id,
            'p_name' => $name,
            'p_category_id' => $categoryId,
            'p_duration' => (int)$duration,
            'p_price' => (float)$price,
            'p_description' => $description,
            'p_is_active' => ($status === 'active')
        ]);
        if ($rpc['success']) {
            $_SESSION['success_message'] = "サービスが正常に追加されました。";
            header("Location: services.php");
            exit;
        } else {
            $errors[] = "Supabaseエラー: " . ($rpc['message'] ?? '');
        }
    }
}

// サービス編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_service'])) {
    $service_id = (int)$_POST['service_id'];
    $name = trim($_POST['name']);
    $category = trim($_POST['category'] ?? '');
    $duration = (int)$_POST['duration'];
    $price = (int)$_POST['price'];
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'];
    
    // 入力値の検証
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "サービス名を入力してください。";
    }
    
    if ($duration <= 0) {
        $errors[] = "有効な所要時間を入力してください。";
    }
    
    if ($price < 0) {
        $errors[] = "有効な料金を入力してください。";
    }
    
    // エラーがなければデータベースを更新
    if (empty($errors)) {
        $categoryId = null; // 任意: カテゴリーIDに変換する場合はここで対応
        $rpc = supabaseRpcCall('service_update_admin', [
            'p_service_id' => (int)$service_id,
            'p_name' => $name,
            'p_category_id' => $categoryId,
            'p_duration' => (int)$duration,
            'p_price' => (float)$price,
            'p_description' => $description,
            'p_is_active' => ($status === 'active')
        ]);
        if ($rpc['success']) {
            $_SESSION['success_message'] = "サービスが正常に更新されました。";
            header("Location: services.php");
            exit;
        } else {
            $errors[] = "Supabaseエラー: " . ($rpc['message'] ?? '');
        }
    }
}

// サービス削除処理
if ($action === 'delete' && isset($_GET['id'])) {
    $service_id = (int)$_GET['id'];
    
    try {
        // 予約参照チェックはDB側でRLS/制約に委譲し、RPCで削除
        $rpc = supabaseRpcCall('service_delete_admin', ['p_service_id' => (int)$service_id]);
        if ($rpc['success']) {
            $_SESSION['success_message'] = "サービスが正常に削除されました。";
        } else {
            $_SESSION['error_message'] = "Supabaseエラー: " . ($rpc['message'] ?? '');
        }
        header("Location: services.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "データベースエラー: " . $e->getMessage();
        header("Location: services.php");
        exit;
    }
}

// 編集のためのサービス情報を取得
$service_to_edit = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $service_id = (int)$_GET['id'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM services WHERE service_id = :service_id AND salon_id = :salon_id");
        $stmt->bindParam(':service_id', $service_id);
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->execute();
        $service_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$service_to_edit) {
            $_SESSION['error_message'] = "指定されたサービスが見つかりません。";
            header("Location: services.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "データベースエラー: " . $e->getMessage();
        header("Location: services.php");
        exit;
    }
}

// サービス一覧を取得
try {
    $stmt = $conn->prepare("SELECT * FROM services WHERE salon_id = :salon_id ORDER BY name ASC");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "データベースエラー: " . $e->getMessage();
    $services = [];
}

// カテゴリー一覧を取得
try {
    $cat_stmt = $conn->prepare("SELECT * FROM service_categories WHERE salon_id = :salon_id ORDER BY display_order ASC, name ASC");
    $cat_stmt->bindParam(':salon_id', $salon_id);
    $cat_stmt->execute();
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // カテゴリー名と色の連想配列を作成
    $category_colors = [];
    foreach ($categories as $category) {
        $category_colors[$category['name']] = $category['color'];
    }
} catch (PDOException $e) {
    // エラーがあっても処理を続行するため、ここでは何もしない
    $categories = [];
    $category_colors = [];
}

// カテゴリーが存在しない場合のデフォルトカテゴリー
if (empty($categories)) {
    $default_categories = [
        ['name' => 'フットケア', 'color' => '#4e73df'],
        ['name' => 'ボディケア', 'color' => '#1cc88a'],
        ['name' => 'フェイシャル', 'color' => '#36b9cc'],
        ['name' => 'ヘッドスパ', 'color' => '#f6c23e'],
        ['name' => 'その他', 'color' => '#6c757d']
    ];
    
    // デフォルトカテゴリーの連想配列も作成
    $category_colors = [];
    foreach ($default_categories as $category) {
        $category_colors[$category['name']] = $category['color'];
    }
}

// すべてのサービスのカテゴリー一覧（重複なし）
$service_categories = [];
foreach ($services as $service) {
    if (!empty($service['category']) && !in_array($service['category'], $service_categories)) {
        $service_categories[] = $service['category'];
    }
}
sort($service_categories);

// ページのタイトルとCSS/JS設定
$page_title = "サービス管理";
$page_css = '<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="assets/css/services.css">';
$page_js = '<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="assets/js/services.js"></script>';

// ヘッダーの読み込み
require_once 'includes/header.php';
?>

<!-- メインコンテンツ -->
<div class="container-fluid px-4">
    <h1 class="mt-4">
        <i class="fas fa-concierge-bell"></i> サービス管理
    </h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="dashboard.php">ダッシュボード</a></li>
        <li class="breadcrumb-item active">サービス管理</li>
    </ol>
    
    <!-- 成功・エラーメッセージの表示 -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); endif; ?>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <!-- アクションボタン -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-lg-6 col-md-8 col-12 mb-3 mb-lg-0">
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                            <i class="fas fa-plus me-1"></i> 新規サービス追加
                        </button>
                        <a href="goto_categories.php" class="btn btn-outline-primary" onclick="return confirm('カテゴリー管理画面に移動します。よろしいですか？');">
                            <i class="fas fa-tags me-1"></i> カテゴリー管理
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 col-md-4 col-12 text-lg-end">
                    <span class="badge bg-primary p-2 fs-6">全 <?php echo count($services); ?> 件</span>
                    <span class="badge bg-success p-2 fs-6 ms-2">有効 <?php echo count(array_filter($services, function($s) { return $s['status'] === 'active'; })); ?> 件</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 検索とフィルター -->
    <div class="card mb-4">
        <div class="card-body pb-2">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="service-search" class="form-control" placeholder="サービス名で検索...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="sort-options" class="form-select">
                        <option value="name-asc">サービス名 (昇順)</option>
                        <option value="name-desc">サービス名 (降順)</option>
                        <option value="price-asc">価格 (安い順)</option>
                        <option value="price-desc">価格 (高い順)</option>
                        <option value="duration-asc">所要時間 (短い順)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="status-filter" class="form-select">
                        <option value="all">すべてのステータス</option>
                        <option value="active">有効のみ</option>
                        <option value="inactive">無効のみ</option>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary view-toggle-btn active" data-view="card">
                            <i class="fas fa-th"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary view-toggle-btn" data-view="list">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- カテゴリーフィルター -->
            <div class="mt-3 mb-2">
                <div class="d-flex flex-wrap gap-1">
                    <button class="btn btn-sm btn-outline-secondary category-filter active" data-category="all">すべて</button>
                    <?php foreach ($categories as $category): ?>
                    <button class="btn btn-sm badge category-filter" 
                            data-category="<?php echo htmlspecialchars($category['name']); ?>"
                            style="background-color: <?php echo htmlspecialchars($category['color']); ?>; color: <?php echo (hexdec(substr($category['color'], 1, 2)) * 0.299 + hexdec(substr($category['color'], 3, 2)) * 0.587 + hexdec(substr($category['color'], 5, 2)) * 0.114) > 150 ? '#000000' : '#ffffff'; ?>">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- サービス一覧 -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($services)): ?>
            <div class="text-center p-4 empty-state">
                <i class="fas fa-concierge-bell mb-3 empty-state-icon"></i>
                <p class="mb-3">サービスが登録されていません</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                    <i class="fas fa-plus me-1"></i> 新規サービス追加
                </button>
            </div>
            <?php else: ?>
            <!-- カード表示用コンテナ -->
            <div id="services-container" class="card-view">
                <div class="row">
                    <?php foreach ($services as $service): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-4 service-item" 
                         data-name="<?php echo htmlspecialchars($service['name']); ?>"
                         data-price="<?php echo $service['price']; ?>" 
                         data-duration="<?php echo $service['duration']; ?>" 
                         data-date="<?php echo $service['created_at']; ?>"
                         data-status="<?php echo $service['status']; ?>"
                         data-category="<?php echo isset($service['category']) ? htmlspecialchars($service['category']) : 'その他'; ?>">
                        <?php
                            $card_category = isset($service['category']) && !empty($service['category']) ? $service['category'] : 'その他';
                            $card_color = isset($category_colors[$card_category]) ? $category_colors[$card_category] : '#6c757d';
                        ?>
                        <div class="card h-100 service-card <?php echo $service['status'] === 'active' ? 'active-service' : 'inactive-service'; ?>" 
                             style="border-top: 4px solid <?php echo $card_color; ?>; border-radius: 6px;">
                            <div class="card-body position-relative">
                                <div class="service-actions">
                                    <a href="services.php?action=edit&id=<?php echo $service['service_id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteServiceModal" data-id="<?php echo $service['service_id']; ?>" data-name="<?php echo htmlspecialchars($service['name']); ?>">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                                <?php if (isset($service['category']) && !empty($service['category'])): ?>
                                <?php
                                    $category_color = isset($category_colors[$service['category']]) ? $category_colors[$service['category']] : '#6c757d';
                                    $text_color = (hexdec(substr($category_color, 1, 2)) * 0.299 + hexdec(substr($category_color, 3, 2)) * 0.587 + hexdec(substr($category_color, 5, 2)) * 0.114) > 150 ? '#000000' : '#ffffff';
                                ?>
                                <span class="badge mb-2" style="background-color: <?php echo $category_color; ?>; color: <?php echo $text_color; ?>">
                                    <?php echo htmlspecialchars($service['category']); ?>
                                </span>
                                <?php endif; ?>
                                <h5 class="card-title service-name"><?php echo htmlspecialchars($service['name']); ?></h5>
                                <div class="service-description mb-3"><?php echo htmlspecialchars($service['description']); ?></div>
                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                    <span class="service-duration"><i class="far fa-clock me-1"></i> <?php echo $service['duration']; ?>分</span>
                                    <span class="service-price">¥<?php echo number_format($service['price']); ?></span>
                                </div>
                                <div class="mt-2">
                                    <span class="badge <?php echo $service['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $service['status'] === 'active' ? '有効' : '無効'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- サービス追加モーダル -->
<div class="modal fade" id="addServiceModal" tabindex="-1" aria-labelledby="addServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addServiceModalLabel">
                    <i class="fas fa-plus-circle"></i> 新規サービス追加
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="services.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">サービス名 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="category" class="form-label">カテゴリー</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">カテゴリーを選択</option>
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['name']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($default_categories as $default_cat): ?>
                                <option value="<?php echo htmlspecialchars($default_cat['name']); ?>"><?php echo htmlspecialchars($default_cat['name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($service_categories)): ?>
                                <?php foreach ($service_categories as $cat): ?>
                                    <?php if ((!empty($categories) && !in_array($cat, array_column($categories, 'name'))) || 
                                             (empty($categories) && !in_array($cat, array_column($default_categories, 'name')))): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="duration" class="form-label">所要時間（分） <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="duration" name="duration" min="5" step="5" required>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">料金（円） <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="price" name="price" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">説明</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">ステータス</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active">有効</option>
                            <option value="inactive">無効</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" name="add_service" class="btn btn-primary">サービスを追加</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($action === 'edit' && $service_to_edit): ?>
<!-- サービス編集モーダル -->
<div class="modal fade" id="editServiceModal" tabindex="-1" aria-labelledby="editServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editServiceModalLabel">
                    <i class="fas fa-edit"></i> サービス編集
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="services.php">
                <input type="hidden" name="service_id" value="<?php echo $service_to_edit['service_id']; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">サービス名 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" value="<?php echo htmlspecialchars($service_to_edit['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category" class="form-label">カテゴリー</label>
                        <select class="form-select" id="edit_category" name="category">
                            <option value="">カテゴリーを選択</option>
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['name']); ?>" <?php echo ($service_to_edit['category'] == $category['name']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($default_categories as $default_cat): ?>
                                <option value="<?php echo htmlspecialchars($default_cat['name']); ?>" <?php echo ($service_to_edit['category'] == $default_cat['name']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($default_cat['name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($service_categories)): ?>
                                <?php foreach ($service_categories as $cat): ?>
                                    <?php if ((!empty($categories) && !in_array($cat, array_column($categories, 'name'))) || 
                                             (empty($categories) && !in_array($cat, array_column($default_categories, 'name')))): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($service_to_edit['category'] == $cat) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_duration" class="form-label">所要時間（分） <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="edit_duration" name="duration" min="5" step="5" value="<?php echo $service_to_edit['duration']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_price" class="form-label">料金（円） <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="edit_price" name="price" min="0" value="<?php echo $service_to_edit['price']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">説明</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"><?php echo htmlspecialchars($service_to_edit['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">ステータス</label>
                        <select class="form-select" id="edit_status" name="status">
                            <option value="active" <?php echo ($service_to_edit['status'] == 'active') ? 'selected' : ''; ?>>有効</option>
                            <option value="inactive" <?php echo ($service_to_edit['status'] == 'inactive') ? 'selected' : ''; ?>>無効</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" name="edit_service" class="btn btn-primary">変更を保存</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- サービス削除確認モーダル -->
<div class="modal fade" id="deleteServiceModal" tabindex="-1" aria-labelledby="deleteServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteServiceModalLabel">
                    <i class="fas fa-trash"></i> サービスの削除
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>このサービスを削除してもよろしいですか？</p>
                <p><strong id="deleteServiceName"></strong></p>
                <p class="text-danger">※この操作は取り消せません。予約で使用されているサービスは削除できません。</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">削除する</a>
            </div>
        </div>
    </div>
</div>

<?php
// フッターの読み込み
require_once 'includes/footer.php';
?> 