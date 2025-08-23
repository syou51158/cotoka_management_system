<?php
// 必要なファイルをインクルード
require_once '../../config/config.php';
require_once '../../classes/Database.php';
require_once '../../includes/functions.php';

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'データベース接続エラー: ' . $e->getMessage()]);
    exit;
}

// サロンIDとテナントIDを取得
$salon_id = isset($_SESSION['salon_id']) ? $_SESSION['salon_id'] : 1;
$tenant_id = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : 1;

// 日付パラメータを取得
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// 日付の形式を検証
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => '無効な日付形式です。']);
    exit;
}

// 曜日を取得（0: 日曜日, 1: 月曜日, ..., 6: 土曜日）
$day_of_week = date('w', strtotime($date));

// スタッフリソースを取得（シフト情報を考慮）
try {
    // シフト情報を考慮したスタッフを取得
    $stmt = $conn->prepare("
        SELECT 
            s.staff_id,
            CONCAT(s.last_name, ' ', s.first_name) AS title,
            s.color,
            s.role,
            s.is_active,
            ss.start_time,
            ss.end_time
        FROM staff s
        LEFT JOIN (
            -- 指定された日付の特定のシフト
            SELECT staff_id, start_time, end_time
            FROM staff_shifts
            WHERE salon_id = :salon_id
            AND shift_date = :date
            AND status = 'active'
            
            UNION
            
            -- 特定のシフトがない場合、定期シフトパターンから取得
            SELECT ssp.staff_id, ssp.start_time, ssp.end_time
            FROM staff_shift_patterns ssp
            WHERE ssp.salon_id = :salon_id
            AND ssp.day_of_week = :day_of_week
            AND ssp.is_active = 1
            AND NOT EXISTS (
                SELECT 1 FROM staff_shifts ss
                WHERE ss.staff_id = ssp.staff_id
                AND ss.salon_id = ssp.salon_id
                AND ss.shift_date = :date
            )
        ) ss ON s.staff_id = ss.staff_id
        WHERE s.salon_id = :salon_id 
        AND s.is_active = 1
        AND ss.start_time IS NOT NULL
        ORDER BY s.display_order, s.last_name
    ");
    
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':day_of_week', $day_of_week);
    $stmt->execute();
    
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 結果がない場合は、すべてのアクティブなスタッフを返す（緊急用のフォールバック）
    if (empty($resources)) {
        $fallback_stmt = $conn->prepare("
            SELECT 
                staff_id,
                CONCAT(last_name, ' ', first_name) AS title,
                color,
                role,
                is_active,
                '09:00:00' AS start_time,
                '18:00:00' AS end_time
            FROM staff
            WHERE salon_id = :salon_id AND is_active = 1
            ORDER BY display_order, last_name
        ");
        $fallback_stmt->bindParam(':salon_id', $salon_id);
        $fallback_stmt->execute();
        $resources = $fallback_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // リソース配列をフォーマット
    $formatted_resources = [];
    foreach ($resources as $resource) {
        $formatted_resources[] = [
            'id' => 'staff_' . $resource['staff_id'],
            'title' => $resource['title'],
            'businessHours' => [
                'startTime' => $resource['start_time'],
                'endTime' => $resource['end_time'],
                'daysOfWeek' => [0, 1, 2, 3, 4, 5, 6] // すべての曜日
            ],
            'eventColor' => $resource['color'] ?: '#3788d8'
        ];
    }
    
    // JSON形式で返す
    header('Content-Type: application/json');
    echo json_encode($formatted_resources);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'リソース取得エラー: ' . $e->getMessage()]);
    exit;
} 