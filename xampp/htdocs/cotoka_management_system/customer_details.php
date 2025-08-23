<?php
// 必要なファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'includes/functions.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// ログインしていない場合はログインページにリダイレクト
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    $error_message = "データベース接続エラー：" . $e->getMessage();
    require_once 'includes/header.php';
    echo '<div class="alert alert-danger m-3">' . $error_message . '</div>';
    require_once 'includes/footer.php';
    exit;
}

// 顧客IDがない場合は顧客一覧へリダイレクト
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: customer_manager.php');
    exit;
}

$customer_id = (int)$_GET['id'];
$salon_id = getCurrentSalonId();
$customer = null;
$recent_appointments = [];
$error_message = null;

// 顧客情報の取得
try {
    $sql = "
        SELECT 
            c.customer_id,
            c.first_name,
            c.last_name,
            c.phone,
            c.email,
            c.birthday,
            c.gender,
            c.address,
            c.status,
            c.created_at,
            c.updated_at,
            c.notes,
            c.last_visit_date,
            c.visit_count,
            c.total_spent,
            (SELECT MIN(created_at) FROM appointments WHERE customer_id = c.customer_id) AS first_visit_date
        FROM 
            customers c
        WHERE 
            c.customer_id = :customer_id 
            AND c.salon_id = :salon_id
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
    $stmt->bindParam(':salon_id', $salon_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        $error_message = "指定された顧客情報が見つかりません。";
    } else {
        // 最近の予約を取得
        $appointment_sql = "
            SELECT 
                a.appointment_id,
                a.appointment_date,
                a.start_time,
                a.status,
                s.first_name AS staff_first_name,
                s.last_name AS staff_last_name,
                srv.name AS service_name,
                srv.price AS service_price
            FROM 
                appointments a
                LEFT JOIN staff s ON a.staff_id = s.staff_id
                LEFT JOIN services srv ON a.service_id = srv.service_id
            WHERE 
                a.customer_id = :customer_id
                AND a.salon_id = :salon_id
            ORDER BY 
                a.appointment_date DESC, a.start_time DESC
            LIMIT 10
        ";
        
        $appointment_stmt = $conn->prepare($appointment_sql);
        $appointment_stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
        $appointment_stmt->bindParam(':salon_id', $salon_id, PDO::PARAM_INT);
        $appointment_stmt->execute();
        $recent_appointments = $appointment_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = "顧客情報取得エラー: " . $e->getMessage();
}

// ページ固有のCSS
$page_css = <<<EOT
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/css/customer_manager/customer_manager.css">
EOT;

// ページタイトル
$page_title = "顧客詳細";

// ヘッダーの読み込み
require_once 'includes/header.php';
?>

<div class="container-fluid px-4">
    <div class="row g-3">
        <div class="col-12">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">顧客詳細</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="customer_manager.php" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left"></i> 顧客一覧に戻る
                    </a>
                    <a href="customer_edit.php?id=<?php echo $customer_id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-edit"></i> 編集
                    </a>
                </div>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                </div>
            <?php elseif ($customer): ?>
                <div class="customer-profile mb-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="customer-avatar me-3">
                                    <i class="fas fa-user-circle fa-5x text-primary"></i>
                                </div>
                                <div>
                                    <h3><?php echo htmlspecialchars($customer['last_name'] . ' ' . $customer['first_name']); ?></h3>
                                    <div class="customer-basic-info">
                                        <?php if (!empty($customer['phone'])): ?>
                                        <p class="mb-1"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($customer['phone']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($customer['email'])): ?>
                                        <p class="mb-1"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($customer['email']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header">基本情報</div>
                            <div class="card-body">
                                <table class="table table-borderless table-sm">
                                    <tbody>
                                        <tr>
                                            <th scope="row" width="30%">性別</th>
                                            <td>
                                                <?php
                                                switch($customer['gender']) {
                                                    case 'male':
                                                        echo '男性';
                                                        break;
                                                    case 'female':
                                                        echo '女性';
                                                        break;
                                                    case 'other':
                                                        echo 'その他';
                                                        break;
                                                    default:
                                                        echo '未設定';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">生年月日</th>
                                            <td><?php echo !empty($customer['birthday']) ? date('Y年m月d日', strtotime($customer['birthday'])) : '未設定'; ?></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">住所</th>
                                            <td><?php echo !empty($customer['address']) ? htmlspecialchars($customer['address']) : '未設定'; ?></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">ステータス</th>
                                            <td>
                                                <span class="badge <?php echo $customer['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $customer['status'] == 'active' ? 'アクティブ' : '非アクティブ'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">登録日</th>
                                            <td><?php echo date('Y年m月d日', strtotime($customer['created_at'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">最終更新日</th>
                                            <td><?php echo date('Y年m月d日', strtotime($customer['updated_at'])); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header">来店情報</div>
                            <div class="card-body">
                                <table class="table table-borderless table-sm">
                                    <tbody>
                                        <tr>
                                            <th scope="row" width="30%">初回来店日</th>
                                            <td><?php echo !empty($customer['first_visit_date']) ? date('Y年m月d日', strtotime($customer['first_visit_date'])) : '未来店'; ?></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">最終来店日</th>
                                            <td><?php echo !empty($customer['last_visit_date']) ? date('Y年m月d日', strtotime($customer['last_visit_date'])) : '未来店'; ?></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">来店回数</th>
                                            <td><?php echo intval($customer['visit_count']); ?>回</td>
                                        </tr>
                                        <tr>
                                            <th scope="row">総利用金額</th>
                                            <td>¥<?php echo number_format(intval($customer['total_spent'])); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="card mb-3">
                            <div class="card-header">備考</div>
                            <div class="card-body">
                                <p class="mb-0"><?php echo !empty($customer['notes']) ? nl2br(htmlspecialchars($customer['notes'])) : '備考情報がありません。'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">予約履歴</div>
                    <div class="card-body">
                        <?php if (empty($recent_appointments)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> 予約履歴はありません。
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>予約日</th>
                                            <th>時間</th>
                                            <th>サービス</th>
                                            <th>担当者</th>
                                            <th>金額</th>
                                            <th>状態</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_appointments as $appointment): ?>
                                            <tr>
                                                <td><?php echo date('Y年m月d日', strtotime($appointment['appointment_date'])); ?></td>
                                                <td><?php echo date('H:i', strtotime($appointment['start_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['service_name'] ?? '不明'); ?></td>
                                                <td><?php echo htmlspecialchars(($appointment['staff_last_name'] ?? '') . ' ' . ($appointment['staff_first_name'] ?? '')); ?></td>
                                                <td><?php echo isset($appointment['service_price']) ? '¥' . number_format($appointment['service_price']) : '-'; ?></td>
                                                <td>
                                                    <?php
                                                    $status_text = '未設定';
                                                    $status_class = 'bg-secondary';
                                                    
                                                    switch ($appointment['status']) {
                                                        case 'confirmed':
                                                            $status_text = '確定';
                                                            $status_class = 'bg-success';
                                                            break;
                                                        case 'pending':
                                                            $status_text = '保留中';
                                                            $status_class = 'bg-warning text-dark';
                                                            break;
                                                        case 'canceled':
                                                            $status_text = 'キャンセル';
                                                            $status_class = 'bg-danger';
                                                            break;
                                                        case 'completed':
                                                            $status_text = '完了';
                                                            $status_class = 'bg-info';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center mb-4">
                    <div class="alert alert-secondary">
                        <small>
                            <i class="fas fa-clock me-1"></i> システム日時：2025年3月26日 <?php echo date('H:i:s'); ?>
                        </small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 