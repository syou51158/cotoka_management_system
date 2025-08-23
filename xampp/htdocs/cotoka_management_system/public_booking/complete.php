<?php
// セッション開始
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// PHPMailer関連のインポート
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// エラー表示設定
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../includes/functions.php';
require_once '../vendor/autoload.php'; // PHPMailer用
include_once '../mail_config.php';

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// 予約IDの取得
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$appointment_id && isset($_SESSION['booking_appointment_id'])) {
    $appointment_id = $_SESSION['booking_appointment_id'];
}

// デバッグ情報
error_log("完了ページ: appointment_id = $appointment_id, セッション = " . json_encode($_SESSION, JSON_UNESCAPED_UNICODE));

// 予約IDが不正な場合
if (!$appointment_id) {
    error_log("予約IDが不正です: " . ($appointment_id ?: 'なし'));
    $_SESSION['error_message'] = "予約情報が見つかりません。最初からやり直してください。";
    header("Location: index.php");
    exit;
}

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    error_log("データベース接続エラー: " . $e->getMessage());
    $_SESSION['error_message'] = "システムエラーが発生しました。";
    header("Location: error.php");
    exit;
}

// 予約情報の取得
$query = "SELECT a.*, c.customer_id, c.first_name, c.last_name, c.email, c.phone,
                s.first_name AS staff_first_name, s.last_name AS staff_last_name,
                sa.name AS salon_name
          FROM appointments a 
          JOIN customers c ON a.customer_id = c.customer_id 
          LEFT JOIN staff s ON a.staff_id = s.staff_id
          JOIN salons sa ON a.salon_id = sa.salon_id
          WHERE a.appointment_id = :appointment_id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':appointment_id', $appointment_id);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    error_log("予約情報が見つかりません: ID = $appointment_id");
    $_SESSION['error_message'] = "予約情報が見つかりません。";
    header("Location: error.php");
    exit;
}

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);
$customer_name = $appointment['last_name'] . ' ' . $appointment['first_name'];
$staff_name = !empty($appointment['staff_name']) ? $appointment['staff_name'] : (!empty($appointment['staff_last_name']) ? $appointment['staff_last_name'] . ' ' . $appointment['staff_first_name'] : "指定なし");

// 予約サービスの取得
$services_query = "SELECT s.name, aps.price, aps.duration 
                  FROM appointment_services aps 
                  JOIN services s ON aps.service_id = s.service_id 
                  WHERE aps.appointment_id = :appointment_id";
$services_stmt = $conn->prepare($services_query);
$services_stmt->bindParam(':appointment_id', $appointment_id);
$services_stmt->execute();

$services = [];
$total_price = 0;
$total_duration = 0;

if ($services_stmt->rowCount() > 0) {
    while ($service = $services_stmt->fetch(PDO::FETCH_ASSOC)) {
        $services[] = $service;
        $total_price += $service['price'];
        $total_duration += $service['duration'];
    }
} else {
    // サービス情報がない場合は予約から取得
    $total_price = $appointment['price'] ?? 0;
    $total_duration = $appointment['duration'] ?? 0;
}

// 日付のフォーマット
$date_obj = new DateTime($appointment['appointment_date']);
$weekday_num = $date_obj->format('w');
$days_of_week_jp = ['日', '月', '火', '水', '木', '金', '土'];
$date_formatted = $date_obj->format('Y年n月j日') . '（' . $days_of_week_jp[$weekday_num] . '）';

// 確認コードの生成（まだ生成されていない場合）
if (!isset($_SESSION['booking_confirmation_code'])) {
    $_SESSION['booking_confirmation_code'] = substr(md5(uniqid(rand(), true)), 0, 8);
}
$confirmation_code = $_SESSION['booking_confirmation_code'];

// セッション変数をリセット（メール送信のため）
$_SESSION['booking_email_sent'] = false;

