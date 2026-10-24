-- ============================================================
-- MIGRACIÓN 62: Columnas faltantes en public.sucursales y public.usuarios
--
-- Fixes 500 en superadmin/api.php al listar sucursales/usuarios de un
-- tenant: ambas columnas existían en la BD local pero nunca se escribió
-- la migración para producción.
--
--   - public.sucursales.archivada BOOLEAN NOT NULL DEFAULT FALSE
--   - public.usuarios.observaciones TEXT
--
-- Idempotente.
-- ============================================================

ALTER TABLE public.sucursales
    ADD COLUMN IF NOT EXISTS archivada BOOLEAN NOT NULL DEFAULT FALSE;

ALTER TABLE public.usuarios
    ADD COLUMN IF NOT EXISTS observaciones TEXT;
