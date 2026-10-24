-- ============================================================
-- MIGRACION 51: Modo oscuro personal para cada superadmin
-- ============================================================
-- Agrega tema_oscuro a public.superadmins: a diferencia del modo
-- oscuro de tenant_config (que es por empresa), este es personal
-- de cada cuenta de superadmin, ya que el Panel Superadmin no
-- pertenece a ningun tenant.

ALTER TABLE public.superadmins
    ADD COLUMN IF NOT EXISTS tema_oscuro BOOLEAN NOT NULL DEFAULT FALSE;
