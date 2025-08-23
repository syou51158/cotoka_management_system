<?php
/**
 * 業務データ移行スクリプト
 * 
 * 予約テーブル（appointments）から業務テーブル（staff_tasks）にデータを移行します。
 * appointment_typeが'task'のデータを移行し、移行後に予約テーブルから削除します。
 */

// 必要なファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'includes/functions.php';

// セッション確認 - 一時的に無効化
// if (!isset($_SESSION['user_id'])) {
//     die('認証されていません。ログインしてください。');
// }

// 管理者権限チェック - 一時的に無効化
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
//     die('この操作には管理者権限が必要です。');
// }

// データベース接続
$db = new Database();
$conn = $db->getConnection();

// 移行結果を保存する配列
$results = [
    'success' => 0,
    'failed' => 0,
    'errors' => []
];

try {
    // トランザクション開始
    $conn->beginTransaction();
    
    // 予約テーブルから業務タイプのデータを取得
    $sql = "SELECT * FROM appointments WHERE appointment_type = 'task'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h1>業務データ移行</h1>";
    echo "<p>移行対象データ: " . count($tasks) . "件</p>";
    
    // 各業務データを処理
    foreach ($tasks as $task) {
        try {
            // 業務テーブルに挿入するデータを準備
            $insertSql = "INSERT INTO staff_tasks 
                        (staff_id, salon_id, tenant_id, task_date, start_time, end_time, 
                         task_description, status, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $status = 'active';
            if ($task['status'] === 'cancelled') {
                $status = 'cancelled';
            }
            
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->execute([
                $task['staff_id'],
                $task['salon_id'],
                $task['tenant_id'],
                $task['appointment_date'],
                $task['start_time'],
                $task['end_time'],
                $task['task_description'] ?: '未設定の業務',
                $status,
                $task['created_at'],
                $task['updated_at']
            ]);
            
            // 新しい業務IDを取得
            $newTaskId = $conn->lastInsertId();
            
            // 予約テーブルから削除
            $deleteSql = "DELETE FROM appointments WHERE appointment_id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->execute([$task['appointment_id']]);
            
            echo "<p>✅ 業務ID " . $task['appointment_id'] . " を移行しました。新しい業務ID: " . $newTaskId . "</p>";
            $results['success']++;
            
        } catch (Exception $e) {
            echo "<p>❌ 業務ID " . $task['appointment_id'] . " の移行に失敗しました: " . $e->getMessage() . "</p>";
            $results['failed']++;
            $results['errors'][] = [
                'task_id' => $task['appointment_id'],
                'error' => $e->getMessage()
            ];
        }
    }
    
    // トランザクションをコミット
    $conn->commit();
    
    echo "<h2>移行結果</h2>";
    echo "<p>成功: " . $results['success'] . "件</p>";
    echo "<p>失敗: " . $results['failed'] . "件</p>";
    
    if ($results['failed'] > 0) {
        echo "<h3>エラー詳細</h3>";
        echo "<ul>";
        foreach ($results['errors'] as $error) {
            echo "<li>業務ID " . $error['task_id'] . ": " . $error['error'] . "</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    // エラーが発生した場合はロールバック
    $conn->rollBack();
    echo "<h2>エラーが発生しました</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
} 