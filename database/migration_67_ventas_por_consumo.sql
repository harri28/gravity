-- migration_67_ventas_por_consumo.sql
-- Agrega ventas.por_consumo BOOLEAN.
-- Cuando es TRUE, el COMPROBANTE (ticket impreso, boleta/factura enviada a SUNAT y la
-- consulta publica "Mi Comprobante") muestra una sola linea generica "CONSUMO" en vez
-- del detalle de productos. La tabla venta_detalles CONSERVA el detalle real, asi que
-- historial, stock, utilidad y demas registros internos no se ven afectados.
--
-- Aplica a TODOS los schemas de sucursal existentes. El schema base para sucursales
-- nuevas ya trae la columna (ver database/schema_sucursal.sql).
--
-- USO (VPS):
--   sudo -u postgres psql -d gravity -f database/migration_67_ventas_por_consumo.sql

DO $$
DECLARE s RECORD;
BEGIN
    FOR s IN
        SELECT schema_name
        FROM information_schema.schemata
        WHERE schema_name NOT LIKE 'pg_%'
          AND schema_name NOT IN ('information_schema', 'public')
          AND EXISTS (
              SELECT 1 FROM information_schema.tables
              WHERE table_schema = schema_name AND table_name = 'ventas'
          )
        ORDER BY schema_name
    LOOP
        BEGIN
            EXECUTE format('ALTER TABLE %I.ventas ADD COLUMN IF NOT EXISTS por_consumo BOOLEAN NOT NULL DEFAULT FALSE', s.schema_name);
            RAISE NOTICE 'Schema % OK', s.schema_name;
        EXCEPTION WHEN OTHERS THEN
            RAISE NOTICE 'Schema % SALTADO: %', s.schema_name, SQLERRM;
        END;
    END LOOP;
END $$;
