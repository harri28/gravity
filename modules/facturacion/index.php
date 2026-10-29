<?php
// ============================================================
// ARCHIVO: farmacia/modules/facturacion/index.php
// MÓDULO:  Facturación (tabs: Reporte de Ventas | Notas de crédito | Rentabilidad)
// ============================================================

require_once '../../config/database.php';

$base_path      = '../../';
$required_roles = ['admin', 'gerente'];
$current_module = 'facturacion';
$current_page   = 'facturacion';
$page_title     = 'Facturación — Gravity';
$breadcrumb     = '<strong>Facturación</strong>';

$_tenant_info = ['ruc' => '', 'telefono' => '', 'direccion' => ''];
if (sesionTenantId()) {
    $db = getDB();
    $t  = $db->prepare("SELECT ruc, telefono, direccion FROM public.tenants WHERE id = :id");
    $t->execute([':id' => sesionTenantId()]);
    $_tenant_info = $t->fetch() ?: $_tenant_info;
}

include '../../includes/header.php';
?>

<link rel="stylesheet" href="<?= $base_path ?>assets/vendor/datatables/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="<?= $base_path ?>assets/vendor/select2/select2.min.css">

<style>
/* ---- Tabs ---- */
.fact-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 20px;
    background: var(--surface-2);
    border-radius: var(--radius);
    padding: 5px;
    width: fit-content;
}
.fact-tab {
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
.fact-tab:hover { background: var(--surface); color: var(--text); }
.fact-tab.active { background: var(--primary); color: #fff; }
.fact-tab.active:hover { background: var(--primary-dark, var(--primary)); }

/* ---- Select2 ---- */
.select2-container { width: 100% !important; }
.select2-container--default .select2-selection--single {
    height: 38px;
    border: 1px solid var(--border);
    border-radius: 10px;
    display: flex;
    align-items: center;
    background: var(--surface);
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 36px;
    padding-left: 12px;
    padding-right: 28px;
    font-size: .84rem;
    color: var(--text-primary);
}
.select2-container--open { z-index: 1065; }
.select2-dropdown {
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 16px 40px rgba(15,23,42,.14);
}
.select2-search--dropdown { padding: 10px; }
.select2-search__field {
    border: 1px solid var(--border) !important;
    border-radius: 8px !important;
    padding: 8px 10px !important;
    font-size: .84rem;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background: var(--primary);
    color: #fff;
}

/* ---- Reporte / Notas shared table styles ---- */
.fact-dt-table thead th { white-space: nowrap; }
.fact-dt-table tbody td { vertical-align: middle; }
.fact-dt-table { table-layout: fixed; }
/* Centrar CDR / Estado / Acciones (SUNAT ya se centra vía .sunat-cell) --
   posiciones de columna difieren entre las dos tablas, se apunta cada una
   por su ID. */
#rpt-ventas-table th:nth-child(6), #rpt-ventas-table td:nth-child(6),
#rpt-ventas-table th:nth-child(8), #rpt-ventas-table td:nth-child(8),
#rpt-ventas-table th:nth-child(9), #rpt-ventas-table td:nth-child(9) {
    text-align: center;
}
#notas-table th:nth-child(8), #notas-table td:nth-child(8),
#notas-table th:nth-child(10), #notas-table td:nth-child(10),
#notas-table th:nth-child(11), #notas-table td:nth-child(11) {
    text-align: center;
}
.table-wrap { overflow-x: auto; overflow-y: hidden; width: 100%; -webkit-overflow-scrolling: touch; }
.table-wrap table.dataTable { width: 100% !important; border-collapse: separate !important; }
.dataTables_wrapper { padding: 0 0 12px; width: 100%; overflow: hidden; box-sizing: border-box; }
.dt-toolbar, .dt-footer {
    display: flex; gap: 12px; align-items: center; justify-content: space-between;
    flex-wrap: wrap; padding: 14px 16px 0; width: 100%; box-sizing: border-box;
}
.dt-toolbar { border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 8px; }
.dt-footer  { border-top: 1px solid var(--border); margin-top: 8px; padding-top: 12px; padding-bottom: 10px; }
.dataTables_filter input, .dataTables_length select {
    border: 1px solid var(--border); border-radius: 10px; background: #fff;
    color: var(--text); min-height: 36px; padding: 0 12px; font: inherit;
}
.dataTables_filter label, .dataTables_length label, .dataTables_info { color: var(--text-muted); font-size: .8rem; }
.dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate { margin: 0 !important; }
.dataTables_filter { margin-left: auto; display: flex; align-items: center; justify-content: flex-end; flex: 1 1 280px; min-width: 220px; }
.dataTables_paginate { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
.dataTables_wrapper .dataTables_paginate ul.pagination {
    list-style: none !important; margin: 0 !important; padding: 0 !important;
    display: flex !important; gap: 6px; align-items: center;
}
.dataTables_wrapper .dataTables_paginate ul.pagination li { list-style: none !important; margin: 0 !important; }
.dataTables_wrapper .dataTables_paginate .page-link {
    border: 1px solid var(--border) !important; border-radius: 8px !important;
    background: #fff !important; color: var(--text) !important;
    padding: 5px 10px !important; min-width: 34px; font-size: .8rem !important;
    line-height: 1.2 !important; text-align: center; box-shadow: none !important;
}
.dataTables_wrapper .dataTables_paginate .page-item.active .page-link,
.dataTables_wrapper .dataTables_paginate .page-link:hover {
    background: var(--primary) !important; color: #fff !important; border-color: var(--primary) !important;
}
.dataTables_wrapper .dataTables_paginate .page-item.disabled .page-link {
    opacity: .5; cursor: not-allowed !important; background: #fff !important;
    color: var(--text-muted) !important; border-color: var(--border) !important;
}
.dataTables_wrapper .dataTables_filter input { margin-left: 8px; min-width: 180px; max-width: 260px; }
.dataTables_wrapper .dataTables_length select { margin: 0 6px; min-width: 74px; }
.dataTables_info { padding: 0 16px 12px; }

/* ---- Reporte shared ---- */
.tabla-summary { display: flex; justify-content: flex-end; gap: 12px; padding: 12px 16px 4px; flex-wrap: wrap; }
.summary-pill { min-width: 150px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 10px 14px; display: flex; flex-direction: column; gap: 2px; }
.summary-label { font-size: .72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .03em; }
.summary-pill strong { font-size: 1rem; color: var(--text); }
.date-block, .voucher-block, .client-block { display: flex; flex-direction: column; gap: 2px; }
.date-block strong, .voucher-block strong, .client-name { font-size: .9rem; font-weight: 600; color: var(--text); }
.date-block span, .voucher-block span, .client-doc { font-size: .74rem; color: var(--text-muted); }
.total-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 110px; padding: 8px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 999px; font-weight: 700; color: var(--text); }
.icon-action-btn { width: 34px; height: 34px; border-radius: 999px; border: 1px solid #dbeafe; background: #f8fbff; color: var(--primary); display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: .18s ease; }
.icon-action-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
.actions-inline { display: flex; gap: 8px; justify-content: center; }
.sunat-cell { width: 88px !important; min-width: 88px !important; max-width: 88px !important; white-space: nowrap; text-align: center; padding-left: 8px !important; padding-right: 8px !important; }
.sunat-cell .badge { display: inline-flex; align-items: center; justify-content: center; min-width: 76px; max-width: 76px; padding: 6px 10px; font-size: .72rem !important; line-height: 1; }

/* ---- Notas ---- */
.nota-resumen-box { background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; }
.nota-resumen .label { font-size: .7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; }
.nota-resumen .value { margin-top: 4px; font-size: .9rem; font-weight: 700; color: var(--text-primary); }
.nota-grid { display: grid; grid-template-columns: 1.2fr .8fr; gap: 14px; }
.nota-preview { background: var(--surface-2); border: 1px dashed var(--border); border-radius: 12px; padding: 12px 14px; }
.nota-preview .title { font-size: .72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; }
.nota-preview .main { font-size: .95rem; font-weight: 700; color: var(--text-primary); margin-top: 6px; }
.nota-preview .sub { margin-top: 3px; font-size: .8rem; color: var(--text-muted); }

/* ---- Rentabilidad ---- */
.stat-icon.red    { background: #fef2f2; color: #dc2626; }
.stat-icon.purple { background: #f5f3ff; color: #7c3aed; }
.stat-icon.teal   { background: #f0fdfa; color: #0d9488; }
.sortable { cursor: pointer; user-select: none; }
.sortable:hover { background: var(--surface-2); }

@media (max-width: 900px) {
    .nota-resumen, .nota-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .dt-toolbar, .dt-footer { padding: 12px 12px 0; }
    .dataTables_filter { margin-left: 0; width: 100%; }
    .dataTables_filter input { width: 100%; max-width: none; }
    .tabla-summary { justify-content: stretch; padding: 12px; }
    .summary-pill { width: 100%; min-width: 0; }
    .fact-tabs { flex-wrap: wrap; width: 100%; }
}
</style>

<div class="page-header">
    <div>
        <div class="page-title" id="fact-page-title">
            <i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px" id="fact-page-icon"></i><span id="fact-page-title-text">Reporte de Ventas</span>
        </div>
        <div class="page-subtitle" id="fact-page-subtitle">Consulta, filtra y exporta las ventas del período</div>
    </div>
    <div class="page-actions" id="fact-page-actions">
        <button class="btn btn-success" onclick="rptExportar()">
            <i class="fas fa-file-excel"></i> Descargar Excel
        </button>
    </div>
</div>

<!-- Tabs -->
<div class="fact-tabs">
    <button class="fact-tab active" id="fact-tab-btn-reporte" onclick="switchTab('reporte')">
        <i class="fas fa-chart-bar"></i> Reporte de Ventas
    </button>
    <button class="fact-tab" id="fact-tab-btn-notas" onclick="switchTab('notas')">
        <i class="fas fa-receipt"></i> Notas de crédito
    </button>
    <button class="fact-tab" id="fact-tab-btn-rentabilidad" onclick="switchTab('rentabilidad')">
        <i class="fas fa-chart-pie"></i> Rentabilidad
    </button>
    <?php if (isAdmin()): ?>
    <button class="fact-tab" id="fact-tab-btn-reenvios" onclick="switchTab('reenvios')">
        <i class="fas fa-arrows-rotate"></i> Reenvíos SUNAT
        <span class="badge" id="reenvios-badge" style="margin-left:6px;background:#f59e0b;color:#fff;display:none">0</span>
    </button>
    <?php endif; ?>
</div>


<!-- ============================================================
     PANE: REPORTE DE VENTAS
     ============================================================ -->
<div id="pane-reporte" class="fact-pane">

    <div class="card" style="margin-bottom:20px">
        <div style="padding:10px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">Desde</label>
                <input type="date" id="rpt-desde" class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">Hasta</label>
                <input type="date" id="rpt-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <button class="btn btn-primary" onclick="rptBuscar()" style="margin-bottom:0">
                <i class="fas fa-search"></i> Buscar
            </button>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:0">
                <span style="font-size:.78rem;color:var(--text-muted)">Rápido:</span>
                <button class="btn btn-ghost btn-sm" onclick="rptSetPeriodo('hoy')">Hoy</button>
                <button class="btn btn-ghost btn-sm" onclick="rptSetPeriodo('ayer')">Ayer</button>
                <button class="btn btn-ghost btn-sm" onclick="rptSetPeriodo('semana')">Esta semana</button>
                <button class="btn btn-ghost btn-sm" onclick="rptSetPeriodo('mes')">Este mes</button>
                <button class="btn btn-ghost btn-sm" onclick="rptSetPeriodo('mes_ant')">Mes anterior</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Detalle de ventas</div>
            <div style="display:flex;align-items:center;gap:12px;margin-left:auto">
                <span style="font-size:.82rem;color:var(--text-muted)" id="rpt-result-count">—</span>
                <button class="btn btn-success btn-sm" onclick="rptExportar()">
                    <i class="fas fa-file-excel"></i> Exportar
                </button>
            </div>
        </div>
        <div class="table-wrap">
            <table id="rpt-ventas-table" class="display fact-dt-table" style="width:100%;min-width:1180px">
                <thead><tr>
                    <th>Fecha</th><th>Cliente</th><th>Comprobante</th>
                    <th class="text-center">Total</th><th>XML</th><th>CDR</th>
                    <th>SUNAT</th><th>Estado</th><th>Acciones</th>
                </tr></thead>
                <tbody id="rpt-tabla-body">
                    <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
                <tfoot id="rpt-tabla-foot"></tfoot>
            </table>
        </div>
    </div>

</div><!-- /pane-reporte -->


<!-- ============================================================
     PANE: NOTAS DE CRÉDITO
     ============================================================ -->
<div id="pane-notas" class="fact-pane" style="display:none">

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-filter" style="color:var(--primary)"></i> Filtros</div>
            <button class="btn btn-ghost btn-sm" onclick="ncResetFiltros()" style="margin-left:auto">
                <i class="fas fa-undo"></i> Limpiar
            </button>
        </div>
        <div style="padding:16px 20px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin:0;flex:1;min-width:150px">
                <label class="form-label">Desde</label>
                <input type="date" id="f-nota-desde" class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:150px">
                <label class="form-label">Hasta</label>
                <input type="date" id="f-nota-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:150px">
                <label class="form-label">Estado SUNAT</label>
                <select class="form-control" id="f-nota-estado">
                    <option value="">Todos</option>
                    <option value="Aceptado">Aceptado</option>
                    <option value="Observado">Observado</option>
                    <option value="Pendiente">Pendiente</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:2;min-width:220px">
                <label class="form-label">Buscar</label>
                <div class="input-group">
                    <span class="input-group-icon"><i class="fas fa-search"></i></span>
                    <input type="text" id="f-nota-q" class="form-control" placeholder="Serie, cliente, referencia...">
                </div>
            </div>
            <button class="btn btn-primary" onclick="ncCargarNotas()">
                <i class="fas fa-search"></i> Buscar
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4"><div class="nota-resumen-box box"><div class="label">Notas emitidas</div><div class="value" id="nota-total-emitidas">-</div></div></div>
        <div class="col-6 col-md-4"><div class="nota-resumen-box box"><div class="label">Total referenciado</div><div class="value" id="nota-total-monto">S/ 0.00</div></div></div>
        <div class="col-6 col-md-4"><div class="nota-resumen-box box"><div class="label">Envío SUNAT</div><div class="value" id="nota-total-sunat">-</div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Detalle de notas de crédito</div>
            <div style="display:flex;align-items:center;gap:12px;margin-left:auto">
                <span style="font-size:.82rem;color:var(--text-muted)" id="nota-result-count">—</span>
            </div>
        </div>
        <div class="table-wrap">
            <table id="notas-table" class="display fact-dt-table" style="width:100%;min-width:1120px">
                <thead><tr>
                    <th>Fecha</th><th>Documento</th><th>Cliente</th><th>Motivo</th>
                    <th>Referencia</th><th class="text-center">Total</th>
                    <th>XML</th><th>CDR</th><th>SUNAT</th><th>Estado</th><th>Acciones</th>
                </tr></thead>
                <tbody id="notas-body">
                    <tr><td colspan="11" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /pane-notas -->


<!-- ============================================================
     PANE: RENTABILIDAD
     ============================================================ -->
<div id="pane-rentabilidad" class="fact-pane" style="display:none">

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-filter" style="color:var(--primary)"></i> Filtros</div>
            <button class="btn btn-ghost btn-sm" onclick="rentResetFiltros()" style="margin-left:auto">
                <i class="fas fa-undo"></i> Limpiar
            </button>
        </div>
        <div style="padding:16px 20px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin:0;flex:1;min-width:150px">
                <label class="form-label">Desde</label>
                <input type="date" id="rent-desde" class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:150px">
                <label class="form-label">Hasta</label>
                <input type="date" id="rent-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:150px">
                <label class="form-label">Categoría</label>
                <select class="form-control" id="rent-categoria"><option value="0">Todas</option></select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">Vendedor</label>
                <select class="form-control" id="rent-vendedor"><option value="">Todos</option></select>
            </div>
            <button class="btn btn-primary" onclick="rentBuscar()">
                <i class="fas fa-search"></i> Calcular
            </button>
        </div>
        <div style="padding:0 20px 16px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin:0;flex:1;min-width:130px">
                <label class="form-label">Comprobante</label>
                <select class="form-control" id="rent-tipo-comp">
                    <option value="">Todos</option>
                    <option value="ticket">Ticket</option>
                    <option value="boleta">Boleta</option>
                    <option value="factura">Factura</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:130px">
                <label class="form-label">Método de pago</label>
                <select class="form-control" id="rent-tipo-pago">
                    <option value="">Todos</option>
                    <option value="efectivo">Efectivo</option>
                    <option value="yape">Yape</option>
                    <option value="plin">Plin</option>
                    <option value="tarjeta">Tarjeta</option>
                    <option value="transferencia">Transferencia</option>
                    <option value="credito">Crédito</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:130px">
                <label class="form-label">Tipo</label>
                <select class="form-control" id="rent-product-type">
                    <option value="">Productos y servicios</option>
                    <option value="product">Solo productos</option>
                    <option value="service">Solo servicios</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">Laboratorio</label>
                <input type="text" id="rent-laboratorio" class="form-control" placeholder="Contiene...">
            </div>
            <div class="form-group" style="margin:0;width:100px">
                <label class="form-label">Margen mín %</label>
                <input type="number" step="0.1" id="rent-margen-min" class="form-control" placeholder="—">
            </div>
            <div class="form-group" style="margin:0;width:100px">
                <label class="form-label">Margen máx %</label>
                <input type="number" step="0.1" id="rent-margen-max" class="form-control" placeholder="—">
            </div>
        </div>
        <div style="padding:0 20px 16px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin:0;flex:1;min-width:150px">
                <label class="form-label">Ordenar por</label>
                <select class="form-control" id="rent-orden-col" onchange="rentAplicarOrden()">
                    <option value="ganancia">Ganancia</option>
                    <option value="unidades">Unidades vendidas</option>
                    <option value="ingresos">Ingresos</option>
                    <option value="margen_pct">Margen %</option>
                    <option value="roi_pct">ROI %</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:150px">
                <label class="form-label">Mostrar</label>
                <select class="form-control" id="rent-orden-dir" onchange="rentAplicarOrden()">
                    <option value="-1">Los que más (mayor a menor)</option>
                    <option value="1">Los que menos (menor a mayor)</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;width:120px">
                <label class="form-label">Top</label>
                <select class="form-control" id="rent-top-n" onchange="rentAplicarOrden()">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="0">Todos</option>
                </select>
            </div>
            <button class="btn btn-secondary" onclick="rentExportarCSV()">
                <i class="fas fa-file-csv"></i> Exportar CSV
            </button>
        </div>
        <div style="padding:0 20px 16px;display:flex;gap:8px;flex-wrap:wrap">
            <span style="font-size:.78rem;color:var(--text-muted);align-self:center">Período:</span>
            <button class="btn btn-ghost btn-sm" onclick="rentSetPeriodo('hoy')">Hoy</button>
            <button class="btn btn-ghost btn-sm" onclick="rentSetPeriodo('semana')">Esta semana</button>
            <button class="btn btn-ghost btn-sm" onclick="rentSetPeriodo('mes')">Este mes</button>
            <button class="btn btn-ghost btn-sm" onclick="rentSetPeriodo('mes_ant')">Mes anterior</button>
            <button class="btn btn-ghost btn-sm" onclick="rentSetPeriodo('trimestre')">Trimestre</button>
        </div>
    </div>

    <div class="row g-3 mb-4" id="rent-stats-container">
        <?php foreach (range(0,4) as $_): ?>
        <div class="col-6 col-md-4 col-lg">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-spinner fa-spin"></i></div>
                <div><div class="stat-value">—</div><div class="stat-label">Cargando...</div></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-7">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-tags" style="color:var(--primary)"></i> Rentabilidad por categoría</div>
                </div>
                <div id="rent-tabla-categorias">
                    <div style="padding:24px;text-align:center;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-trophy" style="color:#f59e0b"></i> <span id="rent-top-titulo">Top 10 más rentables</span></div>
                </div>
                <div id="rent-tabla-top">
                    <div style="padding:24px;text-align:center;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-chart-area" style="color:var(--primary)"></i> Tendencia de ganancia</div>
            <div style="display:flex;gap:12px;align-items:center;margin-left:auto;font-size:.78rem">
                <span style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:2px;background:#16a34a;display:inline-block"></span>Ganancia</span>
                <span style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:2px;background:#3b82f6;display:inline-block"></span>Ingresos</span>
                <span style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:2px;background:#f59e0b;display:inline-block"></span>Costo</span>
            </div>
        </div>
        <div id="rent-chart-wrap" style="padding:0 16px 16px">
            <div style="padding:24px;text-align:center;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-table" style="color:var(--primary)"></i> Todos los productos</div>
            <div style="display:flex;align-items:center;gap:12px;margin-left:auto">
                <div class="input-group" style="width:220px">
                    <span class="input-group-icon"><i class="fas fa-search"></i></span>
                    <input type="text" id="rent-prod-q" class="form-control" placeholder="Buscar producto..." oninput="rentFiltrar()">
                </div>
                <span style="font-size:.82rem;color:var(--text-muted)" id="rent-prod-count">—</span>
            </div>
        </div>
        <div class="table-wrap">
            <table id="rent-tabla-productos">
                <thead><tr>
                    <th style="width:36px">#</th>
                    <th class="sortable" data-col="producto" onclick="rentSort('producto')">Producto <i class="fas fa-sort" style="font-size:.7rem;color:var(--text-muted)"></i></th>
                    <th class="sortable" data-col="categoria" onclick="rentSort('categoria')">Categoría <i class="fas fa-sort" style="font-size:.7rem;color:var(--text-muted)"></i></th>
                    <th class="sortable text-right" data-col="unidades" onclick="rentSort('unidades')">Uds <i class="fas fa-sort" style="font-size:.7rem;color:var(--text-muted)"></i></th>
                    <th class="sortable text-right" data-col="ingresos" onclick="rentSort('ingresos')">Ingresos <i class="fas fa-sort" style="font-size:.7rem;color:var(--text-muted)"></i></th>
                    <th class="sortable text-right" data-col="costo" onclick="rentSort('costo')">Costo <i class="fas fa-sort" style="font-size:.7rem;color:var(--text-muted)"></i></th>
                    <th class="sortable text-right" data-col="ganancia" onclick="rentSort('ganancia')">Ganancia <i class="fas fa-sort" style="font-size:.7rem;color:var(--text-muted)"></i></th>
                    <th class="sortable text-right" data-col="margen_pct" onclick="rentSort('margen_pct')">Margen % <i class="fas fa-sort" style="font-size:.7rem;color:var(--text-muted)"></i></th>
                    <th class="sortable text-right" data-col="roi_pct" onclick="rentSort('roi_pct')">ROI % <i class="fas fa-sort" style="font-size:.7rem;color:var(--text-muted)"></i></th>
                    <th style="min-width:100px">Indicador</th>
                </tr></thead>
                <tbody id="rent-prod-body">
                    <tr><td colspan="10" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /pane-rentabilidad -->


<?php if (isAdmin()): ?>
<!-- ============================================================
     PANE: REENVIOS SUNAT (solo admin/gerente)
     ============================================================ -->
<div id="pane-reenvios" class="fact-pane" style="display:none">

    <div class="card" style="margin-bottom:20px">
        <div style="padding:16px;display:flex;gap:12px;flex-wrap:wrap">
            <div style="flex:1;min-width:160px;background:var(--surface-2);border:1px solid var(--border);border-radius:12px;padding:12px">
                <div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em">Programados</div>
                <div style="font-size:1.6rem;font-weight:700;color:#f59e0b;margin-top:4px" id="rnv-stat-programados">0</div>
                <div style="font-size:.75rem;color:var(--text-muted)">Esperando próximo intento del worker</div>
            </div>
            <div style="flex:1;min-width:160px;background:var(--surface-2);border:1px solid var(--border);border-radius:12px;padding:12px">
                <div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em">Agotados</div>
                <div style="font-size:1.6rem;font-weight:700;color:#dc2626;margin-top:4px" id="rnv-stat-agotados">0</div>
                <div style="font-size:.75rem;color:var(--text-muted)">20 intentos sin éxito — reenvío manual</div>
            </div>
            <div style="flex:1;min-width:160px;background:var(--surface-2);border:1px solid var(--border);border-radius:12px;padding:12px">
                <div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em">Rechazados</div>
                <div style="font-size:1.6rem;font-weight:700;color:#dc2626;margin-top:4px" id="rnv-stat-rechazados">0</div>
                <div style="font-size:.75rem;color:var(--text-muted)">SUNAT devolvió error permanente</div>
            </div>
            <div style="flex:1;min-width:200px;background:var(--surface-2);border:1px solid var(--border);border-radius:12px;padding:12px">
                <div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em">Próximo intento</div>
                <div style="font-size:1.1rem;font-weight:700;color:var(--text-primary);margin-top:4px" id="rnv-stat-proximo">—</div>
                <div style="font-size:.75rem;color:var(--text-muted)">El worker corre cada 5 min</div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card" style="margin-bottom:12px">
        <div style="padding:12px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin:0;min-width:140px">
                <label class="form-label">Desde</label>
                <input type="date" class="form-control" id="rnv-desde">
            </div>
            <div class="form-group" style="margin:0;min-width:140px">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control" id="rnv-hasta">
            </div>
            <div class="form-group" style="margin:0;min-width:140px">
                <label class="form-label">Tipo</label>
                <select class="form-control" id="rnv-tipo">
                    <option value="">Todos</option>
                    <option value="boleta">Boleta</option>
                    <option value="factura">Factura</option>
                    <option value="nota_credito">Nota de crédito</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;min-width:160px">
                <label class="form-label">Estado</label>
                <select class="form-control" id="rnv-estado">
                    <option value="">Todos</option>
                    <option value="programado">Programado</option>
                    <option value="pendiente">Nunca enviado</option>
                    <option value="agotado">Agotado (20 intentos)</option>
                    <option value="rechazado">Rechazado por SUNAT</option>
                </select>
            </div>
            <div style="margin-left:auto;display:flex;gap:8px">
                <button class="btn btn-primary" onclick="rnvCargar()"><i class="fas fa-magnifying-glass"></i> Buscar</button>
                <button class="btn btn-outline" onclick="rnvLimpiarFiltros()">Limpiar</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:10px">
            <h3 style="margin:0;font-size:1rem">Comprobantes pendientes <span id="rnv-count" style="color:var(--text-muted);font-weight:500">(0)</span></h3>
            <div id="rnv-batch-bar" style="display:none;align-items:center;gap:8px;flex-wrap:wrap">
                <span id="rnv-sel-count" style="font-size:.85rem;color:var(--text-muted)">0 seleccionado(s)</span>
                <button class="btn btn-sm btn-warning" onclick="rnvReintentarSync()" title="Hasta 5 a la vez, con progreso en vivo (puede tardar varios minutos)"><i class="fas fa-bolt"></i> Reintentar ahora</button>
                <button class="btn btn-sm btn-primary" onclick="rnvProgramarWorker()" title="Delega al worker; se procesan en los próximos minutos sin bloquear el navegador"><i class="fas fa-clock"></i> Programar al worker</button>
                <button class="btn btn-sm btn-success" onclick="rnvDescargarZips()" title="Descarga un ZIP maestro con los ZIP firmados listos para subir manualmente al portal SOL de SUNAT"><i class="fas fa-download"></i> Descargar ZIP para SUNAT</button>
            </div>
        </div>
        <div class="table-responsive" style="padding:12px">
            <table id="rnv-table" class="table" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:36px"><input type="checkbox" id="rnv-check-all" onclick="rnvToggleAll(this.checked)"></th>
                        <th>Comprobante</th>
                        <th>Fecha venta</th>
                        <th>Cliente</th>
                        <th class="text-center">Total</th>
                        <th class="text-center">Intentos</th>
                        <th>Próximo intento</th>
                        <th>Estado</th>
                        <th>Último error</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

</div><!-- /pane-reenvios -->

<!-- Modal: progreso de reintento sincronico -->
<div class="modal-overlay" id="modal-rnv-progress">
    <div class="modal" style="max-width:560px;width:min(560px,calc(100vw - 32px))">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-bolt" style="color:#f59e0b;margin-right:8px"></i>Reintentando a SUNAT</h3>
        </div>
        <div class="modal-body" style="padding:20px 24px">
            <div id="rnv-progress-text" style="font-size:.95rem;margin-bottom:12px">Preparando…</div>
            <div style="background:var(--surface-2);border-radius:999px;height:10px;overflow:hidden">
                <div id="rnv-progress-bar" style="height:100%;background:#f59e0b;width:0%;transition:width .2s"></div>
            </div>
            <div id="rnv-progress-log" style="margin-top:14px;max-height:220px;overflow-y:auto;background:var(--surface-2);border-radius:8px;padding:10px;font-family:'Courier New',monospace;font-size:.8rem"></div>
        </div>
        <div class="modal-footer" style="padding:12px 24px;text-align:right">
            <button class="btn btn-outline" id="rnv-progress-close" onclick="rnvCerrarProgress()" disabled>Cerrar</button>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- ============================================================
     MODALES
     ============================================================ -->

<!-- Modal: Nota de crédito desde reporte -->
<div class="modal-overlay" id="modal-nota-credito-reporte">
    <div class="modal" style="max-width:760px;width:min(760px,calc(100vw - 32px))">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-file-circle-plus" style="color:var(--primary);margin-right:8px"></i>Anular con Nota de Crédito</h3>
            <button class="modal-close" onclick="rptCloseNc()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding:20px 24px;max-height:74vh;overflow-y:auto">
            <div style="background:var(--surface-2);border:1px solid var(--border);border-radius:14px;padding:14px 16px;margin-bottom:16px">
                <div style="font-size:.74rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em">Comprobante origen</div>
                <div style="margin-top:6px;font-size:1rem;font-weight:700;color:var(--text-primary)" id="rpt-nc-origen-numero">-</div>
                <div style="margin-top:2px;font-size:.82rem;color:var(--text-muted)" id="rpt-nc-origen-cliente">-</div>
                <div style="margin-top:4px;font-size:.9rem;font-weight:700;color:var(--primary)" id="rpt-nc-origen-total">S/ 0.00</div>
            </div>
            <div class="form-group" style="margin-bottom:14px">
                <label class="form-label">Motivo SUNAT *</label>
                <select id="rpt-nc-motivo" class="form-control"></select>
            </div>
            <div class="form-group" style="margin-bottom:6px">
                <label class="form-label">Descripción / motivo interno *</label>
                <textarea id="rpt-nc-descripcion" class="form-control" rows="4" placeholder="Describe brevemente el motivo de la nota de crédito"></textarea>
            </div>
            <div style="font-size:.8rem;color:var(--text-muted)">La nota de crédito se emitirá vinculada al comprobante seleccionado.</div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="rptCloseNc()">Cancelar</button>
            <button class="btn btn-primary" id="rpt-btn-emitir-nc" onclick="rptEmitirNc()">
                <i class="fas fa-paper-plane"></i> Emitir nota de crédito
            </button>
        </div>
    </div>
</div>

<!-- Modal: Nueva nota de crédito (desde pestaña Notas) -->
<div class="modal-overlay" id="modal-nota-credito">
    <div class="modal" style="max-width:980px;width:min(980px,calc(100vw - 32px))">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-file-circle-plus" style="color:var(--primary);margin-right:8px"></i>Nueva nota de crédito</h3>
            <button class="modal-close" onclick="closeModal('modal-nota-credito')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding:20px 24px;max-height:74vh;overflow-y:auto">
            <div class="nota-grid">
                <div>
                    <div class="form-group" style="margin-bottom:12px">
                        <label class="form-label">Comprobante origen *</label>
                        <select id="nota-origen" style="width:100%"></select>
                        <div style="font-size:.74rem;color:var(--text-muted);margin-top:4px">Busca boletas o facturas ya enviadas a SUNAT.</div>
                    </div>
                    <div class="nota-resumen" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:0">
                        <div class="box"><div class="label">Documento</div><div class="value" id="nota-origen-doc">-</div></div>
                        <div class="box"><div class="label">Cliente</div><div class="value" id="nota-origen-cliente">-</div></div>
                        <div class="box"><div class="label">Total</div><div class="value" id="nota-origen-total">S/ 0.00</div></div>
                    </div>
                </div>
                <div>
                    <div class="form-group" style="margin-bottom:12px">
                        <label class="form-label">Motivo *</label>
                        <select id="nota-motivo" class="form-control"></select>
                    </div>
                    <div class="form-group" style="margin-bottom:12px">
                        <label class="form-label">Descripción *</label>
                        <textarea id="nota-descripcion" class="form-control" rows="5" placeholder="Describe brevemente el motivo de la nota de crédito"></textarea>
                    </div>
                    <div class="nota-preview">
                        <div class="title">Importante</div>
                        <div class="main">La nota de crédito se emitirá por el total del comprobante seleccionado.</div>
                        <div class="sub">Si luego necesitas una devolución parcial, lo afinamos como siguiente mejora.</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-nota-credito')">Cancelar</button>
            <button class="btn btn-primary" id="btn-crear-nota" onclick="ncCrear()">
                <i class="fas fa-paper-plane"></i> Emitir nota de crédito
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Emitir boleta/factura a SUNAT desde una nota de venta -->
<div class="modal-overlay" id="modal-emitir-sunat">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-file-invoice-dollar" style="color:var(--primary);margin-right:8px"></i>Emitir comprobante a SUNAT
            </h3>
            <button class="modal-close" onclick="closeModal('modal-emitir-sunat')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div style="background:var(--surface-2);border-radius:var(--radius);padding:10px 14px;margin-bottom:14px;font-size:.88rem" id="emi-info">—</div>
            <div class="form-group">
                <label class="form-label">Tipo de comprobante</label>
                <select id="emi-tipo" class="form-control" onchange="emiOnTipo()">
                    <option value="boleta">Boleta electrónica</option>
                    <option value="factura">Factura electrónica</option>
                </select>
            </div>
            <div id="emi-nota-factura" style="display:none;font-size:.8rem;color:var(--text-muted);margin:-6px 0 12px">
                La factura requiere que la venta tenga un cliente con <strong>RUC</strong>. Si es "Cliente General", primero asígnale un cliente con RUC o emite boleta.
            </div>
            <div style="display:flex;gap:10px;align-items:flex-start;background:#fff7ed;border:1px solid #fed7aa;border-radius:var(--radius);padding:10px 12px;font-size:.8rem;color:#9a3412">
                <i class="fas fa-triangle-exclamation" style="margin-top:2px"></i>
                <div>Se emite con la <strong>fecha original de la venta</strong>. Emitir comprobantes de días pasados puede ser rechazado por SUNAT fuera de plazo o requerir resumen diario — valídalo con el contador.</div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-emitir-sunat')">Cancelar</button>
            <button class="btn btn-primary" id="emi-btn" onclick="emiConfirmar()">
                <i class="fas fa-paper-plane"></i> Emitir a SUNAT
            </button>
        </div>
    </div>
</div>

<div class="app-toast-container" id="toast-container"></div>

<script src="<?= $base_path ?>assets/vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="<?= $base_path ?>assets/vendor/datatables/jquery.dataTables.min.js"></script>
<script src="<?= $base_path ?>assets/vendor/datatables/dataTables.bootstrap5.min.js"></script>
<script src="<?= $base_path ?>assets/vendor/select2/select2.min.js"></script>
<script>
const BASE = '../../';
const EMPRESA_NOMBRE  = <?= json_encode(sesionTenantNombre()) ?>;
const EMPRESA_RUC     = <?= json_encode($_tenant_info['ruc'] ?? '') ?>;
const EMPRESA_TEL     = <?= json_encode($_tenant_info['telefono'] ?? '') ?>;
const EMPRESA_DIR     = <?= json_encode($_tenant_info['direccion'] ?? '') ?>;
const SUCURSAL_NOMBRE = <?= json_encode(sesionSucursal()) ?>;
const VENDEDOR_NOMBRE = <?= json_encode(sesionNombre()) ?>;

// ================================================================
// SHARED UTILITIES
// ================================================================

function showToast(msg, type = 'info') {
    const icons = { success: 'check-circle', error: 'exclamation-circle', info: 'info-circle' };
    const t = document.createElement('div');
    t.className = `app-toast ${type}`;
    t.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i> ${msg}`;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function actionIconButton(icon, title, href = null, onclick = null) {
    const attrs = href
        ? `href="${href}" target="_blank" rel="noopener"`
        : `href="javascript:void(0)" onclick="${onclick}"`;
    return `<a class="icon-action-btn" ${attrs} title="${title}"><i class="fas fa-${icon}"></i></a>`;
}

function openPrintWindow(title, contentHtml) {
    const w = window.open('', '_blank', 'width=420,height=700');
    if (!w) return;
    // Reglas anti-borroso para impresion en Chrome (ver pos.php:printReceipt):
    // pt en vez de px, print-color-adjust:exact, font-smoothing:none,
    // image-rendering:optimize-contrast. Sin esto Chrome aplica sub-pixel
    // antialiasing que la impresora termica interpreta como glifo lavado.
    w.document.open();
    w.document.write(`<!doctype html><html lang="es"><head><meta charset="utf-8"><title>${esc(title)}</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{
    -webkit-print-color-adjust:exact !important;
    print-color-adjust:exact !important;
    -webkit-font-smoothing:none;
    text-rendering:geometricPrecision;
}
body{font-family:'Arial Narrow',Arial,Helvetica,sans-serif;font-size:9pt;font-weight:500;line-height:1.5;color:#000;width:80mm;margin:0 auto;padding:4mm 3mm}
img{image-rendering:-webkit-optimize-contrast}
@media print{@page{size:80mm auto;margin:4mm 3mm}body{padding:0}}
</style></head>
<body>${contentHtml}<script>setTimeout(()=>{window.print();},250);<\/script></body></html>`);
    w.document.close();
}

function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openModal(id)  { document.getElementById(id).classList.add('open'); }

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(overlay.id); });
});

// ================================================================
// TAB SWITCHING
// ================================================================

const _tabMeta = {
    reporte:      { icon: 'chart-bar',      title: 'Reporte de Ventas',  subtitle: 'Consulta, filtra y exporta las ventas del período' },
    notas:        { icon: 'receipt',        title: 'Notas de crédito',   subtitle: 'Emite y reenvía notas de crédito electrónicas vinculadas a boletas y facturas' },
    rentabilidad: { icon: 'chart-pie',      title: 'Rentabilidad',       subtitle: 'Análisis de márgenes, ganancia bruta y ROI por período' },
    reenvios:     { icon: 'arrows-rotate',  title: 'Reenvíos SUNAT',     subtitle: 'Comprobantes con reintentos automáticos — el worker corre cada 5 minutos' },
};
const _tabActions = {
    reporte:      `<button class="btn btn-success" onclick="rptExportar()"><i class="fas fa-file-excel"></i> Descargar Excel</button>`,
    notas:        `<button class="btn btn-primary" onclick="ncAbrirModal()"><i class="fas fa-plus"></i> Nueva nota de crédito</button>`,
    rentabilidad: `<div style="background:#fefce8;border:1px solid #fde68a;border-radius:var(--radius);padding:6px 12px;font-size:.78rem;color:#92400e;display:flex;align-items:center;gap:6px"><i class="fas fa-info-circle"></i> Costos calculados con el precio de compra actual</div>`,
    reenvios:     `<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:var(--radius);padding:6px 12px;font-size:.78rem;color:#1e40af;display:flex;align-items:center;gap:6px"><i class="fas fa-circle-info"></i> Max 20 intentos por comprobante, cada 5 min</div>`,
};
const _tabLoaded = { reporte: false, notas: false, rentabilidad: false, reenvios: false };

function switchTab(tab) {
    document.querySelectorAll('.fact-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('fact-tab-btn-' + tab).classList.add('active');
    document.querySelectorAll('.fact-pane').forEach(p => p.style.display = 'none');
    document.getElementById('pane-' + tab).style.display = '';

    const m = _tabMeta[tab];
    document.getElementById('fact-page-icon').className = `fas fa-${m.icon}`;
    document.getElementById('fact-page-title-text').textContent = m.title;
    document.getElementById('fact-page-subtitle').textContent = m.subtitle;
    document.getElementById('fact-page-actions').innerHTML = _tabActions[tab];

    if (!_tabLoaded[tab]) {
        _tabLoaded[tab] = true;
        if (tab === 'reporte')      { rptCargarMotivos(); rptBuscar(); }
        if (tab === 'notas')        { Promise.all([ncCargarMotivos(), ncCargarOrigenes()]).then(() => ncCargarNotas()); }
        if (tab === 'rentabilidad') { rentLoadFiltros(); rentBuscar(); }
        if (tab === 'reenvios')     { rnvCargar(); }
    } else {
        if (tab === 'reporte' && rptTable)  rptTable.columns.adjust();
        if (tab === 'notas'   && notasTable) notasTable.columns.adjust();
        if (tab === 'reenvios')             { rnvCargar(); }
    }
}

// ================================================================
// REENVIOS SUNAT (solo admin/gerente)
// ================================================================
let rnvTable = null;
const RNV_MAX_SYNC = 5;

function rnvEstadoBadge(estado) {
    const map = {
        programado: { label:'Programado',    cls:'badge-warning' },
        agotado:    { label:'Agotado',       cls:'badge-danger'  },
        rechazado:  { label:'Rechazado',     cls:'badge-danger'  },
        pendiente:  { label:'Nunca enviado', cls:'badge-warning' },
        aceptado:   { label:'Aceptado',      cls:'badge-success' },
    };
    const b = map[estado] || map.pendiente;
    return `<span class="badge ${b.cls}">${b.label}</span>`;
}

function rnvFormatearFecha(iso) {
    if (!iso) return '—';
    const d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d.getTime())) return esc(String(iso));
    return d.toLocaleDateString('es-PE') + ' ' + d.toLocaleTimeString('es-PE', { hour:'2-digit', minute:'2-digit' });
}

function rnvLimpiarFiltros() {
    document.getElementById('rnv-desde').value = '';
    document.getElementById('rnv-hasta').value = '';
    document.getElementById('rnv-tipo').value = '';
    document.getElementById('rnv-estado').value = '';
    rnvCargar();
}

function rnvBuildQuery(extra = {}) {
    const p = new URLSearchParams();
    const d = document.getElementById('rnv-desde')?.value || '';
    const h = document.getElementById('rnv-hasta')?.value || '';
    const t = document.getElementById('rnv-tipo')?.value || '';
    const e = document.getElementById('rnv-estado')?.value || '';
    if (d) p.append('desde', d);
    if (h) p.append('hasta', h);
    if (t) p.append('tipo', t);
    if (e) p.append('estado', e);
    for (const k in extra) p.append(k, extra[k]);
    return p.toString();
}

function rnvCargar() {
    // Stats
    fetch(BASE + 'modules/facturacion/api.php?action=reenvios_stats')
        .then(r => r.json())
        .then(s => {
            document.getElementById('rnv-stat-programados').textContent = s.programados || 0;
            document.getElementById('rnv-stat-agotados').textContent    = s.agotados || 0;
            document.getElementById('rnv-stat-rechazados').textContent  = s.rechazados || 0;
            document.getElementById('rnv-stat-proximo').textContent     = rnvFormatearFecha(s.proximo_at);
            const totalActivos = (parseInt(s.programados,10)||0);
            const badge = document.getElementById('reenvios-badge');
            if (badge) {
                if (totalActivos > 0) { badge.textContent = totalActivos; badge.style.display = ''; }
                else                  { badge.style.display = 'none'; }
            }
        })
        .catch(() => {});

    // Tabla
    fetch(BASE + 'modules/facturacion/api.php?action=reenvios_pendientes&' + rnvBuildQuery())
        .then(r => r.json())
        .then(resp => {
            // Nuevo formato: { total, limit, rows }. Fallback a array por compat.
            const data  = Array.isArray(resp) ? resp : (resp.rows || []);
            const total = Array.isArray(resp) ? data.length : (resp.total || 0);
            const limit = Array.isArray(resp) ? data.length : (resp.limit || 1000);
            document.getElementById('rnv-count').textContent = `(${total})`;
            const tbody = document.querySelector('#rnv-table tbody');
            if (!data.length) {
                tbody.innerHTML = `<tr><td colspan="10" style="text-align:center;padding:28px;color:var(--text-muted)"><i class="fas fa-circle-check" style="color:#10b981;margin-right:6px"></i>No hay comprobantes pendientes con esos filtros.</td></tr>`;
                if (rnvTable) { rnvTable.destroy(); rnvTable = null; }
                rnvActualizarBatchBar();
                return;
            }
            tbody.innerHTML = data.map(r => {
                const intentos = parseInt(r.reenvio_intentos || 0, 10);
                const intentoCls = intentos >= 15 ? 'color:#dc2626;font-weight:700' : (intentos >= 10 ? 'color:#f59e0b;font-weight:600' : '');
                const errorTxt = (r.reenvio_ultimo_error || '').toString();
                const errorCorto = errorTxt.length > 60 ? errorTxt.substring(0, 60) + '…' : errorTxt;
                const compId = parseInt(r.id, 10);
                const fechaVenta = rnvFormatearFecha(r.venta_created_at);
                return `<tr>
                    <td class="text-center"><input type="checkbox" class="rnv-row-check" value="${compId}" onclick="rnvActualizarBatchBar()"></td>
                    <td><strong>${esc(r.numero_completo||'')}</strong><br><small style="color:var(--text-muted)">${esc(r.tipo||'')}</small>${r.ambiente_sunat==='beta'?' <span class="badge" style="background:#f59e0b;color:#fff">BETA</span>':''}</td>
                    <td>${fechaVenta}<br><small style="color:var(--text-muted)">${esc(r.numero_venta||'')}</small></td>
                    <td>${esc(r.cliente||'—')}<br><small style="color:var(--text-muted)">${esc(r.documento||'')}</small></td>
                    <td class="text-center">S/ ${parseFloat(r.total||0).toFixed(2)}</td>
                    <td class="text-center" style="${intentoCls}">${intentos}/20</td>
                    <td>${rnvFormatearFecha(r.reenvio_proximo_at)}</td>
                    <td>${rnvEstadoBadge(r.estado_reenvio)}</td>
                    <td title="${esc(errorTxt)}" style="font-size:.78rem;color:var(--text-muted);max-width:260px">${esc(errorCorto)}</td>
                    <td class="text-center"><div class="actions-inline">${actionIconButton('paper-plane','Reintentar ahora',null,`rnvReenviarUno(${compId})`)}${r.estado_reenvio==='programado'?actionIconButton('pause','Pausar programación (quita del worker)',null,`rnvCancelarUno(${compId})`):''}</div></td>
                </tr>`;
            }).join('');
            if (rnvTable) rnvTable.destroy();
            rnvTable = $('#rnv-table').DataTable({
                order: [[2, 'desc']],
                pageLength: 25,
                language: { url: BASE + 'assets/vendor/datatables/es-ES.json' },
                columnDefs: [{ orderable: false, targets: [0, 9] }],
                drawCallback: function() {
                    document.getElementById('rnv-check-all').checked = false;
                    rnvActualizarBatchBar();
                },
            });
            rnvActualizarBatchBar();
        })
        .catch(err => showToast(err.message || 'No se pudo cargar la lista de reenvíos','error'));
}

function rnvToggleAll(checked) {
    document.querySelectorAll('.rnv-row-check').forEach(c => { c.checked = checked; });
    rnvActualizarBatchBar();
}

function rnvSeleccionados() {
    return Array.from(document.querySelectorAll('.rnv-row-check:checked')).map(c => parseInt(c.value, 10));
}

function rnvActualizarBatchBar() {
    const ids = rnvSeleccionados();
    const bar = document.getElementById('rnv-batch-bar');
    const cnt = document.getElementById('rnv-sel-count');
    if (!bar || !cnt) return;
    if (ids.length === 0) { bar.style.display = 'none'; return; }
    bar.style.display = 'flex';
    cnt.textContent = `${ids.length} seleccionado(s)`;
}

function rnvReenviarUno(comprobanteId) {
    if (!comprobanteId) return;
    fetch(BASE + 'modules/facturacion/api.php?action=reenviar_sunat', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ comprobante_id: comprobanteId })
    })
    .then(async r => {
        const d = await r.json();
        if (!r.ok || d.error) throw new Error(d.message || 'No se pudo reenviar.');
        showToast(d.message || 'Reenviado a SUNAT','success');
        rnvCargar();
    })
    .catch(err => { showToast(err.message || 'Error al reenviar','error'); rnvCargar(); });
}

// --- Batch sincronico con modal de progreso ---
async function rnvReintentarSync() {
    const ids = rnvSeleccionados();
    if (!ids.length) return;
    if (ids.length > RNV_MAX_SYNC) {
        showToast(`El modo sincrónico acepta máximo ${RNV_MAX_SYNC} comprobantes a la vez. Usa "Programar al worker" para lotes grandes.`, 'error');
        return;
    }

    const modal = document.getElementById('modal-rnv-progress');
    const txt   = document.getElementById('rnv-progress-text');
    const bar   = document.getElementById('rnv-progress-bar');
    const log   = document.getElementById('rnv-progress-log');
    const btn   = document.getElementById('rnv-progress-close');
    log.innerHTML = ''; bar.style.width = '0%'; btn.disabled = true;
    openModal('modal-rnv-progress');

    let ok = 0, fail = 0;
    for (let i = 0; i < ids.length; i++) {
        const id = ids[i];
        txt.textContent = `Enviando ${i + 1} de ${ids.length}…`;
        log.innerHTML += `<div>[${i + 1}] Enviando ID ${id}…</div>`;
        log.scrollTop = log.scrollHeight;
        try {
            const r = await fetch(BASE + 'modules/facturacion/api.php?action=reenviar_sunat', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ comprobante_id: id })
            });
            const d = await r.json();
            if (r.ok && !d.error) {
                ok++;
                log.innerHTML += `<div style="color:#10b981">    ✓ OK</div>`;
            } else {
                fail++;
                log.innerHTML += `<div style="color:#dc2626">    ✗ ${esc(d.message || 'Error')}</div>`;
            }
        } catch (e) {
            fail++;
            log.innerHTML += `<div style="color:#dc2626">    ✗ ${esc(e.message || 'Error de red')}</div>`;
        }
        bar.style.width = (((i + 1) / ids.length) * 100).toFixed(0) + '%';
        log.scrollTop = log.scrollHeight;
    }
    txt.innerHTML = `<strong>Terminado:</strong> ${ok} aceptado(s), ${fail} fallido(s).`;
    btn.disabled = false;
    // Refrescar la lista de atrás para que los aceptados ya no aparezcan
    rnvCargar();
}

