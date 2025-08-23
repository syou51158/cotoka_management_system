<?php
/**
 * 認証ミドルウェア
 * 
 * ユーザーの認証状態と権限に基づいてアクセス制御を行う関数群
 */

// 既存の関数との重複を避けるためにチェック
if (!function_exists('requireLogin')) {
    /**
     * ユーザーがログインしているかどうかをチェック
     * ログインしていない場合はログインページにリダイレクト
     * 
     * @return bool ログインしている場合はtrue
     */
    function requireLogin()
    {
        if (!isLoggedIn()) {
            // リダイレクト後にログイン後の遷移先を指定
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            $_SESSION['flash_message'] = 'ログインが必要です。';
            $_SESSION['flash_type'] = 'warning';
            
            redirect('login.php');
            exit;
        }
        
        return true;
    }
}

// isAdmin() 関数はすでに functions.php に定義されているため、ここでは定義しません
// この関数は $_SESSION['role_name'] が 'admin' であるかどうかをチェックします

// 既存の関数との重複を避けるためにチェック
if (!function_exists('isTenantAdmin')) {
    /**
     * ユーザーがテナント管理者（tenant_admin）かどうかをチェック
     * 
     * @return bool テナント管理者の場合はtrue
     */
    function isTenantAdmin()
    {
        return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'tenant_admin';
    }
}

// 既存の関数との重複を避けるためにチェック
if (!function_exists('isManager')) {
    /**
     * ユーザーが店舗マネージャー（manager）かどうかをチェック
     * 
     * @return bool 店舗マネージャーの場合はtrue
     */
    function isManager()
    {
        return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'manager';
    }
}

// 既存の関数との重複を避けるためにチェック
if (!function_exists('isStaff')) {
    /**
     * ユーザーがスタッフ（staff）かどうかをチェック
     * 
     * @return bool スタッフの場合はtrue
     */
    function isStaff()
    {
        return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'staff';
    }
}

// 既存の関数との重複を避けるためにチェック
if (!function_exists('requireAdmin')) {
    /**
     * 全体管理者（admin）権限が必要なページへのアクセスを制限
     * 管理者でない場合はエラーページにリダイレクト
     * 
     * @return bool 管理者の場合はtrue
     */
    function requireAdmin()
    {
        requireLogin();
        
        if (!isAdmin()) {
            $_SESSION['flash_message'] = 'この操作を行う権限がありません。';
            $_SESSION['flash_type'] = 'danger';
            
            redirect('error.php?code=403');
            exit;
        }
        
        return true;
    }
}

// 既存の関数との重複を避けるためにチェック
if (!function_exists('requireTenantAdmin')) {
    /**
     * テナント管理者（tenant_admin）以上の権限が必要なページへのアクセスを制限
     * テナント管理者または全体管理者でない場合はエラーページにリダイレクト
     * 
     * @return bool テナント管理者以上の場合はtrue
     */
    function requireTenantAdmin()
    {
        requireLogin();
        
        if (!isAdmin() && !isTenantAdmin()) {
            $_SESSION['flash_message'] = 'この操作を行う権限がありません。';
            $_SESSION['flash_type'] = 'danger';
            
            redirect('error.php?code=403');
            exit;
        }
        
        return true;
    }
}

// 既存の関数との重複を避けるためにチェック
if (!function_exists('requireManager')) {
    /**
     * 店舗マネージャー（manager）以上の権限が必要なページへのアクセスを制限
     * マネージャー、テナント管理者、または全体管理者でない場合はエラーページにリダイレクト
     * 
     * @return bool マネージャー以上の場合はtrue
     */
    function requireManager()
    {
        requireLogin();
        
        if (!isAdmin() && !isTenantAdmin() && !isManager()) {
            $_SESSION['flash_message'] = 'この操作を行う権限がありません。';
            $_SESSION['flash_type'] = 'danger';
            
            redirect('error.php?code=403');
            exit;
        }
        
        return true;
    }
}

// 既存の関数との重複を避けるためにチェック
if (!function_exists('canAccessSalon')) {
    /**
     * ユーザーが特定のサロンにアクセスする権限を持っているかどうかをチェック
     * 
     * @param int $salonId チェックするサロンID
     * @return bool アクセス権限がある場合はtrue
     */
    function canAccessSalon($salonId)
    {
        if (!isLoggedIn()) {
            return false;
        }
        
        // 全体管理者は全てのサロンにアクセス可能
        if (isAdmin()) {
            return true;
        }
        
        // セッションに保存されたアクセス可能なサロンIDのリストをチェック
        if (isset($_SESSION['accessible_salons']) && is_array($_SESSION['accessible_salons'])) {
            return in_array((int)$salonId, $_SESSION['accessible_salons']);
        }
        
        return false;
    }
}

// 既存の関数との重複を避けるためにチェック
if (!function_exists('requireSalonAccess')) {
    /**
     * 特定のサロンへのアクセス権限を要求
     * 権限がない場合はエラーページにリダイレクト
     * 
     * @param int $salonId チェックするサロンID
     * @return bool アクセス権限がある場合はtrue
     */
    function requireSalonAccess($salonId)
    {
        requireLogin();
        
        if (!canAccessSalon($salonId)) {
            $_SESSION['flash_message'] = 'このサロンにアクセスする権限がありません。';
            $_SESSION['flash_type'] = 'danger';
            
            redirect('error.php?code=403');
            exit;
        }
        
        return true;
    }
}

// 既存の関数との重複を避けるためにチェック
if (!function_exists('isSameTenant')) {
    /**
     * ユーザーが同じテナントに所属しているか確認
     * 
     * @param int $tenantId チェックするテナントID
     * @return bool 同じテナントに所属している場合はtrue
     */
    function isSameTenant($tenantId)
    {
        if (!isLoggedIn()) {
            return false;
        }
        
        // 全体管理者は全てのテナントにアクセス可能
        if (isAdmin()) {
            return true;
        }
        
        return isset($_SESSION['tenant_id']) && $_SESSION['tenant_id'] == $tenantId;
    }
}

// 既存の関数との重複を避けるためにチェック
if (!function_exists('requireTenantAccess')) {
    /**
     * 同じテナントへのアクセス権限を要求
     * 権限がない場合はエラーページにリダイレクト
     * 
     * @param int $tenantId チェックするテナントID
     * @return bool アクセス権限がある場合はtrue
     */
    function requireTenantAccess($tenantId)
    {
        requireLogin();
        
        if (!isSameTenant($tenantId)) {
            $_SESSION['flash_message'] = 'このテナントにアクセスする権限がありません。';
            $_SESSION['flash_type'] = 'danger';
            
            redirect('error.php?code=403');
            exit;
        }
        
        return true;
    }
} 