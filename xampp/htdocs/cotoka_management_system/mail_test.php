<?php
$to = "syo.t.company@gmail.com"; // 確認したいメールアドレスに変更
$subject = "テストメール from XAMPP";
$message = "これはXAMPPからのテストメールです。\n\nこのメールが届けば、メール送信機能は正常に動作しています。";
$headers = "From: test@example.com\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// 設定情報を出力
echo "PHPメール設定:<br>";
echo "sendmail_path: " . ini_get("sendmail_path") . "<br>";
echo "SMTP: " . ini_get("SMTP") . "<br>";
echo "smtp_port: " . ini_get("smtp_port") . "<br><br>";

// 通常のmail関数
$result = mail($to, $subject, $message, $headers);
echo "mail()関数の結果: " . ($result ? "成功" : "失敗") . "<br>";

// mb_send_mail関数
$mb_result = mb_send_mail($to, $subject, $message, $headers);
echo "mb_send_mail()関数の結果: " . ($mb_result ? "成功" : "失敗") . "<br>";

echo "<br>メール送信完了しました。受信箱とスパムフォルダを確認してください。";
?> 