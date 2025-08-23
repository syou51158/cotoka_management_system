<?php
/**
 * サービスカテゴリー管理画面
 * 
 * サービスカテゴリーの追加、編集、削除を行うための管理画面
 */

// 設定ファイルと関数の読み込み
require_once 'config/config.php';
require_once 'includes/functions.php';

// タイムゾーンを設定
date_default_timezone_set('Asia/Tokyo');

// セッションチェック
if (!isset($_SESSION['user_id']) || !isset($_SESSION['salon_id'])) {
    // ログインしていない場合はログインページにリダイレクト
    header('Location: login.php');
    exit;
}

// デバッグ - アクセスログ記録
$debug_log = fopen("logs/service_categories_access.log", "a");
fwrite($debug_log, date('[Y-m-d H:i:s]') . " アクセス: ユーザーID=" . $_SESSION['user_id'] . ", サロンID=" . $_SESSION['salon_id'] . "\n");
fclose($debug_log);

// ユーザー情報の取得
$user_id = $_SESSION['user_id'];
$salon_id = $_SESSION['salon_id'];
$tenant_id = $_SESSION['tenant_id'] ?? 1;

// データベース接続
try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

// エラーと成功メッセージの初期化
$errors = [];
$success_message = '';

// カテゴリー追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $color = trim($_POST['color']);
    $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    
    // 入力値の検証
    if (empty($name)) {
        $errors[] = "カテゴリー名を入力してください。";
    }
    
    if (empty($color)) {
        $color = '#4e73df'; // デフォルトカラー
    }
    
    // 同じ名前のカテゴリーが存在するかチェック
    if (empty($errors)) {
        try {
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM service_categories WHERE salon_id = :salon_id AND name = :name");
            $check_stmt->bindParam(':salon_id', $salon_id);
            $check_stmt->bindParam(':name', $name);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() > 0) {
                $errors[] = "同じ名前のカテゴリーが既に存在します。";
            }
        } catch (PDOException $e) {
            $errors[] = "データベースエラー: " . $e->getMessage();
        }
    }
    
    // エラーがなければデータベースに保存
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO service_categories (salon_id, tenant_id, name, color, display_order, created_at, updated_at) VALUES (:salon_id, :tenant_id, :name, :color, :display_order, NOW(), NOW())");
            $stmt->bindParam(':salon_id', $salon_id);
            $stmt->bindParam(':tenant_id', $tenant_id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':color', $color);
            $stmt->bindParam(':display_order', $display_order);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "カテゴリーが正常に追加されました。";
                header("Location: service_categories.php");
                exit;
            } else {
                $errors[] = "カテゴリーの追加に失敗しました。";
            }
        } catch (PDOException $e) {
            $errors[] = "データベースエラー: " . $e->getMessage();
        }
    }
}

// カテゴリー編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $category_id = (int)$_POST['category_id'];
    $name = trim($_POST['name']);
    $color = trim($_POST['color']);
    $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    
    // 入力値の検証
    if (empty($name)) {
        $errors[] = "カテゴリー名を入力してください。";
    }
    
    if (empty($color)) {
        $color = '#4e73df'; // デフォルトカラー
    }
    
    // 同じ名前の別カテゴリーが存在するかチェック
    if (empty($errors)) {
        try {
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM service_categories WHERE salon_id = :salon_id AND name = :name AND category_id != :category_id");
            $check_stmt->bindParam(':salon_id', $salon_id);
            $check_stmt->bindParam(':name', $name);
            $check_stmt->bindParam(':category_id', $category_id);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() > 0) {
                $errors[] = "同じ名前のカテゴリーが既に存在します。";
            }
        } catch (PDOException $e) {
            $errors[] = "データベースエラー: " . $e->getMessage();
        }
    }
    
    // エラーがなければデータベースを更新
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE service_categories SET name = :name, color = :color, display_order = :display_order, updated_at = NOW() WHERE category_id = :category_id AND salon_id = :salon_id");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':color', $color);
            $stmt->bindParam(':display_order', $display_order);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->bindParam(':salon_id', $salon_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "カテゴリーが正常に更新されました。";
                header("Location: service_categories.php");
                exit;
            } else {
                $errors[] = "カテゴリーの更新に失敗しました。";
            }
        } catch (PDOException $e) {
            $errors[] = "データベースエラー: " . $e->getMessage();
        }
    }
}

