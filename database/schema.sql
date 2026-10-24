-- ============================================================
-- ARCHIVO: farmacia/database/schema.sql
-- DESCRIPCIÓN: Esquema de base de datos para Sistema de Farmacia
-- EJECUTAR:
--   psql -U postgres -c "CREATE DATABASE farmacia;"
--   psql -U postgres -d farmacia -f schema.sql
-- ============================================================

-- Extensiones
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ============================================================
-- TABLA: categorias
-- ============================================================
CREATE TABLE IF NOT EXISTS categorias (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLA: productos
-- ============================================================
CREATE TABLE IF NOT EXISTS productos (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    categoria_id INTEGER REFERENCES categorias(id),
    precio_compra DECIMAL(10,2) DEFAULT 0,
    precio_venta DECIMAL(10,2) NOT NULL,
    stock INTEGER DEFAULT 0,
    stock_minimo INTEGER DEFAULT 5,
    unidad VARCHAR(50) DEFAULT 'unidad',
    laboratorio VARCHAR(100),
    presentacion VARCHAR(100),
    requiere_receta BOOLEAN DEFAULT FALSE,
    activo BOOLEAN DEFAULT TRUE,
    favorito BOOLEAN DEFAULT FALSE,
    total_vendido DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLA: clientes
-- ============================================================
CREATE TABLE IF NOT EXISTS clientes (
    id SERIAL PRIMARY KEY,
    nombres VARCHAR(150) NOT NULL,
    apellidos VARCHAR(150),
    dni VARCHAR(20) UNIQUE,
    ruc VARCHAR(20),
    telefono VARCHAR(20),
    email VARCHAR(100),
    direccion TEXT,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLA: cajas
-- ============================================================
CREATE TABLE IF NOT EXISTS cajas (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) DEFAULT 'Caja Principal',
    saldo_inicial DECIMAL(10,2) DEFAULT 0,
    saldo_actual DECIMAL(10,2) DEFAULT 0,
    estado VARCHAR(20) DEFAULT 'cerrada', -- abierta, cerrada
    apertura_at TIMESTAMP,
    cierre_at TIMESTAMP,
    usuario_apertura VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLA: ventas
-- ============================================================
CREATE TABLE IF NOT EXISTS ventas (
    id SERIAL PRIMARY KEY,
    numero_venta VARCHAR(20) UNIQUE NOT NULL,
    cliente_id INTEGER REFERENCES clientes(id),
    caja_id INTEGER REFERENCES cajas(id),
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    descuento DECIMAL(10,2) DEFAULT 0,
    igv DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    tipo_pago VARCHAR(30) DEFAULT 'efectivo', -- efectivo, yape, plin, tarjeta, transferencia
    tipo_comprobante VARCHAR(20) DEFAULT 'boleta', -- boleta, factura, ticket
    estado VARCHAR(20) DEFAULT 'completada', -- completada, anulada, pendiente
    observaciones TEXT,
    vendedor VARCHAR(100) DEFAULT 'Administrador',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLA: venta_detalles
-- ============================================================
CREATE TABLE IF NOT EXISTS venta_detalles (
    id SERIAL PRIMARY KEY,
    venta_id INTEGER REFERENCES ventas(id) ON DELETE CASCADE,
    producto_id INTEGER REFERENCES productos(id),
    cantidad INTEGER NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    descuento DECIMAL(10,2) DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- DATOS DE EJEMPLO
-- ============================================================
INSERT INTO categorias (nombre, descripcion) VALUES
('Medicamentos', 'Fármacos y medicamentos en general'),
('Vitaminas y Suplementos', 'Vitaminas, minerales y suplementos nutricionales'),
('Cuidado Personal', 'Productos de higiene y cuidado personal'),
('Primeros Auxilios', 'Materiales de curación y primeros auxilios'),
('Bebés y Niños', 'Productos para bebés y niños'),
('Genéricos', 'Medicamentos genéricos');

INSERT INTO clientes (nombres, apellidos, dni, telefono) VALUES
('Cliente', 'General', '00000000', '000000000'),
('María', 'García López', '45678901', '987654321'),
('Carlos', 'Mendoza Torres', '32145678', '976543210');

INSERT INTO productos (codigo, nombre, categoria_id, precio_compra, precio_venta, stock, stock_minimo, laboratorio, presentacion, favorito, total_vendido) VALUES
('MED001', 'Paracetamol 500mg x 10 tab',   1,  1.50,  3.50, 150, 20, 'Genfarma',        'Tabletas x 10',  TRUE,  245),
('MED002', 'Ibuprofeno 400mg x 10 tab',    1,  2.00,  4.50,  80, 15, 'Bayer',           'Tabletas x 10',  TRUE,  189),
('MED003', 'Amoxicilina 500mg x 21 cap',   1,  8.00, 18.00,  45, 10, 'GlaxoSmithKline', 'Cápsulas x 21',  FALSE,  67),
('VIT001', 'Vitamina C 500mg x 30 tab',    2,  5.00, 12.00, 120, 20, 'Farmex',          'Tabletas x 30',  TRUE,  312),
('VIT002', 'Complejo B x 30 tab',          2,  4.50, 10.00,  90, 15, 'Farmindustria',   'Tabletas x 30',  FALSE, 145),
('CUI001', 'Alcohol 70% 250ml',            3,  2.00,  4.50, 200, 30, 'Genérico',        'Frasco 250ml',   TRUE,  420),
('CUI002', 'Algodón 100g',                 4,  1.50,  3.50, 180, 25, 'Genérico',        'Bolsa 100g',     TRUE,  380),
('PRI001', 'Agua Oxigenada 120ml',         4,  1.00,  2.50, 160, 20, 'Genérico',        'Frasco 120ml',   FALSE, 220),
('PRI002', 'Gasa estéril 10x10 x5',        4,  2.50,  5.50, 100, 15, 'Medilene',        'Pack x 5',       FALSE,  98),
('GEN001', 'Omeprazol 20mg x 14 cap',      6,  3.00,  7.00,  75, 10, 'Genfarma',        'Cápsulas x 14',  TRUE,  178);

INSERT INTO cajas (nombre, saldo_inicial, saldo_actual, estado, apertura_at, usuario_apertura) VALUES
('Caja Principal', 200.00, 200.00, 'abierta', CURRENT_TIMESTAMP, 'Administrador');
