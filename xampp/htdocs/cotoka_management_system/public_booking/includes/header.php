<?php
// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// セッション開始（セッションがまだ開始されていない場合のみ）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ページタイトルが設定されていない場合のデフォルト値
if (!isset($page_title)) {
    $salon_name = $_SESSION['salon_name'] ?? 'サロン';
    $page_title = $salon_name . " オンライン予約";
}

// 現在のページを取得
$current_page = basename($_SERVER['PHP_SELF']);

// ページによって表示コンテンツが変わる場合の変数初期化
$header_title = '';

// 現在のページに応じてヘッダータイトルを設定
switch ($current_page) {
    case 'index.php':
        $header_title = 'ONLINE BOOKING';
        break;
    case 'select_service.php':
        $header_title = 'メニュー選択';
        break;
    case 'select_datetime.php':
        $header_title = '日時選択';
        break;
    case 'input_info.php':
        $header_title = '情報入力';
        break;
    case 'confirm.php':
        $header_title = '予約確認';
        break;
    case 'complete.php':
        $header_title = '予約完了';
        break;
    case 'error.php':
        $header_title = 'エラー';
        break;
    default:
        $header_title = 'ONLINE BOOKING';
        break;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- キャッシュ制御 -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- 共通CSS -->
    <link rel="stylesheet" href="css/booking_common.css?v=<?php echo time(); ?>">
    
    <?php if (isset($additional_css) && is_array($additional_css)): ?>
        <?php foreach ($additional_css as $css_file): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css_file); ?>?v=<?php echo time(); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <header class="booking-header">
        <div class="container">
            <h1 class="text-center m-0">
                <?php if (file_exists("../assets/images/logo.png")): ?>
                <img src="../assets/images/logo.png" alt="<?php echo htmlspecialchars($_SESSION['salon_name'] ?? 'サロン'); ?>" class="booking-logo">
                <?php endif; ?>
                <div><?php echo htmlspecialchars($header_title); ?></div>
            </h1>
        </div>
    </header>

    <div class="container py-4">
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php 
            echo htmlspecialchars($_SESSION['error_message']); 
            unset($_SESSION['error_message']);
            ?>
        </div>
        <?php endif; ?> 