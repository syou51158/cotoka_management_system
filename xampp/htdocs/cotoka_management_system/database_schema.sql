-- ==================================================
-- COTOKA管理システム - データベーススキーマ定義
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

-- 予約テーブル
CREATE TABLE IF NOT EXISTS `appointments` (
  `appointment_id` int(11) NOT NULL AUTO_INCREMENT,
  `salon_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('scheduled','confirmed','completed','cancelled','no-show') DEFAULT 'scheduled',
  `is_confirmed` tinyint(1) DEFAULT 0,
  `confirmation_sent_at` timestamp NULL DEFAULT NULL,
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `appointment_type` varchar(20) NOT NULL DEFAULT 'customer',
  `task_description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`appointment_id`),
  KEY `idx_appointment_date` (`appointment_date`),
  KEY `idx_appointment_status` (`status`),
  KEY `idx_appointment_staff_date` (`staff_id`,`appointment_date`),
  KEY `idx_appointment_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 予約サービス紐付けテーブル（多対多の関連を管理）
CREATE TABLE IF NOT EXISTS `appointment_services` (
  `appointment_service_id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`appointment_service_id`),
  KEY `idx_appointment_service_appt` (`appointment_id`),
  KEY `idx_appointment_service_service` (`service_id`),
  CONSTRAINT `appointment_services_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  CONSTRAINT `appointment_services_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 利用可能な時間枠テーブル
