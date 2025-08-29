<?php
/**
 * User クラス
 * 
 * ユーザー情報の管理を行うクラス
 */
class User
{
    private $db;
    
    /**
     * コンストラクタ - データベース接続を取得
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    
    /**
     * ユーザー認証を行う
     * 
     * @param string $email メールアドレス
     * @param string $password パスワード
     * @return array|false 認証成功時はユーザー情報の配列、失敗時はfalse
     */
    public function authenticate($email, $password)
    {
        $user = $this->db->fetchOne('users', ['email' => $email, 'status' => 'active']);
        
        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            return $user;
        }
        
        return false;
    }
    
    /**
     * ログイン処理
     * 
     * @param string $identifier ユーザーIDまたはメールアドレス
     * @param string $password パスワード
     * @param bool $rememberMe ログイン状態を保持するか
     * @return bool ログイン成功時はtrue、失敗時はfalse
     */
    public function login($identifier, $password, $rememberMe = false) {
        try {
            // 1) email で検索、なければ user_id で検索
            $user = $this->db->fetchOne('users', ['email' => $identifier, 'status' => 'active']);
            if (!$user) {
                $user = $this->db->fetchOne('users', ['user_id' => $identifier, 'status' => 'active']);
            }
            
            if (!$user) {
                error_log('ユーザーが見つかりません: ' . $identifier);
                return false;
            }
            
            if (!password_verify($password, $user['password'])) {
                error_log('パスワードが一致しません');
                return false;
            }
            
            // ロール名を取得
            $roleName = null;
            if (!empty($user['role_id'])) {
                $role = $this->db->fetchOne('roles', ['role_id' => $user['role_id']], 'role_name');
                $roleName = $role ? $role['role_name'] : null;
            }
            
            // セッションにユーザー情報を保存
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_unique_id'] = $user['user_id'];
            $_SESSION['role_id'] = $user['role_id'] ?? null;
            $_SESSION['role_name'] = $roleName;
            $_SESSION['user_name'] = $user['name'] ?? '';
            
            // テナントIDを設定（存在し、全体管理者でない場合）
            if (isset($user['tenant_id']) && $roleName !== 'admin') {
                $_SESSION['tenant_id'] = $user['tenant_id'];
                $tenant = $this->db->fetchOne('tenants', ['tenant_id' => $user['tenant_id']]);
                if ($tenant) {
                    $_SESSION['tenant_name'] = $tenant['company_name'] ?? '';
                }
            }
            
            // アクセス可能なサロンのIDリストをセッションに保存
            $_SESSION['accessible_salons'] = $this->getAccessibleSalonIds($user['id'], $roleName, $user['tenant_id'] ?? null);
            
            // Remember Me機能
            if ($rememberMe) {
                $tokenPlain = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $tokenPlain);
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

                $hasTokenHash = $this->rememberTokensHasColumn('token_hash');

                if ($hasTokenHash) {
                    $this->db->insert('remember_tokens', [
                        'user_id' => $user['user_id'],
                        'token_hash' => $tokenHash,
                        'expires_at' => $expires
                    ]);
                } else {
                    // 旧スキーマ互換: token 列にハッシュを保存（平文は保存しない）
                    $this->db->insert('remember_tokens', [
                        'user_id' => $user['user_id'],
                        'token' => $tokenHash,
                        'expires_at' => $expires
                    ]);
                }

                $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                setcookie('remember_token', $tokenPlain, strtotime('+30 days'), '/', '', $secure, true);
            }
            
            // 最終ログイン日時を更新
            $this->updateLastLogin($user['id']);
            
            return true;
            
        } catch (Exception $e) {
            error_log('ログインエラー: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remember Meトークンで自動ログイン
     *
     * @param string $tokenPlain Cookieからの平文トークン
     * @return bool 成功時true
     */
    public function autoLoginByToken($tokenPlain)
    {
        try {
            if (empty($tokenPlain)) { return false; }

            $tokenHash = hash('sha256', $tokenPlain);

            $hasTokenHash = $this->rememberTokensHasColumn('token_hash');

            $record = null;
            if ($hasTokenHash) {
                $record = $this->db->fetchOne('remember_tokens', ['token_hash' => $tokenHash], 'user_id,expires_at');
            } else {
                // まずハッシュで照合（ハッシュ保存へ移行後の互換）
                $record = $this->db->fetchOne('remember_tokens', ['token' => $tokenHash], 'user_id,expires_at');
                if (!$record) {
                    // 旧方式（平文）
                    $record = $this->db->fetchOne('remember_tokens', ['token' => $tokenPlain], 'user_id,expires_at');
                }
            }

            if (!$record) { return false; }

            if (strtotime($record['expires_at']) < time()) { return false; }

            // ユーザー取得（user_id は users.user_id と対応）
            $user = $this->db->fetchOne('users', ['user_id' => $record['user_id'], 'status' => 'active']);
            if (!$user) { return false; }

            $roleName = null;
            if (!empty($user['role_id'])) {
                $role = $this->db->fetchOne('roles', ['role_id' => $user['role_id']], 'role_name');
                $roleName = $role ? $role['role_name'] : null;
            }

            // セッションにユーザー情報を保存
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_unique_id'] = $user['user_id'];
            $_SESSION['role_id'] = $user['role_id'] ?? null;
            $_SESSION['role_name'] = $roleName;
            $_SESSION['user_name'] = $user['name'] ?? '';

            if (isset($user['tenant_id']) && $roleName !== 'admin') {
                $_SESSION['tenant_id'] = $user['tenant_id'];
                $tenant = $this->db->fetchOne('tenants', ['tenant_id' => $user['tenant_id']]);
                if ($tenant) { $_SESSION['tenant_name'] = $tenant['company_name'] ?? ''; }
            }

            $_SESSION['accessible_salons'] = $this->getAccessibleSalonIds($user['id'], $roleName, $user['tenant_id'] ?? null);

            // トークンをローテーション（置き換え）
            $newTokenPlain = bin2hex(random_bytes(32));
            $newTokenHash = hash('sha256', $newTokenPlain);
            $newExpires = date('Y-m-d H:i:s', strtotime('+30 days'));

            if ($hasTokenHash) {
                $this->db->update('remember_tokens', [
                    'token_hash' => $newTokenHash,
                    'expires_at' => $newExpires
                ], ['user_id' => $record['user_id']]);
            } else {
                $this->db->update('remember_tokens', [
                    'token' => $newTokenHash,
                    'expires_at' => $newExpires
                ], ['user_id' => $record['user_id']]);
            }

            $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            setcookie('remember_token', $newTokenPlain, strtotime('+30 days'), '/', '', $secure, true);

            $this->updateLastLogin($user['id']);
            return true;

        } catch (Exception $e) {
            error_log('autoLoginByToken エラー: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * remember_tokens テーブルに列が存在するか（Supabase REST対応）
     */
    private function rememberTokensHasColumn($columnName)
    {
        try {
            // 該当カラムのみを選択してみる。存在しない場合は Supabase が 400 を返すため例外で判定
            $this->db->fetchOne('remember_tokens', [], $columnName);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * ユーザー情報を取得
     * 
     * @param int $userId ユーザーID
     * @return array|false ユーザー情報の配列、またはユーザーが見つからない場合はfalse
     */
    public function getById($userId)
    {
        $user = $this->db->fetchOne('users', ['id' => $userId]);
        if (!$user) { return false; }
        $roleName = null;
        if (!empty($user['role_id'])) {
            $role = $this->db->fetchOne('roles', ['role_id' => $user['role_id']], 'role_name');
            $roleName = $role ? $role['role_name'] : null;
        }
        $user['role_name'] = $roleName;
        unset($user['password']);
        return $user;
    }
    
    /**
     * ユーザーが特定のロールを持っているか確認
     * 
     * @param int $userId ユーザーID
     * @param string $roleName ロール名
     * @return bool ユーザーが指定されたロールを持っている場合はtrue
     */
    public function hasRole($userId, $roleName)
    {
        $user = $this->db->fetchOne('users', ['id' => $userId]);
        if (!$user || empty($user['role_id'])) { return false; }
        $role = $this->db->fetchOne('roles', ['role_id' => $user['role_id']], 'role_name');
        return $role && $role['role_name'] === $roleName;
    }
    
    /**
     * 最終ログイン日時を更新
     * 
     * @param int $userId ユーザーID
     * @return bool 更新が成功したかどうか
     */
    public function updateLastLogin($userId)
    {
        $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $userId]);
        return true;
    }

    /**
     * パスワードリセットトークンを生成
     */
    public function generatePasswordResetToken($email)
    {
        $user = $this->db->fetchOne('users', ['email' => $email, 'status' => 'active'], 'id,user_id,email,name');
        if (!$user) { return false; }
        
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // 既存のトークンを削除
        $this->db->delete('password_resets', ['email' => $email]);

        // 新しいトークンを保存
        $this->db->insert('password_resets', [
            'email' => $email,
            'token_hash' => $tokenHash,
            'expires_at' => $expires
        ]);

        return [
            'token' => $token,
            'user' => $user
        ];
    }

    /**
     * パスワードリセットトークンを検証
     */
    public function verifyResetToken($token)
    {
        $tokenHash = hash('sha256', $token);
        $row = $this->db->fetchOne('password_resets', ['token_hash' => $tokenHash], 'email,expires_at');
        if (!$row) { return false; }
        if (strtotime($row['expires_at']) <= time()) { return false; }
        return $row['email'];
    }

    /**
     * パスワードをリセット
     */
    public function resetPassword($token, $newPassword)
    {
        $email = $this->verifyResetToken($token);
        if (!$email) { return false; }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->update('users', ['password' => $passwordHash], ['email' => $email]);
        $this->db->delete('password_resets', ['email' => $email]);
        return true;
    }
    
    /**
     * テナントに属するユーザーを取得
     */
    public function getByTenantId($tenantId, $status = null)
    {
        $filters = ['tenant_id' => $tenantId];
        if ($status) { $filters['status'] = $status; }
        $users = $this->db->fetchAll('users', $filters, '*', ['order' => 'name.asc']);
        foreach ($users as &$u) { unset($u['password']); }
        return $users;
    }
    
    /**
     * テナント内でユーザーのメールアドレスが重複していないかチェック
     */
    public function isEmailUniqueInTenant($email, $tenantId, $excludeUserId = null)
    {
        $row = $this->db->fetchOne('users', ['email' => $email, 'tenant_id' => $tenantId], 'id');
        if (!$row) { return true; }
        if ($excludeUserId && (int)$row['id'] === (int)$excludeUserId) { return true; }
        return false;
    }
    
    /**
     * メールアドレスが既に存在するかチェック（テナント関係なく）
     */
    public function emailExists($email, $excludeUserId = null)
    {
        $row = $this->db->fetchOne('users', ['email' => $email], 'id');
        if (!$row) { return false; }
        if ($excludeUserId && (int)$row['id'] === (int)$excludeUserId) { return false; }
        return true;
    }
    
    /**
     * ユーザーを登録する
     */
    public function register($data)
    {
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $userId = $this->generateUniqueUserId($data['name']);
        $roleName = $data['role'] ?? 'staff';
        $roleId = $this->getRoleIdByName($roleName);
        if (!$roleId) { throw new Exception('指定されたロールが見つかりません: ' . $roleName); }

        $insert = [
            'user_id' => $userId,
            'tenant_id' => $data['tenant_id'] ?? null,
            'email' => $data['email'],
            'password' => $passwordHash,
            'name' => $data['name'],
            'status' => $data['status'] ?? 'active',
            'role_id' => $roleId,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $res = $this->db->insert('users', $insert);
        $new = is_array($res) && isset($res[0]) ? $res[0] : $res;
        if (!isset($new['id'])) { throw new Exception('ユーザー登録に失敗しました'); }
        return (int)$new['id'];
    }
    
    /**
     * 指定されたユーザーIDが既に存在するか確認
     */
    private function userIdExists($userId)
    {
        $row = $this->db->fetchOne('users', ['user_id' => $userId], 'id');
        return (bool)$row;
    }

    /**
     * 一意なユーザーIDを生成（"U" + 10桁英数字）
     */
    private function generateUniqueUserId($seed = '')
    {
        for ($i = 0; $i < 10; $i++) {
            $candidate = 'U' . substr(bin2hex(random_bytes(8)), 0, 10);
            if (!$this->userIdExists($candidate)) {
                return $candidate;
            }
        }
        // フォールバック
        $fallback = 'U' . substr(hash('sha1', $seed . microtime(true)), 0, 10);
        return $fallback;
    }
    
    /**
     * ロール名からロールIDを取得
     */
    private function getRoleIdByName($roleName)
    {
        $row = $this->db->fetchOne('roles', ['role_name' => $roleName], 'role_id');
        return $row ? $row['role_id'] : null;
    }
    
    /**
     * ユーザーアカウントを作成する（管理者用）
     * 
     * @param array $data ユーザーデータ
     * @return int 新しく作成されたユーザーのID
     */
    public function create($data)
    {
        // メールアドレスの重複チェック
        if (isset($data['tenant_id']) && !$this->isEmailUniqueInTenant($data['email'], $data['tenant_id'])) {
            throw new Exception('メールアドレスは既に使用されています。');
        }
        return $this->register($data);
    }
    
    /**
     * ユーザー情報を更新する
     * 
     * @param array $data ユーザーデータ
     * @return bool 更新が成功したかどうか
     */
    public function updateUser($data)
    {
        if (!isset($data['id'])) {
            throw new Exception('ユーザーIDが指定されていません。');
        }
        $userId = $data['id'];
        $update = [];
        foreach (['name','email','status','tenant_id','role_id'] as $field) {
            if (array_key_exists($field, $data)) { $update[$field] = $data[$field]; }
        }
        if (!empty($data['password'])) { $update['password'] = password_hash($data['password'], PASSWORD_DEFAULT); }
        if (empty($update)) { return false; }
        $update['updated_at'] = date('Y-m-d H:i:s');
        $this->db->update('users', $update, ['id' => $userId]);
        return true;
    }
    
    /**
     * ユーザーを削除する
     */
    public function performDelete($userId)
    {
        $this->db->delete('users', ['id' => $userId]);
        return true;
    }
    
    /**
     * ログアウト処理
     */
    public function logout()
    {
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) { setcookie(session_name(), '', time() - 86400, '/'); }
        
        if (isset($_COOKIE['remember_token'])) {
            $tokenPlain = $_COOKIE['remember_token'];
            $tokenHash = hash('sha256', $tokenPlain);

            try {
                $hasTokenHash = $this->rememberTokensHasColumn('token_hash');
                if ($hasTokenHash) {
                    $this->db->delete('remember_tokens', ['token_hash' => $tokenHash]);
                } else {
                    $this->db->delete('remember_tokens', ['token' => $tokenHash]);
                    $this->db->delete('remember_tokens', ['token' => $tokenPlain]);
                }
            } catch (Exception $e) {
                error_log('Remember Me トークン削除エラー: ' . $e->getMessage());
            }

            $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            setcookie('remember_token', '', time() - 86400, '/', '', $secure, true);
        }
        session_destroy();
    }
    
    /**
     * ユーザーがアクセス可能なサロン一覧を取得
     */
    public function getAccessibleSalons($userId)
    {
        try {
            $user = $this->getById($userId);
            if (!$user) { return []; }
            $roleName = $user['role_name'] ?? null;

            // 事前にテナント一覧を取得してマップ化
            $tenants = $this->db->fetchAll('tenants', [], 'tenant_id,company_name');
            $tenantMap = [];
            foreach ($tenants as $t) { $tenantMap[$t['tenant_id']] = $t['company_name'] ?? null; }

            if ($roleName === 'admin') {
                $salons = $this->db->fetchAll('salons', ['status' => 'active'], '*', ['order' => 'name.asc']);
                foreach ($salons as &$s) { $s['company_name'] = $tenantMap[$s['tenant_id']] ?? null; }
                return $salons;
            }
            
            if ($roleName === 'tenant_admin' && !empty($user['tenant_id'])) {
                $salons = $this->db->fetchAll('salons', ['tenant_id' => $user['tenant_id'], 'status' => 'active'], '*', ['order' => 'name.asc']);
                foreach ($salons as &$s) { $s['company_name'] = $tenantMap[$s['tenant_id']] ?? null; }
                return $salons;
            }
            
            // manager/staff
            $userSalons = $this->db->fetchAll('user_salons', ['user_id' => $userId], 'salon_id');
            $salonIds = array_map(function($r){ return (int)$r['salon_id']; }, $userSalons);
            if (empty($salonIds)) { return []; }
            $salons = $this->db->fetchAll('salons', ['salon_id' => $salonIds, 'status' => 'active'], '*', ['order' => 'name.asc']);
            foreach ($salons as &$s) { $s['company_name'] = $tenantMap[$s['tenant_id']] ?? null; }
            return $salons;
            
        } catch (Exception $e) {
            error_log("getAccessibleSalons エラー: " . $e->getMessage());
            return [];
        }
    }

    /**
     * アクセス可能なサロンID一覧を取得（セッション格納用の軽量版）
     */
    private function getAccessibleSalonIds($userId, $roleName = null, $tenantId = null)
    {
        $salons = $this->getAccessibleSalons($userId);
        return array_column($salons, 'salon_id');
    }
    
    /**
     * ユーザーが特定のサロンにアクセス可能かチェック
     */
    public function canAccessSalon($userId, $salonId)
    {
        $accessibleSalons = $this->getAccessibleSalons($userId);
        $accessibleSalonIds = array_column($accessibleSalons, 'salon_id');
        return in_array($salonId, $accessibleSalonIds);
    }
    
    /**
     * Supabaseユーザー情報を使用してローカルデータベースにユーザーを作成
     */
    public function createWithSupabase($supabase_user_id, $email, $name, $tenantId, $salonId, $roleId = 'staff')
    {
        try {
            // 重複チェック
            $existingUser = $this->db->fetchOne('users', ['email' => $email]);
            if ($existingUser) { error_log("メールアドレスが既に使用されています: " . $email); return false; }
            $existingSupabaseUser = $this->db->fetchOne('users', ['supabase_user_id' => $supabase_user_id]);
            if ($existingSupabaseUser) { error_log("SupabaseユーザーIDが既に使用されています: " . $supabase_user_id); return false; }
            
            // ユーザーID生成
            $userId = 'U' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
            while ($this->db->fetchOne('users', ['user_id' => $userId])) {
                $userId = 'U' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
            }

            // role_id 解決（数値ID or ロール名）
            if (is_numeric($roleId)) {
                $resolvedRoleId = (int)$roleId;
            } else {
                $resolvedRoleId = $this->getRoleIdByName($roleId ?: 'staff');
                if (!$resolvedRoleId) { $resolvedRoleId = $this->getRoleIdByName('staff'); }
            }
            
            // 挿入
            $res = $this->db->insert('users', [
                'user_id' => $userId,
                'supabase_user_id' => $supabase_user_id,
                'name' => $name,
                'email' => $email,
                'role_id' => $resolvedRoleId,
                'tenant_id' => $tenantId,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $new = is_array($res) && isset($res[0]) ? $res[0] : $res;
            if (!isset($new['id'])) { error_log('ユーザー作成に失敗しました'); return false; }
            $newUserId = $new['id'];
            
            // サロン関連付け
            if (!empty($salonId)) {
                $this->db->insert('user_salons', [
                    'user_id' => $newUserId,
                    'salon_id' => $salonId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // 作成ユーザー取得
            $createdUser = $this->db->fetchOne('users', ['id' => $newUserId]);
            if ($createdUser && isset($createdUser['role_id'])) {
                $role = $this->db->fetchOne('roles', ['role_id' => $createdUser['role_id']], 'role_name');
                if ($role) { $createdUser['role_name'] = $role['role_name']; }
            }
            
            error_log("ユーザー作成成功: " . json_encode($createdUser, JSON_UNESCAPED_UNICODE));
            return $createdUser;
            
        } catch (Exception $e) {
            error_log("createWithSupabase エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * SupabaseユーザーIDでユーザーを検索
     * 
     * @param string $supabase_user_id SupabaseユーザーID
     * @return array|false ユーザー情報または false
     */
    public function findBySupabaseId($supabase_user_id)
    {
        try {
            // Supabase REST APIを使用してユーザーを検索
            $filters = [
                'supabase_user_id' => $supabase_user_id,
                'status' => 'active'
            ];
            
            $result = $this->db->fetchOne('users', $filters);
            
            if ($result && isset($result['role_id'])) {
                // 別途ロール情報を取得
                $role = $this->db->fetchOne('roles', ['role_id' => $result['role_id']], 'role_name');
                if ($role) {
                    $result['role_name'] = $role['role_name'];
                }
            }
            
            error_log("findBySupabaseId 結果: " . json_encode($result, JSON_UNESCAPED_UNICODE));
            return $result;
        } catch (Exception $e) {
            error_log("findBySupabaseId エラー: " . $e->getMessage());
            return false;
        }
    }
}