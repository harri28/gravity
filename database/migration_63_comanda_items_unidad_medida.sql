-- ============================================================
-- MIGRACIÓN 63: Agrega unidad_medida y factor_unidad a comanda_items
--
-- Permite que al agregar un producto a una comanda desde mesa.php se
-- elija una presentación alternativa (ej. "CAJA" = 12 unidades) con su
-- precio propio. Antes solo se podía vender por unidad base.
--
-- Columnas:
--   - unidad_medida  VARCHAR(50)        NULL  (ej. "CAJA"; NULL = unidad base)
--   - factor_unidad  DECIMAL(10,2)      NOT NULL DEFAULT 1
--     (cuantas unidades base equivale la presentación; se guarda para que al
--      quitar/cambiar el item el stock se reponga correctamente aunque
--      producto_precios_unidad haya cambiado despues)
--
-- Idempotente.
-- ============================================================

DO $$
DECLARE r RECORD;
BEGIN
    FOR r IN
        SELECT DISTINCT schemaname FROM pg_tables
        WHERE tablename = 'comanda_items'
        ORDER BY schemaname
    LOOP
        EXECUTE format(
            'ALTER TABLE %I.comanda_items ADD COLUMN IF NOT EXISTS unidad_medida VARCHAR(50)',
            r.schemaname
        );
        EXECUTE format(
            'ALTER TABLE %I.comanda_items ADD COLUMN IF NOT EXISTS factor_unidad DECIMAL(10,2) NOT NULL DEFAULT 1',
            r.schemaname
        );
        RAISE NOTICE 'Migrado esquema: %', r.schemaname;
    END LOOP;
END;
$$;
