-- Cotoka DB migration: optimize multitenant design and indexing

-- 1) Add tenant_id to appointment_services and backfill
ALTER TABLE cotoka.appointment_services ADD COLUMN IF NOT EXISTS tenant_id INTEGER NULL;
UPDATE cotoka.appointment_services aps
SET tenant_id = a.tenant_id
FROM cotoka.appointments a 
WHERE aps.appointment_id = a.appointment_id AND aps.tenant_id IS NULL;
CREATE INDEX IF NOT EXISTS idx_appointment_services_tenant ON cotoka.appointment_services(tenant_id);
ALTER TABLE cotoka.appointment_services
  ADD CONSTRAINT appointment_services_ibfk_3 FOREIGN KEY (tenant_id) REFERENCES cotoka.tenants(tenant_id) ON DELETE CASCADE;

-- 2) Add tenant_id to available_slots and backfill
ALTER TABLE cotoka.available_slots ADD COLUMN IF NOT EXISTS tenant_id INTEGER NULL;
UPDATE cotoka.available_slots s
SET tenant_id = sa.tenant_id
FROM cotoka.salons sa 
WHERE s.salon_id = sa.salon_id AND s.tenant_id IS NULL;
CREATE INDEX IF NOT EXISTS idx_available_slots_tenant ON cotoka.available_slots(tenant_id);
ALTER TABLE cotoka.available_slots
  ADD CONSTRAINT available_slots_ibfk_3 FOREIGN KEY (tenant_id) REFERENCES cotoka.tenants(tenant_id) ON DELETE CASCADE;

-- 3) Add tenant_id to customer_feedback and backfill
ALTER TABLE cotoka.customer_feedback ADD COLUMN IF NOT EXISTS tenant_id INTEGER NULL;
UPDATE cotoka.customer_feedback cf
SET tenant_id = sa.tenant_id
FROM cotoka.salons sa 
WHERE cf.salon_id = sa.salon_id AND cf.tenant_id IS NULL;
CREATE INDEX IF NOT EXISTS idx_customer_feedback_tenant ON cotoka.customer_feedback(tenant_id);
ALTER TABLE cotoka.customer_feedback
  ADD CONSTRAINT customer_feedback_ibfk_1 FOREIGN KEY (tenant_id) REFERENCES cotoka.tenants(tenant_id) ON DELETE CASCADE;

-- 4) Add tenant_id to inventory_transactions and backfill
ALTER TABLE cotoka.inventory_transactions ADD COLUMN IF NOT EXISTS tenant_id INTEGER NULL;
UPDATE cotoka.inventory_transactions it
SET tenant_id = ii.tenant_id
FROM cotoka.inventory_items ii 
WHERE it.item_id = ii.item_id AND it.tenant_id IS NULL;
CREATE INDEX IF NOT EXISTS idx_inventory_transactions_tenant ON cotoka.inventory_transactions(tenant_id);
ALTER TABLE cotoka.inventory_transactions
  ADD CONSTRAINT inventory_transactions_ibfk_2 FOREIGN KEY (tenant_id) REFERENCES cotoka.tenants(tenant_id) ON DELETE CASCADE;

-- 5) Add tenant_id to sale_items and backfill
ALTER TABLE cotoka.sale_items ADD COLUMN IF NOT EXISTS tenant_id INTEGER NULL;
UPDATE cotoka.sale_items si
SET tenant_id = s.tenant_id
FROM cotoka.sales s 
WHERE si.sale_id = s.sale_id AND si.tenant_id IS NULL;
CREATE INDEX IF NOT EXISTS idx_sale_items_tenant ON cotoka.sale_items(tenant_id);
ALTER TABLE cotoka.sale_items
  ADD CONSTRAINT sale_items_ibfk_2 FOREIGN KEY (tenant_id) REFERENCES cotoka.tenants(tenant_id) ON DELETE CASCADE;

-- 6) Add tenant_id to salon_business_hours and backfill
ALTER TABLE cotoka.salon_business_hours ADD COLUMN IF NOT EXISTS tenant_id INTEGER NULL;
UPDATE cotoka.salon_business_hours sbh
SET tenant_id = sa.tenant_id
FROM cotoka.salons sa 
WHERE sbh.salon_id = sa.salon_id AND sbh.tenant_id IS NULL;
CREATE INDEX IF NOT EXISTS idx_salon_business_hours_tenant ON cotoka.salon_business_hours(tenant_id);
ALTER TABLE cotoka.salon_business_hours
  ADD CONSTRAINT salon_business_hours_ibfk_2 FOREIGN KEY (tenant_id) REFERENCES cotoka.tenants(tenant_id) ON DELETE CASCADE;

