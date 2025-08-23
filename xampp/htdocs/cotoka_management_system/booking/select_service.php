<?php
// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../includes/functions.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// セッション開始
session_start();

// URLからサロンIDを取得
$salon_id = isset($_GET['salon_id']) ? intval($_GET['salon_id']) : 1;

// セッションに保存
$_SESSION['booking_salon_id'] = $salon_id;

// フォームが送信された場合の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 選択されたサービスをセッションに保存
    $_SESSION['booking_services'] = isset($_POST['services']) ? $_POST['services'] : [];
    
    // 日時選択画面へリダイレクト
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

// サービスカテゴリを取得
try {
    $stmt = $conn->prepare("
        SELECT 
            sc.category_id, 
            sc.name, 
            sc.description 
        FROM 
            service_categories sc
        WHERE 
            sc.salon_id = :salon_id 
        ORDER BY 
            sc.display_order ASC
    ");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($categories)) {
        throw new Exception("サービスカテゴリが見つかりません");
    }
} catch (Exception $e) {
    $error_message = "サービスカテゴリ取得エラー：" . $e->getMessage();
    exit($error_message);
}

// カテゴリごとのサービスを取得
$services_by_category = [];
try {
    foreach ($categories as $category) {
        $stmt = $conn->prepare("
            SELECT 
                s.service_id, 
                s.name, 
                s.description, 
                s.price, 
                s.duration
            FROM 
                services s
            WHERE 
                s.salon_id = :salon_id 
                AND s.status = 'active'
                AND (s.category = :category_name OR (s.category_id = :category_id AND s.category_id IS NOT NULL))
            ORDER BY 
                s.display_order ASC
        ");
        $stmt->bindParam(':category_id', $category['category_id']);
        $stmt->bindParam(':category_name', $category['name']);
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->execute();
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($services)) {
            $services_by_category[$category['category_id']] = [
                'category' => $category,
                'services' => $services
            ];
        }
    }
    
    // サービスがカテゴリに関連付けられていない場合に備えて、すべてのサービスも取得
    $stmt = $conn->prepare("
        SELECT 
            s.service_id, 
            s.name, 
            s.description, 
            s.price, 
            s.duration,
            s.category
        FROM 
            services s
        WHERE 
            s.salon_id = :salon_id 
            AND s.status = 'active'
            AND (s.category_id IS NULL OR s.category_id = 0)
        ORDER BY 
            s.display_order ASC
    ");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $uncategorized_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($uncategorized_services)) {
        // カテゴリ名でグループ化
        $grouped_services = [];
        foreach ($uncategorized_services as $service) {
            $cat_name = !empty($service['category']) ? $service['category'] : 'その他';
            if (!isset($grouped_services[$cat_name])) {
                $grouped_services[$cat_name] = [];
            }
            $grouped_services[$cat_name][] = $service;
        }
        
        // 各カテゴリグループをservices_by_categoryに追加
        foreach ($grouped_services as $cat_name => $services) {
            // カテゴリIDが無いものにはユニークな識別子を生成
            $virtual_cat_id = 'virtual_' . md5($cat_name);
            $services_by_category[$virtual_cat_id] = [
                'category' => [
                    'category_id' => $virtual_cat_id,
                    'name' => $cat_name,
                    'description' => ''
                ],
                'services' => $services
            ];
        }
    }
    
    // サービスが見つからない場合
    if (empty($services_by_category)) {
        throw new Exception("有効なサービスが見つかりません");
    }
} catch (Exception $e) {
    $error_message = "サービス取得エラー：" . $e->getMessage();
    exit($error_message);
}

// 初回割引クーポン
$first_visit_discount = [
    'title' => '初回限定割引',
    'description' => '初めてのご来店で10%OFF',
    'discount_rate' => 10
];

