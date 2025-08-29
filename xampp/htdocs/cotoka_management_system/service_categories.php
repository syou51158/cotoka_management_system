<?php
// エラー表示を有効化
ini_set('display_errors', 1);
error_reporting(E_ALL);

// アクセスログ
error_log("カテゴリー管理ページにアクセスしました: " . date('Y-m-d H:i:s'), 3, "logs/app_debug.log");

// セッション開始（もし開始されていない場合）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 必要最小限の設定
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'includes/functions.php';

// ページ設定
$page_title = "サービスカテゴリー管理";
$custom_css = ["assets/css/service_categories.css"];
$custom_js = ["assets/js/service_categories.js"];

// デバッグ情報
$debug = [];
$debug[] = "スクリプト開始: " . date('Y-m-d H:i:s');

// データベース接続状態のテスト（Supabase対応）
$db_connection_test = "Supabaseデータベース接続テスト実行中...";
try {
    $test_db = new Database();
    $test_conn = $test_db->getConnection();
    if ($test_conn) {
        $db_connection_test .= "Supabase接続成功";
        $debug[] = $db_connection_test;
    } else {
        $db_connection_test .= "Supabase接続失敗";
        $debug[] = $db_connection_test;
    }
} catch (Exception $e) {
    $db_connection_test .= "例外発生: " . $e->getMessage();
    $debug[] = $db_connection_test;
}

// サロンID取得 - セッション未設定の場合は強制的に1を設定
if (!isset($_SESSION['salon_id'])) {
    $_SESSION['salon_id'] = 1;
    $debug[] = "警告: salon_idがセッションに存在しないため、1を強制設定しました";
}

if (!isset($_SESSION['tenant_id'])) {
    $_SESSION['tenant_id'] = 1;
    $debug[] = "警告: tenant_idがセッションに存在しないため、1を強制設定しました";
}

$salon_id = $_SESSION['salon_id'];
$tenant_id = $_SESSION['tenant_id'];
$debug[] = "使用するsalon_id: $salon_id, tenant_id: $tenant_id";

// リクエスト情報をデバッグログに記録
$debug[] = "リクエストメソッド: " . $_SERVER['REQUEST_METHOD'];
$debug[] = "GET変数: " . json_encode($_GET);
$debug[] = "POST変数: " . json_encode($_POST);

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
    $debug[] = "データベース接続成功 (PDO)";
    
    // PDOの設定を確認
    $attributes = [
        "ATTR_ERRMODE" => $conn->getAttribute(PDO::ATTR_ERRMODE),
        "ATTR_EMULATE_PREPARES" => $conn->getAttribute(PDO::ATTR_EMULATE_PREPARES)
    ];
    $debug[] = "PDO設定: " . json_encode($attributes);
    
    // サロンIDのデータが存在するか確認
    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM service_categories WHERE salon_id = :salon_id");
    $check_stmt->bindParam(':salon_id', $salon_id);
    $check_stmt->execute();
    $category_count = $check_stmt->fetchColumn();
    $debug[] = "サロンID $salon_id のカテゴリー数: $category_count";
    
} catch (PDOException $e) {
    echo '<div class="alert alert-danger mt-3">データベース接続エラー: ' . $e->getMessage() . '</div>';
    $debug[] = "データベース接続エラー (PDO): " . $e->getMessage();
    exit;
}

// エラーと成功メッセージ
$errors = [];
$success_message = '';

// ヘッダー読み込み
require_once 'includes/header.php';

// カテゴリー追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $color = trim($_POST['color']);
    $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    
    $debug[] = "カテゴリー追加処理: name=$name, color=$color, order=$display_order";
    
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
            $debug[] = "カテゴリー重複チェックエラー: " . $e->getMessage();
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
                $success_message = "カテゴリーが正常に追加されました。";
                $debug[] = "カテゴリー追加成功: ID=" . $conn->lastInsertId();
            } else {
                $errors[] = "カテゴリーの追加に失敗しました。";
                $debug[] = "カテゴリー追加失敗: " . json_encode($stmt->errorInfo());
            }
        } catch (PDOException $e) {
            $errors[] = "データベースエラー: " . $e->getMessage();
            $debug[] = "カテゴリー追加エラー: " . $e->getMessage();
        }
    }
}

