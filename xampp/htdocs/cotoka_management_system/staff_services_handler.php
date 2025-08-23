<?php
// 必要なファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'includes/functions.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// ログインしていない場合はエラーを返す
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ログインが必要です']);
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

// 現在のサロンIDを取得
$salon_id = getCurrentSalonId();
if (!$salon_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'サロンIDが取得できませんでした']);
    exit;
}

// 現在のテナントIDを取得
$tenant_id = getCurrentTenantId();

// アクションに基づいて処理を分岐
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {
    case 'get_services':
        // スタッフIDを取得
        $staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
        
        if (!$staff_id) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => '無効なスタッフIDです']);
            exit;
        }
        
        try {
            // 全サービス一覧を取得
            $services_stmt = $conn->prepare("
                SELECT service_id, name, category, duration, price, status
                FROM services
                WHERE salon_id = :salon_id AND status = 'active'
                ORDER BY category, name
            ");
            $services_stmt->bindParam(':salon_id', $salon_id);
            $services_stmt->execute();
            $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // スタッフに紐付けられたサービス一覧を取得
            $staff_services_stmt = $conn->prepare("
                SELECT ss.id, ss.staff_id, ss.service_id, ss.proficiency_level, s.name
                FROM staff_services ss
                JOIN services s ON ss.service_id = s.service_id
                WHERE ss.staff_id = :staff_id AND ss.salon_id = :salon_id AND ss.is_active = 1
            ");
            $staff_services_stmt->bindParam(':staff_id', $staff_id);
            $staff_services_stmt->bindParam(':salon_id', $salon_id);
            $staff_services_stmt->execute();
            $staff_services = $staff_services_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'services' => $services,
                'staffServices' => $staff_services
            ]);
            
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
        }
        break;
        
    case 'save_staff_services':
        // POSTリクエストかどうかを確認
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => '無効なリクエストメソッドです']);
            exit;
        }
        
        // スタッフIDを取得
        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
        
        if (!$staff_id) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => '無効なスタッフIDです']);
            exit;
        }
        
        try {
            // トランザクション開始
            $conn->beginTransaction();
            
            // 現在のスタッフのサービスをすべて無効化
            $deactivate_stmt = $conn->prepare("
                UPDATE staff_services
                SET is_active = 0, updated_at = NOW()
                WHERE staff_id = :staff_id AND salon_id = :salon_id
            ");
            $deactivate_stmt->bindParam(':staff_id', $staff_id);
            $deactivate_stmt->bindParam(':salon_id', $salon_id);
            $deactivate_stmt->execute();
            
            // 選択されたサービスを処理
            if (isset($_POST['service_ids']) && is_array($_POST['service_ids'])) {
                foreach ($_POST['service_ids'] as $service_id) {
                    $service_id = intval($service_id);
                    $proficiency = isset($_POST["proficiency_{$service_id}"]) ? $_POST["proficiency_{$service_id}"] : '中級';
                    
                    // 既存のレコードがあるか確認
                    $check_stmt = $conn->prepare("
                        SELECT id FROM staff_services
                        WHERE staff_id = :staff_id AND service_id = :service_id AND salon_id = :salon_id
                        LIMIT 1
                    ");
                    $check_stmt->bindParam(':staff_id', $staff_id);
                    $check_stmt->bindParam(':service_id', $service_id);
                    $check_stmt->bindParam(':salon_id', $salon_id);
                    $check_stmt->execute();
                    $existing_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing_record) {
                        // 既存レコードを更新
                        $update_stmt = $conn->prepare("
                            UPDATE staff_services
                            SET is_active = 1, proficiency_level = :proficiency, updated_at = NOW()
                            WHERE id = :id
                        ");
                        $update_stmt->bindParam(':id', $existing_record['id']);
                        $update_stmt->bindParam(':proficiency', $proficiency);
                        $update_stmt->execute();
                    } else {
                        // 新規レコードを挿入
                        $insert_stmt = $conn->prepare("
                            INSERT INTO staff_services (
                                staff_id, service_id, salon_id, tenant_id, proficiency_level, is_active
                            ) VALUES (
                                :staff_id, :service_id, :salon_id, :tenant_id, :proficiency, 1
                            )
                        ");
                        $insert_stmt->bindParam(':staff_id', $staff_id);
                        $insert_stmt->bindParam(':service_id', $service_id);
                        $insert_stmt->bindParam(':salon_id', $salon_id);
                        $insert_stmt->bindParam(':tenant_id', $tenant_id);
                        $insert_stmt->bindParam(':proficiency', $proficiency);
                        $insert_stmt->execute();
                    }
                }
            }
            
            // コミット
            $conn->commit();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'スタッフのサービス設定が保存されました']);
            
        } catch (PDOException $e) {
            // ロールバック
            $conn->rollBack();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
        }
        break;
        
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '無効なアクションです']);
        break;
} 