$page_title = $salon['salon_name'] . " - メニュー選択";
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
        
        .service-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .service-container h3 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .discount-card {
            background-color: #fff3e0;
            border-left: 4px solid #ff9800;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .discount-icon {
            flex-shrink: 0;
            width: 50px;
            height: 50px;
            background-color: #ff9800;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 15px;
        }
        
        .discount-content {
            flex-grow: 1;
        }
        
        .discount-title {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 5px;
            color: #e65100;
        }
        
        .discount-description {
            color: #555;
            margin-bottom: 0;
        }
        
        .category-section {
            margin-bottom: 30px;
        }
        
        .category-title {
            font-size: 1.3rem;
            color: #555;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-light);
            margin-bottom: 15px;
        }
        
        .service-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #eee;
            transition: all 0.3s ease;
            display: flex;
            align-items: flex-start;
        }
        
        .service-card:hover {
            border-color: var(--primary-light);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
        }
        
        .service-checkbox {
            flex-shrink: 0;
            margin-right: 15px;
            margin-top: 3px;
        }
        
        .service-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .service-content {
            flex-grow: 1;
        }
        
        .service-name {
            font-weight: 500;
            margin-bottom: 5px;
            color: #333;
        }
        
        .service-description {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
        }
        
        .service-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .service-duration {
            font-size: 0.85rem;
            color: #888;
            display: flex;
            align-items: center;
        }
        
        .service-duration i {
            margin-right: 5px;
        }
        
        .service-price {
            font-weight: 500;
            color: var(--primary-dark);
        }
        
        .custom-checkbox .custom-control-input:checked ~ .custom-control-label::before {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .selected-services {
            position: sticky;
            bottom: 0;
            background-color: white;
            padding: 15px;
            box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.1);
            border-top: 1px solid #eee;
            z-index: 100;
        }
        
        .selected-counter {
            background-color: var(--primary-color);
            color: white;
            padding: 2px 8px;
            border-radius: 50%;
            font-size: 0.9rem;
            margin-left: 5px;
        }
        
        .action-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        .next-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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
            
            .service-container {
                padding: 15px;
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
            <li class="active"><i class="fas fa-list"></i> メニュー選択</li>
            <li><i class="far fa-calendar-alt"></i> 日時選択</li>
            <li><i class="fas fa-user"></i> 情報入力</li>
            <li><i class="fas fa-check"></i> 予約確認</li>
            <li><i class="fas fa-check-circle"></i> 予約完了</li>
        </ul>
        
        <div class="service-container">
            <h3 class="text-center mb-4">ご希望のメニューを選択してください</h3>
            
            <!-- 初回割引表示 -->
            <div class="discount-card">
                <div class="discount-icon">
                    <i class="fas fa-gift"></i>
                </div>
                <div class="discount-content">
                    <div class="discount-title"><?php echo $first_visit_discount['title']; ?></div>
                    <p class="discount-description"><?php echo $first_visit_discount['description']; ?></p>
                </div>
            </div>
            
            <form id="serviceForm" action="select_datetime.php" method="post">
                <!-- 既存のカテゴリとサービス表示 -->
                <?php foreach ($services_by_category as $cat_id => $cat_data): ?>
                    <div class="category-section">
                        <h4 class="category-title"><?php echo htmlspecialchars($cat_data['category']['name']); ?></h4>
                        
                        <?php if (!empty($cat_data['category']['description'])): ?>
                            <p class="mb-3 text-muted"><?php echo htmlspecialchars($cat_data['category']['description']); ?></p>
                        <?php endif; ?>
                        
                        <?php foreach ($cat_data['services'] as $service): ?>
                            <div class="service-card">
                                <div class="service-checkbox">
                                    <input type="checkbox" id="service_<?php echo $cat_id; ?>_<?php echo $service['service_id']; ?>" name="services[]" value="<?php echo $service['service_id']; ?>" class="service-check">
                                </div>
                                <label for="service_<?php echo $cat_id; ?>_<?php echo $service['service_id']; ?>" class="service-content mb-0">
                                    <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                                    
                                    <?php if (!empty($service['description'])): ?>
                                        <div class="service-description"><?php echo htmlspecialchars($service['description']); ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="service-meta">
                                        <div class="service-duration">
                                            <i class="far fa-clock"></i> <?php echo $service['duration']; ?>分
                                        </div>
                                        <div class="service-price">¥<?php echo number_format($service['price']); ?></div>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </form>
        </div>
        
        <div class="selected-services">
            <div class="action-buttons">
                <a href="index.php" class="btn back-btn">
                    <i class="fas fa-chevron-left mr-1"></i> 戻る
                </a>
                <div>
                    <span id="selected-services-count">0</span>個のメニューを選択中
                </div>
                <button type="button" id="nextBtn" class="btn next-btn" disabled>
                    日時を選択する <i class="fas fa-chevron-right ml-1"></i>
                </button>
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
    
    <script>
        $(document).ready(function() {
            // サービスチェックボックスの状態を監視
            $('.service-check').on('change', function() {
                updateSelectedCount();
            });
            
            // 次へボタンのクリック
            $('#nextBtn').on('click', function() {
                if ($('.service-check:checked').length > 0) {
                    $('#serviceForm').submit();
                } else {
                    alert('少なくとも1つのメニューを選択してください。');
                }
            });
            
            // 選択されたサービス数の更新
            function updateSelectedCount() {
                const selectedCount = $('.service-check:checked').length;
                $('#selected-services-count').text(selectedCount);
                
                // 少なくとも1つ選択されていれば次へボタンを有効化
                if (selectedCount > 0) {
                    $('#nextBtn').prop('disabled', false);
                } else {
                    $('#nextBtn').prop('disabled', true);
                }
            }
            
            // ページ読み込み時に選択数を更新
            updateSelectedCount();
        });
    </script>
</body>
</html> 