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
        $this->db = Database::getInstance();
        $this->conn = null; // Supabase REST使用のため直接接続は保持しない
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
        try {
            $tenantId = getCurrentTenantId();
            
            // RESTフィルタ構築
            $restFilters = [
                'salon_id' => $salon_id,
                'tenant_id' => $tenantId,
            ];
            if (!empty($filters['status'])) { $restFilters['status'] = $filters['status']; }
            if (!empty($filters['customer_id'])) { $restFilters['customer_id'] = $filters['customer_id']; }
            if (!empty($filters['staff_id'])) { $restFilters['staff_id'] = $filters['staff_id']; }
            if (!empty($filters['service_id'])) { $restFilters['service_id'] = $filters['service_id']; }
            if (!empty($filters['date'])) { $restFilters['appointment_date'] = 'eq.' . $filters['date']; }
            if (!empty($filters['date_from'])) { $restFilters['appointment_date'] = 'gte.' . $filters['date_from']; }
            if (!empty($filters['date_to'])) { $restFilters['appointment_date'] = 'lte.' . $filters['date_to']; }

            // orderパラメータ変換
            $orderOpt = 'appointment_date.desc,start_time.asc'; // default
            if (!empty($order_by)) {
                $parts = array_map('trim', explode(',', $order_by));
                $mapped = [];
                foreach ($parts as $p) {
                    if ($p === '') continue;
                    $sp = preg_split('/\s+/', trim($p));
                    $col = $sp[0];
                    $dir = isset($sp[1]) ? strtolower($sp[1]) : 'asc';
                    $mapped[] = $col . '.' . ($dir === 'desc' ? 'desc' : 'asc');
                }
                if ($mapped) { $orderOpt = implode(',', $mapped); }
            }

            // 検索フィルタ（PHP側で処理）
            $search = isset($filters['search']) ? mb_strtolower($filters['search']) : null;

            // appointmentsテーブルから基本データを取得
            // 検索がある場合は多めに取得してPHPで絞り込む
            $fetchLimit = $search ? 2000 : $limit;
            $fetchOffset = $search ? 0 : $offset;

            $appointments = $this->db->fetchAll('appointments', $restFilters, '*', [
                'order' => $orderOpt,
                'limit' => $fetchLimit,
                'offset' => $fetchOffset,
            ]);

            if (empty($appointments)) {
                return [];
            }

            // 関連IDを収集
            $customerIds = array_unique(array_filter(array_column($appointments, 'customer_id')));
            $serviceIds = array_unique(array_filter(array_column($appointments, 'service_id')));
            $staffIds = array_unique(array_filter(array_column($appointments, 'staff_id')));

            // 関連データを一括取得
            $customers = !empty($customerIds) ? $this->db->fetchAll('customers', ['customer_id' => $customerIds, 'tenant_id' => $tenantId], 'customer_id,first_name,last_name,phone,email') : [];
            $services = !empty($serviceIds) ? $this->db->fetchAll('services', ['service_id' => $serviceIds, 'tenant_id' => $tenantId], 'service_id,name,duration,price') : [];
            $staff = !empty($staffIds) ? $this->db->fetchAll('staff', ['staff_id' => $staffIds, 'tenant_id' => $tenantId], 'staff_id,first_name,last_name') : [];

            // ルックアップマップ作成
            $customerMap = array_column($customers, null, 'customer_id');
            $serviceMap = array_column($services, null, 'service_id');
            $staffMap = array_column($staff, null, 'staff_id');

            // 予約データに情報を付与
            $enriched = [];
            foreach ($appointments as $a) {
                if (isset($customerMap[$a['customer_id']])) {
                    $c = $customerMap[$a['customer_id']];
                    $a['customer_first_name'] = $c['first_name'] ?? null;
                    $a['customer_last_name'] = $c['last_name'] ?? null;
                    $a['customer_phone'] = $c['phone'] ?? null;
                    $a['customer_email'] = $c['email'] ?? null;
                }
                if (isset($serviceMap[$a['service_id']])) {
                    $s = $serviceMap[$a['service_id']];
                    $a['service_name'] = $s['name'] ?? null;
                    $a['service_duration'] = $s['duration'] ?? null;
                    $a['service_price'] = $s['price'] ?? null;
                }
                if (isset($staffMap[$a['staff_id']])) {
                    $st = $staffMap[$a['staff_id']];
                    $a['staff_first_name'] = $st['first_name'] ?? null;
                    $a['staff_last_name'] = $st['last_name'] ?? null;
                }

                // searchフィルタ
                if ($search) {
                    $hay = mb_strtolower(implode(' ', [
                        $a['customer_first_name'] ?? '',
                        $a['customer_last_name'] ?? '',
                        $a['customer_phone'] ?? '',
                        $a['customer_email'] ?? '',
                    ]));
                    if (mb_strpos($hay, $search) === false) {
                        continue; // マッチしない
                    }
                }
                $enriched[] = $a;
            }

            // 検索後のページング適用
            if ($search) {
                return array_slice($enriched, (int)$offset, (int)$limit);
            }

            return $enriched;
        } catch (Exception $e) {
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
        try {
            $tenantId = getCurrentTenantId();
            $restFilters = [
                'salon_id' => $salon_id,
                'tenant_id' => $tenantId,
            ];
            if (!empty($filters['status'])) { $restFilters['status'] = $filters['status']; }
            if (!empty($filters['customer_id'])) { $restFilters['customer_id'] = $filters['customer_id']; }
            if (!empty($filters['staff_id'])) { $restFilters['staff_id'] = $filters['staff_id']; }
            if (!empty($filters['service_id'])) { $restFilters['service_id'] = $filters['service_id']; }
            if (!empty($filters['date'])) { $restFilters['appointment_date'] = $filters['date']; }

            $rows = $this->db->fetchAll('appointments', $restFilters, 'appointment_id,appointment_date', [ 'limit' => 2000 ]);

            $dateFrom = $filters['date_from'] ?? null;
            $dateTo = $filters['date_to'] ?? null;
            $search = isset($filters['search']) ? mb_strtolower($filters['search']) : null;

            // まず日付で絞り込み
            $rows = array_values(array_filter($rows, function ($r) use ($dateFrom, $dateTo) {
                if ($dateFrom && $r['appointment_date'] < $dateFrom) return false;
                if ($dateTo && $r['appointment_date'] > $dateTo) return false;
                return true;
            }));

            // searchが指定されている場合のみ、顧客を個別に参照して判定
            if ($search !== null && $search !== '') {
                $count = 0;
                foreach ($rows as $r) {
                    $ok = false;
                    if (!empty($r['customer_id'])) {
                        try {
                            $c = $this->db->fetchOne('customers', [
                                'customer_id' => $r['customer_id'],
                                'tenant_id' => $tenantId,
                            ], 'first_name,last_name,phone,email');
                            if ($c) {
                                $hay = mb_strtolower(implode(' ', [
                                    $c['first_name'] ?? '', $c['last_name'] ?? '', $c['phone'] ?? '', $c['email'] ?? ''
                                ]));
                                $ok = (mb_strpos($hay, $search) !== false);
                            }
                        } catch (Exception $e) { /* ignore */ }
                    }
                    if ($ok) { $count++; }
                }
                return $count;
            }
            return count($rows);
        } catch (Exception $e) {
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
        try {
            $tenantId = getCurrentTenantId();
            $a = $this->db->fetchOne('appointments', [
                'appointment_id' => $appointment_id,
                'tenant_id' => $tenantId,
            ]);
            if (!$a) { return false; }

            // 顧客
            if (!empty($a['customer_id'])) {
                try {
                    $c = $this->db->fetchOne('customers', [
                        'customer_id' => $a['customer_id'],
                        'tenant_id' => $tenantId,
                    ], 'first_name,last_name,phone,email');
                    if ($c) {
                        $a['customer_first_name'] = $c['first_name'] ?? null;
                        $a['customer_last_name'] = $c['last_name'] ?? null;
                        $a['customer_phone'] = $c['phone'] ?? null;
                        $a['customer_email'] = $c['email'] ?? null;
                    }
                } catch (Exception $e) { /* ignore */ }
            }
            // サービス
            if (!empty($a['service_id'])) {
                try {
                    $s = $this->db->fetchOne('services', [
                        'service_id' => $a['service_id'],
                        'tenant_id' => $tenantId,
                    ], 'name,duration,price');
                    if ($s) {
                        $a['service_name'] = $s['name'] ?? null;
                        $a['service_duration'] = $s['duration'] ?? null;
                        $a['service_price'] = $s['price'] ?? null;
                    }
                } catch (Exception $e) { /* ignore */ }
            }
            // スタッフ
            if (!empty($a['staff_id'])) {
                try {
                    $st = $this->db->fetchOne('staff', [
                        'staff_id' => $a['staff_id'],
                        'tenant_id' => $tenantId,
                    ], 'first_name,last_name');
                    if ($st) {
                        $a['staff_first_name'] = $st['first_name'] ?? null;
                        $a['staff_last_name'] = $st['last_name'] ?? null;
                    }
                } catch (Exception $e) { /* ignore */ }
            }
            // サロン
            if (!empty($a['salon_id'])) {
                try {
                    $sl = $this->db->fetchOne('salons', [
                        'salon_id' => $a['salon_id'],
                        'tenant_id' => $tenantId,
                    ], 'name');
                    if ($sl) {
                        $a['salon_name'] = $sl['name'] ?? null;
                    }
                } catch (Exception $e) { /* ignore */ }
            }

            return $a;
        } catch (Exception $e) {
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
                $hasConflict = false;
            }

            if ($hasConflict) {
                error_log("createAppointment: 予約時間が重複しています - スタッフID: " . $data['staff_id']);
                return false;
            }

            // 時間形式の正規化
            $data['start_time'] = $this->ensureTimeFormat($data['start_time']);
            if (empty($data['end_time'])) {
                // デフォルト30分
                $data['end_time'] = date('H:i:s', strtotime($data['start_time']) + (30 * 60));
            } else {
                $data['end_time'] = $this->ensureTimeFormat($data['end_time']);
            }

            // 業務タイプの場合、ダミーのcustomer_idとservice_idを設定
            if ($isTaskType) {
                if (empty($data['customer_id'])) { $data['customer_id'] = 0; }
                if (empty($data['service_id'])) { $data['service_id'] = 0; }
            }

            // テナントID
            if (empty($data['tenant_id'])) {
                $data['tenant_id'] = getCurrentTenantId();
            }

            // デフォルト値
            $payload = [
                'salon_id' => (int)$data['salon_id'],
                'tenant_id' => (int)$data['tenant_id'],
                'customer_id' => (int)($data['customer_id'] ?? 0),
                'staff_id' => (int)$data['staff_id'],
                'service_id' => (int)($data['service_id'] ?? 0),
                'appointment_date' => $data['appointment_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'status' => $data['status'] ?? 'scheduled',
                'notes' => $data['notes'] ?? null,
                'appointment_type' => $data['appointment_type'] ?? 'customer',
                'task_description' => $data['task_description'] ?? null,
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ];

            $res = $this->db->insert('appointments', $payload);
            if (is_array($res) && !empty($res[0]['appointment_id'])) {
                $appointmentId = (int)$res[0]['appointment_id'];
                error_log("createAppointment: 予約が正常に作成されました - 予約ID: " . $appointmentId);
                return $appointmentId;
            }
            return false;
        } catch (Exception $e) {
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
        try {
            $tenantId = getCurrentTenantId();
            // 既存レコード確認
            $existing = $this->db->fetchOne('appointments', [
                'appointment_id' => $appointment_id,
                'tenant_id' => $tenantId,
            ]);
            if (!$existing) {
                $this->setLastErrorMessage("指定された予約ID ($appointment_id) は存在しません。");
                return false;
            }

            // 業務タイプの取り扱い
            $newType = $data['appointment_type'] ?? ($existing['appointment_type'] ?? 'customer');
            $isTaskType = ($newType === 'task');

            // デフォルト値の整備（業務タイプの場合）
            $customer_id = array_key_exists('customer_id', $data) ? $data['customer_id'] : ($existing['customer_id'] ?? 0);
            $service_id  = array_key_exists('service_id', $data) ? $data['service_id']  : ($existing['service_id']  ?? 0);
            if ($isTaskType) {
                if ($customer_id === null || $customer_id === '' ) { $customer_id = 0; }
                if ($service_id  === null || $service_id  === '' ) { $service_id  = 0; }
            }

            $staff_id = array_key_exists('staff_id', $data) ? $data['staff_id'] : ($existing['staff_id'] ?? null);
            $appointment_date = array_key_exists('appointment_date', $data) ? $data['appointment_date'] : ($existing['appointment_date'] ?? null);

            // 時間の正規化
            $start_time = array_key_exists('start_time', $data) ? $this->ensureTimeFormat($data['start_time']) : ($existing['start_time'] ?? null);
            if (array_key_exists('end_time', $data)) {
                $end_time = $data['end_time'] !== null && $data['end_time'] !== '' ? $this->ensureTimeFormat($data['end_time']) : null;
            } else {
                $end_time = $existing['end_time'] ?? null;
            }
            // start_timeが更新されend_timeが未指定なら30分後に補完
            if ($start_time && !$end_time) {
                $end_time = date('H:i:s', strtotime($start_time) + (30 * 60));
            }

            // 競合チェック（必要情報が揃っている場合のみ）
            if (!empty($existing['salon_id']) && $staff_id && $appointment_date && $start_time) {
                $hasConflict = $this->checkScheduleConflict(
                    (int)$existing['salon_id'],
                    (int)$staff_id,
                    $appointment_date,
                    $start_time,
                    $end_time ?: date('H:i:s', strtotime($start_time) + (30 * 60)),
                    $appointment_id
                );
                if ($hasConflict) {
                    $this->setLastErrorMessage('予約時間が他の予約と重複しています。');
                    return false;
                }
            }

            // 更新ペイロード作成（指定がないフィールドは現状維持）
            $payload = [
                'customer_id' => (int)$customer_id,
                'service_id' => (int)$service_id,
                'staff_id' => $staff_id !== null && $staff_id !== '' ? (int)$staff_id : null,
                'appointment_date' => $appointment_date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'status' => array_key_exists('status', $data) ? ($data['status'] ?? $existing['status'] ?? 'scheduled') : ($existing['status'] ?? 'scheduled'),
                'notes' => array_key_exists('notes', $data) ? ($data['notes'] ?? null) : ($existing['notes'] ?? null),
                'appointment_type' => $newType,
                'task_description' => array_key_exists('task_description', $data) ? ($data['task_description'] ?? null) : ($existing['task_description'] ?? null),
                'updated_at' => date('c'),
            ];

            // ログ出力
            error_log("updateAppointment payload: " . json_encode($payload));

            $ok = $this->db->update('appointments', $payload, [
                'appointment_id' => $appointment_id,
                'tenant_id' => $tenantId,
            ]);

            if ($ok) { return true; }
            $this->setLastErrorMessage('予約の更新に失敗しました。');
            return false;
        } catch (Exception $e) {
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
        try {
            $tenantId = getCurrentTenantId();
            $ok = $this->db->update('appointments', [
                'status' => $status,
                'updated_at' => date('c'),
            ], [
                'appointment_id' => $appointment_id,
                'tenant_id' => $tenantId,
            ]);
            return (bool)$ok;
        } catch (Exception $e) {
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
        try {
            $tenantId = getCurrentTenantId();
            return (bool)$this->db->delete('appointments', [
                'appointment_id' => $appointment_id,
                'tenant_id' => $tenantId,
            ]);
        } catch (Exception $e) {
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
            $start_time = $this->ensureTimeFormat($start_time);
            $end_time = $end_time ? $this->ensureTimeFormat($end_time) : date('H:i:s', strtotime($start_time) + (30 * 60));
            $tenantId = getCurrentTenantId();

            // 該当日の予約を取得してPHP側で重複判定
            $rows = $this->db->fetchAll('appointments', [
                'salon_id' => $salon_id,
                'tenant_id' => $tenantId,
                'staff_id' => $staff_id,
                'appointment_date' => $date,
            ], '*', ['limit' => 1000]);

            foreach ($rows as $r) {
                if (in_array($r['status'] ?? '', ['cancelled', 'no_show'], true)) { continue; }
                if ($exclude_appointment_id && (int)$r['appointment_id'] === (int)$exclude_appointment_id) { continue; }
                $s = $this->ensureTimeFormat($r['start_time']);
                $e = $this->ensureTimeFormat($r['end_time']);
                // 重なり条件
                $overlap = (($s <= $start_time && $e > $start_time) ||
                            ($s < $end_time && $e >= $end_time) ||
                            ($s >= $start_time && $e <= $end_time));
                if ($overlap) { return true; }
            }
            return false;
        } catch (Exception $e) {
            error_log("checkScheduleConflict エラー: " . $e->getMessage());
            return false;
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
        try {
            $tenantId = getCurrentTenantId();
            // salonとtenantで絞り込み、PHP側で日付範囲と集計
            $rows = $this->db->fetchAll('appointments', [
                'salon_id' => $salon_id,
                'tenant_id' => $tenantId,
            ], 'appointment_date', [ 'limit' => 5000 ]);

            $result = [];
            foreach ($rows as $r) {
                $d = $r['appointment_date'];
                if ($d < $start_date || $d > $end_date) { continue; }
                if (!isset($result[$d])) { $result[$d] = 0; }
                $result[$d]++;
            }
            return $result;
        } catch (Exception $e) {
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
        try {
            $tenantId = getCurrentTenantId();
            // まず予約本体を取得
            $rows = $this->db->fetchAll('appointments', [
                'customer_id' => $customer_id,
                'tenant_id' => $tenantId,
            ], '*', [
                'order' => 'appointment_date.desc,start_time.desc',
                'limit' => (int)$limit,
            ]);

            // サービス・スタッフ名の付与
            $enriched = [];
            foreach ($rows as $r) {
                if (!empty($r['service_id'])) {
                    try {
                        $s = $this->db->fetchOne('services', [ 'service_id' => $r['service_id'], 'tenant_id' => $tenantId ], 'name');
                        if ($s) { $r['service_name'] = $s['name'] ?? null; }
                    } catch (Exception $e) { /* ignore */ }
                }
                if (!empty($r['staff_id'])) {
                    try {
                        $st = $this->db->fetchOne('staff', [ 'staff_id' => $r['staff_id'], 'tenant_id' => $tenantId ], 'first_name,last_name');
                        if ($st) { $r['staff_first_name'] = $st['first_name'] ?? null; $r['staff_last_name'] = $st['last_name'] ?? null; }
                    } catch (Exception $e) { /* ignore */ }
                }
                $enriched[] = $r;
            }
            return $enriched;
        } catch (Exception $e) {
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
        $start_time = $this->ensureTimeFormat($start_time);
        $end_time = $this->ensureTimeFormat($end_time);
        $tenantId = getCurrentTenantId();
        try {
            $rows = $this->db->fetchAll('appointments', [
                'staff_id' => $staff_id,
                'tenant_id' => $tenantId,
                'appointment_date' => $date,
            ], '*', ['limit' => 1000]);
            foreach ($rows as $r) {
                if (in_array($r['status'] ?? '', ['cancelled', 'no_show'], true)) { continue; }
                if ($exclude_appointment_id && (int)$r['appointment_id'] === (int)$exclude_appointment_id) { continue; }
                $s = $this->ensureTimeFormat($r['start_time']);
                $e = $this->ensureTimeFormat($r['end_time']);
                $overlap = (($s <= $start_time && $e > $start_time) ||
                            ($s < $end_time && $e >= $end_time) ||
                            ($s >= $start_time && $e <= $end_time));
                if ($overlap) {
                    // 付加情報
                    if (!empty($r['customer_id'])) {
                        $c = $this->db->fetchOne('customers', ['customer_id' => $r['customer_id'], 'tenant_id' => $tenantId], 'first_name,last_name');
                        if ($c) {
                            $r['customer_first_name'] = $c['first_name'] ?? null;
                            $r['customer_last_name'] = $c['last_name'] ?? null;
                        }
                    }
                    if (!empty($r['service_id'])) {
                        $s2 = $this->db->fetchOne('services', ['service_id' => $r['service_id'], 'tenant_id' => $tenantId], 'name');
                        if ($s2) { $r['service_name'] = $s2['name'] ?? null; }
                    }
                    return $r;
                }
            }
            return false;
        } catch (Exception $e) {
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
            $start_time = $new_time . ':00';
            $end_time = $new_end_time === null ? date('H:i:s', strtotime($new_time . ':00') + 1800) : ($new_end_time . ':00');
            $tenantId = getCurrentTenantId();
            $update = [
                'appointment_date' => $new_date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'updated_at' => date('c'),
            ];
            return $this->db->update('appointments', $update, ['appointment_id' => $appointment_id, 'tenant_id' => $tenantId]);
        } catch (Exception $e) {
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
        $parts = explode(':', $time);
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
            // まずはテナントの顧客をある程度取得してからPHP側でフィルタ
            $rows = $this->db->fetchAll('customers', ['tenant_id' => $tenantId], 'customer_id,first_name,last_name,phone,email', ['limit' => 500]);
            $q = mb_strtolower($query);
            $filtered = array_values(array_filter($rows, function ($r) use ($q) {
                $full = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                $hay = mb_strtolower(implode(' ', [
                    $r['first_name'] ?? '', $r['last_name'] ?? '', $full, $r['phone'] ?? '', $r['email'] ?? ''
                ]));
                return $q === '' ? true : (mb_strpos($hay, $q) !== false);
            }));
            return array_slice($filtered, 0, 20);
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
            $tenantId = getCurrentTenantId();
            $row = $this->db->fetchOne('services', ['service_id' => $serviceId, 'tenant_id' => $tenantId], 'service_id,name,description,duration,price,color');
            return $row ?: null;
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
            $tenantId = getCurrentTenantId();
            return $this->db->fetchAll('customers', ['salon_id' => $salonId, 'tenant_id' => $tenantId], 'customer_id,first_name,last_name,phone,email', ['order' => 'last_name.asc,first_name.asc', 'limit' => 1000]);
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
            $tenantId = getCurrentTenantId();
            return $this->db->fetchAll('services', ['salon_id' => $salonId, 'tenant_id' => $tenantId], 'service_id,name,description,duration,price,color', ['order' => 'name.asc', 'limit' => 1000]);
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
            $tenantId = getCurrentTenantId();
            return $this->db->fetchAll('staff', ['salon_id' => $salonId, 'tenant_id' => $tenantId, 'status' => 'active'], 'staff_id,first_name,last_name,email,phone,position', ['order' => 'last_name.asc,first_name.asc', 'limit' => 1000]);
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