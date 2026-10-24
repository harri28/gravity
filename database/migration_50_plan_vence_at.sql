-- Migración 50: fecha de vencimiento del plan por tenant
-- Permite a Superadmin configurar, por cada tenant, una fecha de vencimiento
-- de su plan de suscripción. Gravity muestra un aviso al cliente (todos
-- los roles) cuando faltan 7 días o menos, o ya venció. Es solo informativo:
-- no bloquea el acceso al sistema.

ALTER TABLE public.tenants ADD COLUMN IF NOT EXISTS plan_vence_at DATE;