function rnvCerrarProgress() {
    closeModal('modal-rnv-progress');
    rnvCargar();
}

// --- Pausar programacion (uno) ---
function rnvCancelarUno(comprobanteId) {
    if (!comprobanteId) return;
    fetch(BASE + 'modules/facturacion/api.php?action=cancelar_programacion_batch', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ comprobante_ids: [comprobanteId] })
    })
    .then(async r => {
        const d = await r.json();
        if (!r.ok || d.error) throw new Error(d.message || 'No se pudo pausar.');
        showToast(d.message || 'Programación cancelada','success');
        rnvCargar();
    })
    .catch(err => showToast(err.message || 'Error al pausar','error'));
}

// --- Batch asincronico: programar al worker ---
function rnvProgramarWorker() {
    const ids = rnvSeleccionados();
    if (!ids.length) return;
    if (!confirm(`Programar ${ids.length} comprobante(s) para que el worker los reintente. Los resultados aparecerán aquí en los próximos minutos. ¿Continuar?`)) return;

    fetch(BASE + 'modules/facturacion/api.php?action=reenviar_sunat_batch', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ comprobante_ids: ids })
    })
    .then(async r => {
        const d = await r.json();
        if (!r.ok || d.error) throw new Error(d.message || 'No se pudo programar.');
        showToast(d.message, 'success');
        rnvCargar();
    })
    .catch(err => showToast(err.message || 'Error al programar','error'));
}

