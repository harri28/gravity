-- ============================================================
-- MIGRACIÓN 61: Columna logo + agrandar telefono/plan en public.tenants
--
-- Fixes:
--   1) public.tenants.logo (varchar 255) — existía en la BD local pero no
--      en el repo ni en el VPS. superadmin/api.php la consultaba y rompía
--      con "column t.logo does not exist" (500) al listar empresas.
--   2) public.tenants.telefono — pasa de varchar(20) a varchar(30) para
--      aceptar formatos con prefijo internacional/extensión que exceden 20
--      ("value too long for type character varying(20)").
--   3) public.tenants.plan — pasa a varchar(30) por si en el futuro se
--      agregan planes con nombres más largos.
--
-- Idempotente.
-- ============================================================

ALTER TABLE public.tenants
    ADD COLUMN IF NOT EXISTS logo VARCHAR(255);

ALTER TABLE public.tenants
    ALTER COLUMN telefono TYPE VARCHAR(30);

ALTER TABLE public.tenants
    ALTER COLUMN plan TYPE VARCHAR(30);
