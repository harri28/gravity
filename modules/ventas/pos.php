<?php
// ============================================================
// ARCHIVO: farmacia/modules/ventas/index.php
// MÓDULO:  Ventas → Punto de Venta (POS)
// ============================================================

require_once '../../config/database.php';

$base_path      = '../../';
$current_module = 'ventas';
$current_page   = 'pos';
$page_title     = 'Punto de Venta — Gravity';
$breadcrumb     = '<strong>Ventas</strong> / Punto de Venta';

$db = getDB();

// Datos del tenant para el ticket de impresión
$_tenant_info = ['ruc' => '', 'telefono' => '', 'direccion' => ''];
if (sesionTenantId()) {
    $t = $db->prepare("SELECT ruc, telefono, direccion FROM public.tenants WHERE id = :id");
    $t->execute([':id' => sesionTenantId()]);
    $_tenant_info = $t->fetch() ?: $_tenant_info;
}

// Logo del tenant en base64 para embeber en el ticket impreso
$_logo_data_uri = '';
$_tenant_cfg = getTenantConfig();
if (!empty($_tenant_cfg['logo_path'])) {
    $logo_file = __DIR__ . '/../../' . $_tenant_cfg['logo_path'];
    if (file_exists($logo_file)) {
        $ext  = strtolower(pathinfo($logo_file, PATHINFO_EXTENSION));
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                 'gif' => 'image/gif', 'webp' => 'image/webp'][$ext] ?? 'image/png';
        $_logo_data_uri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logo_file));
    }
}

// Categorías para filtro
$categorias = $db->query("SELECT id, nombre FROM public.categorias WHERE activo = TRUE ORDER BY nombre")->fetchAll();

include '../../includes/header.php';
?>