// メール送信（まだ送信されていない場合）
if (!isset($_SESSION['booking_email_sent']) || $_SESSION['booking_email_sent'] !== true) {
    // デバッグ用：メール送信前の情報をログに記録
    error_log("メール送信開始: appointment_id=" . $appointment_id . ", email=" . $appointment['email']);
    error_log("メール設定: server=" . $mail_config['smtp_server'] . ", port=" . $mail_config['smtp_port'] . ", user=" . $mail_config['smtp_user'] . ", secure=" . $mail_config['smtp_secure']);
    
    // ロリポップのメール設定を明示的に設定
    $lolipop_config = [
        'smtp_server' => 'smtp.lolipop.jp',
        'smtp_port' => 465,
        'smtp_user' => 'cms@cotoka.jp',
        'smtp_pass' => 'Syou108810--',
        'smtp_secure' => 'ssl',
        'from_email' => 'cms@cotoka.jp',
        'from_name' => $appointment['salon_name'],
        'reply_to' => 'cms@cotoka.jp'
    ];
    
    // ロリポップの設定をグローバル設定にマージ
    $mail_config = array_merge($mail_config, $lolipop_config);
    error_log("最終メール設定: " . json_encode(array_diff_key($mail_config, ['smtp_pass' => ''])));
    
    // 送信試行
    $send_result = send_confirmation_email($appointment, $services, $confirmation_code, $date_formatted);
    
    if ($send_result) {
        $_SESSION['booking_email_sent'] = true;
        $email_status = "予約確認メールを送信しました。";
        error_log("メール送信成功 - 宛先: " . $appointment['email']);
    } else {
        $email_status = "メール送信に失敗しました。スタッフにお問い合わせください。";
        error_log("メール送信失敗 - 宛先: " . $appointment['email'] . " - PHPMailerのログを確認してください");
    }
} else {
    $email_status = "予約確認メールはすでに送信済みです。";
}

