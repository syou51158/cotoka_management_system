<?php
/**
 * 業務（タスク）管理ハンドラー
 * 
 * 業務の追加・編集・削除を処理するAJAXエンドポイント
 */

// 必要なファイルを読み込み
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../includes/functions.php';

// セッション確認
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '認証されていません。']);
    exit;
}

// CSRFトークン検証 (POSTリクエストの場合)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token']))) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'セキュリティトークンが無効です。']);
    exit;
}

// データベース接続
$db = new Database();
$conn = $db->getConnection();

// レスポンス用の配列
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// リクエストの種類に応じて処理を分岐
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// デバッグ情報
error_log('タスクハンドラー呼び出し: アクション=' . $action);

switch ($action) {
    case 'add':
        handleAddTask();
        break;
    
    case 'edit':
        handleEditTask();
        break;
    
    case 'delete':
        handleDeleteTask();
        break;
    
    case 'get':
        handleGetTask();
        break;
    
    case 'list':
        handleListTasks();
        break;
    
    default:
        $response['message'] = '無効なアクションです。';
        break;
}

// JSONとして結果を返す
header('Content-Type: application/json');
echo json_encode($response);
exit;

/**
 * 業務追加処理
 */
function handleAddTask() {
    global $db, $response;
    
    try {
        // 必須パラメータの確認
        $requiredParams = ['staff_id', 'task_date', 'start_time', 'end_time', 'task_description'];
        foreach ($requiredParams as $param) {
            if (!isset($_POST[$param]) || empty($_POST[$param])) {
                $response['message'] = $param . 'は必須です。';
                return;
            }
        }
        
        // パラメータ取得
        $staffId = (int)$_POST['staff_id'];
        $salonId = isset($_SESSION['salon_id']) ? (int)$_SESSION['salon_id'] : 0;
        $tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
        $taskDate = $_POST['task_date'];
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];
        $taskDescription = htmlspecialchars($_POST['task_description']);
        
        // 営業時間内かチェック
        $isWithinBusinessHours = checkIfWithinBusinessHours($salonId, $taskDate, $startTime, $endTime);
        if (!$isWithinBusinessHours) {
            $response['message'] = '指定された時間は営業時間外です。';
            return;
        }
        
        // スタッフのシフト内かチェック
        $isWithinShift = checkIfWithinStaffShift($staffId, $taskDate, $startTime, $endTime);
        if (!$isWithinShift) {
            $response['message'] = '指定された時間はスタッフのシフト時間外です。';
            return;
        }
        
        // 他の予約や業務との重複をチェック
        $hasConflict = checkForTimeConflicts($staffId, $taskDate, $startTime, $endTime);
        if ($hasConflict) {
            $response['message'] = '指定された時間には既に予約または業務が入っています。';
            return;
        }
        
        // 業務を追加
        $sql = "INSERT INTO staff_tasks 
                (staff_id, salon_id, tenant_id, task_date, start_time, end_time, task_description, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute([$staffId, $salonId, $tenantId, $taskDate, $startTime, $endTime, $taskDescription]);
        
        $taskId = $db->getConnection()->lastInsertId();
        
        $response['success'] = true;
        $response['message'] = '業務が正常に追加されました。';
        $response['data'] = [
            'task_id' => $taskId,
            'staff_id' => $staffId,
            'salon_id' => $salonId,
            'task_date' => $taskDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'task_description' => $taskDescription
        ];
        
    } catch (PDOException $e) {
        error_log('業務追加エラー: ' . $e->getMessage());
        $response['message'] = 'データベースエラーが発生しました: ' . $e->getMessage();
    }
}

/**
 * 業務編集処理
 */