// --- Descargar ZIP maestro para subir manual a SUNAT ---
function rnvDescargarZips() {
    const ids = rnvSeleccionados();
    if (!ids.length) return;
    if (ids.length > 100) {
        showToast('Máximo 100 comprobantes por descarga.', 'error');
        return;
    }
    showToast(`Generando ZIP con ${ids.length} comprobante(s)… puede tardar unos segundos.`, 'info');

    // POST con body JSON, el server responde con el ZIP binario
    fetch(BASE + 'modules/facturacion/api.php?action=descargar_sunat_pending_zip', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ comprobante_ids: ids })
    })
    .then(async r => {
        if (!r.ok) {
            let msg = 'No se pudo generar el ZIP.';
            try { const d = await r.json(); msg = d.message || msg; } catch(_){}
            throw new Error(msg);
        }
        const blob = await r.blob();
        const url  = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `sunat_pendientes_${new Date().toISOString().slice(0,10).replace(/-/g,'')}.zip`;
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        URL.revokeObjectURL(url);
    })
    .catch(err => showToast(err.message || 'Error al descargar','error'));
}

// ================================================================
// REPORTE DE VENTAS
// ================================================================

let rptTable = null;
let rptCache = [];
let rptMap   = {};
let rptNcOrigen  = null;
let rptNcMotivos = [];

