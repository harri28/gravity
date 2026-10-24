<?php
// ============================================================
// ARCHIVO: farmacia/modules/almacen/index.php
// MÓDULO:  Almacén — Ingresos de Stock + Proveedores (tabs)
// ============================================================

require_once '../../config/database.php';

$base_path      = '../../';
$current_module = 'almacen';
$current_page   = 'almacen';
$page_title     = 'Almacén — Gravity';
$breadcrumb     = '<strong>Almacén</strong>';

$db = getDB();
$categorias = $db->query("SELECT id, nombre FROM public.categorias WHERE activo = TRUE ORDER BY nombre")->fetchAll();

include '../../includes/header.php';
?>

<style>
/* Quita las flechas de incremento/decremento de los inputs numéricos
   de las tablas de "Nuevo Ingreso" y "Nueva Salida" (Cantidad, Precios) */
#n-lineas-body input[type="number"]::-webkit-outer-spin-button,
#n-lineas-body input[type="number"]::-webkit-inner-spin-button,
#s-lineas-body input[type="number"]::-webkit-outer-spin-button,
#s-lineas-body input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
#n-lineas-body input[type="number"],
#s-lineas-body input[type="number"] {
    -moz-appearance: textfield;
}
.alm-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 20px;
    background: var(--surface-2);
    border-radius: var(--radius);
    padding: 5px;
    width: fit-content;
}
.alm-tab {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 8px 20px;
    border: none;
    border-radius: calc(var(--radius) - 2px);
    background: transparent;
    color: var(--text-muted);
    font-size: .88rem;
    font-weight: 500;
    cursor: pointer;
    transition: background .15s, color .15s;
    white-space: nowrap;
}
.alm-tab:hover { background: var(--surface); color: var(--text); }
.alm-tab.active { background: var(--primary); color: #fff; }
.alm-tab.active:hover { background: var(--primary-dark, var(--primary)); }

/* ---- Traslados tab ---- */
.toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:16px}
.toolbar-filters{display:flex;gap:8px;flex-wrap:wrap}
.trs-filter-btn{padding:6px 14px;border:1.5px solid var(--border);border-radius:20px;background:none;font-size:.78rem;font-weight:600;color:var(--text-muted);cursor:pointer;transition:.15s}
.trs-filter-btn:hover{border-color:var(--primary);color:var(--primary)}
.trs-filter-btn.active{background:var(--primary);border-color:var(--primary);color:#fff}
.row-pending-receive{background:#fff8e8}
.row-pending-receive:hover{background:#fef3c7 !important}
.pending-hint{display:inline-flex;align-items:center;gap:6px;margin-top:6px;padding:4px 10px;border-radius:999px;background:#fef3c7;color:#b45309;font-size:.72rem;font-weight:700}
.b-borrador{background:#f1f5f9;color:#64748b}
.b-enviado{background:#dbeafe;color:#1d4ed8}
.b-recibido{background:#dcfce7;color:#15803d}
.b-anulado{background:#fee2e2;color:#dc2626}
.modal-lg{max-width:980px}
.modal-md{max-width:680px}
.items-table{width:100%;border-collapse:collapse;margin-bottom:12px}
.items-table th{padding:8px 10px;font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);text-align:left;border-bottom:1px solid var(--border)}
.items-table td{padding:6px}
.items-table input{padding:7px 9px;border:1.5px solid var(--border);border-radius:7px;font-size:.83rem;color:var(--text);background:var(--surface);width:100%;outline:none}
.btn-del-row{background:#fee2e2;border:none;color:#dc2626;width:28px;height:28px;border-radius:6px;cursor:pointer;font-size:.8rem}
.btn-del-row:hover{background:#dc2626;color:#fff}
.trs-search-results{display:none;position:absolute;top:100%;left:0;right:0;background:var(--surface);border:1px solid var(--border);border-radius:10px;box-shadow:0 12px 30px rgba(0,0,0,.12);max-height:230px;overflow:auto;z-index:30}
.trs-result-item{padding:10px 12px;cursor:pointer;border-bottom:1px solid var(--border)}
.trs-result-item:last-child{border-bottom:none}
.trs-result-item:hover{background:var(--surface-2)}
.inline-actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
.trs-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
@media(max-width:900px){.trs-detail-grid{grid-template-columns:1fr}}
</style>

<div class="page-header">
    <div>
        <div class="page-title" id="alm-page-title">
            <i class="fas fa-warehouse" style="color:var(--primary);margin-right:8px"></i>Almacén
        </div>
        <div class="page-subtitle" id="alm-page-subtitle">Registra la recepción de mercadería de proveedores</div>
    </div>
    <div class="page-actions" id="alm-page-actions">
        <button class="btn btn-primary" onclick="openNuevoIngreso()">
            <i class="fas fa-plus"></i> Nuevo Ingreso
        </button>
    </div>
</div>

<!-- Tabs -->
<div class="alm-tabs">
    <button class="alm-tab active" id="tab-btn-movimientos" onclick="switchTab('movimientos')">
        <i class="fas fa-list"></i> Movimientos
    </button>
    <button class="alm-tab" id="tab-btn-ingresos"   onclick="switchTab('ingresos')">
        <i class="fas fa-truck-loading"></i> Ingresos de Stock
    </button>
    <button class="alm-tab" id="tab-btn-salidas"    onclick="switchTab('salidas')">
        <i class="fas fa-sign-out-alt"></i> Registro de Salida
    </button>
    <button class="alm-tab" id="tab-btn-almacen"    onclick="switchTab('almacen')">
        <i class="fas fa-boxes"></i> Almacén
    </button>
    <button class="alm-tab" id="tab-btn-traslados" onclick="switchTab('traslados')">
        <i class="fas fa-exchange-alt"></i> Traslados
    </button>
</div>

<!-- ===================== TAB: MOVIMIENTOS ===================== -->
<div id="tab-movimientos">

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" id="mov-desde" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" id="mov-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Tipo</label>
                <select class="form-control" id="mov-tipo">
                    <option value="">Todos</option>
                    <option value="entrada">Entrada</option>
                    <option value="salida">Salida</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Buscar</label>
                <div class="input-group">
                    <span class="input-group-icon"><i class="fas fa-search"></i></span>
                    <input type="text" id="mov-q" class="form-control" placeholder="N° de movimiento, proveedor o motivo...">
                </div>
            </div>
            <div class="col-6 col-md-auto">
                <button class="btn btn-primary w-100" onclick="loadMovimientos()"><i class="fas fa-search"></i> Buscar</button>
            </div>
        </div>
    </div>

    <!-- Tabla de movimientos -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Historial de movimientos de almacén</div>
            <span style="font-size:.82rem;color:var(--text-muted)" id="mov-count">Cargando...</span>
        </div>
        <div class="table-wrap table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Fecha</th>
                        <th>Detalle</th>
                        <th>Código</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Tipo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="mov-tabla-body">
                    <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)">
                        <i class="fas fa-spinner fa-spin"></i>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div><!-- /tab-movimientos -->

<!-- ===================== TAB: INGRESOS ===================== -->
<div id="tab-ingresos" style="display:none">


    <!-- Filtros -->
    <div class="card mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" id="f-desde" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" id="f-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Estado</label>
                <select class="form-control" id="f-estado">
                    <option value="">Todos</option>
                    <option value="completado">Completado</option>
                    <option value="anulado">Anulado</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Buscar</label>
                <div class="input-group">
                    <span class="input-group-icon"><i class="fas fa-search"></i></span>
                    <input type="text" id="f-q" class="form-control" placeholder="N° ingreso o proveedor...">
                </div>
            </div>
            <div class="col-6 col-md-auto">
                <button class="btn btn-primary w-100" onclick="loadIngresos()"><i class="fas fa-search"></i> Buscar</button>
            </div>
        </div>
    </div>

    <!-- Tabla de ingresos -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Historial de ingresos</div>
            <span style="font-size:.82rem;color:var(--text-muted)" id="result-count">Cargando...</span>
        </div>
        <div class="table-wrap table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>N° Ingreso</th>
                        <th>Fecha</th>
                        <th>Origen</th>
                        <th>Destino</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="tabla-body">
                    <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-light)">
                        <i class="fas fa-spinner fa-spin"></i>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div><!-- /tab-ingresos -->

<!-- ===================== TAB: SALIDAS ===================== -->
<div id="tab-salidas" style="display:none">

    <div class="card mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" id="sal-desde" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" id="sal-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Motivo</label>
                <select class="form-control" id="sal-motivo">
                    <option value="">Todos</option>
                    <option value="merma">Merma</option>
                    <option value="vencimiento">Vencimiento</option>
                    <option value="devolucion">Devolución</option>
                    <option value="transferencia">Transferencia</option>
                    <option value="otro">Otro</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Buscar</label>
                <div class="input-group">
                    <span class="input-group-icon"><i class="fas fa-search"></i></span>
                    <input type="text" id="sal-q" class="form-control" placeholder="N° salida o producto...">
                </div>
            </div>
            <div class="col-6 col-md-auto">
                <button class="btn btn-primary w-100" onclick="loadSalidas()"><i class="fas fa-search"></i> Buscar</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Historial de salidas</div>
            <span id="sal-count" style="font-size:.82rem;color:var(--text-muted)"></span>
        </div>
        <div class="table-wrap table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>N° Salida</th>
                        <th>Fecha</th>
                        <th>Motivo</th>
                        <th>Productos</th>
                        <th>Responsable</th>
                        <th>Observación</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="sal-body">
                    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">
                        <i class="fas fa-sign-out-alt" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3"></i>
                        No hay salidas registradas
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div><!-- /tab-salidas -->

<!-- ===================== TAB: ALMACÉN ===================== -->
<div id="tab-almacen" style="display:none">

    <div class="card mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label">Buscar producto</label>
                <div class="input-group">
                    <span class="input-group-icon"><i class="fas fa-search"></i></span>
                    <input type="text" id="alm-q" class="form-control" placeholder="Nombre, código o laboratorio...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Categoría</label>
                <select class="form-control" id="alm-cat">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Stock</label>
                <select class="form-control" id="alm-stock-filtro">
                    <option value="">Todos</option>
                    <option value="disponible">Con stock</option>
                    <option value="bajo">Stock bajo</option>
                    <option value="sin">Sin stock</option>
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <button class="btn btn-primary w-100" onclick="loadAlmacenStock()"><i class="fas fa-search"></i> Filtrar</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Resumen de stock</div>
            <span id="alm-stock-count" style="font-size:.82rem;color:var(--text-muted)"></span>
        </div>
        <div class="table-wrap table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Laboratorio</th>
                        <th class="text-right">Stock actual</th>
                        <th class="text-right">Stock mín.</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="alm-stock-body">
                    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">
                        <i class="fas fa-spinner fa-spin"></i>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div><!-- /tab-almacen -->

<!-- ===================== TAB: TRASLADOS ===================== -->
<div id="tab-traslados" style="display:none">

    <div class="toolbar">
        <div class="toolbar-filters" id="filtrosTraslado">
            <button class="trs-filter-btn active" onclick="filtrarEstado('', this)">Todos</button>
            <button class="trs-filter-btn" onclick="filtrarEstado('borrador', this)">Borrador</button>
            <button class="trs-filter-btn" onclick="filtrarEstado('enviado', this)">Enviado</button>
            <button class="trs-filter-btn" onclick="filtrarEstado('recibido', this)">Recibido</button>
            <button class="trs-filter-btn" onclick="filtrarEstado('anulado', this)">Anulado</button>
        </div>
        <input type="text" id="trs-q" class="form-control" style="min-width:220px;flex:1 1 220px;max-width:380px" placeholder="Buscar número o sucursal...">
    </div>

    <div class="card">
        <div class="table-wrap table-responsive">
            <table class="data-table">
                <thead><tr>
                    <th>N° Traslado</th>
                    <th>Origen</th>
                    <th>Destino</th>
                    <th>Items</th>
                    <th>Unidades</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr></thead>
                <tbody id="tbodyTraslados">
                    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">
                        <i class="fas fa-exchange-alt" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3"></i>
                        Selecciona este tab para cargar los traslados
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div><!-- /tab-traslados -->

<!-- ===================== MODAL: Nuevo Traslado ===================== -->
<div class="modal-overlay" id="modal-traslado">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-exchange-alt" style="color:var(--primary);margin-right:8px"></i>Nuevo traslado</div>
            <button class="modal-close" onclick="closeModal('modal-traslado')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                <div class="form-group" style="margin:0">
                    <label class="form-label">Sucursal origen</label>
                    <input type="text" id="trs-origen" class="form-control" readonly value="<?= htmlspecialchars(sesionSucursal()) ?>">
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">Sucursal destino *</label>
                    <select id="trs-destino" class="form-control"></select>
                </div>
            </div>
            <div class="form-group" style="position:relative;margin-bottom:14px">
                <label class="form-label">Agregar producto</label>
                <input type="text" id="trs-producto-buscar" class="form-control" placeholder="Busca por código o nombre">
                <div class="trs-search-results" id="trs-resultados"></div>
            </div>
            <table class="items-table">
                <thead><tr>
                    <th>Código</th><th>Producto</th>
                    <th style="width:120px">Stock origen</th>
                    <th style="width:120px">Cantidad</th>
                    <th style="width:140px">Costo</th>
                    <th style="width:40px"></th>
                </tr></thead>
                <tbody id="tbodyItemsTraslado">
                    <tr id="emptyItemsTraslado"><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Agrega productos para trasladar</td></tr>
                </tbody>
            </table>
            <div class="form-group" style="margin:0">
                <label class="form-label">Observaciones</label>
                <textarea id="trs-observaciones" class="form-control" rows="3" placeholder="Detalle interno del traslado..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-traslado')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarTraslado()"><i class="fas fa-save"></i> Guardar traslado</button>
        </div>
    </div>
</div>

<!-- ===================== MODAL: Detalle Traslado ===================== -->
<div class="modal-overlay" id="modal-detalle-traslado">
    <div class="modal modal-md">
        <div class="modal-header">
            <div class="modal-title" id="detalleTrasladoTitulo">Detalle del traslado</div>
            <button class="modal-close" onclick="closeModal('modal-detalle-traslado')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="detalleTrasladoBody">
            <div style="text-align:center;padding:32px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
        <div class="modal-footer inline-actions" id="detalleTrasladoActions"></div>
    </div>
</div>

<!-- ===================== MODAL: Detalle de Ingreso ===================== -->
<div class="modal-overlay" id="modal-detalle">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title" id="detalle-titulo">Detalle de Ingreso</h3>
            <button class="modal-close" onclick="closeModal('modal-detalle')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="detalle-body"></div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-detalle')">Cerrar</button>
            <button class="btn btn-danger" id="btn-anular" style="display:none" onclick="anularIngreso()">
                <i class="fas fa-ban"></i> Anular Ingreso
            </button>
        </div>
    </div>
</div>

<!-- ===================== MODAL: Nuevo Ingreso ===================== -->
<div class="modal-overlay" id="modal-nuevo">
    <div class="modal" style="max-width:900px;width:96%">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-truck-loading" style="color:var(--primary);margin-right:8px"></i>Nuevo Ingreso de Stock
            </h3>
            <button class="modal-close" onclick="closeModal('modal-nuevo')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="max-height:75vh;overflow-y:auto">

            <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px;margin-bottom:18px">
                <div class="form-group" style="margin:0">
                    <label class="form-label">Proveedor</label>
                    <select id="n-proveedor" class="form-control">
                        <option value="">— Sin proveedor —</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">N° Factura / Guía</label>
                    <input type="text" id="n-factura" class="form-control" placeholder="F001-00123">
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">Fecha Factura</label>
                    <input type="date" id="n-fecha" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div style="margin-bottom:12px">
                <label class="form-label">
                    <i class="fas fa-barcode" style="color:var(--primary);margin-right:5px"></i>
                    Agregar producto — escaneá o escribí nombre / código
                </label>
                <div style="position:relative">
                    <div class="input-group">
                        <span class="input-group-icon"><i class="fas fa-search"></i></span>
                        <input type="text" id="n-producto-search" class="form-control"
                            placeholder="Nombre o código de barras..." autocomplete="off">
                    </div>
                    <div id="n-producto-dropdown"
                        style="display:none;position:absolute;top:100%;left:0;right:0;background:var(--surface);
                               border:1px solid var(--border);border-radius:var(--radius);z-index:200;
                               box-shadow:0 4px 16px rgba(0,0,0,.12);max-height:220px;overflow-y:auto">
                    </div>
                </div>
            </div>

            <div class="table-wrap" style="margin-bottom:14px">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th class="text-right" style="width:90px">Cantidad</th>
                            <th class="text-right" style="width:120px">P. Compra (S/)</th>
                            <th class="text-right" style="width:120px">P. Venta (S/)</th>
                            <th class="text-right" style="width:100px">Subtotal</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="n-lineas-body">
                        <tr id="n-lineas-empty">
                            <td colspan="7" style="text-align:center;padding:24px;color:var(--text-light)">
                                <i class="fas fa-inbox" style="font-size:1.3rem"></i>
                                <p style="margin:8px 0 0">Agrega productos escaneando o buscando arriba</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="display:flex;justify-content:flex-end;margin-bottom:14px">
                <div style="min-width:260px;background:var(--surface-2);border-radius:var(--radius);padding:12px 16px;font-size:.88rem">
                    <div style="display:flex;justify-content:space-between;padding:3px 0">
                        <span style="color:var(--text-muted)">Subtotal</span>
                        <span id="n-subtotal">S/ 0.00</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0 0;border-top:1px solid var(--border);margin-top:6px;font-weight:700;font-size:1rem">
                        <span>TOTAL</span>
                        <span id="n-total" style="color:var(--success)">S/ 0.00</span>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin:0">
                <label class="form-label">Observaciones <span style="font-size:.8rem;color:var(--text-muted)">(opcional)</span></label>
                <input type="text" id="n-obs" class="form-control" placeholder="Notas sobre el ingreso...">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-nuevo')">Cancelar</button>
            <button class="btn btn-primary btn-lg" id="btn-guardar-ingreso" onclick="saveIngreso()">
                <i class="fas fa-save"></i> Registrar Ingreso
            </button>
        </div>
    </div>
</div>

<!-- ===================== MODAL: NUEVA SALIDA ===================== -->
<div class="modal-overlay" id="modal-nueva-salida">
    <div class="modal" style="max-width:900px;width:96%">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-sign-out-alt" style="color:var(--primary);margin-right:8px"></i>Nueva Salida de Almacén
            </h3>
            <button class="modal-close" onclick="closeModal('modal-nueva-salida')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="max-height:75vh;overflow-y:auto">

            <div style="display:grid;grid-template-columns:1fr 2fr;gap:14px;margin-bottom:10px">
                <div class="form-group" style="margin:0">
                    <label class="form-label">Motivo</label>
                    <select id="s-motivo" class="form-control">
                        <option value="merma">Merma</option>
                        <option value="vencimiento">Vencimiento</option>
                        <option value="devolucion">Devolución a proveedor</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">Observaciones <span style="font-size:.8rem;color:var(--text-muted)">(opcional)</span></label>
                    <input type="text" id="s-obs" class="form-control" placeholder="Notas sobre la salida...">
                </div>
            </div>

            <div style="background:var(--surface-2);border-radius:var(--radius);padding:10px 14px;margin-bottom:16px;font-size:.82rem;color:var(--text-muted);display:flex;align-items:center;gap:8px">
                <i class="fas fa-circle-info" style="color:var(--primary)"></i>
                ¿Necesitas enviar stock a otra sucursal? Eso se hace desde
                <a href="javascript:void(0)" onclick="closeModal('modal-nueva-salida');switchTab('traslados')" style="color:var(--primary);font-weight:600">Traslados</a>,
                no desde acá — esta pantalla es solo para stock que sale del sistema (mermas, vencimientos, devoluciones).
            </div>

            <div style="margin-bottom:12px">
                <label class="form-label">
                    <i class="fas fa-barcode" style="color:var(--primary);margin-right:5px"></i>
                    Agregar producto — escaneá o escribí nombre / código
                </label>
                <div style="position:relative">
                    <div class="input-group">
                        <span class="input-group-icon"><i class="fas fa-search"></i></span>
                        <input type="text" id="s-producto-search" class="form-control"
                            placeholder="Nombre o código de barras..." autocomplete="off">
                    </div>
                    <div id="s-producto-dropdown"
                        style="display:none;position:absolute;top:100%;left:0;right:0;background:var(--surface);
                               border:1px solid var(--border);border-radius:var(--radius);z-index:200;
                               box-shadow:0 4px 16px rgba(0,0,0,.12);max-height:220px;overflow-y:auto">
                    </div>
                </div>
            </div>

            <div class="table-wrap" style="margin-bottom:14px">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th class="text-right" style="width:90px">Cantidad</th>
                            <th class="text-right" style="width:120px">Costo unit. (S/)</th>
                            <th class="text-right" style="width:100px">Subtotal</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="s-lineas-body">
                        <tr id="s-lineas-empty">
                            <td colspan="6" style="text-align:center;padding:24px;color:var(--text-light)">
                                <i class="fas fa-inbox" style="font-size:1.3rem"></i>
                                <p style="margin:8px 0 0">Agrega productos escaneando o buscando arriba</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="display:flex;justify-content:flex-end">
                <div style="min-width:260px;background:var(--surface-2);border-radius:var(--radius);padding:12px 16px;font-size:.88rem">
                    <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1rem">
                        <span>VALOR TOTAL</span>
                        <span id="s-total" style="color:var(--danger)">S/ 0.00</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-nueva-salida')">Cancelar</button>
            <button class="btn btn-primary btn-lg" id="btn-guardar-salida" onclick="saveSalida()">
                <i class="fas fa-save"></i> Registrar Salida
            </button>
        </div>
    </div>
</div>


<div class="app-toast-container" id="toast-container"></div>

<script>
const BASE = '../../';
const SUCURSAL_NOMBRE     = <?= json_encode(sesionSucursal()) ?>;
const CURRENT_SUCURSAL_ID = <?= (int) sesionSucursalId() ?>;
let currentIngresoId = null;
let lines = [];
let trasladoEstadoFiltro = '';
let trasladoItems = [];
let sucursalesTraslado = [];

// ---- Tabs ----
const TABS = ['movimientos', 'ingresos', 'salidas', 'almacen', 'traslados'];
const TAB_CFG = {
    movimientos: { title: '<i class="fas fa-list" style="color:var(--primary);margin-right:8px"></i>Movimientos',                subtitle: 'Historial de todas las entradas y salidas de stock del almacén', actions: '' },
    ingresos:   { title: '<i class="fas fa-truck-loading" style="color:var(--primary);margin-right:8px"></i>Ingresos de Stock',  subtitle: 'Registra la recepción de mercadería de proveedores',       actions: '<button class="btn btn-primary" onclick="openNuevoIngreso()"><i class="fas fa-plus"></i> Nuevo Ingreso</button>' },
    salidas:    { title: '<i class="fas fa-sign-out-alt" style="color:var(--primary);margin-right:8px"></i>Registro de Salida', subtitle: 'Registra salidas de stock por merma, vencimiento o devolución', actions: '<button class="btn btn-primary" onclick="openNuevaSalida()"><i class="fas fa-plus"></i> Nueva Salida</button>' },
    almacen:    { title: '<i class="fas fa-boxes" style="color:var(--primary);margin-right:8px"></i>Almacén',                   subtitle: 'Resumen y estado actual del stock por producto',               actions: '' },
    traslados:  { title: '<i class="fas fa-exchange-alt" style="color:var(--primary);margin-right:8px"></i>Traslados',            subtitle: 'Mueve productos entre sucursales de forma controlada',     actions: '<button class="btn btn-primary" onclick="abrirNuevoTraslado()"><i class="fas fa-plus"></i> Nuevo traslado</button>' },
};

function switchTab(tab) {
    TABS.forEach(t => {
        document.getElementById('tab-' + t).style.display = t === tab ? '' : 'none';
        document.getElementById('tab-btn-' + t).classList.toggle('active', t === tab);
    });
    const cfg = TAB_CFG[tab];
    document.getElementById('alm-page-title').innerHTML       = cfg.title;
    document.getElementById('alm-page-subtitle').textContent  = cfg.subtitle;
    document.getElementById('alm-page-actions').innerHTML     = cfg.actions;
    location.hash = tab;

    if (tab === 'movimientos') loadMovimientos();
    if (tab === 'ingresos')    loadIngresos();
    if (tab === 'almacen')    loadAlmacenStock();
    if (tab === 'salidas')    loadSalidas();
    if (tab === 'traslados')  loadSucursalesTraslado().then(loadTraslados);
}

function loadMovimientos() {
    const params = new URLSearchParams({
        action: 'movimientos_listar',
        desde:  document.getElementById('mov-desde').value,
        hasta:  document.getElementById('mov-hasta').value,
        tipo:   document.getElementById('mov-tipo').value,
        q:      document.getElementById('mov-q').value,
    });
    document.getElementById('mov-tabla-body').innerHTML =
        '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i> Buscando...</td></tr>';

    fetch(BASE + 'modules/almacen/api.php?' + params)
        .then(r => r.json())
        .then(data => {
            document.getElementById('mov-count').textContent = data.length + ' resultado(s)';
            if (!data.length) {
                document.getElementById('mov-tabla-body').innerHTML =
                    '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-inbox" style="font-size:1.3rem"></i><br><br>No se encontraron movimientos</td></tr>';
                return;
            }
            document.getElementById('mov-tabla-body').innerHTML = data.map(m => {
                const dt = new Date(m.created_at);
                const fechaStr = dt.toLocaleDateString('es-PE') + ' ' + dt.toLocaleTimeString('es-PE', {hour:'2-digit', minute:'2-digit'});
                const tipoBadge = m.tipo === 'entrada'
                    ? '<span class="badge badge-success"><i class="fas fa-arrow-down"></i> Entrada</span>'
                    : '<span class="badge badge-danger"><i class="fas fa-arrow-up"></i> Salida</span>';
                const codigoTxt = m.num_items == 1 && m.producto_codigo
                    ? m.producto_codigo
                    : `<span style="color:var(--text-muted)">Varios</span>`;
                const verDetalleFn = m.tipo === 'entrada' ? 'verDetalle' : 'verDetalleSalida';
                return `<tr>
                    <td><span style="font-weight:700;color:var(--primary)">${m.numero}</span></td>
                    <td style="font-size:.82rem;color:var(--text-muted)">${fechaStr}</td>
                    <td style="font-size:.85rem">${m.detalle}${m.observaciones ? ' <span style="color:var(--text-muted)">— ' + m.observaciones + '</span>' : ''}</td>
                    <td style="font-size:.8rem">${codigoTxt}</td>
                    <td><span class="badge badge-gray">${m.num_items}</span></td>
                    <td class="text-right"><strong>S/ ${parseFloat(m.total).toFixed(2)}</strong></td>
                    <td>${tipoBadge}</td>
                    <td>
                        <button class="btn btn-ghost btn-sm" title="Ver detalle"
                                onclick="${verDetalleFn}(${m.id},'${m.numero}','${m.estado}')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');
        })
        .catch(() => showToast('Error al cargar movimientos', 'error'));
}

// ---- Init ----
document.addEventListener('DOMContentLoaded', () => {
    loadMovimientos();
    loadIngresos();
    loadProveedoresSelect();
    setupProductoSearch();
    setupSalidaProductoSearch();
    setupBarcodeScanner();
    document.getElementById('f-q').addEventListener('keyup', e => { if (e.key === 'Enter') loadIngresos(); });
    document.getElementById('trs-q').addEventListener('keyup', e => { if (e.key === 'Enter') loadTraslados(); });
    document.getElementById('trs-producto-buscar').addEventListener('input', e => buscarProductosTraslado(e.target.value.trim()));
    document.addEventListener('click', e => {
        const box = document.getElementById('trs-resultados');
        if (box && !box.contains(e.target) && e.target.id !== 'trs-producto-buscar') box.style.display = 'none';
    });
    const hash = location.hash.replace('#', '');
    if (TABS.includes(hash)) switchTab(hash);
});

// ---- Salidas ----
let salidaLines = [];
let currentSalidaId = null;

function loadSalidas() {
    const params = new URLSearchParams({
        action: 'salidas_listar',
        desde:  document.getElementById('sal-desde').value,
        hasta:  document.getElementById('sal-hasta').value,
        motivo: document.getElementById('sal-motivo').value,
        q:      document.getElementById('sal-q').value,
    });
    document.getElementById('sal-body').innerHTML =
        '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';

    fetch(BASE + 'modules/almacen/api.php?' + params)
        .then(r => r.json())
        .then(data => {
            document.getElementById('sal-count').textContent = data.length + ' registro(s)';
            if (!data.length) {
                document.getElementById('sal-body').innerHTML =
                    '<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-sign-out-alt" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3"></i>No hay salidas registradas</td></tr>';
                return;
            }
            const MOTIVOS = { merma:'Merma', vencimiento:'Vencimiento', devolucion:'Devolución', otro:'Otro' };
            document.getElementById('sal-body').innerHTML = data.map(s => {
                const esCls = s.estado === 'completado' ? 'badge-success' : 'badge-danger';
                const dt    = new Date(s.created_at);
                const fecha = dt.toLocaleDateString('es-PE') + ' ' + dt.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
                return `<tr style="cursor:pointer" onclick="verDetalleSalida(${s.id},'${s.numero_salida}','${s.estado}')">
                    <td><span style="font-weight:700;color:var(--primary)">${s.numero_salida}</span></td>
                    <td style="font-size:.82rem;color:var(--text-muted)">${fecha}</td>
                    <td style="font-size:.85rem">${MOTIVOS[s.motivo] || s.motivo}</td>
                    <td style="font-size:.85rem;text-align:center">${s.total_productos}</td>
                    <td style="font-size:.85rem">${s.usuario || '—'}</td>
                    <td style="font-size:.82rem;color:var(--text-muted)">${s.observaciones || '—'}</td>
                    <td><span class="badge ${esCls}">${s.estado}</span></td>
                    <td></td>
                </tr>`;
            }).join('');
        })
        .catch(() => showToast('Error al cargar salidas', 'error'));
}

function verDetalleSalida(id, numero, estado) {
    currentSalidaId = id;
    document.getElementById('detalle-titulo').innerHTML =
        '<i class="fas fa-sign-out-alt" style="color:var(--primary);margin-right:8px"></i>Detalle: ' + numero;
    const btnAnular = document.getElementById('btn-anular');
    btnAnular.style.display = estado === 'completado' ? 'inline-flex' : 'none';
    btnAnular.setAttribute('onclick', 'anularSalida()');
    btnAnular.innerHTML = '<i class="fas fa-ban"></i> Anular Salida';
    document.getElementById('detalle-body').innerHTML =
        '<div style="text-align:center;padding:30px"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:var(--text-light)"></i></div>';
    openModal('modal-detalle');

    fetch(BASE + `modules/almacen/api.php?action=salida_detalle&id=${id}`)
        .then(r => r.json())
        .then(items => {
            if (!items.length) {
                document.getElementById('detalle-body').innerHTML =
                    '<p style="text-align:center;padding:20px;color:var(--text-muted)">Sin detalles disponibles</p>';
                return;
            }
            const total = items.reduce((s, i) => s + parseFloat(i.subtotal), 0);
            document.getElementById('detalle-body').innerHTML = `
                <div class="table-wrap">
                    <table>
                        <thead><tr>
                            <th>Código</th><th>Producto</th>
                            <th class="text-right">Cant.</th>
                            <th class="text-right">Costo unit.</th>
                            <th class="text-right">Subtotal</th>
                        </tr></thead>
                        <tbody>${items.map(i => `
                            <tr>
                                <td style="font-size:.8rem;color:var(--text-muted)">${i.codigo}</td>
                                <td style="font-weight:500">${i.producto_nombre}</td>
                                <td class="text-right">${i.cantidad}</td>
                                <td class="text-right">S/ ${parseFloat(i.costo_unitario).toFixed(2)}</td>
                                <td class="text-right"><strong>S/ ${parseFloat(i.subtotal).toFixed(2)}</strong></td>
                            </tr>`).join('')}
                        </tbody>
                        <tfoot><tr style="background:var(--surface-2)">
                            <td colspan="4" style="padding:10px 14px;font-weight:700;text-align:right">TOTAL</td>
                            <td style="padding:10px 14px;font-weight:700;text-align:right;color:var(--danger);font-size:1rem">
                                S/ ${total.toFixed(2)}
                            </td>
                        </tr></tfoot>
                    </table>
                </div>`;
        });
}

function anularSalida() {
    if (!currentSalidaId || !confirm('¿Anular esta salida? El stock de todos los productos será devuelto.')) return;
    fetch(BASE + 'modules/almacen/api.php?action=anular_salida', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: currentSalidaId }),
    })
    .then(r => r.json())
    .then(d => {
        if (d.error) { showToast(d.message, 'error'); return; }
        showToast('Salida anulada correctamente', 'success');
        closeModal('modal-detalle');
        loadSalidas();
        loadStats();
    })
    .catch(() => showToast('Error al anular', 'error'));
}

function openNuevaSalida() {
    salidaLines = [];
    renderSalidaLineas();
    document.getElementById('s-motivo').value = 'merma';
    document.getElementById('s-obs').value     = '';
    document.getElementById('s-producto-search').value = '';
    document.getElementById('s-producto-dropdown').style.display = 'none';
    calcularTotalSalida();
    openModal('modal-nueva-salida');
    setTimeout(() => document.getElementById('s-producto-search').focus(), 100);
}

function setupSalidaProductoSearch() {
    let timer;
    const input    = document.getElementById('s-producto-search');
    const dropdown = document.getElementById('s-producto-dropdown');

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (!q) { dropdown.style.display = 'none'; return; }
        timer = setTimeout(() => buscarProductoSalida(q), 200);
    });

    input.addEventListener('keydown', e => {
        if (e.key === 'Escape') { dropdown.style.display = 'none'; input.value = ''; }
    });

    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

function buscarProductoSalida(q) {
    const dropdown = document.getElementById('s-producto-dropdown');
    fetch(BASE + `modules/almacen/api.php?action=buscar_producto&q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                dropdown.innerHTML = '<div style="padding:12px 16px;color:var(--text-muted);font-size:.85rem">Sin resultados</div>';
                dropdown.style.display = 'block';
                return;
            }
            dropdown.innerHTML = data.map(p => `
                <div onclick='seleccionarProductoSalida(${JSON.stringify(p)})'
                    style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border);
                           display:flex;justify-content:space-between;align-items:center;font-size:.85rem"
                    onmouseover="this.style.background='var(--primary-light)'"
                    onmouseout="this.style.background=''">
                    <div>
                        <span style="font-weight:600">${p.nombre}</span>
                        <span style="color:var(--text-muted);margin-left:8px;font-size:.78rem">${p.codigo}</span>
                    </div>
                    <div style="text-align:right;white-space:nowrap">
                        <div style="color:var(--text-muted);font-size:.78rem">Stock: ${p.stock}</div>
                        <div style="color:var(--primary);font-weight:600">S/ ${parseFloat(p.precio_compra).toFixed(2)}</div>
                    </div>
                </div>`).join('');
            dropdown.style.display = 'block';
        });
}

function seleccionarProductoSalida(p) {
    document.getElementById('s-producto-dropdown').style.display = 'none';
    document.getElementById('s-producto-search').value = '';
    addSalidaLinea(p);
    document.getElementById('s-producto-search').focus();
}

function addSalidaLinea(p) {
    const pid = parseInt(p.id);
    const existing = salidaLines.findIndex(l => l.producto_id === pid);
    if (existing >= 0) {
        salidaLines[existing].cantidad++;
    } else {
        salidaLines.unshift({ producto_id: pid, codigo: p.codigo, nombre: p.nombre, stock_disponible: parseFloat(p.stock), cantidad: 1, costo_unitario: parseFloat(p.precio_compra) || 0 });
    }
    renderSalidaLineas();
    calcularTotalSalida();
}

function updateSalidaLinea(idx, field, value) {
    if (field === 'cantidad')       salidaLines[idx].cantidad       = Math.max(0.01, parseFloat(value) || 1);
    if (field === 'costo_unitario') salidaLines[idx].costo_unitario = Math.max(0, parseFloat(value) || 0);

    const sub = salidaLines[idx].cantidad * salidaLines[idx].costo_unitario;
    const subtotalCell = document.querySelector(`.salida-subtotal[data-idx="${idx}"]`);
    if (subtotalCell) subtotalCell.textContent = 'S/ ' + sub.toFixed(2);

    calcularTotalSalida();
}

function removeSalidaLinea(idx) { salidaLines.splice(idx, 1); renderSalidaLineas(); calcularTotalSalida(); }

function renderSalidaLineas() {
    const tbody = document.getElementById('s-lineas-body');
    const empty = document.getElementById('s-lineas-empty');
    tbody.querySelectorAll('.linea-row').forEach(r => r.remove());
    if (!salidaLines.length) { empty.style.display = ''; return; }
    empty.style.display = 'none';
    salidaLines.forEach((l, idx) => {
        const sub = l.cantidad * l.costo_unitario;
        const excedeStock = l.cantidad > l.stock_disponible;
        const tr  = document.createElement('tr');
        tr.className = 'linea-row';
        tr.innerHTML = `
            <td style="font-size:.8rem;color:var(--text-muted);font-family:monospace">${l.codigo}</td>
            <td style="font-weight:500;font-size:.88rem">${l.nombre}${excedeStock ? `<div style="color:var(--danger);font-size:.75rem">Stock disponible: ${l.stock_disponible}</div>` : ''}</td>
            <td class="text-right">
                <input type="text" inputmode="decimal" value="${l.cantidad}"
                    style="width:80px;text-align:center;padding:5px 10px;border:1px solid ${excedeStock ? 'var(--danger)' : 'var(--border)'};
                           border-radius:var(--radius-sm);font-size:.88rem;background:var(--surface)"
                    oninput="updateSalidaLinea(${idx},'cantidad',sanitizarCantidadInput(this))"
                    onfocus="cursorAlFinal(this)">
            </td>
            <td class="text-right">
                <input type="number" value="${l.costo_unitario.toFixed(2)}" min="0" step="0.01"
                    style="width:90px;text-align:right;padding:5px 7px;border:1px solid var(--border);
                           border-radius:var(--radius-sm);font-size:.88rem;background:var(--surface)"
                    oninput="updateSalidaLinea(${idx},'costo_unitario',this.value)">
            </td>
            <td class="text-right salida-subtotal" data-idx="${idx}" style="font-weight:600">S/ ${sub.toFixed(2)}</td>
            <td>
                <button onclick="removeSalidaLinea(${idx})"
                    style="background:none;border:none;color:var(--danger);cursor:pointer;padding:4px 6px;font-size:.9rem">
                    <i class="fas fa-trash"></i>
                </button>
            </td>`;
        tbody.insertBefore(tr, empty);
    });
}

function calcularTotalSalida() {
    const total = salidaLines.reduce((s, l) => s + l.cantidad * l.costo_unitario, 0);
    document.getElementById('s-total').textContent = 'S/ ' + total.toFixed(2);
}

function saveSalida() {
    if (!salidaLines.length) { showToast('Agrega al menos un producto', 'error'); return; }
    for (const l of salidaLines) {
        if (l.cantidad <= 0) { showToast('La cantidad debe ser mayor a 0', 'error'); return; }
        if (l.cantidad > l.stock_disponible) { showToast(`Stock insuficiente en "${l.nombre}" (disponible: ${l.stock_disponible})`, 'error'); return; }
    }
    const payload = {
        motivo:        document.getElementById('s-motivo').value,
        observaciones: document.getElementById('s-obs').value,
        items: salidaLines.map(l => ({ producto_id: l.producto_id, cantidad: l.cantidad, costo_unitario: l.costo_unitario })),
    };
    const btn = document.getElementById('btn-guardar-salida');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
    fetch(BASE + 'modules/almacen/api.php?action=registrar_salida', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Registrar Salida';
        if (d.error) { showToast(d.message, 'error'); return; }
        showToast('Salida ' + d.numero_salida + ' registrada correctamente', 'success');
        closeModal('modal-nueva-salida');
        loadSalidas();
        loadStats();
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Registrar Salida';
        showToast('Error al registrar', 'error');
    });
}

// ---- Almacén stock ----
function loadAlmacenStock() {
    const q       = document.getElementById('alm-q').value.trim();
    const cat     = document.getElementById('alm-cat').value;
    const filtro  = document.getElementById('alm-stock-filtro').value;
    const tbody   = document.getElementById('alm-stock-body');
    const counter = document.getElementById('alm-stock-count');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';

    const params = new URLSearchParams({ action: 'listar', q, categoria_id: cat });
    fetch(BASE + 'modules/inventario/api.php?' + params)
        .then(r => r.json())
        .then(data => {
            let items = data;
            if (filtro === 'disponible') items = data.filter(p => parseFloat(p.stock) > parseFloat(p.stock_minimo || 0));
            if (filtro === 'bajo')       items = data.filter(p => parseFloat(p.stock) > 0 && parseFloat(p.stock) <= parseFloat(p.stock_minimo || 0));
            if (filtro === 'sin')        items = data.filter(p => parseFloat(p.stock) <= 0);

            counter.textContent = items.length + ' producto(s)';

            if (!items.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted)">No se encontraron productos</td></tr>';
                return;
            }
            tbody.innerHTML = items.map(p => {
                const stock = parseFloat(p.stock);
                const min   = parseFloat(p.stock_minimo || 0);
                const badge = stock <= 0
                    ? '<span class="badge badge-danger">Sin stock</span>'
                    : stock <= min
                        ? '<span class="badge badge-warning">Stock bajo</span>'
                        : '<span class="badge badge-success">OK</span>';
                return `<tr>
                    <td style="font-family:monospace;font-size:.8rem">${p.codigo}</td>
                    <td style="font-weight:500">${p.nombre}</td>
                    <td style="font-size:.82rem;color:var(--text-muted)">${p.categoria_nombre || '—'}</td>
                    <td style="font-size:.82rem;color:var(--text-muted)">${p.laboratorio || '—'}</td>
                    <td class="text-right" style="font-weight:700;color:${stock <= 0 ? 'var(--danger)' : stock <= min ? 'var(--warning)' : 'var(--success)'}">${stock}</td>
                    <td class="text-right" style="color:var(--text-muted)">${min}</td>
                    <td style="font-size:.82rem">${p.fecha_vencimiento || '—'}</td>
                    <td>${badge}</td>
                </tr>`;
            }).join('');
        })
        .catch(() => showToast('Error al cargar stock', 'error'));
}

// ========================================================
// INGRESOS
// ========================================================

function loadStats() {
    fetch(BASE + 'modules/almacen/api.php?action=stats_almacen')
        .then(r => r.json())
        .then(d => {
            const cfg = [
                { icon: 'truck-loading', color: 'blue',   val: d.ingresos_mes,        label: 'Ingresos este mes' },
                { icon: 'dollar-sign',   color: 'green',  val: 'S/ ' + parseFloat(d.valor_mes).toFixed(2), label: 'Valor comprado' },
                { icon: 'truck',         color: 'yellow', val: d.proveedores_activos,  label: 'Proveedores activos' },
                { icon: 'ban',           color: 'red',    val: d.anulados_mes,         label: 'Anulados este mes' },
            ];
            document.getElementById('stats-container').innerHTML = cfg.map(c => `
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon ${c.color}"><i class="fas fa-${c.icon}"></i></div>
                        <div><div class="stat-value">${c.val}</div><div class="stat-label">${c.label}</div></div>
                    </div>
                </div>`).join('');
        });
}

function loadIngresos() {
    const params = new URLSearchParams({
        action: 'ingresos_listar',
        desde:  document.getElementById('f-desde').value,
        hasta:  document.getElementById('f-hasta').value,
        estado: document.getElementById('f-estado').value,
        q:      document.getElementById('f-q').value,
    });
    document.getElementById('tabla-body').innerHTML =
        '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';

    fetch(BASE + 'modules/almacen/api.php?' + params)
        .then(r => r.json())
        .then(data => {
            document.getElementById('result-count').textContent = data.length + ' registro(s)';
            if (!data.length) {
                document.getElementById('tabla-body').innerHTML =
                    '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-inbox" style="font-size:1.3rem"></i><br><br>No se encontraron ingresos</td></tr>';
                return;
            }
            document.getElementById('tabla-body').innerHTML = data.map(i => {
                const esCls = i.estado === 'completado' ? 'badge-success' : 'badge-danger';
                const dt    = new Date(i.created_at);
                const fecha = dt.toLocaleDateString('es-PE') + ' ' + dt.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
                return `<tr style="cursor:pointer" onclick="verDetalle(${i.id},'${i.numero_ingreso}','${i.estado}')">
                    <td><span style="font-weight:700;color:var(--primary)">${i.numero_ingreso}</span></td>
                    <td style="font-size:.82rem;color:var(--text-muted)">${fecha}</td>
                    <td style="font-size:.85rem">${i.origen}</td>
                    <td style="font-size:.85rem;color:var(--text-muted)">${SUCURSAL_NOMBRE}</td>
                    <td style="font-size:.82rem;color:var(--text-muted)">${i.motivo || '—'}</td>
                    <td><span class="badge ${esCls}">${i.estado}</span></td>
                </tr>`;
            }).join('');
        })
        .catch(() => showToast('Error al cargar ingresos', 'error'));
}

function verDetalle(id, numero, estado) {
    currentIngresoId = id;
    document.getElementById('detalle-titulo').innerHTML =
        '<i class="fas fa-truck-loading" style="color:var(--primary);margin-right:8px"></i>Detalle: ' + numero;
    const btnAnular = document.getElementById('btn-anular');
    btnAnular.style.display = estado === 'completado' ? 'inline-flex' : 'none';
    btnAnular.setAttribute('onclick', 'anularIngreso()');
    btnAnular.innerHTML = '<i class="fas fa-ban"></i> Anular Ingreso';
    document.getElementById('detalle-body').innerHTML =
        '<div style="text-align:center;padding:30px"><i class="fas fa-spinner fa-spin" style="font-size:1.5rem;color:var(--text-light)"></i></div>';
    openModal('modal-detalle');

    fetch(BASE + `modules/almacen/api.php?action=ingreso_detalle&id=${id}`)
        .then(r => r.json())
        .then(items => {
            if (!items.length) {
                document.getElementById('detalle-body').innerHTML =
                    '<p style="text-align:center;padding:20px;color:var(--text-muted)">Sin detalles disponibles</p>';
                return;
            }
            const total = items.reduce((s, i) => s + parseFloat(i.subtotal), 0);
            document.getElementById('detalle-body').innerHTML = `
                <div class="table-wrap">
                    <table>
                        <thead><tr>
                            <th>Código</th><th>Producto</th>
                            <th class="text-right">Cant.</th>
                            <th class="text-right">P. Compra</th>
                            <th class="text-right">Subtotal</th>
                        </tr></thead>
                        <tbody>${items.map(i => `
                            <tr>
                                <td style="font-size:.8rem;color:var(--text-muted)">${i.codigo}</td>
                                <td style="font-weight:500">${i.producto_nombre}</td>
                                <td class="text-right">${i.cantidad}</td>
                                <td class="text-right">S/ ${parseFloat(i.precio_unitario).toFixed(2)}</td>
                                <td class="text-right"><strong>S/ ${parseFloat(i.subtotal).toFixed(2)}</strong></td>
                            </tr>`).join('')}
                        </tbody>
                        <tfoot><tr style="background:var(--surface-2)">
                            <td colspan="4" style="padding:10px 14px;font-weight:700;text-align:right">TOTAL</td>
                            <td style="padding:10px 14px;font-weight:700;text-align:right;color:var(--success);font-size:1rem">
                                S/ ${total.toFixed(2)}
                            </td>
                        </tr></tfoot>
                    </table>
                </div>`;
        });
}

function anularIngreso() {
    if (!currentIngresoId || !confirm('¿Anular este ingreso? El stock de todos los productos será revertido.')) return;
    fetch(BASE + 'modules/almacen/api.php?action=anular_ingreso', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: currentIngresoId }),
    })
    .then(r => r.json())
    .then(d => {
        if (d.error) { showToast(d.message, 'error'); return; }
        showToast('Ingreso anulado correctamente', 'success');
        closeModal('modal-detalle');
        loadIngresos();
        loadStats();
    })
    .catch(() => showToast('Error al anular', 'error'));
}

function loadProveedoresSelect() {
    fetch(BASE + 'modules/almacen/api.php?action=proveedores_listar&activos=1&q=')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('n-proveedor');
            data.forEach(p => {
                sel.innerHTML += `<option value="${p.id}">${p.nombre_comercial || p.razon_social}</option>`;
            });
        });
}

function openNuevoIngreso() {
    lines = [];
    renderLineas();
    document.getElementById('n-proveedor').value = '';
    document.getElementById('n-factura').value   = '';
    document.getElementById('n-fecha').value     = '<?= date('Y-m-d') ?>';
    document.getElementById('n-obs').value       = '';
    document.getElementById('n-producto-search').value = '';
    document.getElementById('n-producto-dropdown').style.display = 'none';
    calcularTotales();
    openModal('modal-nuevo');
    setTimeout(() => document.getElementById('n-producto-search').focus(), 100);
}

function setupProductoSearch() {
    let timer;
    const input    = document.getElementById('n-producto-search');
    const dropdown = document.getElementById('n-producto-dropdown');

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (!q) { dropdown.style.display = 'none'; return; }
        timer = setTimeout(() => buscarProducto(q), 200);
    });

    input.addEventListener('keydown', e => {
        if (e.key === 'Escape') { dropdown.style.display = 'none'; input.value = ''; }
    });

    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

