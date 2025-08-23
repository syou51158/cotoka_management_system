<?php
// 必要なファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'includes/functions.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// URLからサロンIDを取得（デフォルトは1）
$salon_id = isset($_GET['salon_id']) ? intval($_GET['salon_id']) : 1;

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
    $stmt = $conn->prepare("SELECT salon_id, name as salon_name, description, address, phone as phone_number FROM salons WHERE salon_id = :salon_id");
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

$page_title = $salon['salon_name'] . " オンライン予約";
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
        
        .salon-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .salon-info {
            margin-bottom: 20px;
        }
        
        .salon-info h2 {
            color: var(--primary-dark);
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .salon-info p {
            color: #666;
            line-height: 1.6;
        }
        
        .salon-details {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .salon-detail {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            width: 100%;
        }
        
        .salon-detail i {
            color: var(--primary-color);
            font-size: 1.2rem;
            margin-right: 10px;
            margin-top: 3px;
        }
        
        .salon-detail-text {
            flex: 1;
        }
        
        .salon-detail-text strong {
            display: block;
            color: #333;
            margin-bottom: 5px;
        }
        
        .booking-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .booking-btn:hover {
            background-color: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        @media (max-width: 767px) {
            .booking-steps {
                flex-direction: column;
                align-items: center;
            }
            
            .booking-steps li {
                margin-bottom: 10px;
                width: 80%;
                text-align: center;
            }
            
            .salon-detail {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="booking-header">
        <div class="container">
            <h1 class="m-0">
                <img src="assets/images/logo.png" alt="<?php echo htmlspecialchars($salon['salon_name']); ?>" class="booking-logo">
                ONLINE BOOKING SERVICE
            </h1>
        </div>
    </header>

    <div class="container py-4">
        <ul class="booking-steps">
            <li class="active"><i class="fas fa-list"></i> メニュー選択</li>
            <li><i class="far fa-calendar-alt"></i> 日時選択</li>
            <li><i class="fas fa-user"></i> 情報入力</li>
            <li><i class="fas fa-check"></i> 予約確認</li>
            <li><i class="fas fa-check-circle"></i> 予約完了</li>
        </ul>
        
        <div class="salon-card">
            <div class="salon-info">
                <h2><?php echo htmlspecialchars($salon['salon_name']); ?></h2>
                <p><?php echo htmlspecialchars($salon['description']); ?></p>
            </div>
            
            <div class="salon-details">
                <div class="salon-detail">
                    <i class="fas fa-map-marker-alt"></i>
                    <div class="salon-detail-text">
                        <strong>住所</strong>
                        <?php echo htmlspecialchars($salon['address']); ?>
                    </div>
                </div>
                
                <div class="salon-detail">
                    <i class="fas fa-phone"></i>
                    <div class="salon-detail-text">
                        <strong>電話番号</strong>
                        <?php echo htmlspecialchars($salon['phone_number']); ?>
                    </div>
                </div>
                
                <div class="salon-detail">
                    <i class="fas fa-credit-card"></i>
                    <div class="salon-detail-text">
                        <strong>お支払い方法</strong>
                        各種クレジットカード、PayPay、iD、交通系電子マネー、UnionPay、QUICPay、ApplePayもご利用いただけます。
                    </div>
                </div>
                
                <div class="salon-detail">
                    <i class="fas fa-info-circle"></i>
                    <div class="salon-detail-text">
                        <strong>ご利用案内</strong>
                        お子様とご一緒にご来店されたい方は、一度お問い合わせください。
                    </div>
                </div>
            </div>
            
            <div class="text-center">
                <a href="booking_select_service.php?salon_id=<?php echo $salon_id; ?>" class="btn booking-btn">
                    メニューを選択する <i class="fas fa-chevron-right ml-2"></i>
                </a>
            </div>
        </div>
    </div>

    <footer class="bg-light py-3">
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