-- ==================================================
-- COTOKA管理システム - Supabaseデータベーススキーマ定義
-- ==================================================
-- 
-- システム構造ルール:
-- 1. テナント（会社）→サロン（店舗）→ユーザー（従業員）の階層構造
-- 2. テナントは複数のサロンを所有できる
-- 3. ユーザーは一つのテナントに所属し、複数のサロンにアクセス可能
--
-- 権限レベルルール:
-- 1. admin：システム全体管理者、テナントに所属せず全てのサロンにアクセス可能
-- 2. tenant_admin：テナント管理者、特定テナントに所属し自社サロンのみ管理可能
-- 3. manager：店舗管理者、特定テナントに所属し割り当てられたサロンのみ管理可能
-- 4. staff：店舗スタッフ、特定テナントに所属し割り当てられたサロンのみ閲覧/利用可能
--
-- アクセス制御ルール:
-- 1. テナント・サロンへのアクセスはuser_salonsテーブルで管理
-- 2. 管理機能へのアクセスはrolesテーブルの権限レベルで制御
-- 3. Remember Me機能によるセッション維持はremember_tokensテーブルで管理
-- 

-- ロールテーブル（権限管理）
CREATE TABLE IF NOT EXISTS roles (
  role_id SERIAL PRIMARY KEY,
  role_name VARCHAR(50) NOT NULL UNIQUE,
  description TEXT
);

