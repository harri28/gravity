<?php
$required_roles  = ['admin', 'gerente', 'cajero'];
$base_path       = '../../';
$current_module  = 'compras';
$current_page    = 'compras';
$page_title      = 'Compras — Gravity';
$breadcrumb      = '<i class="fas fa-shopping-cart"></i> Compras';
require_once '../../config/database.php';
require_once '../../includes/header.php';
?>

<style>
/* ---- Stat cards ---- */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:16px; margin-bottom:28px; }
.stat-card  { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:18px 20px;
              display:flex; align-items:center; gap:14px; }
.stat-icon  { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.si-indigo  { background:#eef2ff; color:#6366f1; }
.si-amber   { background:#fef3c7; color:#d97706; }
.si-red     { background:#fee2e2; color:#dc2626; }
.si-green   { background:#dcfce7; color:#16a34a; }
.si-blue    { background:#dbeafe; color:#2563eb; }
.stat-value { font-size:1.5rem; font-weight:700; color:var(--text-primary); line-height:1; }
.stat-label { font-size:.75rem; color:var(--text-muted); margin-top:3px; }

/* ---- Tabs ---- */
.tabs { display:flex; border-bottom:1px solid var(--border); margin-bottom:24px; gap:4px; }
.tab-btn { background:none; border:none; border-bottom:2px solid transparent; padding:10px 20px;
           font-size:.88rem; font-weight:600; color:var(--text-muted); cursor:pointer;
           display:flex; align-items:center; gap:7px; margin-bottom:-1px; transition:color .15s, border-color .15s; }
.tab-btn:hover  { color:var(--text-primary); }
.tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); }
.tab-pane { display:none; }
.tab-pane.active { display:block; }

/* ---- Toolbar ---- */
.toolbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px; }
.toolbar-filters { display:flex; gap:8px; flex-wrap:wrap; }
.filter-btn { padding:6px 14px; border:1.5px solid var(--border); border-radius:20px; background:none;
              font-size:.78rem; font-weight:600; color:var(--text-muted); cursor:pointer; transition:.15s; }
.filter-btn:hover  { border-color:var(--primary); color:var(--primary); }
.filter-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }
.toolbar-search { position:relative; flex:1; min-width:220px; max-width:340px; }
.toolbar-search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:.8rem; }
.toolbar-search input { width:100%; padding:7px 12px 7px 34px; border:1.5px solid var(--border); border-radius:20px;
                        font-size:.82rem; background:var(--surface, #fff); color:var(--text-primary); outline:none; transition:.15s; }
.toolbar-search input:focus { border-color:var(--primary); }

/* ---- Table ---- */
.table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow-x:auto; overflow-y:hidden; }
.data-table { width:100%; border-collapse:collapse; }
.data-table thead th { background:var(--surface-2); padding:11px 16px; font-size:.73rem; font-weight:700;
                       text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted); text-align:left; }
.data-table tbody tr { border-top:1px solid var(--border); transition:background .1s; }
.data-table tbody tr:hover { background:var(--surface-2); }
.data-table td { padding:12px 16px; font-size:.87rem; color:var(--text-secondary); vertical-align:middle; }
.td-main { font-weight:600; color:var(--text-primary); }

/* ---- Estado badges ---- */
.badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; text-transform:uppercase; }
.b-borrador  { background:#f1f5f9; color:#64748b; }
.b-pendiente { background:#fef3c7; color:#b45309; }
.b-aprobada  { background:#dbeafe; color:#1d4ed8; }
.b-recibida  { background:#dcfce7; color:#15803d; }
.b-cancelada { background:#fee2e2; color:#dc2626; }
.b-pagado    { background:#dcfce7; color:#15803d; }
.b-parcial   { background:#fef3c7; color:#b45309; }
.b-vencido   { background:#fee2e2; color:#dc2626; }
.b-efectivo  { background:#f0fdf4; color:#16a34a; }
.b-credito   { background:#fef3c7; color:#d97706; }
.b-transferencia { background:#dbeafe; color:#2563eb; }

/* ---- Barra de progreso pago ---- */
.pay-bar { width:100%; height:6px; background:#e2e8f0; border-radius:4px; margin-top:4px; overflow:hidden; }
.pay-bar-fill { height:100%; background:#6366f1; border-radius:4px; transition:width .3s; }

/* ---- Modal grande ---- */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:500;
                 align-items:center; justify-content:center; padding:16px; }
.modal-overlay.active { display:flex; }
.modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:100%;
         box-shadow:0 20px 60px rgba(0,0,0,.15); animation:modalIn .18s ease; overflow:hidden; }
.modal-lg { max-width:780px; }
.modal-md { max-width:480px; }
@keyframes modalIn { from{opacity:0;transform:translateY(-14px) scale(.97)} to{opacity:1;transform:none} }
.modal-header { padding:18px 24px; border-bottom:1px solid var(--border);
                display:flex; align-items:center; justify-content:space-between; }
.modal-title  { font-size:1rem; font-weight:700; color:var(--text-primary); }
.modal-close  { background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1.05rem;
                border-radius:6px; padding:4px; transition:color .15s; }
.modal-close:hover { color:var(--text-primary); }
.modal-body   { padding:20px 24px; max-height:70vh; overflow-y:auto; }
.modal-footer { padding:14px 24px; border-top:1px solid var(--border); display:flex; gap:10px; justify-content:flex-end; }

/* ---- Form ---- */
.form-row   { display:grid; gap:14px; margin-bottom:14px; }
.form-row.c2 { grid-template-columns:1fr 1fr; }
.form-row.c3 { grid-template-columns:1fr 1fr 1fr; }
.form-group label { display:block; font-size:.75rem; font-weight:600; color:var(--text-muted);
                    text-transform:uppercase; letter-spacing:.04em; margin-bottom:5px; }
.form-group input, .form-group select, .form-group textarea {
    width:100%; padding:9px 12px; background:var(--surface);
    border:1.5px solid var(--border); border-radius:8px; font-size:.88rem;
    color:var(--text-primary); outline:none; transition:border-color .15s; font-family:inherit; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:var(--primary); }
.form-group textarea { resize:vertical; min-height:70px; }

/* ---- Items de orden ---- */
.items-table { width:100%; border-collapse:collapse; margin-bottom:12px; }
.items-table th { padding:8px 10px; font-size:.72rem; font-weight:700; text-transform:uppercase;
                  color:var(--text-muted); text-align:left; border-bottom:1px solid var(--border); }
.items-table td { padding:6px 6px; vertical-align:middle; }
.items-table input, .items-table select {
    padding:7px 9px; border:1.5px solid var(--border); border-radius:7px;
    font-size:.83rem; color:var(--text-primary); background:var(--surface); width:100%; outline:none; }
.items-table input:focus, .items-table select:focus { border-color:var(--primary); }
.btn-del-row { background:#fee2e2; border:none; color:#dc2626; width:28px; height:28px;
               border-radius:6px; cursor:pointer; font-size:.8rem; }
.btn-del-row:hover { background:#dc2626; color:#fff; }
.items-total { text-align:right; font-size:.9rem; color:var(--text-secondary); padding:8px 0; border-top:1px solid var(--border); }
.items-total strong { color:var(--text-primary); font-size:1rem; }
#ocCostoEnvio { width:140px !important; padding:7px 9px; font-size:.83rem; }

/* ---- Historial pagos ---- */
.pago-row { display:flex; align-items:center; justify-content:space-between;
            padding:8px 0; border-bottom:1px solid var(--border); font-size:.84rem; }
.pago-row:last-child { border-bottom:none; }

/* ---- Empty state ---- */
.empty-state { padding:60px 20px; text-align:center; color:var(--text-muted); }
.empty-state i { font-size:2.5rem; display:block; margin-bottom:12px; }

/* ---- Btn ---- */
.btn-sm { padding:6px 13px; font-size:.78rem; }

/* ---- Icon action buttons (fila de tabla) ---- */
.icon-action-btn {
    width:34px; height:34px; border-radius:999px;
    border:1px solid #dbeafe; background:#f8fbff; color:var(--primary);
    display:inline-flex; align-items:center; justify-content:center;
    cursor:pointer; transition:.18s ease; padding:0;
}
.icon-action-btn:hover { background:var(--primary); color:#fff; border-color:var(--primary); }
.icon-action-btn.danger { border-color:#fecaca; background:#fef2f2; color:var(--danger); }
.icon-action-btn.danger:hover { background:var(--danger); color:#fff; border-color:var(--danger); }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">Compras</h1>
        <p class="page-subtitle">Órdenes de compra, créditos y cuentas por pagar</p>
    </div>
    <button class="btn btn-primary" onclick="abrirNuevaOrden()">
        <i class="fas fa-plus"></i> Nueva orden de compra
    </button>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg">
        <div class="stat-card">
            <div class="stat-icon si-indigo"><i class="fas fa-file-alt"></i></div>
            <div><div class="stat-value" id="sOrdenesActivas">—</div><div class="stat-label">Órdenes activas</div></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="stat-card">
            <div class="stat-icon si-amber"><i class="fas fa-clock"></i></div>
            <div><div class="stat-value" id="sOrdenesPendientes">—</div><div class="stat-label">Por aprobar</div></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="stat-card">
            <div class="stat-icon si-red"><i class="fas fa-exclamation-circle"></i></div>
            <div><div class="stat-value" id="sCuentasVencidas">—</div><div class="stat-label">Cuentas vencidas</div></div>
        </div>
    </div>
    <div class="col-6 col-md-6 col-lg">
        <div class="stat-card">
            <div class="stat-icon si-blue"><i class="fas fa-hand-holding-usd"></i></div>
            <div><div class="stat-value" id="sTotalPorPagar">—</div><div class="stat-label">Total por pagar</div></div>
        </div>
    </div>
    <div class="col-6 col-md-6 col-lg">
        <div class="stat-card">
            <div class="stat-icon si-green"><i class="fas fa-shopping-cart"></i></div>
            <div><div class="stat-value" id="sComprasMes">—</div><div class="stat-label">Compras este mes</div></div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="tabs">
    <button class="tab-btn active" onclick="switchTab('ordenes')">
        <i class="fas fa-file-alt"></i> Órdenes de Compra
    </button>
    <button class="tab-btn" onclick="switchTab('cuentas')">
        <i class="fas fa-hand-holding-usd"></i> Cuentas por Pagar
    </button>
    <button class="tab-btn" onclick="switchTab('cobrar')">
        <i class="fas fa-file-invoice-dollar"></i> Cuentas por Cobrar
    </button>
    <button class="tab-btn" onclick="switchTab('abastecimiento')">
        <i class="fas fa-boxes"></i> Abastecimiento
    </button>
</div>

<!-- TAB: Órdenes -->
<div class="tab-pane active" id="tab-ordenes">
    <div class="toolbar">
        <div class="toolbar-filters" id="filtrosOrden">
            <button class="filter-btn active" onclick="filtrarOrdenes('')">Todas</button>
            <button class="filter-btn" onclick="filtrarOrdenes('borrador')">Borrador</button>
            <button class="filter-btn" onclick="filtrarOrdenes('pendiente')">Pendiente</button>
            <button class="filter-btn" onclick="filtrarOrdenes('aprobada')">Aprobada</button>
            <button class="filter-btn" onclick="filtrarOrdenes('recibida')">Recibida</button>
            <button class="filter-btn" onclick="filtrarOrdenes('cancelada')">Cancelada</button>
        </div>
        <div class="toolbar-search">
            <i class="fas fa-search"></i>
            <input type="text" id="buscarOrden" placeholder="Buscar por Nº orden, proveedor, RUC o producto..."
                   autocomplete="off" oninput="buscarOrdenes(this.value)">
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr>
                <th>Nº Orden</th>
                <th>Proveedor</th>
                <th>Tipo Pago</th>
                <th>Ítems</th>
                <th style="text-align:right">Total</th>
                <th>Estado</th>
                <th>Fecha</th>
                <th style="text-align:center">Acciones</th>
            </tr></thead>
            <tbody id="tbodyOrdenes">
                <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">
                    <i class="fas fa-spinner fa-spin"></i> Cargando...
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- TAB: Cuentas por pagar -->
<div class="tab-pane" id="tab-cuentas">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr>
                <th>Proveedor</th>
                <th>Documento</th>
                <th>Referencia</th>
                <th style="text-align:right">Total</th>
                <th style="text-align:right">Pagado</th>
                <th style="text-align:right">Pendiente</th>
                <th>Vencimiento</th>
                <th>Estado</th>
                <th></th>
            </tr></thead>
            <tbody id="tbodyCuentas">
                <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">
                    <i class="fas fa-spinner fa-spin"></i> Cargando...
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- TAB: Cuentas por cobrar -->
<div class="tab-pane" id="tab-cobrar">
    <!-- Mini stats -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4">
            <div class="stat-card">
                <div class="stat-icon si-indigo"><i class="fas fa-clock"></i></div>
                <div><div class="stat-value" id="sCobrarPendientes">—</div><div class="stat-label">Cuentas pendientes</div></div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="stat-card">
                <div class="stat-icon si-blue"><i class="fas fa-file-invoice-dollar"></i></div>
                <div><div class="stat-value" id="sTotalPorCobrar">—</div><div class="stat-label">Total por cobrar</div></div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="stat-card">
                <div class="stat-icon si-red"><i class="fas fa-exclamation-circle"></i></div>
                <div><div class="stat-value" id="sCobrarVencidas">—</div><div class="stat-label">Cuentas vencidas</div></div>
            </div>
        </div>
    </div>
    <!-- Toolbar -->
    <div class="toolbar">
        <div class="toolbar-filters" id="filtrosCobrar">
            <button class="filter-btn active" onclick="filtrarCobrar('',this)">Todas</button>
            <button class="filter-btn" onclick="filtrarCobrar('pendiente',this)">Pendiente</button>
            <button class="filter-btn" onclick="filtrarCobrar('parcial',this)">Parcial</button>
            <button class="filter-btn" onclick="filtrarCobrar('vencido',this)">Vencida</button>
            <button class="filter-btn" onclick="filtrarCobrar('pagado',this)">Pagada</button>
        </div>
        <button class="btn btn-primary btn-sm" onclick="abrirNuevaCuentaCobrar()">
            <i class="fas fa-plus"></i> Nueva cuenta
        </button>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr>
                <th>Cliente</th>
                <th>Documento</th>
                <th>Referencia</th>
                <th style="text-align:right">Total</th>
                <th style="text-align:right">Cobrado</th>
                <th style="text-align:right">Pendiente</th>
                <th>Vencimiento</th>
                <th>Estado</th>
                <th></th>
            </tr></thead>
            <tbody id="tbodyCobrar">
                <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">
                    <i class="fas fa-spinner fa-spin"></i> Cargando...
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Dropdown compartido del buscador de productos en Nueva Orden de Compra
     (position:fixed, fuera de la tabla para que el overflow-x:auto no lo recorte) -->
<div id="oc-producto-dropdown" style="display:none;position:fixed;z-index:2000;background:#fff;border:1px solid var(--border);border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.15);max-height:260px;overflow-y:auto"></div>

<!-- ============================================================
     MODAL: Nueva Orden de Compra
============================================================ -->
<div class="modal-overlay" id="modalOrdenOverlay">
<div class="modal modal-lg">
    <div class="modal-header">
        <span class="modal-title"><i class="fas fa-file-alt" style="color:var(--primary);margin-right:8px"></i>Nueva Orden de Compra</span>
        <button class="modal-close" onclick="cerrarModal('modalOrdenOverlay')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
        <div class="form-row c2">
            <div class="form-group">
                <label>Proveedor *</label>
                <select id="ocProveedor">
                    <option value="">Seleccionar proveedor...</option>
                </select>
            </div>
            <div class="form-group">
                <label>Fecha de entrega esperada</label>
                <input type="date" id="ocFechaEntrega">
            </div>
        </div>
        <div class="form-row c3">
            <div class="form-group">
                <label>Tipo de pago *</label>
                <select id="ocTipoPago" onchange="toggleCredito()">
                    <option value="efectivo">Efectivo</option>
                    <option value="transferencia">Transferencia</option>
                    <option value="credito">Crédito</option>
                </select>
            </div>
            <div class="form-group" id="grupoDiasCredito" style="display:none">
                <label>Días de crédito</label>
                <input type="number" id="ocDiasCredito" value="30" min="1" max="365">
            </div>
            <div class="form-group">
                <label>Número de Factura</label>
                <input type="text" id="ocNumeroFactura" placeholder="F001-00040598">
            </div>
        </div>

        <!-- Productos -->
        <div style="margin-bottom:10px;display:flex;align-items:center;justify-content:space-between">
            <span style="font-size:.85rem;font-weight:700;color:var(--text-primary)">Productos</span>
            <button class="btn btn-secondary btn-sm" onclick="agregarFila()">
                <i class="fas fa-plus"></i> Agregar ítem
            </button>
        </div>
        <div style="overflow-x:auto">
        <table class="items-table">
            <thead><tr>
                <th style="width:28%">Producto</th>
                <th style="width:18%">Descripción</th>
                <th style="width:9%">U.M.</th>
                <th style="width:10%">Cantidad</th>
                <th style="width:13%">P. Unitario</th>
                <th style="width:13%">Sub. Total</th>
                <th style="width:2%"></th>
            </tr></thead>
            <tbody id="itemsBody"></tbody>
        </table>
        </div>
        <div class="items-total" id="itemsTotales">
            Subtotal: <strong id="totSubtotal">S/ 0.00</strong>
        </div>

        <div class="form-row c2" style="margin-top:10px">
            <div class="form-group">
                <label>Costo de Envío</label>
                <input type="text" inputmode="decimal" id="ocCostoEnvio" placeholder="0.00"
                    oninput="sanitizarDecimalOrden(this); calcularTotales()">
            </div>
        </div>

        <div class="items-total" id="itemsTotalFinal" style="margin-top:4px">
            Total: <strong id="totTotal">S/ 0.00</strong>
        </div>

        <div class="form-row" style="margin-top:10px">
            <div class="form-group">
                <label>Observaciones</label>
                <textarea id="ocObs" rows="2" placeholder="Notas adicionales para el proveedor..."></textarea>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary" onclick="cerrarModal('modalOrdenOverlay')">Cancelar</button>
        <button class="btn btn-primary" id="btnRegistrarOrden" onclick="guardarOrden()">
            <i class="fas fa-save"></i> Registrar orden de compra
        </button>
    </div>
</div>
</div>

<!-- ============================================================
     MODAL: Detalle / Acciones de Orden
============================================================ -->
<div class="modal-overlay" id="modalDetalleOverlay">
<div class="modal modal-lg">
    <div class="modal-header">
        <span class="modal-title" id="detalleTitle">Orden de Compra</span>
        <button class="modal-close" onclick="cerrarModal('modalDetalleOverlay')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="detalleBody">
        <div style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i></div>
    </div>
    <div class="modal-footer" id="detalleFooter" style="flex-wrap:wrap;gap:8px"></div>
</div>
</div>

<!-- ============================================================
     MODAL: Registrar Pago
============================================================ -->
<div class="modal-overlay" id="modalPagoOverlay">
<div class="modal modal-md">
    <div class="modal-header">
        <span class="modal-title"><i class="fas fa-money-bill-wave" style="color:#16a34a;margin-right:8px"></i>Registrar Pago</span>
        <button class="modal-close" onclick="cerrarModal('modalPagoOverlay')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="pagoCuentaId">
        <div id="pagoInfoCuenta" style="background:var(--surface-2);border-radius:8px;padding:12px;margin-bottom:16px;font-size:.85rem"></div>
        <div class="form-row c2">
            <div class="form-group">
                <label>Monto *</label>
                <input type="number" id="pagoMonto" step="0.01" min="0.01" placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Método de pago</label>
                <select id="pagoMetodo">
                    <option value="efectivo">Efectivo</option>
                    <option value="transferencia">Transferencia</option>
                    <option value="cheque">Cheque</option>
                    <option value="yape">Yape / Plin</option>
                    <option value="tarjeta">Tarjeta</option>
                </select>
            </div>
        </div>
        <div class="form-row c2">
            <div class="form-group">
                <label>Referencia / Nº operación</label>
                <input type="text" id="pagoRef" placeholder="Opcional">
            </div>
            <div class="form-group">
                <label>Notas</label>
                <input type="text" id="pagoNotas" placeholder="Opcional">
            </div>
        </div>
        <div id="historialPagos" style="margin-top:8px"></div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary" onclick="cerrarModal('modalPagoOverlay')">Cancelar</button>
        <button class="btn btn-primary" onclick="confirmarPago()"><i class="fas fa-check"></i> Registrar pago</button>
    </div>
</div>
</div>

<!-- ============================================================
     MODAL: Registrar Cobro
============================================================ -->
<div class="modal-overlay" id="modalCobrarOverlay">
<div class="modal modal-md">
    <div class="modal-header">
        <span class="modal-title"><i class="fas fa-hand-holding-usd" style="color:#16a34a;margin-right:8px"></i>Registrar Cobro</span>
        <button class="modal-close" onclick="cerrarModal('modalCobrarOverlay')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="cobrarCuentaId">
        <div id="cobrarInfoCuenta" style="background:var(--surface-2);border-radius:8px;padding:12px;margin-bottom:16px;font-size:.85rem"></div>
        <div class="form-row c2">
            <div class="form-group">
                <label>Monto *</label>
                <input type="number" id="cobrarMonto" step="0.01" min="0.01" placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Método de cobro</label>
                <select id="cobrarMetodo">
                    <option value="efectivo">Efectivo</option>
                    <option value="yape">Yape / Plin</option>
                    <option value="tarjeta">Tarjeta</option>
                    <option value="transferencia">Transferencia</option>
                    <option value="cheque">Cheque</option>
                </select>
            </div>
        </div>
        <div class="form-row c2">
            <div class="form-group">
                <label>Referencia / Nº operación</label>
                <input type="text" id="cobrarRef" placeholder="Opcional">
            </div>
            <div class="form-group">
                <label>Notas</label>
                <input type="text" id="cobrarNotas" placeholder="Opcional">
            </div>
        </div>
        <div id="historialCobros" style="margin-top:8px"></div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary" onclick="cerrarModal('modalCobrarOverlay')">Cancelar</button>
        <button class="btn btn-primary" onclick="confirmarCobro()"><i class="fas fa-check"></i> Registrar cobro</button>
    </div>
</div>
</div>

<!-- ============================================================
     MODAL: Nueva Cuenta por Cobrar
============================================================ -->
<div class="modal-overlay" id="modalNuevaCobrarOverlay">
<div class="modal modal-md">
    <div class="modal-header">
        <span class="modal-title"><i class="fas fa-plus-circle" style="color:var(--primary);margin-right:8px"></i>Nueva Cuenta por Cobrar</span>
        <button class="modal-close" onclick="cerrarModal('modalNuevaCobrarOverlay')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
        <div class="form-row c2">
            <div class="form-group">
                <label>Cliente *</label>
                <select id="ncbCliente"><option value="">Seleccionar cliente...</option></select>
            </div>
            <div class="form-group">
                <label>Monto total *</label>
                <input type="number" id="ncbMonto" step="0.01" min="0.01" placeholder="0.00">
            </div>
        </div>
        <div class="form-row c2">
            <div class="form-group">
                <label>Nº Documento / Referencia</label>
                <input type="text" id="ncbDoc" placeholder="Ej: V20260525-0001">
            </div>
            <div class="form-group">
                <label>Fecha de vencimiento</label>
                <input type="date" id="ncbVencimiento">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Notas</label>
                <textarea id="ncbNotas" rows="2" placeholder="Observaciones..."></textarea>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary" onclick="cerrarModal('modalNuevaCobrarOverlay')">Cancelar</button>
        <button class="btn btn-primary" onclick="guardarCuentaCobrar()"><i class="fas fa-save"></i> Guardar</button>
    </div>
</div>
</div>

<!-- TAB: Abastecimiento -->
<div class="tab-pane" id="tab-abastecimiento">
    <div style="padding:40px;text-align:center;color:var(--text-muted)">
        <i class="fas fa-boxes" style="font-size:2.5rem;opacity:.3;margin-bottom:14px;display:block"></i>
        <div style="font-size:1rem;font-weight:600;color:var(--text-secondary);margin-bottom:6px">Abastecimiento</div>
        <div style="font-size:.85rem">Próximamente disponible.</div>
    </div>
</div>

<script>
const API   = '<?= $base_path ?>modules/compras/api.php';
const ROL_PUEDE_EDITAR_ENVIO = <?= isAdmin() ? 'true' : 'false' ?>;
const BASE  = '<?= $base_path ?>';
let _filtroOrden = '';
let _busquedaOrden = '';
let _busquedaTimer = null;

// ----------------------------------------------------------------
// Tabs
// ----------------------------------------------------------------
function switchTab(tab) {
    const tabs = ['ordenes','cuentas','cobrar','abastecimiento'];
    document.querySelectorAll('.tab-btn').forEach((b,i) => b.classList.toggle('active', tabs[i] === tab));
    document.getElementById('tab-ordenes').classList.toggle('active',        tab === 'ordenes');
    document.getElementById('tab-cuentas').classList.toggle('active',        tab === 'cuentas');
    document.getElementById('tab-cobrar').classList.toggle('active',         tab === 'cobrar');
    document.getElementById('tab-abastecimiento').classList.toggle('active', tab === 'abastecimiento');
    if (tab === 'cuentas') cargarCuentas();
    if (tab === 'cobrar')  cargarCuentasCobrar();
}

// ----------------------------------------------------------------
// Stats
// ----------------------------------------------------------------
async function cargarStats() {
    try {
        const r = await fetch(`${API}?action=stats`);
        const d = await r.json();
        document.getElementById('sOrdenesActivas').textContent    = d.ordenes_activas ?? '—';
        document.getElementById('sOrdenesPendientes').textContent = d.ordenes_pendientes ?? '—';
        document.getElementById('sCuentasVencidas').textContent   = d.cuentas_vencidas ?? '—';
        document.getElementById('sTotalPorPagar').textContent     = 'S/ ' + parseFloat(d.total_por_pagar||0).toFixed(2);
        document.getElementById('sComprasMes').textContent        = 'S/ ' + parseFloat(d.compras_mes||0).toFixed(2);
    } catch {}
}

// ----------------------------------------------------------------
// Órdenes
// ----------------------------------------------------------------
function filtrarOrdenes(estado) {
    _filtroOrden = estado;
    document.querySelectorAll('#filtrosOrden .filter-btn').forEach(b => {
        b.classList.toggle('active', b.textContent.trim().toLowerCase().startsWith(estado || 'todas'));
    });
    cargarOrdenes();
}

function buscarOrdenes(valor) {
    clearTimeout(_busquedaTimer);
    _busquedaTimer = setTimeout(() => {
        _busquedaOrden = valor.trim();
        cargarOrdenes();
    }, 300);
}

const ESTADO_LABEL = {
    borrador:'Borrador', pendiente:'Pendiente', aprobada:'Aprobada',
    recibida:'Recibida', cancelada:'Cancelada'
};
const PAGO_LABEL = { efectivo:'Efectivo', credito:'Crédito', transferencia:'Transf.' };

async function cargarOrdenes() {
    const tbody = document.getElementById('tbodyOrdenes');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:36px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    try {
        const params = new URLSearchParams({ action: 'ordenes_listar' });
        if (_filtroOrden)    params.set('estado', _filtroOrden);
        if (_busquedaOrden)  params.set('q', _busquedaOrden);
        const r = await fetch(`${API}?${params}`);
        const data = await r.json();
        if (data.error) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:36px;color:#dc2626"><i class="fas fa-exclamation-triangle"></i> ${esc(data.message)}</td></tr>`;
            return;
        }
        const lista = data;
        if (!lista.length) {
            tbody.innerHTML = (_filtroOrden || _busquedaOrden)
                ? `<tr><td colspan="8"><div class="empty-state"><i class="fas fa-search"></i>No se encontraron órdenes con ese filtro o búsqueda</div></td></tr>`
                : `<tr><td colspan="8">
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <div>No hay órdenes de compra registradas</div>
                        <a onclick="abrirNuevaOrden()" style="font-size:inherit;color:var(--primary);text-decoration:underline;cursor:pointer">Nueva orden de compra</a>
                    </div>
                   </td></tr>`;
            return;
        }
        tbody.innerHTML = lista.map(o => `
        <tr onclick="verOrden(${o.id})" style="cursor:pointer">
            <td class="td-main">${esc(o.numero_orden)}</td>
            <td>
                <div style="font-weight:600;color:var(--text-primary)">${esc(o.proveedor||'—')}</div>
                ${o.ruc ? `<div style="font-size:.75rem;color:var(--text-muted)">${esc(o.ruc)}</div>` : ''}
            </td>
            <td><span class="badge b-${o.tipo_pago}">${PAGO_LABEL[o.tipo_pago]||o.tipo_pago}${o.tipo_pago==='credito'&&o.dias_credito?` ${o.dias_credito}d`:''}</span></td>
            <td style="text-align:center">${o.total_items}</td>
            <td style="text-align:right;font-weight:600">S/ ${parseFloat(o.total).toFixed(2)}</td>
            <td><span class="badge b-${o.estado}">${ESTADO_LABEL[o.estado]||o.estado}</span></td>
            <td style="font-size:.8rem;color:var(--text-muted)">${fmtDate(o.created_at)}</td>
            <td style="text-align:center" onclick="event.stopPropagation()">
                <div style="display:flex;gap:5px;justify-content:center">
                    <button class="icon-action-btn" onclick="verOrden(${o.id})" title="Ver detalle">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${o.estado!=='cancelada' ? `<button class="icon-action-btn danger" onclick="cambiarEstado(${o.id},'cancelada')" title="Cancelar"><i class="fas fa-ban"></i></button>` : ''}
                </div>
            </td>
        </tr>`).join('');
    } catch { tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:36px;color:#dc2626">Error al cargar</td></tr>'; }
}

async function verOrden(id) {
    document.getElementById('detalleBody').innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i></div>';
    document.getElementById('detalleFooter').innerHTML = '';
    document.getElementById('modalDetalleOverlay').classList.add('active');
    try {
        const r = await fetch(`${API}?action=orden_detalle&id=${id}`);
        const o = await r.json();
        document.getElementById('detalleTitle').textContent = o.numero_orden;
        document.getElementById('detalleBody').innerHTML = `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px">
                <div>
                    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px">Proveedor</div>
                    <div style="font-weight:600">${esc(o.proveedor||'—')}</div>
                    ${o.ruc?`<div style="font-size:.78rem;color:var(--text-muted)">${esc(o.ruc)}</div>`:''}
                </div>
                <div>
                    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px">Estado / Pago</div>
                    <span class="badge b-${o.estado}" style="margin-right:6px">${ESTADO_LABEL[o.estado]}</span>
                    <span class="badge b-${o.tipo_pago}">${PAGO_LABEL[o.tipo_pago]||o.tipo_pago}${o.tipo_pago==='credito'&&o.dias_credito?` ${o.dias_credito} días`:''}</span>
                </div>
                <div>
                    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px">Fecha creación</div>
                    <div>${fmtDate(o.created_at)}</div>
                </div>
                ${o.fecha_entrega?`<div>
                    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px">Entrega esperada</div>
                    <div>${fmtDate(o.fecha_entrega)}</div>
                </div>`:''}
                ${o.numero_factura?`<div>
                    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px">N° Factura</div>
                    <div>${esc(o.numero_factura)}</div>
                </div>`:''}
            </div>
            <table class="items-table" style="margin-bottom:12px">
                <thead><tr><th>Producto</th><th>Descripción</th><th style="text-align:center">U.M.</th><th style="text-align:center">Cant.</th><th style="text-align:right">P. Unit.</th><th style="text-align:right">Subtotal</th></tr></thead>
                <tbody>${(o.items||[]).map(it=>`
                <tr style="border-top:1px solid var(--border)">
                    <td style="padding:8px 10px;font-size:.85rem;font-weight:600">${esc(it.producto_nombre||'—')}</td>
                    <td style="padding:8px 10px;font-size:.82rem;color:var(--text-muted)">${esc(it.descripcion||'')}</td>
                    <td style="padding:8px 10px;text-align:center;font-size:.82rem">${esc(it.unidad_medida||'—')}</td>
                    <td style="padding:8px 10px;text-align:center">${it.cantidad}</td>
                    <td style="padding:8px 10px;text-align:right">S/ ${parseFloat(it.precio_unitario).toFixed(2)}</td>
                    <td style="padding:8px 10px;text-align:right;font-weight:600">S/ ${parseFloat(it.subtotal).toFixed(2)}</td>
                </tr>`).join('')}
                </tbody>
            </table>
            <div style="text-align:right;font-size:.88rem;padding-top:8px;border-top:1px solid var(--border)">
                <span style="color:var(--text-muted);margin-right:16px">Subtotal: <strong>S/ ${parseFloat(o.subtotal).toFixed(2)}</strong></span>
                ${parseFloat(o.igv)>0?`<span style="color:var(--text-muted);margin-right:16px">IGV: <strong>S/ ${parseFloat(o.igv).toFixed(2)}</strong></span>`:''}
                ${parseFloat(o.costo_envio||0)>0?`<span style="color:var(--text-muted);margin-right:16px">Envío: <strong>S/ ${parseFloat(o.costo_envio).toFixed(2)}</strong></span>`:''}
                <span style="font-size:1rem">Total: <strong>S/ ${parseFloat(o.total).toFixed(2)}</strong></span>
            </div>
            ${o.observaciones?`<div style="margin-top:14px;padding:10px;background:var(--surface-2);border-radius:8px;font-size:.84rem;color:var(--text-secondary)">${esc(o.observaciones)}</div>`:''}
        `;
        // Guardar referencia para compartir/PDF
        _ultimaOrden = { id: o.id, numero: o.numero_orden };

        const footer = document.getElementById('detalleFooter');
        let btns = `<button class="btn btn-secondary" onclick="cerrarModal('modalDetalleOverlay')">Cerrar</button>`;

        // Acciones según estado
        if (o.estado !== 'cancelada') {
            btns += `<button class="btn btn-secondary" style="color:#dc2626" onclick="cerrarModal('modalDetalleOverlay');cambiarEstado(${o.id},'cancelada')"><i class="fas fa-ban"></i> Cancelar</button>`;
        }

        if (o.estado !== 'cancelada' && ROL_PUEDE_EDITAR_ENVIO) {
            btns += `<button class="btn btn-secondary" onclick="editarEnvio(${o.id}, ${parseFloat(o.costo_envio||0)})"><i class="fas fa-truck"></i> Editar envío</button>`;
        }

        // PDF + Compartir siempre disponibles
        btns += `<button class="btn btn-primary" style="background:#6366f1" onclick="abrirPDF()"><i class="fas fa-file-pdf"></i> Ver PDF</button>`;
        btns += `<button class="btn btn-primary" style="background:#25d366" onclick="cerrarModal('modalDetalleOverlay');document.getElementById('compartirNumOrden').textContent='${esc(o.numero_orden)}';document.getElementById('modalCompartirOverlay').classList.add('active')"><i class="fas fa-share-alt"></i> Compartir</button>`;

        footer.innerHTML = btns;
    } catch { document.getElementById('detalleBody').innerHTML = '<p style="color:#dc2626;text-align:center;padding:20px">Error al cargar</p>'; }
}

async function editarEnvio(id, actual) {
    const v = prompt('Nuevo costo de envío (S/):', actual.toFixed(2));
    if (v === null) return;
    const monto = parseFloat(v.replace(',', '.'));
    if (isNaN(monto) || monto < 0) { toast('Monto no válido','err'); return; }
    try {
        const r = await fetch(`${API}?action=orden_actualizar_envio`, {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({id, costo_envio: monto}),
        });
        const d = await r.json();
        if (d.error) { toast(d.message,'err'); return; }
        toast(d.message);
        verOrden(id); cargarOrdenes(); cargarStats();
    } catch { toast('Error de conexión','err'); }
}

async function cambiarEstado(id, estado) {
    const labels = { pendiente: 'enviar a proveedor', aprobada: 'aprobar', cancelada: 'cancelar' };
    if (!confirm(`¿Deseas ${labels[estado]||estado} esta orden?`)) return;
    try {
        const r = await fetch(`${API}?action=orden_cambiar_estado`, {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({id, estado}),
        });
        const d = await r.json();
        if (d.error) { toast(d.message,'err'); return; }
        toast(d.message);
        cargarOrdenes(); cargarStats();
    } catch { toast('Error de conexión','err'); }
}

// ----------------------------------------------------------------
// Cuentas por pagar
// ----------------------------------------------------------------
async function cargarCuentas() {
    const tbody = document.getElementById('tbodyCuentas');
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:36px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    try {
        const r = await fetch(`${API}?action=cuentas_listar`);
        const lista = await r.json();
        if (!lista.length) {
            tbody.innerHTML = `<tr><td colspan="9">
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="color:#16a34a"></i>
                    <div>No hay cuentas por pagar pendientes</div>
                    <span style="font-size:.82rem;color:var(--text-muted)">Se generan automáticamente al recibir órdenes a crédito</span>
                </div>
               </td></tr>`;
            return;
        }
        tbody.innerHTML = lista.map(c => {
            const pct = c.monto_total > 0 ? (c.monto_pagado / c.monto_total * 100).toFixed(0) : 0;
            const hoy = new Date().toISOString().split('T')[0];
            const vencida = c.fecha_vencimiento && c.fecha_vencimiento < hoy && c.estado !== 'pagado';
            return `<tr>
                <td class="td-main">${esc(c.proveedor||'—')}</td>
                <td style="font-size:.8rem;color:var(--text-muted)">${esc(c.numero_doc||'—')}</td>
                <td style="font-size:.8rem;color:var(--text-muted)">
                    ${c.numero_orden?`OC: ${esc(c.numero_orden)}`:''}
                    ${c.numero_ingreso?`<br>Ing: ${esc(c.numero_ingreso)}`:''}
                </td>
                <td style="text-align:right">S/ ${parseFloat(c.monto_total).toFixed(2)}</td>
                <td style="text-align:right;color:#16a34a;font-weight:600">S/ ${parseFloat(c.monto_pagado).toFixed(2)}</td>
                <td style="text-align:right">
                    <div style="font-weight:600;color:${parseFloat(c.monto_pendiente)>0?'#dc2626':'#16a34a'}">
                        S/ ${parseFloat(c.monto_pendiente).toFixed(2)}
                    </div>
                    <div class="pay-bar"><div class="pay-bar-fill" style="width:${pct}%"></div></div>
                </td>
                <td style="font-size:.82rem;${vencida?'color:#dc2626;font-weight:600':'color:var(--text-muted)'}">
                    ${c.fecha_vencimiento ? fmtDate(c.fecha_vencimiento) : '—'}
                    ${vencida?'<br><span style="font-size:.7rem">VENCIDA</span>':''}
                </td>
                <td><span class="badge b-${c.estado}">${c.estado.charAt(0).toUpperCase()+c.estado.slice(1)}</span></td>
                <td>
                    ${c.estado !== 'pagado' ? `
                    <button class="btn btn-primary btn-sm" onclick="abrirPago(${c.id},'${esc(c.proveedor||'')}',${c.monto_pendiente},${c.monto_total},${c.monto_pagado})">
                        <i class="fas fa-money-bill-wave"></i> Pagar
                    </button>` : `<span style="font-size:.78rem;color:#16a34a"><i class="fas fa-check-circle"></i> Pagado</span>`}
                </td>
            </tr>`;
        }).join('');
    } catch { tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:36px;color:#dc2626">Error al cargar</td></tr>'; }
}

// ---- Pago ----
async function abrirPago(id, proveedor, pendiente, total, pagado) {
    document.getElementById('pagoCuentaId').value = id;
    document.getElementById('pagoMonto').value    = pendiente.toFixed(2);
    document.getElementById('pagoMonto').max      = pendiente;
    document.getElementById('pagoRef').value      = '';
    document.getElementById('pagoNotas').value    = '';
    document.getElementById('pagoInfoCuenta').innerHTML = `
        <div style="font-weight:600;margin-bottom:4px">${esc(proveedor)}</div>
        <div style="display:flex;gap:16px;font-size:.82rem;color:var(--text-secondary)">
            <span>Total: <strong>S/ ${parseFloat(total).toFixed(2)}</strong></span>
            <span>Pagado: <strong style="color:#16a34a">S/ ${parseFloat(pagado).toFixed(2)}</strong></span>
            <span>Pendiente: <strong style="color:#dc2626">S/ ${parseFloat(pendiente).toFixed(2)}</strong></span>
        </div>`;

    // Historial de pagos previos
    try {
        const r = await fetch(`${API}?action=cuenta_detalle&id=${id}`);
        const d = await r.json();
        const hist = d.pagos || [];
        document.getElementById('historialPagos').innerHTML = hist.length ? `
            <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px">Pagos anteriores</div>
            ${hist.map(p=>`<div class="pago-row">
                <span>${fmtDate(p.created_at)} — ${p.metodo_pago}${p.referencia?` (${p.referencia})`:''}</span>
                <strong>S/ ${parseFloat(p.monto).toFixed(2)}</strong>
            </div>`).join('')}` : '';
    } catch {}

    document.getElementById('modalPagoOverlay').classList.add('active');
    setTimeout(()=>document.getElementById('pagoMonto').focus(), 80);
}

async function confirmarPago() {
    const cuentaId = document.getElementById('pagoCuentaId').value;
    const monto    = parseFloat(document.getElementById('pagoMonto').value);
    if (!monto || monto <= 0) { toast('Ingresa un monto válido','err'); return; }
    try {
        const r = await fetch(`${API}?action=registrar_pago`, {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({
                cuenta_id:  parseInt(cuentaId),
                monto,
                metodo_pago: document.getElementById('pagoMetodo').value,
                referencia:  document.getElementById('pagoRef').value.trim(),
                notas:       document.getElementById('pagoNotas').value.trim(),
            }),
        });
        const d = await r.json();
        if (d.error) { toast(d.message,'err'); return; }
        toast(d.message);
        cerrarModal('modalPagoOverlay');
        cargarCuentas(); cargarStats();
    } catch { toast('Error de conexión','err'); }
}

// ----------------------------------------------------------------
// Nueva Orden - formulario de ítems
// ----------------------------------------------------------------
let _productos = [];
let _proveedores = [];

async function abrirNuevaOrden() {
    document.getElementById('ocProveedor').innerHTML = '<option value="">Cargando...</option>';
    document.getElementById('ocTipoPago').value      = 'efectivo';
    document.getElementById('ocDiasCredito').value   = '30';
    document.getElementById('ocNumeroFactura').value = '';
    document.getElementById('ocObs').value           = '';
    document.getElementById('ocCostoEnvio').value    = '';
    const hoy = new Date();
    document.getElementById('ocFechaEntrega').value  =
        hoy.getFullYear() + '-' + String(hoy.getMonth() + 1).padStart(2, '0') + '-' + String(hoy.getDate()).padStart(2, '0');
    document.getElementById('itemsBody').innerHTML   = '';
    toggleCredito();
    calcularTotales();

    // Cargar proveedores
    try {
        const r = await fetch(`${API}?action=proveedores`);
        _proveedores = await r.json();
        if (!Array.isArray(_proveedores)) throw new Error('respuesta inesperada');
        if (_proveedores.length === 0) {
            document.getElementById('ocProveedor').innerHTML =
                '<option value="">— Sin proveedores registrados —</option>';
            toast('No hay proveedores. Ve a Almacén → Proveedores para agregar uno.', 'warn');
        } else {
            document.getElementById('ocProveedor').innerHTML =
                '<option value="">Seleccionar proveedor...</option>' +
                _proveedores.map(p=>`<option value="${p.id}">${esc(p.razon_social)}${p.ruc?` (${p.ruc})`:''}</option>`).join('');
        }
    } catch (e) {
        document.getElementById('ocProveedor').innerHTML = '<option value="">Error al cargar proveedores</option>';
        console.error('proveedores:', e);
    }

    agregarFila();
    document.getElementById('modalOrdenOverlay').classList.add('active');
}

function toggleCredito() {
    const es_credito = document.getElementById('ocTipoPago').value === 'credito';
    document.getElementById('grupoDiasCredito').style.display = es_credito ? '' : 'none';
}

// Cantidad y P. Unitario: type="text" en vez de "number" porque Chrome no
// permite mover el cursor programaticamente en inputs number. Filtro deja
// solo digitos y un punto decimal; el cursor se difiere con setTimeout
// porque el navegador posiciona el cursor segun el clic DESPUES de
// disparar "focus" (mismo patron ya usado en Almacen/Toma de Inventario).
function sanitizarDecimalOrden(el) {
    let v = el.value.replace(/[^0-9.]/g, '');
    const partes = v.split('.');
    if (partes.length > 2) v = partes[0] + '.' + partes.slice(1).join('');
    el.value = v;
}

function cursorAlFinalOrden(el) {
    setTimeout(() => {
        const pos = el.value.length;
        el.setSelectionRange(pos, pos);
    }, 0);
}

let _filaIdx = 0;
function agregarFila() {
    const idx = _filaIdx++;
    const tr  = document.createElement('tr');
    tr.id     = `fila-${idx}`;
    tr.innerHTML = `
        <td>
            <input type="text" id="fbusca-${idx}" placeholder="Buscar producto o escanee..." autocomplete="off"
                oninput="buscarProductoOrden(${idx}, this.value)"
                onfocus="buscarProductoOrden(${idx}, this.value)"
                style="padding:7px 9px;border:1.5px solid var(--border);border-radius:7px;font-size:.83rem;color:var(--text-primary);background:var(--surface);width:100%">
            <input type="hidden" id="fp-${idx}">
        </td>
        <td><input type="text" id="fd-${idx}" placeholder="Descripción adicional"></td>
        <td><input type="text" id="fum-${idx}" placeholder="unidad" style="text-align:center"></td>
        <td><input type="text" inputmode="decimal" id="fq-${idx}" value="" placeholder="0"
                oninput="sanitizarDecimalOrden(this); calcularFila(${idx})" onfocus="cursorAlFinalOrden(this)"
                style="text-align:center;padding:7px 10px"></td>
        <td><input type="text" inputmode="decimal" id="fu-${idx}" value="0.00"
                oninput="sanitizarDecimalOrden(this); calcularFila(${idx})" onfocus="cursorAlFinalOrden(this)"
                style="text-align:center;padding:7px 10px"></td>
        <td><input type="text" inputmode="decimal" id="fs-${idx}" value="0.00"
                oninput="sanitizarDecimalOrden(this); calcularSubtotalManual(${idx})" onfocus="cursorAlFinalOrden(this)"
                style="text-align:right;padding:7px 10px;font-weight:600"></td>
        <td><button class="btn-del-row" onclick="document.getElementById('fila-${idx}').remove();calcularTotales()"><i class="fas fa-trash"></i></button></td>
    `;
    document.getElementById('itemsBody').appendChild(tr);
    return idx;
}

// Buscador de producto por fila -- el dropdown es UN SOLO elemento
// compartido (fuera de la tabla) posicionado con "fixed" segun el input
// activo, para que el overflow-x:auto de la tabla no lo recorte.
let _ocBuscaTimer = null;
let _ocDropdownIdx = null;

function buscarProductoOrden(idx, valor) {
    _ocDropdownIdx = idx;
    const dropdown = document.getElementById('oc-producto-dropdown');
    const input    = document.getElementById(`fbusca-${idx}`);
    const rect     = input.getBoundingClientRect();
    dropdown.style.left  = rect.left + 'px';
    dropdown.style.top   = (rect.bottom + 4) + 'px';
    dropdown.style.width = rect.width + 'px';

    clearTimeout(_ocBuscaTimer);
    const q = valor.trim();
    if (!q) {
        dropdown.innerHTML = '<div style="padding:10px 14px;color:var(--text-muted);font-size:.83rem">Escribe para buscar...</div>';
        dropdown.style.display = 'block';
        return;
    }
    _ocBuscaTimer = setTimeout(() => {
        fetch(`${API}?action=productos_buscar&q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(lista => {
                if (_ocDropdownIdx !== idx) return; // el usuario ya cambio de fila
                dropdown.innerHTML = !Array.isArray(lista) || !lista.length
                    ? '<div style="padding:10px 14px;color:var(--text-muted);font-size:.83rem">Sin resultados</div>'
                    : lista.map(p => `
                        <div onclick='seleccionarProductoOrden(${idx}, ${JSON.stringify(p).replace(/'/g, "&#39;")})'
                            style="padding:9px 12px;cursor:pointer;border-bottom:1px solid var(--border);font-size:.83rem"
                            onmouseover="this.style.background='var(--primary-light)'"
                            onmouseout="this.style.background=''">
                            <div style="font-weight:600">${esc(p.nombre)}</div>
                            <div style="color:var(--text-muted);font-size:.76rem">${esc(p.codigo)} · Stock: ${p.stock}</div>
                        </div>`).join('');
                dropdown.style.display = 'block';
            })
            .catch(() => {});
    }, 200);
}

function seleccionarProductoOrden(idx, p) {
    document.getElementById(`fbusca-${idx}`).value = p.nombre;
    document.getElementById(`fp-${idx}`).value      = p.id;
    if (p.precio_compra) document.getElementById(`fu-${idx}`).value  = parseFloat(p.precio_compra).toFixed(2);
    if (p.unidad)         document.getElementById(`fum-${idx}`).value = p.unidad;
    document.getElementById('oc-producto-dropdown').style.display = 'none';
    calcularFila(idx);
}

document.addEventListener('click', e => {
    const dropdown = document.getElementById('oc-producto-dropdown');
    if (!dropdown || dropdown.style.display === 'none') return;
    if (e.target.id?.startsWith('fbusca-') || dropdown.contains(e.target)) return;
    dropdown.style.display = 'none';
});

// El scanner HID (assets/js/barcode-scanner.js) borra lo tipeado del
// input activo y dispara este evento en su lugar -- por eso escanear
// dentro de "Buscar producto o escanee" no dejaba nada escrito. Igual
// que en Almacen (setupBarcodeScanner), se agrega directo a la lista.
document.addEventListener('barcodescan', function (e) {
    if (!document.getElementById('modalOrdenOverlay').classList.contains('active')) return;
    const code = e.detail.code.trim();
    if (!code) return;

    fetch(`${API}?action=productos_buscar&q=${encodeURIComponent(code)}`)
        .then(r => r.json())
        .then(data => {
            if (!Array.isArray(data) || !data.length) {
                toast(`Código ${code} no encontrado`, 'err');
                return;
            }
            const exact = data.find(p =>
                (p.codigo_barras || '').toUpperCase() === code.toUpperCase() ||
                p.codigo.toUpperCase() === code.toUpperCase());
            const prod = exact || data[0];

            let idxExistente = null;
            document.querySelectorAll('[id^="fp-"]').forEach(el => {
                if (parseInt(el.value) === parseInt(prod.id)) idxExistente = el.id.replace('fp-', '');
            });

            if (idxExistente !== null) {
                const fq = document.getElementById(`fq-${idxExistente}`);
                fq.value = (parseFloat(fq.value) || 0) + 1;
                calcularFila(idxExistente);
            } else {
                const idx = agregarFila();
                seleccionarProductoOrden(idx, prod);
                document.getElementById(`fq-${idx}`).value = '1';
                calcularFila(idx);
            }
            toast(`Escaneado: ${prod.nombre}`);
        })
        .catch(() => toast('Error al buscar el código escaneado', 'err'));
});

function calcularFila(idx) {
    const q  = parseFloat(document.getElementById(`fq-${idx}`)?.value||0);
    const u  = parseFloat(document.getElementById(`fu-${idx}`)?.value||0);
    const fs = document.getElementById(`fs-${idx}`);
    if (fs) fs.value = (q * u).toFixed(2);
    calcularTotales();
}

// Contraparte de calcularFila: al escribir el Subtotal a mano, se
// deriva el Precio Unitario (Cantidad sigue siendo la que manda -- si
// despues se edita Cantidad, calcularFila vuelve a mandar y recalcula
// el Subtotal en base al Precio Unitario que quedo aqui).
function calcularSubtotalManual(idx) {
    const q = parseFloat(document.getElementById(`fq-${idx}`)?.value||0);
    const s = parseFloat(document.getElementById(`fs-${idx}`)?.value||0);
    if (q > 0) {
        const fu = document.getElementById(`fu-${idx}`);
        if (fu) fu.value = (s / q).toFixed(2);
    }
    calcularTotales();
}

function calcularTotales() {
    let sub = 0;
    document.querySelectorAll('[id^="fs-"]').forEach(el => {
        sub += parseFloat(el.value) || 0;
    });
    const envio = parseFloat(document.getElementById('ocCostoEnvio')?.value || 0) || 0;
    document.getElementById('totSubtotal').textContent = 'S/ ' + sub.toFixed(2);
    document.getElementById('totTotal').textContent = 'S/ ' + (sub + envio).toFixed(2);
}


let _ultimaOrden = null;

async function guardarOrden() {
    const pid = document.getElementById('ocProveedor').value;
    if (!pid) { toast('Selecciona un proveedor','err'); return; }

    const items = [];
    document.querySelectorAll('[id^="fila-"]').forEach(tr => {
        const idx   = tr.id.replace('fila-','');
        const pid_p = document.getElementById(`fp-${idx}`)?.value;
        const desc  = document.getElementById(`fd-${idx}`)?.value.trim();
        const um    = document.getElementById(`fum-${idx}`)?.value.trim();
        const qty   = parseInt(document.getElementById(`fq-${idx}`)?.value||0);
        const pu    = parseFloat(document.getElementById(`fu-${idx}`)?.value||0);
        if ((pid_p || desc) && qty > 0 && pu >= 0) {
            items.push({ producto_id: parseInt(pid_p||0)||null, descripcion: desc, unidad_medida: um, cantidad: qty, precio_unitario: pu });
        }
    });
    if (!items.length) { toast('Agrega al menos una cantidad','err'); return; }

    const btnReg = document.getElementById('btnRegistrarOrden');
    btnReg.disabled = true;

    try {
        const r = await fetch(`${API}?action=orden_crear`, {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({
                proveedor_id:   parseInt(pid),
                tipo_pago:      document.getElementById('ocTipoPago').value,
                dias_credito:   parseInt(document.getElementById('ocDiasCredito').value||0),
                numero_factura: document.getElementById('ocNumeroFactura').value.trim(),
                fecha_entrega:  document.getElementById('ocFechaEntrega').value || null,
                observaciones:  document.getElementById('ocObs').value.trim(),
                costo_envio:    parseFloat(document.getElementById('ocCostoEnvio').value || 0) || 0,
                items,
            }),
        });
        const d = await r.json();
        if (d.error) { toast(d.message,'err'); return; }

        _ultimaOrden = { id: d.id, numero: d.numero };
        cerrarModal('modalOrdenOverlay');
        cargarOrdenes(); cargarStats();
        toast('Orden registrada correctamente');
    } catch { toast('Error de conexión','err'); }
    finally { btnReg.disabled = false; }
}

function cerrarCompartir() {
    document.getElementById('modalCompartirOverlay').classList.remove('active');
}

function abrirPDF() {
    if (!_ultimaOrden) return;
    window.open(`<?= $base_path ?>modules/compras/pdf.php?id=${_ultimaOrden.id}`, '_blank');
}

async function enviarWhatsApp() {
    if (!_ultimaOrden) return;
    try {
        const r = await fetch(`${API}?action=orden_detalle&id=${_ultimaOrden.id}`);
        const o = await r.json();
        if (o.error) { toast(o.message, 'err'); return; }
        const lineas = (o.items || []).map(it =>
            `  • ${it.producto_nombre || it.descripcion || '—'} × ${it.cantidad}`
        ).join('\n');
        const texto = `*Orden de Compra: ${o.numero_orden || _ultimaOrden.numero}*\n`
            + `Proveedor: ${o.proveedor || '—'}\n`
            + `Tipo de pago: ${o.tipo_pago || '—'}\n\n`
            + `*Productos:*\n${lineas}`;
        window.open(`https://wa.me/?text=${encodeURIComponent(texto)}`, '_blank');
    } catch { toast('No se pudo preparar el mensaje', 'err'); }
}

async function enviarCorreo() {
    if (!_ultimaOrden) return;
    try {
        const r = await fetch(`${API}?action=orden_detalle&id=${_ultimaOrden.id}`);
        const o = await r.json();
        if (o.error) { toast(o.message, 'err'); return; }
        const lineas = (o.items || []).map(it =>
            `  - ${it.producto_nombre || it.descripcion || '—'}: ${it.cantidad} unidades`
        ).join('\n');
        const asunto = `Orden de Compra ${o.numero_orden || _ultimaOrden.numero}`;
        const cuerpo = `Estimado(a) ${o.proveedor || 'proveedor'},\n\n`
            + `Le hacemos llegar nuestra orden de compra N° ${o.numero_orden || _ultimaOrden.numero}:\n\n`
            + `${lineas}\n\n`
            + `Tipo de pago: ${o.tipo_pago || '—'}\n\n`
            + `Quedo a su disposición para cualquier consulta.\n\nSaludos.`;
        window.location.href = `mailto:?subject=${encodeURIComponent(asunto)}&body=${encodeURIComponent(cuerpo)}`;
    } catch { toast('No se pudo preparar el correo', 'err'); }
}

// ----------------------------------------------------------------
// Cuentas por Cobrar
// ----------------------------------------------------------------
let _filtroCobrar = '';

function filtrarCobrar(estado, btn) {
    _filtroCobrar = estado;
    document.querySelectorAll('#filtrosCobrar .filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    cargarCuentasCobrar();
}

async function cargarCuentasCobrar() {
    const tbody = document.getElementById('tbodyCobrar');
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:36px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    try {
        // Stats
        const rs = await fetch(`${API}?action=stats_cobrar`);
        const ds = await rs.json();
        if (!ds.error) {
            document.getElementById('sCobrarPendientes').textContent = ds.pendientes ?? '—';
            document.getElementById('sTotalPorCobrar').textContent   = 'S/ ' + parseFloat(ds.total_por_cobrar||0).toFixed(2);
            document.getElementById('sCobrarVencidas').textContent   = ds.vencidas ?? '—';
        }
        // Lista
        const url = _filtroCobrar
            ? `${API}?action=cuentas_cobrar_listar&estado=${_filtroCobrar}`
            : `${API}?action=cuentas_cobrar_listar`;
        const r    = await fetch(url);
        const lista = await r.json();
        if (lista.error) {
            tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:36px;color:#dc2626"><i class="fas fa-exclamation-triangle"></i> ${esc(lista.message)}</td></tr>`;
            return;
        }
        if (!lista.length) {
            tbody.innerHTML = _filtroCobrar
                ? `<tr><td colspan="9"><div class="empty-state"><i class="fas fa-search"></i>No se encontraron cuentas con ese filtro</div></td></tr>`
                : `<tr><td colspan="9">
                    <div class="empty-state">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <div>No hay cuentas por cobrar registradas</div>
                        <a onclick="abrirNuevaCuentaCobrar()" style="font-size:inherit;color:var(--primary);text-decoration:underline;cursor:pointer">Nueva cuenta por cobrar</a>
                    </div>
                   </td></tr>`;
            return;
        }
        const hoy = new Date().toISOString().split('T')[0];
        tbody.innerHTML = lista.map(c => {
            const pct     = c.monto_total > 0 ? (c.monto_pagado / c.monto_total * 100).toFixed(0) : 0;
            const vencida = c.fecha_vencimiento && c.fecha_vencimiento < hoy && c.estado !== 'pagado';
            const BADGE   = { pendiente:'b-pendiente', parcial:'b-parcial', pagado:'b-pagado', vencido:'b-vencido' };
            return `<tr>
                <td class="td-main">${esc(c.cliente||'—')}<br>
                    ${c.dni ? `<span style="font-size:.72rem;color:var(--text-muted)">DNI: ${esc(c.dni)}</span>` : ''}
                </td>
                <td style="font-size:.8rem;color:var(--text-muted)">${esc(c.numero_doc||'—')}</td>
                <td style="font-size:.8rem;color:var(--text-muted)">${c.numero_venta ? `Venta: ${esc(c.numero_venta)}` : '—'}</td>
                <td style="text-align:right">S/ ${parseFloat(c.monto_total).toFixed(2)}</td>
                <td style="text-align:right;color:#16a34a;font-weight:600">S/ ${parseFloat(c.monto_pagado).toFixed(2)}</td>
                <td style="text-align:right">
                    <div style="font-weight:600;color:${parseFloat(c.monto_pendiente)>0?'#dc2626':'#16a34a'}">
                        S/ ${parseFloat(c.monto_pendiente).toFixed(2)}
                    </div>
                    <div class="pay-bar"><div class="pay-bar-fill" style="width:${pct}%"></div></div>
                </td>
                <td style="font-size:.82rem;${vencida?'color:#dc2626;font-weight:600':'color:var(--text-muted)'}">
                    ${c.fecha_vencimiento ? fmtDate(c.fecha_vencimiento) : '—'}
                    ${vencida ? '<br><span style="font-size:.7rem">VENCIDA</span>' : ''}
                </td>
                <td><span class="badge ${BADGE[c.estado]||''}">${c.estado.charAt(0).toUpperCase()+c.estado.slice(1)}</span></td>
                <td>
                    ${c.estado !== 'pagado' ? `
                    <button class="btn btn-primary btn-sm" onclick="abrirCobro(${c.id},'${esc(c.cliente||'')}',${c.monto_pendiente},${c.monto_total},${c.monto_pagado})">
                        <i class="fas fa-hand-holding-usd"></i> Cobrar
                    </button>` : `<span style="font-size:.78rem;color:#16a34a"><i class="fas fa-check-circle"></i> Cobrado</span>`}
                </td>
            </tr>`;
        }).join('');
    } catch { tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:36px;color:#dc2626">Error al cargar</td></tr>'; }
}

// ---- Registrar cobro ----
async function abrirCobro(id, cliente, pendiente, total, pagado) {
    document.getElementById('cobrarCuentaId').value = id;
    document.getElementById('cobrarMonto').value    = parseFloat(pendiente).toFixed(2);
    document.getElementById('cobrarMonto').max      = pendiente;
    document.getElementById('cobrarRef').value      = '';
    document.getElementById('cobrarNotas').value    = '';
    document.getElementById('cobrarInfoCuenta').innerHTML = `
        <div style="font-weight:600;margin-bottom:4px">${esc(cliente)}</div>
        <div style="display:flex;gap:16px;font-size:.82rem;color:var(--text-secondary)">
            <span>Total: <strong>S/ ${parseFloat(total).toFixed(2)}</strong></span>
            <span>Cobrado: <strong style="color:#16a34a">S/ ${parseFloat(pagado).toFixed(2)}</strong></span>
            <span>Pendiente: <strong style="color:#dc2626">S/ ${parseFloat(pendiente).toFixed(2)}</strong></span>
        </div>`;
    // Historial de cobros previos
    try {
        const r = await fetch(`${API}?action=cuenta_cobrar_detalle&id=${id}`);
        const d = await r.json();
        const hist = d.cobros || [];
        document.getElementById('historialCobros').innerHTML = hist.length ? `
            <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px">Cobros anteriores</div>
            ${hist.map(p=>`<div class="pago-row">
                <span>${fmtDate(p.created_at)} — ${p.metodo_pago}${p.referencia?` (${p.referencia})`:''}</span>
                <strong>S/ ${parseFloat(p.monto).toFixed(2)}</strong>
            </div>`).join('')}` : '';
    } catch {}
    document.getElementById('modalCobrarOverlay').classList.add('active');
    setTimeout(() => document.getElementById('cobrarMonto').focus(), 80);
}

async function confirmarCobro() {
    const cuentaId = document.getElementById('cobrarCuentaId').value;
    const monto    = parseFloat(document.getElementById('cobrarMonto').value);
    if (!monto || monto <= 0) { toast('Ingresa un monto válido', 'err'); return; }
    try {
        const r = await fetch(`${API}?action=registrar_cobro`, {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({
                cuenta_id:   parseInt(cuentaId),
                monto,
                metodo_pago: document.getElementById('cobrarMetodo').value,
                referencia:  document.getElementById('cobrarRef').value.trim(),
                notas:       document.getElementById('cobrarNotas').value.trim(),
            }),
        });
        const d = await r.json();
        if (d.error) { toast(d.message, 'err'); return; }
        toast(d.message);
        cerrarModal('modalCobrarOverlay');
        cargarCuentasCobrar();
    } catch { toast('Error de conexión', 'err'); }
}

// ---- Nueva cuenta por cobrar ----
async function abrirNuevaCuentaCobrar() {
    document.getElementById('ncbMonto').value      = '';
    document.getElementById('ncbDoc').value        = '';
    document.getElementById('ncbVencimiento').value= '';
    document.getElementById('ncbNotas').value      = '';
    document.getElementById('ncbCliente').innerHTML = '<option value="">Cargando...</option>';
    try {
        const r = await fetch(`${API}?action=clientes_lista`);
        const lista = await r.json();
        document.getElementById('ncbCliente').innerHTML =
            '<option value="">Seleccionar cliente...</option>' +
            lista.map(c => `<option value="${c.id}">${esc(c.nombres + (c.apellidos?' '+c.apellidos:''))}${c.dni?' ('+c.dni+')':''}</option>`).join('');
    } catch { document.getElementById('ncbCliente').innerHTML = '<option value="">Error al cargar</option>'; }
    document.getElementById('modalNuevaCobrarOverlay').classList.add('active');
}

async function guardarCuentaCobrar() {
    const cliente_id = document.getElementById('ncbCliente').value;
    const monto      = parseFloat(document.getElementById('ncbMonto').value);
    if (!cliente_id) { toast('Selecciona un cliente', 'err'); return; }
    if (!monto || monto <= 0) { toast('Ingresa un monto válido', 'err'); return; }
    try {
        const r = await fetch(`${API}?action=cuenta_cobrar_crear`, {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({
                cliente_id:        parseInt(cliente_id),
                monto_total:       monto,
                numero_doc:        document.getElementById('ncbDoc').value.trim(),
                fecha_vencimiento: document.getElementById('ncbVencimiento').value || null,
                notas:             document.getElementById('ncbNotas').value.trim(),
            }),
        });
        const d = await r.json();
        if (d.error) { toast(d.message, 'err'); return; }
        toast(d.message);
        cerrarModal('modalNuevaCobrarOverlay');
        cargarCuentasCobrar();
    } catch { toast('Error de conexión', 'err'); }
}

// ----------------------------------------------------------------
// Utilidades
// ----------------------------------------------------------------
function cerrarModal(id) { document.getElementById(id).classList.remove('active'); }

function fmtDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleDateString('es-PE', {day:'2-digit',month:'short',year:'numeric'});
}
function esc(s) {
    return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function toast(msg, tipo='ok') {
    // Usa el toast del sistema si existe, si no crea uno temporal
    const existing = document.getElementById('app-toast');
    const el = existing || (() => {
        const t = document.createElement('div');
        t.id = 'app-toast';
        t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 18px;font-size:.85rem;display:flex;align-items:center;gap:10px;box-shadow:0 8px 24px rgba(0,0,0,.1);z-index:9999;opacity:0;transform:translateY(8px);transition:opacity .25s,transform .25s;pointer-events:none;max-width:320px';
        document.body.appendChild(t);
        return t;
    })();
    el.style.borderColor = tipo==='ok' ? '#86efac' : '#fca5a5';
    el.innerHTML = `<i class="fas fa-${tipo==='ok'?'check':'exclamation'}-circle" style="color:${tipo==='ok'?'#16a34a':'#dc2626'}"></i><span>${esc(msg)}</span>`;
    el.style.opacity = '1'; el.style.transform = 'none';
    clearTimeout(el._t);
    el._t = setTimeout(()=>{ el.style.opacity='0'; el.style.transform='translateY(8px)'; }, 3500);
}

document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if(e.target===o) cerrarModal(o.id); });
});

// ---- Init ----
cargarStats();
cargarOrdenes();
</script>

<!-- Modal compartir orden -->
<div class="modal-overlay" id="modalCompartirOverlay" onclick="if(event.target===this)cerrarCompartir()">
<div class="modal" style="max-width:420px">
    <div class="modal-header">
        <h3 class="modal-title"><i class="fas fa-share-alt"></i> Compartir orden</h3>
        <button class="modal-close" onclick="cerrarCompartir()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" style="padding:28px 24px;text-align:center">
        <p style="color:var(--text-muted);margin-bottom:6px;font-size:.85rem">Orden registrada</p>
        <p style="font-size:1.3rem;font-weight:700;color:var(--primary);margin-bottom:24px" id="compartirNumOrden"></p>
        <div style="display:flex;flex-direction:column;gap:12px">
            <button class="btn btn-primary" style="justify-content:center;gap:10px;font-size:.95rem" onclick="abrirPDF()">
                <i class="fas fa-file-pdf"></i> Ver / Imprimir PDF
            </button>
            <button class="btn btn-primary" style="justify-content:center;gap:10px;font-size:.95rem;background:#25d366" onclick="enviarWhatsApp()">
                <i class="fab fa-whatsapp"></i> Enviar por WhatsApp
            </button>
            <button class="btn btn-primary" style="justify-content:center;gap:10px;font-size:.95rem;background:#6366f1" onclick="enviarCorreo()">
                <i class="fas fa-envelope"></i> Enviar por correo
            </button>
        </div>
    </div>
    <div class="modal-footer" style="justify-content:center">
        <button class="btn btn-secondary" onclick="cerrarCompartir()">Cerrar</button>
    </div>
</div>
</div>

<?php require_once '../../includes/footer.php'; ?>
