<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../includes/functions.php';

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// セッションチェック
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// POSTリクエストチェック
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (PDOException $e) {
    error_log('データベース接続エラー: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

// サロンID取得（複数店舗の場合）
$salon_id = isset($_SESSION['salon_id']) ? $_SESSION['salon_id'] : 1;

// パラメータの取得
$action = isset($_POST['action']) ? $_POST['action'] : '';
$report_type = isset($_POST['report_type']) ? $_POST['report_type'] : 'sales';
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');

// スタッフとサービスフィルター
$staff_id = isset($_POST['staff_id']) && !empty($_POST['staff_id']) ? $_POST['staff_id'] : null;
$service_id = isset($_POST['service_id']) && !empty($_POST['service_id']) ? $_POST['service_id'] : null;

// アクション確認
if ($action !== 'get_report_data') {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// レポートデータの取得
$response = [
    'success' => true,
    'report_type' => $report_type,
    'period' => [
        'start_date' => $start_date,
        'end_date' => $end_date,
        'display' => formatPeriodDisplay($start_date, $end_date)
    ]
];

// 前期間のデータ（比較用）
$previous_start_date = getPreviousPeriod($start_date, $end_date, 'start');
$previous_end_date = getPreviousPeriod($start_date, $end_date, 'end');

// レポートタイプに応じた処理
switch ($report_type) {
    case 'sales':
        // Supabase RPCで売上サマリーと日次を取得
        $summaryRpc = supabaseRpcCall('report_sales_summary', [
            'p_salon_id' => (int)$salon_id,
            'p_start' => $start_date,
            'p_end' => $end_date
        ]);
        $dailyRpc = supabaseRpcCall('report_sales_daily', [
            'p_salon_id' => (int)$salon_id,
            'p_start' => $start_date,
            'p_end' => $end_date
        ]);

        $summary = ['total_sales'=>['value'=>0,'change'=>0], 'transaction_count'=>['value'=>0,'change'=>0], 'customer_count'=>['value'=>0,'change'=>0], 'average_sales'=>['value'=>0,'change'=>0]];
        if ($summaryRpc['success']) {
            $row = is_array($summaryRpc['data']) ? ($summaryRpc['data'][0] ?? null) : null;
            if ($row) {
                $summary['total_sales']['value'] = (int)($row['total_sales'] ?? 0);
                $summary['transaction_count']['value'] = (int)($row['transaction_count'] ?? 0);
                $summary['customer_count']['value'] = (int)($row['customer_count'] ?? 0);
                $summary['average_sales']['value'] = (int)($row['average_sales'] ?? 0);
            }
        }

        $charts = ['trend'=>['labels'=>[], 'data'=>[]], 'services'=>['labels'=>[], 'data'=>[]]];
        $table = [];
        if ($dailyRpc['success']) {
            $rows = is_array($dailyRpc['data']) ? $dailyRpc['data'] : [];
            foreach ($rows as $r) {
                $date = isset($r['sale_date']) ? (new DateTime($r['sale_date']))->format('Y-m-d') : '';
                $charts['trend']['labels'][] = (new DateTime($date))->format('m/d');
                $charts['trend']['data'][] = (int)($r['total_sales'] ?? 0);
                $table[] = [
                    'date' => (new DateTime($date))->format('Y年m月d日'),
                    'total_sales' => (int)($r['total_sales'] ?? 0),
                    'transaction_count' => (int)($r['transaction_count'] ?? 0),
                    'average_sales' => (int)((($r['transaction_count'] ?? 0) > 0) ? (($r['total_sales'] ?? 0) / $r['transaction_count']) : 0),
                    'new_customers' => 0,
                    'repeat_customers' => 0
                ];
            }
        }

        $response = array_merge($response, [
            'summary' => $summary,
            'charts' => $charts,
            'table_data' => $table
        ]);
        break;
        
    case 'customers':
        $custRpc = supabaseRpcCall('report_customers_summary', [
            'p_salon_id' => (int)$salon_id,
            'p_start' => $start_date,
            'p_end' => $end_date
        ]);
        if ($custRpc['success']) {
            $row = is_array($custRpc['data']) ? ($custRpc['data'][0] ?? null) : null;
            $response['summary'] = [
                'total_customers' => ['value' => (int)($row['total_customers'] ?? 0)],
                'new_customers' => ['value' => (int)($row['new_customers'] ?? 0)],
                'repeat_rate' => ['value' => (float)($row['repeat_rate'] ?? 0)],
                'average_customer_value' => ['value' => (float)($row['average_customer_value'] ?? 0)]
            ];
            $response['charts'] = ['customer' => ['labels' => [], 'new' => [], 'repeat' => []]];
            $response['table_data'] = [];
            $response['kpi'] = [
                'customer_acquisition' => ['value' => 0, 'percentage' => 0],
                'customer_retention' => ['value' => (float)($row['repeat_rate'] ?? 0), 'percentage' => 0]
            ];
        } else {
            $response['success'] = false; $response['message'] = $custRpc['message'] ?? '';
        }
        break;
        
    case 'services':
        $svcRpc = supabaseRpcCall('report_services_summary', [
            'p_salon_id' => (int)$salon_id,
            'p_start' => $start_date,
            'p_end' => $end_date
        ]);
        if ($svcRpc['success']) {
            $response['summary'] = [];
            $response['table_data'] = [];
            $labels = []; $data = [];
            $rows = is_array($svcRpc['data']) ? $svcRpc['data'] : [];
            foreach ($rows as $r) {
                $labels[] = $r['service_name'] ?? '';
                $data[] = (int)($r['total_sales'] ?? 0);
                $response['table_data'][] = [
                    'service_name' => $r['service_name'] ?? '-',
                    'total_sales' => (int)($r['total_sales'] ?? 0),
                    'service_count' => (int)($r['service_count'] ?? 0),
                    'percentage' => 0,
                    'customer_count' => (int)($r['customer_count'] ?? 0),
                    'average_price' => (int)($r['average_price'] ?? 0)
                ];
            }
            $response['charts'] = ['services' => ['labels' => $labels, 'data' => $data]];
        } else { $response['success'] = false; $response['message'] = $svcRpc['message'] ?? ''; }
        break;
        
    case 'staff':
        $stfRpc = supabaseRpcCall('report_staff_summary', [
            'p_salon_id' => (int)$salon_id,
            'p_start' => $start_date,
            'p_end' => $end_date
        ]);
        if ($stfRpc['success']) {
            $response['summary'] = [];
            $response['table_data'] = [];
            $labels = []; $data = [];
            $rows = is_array($stfRpc['data']) ? $stfRpc['data'] : [];
            foreach ($rows as $r) {
                $labels[] = $r['staff_name'] ?? '';
                $data[] = (int)($r['total_sales'] ?? 0);
                $response['table_data'][] = [
                    'staff_name' => $r['staff_name'] ?? '-',
                    'total_sales' => (int)($r['total_sales'] ?? 0),
                    'customer_count' => (int)($r['customer_count'] ?? 0),
                    'service_count' => (int)($r['service_count'] ?? 0),
                    'average_sales' => (int)($r['average_sales'] ?? 0),
                    'top_service' => '-'
                ];
            }
            $response['charts'] = ['services' => ['labels' => $labels, 'data' => $data]];
        } else { $response['success'] = false; $response['message'] = $stfRpc['message'] ?? ''; }
        break;
        
    case 'comparison':
        $comparison_start_date = isset($_POST['comparison_start_date']) ? $_POST['comparison_start_date'] : $previous_start_date;
        $comparison_end_date = isset($_POST['comparison_end_date']) ? $_POST['comparison_end_date'] : $previous_end_date;
        $cmpRpc = supabaseRpcCall('report_comparison', [
            'p_salon_id' => (int)$salon_id,
            'p_start' => $start_date,
            'p_end' => $end_date,
            'p_comp_start' => $comparison_start_date,
            'p_comp_end' => $comparison_end_date
        ]);
        if ($cmpRpc['success']) {
            $row = is_array($cmpRpc['data']) ? ($cmpRpc['data'][0] ?? null) : null;
            $response['table_data'] = [
                ['metric'=>'総売上','base_value'=>(int)($row['base_total_sales']??0),'comparison_value'=>(int)($row['comp_total_sales']??0),'difference'=>(int)(($row['base_total_sales']??0)-($row['comp_total_sales']??0)),'percentage'=>0],
                ['metric'=>'顧客数','base_value'=>(int)($row['base_customer_count']??0),'comparison_value'=>(int)($row['comp_customer_count']??0),'difference'=>(int)(($row['base_customer_count']??0)-($row['comp_customer_count']??0)),'percentage'=>0],
                ['metric'=>'平均客単価','base_value'=>0,'comparison_value'=>0,'difference'=>0,'percentage'=>0],
                ['metric'=>'新規顧客','base_value'=>(int)($row['base_new_customers']??0),'comparison_value'=>(int)($row['comp_new_customers']??0),'difference'=>(int)(($row['base_new_customers']??0)-($row['comp_new_customers']??0)),'percentage'=>0]
            ];
        } else { $response['success'] = false; $response['message'] = $cmpRpc['message'] ?? ''; }
        break;
        
    default:
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Invalid report type']);
        exit;
}

// レスポンス返却
header('Content-Type: application/json');
echo json_encode($response);
exit;

/**
 * 期間表示のフォーマット
 * 
 * @param string $start_date 開始日
 * @param string $end_date 終了日
 * @return string フォーマットされた期間表示
 */
function formatPeriodDisplay($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    
    if ($start->format('Y-m') == $end->format('Y-m')) {
        // 同じ月内
        return $start->format('Y年m月');
    } else if ($start->format('Y') == $end->format('Y')) {
        // 同じ年内の異なる月
        return $start->format('Y年m月d日') . ' 〜 ' . $end->format('m月d日');
    } else {
        // 異なる年
        return $start->format('Y年m月d日') . ' 〜 ' . $end->format('Y年m月d日');
    }
}

/**
 * 前期間の算出
 * 
 * @param string $start_date 開始日
 * @param string $end_date 終了日
 * @param string $type 'start'または'end'
 * @return string 前期間の日付
 */
function getPreviousPeriod($start_date, $end_date, $type) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    
    // 期間の長さ（日数）を計算
    $interval = $start->diff($end);
    $days = $interval->days + 1; // 終了日を含める
    
    // 前期間の計算
    if ($type === 'start') {
        $previous_start = clone $start;
        $previous_start->modify("-{$days} days");
        return $previous_start->format('Y-m-d');
    } else {
        $previous_end = clone $start;
        $previous_end->modify('-1 day');
        return $previous_end->format('Y-m-d');
    }
}

/**
 * 売上レポートデータの取得
 * 
 * @param PDO $conn データベース接続
 * @param int $salon_id サロンID
 * @param string $start_date 開始日
 * @param string $end_date 終了日
 * @param string $previous_start_date 前期間開始日
 * @param string $previous_end_date 前期間終了日
 * @param int|null $staff_id スタッフID
 * @param int|null $service_id サービスID
 * @return array レポートデータ
 */
function getSalesReportData($conn, $salon_id, $start_date, $end_date, $previous_start_date, $previous_end_date, $staff_id = null, $service_id = null) {
    // データ配列初期化
    $data = [
        'summary' => [],
        'charts' => [
            'trend' => [
                'labels' => [],
                'data' => []
            ],
            'services' => [
                'labels' => [],
                'data' => []
            ]
        ],
        'table_data' => []
    ];
    
    // 追加のWHERE条件
    $additional_where = '';
    $params = [
        ':salon_id' => $salon_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];
    
    if ($staff_id) {
        $additional_where .= " AND s.staff_id = :staff_id";
        $params[':staff_id'] = $staff_id;
    }
    
    if ($service_id) {
        $additional_where .= " AND si.service_id = :service_id";
        $params[':service_id'] = $service_id;
    }
    
    // 現在期間の売上概要データ取得
    $total_sales_query = "
        SELECT 
            SUM(s.total_amount) as total_sales,
            COUNT(DISTINCT s.sale_id) as transaction_count,
            COUNT(DISTINCT s.customer_id) as customer_count
        FROM 
            sales s
            LEFT JOIN sale_items si ON s.sale_id = si.sale_id
        WHERE 
            s.salon_id = :salon_id 
            AND s.sale_date BETWEEN :start_date AND :end_date
            $additional_where
    ";
    
    $total_sales_stmt = $conn->prepare($total_sales_query);
    $total_sales_stmt->execute($params);
    $current_totals = $total_sales_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 前期間の売上概要データ取得
    $params[':start_date'] = $previous_start_date;
    $params[':end_date'] = $previous_end_date;
    
    $previous_sales_stmt = $conn->prepare($total_sales_query);
    $previous_sales_stmt->execute($params);
    $previous_totals = $previous_sales_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 変化率の計算
    $total_sales_change = calculateChangePercentage($current_totals['total_sales'], $previous_totals['total_sales']);
    $transaction_count_change = calculateChangePercentage($current_totals['transaction_count'], $previous_totals['transaction_count']);
    $customer_count_change = calculateChangePercentage($current_totals['customer_count'], $previous_totals['customer_count']);
    
    // 平均客単価の計算
    $average_sales = $current_totals['transaction_count'] > 0 
        ? $current_totals['total_sales'] / $current_totals['transaction_count'] 
        : 0;
    
    $previous_average_sales = $previous_totals['transaction_count'] > 0 
        ? $previous_totals['total_sales'] / $previous_totals['transaction_count'] 
        : 0;
    
    $average_sales_change = calculateChangePercentage($average_sales, $previous_average_sales);
    
    // サマリーデータの設定
    $data['summary'] = [
        'total_sales' => [
            'value' => (int)$current_totals['total_sales'],
            'change' => $total_sales_change
        ],
        'transaction_count' => [
            'value' => (int)$current_totals['transaction_count'],
            'change' => $transaction_count_change
        ],
        'customer_count' => [
            'value' => (int)$current_totals['customer_count'],
            'change' => $customer_count_change
        ],
        'average_sales' => [
            'value' => (int)$average_sales,
            'change' => $average_sales_change
        ]
    ];
    
    // 日別売上データの取得
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
    
    $daily_sales_query = "
        SELECT 
            s.sale_date,
            SUM(s.total_amount) as daily_sales,
            COUNT(DISTINCT s.sale_id) as transaction_count,
            COUNT(DISTINCT s.customer_id) as customer_count,
            COUNT(DISTINCT CASE WHEN c.first_visit_date = s.sale_date THEN c.customer_id END) as new_customers,
            COUNT(DISTINCT CASE WHEN c.first_visit_date < s.sale_date THEN c.customer_id END) as repeat_customers
        FROM 
            sales s
            LEFT JOIN sale_items si ON s.sale_id = si.sale_id
            LEFT JOIN customers c ON s.customer_id = c.customer_id
        WHERE 
            s.salon_id = :salon_id 
            AND s.sale_date BETWEEN :start_date AND :end_date
            $additional_where
        GROUP BY 
            s.sale_date
        ORDER BY 
            s.sale_date
    ";
    
    $daily_sales_stmt = $conn->prepare($daily_sales_query);
    $daily_sales_stmt->execute($params);
    $daily_sales = $daily_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // チャートデータとテーブルデータの作成
    foreach ($daily_sales as $day) {
        // チャートデータ
        $data['charts']['trend']['labels'][] = (new DateTime($day['sale_date']))->format('m/d');
        $data['charts']['trend']['data'][] = (int)$day['daily_sales'];
        
        // テーブルデータ
        $avg_sale = $day['transaction_count'] > 0 ? (int)($day['daily_sales'] / $day['transaction_count']) : 0;
        
        $data['table_data'][] = [
            'date' => (new DateTime($day['sale_date']))->format('Y年m月d日'),
            'total_sales' => (int)$day['daily_sales'],
            'transaction_count' => (int)$day['transaction_count'],
            'average_sales' => $avg_sale,
            'new_customers' => (int)$day['new_customers'],
            'repeat_customers' => (int)$day['repeat_customers']
        ];
    }
    
    // サービス別売上データ取得
    $service_sales_query = "
        SELECT 
            sv.service_name,
            SUM(si.price * si.quantity) as service_sales
        FROM 
            sale_items si
            JOIN sales s ON si.sale_id = s.sale_id
            JOIN services sv ON si.service_id = sv.service_id
        WHERE 
            s.salon_id = :salon_id 
            AND s.sale_date BETWEEN :start_date AND :end_date
            $additional_where
        GROUP BY 
            sv.service_id
        ORDER BY 
            service_sales DESC
        LIMIT 10
    ";
    
    $service_sales_stmt = $conn->prepare($service_sales_query);
    $service_sales_stmt->execute($params);
    $service_sales = $service_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // サービス売上チャートデータの作成
    foreach ($service_sales as $service) {
        $data['charts']['services']['labels'][] = $service['service_name'];
        $data['charts']['services']['data'][] = (int)$service['service_sales'];
    }
    
    return $data;
}

/**
 * 顧客レポートデータの取得
 * 
 * @param PDO $conn データベース接続
 * @param int $salon_id サロンID
 * @param string $start_date 開始日
 * @param string $end_date 終了日
 * @param string $previous_start_date 前期間開始日
 * @param string $previous_end_date 前期間終了日
 * @param int|null $staff_id スタッフID
 * @param int|null $service_id サービスID
 * @return array レポートデータ
 */
function getCustomersReportData($conn, $salon_id, $start_date, $end_date, $previous_start_date, $previous_end_date, $staff_id = null, $service_id = null) {
    // データ配列初期化
    $data = [
        'summary' => [],
        'charts' => [
            'customer' => [
                'labels' => [],
                'new' => [],
                'repeat' => []
            ]
        ],
        'table_data' => [],
        'kpi' => []
    ];
    
    // 追加のWHERE条件
    $additional_where = '';
    $params = [
        ':salon_id' => $salon_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];
    
    if ($staff_id) {
        $additional_where .= " AND s.staff_id = :staff_id";
        $params[':staff_id'] = $staff_id;
    }
    
    if ($service_id) {
        $additional_where .= " AND si.service_id = :service_id";
        $params[':service_id'] = $service_id;
    }
    
    // 現在期間の顧客データ取得
    $customer_query = "
        SELECT 
            COUNT(DISTINCT c.customer_id) as total_customers,
            COUNT(DISTINCT CASE WHEN c.first_visit_date BETWEEN :start_date AND :end_date THEN c.customer_id END) as new_customers,
            COUNT(DISTINCT CASE WHEN c.first_visit_date < :start_date AND s.sale_date BETWEEN :start_date AND :end_date THEN c.customer_id END) as repeat_customers,
            SUM(s.total_amount) as total_spent
        FROM 
            customers c
            LEFT JOIN sales s ON c.customer_id = s.customer_id
            LEFT JOIN sale_items si ON s.sale_id = si.sale_id
        WHERE 
            c.salon_id = :salon_id 
            AND s.sale_date BETWEEN :start_date AND :end_date
            $additional_where
    ";
    
    $customer_stmt = $conn->prepare($customer_query);
    $customer_stmt->execute($params);
    $current_customers = $customer_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 前期間の顧客データ取得
    $params[':start_date'] = $previous_start_date;
    $params[':end_date'] = $previous_end_date;
    
    $previous_customer_stmt = $conn->prepare($customer_query);
    $previous_customer_stmt->execute($params);
    $previous_customers = $previous_customer_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 変化率の計算
    $total_customers_change = calculateChangePercentage($current_customers['total_customers'], $previous_customers['total_customers']);
    $new_customers_change = calculateChangePercentage($current_customers['new_customers'], $previous_customers['new_customers']);
    
    // リピート率の計算
    $repeat_rate = $current_customers['total_customers'] > 0 
        ? ($current_customers['repeat_customers'] / $current_customers['total_customers']) * 100 
        : 0;
    
    $previous_repeat_rate = $previous_customers['total_customers'] > 0 
        ? ($previous_customers['repeat_customers'] / $previous_customers['total_customers']) * 100 
        : 0;
    
    $repeat_rate_change = calculateChangePercentage($repeat_rate, $previous_repeat_rate);
    
    // 顧客生涯価値の計算（簡易版）
    $all_time_query = "
        SELECT 
            AVG(customer_total) as avg_customer_value
        FROM (
            SELECT 
                c.customer_id,
                SUM(s.total_amount) as customer_total
            FROM 
                customers c
                JOIN sales s ON c.customer_id = s.customer_id
            WHERE 
                c.salon_id = :salon_id
                $additional_where
            GROUP BY 
                c.customer_id
        ) as customer_totals
    ";
    
    $params = [
        ':salon_id' => $salon_id
    ];
    
    if ($staff_id) {
        $params[':staff_id'] = $staff_id;
    }
    
    if ($service_id) {
        $params[':service_id'] = $service_id;
    }
    
    $all_time_stmt = $conn->prepare($all_time_query);
    $all_time_stmt->execute($params);
    $all_time_data = $all_time_stmt->fetch(PDO::FETCH_ASSOC);
    
    $avg_customer_value = (int)$all_time_data['avg_customer_value'];
    $previous_avg_customer_value = $avg_customer_value * 0.9; // 仮の前期間値
    $customer_value_change = calculateChangePercentage($avg_customer_value, $previous_avg_customer_value);
    
    // サマリーデータの設定
    $data['summary'] = [
        'total_customers' => [
            'value' => (int)$current_customers['total_customers'],
            'change' => $total_customers_change
        ],
        'new_customers' => [
            'value' => (int)$current_customers['new_customers'],
            'change' => $new_customers_change
        ],
        'repeat_rate' => [
            'value' => round($repeat_rate, 1),
            'change' => $repeat_rate_change
        ],
        'average_customer_value' => [
            'value' => $avg_customer_value,
            'change' => $customer_value_change
        ]
    ];
    
    // 月別顧客推移データの取得
    $params = [
        ':salon_id' => $salon_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];
    
    if ($staff_id) {
        $params[':staff_id'] = $staff_id;
    }
    
    if ($service_id) {
        $params[':service_id'] = $service_id;
    }
    
    $monthly_customer_query = "
        SELECT 
            DATE_FORMAT(s.sale_date, '%Y-%m') as month,
            COUNT(DISTINCT CASE WHEN c.first_visit_date = s.sale_date THEN c.customer_id END) as new_customers,
            COUNT(DISTINCT CASE WHEN c.first_visit_date < s.sale_date THEN c.customer_id END) as repeat_customers
        FROM 
            sales s
            JOIN customers c ON s.customer_id = c.customer_id
            LEFT JOIN sale_items si ON s.sale_id = si.sale_id
        WHERE 
            s.salon_id = :salon_id 
            AND s.sale_date BETWEEN :start_date AND :end_date
            $additional_where
        GROUP BY 
            DATE_FORMAT(s.sale_date, '%Y-%m')
        ORDER BY 
            month
    ";
    
    $monthly_customer_stmt = $conn->prepare($monthly_customer_query);
    $monthly_customer_stmt->execute($params);
    $monthly_customers = $monthly_customer_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // チャートデータの作成
    foreach ($monthly_customers as $month) {
        $date = new DateTime($month['month'] . '-01');
        $data['charts']['customer']['labels'][] = $date->format('Y年m月');
        $data['charts']['customer']['new'][] = (int)$month['new_customers'];
        $data['charts']['customer']['repeat'][] = (int)$month['repeat_customers'];
    }
    
    // 顧客詳細データの取得
    $customer_details_query = "
        SELECT 
            c.customer_name,
            COUNT(DISTINCT s.sale_id) as visit_count,
            MAX(s.sale_date) as last_visit,
            SUM(s.total_amount) as total_spent,
            AVG(s.total_amount) as average_spent,
            (
                SELECT sv.service_name
                FROM sale_items si
                JOIN sales s2 ON si.sale_id = s2.sale_id
                JOIN services sv ON si.service_id = sv.service_id
                WHERE s2.customer_id = c.customer_id
                GROUP BY si.service_id
                ORDER BY COUNT(si.sale_item_id) DESC
                LIMIT 1
            ) as favorite_service
        FROM 
            customers c
            JOIN sales s ON c.customer_id = s.customer_id
        WHERE 
            c.salon_id = :salon_id
            AND s.sale_date BETWEEN :start_date AND :end_date
            $additional_where
        GROUP BY 
            c.customer_id
        ORDER BY 
            total_spent DESC
        LIMIT 100
    ";
    
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
    
    $customer_details_stmt = $conn->prepare($customer_details_query);
    $customer_details_stmt->execute($params);
    $customer_details = $customer_details_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // テーブルデータの作成
    foreach ($customer_details as $customer) {
        $data['table_data'][] = [
            'customer_name' => $customer['customer_name'],
            'visit_count' => (int)$customer['visit_count'],
            'last_visit' => (new DateTime($customer['last_visit']))->format('Y年m月d日'),
            'total_spent' => (int)$customer['total_spent'],
            'average_spent' => (int)$customer['average_spent'],
            'favorite_service' => $customer['favorite_service'] ?: '-'
        ];
    }
    
    // KPI指標の設定
    $acquisition_rate = $current_customers['total_customers'] > 0 
        ? ($current_customers['new_customers'] / $current_customers['total_customers']) * 100 
        : 0;
    
    $retention_rate = $current_customers['total_customers'] > 0 
        ? ($current_customers['repeat_customers'] / $current_customers['total_customers']) * 100 
        : 0;
    
    $data['kpi'] = [
        'customer_acquisition' => [
            'value' => round($acquisition_rate, 1),
            'percentage' => min(100, round(($acquisition_rate / 20) * 100)) // 目標20%に対する達成率
        ],
        'customer_retention' => [
            'value' => round($retention_rate, 1),
            'percentage' => min(100, round(($retention_rate / 70) * 100)) // 目標70%に対する達成率
        ]
    ];
    
    return $data;
}

/**
 * サービスレポートデータの取得
 * 
 * @param PDO $conn データベース接続
 * @param int $salon_id サロンID
 * @param string $start_date 開始日
 * @param string $end_date 終了日
 * @param string $previous_start_date 前期間開始日
 * @param string $previous_end_date 前期間終了日
 * @param int|null $staff_id スタッフID
 * @return array レポートデータ
 */
function getServicesReportData($conn, $salon_id, $start_date, $end_date, $previous_start_date, $previous_end_date, $staff_id = null) {
    // データ配列初期化
    $data = [
        'summary' => [],
        'table_data' => []
    ];
    
    // 追加のWHERE条件
    $additional_where = '';
    $params = [
        ':salon_id' => $salon_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];
    
    if ($staff_id) {
        $additional_where .= " AND s.staff_id = :staff_id";
        $params[':staff_id'] = $staff_id;
    }
    
    // 現在期間のサービス概要データ取得
    $service_totals_query = "
        SELECT 
            SUM(si.price * si.quantity) as total_service_sales,
            COUNT(si.sale_item_id) as service_count
        FROM 
            sale_items si
            JOIN sales s ON si.sale_id = s.sale_id
        WHERE 
            s.salon_id = :salon_id 
            AND s.sale_date BETWEEN :start_date AND :end_date
            $additional_where
    ";
    
    $service_totals_stmt = $conn->prepare($service_totals_query);
    $service_totals_stmt->execute($params);
    $current_totals = $service_totals_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 前期間のサービス概要データ取得
    $params[':start_date'] = $previous_start_date;
    $params[':end_date'] = $previous_end_date;
    
    $previous_service_stmt = $conn->prepare($service_totals_query);
    $previous_service_stmt->execute($params);
    $previous_totals = $previous_service_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 変化率の計算
    $total_service_sales_change = calculateChangePercentage($current_totals['total_service_sales'], $previous_totals['total_service_sales']);
    $service_count_change = calculateChangePercentage($current_totals['service_count'], $previous_totals['service_count']);
    
    // 最人気サービスの取得
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
    
    $top_service_query = "
        SELECT 
            sv.service_name,
            COUNT(si.sale_item_id) as service_count
        FROM 
            sale_items si
            JOIN sales s ON si.sale_id = s.sale_id
            JOIN services sv ON si.service_id = sv.service_id
        WHERE 
            s.salon_id = :salon_id 
            AND s.sale_date BETWEEN :start_date AND :end_date
            $additional_where
        GROUP BY 
            sv.service_id
        ORDER BY 
            service_count DESC
        LIMIT 1
    ";
    
    $top_service_stmt = $conn->prepare($top_service_query);
    $top_service_stmt->execute($params);
    $top_service = $top_service_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 平均サービス単価の計算
    $average_service_price = $current_totals['service_count'] > 0 
        ? $current_totals['total_service_sales'] / $current_totals['service_count'] 
        : 0;
    
    $previous_average_price = $previous_totals['service_count'] > 0 
        ? $previous_totals['total_service_sales'] / $previous_totals['service_count'] 
        : 0;
    
    $average_price_change = calculateChangePercentage($average_service_price, $previous_average_price);
    
    // サマリーデータの設定
    $data['summary'] = [
        'total_service_sales' => [
            'value' => (int)$current_totals['total_service_sales'],
            'change' => $total_service_sales_change
        ],
        'service_count' => [
            'value' => (int)$current_totals['service_count'],
            'change' => $service_count_change
        ],
        'top_service' => [
            'value' => $top_service['service_name'] ?? '-',
            'change' => null
        ],
        'average_service_price' => [
            'value' => (int)$average_service_price,
            'change' => $average_price_change
        ]
    ];
    
    // サービス詳細データの取得
    $service_details_query = "
        SELECT 
            sv.service_name,
            SUM(si.price * si.quantity) as total_sales,
            COUNT(si.sale_item_id) as service_count,
            COUNT(DISTINCT s.customer_id) as customer_count,
            AVG(si.price) as average_price
        FROM 
            sale_items si
            JOIN sales s ON si.sale_id = s.sale_id
            JOIN services sv ON si.service_id = sv.service_id
        WHERE 
            s.salon_id = :salon_id 
            AND s.sale_date BETWEEN :start_date AND :end_date
            $additional_where
        GROUP BY 
            sv.service_id
        ORDER BY 
            total_sales DESC
    ";
    
    $service_details_stmt = $conn->prepare($service_details_query);
    $service_details_stmt->execute($params);
    $service_details = $service_details_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 総売上の取得（割合計算用）
    $total_sales = $current_totals['total_service_sales'];
    
    // テーブルデータの作成
    foreach ($service_details as $service) {
        // 売上割合の計算
        $percentage = $total_sales > 0 ? ($service['total_sales'] / $total_sales) * 100 : 0;
        
        $data['table_data'][] = [
            'service_name' => $service['service_name'],
            'total_sales' => (int)$service['total_sales'],
            'service_count' => (int)$service['service_count'],
            'percentage' => round($percentage, 1),
            'customer_count' => (int)$service['customer_count'],
            'average_price' => (int)$service['average_price']
        ];
    }
    
    return $data;
}

/**
 * スタッフレポートデータの取得
 * 
 * @param PDO $conn データベース接続
 * @param int $salon_id サロンID
 * @param string $start_date 開始日
 * @param string $end_date 終了日
 * @param string $previous_start_date 前期間開始日
 * @param string $previous_end_date 前期間終了日
 * @param int|null $service_id サービスID
 * @return array レポートデータ
 */
function getStaffReportData($conn, $salon_id, $start_date, $end_date, $previous_start_date, $previous_end_date, $service_id = null) {
    // データ配列初期化
    $data = [
        'summary' => [],
        'table_data' => []
    ];
    
    // 追加のWHERE条件
    $additional_where = '';
    $params = [
        ':salon_id' => $salon_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];
    
    if ($service_id) {
        $additional_where .= " AND si.service_id = :service_id";
        $params[':service_id'] = $service_id;
    }
    
    // 現在期間のスタッフ概要データ取得
    $staff_totals_query = "
        SELECT 
            SUM(s.total_amount) as total_staff_sales,
            COUNT(DISTINCT s.customer_id) as staff_customer_count
        FROM 
            sales s
            LEFT JOIN sale_items si ON s.sale_id = si.sale_id
        WHERE 
            s.salon_id = :salon_id 
            AND s.sale_date BETWEEN :start_date AND :end_date
            $additional_where
    ";
    
    $staff_totals_stmt = $conn->prepare($staff_totals_query);
    $staff_totals_stmt->execute($params);
    $current_totals = $staff_totals_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 前期間のスタッフ概要データ取得
    $params[':start_date'] = $previous_start_date;
    $params[':end_date'] = $previous_end_date;
    
    $previous_staff_stmt = $conn->prepare($staff_totals_query);
    $previous_staff_stmt->execute($params);
    $previous_totals = $previous_staff_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 変化率の計算
    $total_staff_sales_change = calculateChangePercentage($current_totals['total_staff_sales'], $previous_totals['total_staff_sales']);
    $staff_customer_count_change = calculateChangePercentage($current_totals['staff_customer_count'], $previous_totals['staff_customer_count']);
    
    // 最も売上の高いスタッフの取得
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
    
    $top_staff_query = "
        SELECT 
            st.staff_name,
            SUM(s.total_amount) as staff_sales
        FROM 
            sales s
            LEFT JOIN sale_items si ON s.sale_id = si.sale_id
            JOIN staff st ON s.staff_id = st.staff_id
        WHERE 
            s.salon_id = :salon_id 
            AND s.sale_date BETWEEN :start_date AND :end_date
            $additional_where
        GROUP BY 
            st.staff_id
        ORDER BY 
            staff_sales DESC
        LIMIT 1
    ";
    
    $top_staff_stmt = $conn->prepare($top_staff_query);
    $top_staff_stmt->execute($params);
    $top_staff = $top_staff_stmt->fetch(PDO::FETCH_ASSOC);
    
    // スタッフ数の取得
    $staff_count_query = "
        SELECT 
            COUNT(DISTINCT s.staff_id) as staff_count
        FROM 
            sales s
            LEFT JOIN sale_items si ON s.sale_id = si.sale_id
        WHERE 
            s.salon_id = :salon_id 
            AND s.sale_date BETWEEN :start_date AND :end_date
            $additional_where
    ";
    
    $staff_count_stmt = $conn->prepare($staff_count_query);
    $staff_count_stmt->execute($params);
    $staff_count_data = $staff_count_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 平均スタッフ売上の計算
    $average_staff_sales = $staff_count_data['staff_count'] > 0 
        ? $current_totals['total_staff_sales'] / $staff_count_data['staff_count'] 
        : 0;
    
    // 前期間の平均スタッフ売上（簡易計算）
    $previous_average_staff_sales = $average_staff_sales * 0.9; // 仮の前期間値
    $average_staff_sales_change = calculateChangePercentage($average_staff_sales, $previous_average_staff_sales);
    
    // サマリーデータの設定
    $data['summary'] = [
        'total_staff_sales' => [
            'value' => (int)$current_totals['total_staff_sales'],
            'change' => $total_staff_sales_change
        ],
        'staff_customer_count' => [
            'value' => (int)$current_totals['staff_customer_count'],
            'change' => $staff_customer_count_change
        ],
        'top_staff' => [
            'value' => $top_staff['staff_name'] ?? '-',
            'change' => null
        ],
        'average_staff_sales' => [
            'value' => (int)$average_staff_sales,
            'change' => $average_staff_sales_change
        ]
    ];
    
    // スタッフ詳細データの取得
    $staff_details_query = "
        SELECT 
            st.staff_name,
            SUM(s.total_amount) as total_sales,
            COUNT(DISTINCT s.customer_id) as customer_count,
            COUNT(si.sale_item_id) as service_count,
            (
                SELECT sv.service_name
                FROM sale_items si2
                JOIN sales s2 ON si2.sale_id = s2.sale_id
                JOIN services sv ON si2.service_id = sv.service_id
                WHERE s2.staff_id = st.staff_id
                AND s2.sale_date BETWEEN :start_date AND :end_date
                GROUP BY si2.service_id
                ORDER BY COUNT(si2.sale_item_id) DESC
                LIMIT 1
            ) as top_service
        FROM 
            staff st
            LEFT JOIN sales s ON st.staff_id = s.staff_id
            LEFT JOIN sale_items si ON s.sale_id = si.sale_id
        WHERE 
            st.salon_id = :salon_id 
            AND st.status = 'active'
            AND s.sale_date BETWEEN :start_date AND :end_date
            $additional_where
        GROUP BY 
            st.staff_id
        ORDER BY 
            total_sales DESC
    ";
    
    $staff_details_stmt = $conn->prepare($staff_details_query);
    $staff_details_stmt->execute($params);
    $staff_details = $staff_details_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // テーブルデータの作成
    foreach ($staff_details as $staff) {
        // 平均売上の計算
        $average_sales = $staff['customer_count'] > 0 ? (int)($staff['total_sales'] / $staff['customer_count']) : 0;
        
        $data['table_data'][] = [
            'staff_name' => $staff['staff_name'],
            'total_sales' => (int)$staff['total_sales'],
            'customer_count' => (int)$staff['customer_count'],
            'service_count' => (int)$staff['service_count'],
            'average_sales' => $average_sales,
            'top_service' => $staff['top_service'] ?: '-'
        ];
    }
    
    return $data;
}

/**
 * 比較レポートデータの取得
 * 
 * @param PDO $conn データベース接続
 * @param int $salon_id サロンID
 * @param string $start_date 基準期間開始日
 * @param string $end_date 基準期間終了日
 * @param string $comparison_start_date 比較期間開始日
 * @param string $comparison_end_date 比較期間終了日
 * @return array レポートデータ
 */
function getComparisonReportData($conn, $salon_id, $start_date, $end_date, $comparison_start_date, $comparison_end_date) {
    // データ配列初期化
    $data = [
        'table_data' => []
    ];
    
    // 基準期間のデータ取得
    $base_query = "
        SELECT 
            SUM(s.total_amount) as total_sales,
            COUNT(DISTINCT s.customer_id) as customer_count,
            COUNT(DISTINCT s.sale_id) as transaction_count,
            COUNT(DISTINCT CASE WHEN c.first_visit_date BETWEEN :start_date AND :end_date THEN c.customer_id END) as new_customers
        FROM 
            sales s
            LEFT JOIN customers c ON s.customer_id = c.customer_id
        WHERE 
            s.salon_id = :salon_id 
            AND s.sale_date BETWEEN :start_date AND :end_date
    ";
    
    $base_params = [
        ':salon_id' => $salon_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];
    
    $base_stmt = $conn->prepare($base_query);
    $base_stmt->execute($base_params);
    $base_data = $base_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 比較期間のデータ取得
    $comparison_params = [
        ':salon_id' => $salon_id,
        ':start_date' => $comparison_start_date,
        ':end_date' => $comparison_end_date
    ];
    
    $comparison_stmt = $conn->prepare($base_query);
    $comparison_stmt->execute($comparison_params);
    $comparison_data = $comparison_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 平均客単価の計算
    $base_average_sale = $base_data['transaction_count'] > 0 
        ? $base_data['total_sales'] / $base_data['transaction_count'] 
        : 0;
    
    $comparison_average_sale = $comparison_data['transaction_count'] > 0 
        ? $comparison_data['total_sales'] / $comparison_data['transaction_count'] 
        : 0;
    
    // リピート率の計算
    $base_repeat_customers = $base_data['customer_count'] - $base_data['new_customers'];
    $base_repeat_rate = $base_data['customer_count'] > 0 
        ? ($base_repeat_customers / $base_data['customer_count']) * 100 
        : 0;
    
    $comparison_repeat_customers = $comparison_data['customer_count'] - $comparison_data['new_customers'];
    $comparison_repeat_rate = $comparison_data['customer_count'] > 0 
        ? ($comparison_repeat_customers / $comparison_data['customer_count']) * 100 
        : 0;
    
    // 比較データの作成
    $data['table_data'] = [
        [
            'metric' => '総売上',
            'base_value' => (int)$base_data['total_sales'],
            'comparison_value' => (int)$comparison_data['total_sales'],
            'difference' => (int)$base_data['total_sales'] - (int)$comparison_data['total_sales'],
            'percentage' => calculateChangePercentage($base_data['total_sales'], $comparison_data['total_sales'])
        ],
        [
            'metric' => '顧客数',
            'base_value' => (int)$base_data['customer_count'],
            'comparison_value' => (int)$comparison_data['customer_count'],
            'difference' => (int)$base_data['customer_count'] - (int)$comparison_data['customer_count'],
            'percentage' => calculateChangePercentage($base_data['customer_count'], $comparison_data['customer_count'])
        ],
        [
            'metric' => '平均客単価',
            'base_value' => (int)$base_average_sale,
            'comparison_value' => (int)$comparison_average_sale,
            'difference' => (int)$base_average_sale - (int)$comparison_average_sale,
            'percentage' => calculateChangePercentage($base_average_sale, $comparison_average_sale)
        ],
        [
            'metric' => '新規顧客',
            'base_value' => (int)$base_data['new_customers'],
            'comparison_value' => (int)$comparison_data['new_customers'],
            'difference' => (int)$base_data['new_customers'] - (int)$comparison_data['new_customers'],
            'percentage' => calculateChangePercentage($base_data['new_customers'], $comparison_data['new_customers'])
        ],
        [
            'metric' => 'リピート率',
            'base_value' => round($base_repeat_rate, 1),
            'comparison_value' => round($comparison_repeat_rate, 1),
            'difference' => round($base_repeat_rate - $comparison_repeat_rate, 1),
            'percentage' => calculateChangePercentage($base_repeat_rate, $comparison_repeat_rate)
        ]
    ];
    
    return $data;
}

/**
 * 変化率の計算
 * 
 * @param float $current 現在の値
 * @param float $previous 前の値
 * @return float 変化率（%）
 */
function calculateChangePercentage($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    
    return round((($current - $previous) / $previous) * 100, 1);
} 