function buscarProducto(q) {
    const dropdown = document.getElementById('n-producto-dropdown');
    fetch(BASE + `modules/almacen/api.php?action=buscar_producto&q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                dropdown.innerHTML = '<div style="padding:12px 16px;color:var(--text-muted);font-size:.85rem">Sin resultados</div>';
                dropdown.style.display = 'block';
                return;
            }
            dropdown.innerHTML = data.map(p => `
                <div onclick='seleccionarProducto(${JSON.stringify(p)})'
                    style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border);
                           display:flex;justify-content:space-between;align-items:center;font-size:.85rem"
                    onmouseover="this.style.background='var(--primary-light)'"
                    onmouseout="this.style.background=''">
                    <div>
                        <span style="font-weight:600">${p.nombre}</span>
                        <span style="color:var(--text-muted);margin-left:8px;font-size:.78rem">${p.codigo}</span>
                    </div>
                    <div style="text-align:right;white-space:nowrap">
                        <div style="color:var(--text-muted);font-size:.78rem">Stock: ${p.stock}</div>
                        <div style="color:var(--primary);font-weight:600">S/ ${parseFloat(p.precio_compra).toFixed(2)}</div>
                    </div>
                </div>`).join('');
            dropdown.style.display = 'block';
        });
}

function seleccionarProducto(p) {
    document.getElementById('n-producto-dropdown').style.display = 'none';
    document.getElementById('n-producto-search').value = '';
    addLinea(p);
    document.getElementById('n-producto-search').focus();
}

// Deja solo digitos y un punto decimal, y refleja el valor limpio en el
// input -- se usa en los inputs de Cantidad (type="text" en vez de
// "number" porque Chrome no permite mover el cursor programaticamente
// en inputs type="number").
function sanitizarCantidadInput(el) {
    let v = el.value.replace(/[^0-9.]/g, '');
    const partes = v.split('.');
    if (partes.length > 2) v = partes[0] + '.' + partes.slice(1).join('');
    el.value = v;
    return v;
}

function cursorAlFinal(el) {
    // El navegador posiciona el cursor segun el clic DESPUES de disparar
    // "focus" -- si no se difiere, esa posicion pisa la nuestra. setTimeout(0)
    // la corre despues de que el navegador termine su propio posicionamiento.
    setTimeout(() => {
        const pos = el.value.length;
        el.setSelectionRange(pos, pos);
    }, 0);
}

function addLinea(p) {
    const pid = parseInt(p.id);
    const existing = lines.findIndex(l => l.producto_id === pid);
    if (existing >= 0) {
        lines[existing].cantidad++;
    } else {
        lines.unshift({ producto_id: pid, codigo: p.codigo, nombre: p.nombre, cantidad: 1, precio_unitario: parseFloat(p.precio_compra) || 0, precio_venta: parseFloat(p.precio_venta) || 0 });
    }
    renderLineas();
    calcularTotales();
}

function updateLinea(idx, field, value) {
    if (field === 'cantidad') lines[idx].cantidad = Math.max(0.01, parseFloat(value) || 1);

    const sub = lines[idx].cantidad * lines[idx].precio_unitario;
    const subtotalCell = document.querySelector(`.linea-subtotal[data-idx="${idx}"]`);
    if (subtotalCell) subtotalCell.textContent = 'S/ ' + sub.toFixed(2);

    calcularTotales();
}

function removeLinea(idx) { lines.splice(idx, 1); renderLineas(); calcularTotales(); }

function renderLineas() {
    const tbody = document.getElementById('n-lineas-body');
    const empty = document.getElementById('n-lineas-empty');
    tbody.querySelectorAll('.linea-row').forEach(r => r.remove());
    if (!lines.length) { empty.style.display = ''; return; }
    empty.style.display = 'none';
    lines.forEach((l, idx) => {
        const sub = l.cantidad * l.precio_unitario;
        const tr  = document.createElement('tr');
        tr.className = 'linea-row';
        tr.innerHTML = `
            <td style="font-size:.8rem;color:var(--text-muted);font-family:monospace">${l.codigo}</td>
            <td style="font-weight:500;font-size:.88rem">${l.nombre}</td>
            <td class="text-right">
                <input type="text" inputmode="decimal" value="${l.cantidad}"
                    style="width:80px;text-align:center;padding:5px 10px;border:1px solid var(--border);
                           border-radius:var(--radius-sm);font-size:.88rem;background:var(--surface)"
                    oninput="updateLinea(${idx},'cantidad',sanitizarCantidadInput(this))"
                    onfocus="cursorAlFinal(this)">
            </td>
            <td class="text-right" style="font-size:.88rem;color:var(--text-muted)">
                S/ ${l.precio_unitario.toFixed(2)}
            </td>
            <td class="text-right" style="font-size:.88rem;color:var(--text-muted)">
                S/ ${l.precio_venta.toFixed(2)}
            </td>
            <td class="text-right linea-subtotal" data-idx="${idx}" style="font-weight:600">S/ ${sub.toFixed(2)}</td>
            <td>
                <button onclick="removeLinea(${idx})"
                    style="background:none;border:none;color:var(--danger);cursor:pointer;padding:4px 6px;font-size:.9rem">
                    <i class="fas fa-trash"></i>
                </button>
            </td>`;
        tbody.insertBefore(tr, empty);
    });
}

function calcularTotales() {
    const subtotal = lines.reduce((s, l) => s + l.cantidad * l.precio_unitario, 0);
    document.getElementById('n-subtotal').textContent = 'S/ ' + subtotal.toFixed(2);
    document.getElementById('n-total').textContent    = 'S/ ' + subtotal.toFixed(2);
}

function saveIngreso() {
    if (!lines.length) { showToast('Agrega al menos un producto', 'error'); return; }
    for (const l of lines) {
        if (l.cantidad <= 0)        { showToast('La cantidad debe ser mayor a 0',         'error'); return; }
        if (l.precio_unitario <= 0) { showToast('El precio de compra debe ser mayor a 0', 'error'); return; }
    }
    const payload = {
        proveedor_id:   document.getElementById('n-proveedor').value || null,
        numero_factura: document.getElementById('n-factura').value,
        fecha_factura:  document.getElementById('n-fecha').value,
        observaciones:  document.getElementById('n-obs').value,
        items: lines.map(l => ({ producto_id: l.producto_id, cantidad: l.cantidad, precio_unitario: l.precio_unitario, precio_venta: l.precio_venta })),
    };
    const btn = document.getElementById('btn-guardar-ingreso');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
    fetch(BASE + 'modules/almacen/api.php?action=registrar_ingreso', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Registrar Ingreso';
        if (d.error) { showToast(d.message, 'error'); return; }
        showToast('Ingreso ' + d.numero_ingreso + ' registrado correctamente', 'success');
        closeModal('modal-nuevo');
        loadIngresos();
        loadStats();
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Registrar Ingreso';
        showToast('Error al registrar', 'error');
    });
}

function setupBarcodeScanner() {
    document.addEventListener('barcodescan', function(e) {
        const code = e.detail.code.trim();
        const ingresoOpen = document.getElementById('modal-nuevo').classList.contains('open');
        const salidaOpen  = document.getElementById('modal-nueva-salida').classList.contains('open');
        if (!ingresoOpen && !salidaOpen) return;
        fetch(BASE + `modules/almacen/api.php?action=buscar_producto&q=${encodeURIComponent(code)}`)
            .then(r => r.json())
            .then(data => {
                const exact = data.find(p => p.codigo.toUpperCase() === code.toUpperCase());
                const prod  = exact || data[0];
                if (prod) {
                    if (ingresoOpen) addLinea(prod);
                    else addSalidaLinea(prod);
                    showToast('<i class="fas fa-barcode"></i> ' + prod.nombre, 'success');
                } else {
                    showToast('Código <strong>' + code + '</strong> no encontrado en inventario', 'error');
                }
            });
    });
}

// ========================================================
// TRASLADOS
// ========================================================
function esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function trasPost(action,body){return fetch(BASE+'modules/traslados/api.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(r=>r.json());}
function puedeRecibir(row){return row.estado==='enviado' && parseInt(row.sucursal_destino_id)===CURRENT_SUCURSAL_ID;}
function puedeAnular(row){return row.estado==='enviado' && parseInt(row.sucursal_origen_id)===CURRENT_SUCURSAL_ID;}
function badgeTraslado(e){return `b-${e}`;}

function filtrarEstado(estado, btn){
    trasladoEstadoFiltro = estado;
    document.querySelectorAll('#filtrosTraslado .trs-filter-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    loadTraslados();
}

function loadSucursalesTraslado(){
    return fetch(BASE+'modules/traslados/api.php?action=sucursales').then(r=>r.json()).then(data => {
        sucursalesTraslado = Array.isArray(data) ? data : [];
        const sel = document.getElementById('trs-destino');
        sel.innerHTML = '<option value="">Selecciona destino</option>' + sucursalesTraslado
            .filter(s => (s.activo===true || s.activo==='t') && parseInt(s.id)!==CURRENT_SUCURSAL_ID)
            .map(s => `<option value="${s.id}">${esc(s.nombre)}</option>`).join('');
    });
}

function loadTraslados(){
    const q = document.getElementById('trs-q').value.trim();
    const tbody = document.getElementById('tbodyTraslados');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    fetch(BASE+'modules/traslados/api.php?action=listar&estado='+encodeURIComponent(trasladoEstadoFiltro)+'&q='+encodeURIComponent(q))
        .then(r=>r.json())
        .then(data => {
            if (data.error){ tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:30px;color:#dc2626">${esc(data.message)}</td></tr>`; return; }
            if (!data.length){ tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)">No hay traslados registrados</td></tr>'; return; }
            tbody.innerHTML = data.map(r => {
                const dt = new Date(r.created_at);
                const pendienteRecibir = puedeRecibir(r);
                const acciones = [`<button class="btn btn-ghost btn-sm" onclick="verDetalleTraslado(${parseInt(r.id)})" title="Ver detalle"><i class="fas fa-eye"></i></button>`];
                if (pendienteRecibir){
                    acciones.unshift(`<button class="btn btn-primary btn-sm" onclick="recibirTraslado(${parseInt(r.id)})"><i class="fas fa-check"></i> Recibir</button>`);
                } else if (puedeAnular(r)){
                    acciones.unshift(`<button class="btn btn-outline btn-sm" onclick="anularTraslado(${parseInt(r.id)})">Anular</button>`);
                }
                return `<tr class="${pendienteRecibir ? 'row-pending-receive' : ''}">
                    <td><strong>${esc(r.numero_traslado)}</strong></td>
                    <td>${esc(r.sucursal_origen)}</td>
                    <td>${esc(r.sucursal_destino)}${pendienteRecibir ? '<div class="pending-hint"><i class="fas fa-hand-holding-box"></i> Pendiente de recibir</div>' : ''}</td>
                    <td>${parseInt(r.total_items||0)}</td>
                    <td>${parseFloat(r.total_unidades||0)}</td>
                    <td><span class="badge ${badgeTraslado(r.estado)}">${esc(r.estado)}</span></td>
                    <td>${dt.toLocaleDateString('es-PE')}<br><small style="color:var(--text-muted)">${dt.toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit'})}</small></td>
                    <td><div class="inline-actions">${acciones.join('')}</div></td>
                </tr>`;
            }).join('');
        })
        .catch(() => showToast('Error al cargar traslados', 'error'));
}

