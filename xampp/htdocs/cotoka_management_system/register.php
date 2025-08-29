<?php
require_once 'config/config.php';
require_once 'includes/csrf.php';

// セッション開始
session_start();

// CSRFトークンの生成
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success_message = '';

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFトークンの検証
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = '不正なリクエストです。';
    } else {
        // 入力値の取得とバリデーション
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $tenant_id = (int)($_POST['tenant_id'] ?? 0);
        $salon_id = (int)($_POST['salon_id'] ?? 0);
        
        // バリデーション
        if (empty($name)) {
            $errors[] = '名前を入力してください。';
        }
        
        if (empty($email)) {
            $errors[] = 'メールアドレスを入力してください。';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '有効なメールアドレスを入力してください。';
        }
        
        if (empty($password)) {
            $errors[] = 'パスワードを入力してください。';
        } elseif (strlen($password) < 6) {
            $errors[] = 'パスワードは6文字以上で入力してください。';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'パスワードが一致しません。';
        }
        
        if ($tenant_id <= 0) {
            $errors[] = 'テナントを選択してください。';
        }
        
        if ($salon_id <= 0) {
            $errors[] = 'サロンを選択してください。';
        }
        
        // ユーザー登録処理
        if (empty($errors)) {
            try {
                // Supabase Auth APIでユーザー登録
                $authData = [
                    'email' => $email,
                    'password' => $password,
                    'data' => [
                        'name' => $name
                    ]
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, SUPABASE_URL . '/auth/v1/signup');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($authData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'apikey: ' . SUPABASE_ANON_KEY
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $authResult = json_decode($response, true);
                
                // デバッグ情報を追加
                error_log("Supabase Auth Response - HTTP Code: $httpCode");
                error_log("Supabase Auth Response - Body: $response");
                
                if ($httpCode === 200 && isset($authResult['user'])) {
                    // 成功メッセージの設定
                    $_SESSION['success_message'] = 'ユーザー登録が完了しました。ログインしてください。';
                    header('Location: login.php');
                    exit;
                } else {
                    // Supabase認証エラーの処理
                    if (isset($authResult['error'])) {
                        if (strpos($authResult['error']['message'], 'already registered') !== false) {
                            $errors[] = 'このメールアドレスは既に登録されています。';
                        } else {
                            $errors[] = 'ユーザー登録に失敗しました: ' . $authResult['error']['message'];
                        }
                    } else {
                        $errors[] = "ユーザー登録に失敗しました。HTTPコード: $httpCode, レスポンス: $response";
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'ユーザー登録中にエラーが発生しました: ' . $e->getMessage();
            }
        }
        
        // 新しいCSRFトークンを生成
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// 利用可能なテナント一覧を取得（固定値）
$tenants = [
    ['tenant_id' => 1, 'company_name' => 'Trend Company株式会社']
];

// 最初のテナントを選択
$selected_tenant_id = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 1;

// 選択されたテナントに属するサロン一覧を取得（固定値）
$salons = [
    ['salon_id' => 1, 'name' => 'Cotoka美容室 渋谷店']
];
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
                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success">
                                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                            </div>
                        <?php else: ?>
                            
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
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="mb-4">
                                    <h5>基本情報</h5>
                                    <hr>
                                    <div class="mb-3">
                                        <label for="name" class="form-label">お名前 <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" required value="<?php echo isset($name) ? htmlspecialchars($name, ENT_QUOTES, 'UTF-8') : ''; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">メールアドレス <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : ''; ?>">
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
                                                    <?php echo htmlspecialchars($tenant['company_name'], ENT_QUOTES, 'UTF-8'); ?>
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
                                                    <?php echo htmlspecialchars($salon['name'], ENT_QUOTES, 'UTF-8'); ?>
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
                                            <input type="password" class="form-control" id="password" name="password" required minlength="6">
                                            <small class="text-muted">6文字以上で入力してください</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">パスワード (確認) <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
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