<?php
// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../includes/functions.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// セッション開始
session_start();

// セッションデータを取得
$salon_id = $_SESSION['booking_salon_id'] ?? 0;
$selected_date = $_SESSION['booking_selected_date'] ?? '';
$selected_staff_id = $_SESSION['booking_selected_staff_id'] ?? 0;
$selected_time = $_SESSION['booking_selected_time'] ?? '';
$selected_services = $_SESSION['booking_services'] ?? [];
$customer_info = $_SESSION['booking_customer_info'] ?? [];

// 必要なデータが不足している場合はリダイレクト
if (empty($salon_id) || empty($selected_date) || empty($selected_staff_id) || empty($selected_time) || empty($selected_services) || empty($customer_info)) {
    header('Location: index.php');
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

// 選択したスタッフの情報を取得
try {
    $stmt = $conn->prepare("SELECT staff_id, first_name, last_name FROM staff WHERE staff_id = :staff_id AND salon_id = :salon_id");
    $stmt->bindParam(':staff_id', $selected_staff_id);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        throw new Exception("スタッフ情報が見つかりません");
    }
} catch (Exception $e) {
    $error_message = "スタッフ情報取得エラー：" . $e->getMessage();
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

// 予約終了時間を計算
$start_time = $selected_time;
$end_time_obj = new DateTime($selected_date . ' ' . $start_time);
$end_time_obj->modify('+' . $total_duration . ' minutes');
$end_time = $end_time_obj->format('H:i');

// 日本語の曜日
$days_of_week_jp = ['日', '月', '火', '水', '木', '金', '土'];
$date_obj = new DateTime($selected_date);
$weekday_num = $date_obj->format('w'); // 0（日）～ 6（土）
$date_formatted = $date_obj->format('Y年n月j日') . '（' . $days_of_week_jp[$weekday_num] . '）';

// 予約を確定
$appointment_created = false;
$error_message = '';

// フォーム送信時
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
    try {
        // トランザクション開始
        $conn->beginTransaction();
        
        // 顧客情報の登録・更新
        $customer_id = 0;
        
        // メールアドレスで既存の顧客を検索
        $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = :email AND salon_id = :salon_id");
        $stmt->bindParam(':email', $customer_info['email']);
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->execute();
        $existing_customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_customer) {
            // 既存の顧客を更新
            $customer_id = $existing_customer['customer_id'];
            
            $stmt = $conn->prepare("
                UPDATE customers 
                SET first_name = :first_name, 
                    last_name = :last_name, 
                    phone = :phone, 
                    birthdate = :birthdate,
                    gender = :gender,
                    notify_email = :notify_email,
                    updated_at = NOW()
                WHERE customer_id = :customer_id
            ");
            $stmt->bindParam(':customer_id', $customer_id);
        } else {
            // 新規顧客を登録
            $stmt = $conn->prepare("
                INSERT INTO customers (
                    salon_id, 
                    first_name, 
                    last_name, 
                    email, 
                    phone, 
                    birthdate, 
                    gender, 
                    notify_email, 
                    source,
                    created_at, 
                    updated_at
                ) VALUES (
                    :salon_id, 
                    :first_name, 
                    :last_name, 
                    :email, 
                    :phone, 
                    :birthdate, 
                    :gender, 
                    :notify_email, 
                    'online',
                    NOW(), 
                    NOW()
                )
            ");
            $stmt->bindParam(':salon_id', $salon_id);
            $stmt->bindParam(':email', $customer_info['email']);
        }
        
        // 共通のパラメータをバインド
        $stmt->bindParam(':first_name', $customer_info['first_name']);
        $stmt->bindParam(':last_name', $customer_info['last_name']);
        $stmt->bindParam(':phone', $customer_info['phone']);
        $stmt->bindParam(':birthdate', $customer_info['birthdate']);
        $stmt->bindParam(':gender', $customer_info['gender']);
        $notify_email = isset($customer_info['notify_email']) && $customer_info['notify_email'] ? 1 : 0;
        $stmt->bindParam(':notify_email', $notify_email);
        
        $stmt->execute();
        
        if (!$customer_id) {
            $customer_id = $conn->lastInsertId();
        }
        
        // 予約の登録
        foreach ($selected_services as $service_id) {
            $stmt = $conn->prepare("
                INSERT INTO appointments (
                    salon_id,
                    customer_id,
                    staff_id,
                    service_id,
                    appointment_date,
                    start_time,
                    end_time,
                    status,
                    notes,
                    created_at,
                    updated_at,
                    source
                ) VALUES (
                    :salon_id,
                    :customer_id,
                    :staff_id,
                    :service_id,
                    :appointment_date,
                    :start_time,
                    :end_time,
                    'confirmed',
                    :notes,
                    NOW(),
                    NOW(),
                    'online'
                )
            ");
            
            $stmt->bindParam(':salon_id', $salon_id);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':staff_id', $selected_staff_id);
            $stmt->bindParam(':service_id', $service_id);
            $stmt->bindParam(':appointment_date', $selected_date);
            $stmt->bindParam(':start_time', $selected_time);
            $stmt->bindParam(':end_time', $end_time);
            $stmt->bindParam(':notes', $customer_info['notes']);
            
            $stmt->execute();
        }
        
        // 予約IDを取得（最後に登録した予約のID）
        $appointment_id = $conn->lastInsertId();
        
        // 予約IDをセッションに保存
        $_SESSION['booking_appointment_id'] = $appointment_id;
        
        // トランザクション確定
        $conn->commit();
        
        // 予約完了画面へリダイレクト
        header('Location: complete.php');
        exit;
        
    } catch (Exception $e) {
        // エラー時はロールバック
        $conn->rollBack();
        $error_message = "予約登録エラー：" . $e->getMessage();
    }
}

$page_title = $salon['salon_name'] . " - 予約確認";
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
        
        .confirm-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .confirm-container h3 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .info-section {
            margin-bottom: 25px;
        }
        
        .info-section h4 {
            font-size: 1.2rem;
            color: #555;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #eee;
        }
        
        .info-table {
            width: 100%;
        }
        
        .info-table tr {
            border-bottom: 1px solid #f5f5f5;
        }
        
        .info-table tr:last-child {
            border-bottom: none;
        }
        
        .info-table th {
            width: 30%;
            padding: 12px 10px;
            color: #666;
            font-weight: 500;
            vertical-align: top;
        }
        
        .info-table td {
            padding: 12px 10px;
            color: #333;
        }
        
        .service-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .service-list li {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed #eee;
        }
        
        .service-list li:last-child {
            border-bottom: none;
        }
        
        .service-name {
            flex: 1;
        }
        
        .service-price {
            font-weight: 500;
            color: var(--primary-dark);
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            margin-top: 10px;
            border-top: 2px solid #eee;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .agreement-box {
            background-color: #f9f9f9;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .agreement-text {
            max-height: 150px;
            overflow-y: auto;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: #555;
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
        
        .confirm-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .confirm-btn:hover {
            background-color: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .confirm-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .error-message {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 10px 15px;
            margin-bottom: 20px;
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
            
            .confirm-container {
                padding: 15px;
            }
            
            .info-table th {
                width: 40%;
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
            <li><i class="fas fa-user"></i> 情報入力</li>
            <li class="active"><i class="fas fa-check"></i> 予約確認</li>
            <li><i class="fas fa-check-circle"></i> 予約完了</li>
        </ul>
        
        <div class="confirm-container">
            <h3 class="text-center mb-4">予約内容の確認</h3>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="info-section">
                        <h4><i class="fas fa-calendar-alt mr-2"></i>予約情報</h4>
                        <table class="info-table">
                            <tr>
                                <th>日付</th>
                                <td><?php echo $date_formatted; ?></td>
                            </tr>
                            <tr>
                                <th>時間</th>
                                <td><?php echo $start_time; ?>～<?php echo $end_time; ?></td>
                            </tr>
                            <tr>
                                <th>担当スタッフ</th>
                                <td><?php echo htmlspecialchars($staff['last_name'] . ' ' . $staff['first_name']); ?></td>
                            </tr>
                            <tr>
                                <th>所要時間</th>
                                <td><?php echo $total_duration; ?>分</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="info-section">
                        <h4><i class="fas fa-user mr-2"></i>お客様情報</h4>
                        <table class="info-table">
                            <tr>
                                <th>お名前</th>
                                <td><?php echo htmlspecialchars($customer_info['last_name'] . ' ' . $customer_info['first_name']); ?></td>
                            </tr>
                            <tr>
                                <th>性別</th>
                                <td><?php echo $customer_info['gender'] === 'male' ? '男性' : '女性'; ?></td>
                            </tr>
                            <tr>
                                <th>メールアドレス</th>
                                <td><?php echo htmlspecialchars($customer_info['email']); ?></td>
                            </tr>
                            <tr>
                                <th>電話番号</th>
                                <td><?php echo htmlspecialchars($customer_info['phone']); ?></td>
                            </tr>
                            <tr>
                                <th>生年月日</th>
                                <td><?php echo date('Y年n月j日', strtotime($customer_info['birthdate'])); ?></td>
                            </tr>
                            <?php if (!empty($customer_info['notes'])): ?>
                                <tr>
                                    <th>備考・ご要望</th>
                                    <td><?php echo nl2br(htmlspecialchars($customer_info['notes'])); ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="info-section">
                        <h4><i class="fas fa-list-alt mr-2"></i>予約メニュー</h4>
                        <ul class="service-list">
                            <?php foreach ($selected_service_details as $service): ?>
                                <li>
                                    <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                                    <div class="service-price">¥<?php echo number_format($service['price']); ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="total-row">
                            <div>合計金額</div>
                            <div>¥<?php echo number_format($total_price); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h4><i class="fas fa-info-circle mr-2"></i>ご注意事項</h4>
                        <div class="agreement-box">
                            <div class="agreement-text">
                                <p>【予約規約】</p>
                                <p>・予約のキャンセルは、予約時間の24時間前までにご連絡ください。</p>
                                <p>・当日キャンセルや無断キャンセルの場合、キャンセル料が発生する場合があります。</p>
                                <p>・遅刻された場合、施術時間が短くなることがあります。</p>
                                <p>・貴重品の管理は、お客様自身でお願いいたします。</p>
                                <p>・体調不良の場合は、事前にご連絡ください。</p>
                            </div>
                            
                            <form id="confirmForm" action="confirm.php" method="post">
                                <input type="hidden" name="confirm_booking" value="1">
                                
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="agree_terms" name="agree_terms" required>
                                    <label class="custom-control-label" for="agree_terms">上記の注意事項を確認し、同意します</label>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="action-buttons text-center">
            <a href="input_info.php" class="btn back-btn mr-2">
                <i class="fas fa-chevron-left mr-1"></i> 戻る
            </a>
            <button type="button" id="submitBtn" class="btn confirm-btn" disabled>
                予約を確定する <i class="fas fa-check ml-1"></i>
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
            // 規約同意チェックボックスのイベント
            $('#agree_terms').on('change', function() {
                $('#submitBtn').prop('disabled', !$(this).prop('checked'));
            });
            
            // 予約確定ボタンのクリック
            $('#submitBtn').on('click', function() {
                if ($('#agree_terms').prop('checked')) {
                    $('#confirmForm').submit();
                } else {
                    alert('予約規約に同意してください。');
                }
            });
        });
    </script>
</body>
</html>