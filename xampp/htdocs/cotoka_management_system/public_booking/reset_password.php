<?php
// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../includes/functions.php';
require_once '../vendor/autoload.php'; // PHPMailer用

// PHPMailerの名前空間をインポート
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// セッション開始（セッションがまだ開始されていない場合のみ）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// GETパラメータからサロンIDを取得
if (isset($_GET['salon_id'])) {
    $_SESSION['booking_salon_id'] = $_GET['salon_id'];
    error_log("reset_password.php - GETパラメータからsalon_id取得: " . $_GET['salon_id']);
}

// メッセージ初期化
$message = '';
$error = '';
$success = false;

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // サロンIDを取得（GET > POST > SESSION > COOKIE）
    $salon_id = $_GET['salon_id'] ?? $_POST['salon_id'] ?? $_SESSION['booking_salon_id'] ?? $_COOKIE['salon_id'] ?? 2;
    error_log("reset_password.php - 使用するsalon_id: " . $salon_id);
    
    // サロン情報を取得
    $salon_stmt = $conn->prepare("SELECT salon_id, name, email FROM salons WHERE salon_id = :salon_id");
    $salon_stmt->bindParam(':salon_id', $salon_id);
    $salon_stmt->execute();
    $salon = $salon_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$salon) {
        error_log("サロン情報が見つかりません。salon_id: " . $salon_id);
        $salon = [
            'salon_id' => $salon_id,
            'name' => 'COTOKA美容室',
            'email' => 'cms@cotoka.jp'
        ];
    } else {
        error_log("サロン情報が取得できました。サロン名: " . $salon['name']);
    }
    
    // セッションにサロンIDを保存
    $_SESSION['booking_salon_id'] = $salon_id;
    
} catch (Exception $e) {
    error_log("データベース接続エラー：" . $e->getMessage());
    $error = "システムエラーが発生しました。しばらく時間をおいて再度お試しください。";
}

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // テストメール送信処理
    if (isset($_POST['test_email'])) {
        try {
            // メール設定を読み込み
            $mail_config = [];
            if (file_exists('../mail_config.php')) {
                require_once '../mail_config.php';
            } else {
                // メール設定ファイルがない場合はデフォルト設定
                $mail_config = [
                    'smtp_server' => 'smtp.lolipop.jp',
                    'smtp_port' => 465,
                    'smtp_user' => 'cms@cotoka.jp',
                    'smtp_pass' => 'Syou108810--',
                    'smtp_secure' => 'ssl',
                    'from_email' => 'cms@cotoka.jp',
                    'from_name' => 'COTOKA美容室',
                    'reply_to' => 'cms@cotoka.jp'
                ];
                error_log("mail_config.phpが見つからないため、デフォルト設定を使用します");
            }
            
            // PHPMailerを使用してテストメール送信
            $mail = new PHPMailer(true);
            
            // デバッグ有効化
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer DEBUG [$level]: $str");
            };
            
            // SMTP設定
            $mail->isSMTP();
            $mail->Host = $mail_config['smtp_server'];
            $mail->SMTPAuth = true;
            $mail->Username = $mail_config['smtp_user'];
            $mail->Password = $mail_config['smtp_pass'];
            
            // SSL設定
            if ($mail_config['smtp_secure'] == 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            $mail->Port = $mail_config['smtp_port'];
            $mail->CharSet = 'UTF-8';
            
            // 送信先設定 - 管理者宛に送信
            $mail->setFrom($mail_config['from_email'], $mail_config['from_name']);
            $mail->addAddress('cms@cotoka.jp', '管理者');
            
            // メール内容
            $mail->Subject = "【テスト】システムテストメール";
            $mail->Body = "これはパスワードリセット機能のテストメールです。\n\n" . 
                        "送信時刻: " . date('Y-m-d H:i:s') . "\n" .
                        "ホスト: " . $_SERVER['HTTP_HOST'] . "\n" .
                        "IPアドレス: " . $_SERVER['REMOTE_ADDR'];
            $mail->isHTML(false);
            
            // メール送信
            $send_result = $mail->send();
            
            if ($send_result) {
                $message = "テストメールを送信しました。管理者メールを確認してください。";
                error_log("テストメール送信成功: cms@cotoka.jp");
            } else {
                $error = "テストメールの送信に失敗しました: " . $mail->ErrorInfo;
                error_log("テストメール送信失敗：" . $mail->ErrorInfo);
            }
        } catch (Exception $e) {
            $error = "メールの送信に失敗しました: " . $e->getMessage();
            error_log("PHPMailer例外：" . $e->getMessage());
        }
    }
    // 通常のパスワードリセット処理
    else {
        $email = $_POST['email'] ?? '';
        // セッションまたはPOSTからサロンIDを取得し、デフォルト値として2を設定
        $salon_id = $_POST['salon_id'] ?? $_SESSION['booking_salon_id'] ?? 2;
        error_log("パスワードリセット試行: メール=$email, サロンID=$salon_id");
        
        if (empty($email)) {
            $error = "メールアドレスを入力してください。";
        } elseif (empty($salon_id)) {
            $error = "サロン情報が不足しています。最初からやり直してください。";
        } else {
            error_log("パスワードリセット処理開始 - email: $email, salon_id: $salon_id");
            try {
                // 既にサロン情報は最初に取得しているので、ここでは重複の取得は行わない
                $salon_name = $salon['name'];
                $notification_email = $salon['email'];
                
                // メールアドレスで顧客を検索
                $stmt = $conn->prepare("SELECT customer_id, first_name, last_name FROM customers WHERE email = :email AND salon_id = :salon_id");
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':salon_id', $salon_id);
                $stmt->execute();
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($customer) {
                    // 新しいパスワードを生成
                    $new_password = bin2hex(random_bytes(8)); // 16文字のランダムパスワード
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // パスワードを更新
                    $stmt = $conn->prepare("UPDATE customers SET password = :password WHERE customer_id = :customer_id");
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':customer_id', $customer['customer_id']);
                    $stmt->execute();
                    
                    // メール設定を読み込み
                    $mail_config = [];
                    if (file_exists('../mail_config.php')) {
                        require_once '../mail_config.php';
                    } else {
                        // メール設定ファイルがない場合はデフォルト設定
                        $mail_config = [
                            'smtp_server' => 'smtp.lolipop.jp',
                            'smtp_port' => 465,
                            'smtp_user' => 'cms@cotoka.jp',
                            'smtp_pass' => 'Syou108810--',
                            'smtp_secure' => 'ssl',
                            'from_email' => 'cms@cotoka.jp',
                            'from_name' => $salon_name,
                            'reply_to' => 'cms@cotoka.jp'
                        ];
                        error_log("mail_config.phpが見つからないため、デフォルト設定を使用します");
                    }
                    
                    // PHPMailerを使用してパスワードリセットメールを送信
                    try {
                        $mail = new PHPMailer(true);
                        
                        // デバッグ有効化
                        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                        $mail->Debugoutput = function($str, $level) {
                            error_log("PHPMailer DEBUG [$level]: $str");
                        };
                        
                        // SMTP設定
                        $mail->isSMTP();
                        $mail->Host = $mail_config['smtp_server'];
                        $mail->SMTPAuth = true;
                        $mail->Username = $mail_config['smtp_user'];
                        $mail->Password = $mail_config['smtp_pass'];
                        
                        // SSL設定
                        if ($mail_config['smtp_secure'] == 'ssl') {
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                            $mail->SMTPOptions = [
                                'ssl' => [
                                    'verify_peer' => false,
                                    'verify_peer_name' => false,
                                    'allow_self_signed' => true
                                ]
                            ];
                        } else {
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        }
                        
                        $mail->Port = $mail_config['smtp_port'];
                        $mail->CharSet = 'UTF-8';
                        
                        // 送信先設定
                        $mail->setFrom($mail_config['from_email'], $salon_name);
                        $mail->addAddress($email, $customer['last_name'] . ' ' . $customer['first_name']);
                        $mail->addBCC('cms@cotoka.jp', '管理者'); // 管理者にもBCCで送る
                        
                        // メール内容
                        $mail->Subject = "【" . $salon_name . "】パスワードリセットのお知らせ (ID:" . $salon_id . ")";
                        
                        $mail_body = <<<EOT
{$customer['last_name']} {$customer['first_name']} 様

{$salon_name}のパスワードがリセットされました。
新しいパスワードは以下の通りです。

パスワード: {$new_password}

このパスワードで予約サイトにログインしてください。
ログイン後、マイページでパスワードを変更することをお勧めします。

※このメールはシステムから自動送信されています。
※このメールには返信できません。
※パスワードリセットをリクエストしていない場合は、このメールを無視してください。

------------------
{$salon_name}
{$salon['email']}
EOT;
                        
                        // HTML形式のメール本文も設定
                        $html_body = <<<EOT
<div style="font-family: 'ヒラギノ角ゴ Pro W3', 'Hiragino Kaku Gothic Pro', 'メイリオ', Meiryo, Osaka, 'MS Pゴシック', 'MS PGothic', sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
    <h2 style="color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">{$salon_name} パスワードリセットのお知らせ</h2>
    <p>{$customer['last_name']} {$customer['first_name']} 様</p>
    <p>{$salon_name}のパスワードがリセットされました。<br>新しいパスワードは以下の通りです。</p>
    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; text-align: center;">
        <p style="margin: 0; font-weight: bold; font-size: 18px;">{$new_password}</p>
    </div>
    <p>このパスワードで予約サイトにログインしてください。<br>ログイン後、マイページでパスワードを変更することをお勧めします。</p>
    <p style="font-size: 12px; color: #666; margin-top: 30px; border-top: 1px solid #eee; padding-top: 10px;">
        ※このメールはシステムから自動送信されています。<br>
        ※このメールには返信できません。<br>
        ※パスワードリセットをリクエストしていない場合は、このメールを無視してください。
    </p>
    <div style="margin-top: 30px; padding-top: 10px; border-top: 1px solid #eee; font-size: 12px; color: #666;">
        {$salon_name}<br>
        {$salon['email']}
    </div>
</div>
EOT;
                        $mail->AltBody = $mail_body; // プレーンテキスト版
                        $mail->Body = $html_body;    // HTML版
                        $mail->isHTML(true);         // HTML形式に設定
                        
                        // メール送信
                        $send_result = $mail->send();
                        
                        if ($send_result) {
                            $success = true;
                            $message = "パスワードリセット用のメールを送信しました。メールをご確認ください。";
                            error_log("パスワードリセットメール送信成功: " . $email);
                            
                            // 送信したメールの内容をログに出力（デバッグ用）
                            error_log("送信したメール内容: \n件名: " . $mail->Subject . "\n本文: \n" . $mail_body);
                            
                            // メールの内容を表示するデバッグ用のメッセージ
                            $debug_message = "<hr><p><strong>送信したメール内容（デバッグ用）:</strong></p>";
                            $debug_message .= "<p><strong>件名:</strong> " . htmlspecialchars($mail->Subject) . "</p>";
                            $debug_message .= "<p><strong>本文:</strong></p><pre>" . htmlspecialchars($mail_body) . "</pre>";
                        } else {
                            $error = "メールの送信に失敗しました。管理者にお問い合わせください。";
                            error_log("パスワードリセットメール送信失敗：" . $mail->ErrorInfo);
                        }
                    } catch (Exception $e) {
                        $error = "メールの送信に失敗しました。管理者にお問い合わせください。";
                        error_log("PHPMailer例外：" . $e->getMessage());
                    }
                } else {
                    $error = "入力されたメールアドレスのアカウントが見つかりません。";
                }
            } catch (Exception $e) {
                error_log("パスワードリセットエラー：" . $e->getMessage());
                $error = "処理中にエラーが発生しました。";
            }
        }
    }
}

