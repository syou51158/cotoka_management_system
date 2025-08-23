<?php
/**
 * 顧客データ取得API
 * 
 * メソッド: POST, HEAD
 * パラメータ:
 *   - salon_id: サロンID
 *   - search: (オプション) 検索キーワード
 *   - limit: (オプション) 取得件数制限
 *   - offset: (オプション) 取得開始位置
 */

// 出力バッファリングを開始
ob_start();

// HEADリクエストの処理 (APIの存在確認用)
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('HTTP/1.1 200 OK');
    exit;
}

// ヘッダー設定
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// データベース設定とクラスを読み込み
require_once '../../config/config.php';
require_once '../../classes/Database.php';

// エラーログ設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// セッションを開始
session_start();

// デバッグ用のログ出力
error_log("API呼び出し: get_customers.php");

// レスポンスデータの初期化
$response = [];

try {
    // POSTデータを取得
    $raw_input = file_get_contents('php://input');
    error_log("受信データ (raw): " . $raw_input);
    
    $data = json_decode($raw_input, true);
    error_log("受信データ (decoded): " . json_encode($data));
    
    // バリデーション
    if (!isset($data['salon_id']) || !is_numeric($data['salon_id'])) {
        // APIのチェック用途の場合は、サロンIDが必須でないケースも許可
        if (empty($raw_input) || $raw_input === '{}') {
            error_log("API存在確認のためのリクエストと判断");
            echo json_encode(['message' => 'API is available. Please provide salon_id parameter.']);
            exit;
        }
        throw new Exception('サロンIDが指定されていません');
    }
    
    $salon_id = intval($data['salon_id']);
    $search = isset($data['search']) ? $data['search'] : '';
    $limit = isset($data['limit']) ? intval($data['limit']) : 100;
    $offset = isset($data['offset']) ? intval($data['offset']) : 0;
    
    // 検索条件の構築
    $searchCondition = '';
    $searchParams = [];
    
    if (!empty($search)) {
        $searchCondition = "
            AND (
                c.first_name LIKE :search 
                OR c.last_name LIKE :search 
                OR c.email LIKE :search 
                OR c.phone LIKE :search
            )
        ";
        $searchParams[':search'] = "%$search%";
    }
    
    // データベース接続
    $db = new Database();
    $conn = $db->getConnection();
    
    // クエリを実際のテーブル構造に合わせて修正
    $stmt = $conn->prepare("
        SELECT 
            c.customer_id,
            c.first_name,
            c.last_name,
            c.email,
            c.phone,
            c.address,
            c.birthday as birth_date,
            COALESCE(c.birthday, '') as birth_date,
            '' as gender,
            c.notes,
            c.visit_count,
            c.last_visit_date,
            c.total_spent,
            c.created_at,
            c.updated_at,
            COUNT(a.appointment_id) AS appointment_count,
            MAX(a.appointment_date) AS last_appointment_date
        FROM 
            customers c
        LEFT JOIN 
            appointments a ON c.customer_id = a.customer_id AND a.salon_id = :salon_id
        WHERE 
            c.salon_id = :salon_id
            $searchCondition
        GROUP BY 
            c.customer_id
        ORDER BY 
            c.last_name, c.first_name
        LIMIT :limit OFFSET :offset
    ");
    
    $stmt->bindParam(':salon_id', $salon_id);
    
    if (!empty($search)) {
        $stmt->bindParam(':search', $searchParams[':search']);
    }
    
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    // データを取得
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("取得した顧客数: " . count($customers));
    
    // データの整形
    foreach ($customers as &$customer) {
        // 表示用の名前を追加
        $customer['display_name'] = $customer['last_name'] . ' ' . $customer['first_name'];
        
        // 生年月日をフォーマット
        if (isset($customer['birth_date']) && $customer['birth_date']) {
            $birthDate = new DateTime($customer['birth_date']);
            $customer['birth_date_formatted'] = $birthDate->format('Y年m月d日');
            
            // 年齢を計算
            $now = new DateTime();
            $age = $now->diff($birthDate)->y;
            $customer['age'] = $age;
        } else {
            $customer['birth_date_formatted'] = '';
            $customer['age'] = null;
        }
        
        // 最終来店日をフォーマット
        if (isset($customer['last_visit_date']) && $customer['last_visit_date']) {
            $lastVisit = new DateTime($customer['last_visit_date']);
            $customer['last_visit_formatted'] = $lastVisit->format('Y年m月d日');
        } else {
            $customer['last_visit_formatted'] = '来店なし';
        }
        
        // 最終予約日をフォーマット
        if (isset($customer['last_appointment_date']) && $customer['last_appointment_date']) {
            $lastAppointment = new DateTime($customer['last_appointment_date']);
            $customer['last_appointment_formatted'] = $lastAppointment->format('Y年m月d日');
        } else {
            $customer['last_appointment_formatted'] = '予約なし';
        }
    }
    
    // レスポンスの設定
    $response = $customers;
    
} catch (Exception $e) {
    http_response_code(400);
    $response = [
        'error' => true,
        'message' => $e->getMessage()
    ];
    error_log('API例外エラー: ' . $e->getMessage());
} catch (PDOException $e) {
    http_response_code(500);
    $response = [
        'error' => true,
        'message' => '顧客データの取得中にエラーが発生しました: ' . $e->getMessage()
    ];
    error_log('顧客取得APIエラー: ' . $e->getMessage());
}

// 結果を返す
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?> 