function rptGetParams() {
    return { desde: document.getElementById('rpt-desde').value, hasta: document.getElementById('rpt-hasta').value };
}
function rptBuildQuery(extra = {}) {
    return new URLSearchParams({ ...rptGetParams(), ...extra }).toString();
}
function rptDestroyTable() {
    if (rptTable) { rptTable.destroy(); rptTable = null; }
}
function rptInitTable() {
    const table = document.getElementById('rpt-ventas-table');
    if (!table || typeof window.jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') return;
    rptDestroyTable();
    rptTable = jQuery(table).DataTable({
        pageLength: 15, lengthMenu: [10,15,25,50,100], order: [[0,'desc']],
        autoWidth: false, responsive: false, pagingType: 'simple_numbers',
        dom: "<'dt-toolbar'lf>t<'dt-footer'ip>",
        language: {
            search: 'Buscar en resultados:', searchPlaceholder: 'Cliente o comprobante...',
            lengthMenu: 'Mostrar _MENU_ registros', info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Sin registros disponibles', infoFiltered: '(filtrado de _MAX_ registros)',
            zeroRecords: 'No se encontraron ventas con ese criterio', emptyTable: 'No hay ventas para mostrar',
            paginate: { first:'Primero', last:'Ultimo', next:'Siguiente', previous:'Anterior' }
        },
        columnDefs: [
            { targets: [6,7,8], orderable: false, searchable: false },
            { targets: 3, className: 'dt-body-right' }
        ]
    });
}

function rptSetPeriodo(p) {
    const hoy = new Date(), fmt = d => d.toISOString().slice(0,10);
    let desde, hasta;
    switch (p) {
        case 'hoy':  desde = hasta = fmt(hoy); break;
        case 'ayer': { const a = new Date(hoy); a.setDate(a.getDate()-1); desde = hasta = fmt(a); break; }
        case 'semana': { const l = new Date(hoy); l.setDate(hoy.getDate()-((hoy.getDay()+6)%7)); desde=fmt(l); hasta=fmt(hoy); break; }
        case 'mes':     desde = fmt(new Date(hoy.getFullYear(),hoy.getMonth(),1)); hasta = fmt(hoy); break;
        case 'mes_ant': { const f=new Date(hoy.getFullYear(),hoy.getMonth()-1,1),l=new Date(hoy.getFullYear(),hoy.getMonth(),0); desde=fmt(f); hasta=fmt(l); break; }
    }
    document.getElementById('rpt-desde').value = desde;
    document.getElementById('rpt-hasta').value = hasta;
    rptBuscar();
}

function rptBuscar() { rptLoadReporte(); }

function rptRenderSummary(totalDocs, totalAmount) {
    let s = document.getElementById('rpt-tabla-summary');
    if (!s) {
        s = document.createElement('div');
        s.id = 'rpt-tabla-summary';
        s.className = 'tabla-summary';
        document.querySelector('#pane-reporte .table-wrap')?.insertAdjacentElement('afterend', s);
    }
    s.innerHTML = `<div class="summary-pill"><span class="summary-label">Comprobantes</span><strong>${totalDocs}</strong></div>`;
}

function rptShortSunat(rawStatus, responseCode = null) {
    const txt = String(rawStatus||'').trim(), code = String(responseCode??'').trim(), low = txt.toLowerCase();
    if (code==='0') return { label:'Aceptado', className:'badge-success', title:txt||'CDR con respuesta 0' };
    if (code==='1') return { label:'Observado', className:'badge-danger', title:txt||'CDR con observaciones' };
    if (!txt) return { label:'-', className:'badge-gray', title:'' };
    if (low.includes('aceptad')) return { label:'Aceptado', className:'badge-success', title:txt };
    if (low.includes('pendiente')) return { label:'Pendiente', className:'badge-warning', title:txt };
    if (low.includes('observ')) return { label:'Observado', className:'badge-danger', title:txt };
    return { label:txt, className:'badge-gray', title:txt };
}

function rptLoadReporte() {
    rptDestroyTable();
    document.getElementById('rpt-tabla-body').innerHTML =
        '<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>';
    document.getElementById('rpt-result-count').textContent = '...';
    const foot = document.getElementById('rpt-tabla-foot');
    if (foot) { foot.innerHTML = ''; foot.style.display = 'none'; }

    fetch(BASE + 'modules/facturacion/api.php?' + rptBuildQuery({ action:'reporte' }))
        .then(r => r.json())
        .then(data => {
            rptCache = Array.isArray(data) ? data : [];
            if (data.error) {
                document.getElementById('rpt-tabla-body').innerHTML =
                    `<tr><td colspan="9" style="text-align:center;padding:30px;color:#dc2626"><i class="fas fa-exclamation-triangle"></i> ${data.message}</td></tr>`;
                document.getElementById('rpt-result-count').textContent = 'Error';
                rptRenderSummary(0,0); return;
            }
            document.getElementById('rpt-result-count').textContent = `${data.length} resultado(s)`;
            if (!data.length) {
                document.getElementById('rpt-tabla-body').innerHTML =
                    '<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-inbox" style="font-size:1.3rem"></i><br><br>Sin ventas para los filtros aplicados</td></tr>';
                rptRenderSummary(0,0); return;
            }
            rptMap = {};
            document.getElementById('rpt-tabla-body').innerHTML = data.map(v => {
                rptMap[String(v.id)] = v;
                const dt = new Date(v.created_at), fechaOrden = dt.getTime();
                const fecha = dt.toLocaleDateString('es-PE'), hora = dt.toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit'});
                const total = parseFloat(v.total||0);
                const clienteDoc = v.ruc||v.dni||'';
                const respCode = v.nubefact_response ? (() => {
                    try { const p=typeof v.nubefact_response==='string'?JSON.parse(v.nubefact_response):v.nubefact_response; return p?.cdr?.response_code??p?.cdr?.responseCode??null; }
                    catch(_){return null;}
                })() : null;
                const sunat = rptShortSunat(v.estado_sunat, respCode);
                const estadoLabel = v.estado==='completada'?'Vigente':'Anulado';
                const estadoClass = v.estado==='completada'?'badge-success':'badge-danger';
                const compHtml = v.comprobante_numero
                    ? `<div class="voucher-block"><strong>${v.comprobante_numero}</strong><span>${String(v.tipo_comprobante||'').toUpperCase()}</span></div>`
                    : `<div class="voucher-block"><strong>${v.numero_venta}</strong><span>NOTA DE VENTA</span></div>`;
                const xmlBtn = v.enlace_xml ? actionIconButton('file-code','Ver XML',v.enlace_xml) : '';
                const cdrBtn = v.enlace_cdr ? actionIconButton('receipt','Ver CDR',v.enlace_cdr) : '';
                const esNotaVenta = parseInt(v.comprobante_id||0,10)===0 && v.estado==='completada';
                const acciones = `<div class="actions-inline">
                    ${actionIconButton('print','Imprimir ticket',null,`rptImprimirTicket(${parseInt(v.id,10)})`)}
                    ${esNotaVenta ? actionIconButton('file-invoice-dollar','Emitir boleta/factura a SUNAT',null,`rptEmitirDesdeVenta(${parseInt(v.id,10)})`) : ''}
                    ${v.comprobante_id&&v.enlace_cdr&&v.tipo_comprobante!=='ticket' ? actionIconButton('file-circle-plus','Generar nota de crédito',null,`rptAbrirNc(${parseInt(v.comprobante_id,10)})`) : ''}
                    ${v.enlace_pdf ? actionIconButton('file-pdf','Ver PDF',v.enlace_pdf) : ''}
                    ${parseInt(v.comprobante_id||0,10)>0&&v.enlace_xml ? actionIconButton('download','Descargar XML + CDR',null,`facDescargarSunatZip(${parseInt(v.comprobante_id,10)})`) : ''}
                    ${parseInt(v.comprobante_id||0,10)>0&&!v.enlace_cdr&&v.estado==='completada' ? actionIconButton('paper-plane','Reenviar a SUNAT',null,`rptReenviarSunat(${parseInt(v.comprobante_id,10)})`) : ''}
                </div>`;
                return `<tr>
                    <td data-order="${fechaOrden}"><div class="date-block"><strong>${fecha}</strong><span>${hora}</span></div></td>
                    <td><div class="client-block"><div class="client-name">${esc(v.cliente)}</div>${clienteDoc?`<div class="client-doc">${esc(clienteDoc)}</div>`:''}</div></td>
                    <td>${compHtml}</td>
                    <td data-order="${total}" class="text-center"><span class="total-pill">S/ ${total.toFixed(2)}</span></td>
                    <td>${xmlBtn}</td><td>${cdrBtn}</td>
                    <td class="sunat-cell">
                        <span class="badge ${sunat.className}" title="${sunat.title}">${sunat.label}</span>
                        ${v.ambiente_sunat === 'beta' ? '<span class="badge" style="background:#f59e0b;color:#fff;margin-left:4px" title="Enviado al ambiente de pruebas de SUNAT, sin validez legal">BETA</span>' : ''}
                    </td>
                    <td><span class="badge ${estadoClass}">${estadoLabel}</span></td>
                    <td>${acciones}</td>
                </tr>`;
            }).join('');
            const completadas = data.filter(v=>v.estado==='completada');
            rptRenderSummary(completadas.length, completadas.reduce((s,v)=>s+parseFloat(v.total||0),0));
            rptInitTable();
        })
        .catch(() => {
            document.getElementById('rpt-tabla-body').innerHTML =
                '<tr><td colspan="9" style="text-align:center;padding:30px;color:#dc2626"><i class="fas fa-exclamation-triangle"></i> Error al cargar el reporte</td></tr>';
            rptRenderSummary(0,0);
        });
}

function rptExportar() {
    const link = document.createElement('a');
    link.href = BASE + 'modules/facturacion/api.php?' + rptBuildQuery({ action:'exportar' });
    link.download = '';
    document.body.appendChild(link); link.click(); document.body.removeChild(link);
    showToast('Descargando archivo Excel...','success');
}

function facDescargarSunatZip(comprobanteId) {
    if (!comprobanteId) return;
    const link = document.createElement('a');
    link.href = BASE + 'modules/facturacion/api.php?action=descargar_sunat_zip&comprobante_id=' + encodeURIComponent(comprobanteId);
    link.download = '';
    document.body.appendChild(link); link.click(); document.body.removeChild(link);
    showToast('Descargando XML + CDR...','success');
}

function rptReenviarSunat(comprobanteId) {
    if (!comprobanteId) return;
    fetch(BASE + 'modules/facturacion/api.php?action=reenviar_sunat', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ comprobante_id: comprobanteId })
    })
    .then(async r => { const d=await r.json(); if(!r.ok||d.error) throw new Error(d.message||'No se pudo reenviar.'); showToast(d.message||'Reenviado a SUNAT.','success'); rptBuscar(); })
    .catch(err => showToast(err.message||'Error al reenviar a SUNAT','error'));
}

