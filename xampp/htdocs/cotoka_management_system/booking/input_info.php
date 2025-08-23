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

// POSTデータとセッションデータの取得
$salon_id = $_SESSION['booking_salon_id'] ?? 0;

// POSTからのデータを優先し、なければセッションから取得
$selected_date = $_POST['selected_date'] ?? $_SESSION['booking_selected_date'] ?? '';
$selected_staff_id = $_POST['selected_staff_id'] ?? $_SESSION['booking_selected_staff_id'] ?? 0;
$selected_time = $_POST['selected_time'] ?? $_SESSION['booking_selected_time'] ?? '';

// POSTで受け取った値をセッションに保存
if (isset($_POST['selected_date'])) $_SESSION['booking_selected_date'] = $_POST['selected_date'];
if (isset($_POST['selected_staff_id'])) $_SESSION['booking_selected_staff_id'] = $_POST['selected_staff_id'];
if (isset($_POST['selected_time'])) $_SESSION['booking_selected_time'] = $_POST['selected_time'];

$selected_services = $_SESSION['booking_services'] ?? [];
$customer_info = $_SESSION['booking_customer_info'] ?? [];

// フォームからのデータ処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['first_name'], $_POST['last_name'])) {
    // POSTデータから顧客情報を取得 (顧客情報フォームからの送信)
    $customer_info = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'birthdate' => $_POST['birthdate'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'notes' => $_POST['notes'] ?? '',
        'notify_email' => isset($_POST['notify_email']) ? 1 : 0
    ];
    
    // セッションに保存
    $_SESSION['booking_customer_info'] = $customer_info;
    
    // 確認ページへリダイレクト
    header('Location: confirm.php');
    exit;
}

// 必要なデータが不足している場合はリダイレクト
if (empty($salon_id) || empty($selected_date) || empty($selected_staff_id) || empty($selected_time) || empty($selected_services)) {
    header('Location: select_datetime.php');
    exit;
}

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    $error_message = "データベース接続エラー：" . $e->getMessage();
    exit($error_message);
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
    $error_message = "サロン情報取得エラー：" . $e->getMessage();
    exit($error_message);
}

// 選択されたサービスの詳細を取得
$total_duration = 0;
$total_price = 0;
$selected_service_details = [];

