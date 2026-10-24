-- Permite registrar ventas por peso o volumen en el contador de productos vendidos.
-- Las cantidades de venta ya son decimales; esta columna tambien debe admitirlas.
DO $migration$
DECLARE
    s RECORD;
BEGIN
    FOR s IN
        SELECT schema_name
        FROM information_schema.schemata
        WHERE schema_name NOT LIKE 'pg_%'
          AND schema_name NOT IN ('information_schema', 'public')
          AND EXISTS (
              SELECT 1
              FROM information_schema.columns
              WHERE table_schema = schema_name
                AND table_name = 'productos'
                AND column_name = 'total_vendido'
          )
        ORDER BY schema_name
    LOOP
        EXECUTE format(
            'ALTER TABLE %I.productos ALTER COLUMN total_vendido TYPE DECIMAL(10,2) USING total_vendido::DECIMAL(10,2)',
            s.schema_name
        );
    END LOOP;
END $migration$;