// Emitir boleta/factura a SUNAT a partir de una nota de venta (ticket) ya hecha.
let emiVentaId = null;
function rptEmitirDesdeVenta(ventaId) {
    const v = rptMap[String(ventaId)];
    if (!v) return;
    emiVentaId = ventaId;
    document.getElementById('emi-info').innerHTML =
        `Venta <strong>${esc(v.numero_venta)}</strong> · Total <strong>S/ ${parseFloat(v.total||0).toFixed(2)}</strong><br>` +
        `Cliente: ${esc(v.cliente||'Cliente General')}`;
    document.getElementById('emi-tipo').value = 'boleta';
    emiOnTipo();
    openModal('modal-emitir-sunat');
}
function emiOnTipo() {
    const esFactura = document.getElementById('emi-tipo').value === 'factura';
    document.getElementById('emi-nota-factura').style.display = esFactura ? '' : 'none';
}
function emiConfirmar() {
    if (!emiVentaId) return;
    const btn = document.getElementById('emi-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Emitiendo...';
    const restore = () => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Emitir a SUNAT'; };
    fetch(BASE + 'modules/facturacion/api.php?action=emitir_desde_venta', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ venta_id: emiVentaId, tipo: document.getElementById('emi-tipo').value })
    })
    .then(async r => {
        const d = await r.json();
        restore();
        if (!r.ok || d.error) throw new Error(d.message || 'No se pudo emitir.');
        closeModal('modal-emitir-sunat');
        showToast(d.message || 'Comprobante emitido.', d.sunat_ok ? 'success' : 'info');
        rptBuscar();
    })
    .catch(err => { restore(); showToast(err.message || 'Error al emitir el comprobante', 'error'); });
}

function rptBuildTicketHtml(data) {
    const items = Array.isArray(data.items)?data.items:[];
    const total=parseFloat(data.total||0), subtotal=parseFloat(data.subtotal||0);
    const descuento=parseFloat(data.descuento||0), igv=parseFloat(data.igv||0);
    // Desglose por afectacion calculado de los items (igual que el XML a SUNAT),
    // no hardcodeado: asi exonerado/inafecto NO muestran IGV.
    let opGravada=0, opExonerada=0, opInafecta=0, igvCalc=0;
    items.forEach(it => {
        const cod = String(it.afectacion_igv_codigo||'10');
        const vt = parseFloat(it.valor_total||0)||0;
        if (['20','21'].includes(cod)) opExonerada += vt;
        else if (['30','31','32','33','34','35','36'].includes(cod)) opInafecta += vt;
        else opGravada += vt;
        igvCalc += parseFloat(it.igv||0)||0;
    });
    if (opGravada + opExonerada + opInafecta === 0) { // fallback si los items no traen desglose
        if (igv > 0) opGravada = subtotal; else opExonerada = total;
        igvCalc = igv;
    }
    const tipo=String(data.tipo_comprobante||'').toLowerCase();
    const compLabel = tipo==='factura'?'FACTURA DE VENTA ELECTRÓNICA':(tipo==='boleta'?'BOLETA DE VENTA ELECTRÓNICA':'TICKET DE VENTA');
    const compNumero = data.comprobante_numero||data.numero_venta||'---';
    const dt = new Date(data.created_at||Date.now());
    const fecha = dt.toLocaleDateString('es-PE',{day:'2-digit',month:'2-digit',year:'numeric'});
    const hora  = dt.toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
    const itemsHtml = items.map(it => {
        const nombre=it.nombre||it.producto_nombre||it.descripcion||'Producto';
        const qty=parseFloat(it.cantidad||it.qty||0)||0, pu=parseFloat(it.precio_unitario||it.precio||0)||0;
        const sub=parseFloat(it.precio_total||it.subtotal||0)||(pu*qty);
        return `<div style="margin-bottom:5px"><div style="display:flex;justify-content:space-between;font-size:9pt"><span style="flex:1;padding-right:6px;word-break:break-word">${esc(nombre)}</span><span style="white-space:nowrap;font-weight:700">S/${sub.toFixed(2)}</span></div><div style="font-size:8pt;color:#000;padding-left:2px">${qty} unid. x S/${pu.toFixed(2)}</div></div>`;
    }).join('');
    return `<div style="font-family:'Arial Narrow',Arial,Helvetica,sans-serif;color:#000;font-size:10pt;line-height:1.5;width:100%;font-weight:500">
        <div style="text-align:center;margin-bottom:10px">
            <div style="font-size:12pt;font-weight:900;letter-spacing:1px;text-transform:uppercase">${esc(EMPRESA_NOMBRE||'FARMACIA')}</div>
            ${EMPRESA_RUC?`<div style="font-size:9pt">RUC: ${esc(EMPRESA_RUC)}</div>`:''}
            ${SUCURSAL_NOMBRE?`<div style="font-size:9pt;font-weight:600">${esc(SUCURSAL_NOMBRE)}</div>`:''}
            ${EMPRESA_DIR?`<div style="font-size:8pt">${esc(EMPRESA_DIR)}</div>`:''}
            ${EMPRESA_TEL?`<div style="font-size:8pt">Tel: ${esc(EMPRESA_TEL)}</div>`:''}
        </div>
        <div style="border-top:2px solid #000;border-bottom:2px solid #000;text-align:center;padding:5px 0;margin-bottom:8px">
            <div style="font-size:9pt;font-weight:700">${compLabel}</div><div style="font-size:9pt">${esc(compNumero)}</div>
        </div>
        <div style="font-size:9pt;margin-bottom:6px">
            <div>Fecha : ${fecha} ${hora}</div>
            <div>Cajero: ${esc(data.vendedor||VENDEDOR_NOMBRE)}</div>
            ${data.cliente?`<div>Cliente: ${esc(data.cliente)}</div>`:''}
            ${data.ruc?`<div>RUC: ${esc(data.ruc)}</div>`:data.dni?`<div>DNI: ${esc(data.dni)}</div>`:''}
        </div>
        <div style="border-top:1px dashed #000;margin:6px 0"></div>
        <div style="display:flex;justify-content:space-between;font-size:9pt;font-weight:700;margin-bottom:3px"><span>DESCRIPCIÓN</span><span>TOTAL</span></div>
        <div style="border-top:1px dashed #000;margin:6px 0"></div>
        <div style="margin-bottom:4px">${itemsHtml}</div>
        <div style="border-top:1px dashed #000;margin:6px 0"></div>
        <div style="font-size:9pt;margin:4px 0">
            ${opGravada>0?`<div style="display:flex;justify-content:space-between;gap:10px"><span>OP. GRAVADA:</span><span>S/ ${opGravada.toFixed(2)}</span></div>`:''}
            ${opExonerada>0?`<div style="display:flex;justify-content:space-between;gap:10px"><span>OP. EXONERADA:</span><span>S/ ${opExonerada.toFixed(2)}</span></div>`:''}
            ${opInafecta>0?`<div style="display:flex;justify-content:space-between;gap:10px"><span>OP. INAFECTA:</span><span>S/ ${opInafecta.toFixed(2)}</span></div>`:''}
            ${descuento>0?`<div style="display:flex;justify-content:space-between;gap:10px"><span>DESCUENTO:</span><span>-S/ ${descuento.toFixed(2)}</span></div>`:''}
            ${igvCalc>0?`<div style="display:flex;justify-content:space-between;gap:10px"><span>IGV (18%):</span><span>S/ ${igvCalc.toFixed(2)}</span></div>`:''}
        </div>
        <div style="border-top:2px solid #000;margin:6px 0"></div>
        <div style="display:flex;justify-content:space-between;font-size:11pt;font-weight:800;line-height:1.6"><span>TOTAL:</span><span>S/ ${total.toFixed(2)}</span></div>
        <div style="border-top:2px solid #000;margin:6px 0"></div>
        <div style="text-align:center;font-size:9pt;padding:4px 0;font-weight:700;letter-spacing:.02em"><div>BIENES TRANSFERIDOS EN LA AMAZONÍA PARA</div><div>SER CONSUMIDOS EN LA MISMA</div></div>
    </div>`;
}

async function rptImprimirTicket(ventaId) {
    const row = rptMap[String(ventaId)];
    if (!row) { showToast('No se encontró la venta para imprimir.','error'); return; }
    try {
        const r = await fetch(BASE + 'modules/facturacion/api.php?action=detalle_venta_ticket&id=' + encodeURIComponent(ventaId));
        const data = await r.json();
        if (!r.ok||data.error) throw new Error(data.message||'No se pudo preparar el ticket.');
        openPrintWindow(`Ticket ${data.numero_venta||data.comprobante_numero||ventaId}`, rptBuildTicketHtml(data));
    } catch(e) { showToast('No se pudo preparar el ticket para imprimir.','error'); }
}

