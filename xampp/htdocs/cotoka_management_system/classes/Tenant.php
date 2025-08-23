<?php
/**
 * Tenant クラス
 * 
 * テナント情報の管理を行うクラス
 */
class Tenant
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
     * テナントのリストを取得
     * 
     * @param string|null $status 取得するテナントのステータス（null=全て）
     * @return array テナントの配列
     */
    public function getAll($status = null)
    {
        $sql = "SELECT * FROM tenants";
        $params = [];
        
        if ($status) {
            $sql .= " WHERE subscription_status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY company_name ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * テナント情報を取得
     * 
     * @param int $tenantId テナントID
     * @return array|false テナント情報の配列、またはテナントが見つからない場合はfalse
     */
    public function getById($tenantId)
    {
        $sql = "SELECT * FROM tenants WHERE tenant_id = ?";
        return $this->db->fetchOne($sql, [$tenantId]);
    }
    
    /**
     * メールアドレスからテナント情報を取得
     * 
     * @param string $email メールアドレス
     * @return array|false テナント情報の配列、またはテナントが見つからない場合はfalse
     */
    public function getByEmail($email)
    {
        $sql = "SELECT * FROM tenants WHERE email = ?";
        return $this->db->fetchOne($sql, [$email]);
    }
    
    /**
     * テナント数を取得
     * 
     * @param string|null $status 取得するテナントのステータス（null=全て）
     * @return int テナント数
     */
    public function getCount($status = null)
    {
        $sql = "SELECT COUNT(*) as count FROM tenants";
        $params = [];
        
        if ($status) {
            $sql .= " WHERE subscription_status = ?";
            $params[] = $status;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result['count'] ?? 0;
    }
    
    /**
     * テナントを追加
     * 
     * @param array $data テナントデータ
     * @return int 新しいテナントのID
     */
    public function add($data)
    {
        $this->db->beginTransaction();
        
        try {
            // テナント情報の登録
            $sql = "INSERT INTO tenants (company_name, owner_name, email, phone, address, 
                        subscription_plan, subscription_status, trial_ends_at, subscription_ends_at, 
                        max_salons, max_users, max_storage_mb) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $trialEndsAt = date('Y-m-d H:i:s', strtotime('+' . DEFAULT_SUBSCRIPTION_TRIAL_DAYS . ' days'));
            
            $params = [
                $data['company_name'],
                $data['owner_name'] ?? null,
                $data['email'],
                $data['phone'] ?? null,
                $data['address'] ?? null,
                $data['subscription_plan'] ?? 'free',
                $data['subscription_status'] ?? 'trial',
                $data['trial_ends_at'] ?? $trialEndsAt,
                $data['subscription_ends_at'] ?? null,
                $data['max_salons'] ?? 1,
                $data['max_users'] ?? 3,
                $data['max_storage_mb'] ?? 100
            ];
            
            $this->db->query($sql, $params);
            $tenantId = $this->db->lastInsertId();
            
            // デフォルトのサロン作成
            if (isset($data['default_salon_name'])) {
                $salonSql = "INSERT INTO salons (tenant_id, name, address, phone, email) 
                             VALUES (?, ?, ?, ?, ?)";
                
                $salonParams = [
                    $tenantId,
                    $data['default_salon_name'],
                    $data['address'] ?? null,
                    $data['phone'] ?? null,
                    $data['email'] ?? null
                ];
                
                $this->db->query($salonSql, $salonParams);
            }
            
            $this->db->commit();
            return $tenantId;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * テナント情報を更新
     * 
     * @param int $tenantId テナントID
     * @param array $data 更新データ
     * @return bool 更新が成功したかどうか
     */
    public function update($tenantId, $data)
    {
        $sql = "UPDATE tenants 
                SET company_name = ?, owner_name = ?, email = ?, phone = ?, address = ?, 
                    subscription_plan = ?, subscription_status = ?, trial_ends_at = ?, 
                    subscription_ends_at = ?, max_salons = ?, max_users = ?, max_storage_mb = ? 
                WHERE tenant_id = ?";
        
        $params = [
            $data['company_name'],
            $data['owner_name'] ?? null,
            $data['email'],
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['subscription_plan'] ?? 'free',
            $data['subscription_status'] ?? 'trial',
            $data['trial_ends_at'] ?? null,
            $data['subscription_ends_at'] ?? null,
            $data['max_salons'] ?? 1,
            $data['max_users'] ?? 3,
            $data['max_storage_mb'] ?? 100,
            $tenantId
        ];
        
        $this->db->query($sql, $params);
        return true;
    }
    
    /**
     * テナントのサブスクリプション情報を更新
     * 
     * @param int $tenantId テナントID
     * @param string $plan プラン ('free', 'basic', 'premium', 'enterprise')
     * @param string $status ステータス ('active', 'trial', 'expired', 'cancelled')
     * @param string|null $expiresAt 有効期限（Y-m-d H:i:s形式）
     * @return bool 更新が成功したかどうか
     */
    public function updateSubscription($tenantId, $plan, $status, $expiresAt = null)
    {
        global $SUBSCRIPTION_PLANS;
        
        $planInfo = $SUBSCRIPTION_PLANS[$plan] ?? null;
        if (!$planInfo) {
            throw new Exception('無効なサブスクリプションプラン');
        }
        
        $sql = "UPDATE tenants 
                SET subscription_plan = ?, subscription_status = ?, subscription_ends_at = ?, 
                    max_salons = ?, max_users = ?, max_storage_mb = ? 
                WHERE tenant_id = ?";
        
        $params = [
            $plan,
            $status,
            $expiresAt,
            $planInfo['max_salons'],
            $planInfo['max_users'],
            $planInfo['max_storage_mb'],
            $tenantId
        ];
        
        $this->db->beginTransaction();
        
        try {
            $this->db->query($sql, $params);
            
            // サブスクリプション履歴に記録
            $historySql = "INSERT INTO subscription_history 
                           (tenant_id, plan, amount, start_date, end_date, payment_status) 
                           VALUES (?, ?, ?, NOW(), ?, 'paid')";
            
            $historyParams = [
                $tenantId,
                $plan,
                $planInfo['price'],
                $expiresAt ? date('Y-m-d', strtotime($expiresAt)) : date('Y-m-d', strtotime('+1 year'))
            ];
            
            $this->db->query($historySql, $historyParams);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * テナントを無効化（ソフトデリート）
     * 
     * @param int $tenantId テナントID
     * @return bool 無効化が成功したかどうか
     */
    public function deactivate($tenantId)
    {
        $sql = "UPDATE tenants SET subscription_status = 'cancelled' WHERE tenant_id = ?";
        $this->db->query($sql, [$tenantId]);
        return true;
    }
    
    /**
     * テナントリソースの利用状況を取得
     * 
     * @param int $tenantId テナントID
     * @return array リソース利用状況の配列
     */
    public function getResourceUsage($tenantId)
    {
        // サロン数
        $salonCountSql = "SELECT COUNT(*) as count FROM salons WHERE tenant_id = ?";
        $salonCount = $this->db->fetchOne($salonCountSql, [$tenantId]);
        
        // ユーザー数
        $userCountSql = "SELECT COUNT(*) as count FROM users WHERE tenant_id = ?";
        $userCount = $this->db->fetchOne($userCountSql, [$tenantId]);
        
        // ストレージ使用量（実装例）
        $storageUsageSql = "SELECT SUM(file_size) as total FROM files WHERE tenant_id = ?";
        $storageUsage = $this->db->fetchOne($storageUsageSql, [$tenantId]);
        
        return [
            'salons' => $salonCount['count'] ?? 0,
            'users' => $userCount['count'] ?? 0,
            'storage_mb' => ($storageUsage['total'] ?? 0) / 1024 / 1024
        ];
    }
    
    /**
     * テナントのリソース上限チェック
     * 
     * @param int $tenantId テナントID
     * @param string $resourceType リソースタイプ ('salons', 'users', 'storage')
     * @return bool 上限を超えていないか（trueなら問題なし）
     */
    public function checkResourceLimit($tenantId, $resourceType)
    {
        $tenant = $this->getById($tenantId);
        if (!$tenant) {
            return false;
        }
        
        $usage = $this->getResourceUsage($tenantId);
        
        switch ($resourceType) {
            case 'salons':
                return $usage['salons'] < $tenant['max_salons'];
            
            case 'users':
                return $usage['users'] < $tenant['max_users'];
            
            case 'storage':
                return $usage['storage_mb'] < $tenant['max_storage_mb'];
            
            default:
                return false;
        }
    }
    
    /**
     * すべてのテナントのサブスクリプションステータスをチェックして更新
     * （クーロンジョブで定期的に実行）
     * 
     * @return array 更新されたテナントのリスト
     */
    public function updateAllSubscriptionStatuses()
    {
        $now = date('Y-m-d H:i:s');
        $updatedTenants = [];
        
        // トライアル期間が切れたテナントを検出
        $trialExpiredSql = "SELECT tenant_id FROM tenants 
                           WHERE subscription_status = 'trial' 
                           AND trial_ends_at < ?";
        
        $trialExpired = $this->db->fetchAll($trialExpiredSql, [$now]);
        
        foreach ($trialExpired as $tenant) {
            $this->db->query(
                "UPDATE tenants SET subscription_status = 'expired' WHERE tenant_id = ?",
                [$tenant['tenant_id']]
            );
            $updatedTenants[] = $tenant['tenant_id'];
        }
        
        // 契約期間が切れたテナントを検出
        $subExpiredSql = "SELECT tenant_id FROM tenants 
                         WHERE subscription_status = 'active' 
                         AND subscription_ends_at < ?";
        
        $subExpired = $this->db->fetchAll($subExpiredSql, [$now]);
        
        foreach ($subExpired as $tenant) {
            $this->db->query(
                "UPDATE tenants SET subscription_status = 'expired' WHERE tenant_id = ?",
                [$tenant['tenant_id']]
            );
            $updatedTenants[] = $tenant['tenant_id'];
        }
        
        return $updatedTenants;
    }
} 