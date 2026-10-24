<?php
$__bgPath = __DIR__ . '/../assets/img/gravity-bg.jpg';
$__bgVer = @filemtime($__bgPath) ?: 0;
$__bgUrl = (function_exists('app_public_prefix') ? app_public_prefix() : '/gravity') . '/assets/img/gravity-bg.jpg?v=' . $__bgVer;
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gravity — Sistema para farmacias</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/logos/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, sans-serif;
            color: #1e293b;
            background: #f8fafc;
        }

        /* ---- Hero (fondo Gravity: pozo gravitatorio azul sobre negro) ---- */
        .hero {
            position: relative;
            background:
                linear-gradient(135deg, rgba(3,3,8,.84) 0%, rgba(2,24,48,.74) 50%, rgba(3,3,8,.86) 100%),
                url('<?= htmlspecialchars($__bgUrl) ?>') center center / cover no-repeat;
            color: #fff;
            padding: 96px 24px 128px;
            text-align: center;
            overflow: hidden;
        }
        .hero-icon {
            width: 64px; height: 64px; margin: 0 auto 18px;
            background: rgba(4,138,245,.15);
            border: 1px solid rgba(4,138,245,.35);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem;
            color: #9ec8ff;
            backdrop-filter: blur(6px);
        }
        .hero h1 { font-size: 2.6rem; font-weight: 800; margin-bottom: 10px; letter-spacing: -.02em; text-shadow: 0 2px 12px rgba(0,0,0,.55); }
        .hero p { font-size: 1.02rem; color: rgba(255,255,255,.94); max-width: 520px; margin: 0 auto 28px; line-height: 1.55; text-shadow: 0 1px 8px rgba(0,0,0,.75); }
        .btn-login {
            display: inline-flex; align-items: center; gap: 9px;
            background: linear-gradient(135deg, #048af5, #0471cd);
            color: #fff; font-weight: 700; font-size: .95rem;
            padding: 13px 30px; border-radius: 10px;
            text-decoration: none;
            box-shadow: 0 12px 30px rgba(4,113,205,.45), 0 0 0 1px rgba(4,138,245,.3) inset;
            transition: opacity .15s, transform .15s;
        }
        .btn-login:hover { opacity: .92; transform: translateY(-1px); }

        /* ---- Features ---- */
        .features {
            position: relative;
            z-index: 1;
            max-width: 1040px;
            margin: -56px auto 64px;
            padding: 0 24px;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 18px;
        }
        .feature-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px 22px;
            box-shadow: 0 10px 30px rgba(15,23,42,.06);
        }
        .feature-icon {
            width: 42px; height: 42px;
            background: #e6efff; color: #0471cd;
            border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 14px;
        }
        .feature-card h3 { font-size: .98rem; font-weight: 700; margin-bottom: 6px; }
        .feature-card p { font-size: .84rem; color: #64748b; line-height: 1.5; }

        footer {
            text-align: center;
            padding: 28px 24px 40px;
            font-size: .78rem;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    <section class="hero">
        <div class="hero-icon"><i class="fas fa-rocket"></i></div>
        <h1>Gravity</h1>
        <p>Sistema de punto de venta y gestión para farmacias, con soporte multi-sucursal y facturación electrónica SUNAT.</p>
        <a class="btn-login" href="modules/auth/login.php">
            <i class="fas fa-right-to-bracket"></i> Iniciar sesión
        </a>
    </section>

    <section class="features">
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-cash-register"></i></div>
                <h3>Punto de venta</h3>
                <p>Ventas rápidas con lector de código de barras, favoritos y control de caja por turno.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-building"></i></div>
                <h3>Multi-sucursal y multi-empresa</h3>
                <p>Cada sucursal con su propio inventario y caja; varias empresas administradas desde un solo sistema.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-file-invoice"></i></div>
                <h3>Facturación electrónica SUNAT</h3>
                <p>Boletas, facturas y notas de crédito enviadas directo a SUNAT, con reportes de rentabilidad.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-boxes-stacked"></i></div>
                <h3>Inventario y almacén</h3>
                <p>Control de stock, ingresos de mercadería, proveedores y traslados entre sucursales.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                <h3>Compras y cobranzas</h3>
                <p>Órdenes de compra, cuentas por pagar y por cobrar, todo integrado con el inventario.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <h3>Reportes y dashboard</h3>
                <p>Resumen diario de ventas, alertas de stock y productos más vendidos en tiempo real.</p>
            </div>
        </div>
    </section>

    <footer>
        &copy; <?= date('Y') ?> Gravity
    </footer>

</body>
</html>
<?php exit; ?>