function abrirNuevoTraslado(){
    trasladoItems = [];
    renderItemsTraslado();
    document.getElementById('trs-destino').value = '';
    document.getElementById('trs-observaciones').value = '';
    document.getElementById('trs-producto-buscar').value = '';
    document.getElementById('trs-resultados').style.display = 'none';
    openModal('modal-traslado');
}

function renderItemsTraslado(){
    const tbody = document.getElementById('tbodyItemsTraslado');
    if (!trasladoItems.length){
        tbody.innerHTML = '<tr id="emptyItemsTraslado"><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Agrega productos para trasladar</td></tr>';
        return;
    }
    tbody.innerHTML = trasladoItems.map((item, i) => `<tr>
        <td>${esc(item.codigo)}</td>
        <td>${esc(item.nombre)}</td>
        <td>${parseFloat(item.stock||0)}</td>
        <td><input type="number" min="0.01" step="0.01" max="${parseFloat(item.stock||0)}" value="${parseFloat(item.cantidad||1)}" onchange="trasladoItems[${i}].cantidad=parseFloat(this.value||1)"></td>
        <td><input type="number" min="0" step="0.01" value="${parseFloat(item.costo_unitario||0).toFixed(2)}" onchange="trasladoItems[${i}].costo_unitario=parseFloat(this.value||0)"></td>
        <td><button class="btn-del-row" onclick="eliminarItemTraslado(${i})"><i class="fas fa-trash"></i></button></td>
    </tr>`).join('');
}

