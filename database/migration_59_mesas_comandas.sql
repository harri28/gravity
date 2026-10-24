-- ============================================================
-- ARCHIVO: database/migration_59_mesas_comandas.sql
-- DESCRIPCION: Modelo de comandas por mesa (base para karaoke/bar/
--              licoreria). Reemplaza el flujo de POS directo por uno
--              de "abrir mesa -> agregar bebidas -> cobrar al cerrar".
--
--              Tablas nuevas (per-schema, igual que productos/ventas):
--                mesas          - catalogo de mesas de la sucursal
--                comandas       - una por "sesion de uso" de una mesa
--                comanda_items  - items pedidos en cada comanda
--
--              Columnas agregadas:
--                ventas.comanda_id         - link reverso al cobrar
--                comandas.comanda_padre_id - futuro "recuperar mesa"
--                                            (reabrir una comanda cerrada
--                                            en otra mesa)
--
--              Reglas garantizadas a nivel BD:
--                - Solo UNA comanda abierta por mesa a la vez
--                  (indice unico parcial WHERE estado = 'abierta')
--                - Numero de mesa unico dentro de una sucursal/schema
--                - estado restringido a 'abierta'|'cerrada'|'anulada'
--
-- USO:
--   psql -U postgres -d gravity -f database/migration_59_mesas_comandas.sql
-- ============================================================

DO $migration$
DECLARE
    s TEXT;
BEGIN
    FOR s IN
        SELECT schema_name FROM information_schema.schemata
        WHERE schema_name NOT LIKE 'pg_%'
          AND schema_name NOT IN ('information_schema', 'public')
    LOOP
        -- ------------------------------------------------------------
        -- mesas (catalogo editable desde Admin -> Mesas)
        -- ------------------------------------------------------------
        EXECUTE format($body$
            CREATE TABLE IF NOT EXISTS %I.mesas (
                id          SERIAL PRIMARY KEY,
                numero      VARCHAR(20)   NOT NULL UNIQUE,
                nombre      VARCHAR(80),
                capacidad   INTEGER,
                activa      BOOLEAN       NOT NULL DEFAULT TRUE,
                created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
            )
        $body$, s);

        EXECUTE format(
            'CREATE INDEX IF NOT EXISTS idx_%s_mesas_activa ON %I.mesas(activa)',
            s, s
        );

        -- ------------------------------------------------------------
        -- comandas (una por sesion de uso de una mesa)
        -- ------------------------------------------------------------
        EXECUTE format($body$
            CREATE TABLE IF NOT EXISTS %I.comandas (
                id                   SERIAL PRIMARY KEY,
                mesa_id              INTEGER       NOT NULL REFERENCES %I.mesas(id),
                usuario_apertura_id  INTEGER       NOT NULL,  -- ref public.usuarios (sin FK cross-schema)
                usuario_cierre_id    INTEGER,                 -- ref public.usuarios
                hora_apertura        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                hora_cierre          TIMESTAMP,
                estado               VARCHAR(20)   NOT NULL DEFAULT 'abierta',
                total                DECIMAL(10,2) NOT NULL DEFAULT 0,
                venta_id             INTEGER,       -- se setea al cobrar (ref local a ventas.id)
                comanda_padre_id     INTEGER REFERENCES %I.comandas(id),
                                                   -- futuro: "recuperar mesa" = reabrir una comanda
                                                   -- cerrada en otra mesa como una nueva comanda hija
                motivo_anulacion     TEXT,
                observaciones        TEXT,
                created_at           TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT ck_comandas_estado CHECK (estado IN ('abierta','cerrada','anulada'))
            )
        $body$, s, s, s);

        -- Indice unico parcial: UNA sola comanda abierta por mesa a la vez
        EXECUTE format(
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_%s_comandas_mesa_abierta '
            'ON %I.comandas (mesa_id) WHERE estado = ''abierta''',
            s, s
        );
        -- Indices para consultas tipicas
        EXECUTE format(
            'CREATE INDEX IF NOT EXISTS idx_%s_comandas_estado ON %I.comandas(estado)',
            s, s
        );
        EXECUTE format(
            'CREATE INDEX IF NOT EXISTS idx_%s_comandas_venta ON %I.comandas(venta_id) WHERE venta_id IS NOT NULL',
            s, s
        );

        -- ------------------------------------------------------------
        -- comanda_items (items pedidos en cada comanda)
        -- ------------------------------------------------------------
        EXECUTE format($body$
            CREATE TABLE IF NOT EXISTS %I.comanda_items (
                id              SERIAL PRIMARY KEY,
                comanda_id      INTEGER       NOT NULL REFERENCES %I.comandas(id) ON DELETE CASCADE,
                producto_id     INTEGER       NOT NULL REFERENCES %I.productos(id),
                cantidad        DECIMAL(10,3) NOT NULL DEFAULT 1,
                precio_unitario DECIMAL(10,2) NOT NULL,
                subtotal        DECIMAL(10,2) NOT NULL,
                hora_pedido     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                usuario_id      INTEGER       NOT NULL,   -- quien agrego el item (ref public.usuarios)
                notas           TEXT
            )
        $body$, s, s, s);

        EXECUTE format(
            'CREATE INDEX IF NOT EXISTS idx_%s_comanda_items_comanda ON %I.comanda_items(comanda_id)',
            s, s
        );

        -- ------------------------------------------------------------
        -- ventas.comanda_id: link reverso cuando una venta vino de cobrar una mesa.
        -- NO es FK (evita dependencia circular con la tabla comandas) pero
        -- se mantiene indexada para joins y para el filtro de Historial.
        -- ------------------------------------------------------------
        EXECUTE format('ALTER TABLE %I.ventas ADD COLUMN IF NOT EXISTS comanda_id INTEGER', s);
        EXECUTE format(
            'CREATE INDEX IF NOT EXISTS idx_%s_ventas_comanda ON %I.ventas(comanda_id) WHERE comanda_id IS NOT NULL',
            s, s
        );

    END LOOP;
END $migration$;