-- テナントテーブル（経営主体・会社単位）
CREATE TABLE IF NOT EXISTS tenants (
  tenant_id SERIAL PRIMARY KEY,
  company_name VARCHAR(100) NOT NULL,
  owner_name VARCHAR(100),
  email VARCHAR(100) NOT NULL,
  phone VARCHAR(20),
  address TEXT,
  subscription_plan VARCHAR(20) NOT NULL DEFAULT 'free' CHECK (subscription_plan IN ('free','basic','premium','enterprise')),
  subscription_status VARCHAR(20) NOT NULL DEFAULT 'trial' CHECK (subscription_status IN ('active','trial','expired','cancelled')),
  trial_ends_at TIMESTAMP,
  subscription_ends_at TIMESTAMP,
  max_salons INTEGER NOT NULL DEFAULT 1,
  max_users INTEGER NOT NULL DEFAULT 3,
  max_storage_mb INTEGER NOT NULL DEFAULT 100,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ユーザーテーブル（システムにログインする人）
CREATE TABLE IF NOT EXISTS users (
  id SERIAL PRIMARY KEY,
  user_id VARCHAR(50) NOT NULL UNIQUE,
  tenant_id INTEGER REFERENCES tenants(tenant_id) ON DELETE SET NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(100) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active','inactive')),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login TIMESTAMP,
  role_id INTEGER REFERENCES roles(role_id)
);

-- サロンテーブル（実際の店舗単位）
CREATE TABLE IF NOT EXISTS salons (
  salon_id SERIAL PRIMARY KEY,
  tenant_id INTEGER NOT NULL REFERENCES tenants(tenant_id) ON DELETE CASCADE,
  name VARCHAR(100) NOT NULL,
  address TEXT,
  phone VARCHAR(20),
  email VARCHAR(100),
  business_hours TEXT,
  description TEXT,
  status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active','inactive')),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ユーザーとサロンの関連テーブル
CREATE TABLE IF NOT EXISTS user_salons (
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  salon_id INTEGER NOT NULL REFERENCES salons(salon_id) ON DELETE CASCADE,
  PRIMARY KEY (user_id, salon_id)
);

-- スタッフテーブル
CREATE TABLE IF NOT EXISTS staff (
  staff_id SERIAL PRIMARY KEY,
  tenant_id INTEGER NOT NULL REFERENCES tenants(tenant_id) ON DELETE CASCADE,
  user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  email VARCHAR(100),
  phone VARCHAR(20),
  position VARCHAR(50),
  bio TEXT,
  status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active','inactive','leave')),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 顧客テーブル
CREATE TABLE IF NOT EXISTS customers (
  customer_id SERIAL PRIMARY KEY,
  tenant_id INTEGER NOT NULL REFERENCES tenants(tenant_id) ON DELETE CASCADE,
  salon_id INTEGER NOT NULL REFERENCES salons(salon_id) ON DELETE CASCADE,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  email VARCHAR(100),
  phone VARCHAR(20),
  gender VARCHAR(10) CHECK (gender IN ('male','female','other')),
  date_of_birth DATE,
  address TEXT,
  notes TEXT,
  status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active','inactive')),
  last_visit_date DATE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- サービスカテゴリーテーブル
CREATE TABLE IF NOT EXISTS service_categories (
  category_id SERIAL PRIMARY KEY,
  tenant_id INTEGER NOT NULL REFERENCES tenants(tenant_id) ON DELETE CASCADE,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  display_order INTEGER DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- サービステーブル
CREATE TABLE IF NOT EXISTS services (
  service_id SERIAL PRIMARY KEY,
  tenant_id INTEGER NOT NULL REFERENCES tenants(tenant_id) ON DELETE CASCADE,
  category_id INTEGER REFERENCES service_categories(category_id) ON DELETE SET NULL,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  duration INTEGER NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  color VARCHAR(7) DEFAULT '#3788d8',
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 予約テーブル
CREATE TABLE IF NOT EXISTS appointments (
  appointment_id SERIAL PRIMARY KEY,
  salon_id INTEGER NOT NULL REFERENCES salons(salon_id) ON DELETE CASCADE,
  tenant_id INTEGER NOT NULL REFERENCES tenants(tenant_id) ON DELETE CASCADE,
  customer_id INTEGER NOT NULL REFERENCES customers(customer_id) ON DELETE CASCADE,
  staff_id INTEGER NOT NULL REFERENCES staff(staff_id) ON DELETE CASCADE,
  service_id INTEGER NOT NULL REFERENCES services(service_id) ON DELETE CASCADE,
  appointment_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  status VARCHAR(20) DEFAULT 'scheduled' CHECK (status IN ('scheduled','confirmed','completed','cancelled','no-show')),
  is_confirmed BOOLEAN DEFAULT FALSE,
  confirmation_sent_at TIMESTAMP,
  reminder_sent_at TIMESTAMP,
  notes TEXT,
  appointment_type VARCHAR(20) NOT NULL DEFAULT 'customer',
  task_description VARCHAR(255),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ログイン試行記録テーブル（セキュリティ対策）
CREATE TABLE IF NOT EXISTS login_attempts (
  id SERIAL PRIMARY KEY,
  identifier VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  attempt_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- RememberMeトークンテーブル
CREATE TABLE IF NOT EXISTS remember_tokens (
  id SERIAL PRIMARY KEY,
  user_id VARCHAR(50) NOT NULL,
  token VARCHAR(255) NOT NULL UNIQUE,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- システムログテーブル
CREATE TABLE IF NOT EXISTS system_logs (
  log_id SERIAL PRIMARY KEY,
  action VARCHAR(100) NOT NULL,
  target_type VARCHAR(50) NOT NULL,
  target_id INTEGER,
  user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  details TEXT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 初期データの挿入
INSERT INTO roles (role_name, description) VALUES 
('admin', 'システム全体管理者'),
('tenant_admin', 'テナント管理者'),
('manager', '店舗管理者'),
('staff', '店舗スタッフ')
ON CONFLICT (role_name) DO NOTHING;

-- インデックスの作成
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_tenant ON users(tenant_id);
CREATE INDEX IF NOT EXISTS idx_salons_tenant ON salons(tenant_id);
CREATE INDEX IF NOT EXISTS idx_staff_tenant ON staff(tenant_id);
CREATE INDEX IF NOT EXISTS idx_customers_tenant ON customers(tenant_id);
CREATE INDEX IF NOT EXISTS idx_customers_salon ON customers(salon_id);
CREATE INDEX IF NOT EXISTS idx_appointments_date ON appointments(appointment_date);
CREATE INDEX IF NOT EXISTS idx_appointments_staff_date ON appointments(staff_id, appointment_date);
CREATE INDEX IF NOT EXISTS idx_login_attempts_identifier ON login_attempts(identifier);
CREATE INDEX IF NOT EXISTS idx_remember_tokens_user ON remember_tokens(user_id);