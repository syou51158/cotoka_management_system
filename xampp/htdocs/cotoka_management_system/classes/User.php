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
        $sql = "SELECT * FROM users WHERE email = ? AND status = 'active'";
        $user = $this->db->fetchOne($sql, [$email]);
        
        if ($user && password_verify($password, $user['password'])) {
            // パスワードハッシュをデータベースに保存しない
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
            // メールアドレスまたはユーザーIDでユーザーを検索
            $sql = "SELECT u.*, r.role_name 
                   FROM users u 
                   LEFT JOIN roles r ON u.role_id = r.role_id 
                   WHERE (u.email = ? OR u.user_id = ?) AND u.status = 'active' 
                   LIMIT 1";
            $params = [$identifier, $identifier];
            
            // デバッグ情報
            error_log('Login SQL: ' . $sql);
            error_log('Login Parameters: ' . json_encode($params, JSON_UNESCAPED_UNICODE));
            
            $user = $this->db->fetchOne($sql, $params);
            
            error_log('ユーザー情報: ' . json_encode($user, JSON_UNESCAPED_UNICODE));
            
            if (!$user) {
                error_log('ユーザーが見つかりません: ' . $identifier);
                return false;
            }
            
            if (!password_verify($password, $user['password'])) {
                error_log('パスワードが一致しません');
                return false;
            }
            
            // セッションにユーザー情報を保存
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_unique_id'] = $user['user_id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['user_name'] = $user['name'];
            
            // テナントIDを設定（存在し、全体管理者でない場合）
            if (isset($user['tenant_id']) && $user['role_name'] !== 'admin') {
                $_SESSION['tenant_id'] = $user['tenant_id'];
                
                // テナント情報を取得
                $sql = "SELECT * FROM tenants WHERE tenant_id = ?";
                $tenant = $this->db->fetchOne($sql, [$user['tenant_id']]);
                
                if ($tenant) {
                    $_SESSION['tenant_name'] = $tenant['company_name'];
                }
            }
            
            // アクセス可能なサロンのリストを取得（役割に基づいて）
            $_SESSION['accessible_salons'] = $this->getAccessibleSalonIds($user['id'], $user['role_name'], $user['tenant_id'] ?? null);
            
            error_log('ログイン成功。セッション情報: ' . json_encode($_SESSION, JSON_UNESCAPED_UNICODE));
            
            // Remember Me機能
            if ($rememberMe) {
                $tokenPlain = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $tokenPlain);
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

                // remember_tokens の列存在チェック
                $hasTokenHash = $this->rememberTokensHasColumn('token_hash');

                if ($hasTokenHash) {
                    $sql = "INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)";
                    $this->db->query($sql, [
                        $user['user_id'],
                        $tokenHash,
                        $expires
                    ]);
                } else {
                    // 旧スキーマ互換: token 列にハッシュを保存（平文は保存しない）
                    $sql = "INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)";
                    $this->db->query($sql, [
                        $user['user_id'],
                        $tokenHash,
                        $expires
                    ]);
                }

                // Cookieを設定（HTTPS環境のみsecure、それ以外はfalse）
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
            if (empty($tokenPlain)) {
                return false;
            }

            $tokenHash = hash('sha256', $tokenPlain);

            // remember_tokens の列存在を確認
            $hasTokenHash = $this->rememberTokensHasColumn('token_hash');

            $record = null;
            if ($hasTokenHash) {
                $sql = "SELECT user_id, expires_at FROM remember_tokens WHERE token_hash = ? LIMIT 1";
                $record = $this->db->fetchOne($sql, [$tokenHash]);
            } else {
                // まずハッシュで照合（ハッシュ保存へ移行後の互換）
                $sql = "SELECT user_id, expires_at FROM remember_tokens WHERE token = ? LIMIT 1";
                $record = $this->db->fetchOne($sql, [$tokenHash]);

                // 見つからなければ旧方式（平文保存）も試す（移行用）
                if (!$record) {
                    $record = $this->db->fetchOne($sql, [$tokenPlain]);
                }
            }

            if (!$record) {
                return false;
            }

            // 期限チェック
            if (strtotime($record['expires_at']) < time()) {
                return false;
            }

            // ユーザー取得（user_id は users.user_id と対応）
            $userSql = "SELECT u.*, r.role_name 
                        FROM users u 
                        LEFT JOIN roles r ON u.role_id = r.role_id 
                        WHERE u.user_id = ? AND u.status = 'active' 
                        LIMIT 1";
            $user = $this->db->fetchOne($userSql, [$record['user_id']]);
            if (!$user) {
                return false;
            }

            // セッションにユーザー情報を保存
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_unique_id'] = $user['user_id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['user_name'] = $user['name'];

            if (isset($user['tenant_id']) && $user['role_name'] !== 'admin') {
                $_SESSION['tenant_id'] = $user['tenant_id'];
                $tenant = $this->db->fetchOne("SELECT * FROM tenants WHERE tenant_id = ?", [$user['tenant_id']]);
                if ($tenant) {
                    $_SESSION['tenant_name'] = $tenant['company_name'];
                }
            }

            $_SESSION['accessible_salons'] = $this->getAccessibleSalonIds($user['id'], $user['role_name'], $user['tenant_id'] ?? null);

            // トークンをローテーション（置き換え）
            $newTokenPlain = bin2hex(random_bytes(32));
            $newTokenHash = hash('sha256', $newTokenPlain);
            $newExpires = date('Y-m-d H:i:s', strtotime('+30 days'));

            if ($hasTokenHash) {
                // 既存のレコードを更新
                $this->db->query("UPDATE remember_tokens SET token_hash = ?, expires_at = ? WHERE user_id = ?", [
                    $newTokenHash,
                    $newExpires,
                    $record['user_id']
                ]);
            } else {
                // 旧スキーマ: token 列を更新（ハッシュで上書き）
                $this->db->query("UPDATE remember_tokens SET token = ?, expires_at = ? WHERE user_id = ?", [
                    $newTokenHash,
                    $newExpires,
                    $record['user_id']
                ]);
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
     * remember_tokens テーブルに列が存在するか
     */
    private function rememberTokensHasColumn($columnName)
    {
        try {
            $sql = "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS 
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'remember_tokens' AND COLUMN_NAME = ?";
            $row = $this->db->fetchOne($sql, [DB_NAME, $columnName]);
            return isset($row['cnt']) && (int)$row['cnt'] > 0;
        } catch (Exception $e) {
            // 確認できない場合は false
            error_log('rememberTokensHasColumn エラー: ' . $e->getMessage());
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
        $sql = "SELECT u.*, r.role_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.role_id 
                WHERE u.id = ?";
        $user = $this->db->fetchOne($sql, [$userId]);
        
        if ($user) {
            unset($user['password']);
        }
        
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
        $sql = "SELECT COUNT(*) as count 
                FROM users u 
                JOIN roles r ON u.role_id = r.role_id 
                WHERE u.id = ? AND r.role_name = ?";
        $result = $this->db->fetchOne($sql, [$userId, $roleName]);
        
        return $result['count'] > 0;
    }
    
    /**
     * 役割に基づいてアクセス可能なサロンIDのリストを取得
     * 
     * @param int $userId ユーザーID
     * @param string $roleName ロール名
     * @param int|null $tenantId テナントID
     * @return array サロンIDの配列
     */
    private function getAccessibleSalonIds($userId, $roleName, $tenantId = null)
    {
        $salonIds = [];
        
        // 全体管理者は全てのサロンにアクセス可能
        if ($roleName === 'admin') {
            $sql = "SELECT salon_id FROM salons";
            $salons = $this->db->fetchAll($sql);
            
            foreach ($salons as $salon) {
                $salonIds[] = (int)$salon['salon_id'];
            }
            
            return $salonIds;
        }
        
        // テナント管理者はそのテナントの全サロンにアクセス可能
        if ($roleName === 'tenant_admin' && $tenantId) {
            $sql = "SELECT salon_id FROM salons WHERE tenant_id = ?";
            $salons = $this->db->fetchAll($sql, [$tenantId]);
            
            foreach ($salons as $salon) {
                $salonIds[] = (int)$salon['salon_id'];
            }
            
            return $salonIds;
        }
        
        // マネージャーとスタッフはuser_salonsテーブルで定義されたサロンのみアクセス可能
        if (($roleName === 'manager' || $roleName === 'staff') && $tenantId) {
            $sql = "SELECT salon_id FROM user_salons WHERE user_id = ?";
            $salons = $this->db->fetchAll($sql, [$userId]);
            
            foreach ($salons as $salon) {
                $salonIds[] = (int)$salon['salon_id'];
            }
            
            return $salonIds;
        }
        
        return $salonIds;
    }
    
    // 重複していた canAccessSalon の定義を統合しました（下部の実装を使用）
    
    /**
     * 最終ログイン日時を更新
     * 
     * @param int $userId ユーザーID
     * @return bool 更新が成功したかどうか
     */
    public function updateLastLogin($userId)
    {
        $sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->query($sql, [$userId]);
        return true;
    }
    
    /**
     * テナントに属するユーザーを取得
     * 
     * @param int $tenantId テナントID
     * @param string|null $status ステータスでフィルタリング
     * @return array ユーザー情報の配列
     */
    public function getByTenantId($tenantId, $status = null)
    {
        $sql = "SELECT * FROM users WHERE tenant_id = ?";
        $params = [$tenantId];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY name ASC";
        
        $users = $this->db->fetchAll($sql, $params);
        
        // パスワードハッシュをリストから削除
        foreach ($users as &$user) {
            unset($user['password']);
        }
        
        return $users;
    }
    
    /**
     * テナント内でユーザーのメールアドレスが重複していないかチェック
     * 
     * @param string $email メールアドレス
     * @param int $tenantId テナントID
     * @param int|null $excludeUserId 除外するユーザーID（更新時）
     * @return bool メールアドレスが重複していなければtrue
     */
    public function isEmailUniqueInTenant($email, $tenantId, $excludeUserId = null)
    {
        $sql = "SELECT COUNT(*) as count FROM users WHERE email = ? AND tenant_id = ?";
        $params = [$email, $tenantId];
        
        if ($excludeUserId) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        
        return $result['count'] == 0;
    }
    
    /**
     * メールアドレスが既に存在するかチェック（テナント関係なく）
     * 
     * @param string $email メールアドレス
     * @param int|null $excludeUserId 除外するユーザーID（更新時）
     * @return bool メールアドレスが存在する場合はtrue
     */
    public function emailExists($email, $excludeUserId = null)
    {
        $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeUserId) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        
        return $result['count'] > 0;
    }
    
    /**
     * ユーザーを登録する
     * 
     * @param array $data ユーザーデータ
     * @return int 新しく登録されたユーザーのID
     */
    public function register($data)
    {
        // パスワードをハッシュ化
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // ユーザーIDを生成（ユニークな識別子として使用）
        $userId = $this->generateUniqueUserId($data['name']);
        
        // ロールIDを取得
        $roleName = $data['role'] ?? 'staff';
        $roleId = $this->getRoleIdByName($roleName);
        
        if (!$roleId) {
            throw new Exception('指定されたロールが見つかりません: ' . $roleName);
        }
        
        // SQLの準備
        $sql = "INSERT INTO users (user_id, tenant_id, email, password, name, status, role_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        // パラメータの準備
        $params = [
            $userId,
            $data['tenant_id'] ?? null,
            $data['email'],
            $passwordHash,
            $data['name'],
            $data['status'] ?? 'active',
            $roleId
        ];
        
        // クエリの実行
        $this->db->query($sql, $params);
        
        // 挿入されたIDを返す
        return $this->db->lastInsertId();
    }
    
    /**
     * ユニークなユーザーIDを生成する
     * 
     * @param string $name ユーザー名
     * @return string ユニークなユーザーID
     */
    private function generateUniqueUserId($name)
    {
        // 名前からベースとなる文字列を生成
        $base = mb_strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $name)));
        if (empty($base)) {
            $base = 'user';
        }
        
        // 最初の試み
        $userId = substr($base, 0, 8) . rand(100, 999);
        
        // 既に存在する場合は別のIDを試す
        $count = 0;
        $maxTries = 10;
        
        while ($this->userIdExists($userId) && $count < $maxTries) {
            $userId = substr($base, 0, 6) . rand(1000, 9999);
            $count++;
        }
        
        // それでも重複する場合はタイムスタンプを使用
        if ($this->userIdExists($userId)) {
            $userId = substr($base, 0, 5) . time();
        }
        
        return $userId;
    }
    
    /**
     * 指定されたユーザーIDが既に存在するか確認
     * 
     * @param string $userId ユーザーID
     * @return bool 存在する場合はtrue
     */
    private function userIdExists($userId)
    {
        $sql = "SELECT COUNT(*) as count FROM users WHERE user_id = ?";
        $result = $this->db->fetchOne($sql, [$userId]);
        
        return $result['count'] > 0;
    }
    
    /**
     * ロール名からロールIDを取得
     * 
     * @param string $roleName ロール名
     * @return int|null ロールID、存在しない場合はnull
     */
    private function getRoleIdByName($roleName)
    {
        $sql = "SELECT role_id FROM roles WHERE role_name = ?";
        $result = $this->db->fetchOne($sql, [$roleName]);
        
        return $result ? $result['role_id'] : null;
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
        $fields = [];
        $params = [];
        
        // 更新可能なフィールド
        $updateableFields = [
            'name', 'email', 'status', 'tenant_id', 'role_id'
        ];
        
        // フィールドの設定
        foreach ($updateableFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        // パスワード更新
        if (!empty($data['password'])) {
            $fields[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        // 更新するフィールドがあれば実行
        if (!empty($fields)) {
            $fields[] = "updated_at = NOW()";
            
            $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
            $params[] = $userId;
            
            $this->db->query($sql, $params);
            return true;
        }
        
        return false;
    }
    
    /**
     * ユーザーを削除する
     * 
     * @param int $userId ユーザーID
     * @return bool 削除が成功したかどうか
     */
    public function performDelete($userId)
    {
        $sql = "DELETE FROM users WHERE id = ?";
        $this->db->query($sql, [$userId]);
        
        return true;
    }
    
    /**
     * ログアウト処理
     */
    public function logout()
    {
        // セッション変数をクリア
        $_SESSION = [];
        
        // Cookieを削除
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 86400, '/');
        }
        
        // Remember Me CookieとDBトークンも削除
        if (isset($_COOKIE['remember_token'])) {
            $tokenPlain = $_COOKIE['remember_token'];
            $tokenHash = hash('sha256', $tokenPlain);

            try {
                $hasTokenHash = $this->rememberTokensHasColumn('token_hash');
                if ($hasTokenHash) {
                    $this->db->query("DELETE FROM remember_tokens WHERE token_hash = ?", [$tokenHash]);
                } else {
                    // 旧スキーマ: ハッシュ or 平文の両方を試す
                    $this->db->query("DELETE FROM remember_tokens WHERE token = ?", [$tokenHash]);
                    $this->db->query("DELETE FROM remember_tokens WHERE token = ?", [$tokenPlain]);
                }
            } catch (Exception $e) {
                error_log('Remember Me トークン削除エラー: ' . $e->getMessage());
            }

            $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            setcookie('remember_token', '', time() - 86400, '/', '', $secure, true);
        }
        
        // セッションを破棄
        session_destroy();
    }
    
    /**
     * ユーザーがアクセス可能なサロン一覧を取得
     * 
     * @param int $userId ユーザーID
     * @return array アクセス可能なサロンの配列
     */
    public function getAccessibleSalons($userId)
    {
        try {
            // ユーザー情報を取得
            $userSql = "SELECT u.*, r.role_name FROM users u 
                       LEFT JOIN roles r ON u.role_id = r.role_id 
                       WHERE u.id = ?";
            $user = $this->db->fetchOne($userSql, [$userId]);
            
            if (!$user) {
                return [];
            }
            
            // 管理者（admin）の場合は全てのサロンにアクセス可能
            if ($user['role_name'] === 'admin') {
                $sql = "SELECT s.*, t.company_name FROM salons s 
                       LEFT JOIN tenants t ON s.tenant_id = t.tenant_id 
                       WHERE s.status = 'active' 
                       ORDER BY t.company_name, s.name";
                return $this->db->fetchAll($sql);
            }
            
            // テナント管理者の場合は自テナントの全サロンにアクセス可能
            if ($user['role_name'] === 'tenant_admin' && $user['tenant_id']) {
                $sql = "SELECT s.*, t.company_name FROM salons s 
                       LEFT JOIN tenants t ON s.tenant_id = t.tenant_id 
                       WHERE s.tenant_id = ? AND s.status = 'active' 
                       ORDER BY s.name";
                return $this->db->fetchAll($sql, [$user['tenant_id']]);
            }
            
            // マネージャーやスタッフの場合は割り当てられたサロンのみ
            $sql = "SELECT s.*, t.company_name FROM salons s 
                   LEFT JOIN tenants t ON s.tenant_id = t.tenant_id 
                   INNER JOIN user_salons us ON s.salon_id = us.salon_id 
                   WHERE us.user_id = ? AND s.status = 'active' 
                   ORDER BY t.company_name, s.name";
            return $this->db->fetchAll($sql, [$userId]);
            
        } catch (Exception $e) {
            error_log("getAccessibleSalons エラー: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ユーザーが特定のサロンにアクセス可能かチェック
     * 
     * @param int $userId ユーザーID
     * @param int $salonId サロンID
     * @return bool アクセス可能な場合はtrue
     */
    public function canAccessSalon($userId, $salonId)
    {
        $accessibleSalons = $this->getAccessibleSalons($userId);
        $accessibleSalonIds = array_column($accessibleSalons, 'salon_id');
        return in_array($salonId, $accessibleSalonIds);
    }
} 