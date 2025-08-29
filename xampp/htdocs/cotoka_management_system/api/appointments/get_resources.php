<?php
// 必要なファイルをインクルード
require_once '../../config/config.php';
require_once '../../classes/Database.php';
require_once '../../includes/functions.php';

// Databaseクラス（Supabase）初期化
try {
    $db = Database::getInstance();
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
$day_of_week = (int)date('w', strtotime($date));

// スタッフリソースを取得（シフト情報を考慮）
try {
    // 1) サロン内のアクティブなスタッフ一覧を取得
    $staffRows = $db->fetchAll(
        'staff',
        [
            'salon_id' => $salon_id,
            'is_active' => 1,
        ],
        'staff_id,first_name,last_name,color,role,is_active,display_order',
        [
            'order' => 'display_order.asc,last_name.asc',
            'limit' => 2000,
        ]
    );

    $resources = [];

    foreach ($staffRows as $s) {
        $sid = $s['staff_id'];

        // 2) 指定日の個別シフトを確認
        $shift = $db->fetchOne(
            'staff_shifts',
            [
                'salon_id' => $salon_id,
                'staff_id' => $sid,
                'shift_date' => $date,
                'status' => 'active',
            ],
            'staff_id,start_time,end_time'
        );

        $start = null;
        $end = null;

        if ($shift && !empty($shift['start_time']) && !empty($shift['end_time'])) {
            $start = $shift['start_time'];
            $end = $shift['end_time'];
        } else {
            // 3) 個別シフトが無ければ、曜日に基づく定期シフトパターンを確認
            $pattern = $db->fetchOne(
                'staff_shift_patterns',
                [
                    'salon_id' => $salon_id,
                    'staff_id' => $sid,
                    'day_of_week' => $day_of_week,
                    'is_active' => 1,
                ],
                'staff_id,start_time,end_time'
            );

            if ($pattern && !empty($pattern['start_time']) && !empty($pattern['end_time'])) {
                $start = $pattern['start_time'];
                $end = $pattern['end_time'];
            }
        }

        // シフト情報があるスタッフのみをリソースに追加
        if ($start && $end) {
            $resources[] = [
                'staff_id' => $sid,
                'title' => trim(($s['last_name'] ?? '') . ' ' . ($s['first_name'] ?? '')),
                'color' => $s['color'] ?? null,
                'role' => $s['role'] ?? null,
                'is_active' => $s['is_active'] ?? 1,
                'start_time' => $start,
                'end_time' => $end,
            ];
        }
    }

    // 4) 取得できなかった場合はフォールバック（全アクティブスタッフを09:00-18:00で返す）
    if (empty($resources)) {
        foreach ($staffRows as $s) {
            $resources[] = [
                'staff_id' => $s['staff_id'],
                'title' => trim(($s['last_name'] ?? '') . ' ' . ($s['first_name'] ?? '')),
                'color' => $s['color'] ?? null,
                'role' => $s['role'] ?? null,
                'is_active' => $s['is_active'] ?? 1,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
            ];
        }
    }

    // 5) レスポンス用にフォーマット
    $formatted_resources = [];
    foreach ($resources as $resource) {
        $formatted_resources[] = [
            'id' => 'staff_' . $resource['staff_id'],
            'title' => $resource['title'],
            'businessHours' => [
                'startTime' => $resource['start_time'],
                'endTime' => $resource['end_time'],
                'daysOfWeek' => [0, 1, 2, 3, 4, 5, 6], // すべての曜日
            ],
            'eventColor' => !empty($resource['color']) ? $resource['color'] : '#3788d8',
        ];
    }

    // JSON形式で返す
    header('Content-Type: application/json');
    echo json_encode($formatted_resources);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'リソース取得エラー: ' . $e->getMessage()]);
    exit;
}