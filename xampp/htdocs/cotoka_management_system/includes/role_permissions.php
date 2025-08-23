<?php
/**
 * 権限定義ファイル
 * 
 * システム内の各ロールと権限の定義
 */

// システムのロール定義
// スーパー管理者の定義を削除
define('ROLE_TENANT_ADMIN', 'tenant_admin');     // テナント管理者（特定テナントの管理者）
define('ROLE_MANAGER', 'manager');               // マネージャー（サロン管理者）
define('ROLE_STAFF', 'staff');                   // スタッフ（一般ユーザー）

/**
 * 各ロールに対するメニュー表示設定
 * key: メニュー項目のID
 * roles: そのメニューを表示するロールの配列
 * admin_only: 管理者のみ(true)、全ユーザー(false)
 */
$MENU_PERMISSIONS = [
    'dashboard' => [
        'roles' => [ROLE_TENANT_ADMIN, ROLE_MANAGER, ROLE_STAFF, 'admin'],
        'admin_only' => false
    ],
    'appointments' => [
        'roles' => [ROLE_TENANT_ADMIN, ROLE_MANAGER, ROLE_STAFF, 'admin'],
        'admin_only' => false
    ],
    'customers' => [
        'roles' => [ROLE_TENANT_ADMIN, ROLE_MANAGER, ROLE_STAFF, 'admin'],
        'admin_only' => false
    ],
    'services' => [
        'roles' => [ROLE_TENANT_ADMIN, ROLE_MANAGER, ROLE_STAFF, 'admin'],
        'admin_only' => false
    ],
    'staff' => [
        'roles' => [ROLE_TENANT_ADMIN, ROLE_MANAGER, 'admin'],
        'admin_only' => true
    ],
    'sales' => [
        'roles' => [ROLE_TENANT_ADMIN, ROLE_MANAGER, 'admin'],
        'admin_only' => true
    ],
    'reports' => [
        'roles' => [ROLE_TENANT_ADMIN, ROLE_MANAGER, 'admin'],
        'admin_only' => true
    ],
    'settings' => [
        'roles' => [ROLE_TENANT_ADMIN, ROLE_MANAGER, 'admin'],
        'admin_only' => true
    ],
    'system_settings' => [
        'roles' => [ROLE_TENANT_ADMIN, 'admin'],
        'admin_only' => true
    ],
    'tenant_management' => [
        'roles' => ['admin'],
        'admin_only' => true
    ],
    'user_management' => [
        'roles' => [ROLE_TENANT_ADMIN, 'admin'],
        'admin_only' => true
    ],
    // スーパー管理者用管理画面を削除
    'file_management' => [
        'roles' => [ROLE_TENANT_ADMIN, ROLE_MANAGER, 'admin'],
        'admin_only' => true
    ]
];

/**
 * 各ロールの権限設定
 * 
 * 権限の説明：
 * - view: 閲覧権限
 * - create: 作成権限
 * - edit: 編集権限
 * - delete: 削除権限
 * - manage: すべての権限（view, create, edit, delete）
 */
$ROLE_PERMISSIONS = [
    // テナント管理者の権限
    ROLE_TENANT_ADMIN => [
        'users' => ['manage'],
        'salons' => ['manage'],
        'services' => ['manage'],
        'appointments' => ['manage'],
        'customers' => ['manage'],
        'staff' => ['manage'],
        'sales' => ['manage'],
        'reports' => ['manage'],
        'settings' => ['manage'],
        'files' => ['manage']
    ],
    
    // マネージャーの権限
    ROLE_MANAGER => [
        'services' => ['manage'],
        'appointments' => ['manage'],
        'customers' => ['manage'],
        'staff' => ['view', 'edit'],
        'sales' => ['view'],
        'reports' => ['view'],
        'settings' => ['view', 'edit'],
        'files' => ['view', 'create']
    ],
    
    // スタッフの権限
    ROLE_STAFF => [
        'appointments' => ['view', 'create', 'edit'],
        'customers' => ['view', 'create', 'edit'],
        'services' => ['view']
    ]
];

/**
 * ユーザーが特定の機能にアクセスできるかチェックする関数
 * 
 * @param string $feature 機能名
 * @param string $permission 必要な権限（view, create, edit, delete, manage）
 * @return bool アクセス可能かどうか
 */
function userHasPermission($feature, $permission = 'view') {
    global $ROLE_PERMISSIONS;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $userRole = getUserRole();
    
    // ロール権限設定をチェック
    if (isset($ROLE_PERMISSIONS[$userRole][$feature])) {
        $permissions = $ROLE_PERMISSIONS[$userRole][$feature];
        return in_array($permission, $permissions) || in_array('manage', $permissions);
    }
    
    return false;
}

/**
 * メニュー項目を表示すべきかどうかをチェックする関数
 * 
 * @param string $menuId メニュー項目のID
 * @return bool 表示すべきかどうか
 */
function shouldShowMenuItem($menuId) {
    global $MENU_PERMISSIONS;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $userRole = getUserRole();
    
    // 管理者とマネージャーは全てのメニューを表示
    if ($userRole === ROLE_TENANT_ADMIN || $userRole === ROLE_MANAGER || $userRole === 'admin') {
        return true;
    }
    
    // メニュー定義がなければfalse
    if (!isset($MENU_PERMISSIONS[$menuId])) {
        return false;
    }
    
    // メニューを表示するロールに含まれているかチェック
    if (in_array($userRole, $MENU_PERMISSIONS[$menuId]['roles'])) {
        return true;
    }
    
    return false;
}
?>