// カテゴリー編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $category_id = (int)$_POST['category_id'];
    $name = trim($_POST['name']);
    $color = trim($_POST['color']);
    $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    
    $debug[] = "カテゴリー編集処理: id=$category_id, name=$name, color=$color, order=$display_order";
    $debug[] = "編集時の全POSTデータ: " . json_encode($_POST);
    
    // 入力値の検証
    if (empty($name)) {
        $errors[] = "カテゴリー名を入力してください。";
    }
    
    if (empty($color)) {
        $color = '#4e73df'; // デフォルトカラー
    }
    
    // カテゴリーが存在するか確認
    try {
        $check_stmt = $conn->prepare("SELECT * FROM service_categories WHERE category_id = :category_id AND salon_id = :salon_id");
        $check_stmt->bindParam(':category_id', $category_id);
        $check_stmt->bindParam(':salon_id', $salon_id);
        $check_stmt->execute();
        
        $category = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$category) {
            $errors[] = "指定されたカテゴリーが見つかりません。ID: $category_id";
            $debug[] = "カテゴリーが見つかりません: ID=$category_id, salon_id=$salon_id";
        } else {
            $debug[] = "編集対象カテゴリー: " . json_encode($category);
        }
    } catch (PDOException $e) {
        $errors[] = "データベースエラー: " . $e->getMessage();
        $debug[] = "カテゴリー存在確認エラー: " . $e->getMessage();
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
            $debug[] = "カテゴリー編集重複チェックエラー: " . $e->getMessage();
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
            
            $result = $stmt->execute();
            $debug[] = "UPDATE実行結果: " . ($result ? "成功" : "失敗");
            $debug[] = "影響を受けた行数: " . $stmt->rowCount();
            
            if ($result) {
                $success_message = "カテゴリーが正常に更新されました。";
                $debug[] = "カテゴリー更新成功: ID=$category_id, 影響行数=" . $stmt->rowCount();
                
                // 更新後のデータを検証
                $verify_stmt = $conn->prepare("SELECT * FROM service_categories WHERE category_id = :category_id");
                $verify_stmt->bindParam(':category_id', $category_id);
                $verify_stmt->execute();
                $updated_category = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                $debug[] = "更新後のデータ: " . json_encode($updated_category);
            } else {
                $errors[] = "カテゴリーの更新に失敗しました。";
                $debug[] = "カテゴリー更新失敗: " . json_encode($stmt->errorInfo());
            }
        } catch (PDOException $e) {
            $errors[] = "データベースエラー: " . $e->getMessage();
            $debug[] = "カテゴリー更新エラー: " . $e->getMessage();
        }
    }
}

