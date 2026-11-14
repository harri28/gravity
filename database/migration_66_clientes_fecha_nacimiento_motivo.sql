-- migration_66_clientes_fecha_nacimiento_motivo.sql
-- Agrega a clientes:
--   fecha_nacimiento DATE     -> para calcular la edad (cumpleañeros/cortesías)
--   motivo           VARCHAR  -> por qué se registró (cumpleanos | cortesia | promocion | otro)
-- Usado por el flujo de "cortesía" del POS (venta en S/ 0.00) y por el módulo
-- Clientes (columna "Motivo" que muestra la edad para cumpleaños).
--
-- Aplica a TODOS los schemas de sucursal existentes. El schema base para
-- sucursales nuevas ya trae estas columnas (ver database/schema_sucursal.sql).
--
-- USO (VPS):
--   sudo -u postgres psql -d gravity -f database/migration_66_clientes_fecha_nacimiento_motivo.sql

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
              WHERE table_schema = schema_name AND table_name = 'clientes'
          )
        ORDER BY schema_name
    LOOP
        BEGIN
            EXECUTE format('ALTER TABLE %I.clientes ADD COLUMN IF NOT EXISTS fecha_nacimiento DATE', s.schema_name);
            EXECUTE format('ALTER TABLE %I.clientes ADD COLUMN IF NOT EXISTS motivo VARCHAR(30)', s.schema_name);
            RAISE NOTICE 'Schema % OK', s.schema_name;
        EXCEPTION WHEN OTHERS THEN
            RAISE NOTICE 'Schema % SALTADO: %', s.schema_name, SQLERRM;
        END;
    END LOOP;
END $$;
