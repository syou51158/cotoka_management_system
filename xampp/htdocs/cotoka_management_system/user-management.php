<?php
/**
 * ユーザー管理ページ
 * 
 * グローバル管理者（admin）専用のユーザー管理ページ
 */

// 設定ファイルと関数ファイルの読み込み
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';

// ログインしていない場合はログインページにリダイレクト
if (!isLoggedIn()) {
    redirect('login.php');
}

// グローバル管理者でない場合は通常のダッシュボードにリダイレクト
if (!isGlobalAdmin()) {
    redirect('dashboard.php');
}

// Supabase RPC 利用のためDB接続は不要（header互換のために後で $conn を設定）

// CSRFトークンを生成
$csrf_token = generateCSRFToken();

// アクション処理
$message = '';
$messageType = '';

// ユーザー追加処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    // CSRFトークンの検証
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $message = 'セキュリティトークンが無効です。もう一度お試しください。';
        $messageType = 'danger';
    } else {
        $userId = trim($_POST['user_id']);
        $email = trim($_POST['email']);
        $name = trim($_POST['name']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $tenantId = !empty($_POST['tenant_id']) ? $_POST['tenant_id'] : null;

        // バリデーション
        if (empty($userId) || empty($email) || empty($name) || empty($password)) {
            $message = 'すべての必須フィールドを入力してください。';
            $messageType = 'danger';
        } else {
            $res = supabaseRpcCall('users_add_admin', [
                'p_user_id' => $userId,
                'p_email' => $email,
                'p_name' => $name,
                'p_password' => $password,
                'p_role' => $role,
                'p_tenant_id' => $tenantId ? (int)$tenantId : null
            ]);
            if ($res['success']) {
                $message = 'ユーザーを追加しました。';
                $messageType = 'success';
            } else {
                $message = 'エラーが発生しました: ' . ($res['message'] ?? '');
                $messageType = 'danger';
            }
        }
    }
}

// ユーザー編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    // CSRFトークンの検証
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $message = 'セキュリティトークンが無効です。もう一度お試しください。';
        $messageType = 'danger';
    } else {
        $userId = $_POST['user_id'];
        $email = trim($_POST['email']);
        $name = trim($_POST['name']);
        $role = $_POST['role'];
        $tenantId = !empty($_POST['tenant_id']) ? $_POST['tenant_id'] : null;
        $status = $_POST['status'];

        // バリデーション
        if (empty($email) || empty($name)) {
            $message = 'すべての必須フィールドを入力してください。';
            $messageType = 'danger';
        } else {
            $res = supabaseRpcCall('users_update_admin', [
                'p_user_id' => $userId,
                'p_email' => $email,
                'p_name' => $name,
                'p_role' => $role,
                'p_tenant_id' => $tenantId ? (int)$tenantId : null,
                'p_status' => $status,
                'p_password' => ($_POST['password'] ?? '')
            ]);
            if ($res['success']) {
                $message = 'ユーザー情報を更新しました。';
                $messageType = 'success';
            } else {
                $message = 'エラーが発生しました: ' . ($res['message'] ?? '');
                $messageType = 'danger';
            }
        }
    }
}

// ユーザー削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    // CSRFトークンの検証
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $message = 'セキュリティトークンが無効です。もう一度お試しください。';
        $messageType = 'danger';
    } else {
        $userId = $_POST['user_id'];
        
        // 自分自身は削除できないように
        if ($userId === $_SESSION['user_id']) {
            $message = '現在ログインしているユーザーを削除することはできません。';
            $messageType = 'danger';
        } else {
            $res = supabaseRpcCall('users_delete_admin', ['p_user_id' => $userId]);
            if ($res['success']) {
                $message = 'ユーザーを削除しました。';
                $messageType = 'success';
            } else {
                $message = 'エラーが発生しました: ' . ($res['message'] ?? '');
                $messageType = 'danger';
            }
        }
    }
}