function handleEditTask() {
    global $db, $response;
    
    try {
        // 必須パラメータの確認
        if (!isset($_POST['task_id']) || empty($_POST['task_id'])) {
            $response['message'] = 'task_idは必須です。';
            return;
        }
        
        $taskId = (int)$_POST['task_id'];
        
        // 既存の業務データを取得
        $sql = "SELECT * FROM staff_tasks WHERE task_id = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            $response['message'] = '指定された業務が見つかりません。';
            return;
        }
        
        // 更新するフィールドを設定
        $staffId = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : $task['staff_id'];
        $taskDate = isset($_POST['task_date']) ? $_POST['task_date'] : $task['task_date'];
        $startTime = isset($_POST['start_time']) ? $_POST['start_time'] : $task['start_time'];
        $endTime = isset($_POST['end_time']) ? $_POST['end_time'] : $task['end_time'];
        $taskDescription = isset($_POST['task_description']) ? htmlspecialchars($_POST['task_description']) : $task['task_description'];
        $status = isset($_POST['status']) ? $_POST['status'] : $task['status'];
        
        // 時間が変更された場合、制約をチェック
        if ($startTime != $task['start_time'] || $endTime != $task['end_time'] || $taskDate != $task['task_date'] || $staffId != $task['staff_id']) {
            // 営業時間内かチェック
            $isWithinBusinessHours = checkIfWithinBusinessHours($task['salon_id'], $taskDate, $startTime, $endTime);
            if (!$isWithinBusinessHours) {
                $response['message'] = '指定された時間は営業時間外です。';
                return;
            }
            
            // スタッフのシフト内かチェック
            $isWithinShift = checkIfWithinStaffShift($staffId, $taskDate, $startTime, $endTime);
            if (!$isWithinShift) {
                $response['message'] = '指定された時間はスタッフのシフト時間外です。';
                return;
            }
            
            // 他の予約や業務との重複をチェック（自分自身は除く）
            $hasConflict = checkForTimeConflicts($staffId, $taskDate, $startTime, $endTime, $taskId);
            if ($hasConflict) {
                $response['message'] = '指定された時間には既に予約または業務が入っています。';
                return;
            }
        }
        
        // 業務を更新
        $sql = "UPDATE staff_tasks 
                SET staff_id = ?, task_date = ?, start_time = ?, end_time = ?, 
                    task_description = ?, status = ? 
                WHERE task_id = ?";
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute([$staffId, $taskDate, $startTime, $endTime, $taskDescription, $status, $taskId]);
        
        $response['success'] = true;
        $response['message'] = '業務が正常に更新されました。';
        $response['data'] = [
            'task_id' => $taskId,
            'staff_id' => $staffId,
            'task_date' => $taskDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'task_description' => $taskDescription,
            'status' => $status
        ];
        
    } catch (PDOException $e) {
        error_log('業務編集エラー: ' . $e->getMessage());
        $response['message'] = 'データベースエラーが発生しました: ' . $e->getMessage();
    }
}

/**
 * 業務削除処理
 */
function handleDeleteTask() {
    global $db, $response;
    
    try {
        // 必須パラメータの確認
        if (!isset($_POST['task_id']) || empty($_POST['task_id'])) {
            $response['message'] = 'task_idは必須です。';
            return;
        }
        
        $taskId = (int)$_POST['task_id'];
        
        // 業務を完全に削除
        $sql = "DELETE FROM staff_tasks WHERE task_id = ?";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute([$taskId]);
        
        $response['success'] = true;
        $response['message'] = '業務が正常に削除されました。';
        $response['data'] = ['task_id' => $taskId];
        
    } catch (PDOException $e) {
        error_log('業務削除エラー: ' . $e->getMessage());
        $response['message'] = 'データベースエラーが発生しました: ' . $e->getMessage();
    }
}

/**
 * 業務情報を取得する処理
 * @return void
 */