<link rel="stylesheet" href="<?= $base_path ?>assets/vendor/select2/select2.min.css">
<link rel="stylesheet" href="<?= $base_path ?>assets/css/cobro-modal.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/cobro-modal.css') ?: 1 ?>">
<style>
.pos-page-wrap {
    display:flex;
    flex-direction:column;
    height:calc(100vh - var(--topbar-h) - 48px);
}
.vt-tabs {
    display:flex; gap:6px; margin-bottom:16px;
    background:var(--surface-2); border-radius:var(--radius);
    padding:5px; width:fit-content; flex-shrink:0;
}
.vt-tab {
    display:inline-flex; align-items:center; gap:7px; padding:8px 20px;
    border:none; border-radius:calc(var(--radius) - 2px);
    background:transparent; color:var(--text-muted);
    font-size:.88rem; font-weight:500; cursor:pointer;
    transition:background .15s,color .15s; white-space:nowrap;
    text-decoration:none;
}
.vt-tab:hover { background:var(--surface); color:var(--text); }
.vt-tab.active { background:var(--primary); color:#fff; }
.vt-tab.active:hover { background:var(--primary-dark,var(--primary)); color:#fff; }
</style>

<div class="pos-page-wrap">

<div class="page-header" style="flex-shrink:0">
    <div>
        <div class="page-title"><i class="fas fa-store" style="color:var(--primary);margin-right:8px"></i>Punto de Venta</div>
        <div class="page-subtitle">Registra ventas rápidamente — <?= date('d/m/Y H:i') ?></div>
    </div>
</div>

<?php $__tab = (($_GET['tab'] ?? '') === 'ventas') ? 'ventas' : 'pos'; ?>
<div class="vt-tabs">
    <a class="vt-tab" href="index.php"><i class="fas fa-chair"></i> Mesas</a>
    <a class="vt-tab <?= $__tab === 'pos' ? 'active' : '' ?>" href="pos.php"><i class="fas fa-store"></i> Punto de venta</a>
    <a class="vt-tab <?= $__tab === 'ventas' ? 'active' : '' ?>" href="pos.php?tab=ventas"><i class="fas fa-history"></i> Historial</a>
</div>

<!-- TAB: POS -->
<div id="tab-pos" style="flex:1;min-height:0;display:flex;flex-direction:column">
<div class="pos-layout" style="flex:1;min-height:0;height:auto">
    <!-- COLUMNA CATEGORÍAS -->
    <div class="pos-cats">
        <div class="filter-chips" id="cat-chips">
            <span class="chip active" data-cat="0">Todos</span>
            <?php foreach ($categorias as $cat): ?>
            <span class="chip" data-cat="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- COLUMNA PRODUCTOS: búsqueda + grilla -->
    <div class="pos-left">
        <div class="input-group">
            <span class="input-group-icon"><i class="fas fa-search"></i></span>
            <input type="text" id="search-input" class="form-control"
                placeholder="Buscar producto por nombre o código..." autocomplete="off">
        </div>

        <!-- Grilla de productos -->
        <div class="products-grid" id="products-grid">
            <div style="grid-column:1/-1;text-align:center;color:var(--text-light);padding:40px">
                <i class="fas fa-spinner fa-spin" style="font-size:1.5rem"></i>
                <p style="margin-top:10px">Cargando productos...</p>
            </div>
        </div>
    </div>

    <!-- COLUMNA DERECHA: carrito -->
    <div class="pos-right">
        <div class="cart-panel">
            <div class="cart-header">
                <div class="cart-title"><i class="fas fa-shopping-cart" style="margin-right:7px;color:var(--primary)"></i>Generar venta</div>
                <button class="cart-drawer-close" onclick="closeCartDrawer()" title="Cerrar"><i class="fas fa-chevron-down"></i></button>
                <span class="cart-count" id="cart-count">0</span>
            </div>

            <div class='cart-items' id="cart-items">
                <div class="cart-empty" id="cart-empty">
                    <i class="fas fa-shopping-basket"></i>
                    <span>El carrito está vacío</span>
                    <small>Selecciona productos del panel izquierdo</small>
                </div>
            </div>

            <div class="cart-summary" id="cart-summary" style="display:none">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span id="sum-subtotal">S/ 0.00</span>
                </div>
                <div class="summary-row">
                    <span>Descuento</span>
                    <span id="sum-descuento" style="color:var(--success)">-S/ 0.00</span>
                </div>
                <div class="summary-row" id="sum-igv-row">
                    <span id="sum-igv-label">IGV (18%)</span>
                    <span id="sum-igv">S/ 0.00</span>
                </div>
                <div class="summary-row total">
                    <span>TOTAL</span>
                    <span id="sum-total">S/ 0.00</span>
                </div>
            </div>

            <!-- Banner estado de caja -->
            <div id="caja-banner" style="display:none;margin:0 0 10px;padding:9px 12px;border-radius:8px;font-size:.8rem;font-weight:600;display:flex;align-items:center;gap:8px"></div>

            <div class="cart-footer" style="padding:12px 14px">
                <button class="btn btn-success w-100" onclick="procesarVenta()" id="btn-vender" disabled
                    style="height:52px;font-size:1rem;display:flex;align-items:center;justify-content:center;gap:10px">
                    <i class="fas fa-check-circle" style="font-size:1.1rem"></i>
                    <span>Cobrar</span>
                    <span id="footer-total" style="background:rgba(255,255,255,.2);padding:3px 12px;border-radius:20px;font-size:.95rem;font-weight:800">S/ 0.00</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- FAB carrito (solo móvil) -->
<button class="cart-fab" id="cart-fab" onclick="openCartDrawer()">
    <i class="fas fa-shopping-cart"></i>
    <span class="cart-fab-badge" id="cart-fab-badge">0</span>
</button>
<div class="cart-drawer-backdrop" id="cart-drawer-backdrop" onclick="closeCartDrawer()"></div>
</div><!-- /tab-pos -->

<!-- TAB: VENTAS (historial) -->
<div id="tab-ventas" style="display:none">

    <div class="row g-3 mb-3" id="h-stats-container" style="margin-top:4px">
        <?php foreach (['blue','green','yellow','red'] as $c): ?>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon <?= $c ?>"><i class="fas fa-spinner fa-spin"></i></div><div><div class="stat-value">—</div><div class="stat-label">...</div></div></div></div>
        <?php endforeach; ?>
    </div>

    <div class="card mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" id="h-desde" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" id="h-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Estado</label>
                <select class="form-control" id="h-estado">
                    <option value="">Todos</option>
                    <option value="completada">Completada</option>
                    <option value="anulada">Anulada</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Buscar</label>
                <div class="input-group">
                    <span class="input-group-icon"><i class="fas fa-search"></i></span>
                    <input type="text" id="h-q" class="form-control" placeholder="N° venta o cliente...">
                </div>
            </div>
            <div class="col-6 col-md-auto">
                <button class="btn btn-primary w-100" onclick="loadHistorial()"><i class="fas fa-search"></i> Buscar</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Ventas registradas</div>
            <span style="font-size:.82rem;color:var(--text-muted)" id="h-result-count">—</span>
        </div>
        <div class="table-wrap table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>N° Venta</th>
                        <th>Fecha / Hora</th>
                        <th>Cliente</th>
                        <th>Items</th>
                        <th>Pago</th>
                        <th>Comp.</th>
                        <th class="text-right">Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="h-tabla-body">
                    <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /tab-ventas -->

</div><!-- /.pos-page-wrap -->

<!-- MODAL: Cobro / Pago -- HTML extraido a partial compartido con mesa.php -->
<?php include __DIR__ . '/_modal_cobro.php'; ?>

<!-- MODAL: Vista previa del comprobante -->
<div class="modal-overlay" id="modal-preview">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-eye" style="color:var(--primary);margin-right:8px"></i>Vista Previa del Comprobante</h3>
            <button class="modal-close" onclick="closeModal('modal-preview')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding:16px;background:#e5e7eb;max-height:70vh;overflow-y:auto">
            <div style="background:#fff;border-radius:4px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.15)">
                <div id="preview-ticket-body"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-preview')">Cerrar</button>
            <button class="btn btn-outline btn-sm" onclick="printReceipt(document.getElementById('preview-ticket-body').innerHTML)">
                <i class="fas fa-print"></i> Imprimir vista previa
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Ticket de venta -->
<div class="modal-overlay" id="modal-ticket">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-check-circle" style="color:var(--success);margin-right:8px"></i>Venta Completada</h3>
            <button class="modal-close" onclick="closeModal('modal-ticket');resetPOS()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding:16px;background:#e5e7eb;max-height:70vh;overflow-y:auto">
            <div style="background:#fff;border-radius:4px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.15)">
                <div id="ticket-body"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline btn-sm" onclick="printReceipt(document.getElementById('ticket-body').innerHTML)">
                <i class="fas fa-print"></i> Imprimir
            </button>
            <button class="btn btn-outline btn-sm" onclick="enviarWhatsApp()" style="border-color:#25d366;color:#25d366">
                <i class="fab fa-whatsapp"></i> WhatsApp
            </button>
            <button class="btn btn-outline btn-sm" onclick="copiarEnlaceComprobante()">
                <i class="fas fa-link"></i> Copiar enlace de comprobante
            </button>
            <button class="btn btn-primary" onclick="closeModal('modal-ticket');resetPOS()">
                <i class="fas fa-plus"></i> Nueva Venta
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Ver ticket de venta (desde Historial) -->
<div class="modal-overlay" id="modal-ticket-historial">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-receipt" style="color:var(--primary);margin-right:8px"></i>Comprobante de Venta</h3>
            <button class="modal-close" onclick="closeModal('modal-ticket-historial')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding:16px;background:#e5e7eb;max-height:70vh;overflow-y:auto">
            <div style="background:#fff;border-radius:4px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.15)">
                <div id="ticket-body-historial"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline btn-sm" onclick="printReceipt(document.getElementById('ticket-body-historial').innerHTML)">
                <i class="fas fa-print"></i> Imprimir
            </button>
            <button class="btn btn-outline btn-sm" onclick="enviarWhatsApp()" style="border-color:#25d366;color:#25d366">
                <i class="fab fa-whatsapp"></i> WhatsApp
            </button>
            <button class="btn btn-outline btn-sm" onclick="copiarEnlaceComprobante()">
                <i class="fas fa-link"></i> Copiar enlace
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Selección de unidad de medida -->
<div class="modal-overlay" id="modal-unidad-medida">
    <div class="modal" style="max-width:360px">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-boxes-stacked" style="color:var(--primary);margin-right:8px"></i>Selecciona una equivalencia</h3>
            <button class="modal-close" onclick="closeModal('modal-unidad-medida')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="display:flex;flex-direction:column;gap:10px">
            <div id="unidad-medida-producto-nombre" style="font-weight:600;color:var(--text-muted);margin-bottom:-2px"></div>
            <div id="unidad-medida-opciones" style="display:flex;flex-direction:column;gap:10px">
                <!-- poblado por JS -->
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Nuevo Cliente -->
<div class="modal-overlay" id="modal-nuevo-cliente">
    <div class="modal" style="max-width:860px;width:min(860px,calc(100vw - 32px))">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-user-plus" style="color:var(--primary);margin-right:8px"></i>Nuevo Cliente</h3>
            <button class="modal-close" onclick="closeModal('modal-nuevo-cliente')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding:20px 24px;max-height:72vh;overflow-y:auto">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div class="form-group">
                    <label class="form-label">Nombres <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="nc-nombres" class="form-control" placeholder="Juan" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">Apellidos</label>
                    <input type="text" id="nc-apellidos" class="form-control" placeholder="Pérez" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">DNI</label>
                    <input type="text" id="nc-dni" class="form-control" placeholder="12345678" maxlength="8" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">RUC</label>
                    <input type="text" id="nc-ruc" class="form-control" placeholder="20123456789" maxlength="11" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">Teléfono</label>
                    <input type="text" id="nc-telefono" class="form-control" placeholder="987654321" maxlength="12" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" id="nc-email" class="form-control" placeholder="correo@ejemplo.com" autocomplete="off">
                </div>
            </div>
            <div class="form-group" style="margin-top:8px">
                <label class="form-label">Dirección</label>
                <input type="text" id="nc-direccion" class="form-control" placeholder="Av. Principal 123" autocomplete="off">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-nuevo-cliente')">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-cliente" onclick="guardarNuevoCliente()">
                <i class="fas fa-save"></i> Guardar Cliente
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Anular Venta -->
<div class="modal-overlay" id="modal-anular-venta">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-ban" style="color:var(--danger);margin-right:8px"></i>Anular venta</h3>
            <button class="modal-close" onclick="closeModal('modal-anular-venta')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p id="anular-venta-texto" style="margin:0 0 12px;color:var(--text-muted);font-size:.9rem"></p>
            <div class="form-group" style="margin:0">
                <label class="form-label">Motivo de la anulación <span style="color:var(--danger)">*</span></label>
                <textarea id="anular-venta-motivo" class="form-control" rows="3" maxlength="255"
                          placeholder="Ej: cliente devolvió el producto, error en el cobro, etc." autocomplete="off"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-anular-venta')">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirmar-anular-venta" onclick="confirmarAnularVenta()">
                <i class="fas fa-ban"></i> Anular venta
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="app-toast-container" id="toast-container"></div>

<style>
/* Respaldo por si la ventana nueva de printReceipt() queda bloqueada
   (bloqueador de ventanas emergentes, común en equipos de impresora
   térmica/POS) y el usuario termina imprimiendo la página de fondo con
   Ctrl+P: oculta todo excepto el ticket, para no imprimir el modal
   completo (encabezado, botones, etc.) */
@media print {
    body * { visibility: hidden; }
    #ticket-body, #ticket-body *,
    #ticket-body-historial, #ticket-body-historial *,
    #preview-ticket-body, #preview-ticket-body * { visibility: visible; }
    #ticket-body, #ticket-body-historial, #preview-ticket-body {
        position: absolute; left: 0; top: 0; width: 100%;
    }
}
</style>

<script src="<?= $base_path ?>assets/vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="<?= $base_path ?>assets/vendor/select2/select2.min.js"></script>
<script src="<?= $base_path ?>assets/vendor/qrcode/qrcode.min.js"></script>
<div id="qr-generator-hidden" style="position:absolute;left:-9999px;top:-9999px"></div>
<script>
// ============================================================
// POS JavaScript
// ============================================================

const BASE = '../../';
const esCajero = <?= isCajero() ? 'true' : 'false' ?>;
const CLIENTES_API = BASE + 'modules/clientes/api.php';
const EMPRESA_NOMBRE  = <?= json_encode(sesionTenantNombre()) ?>;
const EMPRESA_RUC     = <?= json_encode($_tenant_info['ruc']       ?? '') ?>;
const EMPRESA_TEL     = <?= json_encode($_tenant_info['telefono']  ?? '') ?>;
const EMPRESA_DIR     = <?= json_encode($_tenant_info['direccion'] ?? '') ?>;
const EMPRESA_LOGO    = <?= json_encode($_logo_data_uri) ?>;
const SUCURSAL_NOMBRE = <?= json_encode(sesionSucursal()) ?>;
const VENDEDOR_NOMBRE = <?= json_encode(sesionNombre()) ?>;
const APP_ROOT_PATH   = <?= json_encode(app_public_prefix()) ?>;

function buildComprobanteUrl(token) {
    if (!token) return '';
    const parts    = window.location.hostname.split('.');
    const rootHost = parts.length >= 3 ? parts.slice(-2).join('.') : window.location.hostname;
    return `${window.location.protocol}//${rootHost}${APP_ROOT_PATH}/mi-comprobante.php?t=${encodeURIComponent(token)}`;
}

function generarQrDataUri(texto, size) {
    if (!texto || typeof QRCode === 'undefined') return '';
    const holder = document.getElementById('qr-generator-hidden');
    holder.innerHTML = '';
    new QRCode(holder, { text: texto, width: size, height: size, correctLevel: QRCode.CorrectLevel.M });
    const canvas = holder.querySelector('canvas');
    const dataUri = canvas ? canvas.toDataURL('image/png') : '';
    holder.innerHTML = '';
    return dataUri;
}
let allProducts = [];
let showAllProducts = false;
const GRID_LIMIT = 20;
let cart = [];
let selectedCliente = null;
let defaultCliente = null;
let currentCat = 0;
let currentVentaData = null;
let _lastSale = null;
let seriesByType = { boleta: [], factura: [], ticket: [] };
let splitPaymentEnabled = false;
let splitPaymentRows = [];
let creditPaymentEnabled = false;
let creditPaymentRows = [];
let tiposDocumentoCliente = [];

// ---- Init ----
let cajaAbierta = false;
let cajaEsMia   = true;

document.addEventListener('DOMContentLoaded', () => {
    bootstrapPosClienteUI();
    checkCaja();
    loadProducts();
    loadSeriesDisponibles();
    setupSearch();
    setupClienteSelect();
    loadTiposDocumentoCliente();
    setupBarcodeScanner();
    loadDefaultCliente();
});

function checkCaja() {
    fetch(BASE + 'modules/caja/api.php?action=estado')
        .then(r => r.json())
        .then(d => {
            const banner = document.getElementById('caja-banner');
            const btnVender = document.getElementById('btn-vender');
            if (d.caja && d.caja.estado === 'abierta') {
                cajaAbierta = true;
                cajaEsMia = !!d.es_propia;
                banner.style.display = 'flex';
                if (cajaEsMia) {
                    banner.style.background = '#f0fdf4';
                    banner.style.border = '1px solid #bbf7d0';
                    banner.style.color = '#15803d';
                    banner.innerHTML = '<i class="fas fa-cash-register"></i> Caja abierta — ' + (d.caja.nombre || 'Caja Principal');
                } else {
                    banner.style.background = '#fffbeb';
                    banner.style.border = '1px solid #fde68a';
                    banner.style.color = '#b45309';
                    banner.innerHTML = '<i class="fas fa-eye"></i> Caja abierta por ' + (d.caja.usuario_apertura || 'otro usuario') + ' — modo solo lectura';
                }
            } else {
                cajaAbierta = false;
                cajaEsMia = true;
                banner.style.display = 'flex';
                banner.style.background = '#fef2f2';
                banner.style.border = '1px solid #fecaca';
                banner.style.color = '#dc2626';
                banner.innerHTML = '<i class="fas fa-lock"></i> Caja cerrada — <a href="../caja/index.php" style="color:#dc2626;margin-left:4px;font-weight:700">Ir a aperturar</a>';
            }
            if (btnVender) btnVender.disabled = !cart.length || !cajaEsMia;
        })
        .catch(() => { cajaAbierta = false; cajaEsMia = true; });
}

function productAffectationType(product) {
    return String(product.afectacion_tipo || '').trim().toUpperCase() || (
        ['20', '21'].includes(String(product.afectacion_igv_codigo || '').trim()) ? 'EXO'
        : ['30', '31', '32', '33', '34', '35', '36'].includes(String(product.afectacion_igv_codigo || '').trim()) ? 'INA'
        : 'GRAV'
    );
}

function productIncludesIgv(product) {
    return product.incluye_igv === true || product.incluye_igv === 't' || product.incluye_igv === 'true' || product.incluye_igv === 1 || product.incluye_igv === '1';
}

function productIgvRate(product) {
    const rate = parseFloat(product.porcentaje_igv);
    return Number.isFinite(rate) ? rate : 18;
}

function getProductUnitSalePrice(product) {
    const base = parseFloat(product.precio_venta) || 0;
    if (productAffectationType(product) === 'GRAV' && !productIncludesIgv(product)) {
        return base * (1 + (productIgvRate(product) / 100));
    }
    return base;
}

function computeCartTotals(items = cart) {
    return items.reduce((acc, item) => {
        const product = item.product || item;
        const qty = parseFloat(item.qty ?? item.cantidad ?? 0) || 0;
        const affectationType = productAffectationType(product);
        const basePrice = parseFloat(product.precio_venta) || 0;
        const rate = affectationType === 'GRAV' ? productIgvRate(product) : 0;
        const includesIgv = affectationType === 'GRAV' ? productIncludesIgv(product) : false;
        const lineValue = affectationType === 'GRAV'
            ? (includesIgv ? ((getProductUnitSalePrice(product) / (1 + (rate / 100))) * qty) : (basePrice * qty))
            : (basePrice * qty);
        const lineIgv = affectationType === 'GRAV'
            ? (includesIgv ? ((getProductUnitSalePrice(product) * qty) - lineValue) : ((basePrice * (rate / 100)) * qty))
            : 0;
        const lineTotal = affectationType === 'GRAV'
            ? (includesIgv ? (getProductUnitSalePrice(product) * qty) : ((basePrice + (basePrice * (rate / 100))) * qty))
            : (basePrice * qty);

        if (affectationType === 'GRAV') acc.gravada += lineValue;
        else if (affectationType === 'EXO') acc.exonerada += lineValue;
        else acc.inafecta += lineValue;

        acc.subtotal += lineValue;
        acc.igv += lineIgv;
        acc.total += lineTotal;
        return acc;
    }, { subtotal: 0, gravada: 0, exonerada: 0, inafecta: 0, igv: 0, total: 0 });
}

function loadSeriesDisponibles() {
    fetch(BASE + 'modules/ventas/api.php?action=series_disponibles')
        .then(r => r.json())
        .then(data => {
            seriesByType = data || { boleta: [], factura: [], ticket: [] };
            onComprobanteChange();
        })
        .catch(() => {});
}

// ---- Smart product sort (localStorage) ----
const USAGE_KEY = 'pos_usage_<?= (int)sesionSucursal() ?>';

function getProductUsage() {
    try { return JSON.parse(localStorage.getItem(USAGE_KEY) || '{}'); } catch { return {}; }
}

function updateProductUsage(productIds) {
    const usage = getProductUsage();
    const now   = Date.now();
    productIds.forEach(id => {
        if (!usage[id]) usage[id] = { count: 0, lastUsed: 0 };
        usage[id].count++;
        usage[id].lastUsed = now;
    });
    localStorage.setItem(USAGE_KEY, JSON.stringify(usage));
}

function sortProductsSmart(products) {
    return [...products].sort((a, b) => {
        const sold = (parseFloat(b.total_vendido) || 0) - (parseFloat(a.total_vendido) || 0);
        return sold !== 0 ? sold : a.nombre.localeCompare(b.nombre, 'es');
    });
}

// ---- Cargar Productos ----
function loadProducts() {
    fetch(BASE + 'modules/ventas/api.php?action=productos')
        .then(r => r.json())
        .then(data => {
            allProducts = data;
            showAllProducts = false;
            renderProducts(sortProductsSmart(allProducts), GRID_LIMIT);
        })
        .catch(() => showToast('Error al cargar productos', 'error'));
}

function showAllProductsGrid() {
    showAllProducts = true;
    filterProducts(document.getElementById('search-input').value, currentCat);
}

function renderProducts(products, limit) {
    const grid = document.getElementById('products-grid');
    if (!products.length) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--text-light);padding:40px"><i class="fas fa-box-open" style="font-size:1.5rem"></i><p style="margin-top:10px">No se encontraron productos</p></div>';
        return;
    }

    const buildCard = p => {
        const isOut = parseInt(p.stock) <= 0;
        const isLow = !isOut && parseInt(p.stock) <= parseInt(p.stock_minimo);
        const enConteo = p.en_conteo_inventario === true || p.en_conteo_inventario === 't';
        const cls   = enConteo ? 'en-conteo' : (isOut ? 'out-stock' : (isLow ? 'low-stock' : ''));
        const stockLabel = enConteo
            ? `<span class="product-stock en-conteo"><i class="fas fa-clipboard-check"></i> En conteo de inventario</span>`
            : (isOut
                ? `<span class="product-stock out">Agotado</span>`
                : (isLow
                    ? `<span class="product-stock low">Stock: ${p.stock} ⚠</span>`
                    : `<span class="product-stock">Stock: ${p.stock}</span>`));
        const precioNormal = getProductUnitSalePrice(p);
        const tienePromo = p.precio_promocional_base != null && parseFloat(p.precio_promocional_base) < parseFloat(p.precio_venta);
        const precioHtml = tienePromo
            ? `<div class="product-price">
                   <span style="text-decoration:line-through;color:var(--text-light);font-size:.8em;margin-right:5px">S/ ${precioNormal.toFixed(2)}</span>
                   <span style="color:var(--danger)">S/ ${getProductUnitSalePrice({ ...p, precio_venta: p.precio_promocional_base }).toFixed(2)}</span>
                   <i class="fas fa-tag" style="color:var(--danger);font-size:.8em;margin-left:3px" title="Promoción vigente"></i>
               </div>`
            : `<div class="product-price">S/ ${precioNormal.toFixed(2)}</div>`;
        return `
        <div class="product-card ${cls}" onclick="addToCart(${p.id})" data-id="${p.id}">
            ${p.favorito == 't' ? '<span class="fav-icon"><i class="fas fa-star"></i></span>' : ''}
            <div class="product-name">${p.nombre}</div>
            <div class="product-lab">${p.laboratorio || ''}</div>
            ${precioHtml}
            ${stockLabel}
        </div>`;
    };

    const total    = products.length;
    const truncate = limit && total > limit;
    const topCards = truncate ? products.slice(0, limit) : products;
    const topIds   = new Set(topCards.map(p => p.id));

    // Próximos a agotar: solo en la vista por defecto (con límite), máx 5, más críticos primero
    const alertCards = truncate
        ? allProducts
            .filter(p => parseInt(p.stock) > 0 && parseInt(p.stock) <= parseInt(p.stock_minimo) && !topIds.has(p.id))
            .sort((a, b) => parseInt(a.stock) - parseInt(b.stock))
            .slice(0, 5)
        : [];

    let html = topCards.map(buildCard).join('');

    if (alertCards.length) {
        html += `
        <div style="grid-column:1/-1;display:flex;align-items:center;gap:8px;padding:6px 2px">
            <div style="flex:1;height:1px;background:var(--warning);opacity:.5"></div>
            <span style="font-size:.72rem;font-weight:600;color:var(--warning);white-space:nowrap">
                <i class="fas fa-exclamation-triangle"></i>&nbsp;Próximos a agotar
            </span>
            <div style="flex:1;height:1px;background:var(--warning);opacity:.5"></div>
        </div>
        ${alertCards.map(buildCard).join('')}`;
    }

    if (truncate) {
        html += `<div style="grid-column:1/-1;display:flex;justify-content:center;padding:10px 0">
            <button class="btn-ver-todos" onclick="showAllProductsGrid()">
                <i class="fas fa-th-large"></i> Ver todos los productos (${total})
            </button>
        </div>`;
    }

    grid.innerHTML = html;
}

// ---- Búsqueda ----
function setupSearch() {
    let timer;
    document.getElementById('search-input').addEventListener('input', e => {
        clearTimeout(timer);
        timer = setTimeout(() => filterProducts(e.target.value, currentCat), 200);
    });

    document.getElementById('cat-chips').addEventListener('click', e => {
        const chip = e.target.closest('.chip');
        if (!chip) return;
        document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        currentCat = parseInt(chip.dataset.cat);
        filterProducts(document.getElementById('search-input').value, currentCat);
    });
}

function filterProducts(query, catId) {
    let filtered = allProducts;
    if (catId > 0) filtered = filtered.filter(p => parseInt(p.categoria_id) === catId);
    if (query.trim()) {
        const q = query.toLowerCase().trim();
        filtered = filtered.filter(p =>
            p.nombre.toLowerCase().includes(q) ||
            p.codigo.toLowerCase().includes(q) ||
            (p.laboratorio && p.laboratorio.toLowerCase().includes(q))
        );
    }
    // Limit only when there's no active filter and the user hasn't clicked "Ver todos"
    const limit = (!query.trim() && catId <= 0 && !showAllProducts) ? GRID_LIMIT : null;
    renderProducts(sortProductsSmart(filtered), limit);
}

