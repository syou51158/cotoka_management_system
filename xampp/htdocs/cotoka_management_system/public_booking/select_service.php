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

// サロンIDの取得と検証
$salon_id = $_SESSION['booking_salon_id'] ?? null;
if (!$salon_id) {
    $_SESSION['error_message'] = "セッションが切れました。最初からやり直してください。";
    header('Location: index.php');
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

// フォームが送信された場合の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['services']) || empty($_POST['services'])) {
        $error_message = "サービスを選択してください。";
    } else {
        $selected_services = $_POST['services'];
        
        // 選択されたサービスの検証
        try {
            $valid_services = [];
            $total_duration = 0;
            $total_price = 0;
            
            foreach ($selected_services as $service_id) {
                $stmt = $conn->prepare("
                    SELECT 
                        s.service_id,
                        s.name,
                        s.duration,
                        s.price,
                        s.status,
                        COUNT(ss.staff_id) as available_staff_count
                    FROM services s
                    LEFT JOIN staff_services ss ON s.service_id = ss.service_id 
                        AND ss.salon_id = s.salon_id 
                        AND ss.is_active = 1
                    WHERE s.service_id = :service_id 
                    AND s.salon_id = :salon_id 
                    AND s.status = 'active'
                    GROUP BY s.service_id
                ");
                $stmt->bindParam(':service_id', $service_id);
                $stmt->bindParam(':salon_id', $salon_id);
                $stmt->execute();
                $service = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($service && $service['available_staff_count'] > 0) {
                    $valid_services[] = [
                        'service_id' => $service['service_id'],
                        'name' => $service['name'],
                        'duration' => $service['duration'],
                        'price' => $service['price']
                    ];
                    $total_duration += $service['duration'];
                    $total_price += $service['price'];
                }
            }
            
            if (empty($valid_services)) {
                throw new Exception("選択されたサービスは現在予約できません。");
            }
            
            // セッションに保存
            $_SESSION['booking_services'] = $valid_services;
            $_SESSION['booking_total_duration'] = $total_duration;
            $_SESSION['booking_total_price'] = $total_price;
            
            // 日時選択画面へリダイレクト
            header('Location: select_datetime.php');
            exit;
            
        } catch (Exception $e) {
            error_log("サービス検証エラー：" . $e->getMessage());
            $error_message = $e->getMessage();
        }
    }
}

