<?php
// ユーザー追加ページ
$page_title = '新規ユーザー登録';
require_once 'includes/header.php';
require_once 'classes/User.php';
require_once 'classes/Salon.php';

// 権限チェック
if (!userHasPermission('users', 'create')) {
    setFlashMessage('error', 'このページにアクセスする権限がありません');
    redirect('dashboard.php');
    exit;
}

// ユーザーとサロンのオブジェクト
$userObj = new User($db);
$salonObj = new Salon($db);

// テナントIDの取得
$tenant_id = getCurrentTenantId();

// テナントのサロン一覧を取得
$salons = $salonObj->getAllByTenant($tenant_id);

// フォーム送信処理
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFトークン検証
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = '不正なリクエストです。ページを再読み込みして再度お試しください。';
    } else {
        // 入力データを取得
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $role = sanitize($_POST['role'] ?? '');
        $salon_id = intval($_POST['salon_id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        
        // バリデーション
        if (empty($first_name)) {
            $errors[] = '名を入力してください';
        }
        
        if (empty($last_name)) {
            $errors[] = '姓を入力してください';
        }
        
        if (empty($email)) {
            $errors[] = 'メールアドレスを入力してください';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '有効なメールアドレスを入力してください';
        } elseif ($userObj->emailExists($email)) {
            $errors[] = 'このメールアドレスは既に使用されています';
        }
        
        if (empty($role)) {
            $errors[] = '役割を選択してください';
        }
        
        if (empty($password)) {
            $errors[] = 'パスワードを入力してください';
        } elseif (strlen($password) < 8) {
            $errors[] = 'パスワードは8文字以上で入力してください';
        }
        
        if ($password !== $password_confirm) {
            $errors[] = 'パスワードと確認用パスワードが一致しません';
        }
        
        // エラーがなければ登録処理
        if (empty($errors)) {
            // パスワードをハッシュ化
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // ユーザーデータ
            $user_data = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'password' => $password_hash,
                'role' => $role,
                'tenant_id' => $tenant_id,
                'salon_id' => $salon_id > 0 ? $salon_id : null,
                'status' => 'active'
            ];
            
            // ユーザー登録
            $result = $userObj->create($user_data);
            
            if ($result) {
                // 成功メッセージを設定してリダイレクト
                setFlashMessage('success', 'ユーザーを登録しました');
                redirect('user-management.php');
                exit;
            } else {
                $errors[] = 'ユーザー登録に失敗しました。もう一度お試しください。';
            }
        }
    }
}
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"><?php echo $page_title; ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">ホーム</a></li>
                    <li class="breadcrumb-item"><a href="user-management.php">ユーザー管理</a></li>
                    <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">ユーザー情報</h3>
            </div>
            <div class="card-body">
                <form action="user-add.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $_POST['first_name'] ?? ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">姓 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $_POST['last_name'] ?? ''; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">メールアドレス <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">役割 <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">役割を選択してください</option>
                                    <?php if (isSuperAdmin()): ?>
                                    <option value="tenant_admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'tenant_admin') ? 'selected' : ''; ?>>テナント管理者</option>
                                    <?php endif; ?>
                                    <option value="manager" <?php echo (isset($_POST['role']) && $_POST['role'] === 'manager') ? 'selected' : ''; ?>>マネージャー</option>
                                    <option value="staff" <?php echo (isset($_POST['role']) && $_POST['role'] === 'staff') ? 'selected' : ''; ?>>スタッフ</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="salon_id" class="form-label">所属サロン</label>
                                <select class="form-select" id="salon_id" name="salon_id">
                                    <option value="">サロンを選択してください</option>
                                    <?php foreach ($salons as $salon): ?>
                                    <option value="<?php echo $salon['salon_id']; ?>" <?php echo (isset($_POST['salon_id']) && intval($_POST['salon_id']) === $salon['salon_id']) ? 'selected' : ''; ?>>
                                        <?php echo sanitize($salon['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">パスワード <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <small class="form-text text-muted">8文字以上で入力してください</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">パスワード（確認） <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">登録する</button>
                        <a href="user-management.php" class="btn btn-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