// Nota de crédito desde reporte
async function rptCargarMotivos() {
    try {
        const r = await fetch(BASE + 'modules/facturacion/api.php?action=tipos_nota_credito');
        rptNcMotivos = await r.json();
        const sel = document.getElementById('rpt-nc-motivo');
        if (!sel) return;
        sel.innerHTML = '<option value="">Selecciona un motivo</option>' +
            (rptNcMotivos||[]).map(m=>`<option value="${m.id}">${esc(m.codigo)} - ${esc(m.descripcion)}</option>`).join('');
        const def = rptNcMotivos.find(m=>String(m.codigo||'').trim()==='01')||rptNcMotivos[0]||null;
        rptInitMotivoSelect();
        if (def && sel) { sel.value = String(def.id); if(window.jQuery&&jQuery.fn.select2&&jQuery(sel).data('select2')) jQuery(sel).val(String(def.id)).trigger('change'); }
    } catch(_) {
        const sel = document.getElementById('rpt-nc-motivo');
        if (sel) sel.innerHTML = '<option value="">No se pudieron cargar los motivos</option>';
    }
}

function rptInitMotivoSelect() {
    const sel = document.getElementById('rpt-nc-motivo');
    if (!sel||!(window.jQuery&&jQuery.fn.select2)) return;
    const $s = jQuery(sel);
    if ($s.data('select2')) $s.select2('destroy');
    $s.select2({ width:'100%', dropdownParent:jQuery('#modal-nota-credito-reporte'), minimumResultsForSearch:Infinity });
}

function rptAbrirNc(comprobanteId) {
    const row = rptCache.find(v=>String(v.comprobante_id||0)===String(comprobanteId));
    if (!row||!parseInt(row.comprobante_id||0,10)) { showToast('No se encontró el comprobante.','error'); return; }
    if (!String(row.enlace_cdr||'').trim()) { showToast('El comprobante debe tener CDR para emitir nota de crédito.','error'); return; }
    if (String(row.tipo_comprobante||'').toLowerCase()==='ticket') { showToast('No se puede emitir nota de crédito sobre ticket.','error'); return; }
    rptNcOrigen = row;
    document.getElementById('rpt-nc-origen-numero').textContent = row.comprobante_numero||row.numero_venta||'-';
    const cliente = row.cliente?String(row.cliente):'Cliente';
    const doc = [row.ruc?`RUC: ${row.ruc}`:'', row.dni?`DNI: ${row.dni}`:''].filter(Boolean).join(' · ');
    document.getElementById('rpt-nc-origen-cliente').textContent = doc?`${cliente} · ${doc}`:cliente;
    document.getElementById('rpt-nc-origen-total').textContent = `S/ ${parseFloat(row.total||0).toFixed(2)}`;
    document.getElementById('rpt-nc-descripcion').value = 'Anulacion de la operacion';
    const def = Array.isArray(rptNcMotivos)?(rptNcMotivos.find(m=>String(m.codigo||'').trim()==='01')||rptNcMotivos[0]||null):null;
    const motivoSel = document.getElementById('rpt-nc-motivo');
    if (motivoSel) { motivoSel.value=def?String(def.id):''; if(window.jQuery&&jQuery.fn.select2&&jQuery(motivoSel).data('select2')) jQuery(motivoSel).val(def?String(def.id):'').trigger('change'); }
    openModal('modal-nota-credito-reporte');
    rptInitMotivoSelect();
}

function rptCloseNc() { closeModal('modal-nota-credito-reporte'); }

function rptEmitirNc() {
    if (!rptNcOrigen) { showToast('Selecciona un comprobante origen','error'); return; }
    const tipoId = document.getElementById('rpt-nc-motivo').value;
    const descripcion = document.getElementById('rpt-nc-descripcion').value.trim();
    if (!tipoId) { showToast('Selecciona el motivo de la nota','error'); return; }
    if (!descripcion) { showToast('Escribe una descripción','error'); return; }
    const btn = document.getElementById('rpt-btn-emitir-nc');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Emitiendo...';
    fetch(BASE+'modules/facturacion/api.php?action=crear_nota_credito',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({ comprobante_origen_id:rptNcOrigen.comprobante_id, tipo_nota_credito_id:tipoId, descripcion })
    })
    .then(async r=>{ const d=await r.json(); if(!r.ok||d.error) throw new Error(d.message||'No se pudo emitir.'); rptCloseNc(); showToast(d.message||'Nota de crédito emitida.','success'); rptBuscar(); })
    .catch(err=>showToast(err.message||'Error al emitir nota de crédito','error'))
    .finally(()=>{ btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Emitir nota de crédito'; });
}

// ================================================================
// NOTAS DE CRÉDITO
// ================================================================

let notasTable = null, origenesNotas = [], motivosNotas = [], origenActual = null, notasCreditoMap = {};
const origenInicialId = new URLSearchParams(window.location.search).get('origen');

function ncGetParams() {
    return { desde:document.getElementById('f-nota-desde').value, hasta:document.getElementById('f-nota-hasta').value,
             estado:document.getElementById('f-nota-estado').value, q:document.getElementById('f-nota-q').value };
}
function ncBuildQuery(extra={}) { return new URLSearchParams({...ncGetParams(),...extra}).toString(); }

function ncDestroyTable() { if(notasTable){notasTable.destroy();notasTable=null;} }

function ncInitTable() {
    const table = document.getElementById('notas-table');
    if(!table||typeof window.jQuery==='undefined'||typeof jQuery.fn.DataTable==='undefined') return;
    ncDestroyTable();
    notasTable = jQuery(table).DataTable({
        pageLength:10, lengthMenu:[10,15,25,50], autoWidth:false, order:[[0,'desc']], pagingType:'simple_numbers',
        dom:"<'dt-toolbar'lf>t<'dt-footer'ip>",
        language:{ search:'Buscar en resultados:', searchPlaceholder:'Serie, cliente, referencia...',
            lengthMenu:'Mostrar _MENU_ registros', info:'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty:'Sin registros', infoFiltered:'(filtrado de _MAX_ registros)',
            zeroRecords:'No se encontraron notas de crédito', emptyTable:'No hay notas de crédito para mostrar',
            paginate:{first:'Primero',last:'Ultimo',next:'Siguiente',previous:'Anterior'} },
        columnDefs:[{targets:[6,7,8,10],orderable:false,searchable:false},{targets:5,className:'dt-body-right'},{targets:[6,7,8,9,10],className:'dt-body-center'}]
    });
}

function ncSunatBadge(rawStatus, responseCode=null) {
    const txt=String(rawStatus||'').trim(), code=String(responseCode??'').trim(), low=txt.toLowerCase();
    if(code==='0'||low.includes('acept')) return {label:'Aceptado',className:'badge-success',title:txt||'CDR aceptado'};
    if(code==='1'||low.includes('observ')) return {label:'Observado',className:'badge-danger',title:txt||'CDR observado'};
    if(!txt) return {label:'Pendiente',className:'badge-warning',title:'Pendiente de envío'};
    return {label:txt,className:'badge-gray',title:txt};
}

function ncResetFiltros() {
    const hoy=new Date();
    document.getElementById('f-nota-desde').value=new Date(hoy.getFullYear(),hoy.getMonth(),1).toISOString().slice(0,10);
    document.getElementById('f-nota-hasta').value=hoy.toISOString().slice(0,10);
    document.getElementById('f-nota-estado').value='';
    document.getElementById('f-nota-q').value='';
    ncCargarNotas();
}

function ncCargarNotas() {
    ncDestroyTable();
    document.getElementById('notas-body').innerHTML='<tr><td colspan="11" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>';
    document.getElementById('nota-result-count').textContent='...';
    fetch(BASE+'modules/facturacion/api.php?'+ncBuildQuery({action:'notas_reporte'}))
        .then(r=>r.json())
        .then(data=>{
            if(data.error){
                document.getElementById('notas-body').innerHTML=`<tr><td colspan="11" style="text-align:center;padding:30px;color:#dc2626">${esc(data.message)}</td></tr>`;
                document.getElementById('nota-result-count').textContent='Error'; return;
            }
            document.getElementById('nota-result-count').textContent=`${data.length} resultado(s)`;
            if(!data.length){
                document.getElementById('notas-body').innerHTML='<tr><td colspan="11" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-inbox"></i><br><br>Sin notas de crédito para mostrar</td></tr>';
                document.getElementById('nota-total-emitidas').textContent='0';
                document.getElementById('nota-total-monto').textContent='S/ 0.00';
                document.getElementById('nota-total-sunat').textContent='-'; return;
            }
            const totalMonto=data.reduce((s,r)=>s+parseFloat(r.total||0),0);
            const aceptadas=data.filter(r=>String(r.enlace_del_cdr||'').trim()!=='').length;
            document.getElementById('nota-total-emitidas').textContent=String(data.length);
            document.getElementById('nota-total-monto').textContent=`S/ ${totalMonto.toFixed(2)}`;
            document.getElementById('nota-total-sunat').textContent=`${aceptadas} con CDR`;
            notasCreditoMap={};
            document.getElementById('notas-body').innerHTML=data.map(row=>{
                notasCreditoMap[String(row.id)]=row;
                const dt=new Date(row.created_at), fechaOrden=dt.getTime();
                const fecha=dt.toLocaleDateString('es-PE'), hora=dt.toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit'});
                const respCode=row.nubefact_response?(()=>{try{const p=typeof row.nubefact_response==='string'?JSON.parse(row.nubefact_response):row.nubefact_response;return p?.cdr?.response_code??p?.cdr?.responseCode??null;}catch(_){return null;}})():null;
                const sunat=ncSunatBadge(row.estado_sunat,respCode);
                const pdf=row.enlace_del_pdf?actionIconButton('file-pdf','Ver PDF',row.enlace_del_pdf):'';
                const xml=row.enlace_del_xml?actionIconButton('file-code','Ver XML',row.enlace_del_xml):'';
                const cdr=row.enlace_del_cdr?actionIconButton('receipt','Ver CDR',row.enlace_del_cdr):'';
                const reenviar=actionIconButton('paper-plane',row.enlace_del_cdr?'Ya tiene CDR':'Reenviar a SUNAT',null,`ncReenviarSunat(${parseInt(row.id,10)},${row.enlace_del_cdr?'true':'false'})`);
                const estadoClass=row.enlace_del_cdr?'badge-success':'badge-warning';
                const clienteDoc=row.ruc||row.dni||'';
                return `<tr>
                    <td data-order="${fechaOrden}"><div style="display:flex;flex-direction:column"><strong>${fecha}</strong><span style="font-size:.78rem;color:var(--text-muted)">${hora}</span></div></td>
                    <td><div style="display:flex;flex-direction:column"><strong style="font-size:.88rem">${esc(row.numero_completo)}</strong><span style="font-size:.75rem;color:var(--text-muted)">NOTA DE CREDITO</span></div></td>
                    <td><div style="font-size:.83rem"><div style="font-weight:600">${esc(row.cliente)}</div>${clienteDoc?`<div style="font-size:.74rem;color:var(--text-muted)">${esc(clienteDoc)}</div>`:''}</div></td>
                    <td style="font-size:.82rem"><div style="font-weight:600">${esc(row.codigo_tipo_nota_credito||'-')}</div><div style="font-size:.74rem;color:var(--text-muted)">${esc(row.motivo_nota_credito||'')}</div></td>
                    <td style="font-size:.82rem"><div style="font-weight:600">${esc(row.referencia_numero_completo||row.documento_modificado_numero_completo||'-')}</div></td>
                    <td class="text-center" data-order="${parseFloat(row.total||0)}"><span class="total-pill">S/ ${parseFloat(row.total||0).toFixed(2)}</span></td>
                    <td>${xml}</td><td>${cdr}</td>
                    <td class="sunat-cell"><span class="badge ${sunat.className}" title="${sunat.title}">${sunat.label}</span></td>
                    <td><span class="badge ${estadoClass}">${row.enlace_del_cdr?'Vigente':'Pendiente'}</span></td>
                    <td><div class="actions-inline">${actionIconButton('print','Imprimir ticket',null,`ncImprimirTicket(${parseInt(row.id,10)})`)}${pdf}${row.enlace_del_xml?actionIconButton('download','Descargar XML + CDR',null,`facDescargarSunatZip(${parseInt(row.id,10)})`):''}${reenviar}</div></td>
                </tr>`;
            }).join('');
            ncInitTable();
        })
        .catch(()=>{
            document.getElementById('notas-body').innerHTML='<tr><td colspan="11" style="text-align:center;padding:30px;color:#dc2626"><i class="fas fa-exclamation-triangle"></i> Error al cargar notas</td></tr>';
            document.getElementById('nota-result-count').textContent='Error';
        });
}

function ncBuildTicketHtml(row) {
    const dt=new Date(row.created_at||Date.now());
    const fecha=dt.toLocaleDateString('es-PE',{day:'2-digit',month:'2-digit',year:'numeric'});
    const hora=dt.toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
    const total=parseFloat(row.total||0), cliente=row.cliente||'Cliente General';
    const clienteDoc=row.ruc||row.dni||'', ref=row.referencia_numero_completo||row.documento_modificado_numero_completo||'-';
    const motivo=row.motivo_nota_credito||row.motivo_descripcion||row.motivo||'', tipo=row.codigo_tipo_nota_credito||row.tipo_codigo||'';
    const numero=row.numero_completo||row.serie||'NC';
    return `<div style="font-family:'Arial Narrow',Arial,Helvetica,sans-serif;color:#000;font-size:10pt;line-height:1.5;width:100%;font-weight:500">
        <div style="text-align:center;margin-bottom:10px">
            <div style="font-size:12pt;font-weight:900;text-transform:uppercase">${esc(EMPRESA_NOMBRE||'FARMACIA')}</div>
            ${EMPRESA_RUC?`<div style="font-size:9pt">RUC: ${esc(EMPRESA_RUC)}</div>`:''}
            ${SUCURSAL_NOMBRE?`<div style="font-size:9pt;font-weight:600">${esc(SUCURSAL_NOMBRE)}</div>`:''}
        </div>
        <div style="border-top:2px solid #000;border-bottom:2px solid #000;text-align:center;padding:5px 0;margin-bottom:8px">
            <div style="font-size:9pt;font-weight:700">NOTA DE CRÉDITO</div><div style="font-size:9pt">${esc(numero)}</div>
        </div>
        <div style="font-size:9pt;margin-bottom:6px">
            <div>Fecha : ${fecha} ${hora}</div><div>Cliente: ${esc(cliente)}</div>
            ${clienteDoc?`<div>${row.ruc?'RUC':'DNI'}: ${esc(clienteDoc)}</div>`:''}
            <div>Ref.: ${esc(ref)}</div>
        </div>
        <div style="border-top:2px solid #000;margin:6px 0"></div>
        <div style="font-size:9pt;margin:4px 0">
            <div style="display:flex;justify-content:space-between;gap:10px"><span>MOTIVO:</span><span>${esc(motivo||'Anulación de la operación')}</span></div>
            <div style="display:flex;justify-content:space-between;gap:10px"><span>TIPO:</span><span>${esc(tipo)}</span></div>
        </div>
        <div style="border-top:2px solid #000;margin:6px 0"></div>
        <div style="display:flex;justify-content:space-between;font-size:11pt;font-weight:800"><span>TOTAL:</span><span>S/ ${total.toFixed(2)}</span></div>
        <div style="border-top:2px solid #000;margin:6px 0"></div>
        <div style="text-align:center;font-size:9pt;padding:4px 0;font-weight:700;letter-spacing:.02em"><div>BIENES TRANSFERIDOS EN LA AMAZONÍA PARA</div><div>SER CONSUMIDOS EN LA MISMA</div></div>
    </div>`;
}

function ncImprimirTicket(notaId) {
    const row=notasCreditoMap[String(notaId)];
    if(!row){showToast('No se encontró la nota para imprimir.','error');return;}
    openPrintWindow(`NC ${row.numero_completo||row.serie||''}`, ncBuildTicketHtml(row));
}

function ncReenviarSunat(comprobanteId, tieneCdr=false) {
    if(tieneCdr){showToast('Esta nota ya tiene CDR y no se puede reenviar.','error');return;}
    fetch(BASE+'modules/facturacion/api.php?action=reenviar_sunat',{
        method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({comprobante_id:comprobanteId})
    })
    .then(async r=>{const d=await r.json();if(!r.ok||d.error)throw new Error(d.message||'No se pudo reenviar');showToast(d.message||'Nota reenviada a SUNAT.','success');ncCargarNotas();})
    .catch(err=>showToast(err.message||'Error al reenviar a SUNAT','error'));
}

async function ncCargarMotivos() {
    const r=await fetch(BASE+'modules/facturacion/api.php?action=tipos_nota_credito');
    motivosNotas=await r.json();
    const sel=document.getElementById('nota-motivo');
    sel.innerHTML='<option value="">Selecciona un motivo</option>'+motivosNotas.map(m=>`<option value="${m.id}">${esc(m.codigo)} - ${esc(m.descripcion)}</option>`).join('');
    const def=motivosNotas.find(m=>String(m.codigo||'').trim()==='01')||motivosNotas[0];
    if(window.jQuery&&jQuery.fn.select2){if(jQuery(sel).data('select2'))jQuery(sel).select2('destroy');jQuery(sel).select2({width:'100%',dropdownParent:jQuery('#modal-nota-credito'),minimumResultsForSearch:Infinity});}
    if(def){sel.value=String(def.id);if(window.jQuery&&jQuery.fn.select2&&jQuery(sel).data('select2'))jQuery(sel).val(String(def.id)).trigger('change');}
}

async function ncCargarOrigenes() {
    const r=await fetch(BASE+'modules/facturacion/api.php?action=documentos_origen_nota_credito');
    origenesNotas=await r.json();
    const $s=window.jQuery('#nota-origen');
    if($s.data('select2'))$s.select2('destroy');
    $s.empty().append('<option value=""></option>');
    origenesNotas.forEach(item=>{
        const label=`${item.numero_completo} — ${item.cliente} — S/ ${parseFloat(item.total||0).toFixed(2)}`;
        const opt=new Option(label,item.id,false,false);
        $s.append(opt);
    });
    $s.select2({placeholder:'Buscar boleta o factura',allowClear:true,dropdownParent:jQuery('#modal-nota-credito'),width:'100%'});
    $s.off('change').on('change',()=>{
        const id=$s.val();
        origenActual=origenesNotas.find(row=>String(row.id)===String(id))||null;
        ncRenderOrigen();
    });
    if(origenInicialId){
        const origen=origenesNotas.find(row=>String(row.id)===String(origenInicialId));
        if(origen){origenActual=origen;ncRenderOrigen();$s.val(String(origenInicialId)).trigger('change');openModal('modal-nota-credito');}
    }
}

function ncRenderOrigen() {
    document.getElementById('nota-origen-doc').textContent=origenActual?origenActual.numero_completo||'-':'-';
    document.getElementById('nota-origen-cliente').textContent=origenActual?origenActual.cliente||'-':'-';
    document.getElementById('nota-origen-total').textContent=origenActual?`S/ ${parseFloat(origenActual.total||0).toFixed(2)}`:'S/ 0.00';
}

function ncAbrirModal() {
    const def=Array.isArray(motivosNotas)?(motivosNotas.find(m=>String(m.codigo||'').trim()==='01')||motivosNotas[0]||null):null;
    document.getElementById('nota-descripcion').value='Anulación de la operación';
    const motivoSel=document.getElementById('nota-motivo');
    if(motivoSel){if(window.jQuery&&jQuery.fn.select2&&jQuery(motivoSel).data('select2'))jQuery(motivoSel).val(def?String(def.id):'').trigger('change');else motivoSel.value=def?String(def.id):'';}
    origenActual=null; ncRenderOrigen();
    openModal('modal-nota-credito');
    setTimeout(()=>{if(window.jQuery&&jQuery('#nota-origen').data('select2')){jQuery('#nota-origen').val(null).trigger('change');}},120);
}

function ncCrear() {
    const origenId=document.getElementById('nota-origen').value;
    const tipoId=document.getElementById('nota-motivo').value;
    const descripcion=document.getElementById('nota-descripcion').value.trim();
    if(!origenId){showToast('Selecciona el comprobante origen','error');return;}
    if(!tipoId){showToast('Selecciona el motivo de la nota','error');return;}
    if(!descripcion){showToast('Escribe una descripción','error');return;}
    const btn=document.getElementById('btn-crear-nota');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Emitiendo...';
    fetch(BASE+'modules/facturacion/api.php?action=crear_nota_credito',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({comprobante_origen_id:origenId,tipo_nota_credito_id:tipoId,descripcion})
    })
    .then(async r=>{const d=await r.json();if(!r.ok||d.error)throw new Error(d.message||'No se pudo emitir.');closeModal('modal-nota-credito');showToast(d.message||'Nota emitida correctamente.','success');ncCargarNotas();})
    .catch(err=>showToast(err.message||'Error al emitir nota de crédito','error'))
    .finally(()=>{btn.disabled=false;btn.innerHTML='<i class="fas fa-paper-plane"></i> Emitir nota de crédito';});
}

