<?php
// エラー出力の設定（HTMLではなくJSON形式で返す）
ini_set('display_errors', 0);
error_reporting(E_ALL);

// エラーハンドラー関数の定義
function jsonErrorHandler($errno, $errstr, $errfile, $errline) {
    $error = [
        'success' => false,
        'message' => "エラーが発生しました: $errstr in $errfile on line $errline"
    ];
    header('Content-Type: application/json');
    echo json_encode($error);
    exit;
}

// JSONエラーハンドラーを設定
set_error_handler('jsonErrorHandler');

// 例外ハンドラー
function jsonExceptionHandler($exception) {
    $error = [
        'success' => false,
        'message' => "例外が発生しました: " . $exception->getMessage()
    ];
    header('Content-Type: application/json');
    echo json_encode($error);
    exit;
}

// 例外ハンドラーを設定
set_exception_handler('jsonExceptionHandler');

// 必要なファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'includes/functions.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// セッションチェック
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '認証されていません。']);
    exit;
}

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'データベース接続エラー: ' . $e->getMessage()]);
    exit;
}

// リクエストパラメータの検証
if (!isset($_POST['staff_id']) || !isset($_POST['start_date']) || !isset($_POST['end_date'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '必要なパラメータが不足しています。']);
    exit;
}

$staff_id = filter_var($_POST['staff_id'], FILTER_VALIDATE_INT);
$start_date = htmlspecialchars(trim($_POST['start_date']), ENT_QUOTES, 'UTF-8');
$end_date = htmlspecialchars(trim($_POST['end_date']), ENT_QUOTES, 'UTF-8');

// 日付形式の検証
if (!$staff_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '無効なパラメータが指定されました。']);
    exit;
}

// 現在のサロンIDとテナントIDを取得
$salon_id = isset($_SESSION['salon_id']) ? $_SESSION['salon_id'] : 1;
$tenant_id = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : 1;

// スタッフIDの検証
try {
    $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE staff_id = :staff_id AND salon_id = :salon_id");
    $stmt->bindParam(':staff_id', $staff_id);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '指定されたスタッフが見つかりません。']);
        exit;
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'スタッフ検証エラー: ' . $e->getMessage()]);
    exit;
}

// シフトパターンの取得
$patterns = [];
try {
    $stmt = $conn->prepare("
        SELECT day_of_week, start_time, end_time 
        FROM staff_shift_patterns 
        WHERE staff_id = :staff_id AND salon_id = :salon_id
    ");
    $stmt->bindParam(':staff_id', $staff_id);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($patterns)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'シフトパターンが設定されていません。']);
        exit;
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'シフトパターン取得エラー: ' . $e->getMessage()]);
    exit;
}

// 日付範囲の生成
$start = new DateTime($start_date);
$end = new DateTime($end_date);
$interval = new DateInterval('P1D');
$date_range = new DatePeriod($start, $interval, $end->modify('+1 day'));

// パターンから曜日ごとのマッピングを作成
$day_patterns = [];
foreach ($patterns as $pattern) {
    $day_patterns[$pattern['day_of_week']] = [
        'start_time' => $pattern['start_time'],
        'end_time' => $pattern['end_time']
    ];
}

// トランザクション開始
$conn->beginTransaction();

try {
    $generated_count = 0;
    $skipped_count = 0;
    
    // 各日付についてシフトを生成
    foreach ($date_range as $date) {
        $current_date = $date->format('Y-m-d');
        $day_of_week = (int)$date->format('w'); // 0 (日曜) から 6 (土曜)
        
        // その曜日のパターンがあるか確認
        if (isset($day_patterns[$day_of_week])) {
            $pattern = $day_patterns[$day_of_week];
            
            // 既存のシフトを確認
            $check_stmt = $conn->prepare("
                SELECT shift_id 
                FROM staff_shifts 
                WHERE staff_id = :staff_id AND salon_id = :salon_id AND shift_date = :shift_date
            ");
            $check_stmt->bindParam(':staff_id', $staff_id);
            $check_stmt->bindParam(':salon_id', $salon_id);
            $check_stmt->bindParam(':shift_date', $current_date);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                // 既存のシフトを更新
                $shift_id = $check_stmt->fetchColumn();
                $update_stmt = $conn->prepare("
                    UPDATE staff_shifts SET
                        start_time = :start_time,
                        end_time = :end_time,
                        status = 'active',
                        updated_at = NOW()
                    WHERE shift_id = :shift_id
                ");
                $update_stmt->bindParam(':start_time', $pattern['start_time']);
                $update_stmt->bindParam(':end_time', $pattern['end_time']);
                $update_stmt->bindParam(':shift_id', $shift_id);
                $update_stmt->execute();
            } else {
                // 新しいシフトを追加
                $insert_stmt = $conn->prepare("
                    INSERT INTO staff_shifts (
                        staff_id,
                        salon_id,
                        tenant_id,
                        shift_date,
                        start_time,
                        end_time,
                        status
                    ) VALUES (
                        :staff_id,
                        :salon_id,
                        :tenant_id,
                        :shift_date,
                        :start_time,
                        :end_time,
                        'active'
                    )
                ");
                $insert_stmt->bindParam(':staff_id', $staff_id);
                $insert_stmt->bindParam(':salon_id', $salon_id);
                $insert_stmt->bindParam(':tenant_id', $tenant_id);
                $insert_stmt->bindParam(':shift_date', $current_date);
                $insert_stmt->bindParam(':start_time', $pattern['start_time']);
                $insert_stmt->bindParam(':end_time', $pattern['end_time']);
                $insert_stmt->execute();
            }
            
            $generated_count++;
        } else {
            $skipped_count++;
        }
    }
    
    // トランザクションをコミット
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => "シフトが正常に生成されました。生成: {$generated_count}件、スキップ: {$skipped_count}件",
        'generated' => $generated_count,
        'skipped' => $skipped_count
    ]);
    
} catch (PDOException $e) {
    // エラー発生時はロールバック
    $conn->rollBack();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'シフト生成エラー: ' . $e->getMessage()]);
} 