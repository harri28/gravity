-- ============================================================
-- migration_69_banner_cancelado.sql
-- Interruptor "A cancelado" del banner de vencimiento, por tenant.
-- Cuando esta en TRUE, el banner se oculta aunque el aviso este activo
-- (el cliente ya pago / no debe verlo por ahora). Se resetea solo a FALSE
-- cuando Superadmin usa "Marcar como pagado" (renueva el vencimiento), de
-- modo que el banner reaparece 2 dias antes del PROXIMO vencimiento.
-- public.tenants es una tabla GLOBAL (no per-sucursal) -> un unico ALTER.
--
-- IMPORTANTE (despliegue): aplicar esta migracion ANTES de desplegar el
-- codigo; sin la columna, el SELECT del header y el guardado en superadmin
-- no encuentran plan_banner_cancelado.
-- ============================================================

ALTER TABLE public.tenants
    ADD COLUMN IF NOT EXISTS plan_banner_cancelado BOOLEAN NOT NULL DEFAULT FALSE;
