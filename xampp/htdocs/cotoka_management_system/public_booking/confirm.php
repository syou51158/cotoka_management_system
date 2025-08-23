<?php
// 出力バッファリングを有効化（ヘッダー送信前に出力を防止）
ob_start();

// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../includes/functions.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// エラー表示設定（開発モード）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// エラーログ設定
ini_set('log_errors', 1);
ini_set('error_log', '/Applications/XAMPP/xamppfiles/logs/php_error_log');

// セッション開始（セッションがまだ開始されていない場合のみ）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// デバッグ情報
error_log("確認ページが読み込まれました。セッション情報: " . print_r($_SESSION, true));

// セッションデータを取得
$salon_id = $_SESSION['booking_salon_id'] ?? 0;
$selected_date = $_SESSION['booking_selected_date'] ?? '';
$selected_staff_id = $_SESSION['booking_selected_staff_id'] ?? 0;
$selected_time = $_SESSION['booking_selected_time'] ?? '';
$selected_services = $_SESSION['booking_services'] ?? [];

// POSTデータから顧客情報を取得
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POSTリクエストのデバッグ情報を記録
    error_log("POST受信: " . print_r($_POST, true));
    
    // 生年月日の値を安全に取得
    $birth_year = isset($_POST['birth_year']) ? $_POST['birth_year'] : '';
    $birth_month = isset($_POST['birth_month']) ? $_POST['birth_month'] : '';
    $birth_day = isset($_POST['birth_day']) ? $_POST['birth_day'] : '';
    
    // 生年月日が全て入力されている場合のみフォーマット
    $birthday = '';
    if ($birth_year && $birth_month && $birth_day) {
        $birthday = sprintf('%04d-%02d-%02d', $birth_year, $birth_month, $birth_day);
    }

    // 性別の値を検証
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
    if (!in_array($gender, ['male', 'female', 'other'])) {
        $gender = null; // 無効な値の場合はnullを設定
    }
    
    $customer_info = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'birthday' => !empty($birthday) ? $birthday : null,
        'gender' => $gender,
        'notify_email' => isset($_POST['notify_email']) ? 1 : 0,
        'source' => 'online',
        'status' => 'active'
    ];
    
    // セッションに保存
    $_SESSION['booking_customer_info'] = $customer_info;
} else {
    $customer_info = $_SESSION['booking_customer_info'] ?? [];
}

// 必要なデータが不足している場合はリダイレクト
if (empty($salon_id) || empty($selected_date) || empty($selected_staff_id) || empty($selected_time) || empty($selected_services)) {
    $_SESSION['error_message'] = "予約情報が不足しています。日時選択からやり直してください。";
    header('Location: select_datetime.php');
    exit;
}

if (empty($customer_info)) {
    $_SESSION['error_message'] = "お客様情報が未入力です。情報を入力してください。";
    header('Location: input_info.php');
    exit;
}

// 日付のフォーマット
$date_formatted = date('Y年n月j日', strtotime($selected_date));

// 曜日の取得
$days_of_week_jp = ['日', '月', '火', '水', '木', '金', '土'];
$date_obj = new DateTime($selected_date);
$weekday_num = $date_obj->format('w'); // 0（日）～ 6（土）
$date_formatted .= '（' . $days_of_week_jp[$weekday_num] . '）';

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    error_log("データベース接続エラー：" . $e->getMessage());
    $_SESSION['error_message'] = "システムエラーが発生しました。しばらく時間をおいて再度お試しください。";
    header('Location: error.php');
    exit;
}

