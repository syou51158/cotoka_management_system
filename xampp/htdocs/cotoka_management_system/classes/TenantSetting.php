<?php
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/classes/Database.php';
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
        $this->db = Database::getInstance();
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
            $tenant_id = getCurrentTenantId();
            if (!$tenant_id) {
                return $default;
            }
        }
        
        try {
            $row = $this->db->fetchOne('tenant_settings', [
                'tenant_id' => (int)$tenant_id,
                'setting_key' => $key,
            ], 'setting_value');
            if ($row && isset($row['setting_value'])) {
                return $row['setting_value'];
            }
            return $default;
        } catch (Exception $e) {
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
        if ($tenant_id === null) {
            $tenant_id = getCurrentTenantId();
            if (!$tenant_id) {
                return false;
            }
        }
        
        try {
            $existing = $this->db->fetchOne('tenant_settings', [
                'tenant_id' => (int)$tenant_id,
                'setting_key' => $key,
            ], 'setting_id');
            
            if ($existing) {
                // 更新
                $ok = $this->db->update('tenant_settings', [
                    'setting_value' => (string)$value,
                ], [
                    'tenant_id' => (int)$tenant_id,
                    'setting_key' => $key,
                ]);
                return (bool)$ok;
            } else {
                // 新規作成
                $res = $this->db->insert('tenant_settings', [
                    'tenant_id' => (int)$tenant_id,
                    'setting_key' => $key,
                    'setting_value' => (string)$value,
                ]);
                return is_array($res);
            }
        } catch (Exception $e) {
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
        if ($tenant_id === null) {
            $tenant_id = getCurrentTenantId();
            if (!$tenant_id) {
                return false;
            }
        }
        
        try {
            return $this->db->delete('tenant_settings', [
                'tenant_id' => (int)$tenant_id,
                'setting_key' => $key,
            ]);
        } catch (Exception $e) {
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
        if ($tenant_id === null) {
            $tenant_id = getCurrentTenantId();
            if (!$tenant_id) {
                return [];
            }
        }
        
        try {
            $rows = $this->db->fetchAll('tenant_settings', [
                'tenant_id' => (int)$tenant_id,
            ], 'setting_key,setting_value');
            $settings = [];
            foreach ($rows as $row) {
                if (isset($row['setting_key'])) {
                    $settings[$row['setting_key']] = $row['setting_value'] ?? null;
                }
            }
            return $settings;
        } catch (Exception $e) {
            if (function_exists('logError')) {
                logError('テナント全設定取得エラー: ' . $e->getMessage(), [
                    'tenant_id' => $tenant_id
                ]);
            }
            return [];
        }
    }
}
