<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/Salon.php';
require_once 'classes/User.php';

// ログインチェック
if (!isLoggedIn()) {
    redirect('login.php');
}

// サロン選択処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salon_id'])) {
    $salonId = (int)$_POST['salon_id'];
    $token = $_POST['csrf_token'] ?? '';
    
    // CSRFトークンの検証
    if (!validateCSRFToken($token)) {
        setFlashMessage('error', 'セキュリティトークンが無効です。ページを再読み込みして再度お試しください。');
        redirect('select_salon.php');
    }
    
    // データベース接続
    $db = new Database();
    $conn = $db->getConnection();
    
    // ユーザーオブジェクト
    $userObj = new User($db);
    
    // アクセス可能なサロンを取得
    $user_id = $_SESSION['user_id'];
    $accessibleSalons = $userObj->getAccessibleSalons($user_id);
    $accessibleSalonIds = array_column($accessibleSalons, 'salon_id');
    
    // サロンへのアクセス権をチェック
    if (in_array($salonId, $accessibleSalonIds)) {
        // サロンIDをセッションに保存
        $_SESSION['salon_id'] = $salonId;
        $_SESSION['current_salon_id'] = $salonId;
        
        // サロン名を取得
        foreach ($accessibleSalons as $salon) {
            if ($salon['salon_id'] == $salonId) {
                $_SESSION['salon_name'] = $salon['name'];
                break;
            }
        }
        
        setFlashMessage('success', 'サロンを選択しました。');
        redirect('dashboard.php');
    } else {
        setFlashMessage('error', 'このサロンへのアクセス権がありません。');
        redirect('select_salon.php');
    }
}

// データベース接続
$db = new Database();
$conn = $db->getConnection();

// ユーザーオブジェクト
$userObj = new User($db);

// アクセス可能なサロンを取得
$user_id = $_SESSION['user_id'];
$salons = $userObj->getAccessibleSalons($user_id);

$pageTitle = 'サロン選択';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .salon-selection-container {
            max-width: 800px;
            width: 100%;
            padding: 2rem;
        }
        .salon-card {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .salon-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .salon-logo {
            width: 100%;
            height: 150px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="salon-selection-container">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="text-center mb-4"><?php echo $pageTitle; ?></h1>
                
                <?php if (isset($_SESSION['flash_message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?> alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['flash_message']['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
                    </div>
                    <?php unset($_SESSION['flash_message']); ?>
                <?php endif; ?>
                
                <?php if (empty($salons)): ?>
                    <div class="alert alert-warning">
                        アクセス可能なサロンがありません。管理者に連絡してください。
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-3 g-4">
                        <?php foreach ($salons as $salon): ?>
                            <div class="col">
                                <div class="card salon-card h-100">
                                    <div class="salon-logo bg-light">
                                        <i class="bi bi-building" style="font-size: 3rem; color: #007bff;"></i>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($salon['name']); ?></h5>
                                        <p class="card-text text-muted">
                                            <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($salon['address'] ?? '住所情報なし'); ?>
                                        </p>
                                    </div>
                                    <div class="card-footer">
                                        <form method="post" action="select_salon.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="salon_id" value="<?php echo $salon['salon_id']; ?>">
                                            <button type="submit" class="btn btn-primary w-100">このサロンを選択</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <a href="logout.php" class="btn btn-outline-danger">ログアウト</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 