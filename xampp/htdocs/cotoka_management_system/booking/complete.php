<?php
// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../includes/functions.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// セッション開始
session_start();

// 予約IDを取得
$appointment_id = $_SESSION['booking_appointment_id'] ?? 0;
$salon_id = $_SESSION['booking_salon_id'] ?? 0;

// 予約IDがない場合はトップページへリダイレクト
if (empty($appointment_id) || empty($salon_id)) {
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
    $stmt = $conn->prepare("SELECT salon_id, name as salon_name, phone as phone_number FROM salons WHERE salon_id = :salon_id");
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

// 予約情報の取得
try {
    $stmt = $conn->prepare("
        SELECT 
            a.appointment_id, 
            a.appointment_date, 
            a.start_time, 
            a.end_time,
            c.first_name as customer_first_name,
            c.last_name as customer_last_name,
            c.email as customer_email,
            s.first_name as staff_first_name,
            s.last_name as staff_last_name,
            srv.name as service_name
        FROM 
            appointments a
            JOIN customers c ON a.customer_id = c.customer_id
            JOIN staff s ON a.staff_id = s.staff_id
            JOIN services srv ON a.service_id = srv.service_id
        WHERE 
            a.appointment_id = :appointment_id
            AND a.salon_id = :salon_id
    ");
    $stmt->bindParam(':appointment_id', $appointment_id);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        throw new Exception("予約情報が見つかりません");
    }
    
    // 追加の予約（複数サービスの場合）を取得
    $stmt = $conn->prepare("
        SELECT 
            srv.name as service_name
        FROM 
            appointments a
            JOIN services srv ON a.service_id = srv.service_id
        WHERE 
            a.appointment_date = :appointment_date
            AND a.start_time = :start_time
            AND a.customer_id = (SELECT customer_id FROM appointments WHERE appointment_id = :appointment_id)
            AND a.appointment_id <> :appointment_id
            AND a.salon_id = :salon_id
    ");
    $stmt->bindParam(':appointment_date', $appointment['appointment_date']);
    $stmt->bindParam(':start_time', $appointment['start_time']);
    $stmt->bindParam(':appointment_id', $appointment_id);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $additional_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "予約情報取得エラー：" . $e->getMessage();
    exit($error_message);
}

// 日本語の曜日
$days_of_week_jp = ['日', '月', '火', '水', '木', '金', '土'];
$date_obj = new DateTime($appointment['appointment_date']);
$weekday_num = $date_obj->format('w'); // 0（日）～ 6（土）
$date_formatted = $date_obj->format('Y年n月j日') . '（' . $days_of_week_jp[$weekday_num] . '）';

// 予約コード生成（シンプルなハッシュ）
$booking_code = substr(md5($appointment_id . $appointment['appointment_date'] . $appointment['start_time']), 0, 8);

// セッション変数をクリア（予約プロセスが完了したため）
$_SESSION['booking_services'] = [];
$_SESSION['booking_selected_date'] = '';
$_SESSION['booking_selected_staff_id'] = 0;
$_SESSION['booking_selected_time'] = '';
$_SESSION['booking_customer_info'] = [];
// 予約IDは残しておく（再アクセス時に予約情報を表示するため）

$page_title = $salon['salon_name'] . " - 予約完了";
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
        
        .complete-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .success-icon {
            font-size: 5rem;
            color: #4CAF50;
            margin-bottom: 10px;
        }
        
        .qr-code {
            display: block;
            width: 200px;
            height: 200px;
            margin: 0 auto 20px;
            background-color: #f8f8f8;
            padding: 10px;
            border-radius: 5px;
        }
        
        .booking-code {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            display: inline-block;
            margin: 10px 0 20px;
            font-size: 1.2rem;
            letter-spacing: 1px;
        }
        
        .details-container {
            max-width: 600px;
            margin: 0 auto;
            text-align: left;
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
            padding: 10px 0;
            border-bottom: 1px dashed #eee;
        }
        
        .service-list li:last-child {
            border-bottom: none;
        }
        
        .contact-info {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        .action-buttons {
            margin-top: 30px;
        }
        
        .home-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .home-btn:hover {
            background-color: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .print-btn {
            color: var(--primary-color);
            background-color: transparent;
            border: 1px solid var(--primary-color);
            border-radius: 25px;
            padding: 10px 25px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .print-btn:hover {
            background-color: rgba(233, 30, 99, 0.1);
            color: var(--primary-dark);
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
            
            .complete-container {
                padding: 15px;
            }
            
            .info-table th {
                width: 40%;
            }
        }
        
        @media print {
            .booking-steps, .action-buttons, .not-print, footer {
                display: none;
            }
            
            .complete-container {
                box-shadow: none;
                padding: 0;
            }
            
            body {
                background-color: white;
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
            <li><i class="fas fa-check"></i> 予約確認</li>
            <li class="active"><i class="fas fa-check-circle"></i> 予約完了</li>
        </ul>
        
        <div class="complete-container">
            <i class="fas fa-check-circle success-icon"></i>
            <h2 class="mb-3">ご予約が完了しました</h2>
            <p class="mb-4">ご予約ありがとうございます。確認メールをお送りしましたのでご確認ください。</p>
            
            <!-- 予約コード -->
            <p class="mb-1">予約コード</p>
            <div class="booking-code"><?php echo $booking_code; ?></div>
            
            <div class="details-container">
                <div class="info-section">
                    <h4><i class="fas fa-calendar-alt mr-2"></i>予約情報</h4>
                    <table class="info-table">
                        <tr>
                            <th>お名前</th>
                            <td><?php echo htmlspecialchars($appointment['customer_last_name'] . ' ' . $appointment['customer_first_name']); ?> 様</td>
                        </tr>
                        <tr>
                            <th>予約日</th>
                            <td><?php echo $date_formatted; ?></td>
                        </tr>
                        <tr>
                            <th>時間</th>
                            <td><?php echo $appointment['start_time']; ?>～<?php echo $appointment['end_time']; ?></td>
                        </tr>
                        <tr>
                            <th>担当スタッフ</th>
                            <td><?php echo htmlspecialchars($appointment['staff_last_name'] . ' ' . $appointment['staff_first_name']); ?></td>
                        </tr>
                    </table>
                </div>
                
                <div class="info-section">
                    <h4><i class="fas fa-list-alt mr-2"></i>予約メニュー</h4>
                    <ul class="service-list">
                        <li><?php echo htmlspecialchars($appointment['service_name']); ?></li>
                        <?php foreach ($additional_services as $service): ?>
                            <li><?php echo htmlspecialchars($service['service_name']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="contact-info">
                    <h5><i class="fas fa-info-circle mr-2"></i>ご注意事項</h5>
                    <p>・ご予約の変更やキャンセルは、予約時間の24時間前までにご連絡ください。</p>
                    <p>・ご予約の日時にご来店お待ちしております。</p>
                    <p>・ご不明な点がございましたら、下記にお問い合わせください。</p>
                    <p class="mt-3 mb-0">
                        <strong><?php echo htmlspecialchars($salon['salon_name']); ?></strong><br>
                        電話番号: <?php echo htmlspecialchars($salon['phone_number']); ?>
                    </p>
                </div>
            </div>
            
            <div class="action-buttons">
                <button class="btn print-btn mr-2" onclick="window.print()">
                    <i class="fas fa-print mr-1"></i> 印刷する
                </button>
                <a href="index.php" class="btn home-btn">
                    トップページに戻る <i class="fas fa-home ml-1"></i>
                </a>
            </div>
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
</body>
</html> 