function handleGetTask() {
    global $db, $response;

    // リクエストパラメータの検証
    $taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
    
    // 業務IDのバリデーション
    if ($taskId <= 0) {
        $response['success'] = false;
        $response['message'] = '無効な業務IDです。';
        return;
    }
    
    try {
        // まず業務が存在するか確認する
        $checkSql = "SELECT COUNT(*) as task_count FROM staff_tasks WHERE task_id = ?";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([$taskId]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || $result['task_count'] == 0) {
            // 業務が見つからない場合のエラー
            $response['success'] = false;
            $response['message'] = '指定された業務が見つかりません。';
            error_log("業務ID {$taskId} が見つかりませんでした。");
            return;
        }
        
        // 業務情報取得のSQL - LEFT JOINを使用して、スタッフが見つからなくても業務データを取得できるようにする
        $sql = "SELECT t.*, 
               CONCAT(s.last_name, ' ', s.first_name) AS staff_name
               FROM staff_tasks t
               LEFT JOIN staff s ON t.staff_id = s.staff_id
               WHERE t.task_id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 結果の確認とデバッグ
        if ($task) {
            error_log("業務情報取得成功: ID={$taskId}");
            
            // グローバルレスポンス変数に結果を格納
            $response['success'] = true;
            $response['message'] = '業務情報を取得しました。';
            $response['data'] = $task;
        } else {
            // データが取得できなかった場合（通常はここには到達しないはず）
            error_log("業務情報取得失敗: SQL実行後にデータが見つかりませんでした。ID={$taskId}");
            
            $response['success'] = false;
            $response['message'] = '業務情報の取得に失敗しました。';
        }
        
    } catch (PDOException $e) {
        error_log('業務情報取得中のデータベースエラー: ' . $e->getMessage());
        
        $response['success'] = false;
        $response['message'] = 'データベースエラー: ' . $e->getMessage();
    } catch (Exception $e) {
        error_log('業務情報取得中の一般エラー: ' . $e->getMessage());
        
        $response['success'] = false;
        $response['message'] = '予期せぬエラー: ' . $e->getMessage();
    }
}

/**
 * 業務一覧取得処理
 */
function handleListTasks() {
    global $db, $response;
    
    try {
        // パラメータ取得
        $salonId = isset($_GET['salon_id']) ? (int)$_GET['salon_id'] : (isset($_SESSION['salon_id']) ? (int)$_SESSION['salon_id'] : 0);
        $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        $staffId = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;
        $status = isset($_GET['status']) ? $_GET['status'] : 'active';
        
        // クエリの構築
        $sql = "SELECT t.*, CONCAT(s.last_name, ' ', s.first_name) AS staff_name
                FROM staff_tasks t
                JOIN staff s ON t.staff_id = s.staff_id
                WHERE t.salon_id = ?";
        
        $params = [$salonId];
        
        if ($date) {
            $sql .= " AND t.task_date = ?";
            $params[] = $date;
        }
        
        if ($staffId) {
            $sql .= " AND t.staff_id = ?";
            $params[] = $staffId;
        }
        
        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY t.start_time ASC";
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response['success'] = true;
        $response['message'] = count($tasks) . '件の業務データを取得しました。';
        $response['data'] = $tasks;
        
    } catch (PDOException $e) {
        error_log('業務一覧取得エラー: ' . $e->getMessage());
        $response['message'] = 'データベースエラーが発生しました: ' . $e->getMessage();
    }
}

/**
 * 営業時間内かチェックする関数
 */
function checkIfWithinBusinessHours($salonId, $date, $startTime, $endTime) {
    global $db;
    
    try {
        // 日付から曜日を取得（0=日曜, 1=月曜, ..., 6=土曜）
        $dayOfWeek = date('w', strtotime($date));
        
        // MySQLの曜日は（1=日曜, 2=月曜, ..., 7=土曜）なので+1する
        $mysqlDayOfWeek = $dayOfWeek + 1;
        
        $sql = "SELECT open_time, close_time, is_closed
                FROM salon_business_hours
                WHERE salon_id = ? AND day_of_week = ?";
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute([$salonId, $mysqlDayOfWeek]);
        $businessHours = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 営業時間が設定されていない、または休業日の場合
        if (!$businessHours || $businessHours['is_closed']) {
            return false;
        }
        
        // 時間文字列を数値分に変換して比較
        $startTimeMinutes = timeStringToMinutes($startTime);
        $endTimeMinutes = timeStringToMinutes($endTime);
        $openTimeMinutes = timeStringToMinutes($businessHours['open_time']);
        $closeTimeMinutes = timeStringToMinutes($businessHours['close_time']);
        
        // デバッグログを出力
        error_log("営業時間チェック: 開始={$startTime}({$startTimeMinutes}分), 終了={$endTime}({$endTimeMinutes}分)");
        error_log("営業時間: 開始={$businessHours['open_time']}({$openTimeMinutes}分), 終了={$businessHours['close_time']}({$closeTimeMinutes}分)");
        
        // 時間を比較
        return ($startTimeMinutes >= $openTimeMinutes && $endTimeMinutes <= $closeTimeMinutes);
        
    } catch (PDOException $e) {
        error_log('営業時間チェックエラー: ' . $e->getMessage());
        return false;
    }
}

