<?php
// マイグレーション実行スクリプト
require_once '../config/config.php';
require_once '../classes/Database.php';

echo "Supabase認証対応マイグレーションを実行します...\n";

$db = new Database();

try {
    // トランザクション開始
    $db->beginTransaction();
    
    // 1. usersテーブルにsupabase_uidカラムを追加
    echo "1. usersテーブルにsupabase_uidカラムを追加...\n";
    $sql1 = "ALTER TABLE users 
              ADD COLUMN IF NOT EXISTS supabase_uid VARCHAR(255) UNIQUE NULL COMMENT 'Supabase Auth UID',
              ADD COLUMN IF NOT EXISTS created_by_supabase BOOLEAN DEFAULT FALSE COMMENT 'Supabase経由で作成されたか'";
    $db->execute($sql1);
    
    // 2. Supabase UID用のインデックスを作成
    echo "2. Supabase UID用のインデックスを作成...\n";
    $sql2 = "CREATE INDEX IF NOT EXISTS idx_users_supabase_uid ON users(supabase_uid)";
    $db->execute($sql2);
    
    // 3. 既存の管理者ユーザーを確認
    echo "3. 既存の管理者ユーザーを確認...\n";
    $admin_user = $db->fetchOne("SELECT * FROM users WHERE email = 'info@cotoka.jp'");
    if ($admin_user) {
        echo "   info@cotoka.jpユーザーが見つかりました。ID: {$admin_user['id']}\n";
        
        // 既存のsupabase_uidを確認
        if (empty($admin_user['supabase_uid'])) {
            $sql3 = "UPDATE users 
                     SET supabase_uid = 'admin-supabase-uid-placeholder', 
                         created_by_supabase = TRUE 
                     WHERE email = 'info@cotoka.jp'";
            $db->execute($sql3);
            echo "   Supabase UIDを設定しました。\n";
        } else {
            echo "   Supabase UIDは既に設定されています。\n";
        }
    } else {
        echo "   info@cotoka.jpユーザーが見つかりませんでした。\n";
    }
    
    // 4. 権限チェック用のビューを作成
    echo "4. 権限チェック用のビューを作成...\n";
    $sql4 = "CREATE OR REPLACE VIEW user_auth_view AS
              SELECT 
                  u.id,
                  u.user_id,
                  u.email,
                  u.name,
                  u.supabase_uid,
                  u.tenant_id,
                  t.tenant_name,
                  u.role_id,
                  r.role_name,
                  r.description as role_description,
                  u.status,
                  u.created_at,
                  u.updated_at,
                  u.last_login,
                  CASE 
                      WHEN r.role_name = 'admin' THEN 1
                      WHEN r.role_name = 'tenant_admin' THEN 2
                      WHEN r.role_name = 'manager' THEN 3
                      ELSE 4
                  END as role_level
              FROM users u
              LEFT JOIN tenants t ON u.tenant_id = t.tenant_id
              LEFT JOIN roles r ON u.role_id = r.id
              WHERE u.status = 'active'";
    $db->execute($sql4);
    
    // トランザクションをコミット
    $db->commit();
    
    echo "\n✅ マイグレーションが正常に完了しました！\n";
    
    // 確認用クエリ
    echo "\n確認用クエリ実行...\n";
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE supabase_uid IS NOT NULL");
    echo "Supabase UIDが設定されたユーザー数: {$result['count']}\n";
    
    $result = $db->fetchAll("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema IN ('cotoka', 'public') AND table_name = 'users' ORDER BY ordinal_position");
    echo "usersテーブルの構造を確認しました。カラム数: " . count($result) . "\n";
    
} catch (Exception $e) {
    // エラー時はロールバック
    $db->rollBack();
    echo "❌ マイグレーションエラー: " . $e->getMessage() . "\n";
    echo "データベースをロールバックしました。\n";
}

echo "\nマイグレーション実行完了。\n";
?>