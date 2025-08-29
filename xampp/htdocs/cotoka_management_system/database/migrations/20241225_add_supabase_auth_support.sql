-- Supabase認証対応マイグレーション
-- 2024年12月25日

-- usersテーブルにSupabase UIDカラムを追加
ALTER TABLE cotoka.users 
ADD COLUMN IF NOT EXISTS supabase_uid VARCHAR(255) UNIQUE NULL,
ADD COLUMN IF NOT EXISTS created_by_supabase BOOLEAN DEFAULT FALSE;

-- Supabase UID用のインデックスを作成
CREATE INDEX IF NOT EXISTS idx_users_supabase_uid ON cotoka.users(supabase_uid);

-- コメント追加
COMMENT ON COLUMN cotoka.users.supabase_uid IS 'Supabase Auth UID';
COMMENT ON COLUMN cotoka.users.created_by_supabase IS 'Supabase経由で作成されたか';

-- 既存の管理者ユーザーをSupabase UIDと紐付ける
-- info@cotoka.jpのユーザーに対して、Supabase UIDを設定
UPDATE cotoka.users 
SET supabase_uid = 'admin-supabase-uid-placeholder', 
    created_by_supabase = TRUE 
WHERE email = 'info@cotoka.jp';

-- get_user_by_supabase_uid関数を作成（Supabase RPC用）
CREATE OR REPLACE FUNCTION cotoka.get_user_by_supabase_uid(p_supabase_uid VARCHAR(255))
RETURNS JSON
LANGUAGE plpgsql
AS $$
DECLARE
    result JSON;
BEGIN
    SELECT json_build_object(
        'id', u.id,
        'user_id', u.user_id,
        'email', u.email,
        'name', u.name,
        'role', COALESCE(r.role_name, 'staff'),
        'role_name', COALESCE(r.role_name, 'スタッフ'),
        'tenant_id', u.tenant_id,
        'tenant_name', COALESCE(t.tenant_name, ''),
        'status', u.status,
        'created_at', u.created_at,
        'updated_at', u.updated_at,
        'last_login', u.last_login
    ) INTO result
    FROM cotoka.users u
    LEFT JOIN cotoka.roles r ON u.role_id = r.id
    LEFT JOIN cotoka.tenants t ON u.tenant_id = t.tenant_id
    WHERE u.supabase_uid = p_supabase_uid AND u.status = 'active'
    LIMIT 1;
    
    IF result IS NULL THEN
        result := json_build_object('error', 'ユーザーが見つかりません');
    END IF;
    
    RETURN result;
END;
$$;

-- 新しいユーザーを作成する関数
CREATE OR REPLACE FUNCTION cotoka.create_user_with_supabase(
    p_supabase_uid VARCHAR(255),
    p_email VARCHAR(255),
    p_name VARCHAR(100),
    p_role VARCHAR(50),
    p_tenant_id INTEGER
)
RETURNS JSON
LANGUAGE plpgsql
AS $$
DECLARE
    new_user_id INTEGER;
    role_id INTEGER;
    user_id_str VARCHAR(50);
BEGIN
    -- ロールIDを取得
    SELECT id INTO role_id FROM cotoka.roles WHERE role_name = p_role LIMIT 1;
    IF role_id IS NULL THEN
        role_id := 2; -- デフォルトはstaff
    END IF;
    
    -- ユーザーIDを生成
    user_id_str := 'user_' || EXTRACT(EPOCH FROM NOW())::INTEGER;
    
    -- ユーザーを作成
    INSERT INTO cotoka.users (user_id, supabase_uid, email, name, role_id, tenant_id, password, status, created_by_supabase)
    VALUES (user_id_str, p_supabase_uid, p_email, p_name, role_id, p_tenant_id, 'supabase-auth-used', 'active', TRUE)
    RETURNING id INTO new_user_id;
    
    -- 作成したユーザーの情報を返す
    RETURN cotoka.get_user_by_supabase_uid(p_supabase_uid);
END;
$$;

-- 権限チェック用のビューを作成
CREATE OR REPLACE VIEW cotoka.user_auth_view AS
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
FROM cotoka.users u
LEFT JOIN cotoka.tenants t ON u.tenant_id = t.tenant_id
LEFT JOIN cotoka.roles r ON u.role_id = r.id
WHERE u.status = 'active';

-- テスト用クエリ
-- SELECT cotoka.get_user_by_supabase_uid('admin-supabase-uid-placeholder');