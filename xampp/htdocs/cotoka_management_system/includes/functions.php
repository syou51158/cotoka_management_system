<?php
/**
 * ユーティリティ関数
 * 
 * アプリケーション全体で使用する共通関数
 */

// 営業時間関連の関数をインクルード
$business_hours_functions = __DIR__ . '/business_hours_functions.php';
if (file_exists($business_hours_functions) && 
    !function_exists('getBusinessHours') && 
    basename($_SERVER['PHP_SELF']) !== 'select_datetime.php') {
    require_once $business_hours_functions;
}

/**
 * リダイレクト関数
 * 
 * @param string $path リダイレクト先のパス
 * @return void
 */
function redirect($path) {
    // BASE_URL定数が定義されていない場合、現在のディレクトリを基準にする
    $base = defined('BASE_URL') ? BASE_URL : '';
    $url = $base . '/' . ltrim($path, '/');
    
    // URLが正しく形成されているか確認
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        // 現在のホストを取得
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $baseDir = dirname($_SERVER['PHP_SELF']);
        
        // ベースディレクトリが複数階層ある場合の調整
        if ($baseDir !== '/' && $baseDir !== '\\') {
            $baseDir = rtrim($baseDir, '/\\') . '/';
        } else {
            $baseDir = '/';
        }
        
        $url = $protocol . $host . $baseDir . ltrim($path, '/');
    }
    
    header("Location: " . $url);
    exit;
}

/**
 * セッションメッセージの設定
 * 
 * @param string $type メッセージタイプ (success, error, warning, info)
 * @param string $message 表示するメッセージ
 * @return void
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * セッションメッセージの取得と削除
 * 
 * @return array|null メッセージ配列またはnull
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * CSRFトークンの生成
 * 
 * @return string 生成されたトークン
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRFトークンの検証
 * 
 * @param string $token 検証するトークン
 * @return bool 検証結果
 */
function validateCSRFToken($token) {
    if (isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === $token) {
        return true;
    }
    return false;
}

/**
 * 入力内容のサニタイズ
 * 
 * @param string $input サニタイズする文字列
 * @return string サニタイズされた文字列
 */
function sanitize($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * 日付のフォーマット
 * 
 * @param string $date フォーマットする日付
 * @param string $format 日付フォーマット
 * @return string フォーマットされた日付
 */
function formatDate($date, $format = DATE_FORMAT) {
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * 時間のフォーマット
 * 
 * @param string $time フォーマットする時間
 * @param string $format 時間フォーマット
 * @return string フォーマットされた時間
 */
function formatTime($time, $format = TIME_FORMAT) {
    $timestamp = strtotime($time);
    return date($format, $timestamp);
}

/**
 * 数値の金額フォーマット
 * 
 * @param float $amount フォーマットする金額
 * @return string フォーマットされた金額
 */
function formatCurrency($amount) {
    return '¥' . number_format($amount);
}

/**
 * パスワードのハッシュ化
 * 
 * @param string $password ハッシュ化するパスワード
 * @return string ハッシュ化されたパスワード
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
}

/**
 * パスワードの検証
 * 
 * @param string $password 検証するパスワード
 * @param string $hash 検証対象のハッシュ
 * @return bool 検証結果
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * ユーザー認証を行う
 * 
 * @param string $email メールアドレス
 * @param string $password パスワード
 * @return array|false 認証成功時はユーザー情報の配列、失敗時はfalse
 */
function authenticateUser($email, $password) {
    $db = new Database();
    $user = new User($db);
    return $user->authenticate($email, $password);
}

/**
 * ログイン状態の確認
 * 
 * @return bool ログイン状態
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * ユーザーが管理者権限を持っているかどうかを確認
 * 
 * @return bool 管理者ならtrue
 */
function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $userRole = getUserRole();
    return in_array($userRole, ['admin', 'tenant_admin', 'manager']);
}

/**
 * テナント管理者かどうかを確認
 * 
 * @return bool テナント管理者権限の有無
 */
function isTenantAdmin() {
    $userRole = getUserRole();
    return isLoggedIn() && $userRole === 'tenant_admin';
}

/**
 * 全体管理者かどうかを確認
 * 
 * @return bool 全体管理者権限の有無
 */
function isGlobalAdmin() {
    if (!isLoggedIn()) return false;
    // セッションのrole_nameを優先し、未設定時は従来のrolesテーブル参照
    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'admin') {
        return true;
    }
    $userRole = getUserRole();
    return $userRole === 'admin';
}

