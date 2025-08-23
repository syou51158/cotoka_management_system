<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/AvailableSlot.php';
require_once '../includes/functions.php';

// セッション開始
session_start();

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '認証が必要です']);
    exit;
}

// CSRFトークンの検証
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '不正なリクエストです']);
    exit;
}

// サロンIDの取得
$salon_id = isset($_SESSION['salon_id']) ? (int)$_SESSION['salon_id'] : null;
if (!$salon_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'サロンIDが設定されていません']);
    exit;
}

// AvailableSlotクラスのインスタンス化
$slotObj = new AvailableSlot();

// リクエストメソッドに応じた処理
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // 空き予約枠の取得
        try {
            $filters = [];
            if (isset($_GET['date'])) {
                $filters['date'] = $_GET['date'];
            }
            if (isset($_GET['staff_id'])) {
                $filters['staff_id'] = (int)$_GET['staff_id'];
            }
            
            $slots = $slotObj->getAvailableSlots($salon_id, $filters);
            echo json_encode(['success' => true, 'data' => $slots]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'データの取得に失敗しました']);
        }
        break;

    case 'POST':
        // 空き予約枠の作成
        try {
            $data = [
                'salon_id' => $salon_id,
                'staff_id' => (int)$_POST['staff_id'],
                'date' => $_POST['date'],
                'start_time' => $_POST['start_time'],
                'end_time' => $_POST['end_time']
            ];

            // バリデーション
            if (!validateSlotData($data)) {
                throw new Exception('入力データが不正です');
            }

            // 時間の重複チェック
            if ($slotObj->isTimeSlotOverlapping($salon_id, $data['staff_id'], $data['date'], $data['start_time'], $data['end_time'])) {
                throw new Exception('指定した時間枠は既に登録されています');
            }

            $slot_id = $slotObj->createAvailableSlot($data);
            echo json_encode(['success' => true, 'slot_id' => $slot_id]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // 空き予約枠の削除
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['slot_id'])) {
                throw new Exception('予約枠IDが指定されていません');
            }

            $slot_id = (int)$data['slot_id'];

            // 予約枠の存在確認と権限チェック
            $current_slot = $slotObj->getSlotById($slot_id);
            if (!$current_slot || $current_slot['salon_id'] !== $salon_id) {
                throw new Exception('指定された予約枠が見つからないか、アクセス権限がありません');
            }

            $success = $slotObj->deleteAvailableSlot($slot_id);
            echo json_encode(['success' => $success]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => '許可されていないメソッドです']);
        break;
}

// 予約枠データのバリデーション
function validateSlotData($data) {
    // 必須フィールドのチェック
    $required_fields = ['salon_id', 'staff_id', 'date', 'start_time', 'end_time'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    // 日付形式のチェック
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
        return false;
    }

    // 時間形式のチェック
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['start_time']) ||
        !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['end_time'])) {
        return false;
    }

    // 開始時間が終了時間より前であることを確認
    if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
        return false;
    }

    // IDフィールドの数値チェック
    if (!is_int($data['salon_id']) || $data['salon_id'] <= 0 ||
        !is_int($data['staff_id']) || $data['staff_id'] <= 0) {
        return false;
    }

    return true;
} 