try {
    // サービスIDをカンマ区切りの文字列に変換
    $service_ids = implode(',', array_map('intval', $selected_services));
    
    $stmt = $conn->prepare("
        SELECT service_id, name, duration, price 
        FROM services 
        WHERE service_id IN ($service_ids) AND salon_id = :salon_id
    ");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $selected_service_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 合計所要時間と価格を計算
    foreach ($selected_service_details as $service) {
        $total_duration += (int)$service['duration'];
        $total_price += (int)$service['price'];
    }
} catch (Exception $e) {
    $error_message = "サービス詳細取得エラー：" . $e->getMessage();
    exit($error_message);
}

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
    $error_message = "スタッフ情報取得エラー：" . $e->getMessage();
    exit($error_message);
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
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #e91e63;
            --primary-dark: #c2185b;
            --primary-light: #f48fb1;
            --secondary-color: #f8bbd0;
        }
        
        body {
            font-family: 'Hiragino Kaku Gothic Pro', 'メイリオ', sans-serif;
            background-color: #f8f9fc;
        }
        
        .booking-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 0;
            text-align: center;
        }
        
        .booking-logo {
            max-height: 60px;
            margin-bottom: 10px;
        }
        
        .booking-steps {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            padding: 0;
            list-style: none;
        }
        
        .booking-steps li {
            padding: 10px 15px;
            border-radius: 20px;
            margin: 0 5px;
            background-color: #f1f1f1;
            color: #777;
            font-size: 0.9rem;
        }
        
        .booking-steps li.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .booking-steps li i {
            margin-right: 5px;
        }
        
        .form-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .form-container h3 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .reservation-summary {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
        }
        
        .summary-item {
            display: flex;
            margin-bottom: 10px;
        }
        
        .summary-label {
            flex: 0 0 100px;
            color: #666;
            font-weight: 500;
        }
        
        .summary-value {
            flex: 1;
            color: #333;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h4 {
            font-size: 1.2rem;
            color: #555;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #eee;
        }
        
        .form-group label {
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            border-radius: 5px;
            font-size: 1rem;
            padding: 10px 15px;
            height: auto;
            border: 1px solid #ddd;
        }
        
        .form-control:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 0.2rem rgba(233, 30, 99, 0.25);
        }
        
        .required-label {
            color: var(--primary-color);
            font-weight: bold;
            margin-left: 5px;
        }
        
        .custom-radio .custom-control-input:checked ~ .custom-control-label::before {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .custom-checkbox .custom-control-input:checked ~ .custom-control-label::before {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .action-buttons {
            position: sticky;
            bottom: 0;
            background-color: white;
            padding: 15px 0;
            box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }
        
        .back-btn {
            color: var(--primary-color);
            background-color: transparent;
            border: 1px solid var(--primary-color);
        }
        
        .back-btn:hover {
            background-color: rgba(233, 30, 99, 0.1);
            color: var(--primary-dark);
        }
        
        .next-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .next-btn:hover {
            background-color: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        @media (max-width: 767px) {
            .booking-steps {
                flex-direction: column;
                align-items: center;
                margin-bottom: 30px;
            }
            
            .booking-steps li {
                margin-bottom: 10px;
                width: 80%;
                text-align: center;
            }
            
            .form-container {
                padding: 15px;
            }
            
            .summary-item {
                flex-direction: column;
            }
            
            .summary-label {
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <header class="booking-header">
        <div class="container">
            <h1 class="m-0">
                <img src="../assets/images/logo.png" alt="<?php echo htmlspecialchars($salon['salon_name']); ?>" class="booking-logo">
                ONLINE BOOKING SERVICE
            </h1>
        </div>
    </header>

    <div class="container py-4">
        <ul class="booking-steps">
            <li><i class="fas fa-list"></i> コース選択</li>
            <li><i class="far fa-calendar-alt"></i> 日時選択</li>
            <li class="active"><i class="fas fa-user"></i> 情報入力</li>
            <li><i class="fas fa-check"></i> 予約確認</li>
            <li><i class="fas fa-check-circle"></i> 予約完了</li>
        </ul>
        
        <div class="form-container">
            <h3 class="text-center mb-4">お客様情報の入力</h3>
            
            <div class="reservation-summary">
                <h5 class="mb-3"><i class="fas fa-clipboard-list mr-2"></i>予約内容</h5>
                <div class="summary-item">
                    <div class="summary-label">日付：</div>
                    <div class="summary-value"><?php echo $date_formatted; ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">時間：</div>
                    <div class="summary-value"><?php echo $selected_time; ?>～<?php echo $end_time; ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">担当：</div>
                    <div class="summary-value"><?php echo htmlspecialchars($staff['last_name'] . ' ' . $staff['first_name']); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">コース：</div>
                    <div class="summary-value">
                        <?php foreach ($selected_service_details as $index => $service): ?>
                            <?php if ($index > 0) echo '<br>'; ?>
                            <?php echo htmlspecialchars($service['name']); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">料金：</div>
                    <div class="summary-value">¥<?php echo number_format($total_price); ?></div>
                </div>
            </div>
            
            <form id="customerForm" action="input_info.php" method="post">
                <div class="form-section">
                    <h4><i class="fas fa-user mr-2"></i>お客様情報</h4>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="last_name">姓<span class="required-label">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($customer_info['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="first_name">名<span class="required-label">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($customer_info['first_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>性別<span class="required-label">*</span></label>
                        <div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="gender_female" name="gender" value="female" class="custom-control-input" <?php echo (isset($customer_info['gender']) && $customer_info['gender'] === 'female') ? 'checked' : ''; ?> required>
                                <label class="custom-control-label" for="gender_female">女性</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="gender_male" name="gender" value="male" class="custom-control-input" <?php echo (isset($customer_info['gender']) && $customer_info['gender'] === 'male') ? 'checked' : ''; ?> required>
                                <label class="custom-control-label" for="gender_male">男性</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">メールアドレス<span class="required-label">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($customer_info['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">電話番号<span class="required-label">*</span></label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($customer_info['phone'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="birthdate">生年月日<span class="required-label">*</span></label>
                        <input type="date" class="form-control" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($customer_info['birthdate'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">備考・ご要望</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($customer_info['notes'] ?? ''); ?></textarea>
                        <small class="form-text text-muted">アレルギーや気になる点などございましたらご記入ください。</small>
                    </div>
                    
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="notify_email" name="notify_email" <?php echo (isset($customer_info['notify_email']) && $customer_info['notify_email']) ? 'checked' : ''; ?>>
                        <label class="custom-control-label" for="notify_email">キャンペーンやお得な情報をメールで受け取る</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <p class="text-muted small">
                        <i class="fas fa-info-circle mr-1"></i>
                        ご入力いただいた個人情報は、予約管理及びお客様へのご連絡のみに使用し、その他の目的には一切使用いたしません。
                    </p>
                </div>
            </form>
        </div>
        
        <div class="action-buttons text-center">
            <a href="select_datetime.php" class="btn back-btn mr-2">
                <i class="fas fa-chevron-left mr-1"></i> 戻る
            </a>
            <button type="button" id="submitBtn" class="btn next-btn">
                入力内容を確認する <i class="fas fa-chevron-right ml-1"></i>
            </button>
        </div>
    </div>

    <footer class="bg-light py-3 mt-5">
        <div class="container">
            <div class="text-center">
                <small>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($salon['salon_name']); ?> All Rights Reserved.</small>
            </div>
        </div>
    </footer>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // フォーム送信
            $('#submitBtn').on('click', function() {
                // フォームの入力チェック
                if ($('#customerForm')[0].checkValidity()) {
                    $('#customerForm').submit();
                } else {
                    // 無効な場合はブラウザのデフォルトバリデーションを表示
                    $('#customerForm')[0].reportValidity();
                }
            });
        });
    </script>
</body>
</html> 