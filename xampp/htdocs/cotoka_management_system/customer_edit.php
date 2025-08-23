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

// 顧客IDがない場合は顧客一覧へリダイレクト（新規登録の場合を除く）
$is_new = true;
$customer_id = 0;
$customer = null;
$message = '';
$error_message = '';
$salon_id = getCurrentSalonId();

// IDが指定されている場合は既存顧客の編集モード
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $customer_id = (int)$_GET['id'];
    $is_new = false;
    
    // 顧客情報の取得
    try {
        $sql = "
            SELECT 
                c.customer_id,
                c.first_name,
                c.last_name,
                c.phone,
                c.email,
                c.birthday,
                c.gender,
                c.address,
                c.status,
                c.notes
            FROM 
                customers c
            WHERE 
                c.customer_id = :customer_id 
                AND c.salon_id = :salon_id
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(':salon_id', $salon_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            $error_message = "指定された顧客情報が見つかりません。";
            $is_new = true;
            $customer_id = 0;
        }
    } catch (PDOException $e) {
        $error_message = "顧客情報取得エラー: " . $e->getMessage();
    }
}

// フォーム送信時の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POSTデータを取得
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthday = !empty($_POST['birthday']) ? $_POST['birthday'] : null;
    $gender = $_POST['gender'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $notes = trim($_POST['notes'] ?? '');
    
    // バリデーション
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = "名（名前）は必須項目です。";
    }
    
    if (empty($last_name)) {
        $errors[] = "姓（苗字）は必須項目です。";
    }
    
    // メールアドレスの検証（入力されている場合のみ）
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "メールアドレスの形式が正しくありません。";
    }
    
    // エラーがない場合、保存処理を実行
    if (empty($errors)) {
        try {
            // 新規追加の場合
            if ($is_new) {
                $sql = "
                    INSERT INTO customers (
                        salon_id, tenant_id, first_name, last_name, phone, email, 
                        birthday, gender, address, status, notes, created_at, updated_at
                    ) VALUES (
                        :salon_id, :tenant_id, :first_name, :last_name, :phone, :email, 
                        :birthday, :gender, :address, :status, :notes, NOW(), NOW()
                    )
                ";
                
                $stmt = $conn->prepare($sql);
                $tenant_id = getCurrentTenantId();
                $stmt->bindParam(':tenant_id', $tenant_id);
            } 
            // 更新の場合
            else {
                $sql = "
                    UPDATE customers SET 
                        first_name = :first_name,
                        last_name = :last_name,
                        phone = :phone,
                        email = :email,
                        birthday = :birthday,
                        gender = :gender,
                        address = :address,
                        status = :status,
                        notes = :notes,
                        updated_at = NOW()
                    WHERE customer_id = :customer_id AND salon_id = :salon_id
                ";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':customer_id', $customer_id);
            }
            
            // 共通のパラメータバインド
            $stmt->bindParam(':salon_id', $salon_id);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':birthday', $birthday);
            $stmt->bindParam(':gender', $gender);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':notes', $notes);
            
            $stmt->execute();
            
            // 新規登録の場合は、新たに挿入されたIDを取得
            if ($is_new) {
                $customer_id = $conn->lastInsertId();
                $message = "顧客情報が登録されました。";
            } else {
                $message = "顧客情報が更新されました。";
            }
            
            // 詳細ページにリダイレクト
            header("Location: customer_details.php?id=$customer_id&message=" . urlencode($message));
            exit;
            
        } catch (PDOException $e) {
            $error_message = "顧客情報の保存中にエラーが発生しました: " . $e->getMessage();
        }
    } else {
        $error_message = implode('<br>', $errors);
        
        // エラー時にフォームデータを保持
        $customer = [
            'customer_id' => $customer_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'email' => $email,
            'birthday' => $birthday,
            'gender' => $gender,
            'address' => $address,
            'status' => $status,
            'notes' => $notes
        ];
    }
}