CREATE TABLE IF NOT EXISTS `available_slots` (
  `slot_id` int(11) NOT NULL AUTO_INCREMENT,
  `salon_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`slot_id`),
  UNIQUE KEY `idx_unique_slot` (`salon_id`,`staff_id`,`date`,`start_time`,`end_time`),
  KEY `idx_salon_date` (`salon_id`,`date`),
  KEY `idx_staff_date` (`staff_id`,`date`),
  CONSTRAINT `available_slots_ibfk_1` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`salon_id`) ON DELETE CASCADE,
  CONSTRAINT `available_slots_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 顧客フィードバックテーブル
CREATE TABLE IF NOT EXISTS `customer_feedback` (
  `feedback_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `salon_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `rating` tinyint(1) NOT NULL COMMENT '1-5の評価',
  `comments` text DEFAULT NULL,
  `feedback_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`feedback_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_salon` (`salon_id`),
  KEY `idx_appointment` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 顧客テーブル
CREATE TABLE IF NOT EXISTS `customers` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `salon_id` int(11) NOT NULL COMMENT '主要利用サロン',
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_visit_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`customer_id`),
  KEY `idx_customer_tenant` (`tenant_id`),
  KEY `idx_customer_salon` (`salon_id`),
  KEY `idx_customer_name` (`last_name`,`first_name`),
  KEY `idx_customer_email` (`email`),
  KEY `idx_customer_phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 在庫アイテムテーブル
CREATE TABLE IF NOT EXISTS `inventory_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `salon_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`item_id`),
  KEY `idx_inventory_tenant` (`tenant_id`),
  KEY `idx_inventory_salon` (`salon_id`),
  KEY `idx_inventory_category` (`category`),
  CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`salon_id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_items_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 在庫取引テーブル
CREATE TABLE IF NOT EXISTS `inventory_transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `salon_id` int(11) NOT NULL,
  `transaction_type` enum('purchase','sale','adjustment','transfer','return') NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp(),
  `reference_id` int(11) DEFAULT NULL COMMENT '関連取引ID',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`transaction_id`),
  KEY `idx_transaction_item` (`item_id`),
  KEY `idx_transaction_salon` (`salon_id`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_transaction_date` (`transaction_date`),
  CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ログイン試行記録テーブル（セキュリティ対策）
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL COMMENT 'ユーザーIDまたはメールアドレス',
  `ip_address` varchar(45) NOT NULL COMMENT 'IPアドレス',
  `attempt_time` datetime NOT NULL DEFAULT current_timestamp() COMMENT '試行日時',
  PRIMARY KEY (`id`),
  KEY `identifier` (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 支払いテーブル
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `salon_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `payment_method` enum('cash','credit_card','debit_card','bank_transfer','digital_wallet','gift_card','other') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `transaction_reference` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`payment_id`),
  KEY `idx_payment_sale` (`sale_id`),
  KEY `idx_payment_salon` (`salon_id`),
  KEY `idx_payment_tenant` (`tenant_id`),
  KEY `idx_payment_method` (`payment_method`),
  KEY `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- RememberMeトークンテーブル
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL COMMENT 'ユーザーID',
  `token` varchar(255) NOT NULL COMMENT 'トークン',
  `expires_at` datetime NOT NULL COMMENT '有効期限',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ロールテーブル（権限管理）
CREATE TABLE IF NOT EXISTS `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL COMMENT 'ロール名',
  `description` text DEFAULT NULL COMMENT '説明',
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 販売アイテムテーブル
CREATE TABLE IF NOT EXISTS `sale_items` (
  `sale_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `item_type` enum('service','product') NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`sale_item_id`),
  KEY `idx_sale_item_sale` (`sale_id`),
  KEY `idx_sale_item_type` (`item_type`,`item_id`),
  KEY `idx_sale_item_staff` (`staff_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 販売テーブル
CREATE TABLE IF NOT EXISTS `sales` (
  `sale_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `salon_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `sale_date` datetime NOT NULL DEFAULT current_timestamp(),
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','partial','paid','refunded') NOT NULL DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`sale_id`),
  KEY `idx_sale_salon` (`salon_id`),
  KEY `idx_sale_tenant` (`tenant_id`),
  KEY `idx_sale_customer` (`customer_id`),
  KEY `idx_sale_date` (`sale_date`),
  KEY `idx_sale_status` (`payment_status`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`salon_id`) ON DELETE CASCADE,
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- サロン営業時間テーブル
CREATE TABLE IF NOT EXISTS `salon_business_hours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `salon_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=日曜, 1=月曜, ..., 6=土曜',
  `is_open` tinyint(1) NOT NULL DEFAULT 1,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_salon_day` (`salon_id`,`day_of_week`),
  CONSTRAINT `salon_business_hours_ibfk_1` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`salon_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- サロンテーブル（実際の店舗単位）
CREATE TABLE IF NOT EXISTS `salons` (
  `salon_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL COMMENT '所属テナントID',
  `name` varchar(100) NOT NULL COMMENT 'サロン名',
  `address` text DEFAULT NULL COMMENT '住所',
  `phone` varchar(20) DEFAULT NULL COMMENT '電話番号',
  `email` varchar(100) DEFAULT NULL COMMENT 'メールアドレス',
  `business_hours` text DEFAULT NULL COMMENT '営業時間（JSON形式）',
  `description` text DEFAULT NULL COMMENT 'サロン説明',
  `status` enum('active','inactive') DEFAULT 'active' COMMENT '状態',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`salon_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `salons_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- サービスカテゴリーテーブル
CREATE TABLE IF NOT EXISTS `service_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`),
  KEY `idx_category_tenant` (`tenant_id`),
  CONSTRAINT `service_categories_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- サービステーブル
CREATE TABLE IF NOT EXISTS `services` (
  `service_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `duration` int(11) NOT NULL COMMENT '所要時間（分）',
  `price` decimal(10,2) NOT NULL,
  `color` varchar(7) DEFAULT '#3788d8',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`service_id`),
  KEY `idx_service_tenant` (`tenant_id`),
  KEY `idx_service_category` (`category_id`),
  CONSTRAINT `services_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `services_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- スタッフテーブル
CREATE TABLE IF NOT EXISTS `staff` (
  `staff_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `status` enum('active','inactive','leave') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`staff_id`),
  KEY `idx_staff_tenant` (`tenant_id`),
  KEY `idx_staff_user` (`user_id`),
  KEY `idx_staff_name` (`last_name`,`first_name`),
  CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `staff_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- スタッフスケジュールテーブル
CREATE TABLE IF NOT EXISTS `staff_schedules` (
  `schedule_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `salon_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_day_off` tinyint(1) DEFAULT 0,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`schedule_id`),
  KEY `idx_schedule_staff` (`staff_id`),
  KEY `idx_schedule_salon` (`salon_id`),
  KEY `idx_schedule_date` (`date`),
  CONSTRAINT `staff_schedules_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE CASCADE,
  CONSTRAINT `staff_schedules_ibfk_2` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`salon_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- スタッフシフトパターンテーブル
CREATE TABLE IF NOT EXISTS `staff_shift_patterns` (
  `pattern_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`pattern_id`),
  KEY `idx_pattern_tenant` (`tenant_id`),
  CONSTRAINT `staff_shift_patterns_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- スタッフシフトテーブル
CREATE TABLE IF NOT EXISTS `staff_shifts` (
  `shift_id` int(11) NOT NULL AUTO_INCREMENT,
  `pattern_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=日曜, 1=月曜, ..., 6=土曜',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`shift_id`),
  KEY `idx_shift_pattern` (`pattern_id`),
  CONSTRAINT `staff_shifts_ibfk_1` FOREIGN KEY (`pattern_id`) REFERENCES `staff_shift_patterns` (`pattern_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- スタッフ専門分野テーブル
CREATE TABLE IF NOT EXISTS `staff_specialties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_staff_service` (`staff_id`,`service_id`),
  KEY `idx_specialty_service` (`service_id`),
  CONSTRAINT `staff_specialties_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE CASCADE,
  CONSTRAINT `staff_specialties_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- サブスクリプション履歴テーブル
CREATE TABLE IF NOT EXISTS `subscription_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `plan` enum('free','basic','premium','enterprise') NOT NULL,
  `status` enum('active','trial','expired','cancelled') NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `idx_subscription_tenant` (`tenant_id`),
  KEY `idx_subscription_dates` (`start_date`,`end_date`),
  CONSTRAINT `subscription_history_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- システムログテーブル
CREATE TABLE IF NOT EXISTS `system_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(100) NOT NULL COMMENT '実行アクション',
  `target_type` varchar(50) NOT NULL COMMENT '対象タイプ',
  `target_id` int(11) DEFAULT NULL COMMENT '対象ID',
  `user_id` int(11) DEFAULT NULL COMMENT '実行ユーザーID',
  `details` text DEFAULT NULL COMMENT '詳細情報',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IPアドレス',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- テナント設定テーブル
CREATE TABLE IF NOT EXISTS `tenant_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `idx_tenant_key` (`tenant_id`,`setting_key`),
  CONSTRAINT `tenant_settings_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- テナントテーブル（経営主体・会社単位）
CREATE TABLE IF NOT EXISTS `tenants` (
  `tenant_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(100) NOT NULL COMMENT '会社名',
  `owner_name` varchar(100) DEFAULT NULL COMMENT 'オーナー名',
  `email` varchar(100) NOT NULL COMMENT '連絡先メール',
  `phone` varchar(20) DEFAULT NULL COMMENT '連絡先電話番号',
  `address` text DEFAULT NULL COMMENT '住所',
  `subscription_plan` enum('free','basic','premium','enterprise') NOT NULL DEFAULT 'free' COMMENT 'サブスクリプションプラン',
  `subscription_status` enum('active','trial','expired','cancelled') NOT NULL DEFAULT 'trial' COMMENT 'サブスクリプション状態',
  `trial_ends_at` datetime DEFAULT NULL COMMENT 'トライアル終了日',
  `subscription_ends_at` datetime DEFAULT NULL COMMENT 'サブスクリプション終了日',
  `max_salons` int(11) NOT NULL DEFAULT 1 COMMENT '最大サロン数',
  `max_users` int(11) NOT NULL DEFAULT 3 COMMENT '最大ユーザー数',
  `max_storage_mb` int(11) NOT NULL DEFAULT 100 COMMENT '最大ストレージ容量(MB)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 時間枠テーブル
CREATE TABLE IF NOT EXISTS `time_slots` (
  `slot_id` int(11) NOT NULL AUTO_INCREMENT,
  `salon_id` int(11) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`slot_id`),
  KEY `idx_slot_salon` (`salon_id`),
  CONSTRAINT `time_slots_ibfk_1` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`salon_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ユーザーとサロンの関連テーブル
CREATE TABLE IF NOT EXISTS `user_salons` (
  `user_id` int(11) NOT NULL COMMENT 'ユーザーID',
  `salon_id` int(11) NOT NULL COMMENT 'サロンID',
  PRIMARY KEY (`user_id`,`salon_id`),
  KEY `salon_id` (`salon_id`),
  CONSTRAINT `user_salons_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_salons_ibfk_2` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`salon_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ユーザーテーブル（システムにログインする人）
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL COMMENT 'ユーザーID（ログイン用）',
  `tenant_id` int(11) DEFAULT NULL COMMENT '所属テナントID（全体管理者の場合はNULL）',
  `email` varchar(255) NOT NULL COMMENT 'メールアドレス',
  `password` varchar(255) NOT NULL COMMENT 'パスワードハッシュ',
  `name` varchar(100) NOT NULL COMMENT '名前',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT '状態',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL COMMENT '最終ログイン日時',
  `role_id` int(11) DEFAULT NULL COMMENT 'ロールID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `tenant_id` (`tenant_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; 