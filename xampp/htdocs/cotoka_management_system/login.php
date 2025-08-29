<?php
// エラー表示を最大限に有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// エラーログの設定
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error.log');

// デバッグ情報
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_log('ログインページが読み込まれました');
}

// 以下は既存のコード
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';
require_once 'classes/Salon.php';
require_once 'classes/Tenant.php';

// ログイン済みの場合はダッシュボードにリダイレクト
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// データベース接続の初期化
try {
    $db = new Database();
} catch (Exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    $db = null;
}

// Remember Meトークンによる自動ログイン（refresh_token使用）
if (!isLoggedIn() && isset($_COOKIE['refresh_token'])) {
    $refresh_token = $_COOKIE['refresh_token'];
    
    $supabase_url = defined('SUPABASE_URL') ? SUPABASE_URL : '';
    $supabase_key = defined('SUPABASE_ANON_KEY') ? SUPABASE_ANON_KEY : '';
    
    if ($supabase_url && $supabase_key) {
        $refresh_url = rtrim($supabase_url, '/') . '/auth/v1/token?grant_type=refresh_token';
        $data = [
            'refresh_token' => $refresh_token
        ];
        
        $ch = curl_init($refresh_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $supabase_key
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $auth_data = json_decode($response, true);
        
        if ($http_code === 200 && isset($auth_data['user'])) {
            $supabase_user = $auth_data['user'];
            
            // Supabase UIDからローカルユーザー情報を取得
            $user_result = getUserBySupabaseUid($supabase_user['id']);
            
            if ($user_result['success'] && !empty($user_result['data'])) {
                $userData = $user_result['data'][0];
                
                // セッションにユーザー情報を設定
                $_SESSION['user_id'] = $userData['id'];
                $_SESSION['user_unique_id'] = $userData['user_id'];
                $_SESSION['user_email'] = $userData['email'];
                $_SESSION['user_name'] = $userData['name'];
                $_SESSION['role'] = $userData['role_name'];
                $_SESSION['tenant_id'] = $userData['tenant_id'];
                $_SESSION['supabase_uid'] = $supabase_user['id'];
                $_SESSION['access_token'] = $auth_data['access_token'];
                $_SESSION['refresh_token'] = $auth_data['refresh_token'];
                
                // テナント情報を取得（getUserBySupabaseUidで既に取得済み）
                $_SESSION['tenant_name'] = $userData['tenant_name'];
                
                // アクセス可能なサロンを取得
                $salonsRpc = supabaseRpcCall('user_accessible_salons', ['p_user_id' => (int)$userData['id']]);
                if ($salonsRpc['success'] && !empty($salonsRpc['data'])) {
                    $_SESSION['accessible_salons'] = $salonsRpc['data'];
                    $_SESSION['salon_id'] = $salonsRpc['data'][0]['salon_id'];
                    $_SESSION['salon_name'] = $salonsRpc['data'][0]['salon_name'];
                }
                
                // Remember Meトークンの設定（必要に応じて）
                if ($rememberMe) {
                    // Supabaseのrefresh_tokenを使用
                    setcookie('refresh_token', $auth_data['refresh_token'], time() + (86400 * 30), '/'); // 30日間
                }
                
                // アクティビティログ記録
                logSystemActivity('ログイン', 'user', $_SESSION['user_id'], 'ユーザーがログインしました');
                
                // 前回のページが保存されている場合はそこにリダイレクト
                $redirectTo = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                unset($_SESSION['redirect_after_login']);
                redirect($redirectTo);
            }
        }
    }
}

$errors = [];

// パスワードリセット成功メッセージの確認
$resetSuccess = isset($_GET['reset']) && $_GET['reset'] === 'success';

// 登録完了のメッセージがあれば表示
$registrationSuccess = isset($_SESSION['registration_success']) && $_SESSION['registration_success'];
if ($registrationSuccess) {
    unset($_SESSION['registration_success']);
}

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = sanitize($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    // CSRFトークン検証（デバッグ用に一時的に無効化）
    // if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    //     $errors[] = '不正なリクエストです。ページを再読み込みして再度お試しください。';
    // }
    
    // 入力値の検証
    if (empty($identifier)) {
        $errors[] = 'ユーザーIDまたはメールアドレスを入力してください。';
    }
    
    if (empty($password)) {
        $errors[] = 'パスワードを入力してください。';
    }
    
    // ログイン試行回数をチェック
    if (empty($errors) && !checkLoginAttempts($identifier, $db)) {
        $errors[] = 'ログイン試行回数が上限に達しました。しばらく時間をおいてから再度お試しください。';
    }
    
    // エラーがなければログイン処理
    if (empty($errors)) {
        try {
            // Supabase標準認証APIを使用
            $supabase_url = defined('SUPABASE_URL') ? SUPABASE_URL : '';
            $supabase_key = defined('SUPABASE_ANON_KEY') ? SUPABASE_ANON_KEY : '';
            
            if (!$supabase_url || !$supabase_key) {
                throw new Exception('Supabase設定が不足しています。');
            }
            
            $auth_url = rtrim($supabase_url, '/') . '/auth/v1/token?grant_type=password';
            $data = [
                'email' => $identifier,
                'password' => $password
            ];
            
            // デバッグ: 送信データをログ出力
            error_log("Login Debug - Sending data: " . json_encode($data));
            error_log("Login Debug - Auth URL: " . $auth_url);
            error_log("Login Debug - Supabase Key: " . substr($supabase_key, 0, 20) . "...");
            
            $ch = curl_init($auth_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'apikey: ' . $supabase_key
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // Supabaseの認証APIにリクエスト
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // デバッグログを強化
            error_log('[Login Debug] Supabase Auth Request Body: ' . json_encode($data));
            error_log('[Login Debug] Supabase Auth Response Code: ' . $http_code);
            error_log('[Login Debug] Supabase Auth Response Body: ' . $response);

            if ($http_code == 200) {
                $supabase_user_data = json_decode($response, true);
                
                if (json_last_error() === JSON_ERROR_NONE && isset($supabase_user_data['user'])) {
                    $supabase_user = $supabase_user_data['user'];
                    
                    // デバッグログを追加
                    error_log('[Login Debug] Supabase User ID: ' . $supabase_user['id']);

                    // Database接続チェック
                    if ($db === null) {
                        error_log("[Login Debug] Database connection is null");
                        $errors[] = 'データベース接続エラーが発生しました。管理者にお問い合わせください。';
                    } else {
                        // Supabase UIDからローカルユーザー情報を取得
                        $user = new User($db);
                        $userData = $user->findBySupabaseId($supabase_user['id']);
                        
                        // デバッグログを追加
                        error_log('[Login Debug] Local User Data Found: ' . json_encode($userData));
                    
                        if ($userData) {
                            // セッションにユーザー情報を設定
                            $_SESSION['user_id'] = $userData['id'];
                            $_SESSION['user_unique_id'] = $userData['id'];
                            $_SESSION['user_email'] = $userData['email'];
                            $_SESSION['user_name'] = $userData['name'];
                            $_SESSION['role'] = $userData['role_name'];
                            $_SESSION['tenant_id'] = $userData['tenant_id'];
                            $_SESSION['supabase_uid'] = $supabase_user['id'];
                            $_SESSION['access_token'] = $supabase_user_data['access_token'];
                            $_SESSION['refresh_token'] = $supabase_user_data['refresh_token'];
                        
                            // テナント情報は別途取得する必要がある場合は後で追加
                            $_SESSION['tenant_name'] = '';
                            
                            // アクセス可能なサロンを取得
                            $accessibleSalons = $user->getAccessibleSalons($userData['id']);
                            if (!empty($accessibleSalons)) {
                                $_SESSION['accessible_salons'] = $accessibleSalons;
                                $_SESSION['salon_id'] = $accessibleSalons[0]['salon_id'];
                                $_SESSION['salon_name'] = $accessibleSalons[0]['name'];
                            }
                            
                            // Remember Meトークンの設定（必要に応じて）
                            if ($rememberMe) {
                                $refreshToken = $supabase_user_data['refresh_token'];
                                $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30日後
                                
                                $insertData = [
                                    'user_id' => $userData['id'],
                                    'token' => $refreshToken,
                                    'expires_at' => $expiresAt
                                ];
                                $db->insert('remember_tokens', $insertData);
                                
                                setcookie('refresh_token', $refreshToken, time() + (86400 * 30), '/'); // 30日間
                            }
                            
                            // ログイン成功時にログイン試行回数をクリア
                            clearLoginAttempts($identifier, $db);
                            
                            // アクティビティログ記録
                            logSystemActivity('ログイン', 'user', $_SESSION['user_id'], 'ユーザーがログインしました');
                    
                    // 前回のページが保存されている場合はそこにリダイレクト
                    $redirectTo = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                    unset($_SESSION['redirect_after_login']);
                    redirect($redirectTo);
                    
                    } else {
                        error_log("Login Debug - User not found or inactive");
                        incrementLoginAttempts($identifier, $db);
                        $errors[] = 'ユーザーIDまたはパスワードが正しくありません。';
                    }
                }
            } else {
                error_log("Login Debug - Supabase authentication failed. HTTP Code: " . $http_code);
                if (isset($auth_data['error'])) {
                    error_log("Login Debug - Supabase Error: " . json_encode($auth_data['error']));
                }
                incrementLoginAttempts($identifier, $db);
                $errors[] = 'ユーザーIDまたはパスワードが正しくありません。';
            }
        } catch (Exception $e) {
            // 開発中は例外の詳細を画面に表示
            $errors[] = 'エラーが発生しました: ' . $e->getMessage();
            error_log('ログインエラー: ' . $e->getMessage());
        }
    }
}

// CSRFトークン生成
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ログイン - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/common/variables.css">
    <link rel="stylesheet" href="assets/css/common/global.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="icon" href="assets/images/favicon.ico">
</head>
<body class="login-page">
    <!-- 高級感のある背景 -->
    <div class="auth-background"></div>

    <div class="login-container">
        <div class="d-flex align-items-center justify-content-center min-vh-100 px-3">
            <div class="login-card">
                <div class="login-brand">
                    <div class="brand-title">
                        <h1>
                            <div class="word">Cotoka</div>
                            <div class="word word-management">Management</div>
                            <div class="word word-system">System</div>
                        </h1>
                    </div>
                    <p>美しさと機能性を融合した次世代サロン管理システム</p>
                    
                    <!-- 追加された動く文字 -->
                    <div class="animated-tagline">
                        <span class="tagline-text">あなたのサロンをもっと輝かせる</span>
                    </div>
                    
                    <div class="features">
                        <ul class="list-unstyled">
                            <li><i class="bi bi-gem"></i> 複数サロンの一元管理を洗練されたインターフェースで</li>
                            <li><i class="bi bi-calendar2-check"></i> 予約・顧客管理をスマートに効率化</li>
                            <li><i class="bi bi-people-fill"></i> スタッフのスケジュール管理も簡単操作</li>
                            <li><i class="bi bi-graph-up-arrow"></i> リアルタイムで分析できる売上レポート</li>
                        </ul>
                    </div>
                </div>
                <div class="login-form">
                    <h2>ようこそ</h2>
                    
                    <?php if ($registrationSuccess): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i> アカウントが正常に作成されました。ログインしてください。
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($resetSuccess): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i> パスワードが正常にリセットされました。新しいパスワードでログインしてください。
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php foreach ($errors as $error): ?>
                        <div><i class="bi bi-exclamation-circle-fill"></i> <?php echo $error; ?></div>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="identifier" name="identifier" 
                                   value="<?php echo htmlspecialchars($identifier ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                   placeholder="メールアドレスまたはユーザー名" required>
                            <label for="identifier">
                                <i class="bi bi-person me-2"></i>メールアドレスまたはユーザー名
                            </label>
                        </div>
                        
                        <div class="form-floating mb-3 password-container">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="パスワードを入力" required>
                            <label for="password">
                                <i class="bi bi-lock me-2"></i>パスワード
                            </label>
                            <button type="button" class="password-toggle" onclick="togglePasswordVisibility()">
                                <i class="bi bi-eye" id="password-toggle-icon"></i>
                            </button>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                            <label class="form-check-label" for="remember_me">ログイン状態を維持する</label>
                        </div>
                        
                        <button type="submit" class="btn btn-gold w-100 btn-lg mb-3" id="loginBtn">
                            <span class="btn-text">
                                <i class="bi bi-box-arrow-in-right me-2"></i>ログイン
                            </span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                ログイン中...
                            </span>
                        </button>
                        
                        <div class="text-center">
                            <a href="forgot_password.php" class="text-decoration-none d-block mb-2">
                                <i class="bi bi-key"></i> パスワードをお忘れですか？
                            </a>
                            <a href="register.php" class="text-decoration-none">
                                <i class="bi bi-person-plus"></i> アカウント登録
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- スクリプト -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/login.js"></script>
    <script>
        // パスワード表示切り替え
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('password-toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        }
        
        // ログインフォーム送信時のローディング状態
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.querySelector('form');
            const loginBtn = document.getElementById('loginBtn');
            
            if (loginForm && loginBtn) {
                loginForm.addEventListener('submit', function() {
                    const btnText = loginBtn.querySelector('.btn-text');
                    const btnLoading = loginBtn.querySelector('.btn-loading');
                    
                    if (btnText && btnLoading) {
                        btnText.classList.add('d-none');
                        btnLoading.classList.remove('d-none');
                        loginBtn.disabled = true;
                    }
                });
            }
        });
        
        // エラーメッセージの自動非表示
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>