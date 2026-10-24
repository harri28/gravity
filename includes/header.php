<?php
// ============================================================
// ARCHIVO: farmacia/includes/header.php
// ============================================================

header('Content-Type: text/html; charset=UTF-8');

require_once ($base_path ?? '../../') . 'config/auth.php';
requireAuth($required_roles ?? []);

$current_module = $current_module ?? '';
$current_page   = $current_page   ?? '';

$_inicial = strtoupper(substr(sesionNombre(), 0, 1)) ?: 'U';

// Sucursales accesibles por el usuario (para el panel del footer)
$_suc_lista = [];
$_suc_color = '#6366f1';
$_suc_icono = 'fa-store';
if (!isSuperadmin() && sesionId() > 0) {
    try {
        if (isAdmin()) {
            $_suc_stmt = getDB()->prepare("
                SELECT id, nombre, color, icono
                FROM public.sucursales
                WHERE tenant_id = :tid AND activo = TRUE
                ORDER BY nombre ASC
            ");
            $_suc_stmt->execute([':tid' => sesionTenantId()]);
        } else {
            $_suc_stmt = getDB()->prepare("
                SELECT s.id, s.nombre, s.color, s.icono
                FROM public.sucursales s
                JOIN public.usuario_sucursal us ON us.sucursal_id = s.id
                WHERE us.usuario_id = :uid AND s.tenant_id = :tid
                  AND s.activo = TRUE AND us.activo = TRUE
                ORDER BY s.nombre ASC
            ");
            $_suc_stmt->execute([':uid' => sesionId(), ':tid' => sesionTenantId()]);
        }
        $_suc_lista = $_suc_stmt->fetchAll(PDO::FETCH_ASSOC);
        // Color e ícono de la sucursal activa
        foreach ($_suc_lista as $_s) {
            if ((int)$_s['id'] === sesionSucursalId()) {
                $_suc_color = $_s['color'] ?: $_suc_color;
                $_suc_icono = $_s['icono'] ?: $_suc_icono;
                break;
            }
        }
    } catch (Throwable $_e) {}
}

$_brand      = getTenantConfig();
$_brand_name = htmlspecialchars($_brand['nombre_sistema'] ?: 'Gravity');
$_brand_logo = $_brand['logo_path'] ?? null;
$_brand_logo_abs = $_brand_logo
    ? realpath(__DIR__ . '/../' . $_brand_logo)
    : null;
$_tema_oscuro = in_array($_brand['tema_oscuro'] ?? false, [true, 't', 1, '1'], true);
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= $_tema_oscuro ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? $_brand_name ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= $base_path ?? '' ?>assets/img/logos/favicon.svg?v=<?= @filemtime(__DIR__ . '/../assets/img/logos/favicon.svg') ?: 1 ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $base_path ?? '' ?>assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script src="<?= $base_path ?? '' ?>assets/js/barcode-scanner.js"></script>
    <style>
        :root { --primary: <?= htmlspecialchars($_suc_color) ?>; }
        /* Rol badge en sidebar */
        .role-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 20px;
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04rem;
        }
        .role-badge.gerente { background: #fef3c7; color: #d97706; }
        .role-badge.admin   { background: #eef2ff; color: #4f46e5; }
        .role-badge.cajero  { background: #f0fdf4; color: #16a34a; }
        .sucursal-label {
            font-size: .72rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-footer .user-info { align-items: flex-start; }
        .logout-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: .9rem;
            padding: 4px 6px;
            border-radius: 6px;
            transition: color .15s, background .15s;
            margin-left: auto;
            align-self: center;
        }
        .logout-btn:hover { color: #dc2626; background: #fef2f2; }
        .sidebar-logo-wrap {
            cursor: pointer;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar-logo-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,.45);
            border-radius: 8px;
            opacity: 0;
            transition: opacity .15s;
            color: #fff;
            font-size: 1rem;
        }
        .sidebar-logo-wrap:hover .sidebar-logo-overlay { opacity: 1; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <?php if (isAdmin()): ?><label for="sidebar-logo-input" class="sidebar-logo-wrap"><?php endif; ?>
        <img id="sidebar-brand-logo"
             src="<?= ($_brand_logo_abs && file_exists($_brand_logo_abs)) ? ($base_path ?? '') . htmlspecialchars($_brand_logo) : '' ?>"
             alt="Logo"
             style="height:65px;width:auto;max-width:210px;object-fit:contain;flex-shrink:0<?= ($_brand_logo_abs && file_exists($_brand_logo_abs)) ? '' : ';display:none' ?>">
        <div class="brand-icon" id="sidebar-brand-icon"<?= ($_brand_logo_abs && file_exists($_brand_logo_abs)) ? ' style="display:none"' : '' ?>><i class="fas fa-pills"></i></div>
        <?php if (isAdmin()): ?>
            <div class="sidebar-logo-overlay"><i class="fas fa-camera"></i></div>
        </label>
        <input type="file" id="sidebar-logo-input"
               accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml"
               style="display:none" onchange="sidebarUploadLogo(this)">
        <?php endif; ?>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-label">PRINCIPAL</span>

            <!-- Dashboard -->
            <a href="<?= $base_path ?? '' ?>modules/dashboard/index.php"
               class="nav-item <?= $current_module === 'dashboard' ? 'active' : '' ?>"
               data-tooltip="Dashboard">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>

            <!-- Ventas -->
            <a href="<?= $base_path ?? '' ?>modules/ventas/index.php"
               class="nav-item <?= $current_module === 'ventas' ? 'active' : '' ?>"
               data-tooltip="Ventas">
                <i class="fas fa-cash-register"></i>
                <span>Ventas</span>
            </a>

            <!-- Caja -->
            <a href="<?= $base_path ?? '' ?>modules/caja/index.php"
               class="nav-item <?= $current_module === 'caja' ? 'active' : '' ?>"
               data-tooltip="Caja">
                <i class="fas fa-cash-register"></i>
                <span>Caja</span>
            </a>

            <!-- Clientes -->
            <a href="<?= $base_path ?? '' ?>modules/clientes/index.php"
               class="nav-item <?= $current_module === 'clientes' ? 'active' : '' ?>"
               data-tooltip="Clientes">
                <i class="fas fa-users"></i>
                <span>Clientes</span>
            </a>

            <!-- Inventario -->
            <a href="<?= $base_path ?? '' ?>modules/inventario/index.php"
               class="nav-item <?= $current_module === 'inventario' ? 'active' : '' ?>"
               data-tooltip="Inventario">
                <i class="fas fa-boxes"></i>
                <span>Inventario</span>
            </a>

            <!-- Almacén -->
            <a href="<?= $base_path ?? '' ?>modules/almacen/index.php"
               class="nav-item <?= ($current_module ?? '') === 'almacen' ? 'active' : '' ?>"
               data-tooltip="Almacén">
                <i class="fas fa-warehouse"></i>
                <span>Almacén</span>
            </a>

            <a href="<?= $base_path ?? '' ?>modules/compras/index.php"
               class="nav-item <?= ($current_module ?? '') === 'compras' ? 'active' : '' ?>"
               data-tooltip="Compras">
                <i class="fas fa-shopping-cart"></i>
                <span>Compras</span>
            </a>

            <?php if (isAdmin()): ?>
            <span class="nav-label" style="margin-top:12px">SISTEMA</span>

            <!-- Facturación -->
            <a href="<?= $base_path ?? '' ?>modules/facturacion/index.php"
               class="nav-item <?= $current_module === 'facturacion' ? 'active' : '' ?>"
               data-tooltip="Facturación">
                <i class="fas fa-file-invoice"></i>
                <span>Facturación</span>
            </a>

            <a href="<?= $base_path ?? '' ?>modules/banco/index.php"
               class="nav-item <?= $current_module === 'banco' ? 'active' : '' ?>"
               data-tooltip="Banco">
                <i class="fas fa-university"></i>
                <span>Banco</span>
            </a>

            <a href="<?= $base_path ?? '' ?>modules/reportes/index.php"
               class="nav-item <?= $current_module === 'reportes' ? 'active' : '' ?>"
               data-tooltip="Reportes">
                <i class="fas fa-chart-pie"></i>
                <span>Reportes</span>
            </a>

            <?php if (sesionRol() === 'admin'): ?>
            <a href="<?= $base_path ?? '' ?>modules/admin/index.php"
               class="nav-item <?= $current_module === 'admin' ? 'active' : '' ?>"
               data-tooltip="Administración">
                <i class="fas fa-cogs"></i>
                <span>Administración</span>
            </a>
            <?php endif; ?>

            <a href="<?= $base_path ?? '' ?>modules/ecommerce/index.php"
               class="nav-item <?= $current_module === 'ecommerce' ? 'active' : '' ?>"
               data-tooltip="E-commerce">
                <i class="fas fa-store"></i>
                <span>E-commerce</span>
            </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Panel de sucursales (se despliega al clickear el usuario) -->
    <div class="user-suc-panel" id="userSucPanel">
        <?php if (!empty($_suc_lista)): ?>
        <div class="user-suc-header">
            <i class="fas fa-building" style="font-size:.75rem"></i>
            Sucursales
        </div>
        <?php foreach ($_suc_lista as $_suc): ?>
        <form method="post" action="<?= $base_path ?? '' ?>modules/auth/switch_sucursal.php" style="margin:0">
            <input type="hidden" name="sucursal_id" value="<?= $_suc['id'] ?>">
            <button type="submit" class="user-suc-item<?= (int)$_suc['id'] === sesionSucursalId() ? ' current' : '' ?>">
                <i class="fas <?= htmlspecialchars($_suc['icono'] ?: 'fa-store') ?>" style="color:<?= htmlspecialchars($_suc['color'] ?: '#6366f1') ?>"></i>
                <span><?= htmlspecialchars($_suc['nombre']) ?></span>
                <?php if ((int)$_suc['id'] === sesionSucursalId()): ?>
                <i class="fas fa-check" style="margin-left:auto;font-size:.75rem;color:var(--primary)"></i>
                <?php endif; ?>
            </button>
        </form>
        <?php endforeach; ?>
        <div class="user-suc-divider"></div>
        <?php endif; ?>
        <a href="<?= $base_path ?? '' ?>modules/auth/logout.php" class="user-suc-item logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Cerrar sesión</span>
        </a>
    </div>

    <div class="sidebar-footer" onclick="toggleUserPanel()" style="cursor:pointer">
        <div class="user-info" style="display:flex;align-items:center;gap:10px;width:100%">
            <div class="user-avatar"><?= $_inicial ?></div>
            <div style="flex:1;min-width:0">
                <div class="user-name" style="display:flex;align-items:center;gap:6px">
                    <?= htmlspecialchars(sesionNombre()) ?>
                    <?php $rolLabels = ['gerente'=>'Superadmin','admin'=>'Admin','cajero'=>'Cajero']; ?>
                    <span class="role-badge <?= sesionRol() ?>"><?= $rolLabels[sesionRol()] ?? sesionRol() ?></span>
                </div>
                <?php if (sesionTenantNombre()): ?>
                <div class="sucursal-label" style="font-size:.68rem;color:var(--primary);font-weight:600;margin-bottom:1px">
                    <i class="fas fa-building" style="font-size:.6rem;margin-right:2px"></i>
                    <?= htmlspecialchars(sesionTenantNombre()) ?>
                </div>
                <?php endif; ?>
                <div class="sucursal-label">
                    <i class="fas <?= htmlspecialchars($_suc_icono) ?>" style="font-size:.65rem;margin-right:3px;color:<?= htmlspecialchars($_suc_color) ?>"></i>
                    <?= htmlspecialchars(sesionSucursal()) ?>
                </div>
            </div>
            <i class="fas fa-chevron-right" id="userSucChevron"
               style="color:var(--text-light);font-size:.75rem;transition:transform .2s;flex-shrink:0"></i>
        </div>
    </div>
</aside>

<?php if (isAdmin()): ?>
<script>
(function(){
    const _bp = '<?= addslashes($base_path ?? '') ?>';
    window.sidebarUploadLogo = function(input) {
        const file = input.files[0];
        if (!file) return;
        const form = new FormData();
        form.append('logo', file);
        fetch(_bp + 'modules/admin/api.php?action=logo_subir', { method: 'POST', body: form })
            .then(r => r.json())
            .then(data => {
                if (data.error) { alert(data.message); return; }
                const img  = document.getElementById('sidebar-brand-logo');
                const icon = document.getElementById('sidebar-brand-icon');
                if (img)  { img.src = _bp + data.logo_path + '?t=' + Date.now(); img.style.display = ''; }
                if (icon) { icon.style.display = 'none'; }
            })
            .catch(() => alert('Error al subir logo'));
        input.value = '';
    };
})();
</script>
<?php endif; ?>
<!-- Overlay para cerrar sidebar en móvil -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

<!-- TOPBAR -->
<div class="topbar">
    <button class="topbar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <div class="topbar-breadcrumb">
        <span><?= $breadcrumb ?? 'Inicio' ?></span>
    </div>
    <div class="topbar-actions">
        <div class="topbar-date">
            <i class="far fa-calendar"></i>
            <span id="current-date"></span>
        </div>
    </div>
</div>

<!-- MAIN CONTENT -->
<main class="main-content" id="main-content">
<?php
// Flash error (ej: caja abierta al intentar cambiar de sucursal)
if (!empty($_SESSION['flash_error'])):
    $_flash_msg = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
?>
<div style="background:#fef2f2;border-bottom:2px solid #ef4444;padding:10px 24px;display:flex;align-items:center;gap:10px;font-size:.88rem;color:#991b1b">
    <i class="fas fa-ban" style="color:#ef4444"></i>
    <span><?= htmlspecialchars($_flash_msg) ?></span>
</div>
<?php
endif;

// Aviso de vencimiento del plan (configurado por Superadmin, por tenant)
if (!isSuperadmin() && sesionId() > 0) {
    try {
        $_plan_stmt = getDB()->prepare("SELECT plan_vence_at, plan_banner_activo FROM public.tenants WHERE id = :tid");
        $_plan_stmt->execute([':tid' => sesionTenantId()]);
        $_plan_row    = $_plan_stmt->fetch();
        $_plan_vence  = $_plan_row['plan_vence_at'] ?? null;
        $_plan_activo = in_array($_plan_row['plan_banner_activo'] ?? false, [true, 't', 1, '1'], true);
        // El interruptor manda: apagado = nunca se muestra, ni vencido.
        if ($_plan_activo && !empty($_plan_vence)) {
            $_plan_dias = (int) floor((strtotime($_plan_vence) - strtotime(date('Y-m-d'))) / 86400);
            if ($_plan_dias <= 7) {
                if ($_plan_dias < 0) {
                    $_plan_msg = 'Tu plan venció hace ' . abs($_plan_dias) . ' día' . (abs($_plan_dias) === 1 ? '' : 's') . '.';
                } elseif ($_plan_dias === 0) {
                    $_plan_msg = 'Tu plan vence hoy.';
                } elseif ($_plan_dias === 1) {
                    $_plan_msg = 'Tu plan vence mañana.';
                } else {
                    $_plan_msg = 'Tu plan vence en ' . $_plan_dias . ' días.';
                }
                $_plan_bg     = $_plan_dias < 0 ? '#fef2f2' : '#fff7ed';
                $_plan_border = $_plan_dias < 0 ? '#ef4444' : '#f97316';
                $_plan_fg     = $_plan_dias < 0 ? '#991b1b' : '#9a3412';
?>
<div style="background:<?= $_plan_bg ?>;border-bottom:2px solid <?= $_plan_border ?>;padding:10px 24px;display:flex;align-items:center;justify-content:center;gap:10px;font-size:.88rem;color:<?= $_plan_fg ?>;text-align:center">
    <i class="fas fa-circle-exclamation" style="color:<?= $_plan_border ?>"></i>
    <strong><?= htmlspecialchars($_plan_msg) ?></strong>
    <span>Renueva tu suscripción para seguir disfrutando del servicio sin interrupciones.</span>
</div>
<?php
            }
        }
    } catch (Throwable $e) {}
}

if (isCajero()) {
    try {
        $_caja_stmt = getDB()->prepare("SELECT id FROM cajas WHERE estado = 'abierta' AND usuario_id = :uid LIMIT 1");
        $_caja_stmt->execute([':uid' => (int) sesionId()]);
        $_caja_row = $_caja_stmt->fetch();
        if (!$_caja_row):
?>
<div style="background:#fef3c7;border-bottom:2px solid #f59e0b;padding:10px 24px;display:flex;align-items:center;gap:10px;font-size:.88rem;color:#92400e">
    <i class="fas fa-exclamation-triangle" style="color:#d97706"></i>
    <strong>Caja no aperturada.</strong>
    <span>Debes <a href="<?= ($base_path ?? '') ?>modules/caja/index.php" style="color:#d97706;font-weight:600;text-decoration:underline">aperturar la caja</a> antes de registrar ventas.</span>
</div>
<?php
        endif;
    } catch (Throwable $e) {}
}
?>