function eliminarItemTraslado(idx){ trasladoItems.splice(idx, 1); renderItemsTraslado(); }

function buscarProductosTraslado(q){
    const box = document.getElementById('trs-resultados');
    if (!q){ box.style.display='none'; box.innerHTML=''; return; }
    fetch(BASE+'modules/traslados/api.php?action=buscar_producto&q='+encodeURIComponent(q))
        .then(r=>r.json())
        .then(data => {
            if (data.error){ box.innerHTML=`<div class="trs-result-item">${esc(data.message||'Error al buscar')}</div>`; box.style.display='block'; return; }
            if (!data.length){ box.innerHTML='<div class="trs-result-item">Sin resultados</div>'; box.style.display='block'; return; }
            box.innerHTML = data.map(p=>`<div class="trs-result-item" onclick='agregarProductoTraslado(${JSON.stringify(p).replace(/'/g,"&#39;")})'><strong>${esc(p.codigo)}</strong> — ${esc(p.nombre)}<br><small>Stock: ${parseFloat(p.stock||0)} · Costo: S/ ${parseFloat(p.precio_compra||0).toFixed(2)}</small></div>`).join('');
            box.style.display = 'block';
        });
}

function agregarProductoTraslado(producto){
    const existing = trasladoItems.findIndex(i => i.codigo === producto.codigo);
    if (existing >= 0){
        const nextQty = parseFloat(trasladoItems[existing].cantidad||1) + 1;
        if (nextQty > parseFloat(producto.stock||0)){ showToast('No hay más stock disponible en origen','error'); return; }
        trasladoItems[existing].cantidad = nextQty;
    } else {
        if (parseFloat(producto.stock||0) <= 0){ showToast('El producto no tiene stock disponible','error'); return; }
        trasladoItems.push({ producto_id:producto.id, codigo:producto.codigo, nombre:producto.nombre, stock:parseFloat(producto.stock||0), cantidad:1, costo_unitario:parseFloat(producto.precio_compra||0) });
    }
    document.getElementById('trs-producto-buscar').value = '';
    document.getElementById('trs-resultados').style.display = 'none';
    renderItemsTraslado();
}