// ユーザー一覧を取得（Supabase RPC）
$users = [];
$apiKey = (defined('SUPABASE_SERVICE_ROLE_KEY') && SUPABASE_SERVICE_ROLE_KEY !== '') ? SUPABASE_SERVICE_ROLE_KEY : null;
$resUsers = supabaseRpcCall('users_list_admin', [], $apiKey);
if ($resUsers['success']) { $users = $resUsers['data']; }

// テナント一覧を取得（ドロップダウン用: Supabase）
$tenants = fetchTenantsListAdminFromSupabase();

// ページタイトルを設定
$pageTitle = "ユーザー管理";
$today = date('Y年m月d日');

// グローバル変数をheader.phpと共有（互換）
$conn = null;

// ヘッダーを読み込む
include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4"><i class="fas fa-users-cog me-2"></i>ユーザー管理</h1>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
            </div>
            <?php endif; ?>
            
            <!-- ユーザー追加ボタン -->
            <div class="text-end mb-4">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-user-plus me-1"></i> 新規ユーザー追加
                </button>
</div>

            <!-- ユーザー一覧テーブル -->
            <div class="card mb-4">
            <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>ユーザー一覧</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                        <table class="table table-hover table-striped" id="userTable">
                            <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                    <th>ユーザーID</th>
                                <th>名前</th>
                                <th>メールアドレス</th>
                                    <th>権限</th>
                                    <th>テナント</th>
                                <th>ステータス</th>
                                <th>最終ログイン</th>
                                    <th>作成日</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php
                                        $roleBadgeClass = 'bg-secondary';
                                        $roleLabel = $user['role'];
                                        
                                        if ($user['role'] === 'admin') {
                                            $roleBadgeClass = 'bg-danger';
                                            $roleLabel = '全体管理者';
                                        } elseif ($user['role'] === 'tenant_admin') {
                                            $roleBadgeClass = 'bg-primary';
                                            $roleLabel = 'テナント管理者';
                                        } elseif ($user['role'] === 'manager') {
                                            $roleBadgeClass = 'bg-success';
                                            $roleLabel = 'マネージャー';
                                        } elseif ($user['role'] === 'staff') {
                                            $roleBadgeClass = 'bg-info';
                                            $roleLabel = 'スタッフ';
                                        }
                                        ?>
                                        <span class="badge <?php echo $roleBadgeClass; ?>">
                                            <?php echo $roleLabel; ?>
                                        </span>
                                        </td>
                                        <td>
                                        <?php if ($user['tenant_id']): ?>
                                            <?php echo htmlspecialchars($user['tenant_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">なし</span>
                                        <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['status'] === 'active'): ?>
                                                <span class="badge bg-success">有効</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">無効</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                        <?php if ($user['last_login']): ?>
                                            <?php echo date('Y/m/d H:i', strtotime($user['last_login'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">未ログイン</span>
                                        <?php endif; ?>
                                        </td>
                                    <td><?php echo date('Y/m/d', strtotime($user['created_at'])); ?></td>
                                        <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-secondary edit-user-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editUserModal"
                                                data-user-id="<?php echo $user['user_id']; ?>"
                                                data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                                data-role="<?php echo $user['role']; ?>"
                                                data-tenant-id="<?php echo $user['tenant_id']; ?>"
                                                data-status="<?php echo $user['status']; ?>">
                                                    <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-user-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteUserModal"
                                                data-user-id="<?php echo $user['user_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($user['name']); ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ユーザー追加モーダル -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">新規ユーザー追加</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm" method="POST" action="user-management.php">
                    <input type="hidden" name="action" value="add_user">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="user_id" class="form-label">ユーザーID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="user_id" name="user_id" required>
                                <div class="form-text">ログイン時に使用するIDです（半角英数字）</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">メールアドレス <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">名前 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">パスワード <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">8文字以上の強力なパスワードを設定してください</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">権限 <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="admin">全体管理者</option>
                                    <option value="tenant_admin">テナント管理者</option>
                                    <option value="manager">マネージャー</option>
                                    <option value="staff">スタッフ</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tenant_id" class="form-label">所属テナント</label>
                                <select class="form-select" id="tenant_id" name="tenant_id">
                                    <option value="">なし（全体管理者用）</option>
                                    <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?php echo $tenant['tenant_id']; ?>"><?php echo htmlspecialchars($tenant['company_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">全体管理者の場合は「なし」を選択してください</div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="submit" form="addUserForm" class="btn btn-primary">ユーザーを追加</button>
            </div>
        </div>
    </div>
</div>

<!-- ユーザー編集モーダル -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">ユーザー編集</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm" method="POST" action="user-management.php">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_email" class="form-label">メールアドレス <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">名前 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_role" class="form-label">権限 <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_role" name="role" required>
                                    <option value="admin">全体管理者</option>
                                    <option value="tenant_admin">テナント管理者</option>
                                    <option value="manager">マネージャー</option>
                                    <option value="staff">スタッフ</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_tenant_id" class="form-label">所属テナント</label>
                                <select class="form-select" id="edit_tenant_id" name="tenant_id">
                                    <option value="">なし（全体管理者用）</option>
                                    <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?php echo $tenant['tenant_id']; ?>"><?php echo htmlspecialchars($tenant['company_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_password" class="form-label">パスワード</label>
                                <input type="password" class="form-control" id="edit_password" name="password">
                                <div class="form-text">変更する場合のみ入力してください</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">ステータス <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_status" name="status" required>
                                    <option value="active">有効</option>
                                    <option value="inactive">無効</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="submit" form="editUserForm" class="btn btn-primary">変更を保存</button>
            </div>
        </div>
    </div>
</div>

<!-- ユーザー削除確認モーダル -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUserModalLabel">ユーザー削除の確認</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>警告: この操作は取り消せません！</p>
                <p>ユーザー「<span id="delete_user_name"></span>」を削除しようとしています。</p>
                <p>このユーザーに関連するすべてのデータも削除されます。</p>
                <form id="deleteUserForm" method="POST" action="user-management.php">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                </form>
            </div>
            <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="submit" form="deleteUserForm" class="btn btn-danger">ユーザーを削除</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 権限に応じてテナント選択フィールドの有効/無効を切り替え
    const roleSelects = document.querySelectorAll('#role, #edit_role');
    
    roleSelects.forEach(select => {
        select.addEventListener('change', function() {
            const tenantSelect = this.id === 'role' ? 
                document.getElementById('tenant_id') : 
                document.getElementById('edit_tenant_id');
                
            if (this.value === 'admin') {
                tenantSelect.value = '';
                tenantSelect.disabled = true;
            } else {
                tenantSelect.disabled = false;
            }
        });
        
        // 初期状態の設定
        select.dispatchEvent(new Event('change'));
    });
    
    // ユーザー編集モーダルのデータ設定
    const editUserModal = document.getElementById('editUserModal');
    if (editUserModal) {
        editUserModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            document.getElementById('edit_user_id').value = button.getAttribute('data-user-id');
            document.getElementById('edit_email').value = button.getAttribute('data-email');
            document.getElementById('edit_name').value = button.getAttribute('data-name');
            document.getElementById('edit_role').value = button.getAttribute('data-role');
            document.getElementById('edit_tenant_id').value = button.getAttribute('data-tenant-id') || '';
            document.getElementById('edit_status').value = button.getAttribute('data-status');
            
            // 編集モーダルでも権限に応じたテナント選択の有効/無効を適用
            document.getElementById('edit_role').dispatchEvent(new Event('change'));
        });
    }
    
    // ユーザー削除モーダルのデータ設定
    const deleteUserModal = document.getElementById('deleteUserModal');
    if (deleteUserModal) {
        deleteUserModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            document.getElementById('delete_user_id').value = button.getAttribute('data-user-id');
            document.getElementById('delete_user_name').textContent = button.getAttribute('data-name');
        });
    }
    
    // DataTables初期化
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#userTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Japanese.json"
            },
            "order": [[0, "desc"]]
    });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
