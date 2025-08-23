<?php
class AvailableSlot {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 空き予約枠を取得
     * @param int $salon_id サロンID
     * @param array $filters フィルター条件
     * @return array 空き予約枠の配列
     */
    public function getAvailableSlots($salon_id, $filters = []) {
        $where_conditions = ['salon_id = ?'];
        $params = [$salon_id];

        if (isset($filters['date'])) {
            $where_conditions[] = 'date = ?';
            $params[] = $filters['date'];
        }

        if (isset($filters['staff_id'])) {
            $where_conditions[] = 'staff_id = ?';
            $params[] = $filters['staff_id'];
        }

        $where_clause = implode(' AND ', $where_conditions);

        $sql = "SELECT 
                    as.slot_id,
                    as.salon_id,
                    as.staff_id,
                    as.date,
                    as.start_time,
                    as.end_time,
                    s.last_name as staff_last_name,
                    s.first_name as staff_first_name
                FROM available_slots as
                LEFT JOIN staff s ON as.staff_id = s.staff_id
                WHERE $where_clause
                ORDER BY date ASC, start_time ASC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
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
        $sql = "INSERT INTO available_slots (
                    salon_id,
                    staff_id,
                    date,
                    start_time,
                    end_time,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['salon_id'],
                $data['staff_id'],
                $data['date'],
                $data['start_time'],
                $data['end_time']
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
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
        $sql = "DELETE FROM available_slots WHERE slot_id = ?";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$slot_id]);
        } catch (PDOException $e) {
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
        $sql = "SELECT COUNT(*) FROM available_slots
                WHERE salon_id = ? 
                AND staff_id = ?
                AND date = ?
                AND (
                    (start_time <= ? AND end_time > ?)
                    OR
                    (start_time < ? AND end_time >= ?)
                    OR
                    (start_time >= ? AND end_time <= ?)
                )";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $salon_id,
                $staff_id,
                $date,
                $start_time,
                $start_time,
                $end_time,
                $end_time,
                $start_time,
                $end_time
            ]);

            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
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
        $sql = "SELECT * FROM available_slots WHERE slot_id = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$slot_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('予約枠取得エラー: ' . $e->getMessage());
            throw new Exception('データベースエラーが発生しました');
        }
    }
} 