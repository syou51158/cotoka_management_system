<?php
// エラーを表示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 送信先
$to = "syo.t.company@gmail.com"; // 確認したいメールアドレスに変更
$subject = "XAMPP SMTP設定テスト";
$message = "これはXAMPPの詳細なSMTP設定テストです。\n";
$message .= "このメールが届けば、メール送信機能は正常に動作しています。\n\n";
$message .= "送信時刻: " . date("Y-m-d H:i:s");

// 方法1: 通常のPHPメール設定を使用
function send_normal_mail($to, $subject, $message) {
    $headers = "From: test@example.com\r\n";
    $headers .= "Reply-To: test@example.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    $result = mail($to, $subject, $message, $headers);
    echo "通常のmail()関数: " . ($result ? "成功" : "失敗") . "<br>";
}

// 方法2: mb_send_mail関数を使用
function send_mb_mail($to, $subject, $message) {
    $headers = "From: test@example.com\r\n";
    $headers .= "Reply-To: test@example.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    $result = mb_send_mail($to, $subject, $message, $headers);
    echo "mb_send_mail()関数: " . ($result ? "成功" : "失敗") . "<br>";
}

// 方法3: 直接SMTPサーバーを指定（ロリポップの場合）
function send_via_smtp($to, $subject, $message) {
    $smtp_server = "smtp.lolipop.jp";
    $smtp_port = 587;
    $smtp_user = "cms@cotoka.jp";
    $smtp_pass = "Syou108810--";
    
    // 一時的なSMTP設定を行う
    ini_set("SMTP", $smtp_server);
    ini_set("smtp_port", $smtp_port);
    
    $headers = "From: " . $smtp_user . "\r\n";
    $headers .= "Reply-To: " . $smtp_user . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    $result = mail($to, $subject . " (SMTP設定)", $message, $headers);
    echo "SMTP直接指定: " . ($result ? "成功" : "失敗") . "<br>";
}

// 現在のメール設定を表示
echo "<h2>現在のPHPメール設定</h2>";
echo "sendmail_path: " . ini_get("sendmail_path") . "<br>";
echo "SMTP: " . ini_get("SMTP") . "<br>";
echo "smtp_port: " . ini_get("smtp_port") . "<br>";
echo "mail.add_x_header: " . ini_get("mail.add_x_header") . "<br>";
echo "PHP version: " . phpversion() . "<br><br>";

// 各メソッドでテスト送信を実行
echo "<h2>テスト送信結果</h2>";
send_normal_mail($to, $subject . " (通常のmail関数)", $message);
send_mb_mail($to, $subject . " (mb_send_mail関数)", $message);
send_via_smtp($to, $subject, $message);

echo "<br><strong>メール送信完了しました。受信箱とスパムフォルダを確認してください。</strong><br>";
echo "送信先: " . $to;
?> 