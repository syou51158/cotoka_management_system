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
        $filters = [];
        if ($status) {
            $filters['subscription_status'] = $status;
        }
        return $this->db->fetchAll('tenants', $filters, '*', ['order' => 'company_name.asc']);
    }
    
    /**
     * テナント情報を取得
     * 
     * @param int $tenantId テナントID
     * @return array|false テナント情報の配列、またはテナントが見つからない場合はfalse
     */
    public function getById($tenantId)
    {
        return $this->db->fetchOne('tenants', ['tenant_id' => $tenantId]);
    }
    
    /**
     * メールアドレスからテナント情報を取得
     * 
     * @param string $email メールアドレス
     * @return array|false テナント情報の配列、またはテナントが見つからない場合はfalse
     */
    public function getByEmail($email)
    {
        return $this->db->fetchOne('tenants', ['email' => $email]);
    }
    
    /**
     * テナント数を取得
     * 
     * @param string|null $status 取得するテナントのステータス（null=全て）
     * @return int テナント数
     */
    public function getCount($status = null)
    {
        $filters = [];
        if ($status) {
            $filters['subscription_status'] = $status;
        }
        return $this->db->count('tenants', $filters);
    }
    
    /**
     * テナントを追加
     * 
     * @param array $data テナントデータ
     * @return int 新しいテナントのID
     */
    public function add($data)
    {
        // Supabase RESTではトランザクションが使えないため、失敗時は補償的に削除を試みる
        try {
            $trialDays = defined('DEFAULT_SUBSCRIPTION_TRIAL_DAYS') ? DEFAULT_SUBSCRIPTION_TRIAL_DAYS : 14;
            $trialEndsAt = date('Y-m-d H:i:s', strtotime('+' . $trialDays . ' days'));

            $tenantInsert = [
                'company_name' => $data['company_name'],
                'owner_name' => $data['owner_name'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'subscription_plan' => $data['subscription_plan'] ?? 'free',
                'subscription_status' => $data['subscription_status'] ?? 'trial',
                'trial_ends_at' => $data['trial_ends_at'] ?? $trialEndsAt,
                'subscription_ends_at' => $data['subscription_ends_at'] ?? null,
                'max_salons' => $data['max_salons'] ?? 1,
                'max_users' => $data['max_users'] ?? 3,
                'max_storage_mb' => $data['max_storage_mb'] ?? 100,
            ];

            $inserted = $this->db->insert('tenants', $tenantInsert);
            if (empty($inserted) || !isset($inserted[0]['tenant_id'])) {
                throw new Exception('テナント作成に失敗しました');
            }
            $tenantId = $inserted[0]['tenant_id'];

            // デフォルトのサロン作成
            if (isset($data['default_salon_name'])) {
                try {
                    $this->db->insert('salons', [
                        'tenant_id' => $tenantId,
                        'salon_name' => $data['default_salon_name'],
                        'address' => $data['address'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'email' => $data['email'] ?? null,
                    ]);
                } catch (Exception $e) {
                    // 補償的にテナントを削除
                    $this->db->delete('tenants', ['tenant_id' => $tenantId]);
                    throw $e;
                }
            }

            return $tenantId;
        } catch (Exception $e) {
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
        $updateData = [
            'company_name' => $data['company_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'subscription_plan' => $data['subscription_plan'] ?? 'free',
            'subscription_status' => $data['subscription_status'] ?? 'trial',
            'trial_ends_at' => $data['trial_ends_at'] ?? null,
            'max_salons' => $data['max_salons'] ?? 1,
            'max_users' => $data['max_users'] ?? 3,
            'max_storage_mb' => $data['max_storage_mb'] ?? 100,
        ];

        return $this->db->update('tenants', $updateData, ['tenant_id' => $tenantId]);
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

        // まずテナント情報を更新
        $updated = $this->db->update('tenants', [
            'subscription_plan' => $plan,
            'subscription_status' => $status,
            'subscription_ends_at' => $expiresAt,
            'max_salons' => $planInfo['max_salons'],
            'max_users' => $planInfo['max_users'],
            'max_storage_mb' => $planInfo['max_storage_mb'],
        ], ['tenant_id' => $tenantId]);

        if (!$updated) {
            throw new Exception('サブスクリプション更新に失敗しました');
        }

        // サブスクリプション履歴に記録
        $startDate = date('Y-m-d');
        $endDate = $expiresAt ? date('Y-m-d', strtotime($expiresAt)) : date('Y-m-d', strtotime('+1 year'));

        $this->db->insert('subscription_history', [
            'tenant_id' => $tenantId,
            'plan' => $plan,
            'amount' => $planInfo['price'],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'payment_status' => 'paid',
        ]);

        return true;
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
        $salons = $this->db->count('salons', ['tenant_id' => $tenantId]);
        
        // ユーザー数
        $users = $this->db->count('users', ['tenant_id' => $tenantId]);
        
        // ストレージ使用量（cotokaスキーマにfilesテーブルがない可能性があるため安全に処理）
        $totalBytes = 0;
        try {
            // PostgRESTの集計エイリアス構文を利用（sum:file_size）。取得キーはsumとなる。
            $sumRow = $this->db->fetchOne('files', ['tenant_id' => $tenantId], 'sum:file_size');
            if ($sumRow && isset($sumRow['sum'])) {
                $totalBytes = (int)$sumRow['sum'];
            }
        } catch (Exception $e) {
            // filesテーブルがない場合などは0のまま
            $totalBytes = 0;
        }
        
        return [
            'salons' => $salons,
            'users' => $users,
            'storage_mb' => $totalBytes / 1024 / 1024,
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
        
        // トライアル期間が切れたテナントを検出（PHP側で時刻比較）
        $trialTenants = $this->db->fetchAll('tenants', ['subscription_status' => 'trial']);
        foreach ($trialTenants as $tenant) {
            if (!empty($tenant['trial_ends_at']) && strtotime($tenant['trial_ends_at']) < $nowTs) {
                $ok = $this->db->update('tenants', ['subscription_status' => 'expired'], ['tenant_id' => $tenant['tenant_id']]);
                if ($ok) {
                    $updatedTenants[] = $tenant['tenant_id'];
                }
            }
        }
        
        // 契約期間が切れたテナントを検出（PHP側で時刻比較）
        $activeTenants = $this->db->fetchAll('tenants', ['subscription_status' => 'active']);
        foreach ($activeTenants as $tenant) {
            if (!empty($tenant['subscription_ends_at']) && strtotime($tenant['subscription_ends_at']) < $nowTs) {
                $ok = $this->db->update('tenants', ['subscription_status' => 'expired'], ['tenant_id' => $tenant['tenant_id']]);
                if ($ok) {
                    $updatedTenants[] = $tenant['tenant_id'];
                }
            }
        }
        
        return $updatedTenants;
    }
}