// ================================================================
// RENTABILIDAD
// ================================================================

let rentAllProductos=[], rentSortCol='ganancia', rentSortDir=-1;

function rentGetParams() {
    const v=id=>document.getElementById(id).value;
    return { desde:v('rent-desde'), hasta:v('rent-hasta'),
             categoria_id:v('rent-categoria'), vendedor:v('rent-vendedor'),
             tipo_comp:v('rent-tipo-comp'), tipo_pago:v('rent-tipo-pago'), product_type:v('rent-product-type'),
             laboratorio:v('rent-laboratorio').trim(), margen_min:v('rent-margen-min'), margen_max:v('rent-margen-max') };
}
function rentBuildQuery(extra={}) { return new URLSearchParams({...rentGetParams(),...extra}).toString(); }

function rentSetPeriodo(p) {
    const hoy=new Date(), fmt=d=>d.toISOString().slice(0,10);
    let desde, hasta;
    switch(p) {
        case 'hoy':      desde=hasta=fmt(hoy); break;
        case 'semana':   { const l=new Date(hoy); l.setDate(hoy.getDate()-((hoy.getDay()+6)%7)); desde=fmt(l); hasta=fmt(hoy); break; }
        case 'mes':      desde=fmt(new Date(hoy.getFullYear(),hoy.getMonth(),1)); hasta=fmt(hoy); break;
        case 'mes_ant':  { desde=fmt(new Date(hoy.getFullYear(),hoy.getMonth()-1,1)); hasta=fmt(new Date(hoy.getFullYear(),hoy.getMonth(),0)); break; }
        case 'trimestre':{ const q=Math.floor(hoy.getMonth()/3); desde=fmt(new Date(hoy.getFullYear(),q*3,1)); hasta=fmt(hoy); break; }
    }
    document.getElementById('rent-desde').value=desde;
    document.getElementById('rent-hasta').value=hasta;
    rentBuscar();
}