// ---- Carrito ----
const _presentacionesCache = {};
let _productoParaUnidad = null;
let _presentacionesActuales = [];

function addToCart(productId) {
    const product = allProducts.find(p => parseInt(p.id) === productId);
    if (!product || parseInt(product.stock) <= 0) return;
    if (product.en_conteo_inventario === true || product.en_conteo_inventario === 't') {
        showToast(`"${product.nombre}" no se puede vender: la categoría "${product.categoria || ''}" está en conteo de inventario.`, 'error');
        return;
    }

    if (_presentacionesCache[productId] !== undefined) {
        continuarAgregarAlCarrito(product, _presentacionesCache[productId]);
        return;
    }
    fetch(BASE + `modules/inventario/api.php?action=precios_unidad_listar&producto_id=${productId}`)
        .then(r => r.json())
        .then(data => {
            _presentacionesCache[productId] = data || [];
            continuarAgregarAlCarrito(product, _presentacionesCache[productId]);
        })
        .catch(() => continuarAgregarAlCarrito(product, []));
}

function continuarAgregarAlCarrito(product, presentaciones) {
    if (!presentaciones.length) {
        agregarAlCarritoConUnidad(product, null, 1);
        return;
    }
    _productoParaUnidad = product;
    _presentacionesActuales = presentaciones;

    document.getElementById('unidad-medida-producto-nombre').textContent = product.nombre;

    const opciones = [{ unidad_medida: null, precio_venta: product.precio_venta }, ...presentaciones];
    document.getElementById('unidad-medida-opciones').innerHTML = opciones.map(op => {
        const precioMostrado = getProductUnitSalePrice({ ...product, precio_venta: op.precio_venta });
        const label = op.unidad_medida || 'Unidad';
        const attr = op.unidad_medida ? `'${op.unidad_medida.replace(/'/g, "\\'")}'` : 'null';
        return `<button type="button" class="btn btn-outline" style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px"
            onclick="seleccionarUnidadMedida(${attr})">
            <span style="font-weight:600">${label}</span>
            <span style="color:var(--primary);font-weight:700">S/ ${precioMostrado.toFixed(2)}</span>
        </button>`;
    }).join('');
    openModal('modal-unidad-medida');
}

function seleccionarUnidadMedida(unidadMedida) {
    const product = _productoParaUnidad;
    closeModal('modal-unidad-medida');
    if (!product) return;

    if (!unidadMedida) {
        agregarAlCarritoConUnidad(product, null, 1);
        return;
    }
    const presentacion = _presentacionesActuales.find(p => p.unidad_medida === unidadMedida);
    if (!presentacion) return;
    agregarAlCarritoConUnidad(product, presentacion, parseFloat(presentacion.cantidad) || 1);
}

function agregarAlCarritoConUnidad(product, presentacion, factor) {
    const productId    = parseInt(product.id);
    const unidadMedida = presentacion ? presentacion.unidad_medida : null;
    const key          = productId + '|' + (unidadMedida || '');
    const stockBase     = parseFloat(product.stock) || 0;
    const maxQty        = Math.floor(stockBase / factor);

    if (maxQty <= 0) { showToast('No hay stock disponible', 'error'); return; }

    const existing = cart.find(i => i.key === key);
    if (existing) {
        if (existing.qty >= maxQty) {
            showToast('No hay más stock disponible', 'error'); return;
        }
        existing.qty++;
    } else {
        // Promociones (Reportes, Bloque 2): solo aplica a la unidad base (sin
        // presentacion), igual que el servidor en registrar_venta -- asi el
        // carrito muestra el mismo precio que realmente se va a cobrar, en
        // vez de la tarjeta de busqueda mostrando el descuento y el carrito
        // ignorandolo.
        const tienePromo = !presentacion && product.precio_promocional_base != null
            && parseFloat(product.precio_promocional_base) < parseFloat(product.precio_venta);
        const productClone = presentacion
            ? { ...product, precio_venta: presentacion.precio_venta }
            : (tienePromo ? { ...product, precio_venta: product.precio_promocional_base } : product);
        cart.push({
            id: productId,
            key,
            product: productClone,
            qty: 1,
            unidadMedida,
            factorEquivalencia: factor,
        });
    }
    renderCart();
    // Feedback visual
    const card = document.querySelector(`.product-card[data-id="${productId}"]`);
    if (card) {
        card.style.transform = 'scale(.96)';
        setTimeout(() => card.style.transform = '', 150);
    }
}

function changeQty(key, delta) {
    const idx = cart.findIndex(i => i.key === key);
    if (idx === -1) return;
    const item = cart[idx];
    if (delta > 0) {
        const maxQty = Math.floor((parseFloat(item.product.stock) || 0) / item.factorEquivalencia);
        if (item.qty >= maxQty) { showToast('No hay más stock disponible', 'error'); return; }
    }
    item.qty += delta;
    if (item.qty <= 0) cart.splice(idx, 1);
    renderCart();
}

function removeItem(key) {
    cart = cart.filter(i => i.key !== key);
    renderCart();
}

function clearCart() {
    cart = [];
    renderCart();
}

function openCartDrawer() {
    document.querySelector('.pos-right').classList.add('cart-open');
    document.getElementById('cart-drawer-backdrop').classList.add('active');
}
function closeCartDrawer() {
    document.querySelector('.pos-right').classList.remove('cart-open');
    document.getElementById('cart-drawer-backdrop').classList.remove('active');
}

function renderCart() {
    const itemsEl   = document.getElementById('cart-items');
    const emptyEl   = document.getElementById('cart-empty');
    const summaryEl = document.getElementById('cart-summary');
    const countEl   = document.getElementById('cart-count');
    const btnVender = document.getElementById('btn-vender');

    const totalQty = cart.reduce((s, i) => s + i.qty, 0);
    countEl.textContent = totalQty;
    const fabBadge = document.getElementById('cart-fab-badge');
    if (fabBadge) { fabBadge.textContent = totalQty; fabBadge.classList.toggle('has-items', totalQty > 0); }

    if (!cart.length) {
        emptyEl.style.display = 'flex';
        summaryEl.style.display = 'none';
        btnVender.disabled = true;
        // Limpiar items pero dejar el empty div
        const oldItems = itemsEl.querySelectorAll('.cart-item');
        oldItems.forEach(el => el.remove());
        return;
    }

    emptyEl.style.display = 'none';
    summaryEl.style.display = 'block';
    btnVender.disabled = !cajaEsMia;

    // Rebuild items
    const oldItems = itemsEl.querySelectorAll('.cart-item');
    oldItems.forEach(el => el.remove());

    cart.forEach(item => {
        const subtotal = item.qty * getProductUnitSalePrice(item.product);
        const unidadBadge = item.unidadMedida ? ` <span style="color:var(--primary);font-weight:700">(${item.unidadMedida})</span>` : '';
        const div = document.createElement('div');
        div.className = 'cart-item';
        div.innerHTML = `
            <div class="cart-item-info">
                <div class="cart-item-name">${item.product.nombre}${unidadBadge}</div>
                <div class="cart-item-price">S/ ${getProductUnitSalePrice(item.product).toFixed(2)} c/u</div>
                <div class="cart-item-controls">
                    <button class="qty-btn" onclick="changeQty('${item.key}',-1)"><i class="fas fa-minus"></i></button>
                    <span class="qty-value">${item.qty}</span>
                    <button class="qty-btn" onclick="changeQty('${item.key}',1)"><i class="fas fa-plus"></i></button>
                </div>
            </div>
            <div class="cart-item-right">
                <button class="cart-item-del" onclick="removeItem('${item.key}')"><i class="fas fa-trash"></i></button>
                <div class="cart-item-total">S/ ${subtotal.toFixed(2)}</div>
            </div>`;
        itemsEl.insertBefore(div, emptyEl);
    });

    const totals = computeCartTotals();
    const descuento  = (document.getElementById('modal-cobro')?.classList.contains('open'))
        ? getCobroDescuentoValue()
        : 0;
    const totalFinal = Math.max(0, round2(totals.total - descuento));

    document.getElementById('sum-subtotal').textContent  = `S/ ${totals.subtotal.toFixed(2)}`;
    document.getElementById('sum-descuento').textContent = `-S/ ${descuento.toFixed(2)}`;
    document.getElementById('sum-igv').textContent       = `S/ ${totals.igv.toFixed(2)}`;
    document.getElementById('sum-total').textContent     = `S/ ${totalFinal.toFixed(2)}`;
    document.getElementById('footer-total').textContent   = `S/ ${totalFinal.toFixed(2)}`;

    const modalCobro = document.getElementById('modal-cobro');
    if (modalCobro?.classList.contains('open')) {
        document.getElementById('cobro-total-venta').textContent = `S/ ${totalFinal.toFixed(2)}`;
        document.getElementById('cobro-total').textContent       = `S/ ${totalFinal.toFixed(2)}`;
        if (creditPaymentEnabled) {
            syncCreditPaymentRowsWithTotal();
        } else if (!splitPaymentEnabled) {
            syncMontoClienteWithTotal();
        } else {
            updateSplitPaymentSummary();
        }
        calcularVuelto();
    }
}

// ---- Cliente ----
function renderSelectedCliente() {
    const clienteInfo = document.getElementById('cliente-info');
    const clienteSearch = document.getElementById('cliente-search');
    const clienteNombre = document.getElementById('cliente-nombre');

    if (!clienteInfo || !clienteSearch || !clienteNombre) {
        return;
    }

    if (!selectedCliente) {
        clienteInfo.style.display = 'none';
        clienteSearch.style.display = 'block';
        return;
    }

    let label = clienteDisplayName(selectedCliente);
    const documento = selectedCliente.numero_documento || selectedCliente.ruc || selectedCliente.dni || '';
    if (documento) {
        if (selectedCliente.tipo_documento_codigo === '6' || selectedCliente.ruc) {
            label += ' · RUC: ' + documento;
        } else if (selectedCliente.tipo_documento_codigo === '1' || selectedCliente.dni) {
            label += ' · DNI: ' + documento;
        } else {
            label += ' · DOC: ' + documento;
        }
    }

    clienteNombre.textContent = label;
    clienteInfo.style.display = 'block';
    clienteSearch.style.display = 'none';
    clienteSearch.value = '';
}

function setSelectedCliente(cliente, { preserveAsDefault = false } = {}) {
    selectedCliente = cliente || null;
    if (preserveAsDefault && cliente) {
        defaultCliente = cliente;
    }
    renderSelectedCliente();
}

function loadDefaultCliente() {
    fetch(BASE + 'modules/ventas/api.php?action=default_cliente')
        .then(r => r.json())
        .then(data => {
            if (data && data.id) {
                setSelectedCliente(data, { preserveAsDefault: true });
            }
        })
        .catch(() => {});
}

