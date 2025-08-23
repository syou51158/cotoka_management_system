<?php
// メールテスト専用ページ - 直接ブラウザで実行
header("Content-Type: text/html; charset=UTF-8");

// エラーを表示
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

echo "<h1>メール送信テスト</h1>";

// 送信先メールアドレス
$to = "syo.t.company@gmail.com";
$subject = "PHPからのテストメール - " . date("Y-m-d H:i:s");
$message = "これはテストメールです。\n\n時刻: " . date("Y-m-d H:i:s");

// ヘッダー
$headers = "From: test@example.com\r\n";
$headers .= "Reply-To: test@example.com\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

echo "<h2>現在のPHP設定</h2>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "sendmail_path: " . ini_get("sendmail_path") . "\n";
echo "SMTP: " . ini_get("SMTP") . "\n";
echo "smtp_port: " . ini_get("smtp_port") . "\n";
echo "mail.add_x_header: " . ini_get("mail.add_x_header") . "\n";
echo "</pre>";

echo "<h2>メールの送信試行</h2>";

// mail関数による送信
$result = mail($to, $subject, $message, $headers);
echo "mail()関数の結果: " . ($result ? "<span style=\"color:green\">成功</span>" : "<span style=\"color:red\">失敗</span>") . "<br>";

// mb_send_mail関数がある場合は使用
if (function_exists("mb_send_mail")) {
    $mb_result = mb_send_mail($to, $subject . " (mb_send_mail)", $message, $headers);
    echo "mb_send_mail()関数の結果: " . ($mb_result ? "<span style=\"color:green\">成功</span>" : "<span style=\"color:red\">失敗</span>") . "<br>";
} else {
    echo "mb_send_mail()関数は利用できません。<br>";
}

// 手動でSMTP設定を変更して試す
$original_smtp = ini_get("SMTP");
$original_smtp_port = ini_get("smtp_port");

// SMTPを設定
ini_set("SMTP", "smtp.lolipop.jp");
ini_set("smtp_port", "587");

echo "<h2>SMTPサーバーを直接指定した場合</h2>";
echo "<pre>";
echo "SMTP: " . ini_get("SMTP") . "\n";
echo "smtp_port: " . ini_get("smtp_port") . "\n";
echo "</pre>";

// 設定変更後のmail関数による送信
$smtp_result = mail($to, $subject . " (SMTP設定変更後)", $message, $headers);
echo "SMTP指定後のmail()関数の結果: " . ($smtp_result ? "<span style=\"color:green\">成功</span>" : "<span style=\"color:red\">失敗</span>") . "<br>";

// エラー情報があれば表示
$last_error = error_get_last();
if ($last_error) {
    echo "<h2>最後のエラー</h2>";
    echo "<pre>";
    print_r($last_error);
    echo "</pre>";
}

// エラーログを表示
echo "<h2>PHPエラーログ (最新の20行)</h2>";
echo "<pre>";
$log_file = "/Applications/XAMPP/xamppfiles/logs/php_error_log";
if (file_exists($log_file)) {
    $log_content = shell_exec("tail -n 20 " . escapeshellarg($log_file));
    echo htmlspecialchars($log_content);
} else {
    echo "エラーログファイルが見つかりません。";
}
echo "</pre>";

echo "<p>送信先: $to</p>";
echo "<p>件名: $subject</p>";
echo "<p>メッセージ: " . nl2br(htmlspecialchars($message)) . "</p>";
echo "<p>ヘッダー: " . nl2br(htmlspecialchars($headers)) . "</p>";
?> 