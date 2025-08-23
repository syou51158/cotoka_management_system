<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';
require_once 'classes/Tenant.php';
require_once 'classes/Salon.php';

// 既にログインしている場合はダッシュボードにリダイレクト
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$errors = [];
$success = false;

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFチェック
    if (!isCsrfTokenValid($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'セキュリティトークンが無効です。もう一度お試しください。');
        redirect('register.php');
    }
    
    // 入力値の取得とバリデーション
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $tenant_id = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;
    $salon_id = isset($_POST['salon_id']) ? (int)$_POST['salon_id'] : null;
    
    // バリデーション
    if (empty($name)) {
        $errors[] = '名前を入力してください。';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '有効なメールアドレスを入力してください。';
    }
    
    if (empty($password)) {
        $errors[] = 'パスワードを入力してください。';
    } elseif (strlen($password) < 8) {
        $errors[] = 'パスワードは8文字以上必要です。';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'パスワードが一致しません。';
    }
    
    if (empty($tenant_id)) {
        $errors[] = '所属する会社/サロンを選択してください。';
    }
    
    if (empty($salon_id)) {
        $errors[] = '勤務先サロンを選択してください。';
    }
    
    // メールアドレスの重複チェック
    $userObj = new User();
    if ($userObj->emailExists($email)) {
        $errors[] = 'このメールアドレスは既に登録されています。';
    }
    
    // エラーがなければユーザー登録処理
    if (empty($errors)) {
        try {
            // ユーザー登録
            $userData = [
                'tenant_id' => $tenant_id,
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => 'staff', // デフォルトはスタッフ権限
                'status' => 'pending' // 承認待ち状態
            ];
            
            $userId = $userObj->register($userData);
            
            // サロンとの関連付け
            $salon = new Salon();
            $salon->addUserToSalon($userId, $salon_id, 'staff');
            
            // 成功メッセージの設定
            $success = true;
            setFlashMessage('success', '登録申請を受け付けました。管理者の承認をお待ちください。');
            
        } catch (Exception $e) {
            $errors[] = '登録中にエラーが発生しました: ' . $e->getMessage();
        }
    }
}

// 利用可能なテナント一覧を取得
$tenantObj = new Tenant();
$tenants = $tenantObj->getAll();

// 最初のテナントを選択
$selected_tenant_id = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : ($tenants[0]['tenant_id'] ?? null);

// 選択されたテナントに属するサロン一覧を取得
$salons = [];
if ($selected_tenant_id) {
    $salonObj = new Salon();
    $salons = $salonObj->getSalonsByTenantId($selected_tenant_id);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザー登録 - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><?php echo APP_NAME; ?> - ユーザー登録</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                登録申請を受け付けました。管理者の承認をお待ちください。<br>
                                承認後、<a href="login.php">ログインページ</a>からログインできるようになります。
                            </div>
                        <?php else: ?>
                            <?php displayFlashMessages(); ?>
                            
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo $error; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                
                                <div class="mb-4">
                                    <h5>基本情報</h5>
                                    <hr>
                                    <div class="mb-3">
                                        <label for="name" class="form-label">お名前 <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" required value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">メールアドレス <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                                        <small class="text-muted">このメールアドレスでログインします</small>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h5>所属情報</h5>
                                    <hr>
                                    <div class="mb-3">
                                        <label for="tenant_id" class="form-label">所属する会社/サロン <span class="text-danger">*</span></label>
                                        <select class="form-select" id="tenant_id" name="tenant_id" required>
                                            <option value="">-- 選択してください --</option>
                                            <?php foreach ($tenants as $tenant): ?>
                                                <option value="<?php echo $tenant['tenant_id']; ?>" <?php echo ($selected_tenant_id == $tenant['tenant_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($tenant['company_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="salon_id" class="form-label">勤務先サロン <span class="text-danger">*</span></label>
                                        <select class="form-select" id="salon_id" name="salon_id" required <?php echo empty($salons) ? 'disabled' : ''; ?>>
                                            <option value="">-- 先に会社/サロンを選択してください --</option>
                                            <?php foreach ($salons as $salon): ?>
                                                <option value="<?php echo $salon['salon_id']; ?>">
                                                    <?php echo htmlspecialchars($salon['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h5>パスワード設定</h5>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="password" class="form-label">パスワード <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                            <small class="text-muted">8文字以上で入力してください</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">パスワード (確認) <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">登録する</button>
                                    <a href="login.php" class="btn btn-link">既にアカウントをお持ちの方はこちら</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 会社/サロン選択時にサロン一覧を動的に更新
        const tenantSelect = document.getElementById('tenant_id');
        const salonSelect = document.getElementById('salon_id');
        
        if (tenantSelect && salonSelect) {
            tenantSelect.addEventListener('change', function() {
                const tenantId = this.value;
                
                if (tenantId) {
                    // AJAX リクエストでサロン一覧を取得
                    fetch('ajax/get_salons.php?tenant_id=' + tenantId)
                        .then(response => response.json())
                        .then(data => {
                            // サロン選択肢を更新
                            salonSelect.innerHTML = '';
                            
                            if (data.length === 0) {
                                const option = document.createElement('option');
                                option.value = '';
                                option.textContent = '-- このテナントにはサロンがありません --';
                                salonSelect.appendChild(option);
                                salonSelect.disabled = true;
                            } else {
                                const defaultOption = document.createElement('option');
                                defaultOption.value = '';
                                defaultOption.textContent = '-- サロンを選択してください --';
                                salonSelect.appendChild(defaultOption);
                                
                                data.forEach(salon => {
                                    const option = document.createElement('option');
                                    option.value = salon.salon_id;
                                    option.textContent = salon.salon_name;
                                    salonSelect.appendChild(option);
                                });
                                
                                salonSelect.disabled = false;
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching salons:', error);
                        });
                } else {
                    // テナントが選択されていない場合
                    salonSelect.innerHTML = '<option value="">-- 先に会社/サロンを選択してください --</option>';
                    salonSelect.disabled = true;
                }
            });
        }
    });
    </script>
</body>
</html> 