<?php
// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../includes/functions.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// セッション開始（セッションがまだ開始されていない場合のみ）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// デバッグログ - セッション開始時のsalon_id
error_log("input_info.php - セッション開始時 - salon_id: " . ($_SESSION['booking_salon_id'] ?? 'なし'));

// セッションデータを取得
$salon_id = $_SESSION['booking_salon_id'] ?? 0;

// salon_idをクッキーにも保存（セッション失効に備えて）
if ($salon_id) {
    setcookie('salon_id', $salon_id, time() + 3600, '/'); // 1時間有効
    error_log("input_info.php - salon_idをクッキーに保存: " . $salon_id);
}

$selected_date = $_SESSION['booking_selected_date'] ?? '';
$selected_staff_id = $_SESSION['booking_selected_staff_id'] ?? 0;
$selected_time = $_SESSION['booking_selected_time'] ?? '';
$selected_services = $_SESSION['booking_services'] ?? [];
$customer_info = $_SESSION['booking_customer_info'] ?? [];

// 必要なデータが不足している場合はリダイレクト
if (empty($salon_id) || empty($selected_date) || empty($selected_staff_id) || empty($selected_time) || empty($selected_services)) {
    $_SESSION['error_message'] = "予約情報が不足しています。日時選択からやり直してください。";
    header('Location: select_datetime.php');
    exit;
}

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
    $stmt = $conn->prepare("SELECT salon_id, name as salon_name FROM salons WHERE salon_id = :salon_id");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $salon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$salon) {
        throw new Exception("サロンが見つかりません");
    }
} catch (Exception $e) {
    error_log("サロン情報取得エラー：" . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: error.php');
    exit;
}

// 選択されたサービスの詳細を取得
$total_duration = array_sum(array_column($selected_services, 'duration'));
$total_price = array_sum(array_column($selected_services, 'price'));

// 選択したスタッフの情報を取得
try {
    $stmt = $conn->prepare("SELECT staff_id, first_name, last_name FROM staff WHERE staff_id = :staff_id");
    $stmt->bindParam(':staff_id', $selected_staff_id);
    $stmt->execute();
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        throw new Exception("スタッフ情報が見つかりません");
    }
} catch (Exception $e) {
    error_log("スタッフ情報取得エラー：" . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: error.php');
    exit;
}

// 日本語の曜日
$days_of_week_jp = ['日', '月', '火', '水', '木', '金', '土'];
$date_obj = new DateTime($selected_date);
$weekday_num = $date_obj->format('w'); // 0（日）～ 6（土）
$date_formatted = $date_obj->format('Y年n月j日') . '（' . $days_of_week_jp[$weekday_num] . '）';

// 予約終了時間を計算
$start_time = $selected_time;
$end_time_obj = new DateTime($selected_date . ' ' . $start_time);
$end_time_obj->modify('+' . $total_duration . ' minutes');
$end_time = $end_time_obj->format('H:i');

$page_title = $salon['salon_name'] . " - お客様情報入力";

// 既存顧客のログイン処理
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $email = $_POST['login_email'] ?? '';
    $password = $_POST['login_password'] ?? '';
    $remember = isset($_POST['remember_login']) ? true : false;
    
    if (empty($email) || empty($password)) {
        $login_error = 'メールアドレスとパスワードを入力してください。';
    } else {
        try {
            // メールアドレスで顧客を検索
            $stmt = $conn->prepare("SELECT customer_id, first_name, last_name, email, password, phone, birthday, gender, notify_email FROM customers WHERE email = :email AND salon_id = :salon_id");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':salon_id', $salon_id);
            $stmt->execute();
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer && password_verify($password, $customer['password'])) {
                // ログイン成功
                $_SESSION['booking_customer_info'] = [
                    'customer_id' => $customer['customer_id'],
                    'first_name' => $customer['first_name'],
                    'last_name' => $customer['last_name'],
                    'email' => $customer['email'],
                    'phone' => $customer['phone'],
                    'birthday' => $customer['birthday'],
                    'gender' => $customer['gender'],
                    'notify_email' => $customer['notify_email'],
                    'is_logged_in' => true
                ];
                
                // ログイン状態を保持する場合
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    $stmt = $conn->prepare("UPDATE customers SET remember_token = :token, last_login = NOW() WHERE customer_id = :customer_id");
                    $stmt->bindParam(':token', $token);
                    $stmt->bindParam(':customer_id', $customer['customer_id']);
                    $stmt->execute();
                    
                    // Cookieを設定（30日間有効）
                    setcookie('customer_remember_token', $token, time() + 30 * 24 * 60 * 60, '/');
                    setcookie('customer_email', $email, time() + 30 * 24 * 60 * 60, '/');
                }
                
                // 確認ページへリダイレクト
                header('Location: confirm.php');
                exit;
            } else {
                $login_error = 'メールアドレスまたはパスワードが正しくありません。';
            }
        } catch (Exception $e) {
            error_log("ログインエラー：" . $e->getMessage());
            $login_error = 'ログイン処理中にエラーが発生しました。';
        }
    }
}