// 予約確認メールを送信する関数
function send_confirmation_email($appointment, $services = [], $confirmation_code = '', $date_formatted = '') {
    global $mail_config;
    
    try {
        error_log("確認メール送信関数開始: " . $appointment['email']);
        
        // 基本情報を出力
        error_log("予約ID: " . $appointment['appointment_id']);
        error_log("確認コード: " . $confirmation_code);
        error_log("予約日時: " . $date_formatted);
        
        if (empty($appointment['email'])) {
            error_log("エラー: メールアドレスが空です");
            return false;
        }
        
        error_log("予約確認メール送信処理を開始します。予約ID: " . $appointment['appointment_id']);
        
        // 顧客情報と担当者名を生成
        $customer_name = $appointment['last_name'] . ' ' . $appointment['first_name'];
        $staff_name = !empty($appointment['staff_name']) 
            ? $appointment['staff_name'] 
            : (!empty($appointment['staff_last_name']) 
                ? $appointment['staff_last_name'] . ' ' . $appointment['staff_first_name'] 
                : "指定なし");
        
        // メール送信ログ
        error_log("お客様宛メールの送信準備 - 送信先: " . $appointment['email']);
        error_log("顧客名: " . $customer_name . ", 担当者名: " . $staff_name);
        
        // メール本文
        $customer_message = $customer_name . " 様\n\n";
        $customer_message .= $appointment['salon_name'] . "をご予約いただき、誠にありがとうございます。\n";
        $customer_message .= "以下の内容でご予約を承りましたのでご確認ください。\n\n";
        $customer_message .= "【予約番号】\n" . $confirmation_code . "\n\n";
        $customer_message .= "【予約内容】\n";
        $customer_message .= "日時: " . $date_formatted . " " . $appointment['start_time'] . "～" . $appointment['end_time'] . "\n";
        
        // 担当スタッフがいる場合
        $customer_message .= "担当: " . $staff_name . "\n";
        
        $customer_message .= "\n【ご予約メニュー】\n";
        $total_price = 0;
        
        if (!empty($services)) {
            foreach ($services as $service) {
                $service_price = isset($service['price']) ? intval($service['price']) : 0;
                $customer_message .= $service['name'] . " (" . $service['duration'] . "分) - ¥" . number_format($service_price) . "\n";
                $total_price += $service_price;
            }
        } else {
            $customer_message .= "メニュー情報はありません\n";
            $total_price = isset($appointment['price']) ? intval($appointment['price']) : 0;
        }
        
        $customer_message .= "\n合計金額: ¥" . number_format($total_price) . "\n\n";
        
        // お客様宛メール本文
        $customer_message .= "
お名前: {$customer_name}
電話番号: {$appointment['phone']}
メールアドレス: {$appointment['email']}

予約確認コード: {$confirmation_code}

ご予約のキャンセルや変更がございましたら、
お手数ですが当サロンまでご連絡ください。

------------------------------
{$appointment['salon_name']}
住所: 東京都渋谷区〇〇町1-2-3
電話: 03-XXXX-XXXX
メール: cms@cotoka.jp
------------------------------
";

        // 管理者宛メール本文
        $admin_message = "
新しい予約が入りました。

【予約内容】
予約ID: {$appointment['appointment_id']}
予約日時: {$date_formatted} {$appointment['start_time']}〜{$appointment['end_time']}
担当: {$staff_name}
予約サービス:
{$total_price}円

【お客様情報】
お名前: {$customer_name}
電話番号: {$appointment['phone']}
メールアドレス: {$appointment['email']}

予約確認コード: {$confirmation_code}
";

        // お客様宛メール送信
        $mail = new PHPMailer(true);
        
        // SMTPの設定
        $mail->isSMTP();
        $mail->Host = $mail_config['smtp_server'];
        $mail->SMTPAuth = true;
        $mail->Username = $mail_config['smtp_user'];
        $mail->Password = $mail_config['smtp_pass'];
        
        // デバッグ出力を常に有効化
        $mail->SMTPDebug = SMTP::DEBUG_LOWLEVEL; // 最大レベルのデバッグ
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug[" . $level . "]: " . $str);
        };
        
        // SSL設定（465ポート用）
        if ($mail_config['smtp_secure'] == 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            // SSL接続の問題を解決するための設定
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
        
        // 文字コード設定
        $mail->CharSet = 'UTF-8';
        
        // 送信元・送信先設定
        $mail->setFrom($mail_config['from_email'], $mail_config['from_name']);
        $mail->addAddress($appointment['email'], $customer_name);
        $mail->addReplyTo($mail_config['reply_to'] ?? $mail_config['from_email']);
        
        // メール内容
        $mail->Subject = "{$appointment['salon_name']} ご予約確認";
        $mail->Body = $customer_message;
        $mail->isHTML(false);
        
        // タイムアウト設定を追加
        $mail->Timeout = 30; // 30秒
        
        // メール送信試行
        $customer_result = $mail->send();
        error_log("顧客宛メール送信結果: " . ($customer_result ? "成功" : "失敗 - " . $mail->ErrorInfo));
        
        // 管理者宛メール送信
        $admin_mail = new PHPMailer(true);
        $admin_mail->isSMTP();
        $admin_mail->Host = $mail_config['smtp_server'];
        $admin_mail->SMTPAuth = true;
        $admin_mail->Username = $mail_config['smtp_user'];
        $admin_mail->Password = $mail_config['smtp_pass'];
        
        // デバッグ出力を常に有効化
        $admin_mail->SMTPDebug = SMTP::DEBUG_LOWLEVEL; // 最大レベルのデバッグ
        $admin_mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug[" . $level . "]: " . $str);
        };
        
        // SSL設定（465ポート用）
        if ($mail_config['smtp_secure'] == 'ssl') {
            $admin_mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            // SSL接続の問題を解決するための設定
            $admin_mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        } else {
            $admin_mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $admin_mail->Port = $mail_config['smtp_port'];
        $admin_mail->CharSet = 'UTF-8';
        
        $admin_mail->setFrom($mail_config['from_email'], "予約システム");
        $admin_mail->addAddress('syo.t.company@gmail.com', '管理者'); // 管理者宛
        $admin_mail->Subject = "【新規予約】{$customer_name}様";
        $admin_mail->Body = $admin_message;
        $admin_mail->isHTML(false);
        
        // タイムアウト設定を追加
        $admin_mail->Timeout = 30; // 30秒
        
        $admin_result = $admin_mail->send();
        error_log("管理者宛メール送信結果: " . ($admin_result ? "成功" : "失敗 - " . $admin_mail->ErrorInfo));
        
        // ログに出力
        error_log("顧客向けメール内容: " . str_replace("\n", "\\n", $customer_message));
        
        return ($customer_result || $admin_result);
        
    } catch (Exception $e) {
        error_log("PHPMailer例外: " . $e->getMessage());
        // メール送信関数でフォールバック
        return false;
    }
}

// ページタイトル
$page_title = "予約完了";