/**
 * 時間文字列を分に変換する関数
 */
function timeStringToMinutes($timeString) {
    // HH:MM:SS または HH:MM 形式の時間文字列を分に変換
    $timeParts = explode(':', $timeString);
    $hours = intval($timeParts[0]);
    $minutes = intval($timeParts[1]);
    return ($hours * 60) + $minutes;
}

/**
 * スタッフのシフト内かチェックする関数
 */
function checkIfWithinStaffShift($staffId, $date, $startTime, $endTime) {
    global $db;
    
    try {
        $sql = "SELECT start_time, end_time
                FROM staff_shifts
                WHERE staff_id = ? AND shift_date = ? AND status = 'active'";
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute([$staffId, $date]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // シフトが設定されていない場合
        if (!$shift) {
            return false;
        }
        
        // 時間文字列を数値分に変換して比較
        $startTimeMinutes = timeStringToMinutes($startTime);
        $endTimeMinutes = timeStringToMinutes($endTime);
        $shiftStartMinutes = timeStringToMinutes($shift['start_time']);
        $shiftEndMinutes = timeStringToMinutes($shift['end_time']);
        
        // デバッグログを出力
        error_log("シフトチェック: 開始={$startTime}({$startTimeMinutes}分), 終了={$endTime}({$endTimeMinutes}分)");
        error_log("シフト時間: 開始={$shift['start_time']}({$shiftStartMinutes}分), 終了={$shift['end_time']}({$shiftEndMinutes}分)");
        
        // 時間を比較
        return ($startTimeMinutes >= $shiftStartMinutes && $endTimeMinutes <= $shiftEndMinutes);
        
    } catch (PDOException $e) {
        error_log('シフトチェックエラー: ' . $e->getMessage());
        return false;
    }
}

/**
 * 時間の重複をチェックする関数
 */
function checkForTimeConflicts($staffId, $date, $startTime, $endTime, $excludeTaskId = null) {
    global $db;
    
    try {
        // 1. 同じスタッフの同じ時間帯の他の業務をチェック
        $taskParams = [$staffId, $date];
        $excludeTaskSql = '';
        
        if ($excludeTaskId) {
            $excludeTaskSql = " AND task_id != ?";
            $taskParams[] = $excludeTaskId;
        }
        
        $sql = "SELECT COUNT(*) FROM staff_tasks
                WHERE staff_id = ? AND task_date = ? $excludeTaskSql AND status = 'active'
                AND ((start_time <= ? AND end_time > ?)
                     OR (start_time < ? AND end_time >= ?)
                     OR (start_time >= ? AND end_time <= ?))";
        
        $taskParams[] = $startTime;
        $taskParams[] = $startTime;
        $taskParams[] = $endTime;
        $taskParams[] = $endTime;
        $taskParams[] = $startTime;
        $taskParams[] = $endTime;
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute($taskParams);
        $taskConflicts = (int)$stmt->fetchColumn();
        
        if ($taskConflicts > 0) {
            return true; // 競合あり
        }
        
        // 2. 同じスタッフの同じ時間帯の予約をチェック
        $apptParams = [$staffId, $date];
        
        $sql = "SELECT COUNT(*) FROM appointments
                WHERE staff_id = ? AND appointment_date = ? AND status != 'cancelled'
                AND ((start_time <= ? AND end_time > ?)
                     OR (start_time < ? AND end_time >= ?)
                     OR (start_time >= ? AND end_time <= ?))";
        
        $apptParams[] = $startTime;
        $apptParams[] = $startTime;
        $apptParams[] = $endTime;
        $apptParams[] = $endTime;
        $apptParams[] = $startTime;
        $apptParams[] = $endTime;
        
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute($apptParams);
        $apptConflicts = (int)$stmt->fetchColumn();
        
        return ($apptConflicts > 0); // 予約との競合があるかどうか
        
    } catch (PDOException $e) {
        error_log('時間重複チェックエラー: ' . $e->getMessage());
        return true; // エラーの場合は安全のため競合ありとする
    }
} 