-- 7) Add tenant_id to staff_schedules and backfill
ALTER TABLE cotoka.staff_schedules ADD COLUMN IF NOT EXISTS tenant_id INTEGER NULL;
UPDATE cotoka.staff_schedules ss
SET tenant_id = sa.tenant_id
FROM cotoka.salons sa 
WHERE ss.salon_id = sa.salon_id AND ss.tenant_id IS NULL;
CREATE INDEX IF NOT EXISTS idx_staff_schedules_tenant ON cotoka.staff_schedules(tenant_id);
ALTER TABLE cotoka.staff_schedules
  ADD CONSTRAINT staff_schedules_ibfk_3 FOREIGN KEY (tenant_id) REFERENCES cotoka.tenants(tenant_id) ON DELETE CASCADE;

-- 8) Add tenant_id to staff_shifts and backfill via shift pattern
ALTER TABLE cotoka.staff_shifts ADD COLUMN IF NOT EXISTS tenant_id INTEGER NULL;
UPDATE cotoka.staff_shifts sh
SET tenant_id = sp.tenant_id
FROM cotoka.staff_shift_patterns sp 
WHERE sh.pattern_id = sp.pattern_id AND sh.tenant_id IS NULL;
CREATE INDEX IF NOT EXISTS idx_staff_shifts_tenant ON cotoka.staff_shifts(tenant_id);
ALTER TABLE cotoka.staff_shifts
  ADD CONSTRAINT staff_shifts_ibfk_2 FOREIGN KEY (tenant_id) REFERENCES cotoka.tenants(tenant_id) ON DELETE CASCADE;

-- 9) Add tenant_id to staff_specialties and backfill
ALTER TABLE cotoka.staff_specialties ADD COLUMN IF NOT EXISTS tenant_id INTEGER NULL;
UPDATE cotoka.staff_specialties ss
SET tenant_id = st.tenant_id
FROM cotoka.staff st 
WHERE ss.staff_id = st.staff_id AND ss.tenant_id IS NULL;
CREATE INDEX IF NOT EXISTS idx_staff_specialties_tenant ON cotoka.staff_specialties(tenant_id);
ALTER TABLE cotoka.staff_specialties
  ADD CONSTRAINT staff_specialties_ibfk_3 FOREIGN KEY (tenant_id) REFERENCES cotoka.tenants(tenant_id) ON DELETE CASCADE;

-- 10) Add tenant_id to system_logs and backfill from users
ALTER TABLE cotoka.system_logs ADD COLUMN IF NOT EXISTS tenant_id INTEGER NULL;
UPDATE cotoka.system_logs sl
SET tenant_id = u.tenant_id
FROM cotoka.users u 
WHERE sl.user_id = u.user_id AND sl.tenant_id IS NULL AND sl.user_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_system_logs_tenant ON cotoka.system_logs(tenant_id);
ALTER TABLE cotoka.system_logs
  ADD CONSTRAINT system_logs_ibfk_2 FOREIGN KEY (tenant_id) REFERENCES cotoka.tenants(tenant_id) ON DELETE CASCADE;

-- 11) Add tenant_id to time_slots and backfill
ALTER TABLE cotoka.time_slots ADD COLUMN IF NOT EXISTS tenant_id INTEGER NULL;
UPDATE cotoka.time_slots ts
SET tenant_id = sa.tenant_id
FROM cotoka.salons sa 
WHERE ts.salon_id = sa.salon_id AND ts.tenant_id IS NULL;
CREATE INDEX IF NOT EXISTS idx_time_slots_tenant ON cotoka.time_slots(tenant_id);
ALTER TABLE cotoka.time_slots
  ADD CONSTRAINT time_slots_ibfk_2 FOREIGN KEY (tenant_id) REFERENCES cotoka.tenants(tenant_id) ON DELETE CASCADE;

-- 12) Add tenant_id to user_salons and backfill
ALTER TABLE cotoka.user_salons ADD COLUMN IF NOT EXISTS tenant_id INTEGER NULL;
UPDATE cotoka.user_salons us
SET tenant_id = sa.tenant_id
FROM cotoka.salons sa 
WHERE us.salon_id = sa.salon_id AND us.tenant_id IS NULL;
CREATE INDEX IF NOT EXISTS idx_user_salons_tenant ON cotoka.user_salons(tenant_id);
ALTER TABLE cotoka.user_salons
  ADD CONSTRAINT user_salons_ibfk_3 FOREIGN KEY (tenant_id) REFERENCES cotoka.tenants(tenant_id) ON DELETE CASCADE;

-- 13) Composite indexes per design rules
CREATE INDEX IF NOT EXISTS idx_appointments_tenant_date ON cotoka.appointments(tenant_id, appointment_date);
CREATE INDEX IF NOT EXISTS idx_customers_tenant_email ON cotoka.customers(tenant_id, email);
CREATE INDEX IF NOT EXISTS idx_sales_tenant_date ON cotoka.sales(tenant_id, sale_date);
CREATE INDEX IF NOT EXISTS idx_payments_tenant_date ON cotoka.payments(tenant_id, payment_date);

-- Note: tenant_id is currently nullable for new inserts
-- This allows gradual migration without breaking existing functionality
-- Once all data is migrated and application code is updated,
-- consider making tenant_id NOT NULL for better data integrity