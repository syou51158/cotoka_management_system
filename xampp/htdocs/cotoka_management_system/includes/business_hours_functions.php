<?php
/**
 * 営業時間関連の関数
 * 
 * マルチテナント対応のサロン営業時間管理機能
 */

/**
 * テナントサロンの営業時間を取得する
 * 
 * @param int $tenant_id テナントID
 * @param int|null $salon_id サロンID（指定しない場合は現在選択中のサロン）
 * @return array|false 営業時間の配列または取得失敗時はfalse
 */
function getBusinessHours($tenant_id, $salon_id = null) {
    global $conn;
    
    // サロンIDが指定されていない場合は、現在選択中のサロンIDを取得
    if ($salon_id === null) {
        $salon_id = getCurrentSalonId();
        
        // サロンIDがまだ取得できない場合は、テナントの最初のサロンを使用
        if (!$salon_id) {
            $salons = getTenantSalons($tenant_id, true);
            if (!empty($salons)) {
                $salon_id = $salons[0]['salon_id'];
            } else {
                // サロンが見つからない場合はデフォルト値を返す
                return [
                    'open_time' => '09:00:00',
                    'close_time' => '18:00:00',
                    'timezone' => 'Asia/Tokyo'
                ];
            }
        }
    }
    
    try {
        // まずはsalon_business_hoursテーブルから取得を試みる
        $stmt = $conn->prepare("
            SELECT 
                MIN(open_time) as min_open_time,
                MAX(close_time) as max_close_time
            FROM salon_business_hours 
            WHERE salon_id = ? AND is_closed = 0
        ");
        $stmt->execute([$salon_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['min_open_time'] && $result['max_close_time']) {
            return [
                'open_time' => $result['min_open_time'],
                'close_time' => $result['max_close_time'],
                'timezone' => getTenantSetting('timezone', 'Asia/Tokyo', $tenant_id)
            ];
        }
        
        // salon_business_hoursに情報がない場合は、salonsテーブルのbusiness_hoursフィールドを確認
        $stmt = $conn->prepare("
            SELECT business_hours
            FROM salons
            WHERE salon_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$salon_id, $tenant_id]);
        $salon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($salon && !empty($salon['business_hours'])) {
            $hours = json_decode($salon['business_hours'], true);
            if (is_array($hours) && isset($hours['open_time']) && isset($hours['close_time'])) {
                return [
                    'open_time' => $hours['open_time'],
                    'close_time' => $hours['close_time'],
                    'timezone' => getTenantSetting('timezone', 'Asia/Tokyo', $tenant_id)
                ];
            }
        }
        
        // テナント設定から取得を試みる
        $open_time = getTenantSetting('default_open_time', '09:00:00', $tenant_id);
        $close_time = getTenantSetting('default_close_time', '18:00:00', $tenant_id);
        
        return [
            'open_time' => $open_time,
            'close_time' => $close_time,
            'timezone' => getTenantSetting('timezone', 'Asia/Tokyo', $tenant_id)
        ];
        
    } catch (Exception $e) {
        // エラーが発生した場合はログに記録
        logError('営業時間取得エラー: ' . $e->getMessage(), [
            'tenant_id' => $tenant_id,
            'salon_id' => $salon_id
        ]);
        
        // デフォルト値を返す
        return [
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'timezone' => 'Asia/Tokyo'
        ];
    }
}

/**
 * 指定した曜日の営業時間を取得する
 * 
 * @param int $day_of_week 曜日 (0=月曜, 6=日曜)
 * @param int $salon_id サロンID
 * @return array|false 営業時間情報または取得失敗時はfalse
 */
function getDayBusinessHours($day_of_week, $salon_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT * FROM salon_business_hours 
            WHERE salon_id = ? AND day_of_week = ?
        ");
        $stmt->execute([$salon_id, $day_of_week]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result;
        }
        
        // データがない場合はデフォルト値を返す
        return [
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'is_closed' => 0
        ];
        
    } catch (Exception $e) {
        logError('曜日別営業時間取得エラー: ' . $e->getMessage(), [
            'day_of_week' => $day_of_week,
            'salon_id' => $salon_id
        ]);
        
        return false;
    }
}
