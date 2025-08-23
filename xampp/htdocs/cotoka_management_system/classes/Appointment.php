<?php
/**
 * Appointment class - 予約管理クラス
 * Cotokaシステムにおける予約の取得、作成、更新、削除を管理
 */
class Appointment {
    private $db;
    private $conn;
    private $lastErrorMessage = '';
    
    /**
     * コンストラクタ
     * データベース接続を初期化
     */
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    /**
     * すべての予約を取得
     * 
     * @param int $salon_id サロンID
     * @param array $filters フィルタリング条件（オプション）
     * @param string $order_by ソート条件（オプション）
     * @param int $limit 取得件数（オプション）
     * @param int $offset 開始位置（オプション）
     * @return array 予約データの配列
     */
    public function getAllAppointments($salon_id, $filters = [], $order_by = 'appointment_date DESC, start_time ASC', $limit = 100, $offset = 0) {
        $sql = "SELECT a.*, 
                c.first_name AS customer_first_name, c.last_name AS customer_last_name, 
                s.name AS service_name, s.duration AS service_duration, s.price AS service_price,
                st.first_name AS staff_first_name, st.last_name AS staff_last_name
                FROM appointments a
                LEFT JOIN customers c ON a.customer_id = c.customer_id
                LEFT JOIN services s ON a.service_id = s.service_id
                LEFT JOIN staff st ON a.staff_id = st.staff_id
                WHERE a.salon_id = :salon_id";
        
        $params = [':salon_id' => $salon_id];
        
        // フィルタ条件を追加
        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                if ($key === 'date') {
                    $sql .= " AND DATE(a.appointment_date) = :date";
                    $params[':date'] = $value;
                } elseif ($key === 'status') {
                    $sql .= " AND a.status = :status";
                    $params[':status'] = $value;
                } elseif ($key === 'customer_id') {
                    $sql .= " AND a.customer_id = :customer_id";
                    $params[':customer_id'] = $value;
                } elseif ($key === 'staff_id') {
                    $sql .= " AND a.staff_id = :staff_id";
                    $params[':staff_id'] = $value;
                } elseif ($key === 'service_id') {
                    $sql .= " AND a.service_id = :service_id";
                    $params[':service_id'] = $value;
                } elseif ($key === 'date_from') {
                    $sql .= " AND a.appointment_date >= :date_from";
                    $params[':date_from'] = $value;
                } elseif ($key === 'date_to') {
                    $sql .= " AND a.appointment_date <= :date_to";
                    $params[':date_to'] = $value;
                } elseif ($key === 'search') {
                    $sql .= " AND (c.first_name LIKE :search OR c.last_name LIKE :search OR c.phone LIKE :search OR c.email LIKE :search)";
                    $params[':search'] = "%$value%";
                }
            }
        }
        
        // ソートと制限
        $sql .= " ORDER BY $order_by LIMIT :limit OFFSET :offset";
        
        try {
            $stmt = $this->conn->prepare($sql);
            
            // パラメータをバインド
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            
            // limit と offset は特別な扱い
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getAllAppointments エラー: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 予約の総数を取得（ページネーション用）
     * 
     * @param int $salon_id サロンID
     * @param array $filters フィルタリング条件（オプション）
     * @return int 予約の総数
     */
    public function getTotalAppointmentsCount($salon_id, $filters = []) {
        $sql = "SELECT COUNT(*) as total FROM appointments a
                LEFT JOIN customers c ON a.customer_id = c.customer_id
                WHERE a.salon_id = :salon_id";
        
        $params = [':salon_id' => $salon_id];
        
        // フィルタ条件を追加
        if (!empty($filters)) {
            foreach ($filters as $key => $value) {
                if ($key === 'date') {
                    $sql .= " AND DATE(a.appointment_date) = :date";
                    $params[':date'] = $value;
                } elseif ($key === 'status') {
                    $sql .= " AND a.status = :status";
                    $params[':status'] = $value;
                } elseif ($key === 'customer_id') {
                    $sql .= " AND a.customer_id = :customer_id";
                    $params[':customer_id'] = $value;
                } elseif ($key === 'staff_id') {
                    $sql .= " AND a.staff_id = :staff_id";
                    $params[':staff_id'] = $value;
                } elseif ($key === 'service_id') {
                    $sql .= " AND a.service_id = :service_id";
                    $params[':service_id'] = $value;
                } elseif ($key === 'date_from') {
                    $sql .= " AND a.appointment_date >= :date_from";
                    $params[':date_from'] = $value;
                } elseif ($key === 'date_to') {
                    $sql .= " AND a.appointment_date <= :date_to";
                    $params[':date_to'] = $value;
                } elseif ($key === 'search') {
                    $sql .= " AND (c.first_name LIKE :search OR c.last_name LIKE :search OR c.phone LIKE :search OR c.email LIKE :search)";
                    $params[':search'] = "%$value%";
                }
            }
        }
        
        try {
            $stmt = $this->conn->prepare($sql);
            
            // パラメータをバインド
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['total'];
        } catch (PDOException $e) {
            error_log("getTotalAppointmentsCount エラー: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 予約IDから予約情報を取得
     * 
     * @param int $appointment_id 予約ID
     * @return array|false 予約データ。見つからない場合はfalse
     */
    public function getAppointmentById($appointment_id) {
        $sql = "SELECT a.*, 
                c.first_name AS customer_first_name, c.last_name AS customer_last_name, 
                c.phone AS customer_phone, c.email AS customer_email,
                s.name AS service_name, s.duration AS service_duration, s.price AS service_price,
                st.first_name AS staff_first_name, st.last_name AS staff_last_name,
                sl.name AS salon_name
                FROM appointments a
                LEFT JOIN customers c ON a.customer_id = c.customer_id
                LEFT JOIN services s ON a.service_id = s.service_id
                LEFT JOIN staff st ON a.staff_id = st.staff_id
                LEFT JOIN salons sl ON a.salon_id = sl.salon_id
                WHERE a.appointment_id = :appointment_id";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':appointment_id', $appointment_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result : false;
        } catch (PDOException $e) {
            error_log("getAppointmentById エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 予約を作成
     * 
     * @param array $data 予約データ
     * @return int|false 成功した場合は予約ID、失敗した場合はfalse
     */
    public function createAppointment($data) {
        // 必須パラメータのバリデーション
        if (empty($data['salon_id']) || empty($data['staff_id']) ||
            empty($data['appointment_date']) || empty($data['start_time'])) {
            error_log("createAppointment: 必須パラメータが不足しています");
            return false;
        }

        // 業務タイプかどうかを確認
        $isTaskType = isset($data['appointment_type']) && $data['appointment_type'] === 'task';

        // 業務タイプでない場合は、customer_idとservice_idも必須
        if (!$isTaskType && (empty($data['customer_id']) || empty($data['service_id']))) {
            error_log("createAppointment: 必須パラメータが不足しています");
            return false;
        }

        // 業務タイプの場合は、task_descriptionが必須
        if ($isTaskType && empty($data['task_description'])) {
            error_log("createAppointment: 業務タイプには業務内容(task_description)が必須です");
            return false;
        }

        try {
            // 既存の重複予約チェック
            $hasConflict = false;
            try {
                // 重複予約チェックのパラメータを正しく設定
                $hasConflict = $this->checkScheduleConflict(
                    $data['salon_id'],
                    $data['staff_id'],
                    $data['appointment_date'],
                    $data['start_time'],
                    isset($data['end_time']) ? $data['end_time'] : null,
                    isset($data['appointment_id']) ? $data['appointment_id'] : null
                );
            } catch (Exception $e) {
                error_log("予約時間チェックエラー: " . $e->getMessage());
                // エラーが発生した場合は安全側に倒して、重複がないとする
                $hasConflict = false;
            }

            if ($hasConflict) {
                error_log("createAppointment: 予約時間が重複しています - スタッフID: " . $data['staff_id']);
                return false;
            }

            // 時間形式の正規化
            $data['start_time'] = $this->ensureTimeFormat($data['start_time']);
            
            // end_timeが指定されていない場合は、タイプに基づいて計算
            if (empty($data['end_time'])) {
                if ($isTaskType) {
                    // 業務タイプの場合、デフォルトの時間を30分に設定
                    $data['end_time'] = date('H:i:s', strtotime($data['start_time']) + (30 * 60));
                } else {
                    // サービスの場合は30分をデフォルトにする（本来はサービス時間を使うべき）
                    $data['end_time'] = date('H:i:s', strtotime($data['start_time']) + (30 * 60));
                    
                    // サービス時間の取得が実装されている場合は、そちらを使用（現在は省略）
                    // $serviceDao = new Service();
                    // $service = $serviceDao->getById($data['service_id']);
                    // if ($service) {
                    //     $duration = $service['duration'];
                    //     $data['end_time'] = date('H:i:s', strtotime($data['start_time']) + ($duration * 60));
                    // }
                }
            } else {
                $data['end_time'] = $this->ensureTimeFormat($data['end_time']);
            }

            // 業務タイプの場合、ダミーのcustomer_idとservice_idを設定
            if ($isTaskType) {
                if (empty($data['customer_id'])) {
                    // ダミーの顧客ID（0や-1など）を使用
                    $data['customer_id'] = 0;
                }
                if (empty($data['service_id'])) {
                    // ダミーのサービスID（0や-1など）を使用
                    $data['service_id'] = 0;
                }
            }

            // テナントIDの設定（未設定の場合はdefault_tenant_idを使用）
            if (empty($data['tenant_id']) && !empty($_SESSION['tenant_id'])) {
                $data['tenant_id'] = $_SESSION['tenant_id'];
            } elseif (empty($data['tenant_id'])) {
                $data['tenant_id'] = SYSTEM_TENANT_ID;
            }

            // SQL文の準備 - appointment_typeとtask_descriptionを追加
            $sql = "INSERT INTO appointments 
                    (salon_id, tenant_id, customer_id, staff_id, service_id, 
                     appointment_date, start_time, end_time, status, notes, 
                     appointment_type, task_description) 
                    VALUES 
                    (:salon_id, :tenant_id, :customer_id, :staff_id, :service_id, 
                     :appointment_date, :start_time, :end_time, :status, :notes,
                     :appointment_type, :task_description)";
            
            $stmt = $this->conn->prepare($sql);
            
            // パラメータのバインド
            $stmt->bindParam(':salon_id', $data['salon_id'], PDO::PARAM_INT);
            $stmt->bindParam(':tenant_id', $data['tenant_id'], PDO::PARAM_INT);
            $stmt->bindParam(':customer_id', $data['customer_id'], PDO::PARAM_INT);
            $stmt->bindParam(':staff_id', $data['staff_id'], PDO::PARAM_INT);
            $stmt->bindParam(':service_id', $data['service_id'], PDO::PARAM_INT);
            $stmt->bindParam(':appointment_date', $data['appointment_date']);
            $stmt->bindParam(':start_time', $data['start_time']);
            $stmt->bindParam(':end_time', $data['end_time']);
            
            // ステータスの設定（デフォルトは'scheduled'）
            $status = isset($data['status']) ? $data['status'] : 'scheduled';
            $stmt->bindParam(':status', $status);
            
            // 備考の設定
            $notes = isset($data['notes']) ? $data['notes'] : null;
            $stmt->bindParam(':notes', $notes);
            
            // appointment_typeの設定（デフォルトは'customer'）
            $appointmentType = isset($data['appointment_type']) ? $data['appointment_type'] : 'customer';
            $stmt->bindParam(':appointment_type', $appointmentType);
            
            // task_descriptionの設定
            $taskDescription = isset($data['task_description']) ? $data['task_description'] : null;
            $stmt->bindParam(':task_description', $taskDescription);
            
            error_log("SQL実行: " . $sql);
            error_log("パラメータ: " . json_encode($data));
            
            // SQLの実行
            $stmt->execute();
            
            // 作成された予約IDの取得
            $appointmentId = $this->conn->lastInsertId();
            
            error_log("createAppointment: 予約が正常に作成されました - 予約ID: " . $appointmentId);
            return $appointmentId;
            
        } catch (PDOException $e) {
            error_log("createAppointment エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 予約情報を更新
     * 
     * @param int $appointment_id 予約ID
     * @param array $data 更新するデータ
     * @return bool 成功した場合はtrue、失敗した場合はfalse
     */
    public function updateAppointment($appointment_id, $data) {
        $sql = "UPDATE appointments SET 
                customer_id = :customer_id,
                service_id = :service_id,
                staff_id = :staff_id,
                appointment_date = :appointment_date,
                start_time = :start_time,
                end_time = :end_time,
                status = :status,
                notes = :notes,
                appointment_type = :appointment_type,
                task_description = :task_description,
                updated_at = NOW()
                WHERE appointment_id = :appointment_id";
        
        try {
            // 更新前に予約が存在するか確認
            $checkSql = "SELECT appointment_id, appointment_type FROM appointments WHERE appointment_id = :appointment_id";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bindValue(':appointment_id', $appointment_id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                $this->setLastErrorMessage("指定された予約ID ($appointment_id) は存在しません。");
                return false;
            }
            
            // 業務タイプの場合、customer_idとservice_idのデフォルト値を設定
            $isTaskType = isset($data['appointment_type']) && $data['appointment_type'] === 'task';
            if ($isTaskType) {
                if (empty($data['customer_id']) || $data['customer_id'] === '') {
                    $data['customer_id'] = 0;
                    error_log("業務タイプのため、customer_idにデフォルト値0を設定しました");
                }
                if (empty($data['service_id']) || $data['service_id'] === '') {
                    $data['service_id'] = 0;
                    error_log("業務タイプのため、service_idにデフォルト値0を設定しました");
                }
            }
            
            // データログ
            error_log("更新対象の予約ID: $appointment_id, タイプ: " . ($data['appointment_type'] ?? $existing['appointment_type'] ?? 'unknown'));
            
            $stmt = $this->conn->prepare($sql);
            
            // パラメータをバインド
            $stmt->bindValue(':appointment_id', $appointment_id, PDO::PARAM_INT);
            
            // NULLや空文字列の場合は0または適切なデフォルト値を設定
            $customer_id = !empty($data['customer_id']) ? $data['customer_id'] : 0;
            $service_id = !empty($data['service_id']) ? $data['service_id'] : 0;
            $staff_id = !empty($data['staff_id']) ? $data['staff_id'] : null;
            
            $stmt->bindValue(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->bindValue(':service_id', $service_id, PDO::PARAM_INT);
            $stmt->bindValue(':staff_id', $staff_id, isset($staff_id) ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':appointment_date', $data['appointment_date']);
            $stmt->bindValue(':start_time', $data['start_time']);
            $stmt->bindValue(':end_time', $data['end_time'] ?? null);
            $stmt->bindValue(':status', $data['status'] ?? 'scheduled');
            $stmt->bindValue(':notes', $data['notes'] ?? null);
            $stmt->bindValue(':appointment_type', $data['appointment_type'] ?? 'customer');
            $stmt->bindValue(':task_description', $data['task_description'] ?? null);
            
            // パラメータをログに記録
            error_log("SQL実行パラメータ - appointment_id: $appointment_id, customer_id: $customer_id, service_id: $service_id, staff_id: " . ($staff_id ?? 'NULL'));
            
            $stmt->execute();
            
            // 更新結果のログ
            $rowCount = $stmt->rowCount();
            error_log("updateAppointment: 更新された行数: $rowCount");
            
            if ($rowCount > 0) {
                return true;
            } else {
                $this->setLastErrorMessage("予約の更新に失敗しました。データに変更がないか、予約IDが無効です。");
                return false;
            }
        } catch (PDOException $e) {
            $errorMessage = "updateAppointment エラー: " . $e->getMessage();
            $this->setLastErrorMessage($errorMessage);
            error_log($errorMessage);
            return false;
        }
    }
    
    /**
     * 予約ステータスのみを更新
     * 
     * @param int $appointment_id 予約ID
     * @param string $status 新しいステータス
     * @return bool 成功した場合はtrue、失敗した場合はfalse
     */
    public function updateAppointmentStatus($appointment_id, $status) {
        $sql = "UPDATE appointments SET 
                status = :status,
                updated_at = NOW()
                WHERE appointment_id = :appointment_id";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':appointment_id', $appointment_id, PDO::PARAM_INT);
            $stmt->bindValue(':status', $status);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("updateAppointmentStatus エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 予約を削除
     * 
     * @param int $appointment_id 予約ID
     * @return bool 成功した場合はtrue、失敗した場合はfalse
     */
    public function deleteAppointment($appointment_id) {
        $sql = "DELETE FROM appointments WHERE appointment_id = :appointment_id";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':appointment_id', $appointment_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("deleteAppointment エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 特定の日付の予約をチェック（スケジュール衝突を避けるため）
     * 
     * @param int $salon_id サロンID
     * @param int $staff_id スタッフID
     * @param string $date 日付
     * @param string $start_time 開始時間
     * @param string $end_time 終了時間
     * @param int $exclude_appointment_id 除外する予約ID（更新時に自分自身を除外）
     * @return bool 衝突がある場合はtrue、ない場合はfalse
     */
    public function checkScheduleConflict($salon_id, $staff_id, $date, $start_time, $end_time, $exclude_appointment_id = null) {
        try {
            // パラメータをフォーマット
            $start_time = $this->ensureTimeFormat($start_time);
            if ($end_time) {
                $end_time = $this->ensureTimeFormat($end_time);
            } else {
                // 終了時間が指定されていない場合、開始時間の30分後をデフォルトに
                $end_time = date('H:i:s', strtotime($start_time) + (30 * 60));
            }
            
            // クエリ構築
            $sql = "SELECT COUNT(*) as count FROM appointments 
                    WHERE salon_id = ? 
                    AND staff_id = ? 
                    AND appointment_date = ? 
                    AND status NOT IN ('cancelled', 'no_show')
                    AND (
                        (start_time <= ? AND end_time > ?) OR 
                        (start_time < ? AND end_time >= ?) OR
                        (start_time >= ? AND end_time <= ?)
                    )";
            
            // パラメータ配列を準備
            $params = [
                $salon_id,
                $staff_id,
                $date,
                $start_time, $start_time,
                $end_time, $end_time,
                $start_time, $end_time
            ];
            
            // 除外する予約IDがある場合
            if ($exclude_appointment_id) {
                $sql .= " AND appointment_id != ?";
                $params[] = $exclude_appointment_id;
            }
            
            $stmt = $this->conn->prepare($sql);
            
            // パラメータをバインド（位置パラメータを使用）
            $stmt->execute($params);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("checkScheduleConflict エラー: " . $e->getMessage());
            return false; // エラーの場合は衝突なしと見なす
        }
    }
    
    /**
     * 日付範囲での予約数を日別に取得
     * 
     * @param int $salon_id サロンID
     * @param string $start_date 開始日
     * @param string $end_date 終了日
     * @return array 日付ごとの予約数
     */
    public function getAppointmentCountsByDateRange($salon_id, $start_date, $end_date) {
        $sql = "SELECT appointment_date, COUNT(*) as count 
                FROM appointments 
                WHERE salon_id = :salon_id 
                AND appointment_date BETWEEN :start_date AND :end_date
                GROUP BY appointment_date";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':salon_id', $salon_id, PDO::PARAM_INT);
            $stmt->bindValue(':start_date', $start_date);
            $stmt->bindValue(':end_date', $end_date);
            $stmt->execute();
            
            $result = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $result[$row['appointment_date']] = (int)$row['count'];
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("getAppointmentCountsByDateRange エラー: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 特定の顧客の過去の予約履歴を取得
     * 
     * @param int $customer_id 顧客ID
     * @param int $limit 取得する最大レコード数
     * @return array 予約履歴データ
     */
    public function getCustomerAppointmentHistory($customer_id, $limit = 10) {
        $sql = "SELECT a.*, 
                s.name AS service_name, 
                st.first_name AS staff_first_name, st.last_name AS staff_last_name
                FROM appointments a
                LEFT JOIN services s ON a.service_id = s.service_id
                LEFT JOIN staff st ON a.staff_id = st.staff_id
                WHERE a.customer_id = :customer_id
                ORDER BY a.appointment_date DESC, a.start_time DESC
                LIMIT :limit";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getCustomerAppointmentHistory エラー: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 時間枠が他の予約と競合するかどうかをチェック
     * 
     * @param string $date 予約日 (YYYY-MM-DD)
     * @param string $start_time 開始時間 (HH:MM)
     * @param string $end_time 終了時間 (HH:MM)
     * @param int $staff_id スタッフID
     * @param int $exclude_appointment_id 除外する予約ID（更新時に自分自身を除外）
     * @return array|false 競合する予約情報、競合がなければfalse
     */
    public function checkTimeConflict($date, $start_time, $end_time, $staff_id, $exclude_appointment_id = null) {
        // 時間形式の統一（HH:MM:SS形式に）
        $start_time = $this->ensureTimeFormat($start_time);
        $end_time = $this->ensureTimeFormat($end_time);
        
        $sql = "SELECT a.*, 
                c.first_name AS customer_first_name, c.last_name AS customer_last_name,
                s.name AS service_name
                FROM appointments a
                LEFT JOIN customers c ON a.customer_id = c.customer_id
                LEFT JOIN services s ON a.service_id = s.service_id
                WHERE a.staff_id = :staff_id 
                AND a.appointment_date = :date 
                AND a.status NOT IN ('cancelled', 'no_show')
                AND (
                    (a.start_time <= :start_time AND a.end_time > :start_time) OR 
                    (a.start_time < :end_time AND a.end_time >= :end_time) OR
                    (a.start_time >= :start_time AND a.end_time <= :end_time)
                )";
        
        // 除外する予約IDがある場合
        if ($exclude_appointment_id) {
            $sql .= " AND a.appointment_id != :exclude_id";
        }
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':staff_id', $staff_id, PDO::PARAM_INT);
            $stmt->bindValue(':date', $date);
            $stmt->bindValue(':start_time', $start_time);
            $stmt->bindValue(':end_time', $end_time);
            
            if ($exclude_appointment_id) {
                $stmt->bindValue(':exclude_id', $exclude_appointment_id, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result : false;
        } catch (PDOException $e) {
            error_log("checkTimeConflict エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 予約時間を更新する
     * 
     * @param int $appointment_id 予約ID
     * @param string $new_date 新しい予約日 (YYYY-MM-DD)
     * @param string $new_time 新しい開始時間 (HH:MM)
     * @param string|null $new_end_time 新しい終了時間 (HH:MM) オプション
     * @return bool 更新成功時はtrue、失敗時はfalse
     */
    public function updateAppointmentTime($appointment_id, $new_date, $new_time, $new_end_time = null) {
        try {
            // 開始日時のフォーマット
            $start_time = $new_time . ':00';
            
            // 終了時間が指定されていない場合は、開始時間から30分後をデフォルトとする
            if ($new_end_time === null) {
                $end_time = date('H:i:s', strtotime($new_time . ':00') + 1800); // 30分後
            } else {
                $end_time = $new_end_time . ':00';
            }
            
            $sql = "UPDATE appointments 
                    SET appointment_date = :appointment_date,
                        start_time = :start_time,
                        end_time = :end_time,
                        updated_at = NOW()
                    WHERE appointment_id = :appointment_id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':appointment_date', $new_date, PDO::PARAM_STR);
            $stmt->bindParam(':start_time', $start_time, PDO::PARAM_STR);
            $stmt->bindParam(':end_time', $end_time, PDO::PARAM_STR);
            $stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("updateAppointmentTime エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 予約IDによる予約情報の取得（エイリアス）
     * 
     * @param int $appointment_id 予約ID
     * @return array|false 予約データ。見つからない場合はfalse
     */
    public function getById($appointment_id) {
        return $this->getAppointmentById($appointment_id);
    }
    
    /**
     * 予約の作成（エイリアス）
     * 
     * @param array $data 予約データ
     * @return int|false 成功した場合は予約ID、失敗した場合はfalse
     */
    public function create($data) {
        return $this->createAppointment($data);
    }
    
    /**
     * 時間文字列を HH:MM:SS 形式に統一する
     * 
     * @param string $time 時間文字列（HH:MM または HH:MM:SS）
     * @return string HH:MM:SS 形式の時間
     */
    private function ensureTimeFormat($time) {
        // セグメント数をチェック
        $parts = explode(':', $time);
        
        // 秒の部分がない場合は追加
        if (count($parts) === 2) {
            return $time . ':00';
        }
        
        return $time;
    }

    /**
     * 顧客を検索するメソッド
     * @param int $tenantId テナントID
     * @param string $query 検索キーワード
     * @return array 顧客情報の配列
     */
    public function searchCustomers($tenantId, $query) {
        try {
            $query = '%' . $query . '%';
            $sql = "SELECT customer_id, first_name, last_name, phone, email
                    FROM customers
                    WHERE tenant_id = ? AND (
                        first_name LIKE ? OR
                        last_name LIKE ? OR
                        CONCAT(first_name, ' ', last_name) LIKE ? OR
                        phone LIKE ? OR
                        email LIKE ?
                    )
                    LIMIT 20";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(1, $tenantId, PDO::PARAM_INT);
            $stmt->bindParam(2, $query, PDO::PARAM_STR);
            $stmt->bindParam(3, $query, PDO::PARAM_STR);
            $stmt->bindParam(4, $query, PDO::PARAM_STR);
            $stmt->bindParam(5, $query, PDO::PARAM_STR);
            $stmt->bindParam(6, $query, PDO::PARAM_STR);
            $stmt->execute();
            
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $customers;
        } catch (Exception $e) {
            error_log('顧客検索エラー: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * サービス情報をIDで取得するメソッド
     * @param int $serviceId サービスID
     * @return array|null サービス情報の配列またはnull
     */
    public function getServiceById($serviceId) {
        try {
            $sql = "SELECT service_id, name, description, duration, price, color
                    FROM services
                    WHERE service_id = ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(1, $serviceId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('サービス情報取得エラー: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * サロンの顧客リストを取得するメソッド
     * @param int $salonId サロンID
     * @return array 顧客情報の配列
     */
    public function getCustomerList($salonId) {
        try {
            $sql = "SELECT customer_id, first_name, last_name, phone, email
                    FROM customers
                    WHERE salon_id = ?
                    ORDER BY last_name, first_name";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(1, $salonId, PDO::PARAM_INT);
            $stmt->execute();
            
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $customers;
        } catch (Exception $e) {
            error_log('顧客リスト取得エラー: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * サロンのサービスリストを取得するメソッド
     * @param int $salonId サロンID
     * @return array サービス情報の配列
     */
    public function getServiceList($salonId) {
        try {
            $sql = "SELECT service_id, name, description, duration, price, color
                    FROM services
                    WHERE salon_id = ?
                    ORDER BY name";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(1, $salonId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('サービス一覧取得エラー: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * サロンのスタッフリストを取得するメソッド
     * @param int $salonId サロンID
     * @return array スタッフ情報の配列
     */
    public function getStaffList($salonId) {
        try {
            $sql = "SELECT staff_id, first_name, last_name, email, phone, position
                    FROM staff
                    WHERE salon_id = ?
                    AND status = 'active'
                    ORDER BY last_name, first_name";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(1, $salonId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('スタッフ一覧取得エラー: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 最後に発生したエラーメッセージを取得
     * 
     * @return string エラーメッセージ
     */
    public function getLastErrorMessage() {
        return $this->lastErrorMessage;
    }
    
    /**
     * エラーメッセージを設定
     * 
     * @param string $message エラーメッセージ
     */
    private function setLastErrorMessage($message) {
        $this->lastErrorMessage = $message;
        error_log('Appointment Error: ' . $message);
    }
} 