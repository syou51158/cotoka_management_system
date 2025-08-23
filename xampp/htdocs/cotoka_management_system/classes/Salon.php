<?php
/**
 * Salon クラス
 * 
 * サロン情報の管理を行うクラス
 */
class Salon
{
    private $db;
    
    /**
     * コンストラクタ - データベース接続を取得
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * サロンのリストを取得
     * 
     * @param bool $activeOnly アクティブなサロンのみを取得するかどうか
     * @return array サロンの配列
     */
    public function getAll($activeOnly = true)
    {
        $sql = "SELECT * FROM salons";
        $params = [];
        
        if ($activeOnly) {
            $sql .= " WHERE status = 'active'";
        }
        
        $sql .= " ORDER BY name ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * サロン情報を取得
     * 
     * @param int $salonId サロンID
     * @return array|false サロン情報の配列、またはサロンが見つからない場合はfalse
     */
    public function getById($salonId)
    {
        $sql = "SELECT * FROM salons WHERE salon_id = ?";
        return $this->db->fetchOne($sql, [$salonId]);
    }
    
    /**
     * サロンを追加
     * 
     * @param array $data サロンデータ
     * @return int 新しいサロンのID
     */
    public function add($data)
    {
        $sql = "INSERT INTO salons (name, address, phone, email, business_hours, description, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['name'],
            $data['address'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['business_hours'] ?? null,
            $data['description'] ?? null,
            $data['status'] ?? 'active'
        ];
        
        $this->db->query($sql, $params);
        return $this->db->lastInsertId();
    }
    
    /**
     * サロン情報を更新
     * 
     * @param int $salonId サロンID
     * @param array $data 更新データ
     * @return bool 更新が成功したかどうか
     */
    public function update($salonId, $data)
    {
        $sql = "UPDATE salons 
                SET name = ?, address = ?, phone = ?, email = ?, 
                    business_hours = ?, description = ?, status = ? 
                WHERE salon_id = ?";
        
        $params = [
            $data['name'],
            $data['address'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['business_hours'] ?? null,
            $data['description'] ?? null,
            $data['status'] ?? 'active',
            $salonId
        ];
        
        $this->db->query($sql, $params);
        return true;
    }
    
    /**
     * サロンのステータスを変更
     * 
     * @param int $salonId サロンID
     * @param string $status 新しいステータス ('active' または 'inactive')
     * @return bool 更新が成功したかどうか
     */
    public function updateStatus($salonId, $status)
    {
        $sql = "UPDATE salons SET status = ? WHERE salon_id = ?";
        $this->db->query($sql, [$status, $salonId]);
        return true;
    }
    
    /**
     * サロンを削除（ソフトデリート - ステータスを inactive に設定）
     * 
     * @param int $salonId サロンID
     * @return bool 削除が成功したかどうか
     */
    public function delete($salonId)
    {
        return $this->updateStatus($salonId, 'inactive');
    }
    
    /**
     * ユーザーがアクセス可能なサロンの一覧を取得
     * 
     * @param int $userId ユーザーID
     * @param bool $activeOnly アクティブなサロンのみ取得するかどうか
     * @return array サロンの配列
     */
    public function getSalonsByUserId($userId, $activeOnly = true)
    {
        $sql = "SELECT s.* FROM salons s
                JOIN user_salons us ON s.salon_id = us.salon_id
                WHERE us.user_id = ?";
                
        $params = [$userId];
        
        if ($activeOnly) {
            $sql .= " AND s.status = 'active'";
        }
        
        $sql .= " ORDER BY s.name ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * ユーザーが特定のサロンにアクセス可能かチェック
     * 
     * @param int $userId ユーザーID
     * @param int $salonId サロンID
     * @return bool アクセス可能かどうか
     */
    public function canUserAccessSalon($userId, $salonId)
    {
        // 通常の権限チェック
        $sql = "SELECT 1 FROM user_salons 
                WHERE user_id = ? AND salon_id = ?";
        
        $result = $this->db->fetchOne($sql, [$userId, $salonId]);
        return !empty($result);
    }
    
    /**
     * ユーザーが特定のサロンで持つロールを取得
     * 
     * @param int $userId ユーザーID
     * @param int $salonId サロンID
     * @return string|null ロール、またはアクセス権がない場合はnull
     */
    public function getUserRoleInSalon($userId, $salonId)
    {
        $sql = "SELECT role FROM user_salons 
                WHERE user_id = ? AND salon_id = ?";
        
        $result = $this->db->fetchOne($sql, [$userId, $salonId]);
        return $result ? $result['role'] : null;
    }
    
    /**
     * ユーザーにサロンへのアクセス権を付与
     * 
     * @param int $userId ユーザーID
     * @param int $salonId サロンID
     * @param string $role ロール ('admin', 'manager', 'staff')
     * @return bool 操作が成功したかどうか
     */
    public function assignUserToSalon($userId, $salonId, $role = 'staff')
    {
        // 既存の関連をチェック
        $existing = $this->db->fetchOne(
            "SELECT 1 FROM user_salons WHERE user_id = ? AND salon_id = ?", 
            [$userId, $salonId]
        );
        
        if ($existing) {
            // 既存の関連を更新
            $sql = "UPDATE user_salons SET role = ? WHERE user_id = ? AND salon_id = ?";
            $this->db->query($sql, [$role, $userId, $salonId]);
        } else {
            // 新しい関連を作成
            $sql = "INSERT INTO user_salons (user_id, salon_id, role) VALUES (?, ?, ?)";
            $this->db->query($sql, [$userId, $salonId, $role]);
        }
        
        return true;
    }
    
    /**
     * ユーザーからサロンへのアクセス権を削除
     * 
     * @param int $userId ユーザーID
     * @param int $salonId サロンID
     * @return bool 操作が成功したかどうか
     */
    public function removeUserFromSalon($userId, $salonId)
    {
        $sql = "DELETE FROM user_salons WHERE user_id = ? AND salon_id = ?";
        $this->db->query($sql, [$userId, $salonId]);
        return true;
    }
    
    /**
     * テナントに属する最初のサロンを取得
     * 
     * @param int $tenantId テナントID
     * @return array|false サロン情報、または見つからない場合はfalse
     */
    public function getFirstByTenantId($tenantId)
    {
        $sql = "SELECT * FROM salons WHERE tenant_id = ? ORDER BY salon_id ASC LIMIT 1";
        return $this->db->fetchOne($sql, [$tenantId]);
    }
    
    /**
     * ユーザーをサロンに追加（既存の関連がある場合は更新）
     * 
     * @param int $userId ユーザーID
     * @param int $salonId サロンID
     * @param string $role ロール ('admin', 'manager', 'staff')
     * @return bool 操作が成功したかどうか
     */
    public function addUserToSalon($userId, $salonId, $role = 'staff')
    {
        // 既存の関連を削除
        $this->removeUserFromSalon($userId, $salonId);
        
        // 新しい関連を追加
        return $this->assignUserToSalon($userId, $salonId, $role);
    }
    
    /**
     * テナントに属するサロンの一覧を取得
     * 
     * @param int $tenantId テナントID
     * @param bool $activeOnly アクティブなサロンのみ取得するかどうか
     * @return array サロンの配列
     */
    public function getSalonsByTenantId($tenantId, $activeOnly = true)
    {
        $sql = "SELECT * FROM salons WHERE tenant_id = ?";
        $params = [$tenantId];
        
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        
        $sql .= " ORDER BY name ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
} 