// サロン情報を取得
try {
    $stmt = $conn->prepare("SELECT salon_id, name as salon_name, tenant_id FROM salons WHERE salon_id = :salon_id");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $salon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$salon) {
        throw new Exception("サロンが見つかりません");
    }
    
    // テナントIDを保存
    $tenant_id = $salon['tenant_id'];
} catch (Exception $e) {
    error_log("サロン情報取得エラー：" . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: error.php');
    exit;
}

// 選択したスタッフの情報を取得
try {
    $stmt = $conn->prepare("SELECT staff_id, first_name, last_name FROM staff WHERE staff_id = :staff_id AND salon_id = :salon_id");
    $stmt->bindParam(':staff_id', $selected_staff_id);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff && $selected_staff_id != 0) {
        throw new Exception("スタッフ情報が見つかりません");
    }
} catch (Exception $e) {
    error_log("スタッフ情報取得エラー：" . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: error.php');
    exit;
}

// サービス情報の処理
try {
    // 合計時間と合計金額の計算
    $total_duration = array_sum(array_column($selected_services, 'duration'));
    $total_price = array_sum(array_column($selected_services, 'price'));
    
    // 開始時間と終了時間の計算
    $start_time = $selected_time;
    $end_time_obj = new DateTime($selected_date . ' ' . $start_time);
    $end_time_obj->modify('+' . $total_duration . ' minutes');
    $end_time = $end_time_obj->format('H:i');
    
} catch (Exception $e) {
    error_log("予約情報処理エラー：" . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: error.php');
    exit;
}

// エラーメッセージの初期化
$errors = [];

// フォームが送信された場合の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
    error_log("予約確定ボタンが押されました: " . print_r($_POST, true));
    
    try {
        // 最小限の検証 - 同意確認
        if (!isset($_POST['agree']) || $_POST['agree'] != '1') {
            error_log("利用規約への同意がありません");
            throw new Exception('利用規約に同意してください。');
        }
        
        // 詳細なデバッグ情報
        error_log("POST情報: " . json_encode($_POST));
        error_log("セッション情報: " . json_encode($_SESSION));
        
        // セッションからデータを取得
        $salon_id = $_SESSION['booking_salon_id'] ?? 0;
        $selected_date = $_SESSION['booking_selected_date'] ?? '';
        $selected_staff_id = $_SESSION['booking_selected_staff_id'] ?? 0;
        $selected_time = $_SESSION['booking_selected_time'] ?? '';
        $selected_services = $_SESSION['booking_services'] ?? [];
        
        error_log("セッションデータ取得: salon_id=$salon_id, date=$selected_date, time=$selected_time, staff=$selected_staff_id, services=" . print_r($selected_services, true));
        
        // データの存在確認
        if (!$salon_id || !$selected_date || !$selected_time || empty($selected_services)) {
            error_log("予約データが不完全です");
            throw new Exception('予約情報が不完全です。最初からやり直してください。');
        }
        
        // 新しいデバッグログを追加
        error_log("POSTデータ検証通過: 必要なデータがすべて存在します");
        
        // POSTデータをログ出力（機密情報を除く）
        error_log("予約フォームが送信されました - POSTデータ: " . json_encode(array_diff_key($_POST, ['booking_token' => 1])));
        
        // 基本的な顧客情報をhidden入力から取得
        $customerInfo = [
            'email' => $_POST['email'] ?? '',
            'last_name' => $_POST['last_name'] ?? '',
            'first_name' => $_POST['first_name'] ?? '',
            'phone' => $_POST['phone'] ?? ''
        ];
        
        // 二重予約チェック - 同じスタッフ、同じ時間帯に既に予約が入っていないか確認
        $overlapCheck = $conn->prepare("
            SELECT COUNT(*) as overlap_count 
            FROM appointments 
            WHERE staff_id = ? 
            AND appointment_date = ? 
            AND (
                (start_time <= ? AND end_time > ?) OR
                (start_time < ? AND end_time >= ?) OR
                (start_time >= ? AND end_time <= ?)
            )
            AND status NOT IN ('cancelled', 'no_show')
        ");
        
        // スケジュールの重複チェック実行
        $overlapCheck->execute([
            $selected_staff_id,
            $selected_date,
            $start_time,
            $start_time,
            $end_time,
            $end_time,
            $start_time,
            $end_time
        ]);
        
        $overlapResult = $overlapCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($overlapResult['overlap_count'] > 0) {
            // 重複予約が見つかった場合
            error_log("二重予約エラー: スタッフID={$selected_staff_id}, 日付={$selected_date}, 開始時間={$start_time}, 終了時間={$end_time}");
            throw new Exception("申し訳ありません。選択した時間はすでに予約されています。別の時間を選択してください。");
        }
        
        // セッションに存在する他の顧客情報を保持
        if (isset($_SESSION['booking_customer_info']) && is_array($_SESSION['booking_customer_info'])) {
            // パスワードなどの重要な情報を保持
            if (isset($_SESSION['booking_customer_info']['password'])) {
                $customerInfo['password'] = $_SESSION['booking_customer_info']['password'];
            }
            
            // その他のオプションフィールド
            foreach (['birthday', 'gender', 'notify_email', 'source', 'status'] as $field) {
                if (isset($_SESSION['booking_customer_info'][$field])) {
                    $customerInfo[$field] = $_SESSION['booking_customer_info'][$field];
                }
            }
        }
        
        // 顧客情報をログに出力
        error_log("処理する顧客情報: " . json_encode($customerInfo));
        
        // 必須フィールドの確認
        if (empty($customerInfo['email']) || empty($customerInfo['last_name']) || empty($customerInfo['first_name'])) {
            echo '<h3>エラー: 必須情報が不足しています</h3>';
            echo '<p>お客様情報が正しく送信されませんでした。もう一度<a href="input_info.php">お客様情報入力画面</a>からやり直してください。</p>';
            echo '<p>デバッグ情報:<br>POST: ' . htmlspecialchars(json_encode($_POST)) . '<br>セッション: ' . htmlspecialchars(json_encode($_SESSION)) . '</p>';
            exit;
        }
        
        // 予約番号を生成
        $confirmation_code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        
        // 各種データを出力
        error_log("顧客情報: " . json_encode($customerInfo));
        error_log("予約情報: date={$selected_date}, time={$selected_time}, staff={$selected_staff_id}");
        error_log("サービス: " . json_encode($selected_services));
        
        // 顧客が存在するか確認
        $customer_id = null;
        $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = ? AND salon_id = ?");
        $stmt->execute([$customerInfo['email'], $salon_id]);
        $existingCustomer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingCustomer) {
            // 既存の顧客
            $customer_id = $existingCustomer['customer_id'];
            
            // 顧客情報を更新（シンプルな更新クエリ）
            $updateStmt = $conn->prepare("
                UPDATE customers SET 
                    first_name = ?,
                    last_name = ?,
                    phone = ?,
                    updated_at = NOW()
                WHERE customer_id = ?
            ");
            $updateStmt->execute([
                $customerInfo['first_name'],
                $customerInfo['last_name'],
                $customerInfo['phone'],
                $customer_id
            ]);
        } else {
            // 新規顧客
            $insertStmt = $conn->prepare("
                INSERT INTO customers (
                    salon_id, email, first_name, last_name, phone, 
                    password, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $password = !empty($customerInfo['password']) 
                ? password_hash($customerInfo['password'], PASSWORD_DEFAULT) 
                : null;
            
            $insertStmt->execute([
                $salon_id,
                $customerInfo['email'],
                $customerInfo['first_name'],
                $customerInfo['last_name'],
                $customerInfo['phone'],
                $password
            ]);
            
            $customer_id = $conn->lastInsertId();
        }
        
        // 最初のサービスIDを取得（必須フィールドのため）
        $first_service_id = $selected_services[0]['service_id'] ?? 0;
        
        if (!$first_service_id) {
            throw new Exception("サービス情報が不足しています");
        }
        
        // 予約を作成（テーブルの構造に合わせて修正）
        $appointmentStmt = $conn->prepare("
            INSERT INTO appointments (
                salon_id, tenant_id, customer_id, staff_id, service_id, appointment_date,
                start_time, end_time, status, 
                appointment_type, source, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'customer', 'online', '予約システムから作成', NOW(), NOW())
        ");
        
        $appointmentStmt->execute([
            $salon_id,
            $tenant_id,
            $customer_id,
            $selected_staff_id,
            $first_service_id,
            $selected_date,
            $start_time,
            $end_time
        ]);
        
        if (!$appointmentStmt) {
            error_log("予約作成クエリ実行エラー: " . $conn->errorInfo()[2]);
            throw new Exception("予約作成に失敗しました。");
        }
        
        // 予約IDを取得
        $appointment_id = $conn->lastInsertId();
        
        if (!$appointment_id) {
            error_log("予約IDの取得に失敗しました");
            throw new Exception("予約IDの取得に失敗しました。");
        }
        
        // 予約サービスを登録
        foreach ($selected_services as $service) {
            $serviceStmt = $conn->prepare("
                INSERT INTO appointment_services (
                    appointment_id, service_id, price, duration,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            
            $serviceStmt->execute([
                $appointment_id,
                $service['service_id'],
                $service['price'],
                $service['duration']
            ]);
        }
        
        // 顧客の最終訪問日を更新
        $updateLastVisitSql = "UPDATE customers SET last_visit_date = CURDATE(), visit_count = visit_count + 1 WHERE customer_id = ?";
        $stmt = $conn->prepare($updateLastVisitSql);
        $stmt->execute([$customer_id]);
        error_log("顧客情報を更新: customer_id={$customer_id}");
        
        // 予約IDをセッションに保存
        $_SESSION['appointment_id'] = $appointment_id;
        $_SESSION['booking_appointment_id'] = $appointment_id;
        
        // デバッグログに記録
        error_log("予約処理完了: appointment_id={$appointment_id}, セッションに保存しました");
        
        // セッションをいったん閉じて確実に書き込む
        session_write_close();
        
        // 出力バッファをクリア
        if (ob_get_length()) {
            ob_end_clean();
        }
        
        // リダイレクト先URL
        $redirect_url = 'complete.php?id=' . $appointment_id;
        error_log("complete.phpへリダイレクトします: " . $redirect_url);

        // 安全なリダイレクト
        redirect_to($redirect_url);
        
    } catch (Exception $e) {
        // 詳細なエラー情報を記録
        error_log("予約エラー: " . $e->getMessage());
        error_log("スタックトレース: " . $e->getTraceAsString());
        
        // セッションにエラーメッセージを保存
        $_SESSION['error_message'] = $e->getMessage();
        
        // エラーメッセージを表示
        $errors[] = $e->getMessage();
        
        // エラーをより詳細に表示（開発環境のみ）
        if (defined('DEV_MODE') && DEV_MODE === true) {
            echo "<div class='error-box'>";
            echo "<h3>エラーが発生しました</h3>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p>詳細はサーバーログを確認してください</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            echo "</div>";
        }
    }
}

// フォームが表示される前に実行されるデバッグ
error_log("予約確認ページのフォームが表示されました。セッション: " . print_r($_SESSION, true));

// ページタイトルの設定
$page_title = $salon['salon_name'] . " - 予約確認";

// 追加CSSの定義
$additional_css = ['css/confirm.css'];

// 追加JSを削除
//$additional_js = ['js/confirm.js'];

// アクティブなステップを設定
$active_step = 'confirm';

// ヘッダーを読み込み
include 'includes/header.php';

// 予約ステップを読み込み
include 'includes/booking_steps.php';
?>

<div class="confirm-container">
    <?php if (!empty($errors)): ?>
    <div class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong>エラーが発生しました：</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <div class="confirmation-card">
        <h3>予約内容の確認</h3>
        <p class="confirmation-intro">以下の内容で予約を確定します。内容をご確認の上、「予約を確定する」ボタンを押してください。</p>
        
        <div class="confirmation-section">
            <h4>予約詳細</h4>
            <div class="confirm-item">
                <div class="confirm-label">日時</div>
                <div class="confirm-value"><?php echo $date_formatted; ?> <?php echo $start_time; ?>～<?php echo $end_time; ?></div>
            </div>
            
            <div class="confirm-item">
                <div class="confirm-label">スタッフ</div>
                <div class="confirm-value staff-info">
                    <?php if (!empty($staff['photo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($staff['photo_url']); ?>" alt="<?php echo htmlspecialchars($staff['last_name'] . ' ' . $staff['first_name']); ?>" class="staff-photo">
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($staff['last_name'] . ' ' . $staff['first_name']); ?></span>
                </div>
            </div>
            
            <div class="confirm-item">
                <div class="confirm-label">メニュー</div>
                <div class="confirm-value">
                    <div class="services-list">
                        <?php foreach ($selected_services as $service): ?>
                        <div class="service-item">
                            <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                            <div class="service-details">
                                <span class="service-duration"><?php echo $service['duration']; ?>分</span>
                                <span class="service-price">￥<?php echo number_format($service['price']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="total-price">
                        <span>合計金額</span>
                        <span>￥<?php echo number_format($total_price); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="confirmation-section">
            <h4>お客様情報</h4>
            <div class="confirm-item">
                <div class="confirm-label">お名前</div>
                <div class="confirm-value"><?php echo htmlspecialchars($customer_info['last_name'] . ' ' . $customer_info['first_name']); ?></div>
            </div>
            
            <div class="confirm-item">
                <div class="confirm-label">メールアドレス</div>
                <div class="confirm-value"><?php echo htmlspecialchars($customer_info['email']); ?></div>
            </div>
            
            <div class="confirm-item">
                <div class="confirm-label">電話番号</div>
                <div class="confirm-value"><?php echo htmlspecialchars($customer_info['phone']); ?></div>
            </div>
            
            <div class="confirm-item">
                <div class="confirm-label">性別</div>
                <div class="confirm-value">
                    <?php
                    $gender_display = '';
                    switch ($customer_info['gender']) {
                        case 'male': $gender_display = '男性'; break;
                        case 'female': $gender_display = '女性'; break;
                        case 'other': $gender_display = 'その他'; break;
                        default: $gender_display = '未設定';
                    }
                    echo $gender_display;
                    ?>
                </div>
            </div>
            
            <?php if (!empty($customer_info['birthday'])): ?>
            <div class="confirm-item">
                <div class="confirm-label">生年月日</div>
                <div class="confirm-value"><?php echo date('Y年n月j日', strtotime($customer_info['birthday'])); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="confirm-item">
                <div class="confirm-label">お知らせメール</div>
                <div class="confirm-value"><?php echo $customer_info['notify_email'] ? '希望する' : '希望しない'; ?></div>
            </div>
        </div>
        
        <!-- POSTメソッドでフォームを送信、JavaScriptを極力排除 -->
        <form method="post" id="bookingForm" action="">
            <div class="terms-agreement">
                <label class="checkbox-label">
                    <input type="checkbox" id="agree_terms" name="agree" value="1" required>
                    <span>利用規約に同意します</span>
                </label>
            </div>
            
            <div class="form-actions">
                <a href="input_info.php" class="btn back-btn">
                    <i class="fas fa-chevron-left"></i> 戻る
                </a>
                <button type="submit" name="confirm_booking" class="btn confirm-btn" id="confirmBtn">
                    予約を確定する <i class="fas fa-check"></i>
                </button>
            </div>
            
            <!-- 最小限の情報のみ保持 -->
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($customer_info['email'] ?? ''); ?>">
            <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($customer_info['last_name'] ?? ''); ?>">
            <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($customer_info['first_name'] ?? ''); ?>">
            <input type="hidden" name="phone" value="<?php echo htmlspecialchars($customer_info['phone'] ?? ''); ?>">
            <input type="hidden" name="booking_token" value="<?php echo md5(time() . mt_rand()); ?>">
        </form>
        
        <div class="confirmation-notes">
            <p><i class="fas fa-info-circle"></i> 予約確定後、ご登録いただいたメールアドレスに予約確認メールが送信されます。</p>
            <p><i class="fas fa-exclamation-triangle"></i> キャンセルは予約日の2日前までにお電話でご連絡ください。</p>
        </div>
    </div>
</div>

<script>
// 最小限のJavaScriptで実装
document.addEventListener('DOMContentLoaded', function() {
    // 同意チェックボックスと確定ボタンの要素を取得
    var agreeTerms = document.getElementById('agree_terms');
    var confirmBtn = document.getElementById('confirmBtn');
    
    // 初期状態では利用規約に同意していなければボタンを無効化
    confirmBtn.disabled = !agreeTerms.checked;
    
    // チェックボックスの状態が変わったら確定ボタンの有効/無効を切り替え
    agreeTerms.addEventListener('change', function() {
        confirmBtn.disabled = !this.checked;
    });
});
</script>

<?php
/**
 * 予約確認メールを送信する関数
 */
function send_confirmation_email($customer, $salon, $staff, $services, $date, $start_time, $end_time, $total_price, $confirmation_code) {
    try {
        error_log("メール送信を開始します: " . $customer['email']);
        
        // メール送信の設定
        $mail_host = 'smtp.lolipop.jp';
        $mail_port = 465;
        $mail_username = 'cms@cotoka.jp';
        $mail_password = 'Syou108810--'; // 本番環境ではより安全な方法でパスワードを管理することをお勧めします
        $mail_from = 'cms@cotoka.jp';
        $mail_from_name = $salon['salon_name'];
    
        // メールの宛先
        $to = $customer['email'];
        
        // 件名
        $subject = '【' . $salon['salon_name'] . '】ご予約確認 - 予約番号: ' . $confirmation_code;
        
        // 日付のフォーマット
        $date_formatted = date('Y年n月j日', strtotime($date));
        $days_of_week_jp = ['日', '月', '火', '水', '木', '金', '土'];
        $weekday_num = date('w', strtotime($date));
        $date_formatted .= '（' . $days_of_week_jp[$weekday_num] . '）';
        
        // メール本文の作成
        $message = $customer['last_name'] . ' ' . $customer['first_name'] . " 様\n\n";
        $message .= $salon['salon_name'] . "をご予約いただき、誠にありがとうございます。\n";
        $message .= "以下の内容でご予約を承りましたのでご確認ください。\n\n";
        $message .= "【予約番号】\n" . $confirmation_code . "\n\n";
        $message .= "【予約内容】\n";
        $message .= "日時: " . $date_formatted . " " . $start_time . "～" . $end_time . "\n";
        
        if (!empty($staff)) {
            $message .= "担当: " . $staff['last_name'] . ' ' . $staff['first_name'] . "\n";
        } else {
            $message .= "担当: 指定なし\n";
        }
        
        $message .= "\n【ご予約メニュー】\n";
        foreach ($services as $service) {
            $message .= $service['name'] . " (" . $service['duration'] . "分) - ¥" . number_format($service['price']) . "\n";
        }
        $message .= "\n合計金額: ¥" . number_format($total_price) . "\n\n";
        
        $message .= "【お客様情報】\n";
        $message .= "お名前: " . $customer['last_name'] . ' ' . $customer['first_name'] . "\n";
        $message .= "メールアドレス: " . $customer['email'] . "\n";
        $message .= "電話番号: " . $customer['phone'] . "\n";
        
        // キャンセルポリシーなどの追加情報
        $message .= "\n【ご予約のキャンセルについて】\n";
        $message .= "ご予約のキャンセルや変更は、予約日の2日前までにお電話にてご連絡ください。\n";
        $message .= "それ以降のキャンセルはキャンセル料が発生する場合がございます。\n\n";
        
        $message .= "何かご不明な点がございましたら、お気軽にお問い合わせください。\n";
        $message .= "ご来店を心よりお待ちしております。\n\n";
        $message .= $salon['salon_name'] . "\n";
    
        // 管理者への通知メール内容
        $admin_email = $mail_from; // 管理者メールアドレス
        $admin_subject = '【新規予約】' . $customer['last_name'] . ' ' . $customer['first_name'] . ' 様 - ' . $date_formatted;
        $admin_message = "新しい予約が入りました。\n\n" . $message;
        
        // 簡易メール送信を優先して使用
        return send_mail_with_mb_send_mail($to, $subject, $message, $mail_from, $mail_from_name, $admin_email, $admin_subject, $admin_message);
        
    } catch (Exception $e) {
        error_log('メール送信処理エラー: ' . $e->getMessage());
        return false;
    }
}

/**
 * mb_send_mailを使用してメールを送信する補助関数
 */
function send_mail_with_mb_send_mail($to, $subject, $message, $mail_from, $mail_from_name, $admin_email, $admin_subject, $admin_message) {
    // ヘッダーの設定
    $headers = "From: " . mb_encode_mimeheader($mail_from_name, "UTF-8") . " <" . $mail_from . ">\r\n";
    $headers .= "Reply-To: " . $mail_from . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // ユーザーへのメール送信
    $result = mb_send_mail($to, mb_encode_mimeheader($subject, "UTF-8"), $message, $headers);
    
    // 管理者へのメール送信
    mb_send_mail($admin_email, mb_encode_mimeheader($admin_subject, "UTF-8"), $admin_message, $headers);
    
    return $result;
}

/**
 * 安全に指定URLにリダイレクトする関数
 */
function redirect_to($url) {
    error_log("リダイレクト: " . $url);
    
    if (!headers_sent()) {
        // 通常のリダイレクト
        header("Location: " . $url);
    } else {
        // ヘッダーが送信済みの場合はJavaScriptでリダイレクト
        echo "<script>window.location.href='" . $url . "';</script>";
    }
    
    // より確実にするためHTMLでもリダイレクト
    echo "<meta http-equiv='refresh' content='0;url=" . $url . "'>";
    echo "<div style='padding:20px;text-align:center;'>ページ移動中です。自動的に移動しない場合は<a href='" . $url . "'>こちら</a>をクリックしてください。</div>";
    exit;
}

// フッターを読み込み
include 'includes/footer.php';
?>