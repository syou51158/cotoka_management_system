-- Create staff_salons mapping table to associate staff to salons within a tenant
CREATE TABLE IF NOT EXISTS cotoka.staff_salons (
  staff_salon_id BIGSERIAL PRIMARY KEY,
  tenant_id BIGINT NOT NULL REFERENCES cotoka.tenants(tenant_id) ON DELETE CASCADE,
  staff_id  BIGINT NOT NULL REFERENCES cotoka.staff(staff_id) ON DELETE CASCADE,
  salon_id  BIGINT NOT NULL REFERENCES cotoka.salons(salon_id) ON DELETE CASCADE,
  is_primary BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW(),
  UNIQUE (tenant_id, staff_id, salon_id)
);

-- Indexes for performance and common access patterns
CREATE INDEX IF NOT EXISTS idx_staff_salons_tenant_salon ON cotoka.staff_salons(tenant_id, salon_id);
CREATE INDEX IF NOT EXISTS idx_staff_salons_tenant_staff ON cotoka.staff_salons(tenant_id, staff_id);

-- Updated-at trigger
CREATE OR REPLACE FUNCTION cotoka.set_timestamp_updated_at() RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_staff_salons_updated_at ON cotoka.staff_salons;
CREATE TRIGGER trg_staff_salons_updated_at
BEFORE UPDATE ON cotoka.staff_salons
FOR EACH ROW EXECUTE FUNCTION cotoka.set_timestamp_updated_at();

-- Row Level Security
ALTER TABLE cotoka.staff_salons ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS tenant_isolation_staff_salons ON cotoka.staff_salons;
CREATE POLICY tenant_isolation_staff_salons ON cotoka.staff_salons
FOR ALL TO authenticated
USING (tenant_id = current_setting('app.current_tenant', true)::bigint)
WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::bigint);