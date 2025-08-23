<?php
/**
 * 予約管理コントローラー
 * 
 * 予約のCRUD操作を処理するコントローラークラス
 */
class AppointmentController {
    private $appointmentObj;
    private $db;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->appointmentObj = new Appointment();
        $this->db = new Database();
    }
    
    /**
     * 予約を削除する
     * 
     * @param int $id 予約ID
     * @return bool 削除の成功・失敗
     */
    public function deleteAppointment($id) {
        try {
            return $this->appointmentObj->deleteAppointment($id);
        } catch (Exception $e) {
            error_log('予約削除エラー: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 予約ステータスを更新する
     * 
     * @param int $id 予約ID
     * @param string $status 新しいステータス
     * @return bool 更新の成功・失敗
     */
    public function updateAppointmentStatus($id, $status) {
        try {
            return $this->appointmentObj->updateAppointmentStatus($id, $status);
        } catch (Exception $e) {
            error_log('ステータス更新エラー: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 予約詳細を取得する
     * 
     * @param int $id 予約ID
     * @return array|bool 予約データまたはfalse
     */
    public function getAppointmentById($id) {
        try {
            return $this->appointmentObj->getAppointmentById($id);
        } catch (Exception $e) {
            error_log('予約取得エラー: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 予約時間を更新する
     * 
     * @param array $data 更新データ（appointment_id, appointment_date, start_time, staff_id）
     * @return bool 更新の成功・失敗
     */
    public function updateAppointmentTime($data) {
        try {
            return $this->appointmentObj->updateAppointmentTime($data);
        } catch (Exception $e) {
            error_log('時間更新エラー: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 日付範囲内の予約を取得する
     * 
     * @param int $tenantId テナントID
     * @param string $startDate 開始日
     * @param string $endDate 終了日
     * @param array $filters フィルター条件
     * @return array 予約データ
     */
    public function getAppointmentsByDateRange($tenantId, $startDate, $endDate, $filters = []) {
        try {
            // キャッシュキーの生成
            $cacheKey = 'appointments_' . $tenantId . '_' . $startDate . '_' . $endDate;
            
            // フィルター条件があればキャッシュキーに追加
            if (!empty($filters)) {
                $cacheKey .= '_' . md5(json_encode($filters));
            }
            
            // セッションキャッシュをチェック
            if (isset($_SESSION[$cacheKey]) && !empty($_SESSION[$cacheKey])) {
                return $_SESSION[$cacheKey];
            }
            
            // 表示モードを取得（デフォルトは「週」）
            $viewMode = $filters['view_mode'] ?? 'week';
            
            // SQLクエリ構築
            // 表示モードに応じて取得するカラムを調整
            if ($viewMode === 'month') {
                // 月表示：一覧に必要な基本情報のみ
                $sql = "SELECT a.appointment_id, a.appointment_date, a.start_time, a.end_time, a.status,
                       c.first_name AS customer_first_name, c.last_name AS customer_last_name, c.phone AS customer_phone,
                       s.name AS service_name, s.color AS service_color,
                       st.first_name AS staff_first_name, st.last_name AS staff_last_name
                FROM appointments a
                LEFT JOIN customers c ON a.customer_id = c.customer_id
                LEFT JOIN services s ON a.service_id = s.service_id
                LEFT JOIN staff st ON a.staff_id = st.staff_id
                WHERE a.salon_id = :tenant_id
                AND a.appointment_date BETWEEN :start_date AND :end_date";
            } else {
                // 日・週表示：詳細な予約情報を取得 - 確実に選択するカラムを指定
                $sql = "SELECT 
                       a.appointment_id, a.appointment_date, a.start_time, a.end_time, a.status, a.notes, a.customer_id, a.service_id, a.staff_id, a.salon_id,
                       c.first_name AS customer_first_name, c.last_name AS customer_last_name, 
                       c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address,
                       s.name AS service_name, s.duration AS service_duration, s.price AS service_price, s.color AS service_color,
                       st.first_name AS staff_first_name, st.last_name AS staff_last_name
                FROM appointments a
                LEFT JOIN customers c ON a.customer_id = c.customer_id
                LEFT JOIN services s ON a.service_id = s.service_id
                LEFT JOIN staff st ON a.staff_id = st.staff_id
                WHERE a.salon_id = :tenant_id
                AND a.appointment_date BETWEEN :start_date AND :end_date
                ORDER BY a.appointment_date, a.start_time";
                
                // クエリをログに出力してデバッグ
                error_log("日・週表示のSQLクエリ: " . $sql);
            }
            
            // パラメータ設定
            $params = [
                ':tenant_id' => $tenantId,
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];
            
            // フィルター条件の適用
            if (isset($filters['staff_id']) && !empty($filters['staff_id'])) {
                $sql .= " AND a.staff_id = :staff_id";
                $params[':staff_id'] = $filters['staff_id'];
            }
            
            if (isset($filters['status']) && !empty($filters['status'])) {
                $sql .= " AND a.status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (isset($filters['search']) && !empty($filters['search'])) {
                $search = '%' . $filters['search'] . '%';
                $sql .= " AND (c.first_name LIKE :search OR c.last_name LIKE :search OR c.email LIKE :search OR c.phone LIKE :search)";
                $params[':search'] = $search;
            }
            
            // 日付・時間順にソート
            $sql .= " ORDER BY a.appointment_date ASC, a.start_time ASC";
            
            // デバッグログ追加
            error_log("SQLクエリ: $sql");
            error_log("決定条件: " . json_encode($params, JSON_UNESCAPED_UNICODE));
            
            // カスタムログファイルに詳細な情報を記録
            $log_dir = __DIR__ . '/../logs';
            $log_file = $log_dir . '/app_debug.log';
            if (is_dir($log_dir) && is_writable($log_dir)) {
                file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "実行SQL: $sql\n", FILE_APPEND);
                file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "パラメータ: " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
            }
            
            // データベースクエリ実行
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute($params);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 求めたデータの数を記録
            error_log("取得した予約数: " . count($appointments));
            
            // カスタムログファイルに予約数を記録
            $log_file = __DIR__ . '/../logs/app_debug.log';
            if (file_exists($log_file) && is_writable($log_file)) {
                file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "取得した予約数: " . count($appointments) . "\n", FILE_APPEND);
            }
            if (!empty($appointments)) {
                // 最初の予約の詳細情報をログに出力
                $firstAppt = $appointments[0];
                error_log("最初の予約データ: " . json_encode($firstAppt, JSON_UNESCAPED_UNICODE));
                
                // 特に顧客情報を確認
                $customerInfo = [
                    'customer_id' => $firstAppt['customer_id'] ?? null,
                    'customer_first_name' => $firstAppt['customer_first_name'] ?? null,
                    'customer_last_name' => $firstAppt['customer_last_name'] ?? null,
                    'customer_phone' => $firstAppt['customer_phone'] ?? null,
                    'customer_email' => $firstAppt['customer_email'] ?? null
                ];
                error_log("顧客情報確認: " . json_encode($customerInfo, JSON_UNESCAPED_UNICODE));
                
                // カスタムログファイルに顧客情報を記録
                $log_file = __DIR__ . '/../logs/app_debug.log';
                if (file_exists($log_file) && is_writable($log_file)) {
                    file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "顧客情報確認: " . json_encode($customerInfo, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
                }
                
                // すべての予約に顧客電話番号があるか確認
                $missingPhoneCount = 0;
                foreach ($appointments as $appt) {
                    if (empty($appt['customer_phone'])) {
                        $missingPhoneCount++;
                    }
                }
                error_log("電話番号がない予約数: $missingPhoneCount / " . count($appointments));
                
                // カスタムログファイルに電話番号の欠落情報を記録
                $log_file = __DIR__ . '/../logs/app_debug.log';
                if (file_exists($log_file) && is_writable($log_file)) {
                    file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "電話番号がない予約数: $missingPhoneCount / " . count($appointments) . "\n", FILE_APPEND);
                    
                    // 電話番号が欠落している予約の詳細情報を記録
                    if ($missingPhoneCount > 0) {
                        file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "=== 電話番号なしの予約リスト ===\n", FILE_APPEND);
                        foreach ($appointments as $idx => $appt) {
                            if (empty($appt['customer_phone'])) {
                                $apptInfo = [
                                    'index' => $idx,
                                    'appointment_id' => $appt['appointment_id'] ?? 'N/A',
                                    'customer_id' => $appt['customer_id'] ?? 'N/A',
                                    'customer_name' => ($appt['customer_last_name'] ?? '') . ' ' . ($appt['customer_first_name'] ?? ''),
                                    'appointment_date' => $appt['appointment_date'] ?? 'N/A',
                                    'start_time' => $appt['start_time'] ?? 'N/A'
                                ];
                                file_put_contents($log_file, date('[Y-m-d H:i:s] ') . json_encode($apptInfo, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
                            }
                        }
                        file_put_contents($log_file, date('[Y-m-d H:i:s] ') . "==============================\n", FILE_APPEND);
                    }
                }
            }
            
            // セッションにキャッシュ
            $_SESSION[$cacheKey] = $appointments;
            
            // デバッグモードフラグ設定
            $_SESSION['debug'] = true;
            
            return $appointments;
        } catch (Exception $e) {
            error_log('予約取得エラー: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * スタッフリストを取得する
     * 
     * @param int $tenantId テナントID
     * @return array スタッフデータ
     */
    public function getStaffList($tenantId) {
        try {
            $sql = "SELECT staff_id, first_name, last_name, CONCAT(last_name, ' ', first_name) AS name FROM staff WHERE salon_id = :tenant_id ORDER BY first_name";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([':tenant_id' => $tenantId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('スタッフリスト取得エラー: ' . $e->getMessage());
            return [];
        }
    }
} 