// カテゴリー削除処理
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $category_id = (int)$_GET['id'];
    
    $debug[] = "カテゴリー削除処理: id=$category_id";
    $debug[] = "削除リクエスト: " . json_encode($_GET);
    
    // カテゴリーが存在するか確認
    try {
        $check_stmt = $conn->prepare("SELECT * FROM service_categories WHERE category_id = :category_id AND salon_id = :salon_id");
        $check_stmt->bindParam(':category_id', $category_id);
        $check_stmt->bindParam(':salon_id', $salon_id);
        $check_stmt->execute();
        
        $category = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$category) {
            $errors[] = "指定されたカテゴリーが見つかりません。ID: $category_id";
            $debug[] = "削除対象カテゴリーが見つかりません: ID=$category_id, salon_id=$salon_id";
        } else {
            $debug[] = "削除対象カテゴリー: " . json_encode($category);
        }
    } catch (PDOException $e) {
        $errors[] = "データベースエラー: " . $e->getMessage();
        $debug[] = "カテゴリー存在確認エラー: " . $e->getMessage();
    }
    
    // このカテゴリーを使用しているサービスがあるか確認
    if (empty($errors)) {
        try {
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM services WHERE salon_id = :salon_id AND category = :category_name");
            $check_stmt->bindParam(':salon_id', $salon_id);
            $check_stmt->bindParam(':category_name', $category['name']);
            $check_stmt->execute();
            
            $service_count = $check_stmt->fetchColumn();
            $debug[] = "使用中サービス数: $service_count";
            
            if ($service_count > 0) {
                $errors[] = "このカテゴリーは {$service_count} 件のサービスで使用されているため削除できません。";
            } else {
                // カテゴリーを削除
                $stmt = $conn->prepare("DELETE FROM service_categories WHERE category_id = :category_id AND salon_id = :salon_id");
                $stmt->bindParam(':category_id', $category_id);
                $stmt->bindParam(':salon_id', $salon_id);
                
                $result = $stmt->execute();
                $debug[] = "DELETE実行結果: " . ($result ? "成功" : "失敗");
                $debug[] = "影響を受けた行数: " . $stmt->rowCount();
                
                if ($result && $stmt->rowCount() > 0) {
                    $success_message = "カテゴリーが正常に削除されました。";
                    $debug[] = "カテゴリー削除成功: ID=$category_id";
                } else {
                    $errors[] = "カテゴリーの削除に失敗しました。";
                    $debug[] = "カテゴリー削除失敗: " . json_encode($stmt->errorInfo());
                }
            }
        } catch (PDOException $e) {
            $errors[] = "データベースエラー: " . $e->getMessage();
            $debug[] = "カテゴリー削除エラー: " . $e->getMessage();
        }
    }
}

// デバッグ情報をログに記録
error_log("カテゴリー管理ページデバッグ情報: " . implode(" | ", $debug), 3, "logs/app_debug.log");

