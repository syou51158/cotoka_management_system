CREATE TABLE IF NOT EXISTS cotoka.sales (
    sale_id SERIAL PRIMARY KEY,
    salon_id INTEGER NOT NULL,
    tenant_id INTEGER NOT NULL,
    customer_id INTEGER,
    staff_id INTEGER,
    service_id INTEGER,
    appointment_id INTEGER,
    amount DECIMAL(10,2) NOT NULL,
    sale_date DATE NOT NULL,
    payment_method VARCHAR(20) CHECK (payment_method IN ('cash', 'credit_card', 'bank_transfer', 'other')) NOT NULL DEFAULT 'cash',
    memo TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (salon_id) REFERENCES cotoka.salons(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES cotoka.tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES cotoka.customers(id) ON DELETE SET NULL,
    FOREIGN KEY (staff_id) REFERENCES cotoka.staff(id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES cotoka.services(id) ON DELETE SET NULL,
    FOREIGN KEY (appointment_id) REFERENCES cotoka.appointments(id) ON DELETE SET NULL
);

-- updated_atカラム用のトリガー関数を作成
CREATE OR REPLACE FUNCTION cotoka.update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- updated_atカラム用のトリガーを作成
CREATE TRIGGER update_sales_updated_at BEFORE UPDATE ON cotoka.sales
    FOR EACH ROW EXECUTE FUNCTION cotoka.update_updated_at_column();

-- 売上サンプルデータの挿入
-- 現在の月から過去6ヶ月分のデータを作成

-- 現在の月のデータ
INSERT INTO cotoka.sales (salon_id, tenant_id, customer_id, staff_id, service_id, appointment_id, amount, sale_date, payment_method, memo)
VALUES 
(1, 1, 1, 1, 1, 1, 6500.00, CURRENT_DATE, 'credit_card', 'カット＆カラー'),
(1, 1, 2, 2, 2, 2, 4500.00, CURRENT_DATE - INTERVAL '2 days', 'cash', 'カット'),
(1, 1, 3, 1, 3, 3, 8000.00, CURRENT_DATE - INTERVAL '5 days', 'credit_card', 'パーマ'),
(2, 1, 4, 3, 4, 4, 9500.00, CURRENT_DATE - INTERVAL '3 days', 'cash', 'ヘッドスパ＆トリートメント'),
(2, 1, 5, 4, 5, 5, 12000.00, CURRENT_DATE - INTERVAL '1 day', 'credit_card', 'ヘアカラー＆トリートメント');

-- 1ヶ月前のデータ
INSERT INTO cotoka.sales (salon_id, tenant_id, customer_id, staff_id, service_id, appointment_id, amount, sale_date, payment_method, memo)
VALUES 
(1, 1, 1, 1, 1, NULL, 6500.00, CURRENT_DATE - INTERVAL '1 month', 'credit_card', 'カット＆カラー'),
(1, 1, 2, 2, 2, NULL, 4500.00, CURRENT_DATE - INTERVAL '1 month', 'cash', 'カット'),
(1, 1, 3, 1, 3, NULL, 8000.00, CURRENT_DATE - INTERVAL '1 month', 'credit_card', 'パーマ'),
(2, 1, 4, 3, 4, NULL, 9500.00, CURRENT_DATE - INTERVAL '1 month', 'cash', 'ヘッドスパ＆トリートメント'),
(2, 1, 5, 4, 5, NULL, 12000.00, CURRENT_DATE - INTERVAL '1 month', 'credit_card', 'ヘアカラー＆トリートメント');

-- 2ヶ月前のデータ
INSERT INTO cotoka.sales (salon_id, tenant_id, customer_id, staff_id, service_id, appointment_id, amount, sale_date, payment_method, memo)
VALUES 
(1, 1, 1, 1, 1, NULL, 6200.00, CURRENT_DATE - INTERVAL '2 months', 'credit_card', 'カット＆カラー'),
(1, 1, 2, 2, 2, NULL, 4200.00, CURRENT_DATE - INTERVAL '2 months', 'cash', 'カット'),
(1, 1, 3, 1, 3, NULL, 7800.00, CURRENT_DATE - INTERVAL '2 months', 'credit_card', 'パーマ'),
(2, 1, 4, 3, 4, NULL, 9200.00, CURRENT_DATE - INTERVAL '2 months', 'cash', 'ヘッドスパ＆トリートメント'),
(2, 1, 5, 4, 5, NULL, 11500.00, CURRENT_DATE - INTERVAL '2 months', 'credit_card', 'ヘアカラー＆トリートメント');

-- 3ヶ月前のデータ
INSERT INTO cotoka.sales (salon_id, tenant_id, customer_id, staff_id, service_id, appointment_id, amount, sale_date, payment_method, memo)
VALUES 
(1, 1, 1, 1, 1, NULL, 6300.00, CURRENT_DATE - INTERVAL '3 months', 'credit_card', 'カット＆カラー'),
(1, 1, 2, 2, 2, NULL, 4300.00, CURRENT_DATE - INTERVAL '3 months', 'cash', 'カット'),
(1, 1, 3, 1, 3, NULL, 7900.00, CURRENT_DATE - INTERVAL '3 months', 'credit_card', 'パーマ'),
(2, 1, 4, 3, 4, NULL, 9300.00, CURRENT_DATE - INTERVAL '3 months', 'cash', 'ヘッドスパ＆トリートメント'),
(2, 1, 5, 4, 5, NULL, 11700.00, CURRENT_DATE - INTERVAL '3 months', 'credit_card', 'ヘアカラー＆トリートメント');

-- 4ヶ月前のデータ
INSERT INTO cotoka.sales (salon_id, tenant_id, customer_id, staff_id, service_id, appointment_id, amount, sale_date, payment_method, memo)
VALUES 
(1, 1, 1, 1, 1, NULL, 6100.00, CURRENT_DATE - INTERVAL '4 months', 'credit_card', 'カット＆カラー'),
(1, 1, 2, 2, 2, NULL, 4100.00, CURRENT_DATE - INTERVAL '4 months', 'cash', 'カット'),
(1, 1, 3, 1, 3, NULL, 7700.00, CURRENT_DATE - INTERVAL '4 months', 'credit_card', 'パーマ'),
(2, 1, 4, 3, 4, NULL, 9100.00, CURRENT_DATE - INTERVAL '4 months', 'cash', 'ヘッドスパ＆トリートメント'),
(2, 1, 5, 4, 5, NULL, 11300.00, CURRENT_DATE - INTERVAL '4 months', 'credit_card', 'ヘアカラー＆トリートメント');

-- 5ヶ月前のデータ
INSERT INTO cotoka.sales (salon_id, tenant_id, customer_id, staff_id, service_id, appointment_id, amount, sale_date, payment_method, memo)
VALUES 
(1, 1, 1, 1, 1, NULL, 6400.00, CURRENT_DATE - INTERVAL '5 months', 'credit_card', 'カット＆カラー'),
(1, 1, 2, 2, 2, NULL, 4400.00, CURRENT_DATE - INTERVAL '5 months', 'cash', 'カット'),
(1, 1, 3, 1, 3, NULL, 8100.00, CURRENT_DATE - INTERVAL '5 months', 'credit_card', 'パーマ'),
(2, 1, 4, 3, 4, NULL, 9400.00, CURRENT_DATE - INTERVAL '5 months', 'cash', 'ヘッドスパ＆トリートメント'),
(2, 1, 5, 4, 5, NULL, 11900.00, CURRENT_DATE - INTERVAL '5 months', 'credit_card', 'ヘアカラー＆トリートメント');

-- 6ヶ月前のデータ
INSERT INTO cotoka.sales (salon_id, tenant_id, customer_id, staff_id, service_id, appointment_id, amount, sale_date, payment_method, memo)
VALUES 
(1, 1, 1, 1, 1, NULL, 6000.00, CURRENT_DATE - INTERVAL '6 months', 'credit_card', 'カット＆カラー'),
(1, 1, 2, 2, 2, NULL, 4000.00, CURRENT_DATE - INTERVAL '6 months', 'cash', 'カット'),
(1, 1, 3, 1, 3, NULL, 7500.00, CURRENT_DATE - INTERVAL '6 months', 'credit_card', 'パーマ'),
(2, 1, 4, 3, 4, NULL, 9000.00, CURRENT_DATE - INTERVAL '6 months', 'cash', 'ヘッドスパ＆トリートメント'),
(2, 1, 5, 4, 5, NULL, 11000.00, CURRENT_DATE - INTERVAL '6 months', 'credit_card', 'ヘアカラー＆トリートメント');