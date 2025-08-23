<?php
/**
 * TenantSetting クラス
 * 
 * テナント固有の設定情報を管理するクラス
 */
class TenantSetting {
    private $db;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        require_once dirname(__DIR__) . '/classes/Database.php';
        $this->db = new Database();
    }
    
    /**
     * テナント設定を取得する
     * 
     * @param int|null $tenant_id テナントID（nullの場合は現在のテナント）
     * @param string $key 設定キー
     * @param mixed $default デフォルト値（設定が見つからない場合）
     * @return mixed 設定値
     */
    public function get($tenant_id, $key, $default = null) {
        // テナントIDがnullの場合は現在のテナントIDを取得
        if ($tenant_id === null) {
            // getCurrentTenantId関数はすでに定義されているとする
            $tenant_id = getCurrentTenantId();
            if (!$tenant_id) {
                return $default;
            }
        }
        
        try {
            $sql = "SELECT setting_value FROM tenant_settings 
                    WHERE tenant_id = ? AND setting_key = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $key, PDO::PARAM_STR);
            $stmt->execute();
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row['setting_value'];
            } else {
                return $default;
            }
        } catch (Exception $e) {
            // エラーログを記録
            if (function_exists('logError')) {
                logError('テナント設定取得エラー: ' . $e->getMessage(), [
                    'tenant_id' => $tenant_id,
                    'key' => $key
                ]);
            }
            return $default;
        }
    }
    
    /**
     * テナント設定を保存する
     * 
     * @param int|null $tenant_id テナントID（nullの場合は現在のテナント）
     * @param string $key 設定キー
     * @param mixed $value 設定値
     * @return bool 成功した場合はtrue、失敗した場合はfalse
     */
    public function set($tenant_id, $key, $value) {
        // テナントIDがnullの場合は現在のテナントIDを取得
        if ($tenant_id === null) {
            $tenant_id = getCurrentTenantId();
            if (!$tenant_id) {
                return false;
            }
        }
        
        try {
            // 既存の設定があるか確認
            $sql = "SELECT setting_id FROM tenant_settings 
                    WHERE tenant_id = ? AND setting_key = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $key, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // 更新
                $sql = "UPDATE tenant_settings SET setting_value = ? 
                        WHERE tenant_id = ? AND setting_key = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(1, $value, PDO::PARAM_STR);
                $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $key, PDO::PARAM_STR);
            } else {
                // 新規作成
                $sql = "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value) 
                        VALUES (?, ?, ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $key, PDO::PARAM_STR);
                $stmt->bindParam(3, $value, PDO::PARAM_STR);
            }
            
            return $stmt->execute();
        } catch (Exception $e) {
            // エラーログを記録
            if (function_exists('logError')) {
                logError('テナント設定保存エラー: ' . $e->getMessage(), [
                    'tenant_id' => $tenant_id,
                    'key' => $key
                ]);
            }
            return false;
        }
    }
    
    /**
     * テナント設定を削除する
     * 
     * @param int|null $tenant_id テナントID（nullの場合は現在のテナント）
     * @param string $key 設定キー
     * @return bool 成功した場合はtrue、失敗した場合はfalse
     */
    public function delete($tenant_id, $key) {
        // テナントIDがnullの場合は現在のテナントIDを取得
        if ($tenant_id === null) {
            $tenant_id = getCurrentTenantId();
            if (!$tenant_id) {
                return false;
            }
        }
        
        try {
            $sql = "DELETE FROM tenant_settings 
                    WHERE tenant_id = ? AND setting_key = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $key, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (Exception $e) {
            // エラーログを記録
            if (function_exists('logError')) {
                logError('テナント設定削除エラー: ' . $e->getMessage(), [
                    'tenant_id' => $tenant_id,
                    'key' => $key
                ]);
            }
            return false;
        }
    }
    
    /**
     * テナントの全設定を取得する
     * 
     * @param int|null $tenant_id テナントID（nullの場合は現在のテナント）
     * @return array 設定の連想配列
     */
    public function getAllSettings($tenant_id = null) {
        // テナントIDがnullの場合は現在のテナントIDを取得
        if ($tenant_id === null) {
            $tenant_id = getCurrentTenantId();
            if (!$tenant_id) {
                return [];
            }
        }
        
        try {
            $sql = "SELECT setting_key, setting_value FROM tenant_settings 
                    WHERE tenant_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            return $settings;
        } catch (Exception $e) {
            // エラーログを記録
            if (function_exists('logError')) {
                logError('テナント全設定取得エラー: ' . $e->getMessage(), [
                    'tenant_id' => $tenant_id
                ]);
            }
            return [];
        }
    }
}