async function guardarTraslado(){
    const destino = document.getElementById('trs-destino').value;
    const obs     = document.getElementById('trs-observaciones').value.trim();
    if (!destino){ showToast('Selecciona la sucursal destino','error'); return; }
    if (!trasladoItems.length){ showToast('Agrega al menos un producto','error'); return; }
    for (const item of trasladoItems){
        if (!item.cantidad || item.cantidad <= 0){ showToast('Todas las cantidades deben ser válidas','error'); return; }
        if (item.cantidad > item.stock){ showToast(`La cantidad de ${item.nombre} supera el stock disponible`,'error'); return; }
    }
    const res = await trasPost('crear', { sucursal_destino_id: parseInt(destino), observaciones: obs, items: trasladoItems });
    if (res.error){ showToast(res.message,'error'); return; }
    showToast(res.message,'success');
    closeModal('modal-traslado');
    loadTraslados();
}

async function verDetalleTraslado(id){
    openModal('modal-detalle-traslado');
    document.getElementById('detalleTrasladoBody').innerHTML = '<div style="text-align:center;padding:32px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i></div>';
    document.getElementById('detalleTrasladoActions').innerHTML = '';
    const data = await fetch(BASE+'modules/traslados/api.php?action=detalle&id='+id).then(r=>r.json());
    if (data.error){ document.getElementById('detalleTrasladoBody').innerHTML = `<div style="color:#dc2626">${esc(data.message)}</div>`; return; }
    document.getElementById('detalleTrasladoTitulo').textContent = `Traslado ${data.numero_traslado}`;
    document.getElementById('detalleTrasladoBody').innerHTML = `
        <div class="trs-detail-grid">
            <div><strong>Origen:</strong><br>${esc(data.sucursal_origen)}</div>
            <div><strong>Destino:</strong><br>${esc(data.sucursal_destino)}</div>
            <div><strong>Estado:</strong><br><span class="badge ${badgeTraslado(data.estado)}">${esc(data.estado)}</span></div>
            <div><strong>Solicitado:</strong><br>${data.usuario_solicita_nombre ? esc(data.usuario_solicita_nombre) : '—'}</div>
        </div>
        <div style="margin-bottom:12px"><strong>Observaciones:</strong><br>${esc(data.observaciones||'Sin observaciones')}</div>
        <table class="items-table">
            <thead><tr><th>Código</th><th>Producto</th><th>Cantidad</th><th>Stock origen</th><th>Stock destino</th></tr></thead>
            <tbody>${(data.items||[]).map(item=>`<tr><td>${esc(item.producto_codigo)}</td><td>${esc(item.producto_nombre)}</td><td>${parseFloat(item.cantidad)}</td><td>${parseFloat(item.stock_origen_snapshot||0)}</td><td>${parseFloat(item.stock_destino_snapshot||0)}</td></tr>`).join('')}</tbody>
        </table>`;
    const actions = [];
    if (data.estado==='borrador' && parseInt(data.sucursal_origen_id)===CURRENT_SUCURSAL_ID){
        actions.push(`<button class="btn btn-primary" onclick="enviarTraslado(${parseInt(data.id)})"><i class="fas fa-paper-plane"></i> Enviar</button>`);
        actions.push(`<button class="btn btn-outline" onclick="anularTraslado(${parseInt(data.id)})">Anular</button>`);
    }
    if (data.estado==='enviado' && parseInt(data.sucursal_destino_id)===CURRENT_SUCURSAL_ID){
        actions.push(`<button class="btn btn-primary" onclick="recibirTraslado(${parseInt(data.id)})"><i class="fas fa-check"></i> Recibir</button>`);
    }
    if (data.estado==='enviado' && parseInt(data.sucursal_origen_id)===CURRENT_SUCURSAL_ID){
        actions.push(`<button class="btn btn-outline" onclick="anularTraslado(${parseInt(data.id)})">Anular</button>`);
    }
    actions.push(`<button class="btn btn-ghost" onclick="closeModal('modal-detalle-traslado')">Cerrar</button>`);
    document.getElementById('detalleTrasladoActions').innerHTML = actions.join('');
}