// 追加CSS
$additional_css = ['css/complete.css'];

// アクティブなステップ
$active_step = 'complete';

// ヘッダーの読み込み
include 'includes/header.php';

// 予約ステップを読み込み
include 'includes/booking_steps.php';
?>

<!-- 予約完了メッセージ -->
<div class="container mt-5">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">予約が完了しました</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <p class="mb-0"><strong>予約確認メール:</strong> <?php echo $email_status; ?></p>
                    </div>
                    
                    <h5 class="card-title">予約内容</h5>
                    <table class="table table-bordered">
                        <tr>
                            <th>予約番号</th>
                            <td><?php echo $appointment_id; ?></td>
                        </tr>
                        <tr>
                            <th>予約日時</th>
                            <td><?php echo $date_formatted . ' ' . $appointment['start_time']; ?>～<?php echo $appointment['end_time']; ?></td>
                        </tr>
                        <tr>
                            <th>担当者</th>
                            <td><?php echo htmlspecialchars($staff_name); ?></td>
                        </tr>
                        <tr>
                            <th>予約サービス</th>
                            <td>
                                <ul class="list-unstyled">
                                    <?php foreach ($services as $service): ?>
                                    <li><?php echo htmlspecialchars($service['name']); ?> (<?php echo $service['duration']; ?>分) - ¥<?php echo number_format($service['price']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="font-weight-bold">合計金額: ¥<?php echo number_format($total_price); ?></div>
                            </td>
                        </tr>
                        <tr>
                            <th>お名前</th>
                            <td><?php echo htmlspecialchars($customer_name); ?></td>
                        </tr>
                        <tr>
                            <th>確認コード</th>
                            <td><?php echo $confirmation_code; ?></td>
                        </tr>
                    </table>
                    
                    <p class="text-center mt-4">
                        ご予約ありがとうございます。確認メールをお送りしました。<br>
                        もしメールが届かない場合は、お電話でご連絡ください。
                    </p>
                    
                    <div class="text-center mt-4">
                        <a href="../index.php" class="btn btn-primary">トップページに戻る</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// システム管理者向けデバッグ情報（開発環境でのみ表示）
if (defined('DEV_MODE') && DEV_MODE || $_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['REMOTE_ADDR'] == '127.0.0.1') {
    echo '<div class="container mt-5 developer-info">';
    echo '<h5>開発者向け情報 (本番環境では表示されません)</h5>';
    echo '<div class="alert alert-secondary">';
    echo '<p><strong>現在のPHP設定:</strong><br>';
    echo 'sendmail_path: ' . ini_get('sendmail_path') . '<br>';
    echo 'SMTP: ' . ini_get('SMTP') . '<br>';
    echo 'smtp_port: ' . ini_get('smtp_port') . '<br>';
    echo 'メール送信状態: ' . (isset($_SESSION['booking_email_sent']) && $_SESSION['booking_email_sent'] === true ? '送信済み' : '未送信') . '<br>';
    echo '</p>';
    
    echo '<p><strong>エラーログ:</strong><br>';
    $log_file = "/Applications/XAMPP/xamppfiles/logs/php_error_log";
    if (file_exists($log_file)) {
        $log_content = shell_exec("tail -n 20 " . escapeshellarg($log_file) . " | grep -i 'phpmailer\\|mail\\|smtp'");
        if (empty($log_content)) {
            $log_content = shell_exec("tail -n 20 " . escapeshellarg($log_file));
        }
        echo '<pre>' . htmlspecialchars($log_content) . '</pre>';
    } else {
        echo 'エラーログが見つかりません。';
    }
    echo '</p>';
    
    echo '<p><strong>メール設定:</strong><br>';
    echo 'SMTP Server: ' . htmlspecialchars($mail_config['smtp_server']) . '<br>';
    echo 'SMTP Port: ' . htmlspecialchars($mail_config['smtp_port']) . '<br>';
    echo 'SMTP User: ' . htmlspecialchars($mail_config['smtp_user']) . '<br>';
    echo 'SMTP Secure: ' . htmlspecialchars($mail_config['smtp_secure']) . '<br>';
    echo '</p>';
    echo '</div>';
    echo '</div>';
}

// フッターを読み込み
include 'includes/footer.php';
?> 