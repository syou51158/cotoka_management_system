<?php
/**
 * Staff クラス
 * 
 * スタッフ情報の管理を行うクラス
 */
class Staff
{
    private $db;
    private $conn;
    
    /**
     * コンストラクタ
     * データベース接続を初期化
     */
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    /**
     * サロンIDに基づくアクティブなスタッフリストを取得
     * 
     * @param int $salon_id サロンID
     * @param string $date 特定の日付（デフォルト: 今日）
     * @return array スタッフデータの配列
     */
    public function getActiveStaffBySalonId($salon_id, $date = null) {
        $date = $date ?: date('Y-m-d');
        
        $sql = "SELECT s.* FROM staff s
                WHERE s.salon_id = :salon_id 
                AND s.status = 'active'";
                
        // 特定の日にスケジュールされているスタッフを優先して取得
        $sql .= " ORDER BY (
                    SELECT COUNT(*) FROM appointments a 
                    WHERE a.staff_id = s.staff_id 
                    AND a.appointment_date = :date
                ) DESC, s.last_name ASC, s.first_name ASC";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':salon_id', $salon_id, PDO::PARAM_INT);
            $stmt->bindValue(':date', $date);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getActiveStaffBySalonId エラー: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 特定の日に勤務しているスタッフの数を取得
     * 
     * @param int $salon_id サロンID
     * @param string $date 日付（YYYY-MM-DD形式）
     * @return int 勤務スタッフ数
     */
    public function getWorkingStaffCount($salon_id, $date) {
        // スタッフのシフトテーブルが存在する場合、そこから取得
        // 現在はスタッフテーブルからアクティブなスタッフ数を返す
        
        $sql = "SELECT COUNT(*) as count FROM staff 
                WHERE salon_id = :salon_id 
                AND status = 'active'";
                
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':salon_id', $salon_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['count'] ?? 0);
        } catch (PDOException $e) {
            error_log("getWorkingStaffCount エラー: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * スタッフの利用可能な時間枠を取得
     * 
     * @param int $staff_id スタッフID
     * @param string $date 日付（YYYY-MM-DD形式）
     * @return array 利用可能な時間枠の配列
     */
    public function getAvailableTimeSlots($staff_id, $date) {
        // サロンの営業時間を取得（実装例）
        $businessHours = $this->getBusinessHours($staff_id);
        $startTime = $businessHours['start'] ?? '10:00'; // デフォルト開始時間
        $endTime = $businessHours['end'] ?? '20:00';     // デフォルト終了時間
        
        // スタッフの予約状況を取得
        $sql = "SELECT 
                    appointment_date, 
                    start_time, 
                    end_time 
                FROM appointments 
                WHERE staff_id = :staff_id 
                AND appointment_date = :date 
                AND status NOT IN ('cancelled', 'no_show')
                ORDER BY start_time";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':staff_id', $staff_id, PDO::PARAM_INT);
            $stmt->bindValue(':date', $date);
            $stmt->execute();
            
            $bookedSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 利用可能な時間枠を生成（30分間隔でデフォルト設定）
            $availableSlots = [];
            $currentTime = strtotime($date . ' ' . $startTime);
            $endTimeTimestamp = strtotime($date . ' ' . $endTime);
            
            while ($currentTime < $endTimeTimestamp) {
                $slotStart = date('H:i', $currentTime);
                $slotEnd = date('H:i', strtotime('+30 minutes', $currentTime));
                
                // この時間枠が予約済みかチェック
                $isAvailable = true;
                foreach ($bookedSlots as $booking) {
                    $bookingStart = substr($booking['start_time'], 0, 5); // HH:MM 形式
                    $bookingEnd = substr($booking['end_time'], 0, 5);    // HH:MM 形式
                    
                    // 予約時間と重複するかチェック
                    if (
                        ($slotStart >= $bookingStart && $slotStart < $bookingEnd) ||
                        ($slotEnd > $bookingStart && $slotEnd <= $bookingEnd) ||
                        ($slotStart <= $bookingStart && $slotEnd >= $bookingEnd)
                    ) {
                        $isAvailable = false;
                        break;
                    }
                }
                
                if ($isAvailable) {
                    $availableSlots[] = [
                        'start' => $slotStart,
                        'end' => $slotEnd
                    ];
                }
                
                $currentTime = strtotime('+30 minutes', $currentTime);
            }
            
            return $availableSlots;
        } catch (PDOException $e) {
            error_log("getAvailableTimeSlots エラー: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * スタッフの勤務情報に基づく営業時間を取得
     * 
     * @param int $staff_id スタッフID
     * @return array 営業時間の連想配列（開始時間、終了時間）
     */
    private function getBusinessHours($staff_id) {
        // スタッフが所属するサロンIDを取得
        $sql = "SELECT salon_id FROM staff WHERE staff_id = :staff_id";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':staff_id', $staff_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $salon_id = $result['salon_id'] ?? null;
            
            if (!$salon_id) {
                return [
                    'start' => '10:00', 
                    'end' => '20:00'
                ];
            }
            
            // サロンの営業時間を取得
            $sql = "SELECT business_hours FROM salons WHERE salon_id = :salon_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':salon_id', $salon_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $business_hours_str = $result['business_hours'] ?? '';
            
            // 営業時間文字列を解析
            $hours = $this->parseSalonBusinessHours($business_hours_str);
            
            return $hours;
        } catch (PDOException $e) {
            error_log("getBusinessHours エラー: " . $e->getMessage());
            return [
                'start' => '10:00', 
                'end' => '20:00'
            ];
        }
    }
    
    /**
     * サロンの営業時間文字列を解析する
     * 
     * @param string $business_hours_str 営業時間文字列（例: '平日: 10:00-20:00, 土日祝: 10:00-18:00'）
     * @return array 営業時間の連想配列（開始時間、終了時間）
     */
    private function parseSalonBusinessHours($business_hours_str) {
        // デフォルト値
        $result = [
            'start' => '10:00',
            'end' => '20:00'
        ];
        
        if (empty($business_hours_str)) {
            return $result;
        }
        
        // 曜日ごとの営業時間を分解
        $time_ranges = [];
        $parts = explode(',', $business_hours_str);
        
        foreach ($parts as $part) {
            if (preg_match('/(\S+):\s*(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})/', $part, $matches)) {
                $day_type = trim($matches[1]);
                $start_hour = (int)$matches[2];
                $start_minute = (int)$matches[3];
                $end_hour = (int)$matches[4];
                $end_minute = (int)$matches[5];
                
                $time_ranges[$day_type] = [
                    'start' => sprintf('%02d:%02d', $start_hour, $start_minute),
                    'end' => sprintf('%02d:%02d', $end_hour, $end_minute)
                ];
            }
        }
        
        // 今日の曜日に該当する時間帯を取得
        $today = date('w'); // 0=日, 1=月, ..., 6=土
        $is_holiday = false; // 祝日判定（実際には祝日APIなどを使用）
        
        if ($today == 0 || $today == 6 || $is_holiday) {
            // 土日祝
            if (isset($time_ranges['土日祝'])) {
                $result = $time_ranges['土日祝'];
            }
        } else {
            // 平日
            if (isset($time_ranges['平日'])) {
                $result = $time_ranges['平日'];
            }
        }
        
        return $result;
    }
}
