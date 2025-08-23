<?php
// PHPMailerテスト用スクリプト
// Composerのオートロードファイルを読み込み
require_once 'vendor/autoload.php';

// PHPMailerの名前空間をインポート
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// エラー表示設定
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 現在のタイムスタンプでエラーログを記録
error_log("=== PHPMailerテスト " . date('Y-m-d H:i:s') . " ===");

// テスト設定の取得
$test_type = $_GET['test'] ?? 'ssl'; // デフォルトはSSL

// 接続設定を選択
switch ($test_type) {
    case 'tls':
        // TLS設定（587ポート）
        $smtp_config = [
            'smtp_server' => 'smtp.lolipop.jp',
            'smtp_port' => 587,
            'smtp_user' => 'cms@cotoka.jp',
            'smtp_pass' => 'Syou108810--',
            'smtp_secure' => 'tls',
            'from_email' => 'cms@cotoka.jp',
            'from_name' => 'COTOKA美容室[TLS]',
            'to_email' => 'syo.t.company@gmail.com'
        ];
        break;
    case 'local':
        // ローカルメールサーバー設定
        $smtp_config = [
            'smtp_server' => 'localhost',
            'smtp_port' => 25,
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_secure' => '',
            'from_email' => 'cms@cotoka.jp',
            'from_name' => 'COTOKA美容室[ローカル]',
            'to_email' => 'syo.t.company@gmail.com'
        ];
        break;
    case 'ssl':
    default:
        // SSL設定（465ポート）
        $smtp_config = [
            'smtp_server' => 'smtp.lolipop.jp',
            'smtp_port' => 465,
            'smtp_user' => 'cms@cotoka.jp',
            'smtp_pass' => 'Syou108810--',
            'smtp_secure' => 'ssl',
            'from_email' => 'cms@cotoka.jp',
            'from_name' => 'COTOKA美容室[SSL]',
            'to_email' => 'syo.t.company@gmail.com'
        ];
}

// 画面表示
echo "<h1>PHPMailerテスト - " . htmlspecialchars($test_type) . "モード</h1>";
echo "<p>設定:</p>";
echo "<ul>";
foreach ($smtp_config as $key => $value) {
    if ($key !== 'smtp_pass') { // パスワードは表示しない
        echo "<li>" . htmlspecialchars($key) . ": " . htmlspecialchars($value) . "</li>";
    }
}
echo "</ul>";

echo "<p>テストモード選択: ";
echo "<a href='?test=ssl'>SSL(465)</a> | ";
echo "<a href='?test=tls'>TLS(587)</a> | ";
echo "<a href='?test=local'>ローカル(25)</a>";
echo "</p>";

// 送信処理
try {
    // PHPMailerオブジェクトの作成
    $mail = new PHPMailer(true);
    
    // デバッグ出力の設定
    $mail->SMTPDebug = SMTP::DEBUG_LOWLEVEL; // 最大レベルのデバッグ出力
    $mail->Debugoutput = function($str, $level) {
        echo "[$level] $str<br>";
        error_log("PHPMailer Debug: [$level] $str");
    };
    
    // SMTPサーバーの設定
    if ($test_type === 'local') {
        // ローカルメールサーバーを使用
        $mail->isMail();
    } else {
        // SMTPサーバーを使用
        $mail->isSMTP();
        $mail->Host = $smtp_config['smtp_server'];
        
        if (!empty($smtp_config['smtp_user'])) {
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_config['smtp_user'];
            $mail->Password = $smtp_config['smtp_pass'];
        } else {
            $mail->SMTPAuth = false;
        }
        
        // セキュリティ設定
        if ($smtp_config['smtp_secure'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            // SSL接続の設定
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        } elseif ($smtp_config['smtp_secure'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            // TLS接続の設定
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        }
        
        $mail->Port = $smtp_config['smtp_port'];
    }
    
    // 文字コード設定
    $mail->CharSet = 'UTF-8';
    
    // 送信元・送信先の設定
    $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
    
    // 複数の送信先をテスト
    $mail->addAddress($smtp_config['to_email']); // 元のテスト送信先
    $mail->addAddress('cms@cotoka.jp'); // 送信元と同じドメインをテスト
    
    $mail->addReplyTo($smtp_config['from_email'], $smtp_config['from_name']);
    
    // メール内容
    $mail->Subject = '[' . $test_type . '] PHPMailerテストメール - ' . date('Y-m-d H:i:s');
    $mail->Body = "これはPHPMailerを使用したテストメールです。\n\n"
                . "テストモード: " . $test_type . "\n"
                . "時刻: " . date('Y-m-d H:i:s') . "\n"
                . "ホスト: " . ($_SERVER['HTTP_HOST'] ?? 'unknown') . "\n"
                . "IPアドレス: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
    
    // HTMLメールにしない
    $mail->isHTML(false);
    
    // タイムアウト設定
    $mail->Timeout = 30; // 30秒
    
    // メール送信
    $result = $mail->send();
    
    // 結果表示を詳細にする
    if ($result) {
        echo "<h3 style='color:green;'>メール送信成功！</h3>";
        echo "<p>送信先:</p>";
        echo "<ul>";
        echo "<li>" . htmlspecialchars($smtp_config['to_email']) . "</li>";
        echo "<li>cms@cotoka.jp</li>";
        echo "</ul>";
        echo "<p>※迷惑メールフォルダも確認してください</p>";
    } else {
        echo "<h3 style='color:red;'>メール送信エラー</h3>";
        echo "<p>" . htmlspecialchars($mail->ErrorInfo) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color:red;'>メール送信エラー</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    error_log("PHPMailerテストエラー: " . $e->getMessage());
}

// PHPエラーログの最新情報
echo "<h3>PHPエラーログ (最新20行)</h3>";
$log_file = "/Applications/XAMPP/xamppfiles/logs/php_error_log";
if (file_exists($log_file)) {
    $log_content = shell_exec("tail -n 20 " . escapeshellarg($log_file));
    echo "<pre>" . htmlspecialchars($log_content) . "</pre>";
} else {
    echo "<p>エラーログファイルが見つかりません</p>";
}

// 現在のPHP設定
echo "<h3>PHP設定</h3>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>sendmail_path: " . ini_get('sendmail_path') . "</p>";
echo "<p>SMTP: " . ini_get('SMTP') . "</p>";
echo "<p>smtp_port: " . ini_get('smtp_port') . "</p>";
?>

<hr>
<p><a href="public_booking/">予約システムに戻る</a></p> 