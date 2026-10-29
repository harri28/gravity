-- ============================================================
-- ARCHIVO: database/migration_65_mesa_color.sql
-- DESCRIPCION: Agrega mesas.color (hex del acento de la tarjeta en la grilla
--              de Mesas) a todos los schemas sucursal existentes. El color se
--              elige al crear/editar la mesa (Admin -> Mesas) y pinta una barra
--              de acento izquierda en la tarjeta. Nullable = sin color.
-- USO: $env:PGPASSWORD="1234"; psql -U postgres -d gravity -f database/migration_65_mesa_color.sql
-- ============================================================

DO $$
DECLARE s TEXT;
BEGIN
    FOR s IN
        SELECT schema_name
        FROM information_schema.schemata
        WHERE schema_name NOT LIKE 'pg_%'
          AND schema_name NOT IN ('information_schema', 'public')
    LOOP
        EXECUTE format('ALTER TABLE IF EXISTS %I.mesas ADD COLUMN IF NOT EXISTS color VARCHAR(7)', s);
    END LOOP;
END $$;
