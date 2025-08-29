<?php
// シンプルなマイグレーション実行スクリプト
require_once '../config/config.php';
require_once '../classes/Database.php';

echo "Supabase認証対応マイグレーションを実行します...\n";

try {
    $db = new Database();
    
    // 1. usersテーブルにsupabase_uidカラムを追加
    echo "1. usersテーブルにsupabase_uidカラムを追加...\n";
    $db->execute("ALTER TABLE users ADD COLUMN IF NOT EXISTS supabase_uid VARCHAR(255) UNIQUE NULL");
    $db->execute("ALTER TABLE users ADD COLUMN IF NOT EXISTS created_by_supabase BOOLEAN DEFAULT FALSE");
    
    // 2. インデックス作成
    echo "2. インデックス作成...\n";
    $db->execute("CREATE INDEX IF NOT EXISTS idx_users_supabase_uid ON users(supabase_uid)");
    
    // 3. 既存ユーザーの確認
    echo "3. 既存ユーザーの確認...\n";
    $admin = $db->fetchOne("SELECT * FROM users WHERE email = 'info@cotoka.jp'");
    if ($admin) {
        $db->execute("UPDATE users SET supabase_uid = 'admin-supabase-uid-placeholder', created_by_supabase = TRUE WHERE email = 'info@cotoka.jp'");
        echo "   info@cotoka.jpにSupabase UIDを設定しました\n";
    }
    
    // 4. ビュー作成
    echo "4. 権限チェック用ビュー作成...\n";
    $db->execute("CREATE OR REPLACE VIEW user_auth_view AS 
                  SELECT u.id, u.user_id, u.email, u.name, u.supabase_uid, u.tenant_id, 
                         t.tenant_name, u.role_id, r.role_name, r.description as role_description, 
                         u.status, u.created_at, u.updated_at, u.last_login FROM users u 
                  LEFT JOIN tenants t ON u.tenant_id = t.tenant_id 
                  LEFT JOIN roles r ON u.role_id = r.id WHERE u.status = 'active'");
    
    echo "\n✅ マイグレーション完了！\n";
    
} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
}
?>