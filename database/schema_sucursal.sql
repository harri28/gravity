-- ============================================================
-- ARCHIVO: farmacia/database/schema_sucursal.sql
-- DESCRIPCIÓN: Tablas por sucursal. Se ejecutan dentro del
--              schema de cada sucursal (search_path ya aplicado).
-- EJECUTAR para una nueva sucursal:
--   SET search_path = nombre_schema;
--   \i database/schema_sucursal.sql
--
-- NOTA: este archivo debe reflejar el estado final de una sucursal
-- YA migrada (no solo el estado "recien creada" original). Cada vez
-- que se agregue una columna via migration_XX a las sucursales
-- existentes, hay que agregarla tambien aqui -- si no, una sucursal
-- nueva nace desactualizada y falla con "column does not exist" en
-- cuanto se usa esa funcionalidad. Ver CLAUDE.md.
-- ============================================================

-- Las categorías son globales (public.categorias, ver
-- migration_21_categorias_global.sql) -- NO se crea tabla local aqui,
-- productos.categoria_id referencia directo a public.categorias(id).

CREATE TABLE IF NOT EXISTS productos (
    id               SERIAL PRIMARY KEY,
    codigo           VARCHAR(50)    UNIQUE NOT NULL,
    nombre           VARCHAR(200)   NOT NULL,
    descripcion      TEXT,
    categoria_id     INTEGER        REFERENCES public.categorias(id),
    precio_compra    DECIMAL(10,3)  DEFAULT 0,
    precio_venta     DECIMAL(10,2)  NOT NULL,
    stock            DECIMAL(10,2)  DEFAULT 0,
    stock_minimo     DECIMAL(10,2)  DEFAULT 5,
    unidad           VARCHAR(50)    DEFAULT 'unidad',
    laboratorio      VARCHAR(100),
    presentacion     VARCHAR(100),
    requiere_receta  BOOLEAN        DEFAULT FALSE,
    activo           BOOLEAN        DEFAULT TRUE,
    favorito         BOOLEAN        DEFAULT FALSE,
    total_vendido    DECIMAL(10,2)  DEFAULT 0,
    fecha_vencimiento DATE,
    -- Campos de facturacion electronica (migration_09/22/26)
    codigo_interno       VARCHAR(50),
    codigo_barras        VARCHAR(100),
    codigo_sunat         VARCHAR(8)     DEFAULT '00000000',
    unidad_id            INTEGER        REFERENCES public.fe_unidades(id),
    unidad_codigo        VARCHAR(3)     DEFAULT 'NIU',
    afectacion_igv_id    INTEGER        REFERENCES public.fe_tipos_afectacion_igv(id),
    afectacion_igv_codigo VARCHAR(2)    DEFAULT '10',
    porcentaje_igv       DECIMAL(5,2)   DEFAULT 18.00,
    incluye_igv          BOOLEAN        DEFAULT TRUE,
    icbper_activo        BOOLEAN        DEFAULT FALSE,
    factor_icbper        DECIMAL(10,4)  DEFAULT 0,
    product_type         VARCHAR(20)    DEFAULT 'product',
    -- Papelera de productos (migration_54): 'eliminado' distingue un
    -- soft-delete real de un simple 'activo=FALSE' (desactivado pero
    -- no eliminado). eliminado_por: usuario_id, sin FK cross-schema a
    -- public.usuarios (mismo criterio que usuario_id en ventas/ingresos).
    eliminado        BOOLEAN        DEFAULT FALSE,
    eliminado_at     TIMESTAMP,
    eliminado_por    INTEGER,
    created_at       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_productos_codigo_sunat ON productos (codigo_sunat);
CREATE INDEX IF NOT EXISTS idx_productos_eliminado ON productos (eliminado, eliminado_at);

-- Nota historica: migration_55 creaba un indice unico parcial sobre
-- codigo_barras (uq_productos_codigo_barras_activo). Se revirtio en
-- migration_57 (2026-10-05) porque, en el catalogo real, varias
-- presentaciones del mismo producto (paquete x10 vs unidad suelta)
-- comparten el mismo codigo de barras a proposito -- la restriccion
-- impedia cargar esas presentaciones y no era sostenible.

-- Catálogo reutilizable de unidades de medida para "Precios por unidad de
-- medida" (ej. CAJA, BLISTER, PAQUETE). Extensible desde el formulario de
-- producto ("+ Nueva unidad").
CREATE TABLE IF NOT EXISTS unidades_medida_venta (
    id         SERIAL PRIMARY KEY,
    nombre     VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO unidades_medida_venta (nombre) VALUES ('CAJA'), ('BLISTER'), ('PAQUETE')
    ON CONFLICT (nombre) DO NOTHING;

-- Precios adicionales por unidad de medida, por producto (ej. un producto
-- base vendido por "unidad" puede tener ademas un precio configurado para
-- venderse por BLISTER de 10, otro por CAJA de 100, etc). El producto sigue
-- siendo la unidad base (su propio precio_venta/stock no cambian).
CREATE TABLE IF NOT EXISTS producto_precios_unidad (
    id             SERIAL PRIMARY KEY,
    producto_id    INTEGER        NOT NULL REFERENCES productos(id) ON DELETE CASCADE,
    unidad_medida  VARCHAR(50)    NOT NULL,
    abreviacion    VARCHAR(20),
    cantidad       INTEGER        NOT NULL,
    precio_venta   DECIMAL(10,2)  NOT NULL,
    created_at     TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_producto_precios_unidad_producto ON producto_precios_unidad(producto_id);

CREATE TABLE IF NOT EXISTS clientes (
    id         SERIAL PRIMARY KEY,
    nombres    VARCHAR(150) NOT NULL,
    apellidos  VARCHAR(150),
    dni        VARCHAR(20)  UNIQUE,
    ruc        VARCHAR(20),
    telefono   VARCHAR(20),
    email      VARCHAR(100),
    direccion  TEXT,
    activo     BOOLEAN   DEFAULT TRUE,
    -- Campos de facturacion electronica (migration_09)
    tipo_documento_id     INTEGER REFERENCES public.fe_tipos_documento_identidad(id),
    tipo_documento_codigo VARCHAR(2),
    numero_documento      VARCHAR(20),
    nombre_completo       VARCHAR(255),
    razon_social          VARCHAR(255),
    codigo_pais           VARCHAR(2) DEFAULT 'PE',
    ubigeo                VARCHAR(6),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_clientes_numero_documento ON clientes (numero_documento);

CREATE TABLE IF NOT EXISTS cajas (
    id               SERIAL PRIMARY KEY,
    nombre           VARCHAR(100)  DEFAULT 'Caja Principal',
    saldo_inicial    DECIMAL(10,2) DEFAULT 0,
    saldo_actual     DECIMAL(10,2) DEFAULT 0,
    estado           VARCHAR(20)   DEFAULT 'cerrada',  -- abierta | cerrada
    apertura_at      TIMESTAMP,
    cierre_at        TIMESTAMP,
    usuario_apertura VARCHAR(100),
    usuario_id       INTEGER,   -- referencia a public.usuarios (sin FK cross-schema)
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ventas (
    id               SERIAL PRIMARY KEY,
    numero_venta     VARCHAR(20)   UNIQUE NOT NULL,
    cliente_id       INTEGER       REFERENCES clientes(id),
    caja_id          INTEGER       REFERENCES cajas(id),
    usuario_id       INTEGER,      -- referencia a public.usuarios
    subtotal         DECIMAL(10,2) NOT NULL DEFAULT 0,
    descuento        DECIMAL(10,2) DEFAULT 0,
    igv              DECIMAL(10,2) DEFAULT 0,
    total            DECIMAL(10,2) NOT NULL,
    tipo_pago        VARCHAR(30)   DEFAULT 'efectivo',
    tipo_comprobante VARCHAR(20)   DEFAULT 'boleta',
    estado           VARCHAR(20)   DEFAULT 'completada',
    observaciones    TEXT,
    motivo_anulacion TEXT,
    vendedor         VARCHAR(100)  DEFAULT 'Administrador',
    -- Campos de facturacion electronica (migration_09/22/30)
    fecha_emision          DATE    DEFAULT CURRENT_DATE,
    fecha_vencimiento      DATE,
    hora_emision           TIME    DEFAULT CURRENT_TIME,
    moneda_id              INTEGER REFERENCES public.fe_monedas(id),
    moneda_codigo          VARCHAR(3) DEFAULT 'PEN',
    tipo_documento_id      INTEGER REFERENCES public.fe_tipos_documento(id),
    codigo_tipo_documento  VARCHAR(2),
    serie                  VARCHAR(4),
    correlativo            VARCHAR(8),
    operacion_tipo_codigo  VARCHAR(4) DEFAULT '0101',
    forma_pago_id          INTEGER REFERENCES public.fe_formas_pago(id),
    sunat_forma_pago       VARCHAR(20) DEFAULT 'Contado',
    exonerada              DECIMAL(18,2) DEFAULT 0,
    inafecta               DECIMAL(18,2) DEFAULT 0,
    gravada                DECIMAL(18,2) DEFAULT 0,
    gratuita               DECIMAL(18,2) DEFAULT 0,
    anticipo               DECIMAL(18,2) DEFAULT 0,
    otros_cargos           DECIMAL(18,2) DEFAULT 0,
    icbper                 DECIMAL(18,2) DEFAULT 0,
    monto_credito          DECIMAL(18,2) DEFAULT 0,
    vuelto                 DECIMAL(18,2) DEFAULT 0,
    cuotas                 JSONB,
    payment_breakdown      JSONB,
    anulado                BOOLEAN DEFAULT FALSE,
    cdr                    INTEGER,
    estado_cpe             INTEGER,
    ticket_sunat           VARCHAR(100),
    qr                     TEXT,
    -- Link reverso cuando esta venta se emitio al cobrar una mesa (comanda).
    -- NO es FK (evita dependencia circular con la tabla comandas, que se crea mas
    -- abajo y a su vez tiene venta_id apuntando a ventas.id).
    comanda_id             INTEGER,
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_ventas_fecha_emision ON ventas (fecha_emision);
CREATE INDEX IF NOT EXISTS idx_ventas_codigo_tipo_documento ON ventas (codigo_tipo_documento);
CREATE INDEX IF NOT EXISTS idx_ventas_comanda ON ventas (comanda_id) WHERE comanda_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS venta_detalles (
    id                     SERIAL PRIMARY KEY,
    venta_id               INTEGER       REFERENCES ventas(id) ON DELETE CASCADE,
    producto_id            INTEGER       REFERENCES productos(id),
    cantidad               DECIMAL(10,2) NOT NULL,
    precio_unitario        DECIMAL(10,2) NOT NULL,
    descuento              DECIMAL(10,2) DEFAULT 0,
    subtotal               DECIMAL(10,2) NOT NULL,
    unidad_medida_vendida  VARCHAR(50),        -- NULL = unidad base del producto
    factor_equivalencia    DECIMAL(10,2) DEFAULT 1,  -- unidades base que representa 1 "cantidad" de esta linea
    -- Campos de facturacion electronica (migration_09/22)
    unidad_id              INTEGER REFERENCES public.fe_unidades(id),
    unidad_codigo          VARCHAR(3) DEFAULT 'NIU',
    afectacion_igv_id      INTEGER REFERENCES public.fe_tipos_afectacion_igv(id),
    afectacion_igv_codigo  VARCHAR(2) DEFAULT '10',
    descripcion            VARCHAR(255),
    codigo_interno         VARCHAR(50),
    codigo_sunat           VARCHAR(8) DEFAULT '00000000',
    igv                    DECIMAL(18,2) DEFAULT 0,
    valor_unitario         DECIMAL(18,10) DEFAULT 0,
    valor_total            DECIMAL(18,2) DEFAULT 0,
    precio_total           DECIMAL(18,2) DEFAULT 0,
    icbper                 DECIMAL(18,2) DEFAULT 0,
    factor_icbper          DECIMAL(18,4) DEFAULT 0,
    cantidad_bolsas        DECIMAL(18,2) DEFAULT 0,
    created_at             TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Mesas / Comandas (modelo karaoke/bar/licoreria, migration_59)
-- ============================================================
-- Flujo: mozo abre una mesa libre -> agrega items a la comanda abierta
-- (descuenta stock al agregar) -> cliente pide la cuenta -> cajero clickea
-- "Cobrar" -> emite el comprobante reutilizando ventas/registrar_venta,
-- cierra la comanda, libera la mesa, y la venta aparece en el Historial
-- de siempre con un link reverso via ventas.comanda_id.
--
-- Reglas garantizadas a nivel BD:
--   - Numero de mesa unico dentro de la sucursal (UNIQUE)
--   - Solo UNA comanda abierta por mesa a la vez (indice parcial unico)
--   - estado restringido a 'abierta'|'cerrada'|'anulada'
--
-- Catalogo editable desde Admin -> Mesas (modulo admin).

CREATE TABLE IF NOT EXISTS mesas (
    id          SERIAL PRIMARY KEY,
    numero      VARCHAR(20)   NOT NULL UNIQUE,
    nombre      VARCHAR(80),
    capacidad   INTEGER,
    color       VARCHAR(7),   -- hex del acento de la tarjeta (ej. '#3b82f6'); NULL = sin color
    activa      BOOLEAN       NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_mesas_activa ON mesas (activa);

CREATE TABLE IF NOT EXISTS comandas (
    id                   SERIAL PRIMARY KEY,
    mesa_id              INTEGER       NOT NULL REFERENCES mesas(id),
    usuario_apertura_id  INTEGER       NOT NULL,  -- ref public.usuarios (sin FK cross-schema)
    usuario_cierre_id    INTEGER,                 -- ref public.usuarios
    hora_apertura        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    hora_cierre          TIMESTAMP,
    estado               VARCHAR(20)   NOT NULL DEFAULT 'abierta',
    total                DECIMAL(10,2) NOT NULL DEFAULT 0,
    venta_id             INTEGER,       -- se setea al cobrar (ref local a ventas.id)
    comanda_padre_id     INTEGER REFERENCES comandas(id),
                                       -- futuro: "recuperar mesa" = reabrir una comanda
                                       -- cerrada en otra mesa como una nueva comanda hija
    motivo_anulacion     TEXT,
    observaciones        TEXT,
    created_at           TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ck_comandas_estado CHECK (estado IN ('abierta','cerrada','anulada'))
);
-- UNA sola comanda abierta por mesa a la vez
CREATE UNIQUE INDEX IF NOT EXISTS uq_comandas_mesa_abierta
    ON comandas (mesa_id) WHERE estado = 'abierta';
CREATE INDEX IF NOT EXISTS idx_comandas_estado ON comandas (estado);
CREATE INDEX IF NOT EXISTS idx_comandas_venta  ON comandas (venta_id) WHERE venta_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS comanda_items (
    id              SERIAL PRIMARY KEY,
    comanda_id      INTEGER       NOT NULL REFERENCES comandas(id) ON DELETE CASCADE,
    producto_id     INTEGER       NOT NULL REFERENCES productos(id),
    cantidad        DECIMAL(10,3) NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal        DECIMAL(10,2) NOT NULL,
    hora_pedido     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_id      INTEGER       NOT NULL,   -- quien agrego el item (ref public.usuarios)
    notas           TEXT,
    unidad_medida   VARCHAR(50),               -- presentacion elegida (ej. "CAJA"), NULL = unidad base
    factor_unidad   DECIMAL(10,2) NOT NULL DEFAULT 1  -- cuantas unidades base equivale (12 para CAJA de 12)
);
CREATE INDEX IF NOT EXISTS idx_comanda_items_comanda ON comanda_items (comanda_id);

CREATE TABLE IF NOT EXISTS proveedores (
    id               SERIAL PRIMARY KEY,
    ruc              VARCHAR(20)  UNIQUE,
    razon_social     VARCHAR(200) NOT NULL,
    nombre_comercial VARCHAR(200),
    telefono         VARCHAR(20),
    email            VARCHAR(100),
    direccion        TEXT,
    contacto_nombre  VARCHAR(150),
    activo           BOOLEAN   DEFAULT TRUE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ingresos (
    id              SERIAL PRIMARY KEY,
    numero_ingreso  VARCHAR(20)   UNIQUE NOT NULL,
    proveedor_id    INTEGER       REFERENCES proveedores(id),
    usuario_id      INTEGER,      -- referencia a public.usuarios
    total           DECIMAL(10,2) DEFAULT 0,
    estado          VARCHAR(20)   DEFAULT 'completado',
    observaciones   TEXT,
    tipo_pago       VARCHAR(30)   DEFAULT 'efectivo',
    orden_compra_id INTEGER,      -- FK agregada mas abajo, una vez creada ordenes_compra
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ingreso_detalles (
    id              SERIAL PRIMARY KEY,
    ingreso_id      INTEGER       REFERENCES ingresos(id) ON DELETE CASCADE,
    producto_id     INTEGER       REFERENCES productos(id),
    cantidad        DECIMAL(10,2) NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal        DECIMAL(10,2) NOT NULL,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS salidas (
    id              SERIAL PRIMARY KEY,
    numero_salida   VARCHAR(20)   UNIQUE NOT NULL,
    motivo          VARCHAR(20)   NOT NULL DEFAULT 'otro',   -- merma | vencimiento | devolucion | otro
    usuario         VARCHAR(100),
    usuario_id      INTEGER,      -- referencia a public.usuarios
    total           DECIMAL(10,2) DEFAULT 0,
    estado          VARCHAR(20)   DEFAULT 'completado',      -- completado | anulado
    observaciones   TEXT,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS salida_detalles (
    id              SERIAL PRIMARY KEY,
    salida_id       INTEGER       REFERENCES salidas(id) ON DELETE CASCADE,
    producto_id     INTEGER       REFERENCES productos(id),
    cantidad        DECIMAL(10,2) NOT NULL,
    costo_unitario  DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal        DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS caja_movimientos (
    id         SERIAL PRIMARY KEY,
    caja_id    INTEGER       REFERENCES cajas(id),
    tipo       VARCHAR(20)   NOT NULL,   -- ingreso | egreso
    concepto   VARCHAR(200),
    monto      DECIMAL(10,2) NOT NULL,
    usuario    VARCHAR(100),
    usuario_id INTEGER,      -- referencia a public.usuarios
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS comprobantes_electronicos (
    id                    SERIAL PRIMARY KEY,
    venta_id              INTEGER     REFERENCES ventas(id),
    tipo                  VARCHAR(20) NOT NULL,   -- boleta | factura
    serie                 VARCHAR(10) NOT NULL,
    numero                INTEGER     NOT NULL,
    numero_completo       VARCHAR(20) NOT NULL,
    estado_sunat          VARCHAR(100),
    ambiente_sunat        VARCHAR(20),  -- 'produccion' | 'beta' -- ambiente real usado al enviar (sunat_server del tenant en ese momento)
    enlace_del_pdf        TEXT,
    enlace_del_xml        TEXT,
    enlace_del_cdr        TEXT,
    cadena_para_codigo_qr TEXT,
    nubefact_response     TEXT,
    referencia_comprobante_id INTEGER REFERENCES comprobantes_electronicos(id),
    tipo_nota_credito_id  INTEGER REFERENCES public.fe_tipos_nota_credito(id),
    codigo_tipo_nota_credito VARCHAR(2),
    motivo_nota_credito   TEXT,
    descripcion_nota_credito TEXT,
    documento_modificado_tipo_documento_codigo VARCHAR(2),
    documento_modificado_serie VARCHAR(10),
    documento_modificado_numero VARCHAR(20),
    documento_modificado_numero_completo VARCHAR(30),
    documento_modificado_fecha DATE,
    -- Campos de facturacion electronica (migration_09/22)
    codigo_tipo_documento VARCHAR(2),
    fecha_emision         DATE,
    ticket_sunat          VARCHAR(100),
    codigo_respuesta      VARCHAR(20),
    mensaje_respuesta     TEXT,
    hash_cpe              TEXT,
    soap_request          TEXT,
    soap_response         TEXT,
    payload_json          JSONB,
    sunat_response_json   JSONB,
    -- Reenvio automatico a SUNAT (migration_58). El worker lee estos campos.
    reenvio_intentos      INTEGER   DEFAULT 0,
    reenvio_proximo_at    TIMESTAMP,
    reenvio_ultimo_at     TIMESTAMP,
    reenvio_ultimo_error  TEXT,
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    anulado               BOOLEAN DEFAULT FALSE,
    created_at            TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS series_comprobantes (
    tipo            VARCHAR(20)  PRIMARY KEY,  -- boleta | factura
    serie           VARCHAR(10)  NOT NULL,
    ultimo_numero   INTEGER      DEFAULT 0,
    -- Campos de facturacion electronica (migration_09)
    tipo_documento_id     INTEGER REFERENCES public.fe_tipos_documento(id),
    codigo_tipo_documento VARCHAR(2),
    descripcion           VARCHAR(100),
    anexo_establecimiento VARCHAR(4) DEFAULT '0000',
    activo                BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS ordenes_compra (
    id              SERIAL PRIMARY KEY,
    numero_orden    VARCHAR(20)   UNIQUE NOT NULL,
    proveedor_id    INTEGER       REFERENCES proveedores(id),
    usuario_id      INTEGER,
    estado          VARCHAR(20)   DEFAULT 'borrador',  -- borrador | pendiente | aprobada | recibida | cancelada
    tipo_pago       VARCHAR(30)   DEFAULT 'efectivo',  -- efectivo | credito | transferencia
    dias_credito    INTEGER       DEFAULT 0,
    subtotal        DECIMAL(10,2) DEFAULT 0,
    igv             DECIMAL(10,2) DEFAULT 0,
    costo_envio     DECIMAL(10,2) DEFAULT 0,
    total           DECIMAL(10,2) DEFAULT 0,
    numero_factura  VARCHAR(30),
    observaciones   TEXT,
    fecha_entrega   DATE,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'ingresos_orden_compra_id_fkey'
    ) THEN
        ALTER TABLE ingresos ADD CONSTRAINT ingresos_orden_compra_id_fkey
            FOREIGN KEY (orden_compra_id) REFERENCES ordenes_compra(id);
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS orden_compra_detalles (
    id              SERIAL PRIMARY KEY,
    orden_id        INTEGER       NOT NULL REFERENCES ordenes_compra(id) ON DELETE CASCADE,
    producto_id     INTEGER       REFERENCES productos(id),
    descripcion     VARCHAR(200),
    cantidad        DECIMAL(10,2) NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal        DECIMAL(10,2) NOT NULL,
    unidad_medida   VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS cuentas_por_pagar (
    id                SERIAL PRIMARY KEY,
    proveedor_id      INTEGER       REFERENCES proveedores(id),
    ingreso_id        INTEGER       REFERENCES ingresos(id),
    orden_compra_id   INTEGER       REFERENCES ordenes_compra(id),
    numero_doc        VARCHAR(50),
    monto_total       DECIMAL(10,2) NOT NULL,
    monto_pagado      DECIMAL(10,2) DEFAULT 0,
    monto_pendiente   DECIMAL(10,2) NOT NULL,
    estado            VARCHAR(20)   DEFAULT 'pendiente',  -- pendiente | parcial | pagado | vencido
    fecha_vencimiento DATE,
    created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pagos_proveedor (
    id          SERIAL PRIMARY KEY,
    cuenta_id   INTEGER       NOT NULL REFERENCES cuentas_por_pagar(id),
    monto       DECIMAL(10,2) NOT NULL,
    metodo_pago VARCHAR(30)   DEFAULT 'efectivo',
    referencia  VARCHAR(100),
    usuario_id  INTEGER,
    notas       TEXT,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- Cuentas por cobrar (ventas al crédito) -- migration_19/19b
CREATE TABLE IF NOT EXISTS cuentas_por_cobrar (
    id                SERIAL PRIMARY KEY,
    cliente_id        INTEGER       REFERENCES clientes(id),
    venta_id          INTEGER       REFERENCES ventas(id),
    numero_doc        VARCHAR(50),
    monto_total       DECIMAL(10,2) NOT NULL,
    monto_pagado      DECIMAL(10,2) DEFAULT 0,
    monto_pendiente   DECIMAL(10,2) NOT NULL,
    estado            VARCHAR(20)   DEFAULT 'pendiente',  -- pendiente | parcial | pagado | vencido
    fecha_vencimiento DATE,
    notas             TEXT,
    created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cobros_cliente (
    id          SERIAL PRIMARY KEY,
    cuenta_id   INTEGER       NOT NULL REFERENCES cuentas_por_cobrar(id),
    monto       DECIMAL(10,2) NOT NULL,
    metodo_pago VARCHAR(30)   DEFAULT 'efectivo',
    referencia  VARCHAR(100),
    usuario_id  INTEGER,
    notas       TEXT,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS gastos (
    id                 SERIAL PRIMARY KEY,
    caja_id            INTEGER       REFERENCES cajas(id),
    descripcion        VARCHAR(200)  NOT NULL,
    proveedor          VARCHAR(150),
    numero_comprobante VARCHAR(50),
    monto              DECIMAL(10,2) NOT NULL,
    metodo_pago        VARCHAR(30)   DEFAULT 'efectivo',
    usuario_id         INTEGER,
    created_at         TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- Toma de Inventario: sesion de conteo fisico (cabecera) + una fila
-- de detalle por producto incluido. El conteo se guarda aparte de
-- productos.stock; las diferencias solo se aplican al cerrar la
-- sesion (accion toma_aplicar en inventario/api.php).
CREATE TABLE IF NOT EXISTS toma_inventario_sesiones (
    id                 SERIAL PRIMARY KEY,
    codigo             VARCHAR(30)   UNIQUE NOT NULL,
    nombre             VARCHAR(150),
    categorias_ids     INTEGER[]     NOT NULL,
    fecha_inicio       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado             VARCHAR(20)   NOT NULL DEFAULT 'activa',
    total_productos    INTEGER       NOT NULL DEFAULT 0,
    total_contados     INTEGER       NOT NULL DEFAULT 0,
    usuario_creador_id INTEGER,
    usuario_cierre_id  INTEGER,
    observaciones      TEXT,
    fecha_cierre       TIMESTAMP,
    created_at         TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_toma_inv_sesiones_estado CHECK (estado IN ('activa', 'completada', 'cancelada'))
);

CREATE INDEX IF NOT EXISTS idx_toma_inv_sesiones_estado
    ON toma_inventario_sesiones (estado, created_at DESC);

CREATE TABLE IF NOT EXISTS toma_inventario_detalles (
    id                SERIAL PRIMARY KEY,
    sesion_id         INTEGER       NOT NULL REFERENCES toma_inventario_sesiones(id) ON DELETE CASCADE,
    producto_id       INTEGER       REFERENCES productos(id) ON DELETE SET NULL,
    producto_codigo   VARCHAR(50)   NOT NULL,
    producto_nombre   VARCHAR(200)  NOT NULL,
    categoria_id      INTEGER,
    categoria_nombre  VARCHAR(100),
    unidad            VARCHAR(50),
    stock_sistema     DECIMAL(10,2) NOT NULL,
    cantidad_contada  DECIMAL(10,2),
    diferencia        DECIMAL(10,2),
    usuario_conteo_id INTEGER,
    contado_en        TIMESTAMP,
    aplicado          BOOLEAN       NOT NULL DEFAULT FALSE,
    aplicado_en       TIMESTAMP,
    stock_antes_aplicar DECIMAL(10,2),
    created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_toma_inv_detalles_cantidad CHECK (cantidad_contada IS NULL OR cantidad_contada >= 0)
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_toma_inv_detalles_sesion_producto
    ON toma_inventario_detalles (sesion_id, producto_id) WHERE producto_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_toma_inv_detalles_sesion
    ON toma_inventario_detalles (sesion_id);

-- Categorías: globales en public.categorias (migration_21) -- ya vienen
-- seedeadas ahi (Medicamentos, Vitaminas y Suplementos, Cuidado Personal,
-- Primeros Auxilios, Bebés y Niños, Genéricos), no hace falta reinsertarlas
-- por sucursal.

-- Promociones (migration_56) -- descuentos temporales sobre productos
-- puntuales, tipicamente generados desde el reporte "Stock Paralizado".
-- Regla de seguridad (aplicada en PHP): si un producto tiene mas de una
-- promocion vigente a la vez, siempre se cobra el precio MAS BAJO
-- resultante -- por eso no hay restriccion que impida superponerlas.
CREATE TABLE IF NOT EXISTS promociones (
    id              SERIAL PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,
    descripcion     TEXT,
    tipo_descuento  VARCHAR(20)   NOT NULL DEFAULT 'porcentaje',
    valor_descuento DECIMAL(10,2) NOT NULL,
    fecha_inicio    DATE NOT NULL,
    fecha_fin       DATE NOT NULL,
    activo          BOOLEAN DEFAULT TRUE,
    creado_por      INTEGER, -- usuario_id, sin FK cross-schema (mismo criterio que ventas.usuario_id)
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_promociones_tipo_descuento CHECK (tipo_descuento IN ('porcentaje', 'monto_fijo')),
    CONSTRAINT chk_promociones_fechas CHECK (fecha_fin >= fecha_inicio),
    CONSTRAINT chk_promociones_valor_positivo CHECK (valor_descuento > 0)
);

CREATE TABLE IF NOT EXISTS promocion_productos (
    id            SERIAL PRIMARY KEY,
    promocion_id  INTEGER NOT NULL REFERENCES promociones(id) ON DELETE CASCADE,
    producto_id   INTEGER NOT NULL REFERENCES productos(id)   ON DELETE CASCADE,
    UNIQUE (promocion_id, producto_id)
);

CREATE INDEX IF NOT EXISTS idx_promocion_productos_producto ON promocion_productos (producto_id);
CREATE INDEX IF NOT EXISTS idx_promociones_vigencia ON promociones (activo, fecha_inicio, fecha_fin);

INSERT INTO clientes (nombres, apellidos, dni, telefono)
VALUES ('Cliente', 'General', '00000000', '000000000')
ON CONFLICT DO NOTHING;

INSERT INTO series_comprobantes (tipo, serie, ultimo_numero, codigo_tipo_documento, descripcion, anexo_establecimiento, activo) VALUES
    ('boleta',               'B001', 0, '03', 'Boleta de venta',       '0000', TRUE),
    ('factura',              'F001', 0, '01', 'Factura',               '0000', TRUE),
    ('ticket',               'TK',   0, '02', 'Nota de venta',         '0000', TRUE),
    ('nota_credito',         'NC01', 0, '07', 'Nota de credito',       '0000', TRUE),
    ('nota_credito_boleta',  'BC01', 0, '07', 'Nota de credito boleta','0000', TRUE),
    ('nota_credito_factura', 'FC01', 0, '07', 'Nota de credito factura','0000', TRUE)
ON CONFLICT (tipo) DO NOTHING;
