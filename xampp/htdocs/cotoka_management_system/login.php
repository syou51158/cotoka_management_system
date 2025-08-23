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

// Remember Meトークンによる自動ログイン
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    $db = new Database();
    $user = new User($db);
    
    if ($user->autoLoginByToken($_COOKIE['remember_token'])) {
        // ログイン成功
        logSystemActivity('自動ログイン', 'user', $_SESSION['user_id'], 'Remember Meトークンによる自動ログイン');
        redirect('dashboard.php');
    }
}

$errors = [];

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
    
    // CSRFトークン検証
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = '不正なリクエストです。ページを再読み込みして再度お試しください。';
    }
    
    // 入力値の検証
    if (empty($identifier)) {
        $errors[] = 'ユーザーIDまたはメールアドレスを入力してください。';
    }
    
    if (empty($password)) {
        $errors[] = 'パスワードを入力してください。';
    }
    
    // エラーがなければログイン処理
    if (empty($errors)) {
        try {
            $db = new Database();
            $user = new User($db);
            
            // ログイン試行回数のチェック
            if (checkLoginAttempts($identifier, $db)) {
                $loggedIn = $user->login($identifier, $password, $rememberMe);
                
                if ($loggedIn) {
                    // ログイン成功時の処理
                    clearLoginAttempts($identifier, $db);
                    
                    // アクティビティログ記録
                    logSystemActivity('ログイン', 'user', $_SESSION['user_id'], 'ユーザーがログインしました');
                    
                    // 前回のページが保存されている場合はそこにリダイレクト
                    $redirectTo = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                    unset($_SESSION['redirect_after_login']);
                    redirect($redirectTo);
                } else {
                    incrementLoginAttempts($identifier, $db);
                    $errors[] = 'ユーザーIDまたはパスワードが正しくありません。';
                }
            } else {
                $errors[] = 'ログイン試行回数が上限を超えました。しばらく時間をおいてから再度お試しください。';
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
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill"></i> アカウントが正常に作成されました。ログインしてください。
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                        <div><i class="bi bi-exclamation-circle-fill"></i> <?php echo $error; ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="identifier" name="identifier" placeholder="メールアドレスまたはユーザー名" required>
                            <label for="identifier">メールアドレスまたはユーザー名</label>
                        </div>
                        
                        <div class="form-floating mb-3 password-container">
                            <input type="password" class="form-control" id="password" name="password" placeholder="パスワード" required>
                            <label for="password">パスワード</label>
                            <button type="button" class="password-toggle" onclick="togglePasswordVisibility()">
                                <i class="bi bi-eye" id="password-toggle-icon"></i>
                            </button>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                            <label class="form-check-label" for="remember_me">ログイン状態を維持する</label>
                        </div>
                        
                        <button type="submit" class="btn btn-gold w-100 btn-lg mb-3">ログイン</button>
                        
                        <div class="text-center">
                            <a href="register.php" class="text-decoration-none">
                                <i class="bi bi-person-plus"></i> アカウント登録
                            </a>
                            <span class="mx-2">|</span>
                            <a href="forgot-password.php" class="text-decoration-none">
                                <i class="bi bi-key"></i> パスワードをお忘れの方
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
</body>
</html>