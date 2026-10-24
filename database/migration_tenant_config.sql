-- Migration: personalización de marca por empresa
CREATE TABLE IF NOT EXISTS public.tenant_config (
    id              SERIAL PRIMARY KEY,
    tenant_id       INTEGER NOT NULL UNIQUE,
    nombre_sistema  VARCHAR(100) NOT NULL DEFAULT 'Gravity',
    logo_path       VARCHAR(255),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);