/**
 * 管理者権限（テナント管理者またはマネージャー）を持っているかどうかを確認
 * 
 * @return bool 管理者権限の有無
 */
function hasAdminPermission() {
    $userRole = getUserRole();
    return isLoggedIn() && in_array($userRole, ['admin', 'tenant_admin', 'manager']);
}

/**
 * 現在のテナントIDを取得
 * 
 * @return int 現在のテナントID
 */
function getCurrentTenantId() {
    // 通常はセッションから
    if (isLoggedIn() && isset($_SESSION['tenant_id']) && $_SESSION['tenant_id']) {
        return $_SESSION['tenant_id'];
    }
    // セッションに無い（例: admin）場合は、現在のサロンから推定
    $currentSalonId = $_SESSION['salon_id'] ?? $_SESSION['current_salon_id'] ?? null;
    if ($currentSalonId) {
        $rpc = supabaseRpcCall('salon_get', ['p_salon_id' => (int)$currentSalonId]);
        if ($rpc['success'] && !empty($rpc['data'])) {
            $row = is_array($rpc['data']) ? ($rpc['data'][0] ?? null) : null;
            if ($row && isset($row['tenant_id'])) {
                $_SESSION['tenant_id'] = (int)$row['tenant_id'];
                return (int)$row['tenant_id'];
            }
        }
    }
    return null;
}

/**
 * 現在のセッションからサロンIDを取得する関数
 * @return int サロンID
 */
function getCurrentSalonId() {
    // 基本的にはセッションからサロンIDを取得する
    $salon_id = $_SESSION['salon_id'] ?? $_SESSION['current_salon_id'] ?? null;

    // デバッグログ
    error_log("getCurrentSalonId: セッションからサロンID取得試行: " . var_export($salon_id, true));

    // GETパラメータでサロンIDが指定されている場合はそちらを優先
    if (isset($_GET['salon_id']) && is_numeric($_GET['salon_id'])) {
        $salon_id = intval($_GET['salon_id']);
        error_log("getCurrentSalonId: GETパラメータからサロンID取得: " . $salon_id);
    }

    // 未設定なら、Supabaseのアクセス可能サロンから先頭を自動選択
    if (empty($salon_id)) {
        $user_uid = $_SESSION['user_unique_id'] ?? null;
        $user_id = $_SESSION['user_id'] ?? null;
        try {
            if ($user_uid) {
                $rpc = supabaseRpcCall('user_accessible_salons_by_uid', ['p_user_uid' => (string)$user_uid]);
            } elseif ($user_id) {
                $rpc = supabaseRpcCall('user_accessible_salons', ['p_user_id' => (int)$user_id]);
            } else {
                $rpc = ['success' => false];
            }
            if (!empty($rpc['success']) && $rpc['success'] && !empty($rpc['data'])) {
                $first = $rpc['data'][0] ?? null;
                if ($first && isset($first['salon_id'])) {
                    $salon_id = (int)$first['salon_id'];
                    setCurrentSalon($salon_id);
                    error_log("getCurrentSalonId: アクセス可能サロンから自動選択: " . $salon_id);
                }
            }
        } catch (Exception $e) {
            error_log('getCurrentSalonId: 自動選択時エラー: ' . $e->getMessage());
        }
    }

    // それでも未設定なら null を返す
    return $salon_id !== null ? intval($salon_id) : null;
}

/**
 * 現在のサロンを設定
 * 
 * @param int $salonId 設定するサロンID
 * @return void
 */
function setCurrentSalon($salonId) {
    if (!$salonId) {
        error_log("setCurrentSalon: 無効なサロンID（null または 0）");
        return;
    }
    
    $_SESSION['current_salon_id'] = $salonId;
    $_SESSION['salon_id'] = $salonId; // 互換性のため両方設定
    
    error_log("setCurrentSalon: サロンIDを設定: " . $salonId);
}

/**
 * テナントの有効なサブスクリプションを持っているか確認
 * 
 * @param int|null $tenantId チェックするテナントID（nullの場合は現在のテナント）
 * @return bool 有効なサブスクリプションを持っているか
 */