// 新規顧客登録処理
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_submit'])) {
    // 生年月日の処理
    $birth_year = $_POST['birth_year'] ?? '';
    $birth_month = $_POST['birth_month'] ?? '';
    $birth_day = $_POST['birth_day'] ?? '';
    
    $birthday = '';
    if (!empty($birth_year) && !empty($birth_month) && !empty($birth_day)) {
        $birthday = sprintf('%04d-%02d-%02d', $birth_year, $birth_month, $birth_day);
    }

    $customer_info = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'birthday' => $birthday,
        'gender' => $_POST['gender'] ?? '',
        'notify_email' => isset($_POST['notify_email']) ? 1 : 0,
        'source' => 'online',
        'status' => 'active'
    ];
    
    // パスワード処理
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // バリデーション
    if (empty($customer_info['first_name'])) $errors[] = "名前（名）を入力してください";
    if (empty($customer_info['last_name'])) $errors[] = "名前（姓）を入力してください";
    if (empty($customer_info['email'])) $errors[] = "メールアドレスを入力してください";
    if (empty($customer_info['phone'])) $errors[] = "電話番号を入力してください";
    if (empty($customer_info['gender'])) $errors[] = "性別を選択してください";
    if (empty($birthday)) $errors[] = "生年月日を入力してください";
    if (empty($password)) $errors[] = "パスワードを入力してください";
    if (strlen($password) < 8) $errors[] = "パスワードは8文字以上で入力してください";
    if ($password !== $password_confirm) $errors[] = "パスワードと確認用パスワードが一致しません";
    
    // メールアドレスの重複チェック
    if (!empty($customer_info['email'])) {
        $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = :email AND salon_id = :salon_id");
        $stmt->bindParam(':email', $customer_info['email']);
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->execute();
        
        if ($stmt->fetch()) {
            $errors[] = "このメールアドレスは既に登録されています。ログインしてください。";
        }
    }
    
    if (empty($errors)) {
        // パスワードをハッシュ化
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $customer_info['password'] = $hashed_password;
        
        // セッションに保存（確実にセッションに保存されるようにする）
        $_SESSION['booking_customer_info'] = $customer_info;
        
        // デバッグ用ログ
        error_log("Customer info saved to session: " . print_r($customer_info, true));
        
        // セッションの書き込みを確実に行う
        session_write_close();
        session_start();
        
        // 確認ページへリダイレクト
        header('Location: confirm.php');
        exit;
    }
}

// 追加CSSの定義
$additional_css = ['css/input_info.css'];

// 追加JSの定義
$additional_js = ['js/input_info.js'];

// アクティブなステップを設定
$active_step = 'info';

// ヘッダーを読み込み
include 'includes/header.php';

// 予約ステップを読み込み
include 'includes/booking_steps.php';
?>

