<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Appointment.php';
require_once '../includes/functions.php';

// セッション開始
session_start();

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '認証が必要です']);
    exit;
}

// CSRFトークンの検証（JSONリクエストの場合はスキップ）
$content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
$is_json_request = strpos($content_type, 'application/json') !== false;

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !$is_json_request && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
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

// Appointmentクラスのインスタンス化
$appointmentObj = new Appointment();

// リクエストメソッドに応じた処理
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // 予約一覧の取得
        try {
            $filters = [];
            if (isset($_GET['date'])) {
                $filters['date'] = $_GET['date'];
            }
            if (isset($_GET['staff_id'])) {
                $filters['staff_id'] = (int)$_GET['staff_id'];
            }
            
            $appointments = $appointmentObj->getAllAppointments($salon_id, $filters);
            echo json_encode(['success' => true, 'data' => $appointments]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'データの取得に失敗しました']);
        }
        break;

    case 'POST':
        // JSONデータを取得
        $json_data = file_get_contents('php://input');
        $data = json_decode($json_data, true);
        
        // アクションに基づいて処理を分岐
        if (isset($data['action']) && $data['action'] === 'confirm') {
            // 予約確認処理
            try {
                if (!isset($data['appointment_id']) || empty($data['appointment_id'])) {
                    throw new Exception('予約IDが指定されていません');
                }
                
                $appointment_id = (int)$data['appointment_id'];
                
                // 予約の存在確認と権限チェック
                $current_appointment = $appointmentObj->getAppointmentById($appointment_id);
                if (!$current_appointment || $current_appointment['salon_id'] !== $salon_id) {
                    throw new Exception('指定された予約が見つからないか、アクセス権限がありません');
                }
                
                // 予約を確認済みに更新
                $update_data = [
                    'is_confirmed' => 1,
                    'confirmation_sent_at' => date('Y-m-d H:i:s')
                ];
                
                $success = $appointmentObj->updateAppointment($appointment_id, $update_data);
                echo json_encode(['success' => $success, 'message' => '予約を確認済みにしました']);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            // 新規予約の作成
            try {
                $data = [
                    'salon_id' => $salon_id,
                    'customer_id' => (int)$_POST['customer_id'],
                    'service_id' => (int)$_POST['service_id'],
                    'staff_id' => (int)$_POST['staff_id'],
                    'appointment_date' => $_POST['date'],
                    'start_time' => $_POST['start_time'],
                    'notes' => $_POST['notes'] ?? null,
                    'status' => 'scheduled'
                ];

                // バリデーション
                if (!validateAppointmentData($data)) {
                    throw new Exception('入力データが不正です');
                }

                // 予約の重複チェック
                if ($appointmentObj->isTimeSlotTaken($salon_id, $data['staff_id'], $data['appointment_date'], $data['start_time'])) {
                    throw new Exception('指定した時間枠は既に予約されています');
                }

                $appointment_id = $appointmentObj->createAppointment($data);
                echo json_encode(['success' => true, 'appointment_id' => $appointment_id]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        }
        break;

    case 'PATCH':
        // 予約の更新
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['appointment_id'])) {
                throw new Exception('予約IDが指定されていません');
            }

            $appointment_id = (int)$data['appointment_id'];
            $update_data = [];

            // 更新可能なフィールドの設定
            if (isset($data['date'])) {
                $update_data['appointment_date'] = $data['date'];
            }
            if (isset($data['start_time'])) {
                $update_data['start_time'] = $data['start_time'];
            }
            if (isset($data['status'])) {
                $update_data['status'] = $data['status'];
            }

            // 予約の存在確認と権限チェック
            $current_appointment = $appointmentObj->getAppointmentById($appointment_id);
            if (!$current_appointment || $current_appointment['salon_id'] !== $salon_id) {
                throw new Exception('指定された予約が見つからないか、アクセス権限がありません');
            }

            // 予約の重複チェック（時間変更の場合）
            if (isset($data['date']) && isset($data['start_time'])) {
                if ($appointmentObj->isTimeSlotTaken(
                    $salon_id,
                    $current_appointment['staff_id'],
                    $data['date'],
                    $data['start_time'],
                    $appointment_id
                )) {
                    throw new Exception('指定した時間枠は既に予約されています');
                }
            }

            $success = $appointmentObj->updateAppointment($appointment_id, $update_data);
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

// 予約データのバリデーション
function validateAppointmentData($data) {
    // 必須フィールドのチェック
    $required_fields = ['salon_id', 'customer_id', 'service_id', 'staff_id', 'appointment_date', 'start_time'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }

    // 日付形式のチェック
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['appointment_date'])) {
        return false;
    }

    // 時間形式のチェック
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['start_time'])) {
        return false;
    }

    // IDフィールドの数値チェック
    $id_fields = ['salon_id', 'customer_id', 'service_id', 'staff_id'];
    foreach ($id_fields as $field) {
        if (!is_int($data[$field]) || $data[$field] <= 0) {
            return false;
        }
    }

    return true;
} 