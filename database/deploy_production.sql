-- ============================================================
-- ARCHIVO: farmacia/database/deploy_production.sql
-- DESCRIPCIÓN: Script completo de instalación en producción.
--              Ejecutar UNA SOLA VEZ en el servidor de producción.
--
-- PASOS PREVIOS:
--   1. Crear la base de datos:
--      psql -U postgres -c "CREATE DATABASE farmacia;"
--
--   2. Ejecutar este archivo:
--      psql -U postgres -d farmacia -f database/deploy_production.sql
--
--   3. Acceder al setup inicial para crear la primera empresa:
--      http://tudominio.com/farmacia/database/setup.php
--
--   4. Configurar SMTP en config/mail.php
--
-- NOTA: Todas las sentencias usan IF NOT EXISTS / IF EXISTS,
--       por lo que es seguro ejecutar este archivo más de una vez.
-- ============================================================


-- ==========================
-- SECCIÓN 1: TABLAS GLOBALES
-- ==========================

CREATE TABLE IF NOT EXISTS public.tenants (
    id            SERIAL PRIMARY KEY,
    nombre        VARCHAR(100) NOT NULL,
    slug          VARCHAR(30)  UNIQUE NOT NULL,
    plan          VARCHAR(20)  DEFAULT 'basico',
    url           VARCHAR(200),
    ruc           VARCHAR(20),
    business_name VARCHAR(255),
    trade_name    VARCHAR(255),
    email         VARCHAR(150),
    telefono      VARCHAR(20),
    direccion     TEXT,
    country_code  VARCHAR(2) DEFAULT 'PE',
    ubigeo        VARCHAR(6),
    departamento  VARCHAR(100),
    provincia     VARCHAR(100),
    distrito      VARCHAR(100),
    api_url       VARCHAR(255),
    whatsapp_instance    VARCHAR(255),
    tax_enabled   BOOLEAN   DEFAULT TRUE,
    sunat_username       VARCHAR(100),
    sunat_password       VARCHAR(255),
    gre_client_id        VARCHAR(255),
    gre_client_secret    TEXT,
    certificate_path     VARCHAR(255),
    certificate_password VARCHAR(255),
    certificate_expires_at DATE,
    sunat_server         VARCHAR(10),
    activo        BOOLEAN   DEFAULT TRUE,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.sucursales (
    id          SERIAL PRIMARY KEY,
    tenant_id   INTEGER      NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
    nombre      VARCHAR(100) NOT NULL,
    schema_name VARCHAR(60)  UNIQUE NOT NULL,
    direccion   TEXT,
    telefono    VARCHAR(20),
    activo      BOOLEAN   DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.usuarios (
    id            SERIAL PRIMARY KEY,
    tenant_id     INTEGER      REFERENCES public.tenants(id) ON DELETE CASCADE,
    nombre        VARCHAR(100) NOT NULL,
    apellido      VARCHAR(100),
    username      VARCHAR(50)  UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email         VARCHAR(150),
    activo        BOOLEAN   DEFAULT TRUE,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.usuario_sucursal (
    id          SERIAL PRIMARY KEY,
    usuario_id  INTEGER NOT NULL REFERENCES public.usuarios(id)   ON DELETE CASCADE,
    sucursal_id INTEGER NOT NULL REFERENCES public.sucursales(id) ON DELETE CASCADE,
    rol         VARCHAR(20) NOT NULL DEFAULT 'cajero',
    activo      BOOLEAN DEFAULT TRUE,
    UNIQUE (usuario_id, sucursal_id)
);

CREATE TABLE IF NOT EXISTS public.password_resets (
    id         SERIAL PRIMARY KEY,
    usuario_id INTEGER      NOT NULL REFERENCES public.usuarios(id) ON DELETE CASCADE,
    token      VARCHAR(100) NOT NULL UNIQUE,
    expires_at TIMESTAMP    NOT NULL,
    used       BOOLEAN   DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_password_resets_token      ON public.password_resets (token);
CREATE INDEX IF NOT EXISTS idx_password_resets_usuario_id ON public.password_resets (usuario_id);

CREATE TABLE IF NOT EXISTS public.tenant_config (
    id             SERIAL PRIMARY KEY,
    tenant_id      INTEGER NOT NULL UNIQUE REFERENCES public.tenants(id) ON DELETE CASCADE,
    nombre_sistema VARCHAR(100) NOT NULL DEFAULT 'Gravity',
    logo_path      VARCHAR(255),
    updated_at     TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS public.ubigeo_departamentos (
    codigo     VARCHAR(2) PRIMARY KEY,
    nombre     VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.ubigeo_provincias (
    codigo               VARCHAR(4) PRIMARY KEY,
    departamento_codigo  VARCHAR(2) NOT NULL REFERENCES public.ubigeo_departamentos(codigo),
    nombre               VARCHAR(100) NOT NULL,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.ubigeo_distritos (
    codigo               VARCHAR(6) PRIMARY KEY,
    provincia_codigo     VARCHAR(4) NOT NULL REFERENCES public.ubigeo_provincias(codigo),
    departamento_codigo  VARCHAR(2) NOT NULL REFERENCES public.ubigeo_departamentos(codigo),
    nombre               VARCHAR(100) NOT NULL,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_ubigeo_provincias_departamento
    ON public.ubigeo_provincias (departamento_codigo);

CREATE INDEX IF NOT EXISTS idx_ubigeo_distritos_provincia
    ON public.ubigeo_distritos (provincia_codigo);

CREATE INDEX IF NOT EXISTS idx_ubigeo_distritos_departamento
    ON public.ubigeo_distritos (departamento_codigo);

CREATE TABLE IF NOT EXISTS public.fe_tipos_documento (
    id          SERIAL PRIMARY KEY,
    codigo      VARCHAR(2) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NOT NULL,
    estado      BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.fe_tipos_documento_identidad (
    id                    SERIAL PRIMARY KEY,
    codigo                VARCHAR(1) NOT NULL UNIQUE,
    descripcion           VARCHAR(255) NOT NULL,
    descripcion_documento VARCHAR(100) NOT NULL,
    estado                BOOLEAN DEFAULT TRUE,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.fe_unidades (
    id          SERIAL PRIMARY KEY,
    codigo      VARCHAR(3) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NOT NULL,
    estado      BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.fe_formas_pago (
    id          SERIAL PRIMARY KEY,
    descripcion VARCHAR(100) NOT NULL UNIQUE,
    estado      BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.fe_tipos_afectacion_igv (
    id          SERIAL PRIMARY KEY,
    codigo      VARCHAR(2) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NOT NULL,
    tipo        VARCHAR(5) NOT NULL,
    estado      BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.fe_monedas (
    id          SERIAL PRIMARY KEY,
    codigo      VARCHAR(3) NOT NULL UNIQUE,
    descripcion VARCHAR(100) NOT NULL,
    pais        VARCHAR(100) NOT NULL,
    simbolo     VARCHAR(5) NOT NULL,
    estado      BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.fe_tipos_nota_credito (
    id          SERIAL PRIMARY KEY,
    codigo      VARCHAR(2) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NOT NULL,
    estado      BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.fe_tipos_nota_debito (
    id          SERIAL PRIMARY KEY,
    codigo      VARCHAR(2) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NOT NULL,
    estado      BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.fe_tipos_operacion (
    id          SERIAL PRIMARY KEY,
    codigo      VARCHAR(4) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NOT NULL,
    estado      BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.ordenes_traslado (
    id                    SERIAL PRIMARY KEY,
    tenant_id             INTEGER NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
    numero_traslado       VARCHAR(30) NOT NULL,
    sucursal_origen_id    INTEGER NOT NULL REFERENCES public.sucursales(id) ON DELETE RESTRICT,
    sucursal_destino_id   INTEGER NOT NULL REFERENCES public.sucursales(id) ON DELETE RESTRICT,
    estado                VARCHAR(20) NOT NULL DEFAULT 'borrador',
    observaciones         TEXT,
    usuario_solicita_id   INTEGER REFERENCES public.usuarios(id) ON DELETE SET NULL,
    usuario_envia_id      INTEGER REFERENCES public.usuarios(id) ON DELETE SET NULL,
    usuario_recibe_id     INTEGER REFERENCES public.usuarios(id) ON DELETE SET NULL,
    fecha_solicitud       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_envio           TIMESTAMP,
    fecha_recepcion       TIMESTAMP,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_ordenes_traslado_estado
        CHECK (estado IN ('borrador', 'enviado', 'recibido', 'anulado')),
    CONSTRAINT chk_ordenes_traslado_sucursales_distintas
        CHECK (sucursal_origen_id <> sucursal_destino_id)
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_ordenes_traslado_tenant_numero
    ON public.ordenes_traslado (tenant_id, numero_traslado);

CREATE INDEX IF NOT EXISTS idx_ordenes_traslado_origen
    ON public.ordenes_traslado (sucursal_origen_id, estado, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_ordenes_traslado_destino
    ON public.ordenes_traslado (sucursal_destino_id, estado, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_ordenes_traslado_tenant
    ON public.ordenes_traslado (tenant_id, created_at DESC);

CREATE TABLE IF NOT EXISTS public.orden_traslado_detalles (
    id                      SERIAL PRIMARY KEY,
    traslado_id             INTEGER NOT NULL REFERENCES public.ordenes_traslado(id) ON DELETE CASCADE,
    producto_codigo         VARCHAR(50) NOT NULL,
    producto_nombre         VARCHAR(200) NOT NULL,
    cantidad                INTEGER NOT NULL,
    costo_unitario          DECIMAL(10,2) DEFAULT 0,
    stock_origen_snapshot   INTEGER DEFAULT 0,
    stock_destino_snapshot  INTEGER DEFAULT 0,
    observaciones           TEXT,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_orden_traslado_detalles_cantidad
        CHECK (cantidad > 0),
    CONSTRAINT chk_orden_traslado_detalles_stock_origen
        CHECK (stock_origen_snapshot >= 0),
    CONSTRAINT chk_orden_traslado_detalles_stock_destino
        CHECK (stock_destino_snapshot >= 0)
);

CREATE INDEX IF NOT EXISTS idx_orden_traslado_detalles_traslado
    ON public.orden_traslado_detalles (traslado_id);

CREATE INDEX IF NOT EXISTS idx_orden_traslado_detalles_codigo
    ON public.orden_traslado_detalles (producto_codigo);

-- Compatibilidad con instalaciones anteriores
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS url       VARCHAR(200);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS ruc       VARCHAR(20);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS business_name VARCHAR(255);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS trade_name    VARCHAR(255);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS email         VARCHAR(150);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS telefono  VARCHAR(20);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS direccion TEXT;
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS country_code  VARCHAR(2) DEFAULT 'PE';
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS ubigeo        VARCHAR(6);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS departamento  VARCHAR(100);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS provincia     VARCHAR(100);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS distrito      VARCHAR(100);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS api_url       VARCHAR(255);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS whatsapp_instance    VARCHAR(255);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS tax_enabled   BOOLEAN DEFAULT TRUE;
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS sunat_username       VARCHAR(100);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS sunat_password       VARCHAR(255);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS gre_client_id        VARCHAR(255);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS gre_client_secret    TEXT;
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS certificate_path     VARCHAR(255);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS certificate_password VARCHAR(255);
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS certificate_expires_at DATE;
ALTER TABLE public.tenants  ADD COLUMN IF NOT EXISTS sunat_server         VARCHAR(10);
ALTER TABLE public.usuarios ADD COLUMN IF NOT EXISTS email     VARCHAR(150);


-- ==========================
-- SECCIÓN 2: TABLAS POR SUCURSAL (schema de ejemplo: sucursal_schema)
-- ==========================
-- NOTA: Estas tablas se crean automáticamente cuando se registra
--       una sucursal desde el panel superadmin o desde setup.php.
--       El archivo database/schema_sucursal.sql contiene la definición
--       completa y es ejecutado por el sistema en cada nueva sucursal.
--
-- Si necesitas crearlas manualmente para un schema existente:
--   SET search_path TO nombre_schema, public;
--   \i database/schema_sucursal.sql


-- ==========================
-- SECCIÓN 3: MIGRACIONES ACUMULADAS (para bases existentes)
-- ==========================
-- Las siguientes migraciones están incluidas en schema_sucursal.sql
-- para instalaciones nuevas. Para bases existentes, ejecutar por cada
-- schema de sucursal:
--
--   SET search_path TO nombre_schema, public;
--   \i database/migration_compras.sql
--   \i database/migration_gastos.sql
--
-- Para tablas globales nuevas (public):
--   \i database/migration_15_traslados_stock.sql