// ページ固有のCSS
$page_css = <<<EOT
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="assets/css/customer_manager/customer_manager.css">
EOT;

// ページタイトル
$page_title = $is_new ? "顧客登録" : "顧客編集";

// ヘッダーの読み込み
require_once 'includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row g-3">
        <div class="col-12">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $is_new ? "新規顧客登録" : "顧客情報編集"; ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="customer_manager.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> 顧客一覧に戻る
                    </a>
                    <?php if (!$is_new): ?>
                    <a href="customer_details.php?id=<?php echo $customer_id; ?>" class="btn btn-sm btn-outline-primary ms-2">
                        <i class="fas fa-eye"></i> 詳細表示
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user-edit me-1"></i>
                    顧客情報
                </div>
                <div class="card-body">
                    <form method="post" id="customer-form">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">姓 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($customer['last_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($customer['first_name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">電話番号</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">メールアドレス</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="birthday" class="form-label">生年月日</label>
                                <input type="date" class="form-control" id="birthday" name="birthday" value="<?php echo htmlspecialchars($customer['birthday'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="gender" class="form-label">性別</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">選択してください</option>
                                    <option value="male" <?php echo (isset($customer['gender']) && $customer['gender'] === 'male') ? 'selected' : ''; ?>>男性</option>
                                    <option value="female" <?php echo (isset($customer['gender']) && $customer['gender'] === 'female') ? 'selected' : ''; ?>>女性</option>
                                    <option value="other" <?php echo (isset($customer['gender']) && $customer['gender'] === 'other') ? 'selected' : ''; ?>>その他</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">住所</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($customer['address'] ?? ''); ?>">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="status" class="form-label">ステータス</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo (isset($customer['status']) && $customer['status'] === 'active') ? 'selected' : ''; ?>>アクティブ</option>
                                    <option value="inactive" <?php echo (isset($customer['status']) && $customer['status'] === 'inactive') ? 'selected' : ''; ?>>非アクティブ</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">備考</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($customer['notes'] ?? ''); ?></textarea>
                        </div>
                        <div class="d-flex justify-content-between mt-4">
                            <?php if (!$is_new): ?>
                            <button type="button" class="btn btn-danger" id="delete-customer-btn" data-customer-id="<?php echo $customer_id; ?>">
                                <i class="fas fa-trash"></i> 削除
                            </button>
                            <?php else: ?>
                            <div></div> <!-- 空のdiv要素でスペースを確保 -->
                            <?php endif; ?>
                            <div>
                                <a href="customer_manager.php" class="btn btn-secondary me-2">キャンセル</a>
                                <button type="submit" class="btn btn-primary">保存</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-muted">
                    <div class="row">
                        <div class="col-12 text-center">
                            <small>
                                <i class="fas fa-clock me-1"></i> システム日時：2025年3月26日 <?php echo date('H:i:s'); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 削除確認モーダル -->
<div class="modal fade" id="delete-confirm-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">顧客削除の確認</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <p>この顧客情報を削除してもよろしいですか？</p>
                <p class="text-danger">この操作は元に戻せません。</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <form method="post" action="api/customers/delete_customer.php">
                    <input type="hidden" name="customer_id" id="confirm-delete-id" value="">
                    <input type="hidden" name="salon_id" value="<?php echo $salon_id; ?>">
                    <button type="submit" class="btn btn-danger">削除</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ja.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // 生年月日のFlatpickr初期化
        flatpickr("#birthday", {
            locale: 'ja',
            dateFormat: 'Y-m-d',
            maxDate: new Date(), // 今日以降の日付は選択不可
            allowInput: true
        });
        
        // 削除ボタンのクリックイベント
        const deleteBtn = document.getElementById('delete-customer-btn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                const customerId = this.getAttribute('data-customer-id');
                if (customerId) {
                    document.getElementById('confirm-delete-id').value = customerId;
                    const deleteModal = new bootstrap.Modal(document.getElementById('delete-confirm-modal'));
                    deleteModal.show();
                }
            });
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?> 