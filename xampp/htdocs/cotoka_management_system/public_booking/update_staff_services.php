<?php
/**
 * スタッフサービスの可用性を確認・修正するスクリプト
 * 
 * このスクリプトは以下の機能を提供します:
 * 1. スタッフがサービスを提供できるかのチェック
 * 2. 不足しているスタッフ-サービス関連の登録
 * 3. シフト情報の確認
 */

// 必要なファイルをインクルード
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../includes/functions.php';

// セッション開始
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

// サロンID取得（GETパラメータまたはセッションから）
$salon_id = $_GET['salon_id'] ?? $_SESSION['booking_salon_id'] ?? null;

if (!$salon_id) {
    die("サロンIDが指定されていません。");
}

// 実行モード確認（dry-run=表示のみ, fix=問題を修正）
$mode = $_GET['mode'] ?? 'dry-run';
$is_fix_mode = ($mode === 'fix');

// ヘッダーとスタイル
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>スタッフサービス可用性チェック</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { padding: 20px; }
        .status-ok { color: green; }
        .status-warning { color: orange; }
        .status-error { color: red; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>スタッフサービス可用性チェック - サロンID: <?= htmlspecialchars($salon_id) ?></h1>
        <p>
            モード: <?= $is_fix_mode ? '<span class="badge badge-warning">修正モード</span>' : '<span class="badge badge-info">確認モード</span>' ?>
            <a href="?salon_id=<?= urlencode($salon_id) ?>&mode=<?= $is_fix_mode ? 'dry-run' : 'fix' ?>" class="btn btn-sm btn-outline-primary ml-2">
                <?= $is_fix_mode ? '確認モードに切替' : '修正モードに切替' ?>
            </a>
            <a href="select_datetime.php" class="btn btn-sm btn-outline-secondary ml-2">予約ページに戻る</a>
        </p>
        
        <hr>
        
        <?php
        // 1. サロン情報の確認
        try {
            $stmt = $conn->prepare("
                SELECT 
                    s.salon_id,
                    s.name as salon_name,
                    s.business_hours
                FROM salons s
                WHERE s.salon_id = :salon_id AND s.status = 'active'
            ");
            $stmt->bindParam(':salon_id', $salon_id);
            $stmt->execute();
            $salon = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$salon) {
                throw new Exception("指定されたサロンが見つかりませんでした。");
            }
            
            echo "<h3>1. サロン情報</h3>";
            echo "<p>サロン名: <strong>{$salon['salon_name']}</strong></p>";
            
            // 営業時間の確認
            $stmt = $conn->prepare("
                SELECT 
                    day_of_week,
                    open_time,
                    close_time,
                    is_closed
                FROM salon_business_hours
                WHERE salon_id = :salon_id
                ORDER BY day_of_week
            ");
            $stmt->bindParam(':salon_id', $salon_id);
            $stmt->execute();
            $business_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h4>営業時間</h4>";
            
            if (empty($business_hours)) {
                echo "<p class='status-error'>営業時間が設定されていません。</p>";
                
                if ($is_fix_mode) {
                    // デフォルトの営業時間を設定
                    $default_hours = [
                        ['day_of_week' => 0, 'open_time' => '10:00:00', 'close_time' => '19:00:00', 'is_closed' => 0], // 日曜日
                        ['day_of_week' => 1, 'open_time' => '10:00:00', 'close_time' => '19:00:00', 'is_closed' => 0], // 月曜日
                        ['day_of_week' => 2, 'open_time' => '10:00:00', 'close_time' => '19:00:00', 'is_closed' => 0], // 火曜日
                        ['day_of_week' => 3, 'open_time' => '10:00:00', 'close_time' => '19:00:00', 'is_closed' => 0], // 水曜日
                        ['day_of_week' => 4, 'open_time' => '10:00:00', 'close_time' => '19:00:00', 'is_closed' => 0], // 木曜日
                        ['day_of_week' => 5, 'open_time' => '10:00:00', 'close_time' => '19:00:00', 'is_closed' => 0], // 金曜日
                        ['day_of_week' => 6, 'open_time' => '10:00:00', 'close_time' => '19:00:00', 'is_closed' => 0]  // 土曜日
                    ];
                    
                    $stmt = $conn->prepare("
                        INSERT INTO salon_business_hours (salon_id, day_of_week, open_time, close_time, is_closed)
                        VALUES (:salon_id, :day_of_week, :open_time, :close_time, :is_closed)
                    ");
                    
                    foreach ($default_hours as $hour) {
                        $stmt->bindParam(':salon_id', $salon_id);
                        $stmt->bindParam(':day_of_week', $hour['day_of_week']);
                        $stmt->bindParam(':open_time', $hour['open_time']);
                        $stmt->bindParam(':close_time', $hour['close_time']);
                        $stmt->bindParam(':is_closed', $hour['is_closed']);
                        $stmt->execute();
                    }
                    
                    echo "<p class='status-ok'>デフォルトの営業時間を設定しました。すべての曜日 10:00-19:00</p>";
                    $business_hours = $default_hours;
                } else {
                    echo "<p>修正モードで実行すると、デフォルトの営業時間を設定します。</p>";
                }
            }
            
            // 営業時間の表示
            echo "<table class='table table-sm'>";
            echo "<thead><tr><th>曜日</th><th>開始時間</th><th>終了時間</th><th>ステータス</th></tr></thead>";
            echo "<tbody>";
            
            $days = ["日", "月", "火", "水", "木", "金", "土"];
            foreach ($business_hours as $hour) {
                $day_name = $days[$hour['day_of_week']];
                $status = $hour['is_closed'] ? "<span class='status-warning'>休業日</span>" : "<span class='status-ok'>営業</span>";
                $open_time = substr($hour['open_time'], 0, 5);
                $close_time = substr($hour['close_time'], 0, 5);
                
                echo "<tr>";
                echo "<td>{$day_name}</td>";
                echo "<td>{$open_time}</td>";
                echo "<td>{$close_time}</td>";
                echo "<td>{$status}</td>";
                echo "</tr>";
            }
            
            echo "</tbody></table>";
            
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>{$e->getMessage()}</div>";
        }
        
        // 2. スタッフ情報の確認
        try {
            $stmt = $conn->prepare("
                SELECT 
                    s.staff_id,
                    CONCAT(s.first_name, ' ', s.last_name) as name,
                    s.status
                FROM staff s
                WHERE s.salon_id = :salon_id 
                ORDER BY s.staff_id
            ");
            $stmt->bindParam(':salon_id', $salon_id);
            $stmt->execute();
            $staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<hr>";
            echo "<h3>2. スタッフ情報</h3>";
            
            if (empty($staffs)) {
                echo "<p class='status-error'>登録されているスタッフがいません。</p>";
            } else {
                echo "<p>スタッフ数: " . count($staffs) . "人</p>";
                
                echo "<table class='table table-sm'>";
                echo "<thead><tr><th>ID</th><th>名前</th><th>ステータス</th></tr></thead>";
                echo "<tbody>";
                
                foreach ($staffs as $staff) {
                    $status_class = ($staff['status'] === 'active') ? 'status-ok' : 'status-error';
                    
                    echo "<tr>";
                    echo "<td>{$staff['staff_id']}</td>";
                    echo "<td>{$staff['name']}</td>";
                    echo "<td class='{$status_class}'>{$staff['status']}</td>";
                    echo "</tr>";
                }
                
                echo "</tbody></table>";
            }
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>{$e->getMessage()}</div>";
        }
        
        // 3. サービス情報の確認
        try {
            $stmt = $conn->prepare("
                SELECT 
                    s.service_id,
                    s.name,
                    s.duration,
                    s.price
                FROM services s
                WHERE s.salon_id = :salon_id
                ORDER BY s.service_id
            ");
            $stmt->bindParam(':salon_id', $salon_id);
            $stmt->execute();
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<hr>";
            echo "<h3>3. サービス情報</h3>";
            
            if (empty($services)) {
                echo "<p class='status-error'>登録されているサービスがありません。</p>";
            } else {
                echo "<p>サービス数: " . count($services) . "件</p>";
                
                echo "<table class='table table-sm'>";
                echo "<thead><tr><th>ID</th><th>サービス名</th><th>所要時間（分）</th><th>価格</th></tr></thead>";
                echo "<tbody>";
                
                foreach ($services as $service) {
                    echo "<tr>";
                    echo "<td>{$service['service_id']}</td>";
                    echo "<td>{$service['name']}</td>";
                    echo "<td>{$service['duration']}</td>";
                    echo "<td>" . number_format($service['price']) . "円</td>";
                    echo "</tr>";
                }
                
                echo "</tbody></table>";
            }
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>{$e->getMessage()}</div>";
        }
        
        // 4. スタッフサービス関連の確認
        try {
            $stmt = $conn->prepare("
                SELECT 
                    ss.staff_id,
                    ss.service_id,
                    ss.is_active,
                    ser.name as service_name,
                    CONCAT(s.first_name, ' ', s.last_name) as staff_name
                FROM staff_services ss
                JOIN services ser ON ss.service_id = ser.service_id
                JOIN staff s ON ss.staff_id = s.staff_id
                WHERE ss.salon_id = :salon_id
                ORDER BY ss.staff_id, ss.service_id
            ");
            $stmt->bindParam(':salon_id', $salon_id);
            $stmt->execute();
            $staff_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<hr>";
            echo "<h3>4. スタッフとサービスの関連</h3>";
            
            if (empty($staff_services)) {
                echo "<p class='status-error'>スタッフとサービスの関連が設定されていません。</p>";
                
                if ($is_fix_mode && !empty($staffs) && !empty($services)) {
                    echo "<p>すべてのスタッフにすべてのサービスを割り当てます...</p>";
                    
                    $stmt = $conn->prepare("
                        INSERT INTO staff_services 
                        (staff_id, service_id, salon_id, tenant_id, is_active)
                        VALUES (:staff_id, :service_id, :salon_id, 1, 1)
                    ");
                    
                    $assigned_count = 0;
                    
                    foreach ($staffs as $staff) {
                        foreach ($services as $service) {
                            try {
                                $stmt->bindParam(':staff_id', $staff['staff_id']);
                                $stmt->bindParam(':service_id', $service['service_id']);
                                $stmt->bindParam(':salon_id', $salon_id);
                                $stmt->execute();
                                $assigned_count++;
                            } catch (PDOException $e) {
                                // 既に存在する場合は無視
                                if ($e->getCode() != 23000) {
                                    echo "<p class='status-error'>エラー: {$e->getMessage()}</p>";
                                }
                            }
                        }
                    }
                    
                    echo "<p class='status-ok'>{$assigned_count}件のサービスをスタッフに割り当てました。</p>";
                    
                    // 再取得
                    $stmt->execute();
                    $staff_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    echo "<p>修正モードで実行すると、すべてのスタッフにすべてのサービスを割り当てます。</p>";
                }
            }
            
            if (!empty($staff_services)) {
                echo "<p>スタッフ-サービス関連: " . count($staff_services) . "件</p>";
                
                // スタッフごとにグループ化
                $grouped = [];
                foreach ($staff_services as $relation) {
                    $staff_id = $relation['staff_id'];
                    if (!isset($grouped[$staff_id])) {
                        $grouped[$staff_id] = [
                            'staff_name' => $relation['staff_name'],
                            'services' => []
                        ];
                    }
                    
                    $grouped[$staff_id]['services'][] = [
                        'service_id' => $relation['service_id'],
                        'service_name' => $relation['service_name'],
                        'is_active' => $relation['is_active']
                    ];
                }
                
                // 各スタッフのサービス一覧を表示
                foreach ($grouped as $staff_id => $data) {
                    echo "<div class='card mb-3'>";
                    echo "<div class='card-header'>{$data['staff_name']} (ID: {$staff_id})</div>";
                    echo "<div class='card-body'>";
                    
                    echo "<table class='table table-sm'>";
                    echo "<thead><tr><th>サービスID</th><th>サービス名</th><th>ステータス</th></tr></thead>";
                    echo "<tbody>";
                    
                    foreach ($data['services'] as $service) {
                        $status = $service['is_active'] ? 
                            "<span class='status-ok'>有効</span>" : 
                            "<span class='status-error'>無効</span>";
                        
                        echo "<tr>";
                        echo "<td>{$service['service_id']}</td>";
                        echo "<td>{$service['service_name']}</td>";
                        echo "<td>{$status}</td>";
                        echo "</tr>";
                    }
                    
                    echo "</tbody></table>";
                    echo "</div></div>";
                }
            }
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>{$e->getMessage()}</div>";
        }
        
        // 5. シフト情報の確認 (今日から7日間)
        try {
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime('+7 days'));
            
            $stmt = $conn->prepare("
                SELECT 
                    ss.staff_id,
                    CONCAT(s.first_name, ' ', s.last_name) as staff_name,
                    ss.shift_date,
                    ss.start_time,
                    ss.end_time,
                    ss.status
                FROM staff_shifts ss
                JOIN staff s ON ss.staff_id = s.staff_id
                WHERE ss.salon_id = :salon_id 
                AND ss.shift_date BETWEEN :start_date AND :end_date
                ORDER BY ss.shift_date, ss.staff_id
            ");
            $stmt->bindParam(':salon_id', $salon_id);
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<hr>";
            echo "<h3>5. スタッフシフト情報 ({$start_date} 〜 {$end_date})</h3>";
            
            if (empty($shifts)) {
                echo "<p class='status-warning'>登録されているシフト情報がありません。</p>";
                
                if ($is_fix_mode && !empty($staffs) && !empty($business_hours)) {
                    echo "<p>今後7日間のデフォルトシフトを作成します...</p>";
                    
                    $stmt = $conn->prepare("
                        INSERT INTO staff_shifts 
                        (staff_id, salon_id, tenant_id, shift_date, start_time, end_time, status)
                        VALUES (:staff_id, :salon_id, 1, :shift_date, :start_time, :end_time, 'active')
                    ");
                    
                    $created_shifts = 0;
                    $current_date = new DateTime($start_date);
                    $end_date_obj = new DateTime($end_date);
                    
                    while ($current_date <= $end_date_obj) {
                        $date_str = $current_date->format('Y-m-d');
                        $day_of_week = $current_date->format('w'); // 0(日)～6(土)
                        
                        // 該当曜日の営業時間を取得
                        $business_hour = null;
                        foreach ($business_hours as $hour) {
                            if ($hour['day_of_week'] == $day_of_week) {
                                $business_hour = $hour;
                                break;
                            }
                        }
                        
                        // 営業日の場合のみシフト作成
                        if ($business_hour && !$business_hour['is_closed']) {
                            foreach ($staffs as $staff) {
                                try {
                                    $stmt->bindParam(':staff_id', $staff['staff_id']);
                                    $stmt->bindParam(':salon_id', $salon_id);
                                    $stmt->bindParam(':shift_date', $date_str);
                                    $stmt->bindParam(':start_time', $business_hour['open_time']);
                                    $stmt->bindParam(':end_time', $business_hour['close_time']);
                                    $stmt->execute();
                                    $created_shifts++;
                                } catch (PDOException $e) {
                                    // 既に存在する場合は無視
                                    if ($e->getCode() != 23000) {
                                        echo "<p class='status-error'>エラー: {$e->getMessage()}</p>";
                                    }
                                }
                            }
                        }
                        
                        $current_date->modify('+1 day');
                    }
                    
                    echo "<p class='status-ok'>{$created_shifts}件のシフトを作成しました。</p>";
                    
                    // 再取得
                    $stmt = $conn->prepare("
                        SELECT 
                            ss.staff_id,
                            CONCAT(s.first_name, ' ', s.last_name) as staff_name,
                            ss.shift_date,
                            ss.start_time,
                            ss.end_time,
                            ss.status
                        FROM staff_shifts ss
                        JOIN staff s ON ss.staff_id = s.staff_id
                        WHERE ss.salon_id = :salon_id 
                        AND ss.shift_date BETWEEN :start_date AND :end_date
                        ORDER BY ss.shift_date, ss.staff_id
                    ");
                    $stmt->bindParam(':salon_id', $salon_id);
                    $stmt->bindParam(':start_date', $start_date);
                    $stmt->bindParam(':end_date', $end_date);
                    $stmt->execute();
                    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    echo "<p>修正モードで実行すると、今後7日間のデフォルトシフトを作成します。</p>";
                }
            }
            
            if (!empty($shifts)) {
                echo "<p>シフト数: " . count($shifts) . "件</p>";
                
                // 日付ごとにグループ化
                $grouped_shifts = [];
                foreach ($shifts as $shift) {
                    $date = $shift['shift_date'];
                    if (!isset($grouped_shifts[$date])) {
                        $grouped_shifts[$date] = [];
                    }
                    
                    $grouped_shifts[$date][] = $shift;
                }
                
                // 各日付のシフト一覧を表示
                foreach ($grouped_shifts as $date => $daily_shifts) {
                    $weekday = date('w', strtotime($date));
                    $weekday_name = ["日", "月", "火", "水", "木", "金", "土"][$weekday];
                    
                    echo "<h4>{$date} ({$weekday_name})</h4>";
                    
                    echo "<table class='table table-sm'>";
                    echo "<thead><tr><th>スタッフ</th><th>開始時間</th><th>終了時間</th><th>ステータス</th></tr></thead>";
                    echo "<tbody>";
                    
                    foreach ($daily_shifts as $shift) {
                        $status_class = ($shift['status'] === 'active') ? 'status-ok' : 'status-error';
                        $start_time = substr($shift['start_time'], 0, 5);
                        $end_time = substr($shift['end_time'], 0, 5);
                        
                        echo "<tr>";
                        echo "<td>{$shift['staff_name']}</td>";
                        echo "<td>{$start_time}</td>";
                        echo "<td>{$end_time}</td>";
                        echo "<td class='{$status_class}'>{$shift['status']}</td>";
                        echo "</tr>";
                    }
                    
                    echo "</tbody></table>";
                }
            }
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>{$e->getMessage()}</div>";
        }
        ?>
        
        <hr>
        <p>
            <a href="select_datetime.php" class="btn btn-primary">予約ページに戻る</a>
            <a href="?salon_id=<?= urlencode($salon_id) ?>&mode=<?= $is_fix_mode ? 'dry-run' : 'fix' ?>" class="btn btn-outline-primary ml-2">
                <?= $is_fix_mode ? '確認モードに切替' : '修正モードに切替' ?>
            </a>
        </p>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 