$page_title = "パスワードのリセット";
$additional_css = ['css/reset_password.css'];

// ヘッダーを読み込み
include 'includes/header.php';
?>

<div class="reset-password-container">
    <div class="reset-password-box">
        <h1>パスワードをお忘れの方</h1>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <div class="button-group">
            <a href="input_info.php" class="btn return-btn">予約入力ページに戻る</a>
        </div>
        
        <?php if (isset($debug_message)): ?>
        <div class="debug-info" style="margin-top: 30px; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px;">
            <h5>メール送信詳細（デバッグ情報）</h5>
            <?php echo $debug_message; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <p class="instructions">
            登録しているメールアドレスを入力してください。<br>
            パスワードリセット用のメールをお送りします。
        </p>
        
        <form method="post" action="">
            <div class="mb-3">
                <label for="email" class="form-label">メールアドレス</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <input type="hidden" name="salon_id" value="<?php echo isset($_SESSION['booking_salon_id']) ? htmlspecialchars($_SESSION['booking_salon_id']) : '2'; ?>">
            <div class="button-group">
                <button type="submit" name="reset_password" class="btn submit-btn">送信</button>
                <a href="input_info.php" class="btn cancel-btn">キャンセル</a>
            </div>
        </form>
        
        <?php 
        // デバッグ情報を表示
        ?>
        <div class="debug-info" style="margin-top: 30px; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px;">
            <h5>デバッグ情報</h5>
            <p><strong>セッション・POSTデータ:</strong></p>
            <ul>
                <li>セッション salon_id: <?php echo htmlspecialchars($_SESSION['booking_salon_id'] ?? 'なし'); ?></li>
                <li>POST salon_id: <?php echo htmlspecialchars($_POST['salon_id'] ?? 'なし'); ?></li>
                <li>使用される salon_id: <?php echo htmlspecialchars($_POST['salon_id'] ?? $_SESSION['booking_salon_id'] ?? '2'); ?></li>
            </ul>
            
            <p><strong>サロン情報:</strong></p>
            <?php if (isset($salon)): ?>
            <ul>
                <li>サロン名: <?php echo htmlspecialchars($salon['name'] ?? '未設定'); ?></li>
                <li>サロンID: <?php echo htmlspecialchars($_SESSION['booking_salon_id'] ?? '未設定'); ?></li>
                <li>サロンメール: <?php echo htmlspecialchars($salon['email'] ?? '未設定'); ?></li>
            </ul>
            <?php else: ?>
            <p>サロン情報が取得できていません</p>
            <?php endif; ?>
            
            <p><strong>メール設定:</strong></p>
            <?php if (isset($mail_config)): ?>
            <ul>
                <li>SMTP Server: <?php echo htmlspecialchars($mail_config['smtp_server'] ?? '未設定'); ?></li>
                <li>SMTP Port: <?php echo htmlspecialchars($mail_config['smtp_port'] ?? '未設定'); ?></li>
                <li>SMTP User: <?php echo htmlspecialchars($mail_config['smtp_user'] ?? '未設定'); ?></li>
                <li>SMTP Secure: <?php echo htmlspecialchars($mail_config['smtp_secure'] ?? '未設定'); ?></li>
                <li>設定ファイル: <?php echo file_exists('../mail_config.php') ? '存在します' : '存在しません'; ?></li>
            </ul>
            <?php else: ?>
            <p>mail_config読み込み前</p>
            <?php endif; ?>
            
            <p><strong>メールテスト:</strong></p>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="test_email" value="1">
                <button type="submit" class="btn btn-sm btn-info">テストメール送信</button>
            </form>
            
            <p><strong>エラーログ:</strong></p>
            <?php
            $log_file = "/Applications/XAMPP/xamppfiles/logs/php_error_log";
            if (file_exists($log_file)) {
                $log_content = shell_exec("tail -n 20 " . escapeshellarg($log_file));
                echo '<pre style="max-height: 200px; overflow-y: auto;">' . htmlspecialchars($log_content) . '</pre>';
            } else {
                echo 'エラーログファイルが見つかりません。';
            }
            ?>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<?php
// フッターを読み込み
include 'includes/footer.php';
?> 