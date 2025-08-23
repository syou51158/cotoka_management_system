CREATE TABLE IF NOT EXISTS sales (
    sale_id INT AUTO_INCREMENT PRIMARY KEY,
    salon_id INT NOT NULL,
    tenant_id INT NOT NULL,
    customer_id INT,
    staff_id INT,
    service_id INT,
    appointment_id INT,
    amount DECIMAL(10,2) NOT NULL,
    sale_date DATE NOT NULL,
    payment_method ENUM('cash', 'credit_card', 'bank_transfer', 'other') NOT NULL DEFAULT 'cash',
    memo TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- 売上サンプルデータの挿入
-- 現在の月から過去6ヶ月分のデータを作成

-- 現在の月のデータ
INSERT INTO sales (salon_id, tenant_id, customer_id, staff_id, service_id, appointment_id, amount, sale_date, payment_method, memo)
VALUES 
(1, 1, 1, 1, 1, 1, 6500.00, CURRENT_DATE(), 'credit_card', 'カット＆カラー'),
(1, 1, 2, 2, 2, 2, 4500.00, DATE_SUB(CURRENT_DATE(), INTERVAL 2 DAY), 'cash', 'カット'),
(1, 1, 3, 1, 3, 3, 8000.00, DATE_SUB(CURRENT_DATE(), INTERVAL 5 DAY), 'credit_card', 'パーマ'),
(2, 1, 4, 3, 4, 4, 9500.00, DATE_SUB(CURRENT_DATE(), INTERVAL 3 DAY), 'cash', 'ヘッドスパ＆トリートメント'),
(2, 1, 5, 4, 5, 5, 12000.00, DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY), 'credit_card', 'ヘアカラー＆トリートメント');

-- 1ヶ月前のデータ
INSERT INTO sales (salon_id, tenant_id, customer_id, staff_id, service_id, appointment_id, amount, sale_date, payment_method, memo)
VALUES 
(1, 1, 1, 1, 1, NULL, 6500.00, DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH), 'credit_card', 'カット＆カラー'),
(1, 1, 2, 2, 2, NULL, 4500.00, DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH), 'cash', 'カット'),
(1, 1, 3, 1, 3, NULL, 8000.00, DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH), 'credit_card', 'パーマ'),
(2, 1, 4, 3, 4, NULL, 9500.00, DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH), 'cash', 'ヘッドスパ＆トリートメント'),
(2, 1, 5, 4, 5, NULL, 12000.00, DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH), 'credit_card', 'ヘアカラー＆トリートメント');

-- 2ヶ月前のデータ
INSERT INTO sales (salon_id, tenant_id, customer_id, staff_id, service_id, appointment_id, amount, sale_date, payment_method, memo)
VALUES 
(1, 1, 1, 1, 1, NULL, 6200.00, DATE_SUB(CURRENT_DATE(), INTERVAL 2 MONTH), 'credit_card', 'カット＆カラー'),
(1, 1, 2, 2, 2, NULL, 4200.00, DATE_SUB(CURRENT_DATE(), INTERVAL 2 MONTH), 'cash', 'カット'),
(1, 1, 3, 1, 3, NULL, 7800.00, DATE_SUB(CURRENT_DATE(), INTERVAL 2 MONTH), 'credit_card', 'パーマ'),
(2, 1, 4, 3, 4, NULL, 9200.00, DATE_SUB(CURRENT_DATE(), INTERVAL 2 MONTH), 'cash', 'ヘッドスパ＆トリートメント'),
(2, 1, 5, 4, 5, NULL, 11500.00, DATE_SUB(CURRENT_DATE(), INTERVAL 2 MONTH), 'credit_card', 'ヘアカラー＆トリートメント');