function hasTenantActiveSubscription($tenantId = null) {
    if (!$tenantId) {
        $tenantId = getCurrentTenantId();
    }
    
    if (!$tenantId) {
        return false;
    }
    
    require_once ROOT_PATH . '/classes/Tenant.php';
    $tenantManager = new Tenant();
    $tenant = $tenantManager->getById($tenantId);
    
    if (!$tenant) {
        return false;
    }
    
    return in_array($tenant['subscription_status'], ['active', 'trial']);
}

/**
 * テナントの契約プランの上限を超えていないか確認
 * 
 * @param string $resourceType リソースタイプ ('salons', 'users', 'storage')
 * @param int|null $tenantId チェックするテナントID（nullの場合は現在のテナント）
 * @return bool 上限を超えていないか（trueなら問題なし）
 */
function checkTenantResourceLimit($resourceType, $tenantId = null) {
    if (!$tenantId) {
        $tenantId = getCurrentTenantId();
    }
    
    if (!$tenantId) {
        return false;
    }
    
    require_once ROOT_PATH . '/classes/Tenant.php';
    $tenantManager = new Tenant();
    
    return $tenantManager->checkResourceLimit($tenantId, $resourceType);
}

/**
 * テナントがアクセス可能なサロン一覧を取得
 * 
 * @param int|null $tenantId チェックするテナントID（nullの場合は現在のテナント）
 * @param bool $activeOnly アクティブなサロンのみ取得するか
 * @return array サロンの配列
 */
function getTenantSalons($tenantId = null, $activeOnly = true) {
    if (!$tenantId) {
        $tenantId = getCurrentTenantId();
    }
    
    if (!$tenantId) {
        return [];
    }
    
    require_once ROOT_PATH . '/classes/Salon.php';
    $salonManager = new Salon();
    return $salonManager->getSalonsByTenantId($tenantId, $activeOnly);
}

/**
 * ユーザーがサロンにアクセス可能かどうかをチェック
 * 
 * @param int $salonId チェックするサロンID
 * @param int|null $userId チェックするユーザーID（nullの場合は現在のユーザー）
 * @return bool アクセス可能かどうか
 */
function canUserAccessSalon($salonId, $userId = null) {
    if (!$userId && isLoggedIn()) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) {
        return false;
    }
    
    // テナント管理者は自テナント内の全サロンにアクセス可能
    if (isTenantAdmin()) {
        require_once ROOT_PATH . '/classes/Salon.php';
        $salonManager = new Salon();
        $salon = $salonManager->getById($salonId);
        
        if ($salon && $salon['tenant_id'] == getCurrentTenantId()) {
            return true;
        }
    }
    
    // スタッフユーザーは割り当てられたサロンのみアクセス可能
    $rootPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
    require_once $rootPath . '/classes/User.php';
    require_once $rootPath . '/classes/Database.php';
    $userManager = new User(new Database());
    return $userManager->canAccessSalon($userId, $salonId);
}

/**
 * システムログを記録
 * 
 * @param string $action 実行されたアクション
 * @param string $entityType エンティティタイプ（任意）
 * @param int|null $entityId エンティティID（任意）
 * @param string $details 詳細情報（任意）
 * @return bool 記録成功の場合true、失敗の場合false
 */
function logSystemActivity($action, $entityType = null, $entityId = null, $details = null) {
    try {
        $db = Database::getInstance();
        
        // テナントIDを取得（エラー防止のため、取得できない場合はnull）
        $tenantId = null;
        try {
            $tenantId = getCurrentTenantId();
        } catch (Exception $e) {
            error_log('テナントID取得エラー: ' . $e->getMessage());
        }
        
        // user_idが数値であることを確認
        $userId = null;
        if (isLoggedIn() && isset($_SESSION['user_id'])) {
            $userId = is_numeric($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        }
        
        // entityIdも数値型に変換
        if ($entityId !== null) {
            $entityId = is_numeric($entityId) ? (int)$entityId : null;
        }
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Supabase REST API 経由で挿入
        $db->insert('system_logs', [
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'action'     => $action,
            'entity_type'=> $entityType,
            'entity_id'  => $entityId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'details'    => $details,
        ]);
        return true;
    } catch (Exception $e) {
        error_log('システムログ記録エラー: ' . $e->getMessage());
        return false;
    }
}

/**
 * テナント設定を取得
 * 
 * @param string $key 設定キー
 * @param mixed $default デフォルト値（設定が見つからない場合）
 * @param int|null $tenantId テナントID（nullの場合は現在のテナント）
 * @return mixed 設定値
 */
function getTenantSetting($key, $default = null, $tenantId = null) {
    if (!$tenantId) {
        $tenantId = getCurrentTenantId();
    }
    
    if (!$tenantId) {
        return $default;
    }
    
    require_once ROOT_PATH . '/classes/TenantSetting.php';
    $settingManager = new TenantSetting();
    return $settingManager->get($tenantId, $key, $default);
}

/**
 * 配列のデバッグ表示
 * 
 * @param mixed $data 表示するデータ
 * @param bool $die 表示後に処理を停止するか
 * @return void
 */
function debug($data, $die = false) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
    if ($die) {
        die();
    }
}