function rentResetFiltros() {
    const hoy=new Date();
    document.getElementById('rent-desde').value=new Date(hoy.getFullYear(),hoy.getMonth(),1).toISOString().slice(0,10);
    document.getElementById('rent-hasta').value=hoy.toISOString().slice(0,10);
    document.getElementById('rent-categoria').value='0';
    document.getElementById('rent-vendedor').value='';
    document.getElementById('rent-prod-q').value='';
    ['rent-tipo-comp','rent-tipo-pago','rent-product-type','rent-laboratorio','rent-margen-min','rent-margen-max'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('rent-orden-col').value='ganancia';
    document.getElementById('rent-orden-dir').value='-1';
    document.getElementById('rent-top-n').value='10';
    rentSortCol='ganancia'; rentSortDir=-1;
    rentBuscar();
}

function rentLoadFiltros() {
    fetch(BASE+'modules/facturacion/api.php?action=categorias_lista').then(r=>r.json()).then(data=>{
        const sel=document.getElementById('rent-categoria');
        data.forEach(c=>{const o=document.createElement('option');o.value=c.id;o.textContent=c.nombre;sel.appendChild(o);});
    }).catch(()=>{});
    fetch(BASE+'modules/facturacion/api.php?action=usuarios_lista').then(r=>r.json()).then(data=>{
        const sel=document.getElementById('rent-vendedor');
        data.forEach(u=>{const o=document.createElement('option');o.value=u.vendedor;o.textContent=u.vendedor;sel.appendChild(o);});
    }).catch(()=>{});
}

function rentBuscar() { rentLoadStats(); rentLoadCategorias(); rentLoadTop(); rentLoadTendencia(); }

function rentLoadStats() {
    document.getElementById('rent-stats-container').innerHTML=[0,1,2,3,4].map(()=>
        `<div class="col-6 col-md-4 col-lg"><div class="stat-card"><div class="stat-icon blue"><i class="fas fa-spinner fa-spin"></i></div><div><div class="stat-value">—</div><div class="stat-label">Cargando...</div></div></div></div>`
    ).join('');
    fetch(BASE+'modules/facturacion/api.php?'+rentBuildQuery({action:'rentabilidad_stats'}))
        .then(r=>r.json())
        .then(d=>{
            if(!d||d.error){showToast('Error al cargar estadísticas','error');return;}
            const n=v=>parseFloat(v||0), m=v=>'S/ '+n(v).toFixed(2);
            const mCls=n(d.margen_pct)>=20?'green':(n(d.margen_pct)>=10?'yellow':'red');
            const rCls=n(d.roi_pct)>=25?'green':(n(d.roi_pct)>=10?'yellow':'red');
            const cards=[
                {icon:'dollar-sign',  color:'blue',  val:m(d.total_ingresos),label:'Ingresos (ventas)'},
                {icon:'shopping-bag', color:'red',   val:m(d.total_costo),   label:'Costo estimado'},
                {icon:'chart-line',   color:'green', val:m(d.ganancia_bruta),label:'Ganancia bruta'},
                {icon:'percentage',   color:mCls,    val:n(d.margen_pct).toFixed(1)+'%',label:'Margen bruto'},
                {icon:'redo',         color:rCls,    val:n(d.roi_pct).toFixed(1)+'%',  label:'ROI estimado'},
            ];
            document.getElementById('rent-stats-container').innerHTML=cards.map(c=>
                `<div class="col-6 col-md-4 col-lg"><div class="stat-card"><div class="stat-icon ${c.color}"><i class="fas fa-${c.icon}"></i></div><div><div class="stat-value">${c.val}</div><div class="stat-label">${c.label}</div></div></div></div>`
            ).join('');
        })
        .catch(()=>showToast('Error al cargar estadísticas','error'));
}

function rentLoadCategorias() {
    document.getElementById('rent-tabla-categorias').innerHTML='<div style="padding:24px;text-align:center;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></div>';
    fetch(BASE+'modules/facturacion/api.php?'+rentBuildQuery({action:'rentabilidad_categorias'}))
        .then(r=>r.json())
        .then(data=>{
            if(!data||data.error||!data.length){document.getElementById('rent-tabla-categorias').innerHTML='<p style="padding:16px;text-align:center;color:var(--text-muted);font-size:.85rem">Sin datos</p>';return;}
            const maxGan=Math.max(...data.map(d=>parseFloat(d.ganancia)));
            const rows=data.map(d=>{
                const gan=parseFloat(d.ganancia), pct=maxGan>0?gan/maxGan*100:0;
                const mCls=parseFloat(d.margen_pct)>=20?'color:var(--success)':(parseFloat(d.margen_pct)>=10?'color:var(--warning,#f59e0b)':'color:var(--danger)');
                return `<tr style="border-bottom:1px solid var(--border)">
                    <td style="padding:10px 14px;font-weight:600;font-size:.85rem">${esc(d.categoria)}</td>
                    <td style="padding:10px 14px;text-align:right;font-size:.82rem;color:var(--text-muted)">${d.num_ventas}</td>
                    <td style="padding:10px 14px;text-align:right;font-size:.82rem">S/ ${parseFloat(d.ingresos).toFixed(2)}</td>
                    <td style="padding:10px 14px;text-align:right;font-size:.82rem;color:var(--text-muted)">S/ ${parseFloat(d.costo).toFixed(2)}</td>
                    <td style="padding:10px 14px;text-align:right;font-size:.85rem;font-weight:700;color:${gan>=0?'var(--success)':'var(--danger)'}">S/ ${gan.toFixed(2)}</td>
                    <td style="padding:10px 14px">
                        <div style="display:flex;align-items:center;gap:6px">
                            <div style="flex:1;background:#f1f5f9;border-radius:3px;height:6px;overflow:hidden;min-width:40px"><div style="width:${Math.max(pct,0)}%;height:100%;background:${gan>=0?'#16a34a':'#dc2626'};border-radius:3px"></div></div>
                            <span style="font-size:.78rem;min-width:38px;text-align:right;font-weight:600;${mCls}">${parseFloat(d.margen_pct).toFixed(1)}%</span>
                        </div>
                    </td>
                </tr>`;
            }).join('');
            document.getElementById('rent-tabla-categorias').innerHTML=`<table style="width:100%;border-collapse:collapse">
                <thead><tr style="background:var(--surface-2)">
                    <th style="padding:8px 14px;text-align:left;font-size:.75rem;color:var(--text-muted);font-weight:600">Categoría</th>
                    <th style="padding:8px 14px;text-align:right;font-size:.75rem;color:var(--text-muted);font-weight:600">Ventas</th>
                    <th style="padding:8px 14px;text-align:right;font-size:.75rem;color:var(--text-muted);font-weight:600">Ingresos</th>
                    <th style="padding:8px 14px;text-align:right;font-size:.75rem;color:var(--text-muted);font-weight:600">Costo</th>
                    <th style="padding:8px 14px;text-align:right;font-size:.75rem;color:var(--text-muted);font-weight:600">Ganancia</th>
                    <th style="padding:8px 14px;font-size:.75rem;color:var(--text-muted);font-weight:600;min-width:120px">Margen</th>
                </tr></thead><tbody>${rows}</tbody></table>`;
        })
        .catch(()=>showToast('Error al cargar categorías','error'));
}

function rentLoadTop() {
    document.getElementById('rent-tabla-top').innerHTML='<div style="padding:24px;text-align:center;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></div>';
    document.getElementById('rent-prod-body').innerHTML='<tr><td colspan="10" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    document.getElementById('rent-prod-count').textContent='—';
    fetch(BASE+'modules/facturacion/api.php?'+rentBuildQuery({action:'rentabilidad_productos'}))
        .then(r=>r.json())
        .then(data=>{
            rentAllProductos=Array.isArray(data)&&!data.error?data:[];
            rentRenderTabla();
            rentRenderTop();
        })
        .catch(()=>showToast('Error al cargar productos','error'));
}

const RENT_TITULO = {
    ganancia:  ['más rentables','menos rentables'],
    unidades:  ['más vendidos','menos vendidos'],
    ingresos:  ['con más ingresos','con menos ingresos'],
    margen_pct:['con mayor margen','con menor margen'],
    roi_pct:   ['con mayor ROI','con menor ROI'],
};

function rentOrdenar(arr) {
    return arr.slice().sort((a,b)=>{
        const av=isNaN(a[rentSortCol])?(a[rentSortCol]||'').toLowerCase():parseFloat(a[rentSortCol]);
        const bv=isNaN(b[rentSortCol])?(b[rentSortCol]||'').toLowerCase():parseFloat(b[rentSortCol]);
        return av<bv?rentSortDir:av>bv?-rentSortDir:0;
    });
}

function rentTopN() { return parseInt(document.getElementById('rent-top-n').value,10)||0; }

function rentRenderTop() {
    const box=document.getElementById('rent-tabla-top');
    const n=rentTopN()||rentAllProductos.length;
    const esMenos=rentSortDir===1;
    const titulo=RENT_TITULO[rentSortCol];
    document.getElementById('rent-top-titulo').textContent=titulo
        ? `Top ${rentTopN()||'todos'} ${titulo[esMenos?1:0]}`
        : 'Top productos';
    if(!rentAllProductos.length){box.innerHTML='<p style="padding:16px;text-align:center;color:var(--text-muted);font-size:.85rem">Sin datos</p>';return;}
    const lista=rentOrdenar(rentAllProductos).slice(0,Math.min(n,50));
    const medals=['🥇','🥈','🥉'];
    const fmtVal=p=>{
        if(rentSortCol==='unidades') return `<div style="font-size:.88rem;font-weight:700">${p.unidades} uds</div><div style="font-size:.72rem;color:var(--text-muted)">S/ ${parseFloat(p.ingresos).toFixed(2)}</div>`;
        if(rentSortCol==='ingresos') return `<div style="font-size:.88rem;font-weight:700">S/ ${parseFloat(p.ingresos).toFixed(2)}</div><div style="font-size:.72rem;color:var(--text-muted)">${p.unidades} uds</div>`;
        if(rentSortCol==='margen_pct') return `<div style="font-size:.88rem;font-weight:700">${parseFloat(p.margen_pct).toFixed(1)}%</div><div style="font-size:.72rem;color:var(--text-muted)">S/ ${parseFloat(p.ganancia).toFixed(2)}</div>`;
        if(rentSortCol==='roi_pct') return `<div style="font-size:.88rem;font-weight:700">${parseFloat(p.roi_pct).toFixed(1)}%</div><div style="font-size:.72rem;color:var(--text-muted)">S/ ${parseFloat(p.ganancia).toFixed(2)}</div>`;
        const gan=parseFloat(p.ganancia);
        return `<div style="font-size:.88rem;font-weight:700;color:${gan>=0?'var(--success)':'var(--danger)'}">S/ ${gan.toFixed(2)}</div><div style="font-size:.72rem;color:var(--text-muted)">${parseFloat(p.margen_pct).toFixed(1)}% margen</div>`;
    };
    box.innerHTML=`<div style="max-height:420px;overflow-y:auto">`+lista.map((p,i)=>
        `<div style="display:flex;align-items:center;gap:10px;padding:9px 14px;border-bottom:1px solid var(--border)">
            <span style="width:20px;text-align:center;font-size:.9rem;flex-shrink:0">${(!esMenos&&medals[i])||`<span style="font-size:.75rem;color:var(--text-muted);font-weight:700">${i+1}</span>`}</span>
            <div style="flex:1;min-width:0"><div style="font-size:.84rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(p.producto)}</div><div style="font-size:.72rem;color:var(--text-muted)">${esc(p.categoria)}</div></div>
            <div style="text-align:right;flex-shrink:0">${fmtVal(p)}</div>
        </div>`).join('')+`</div>`;
}

// Sincroniza los selectores "Ordenar por / Mostrar / Top" con la tabla y el Top.
function rentAplicarOrden() {
    rentSortCol=document.getElementById('rent-orden-col').value;
    rentSortDir=parseInt(document.getElementById('rent-orden-dir').value,10);
    rentRenderTabla(); rentRenderTop();
}

function rentExportarCSV() {
    const q=document.getElementById('rent-prod-q').value.toLowerCase();
    const data=rentOrdenar(rentAllProductos.filter(p=>!q||p.producto.toLowerCase().includes(q)||p.categoria.toLowerCase().includes(q)||p.codigo.toLowerCase().includes(q)));
    if(!data.length){showToast('No hay datos para exportar','error');return;}
    const c=v=>`"${String(v??'').replace(/"/g,'""')}"`;
    const filas=[['Código','Producto','Categoría','Laboratorio','Unidades','Ingresos','Costo','Ganancia','Margen %','ROI %']];
    data.forEach(p=>filas.push([p.codigo,p.producto,p.categoria,p.laboratorio||'',p.unidades,p.ingresos,p.costo,p.ganancia,p.margen_pct,p.roi_pct]));
    const csv='﻿'+filas.map(f=>f.map(c).join(',')).join('\r\n');
    const a=document.createElement('a');
    a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'}));
    a.download=`rentabilidad_${document.getElementById('rent-desde').value}_${document.getElementById('rent-hasta').value}.csv`;
    document.body.appendChild(a); a.click(); a.remove();
}

function rentLoadTendencia() {
    document.getElementById('rent-chart-wrap').innerHTML='<div style="padding:24px;text-align:center;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></div>';
    fetch(BASE+'modules/facturacion/api.php?'+rentBuildQuery({action:'rentabilidad_tendencia'}))
        .then(r=>r.json())
        .then(data=>{
            if(!data||data.error||!data.length){document.getElementById('rent-chart-wrap').innerHTML='<p style="text-align:center;color:var(--text-muted);padding:24px;font-size:.85rem">Sin datos para el período</p>';return;}
            rentRenderTendencia(data);
        })
        .catch(()=>{document.getElementById('rent-chart-wrap').innerHTML='<p style="text-align:center;color:var(--text-muted);padding:24px;font-size:.85rem">Error al cargar tendencia</p>';});
}

function rentRenderTendencia(data) {
    const maxAbs=Math.max(Math.max(...data.map(d=>parseFloat(d.ingresos))),1);
    const labels=data.length<=14?data.map(d=>{const f=new Date(d.fecha+'T00:00:00');return f.toLocaleDateString('es-PE',{day:'2-digit',month:'short'});}):data.map(d=>new Date(d.fecha+'T00:00:00').getDate().toString().padStart(2,'0'));
    const barGroups=data.map((d,i)=>{
        const ing=parseFloat(d.ingresos),cos=parseFloat(d.costo),gan=parseFloat(d.ganancia);
        const hI=Math.round(ing/maxAbs*100),hC=Math.round(cos/maxAbs*100),hG=Math.round(Math.abs(gan)/maxAbs*100);
        const tip=`${d.fecha}\nIngresos: S/ ${ing.toFixed(2)}\nCosto: S/ ${cos.toFixed(2)}\nGanancia: S/ ${gan.toFixed(2)}`;
        return `<div style="flex:1;display:flex;flex-direction:column;align-items:center;min-width:0" title="${tip}">
            <div style="width:100%;display:flex;gap:1px;align-items:flex-end;height:120px">
                <div style="flex:1;height:${hI}%;background:#3b82f6;border-radius:2px 2px 0 0;min-height:2px;opacity:.75"></div>
                <div style="flex:1;height:${hC}%;background:#f59e0b;border-radius:2px 2px 0 0;min-height:2px;opacity:.75"></div>
                <div style="flex:1;height:${hG}%;background:${gan>=0?'#16a34a':'#dc2626'};border-radius:2px 2px 0 0;min-height:2px"></div>
            </div>
        </div>`;
    }).join('');
    const labelRow=labels.map(l=>`<div style="flex:1;text-align:center;font-size:.62rem;color:var(--text-muted);overflow:hidden;white-space:nowrap">${l}</div>`).join('');
    const totIng=data.reduce((s,d)=>s+parseFloat(d.ingresos),0);
    const totCos=data.reduce((s,d)=>s+parseFloat(d.costo),0);
    const totGan=data.reduce((s,d)=>s+parseFloat(d.ganancia),0);
    document.getElementById('rent-chart-wrap').innerHTML=`
        <div style="display:flex;align-items:flex-end;gap:4px;border-bottom:2px solid var(--border);padding-bottom:0">${barGroups}</div>
        <div style="display:flex;gap:4px;margin-top:5px;margin-bottom:16px">${labelRow}</div>
        <div style="display:flex;gap:24px;justify-content:center;padding:8px 0;background:var(--surface-2);border-radius:var(--radius);font-size:.82rem">
            <span>Ingresos: <strong style="color:#3b82f6">S/ ${totIng.toFixed(2)}</strong></span>
            <span>Costo: <strong style="color:#f59e0b">S/ ${totCos.toFixed(2)}</strong></span>
            <span>Ganancia: <strong style="color:${totGan>=0?'var(--success)':'var(--danger)'}">S/ ${totGan.toFixed(2)}</strong></span>
            <span style="color:var(--text-muted)">Período: <strong>${data.length} punto(s)</strong></span>
        </div>`;
}

function rentRenderTabla() {
    const q=document.getElementById('rent-prod-q').value.toLowerCase();
    let data=rentOrdenar(rentAllProductos.filter(p=>!q||p.producto.toLowerCase().includes(q)||p.categoria.toLowerCase().includes(q)||p.codigo.toLowerCase().includes(q)));
    const total=data.length, n=rentTopN();
    if(n>0) data=data.slice(0,n);
    document.getElementById('rent-prod-count').textContent=(n>0&&total>n?`${data.length} de ${total}`:total)+' producto(s)';
    document.querySelectorAll('#rent-tabla-productos .sortable').forEach(th=>{
        const col=th.dataset.col, ico=th.querySelector('i');
        if(ico) ico.className=col===rentSortCol?(rentSortDir===-1?'fas fa-sort-down':'fas fa-sort-up'):'fas fa-sort';
        if(ico) ico.style.color=col===rentSortCol?'var(--primary)':'var(--text-muted)';
    });
    if(!data.length){document.getElementById('rent-prod-body').innerHTML='<tr><td colspan="10" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-box-open" style="font-size:1.3rem"></i><br><br>Sin productos para los filtros</td></tr>';return;}
    const maxGan=Math.max(...data.map(d=>Math.abs(parseFloat(d.ganancia))));
    document.getElementById('rent-prod-body').innerHTML=data.map((p,i)=>{
        const gan=parseFloat(p.ganancia), mar=parseFloat(p.margen_pct);
        const pct=maxGan>0?Math.abs(gan)/maxGan*100:0;
        const ganClr=gan>=0?'var(--success)':'var(--danger)';
        const marClr=mar>=20?'color:var(--success)':(mar>=10?'color:var(--warning,#f59e0b)':'color:var(--danger)');
        return `<tr>
            <td style="font-size:.78rem;color:var(--text-muted);text-align:center">${i+1}</td>
            <td style="font-weight:500;font-size:.85rem">${esc(p.producto)}<div style="font-size:.72rem;color:var(--text-muted);font-family:monospace">${esc(p.codigo)}</div></td>
            <td style="font-size:.8rem;color:var(--text-muted)">${esc(p.categoria)}</td>
            <td class="text-right" style="font-size:.85rem">${p.unidades}</td>
            <td class="text-right" style="font-size:.85rem">S/ ${parseFloat(p.ingresos).toFixed(2)}</td>
            <td class="text-right" style="font-size:.85rem;color:var(--text-muted)">S/ ${parseFloat(p.costo).toFixed(2)}</td>
            <td class="text-right" style="font-size:.9rem;font-weight:700;color:${ganClr}">S/ ${gan.toFixed(2)}</td>
            <td class="text-right" style="font-size:.85rem;font-weight:600;${marClr}">${mar.toFixed(1)}%</td>
            <td class="text-right" style="font-size:.85rem;color:var(--text-muted)">${parseFloat(p.roi_pct).toFixed(1)}%</td>
            <td><div style="display:flex;align-items:center;gap:6px"><div style="flex:1;background:#f1f5f9;border-radius:3px;height:7px;overflow:hidden"><div style="width:${pct.toFixed(1)}%;height:100%;background:${gan>=0?'#16a34a':'#dc2626'};border-radius:3px;transition:.3s"></div></div></div></td>
        </tr>`;
    }).join('');
}

function rentFiltrar() { rentRenderTabla(); }
function rentSort(col) {
    if(rentSortCol===col)rentSortDir*=-1;else{rentSortCol=col;rentSortDir=-1;}
    const sel=document.getElementById('rent-orden-col');
    if([...sel.options].some(o=>o.value===col)) sel.value=col;
    document.getElementById('rent-orden-dir').value=String(rentSortDir);
    rentRenderTabla(); rentRenderTop();
}

// ================================================================
// INIT
// ================================================================

document.addEventListener('DOMContentLoaded', () => {
    const initialTab = new URLSearchParams(window.location.search).get('tab') || 'reporte';
    switchTab(initialTab);
    document.getElementById('f-nota-q').addEventListener('keyup', e => { if(e.key==='Enter') ncCargarNotas(); });
});
</script>

<?php include '../../includes/footer.php'; ?>
