<?php
// ユーザー編集ページ
$page_title = 'ユーザー編集';
require_once 'includes/header.php';
require_once 'classes/User.php';
require_once 'classes/Salon.php';

// 権限チェック
if (!userHasPermission('users', 'edit')) {
    setFlashMessage('error', 'このページにアクセスする権限がありません');
    redirect('dashboard.php');
    exit;
}

// パラメータ取得
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($user_id <= 0) {
    setFlashMessage('error', '無効なユーザーIDです');
    redirect('user-management.php');
    exit;
}

// ユーザーとサロンのオブジェクト
$userObj = new User($db);
$salonObj = new Salon($db);

// ユーザー情報取得
$user = $userObj->getById($user_id);
if (!$user) {
    setFlashMessage('error', 'ユーザーが見つかりません');
    redirect('user-management.php');
    exit;
}

// テナントIDの取得
$tenant_id = getCurrentTenantId();

// ユーザーがスーパー管理者でない場合、同じテナント内のユーザーのみ編集可能
if (!isSuperAdmin() && $user['tenant_id'] != $tenant_id) {
    setFlashMessage('error', 'このユーザーを編集する権限がありません');
    redirect('user-management.php');
    exit;
}

// テナントのサロン一覧を取得
$salons = $salonObj->getAllByTenant($user['tenant_id']);

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
        $status = sanitize($_POST['status'] ?? '');
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
        } elseif ($email !== $user['email'] && $userObj->emailExists($email)) {
            $errors[] = 'このメールアドレスは既に使用されています';
        }
        
        if (empty($role)) {
            $errors[] = '役割を選択してください';
        }
        
        if (empty($status)) {
            $errors[] = 'ステータスを選択してください';
        }
        
        // パスワード変更がある場合のみチェック
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $errors[] = 'パスワードは8文字以上で入力してください';
            }
            
            if ($password !== $password_confirm) {
                $errors[] = 'パスワードと確認用パスワードが一致しません';
            }
        }
        
        // エラーがなければ更新処理
        if (empty($errors)) {
            // ユーザーデータ
            $user_data = [
                'user_id' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'role' => $role,
                'salon_id' => $salon_id > 0 ? $salon_id : null,
                'status' => $status
            ];
            
            // パスワード変更がある場合のみ追加
            if (!empty($password)) {
                $user_data['password'] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            // ユーザー更新
            $result = $userObj->updateUser($user_data);
            
            if ($result) {
                // 成功メッセージを設定してリダイレクト
                setFlashMessage('success', 'ユーザー情報を更新しました');
                redirect('user-management.php');
                exit;
            } else {
                $errors[] = 'ユーザー情報の更新に失敗しました。もう一度お試しください。';
            }
        }
    }
} else {
    // GETリクエスト時は既存のデータをフォームに設定
    $_POST = $user;
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
                <form action="user-edit.php?id=<?php echo $user_id; ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">姓 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">メールアドレス <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
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
                        <div class="col-md-4">
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
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label">ステータス <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] === 'active') ? 'selected' : ''; ?>>有効</option>
                                    <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'selected' : ''; ?>>無効</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">パスワード</label>
                                <input type="password" class="form-control" id="password" name="password">
                                <small class="form-text text-muted">変更する場合のみ入力してください（8文字以上）</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">パスワード（確認）</label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">更新する</button>
                        <a href="user-management.php" class="btn btn-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
