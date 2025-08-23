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

// 予約関連のセッション変数をクリア（新規予約開始時）
$session_vars_to_clear = [
    'booking_services',
    'booking_selected_date',
    'booking_selected_staff_id',
    'booking_selected_time',
    'booking_customer_info',
    'booking_appointment_id',
    'booking_source_id',
    'booking_source_name',
    'booking_tracking_url',
    'booking_salon_id',
    'error_message'
];

foreach ($session_vars_to_clear as $var) {
    if (isset($_SESSION[$var])) {
        unset($_SESSION[$var]);
    }
}

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    error_log("データベース接続エラー：" . $e->getMessage());
    $_SESSION['error_message'] = "システムエラーが発生しました。しばらく時間をおいて再度お試しください。";
    // エラーページへリダイレクト
    header('Location: error.php');
    exit;
}

// URLからサロン情報を取得
$salon_id = null;
$booking_source_id = null;
$source_code = null;
$slug = null;

// スラッグの取得（優先順位: GET > URLパス）
if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $slug = htmlspecialchars(strip_tags($_GET['slug']));
} else {
    $path_parts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $last_part = end($path_parts);
    if ($last_part != 'index.php' && $last_part != '') {
        $slug = htmlspecialchars(strip_tags($last_part));
    }
}

// サロンIDの取得（スラッグ > GET > デフォルト）
try {
    if ($slug) {
        $stmt = $conn->prepare("
            SELECT salon_id, status 
            FROM salons 
            WHERE url_slug = :slug
        ");
        $stmt->bindParam(':slug', $slug);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            if ($result['status'] === 'inactive') {
                throw new Exception("このサロンは現在予約を受け付けていません。");
            }
            $salon_id = $result['salon_id'];
        } else {
            throw new Exception("指定されたサロンが見つかりません。");
        }
    } else if (isset($_GET['salon_id'])) {
        $salon_id = filter_var($_GET['salon_id'], FILTER_VALIDATE_INT);
        if ($salon_id === false) {
            throw new Exception("無効なサロンIDです。");
        }
    } else {
        $salon_id = 1; // デフォルト値
    }
    
    // サロン情報の取得
    $stmt = $conn->prepare("
        SELECT 
            s.salon_id,
            s.name as salon_name,
            s.description,
            s.address,
            s.phone as phone_number,
            s.url_slug,
            s.status,
            s.business_hours,
            s.default_booking_source_id
        FROM salons s
        WHERE s.salon_id = :salon_id
    ");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $salon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$salon) {
        throw new Exception("サロンが見つかりません。");
    }
    
    if ($salon['status'] === 'inactive') {
        throw new Exception("このサロンは現在予約を受け付けていません。");
    }
    
} catch (Exception $e) {
    error_log("サロン情報取得エラー：" . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: error.php');
    exit;
}

// 予約ソースの処理
if (isset($_GET['source'])) {
    $source_code = htmlspecialchars(strip_tags($_GET['source']));
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                bs.source_id,
                bs.source_name,
                bs.tracking_url,
                bs.is_active
            FROM booking_sources bs
            WHERE bs.salon_id = :salon_id 
            AND bs.source_code = :source_code
        ");
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->bindParam(':source_code', $source_code);
        $stmt->execute();
        $source = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($source && $source['is_active']) {
            $booking_source_id = $source['source_id'];
            $_SESSION['booking_source_id'] = $booking_source_id;
            $_SESSION['booking_source_name'] = $source['source_name'];
            $_SESSION['booking_tracking_url'] = $source['tracking_url'];
        } else {
            // 無効なソースの場合はデフォルトを使用
            $booking_source_id = $salon['default_booking_source_id'];
            $_SESSION['booking_source_id'] = $booking_source_id;
        }
    } catch (Exception $e) {
        error_log("予約ソース取得エラー：" . $e->getMessage());
        // エラーが発生した場合はデフォルトソースを使用
        $booking_source_id = $salon['default_booking_source_id'];
        $_SESSION['booking_source_id'] = $booking_source_id;
    }
} else {
    // ソースコードが指定されていない場合はデフォルトを使用
    $booking_source_id = $salon['default_booking_source_id'];
    $_SESSION['booking_source_id'] = $booking_source_id;
}

// セッションにサロン情報を保存
$_SESSION['booking_salon_id'] = $salon_id;
$_SESSION['salon_name'] = $salon['salon_name'];

$page_title = $salon['salon_name'] . " オンライン予約";

// 営業時間の解析（JSON形式で保存されていると仮定）
$business_hours = [];
if (!empty($salon['business_hours'])) {
    $business_hours = json_decode($salon['business_hours'], true) ?? [];
}

// 追加CSSの定義
$additional_css = ['css/index.css'];

// 追加JSの定義
$additional_js = [];

// アクティブなステップを設定
$active_step = 'home';

// ヘッダーを読み込み
include 'includes/header.php';

// 予約ステップを読み込み
include 'includes/booking_steps.php';
?>

<div class="salon-card">
    <div class="salon-info">
        <h2><?php echo htmlspecialchars($salon['salon_name']); ?></h2>
        <p><?php echo nl2br(htmlspecialchars($salon['description'] ?? '')); ?></p>
    </div>
    
    <div class="salon-details">
        <div class="salon-detail">
            <i class="fas fa-map-marker-alt"></i>
            <div class="salon-detail-text">
                <strong>住所</strong>
                <?php echo nl2br(htmlspecialchars($salon['address'] ?? '')); ?>
            </div>
        </div>
        
        <div class="salon-detail">
            <i class="fas fa-phone"></i>
            <div class="salon-detail-text">
                <strong>電話番号</strong>
                <?php echo htmlspecialchars($salon['phone_number'] ?? ''); ?>
            </div>
        </div>
        
        <?php if (!empty($business_hours)): ?>
        <div class="salon-detail">
            <i class="far fa-clock"></i>
            <div class="salon-detail-text">
                <strong>営業時間</strong>
                <div class="business-hours-list">
                    <?php
                    $days = ['月', '火', '水', '木', '金', '土', '日'];
                    foreach ($business_hours as $day => $hours):
                        if (isset($hours['start']) && isset($hours['end'])):
                    ?>
                    <div>
                        <?php echo $days[$day-1]; ?>曜日:
                        <?php echo htmlspecialchars($hours['start']); ?> - 
                        <?php echo htmlspecialchars($hours['end']); ?>
                    </div>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="salon-detail">
            <i class="fas fa-credit-card"></i>
            <div class="salon-detail-text">
                <strong>お支払い方法</strong>
                各種クレジットカード、PayPay、iD、交通系電子マネー、UnionPay、QUICPay、ApplePayもご利用いただけます。
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4">
        <a href="select_service.php" class="btn booking-btn">
            メニューを選択する <i class="fas fa-chevron-right"></i>
        </a>
    </div>
</div>

<?php
// フッターを読み込み
include 'includes/footer.php';
?> 