-- ============================================================
-- migration_68_banner_mensaje.sql
-- Mensaje personalizado del banner de vencimiento de plan, por tenant.
-- public.tenants es una tabla GLOBAL (no per-sucursal), asi que es un
-- unico ALTER, sin el loop por esquemas.
-- Si queda vacio/NULL, el banner usa el texto dinamico por defecto
-- (conteo automatico de dias) definido en includes/header.php.
--
-- Complementa el banner ya existente (plan_vence_at / plan_banner_activo,
-- migraciones 50 y 52). Superadmin lo edita en "Configurar banner de pago".
--
-- IMPORTANTE (despliegue): aplicar esta migracion ANTES de desplegar el
-- codigo; sin la columna, el SELECT del header y el guardado en superadmin
-- no encuentran plan_banner_mensaje.
-- ============================================================

ALTER TABLE public.tenants
    ADD COLUMN IF NOT EXISTS plan_banner_mensaje TEXT;