/**
 * ログイン試行回数をチェック
 * 
 * @param string $identifier ユーザー識別子（メールアドレスまたはユーザーID）
 * @param Database $db データベースインスタンス
 * @param int $maxAttempts 最大試行回数
 * @param int $lockoutTime ロックアウト時間（秒）
 * @return bool 試行可能な場合はtrue
 */
function checkLoginAttempts($identifier, $db = null, $maxAttempts = 5, $lockoutTime = 300) {
    if ($db === null) {
        // Databaseインスタンスが無い場合でも制限はしない
        error_log('checkLoginAttempts: Database connection is null');
        return true;
    }
    
    try {
        // 現在時刻からlockoutTime秒前の時刻（ISO8601 / RFC3339）
        $cutoffIso = gmdate('c', time() - $lockoutTime);
        
        // Supabase REST APIでカウントを取得（gte演算子を使用）
        $attempt_count = $db->count('login_attempts', [
            'identifier'   => $identifier,
            'attempt_time' => ['op' => 'gte', 'value' => $cutoffIso],
        ]);
        
        if ($attempt_count >= $maxAttempts) {
            return false; // ロックアウト
        }
        return true;
    } catch (Exception $e) {
        error_log('checkLoginAttempts error: ' . $e->getMessage());
        return true; // エラー時は制限しない
    }
}

/**
 * ログイン試行回数を増加
 * 
 * @param string $identifier ユーザー識別子（メールアドレスまたはユーザーID）
 * @param Database $db データベースインスタンス
 * @return void
 */
function incrementLoginAttempts($identifier, $db = null) {
    if ($db === null) {
        error_log('incrementLoginAttempts: Database instance is null');
        return;
    }
    
    try {
        $db->insert('login_attempts', [
            'identifier'   => $identifier,
            'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'attempt_time' => gmdate('c'),
        ]);
    } catch (Exception $e) {
        error_log('incrementLoginAttempts error: ' . $e->getMessage());
    }
}

/**
 * ログイン試行回数をクリア
 * 
 * @param string $identifier ユーザー識別子（メールアドレスまたはユーザーID）
 * @param Database $db データベースインスタンス
 * @return void
 */
function clearLoginAttempts($identifier, $db = null) {
    if ($db === null) {
        error_log('clearLoginAttempts: Database instance is null');
        return;
    }
    
    try {
        $db->delete('login_attempts', ['identifier' => $identifier]);
    } catch (Exception $e) {
        error_log('clearLoginAttempts error: ' . $e->getMessage());
    }
}

/**
 * エラーログを記録
 * 
 * @param string $message エラーメッセージ
 * @param array $context コンテキスト情報
 * @return void
 */
function logError($message, $context = []) {
    // ROOT_PATHが定義されていない場合は、現在のディレクトリの親ディレクトリを使用
    $rootPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
    $logDir = $rootPath . '/logs';
    
    // ログディレクトリがなければ作成
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/error_' . date('Y-m-d') . '.log';
    
    $timestamp = date('Y-m-d H:i:s');
    
    // エラー情報を整形
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'guest';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // コンテキスト情報をJSON形式に変換
    $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '{}';
    
    // ログメッセージを作成
    $logMessage = sprintf(
        "[%s] [%s] [%s] %s\nUser-Agent: %s\nContext: %s\n",
        $timestamp,
        $userId,
        $ip,
        $message,
        $userAgent,
        $contextJson
    );
    
    // PHPのエラーログにも記録
    error_log($message);
    
    // ファイルへの書き込みを試みる
    if (!@file_put_contents($logFile, $logMessage . "\n", FILE_APPEND)) {
        error_log("Failed to write to log file: " . $logFile);
        return;
    }
}

/**
 * デバッグログを記録
 * 
 * @param string $message デバッグメッセージ
 * @param array $context 追加のコンテキスト情報
 * @return void
 */
