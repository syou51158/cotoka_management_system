<?php
// エラー報告を有効にする
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// セッション開始
session_start();

// パスワードリセットページ
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';

// ログイン済みの場合はダッシュボードにリダイレクト
if (isset($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$errors = [];
$success = false;
$email = '';

// パスワードリセットリクエスト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    // バリデーション
    if (empty($email)) {
        $errors[] = 'メールアドレスを入力してください。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '有効なメールアドレスを入力してください。';
    }
    
    // エラーがなければパスワードリセット処理
    if (empty($errors)) {
        try {
            // Supabaseパスワードリセット API呼び出し
            $reset_data = [
                'email' => $email
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, SUPABASE_URL . '/auth/v1/recover');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reset_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'apikey: ' . SUPABASE_ANON_KEY
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            error_log("Password Reset Debug - HTTP Code: " . $http_code);
            error_log("Password Reset Debug - Response: " . $response);
            
            if ($http_code === 200) {
                $success = true;
                
                // システムログに記録
                logSystemActivity('パスワードリセット要求', 'user', null, 'メールアドレス: ' . $email);
                
                // メールアドレスをクリア（セキュリティのため）
                $email = '';
            } else {
                error_log("Password Reset Debug - Failed. HTTP Code: " . $http_code);
                // セキュリティのため、常に成功メッセージを表示
                $success = true;
                $email = '';
            }
            
        } catch (Exception $e) {
            error_log("Password Reset Error: " . $e->getMessage());
            // セキュリティのため、常に成功メッセージを表示
            $success = true;
            $email = '';
        }
    }
}


?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワードリセット - Cotoka Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/login.css" rel="stylesheet">
    <style>
        .password-reset-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .reset-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        .btn-gold {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            border: none;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-gold:hover {
            background: linear-gradient(45deg, #e67e22, #d35400);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="password-reset-container d-flex align-items-center justify-content-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="reset-card p-5">
                        <!-- ブランドセクション -->
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-primary mb-2">
                                <i class="bi bi-shield-lock me-2"></i>パスワードリセット
                            </h2>
                            <p class="text-muted">登録されたメールアドレスにリセット用のリンクを送信します</p>
                        </div>

                        <!-- エラーメッセージ -->
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?php foreach ($errors as $error): ?>
                                    <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endforeach; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- 成功メッセージ -->
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-2"></i>
                                パスワードリセットのメールを送信しました。メールをご確認ください。
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- パスワードリセットフォーム -->
                        <form method="POST" action="">
                            <div class="form-floating mb-4">
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                                       placeholder="メールアドレスを入力" required>
                                <label for="email">
                                    <i class="bi bi-envelope me-2"></i>メールアドレス
                                </label>
                            </div>

                            <button type="submit" class="btn btn-gold w-100 btn-lg mb-4" id="resetBtn">
                                <span class="btn-text">
                                    <i class="bi bi-send me-2"></i>リセットメールを送信
                                </span>
                                <span class="btn-loading d-none">
                                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                    送信中...
                                </span>
                            </button>

                            <div class="text-center">
                                <a href="login.php" class="text-decoration-none">
                                    <i class="bi bi-arrow-left me-2"></i>ログインページに戻る
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
                        
                        <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                            <div><i class="bi bi-exclamation-circle-fill"></i> <?php echo $error; ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="request_reset">
                            
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="email" name="email" placeholder="メールアドレス" required>
                                <label for="email">メールアドレス</label>
                            </div>
                            
                            <button type="submit" class="btn btn-gold w-100 btn-lg mb-3">リセットリンクを送信</button>
                            
                            <div class="text-center">
                                <a href="login.php" class="text-decoration-none">ログイン画面に戻る</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // パスワード表示切り替え
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('password_confirm');
            const icon = document.getElementById('password-toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                if (confirmInput) confirmInput.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                passwordInput.type = 'password';
                if (confirmInput) confirmInput.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }

        // パスワード強度チェック
        document.getElementById('password')?.addEventListener('input', function(e) {
            const password = e.target.value;
            const strength = checkPasswordStrength(password);
            // ここにパスワード強度表示の処理を追加できます
        });

        function checkPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            return strength;
        }
    </script>
</body>
</html>