<?php
// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// セッション開始（セッションがまだ開始されていない場合のみ）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// エラーメッセージを取得
$error_message = $_SESSION['error_message'] ?? 'エラーが発生しました。もう一度お試しください。';

// サロン名を取得
$salon_name = $_SESSION['salon_name'] ?? 'サロン';

$page_title = $salon_name . " - エラー";
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- 共通CSS -->
    <link rel="stylesheet" href="css/booking_common.css">
    
    <style>
        .error-container {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin: 2rem 0;
            text-align: center;
        }
        
        .error-icon {
            font-size: 4rem;
            color: var(--error-color);
            margin-bottom: 1.5rem;
        }
        
        .error-title {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 1rem;
        }
        
        .error-message {
            color: #666;
            margin-bottom: 2rem;
            font-size: 1.1rem;
            background-color: transparent;
            display: block;
            text-align: center;
            padding: 0;
        }
        
        .back-home-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 25px;
            padding: 0.8rem 2rem;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(233, 30, 99, 0.2);
            display: inline-block;
            text-decoration: none !important;
        }
        
        .back-home-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(233, 30, 99, 0.3);
        }
    </style>
</head>
<body>
    <header class="booking-header">
        <div class="container">
            <h1 class="text-center m-0">
                <?php if (file_exists("../assets/images/logo.png")): ?>
                <img src="../assets/images/logo.png" alt="<?php echo htmlspecialchars($salon_name); ?>" class="booking-logo">
                <?php endif; ?>
                <div>エラー</div>
            </h1>
        </div>
    </header>

    <div class="container py-4">
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h2 class="error-title">エラーが発生しました</h2>
            <p class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </p>
            <a href="index.php" class="back-home-btn">
                <i class="fas fa-home mr-2"></i> ホームに戻る
            </a>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="text-center">
                <small>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($salon_name); ?> All Rights Reserved.</small>
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