function setupClienteSearch() {
    let timer;
    document.getElementById('cliente-search').addEventListener('input', e => {
        clearTimeout(timer);
        const q = e.target.value.trim();
        if (!q) {
            if (defaultCliente) {
                setSelectedCliente(defaultCliente);
            }
            return;
        }
        timer = setTimeout(() => {
            fetch(BASE + `modules/ventas/api.php?action=buscar_cliente&q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(data => {
                    if (data && data.id) {
                        setSelectedCliente(data);
                    }
                });
        }, 400);
    });
}

function clearCliente() {
    if (defaultCliente) {
        setSelectedCliente(defaultCliente);
        return;
    }
    setSelectedCliente(null);
    const legacySearch = document.getElementById('cliente-search');
    if (legacySearch) legacySearch.value = '';
}

function escHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function bootstrapPosClienteUI() {
    const modal = document.getElementById('modal-nuevo-cliente');
    if (!modal) return;

    const body = modal.querySelector('.modal-body');
    if (body) {
        body.innerHTML = `
            <input type="hidden" id="nc-ubigeo">
            <div class="cliente-form-grid c2">
                <div class="cliente-form-group">
                    <label>Tipo de documento *</label>
                    <select id="nc-tipo-documento" onchange="actualizarEstadoNuevoCliente()"></select>
                </div>
                <div class="cliente-form-group">
                    <label>Numero de documento *</label>
                    <div class="cliente-input-lookup">
                        <input type="text" id="nc-numero-documento" placeholder="Ingresa el documento" autocomplete="off">
                        <button type="button" class="btn btn-outline" id="btn-consultar-cliente" onclick="consultarDocumentoNuevoCliente()">
                            <i class="fas fa-search"></i> Consultar
                        </button>
                    </div>
                    <div class="cliente-lookup-note" id="nc-lookup-note">Consulta automatica disponible para DNI y RUC.</div>
                </div>
            </div>
            <div style="margin:14px 0">
                <span class="cliente-doc-chip" id="nc-doc-chip"><i class="fas fa-id-card"></i> Documento</span>
            </div>
            <div class="cliente-form-grid c2">
                <div class="cliente-form-group">
                    <label id="nc-label-nombres">Nombres o razon social *</label>
                    <input type="text" id="nc-nombres" placeholder="Nombre del cliente" autocomplete="off">
                </div>
                <div class="cliente-form-group" id="nc-grupo-apellidos">
                    <label>Apellidos</label>
                    <input type="text" id="nc-apellidos" placeholder="Apellidos del cliente" autocomplete="off">
                </div>
            </div>
            <div class="cliente-form-grid c2" style="margin-top:14px">
                <div class="cliente-form-group">
                    <label>Telefono</label>
                    <input type="text" id="nc-telefono" maxlength="20" placeholder="987654321" autocomplete="off">
                </div>
                <div class="cliente-form-group">
                    <label>Correo</label>
                    <input type="email" id="nc-email" placeholder="correo@ejemplo.com" autocomplete="off">
                </div>
            </div>
            <div class="cliente-form-grid" style="margin-top:14px">
                <div class="cliente-form-group">
                    <label>Direccion</label>
                    <textarea id="nc-direccion" placeholder="Direccion del cliente"></textarea>
                </div>
            </div>
            <div class="cliente-ubigeo-box" id="nc-ubigeo-info">Sin ubigeo consultado.</div>
        `;
    }
}

function clienteDisplayName(cliente) {
    if (!cliente) return '';
    const razon = (cliente.razon_social || '').trim();
    if (razon) return razon;
    const completo = (cliente.nombre_completo || '').trim();
    if (completo) return completo;
    return ((cliente.nombres || '') + ' ' + (cliente.apellidos || '')).trim();
}

function clienteDocumentLabel(cliente) {
    const documento = cliente?.numero_documento || cliente?.ruc || cliente?.dni || '';
    if (!documento) return '';
    if (cliente?.tipo_documento_codigo === '6' || cliente?.ruc) return `RUC: ${documento}`;
    if (cliente?.tipo_documento_codigo === '1' || cliente?.dni) return `DNI: ${documento}`;
    return `DOC: ${documento}`;
}

function mapClienteToSelectOption(cliente) {
    const nombre = clienteDisplayName(cliente);
    const doc = clienteDocumentLabel(cliente);
    return {
        id: String(cliente.id),
        text: doc ? `${nombre} - ${doc}` : nombre,
        cliente,
    };
}

function renderClienteOption(option) {
    if (!option.id) return option.text;
    const cliente = option.cliente || option.element?._cliente;
    if (!cliente) return option.text;

    const nombre = clienteDisplayName(cliente);
    const documento = clienteDocumentLabel(cliente);
    return window.jQuery(`
        <div>
            <div class="cliente-option-main">${escHtml(nombre)}</div>
            <div class="cliente-option-sub">${escHtml(documento || 'Sin documento')}</div>
        </div>
    `);
}

function renderClienteSelection(option) {
    if (!option.id) return option.text || '';
    const cliente = option.cliente || option.element?._cliente;
    if (!cliente) return option.text || '';
    return escHtml(clienteDisplayName(cliente));
}

function updateClienteMeta(cliente) {
    const meta = document.getElementById('cliente-info');
    const name = document.getElementById('cliente-nombre');
    const doc = document.getElementById('cliente-doc');
    if (!meta || !name || !doc) return;

    if (!cliente) {
        meta.style.display = 'none';
        name.textContent = '';
        doc.textContent = '';
        return;
    }

    const nombre = clienteDisplayName(cliente);
    const documento = clienteDocumentLabel(cliente);
    name.textContent = nombre;
    doc.textContent = documento || '';
    meta.style.display = 'block';
}

function updateCobroClienteResumen() {
    const wrap = document.getElementById('cobro-cliente-resumen');
    const label = document.getElementById('cobro-cliente-label');
    if (!wrap || !label) return;

    if (!selectedCliente) {
        wrap.style.display = 'none';
        label.textContent = '';
        return;
    }

    const nombre = clienteDisplayName(selectedCliente);
    const documento = clienteDocumentLabel(selectedCliente);
    label.textContent = documento ? `${nombre} - ${documento}` : nombre;
    wrap.style.display = 'block';
}

function getCobroTotalValue() {
    const totalText = document.getElementById('cobro-total')?.textContent || 'S/ 0.00';
    return parseFloat(totalText.replace(/[^\d.-]/g, '')) || 0;
}

function formatMoneyValue(value) {
    return `S/ ${Number(value || 0).toFixed(2)}`;
}

function getCobroDescuentoValue() {
    const el = document.getElementById('cobro-descuento');
    if (!el) return 0;
    return Math.max(0, round2(parseFloat(el.value || 0)));
}

function getCheckoutTotalAmount() {
    const gross = computeCartTotals().total;
    return Math.max(0, round2(gross - getCobroDescuentoValue()));
}

function getPaymentMethodOptions(selected = 'efectivo') {
    const methods = [
        ['efectivo', 'Efectivo'],
        ['yape', 'Yape'],
        ['plin', 'Plin'],
        ['tarjeta', 'Visa/Mastercard'],
        ['transferencia', 'Transferencia'],
    ];

    return methods.map(([value, label]) => (
        `<option value="${value}" ${String(selected) === value ? 'selected' : ''}>${label}</option>`
    )).join('');
}

function syncMontoClienteWithTotal(force = false) {
    const input = document.getElementById('monto-cliente');
    if (!input) return;

    const total = getCheckoutTotalAmount();
    if (force || input.value === '' || Number(input.value) === 0) {
        input.value = total.toFixed(2);
    }
}

function setToggleState(checkId, trackId, checked) {
    const cb = document.getElementById(checkId);
    const track = document.getElementById(trackId);
    const dot = track?.querySelector('span');
    if (cb) {
        cb.checked = !!checked;
    }
    if (track && dot) {
        if (checked) {
            track.style.background = 'var(--primary)';
            dot.style.transform = 'translateX(17px)';
        } else {
            track.style.background = '#d1d5db';
            dot.style.transform = 'translateX(0)';
        }
    }
}

function getSplitPaymentRowsFromState() {
    return splitPaymentRows
        .map(row => ({
            method: String(row.method || 'efectivo').trim() || 'efectivo',
            amount: Math.max(0, round2(row.amount)),
        }))
        .filter(row => row.amount > 0);
}

function round2(value) {
    return Math.round((Number(value) || 0) * 100) / 100;
}

function getMinimumInstallmentDate() {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function renderSplitPaymentPanel() {
    const panel = document.getElementById('split-payment-panel');
    const rowsEl = document.getElementById('split-payment-rows');
    const toggleBtn = document.getElementById('btn-dividir-cuenta');
    const toggleWrap = toggleBtn?.closest('div[style*="text-align:right"]');
    const simpleSection = document.getElementById('pago-simple-section');
    const creditPanel = document.getElementById('credit-payment-panel');
    if (!panel || !rowsEl) return;

    panel.style.display = splitPaymentEnabled && !creditPaymentEnabled ? 'block' : 'none';
    if (toggleWrap) {
        toggleWrap.style.display = creditPaymentEnabled ? 'none' : '';
    }
    if (simpleSection) {
        simpleSection.style.display = (!splitPaymentEnabled && !creditPaymentEnabled) ? 'block' : 'none';
    }
    if (creditPanel) {
        creditPanel.style.display = creditPaymentEnabled ? 'block' : 'none';
    }
    if (toggleBtn) {
        toggleBtn.innerHTML = splitPaymentEnabled
            ? '<i class="fas fa-minus"></i> Volver al cobro simple'
            : '<i class="fas fa-plus"></i> Dividir cuenta';
    }

    if (!splitPaymentEnabled || creditPaymentEnabled) {
        rowsEl.innerHTML = '';
        updateSplitPaymentSummary();
        return;
    }

    if (!splitPaymentRows.length) {
        splitPaymentRows = [{
            method: document.getElementById('tipo-pago')?.value || 'efectivo',
            amount: getCheckoutTotalAmount(),
        }];
    }

    rowsEl.innerHTML = splitPaymentRows.map((row, index) => {
        const canDelete = splitPaymentRows.length > 1;
        return `
            <div class="split-pay-row">
                <div>
                    <label class="form-label" style="font-size:.71rem">Método ${index + 1}</label>
                    <select class="form-control" style="font-size:.82rem"
                        onchange="updateSplitPaymentRow(${index}, 'method', this.value)">
                        ${getPaymentMethodOptions(row.method)}
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:.71rem">Monto</label>
                    <input type="number" class="form-control" step="0.01" min="0"
                        value="${Number(row.amount || 0).toFixed(2)}"
                        oninput="updateSplitPaymentRow(${index}, 'amount', this.value)">
                </div>
                <div>
                    <button type="button" class="btn btn-outline btn-sm" style="width:32px;height:32px;padding:0"
                        title="Eliminar método" ${canDelete ? `onclick="removeSplitPaymentRow(${index})"` : 'disabled'}>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');

    updateSplitPaymentSummary();
}

function getCreditInstallmentRowsFromState() {
    return creditPaymentRows
        .map(row => ({
            amount: Math.max(0, round2(row.amount)),
            due_date: String(row.due_date || '').trim(),
        }))
        .filter(row => row.amount > 0 && row.due_date);
}

function renderCreditPaymentPanel() {
    const panel = document.getElementById('credit-payment-panel');
    const rowsEl = document.getElementById('credit-payment-rows');
    if (!panel || !rowsEl) return;

    panel.style.display = creditPaymentEnabled ? 'block' : 'none';
    if (!creditPaymentEnabled) {
        rowsEl.innerHTML = '';
        updateCreditPaymentSummary();
        return;
    }

    if (!creditPaymentRows.length) {
        creditPaymentRows = [{
            amount: getCheckoutTotalAmount(),
            due_date: getMinimumInstallmentDate(),
        }];
    }
    syncCreditPaymentRowsWithTotal();

    rowsEl.innerHTML = creditPaymentRows.map((row, index) => {
        const canDelete = creditPaymentRows.length > 1;
        return `
            <div class="credit-pay-row">
                <div>
                    <label class="form-label" style="font-size:.71rem">Cuota ${index + 1}</label>
                    <input type="number" class="form-control" step="0.01" min="0"
                        value="${Number(row.amount || 0).toFixed(2)}"
                        oninput="updateCreditPaymentRow(${index}, 'amount', this.value)">
                </div>
                <div>
                    <label class="form-label" style="font-size:.71rem">Vence</label>
                    <input type="date" class="form-control"
                        min="${getMinimumInstallmentDate()}"
                        value="${row.due_date || getMinimumInstallmentDate()}"
                        onchange="updateCreditPaymentRow(${index}, 'due_date', this.value)">
                </div>
                <div>
                    <button type="button" class="btn btn-outline btn-sm" style="width:32px;height:32px;padding:0"
                        title="Eliminar cuota" ${canDelete ? `onclick="removeCreditInstallmentRow(${index})"` : 'disabled'}>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');

    updateCreditPaymentSummary();
}

function toggleCreditoSale(force = null) {
    const check = document.getElementById('check-credito');
    creditPaymentEnabled = force === null ? !!check?.checked : !!force;

    setToggleState('check-credito', 'track-credito', creditPaymentEnabled);

    if (creditPaymentEnabled) {
        splitPaymentEnabled = false;
        splitPaymentRows = [];
        const tipoPago = document.getElementById('tipo-pago');
        if (tipoPago) tipoPago.value = 'efectivo';
        const montoRecibido = document.getElementById('monto-recibido');
        if (montoRecibido) montoRecibido.value = '0.00';
        const montoCliente = document.getElementById('monto-cliente');
        if (montoCliente) montoCliente.value = '0.00';
        creditPaymentRows = [{
            amount: getCheckoutTotalAmount(),
            due_date: getMinimumInstallmentDate(),
        }];
    } else {
        creditPaymentRows = [];
        const tipoPago = document.getElementById('tipo-pago');
        if (tipoPago) tipoPago.value = 'efectivo';
        syncMontoClienteWithTotal(true);
    }

    renderSplitPaymentPanel();
    renderCreditPaymentPanel();
    calcularVuelto();
}

function addCreditInstallmentRow() {
    creditPaymentEnabled = true;
    creditPaymentRows.push({
        amount: 0,
        due_date: getMinimumInstallmentDate(),
    });
    setToggleState('check-credito', 'track-credito', true);
    const tipoPago = document.getElementById('tipo-pago');
    if (tipoPago) tipoPago.value = 'efectivo';
    renderSplitPaymentPanel();
    renderCreditPaymentPanel();
    calcularVuelto();
}

function removeCreditInstallmentRow(index) {
    creditPaymentRows.splice(index, 1);
    if (!creditPaymentRows.length) {
        toggleCreditoSale(false);
        return;
    }
    renderSplitPaymentPanel();
    renderCreditPaymentPanel();
    calcularVuelto();
}

function updateCreditPaymentRow(index, field, value) {
    if (!creditPaymentRows[index]) return;
    if (field === 'amount') {
        creditPaymentRows[index].amount = round2(value);
    } else if (field === 'due_date') {
        creditPaymentRows[index].due_date = value;
    }
    updateCreditPaymentSummary();
    calcularVuelto();
}

function updateCreditPaymentSummary() {
    const total = getCheckoutTotalAmount();
    const rows = getCreditInstallmentRowsFromState();
    const totalCredits = rows.reduce((sum, row) => sum + row.amount, 0);
    const remaining = round2(total - totalCredits);

    const totalEl = document.getElementById('credit-total-methods');
    const remainingEl = document.getElementById('credit-remaining');
    if (totalEl) totalEl.textContent = formatMoneyValue(totalCredits);
    if (remainingEl) {
        remainingEl.textContent = formatMoneyValue(Math.max(0, remaining));
        remainingEl.parentElement?.classList.toggle('danger', remaining > 0.01);
        remainingEl.parentElement?.classList.toggle('success', Math.abs(remaining) <= 0.01);
    }
}

function syncCreditPaymentRowsWithTotal(force = false) {
    if (!creditPaymentEnabled) return;
    const total = getCheckoutTotalAmount();

    if (!creditPaymentRows.length) {
        creditPaymentRows = [{
            amount: total,
            due_date: getMinimumInstallmentDate(),
        }];
    } else if (creditPaymentRows.length === 1 && (force || !creditPaymentRows[0].due_date || Math.abs(round2(creditPaymentRows[0].amount) - total) > 0.01)) {
        creditPaymentRows[0].amount = total;
        if (!creditPaymentRows[0].due_date) {
            creditPaymentRows[0].due_date = getMinimumInstallmentDate();
        }
    }

    updateCreditPaymentSummary();

    const rowsEl = document.getElementById('credit-payment-rows');
    if (rowsEl && creditPaymentRows.length) {
        rowsEl.querySelectorAll('.credit-pay-row').forEach((rowEl, index) => {
            const row = creditPaymentRows[index];
            if (!row) return;
            const amountInput = rowEl.querySelector('input[type="number"]');
            const dateInput = rowEl.querySelector('input[type="date"]');
            if (amountInput) amountInput.value = Number(row.amount || 0).toFixed(2);
            if (dateInput && row.due_date) dateInput.value = row.due_date;
        });
    }
}

function toggleDividirCuenta() {
    if (creditPaymentEnabled) {
        toggleCreditoSale(false);
    }
    splitPaymentEnabled = !splitPaymentEnabled;
    if (splitPaymentEnabled) {
        const metodo = document.getElementById('tipo-pago')?.value || 'efectivo';
        splitPaymentRows = [{
            method: metodo,
            amount: getCheckoutTotalAmount(),
        }];
        syncMontoClienteWithTotal(true);
    } else {
        splitPaymentRows = [];
        document.getElementById('tipo-pago').value = 'efectivo';
    }
    renderSplitPaymentPanel();
    calcularVuelto();
}

function addSplitPaymentRow() {
    if (creditPaymentEnabled) {
        toggleCreditoSale(false);
    }
    splitPaymentEnabled = true;
    splitPaymentRows.push({
        method: 'efectivo',
        amount: 0,
    });
    renderSplitPaymentPanel();
    calcularVuelto();
}

function removeSplitPaymentRow(index) {
    splitPaymentRows.splice(index, 1);
    if (!splitPaymentRows.length) {
        splitPaymentEnabled = false;
    }
    renderSplitPaymentPanel();
    calcularVuelto();
}

function updateSplitPaymentRow(index, field, value) {
    if (!splitPaymentRows[index]) return;

    if (field === 'method') {
        splitPaymentRows[index].method = value;
        if (index === 0) {
            const mainMethod = document.getElementById('tipo-pago');
            if (mainMethod) mainMethod.value = value;
        }
    } else if (field === 'amount') {
        splitPaymentRows[index].amount = round2(value);
    }

    updateSplitPaymentSummary();
    calcularVuelto();
}

function updateSplitPaymentSummary() {
    const total = getCheckoutTotalAmount();
    const breakdown = getSplitPaymentRowsFromState();
    const totalMethods = breakdown.reduce((sum, row) => sum + row.amount, 0);
    const remaining = round2(total - totalMethods);

    const totalEl = document.getElementById('split-total-methods');
    const remainingEl = document.getElementById('split-remaining');
    if (totalEl) totalEl.textContent = formatMoneyValue(totalMethods);
    if (remainingEl) {
        remainingEl.textContent = formatMoneyValue(Math.max(0, remaining));
        remainingEl.parentElement?.classList.toggle('danger', remaining > 0.01);
        remainingEl.parentElement?.classList.toggle('success', Math.abs(remaining) <= 0.01);
    }

    const input = document.getElementById('monto-cliente');
    if (splitPaymentEnabled && breakdown.length === 1 && breakdown[0].method === 'efectivo' && input) {
        input.value = breakdown[0].amount.toFixed(2);
    }
}

function onDescuentoChange() {
    const total = getCheckoutTotalAmount();
    document.getElementById('cobro-total-venta').textContent = formatMoneyValue(total);
    document.getElementById('cobro-total').textContent = formatMoneyValue(total);
    if (creditPaymentEnabled) {
        syncCreditPaymentRowsWithTotal();
    } else if (!splitPaymentEnabled) {
        syncMontoClienteWithTotal(true);
    } else {
        updateSplitPaymentSummary();
    }
    calcularVuelto();
    renderCart();
}

function getSplitPaymentPayload(total) {
    const rows = getSplitPaymentRowsFromState();
    const sum = round2(rows.reduce((acc, row) => acc + row.amount, 0));
    const remainder = round2(total - sum);
    const cashTotal = round2(rows
        .filter(row => row.method === 'efectivo')
        .reduce((acc, row) => acc + row.amount, 0));

    return { rows, sum, remainder, cashTotal };
}

function updateClienteSelectValue(cliente) {
    if (typeof window.jQuery === 'undefined') return;
    const $select = window.jQuery('#cliente-select');
    if (!$select.length) return;

    if (!cliente) {
        $select.val(null).trigger('change');
        updateClienteMeta(null);
        return;
    }

    const optionValue = String(cliente.id);
    let option = $select.find(`option[value="${optionValue}"]`);
    if (!option.length) {
        option = window.jQuery(new Option(mapClienteToSelectOption(cliente).text, optionValue, true, true));
        option[0]._cliente = cliente;
        $select.append(option);
    } else {
        option.prop('selected', true);
        option[0]._cliente = cliente;
    }

    $select.trigger('change');
    updateClienteMeta(cliente);
}

function setSelectedCliente(cliente, { preserveAsDefault = false, syncSelect = true } = {}) {
    selectedCliente = cliente || null;
    if (preserveAsDefault && cliente) {
        defaultCliente = cliente;
    }
    if (syncSelect) {
        updateClienteSelectValue(selectedCliente);
    }
    updateCobroClienteResumen();
}

function focusClienteSelect() {
    if (typeof window.jQuery === 'undefined') return;
    const modal = document.getElementById('modal-cobro');
    if (modal && !modal.classList.contains('open')) {
        openModal('modal-cobro');
    }
    const $select = window.jQuery('#cliente-select');
    if ($select.length) {
        $select.select2('open');
    }
}

function setupClienteSelect() {
    if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') return;
    const $select = window.jQuery('#cliente-select');
    if (!$select.length) return;

    $select.select2({
        width: '100%',
        allowClear: false,
        placeholder: 'Buscar cliente por nombre, DNI o RUC',
        dropdownParent: window.jQuery('#modal-cobro'),
        ajax: {
            url: CLIENTES_API + '?action=listar',
            dataType: 'json',
            delay: 250,
            data: params => ({ q: params.term || '', estado: 'activo' }),
            processResults: data => ({
                results: (Array.isArray(data) ? data : []).map(mapClienteToSelectOption),
            }),
            cache: true,
        },
        templateResult: renderClienteOption,
        templateSelection: renderClienteSelection,
        escapeMarkup: markup => markup,
        minimumInputLength: 0,
        language: {
            inputTooShort: () => 'Escribe para buscar clientes',
            searching: () => 'Buscando clientes...',
            noResults: () => 'No se encontraron clientes',
        },
    });

    $select.on('select2:select', event => {
        const cliente = event.params?.data?.cliente || null;
        if (cliente) {
            setSelectedCliente(cliente, { syncSelect: false });
        }
    });

    $select.on('select2:clear', () => {
        updateClienteMeta(null);
        if (defaultCliente) {
            setTimeout(() => setSelectedCliente(defaultCliente), 0);
        } else {
            setSelectedCliente(null, { syncSelect: false });
        }
    });
}

function loadDefaultCliente() {
    fetch(BASE + 'modules/ventas/api.php?action=default_cliente')
        .then(r => r.json())
        .then(data => {
            if (data && data.id) {
                setSelectedCliente(data, { preserveAsDefault: true });
            }
        })
        .catch(() => {});
}

async function loadTiposDocumentoCliente() {
    try {
        const r = await fetch(CLIENTES_API + '?action=document_types');
        const data = await r.json();
        if (data.error) {
            throw new Error(data.message || 'No se pudieron cargar los tipos de documento');
        }

        tiposDocumentoCliente = data.items || [];
        const select = document.getElementById('nc-tipo-documento');
        if (!select) return;

        select.innerHTML = tiposDocumentoCliente.map(item =>
            `<option value="${item.id}" data-codigo="${escHtml(item.codigo)}" data-label="${escHtml(item.descripcion_documento || item.descripcion)}">${escHtml(item.descripcion_documento || item.descripcion)}</option>`
        ).join('');

        const predeterminado = tiposDocumentoCliente.find(item => item.codigo === '1') || tiposDocumentoCliente[0];
        if (predeterminado) {
            select.value = String(predeterminado.id);
        }

        actualizarEstadoNuevoCliente();
    } catch (error) {
        showToast('No se pudieron cargar los tipos de documento', 'error');
    }
}

function getTipoDocumentoNuevoCliente() {
    const select = document.getElementById('nc-tipo-documento');
    const option = select?.options?.[select.selectedIndex];
    if (!option) return null;
    return {
        id: parseInt(option.value, 10),
        codigo: option.dataset.codigo || '',
        label: option.dataset.label || option.textContent || '',
    };
}

function actualizarEstadoNuevoCliente() {
    const tipo = getTipoDocumentoNuevoCliente();
    const codigo = tipo?.codigo || '0';
    const esRuc = codigo === '6';
    const esConsultable = codigo === '1' || codigo === '6';

    document.getElementById('nc-label-nombres').textContent = esRuc ? 'Razon social *' : 'Nombres o razon social *';
    document.getElementById('nc-grupo-apellidos').style.display = esRuc ? 'none' : '';
    document.getElementById('nc-numero-documento').placeholder = codigo === '1' ? '12345678' : (codigo === '6' ? '20123456789' : 'Ingresa el documento');
    document.getElementById('nc-numero-documento').maxLength = codigo === '1' ? 8 : (codigo === '6' ? 11 : 15);
    document.getElementById('btn-consultar-cliente').disabled = !esConsultable;
    document.getElementById('nc-lookup-note').textContent = esConsultable
        ? `Consulta automatica disponible para ${tipo.label}.`
        : 'Para este tipo de documento el registro es manual.';
    document.getElementById('nc-doc-chip').innerHTML = `<i class="fas fa-id-card"></i> ${escHtml(tipo?.label || 'Documento')}`;

    if (esRuc) {
        document.getElementById('nc-apellidos').value = '';
    }
}

function actualizarUbigeoNuevoCliente(ubigeo) {
    const box = document.getElementById('nc-ubigeo-info');
    const codigo = String(ubigeo || '').trim();
    box.textContent = codigo ? `Ubigeo detectado: ${codigo}` : 'Sin ubigeo consultado.';
}

async function consultarDocumentoNuevoCliente() {
    const tipo = getTipoDocumentoNuevoCliente();
    const numero = document.getElementById('nc-numero-documento').value.trim();
    if (!tipo) {
        showToast('Debes seleccionar un tipo de documento', 'error');
        return;
    }
    if (!numero) {
        showToast('Debes ingresar el numero de documento', 'error');
        return;
    }

    const btn = document.getElementById('btn-consultar-cliente');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando';

    try {
        const r = await fetch(CLIENTES_API + '?action=lookup_document', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tipo_documento_id: tipo.id,
                numero_documento: numero,
            }),
        });
        const d = await r.json();
        if (d.error) {
            showToast(d.message, 'error');
            return;
        }

        document.getElementById('nc-nombres').value = d.cliente?.nombres ?? '';
        document.getElementById('nc-apellidos').value = d.cliente?.apellidos ?? '';
        document.getElementById('nc-direccion').value = d.cliente?.direccion ?? '';
        document.getElementById('nc-ubigeo').value = d.cliente?.ubigeo ?? '';
        actualizarUbigeoNuevoCliente(d.cliente?.ubigeo ?? '');
        showToast(d.message || 'Documento consultado correctamente', 'success');
    } catch (error) {
        showToast('No se pudo consultar el documento', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-search"></i> Consultar';
    }
}

async function obtenerClienteCreado(id, numeroDocumento) {
    const r = await fetch(`${CLIENTES_API}?action=listar&q=${encodeURIComponent(numeroDocumento || '')}&estado=activo`);
    const data = await r.json();
    if (!Array.isArray(data)) return null;
    return data.find(item => Number(item.id) === Number(id)) || data[0] || null;
}

function clearCliente() {
    if (defaultCliente) {
        setSelectedCliente(defaultCliente);
        return;
    }
    setSelectedCliente(null);
    updateClienteMeta(null);
}

// ---- Procesar Venta ----
function procesarVenta() {
    if (!cart.length) return;
    if (!cajaAbierta) {
        showToast('Debes aperturar la caja antes de registrar ventas', 'error');
        return;
    }
    if (!cajaEsMia) {
        showToast('La caja está abierta por otro usuario. No puedes registrar ventas en modo solo lectura.', 'error');
        return;
    }
    const totals   = computeCartTotals();
    const total    = totals.total;
    const descuento = 0;
    const totalFinal = Math.max(0, round2(total - descuento));
    const totalStr = `S/ ${totalFinal.toFixed(2)}`;

    document.getElementById('cobro-total-venta').textContent = totalStr;
    document.getElementById('cobro-total').textContent       = totalStr;
    document.getElementById('monto-recibido').value          = totalFinal.toFixed(2); // display readonly
    document.getElementById('monto-cliente').value           = totalFinal.toFixed(2); // por defecto, pago exacto
    document.getElementById('tipo-pago').value               = 'efectivo';
    document.getElementById('tipo-comprobante').value        = 'boleta';
    document.getElementById('cobro-observacion').value       = '';
    splitPaymentEnabled = false;
    splitPaymentRows = [];
    creditPaymentEnabled = false;
    creditPaymentRows = [];
    setToggleState('check-credito', 'track-credito', false);

    updateCobroClienteResumen();
    renderSplitPaymentPanel();

    onComprobanteChange();
    loadSeriesDisponibles();
    calcularVuelto();
    openModal('modal-cobro');
}

function onTipoPagoChange() {
    const metodo = document.getElementById('tipo-pago').value;
    if (splitPaymentEnabled && splitPaymentRows.length) {
        splitPaymentRows[0].method = metodo;
        renderSplitPaymentPanel();
    }

    // Mostrar sección de efectivo solo cuando el método es efectivo (pago simple)
    if (!splitPaymentEnabled && !creditPaymentEnabled) {
        const seccionEfectivo = document.getElementById('pago-simple-efectivo');
        if (seccionEfectivo) {
            if (metodo === 'efectivo') {
                seccionEfectivo.style.display = '';
                document.getElementById('monto-cliente').value = '';
            } else {
                seccionEfectivo.style.display = 'none';
                const inp = document.getElementById('monto-cliente');
                if (inp) inp.value = '';
            }
        }
    }

    calcularVuelto();
}

function onComprobanteChange() {
    const tipo    = document.getElementById('tipo-comprobante').value;
    const serieEl = document.getElementById('cobro-serie');
    if (tipo === 'boleta') {
        serieEl.innerHTML = '<option value="B001">B001</option>';
    } else if (tipo === 'factura') {
        serieEl.innerHTML = '<option value="F001">F001</option>';
    } else {
        const ticketSerie = (seriesByType.ticket || [])[0];
        const label = ticketSerie
            ? ticketSerie.serie + String(parseInt(ticketSerie.ultimo_numero || 0, 10) + 1).padStart(4, '0')
            : 'TK';
        serieEl.innerHTML = `<option value="TK">${label}</option>`;
    }
}

function toggleSwitch(checkId, trackId, event = null) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const cb  = document.getElementById(checkId);
    const dot = document.querySelector('#' + trackId + ' span');
    cb.checked = !cb.checked;
    const track = document.getElementById(trackId);
    if (cb.checked) {
        track.style.background = 'var(--primary)';
        dot.style.transform = 'translateX(17px)';
    } else {
        track.style.background = '#d1d5db';
        dot.style.transform = 'translateX(0)';
    }

    if (checkId === 'check-credito') {
        toggleCreditoSale(cb.checked);
    }
}

// ---- Ticket ESC/POS ----
function buildTicketHTML(opts) {
    const {
        items = [], total = 0, igv = 0, descuento = 0,
        gravada = 0, exonerada = 0, inafecta = 0,
        monto_recibido = 0, tipo_pago = 'efectivo',
        tipo_comprobante = 'ticket', numero_venta = null,
        numero_comprobante = null, cliente = null,
        payment_breakdown = [], cuotas = [],
        fecha = '', hora = '', preview = false, comprobante_url = ''
    } = opts;

    const PAGOS = { efectivo:'Efectivo', credito:'Crédito', yape:'Yape', plin:'Plin', tarjeta:'Visa/Mastercard', transferencia:'Transferencia' };
    const pagoLabel  = PAGOS[tipo_pago] || tipo_pago;
    const vuelto     = Math.max(0, (monto_recibido || 0) - total);

    const COMP = { boleta:'BOLETA DE VENTA ELECTRÓNICA', factura:'FACTURA ELECTRÓNICA', ticket:'TICKET DE VENTA' };
    const compLabel = COMP[tipo_comprobante] || 'COMPROBANTE DE VENTA';
    const numComp   = numero_comprobante || numero_venta || '---';

    const nombreCliente = cliente
        ? ((cliente.nombres || '') + ' ' + (cliente.apellidos || '')).trim()
        : '';

    const qrDataUri = comprobante_url ? generarQrDataUri(comprobante_url, 156) : '';
    const qrHTML = (comprobante_url && qrDataUri) ? `
        <div style="display:flex;align-items:center;margin-top:8px">
            <div style="width:40%"><img src="${qrDataUri}" style="width:100%;max-width:83px;height:auto;display:block;image-rendering:pixelated"></div>
            <div style="width:60%;padding-left:6px;font-size:8pt;line-height:1.35;word-break:break-all">Consulta tu comprobante:<br>${comprobante_url}</div>
        </div>` : '';

    const sep = (double = false) =>
        `<div style="border-top:${double ? '2px solid' : '1px dashed'} #000;margin:6px 0"></div>`;

    const row2 = (l, r, opts2 = {}) => {
        const sz   = opts2.big  ? '11pt' : '9pt';
        const fw   = opts2.bold ? '800'  : '400';
        return `<div style="display:flex;justify-content:space-between;font-size:${sz};font-weight:${fw};line-height:1.6">
            <span>${l}</span><span>${r}</span></div>`;
    };

    const itemsHTML = items.map(i => {
        const nombre = (i.nombre || i.product?.nombre || '');
        const qty    = i.qty    || i.cantidad || 1;
        const pu     = parseFloat(i.precio || i.precio_venta || i.product?.precio_venta || 0);
        const sub    = parseFloat(i.precio_total || (pu * qty)).toFixed(2);
        const unidadLabel = i.unidad_medida_vendida || 'unid.';
        return `<div style="margin-bottom:5px">
            <div style="display:flex;justify-content:space-between;font-size:9pt">
                <span style="flex:1;padding-right:6px;word-break:break-word">${nombre}</span>
                <span style="white-space:nowrap;font-weight:600">S/${sub}</span>
            </div>
            <div style="font-size:8pt;color:#555;padding-left:2px">${qty} ${unidadLabel} x S/${pu.toFixed(2)}</div>
        </div>`;
    }).join('');

    return `<div id="pos-ticket" style="font-family:'Courier New',Courier,monospace;color:#000;font-size:10pt;line-height:1.5;width:100%">

        <div style="text-align:center;margin-bottom:10px">
            ${EMPRESA_LOGO
                ? `<div style="margin-bottom:4px"><img src="${EMPRESA_LOGO}" style="max-width:160px;max-height:80px;object-fit:contain;image-rendering:-webkit-optimize-contrast"></div>`
                : `<div style="font-size:12pt;font-weight:900;letter-spacing:1px;text-transform:uppercase">${EMPRESA_NOMBRE || 'FARMACIA'}</div>`
            }
            ${EMPRESA_RUC     ? `<div style="font-size:9pt">RUC: ${EMPRESA_RUC}</div>` : ''}
            ${SUCURSAL_NOMBRE ? `<div style="font-size:9pt;font-weight:600">${SUCURSAL_NOMBRE}</div>` : ''}
            ${EMPRESA_DIR     ? `<div style="font-size:8pt">${EMPRESA_DIR}</div>` : ''}
            ${EMPRESA_TEL     ? `<div style="font-size:8pt">Tel: ${EMPRESA_TEL}</div>` : ''}
        </div>

        <div style="border-top:2px solid #000;border-bottom:2px solid #000;text-align:center;padding:5px 0;margin-bottom:8px">
            <div style="font-size:9pt;font-weight:700">${compLabel}</div>
            <div style="font-size:9pt">${numComp}</div>
        </div>

        <div style="font-size:9pt;margin-bottom:6px">
            <div>Fecha   : ${fecha} ${hora}</div>
            <div>Cajero  : ${VENDEDOR_NOMBRE}</div>
            ${nombreCliente               ? `<div>Cliente : ${nombreCliente}</div>`  : ''}
            ${cliente?.dni                ? `<div>DNI     : ${cliente.dni}</div>`    : ''}
            ${cliente?.ruc                ? `<div>RUC     : ${cliente.ruc}</div>`    : ''}
            ${cliente?.direccion          ? `<div>Dir.    : ${cliente.direccion}</div>` : ''}
        </div>

        ${sep()}
        <div style="display:flex;justify-content:space-between;font-size:9pt;font-weight:700;margin-bottom:3px">
            <span>DESCRIPCIÓN</span><span>TOTAL</span>
        </div>
        ${sep()}
        <div style="margin-bottom:4px">${itemsHTML}</div>
        ${sep()}

        <div style="font-size:9pt;margin:4px 0">
            ${row2('OP. GRAVADA:', `S/ ${parseFloat(gravada).toFixed(2)}`)}
            ${parseFloat(exonerada) > 0 ? row2('OP. EXONERADA:', `S/ ${parseFloat(exonerada).toFixed(2)}`) : ''}
            ${parseFloat(inafecta) > 0 ? row2('OP. INAFECTA:', `S/ ${parseFloat(inafecta).toFixed(2)}`) : ''}
            ${row2('IGV (18%)  :', `S/ ${igv.toFixed(2)}`)}
            ${descuento > 0 ? row2('DESCUENTO  :', `-S/ ${parseFloat(descuento).toFixed(2)}`) : ''}
        </div>
        ${sep(true)}
        ${row2('TOTAL:', `S/ ${total.toFixed(2)}`, {bold:true, big:true})}
        ${sep(true)}

        <div style="font-size:9pt;margin-top:5px">
            ${tipo_pago === 'credito' && Array.isArray(cuotas) && cuotas.length
                ? [
                    row2('CRÉDITO:', `S/ ${parseFloat(total).toFixed(2)}`),
                    ...cuotas.map((c, i) => row2(
                        `Cuota ${i + 1} (${c.due_date || 'sin fecha'}):`,
                        `S/ ${parseFloat(c.amount || 0).toFixed(2)}`
                    ))
                ].join('')
                : (Array.isArray(payment_breakdown) && payment_breakdown.length ? payment_breakdown.map(p => row2(
                    (PAGOS[(p.method || '').toLowerCase()] || (p.method || 'Pago')) + ':',
                    `S/ ${parseFloat(p.amount || 0).toFixed(2)}`
                )).join('') : row2(pagoLabel + ':', `S/ ${parseFloat(monto_recibido).toFixed(2)}`))
            }
            ${vuelto > 0 ? row2('<b>VUELTO:</b>', `<b>S/ ${vuelto.toFixed(2)}</b>`) : ''}
        </div>

        ${sep()}
        <div style="text-align:center;font-size:9pt;padding:4px 0">
            <div style="font-weight:700;letter-spacing:.02em">BIENES TRANSFERIDOS EN LA AMAZONÍA PARA</div>
            <div style="font-weight:700;letter-spacing:.02em">SER CONSUMIDOS EN LA MISMA</div>
            ${preview ? `<div style="margin-top:8px;font-size:8pt;font-weight:700;color:#888;border:1px dashed #aaa;padding:2px 8px;display:inline-block">
                ★ VISTA PREVIA — NO ES COMPROBANTE VÁLIDO ★</div>` : ''}
        </div>
        ${qrHTML}
    </div>`;
}

function buildTicketA4HTML(opts) {
    const {
        items = [], total = 0, igv = 0, descuento = 0,
        gravada = 0, exonerada = 0, inafecta = 0,
        monto_recibido = 0, tipo_pago = 'efectivo',
        tipo_comprobante = 'ticket', numero_venta = null,
        numero_comprobante = null, cliente = null,
        payment_breakdown = [], cuotas = [],
        fecha = '', hora = '', preview = false, comprobante_url = ''
    } = opts;

    const PAGOS = { efectivo:'Efectivo', credito:'Crédito', yape:'Yape', plin:'Plin', tarjeta:'Visa/Mastercard', transferencia:'Transferencia' };
    const pagoLabel = PAGOS[tipo_pago] || tipo_pago;
    const vuelto     = Math.max(0, (monto_recibido || 0) - total);

    const COMP = { boleta:'BOLETA DE VENTA ELECTRÓNICA', factura:'FACTURA ELECTRÓNICA', ticket:'TICKET DE VENTA' };
    const compLabel = COMP[tipo_comprobante] || 'COMPROBANTE DE VENTA';
    const numComp   = numero_comprobante || numero_venta || '---';

    const nombreCliente = cliente
        ? ((cliente.nombres || '') + ' ' + (cliente.apellidos || '')).trim()
        : '';

    const qrDataUriA4 = comprobante_url ? generarQrDataUri(comprobante_url, 208) : '';
    const qrHTMLA4 = (comprobante_url && qrDataUriA4) ? `
        <div style="display:flex;align-items:center;margin-top:14px">
            <div style="width:40%"><img src="${qrDataUriA4}" style="width:100%;max-width:117px;height:auto;display:block;image-rendering:pixelated"></div>
            <div style="width:60%;padding-left:10px;font-size:8pt;color:#555;line-height:1.4;word-break:break-all">Escanea o visita este enlace para ver tu comprobante:<br><span style="color:#4f46e5">${comprobante_url}</span></div>
        </div>` : '';

    const itemsHTML = items.map((i, idx) => {
        const nombre = (i.nombre || i.product?.nombre || '');
        const qty    = i.qty    || i.cantidad || 1;
        const pu     = parseFloat(i.precio || i.precio_venta || i.product?.precio_venta || 0);
        const sub    = parseFloat(i.precio_total || (pu * qty)).toFixed(2);
        const unidadLabel = i.unidad_medida_vendida ? ` <span style="color:#4f46e5">(${i.unidad_medida_vendida})</span>` : '';
        return `<tr>
            <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb">${idx + 1}</td>
            <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb">${nombre}${unidadLabel}</td>
            <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:right">${qty}</td>
            <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:right">S/ ${pu.toFixed(2)}</td>
            <td style="padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:right">S/ ${sub}</td>
        </tr>`;
    }).join('');

    const pagosHTML = tipo_pago === 'credito' && Array.isArray(cuotas) && cuotas.length
        ? cuotas.map((c, i) => `<tr><td style="padding:4px 10px">Cuota ${i + 1} (${c.due_date || 'sin fecha'})</td><td style="padding:4px 10px;text-align:right">S/ ${parseFloat(c.amount || 0).toFixed(2)}</td></tr>`).join('')
        : (Array.isArray(payment_breakdown) && payment_breakdown.length
            ? payment_breakdown.map(p => `<tr><td style="padding:4px 10px">${(PAGOS[(p.method || '').toLowerCase()] || p.method || 'Pago')}</td><td style="padding:4px 10px;text-align:right">S/ ${parseFloat(p.amount || 0).toFixed(2)}</td></tr>`).join('')
            : `<tr><td style="padding:4px 10px">${pagoLabel}</td><td style="padding:4px 10px;text-align:right">S/ ${parseFloat(monto_recibido).toFixed(2)}</td></tr>`);

    return `<div id="pos-ticket-a4" style="font-family:Arial,sans-serif;color:#111;font-size:10pt">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
            <div>
                ${EMPRESA_LOGO
                    ? `<img src="${EMPRESA_LOGO}" style="max-width:180px;max-height:70px;object-fit:contain;margin-bottom:6px;image-rendering:-webkit-optimize-contrast">`
                    : `<div style="font-size:14pt;font-weight:700">${EMPRESA_NOMBRE || 'FARMACIA'}</div>`
                }
                ${EMPRESA_RUC     ? `<div style="color:#555">RUC: ${EMPRESA_RUC}</div>` : ''}
                ${SUCURSAL_NOMBRE ? `<div style="color:#555;font-weight:600">${SUCURSAL_NOMBRE}</div>` : ''}
                ${EMPRESA_DIR     ? `<div style="color:#555">${EMPRESA_DIR}</div>` : ''}
                ${EMPRESA_TEL     ? `<div style="color:#555">Tel: ${EMPRESA_TEL}</div>` : ''}
            </div>
            <div style="text-align:right">
                <div style="font-size:13pt;font-weight:700;color:#4f46e5">${compLabel}</div>
                <div style="font-size:11pt;color:#333">${numComp}</div>
                <div style="font-size:9pt;color:#888;margin-top:4px">Fecha: ${fecha} ${hora}</div>
                <div style="font-size:9pt;color:#888">Cajero: ${VENDEDOR_NOMBRE}</div>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid #e5e7eb;margin:14px 0">

        ${(nombreCliente || cliente?.dni || cliente?.ruc) ? `
        <div style="border:1px solid #e5e7eb;border-radius:6px;padding:10px 14px;margin-bottom:18px">
            <div style="font-weight:700;margin-bottom:4px">Cliente</div>
            ${nombreCliente      ? `<div>${nombreCliente}</div>` : ''}
            ${cliente?.dni       ? `<div>DNI: ${cliente.dni}</div>` : ''}
            ${cliente?.ruc       ? `<div>RUC: ${cliente.ruc}</div>` : ''}
            ${cliente?.direccion ? `<div>${cliente.direccion}</div>` : ''}
        </div>` : ''}

        <table style="width:100%;border-collapse:collapse;margin-bottom:16px">
            <thead>
                <tr>
                    <th style="background:#f3f4f6;padding:8px 10px;text-align:left;border-bottom:1.5px solid #d1d5db;width:4%">#</th>
                    <th style="background:#f3f4f6;padding:8px 10px;text-align:left;border-bottom:1.5px solid #d1d5db">Descripción</th>
                    <th style="background:#f3f4f6;padding:8px 10px;text-align:right;border-bottom:1.5px solid #d1d5db;width:10%">Cant.</th>
                    <th style="background:#f3f4f6;padding:8px 10px;text-align:right;border-bottom:1.5px solid #d1d5db;width:15%">P. Unit.</th>
                    <th style="background:#f3f4f6;padding:8px 10px;text-align:right;border-bottom:1.5px solid #d1d5db;width:15%">Subtotal</th>
                </tr>
            </thead>
            <tbody>${itemsHTML}</tbody>
        </table>

        <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
            <table style="width:280px;border-collapse:collapse">
                <tr><td style="padding:4px 10px">Op. Gravada:</td><td style="padding:4px 10px;text-align:right">S/ ${parseFloat(gravada).toFixed(2)}</td></tr>
                ${parseFloat(exonerada) > 0 ? `<tr><td style="padding:4px 10px">Op. Exonerada:</td><td style="padding:4px 10px;text-align:right">S/ ${parseFloat(exonerada).toFixed(2)}</td></tr>` : ''}
                ${parseFloat(inafecta) > 0 ? `<tr><td style="padding:4px 10px">Op. Inafecta:</td><td style="padding:4px 10px;text-align:right">S/ ${parseFloat(inafecta).toFixed(2)}</td></tr>` : ''}
                <tr><td style="padding:4px 10px">IGV (18%):</td><td style="padding:4px 10px;text-align:right">S/ ${igv.toFixed(2)}</td></tr>
                ${descuento > 0 ? `<tr><td style="padding:4px 10px">Descuento:</td><td style="padding:4px 10px;text-align:right">-S/ ${parseFloat(descuento).toFixed(2)}</td></tr>` : ''}
                <tr><td style="padding:8px 10px;font-weight:700;font-size:11pt;border-top:1.5px solid #111">Total:</td><td style="padding:8px 10px;text-align:right;font-weight:700;font-size:11pt;border-top:1.5px solid #111">S/ ${total.toFixed(2)}</td></tr>
            </table>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-bottom:20px">
            <table style="width:280px;border-collapse:collapse">
                ${pagosHTML}
                ${vuelto > 0 ? `<tr><td style="padding:4px 10px;font-weight:700">Vuelto:</td><td style="padding:4px 10px;text-align:right;font-weight:700">S/ ${vuelto.toFixed(2)}</td></tr>` : ''}
            </table>
        </div>

        <div style="text-align:center;font-size:8pt;color:#555;border-top:1px solid #e5e7eb;padding-top:10px">
            <div style="font-weight:700">BIENES TRANSFERIDOS EN LA AMAZONÍA PARA</div>
            <div style="font-weight:700">SER CONSUMIDOS EN LA MISMA</div>
            ${preview ? `<div style="margin-top:8px;font-weight:700;color:#888;border:1px dashed #aaa;padding:2px 8px;display:inline-block">★ VISTA PREVIA — NO ES COMPROBANTE VÁLIDO ★</div>` : ''}
        </div>
        ${qrHTMLA4}
    </div>`;
}

function buildComprobanteHTML(opts) {
    const formato = document.getElementById('tipo-impresion')?.value || 'ticket';
    return formato === 'a4' ? buildTicketA4HTML(opts) : buildTicketHTML(opts);
}

function printReceipt(innerHtml, formato) {
    formato = formato || document.getElementById('tipo-impresion')?.value || 'ticket';
    const isA4 = formato === 'a4';
    // Reglas clave anti-borroso para impresion en Chrome:
    //  - -webkit-print-color-adjust/print-color-adjust: exact  -> Chrome no aclara negros
    //  - -webkit-font-smoothing:none; text-rendering:geometricPrecision -> desactiva
    //    el suavizado de sub-pixel que se ve bien en pantalla pero sale lavado en papel
    //  - image-rendering:-webkit-optimize-contrast -> interpolacion mas nitida de PNGs
    //    (logo + QR). Para el QR usamos crisp-edges porque es imagen binaria.
    //  - Tamanos en pt (no px) -> unidad fisica, no depende del zoom del navegador.
    const PRINT_COMMON = `
        *{margin:0;padding:0;box-sizing:border-box}
        html,body{
            -webkit-print-color-adjust:exact !important;
            print-color-adjust:exact !important;
            -webkit-font-smoothing:none;
            text-rendering:geometricPrecision;
        }
        img{image-rendering:-webkit-optimize-contrast}
    `;
    const style = isA4
        ? `${PRINT_COMMON}
           body{font-family:Arial,Helvetica,sans-serif;font-size:10pt;color:#111;padding:20mm 15mm}
           @media print{@page{size:A4;margin:15mm}body{padding:0}}`
        : `${PRINT_COMMON}
           body{font-family:'Courier New',Courier,monospace;font-size:9pt;color:#000;width:80mm;margin:0 auto;padding:4mm 3mm}`;
    const w = window.open('', '_blank', isA4 ? 'width=900,height=1000' : 'width=420,height=700');
    w.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8">
<style>${style}</style></head><body>${innerHtml}</body></html>`);
    w.document.close();
    w.focus();

    setTimeout(() => {
        if (!isA4) {
            // Alto de pagina dinamico segun el contenido real: un "auto" fijo
            // en @page no siempre lo respeta el driver de la impresora
            // termica, y cortaba el ticket en dos hojas cuando habia varios
            // productos. Se mide el alto ya renderizado (con el QR incluido)
            // y se agrega un margen de seguridad.
            const heightMm = Math.ceil(w.document.body.scrollHeight * 0.2646) + 15;
            const pageStyle = w.document.createElement('style');
            pageStyle.textContent = `@media print { @page { size:80mm ${heightMm}mm; margin:4mm 3mm; } body { padding:0; } }`;
            w.document.head.appendChild(pageStyle);
        }
        w.print();
    }, 400);
}

function previsualizarComprobante() {
    if (!cart.length) { showToast('El carrito está vacío', 'error'); return; }

    const now   = new Date();
    const fecha = now.toLocaleDateString('es-PE', {day:'2-digit',month:'2-digit',year:'numeric'});
    const hora  = now.toLocaleTimeString('es-PE', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    const totals = computeCartTotals();
    const descuento = getCobroDescuentoValue();
    const totalFinal = Math.max(0, round2(totals.total - descuento));
    const splitData = splitPaymentEnabled ? getSplitPaymentPayload(totalFinal) : null;
    const creditRows = creditPaymentEnabled ? getCreditInstallmentRowsFromState() : [];
    const tipoPago = creditPaymentEnabled ? 'credito' : document.getElementById('tipo-pago').value;
    const montoRecibido = creditPaymentEnabled ? 0 : (splitData ? splitData.cashTotal : (parseFloat(document.getElementById('monto-cliente')?.value) || totalFinal));

    const html = buildComprobanteHTML({
        items:            cart.map(i => ({
            nombre: i.product.nombre,
            qty: i.qty,
            precio: getProductUnitSalePrice(i.product),
            precio_total: getProductUnitSalePrice(i.product) * i.qty,
            unidad_medida_vendida: i.unidadMedida || null,
        })),
        total:            totalFinal,
        igv:              totals.igv,
        gravada:          totals.gravada,
        exonerada:        totals.exonerada,
        inafecta:         totals.inafecta,
        descuento,
        monto_recibido:   montoRecibido,
        tipo_pago:        tipoPago,
        tipo_comprobante: document.getElementById('tipo-comprobante').value,
        cliente:          selectedCliente,
        payment_breakdown: splitData ? splitData.rows : [],
        cuotas:           creditRows,
        fecha, hora,
        preview: true,
    });

    document.getElementById('preview-ticket-body').innerHTML = html;
    openModal('modal-preview');
}

function calcularVuelto() {
    if (creditPaymentEnabled) {
        updateCreditPaymentSummary();
        const el = document.getElementById('vuelto');
        if (el) {
            el.textContent = 'S/ 0.00';
            el.style.color = 'var(--success)';
        }
        const montoRecibido = document.getElementById('monto-recibido');
        if (montoRecibido) montoRecibido.value = '0.00';
        const montoCliente = document.getElementById('monto-cliente');
        if (montoCliente) montoCliente.value = '0.00';
        return;
    }

    if (splitPaymentEnabled) {
        updateSplitPaymentSummary();
        const el = document.getElementById('vuelto');
        if (el) {
            const total = getCheckoutTotalAmount();
            const splitData = getSplitPaymentPayload(total);
            const remaining = Math.max(0, splitData.remainder);
            el.textContent = `S/ ${remaining.toFixed(2)}`;
            el.style.color = remaining <= 0 ? 'var(--success)' : 'var(--danger)';
        }
        return;
    }

    const el = document.getElementById('vuelto');

    // Para métodos que no son efectivo no hay vuelto
    const metodo = document.getElementById('tipo-pago')?.value;
    if (metodo && metodo !== 'efectivo') {
        if (el) { el.textContent = 'S/ 0.00'; el.style.color = 'var(--success)'; }
        return;
    }

    const total   = getCheckoutTotalAmount();
    const cliente = parseFloat(document.getElementById('monto-cliente').value) || 0;
    const vuelto  = cliente - total;

    if (cliente <= 0) {
        el.textContent = 'S/ 0.00';
        el.style.color = 'var(--success)';
    } else {
        el.textContent = `S/ ${Math.max(0, vuelto).toFixed(2)}`;
        el.style.color  = vuelto >= 0 ? 'var(--success)' : 'var(--danger)';
    }
}

function confirmarVenta() {
    const tipoPago  = creditPaymentEnabled ? 'credito' : document.getElementById('tipo-pago').value;
    const tipoComp  = document.getElementById('tipo-comprobante').value;
    const totals    = computeCartTotals();
    const descuento = getCobroDescuentoValue();
    const total     = Math.max(0, round2(totals.total - descuento));
    const splitData = splitPaymentEnabled ? getSplitPaymentPayload(total) : null;
    const creditRows = creditPaymentEnabled ? getCreditInstallmentRowsFromState() : [];

    // Validación: factura requiere cliente con RUC
    if (tipoComp === 'factura') {
        if (!selectedCliente) {
            showToast('Para emitir factura debe seleccionar un cliente', 'error');
            closeModal('modal-cobro');
            focusClienteSelect();
            return;
        }
        if (!selectedCliente.ruc) {
            showToast('El cliente no tiene RUC. Obligatorio para factura.', 'error');
            return;
        }
    }

    if (creditPaymentEnabled) {
        const numeroDocumento = String(selectedCliente?.numero_documento || selectedCliente?.dni || selectedCliente?.ruc || '').trim();
        const nombreCliente = String(selectedCliente?.razon_social || selectedCliente?.nombre_completo || '').trim().toUpperCase();
        const esClienteGenerico = numeroDocumento === '00000000' || nombreCliente.includes('CLIENTES VARIOS');

        if (!selectedCliente) {
            showToast('Para vender a crédito debes seleccionar un cliente', 'error');
            focusClienteSelect();
            return;
        }
        if (esClienteGenerico) {
            showToast('Para vender a crédito debes elegir un cliente real, no Clientes Varios', 'error');
            focusClienteSelect();
            return;
        }
        if (!creditRows.length) {
            showToast('Agrega al menos una cuota', 'error');
            return;
        }
        if (Math.abs(creditRows.reduce((sum, row) => sum + row.amount, 0) - total) > 0.01) {
            showToast('La suma de las cuotas debe coincidir con el total de la venta', 'error');
            return;
        }
    }

    const montoCliente = parseFloat(document.getElementById('monto-cliente').value) || 0;
    if (splitPaymentEnabled) {
        if (!splitData.rows.length) {
            showToast('Agrega al menos un método de pago', 'error');
            return;
        }
        if (Math.abs(splitData.remainder) > 0.01) {
            showToast('La suma de los métodos de pago debe coincidir con el total de la venta', 'error');
            return;
        }
    } else if (tipoPago === 'efectivo' && montoCliente < total) {
        showToast('El efectivo del cliente es insuficiente', 'error');
        document.getElementById('monto-cliente').focus();
        return;
    }

    const btn = document.getElementById('btn-confirmar-venta');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

    const payload = {
        items: cart.map(i => ({ producto_id: i.id, cantidad: i.qty, precio: parseFloat(i.product.precio_venta), unidad_medida: i.unidadMedida || null })),
        cliente_id: selectedCliente ? selectedCliente.id : null,
        tipo_pago: tipoPago,
        tipo_comprobante: tipoComp,
        serie: document.getElementById('cobro-serie').value,
        es_credito: creditPaymentEnabled,
        cuenta_banco: document.getElementById('cuenta-banco').value,
        observaciones: document.getElementById('cobro-observacion').value.trim(),
        monto_recibido: creditPaymentEnabled ? 0 : (splitPaymentEnabled ? splitData.cashTotal : (montoCliente > 0 ? montoCliente : total)),
        payment_breakdown: creditPaymentEnabled ? [] : (splitPaymentEnabled ? splitData.rows : []),
        cuotas: creditPaymentEnabled ? creditRows : [],
        descuento
    };

    fetch(BASE + 'modules/ventas/api.php?action=registrar_venta', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Pagar y emitir comprobante';
        if (data.error) { showToast(data.message, 'error'); return; }
        updateProductUsage(cart.map(i => i.id));
        closeModal('modal-cobro');
        showTicket(data);
        showToast('Venta registrada correctamente', 'success');
        loadProducts(); // Recargar stock
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Pagar y emitir comprobante';
        showToast('Error al procesar la venta', 'error');
    });
}

function showTicket(data) {
    const c      = data.comprobante;
    const items  = data.items || cart;
    const now    = new Date();
    const fecha  = now.toLocaleDateString('es-PE', {day:'2-digit',month:'2-digit',year:'numeric'});
    const hora   = now.toLocaleTimeString('es-PE', {hour:'2-digit',minute:'2-digit',second:'2-digit'});

    const ticketHTML = buildComprobanteHTML({
        items,
        total:             parseFloat(data.total),
        igv:               parseFloat(data.igv) || 0,
        gravada:           parseFloat(data.gravada) || 0,
        exonerada:         parseFloat(data.exonerada) || 0,
        inafecta:          parseFloat(data.inafecta) || 0,
        descuento:         parseFloat(data.descuento) || 0,
        monto_recibido:    parseFloat(data.monto_recibido) || parseFloat(data.total),
        tipo_pago:         data.tipo_pago || 'efectivo',
        tipo_comprobante:  data.tipo_comprobante || 'ticket',
        numero_venta:      data.numero_venta,
        numero_comprobante: (c && !c.error_nubefact) ? c.numero_completo : ((data.serie && data.correlativo) ? (data.tipo_comprobante === 'ticket' ? `${data.serie}${data.correlativo}` : `${data.serie}-${data.correlativo}`) : null),
        cliente:           selectedCliente,
        payment_breakdown: data.payment_breakdown || [],
        cuotas:            data.cuotas || [],
        fecha, hora,
        preview: false,
        comprobante_url:   buildComprobanteUrl(data.comprobante_token),
    });

    document.getElementById('ticket-body').innerHTML = ticketHTML;

    _lastSale = { numero_venta: data.numero_venta, total: data.total, items, cliente: selectedCliente, token: data.comprobante_token };
    openModal('modal-ticket');
}

function enviarWhatsApp() {
    if (!_lastSale) return;
    const s = _lastSale;
    const lineas = s.items.map(i => {
        const nombre = i.nombre || i.product?.nombre || '';
        const qty    = i.qty || i.cantidad || 1;
        const precio = parseFloat(i.precio || i.precio_venta || i.product?.precio_venta || 0);
        return `  • ${nombre} x${qty}  S/ ${(precio * qty).toFixed(2)}`;
    });
    const comprobanteUrl = buildComprobanteUrl(s.token);
    const mensaje =
        `🧾 *Comprobante de venta*\n` +
        `N°: ${s.numero_venta}\n\n` +
        lineas.join('\n') +
        `\n\n*TOTAL: S/ ${parseFloat(s.total).toFixed(2)}*\n\n` +
        (comprobanteUrl ? `📄 Ver tu comprobante: ${comprobanteUrl}\n\n` : '') +
        `Gracias por su compra 🙏`;
    const phone   = s.cliente?.telefono ? s.cliente.telefono.replace(/\D/g, '') : '';
    const url     = phone
        ? `https://wa.me/51${phone}?text=${encodeURIComponent(mensaje)}`
        : `https://wa.me/?text=${encodeURIComponent(mensaje)}`;
    window.open(url, '_blank');
}

function copiarEnlaceComprobante() {
    if (!_lastSale || !_lastSale.token) { showToast('No hay comprobante disponible', 'error'); return; }
    const url = buildComprobanteUrl(_lastSale.token);

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url)
            .then(() => showToast('Enlace copiado al portapapeles', 'success'))
            .catch(() => showToast('No se pudo copiar el enlace', 'error'));
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = url;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        showToast('Enlace copiado al portapapeles', 'success');
    } catch (e) {
        showToast('No se pudo copiar el enlace', 'error');
    }
    document.body.removeChild(textarea);
}

function resetPOS() {
    cart = [];
    closeCartDrawer();
    clearCliente();
    splitPaymentEnabled = false;
    splitPaymentRows = [];
    creditPaymentEnabled = false;
    creditPaymentRows = [];
    setToggleState('check-credito', 'track-credito', false);
    renderCart();
    renderSplitPaymentPanel();
    document.getElementById('search-input').value = '';
    filterProducts('', 0);
    document.querySelectorAll('.chip').forEach((c,i) => i===0 ? c.classList.add('active') : c.classList.remove('active'));
    currentCat = 0;
}

function printTicket() {
    printReceipt(document.getElementById('ticket-body').innerHTML);
}

// ---- Lector de código de barras ----
function setupBarcodeScanner() {
    document.addEventListener('barcodescan', function (e) {
        const code    = e.detail.code.trim().toUpperCase();
        const product = allProducts.find(p =>
            (p.codigo_barras && p.codigo_barras.toUpperCase() === code) ||
            (p.codigo && p.codigo.toUpperCase() === code) ||
            (p.codigo_interno && p.codigo_interno.toUpperCase() === code)
        );

        if (product) {
            const enConteo = product.en_conteo_inventario === true || product.en_conteo_inventario === 't';
            if (parseInt(product.stock) <= 0) {
                showToast('<i class="fas fa-exclamation-triangle"></i> ' + product.nombre + ' — sin stock disponible', 'error');
            } else if (enConteo) {
                addToCart(parseInt(product.id)); // muestra su propio toast de bloqueo
            } else {
                addToCart(parseInt(product.id));
                showToast('<i class="fas fa-barcode"></i> ' + product.nombre, 'success');
            }
        } else {
            // Código no encontrado: mostrar en búsqueda para que el cajero vea
            document.getElementById('search-input').value = e.detail.code;
            filterProducts(e.detail.code, currentCat);
            showToast('Código <strong>' + code + '</strong> no encontrado', 'error');
        }
    });
}

// ---- Nuevo Cliente ----
function openModalNuevoCliente() {
    ['nc-numero-documento','nc-nombres','nc-apellidos','nc-telefono','nc-email','nc-direccion','nc-ubigeo']
        .forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
    const select = document.getElementById('nc-tipo-documento');
    if (select && tiposDocumentoCliente.length) {
        const predeterminado = tiposDocumentoCliente.find(item => item.codigo === '1') || tiposDocumentoCliente[0];
        if (predeterminado) {
            select.value = String(predeterminado.id);
        }
    }
    actualizarEstadoNuevoCliente();
    actualizarUbigeoNuevoCliente('');
    openModal('modal-nuevo-cliente');
    setTimeout(() => document.getElementById('nc-numero-documento')?.focus(), 100);
}

async function guardarNuevoCliente() {
    const tipo = getTipoDocumentoNuevoCliente();
    const numeroDocumento = document.getElementById('nc-numero-documento')?.value.trim() || '';
    const payload = {
        tipo_documento_id: tipo?.id,
        numero_documento: numeroDocumento,
        nombres: document.getElementById('nc-nombres')?.value.trim() || '',
        apellidos: document.getElementById('nc-apellidos')?.value.trim() || '',
        telefono: document.getElementById('nc-telefono')?.value.trim() || '',
        email: document.getElementById('nc-email')?.value.trim() || '',
        direccion: document.getElementById('nc-direccion')?.value.trim() || '',
        ubigeo: document.getElementById('nc-ubigeo')?.value.trim() || '',
    };

    if (!payload.tipo_documento_id) {
        showToast('Debes seleccionar un tipo de documento', 'error');
        return;
    }

    const btn = document.getElementById('btn-guardar-cliente');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    try {
        const response = await fetch(CLIENTES_API + '?action=crear', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Guardar Cliente';

        if (data.error) {
            showToast(data.message, 'error');
            return;
        }

        const clienteCreado = await obtenerClienteCreado(data.id, numeroDocumento);
        if (clienteCreado) {
            setSelectedCliente(clienteCreado);
        }

        closeModal('modal-nuevo-cliente');
        showToast('Cliente guardado y seleccionado', 'success');
    } catch (error) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Guardar Cliente';
        showToast('Error al guardar el cliente', 'error');
    }
}

// ---- Modal / Toast helpers ----
// ---- Tabs Ventas ----
let historialLoaded = false;

function switchTab(tab) {
    document.getElementById('tab-pos').style.display    = tab === 'pos'    ? 'flex' : 'none';
    document.getElementById('tab-ventas').style.display = tab === 'ventas' ? 'block' : 'none';

    // POS necesita altura fija para el grid; Ventas scrollea la página normalmente.
    const wrap = document.querySelector('.pos-page-wrap');
    if (wrap) wrap.style.height = tab === 'ventas' ? 'auto' : '';

    if (tab === 'ventas' && !historialLoaded) {
        historialLoaded = true;
        loadVentasStats();
        loadHistorial();
        document.getElementById('h-q').addEventListener('keyup', e => { if (e.key === 'Enter') loadHistorial(); });
    }
}

function loadVentasStats() {
    fetch(BASE + 'modules/ventas/api.php?action=stats_dia')
        .then(r => r.json())
        .then(d => {
            const cfg = [
                { icon:'cash-register', color:'blue',   val: d.total_ventas,   label:'Ventas hoy' },
                { icon:'dollar-sign',   color:'green',  val: 'S/ '+parseFloat(d.ingresos).toFixed(2), label:'Ingresos hoy' },
                { icon:'chart-line',    color:'yellow', val: 'S/ '+parseFloat(d.ticket_promedio).toFixed(2), label:'Ticket promedio' },
                { icon:'ban',           color:'red',    val: d.anuladas,       label:'Anuladas hoy' },
            ];
            document.getElementById('h-stats-container').innerHTML = cfg.map(c => `
                <div class="col-6 col-md-3"><div class="stat-card">
                    <div class="stat-icon ${c.color}"><i class="fas fa-${c.icon}"></i></div>
                    <div><div class="stat-value">${c.val}</div><div class="stat-label">${c.label}</div></div>
                </div></div>`).join('');
        });
}

function loadHistorial() {
    const params = new URLSearchParams({
        action: 'historial',
        desde:  document.getElementById('h-desde').value,
        hasta:  document.getElementById('h-hasta').value,
        estado: document.getElementById('h-estado').value,
        q:      document.getElementById('h-q').value,
    });
    document.getElementById('h-tabla-body').innerHTML =
        '<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i> Buscando...</td></tr>';

    fetch(BASE + 'modules/ventas/api.php?' + params)
        .then(r => r.json())
        .then(data => {
            document.getElementById('h-result-count').textContent = data.length + ' resultado(s)';
            if (!data.length) {
                document.getElementById('h-tabla-body').innerHTML =
                    '<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-inbox" style="font-size:1.3rem"></i><br><br>No se encontraron ventas</td></tr>';
                return;
            }
            const pagoIcon = { efectivo:'💵', yape:`<img src="${BASE}assets/img/yape_plin.jpg" alt="Yape" style="height:18px;vertical-align:middle;border-radius:3px">`, plin:`<img src="${BASE}assets/img/yape_plin.jpg" alt="Plin" style="height:18px;vertical-align:middle;border-radius:3px">`, tarjeta:'💳', transferencia:'🏦' };
            document.getElementById('h-tabla-body').innerHTML = data.map(v => {
                const esCls = v.estado === 'completada' ? 'badge-success' : 'badge-danger';
                const dt = new Date(v.created_at);
                const fechaStr = dt.toLocaleDateString('es-PE') + ' ' + dt.toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit'});
                return `<tr>
                    <td><span style="font-weight:700;color:var(--primary)">${v.numero_venta}</span></td>
                    <td style="font-size:.82rem;color:var(--text-muted)">${fechaStr}</td>
                    <td style="font-size:.85rem">${v.cliente}</td>
                    <td><span class="badge badge-gray">${v.num_items} items</span></td>
                    <td style="font-size:.82rem">${pagoIcon[v.tipo_pago]||''} ${v.tipo_pago}</td>
                    <td><span class="badge badge-primary" style="text-transform:capitalize">${v.tipo_comprobante}</span></td>
                    <td class="text-right"><strong>S/ ${parseFloat(v.total).toFixed(2)}</strong></td>
                    <td><span class="badge ${esCls}">${v.estado}</span></td>
                    <td style="white-space:nowrap">
                        <button class="btn btn-ghost btn-sm" title="Ver comprobante" onclick="verTicketVenta(${v.id})">
                            <i class="fas fa-receipt"></i>
                        </button>
                        ${(v.estado === 'completada' && !esCajero) ? `
                        <button class="btn btn-ghost btn-sm" title="Anular venta" style="color:var(--danger)" onclick="anularVentaDesdeHistorial(${v.id},'${v.numero_venta}')">
                            <i class="fas fa-ban"></i>
                        </button>` : ''}
                    </td>
                </tr>`;
            }).join('');
        })
        .catch(() => showToast('Error al cargar historial','error'));
}

function verTicketVenta(id) {
    document.getElementById('ticket-body-historial').innerHTML =
        '<div style="text-align:center;padding:30px"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:var(--text-light)"></i></div>';
    openModal('modal-ticket-historial');

    fetch(BASE + `modules/ventas/api.php?action=detalle_venta_ticket&id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) { showToast(data.message, 'error'); closeModal('modal-ticket-historial'); return; }

            const dt    = new Date(data.created_at);
            const fecha = dt.toLocaleDateString('es-PE', {day:'2-digit',month:'2-digit',year:'numeric'});
            const hora  = dt.toLocaleTimeString('es-PE', {hour:'2-digit',minute:'2-digit',second:'2-digit'});

            const cliente = (data.nombres || data.apellidos || data.razon_social || data.numero_documento) ? {
                nombres: data.razon_social || data.nombres,
                apellidos: data.razon_social ? '' : data.apellidos,
                direccion: data.direccion,
                telefono: data.telefono,
                numero_documento: data.numero_documento,
                tipo_documento_codigo: data.tipo_documento_codigo,
                dni: data.tipo_documento_codigo === '1' ? data.numero_documento : null,
                ruc: data.tipo_documento_codigo === '6' ? data.numero_documento : null,
            } : null;

            const items = data.items.map(i => ({
                nombre: i.producto_nombre,
                qty: parseFloat(i.cantidad),
                precio: parseFloat(i.precio_unitario),
                precio_total: parseFloat(i.precio_total),
                unidad_medida_vendida: i.unidad_medida_vendida,
            }));

            const comprobanteUrl = buildComprobanteUrl(data.comprobante_token);

            let paymentBreakdown = [];
            try { paymentBreakdown = data.payment_breakdown ? JSON.parse(data.payment_breakdown) : []; } catch (e) {}
            let cuotasData = [];
            try { cuotasData = data.cuotas ? JSON.parse(data.cuotas) : []; } catch (e) {}

            const ticketHTML = buildComprobanteHTML({
                items,
                total: parseFloat(data.total),
                igv: parseFloat(data.igv) || 0,
                gravada: parseFloat(data.gravada) || 0,
                exonerada: parseFloat(data.exonerada) || 0,
                inafecta: parseFloat(data.inafecta) || 0,
                descuento: parseFloat(data.descuento) || 0,
                monto_recibido: parseFloat(data.total) + (parseFloat(data.vuelto) || 0),
                tipo_pago: data.tipo_pago || 'efectivo',
                tipo_comprobante: data.tipo_comprobante || 'ticket',
                numero_venta: data.numero_venta,
                numero_comprobante: (data.serie && data.correlativo) ? (data.tipo_comprobante === 'ticket' ? `${data.serie}${data.correlativo}` : `${data.serie}-${data.correlativo}`) : null,
                cliente,
                payment_breakdown: paymentBreakdown,
                cuotas: cuotasData,
                fecha, hora,
                preview: false,
                comprobante_url: comprobanteUrl,
            });

            document.getElementById('ticket-body-historial').innerHTML = ticketHTML;
            _lastSale = { numero_venta: data.numero_venta, total: data.total, items, cliente, token: data.comprobante_token };
        })
        .catch(() => { showToast('Error al cargar el comprobante', 'error'); closeModal('modal-ticket-historial'); });
}

let ventaAnularPendiente = null;

function anularVentaDesdeHistorial(id, numero) {
    ventaAnularPendiente = { id, numero };
    document.getElementById('anular-venta-texto').textContent =
        `¿Anular la venta ${numero}? El stock será repuesto.`;
    document.getElementById('anular-venta-motivo').value = '';
    openModal('modal-anular-venta');
    setTimeout(() => document.getElementById('anular-venta-motivo').focus(), 50);
}

function confirmarAnularVenta() {
    if (!ventaAnularPendiente) return;

    const motivo = document.getElementById('anular-venta-motivo').value.trim();
    if (!motivo) { showToast('Debes indicar el motivo de la anulación', 'error'); return; }

    const { id } = ventaAnularPendiente;
    const btn = document.getElementById('btn-confirmar-anular-venta');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Anulando...';

    fetch(BASE + 'modules/ventas/api.php?action=anular_venta', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ id, motivo })
    })
    .then(r => r.json())
    .then(d => {
        if (d.error) { showToast(d.message,'error'); return; }
        showToast('Venta anulada correctamente','success');
        closeModal('modal-anular-venta');
    })
    .catch(() => showToast('Error al anular la venta','error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        ventaAnularPendiente = null;
        // Se refresca siempre (incluso si hubo error) para que el estado
        // mostrado en la tabla nunca quede desincronizado del real.
        loadHistorial();
        loadVentasStats();
    });
}

// ---- Si se llega desde historial.php con ?tab=ventas ----
if (new URLSearchParams(location.search).get('tab') === 'ventas') {
    document.addEventListener('DOMContentLoaded', () => switchTab('ventas'));
}

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function showToast(msg, type = 'info') {
    const icons = { success: 'check-circle', error: 'exclamation-circle', info: 'info-circle' };
    const toast = document.createElement('div');
    toast.className = `app-toast ${type}`;
    toast.innerHTML = `<i class="fas fa-${icons[type]}"></i> ${msg}`;
    document.getElementById('toast-container').appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}
</script>

<?php include '../../includes/footer.php'; ?>