function logDebug($message, $context = []) {
    // 開発環境かつデバッグモードがONの場合のみ記録
    if (!(defined('ENVIRONMENT') && ENVIRONMENT === 'development' && 
          defined('DEBUG_MODE') && DEBUG_MODE === true)) {
        return;
    }
    
    // ROOT_PATHが定義されていない場合は、現在のディレクトリの親ディレクトリを使用
    $rootPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
    $logDir = $rootPath . '/logs';
    
    // ログディレクトリがなければ作成
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/debug_' . date('Y-m-d') . '.log';
    
    // デバッグ情報を整形
    $timestamp = date('Y-m-d H:i:s');
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'guest';
    
    // コンテキスト情報をJSON形式に変換
    $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '{}';
    
    // ログメッセージを作成
    $logMessage = sprintf(
        "[%s] [%s] %s\nContext: %s\n",
        $timestamp,
        $userId,
        $message,
        $contextJson
    );
    
    // ログファイルに書き込み
    error_log($logMessage . "\n", 3, $logFile);
}

/**
 * 現在選択されているサロンの情報を取得
 * 
 * @return array|null サロン情報の配列または未選択の場合はnull
 */
function getCurrentSalonInfo() {
    if (!isset($_SESSION['current_salon_id']) || empty($_SESSION['current_salon_id'])) {
        return null;
    }
    
    try {
        $db = Database::getInstance();
        $salonId = $_SESSION['current_salon_id'];
        
        $salon = $db->fetchOne('salons', ['salon_id' => $salonId], '*');
        if ($salon) {
            return $salon;
        }
    } catch (Exception $e) {
        error_log('サロン情報取得エラー: ' . $e->getMessage());
    }
    
    return null;
}

/**
 * サロン情報を取得する
 * 
 * @param int $salonId サロンID
 * @return array|null サロン情報の配列またはnull
 */
function getSalonInfo($salonId) {
    if (!$salonId) return null;
    
    $db = Database::getInstance();
    try {
        return $db->fetchOne('salons', ['salon_id' => $salonId], '*');
    } catch (Exception $e) {
        error_log('サロン情報取得エラー: ' . $e->getMessage());
        return null;
    }
}

/**
 * ユーザー名を取得する
 * 
 * @return string ユーザー名
 */
function getUserName() {
    if (isset($_SESSION['user']) && isset($_SESSION['user']['first_name']) && isset($_SESSION['user']['last_name'])) {
        return $_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name'];
    }
    return '';
}

/**
 * ユーザーの役割を取得する
 * 
 * @return string ユーザーの役割名
 */
function getUserRole() {
    if (!isset($_SESSION['role_id'])) {
        error_log('SESSION情報: ' . print_r($_SESSION, true));
        return '';
    }
    
    $roleId = $_SESSION['role_id'];
    $db = Database::getInstance();
    
    try {
        $result = $db->fetchOne('roles', ['role_id' => $roleId], 'role_name');
        if ($result && isset($result['role_name'])) {
            return $result['role_name'];
        }
    } catch (Exception $e) {
        error_log('ロール取得エラー: ' . $e->getMessage());
    }
    
    return '';
}

/**
 * 現在のページがログインページかどうかを判定する
 * 
 * @return bool ログインページの場合はtrue
 */
function isLoginPage() {
    $script_name = basename($_SERVER['SCRIPT_NAME']);
    return in_array($script_name, ['login.php', 'register.php', 'forgot-password.php', 'reset-password.php']);
}

/**
 * 現在のページがアクティブかどうかを判定してCSSクラスを返す
 * 
 * @param string $page 確認するページ名
 * @return string アクティブな場合は'active'、そうでない場合は空文字
 */
function isActive($page) {
    $current_page = basename($_SERVER['SCRIPT_NAME']);
    return ($current_page == $page) ? 'active' : '';
}

/**
 * フラッシュメッセージを表示する
 * 
 * セッションに保存されたメッセージを取得して表示し、その後削除する
 * 
 * @return void
 */