// カテゴリー削除処理
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $category_id = (int)$_GET['id'];
    
    // このカテゴリーを使用しているサービスがあるか確認
    try {
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM services WHERE salon_id = :salon_id AND category = (SELECT name FROM service_categories WHERE category_id = :category_id AND salon_id = :salon_id)");
        $check_stmt->bindParam(':salon_id', $salon_id);
        $check_stmt->bindParam(':category_id', $category_id);
        $check_stmt->execute();
        
        $service_count = $check_stmt->fetchColumn();
        
        if ($service_count > 0) {
            $_SESSION['error_message'] = "このカテゴリーは {$service_count} 件のサービスで使用されているため削除できません。";
            header("Location: service_categories.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "データベースエラー: " . $e->getMessage();
        header("Location: service_categories.php");
        exit;
    }
    
    // カテゴリーを削除
    try {
        $stmt = $conn->prepare("DELETE FROM service_categories WHERE category_id = :category_id AND salon_id = :salon_id");
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':salon_id', $salon_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "カテゴリーが正常に削除されました。";
        } else {
            $_SESSION['error_message'] = "カテゴリーの削除に失敗しました。";
        }
        
        header("Location: service_categories.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "データベースエラー: " . $e->getMessage();
        header("Location: service_categories.php");
        exit;
    }
}

// カテゴリー一覧を取得
try {
    $stmt = $conn->prepare("SELECT * FROM service_categories WHERE salon_id = :salon_id ORDER BY display_order ASC, name ASC");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "データベースエラー: " . $e->getMessage();
    $categories = [];
}

// 各カテゴリーの使用状況を取得
foreach ($categories as &$category) {
    try {
        $service_stmt = $conn->prepare("SELECT COUNT(*) FROM services WHERE salon_id = :salon_id AND category = :category_name");
        $service_stmt->bindParam(':salon_id', $salon_id);
        $service_stmt->bindParam(':category_name', $category['name']);
        $service_stmt->execute();
        $category['service_count'] = $service_stmt->fetchColumn();
    } catch (PDOException $e) {
        $category['service_count'] = 0;
    }
}
unset($category); // 参照を解除

// ページのタイトルとCSS/JS設定
$page_title = "サービスカテゴリー管理";
$custom_css = ["assets/css/service_categories.css"];
$custom_js = ["assets/js/service_categories.js"];

// 検証用デバッグコード - リダイレクト前に追加
$debug_log = fopen("logs/service_categories_debug.log", "a");
fwrite($debug_log, date('[Y-m-d H:i:s]') . " ヘッダー読み込み前: " . $_SERVER['PHP_SELF'] . "\n");
fclose($debug_log);

// ヘッダーの読み込み
include 'includes/header.php';

// 検証用デバッグコード - リダイレクト後に追加
$debug_log = fopen("logs/service_categories_debug.log", "a");
fwrite($debug_log, date('[Y-m-d H:i:s]') . " ヘッダー読み込み後: " . $_SERVER['PHP_SELF'] . "\n");
fclose($debug_log);
?>

<div class="container-fluid">
    <h1 class="mt-4">
        <i class="fas fa-tags"></i> サービスカテゴリー管理
    </h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="dashboard.php">ダッシュボード</a></li>
        <li class="breadcrumb-item"><a href="services.php">サービス管理</a></li>
        <li class="breadcrumb-item active">カテゴリー管理</li>
    </ol>
    
    <!-- 成功メッセージの表示 -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); endif; ?>
    
    <!-- エラーメッセージの表示 -->
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); endif; ?>
    
    <!-- バリデーションエラーメッセージの表示 -->
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
    
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-info-circle"></i> カテゴリーについて
                    </h5>
                    <p>カテゴリーを使用して、サービスを分類・整理できます。カテゴリーごとに色を設定して視覚的に区別することも可能です。</p>
                    <div class="mt-3">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus me-1"></i> 新規カテゴリー追加
                        </button>
                        <a href="services.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-arrow-left me-1"></i> サービス管理に戻る
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-chart-pie"></i> カテゴリー統計
                    </h5>
                    <div class="d-flex justify-content-around">
                        <div class="text-center">
                            <h3><?php echo count($categories); ?></h3>
                            <p>総カテゴリー数</p>
                        </div>
                        <div class="text-center">
                            <h3><?php 
                                $used_categories = array_filter($categories, function($c) { return $c['service_count'] > 0; });
                                echo count($used_categories); 
                            ?></h3>
                            <p>使用中のカテゴリー</p>
                        </div>
                        <div class="text-center">
                            <h3><?php 
                                $unused_categories = array_filter($categories, function($c) { return $c['service_count'] == 0; });
                                echo count($unused_categories); 
                            ?></h3>
                            <p>未使用のカテゴリー</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i> カテゴリー一覧
        </div>
        <div class="card-body">
            <?php if (empty($categories)): ?>
            <div class="empty-state text-center">
                <i class="fas fa-tags empty-state-icon mb-3"></i>
                <h4>カテゴリーがありません</h4>
                <p>「新規カテゴリー追加」ボタンをクリックして、最初のカテゴリーを作成しましょう。</p>
                <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus me-1"></i> 新規カテゴリー追加
                </button>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th style="width: 80px;">カラー</th>
                            <th>カテゴリー名</th>
                            <th style="width: 100px;">表示順</th>
                            <th style="width: 120px;">使用サービス</th>
                            <th style="width: 150px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo $category['category_id']; ?></td>
                            <td>
                                <span class="color-preview" style="background-color: <?php echo htmlspecialchars($category['color']); ?>"></span>
                                <small><?php echo htmlspecialchars($category['color']); ?></small>
                            </td>
                            <td>
                                <span class="badge" style="background-color: <?php echo htmlspecialchars($category['color']); ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </span>
                            </td>
                            <td><?php echo $category['display_order']; ?></td>
                            <td>
                                <?php if ($category['service_count'] > 0): ?>
                                <span class="badge bg-info"><?php echo $category['service_count']; ?> サービス</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">未使用</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex">
                                    <button class="btn btn-sm btn-outline-primary me-1 edit-category-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editCategoryModal" 
                                            data-id="<?php echo $category['category_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                            data-color="<?php echo htmlspecialchars($category['color']); ?>"
                                            data-order="<?php echo $category['display_order']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($category['service_count'] == 0): ?>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteCategoryModal"
                                            data-id="<?php echo $category['category_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($category['name']); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-outline-danger" disabled title="使用中のカテゴリーは削除できません">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
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

