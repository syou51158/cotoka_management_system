<?php
// ユーザー削除処理
require_once 'includes/header.php';
require_once 'classes/User.php';

// CSRFトークン検証
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', '不正なリクエストです。ページを再読み込みして再度お試しください。');
    redirect('user-management.php');
    exit;
}

// 権限チェック
if (!userHasPermission('users', 'delete')) {
    setFlashMessage('error', 'ユーザーを削除する権限がありません');
    redirect('user-management.php');
    exit;
}

// パラメータ取得
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
if ($user_id <= 0) {
    setFlashMessage('error', '無効なユーザーIDです');
    redirect('user-management.php');
    exit;
}

// ユーザーオブジェクト
$userObj = new User($db);

// ユーザー情報取得
$user = $userObj->getById($user_id);
if (!$user) {
    setFlashMessage('error', 'ユーザーが見つかりません');
    redirect('user-management.php');
    exit;
}

// テナントIDの取得
$tenant_id = getCurrentTenantId();

// ユーザーがスーパー管理者でない場合、同じテナント内のユーザーのみ削除可能
if (!isSuperAdmin() && $user['tenant_id'] != $tenant_id) {
    setFlashMessage('error', 'このユーザーを削除する権限がありません');
    redirect('user-management.php');
    exit;
}

// 自分自身は削除できない
if ($user_id == getCurrentUserId()) {
    setFlashMessage('error', '自分自身を削除することはできません');
    redirect('user-management.php');
    exit;
}

// スーパー管理者はスーパー管理者のみ削除可能
if ($user['role'] == 'super_admin' && !isSuperAdmin()) {
    setFlashMessage('error', 'スーパー管理者を削除する権限がありません');
    redirect('user-management.php');
    exit;
}

// 削除処理
$result = $userObj->performDelete($user_id);

if ($result) {
    setFlashMessage('success', 'ユーザーを削除しました');
} else {
    setFlashMessage('error', 'ユーザー削除に失敗しました');
}

redirect('user-management.php');
exit;