// サービスカテゴリとサービスの取得
try {
    // カテゴリの取得
    $stmt = $conn->prepare("
        SELECT 
            sc.category_id,
            sc.name,
            sc.description,
            sc.color,
            sc.display_order,
            COUNT(s.service_id) as service_count
        FROM service_categories sc
        LEFT JOIN services s ON sc.category_id = s.category_id 
            AND s.salon_id = sc.salon_id 
            AND s.status = 'active'
        WHERE sc.salon_id = :salon_id
        GROUP BY sc.category_id
        HAVING service_count > 0
        ORDER BY sc.display_order ASC
    ");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // カテゴリごとのサービスを取得
    $services_by_category = [];
    foreach ($categories as $category) {
        $stmt = $conn->prepare("
            SELECT 
                s.service_id,
                s.name,
                s.description,
                s.price,
                s.duration,
                COUNT(ss.staff_id) as available_staff_count
            FROM services s
            LEFT JOIN staff_services ss ON s.service_id = ss.service_id 
                AND ss.salon_id = s.salon_id 
                AND ss.is_active = 1
            WHERE s.salon_id = :salon_id 
                AND s.status = 'active'
                AND s.category_id = :category_id
            GROUP BY s.service_id
            HAVING available_staff_count > 0
            ORDER BY s.display_order ASC
        ");
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->bindParam(':category_id', $category['category_id']);
        $stmt->execute();
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($services)) {
            $services_by_category[$category['category_id']] = [
                'category' => $category,
                'services' => $services
            ];
        }
    }
    
    // カテゴリなしのサービスを取得
    $stmt = $conn->prepare("
        SELECT 
            s.service_id,
            s.name,
            s.description,
            s.price,
            s.duration,
            s.category,
            COUNT(ss.staff_id) as available_staff_count
        FROM services s
        LEFT JOIN staff_services ss ON s.service_id = ss.service_id 
            AND ss.salon_id = s.salon_id 
            AND ss.is_active = 1
        WHERE s.salon_id = :salon_id 
            AND s.status = 'active'
            AND (s.category_id IS NULL OR s.category_id = 0)
        GROUP BY s.service_id
        HAVING available_staff_count > 0
        ORDER BY s.display_order ASC
    ");
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $uncategorized_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($uncategorized_services)) {
        $grouped_services = [];
        foreach ($uncategorized_services as $service) {
            $cat_name = !empty($service['category']) ? $service['category'] : 'その他';
            if (!isset($grouped_services[$cat_name])) {
                $grouped_services[$cat_name] = [];
            }
            $grouped_services[$cat_name][] = $service;
        }
        
        foreach ($grouped_services as $cat_name => $services) {
            $virtual_cat_id = 'virtual_' . md5($cat_name);
            $services_by_category[$virtual_cat_id] = [
                'category' => [
                    'category_id' => $virtual_cat_id,
                    'name' => $cat_name,
                    'description' => '',
                    'color' => '#666666'
                ],
                'services' => $services
            ];
        }
    }
    
    // サービスが見つからない場合のエラー処理
    if (empty($services_by_category)) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as service_count
            FROM services
            WHERE salon_id = :salon_id AND status = 'active'
        ");
        $stmt->bindParam(':salon_id', $salon_id);
        $stmt->execute();
        $service_count = $stmt->fetch(PDO::FETCH_ASSOC)['service_count'];
        
        if ($service_count > 0) {
            throw new Exception("サービスはありますが、予約可能なスタッフがいません。");
        } else {
            throw new Exception("このサロンには現在予約可能なサービスがありません。");
        }
    }
    
} catch (Exception $e) {
    error_log("サービス取得エラー：" . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: error.php');
    exit;
}

// サロン情報の取得
$salon_name = $_SESSION['salon_name'] ?? 'サロン';
$page_title = $salon_name . " - メニュー選択";

// 追加CSSファイルの設定
$additional_css = ['css/select_service.css'];

// 追加JSファイルの設定
$additional_js = ['js/select_service.js'];

// アクティブなステップを設定
$active_step = 'service';

// ヘッダーを読み込み
include 'includes/header.php';

// 予約ステップを読み込み
include 'includes/booking_steps.php';
?>

<!-- セッション情報の確認（デバッグ用） -->
<?php if (isset($_SESSION['booking_source_id']) && isset($_SESSION['booking_source_name'])): ?>
<div class="session-info">
    <p><strong>予約ソース:</strong> <?php echo htmlspecialchars($_SESSION['booking_source_name']); ?> (ID: <?php echo $_SESSION['booking_source_id']; ?>)</p>
    <?php if (isset($_SESSION['booking_tracking_url'])): ?>
    <p><strong>トラッキングURL:</strong> <?php echo htmlspecialchars($_SESSION['booking_tracking_url']); ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<form method="post" id="service-selection-form">
    <?php foreach ($services_by_category as $category_data): ?>
    <div class="service-category">
        <div class="category-header" style="border-left: 5px solid <?php echo htmlspecialchars($category_data['category']['color']); ?>">
            <h3><?php echo htmlspecialchars($category_data['category']['name']); ?></h3>
            <?php if (!empty($category_data['category']['description'])): ?>
            <div class="category-description">
                <?php echo nl2br(htmlspecialchars($category_data['category']['description'])); ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="service-list">
            <table class="service-table">
                <tbody>
                <?php foreach ($category_data['services'] as $service): ?>
                <tr>
                    <td class="service-checkbox">
                        <input type="checkbox" name="services[]" value="<?php echo $service['service_id']; ?>" 
                               id="service-<?php echo $service['service_id']; ?>" 
                               class="service-select"
                               data-duration="<?php echo $service['duration']; ?>"
                               data-price="<?php echo $service['price']; ?>">
                    </td>
                    <td class="service-info">
                        <label for="service-<?php echo $service['service_id']; ?>" class="service-name">
                            <?php echo htmlspecialchars($service['name']); ?>
                        </label>
                        <?php if (!empty($service['description'])): ?>
                        <div class="service-description">
                            <?php echo nl2br(htmlspecialchars($service['description'])); ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="service-duration">
                        <i class="far fa-clock"></i>
                        <?php echo $service['duration']; ?>分
                    </td>
                    <td class="service-price">
                        <i class="fas fa-yen-sign"></i>
                        <?php echo number_format($service['price']); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
    
    <div id="service-summary" class="service-summary" style="display: none;"></div>
    
    <button type="submit" class="submit-button" id="submit-button" disabled>
        日時を選択する <i class="fas fa-chevron-right"></i>
    </button>
</form>

<?php
// フッターを読み込み
include 'includes/footer.php';
?> 