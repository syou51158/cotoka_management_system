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
        $filters = [];
        if ($activeOnly) {
            $filters['status'] = 'active';
        }
        return $this->db->fetchAll('salons', $filters, '*', ['order' => 'name.asc']);
    }
    
    /**
     * サロン情報を取得
     * 
     * @param int $salonId サロンID
     * @return array|false サロン情報の配列、またはサロンが見つからない場合はfalse
     */
    public function getById($salonId)
    {
        return $this->db->fetchOne('salons', ['salon_id' => $salonId]);
    }
    
    /**
     * サロンを追加
     * 
     * @param array $data サロンデータ
     * @return int 新しいサロンのID（取得できない場合は0）
     */
    public function add($data)
    {
        $insertData = [
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'business_hours' => $data['business_hours'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'active'
        ];
        // 可能ならテナントIDを自動付与
        if (!isset($insertData['tenant_id']) && function_exists('getCurrentTenantId')) {
            $insertData['tenant_id'] = getCurrentTenantId();
        }
        $result = $this->db->insert('salons', $insertData);
        if (is_array($result) && !empty($result[0])) {
            if (isset($result[0]['salon_id'])) return (int)$result[0]['salon_id'];
            if (isset($result[0]['id'])) return (int)$result[0]['id'];
        }
        return 0;
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
        $fields = ['name','address','phone','email','business_hours','description','status'];
        $updateData = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $updateData[$f] = $data[$f];
            }
        }
        if (empty($updateData)) {
            return true; // 更新項目なし
        }
        return $this->db->update('salons', $updateData, ['salon_id' => $salonId]);
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
        return $this->db->update('salons', ['status' => $status], ['salon_id' => $salonId]);
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
        $links = $this->db->fetchAll('user_salons', ['user_id' => $userId]);
        $salons = [];
        foreach ($links as $link) {
            $filters = ['salon_id' => $link['salon_id']];
            if ($activeOnly) { $filters['status'] = 'active'; }
            $salon = $this->db->fetchOne('salons', $filters);
            if ($salon) { $salons[] = $salon; }
        }
        // 名前順でソート
        usort($salons, function($a, $b) {
            return strcmp($a['name'] ?? '', $b['name'] ?? '');
        });
        return $salons;
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
        $result = $this->db->fetchOne('user_salons', [
            'user_id' => $userId,
            'salon_id' => $salonId
        ]);
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
        $result = $this->db->fetchOne('user_salons', [
            'user_id' => $userId,
            'salon_id' => $salonId
        ]);
        return $result ? ($result['role'] ?? null) : null;
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
        $existing = $this->db->fetchOne('user_salons', [
            'user_id' => $userId,
            'salon_id' => $salonId
        ]);
        
        if ($existing) {
            $this->db->update('user_salons', ['role' => $role], [
                'user_id' => $userId,
                'salon_id' => $salonId
            ]);
        } else {
            $insert = [
                'user_id' => $userId,
                'salon_id' => $salonId,
                'role' => $role
            ];
            if (function_exists('getCurrentTenantId')) {
                $insert['tenant_id'] = getCurrentTenantId();
            }
            $this->db->insert('user_salons', $insert);
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
        return $this->db->delete('user_salons', [
            'user_id' => $userId,
            'salon_id' => $salonId
        ]);
    }
    
    /**
     * テナントに属する最初のサロンを取得
     * 
     * @param int $tenantId テナントID
     * @return array|false サロン情報、または見つからない場合はfalse
     */
    public function getFirstByTenantId($tenantId)
    {
        return $this->db->fetchOne('salons', ['tenant_id' => $tenantId], '*', [
            'order' => 'salon_id.asc',
            'limit' => 1
        ]);
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
        $filters = ['tenant_id' => $tenantId];
        if ($activeOnly) { $filters['status'] = 'active'; }
        return $this->db->fetchAll('salons', $filters, '*', ['order' => 'name.asc']);
    }
}