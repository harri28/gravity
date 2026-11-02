<?php
$__bgPath = __DIR__ . '/../assets/img/gravity-bg.jpg';
$__bgVer = @filemtime($__bgPath) ?: 0;
$__prefix = (function_exists('app_public_prefix') ? app_public_prefix() : '/gravity');
$__bgUrl = $__prefix . '/assets/img/gravity-bg.jpg?v=' . $__bgVer;
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gravity — Ventas, stock y SUNAT en un solo sistema</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/logos/favicon.svg?v=<?= @filemtime(__DIR__ . '/../assets/img/logos/favicon.svg') ?: 1 ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #048af5;
            --primary-dark: #0471cd;
            --ink: #1e293b;
            --muted: #64748b;
            --line: #e2e8f0;
        }
        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, sans-serif;
            color: var(--ink);
            background: #f8fafc;
            line-height: 1.55;
        }
        .wrap { max-width: 1040px; margin: 0 auto; padding: 0 24px; }
        .section-tag {
            display: inline-block;
            font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
            color: var(--primary-dark);
            background: #e6efff;
            padding: 6px 13px; border-radius: 999px;
            margin-bottom: 14px;
        }
        .section-title {
            font-size: 1.9rem; font-weight: 800; letter-spacing: -.02em;
            margin-bottom: 10px;
        }
        .section-sub { font-size: 1rem; color: var(--muted); max-width: 560px; }

        /* ---- Hero ---- */
        .hero {
            position: relative;
            background:
                linear-gradient(135deg, rgba(3,3,8,.86) 0%, rgba(2,24,48,.76) 50%, rgba(3,3,8,.88) 100%),
                url('<?= htmlspecialchars($__bgUrl) ?>') center center / cover no-repeat;
            color: #fff;
            padding: 84px 24px 96px;
            text-align: center;
            overflow: hidden;
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: .78rem; font-weight: 600;
            color: #9ec8ff;
            background: rgba(4,138,245,.14);
            border: 1px solid rgba(4,138,245,.35);
            padding: 7px 15px; border-radius: 999px;
            margin-bottom: 22px;
            backdrop-filter: blur(6px);
        }
        .hero h1 {
            font-size: 2.55rem; font-weight: 800; letter-spacing: -.025em;
            max-width: 780px; margin: 0 auto 16px;
            text-shadow: 0 2px 14px rgba(0,0,0,.55);
        }
        .hero h1 .pil { white-space: nowrap; }
        .hero h1 .pil i { font-size: .72em; margin-right: 4px; }
        .hero h1 .c-ventas { color: #7ee0b0; }
        .hero h1 .c-stock  { color: #ffd27a; }
        .hero h1 .c-sunat  { color: #9ec8ff; }
        .hero p {
            font-size: 1.04rem; color: rgba(255,255,255,.93);
            max-width: 560px; margin: 0 auto 30px;
            text-shadow: 0 1px 8px rgba(0,0,0,.7);
        }
        .btn-login {
            display: inline-flex; align-items: center; gap: 9px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; font-weight: 700; font-size: .98rem;
            padding: 14px 32px; border-radius: 11px;
            text-decoration: none;
            box-shadow: 0 12px 30px rgba(4,113,205,.45), 0 0 0 1px rgba(4,138,245,.3) inset;
            transition: opacity .15s, transform .15s;
        }
        .btn-login:hover { opacity: .93; transform: translateY(-1px); }

        /* ---- Mockup del sistema (captura estilizada) ---- */
        .showcase { position: relative; z-index: 2; margin-top: -58px; }
        .mockup {
            max-width: 720px; margin: 0 auto;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: 0 30px 70px rgba(15,23,42,.18);
            overflow: hidden;
        }
        .mockup-bar {
            display: flex; align-items: center; gap: 7px;
            padding: 12px 16px; border-bottom: 1px solid var(--line);
            background: #f8fafc;
        }
        .mockup-bar .dot { width: 11px; height: 11px; border-radius: 50%; }
        .mockup-bar .dot.r { background: #ff5f57; }
        .mockup-bar .dot.y { background: #febc2e; }
        .mockup-bar .dot.g { background: #28c840; }
        .mockup-bar .url {
            margin-left: 12px; font-size: .74rem; color: #94a3b8;
            background: #fff; border: 1px solid var(--line);
            padding: 4px 12px; border-radius: 7px;
        }
        .mockup-body { padding: 24px; }
        .mockup-hi { font-size: .9rem; color: var(--muted); margin-bottom: 4px; }
        .mockup-grid { display: grid; grid-template-columns: 1.3fr 1fr; gap: 20px; }
        .mk-sales-label { font-size: .82rem; color: var(--muted); }
        .mk-sales {
            font-size: 2.5rem; font-weight: 800; letter-spacing: -.02em;
            color: var(--ink); line-height: 1.1; margin: 2px 0 6px;
        }
        .mk-trend { font-size: .82rem; font-weight: 700; color: #16a34a; }
        .mk-chart { display: flex; align-items: flex-end; gap: 7px; height: 92px; margin-top: 18px; }
        .mk-chart .b {
            flex: 1; background: linear-gradient(180deg, #86c0f8, #048af5);
            border-radius: 5px 5px 0 0;
            height: 0; transition: height 1.1s cubic-bezier(.22,1,.36,1);
        }
        .mk-side-title { font-size: .78rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 12px; }
        .mk-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 0; border-bottom: 1px solid #f1f5f9; }
        .mk-row:last-child { border-bottom: 0; }
        .mk-doc { font-size: .82rem; font-weight: 600; }
        .mk-doc small { display: block; font-weight: 500; color: #94a3b8; font-size: .72rem; }
        .mk-amt { font-size: .86rem; font-weight: 700; }
        .mk-tag { font-size: .66rem; font-weight: 700; padding: 2px 7px; border-radius: 5px; margin-right: 7px; }
        .mk-tag.b { background: #e6efff; color: #0471cd; }
        .mk-tag.f { background: #e9f9ef; color: #16a34a; }
        .mk-tag.n { background: #f1f5f9; color: #64748b; }

        /* ---- Bloques de contenido ---- */
        .block { padding: 72px 0; }
        .block.center { text-align: center; }
        .block.center .section-sub { margin-left: auto; margin-right: auto; }
        .block.alt { background: #fff; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }

        /* ---- Flujo ---- */
        .flow { display: flex; flex-wrap: wrap; justify-content: center; align-items: stretch; gap: 10px; margin-top: 40px; }
        .flow-node {
            flex: 0 0 auto; width: 128px;
            background: #fff; border: 1px solid var(--line); border-radius: 14px;
            padding: 18px 12px; text-align: center;
            box-shadow: 0 6px 18px rgba(15,23,42,.05);
        }
        .flow-node .ico {
            width: 44px; height: 44px; margin: 0 auto 10px;
            background: #e6efff; color: var(--primary-dark);
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem;
        }
        .flow-node .lbl { font-size: .8rem; font-weight: 700; }
        .flow-node.sunat .ico { background: #dcfce7; color: #16a34a; }
        .flow-arrow { display: flex; align-items: center; color: #cbd5e1; font-size: 1.1rem; }
        @media (max-width: 760px) {
            .flow-arrow { transform: rotate(90deg); }
            .flow { gap: 6px; }
        }

        /* ---- Procesos automáticos ---- */
        .auto-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; margin-top: 40px; }
        .auto-step {
            display: flex; gap: 14px; align-items: flex-start;
            background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 18px;
        }
        .auto-step .n {
            flex: 0 0 auto; width: 30px; height: 30px; border-radius: 9px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; font-weight: 800; font-size: .85rem;
            display: flex; align-items: center; justify-content: center;
        }
        .auto-step h4 { font-size: .92rem; font-weight: 700; margin-bottom: 2px; }
        .auto-step p { font-size: .82rem; color: var(--muted); }
        .auto-step.you .n { background: #0f172a; }

        /* ---- Módulos ---- */
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin-top: 40px; }
        .feature-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 24px 22px; box-shadow: 0 10px 30px rgba(15,23,42,.05); }
        .feature-icon { width: 42px; height: 42px; background: #e6efff; color: var(--primary-dark); border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 14px; }
        .feature-card h3 { font-size: .98rem; font-weight: 700; margin-bottom: 6px; }
        .feature-card p { font-size: .84rem; color: var(--muted); }

        /* ---- CTA final ---- */
        .cta {
            text-align: center; color: #fff;
            background: linear-gradient(135deg, #02386b, #048af5);
            padding: 70px 24px;
        }
        .cta h2 { font-size: 1.8rem; font-weight: 800; margin-bottom: 10px; }
        .cta p { color: rgba(255,255,255,.9); margin-bottom: 26px; }
        .cta .btn-login { background: #fff; color: var(--primary-dark); box-shadow: 0 12px 30px rgba(0,0,0,.25); }

        footer { text-align: center; padding: 28px 24px 40px; font-size: .78rem; color: #94a3b8; }

        @media (max-width: 760px) {
            .hero h1 { font-size: 1.95rem; }
            .mockup-grid { grid-template-columns: 1fr; }
            .section-title { font-size: 1.55rem; }
        }
    </style>
</head>
<body>

    <!-- ===== 1. HERO (3 pilares) ===== -->
    <section class="hero">
        <span class="hero-badge"><i class="fas fa-shield-halved"></i> Facturación electrónica SUNAT · Multi-sucursal</span>
        <h1>
            El sistema que controla tus
            <span class="pil c-ventas"><i class="fas fa-cash-register"></i>Ventas</span>,
            cuida tu
            <span class="pil c-stock"><i class="fas fa-boxes-stacked"></i>Stock</span>
            y protege tu
            <span class="pil c-sunat"><i class="fas fa-file-invoice"></i>Dinero ante SUNAT</span>.
        </h1>
        <p>Factura en segundos, controla el stock en tiempo real y cuadra tu caja sin errores.</p>
        <a class="btn-login" href="modules/auth/login.php">
            <i class="fas fa-right-to-bracket"></i> Iniciar sesión
        </a>
    </section>

    <!-- ===== 2. CAPTURA ANIMADA ===== -->
    <div class="showcase">
        <div class="mockup">
            <div class="mockup-bar">
                <span class="dot r"></span><span class="dot y"></span><span class="dot g"></span>
                <span class="url">app.gravity.pe/dashboard</span>
            </div>
            <div class="mockup-body">
                <div class="mockup-hi">Hola, bienvenido 👋</div>
                <div class="mockup-grid">
                    <div>
                        <div class="mk-sales-label">Total de ventas del día</div>
                        <div class="mk-sales" id="counter" data-target="45234">S/ 0.00</div>
                        <div class="mk-trend"><i class="fas fa-arrow-trend-up"></i> 18.6% vs. ayer · 128 comprobantes</div>
                        <div class="mk-chart" id="mkChart">
                            <div class="b" data-h="40"></div>
                            <div class="b" data-h="62"></div>
                            <div class="b" data-h="48"></div>
                            <div class="b" data-h="78"></div>
                            <div class="b" data-h="58"></div>
                            <div class="b" data-h="90"></div>
                            <div class="b" data-h="72"></div>
                        </div>
                    </div>
                    <div>
                        <div class="mk-side-title">Últimas ventas</div>
                        <div class="mk-row">
                            <div class="mk-doc"><span class="mk-tag b">B001</span>Boleta<small>00001947</small></div>
                            <div class="mk-amt">S/ 189.90</div>
                        </div>
                        <div class="mk-row">
                            <div class="mk-doc"><span class="mk-tag f">F001</span>Factura<small>00000812</small></div>
                            <div class="mk-amt">S/ 640.00</div>
                        </div>
                        <div class="mk-row">
                            <div class="mk-doc"><span class="mk-tag n">NV</span>Nota de venta<small>0041</small></div>
                            <div class="mk-amt">S/ 85.50</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== 3. FLUJO DEL SISTEMA ===== -->
    <section class="block center">
        <div class="wrap">
            <span class="section-tag">Todo conectado</span>
            <h2 class="section-title">Un solo flujo, de la compra a SUNAT</h2>
            <p class="section-sub">Cada módulo alimenta al siguiente: lo que compras se vuelve stock, el stock se vende y la venta se declara automáticamente.</p>
            <div class="flow">
                <div class="flow-node"><div class="ico"><i class="fas fa-cart-shopping"></i></div><div class="lbl">Compras</div></div>
                <div class="flow-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="flow-node"><div class="ico"><i class="fas fa-box"></i></div><div class="lbl">Productos y servicios</div></div>
                <div class="flow-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="flow-node"><div class="ico"><i class="fas fa-users"></i></div><div class="lbl">Clientes y proveedores</div></div>
                <div class="flow-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="flow-node"><div class="ico"><i class="fas fa-boxes-stacked"></i></div><div class="lbl">Inventario y stock</div></div>
                <div class="flow-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="flow-node"><div class="ico"><i class="fas fa-cash-register"></i></div><div class="lbl">Ventas</div></div>
                <div class="flow-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="flow-node sunat"><div class="ico"><i class="fas fa-file-invoice"></i></div><div class="lbl">SUNAT</div></div>
            </div>
        </div>
    </section>

    <!-- ===== 4. PROCESOS AUTOMÁTICOS ===== -->
    <section class="block alt center">
        <div class="wrap">
            <span class="section-tag">Automático</span>
            <h2 class="section-title">Registras 1 venta, Gravity resuelve el resto</h2>
            <p class="section-sub">Tú solo cobras. El sistema encadena todo lo demás en el mismo instante.</p>
            <div class="auto-grid" style="text-align:left">
                <div class="auto-step you"><div class="n">1</div><div><h4>Registras la venta</h4><p>Desde el punto de venta, con lector de código de barras o búsqueda rápida.</p></div></div>
                <div class="auto-step"><div class="n">2</div><div><h4>Se emite y envía a SUNAT</h4><p>Boleta o factura generada y declarada al instante (las notas de venta no se envían).</p></div></div>
                <div class="auto-step"><div class="n">3</div><div><h4>Ingresa a caja</h4><p>El cobro se registra según el método de pago: efectivo, Yape, tarjeta.</p></div></div>
                <div class="auto-step"><div class="n">4</div><div><h4>Cuenta por cobrar</h4><p>Si la venta fue al crédito, se genera automáticamente la cuenta pendiente.</p></div></div>
                <div class="auto-step"><div class="n">5</div><div><h4>Descuenta el stock</h4><p>El inventario de la sucursal se actualiza en tiempo real.</p></div></div>
                <div class="auto-step"><div class="n">6</div><div><h4>Alerta de stock bajo</h4><p>Si un producto queda bajo el mínimo, aparece en las alertas del dashboard.</p></div></div>
                <div class="auto-step"><div class="n">7</div><div><h4>Comprobante y reportes</h4><p>Se imprime o se envía por WhatsApp, y la venta entra a tus reportes al toque.</p></div></div>
            </div>
        </div>
    </section>

    <!-- ===== 5. MÓDULOS ===== -->
    <section class="block center">
        <div class="wrap">
            <span class="section-tag">Módulos</span>
            <h2 class="section-title">El control total de tu negocio en un solo sistema</h2>
            <p class="section-sub">Rápido, fácil de usar y pensado para trabajar con varias sucursales a la vez.</p>
            <div class="features-grid" style="text-align:left">
                <div class="feature-card"><div class="feature-icon"><i class="fas fa-cash-register"></i></div><h3>Punto de venta</h3><p>Ventas rápidas con lector de código de barras, favoritos y caja por turno.</p></div>
                <div class="feature-card"><div class="feature-icon"><i class="fas fa-building"></i></div><h3>Multi-sucursal y multi-empresa</h3><p>Cada sucursal con su propio inventario y caja, administradas desde un solo sistema.</p></div>
                <div class="feature-card"><div class="feature-icon"><i class="fas fa-file-invoice"></i></div><h3>Facturación SUNAT</h3><p>Boletas, facturas y notas de crédito enviadas directo a SUNAT.</p></div>
                <div class="feature-card"><div class="feature-icon"><i class="fas fa-boxes-stacked"></i></div><h3>Inventario y almacén</h3><p>Stock, ingresos de mercadería, proveedores y traslados entre sucursales.</p></div>
                <div class="feature-card"><div class="feature-icon"><i class="fas fa-file-invoice-dollar"></i></div><h3>Compras y cobranzas</h3><p>Órdenes de compra, cuentas por pagar y por cobrar integradas al inventario.</p></div>
                <div class="feature-card"><div class="feature-icon"><i class="fas fa-chart-line"></i></div><h3>Reportes y dashboard</h3><p>Resumen diario de ventas, alertas de stock y productos más vendidos.</p></div>
            </div>
        </div>
    </section>

    <!-- ===== 6. CTA FINAL ===== -->
    <section class="cta">
        <h2>Empieza a controlar tu negocio con Gravity</h2>
        <p>Ingresa a tu sistema y gestiona ventas, stock y facturación desde un solo lugar.</p>
        <a class="btn-login" href="modules/auth/login.php"><i class="fas fa-right-to-bracket"></i> Iniciar sesión</a>
    </section>

    <footer>
        &copy; <?= date('Y') ?> Gravity
    </footer>

    <script>
    (function () {
        // Formatea a S/ 00,000.00
        function fmt(n) {
            return 'S/ ' + n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        // Contador animado del total de ventas
        function animarContador(el) {
            var target = parseFloat(el.getAttribute('data-target')) || 0;
            var dur = 1600, ini = null;
            function paso(ts) {
                if (!ini) ini = ts;
                var p = Math.min((ts - ini) / dur, 1);
                var ease = 1 - Math.pow(1 - p, 3); // easeOutCubic
                el.textContent = fmt(target * ease);
                if (p < 1) requestAnimationFrame(paso);
                else el.textContent = fmt(target);
            }
            requestAnimationFrame(paso);
        }
        // Barras del mini-gráfico
        function animarBarras(cont) {
            var barras = cont.querySelectorAll('.b');
            for (var i = 0; i < barras.length; i++) {
                (function (b, idx) {
                    setTimeout(function () { b.style.height = b.getAttribute('data-h') + '%'; }, idx * 80);
                })(barras[i], i);
            }
        }
        function arrancar() {
            var c = document.getElementById('counter');
            var g = document.getElementById('mkChart');
            if (c) animarContador(c);
            if (g) animarBarras(g);
        }
        // Dispara cuando el mockup entra en pantalla (o al cargar si ya es visible)
        var mk = document.querySelector('.mockup');
        if ('IntersectionObserver' in window && mk) {
            var obs = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) { arrancar(); obs.disconnect(); }
                });
            }, { threshold: 0.3 });
            obs.observe(mk);
        } else {
            window.addEventListener('load', arrancar);
        }
    })();
    </script>

</body>
</html>
<?php exit; ?>
