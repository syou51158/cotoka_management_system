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
        $this->db = Database::getInstance();
        // $this->conn はSupabase REST APIへの移行により不要
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
        
        try {
            // まず、対象サロンのアクティブなスタッフを氏名順で取得
            $staffList = $this->db->fetchAll(
                'staff',
                ['salon_id' => $salon_id, 'status' => 'active'],
                '*',
                ['order' => 'last_name.asc,first_name.asc']
            );
            
            // 当日の予約数で並び替えるため、各スタッフの予約数をカウント
            foreach ($staffList as &$s) {
                try {
                    $s['_todays_count'] = (int)$this->db->count('appointments', [
                        'staff_id' => $s['staff_id'] ?? null,
                        'appointment_date' => $date,
                    ]);
                } catch (Exception $e) {
                    error_log('getActiveStaffBySalonId count error: ' . $e->getMessage());
                    $s['_todays_count'] = 0;
                }
            }
            unset($s);
            
            // 当日予約数降順、姓・名昇順でソート
            usort($staffList, function ($a, $b) {
                $ca = $a['_todays_count'] ?? 0;
                $cb = $b['_todays_count'] ?? 0;
                if ($ca !== $cb) return $cb <=> $ca; // 予約数多い順
                $ln = strcmp($a['last_name'] ?? '', $b['last_name'] ?? '');
                if ($ln !== 0) return $ln;
                return strcmp($a['first_name'] ?? '', $b['first_name'] ?? '');
            });
            
            // 補助キーは返却しない
            foreach ($staffList as &$s) { unset($s['_todays_count']); }
            unset($s);
            
            return $staffList;
        } catch (Exception $e) {
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
        // 現状はアクティブなスタッフ数を返す（シフトテーブル導入時に拡張）
        try {
            return (int)$this->db->count('staff', [
                'salon_id' => $salon_id,
                'status' => 'active',
            ]);
        } catch (Exception $e) {
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
        // サロンの営業時間を取得
        $businessHours = $this->getBusinessHours($staff_id);
        $startTime = $businessHours['start'] ?? '10:00';
        $endTime = $businessHours['end'] ?? '20:00';
        
        try {
            // 該当スタッフ・日付の予約を取得（キャンセル/NO SHOWは後で除外）
            $bookings = $this->db->fetchAll(
                'appointments',
                [
                    'staff_id' => $staff_id,
                    'appointment_date' => $date,
                ],
                'appointment_date,start_time,end_time,status',
                ['order' => 'start_time.asc']
            );
            
            // キャンセル等を除外
            $bookedSlots = array_values(array_filter($bookings, function ($b) {
                $status = strtolower($b['status'] ?? '');
                return !in_array($status, ['cancelled', 'no_show'], true);
            }));
            
            // 利用可能な時間枠を生成（30分間隔）
            $availableSlots = [];
            $currentTime = strtotime($date . ' ' . $startTime);
            $endTimeTimestamp = strtotime($date . ' ' . $endTime);
            
            while ($currentTime < $endTimeTimestamp) {
                $slotStart = date('H:i', $currentTime);
                $slotEnd = date('H:i', strtotime('+30 minutes', $currentTime));
                
                // この時間枠が予約済みかチェック
                $isAvailable = true;
                foreach ($bookedSlots as $booking) {
                    $bookingStart = substr($booking['start_time'], 0, 5); // HH:MM
                    $bookingEnd = substr($booking['end_time'], 0, 5);   // HH:MM
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
                        'end' => $slotEnd,
                    ];
                }
                
                $currentTime = strtotime('+30 minutes', $currentTime);
            }
            
            return $availableSlots;
        } catch (Exception $e) {
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
        try {
            // スタッフが所属するサロンIDを取得
            $staff = $this->db->fetchOne('staff', ['staff_id' => $staff_id], 'salon_id');
            $salon_id = $staff['salon_id'] ?? null;
            
            if (!$salon_id) {
                return [ 'start' => '10:00', 'end' => '20:00' ];
            }
            
            // サロンの営業時間を取得
            $salon = $this->db->fetchOne('salons', ['salon_id' => $salon_id], 'business_hours');
            $business_hours_str = $salon['business_hours'] ?? '';
            
            return $this->parseSalonBusinessHours($business_hours_str);
        } catch (Exception $e) {
            error_log("getBusinessHours エラー: " . $e->getMessage());
            return [ 'start' => '10:00', 'end' => '20:00' ];
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