function displayFlashMessages() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        
        $type = $message['type'];
        $msg = $message['message'];
        
        $alertClass = 'alert-info';
        switch ($type) {
            case 'success':
                $alertClass = 'alert-success';
                break;
            case 'error':
                $alertClass = 'alert-danger';
                break;
            case 'warning':
                $alertClass = 'alert-warning';
                break;
        }
        
        echo "<div class='alert {$alertClass} alert-dismissible fade show' role='alert'>";
        echo htmlspecialchars($msg);
        echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='閉じる'></button>";
        echo "</div>";
    }
    
    // エラーメッセージがある場合も表示
    if (isset($_SESSION['error_message'])) {
        echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>";
        echo htmlspecialchars($_SESSION['error_message']);
        echo "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='閉じる'></button>";
        echo "</div>";
        unset($_SESSION['error_message']);
    }
}

/**
 * 予約確認コードを生成する
 * 
 * @return string 生成された確認コード
 */
function generate_confirmation_code() {
    $prefix = 'BK';
    $timestamp = date('ymd');
    $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
    return $prefix . $timestamp . $random;
} 

/**
 * Supabase RPC 呼び出し（POST /rest/v1/rpc/<fn>）
 * @param string $functionName
 * @param array $payload
 * @param string $apiKey
 * @return array [success=>bool, data|message]
 */
function supabaseRpcCall($functionName, $payload = [], $apiKey = null) {
    $apiKey = $apiKey ?: (defined('SUPABASE_ANON_KEY') ? SUPABASE_ANON_KEY : '');
    $baseUrl = defined('SUPABASE_URL') ? SUPABASE_URL : '';
    if (!$baseUrl || !$apiKey) {
        return ['success' => false, 'message' => 'Supabase設定が不足しています'];
    }

    $url = rtrim($baseUrl, '/') . '/rest/v1/rpc/' . $functionName;
    $schema = (defined('SUPABASE_SCHEMA') && SUPABASE_SCHEMA) ? SUPABASE_SCHEMA : 'cotoka';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
        // スキーマ指定（RPC呼び出しでもプロファイルを明示）
        'Accept-Profile: ' . $schema,
        'Content-Profile: ' . $schema,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return ['success' => false, 'message' => 'CURLエラー: ' . $errno];
    }

    $data = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $data];
    }
    return ['success' => false, 'message' => 'HTTP ' . $httpCode . ' ' . $response];
}

/**
 * 管理用: グローバルカウントをSupabaseから取得
 */
function fetchGlobalCountsFromSupabase() {
    // 集計はポリシーで制限されがちなので、サービスロールキーがあれば優先使用
    $apiKey = defined('SUPABASE_SERVICE_ROLE_KEY') && SUPABASE_SERVICE_ROLE_KEY !== '' ? SUPABASE_SERVICE_ROLE_KEY : null;
    $res = supabaseRpcCall('global_counts', [], $apiKey);
    if (!$res['success']) return null;
    // global_counts は1行のtable形式。RESTは配列で返る想定
    $rows = is_array($res['data']) ? $res['data'] : [];
    return $rows[0] ?? null;
}

/**
 * 管理用: テナント一覧+集計
 */
function fetchTenantsListAdminFromSupabase() {
    $apiKey = defined('SUPABASE_SERVICE_ROLE_KEY') && SUPABASE_SERVICE_ROLE_KEY !== '' ? SUPABASE_SERVICE_ROLE_KEY : null;
    $res = supabaseRpcCall('tenants_list_admin', [], $apiKey);
    if (!$res['success']) return [];
    return is_array($res['data']) ? $res['data'] : [];
}

/**
 * Supabase UIDからローカルユーザー情報を取得
 * 
 * @param string $supabase_uid Supabase UID
 * @param Database $db データベースインスタンス
 * @return array|false ユーザー情報またはfalse
 */
function getUserBySupabaseUid($supabase_uid, $db = null) {
    if ($db === null) {
        $db = new Database();
    }
    
    try {
        // PostgREST埋め込みを使用してroles/tenantsを取得（cotokaスキーマ）
        $select = 'id,user_id,email,name,role_id,tenant_id,status,created_at,updated_at,last_login,roles(role_name),tenants(tenant_name)';
        $user = $db->fetchOne('users', [
            'supabase_uid' => $supabase_uid,
            'status' => 'active',
        ], $select);

        if ($user) {
            $roleName = isset($user['roles']['role_name']) ? $user['roles']['role_name'] : 'staff';
            $tenantName = isset($user['tenants']['tenant_name']) ? $user['tenants']['tenant_name'] : '';

            $userData = [
                'id' => $user['id'] ?? null,
                'user_id' => $user['user_id'] ?? null,
                'email' => $user['email'] ?? '',
                'name' => $user['name'] ?? '',
                'role' => $roleName,
                // 日本語名のフォールバック
                'role_name' => $roleName === 'staff' ? 'スタッフ' : $roleName,
                'tenant_id' => $user['tenant_id'] ?? null,
                'tenant_name' => $tenantName,
                'status' => $user['status'] ?? null,
                'created_at' => $user['created_at'] ?? null,
                'updated_at' => $user['updated_at'] ?? null,
                'last_login' => $user['last_login'] ?? null,
            ];
            return $userData;
        }

        return false;
    } catch (Exception $e) {
        error_log('getUserBySupabaseUid error: ' . $e->getMessage());
        return false;
    }
}