async function enviarTraslado(id){
    if (!confirm('¿Enviar este traslado? Se descontará el stock de la sucursal origen.')) return;
    const res = await trasPost('enviar', {id});
    if (res.error){ showToast(res.message,'error'); return; }
    showToast(res.message,'success');
    closeModal('modal-detalle-traslado');
    loadTraslados();
}

async function recibirTraslado(id){
    if (!confirm('¿Recibir este traslado? Se sumará el stock en la sucursal destino.')) return;
    const res = await trasPost('recibir', {id});
    if (res.error){ showToast(res.message,'error'); return; }
    showToast(res.message,'success');
    closeModal('modal-detalle-traslado');
    loadTraslados();
}

async function anularTraslado(id){
    if (!confirm('¿Anular este traslado?')) return;
    const res = await trasPost('anular', {id});
    if (res.error){ showToast(res.message,'error'); return; }
    showToast(res.message,'success');
    closeModal('modal-detalle-traslado');
    loadTraslados();
}

// ========================================================
// HELPERS
// ========================================================
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function showToast(msg, type = 'info') {
    const icons = { success: 'check-circle', error: 'exclamation-circle', info: 'info-circle' };
    const t = document.createElement('div');
    t.className = `app-toast ${type}`;
    t.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i> ${msg}`;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => t.remove(), 3500);
}
</script>

<?php include '../../includes/footer.php'; ?>
