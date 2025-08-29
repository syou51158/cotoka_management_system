-- PostgreSQL/Supabase対応のusersテーブル作成
CREATE TABLE IF NOT EXISTS cotoka.users (
    id SERIAL PRIMARY KEY,
    user_id VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(20) CHECK (role IN ('admin', 'manager', 'staff')) NOT NULL DEFAULT 'staff',
    status VARCHAR(20) CHECK (status IN ('active', 'inactive')) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- updated_at自動更新のためのトリガー関数
CREATE OR REPLACE FUNCTION cotoka.update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- updated_atトリガーの作成
CREATE TRIGGER update_users_updated_at
    BEFORE UPDATE ON cotoka.users
    FOR EACH ROW
    EXECUTE FUNCTION cotoka.update_updated_at_column();

-- テストデータの挿入
INSERT INTO cotoka.users (user_id, email, password, name, role) VALUES
('admin001', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '管理者', 'admin'),
('staff001', 'staff@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'スタッフ1', 'staff');

-- パスワードは全て "password" （ハッシュ済み）