<div class="info-container">
    <?php if (!empty($errors)): ?>
    <div class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong>エラーがあります：</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <h2>お名前、電話番号などのお客さま情報を入力してください</h2>
    <p class="info-subtitle">予約状況はリアルタイムで変化しております。予約完了ページにて 【ご予約を承りました。】 と表示され初めて予約完了になります。</p>

    <div class="booking-summary">
        <h3 class="service-title">施術内容</h3>
        <div class="summary-details">
            <div class="summary-item">
                <div class="summary-date"><?php echo substr($selected_date, 0, 4); ?>/<?php echo substr($selected_date, 5, 2); ?>/<?php echo substr($selected_date, 8, 2); ?> <?php echo $start_time; ?>～</div>
            </div>
            
            <div class="summary-item service-list">
                <?php foreach ($selected_services as $service): ?>
                <div class="summary-service">
                    <span class="service-name"><?php echo htmlspecialchars($service['name']); ?></span>
                    <span class="service-price">￥<?php echo number_format($service['price']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="summary-item total-price">
                <span>合計</span>
                <span>￥<?php echo number_format($total_price); ?></span>
            </div>
        </div>
    </div>

    <div class="customer-info-section">
        <div class="info-tabs">
            <div class="info-tab-content">
                <!-- 既存顧客用ログインフォーム -->
                <div class="login-section">
                    <h3>以前、WEB予約をされた方</h3>
                    
                    <div class="login-form">
                        <form method="post" id="loginForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <?php if (!empty($login_error)): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($login_error); ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="login_email">メールアドレス</label>
                                <input type="email" id="login_email" name="login_email" class="form-control" placeholder="例) sample@○○.jp" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="login_password">パスワード</label>
                                <input type="password" id="login_password" name="login_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group remember-me">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="remember_login" id="remember_login" value="1">
                                    <span class="checkbox-text">ログインしたままにする</span>
                                </label>
                                <p class="remember-note">※ 「ログインしたままにする」 にチェックをしてログインすると、1か月間ログイン状態が保持されます。複数の方で同一の端末を共用している場合は必ずログアウトを行ってください。</p>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="login_submit" class="btn login-btn">
                                    ログインしてすすむ <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                            
                            <div class="password-reset">
                                <a href="reset_password.php?salon_id=<?php echo urlencode($salon_id); ?>">パスワードをお忘れの方はこちら</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- 新規顧客登録フォーム -->
                <div class="register-section">
                    <h3>初めてご予約される方</h3>
                    <form method="post" id="customerForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <!-- 氏名 -->
                        <div class="name-group">
                            <div class="form-group">
                                <label for="last_name">姓<span class="required">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>" id="last_name" name="last_name" value="<?php echo htmlspecialchars($customer_info['last_name'] ?? ''); ?>" required>
                                <?php if (isset($errors['last_name'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['last_name']; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="first_name">名<span class="required">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>" id="first_name" name="first_name" value="<?php echo htmlspecialchars($customer_info['first_name'] ?? ''); ?>" required>
                                <?php if (isset($errors['first_name'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['first_name']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">メールアドレス <span class="required">*</span></label>
                            <input type="email" class="form-control <?php echo in_array('メールアドレスを入力してください', $errors) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($customer_info['email'] ?? ''); ?>" required>
                        </div>

                        <div class="password-group">
                            <div class="form-group">
                                <label for="password">パスワード <span class="required">*</span></label>
                                <input type="password" class="form-control <?php echo in_array('パスワードを入力してください', $errors) || in_array('パスワードは8文字以上で入力してください', $errors) ? 'is-invalid' : ''; ?>" id="password" name="password" required minlength="8">
                                <p class="form-hint">※8文字以上で入力してください</p>
                            </div>
                            <div class="form-group">
                                <label for="password_confirm">パスワード（確認用） <span class="required">*</span></label>
                                <input type="password" class="form-control <?php echo in_array('パスワードが一致しません', $errors) ? 'is-invalid' : ''; ?>" id="password_confirm" name="password_confirm" required minlength="8">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone">電話番号 <span class="required">*</span></label>
                            <input type="tel" class="form-control <?php echo in_array('電話番号を入力してください', $errors) ? 'is-invalid' : ''; ?>" id="phone" name="phone" value="<?php echo htmlspecialchars($customer_info['phone'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>性別 <span class="required">*</span></label>
                            <div class="radio-group <?php echo in_array('性別を選択してください', $errors) ? 'is-invalid' : ''; ?>">
                                <label class="radio-label">
                                    <input type="radio" name="gender" value="male" <?php echo (isset($customer_info['gender']) && $customer_info['gender'] === 'male') ? 'checked' : ''; ?> required>
                                    <span class="radio-text">男性</span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="gender" value="female" <?php echo (isset($customer_info['gender']) && $customer_info['gender'] === 'female') ? 'checked' : ''; ?>>
                                    <span class="radio-text">女性</span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="gender" value="other" <?php echo (isset($customer_info['gender']) && $customer_info['gender'] === 'other') ? 'checked' : ''; ?>>
                                    <span class="radio-text">その他</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>生年月日 <span class="required">*</span></label>
                            <div class="date-group <?php echo in_array('生年月日を入力してください', $errors) ? 'is-invalid' : ''; ?>">
                                <?php
                                $birth_parts = ['', '', ''];
                                if (!empty($customer_info['birthday'])) {
                                    $birth_parts = explode('-', $customer_info['birthday']);
                                }
                                ?>
                                <div class="form-group">
                                    <select name="birth_year" id="birth_year" class="form-control" required>
                                        <option value="">年</option>
                                        <?php
                                        $current_year = (int)date('Y');
                                        for ($year = $current_year - 100; $year <= $current_year; $year++) {
                                            $selected = (isset($birth_parts[0]) && (int)$birth_parts[0] === $year) ? 'selected' : '';
                                            echo "<option value=\"$year\" $selected>$year</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <select name="birth_month" id="birth_month" class="form-control" required>
                                        <option value="">月</option>
                                        <?php
                                        for ($month = 1; $month <= 12; $month++) {
                                            $selected = (isset($birth_parts[1]) && (int)$birth_parts[1] === $month) ? 'selected' : '';
                                            echo "<option value=\"$month\" $selected>$month</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <select name="birth_day" id="birth_day" class="form-control" required>
                                        <option value="">日</option>
                                        <?php
                                        for ($day = 1; $day <= 31; $day++) {
                                            $selected = (isset($birth_parts[2]) && (int)$birth_parts[2] === $day) ? 'selected' : '';
                                            echo "<option value=\"$day\" $selected>$day</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="notify_email" value="1" <?php echo (isset($customer_info['notify_email']) && $customer_info['notify_email']) ? 'checked' : ''; ?>>
                                <span class="checkbox-text">お得な情報やキャンペーンのお知らせをメールで受け取る</span>
                            </label>
                        </div>

                        <div class="terms-agreement">
                            <p>予約することで、<a href="#" target="_blank">利用規約</a>および<a href="#" target="_blank">プライバシーポリシー</a>に同意したものとみなされます。</p>
                        </div>

                        <div class="form-actions">
                            <a href="select_datetime.php" class="btn back-btn">
                                <i class="fas fa-chevron-left"></i> 戻る
                            </a>
                            <button type="submit" name="register_submit" class="btn next-btn">
                                確認画面へ <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// フッターを読み込み
include 'includes/footer.php';
?> 