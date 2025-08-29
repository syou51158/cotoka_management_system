<?php
// Supabase認証対応マイグレーション実行スクリプト
require_once 'config/config.php';
require_once 'classes/Database.php';

echo "Supabase認証対応マイグレーションを実行します...\n\n";

$db = new Database();

try {
    // 1. usersテーブルにsupabase_uidカラムを追加
    echo "1. usersテーブルにsupabase_uidカラムを追加...\n";
    $db->execute("ALTER TABLE users ADD COLUMN IF NOT EXISTS supabase_uid VARCHAR(255) UNIQUE NULL");
    $db->execute("ALTER TABLE users ADD COLUMN IF NOT EXISTS created_by_supabase BOOLEAN DEFAULT FALSE");
    echo "   ✓ カラム追加完了\n";
    
    // 2. インデックス作成
    echo "2. インデックス作成...\n";
    $db->execute("CREATE INDEX IF NOT EXISTS idx_users_supabase_uid ON users(supabase_uid)");
    echo "   ✓ インデックス作成完了\n";
    
    // 3. 既存ユーザーの確認と更新
    echo "3. 既存ユーザーの確認...\n";
    $admin = $db->fetchOne("SELECT id, email, supabase_uid FROM users WHERE email = 'info@cotoka.jp'");
    if ($admin) {
        if (empty($admin['supabase_uid'])) {
            $db->execute("UPDATE users SET supabase_uid = 'admin-supabase-uid-placeholder', created_by_supabase = TRUE WHERE email = 'info@cotoka.jp'");
            echo "   ✓ info@cotoka.jpにSupabase UIDを設定しました\n";
        } else {
            echo "   ✓ info@cotoka.jpは既にSupabase UIDが設定されています\n";
        }
    } else {
        echo "   ⚠ info@cotoka.jpユーザーが見つかりません\n";
    }
    
    // 4. 権限チェック用ビュー作成
    echo "4. 権限チェック用ビュー作成...\n";
    $view_sql = "CREATE OR REPLACE VIEW user_auth_view AS
                  SELECT u.id, u.user_id, u.email, u.name, u.supabase_uid, u.tenant_id, 
                         t.tenant_name, u.role_id, r.role_name, r.description as role_description, 
                         u.status, u.created_at, u.updated_at, u.last_login
                  FROM users u
                  LEFT JOIN tenants t ON u.tenant_id = t.tenant_id
                  LEFT JOIN roles r ON u.role_id = r.id
                  WHERE u.status = 'active'";
    $db->execute($view_sql);
    echo "   ✓ ビュー作成完了\n";
    
    echo "\n🎉 マイグレーションが正常に完了しました！\n\n";
    
    // 確認表示
    $count = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE supabase_uid IS NOT NULL");
    echo "Supabase UIDが設定されたユーザー数: {$count['count']}\n";
    
    $columns = $db->fetchAll("SHOW COLUMNS FROM users LIKE 'supabase_uid'");
    if (!empty($columns)) {
        echo "supabase_uidカラムが正常に追加されました。\n";
    } else {
        echo "supabase_uidカラムが見つかりません。\n";
    }
    
} catch (Exception $e) {
    echo "❌ エラーが発生しました: " . $e->getMessage() . "\n";
    echo "エラーの詳細: " . $e->getTraceAsString() . "\n";
}

echo "\nマイグレーション処理を終了します。\n";
?>