// カテゴリー一覧を取得
try {
    $stmt = $conn->prepare("SELECT * FROM service_categories WHERE salon_id = :salon_id ORDER BY display_order ASC, name ASC");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug[] = "カテゴリー取得成功: " . count($categories) . "件";
} catch (PDOException $e) {
    $errors[] = "データベースエラー: " . $e->getMessage();
    $categories = [];
    $debug[] = "カテゴリー取得エラー: " . $e->getMessage();
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">
        <i class="fas fa-tags"></i> サービスカテゴリー管理
    </h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="dashboard.php">ダッシュボード</a></li>
        <li class="breadcrumb-item"><a href="services.php">サービス管理</a></li>
        <li class="breadcrumb-item active">カテゴリー管理</li>
    </ol>
    
    <!-- 成功・エラーメッセージ表示 -->
    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
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

    <!-- 簡易コンテンツ -->
    <div class="card mb-4">
        <div class="card-body">
            <h2>カテゴリー管理ページへようこそ</h2>
            <p>このページでサービスカテゴリーを管理できます。</p>
            <hr>
            <div class="d-flex justify-content-between mb-3">
                <a href="services.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> サービス管理に戻る
                </a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus me-1"></i> 新規カテゴリー追加
                </button>
            </div>
            
            <!-- カテゴリーリスト -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>カテゴリー名</th>
                            <th>色</th>
                            <th>表示順</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (count($categories) > 0) {
                            foreach ($categories as $category) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($category['category_id']) . '</td>';
                                echo '<td>' . htmlspecialchars($category['name']) . '</td>';
                                echo '<td><span class="color-sample" style="background-color: ' . htmlspecialchars($category['color']) . '"></span> ' . htmlspecialchars($category['color']) . '</td>';
                                echo '<td>' . htmlspecialchars($category['display_order']) . '</td>';
                                echo '<td>';
                                echo '<button type="button" class="btn btn-sm btn-primary me-1 edit-category-btn" ';
                                echo 'data-bs-toggle="modal" ';
                                echo 'data-bs-target="#editCategoryModal" ';
                                echo 'data-id="' . htmlspecialchars($category['category_id']) . '" ';
                                echo 'data-name="' . htmlspecialchars($category['name']) . '" ';
                                echo 'data-color="' . htmlspecialchars($category['color']) . '" ';
                                echo 'data-order="' . htmlspecialchars($category['display_order']) . '">';
                                echo '<i class="fas fa-edit"></i></button>';
                                
                                // サービスで使用されているか確認
                                $service_stmt = $conn->prepare("SELECT COUNT(*) FROM services WHERE salon_id = :salon_id AND category = :category_name");
                                $service_stmt->bindParam(':salon_id', $salon_id);
                                $service_stmt->bindParam(':category_name', $category['name']);
                                $service_stmt->execute();
                                $service_count = $service_stmt->fetchColumn();
                                
                                if ($service_count > 0) {
                                    echo '<button class="btn btn-sm btn-danger" disabled title="このカテゴリーは' . $service_count . '件のサービスで使用されているため削除できません">';
                                    echo '<i class="fas fa-trash"></i></button>';
                                } else {
                                    echo '<button type="button" class="btn btn-sm btn-danger delete-category-btn" ';
                                    echo 'data-bs-toggle="modal" ';
                                    echo 'data-bs-target="#deleteCategoryModal" ';
                                    echo 'data-id="' . htmlspecialchars($category['category_id']) . '" ';
                                    echo 'data-name="' . htmlspecialchars($category['name']) . '">';
                                    echo '<i class="fas fa-trash"></i></button>';
                                }
                                echo '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="5" class="text-center">カテゴリーがありません</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.color-sample {
    display: inline-block;
    width: 20px;
    height: 20px;
    border-radius: 3px;
    margin-right: 5px;
    vertical-align: middle;
    border: 1px solid rgba(0, 0, 0, 0.1);
}
</style>

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
            <form method="post" action="service_categories.php" id="editCategoryForm">
                <input type="hidden" name="category_id" id="edit_category_id" value="">
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
                <p>カテゴリー「<span id="delete_category_name"></span>」を削除してもよろしいですか？</p>
                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> この操作は取り消せません。</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <a href="#" id="confirm_delete_btn" class="btn btn-danger">削除する</a>
            </div>
        </div>
    </div>
</div>

<!-- デバッグ情報 -->
<?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
<div class="card mt-4">
    <div class="card-header bg-dark text-white">
        <i class="fas fa-bug"></i> デバッグ情報
    </div>
    <div class="card-body">
        <h5>データベース接続情報</h5>
        <pre><?php 
            echo "ホスト: " . DB_HOST . "\n";
            echo "データベース: " . DB_NAME . "\n";
            echo "サロンID: " . $salon_id . "\n";
            echo "テナントID: " . $tenant_id . "\n";
        ?></pre>
        
        <h5>処理ログ</h5>
        <pre><?php echo implode("\n", $debug); ?></pre>
        
        <h5>最新の SQL エラー</h5>
        <pre><?php 
        if ($conn) {
            print_r($conn->errorInfo());
        }
        ?></pre>
        
        <h5>リクエスト情報</h5>
        <pre><?php 
            echo "GET: " . json_encode($_GET, JSON_PRETTY_PRINT) . "\n";
            echo "POST: " . json_encode($_POST, JSON_PRETTY_PRINT) . "\n";
            echo "SESSION: " . json_encode($_SESSION, JSON_PRETTY_PRINT) . "\n";
        ?></pre>
    </div>
</div>
<?php endif; ?>

<!-- Bootstrap JS と依存ライブラリの読み込み -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- カテゴリー管理用JavaScript -->
<script src="assets/js/service_categories.js"></script>

<?php require_once 'includes/footer.php'; ?>