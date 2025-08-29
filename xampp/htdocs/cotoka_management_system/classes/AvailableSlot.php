<?php
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Database.php';
class AvailableSlot {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 空き予約枠を取得
     * @param int $salon_id サロンID
     * @param array $filters フィルター条件
     * @return array 空き予約枠の配列
     */
    public function getAvailableSlots($salon_id, $filters = []) {
        try {
            // サロンからtenant_idを取得
            $tenant = $this->db->fetchOne('salons', ['salon_id' => (int)$salon_id], 'tenant_id');
            $tenantId = $tenant && isset($tenant['tenant_id']) ? (int)$tenant['tenant_id'] : null;

            // RESTフィルタの組み立て
            $restFilters = [
                'salon_id' => (int)$salon_id,
            ];
            if (!empty($tenantId)) {
                $restFilters['tenant_id'] = $tenantId;
            }
            if (isset($filters['date']) && $filters['date'] !== '') {
                $restFilters['date'] = $filters['date'];
            }
            if (isset($filters['staff_id']) && $filters['staff_id'] !== '') {
                $restFilters['staff_id'] = (int)$filters['staff_id'];
            }

            // 取得（ソート: date.asc, start_time.asc）
            $slots = $this->db->fetchAll(
                'available_slots',
                $restFilters,
                '*',
                ['order' => 'date.asc, start_time.asc']
            );

            // スタッフ氏名の付加（N+1回避のためキャッシュ）
            $staffCache = [];
            foreach ($slots as &$slot) {
                $sid = isset($slot['staff_id']) ? (int)$slot['staff_id'] : 0;
                if ($sid > 0) {
                    if (!isset($staffCache[$sid])) {
                        $staff = $this->db->fetchOne('staff', ['staff_id' => $sid], 'first_name,last_name');
                        $staffCache[$sid] = $staff ?: ['first_name' => null, 'last_name' => null];
                    }
                    $slot['staff_first_name'] = $staffCache[$sid]['first_name'] ?? null;
                    $slot['staff_last_name'] = $staffCache[$sid]['last_name'] ?? null;
                } else {
                    $slot['staff_first_name'] = null;
                    $slot['staff_last_name'] = null;
                }
            }
            unset($slot);

            return $slots;
        } catch (Exception $e) {
            error_log('空き予約枠取得エラー: ' . $e->getMessage());
            throw new Exception('データベースエラーが発生しました');
        }
    }

    /**
     * 空き予約枠を作成
     * @param array $data 予約枠データ
     * @return int 作成された予約枠のID
     */
    public function createAvailableSlot($data) {
        try {
            $tenant_id = null;
            try { $tenant_id = getCurrentTenantId(); } catch (Exception $e) { /* ignore */ }

            $insertData = [
                'salon_id' => (int)$data['salon_id'],
                'tenant_id' => $tenant_id,
                'staff_id' => (int)$data['staff_id'],
                'date' => $data['date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ];

            $result = $this->db->insert('available_slots', $insertData);
            // 返却は作成されたslot_id（representation返戻を想定）
            if (is_array($result) && !empty($result)) {
                return $result[0]['slot_id'] ?? 0;
            }
            return 0;
        } catch (Exception $e) {
            error_log('空き予約枠作成エラー: ' . $e->getMessage());
            throw new Exception('データベースエラーが発生しました');
        }
    }

    /**
     * 空き予約枠を削除
     * @param int $slot_id 予約枠ID
     * @return bool 削除が成功したかどうか
     */
    public function deleteAvailableSlot($slot_id) {
        try {
            // テナント整合性チェック
            $slot = $this->db->fetchOne('available_slots', ['slot_id' => (int)$slot_id], 'tenant_id');
            $slotTenant = $slot['tenant_id'] ?? null;
            $currentTenant = null;
            try { $currentTenant = getCurrentTenantId(); } catch (Exception $e) { /* ignore */ }
            if (!empty($currentTenant) && !empty($slotTenant) && (int)$slotTenant !== (int)$currentTenant) {
                return false; // 他テナントのデータは削除不可
            }

            return $this->db->delete('available_slots', ['slot_id' => (int)$slot_id]);
        } catch (Exception $e) {
            error_log('空き予約枠削除エラー: ' . $e->getMessage());
            throw new Exception('データベースエラーが発生しました');
        }
    }

    /**
     * 予約枠の重複をチェック
     * @param int $salon_id サロンID
     * @param int $staff_id スタッフID
     * @param string $date 日付
     * @param string $start_time 開始時間
     * @param string $end_time 終了時間
     * @return bool 重複があるかどうか
     */
    public function isTimeSlotOverlapping($salon_id, $staff_id, $date, $start_time, $end_time) {
        try {
            // サロンからtenant_idを取得
            $tenant = $this->db->fetchOne('salons', ['salon_id' => (int)$salon_id], 'tenant_id');
            $tenantId = $tenant && isset($tenant['tenant_id']) ? (int)$tenant['tenant_id'] : null;

            $filters = [
                'salon_id' => (int)$salon_id,
                'staff_id' => (int)$staff_id,
                'date' => $date,
            ];
            if (!empty($tenantId)) {
                $filters['tenant_id'] = $tenantId;
            }

            $slots = $this->db->fetchAll('available_slots', $filters, 'start_time,end_time');

            $checkStart = strtotime($date . ' ' . $start_time);
            $checkEnd = strtotime($date . ' ' . $end_time);
            foreach ($slots as $slot) {
                $slotStart = strtotime($date . ' ' . $slot['start_time']);
                $slotEnd = strtotime($date . ' ' . $slot['end_time']);
                // オーバーラップ条件: slotStart < checkEnd AND slotEnd > checkStart
                if ($slotStart < $checkEnd && $slotEnd > $checkStart) {
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            error_log('予約枠重複チェックエラー: ' . $e->getMessage());
            throw new Exception('データベースエラーが発生しました');
        }
    }

    /**
     * 予約枠をIDで取得
     * @param int $slot_id 予約枠ID
     * @return array|false 予約枠データ
     */
    public function getSlotById($slot_id) {
        try {
            $tenantId = null;
            try { $tenantId = getCurrentTenantId(); } catch (Exception $e) { /* ignore */ }

            $filters = ['slot_id' => (int)$slot_id];
            if (!empty($tenantId)) { $filters['tenant_id'] = (int)$tenantId; }

            $row = $this->db->fetchOne('available_slots', $filters, '*');
            return $row ?: false;
        } catch (Exception $e) {
            error_log('予約枠取得エラー: ' . $e->getMessage());
            throw new Exception('データベースエラーが発生しました');
        }
    }
}