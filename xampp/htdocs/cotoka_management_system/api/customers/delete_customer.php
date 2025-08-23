<?php
/**
 * 顧客削除API
 * 
 * メソッド: POST
 * パラメータ:
 *   - customer_id: 削除対象の顧客ID
 *   - salon_id: サロンID
 * 
 * 指定された顧客IDの顧客情報を削除します。
 * 成功時はステータスコード200で成功メッセージを返します。
 * 失敗時は適切なエラーメッセージを返します。
 * 
 * 作成日: 2025-03-26
 */

// ヘッダー設定
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// データベース設定とクラスを読み込み
require_once '../../config/config.php';
require_once '../../classes/Database.php';
require_once '../../classes/User.php';
require_once '../../includes/functions.php';

// エラーログ設定
ini_set('display_errors', 0);
error_reporting(E_ALL);

// セッションを確認
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => '認証エラー: ログインしていません'
    ]);
    exit;
}

// デバッグ用のログ出力
error_log("API呼び出し: delete_customer.php");
error_log("POSTデータ: " . print_r($_POST, true));

// レスポンスデータの初期化
$response = [
    'success' => false,
    'message' => ''
];

try {
    // POSTリクエストを確認
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('不正なリクエストメソッドです。POSTリクエストを使用してください。');
    }
    
    // パラメータの検証
    if (!isset($_POST['customer_id']) || !is_numeric($_POST['customer_id'])) {
        throw new Exception('顧客IDが指定されていないか、無効です。');
    }
    
    if (!isset($_POST['salon_id']) || !is_numeric($_POST['salon_id'])) {
        throw new Exception('サロンIDが指定されていないか、無効です。');
    }
    
    $customer_id = intval($_POST['customer_id']);
    $salon_id = intval($_POST['salon_id']);
    
    // ユーザーがこのサロンにアクセスできるか確認
    $user_id = $_SESSION['user_id'];
    $userObj = new User(new Database());
    $accessibleSalons = $userObj->getAccessibleSalons($user_id);
    $accessibleSalonIds = array_column($accessibleSalons, 'salon_id');
    
    if (!in_array($salon_id, $accessibleSalonIds)) {
        throw new Exception('このサロンへのアクセス権限がありません。');
    }
    
    // データベース接続
    $db = new Database();
    $conn = $db->getConnection();
    
    // トランザクション開始
    $conn->beginTransaction();
    
    // 顧客が存在するか確認
    $check_stmt = $conn->prepare("
        SELECT customer_id FROM customers 
        WHERE customer_id = :customer_id AND salon_id = :salon_id
    ");
    $check_stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':salon_id', $salon_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        throw new Exception('指定された顧客は見つかりませんでした。');
    }
    
    // 顧客に関連する予約があるか確認
    $appointment_stmt = $conn->prepare("
        SELECT appointment_id FROM appointments 
        WHERE customer_id = :customer_id AND salon_id = :salon_id
        LIMIT 1
    ");
    $appointment_stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
    $appointment_stmt->bindParam(':salon_id', $salon_id, PDO::PARAM_INT);
    $appointment_stmt->execute();
    
    if ($appointment_stmt->rowCount() > 0) {
        // 予約がある場合はステータスを非アクティブに変更する安全な方法を採用
        $update_stmt = $conn->prepare("
            UPDATE customers SET 
                status = 'inactive',
                updated_at = NOW()
            WHERE customer_id = :customer_id AND salon_id = :salon_id
        ");
        $update_stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
        $update_stmt->bindParam(':salon_id', $salon_id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        $conn->commit();
        
        $response = [
            'success' => true,
            'message' => '顧客には予約履歴があるため、アカウントを非アクティブ化しました。',
            'action' => 'deactivated'
        ];
    } else {
        // 予約がない場合は完全に削除
        $delete_stmt = $conn->prepare("
            DELETE FROM customers 
            WHERE customer_id = :customer_id AND salon_id = :salon_id
        ");
        $delete_stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
        $delete_stmt->bindParam(':salon_id', $salon_id, PDO::PARAM_INT);
        $delete_stmt->execute();
        
        if ($delete_stmt->rowCount() > 0) {
            $conn->commit();
            
            $response = [
                'success' => true,
                'message' => '顧客情報が正常に削除されました。',
                'action' => 'deleted'
            ];
        } else {
            throw new Exception('顧客情報の削除に失敗しました。');
        }
    }
    
} catch (Exception $e) {
    // トランザクションがアクティブなら、ロールバック
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    http_response_code(400);
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    error_log('API例外エラー: ' . $e->getMessage());
} catch (PDOException $e) {
    // トランザクションがアクティブなら、ロールバック
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => '顧客データの削除中にエラーが発生しました: ' . $e->getMessage()
    ];
    error_log('顧客削除APIエラー: ' . $e->getMessage());
}

// 結果を返す
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?> 