/**
 * メールアドレスからユーザー情報を取得
 * 
 * @param string $email メールアドレス
 * @return array|false ユーザー情報またはfalse
 */
function getUserByEmail($email) {
    $db = new Database();
    try {
        $select = 'id,user_id,email,name,role_id,tenant_id,status,created_at,updated_at,last_login,roles(role_name),tenants(tenant_name)';
        $user = $db->fetchOne('users', [
            'email' => $email,
            'status' => 'active',
        ], $select);

        if ($user) {
            $roleName = isset($user['roles']['role_name']) ? $user['roles']['role_name'] : 'staff';
            $tenantName = isset($user['tenants']['tenant_name']) ? $user['tenants']['tenant_name'] : '';
            $normalized = [
                'id' => $user['id'] ?? null,
                'user_id' => $user['user_id'] ?? null,
                'email' => $user['email'] ?? '',
                'name' => $user['name'] ?? '',
                'role' => $roleName,
                'role_name' => $roleName === 'staff' ? 'スタッフ' : $roleName,
                'tenant_id' => $user['tenant_id'] ?? null,
                'tenant_name' => $tenantName,
                'status' => $user['status'] ?? null,
                'created_at' => $user['created_at'] ?? null,
                'updated_at' => $user['updated_at'] ?? null,
                'last_login' => $user['last_login'] ?? null,
            ];
            return ['success' => true, 'data' => [$normalized]];
        } else {
            return ['success' => false, 'message' => 'ユーザーが見つかりません'];
        }
    } catch (Exception $e) {
        error_log('getUserByEmail error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'エラーが発生しました'];
    }
}

/**
 * ユーザーのアクセス可能なサロンを取得
 * 
 * @param int $user_id ユーザーID
 * @return array サロンリスト
 */
function getUserAccessibleSalons($user_id) {
    $db = new Database();

    // ユーザーの役割を取得（rolesを埋め込み）
    $user = $db->fetchOne('users', ['id' => $user_id], 'id,tenant_id,roles(role_name)');
    $roleName = $user && isset($user['roles']['role_name']) ? $user['roles']['role_name'] : null;

    if ($roleName === 'admin') {
        // 管理者は全サロンにアクセス可（tenantsを埋め込み）
        $rows = $db->fetchAll('salons', ['status' => 'active'], 'salon_id,salon_name,salon_address,tenants(tenant_name)');
        $salons = [];
        foreach ($rows as $row) {
            $salons[] = [
                'salon_id' => $row['salon_id'] ?? null,
                'salon_name' => $row['salon_name'] ?? '',
                'salon_address' => $row['salon_address'] ?? '',
                'tenant_name' => isset($row['tenants']['tenant_name']) ? $row['tenants']['tenant_name'] : '',
            ];
        }
    } else {
        // user_salonsからアクセス可能なサロンを取得（salonsとその先のtenantsを埋め込み）
        // salons.status=active でネスト先のフィルタを付与
        $rows = $db->fetchAll(
            'user_salons',
            [
                'user_id' => $user_id,
                'salons.status' => 'active',
            ],
            'role,salons(salon_id,salon_name,salon_address,tenants(tenant_name))'
        );

        $salons = [];
        foreach ($rows as $row) {
            $salon = $row['salons'] ?? [];
            $salons[] = [
                'salon_id' => $salon['salon_id'] ?? null,
                'salon_name' => $salon['salon_name'] ?? '',
                'salon_address' => $salon['salon_address'] ?? '',
                'tenant_name' => isset($salon['tenants']['tenant_name']) ? $salon['tenants']['tenant_name'] : '',
                'user_role' => $row['role'] ?? null,
            ];
        }
    }

    return ['success' => true, 'data' => $salons];
}