<!-- カテゴリー追加モーダル -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">
                    <i class="fas fa-plus-circle"></i> 新規カテゴリー追加
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="service_categories.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">カテゴリー名 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="color" class="form-label">カラー</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" id="color" name="color" value="#4e73df">
                            <input type="text" class="form-control" id="color_hex" value="#4e73df" oninput="document.getElementById('color').value = this.value">
                        </div>
                        <small class="text-muted">カテゴリーの表示色を選択してください</small>
                    </div>
                    <div class="mb-3">
                        <label for="display_order" class="form-label">表示順</label>
                        <input type="number" class="form-control" id="display_order" name="display_order" value="0" min="0">
                        <small class="text-muted">数字が小さいほど先頭に表示されます（0が最優先）</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" name="add_category" class="btn btn-primary">カテゴリーを追加</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- カテゴリー編集モーダル -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">
                    <i class="fas fa-edit"></i> カテゴリー編集
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="service_categories.php">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">カテゴリー名 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_color" class="form-label">カラー</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" id="edit_color" name="color" value="#4e73df">
                            <input type="text" class="form-control" id="edit_color_hex" value="#4e73df" oninput="document.getElementById('edit_color').value = this.value">
                        </div>
                        <small class="text-muted">カテゴリーの表示色を選択してください</small>
                    </div>
                    <div class="mb-3">
                        <label for="edit_display_order" class="form-label">表示順</label>
                        <input type="number" class="form-control" id="edit_display_order" name="display_order" value="0" min="0">
                        <small class="text-muted">数字が小さいほど先頭に表示されます（0が最優先）</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" name="edit_category" class="btn btn-primary">変更を保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- カテゴリー削除確認モーダル -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCategoryModalLabel">
                    <i class="fas fa-trash"></i> カテゴリー削除の確認
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>カテゴリー「<span id="deleteCategoryName"></span>」を削除してもよろしいですか？</p>
                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> この操作は取り消せません。</p>
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
include 'includes/footer.php';
?>

<script>
// カテゴリー編集モーダルの設定
document.addEventListener('DOMContentLoaded', function() {
    // 編集ボタンのクリックイベント
    const editButtons = document.querySelectorAll('.edit-category-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const categoryId = this.getAttribute('data-id');
            const categoryName = this.getAttribute('data-name');
            const categoryColor = this.getAttribute('data-color');
            const categoryOrder = this.getAttribute('data-order');
            
            document.getElementById('edit_category_id').value = categoryId;
            document.getElementById('edit_name').value = categoryName;
            document.getElementById('edit_color').value = categoryColor;
            document.getElementById('edit_color_hex').value = categoryColor;
            document.getElementById('edit_display_order').value = categoryOrder;
        });
    });
});
</script> 