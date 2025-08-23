<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/Tenant.php';
require_once 'classes/User.php';

// マルチテナント機能が無効の場合はアクセス不可
if (!MULTI_TENANT_ENABLED) {
    setFlashMessage('error', 'マルチテナント機能が有効になっていません。');
    redirect('login.php');
}

$errors = [];
$success = false;

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFチェック
    if (!isCsrfTokenValid($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'セキュリティトークンが無効です。もう一度お試しください。');
        redirect('register_tenant.php');
    }
    
    // 入力値の取得とバリデーション
    $companyName = sanitizeInput($_POST['company_name'] ?? '');
    $ownerName = sanitizeInput($_POST['owner_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $salonName = sanitizeInput($_POST['salon_name'] ?? '');
    
    // バリデーション
    if (empty($companyName)) {
        $errors[] = '会社名/屋号を入力してください。';
    }
    
    if (empty($ownerName)) {
        $errors[] = 'オーナー名を入力してください。';
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
    
    if (empty($salonName)) {
        $errors[] = 'サロン名を入力してください。';
    }
    
    // メールアドレスの重複チェック
    $tenantObj = new Tenant();
    $existingTenant = $tenantObj->getByEmail($email);
    
    if ($existingTenant) {
        $errors[] = 'このメールアドレスは既に登録されています。';
    }
    
    // エラーがなければテナント登録処理
    if (empty($errors)) {
        try {
            // トランザクション開始
            Database::getInstance()->beginTransaction();
            
            // テナント登録
            $tenantData = [
                'company_name' => $companyName,
                'owner_name' => $ownerName,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'default_salon_name' => $salonName
            ];
            
            $tenantId = $tenantObj->add($tenantData);
            
            // 管理者ユーザー登録
            $userObj = new User();
            $userData = [
                'tenant_id' => $tenantId,
                'name' => $ownerName,
                'email' => $email,
                'password' => $password,
                'role' => 'admin',
                'status' => 'active'
            ];
            
            $userId = $userObj->register($userData);
            
            // サロンのデフォルト管理者に設定
            $salon = new Salon();
            $salonId = $salon->getFirstByTenantId($tenantId)['salon_id'];
            $salon->addUserToSalon($userId, $salonId, 'admin');
            
            Database::getInstance()->commit();
            
            // 成功メッセージの設定
            $success = true;
            setFlashMessage('success', '登録が完了しました。ログインページからログインしてください。');
            
        } catch (Exception $e) {
            Database::getInstance()->rollback();
            $errors[] = '登録中にエラーが発生しました: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>サロンオーナー登録 - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><?php echo APP_NAME; ?> - サロンオーナー登録</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                登録が完了しました。<a href="login.php">ログインページ</a>からログインしてください。
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
                                    <h5>会社情報</h5>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="company_name" class="form-label">会社名/屋号 <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="company_name" name="company_name" required value="<?php echo isset($companyName) ? htmlspecialchars($companyName) : ''; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="owner_name" class="form-label">オーナー名 <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="owner_name" name="owner_name" required value="<?php echo isset($ownerName) ? htmlspecialchars($ownerName) : ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">メールアドレス <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                                            <small class="text-muted">このメールアドレスでログインします</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="phone" class="form-label">電話番号</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">住所</label>
                                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo isset($address) ? htmlspecialchars($address) : ''; ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h5>ログイン情報</h5>
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
                                
                                <div class="mb-4">
                                    <h5>サロン情報</h5>
                                    <hr>
                                    <div class="mb-3">
                                        <label for="salon_name" class="form-label">サロン名 <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="salon_name" name="salon_name" required value="<?php echo isset($salonName) ? htmlspecialchars($salonName) : ''; ?>">
                                        <small class="text-muted">最初に作成するサロン名を入力してください。後で追加することもできます。</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                        <label class="form-check-label" for="terms">
                                            <a href="#" target="_blank">利用規約</a>に同意します
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">登録する</button>
                                </div>
                            </form>
                            
                            <div class="mt-4 text-center">
                                <p>すでにアカウントをお持ちですか？ <a href="login.php">ログイン</a></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html> 