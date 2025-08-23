<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../includes/functions.php';
require_once '../classes/User.php';

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// セッションチェック
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo 'Unauthorized access';
    exit;
}

// パラメータの取得
$action = isset($_GET['action']) ? $_GET['action'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'sales';
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// スタッフとサービスフィルター
$staff_id = isset($_GET['staff_id']) && !empty($_GET['staff_id']) ? $_GET['staff_id'] : null;
$service_id = isset($_GET['service_id']) && !empty($_GET['service_id']) ? $_GET['service_id'] : null;

// アクションチェック
if ($action !== 'export_report') {
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid action';
    exit;
}

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // サロンID取得
    $salon_id = getCurrentSalonId();
    
    // ユーザーがアクセス可能なサロンを取得
    $user_id = $_SESSION['user_id'];
    $user = new User($conn);
    $accessible_salons = $user->getAccessibleSalons($user_id);
    
    // サロンIDが設定されていない場合、最初のアクセス可能なサロンを選択
    if (!$salon_id && count($accessible_salons) > 0) {
        $salon_id = $accessible_salons[0]['salon_id'];
        $_SESSION['salon_id'] = $salon_id;
        $_SESSION['current_salon_id'] = $salon_id;
    }
    
    // アクセス可能なサロンがない場合はエラーを返す
    if (count($accessible_salons) === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'アクセス可能なサロンがありません。管理者にお問い合わせください。']);
        exit;
    }
    
    // 現在のサロンIDがアクセス可能なサロンのリストに含まれているか確認
    $salon_accessible = false;
    foreach ($accessible_salons as $salon) {
        if ($salon['salon_id'] == $salon_id) {
            $salon_accessible = true;
            break;
        }
    }
    
    // アクセス可能でない場合は最初のアクセス可能なサロンに切り替え
    if (!$salon_accessible && count($accessible_salons) > 0) {
        $salon_id = $accessible_salons[0]['salon_id'];
        $_SESSION['salon_id'] = $salon_id;
        $_SESSION['current_salon_id'] = $salon_id;
    }
    
    // サロン情報取得
    $salon_query = "SELECT name as salon_name FROM salons WHERE salon_id = :salon_id";
    $salon_stmt = $conn->prepare($salon_query);
    $salon_stmt->bindParam(':salon_id', $salon_id, PDO::PARAM_INT);
    $salon_stmt->execute();
    $salon = $salon_stmt->fetch(PDO::FETCH_ASSOC);
    $salon_name = $salon ? $salon['salon_name'] : 'サロン';
    
    // レポートタイプに応じたデータ取得と出力
    switch ($report_type) {
        case 'sales':
            exportSalesReport($conn, $salon_id, $start_date, $end_date, $format, $salon_name, $staff_id, $service_id);
            break;
            
        case 'customers':
            exportCustomersReport($conn, $salon_id, $start_date, $end_date, $format, $salon_name, $staff_id, $service_id);
            break;
            
        case 'services':
            exportServicesReport($conn, $salon_id, $start_date, $end_date, $format, $salon_name, $staff_id);
            break;
            
        case 'staff':
            exportStaffReport($conn, $salon_id, $start_date, $end_date, $format, $salon_name, $service_id);
            break;
            
        case 'comparison':
            $comparison_start_date = isset($_GET['comparison_start_date']) ? $_GET['comparison_start_date'] : '';
            $comparison_end_date = isset($_GET['comparison_end_date']) ? $_GET['comparison_end_date'] : '';
            exportComparisonReport($conn, $salon_id, $start_date, $end_date, $comparison_start_date, $comparison_end_date, $format, $salon_name);
            break;
            
        default:
            header('HTTP/1.1 400 Bad Request');
            echo 'Invalid report type';
            exit;
    }
} catch (PDOException $e) {
    error_log('データベース接続エラー: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Database connection error';
    exit;
}

/**
 * 売上レポートのエクスポート
 */
function exportSalesReport($conn, $salon_id, $start_date, $end_date, $format, $salon_name, $staff_id = null, $service_id = null) {
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
    
    // 日別売上データ取得
    $query = "
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
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 出力ファイル名
    $filename = $salon_name . '_売上レポート_' . $start_date . '_' . $end_date;
    
    // ヘッダー行
    $headers = ['日付', '売上', '取引数', '顧客数', '平均客単価', '新規顧客', 'リピート顧客'];
    
    // データ行の準備
    $data = [];
    foreach ($sales_data as $row) {
        $avg_sale = $row['transaction_count'] > 0 ? (int)($row['daily_sales'] / $row['transaction_count']) : 0;
        
        $data[] = [
            (new DateTime($row['sale_date']))->format('Y/m/d'),
            number_format($row['daily_sales']) . '円',
            $row['transaction_count'],
            $row['customer_count'],
            number_format($avg_sale) . '円',
            $row['new_customers'],
            $row['repeat_customers']
        ];
    }
    
    // 合計行の追加
    $total_sales = array_sum(array_column($sales_data, 'daily_sales'));
    $total_transactions = array_sum(array_column($sales_data, 'transaction_count'));
    $total_customers = array_sum(array_column($sales_data, 'customer_count'));
    $avg_sale_total = $total_transactions > 0 ? (int)($total_sales / $total_transactions) : 0;
    $total_new = array_sum(array_column($sales_data, 'new_customers'));
    $total_repeat = array_sum(array_column($sales_data, 'repeat_customers'));
    
    $data[] = [
        '合計',
        number_format($total_sales) . '円',
        $total_transactions,
        $total_customers,
        number_format($avg_sale_total) . '円',
        $total_new,
        $total_repeat
    ];
    
    // フォーマットに応じたエクスポート
    if ($format === 'csv') {
        exportToCsv($filename, $headers, $data);
    } else {
        exportToPdf($filename, $headers, $data, $salon_name . ' 売上レポート', $start_date, $end_date);
    }
}

/**
 * 顧客レポートのエクスポート
 */
function exportCustomersReport($conn, $salon_id, $start_date, $end_date, $format, $salon_name, $staff_id = null, $service_id = null) {
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
    
    // 顧客データ取得
    $query = "
        SELECT 
            c.customer_name,
            c.customer_kana,
            c.phone,
            c.email,
            c.first_visit_date,
            MAX(s.sale_date) as last_visit,
            COUNT(DISTINCT s.sale_id) as visit_count,
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
            LEFT JOIN sales s ON c.customer_id = s.customer_id
            LEFT JOIN sale_items si ON s.sale_id = si.sale_id
        WHERE 
            c.salon_id = :salon_id
            AND s.sale_date BETWEEN :start_date AND :end_date
            $additional_where
        GROUP BY 
            c.customer_id
        ORDER BY 
            total_spent DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $customer_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 出力ファイル名
    $filename = $salon_name . '_顧客レポート_' . $start_date . '_' . $end_date;
    
    // ヘッダー行
    $headers = ['顧客名', '顧客カナ', '電話番号', 'メール', '初回来店日', '最終来店日', '来店回数', '累計売上', '平均客単価', '好みのサービス'];
    
    // データ行の準備
    $data = [];
    foreach ($customer_data as $row) {
        $data[] = [
            $row['customer_name'],
            $row['customer_kana'],
            $row['phone'],
            $row['email'],
            $row['first_visit_date'] ? (new DateTime($row['first_visit_date']))->format('Y/m/d') : '-',
            $row['last_visit'] ? (new DateTime($row['last_visit']))->format('Y/m/d') : '-',
            $row['visit_count'],
            number_format($row['total_spent']) . '円',
            number_format($row['average_spent']) . '円',
            $row['favorite_service'] ?: '-'
        ];
    }
    
    // フォーマットに応じたエクスポート
    if ($format === 'csv') {
        exportToCsv($filename, $headers, $data);
    } else {
        exportToPdf($filename, $headers, $data, $salon_name . ' 顧客レポート', $start_date, $end_date);
    }
}

/**
 * サービスレポートのエクスポート
 */
function exportServicesReport($conn, $salon_id, $start_date, $end_date, $format, $salon_name, $staff_id = null) {
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
    
    // サービスデータ取得
    $query = "
        SELECT 
            sv.service_name,
            sv.service_category,
            sv.price as standard_price,
            SUM(si.price * si.quantity) as total_sales,
            COUNT(si.sale_item_id) as service_count,
            COUNT(DISTINCT s.customer_id) as customer_count,
            AVG(si.price) as average_price
        FROM 
            services sv
            LEFT JOIN sale_items si ON sv.service_id = si.service_id
            LEFT JOIN sales s ON si.sale_id = s.sale_id
        WHERE 
            sv.salon_id = :salon_id
            AND s.sale_date BETWEEN :start_date AND :end_date
            $additional_where
        GROUP BY 
            sv.service_id
        ORDER BY 
            total_sales DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $service_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 総売上の計算（割合計算用）
    $total_service_sales = array_sum(array_column($service_data, 'total_sales'));
    
    // 出力ファイル名
    $filename = $salon_name . '_サービスレポート_' . $start_date . '_' . $end_date;
    
    // ヘッダー行
    $headers = ['サービス名', 'カテゴリ', '標準価格', '売上', '提供回数', '売上割合', '利用顧客数', '平均単価'];
    
    // データ行の準備
    $data = [];
    foreach ($service_data as $row) {
        // 売上割合の計算
        $percentage = $total_service_sales > 0 ? ($row['total_sales'] / $total_service_sales) * 100 : 0;
        
        $data[] = [
            $row['service_name'],
            $row['service_category'],
            number_format($row['standard_price']) . '円',
            number_format($row['total_sales']) . '円',
            $row['service_count'],
            round($percentage, 1) . '%',
            $row['customer_count'],
            number_format($row['average_price']) . '円'
        ];
    }
    
    // フォーマットに応じたエクスポート
    if ($format === 'csv') {
        exportToCsv($filename, $headers, $data);
    } else {
        exportToPdf($filename, $headers, $data, $salon_name . ' サービスレポート', $start_date, $end_date);
    }
}

/**
 * スタッフレポートのエクスポート
 */
function exportStaffReport($conn, $salon_id, $start_date, $end_date, $format, $salon_name, $service_id = null) {
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
    
    // スタッフデータ取得
    $query = "
        SELECT 
            st.staff_name,
            st.staff_kana,
            st.position,
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
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $staff_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 出力ファイル名
    $filename = $salon_name . '_スタッフレポート_' . $start_date . '_' . $end_date;
    
    // ヘッダー行
    $headers = ['スタッフ名', 'スタッフカナ', '役職', '売上', '顧客数', 'サービス提供数', '平均売上', '得意サービス'];
    
    // データ行の準備
    $data = [];
    foreach ($staff_data as $row) {
        // 平均売上の計算
        $average_sales = $row['customer_count'] > 0 ? (int)($row['total_sales'] / $row['customer_count']) : 0;
        
        $data[] = [
            $row['staff_name'],
            $row['staff_kana'],
            $row['position'] ?: '-',
            number_format($row['total_sales']) . '円',
            $row['customer_count'],
            $row['service_count'],
            number_format($average_sales) . '円',
            $row['top_service'] ?: '-'
        ];
    }
    
    // フォーマットに応じたエクスポート
    if ($format === 'csv') {
        exportToCsv($filename, $headers, $data);
    } else {
        exportToPdf($filename, $headers, $data, $salon_name . ' スタッフレポート', $start_date, $end_date);
    }
}

/**
 * 比較レポートのエクスポート
 */
function exportComparisonReport($conn, $salon_id, $base_start_date, $base_end_date, $comparison_start_date, $comparison_end_date, $format, $salon_name) {
    // 期間表示のフォーマット
    $base_period = formatPeriodDisplay($base_start_date, $base_end_date);
    $comparison_period = formatPeriodDisplay($comparison_start_date, $comparison_end_date);
    
    // 基準期間データ取得
    $base_params = [
        ':salon_id' => $salon_id,
        ':start_date' => $base_start_date,
        ':end_date' => $base_end_date
    ];
    
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
    
    $base_stmt = $conn->prepare($base_query);
    $base_stmt->execute($base_params);
    $base_data = $base_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 比較期間データ取得
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
    
    // 出力ファイル名
    $filename = $salon_name . '_比較レポート_' . $base_start_date . '_' . $comparison_end_date;
    
    // ヘッダー行
    $headers = ['指標', '基準期間 (' . $base_period . ')', '比較期間 (' . $comparison_period . ')', '差分', '変化率'];
    
    // データ行の準備
    $data = [
        [
            '総売上',
            number_format($base_data['total_sales']) . '円',
            number_format($comparison_data['total_sales']) . '円',
            number_format($base_data['total_sales'] - $comparison_data['total_sales']) . '円',
            calculateChangePercentage($base_data['total_sales'], $comparison_data['total_sales']) . '%'
        ],
        [
            '顧客数',
            $base_data['customer_count'],
            $comparison_data['customer_count'],
            $base_data['customer_count'] - $comparison_data['customer_count'],
            calculateChangePercentage($base_data['customer_count'], $comparison_data['customer_count']) . '%'
        ],
        [
            '取引数',
            $base_data['transaction_count'],
            $comparison_data['transaction_count'],
            $base_data['transaction_count'] - $comparison_data['transaction_count'],
            calculateChangePercentage($base_data['transaction_count'], $comparison_data['transaction_count']) . '%'
        ],
        [
            '平均客単価',
            number_format($base_average_sale) . '円',
            number_format($comparison_average_sale) . '円',
            number_format($base_average_sale - $comparison_average_sale) . '円',
            calculateChangePercentage($base_average_sale, $comparison_average_sale) . '%'
        ],
        [
            '新規顧客',
            $base_data['new_customers'],
            $comparison_data['new_customers'],
            $base_data['new_customers'] - $comparison_data['new_customers'],
            calculateChangePercentage($base_data['new_customers'], $comparison_data['new_customers']) . '%'
        ],
        [
            'リピート率',
            round($base_repeat_rate, 1) . '%',
            round($comparison_repeat_rate, 1) . '%',
            round($base_repeat_rate - $comparison_repeat_rate, 1) . '%',
            calculateChangePercentage($base_repeat_rate, $comparison_repeat_rate) . '%'
        ]
    ];
    
    // フォーマットに応じたエクスポート
    if ($format === 'csv') {
        exportToCsv($filename, $headers, $data);
    } else {
        exportToPdf($filename, $headers, $data, $salon_name . ' 比較レポート', $base_start_date, $comparison_end_date);
    }
}

/**
 * CSVとしてエクスポート
 */
function exportToCsv($filename, $headers, $data) {
    // ヘッダー設定
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // 出力バッファを開始
    $output = fopen('php://output', 'w');
    
    // BOMを出力（UTF-8でExcelで開くため）
    fputs($output, "\xEF\xBB\xBF");
    
    // ヘッダー行を出力
    fputcsv($output, $headers);
    
    // データ行を出力
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    // バッファを閉じる
    fclose($output);
    exit;
}

/**
 * PDFとしてエクスポート（簡易版、実際はライブラリが必要）
 */
function exportToPdf($filename, $headers, $data, $title, $start_date, $end_date) {
    // ヘッダー設定
    header('Content-Type: application/pdf; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    
    // TCPDFライブラリの読み込み（実際の実装では必要）
    // ここでは簡易的なPDF生成を行うためのコードを記述します
    
    // 実際のプロジェクトでは以下のようなコードを使用します
    /*
    require_once('../vendor/tcpdf/tcpdf.php');
    
    // PDFオブジェクトの生成
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8');
    
    // ドキュメント情報の設定
    $pdf->SetCreator('サロン管理システム');
    $pdf->SetAuthor($salon_name);
    $pdf->SetTitle($title);
    
    // ヘッダーフッターの設定
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetFooterMargin(10);
    $pdf->setFooterFont(Array('kozgopromedium', '', 8));
    $pdf->setFooterData(array(0,0,0), array(0,0,0));
    
    // デフォルトの余白設定
    $pdf->SetMargins(10, 10, 10);
    
    // 自動ページ区切り
    $pdf->SetAutoPageBreak(true, 10);
    
    // フォント設定
    $pdf->SetFont('kozgopromedium', '', 10);
    
    // 最初のページを追加
    $pdf->AddPage();
    
    // タイトルの出力
    $pdf->Cell(0, 10, $title, 0, 1, 'C');
    $pdf->Cell(0, 5, '期間: ' . (new DateTime($start_date))->format('Y年m月d日') . ' 〜 ' . (new DateTime($end_date))->format('Y年m月d日'), 0, 1, 'C');
    $pdf->Ln(5);
    
    // テーブルヘッダーの出力
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetFont('kozgopromedium', 'B', 9);
    
    $col_width = 280 / count($headers);
    foreach ($headers as $header) {
        $pdf->Cell($col_width, 7, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // テーブルデータの出力
    $pdf->SetFont('kozgopromedium', '', 9);
    foreach ($data as $row) {
        foreach ($row as $cell) {
            $pdf->Cell($col_width, 6, $cell, 1, 0, 'L');
        }
        $pdf->Ln();
    }
    
    // PDF出力
    $pdf->Output($filename . '.pdf', 'D');
    */
    
    // 簡易的なHTML表示（実際のPDFライブラリがない場合のフォールバック）
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            body { font-family: Arial, sans-serif; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            h1 { text-align: center; }
            .period { text-align: center; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <h1>' . htmlspecialchars($title) . '</h1>
        <div class="period">期間: ' . (new DateTime($start_date))->format('Y年m月d日') . ' 〜 ' . (new DateTime($end_date))->format('Y年m月d日') . '</div>
        
        <table>
            <tr>';
    
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    
    echo '</tr>';
    
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>
    </body>
    </html>';
    
    exit;
}

/**
 * 期間表示のフォーマット
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
 * 変化率の計算
 */
function calculateChangePercentage($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    
    return round((($current - $previous) / $previous) * 100, 1);
} 