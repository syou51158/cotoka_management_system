<?php
/**
 * メール送信の設定ファイル
 * ここでメール送信に関する設定を管理します
 */

// Composerのオートロードを読み込む
require_once 'vendor/autoload.php';

// PHPMailerの名前空間をインポート
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ローカル環境かどうかを判定
function isLocalEnvironment() {
    return ($_SERVER['HTTP_HOST'] == 'localhost' || 
            $_SERVER['REMOTE_ADDR'] == '127.0.0.1' || 
            strpos($_SERVER['HTTP_HOST'], '.local') !== false);
}

// メール送信に使用するSMTP設定
$mail_config = [
    // 基本設定
    'from_email' => 'cms@cotoka.jp',
    'from_name' => 'COTOKA美容室',
    'reply_to' => 'cms@cotoka.jp',
    
    // 管理者メール
    'admin_email' => 'cms@cotoka.jp',
    
    // SMTPサーバー設定（ロリポップ）
    'smtp_server' => 'smtp.lolipop.jp',
    'smtp_port' => 465,
    'smtp_user' => 'cms@cotoka.jp',
    'smtp_pass' => 'Syou108810--',
    'smtp_secure' => 'ssl', // SSL暗号化（465ポート用）
    
    // 開発環境（XAMPP）用設定
    'dev_mode' => isLocalEnvironment(),
    'dev_log_emails' => true, // 開発環境ではメールをログに記録する
];

/**
 * メール送信関数 - PHPMailerを使用
 * 環境に合わせて適切なメール送信方法を選択します
 */
function send_mail($to, $subject, $message, $headers = '', $from_email = '', $from_name = '') {
    global $mail_config;
    
    // 送信元情報が指定されていない場合はデフォルト値を使用
    $from_email = $from_email ?: $mail_config['from_email'];
    $from_name = $from_name ?: $mail_config['from_name'];
    
    // 開発環境ではログに記録
    if ($mail_config['dev_mode'] && $mail_config['dev_log_emails']) {
        error_log("======= メール送信ログ =======");
        error_log("宛先: " . $to);
        error_log("件名: " . $subject);
        if (!empty($headers)) {
            error_log("ヘッダー: " . $headers);
        }
        error_log("本文: " . substr($message, 0, 200) . "...");
        return true; // 開発環境では成功として扱う
    }
    
    try {
        // PHPMailerのインスタンスを作成
        $mail = new PHPMailer(true);
        
        // SMTPサーバーの設定
        $mail->isSMTP();
        $mail->Host = $mail_config['smtp_server'];
        $mail->SMTPAuth = true;
        $mail->Username = $mail_config['smtp_user'];
        $mail->Password = $mail_config['smtp_pass'];
        
        // 暗号化タイプの設定
        if ($mail_config['smtp_secure'] == 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($mail_config['smtp_secure'] == 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            // SSL接続を確実にするための追加設定
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        }
        
        $mail->Port = $mail_config['smtp_port'];
        
        // 文字コード設定
        $mail->CharSet = 'UTF-8';
        
        // 送信元と送信先の設定
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($to);
        
        // ReplyToヘッダー
        $mail->addReplyTo($mail_config['reply_to']);
        
        // メール内容
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        // プレーンテキストとして送信
        $mail->isHTML(false);
        
        // メール送信
        $result = $mail->send();
        
        // 送信結果をログに記録
        error_log("PHPMailerでのメール送信結果: " . ($result ? '成功' : '失敗') . " 宛先: " . $to);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("メール送信エラー: " . $e->getMessage());
        return false;
    }
}
?> 