-- 3ヶ月前のデータ
INSERT INTO sales (salon_id, tenant_id, customer_id, staff_id, service_id, appointment_id, amount, sale_date, payment_method, memo)
VALUES 
(1, 1, 1, 1, 1, NULL, 6300.00, DATE_SUB(CURRENT_DATE(), INTERVAL 3 MONTH), 'credit_card', 'カット＆カラー'),
(1, 1, 2, 2, 2, NULL, 4300.00, DATE_SUB(CURRENT_DATE(), INTERVAL 3 MONTH), 'cash', 'カット'),
(1, 1, 3, 1, 3, NULL, 7900.00, DATE_SUB(CURRENT_DATE(), INTERVAL 3 MONTH), 'credit_card', 'パーマ'),
(2, 1, 4, 3, 4, NULL, 9300.00, DATE_SUB(CURRENT_DATE(), INTERVAL 3 MONTH), 'cash', 'ヘッドスパ＆トリートメント'),
(2, 1, 5, 4, 5, NULL, 11700.00, DATE_SUB(CURRENT_DATE(), INTERVAL 3 MONTH), 'credit_card', 'ヘアカラー＆トリートメント');

-- 4ヶ月前のデータ
INSERT INTO sales (salon_id, tenant_id, customer_id, staff_id, service_id, appointment_id, amount, sale_date, payment_method, memo)
VALUES 
(1, 1, 1, 1, 1, NULL, 6100.00, DATE_SUB(CURRENT_DATE(), INTERVAL 4 MONTH), 'credit_card', 'カット＆カラー'),
(1, 1, 2, 2, 2, NULL, 4100.00, DATE_SUB(CURRENT_DATE(), INTERVAL 4 MONTH), 'cash', 'カット'),
(1, 1, 3, 1, 3, NULL, 7700.00, DATE_SUB(CURRENT_DATE(), INTERVAL 4 MONTH), 'credit_card', 'パーマ'),
(2, 1, 4, 3, 4, NULL, 9100.00, DATE_SUB(CURRENT_DATE(), INTERVAL 4 MONTH), 'cash', 'ヘッドスパ＆トリートメント'),
(2, 1, 5, 4, 5, NULL, 11300.00, DATE_SUB(CURRENT_DATE(), INTERVAL 4 MONTH), 'credit_card', 'ヘアカラー＆トリートメント');

-- 5ヶ月前のデータ
INSERT INTO sales (salon_id, tenant_id, customer_id, staff_id, service_id, appointment_id, amount, sale_date, payment_method, memo)
VALUES 
(1, 1, 1, 1, 1, NULL, 6400.00, DATE_SUB(CURRENT_DATE(), INTERVAL 5 MONTH), 'credit_card', 'カット＆カラー'),
(1, 1, 2, 2, 2, NULL, 4400.00, DATE_SUB(CURRENT_DATE(), INTERVAL 5 MONTH), 'cash', 'カット'),
(1, 1, 3, 1, 3, NULL, 8100.00, DATE_SUB(CURRENT_DATE(), INTERVAL 5 MONTH), 'credit_card', 'パーマ'),
(2, 1, 4, 3, 4, NULL, 9400.00, DATE_SUB(CURRENT_DATE(), INTERVAL 5 MONTH), 'cash', 'ヘッドスパ＆トリートメント'),
(2, 1, 5, 4, 5, NULL, 11900.00, DATE_SUB(CURRENT_DATE(), INTERVAL 5 MONTH), 'credit_card', 'ヘアカラー＆トリートメント');

-- 6ヶ月前のデータ
INSERT INTO sales (salon_id, tenant_id, customer_id, staff_id, service_id, appointment_id, amount, sale_date, payment_method, memo)
VALUES 
(1, 1, 1, 1, 1, NULL, 6000.00, DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH), 'credit_card', 'カット＆カラー'),
(1, 1, 2, 2, 2, NULL, 4000.00, DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH), 'cash', 'カット'),
(1, 1, 3, 1, 3, NULL, 7500.00, DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH), 'credit_card', 'パーマ'),
(2, 1, 4, 3, 4, NULL, 9000.00, DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH), 'cash', 'ヘッドスパ＆トリートメント'),
(2, 1, 5, 4, 5, NULL, 11000.00, DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH), 'credit_card', 'ヘアカラー＆トリートメント'); 