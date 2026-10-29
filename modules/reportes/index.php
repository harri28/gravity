<?php
// ============================================================
// ARCHIVO: farmacia/modules/reportes/index.php
// MÓDULO:  Reportes (tabs: Costos de Compras | Ventas | Inventario | Caja)
// ============================================================

require_once '../../config/database.php';

$base_path      = '../../';
$required_roles = ['admin', 'gerente'];
$current_module = 'reportes';
$current_page   = 'reportes';
$page_title     = 'Reportes — Gravity';
$breadcrumb     = '<strong>Reportes</strong>';

include '../../includes/header.php';
?>

<style>
.rep-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 20px;
    background: var(--surface-2);
    border-radius: var(--radius);
    padding: 5px;
    width: fit-content;
    flex-wrap: wrap;
}
.rep-tab {
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
.rep-tab:hover { background: var(--surface); color: var(--text); }
.rep-tab.active { background: var(--primary); color: #fff; }
.rep-pane { }
@media (max-width: 640px) {
    .rep-tabs { width: 100%; }
}

/* Paginación (Stock Paralizado) -- mismo patrón que modules/inventario/index.php,
   no es un estilo global compartido. */
#pz-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 12px 16px;
    border-top: 1px solid var(--border);
    flex-wrap: wrap;
}
.pg-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    height: 34px;
    padding: 0 8px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text);
    font-size: .82rem;
    font-weight: 500;
    cursor: pointer;
    transition: background .13s, color .13s, border-color .13s;
    line-height: 1;
}
.pg-btn:hover:not(:disabled) { background: var(--surface-2); border-color: var(--primary); color: var(--primary); }
.pg-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; font-weight: 700; }
.pg-btn:disabled { opacity: .4; cursor: default; }
.pg-ellipsis { display: inline-flex; align-items: center; justify-content: center; min-width: 24px; height: 34px; color: var(--text-muted); font-size: .82rem; }
</style>

<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-chart-pie" style="color:var(--primary);margin-right:8px"></i>Reportes</div>
        <div class="page-subtitle">Costos, ventas, inventario y caja — filtra y exporta</div>
    </div>
</div>

<div class="rep-tabs">
    <button class="rep-tab active" id="rep-tab-btn-costos" onclick="repSwitchTab('costos')">
        <i class="fas fa-truck-loading"></i> Costos de Compras
    </button>
    <button class="rep-tab" id="rep-tab-btn-ventas" onclick="repSwitchTab('ventas')">
        <i class="fas fa-cash-register"></i> Ventas
    </button>
    <button class="rep-tab" id="rep-tab-btn-inventario" onclick="repSwitchTab('inventario')">
        <i class="fas fa-boxes"></i> Inventario
    </button>
    <button class="rep-tab" id="rep-tab-btn-caja" onclick="repSwitchTab('caja')">
        <i class="fas fa-cash-register"></i> Caja
    </button>
    <button class="rep-tab" id="rep-tab-btn-productos" onclick="repSwitchTab('productos')">
        <i class="fas fa-ranking-star"></i> Productos
    </button>
    <button class="rep-tab" id="rep-tab-btn-anulaciones" onclick="repSwitchTab('anulaciones')">
        <i class="fas fa-ban"></i> Anulaciones
    </button>
    <button class="rep-tab" id="rep-tab-btn-paralizado" onclick="repSwitchTab('paralizado')">
        <i class="fas fa-snowflake"></i> Stock Paralizado
    </button>
</div>

<!-- ============================================================
     PANE: COSTOS DE COMPRAS
     ============================================================ -->
<div id="rep-pane-costos" class="rep-pane">

    <div class="card" style="margin-bottom:20px">
        <div style="padding:10px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">Desde</label>
                <input type="date" id="cc-desde" class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">Hasta</label>
                <input type="date" id="cc-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:180px">
                <label class="form-label">Proveedor</label>
                <select id="cc-proveedor" class="form-control">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:160px">
                <label class="form-label">Estado</label>
                <select id="cc-estado" class="form-control">
                    <option value="">Todos</option>
                    <option value="borrador">Borrador</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="aprobada">Aprobada</option>
                    <option value="recibida">Recibida</option>
                    <option value="cancelada">Cancelada</option>
                </select>
            </div>
            <button class="btn btn-primary" onclick="ccBuscar()" style="margin-bottom:0">
                <i class="fas fa-search"></i> Buscar
            </button>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:0">
                <span style="font-size:.78rem;color:var(--text-muted)">Rápido:</span>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('cc','hoy')">Hoy</button>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('cc','semana')">Esta semana</button>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('cc','mes')">Este mes</button>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('cc','mes_ant')">Mes anterior</button>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4" id="cc-stats">
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon blue"><i class="fas fa-file-invoice"></i></div><div><div class="stat-value" id="cc-st-ordenes">—</div><div class="stat-label">Órdenes</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon green"><i class="fas fa-coins"></i></div><div><div class="stat-value" id="cc-st-subtotal">—</div><div class="stat-label">Subtotal</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-shipping-fast"></i></div><div><div class="stat-value" id="cc-st-envio">—</div><div class="stat-label">Costo de envío</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon red"><i class="fas fa-receipt"></i></div><div><div class="stat-value" id="cc-st-total">—</div><div class="stat-label">Total general</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Detalle de órdenes de compra</div>
            <div style="display:flex;align-items:center;gap:12px;margin-left:auto">
                <span style="font-size:.82rem;color:var(--text-muted)" id="cc-result-count">—</span>
                <button class="btn btn-success btn-sm" onclick="ccExportar()">
                    <i class="fas fa-file-excel"></i> Exportar
                </button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>N° Orden</th><th>Fecha</th><th>Proveedor</th><th>Estado</th>
                    <th class="text-right">Subtotal</th><th class="text-right">IGV</th>
                    <th class="text-right">Envío</th><th class="text-right">Total</th>
                </tr></thead>
                <tbody id="cc-tabla-body">
                    <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /rep-pane-costos -->


<!-- ============================================================
     PANE: VENTAS
     ============================================================ -->
<div id="rep-pane-ventas" class="rep-pane" style="display:none">

    <div class="card" style="margin-bottom:20px">
        <div style="padding:10px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">Desde</label>
                <input type="date" id="vt-desde" class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">Hasta</label>
                <input type="date" id="vt-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:160px">
                <label class="form-label">Agrupar por</label>
                <select id="vt-agrupar" class="form-control">
                    <option value="vendedor">Vendedor</option>
                    <option value="dia">Día</option>
                    <option value="comprobante">Tipo de comprobante</option>
                </select>
            </div>
            <button class="btn btn-primary" onclick="vtBuscar()" style="margin-bottom:0">
                <i class="fas fa-search"></i> Buscar
            </button>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:0">
                <span style="font-size:.78rem;color:var(--text-muted)">Rápido:</span>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('vt','hoy')">Hoy</button>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('vt','semana')">Esta semana</button>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('vt','mes')">Este mes</button>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('vt','mes_ant')">Mes anterior</button>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4" id="vt-stats">
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon blue"><i class="fas fa-receipt"></i></div><div><div class="stat-value" id="vt-st-ventas">—</div><div class="stat-label">Ventas</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon green"><i class="fas fa-sack-dollar"></i></div><div><div class="stat-value" id="vt-st-ingresos">—</div><div class="stat-label">Ingresos</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-tag"></i></div><div><div class="stat-value" id="vt-st-ticket">—</div><div class="stat-label">Ticket promedio</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon red"><i class="fas fa-ban"></i></div><div><div class="stat-value" id="vt-st-anuladas">—</div><div class="stat-label">Anuladas</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title" id="vt-tabla-titulo">Ventas por vendedor</div>
            <div style="display:flex;align-items:center;gap:12px;margin-left:auto">
                <button class="btn btn-success btn-sm" onclick="vtExportar()">
                    <i class="fas fa-file-excel"></i> Exportar
                </button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th id="vt-th-etiqueta">Vendedor</th>
                    <th class="text-right">N° Ventas</th><th class="text-right">Ingresos</th>
                    <th class="text-right">IGV</th><th class="text-right">Ticket promedio</th>
                    <th class="text-right">% Participación</th><th class="text-right">vs Promedio</th>
                    <th class="text-right">Anuladas</th>
                </tr></thead>
                <tbody id="vt-tabla-body">
                    <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /rep-pane-ventas -->


<!-- ============================================================
     PANE: INVENTARIO (Valorización)
     ============================================================ -->
<div id="rep-pane-inventario" class="rep-pane" style="display:none">

    <div class="card" style="margin-bottom:20px">
        <div style="padding:10px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin:0;flex:1;min-width:180px">
                <label class="form-label">Buscar</label>
                <input type="text" id="inv-q" class="form-control" placeholder="Nombre o código...">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:160px">
                <label class="form-label">Categoría</label>
                <select id="inv-categoria" class="form-control">
                    <option value="">Todas</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">Estado</label>
                <select id="inv-solo-activos" class="form-control">
                    <option value="1">Solo activos</option>
                    <option value="0">Todos</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:150px">
                <label class="form-label" title="Ventana usada para calcular la velocidad de venta diaria">Días de venta a considerar</label>
                <input type="number" id="inv-dias-venta" class="form-control" value="30" min="7" max="365">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:190px">
                <label class="form-label">Orden</label>
                <select id="inv-orden" class="form-control">
                    <option value="valor_desc">Mayor valor en inventario</option>
                    <option value="valor_asc">Menor valor en inventario</option>
                    <option value="cobertura_asc">Menor días de cobertura (más urgente)</option>
                    <option value="cobertura_desc">Mayor días de cobertura</option>
                </select>
            </div>
            <button class="btn btn-primary" onclick="invBuscar()" style="margin-bottom:0">
                <i class="fas fa-search"></i> Buscar
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4" id="inv-stats">
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon blue"><i class="fas fa-boxes"></i></div><div><div class="stat-value" id="inv-st-productos">—</div><div class="stat-label">Productos</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon green"><i class="fas fa-coins"></i></div><div><div class="stat-value" id="inv-st-valor-compra">—</div><div class="stat-label">Valor (costo)</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-sack-dollar"></i></div><div><div class="stat-value" id="inv-st-valor-venta">—</div><div class="stat-label">Valor (venta)</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon red"><i class="fas fa-triangle-exclamation"></i></div><div><div class="stat-value" id="inv-st-alertas">—</div><div class="stat-label">Agotados / stock bajo</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon red"><i class="fas fa-hourglass-half"></i></div><div><div class="stat-value" id="inv-st-riesgo">—</div><div class="stat-label">En riesgo de quiebre (≤7 días)</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Valorización por producto</div>
            <div style="display:flex;align-items:center;gap:12px;margin-left:auto">
                <span style="font-size:.82rem;color:var(--text-muted)" id="inv-result-count">—</span>
                <button class="btn btn-outline btn-sm" onclick="invExportarNombreStock()">
                    <i class="fas fa-file-excel"></i> Solo nombre y stock
                </button>
                <button class="btn btn-success btn-sm" onclick="invExportar()">
                    <i class="fas fa-file-excel"></i> Exportar
                </button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Código</th><th>Producto</th><th>Categoría</th>
                    <th class="text-right">Stock</th><th class="text-right">P. Compra</th>
                    <th class="text-right">P. Venta</th><th class="text-right">Valor Inventario</th>
                    <th class="text-right">Vendido (periodo)</th><th class="text-right">Días Cobertura</th>
                </tr></thead>
                <tbody id="inv-tabla-body">
                    <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /rep-pane-inventario -->


<!-- ============================================================
     PANE: CAJA (Movimientos)
     ============================================================ -->
<div id="rep-pane-caja" class="rep-pane" style="display:none">

    <div class="card" style="margin-bottom:20px">
        <div style="padding:10px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">Desde</label>
                <input type="date" id="cj-desde" class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">Hasta</label>
                <input type="date" id="cj-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:150px">
                <label class="form-label">Tipo</label>
                <select id="cj-tipo" class="form-control">
                    <option value="">Todos</option>
                    <option value="ingreso">Ingreso</option>
                    <option value="egreso">Egreso</option>
                </select>
            </div>
            <button class="btn btn-primary" onclick="cjBuscar()" style="margin-bottom:0">
                <i class="fas fa-search"></i> Buscar
            </button>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:0">
                <span style="font-size:.78rem;color:var(--text-muted)">Rápido:</span>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('cj','hoy')">Hoy</button>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('cj','semana')">Esta semana</button>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('cj','mes')">Este mes</button>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('cj','mes_ant')">Mes anterior</button>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4" id="cj-stats">
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon blue"><i class="fas fa-exchange-alt"></i></div><div><div class="stat-value" id="cj-st-movimientos">—</div><div class="stat-label">Movimientos</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon green"><i class="fas fa-arrow-down"></i></div><div><div class="stat-value" id="cj-st-ingresos">—</div><div class="stat-label">Ingresos</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon red"><i class="fas fa-arrow-up"></i></div><div><div class="stat-value" id="cj-st-egresos">—</div><div class="stat-label">Egresos</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-balance-scale"></i></div><div><div class="stat-value" id="cj-st-neto">—</div><div class="stat-label">Neto</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Movimientos de caja</div>
            <div style="display:flex;align-items:center;gap:12px;margin-left:auto">
                <button class="btn btn-success btn-sm" onclick="cjExportar()">
                    <i class="fas fa-file-excel"></i> Exportar
                </button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Fecha</th><th>Caja</th><th>Tipo</th><th>Concepto</th>
                    <th>Usuario</th><th class="text-right">Monto</th>
                </tr></thead>
                <tbody id="cj-tabla-body">
                    <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /rep-pane-caja -->


<!-- ============================================================
     PANE: PRODUCTOS (Ranking ABC / Pareto 80-15-5)
     ============================================================ -->
<div id="rep-pane-productos" class="rep-pane" style="display:none">

    <div class="card" style="margin-bottom:20px">
        <div style="padding:10px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">Desde</label>
                <input type="date" id="pr-desde" class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">Hasta</label>
                <input type="date" id="pr-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:160px">
                <label class="form-label">Categoría</label>
                <select id="pr-categoria" class="form-control">
                    <option value="">Todas</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:190px">
                <label class="form-label">Orden</label>
                <select id="pr-orden" class="form-control">
                    <option value="ingreso_desc">Más vendidos (por ingreso)</option>
                    <option value="ingreso_asc">Menos vendidos (por ingreso)</option>
                    <option value="cantidad_desc">Más vendidos (por cantidad)</option>
                    <option value="cantidad_asc">Menos vendidos (por cantidad)</option>
                </select>
            </div>
            <button class="btn btn-primary" onclick="prBuscar()" style="margin-bottom:0">
                <i class="fas fa-search"></i> Buscar
            </button>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:0">
                <span style="font-size:.78rem;color:var(--text-muted)">Rápido:</span>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('pr','hoy')">Hoy</button>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('pr','semana')">Esta semana</button>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('pr','mes')">Este mes</button>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('pr','mes_ant')">Mes anterior</button>
            </div>
        </div>
    </div>

    <div class="card mb-4" style="background:var(--surface-2);border:1px dashed var(--border)">
        <div style="display:flex;gap:10px;align-items:flex-start;font-size:.85rem;color:var(--text-muted)">
            <i class="fas fa-circle-info" style="color:var(--primary);margin-top:2px"></i>
            <div>Clasificación ABC (Pareto): <strong>A</strong> = productos que juntos generan hasta el 80% del
            ingreso del periodo, <strong>B</strong> = el siguiente 15% (hasta 95% acumulado), <strong>C</strong> = el
            5% restante. "Menos vendidos" solo incluye productos que <em>sí</em> tuvieron venta en el rango —
            para productos con stock sin ninguna venta, revisa el detector de stock paralizado (próximamente).</div>
        </div>
    </div>

    <div class="row g-3 mb-4" id="pr-stats">
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon blue"><i class="fas fa-boxes-stacked"></i></div><div><div class="stat-value" id="pr-st-productos">—</div><div class="stat-label">Productos con venta</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon green"><i class="fas fa-sack-dollar"></i></div><div><div class="stat-value" id="pr-st-ingreso">—</div><div class="stat-label">Ingreso total</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-star"></i></div><div><div class="stat-value" id="pr-st-clase-a">—</div><div class="stat-label">Productos clase A</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon red"><i class="fas fa-trophy"></i></div><div><div class="stat-value" id="pr-st-top" style="font-size:.95rem">—</div><div class="stat-label">Producto top</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Ranking de productos</div>
            <div style="display:flex;align-items:center;gap:12px;margin-left:auto">
                <span style="font-size:.82rem;color:var(--text-muted)" id="pr-result-count">—</span>
                <button class="btn btn-success btn-sm" onclick="prExportar()">
                    <i class="fas fa-file-excel"></i> Exportar
                </button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Código</th><th>Producto</th><th>Categoría</th>
                    <th class="text-right">Cantidad</th><th class="text-right">Ingreso</th>
                    <th class="text-right">Margen Est.</th><th class="text-right">% Acumulado</th>
                    <th style="text-align:center;width:70px">Clase</th>
                </tr></thead>
                <tbody id="pr-tabla-body">
                    <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /rep-pane-productos -->


<!-- ============================================================
     PANE: ANULACIONES
     ============================================================ -->
<div id="rep-pane-anulaciones" class="rep-pane" style="display:none">

    <div class="card" style="margin-bottom:20px">
        <div style="padding:10px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">Desde</label>
                <input type="date" id="an-desde" class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:140px">
                <label class="form-label">Hasta</label>
                <input type="date" id="an-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <button class="btn btn-primary" onclick="anBuscar()" style="margin-bottom:0">
                <i class="fas fa-search"></i> Buscar
            </button>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:0">
                <span style="font-size:.78rem;color:var(--text-muted)">Rápido:</span>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('an','hoy')">Hoy</button>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('an','semana')">Esta semana</button>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('an','mes')">Este mes</button>
                <button class="btn btn-ghost btn-sm" onclick="repSetPeriodo('an','mes_ant')">Mes anterior</button>
            </div>
        </div>
    </div>

    <div class="card mb-4" style="background:var(--surface-2);border:1px dashed var(--border)">
        <div style="display:flex;gap:10px;align-items:flex-start;font-size:.85rem;color:var(--text-muted)">
            <i class="fas fa-circle-info" style="color:var(--primary);margin-top:2px"></i>
            <div>La columna "Vendedor" es quien registró la venta original, no necesariamente quien hizo clic en
            anular — el sistema no guarda ese dato por separado hoy.</div>
        </div>
    </div>

    <div class="row g-3 mb-4" id="an-stats">
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon red"><i class="fas fa-ban"></i></div><div><div class="stat-value" id="an-st-total">—</div><div class="stat-label">Ventas anuladas</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon yellow"><i class="fas fa-sack-dollar"></i></div><div><div class="stat-value" id="an-st-monto">—</div><div class="stat-label">Monto anulado</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon blue"><i class="fas fa-user"></i></div><div><div class="stat-value" id="an-st-vendedor" style="font-size:.95rem">—</div><div class="stat-label">Vendedor con más anulaciones</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon blue"><i class="fas fa-box"></i></div><div><div class="stat-value" id="an-st-producto" style="font-size:.95rem">—</div><div class="stat-label">Producto más anulado</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Ventas anuladas</div>
            <div style="display:flex;align-items:center;gap:12px;margin-left:auto">
                <span style="font-size:.82rem;color:var(--text-muted)" id="an-result-count">—</span>
                <button class="btn btn-success btn-sm" onclick="anExportar()">
                    <i class="fas fa-file-excel"></i> Exportar
                </button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>N° Venta</th><th>Fecha</th><th>Vendedor</th>
                    <th class="text-right">Monto</th><th>Motivo</th><th>Productos</th>
                </tr></thead>
                <tbody id="an-tabla-body">
                    <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /rep-pane-anulaciones -->


<!-- ============================================================
     PANE: STOCK PARALIZADO
     ============================================================ -->
<div id="rep-pane-paralizado" class="rep-pane" style="display:none">

    <div class="card" style="margin-bottom:20px">
        <div style="padding:10px 16px">
            <div class="form-group" style="margin:0">
                <label class="form-label">Buscar</label>
                <div class="input-group">
                    <span class="input-group-icon"><i class="fas fa-search"></i></span>
                    <input type="text" id="pz-q" class="form-control" placeholder="Nombre o código...">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Productos sin movimiento</div>
            <div style="display:flex;align-items:center;gap:12px;margin-left:auto">
                <span style="font-size:.82rem;color:var(--text-muted)" id="pz-result-count">—</span>
                <button class="btn btn-primary btn-sm" id="pz-btn-crear-promo" onclick="pzAbrirPromoConSeleccionados()" disabled>
                    <i class="fas fa-tags"></i> Crear promoción con seleccionados
                </button>
                <button class="btn btn-success btn-sm" onclick="pzExportar()">
                    <i class="fas fa-file-excel"></i> Exportar
                </button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th style="width:36px"><input type="checkbox" id="pz-check-all" onchange="pzToggleAll(this)"></th>
                    <th>Código</th><th>Producto</th><th>Categoría</th>
                    <th class="text-right">Stock</th><th class="text-right">Valor Inmovilizado</th>
                    <th>Última Venta</th>
                </tr></thead>
                <tbody id="pz-tabla-body">
                    <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
        <div id="pz-pagination"></div>
    </div>

</div><!-- /rep-pane-paralizado -->


<!-- ===================== MODAL: Ítems de la orden de compra ===================== -->
<div class="modal-overlay" id="modal-oc-items">
    <div class="modal" style="max-width:640px">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-box-open" style="color:var(--primary);margin-right:8px"></i>
                <span id="oci-titulo">Ítems de la orden</span>
            </h3>
            <button class="modal-close" onclick="closeModal('modal-oc-items')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="oci-info" style="font-size:.82rem;color:var(--text-muted);margin-bottom:10px">—</div>
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>Producto</th><th class="text-right">Cant.</th>
                        <th class="text-right">P. Unit.</th><th class="text-right">Subtotal</th>
                    </tr></thead>
                    <tbody id="oci-tabla-body">
                        <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                    </tbody>
                </table>
            </div>
            <div style="font-size:.78rem;color:var(--text-muted);margin-top:8px"><i class="fas fa-circle-info"></i> Haz clic en un producto para ver el resumen histórico de sus compras.</div>
        </div>
    </div>
</div>

<!-- ===================== MODAL: Resumen de compras de un producto ===================== -->
<div class="modal-overlay" id="modal-prod-resumen">
    <div class="modal" style="max-width:720px">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-chart-line" style="color:var(--primary);margin-right:8px"></i>
                <span id="pr-titulo">Resumen de compras</span>
            </h3>
            <button class="modal-close" onclick="closeModal('modal-prod-resumen')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="pr-body">
            <div style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
    </div>
</div>

<div class="app-toast-container" id="toast-container"></div>

<script>
const BASE = '../../';
const API  = BASE + 'modules/reportes/api.php';
const _repTabLoaded = {};

function money(n) { return 'S/ ' + (parseFloat(n) || 0).toFixed(2); }
function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function repSwitchTab(tab) {
    document.querySelectorAll('.rep-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('rep-tab-btn-' + tab).classList.add('active');
    document.querySelectorAll('.rep-pane').forEach(p => p.style.display = 'none');
    document.getElementById('rep-pane-' + tab).style.display = '';

    if (!_repTabLoaded[tab]) {
        _repTabLoaded[tab] = true;
        if (tab === 'costos')      { ccCargarProveedores(); ccBuscar(); }
        if (tab === 'ventas')      { vtBuscar(); }
        if (tab === 'inventario')  { invCargarCategorias(); invBuscar(); }
        if (tab === 'caja')        { cjBuscar(); }
        if (tab === 'productos')   { prCargarCategorias(); prBuscar(); }
        if (tab === 'anulaciones') { anBuscar(); }
        if (tab === 'paralizado')  { pzBuscar(); }
    }
}

function repSetPeriodo(prefix, p) {
    const hoy = new Date(), fmt = d => d.toISOString().slice(0,10);
    let desde, hasta;
    switch (p) {
        case 'hoy':  desde = hasta = fmt(hoy); break;
        case 'semana': { const l = new Date(hoy); l.setDate(hoy.getDate()-((hoy.getDay()+6)%7)); desde=fmt(l); hasta=fmt(hoy); break; }
        case 'mes':     desde = fmt(new Date(hoy.getFullYear(),hoy.getMonth(),1)); hasta = fmt(hoy); break;
        case 'mes_ant': { const f=new Date(hoy.getFullYear(),hoy.getMonth()-1,1),l=new Date(hoy.getFullYear(),hoy.getMonth(),0); desde=fmt(f); hasta=fmt(l); break; }
    }
    document.getElementById(prefix + '-desde').value = desde;
    document.getElementById(prefix + '-hasta').value = hasta;
    if (prefix === 'cc') ccBuscar();
    if (prefix === 'vt') vtBuscar();
    if (prefix === 'cj') cjBuscar();
    if (prefix === 'pr') prBuscar();
    if (prefix === 'an') anBuscar();
}

function repDownload(url) {
    const link = document.createElement('a');
    link.href = url;
    link.download = '';
    document.body.appendChild(link); link.click(); document.body.removeChild(link);
    showToast('Descargando archivo Excel...', 'success');
}

// ================================================================
// COSTOS DE COMPRAS
// ================================================================

function ccCargarProveedores() {
    fetch(API + '?action=proveedores_lista')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('cc-proveedor');
            (data || []).forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id; opt.textContent = p.razon_social;
                sel.appendChild(opt);
            });
        })
        .catch(() => {});
}

function ccParams(extra = {}) {
    return new URLSearchParams({
        desde: document.getElementById('cc-desde').value,
        hasta: document.getElementById('cc-hasta').value,
        proveedor_id: document.getElementById('cc-proveedor').value,
        estado: document.getElementById('cc-estado').value,
        ...extra
    }).toString();
}

function ccBuscar() {
    document.getElementById('cc-tabla-body').innerHTML =
        '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';

    fetch(API + '?action=costos_compras_stats&' + ccParams())
        .then(r => r.json())
        .then(s => {
            document.getElementById('cc-st-ordenes').textContent  = s.total_ordenes ?? 0;
            document.getElementById('cc-st-subtotal').textContent = money(s.total_subtotal);
            document.getElementById('cc-st-envio').textContent    = money(s.total_envio);
            document.getElementById('cc-st-total').textContent    = money(s.total_general);
        })
        .catch(() => {});

    fetch(API + '?action=costos_compras&' + ccParams())
        .then(r => r.json())
        .then(data => {
            const rows = Array.isArray(data) ? data : [];
            document.getElementById('cc-result-count').textContent = rows.length + ' orden(es)';
            if (!rows.length) {
                document.getElementById('cc-tabla-body').innerHTML =
                    '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)">Sin resultados</td></tr>';
                return;
            }
            const badgeEstado = { borrador:'badge-gray', pendiente:'badge-warning', aprobada:'badge-primary', recibida:'badge-success', cancelada:'badge-danger' };
            document.getElementById('cc-tabla-body').innerHTML = rows.map(o => {
                const dt = new Date(o.created_at);
                return `<tr style="cursor:pointer" onclick="ccVerItems(${o.id})" title="Ver ítems de la orden">
                    <td><strong>${esc(o.numero_orden)}</strong></td>
                    <td style="font-size:.82rem;color:var(--text-muted)">${dt.toLocaleDateString('es-PE')}</td>
                    <td>${esc(o.proveedor)}</td>
                    <td><span class="badge ${badgeEstado[o.estado]||'badge-gray'}" style="text-transform:capitalize">${esc(o.estado)}</span></td>
                    <td class="text-right">${money(o.subtotal)}</td>
                    <td class="text-right">${money(o.igv)}</td>
                    <td class="text-right">${money(o.costo_envio)}</td>
                    <td class="text-right"><strong>${money(o.total)}</strong></td>
                </tr>`;
            }).join('');
        })
        .catch(() => showToast('Error al cargar el reporte de compras', 'error'));
}

function ccExportar() { repDownload(API + '?action=costos_compras_exportar&' + ccParams()); }

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Drill: orden -> items. Clic en una orden del reporte de Costos de Compras.
function ccVerItems(ordenId) {
    document.getElementById('oci-titulo').textContent = 'Ítems de la orden';
    document.getElementById('oci-info').textContent = '—';
    document.getElementById('oci-tabla-body').innerHTML =
        '<tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    openModal('modal-oc-items');
    fetch(API + '?action=compra_orden_items&orden_id=' + ordenId)
        .then(r => r.json())
        .then(d => {
            if (d.error) { showToast(d.message, 'error'); closeModal('modal-oc-items'); return; }
            const o = d.orden, items = d.items || [];
            document.getElementById('oci-titulo').textContent = 'Orden ' + o.numero_orden;
            document.getElementById('oci-info').innerHTML =
                `${esc(o.proveedor)} · ${new Date(o.created_at).toLocaleDateString('es-PE')} · <span style="text-transform:capitalize">${esc(o.estado)}</span> · Total ${money(o.total)}`;
            if (!items.length) {
                document.getElementById('oci-tabla-body').innerHTML =
                    '<tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-light)">Sin ítems</td></tr>';
                return;
            }
            document.getElementById('oci-tabla-body').innerHTML = items.map(it => {
                const clickable = parseInt(it.producto_id || 0, 10) > 0;
                const cant = parseFloat(it.cantidad || 0);
                return `<tr ${clickable ? `style="cursor:pointer" onclick="ccVerResumenProducto(${it.producto_id})" title="Ver resumen histórico de compras"` : ''}>
                    <td>${esc(it.nombre)}${clickable ? ' <i class="fas fa-chart-line" style="color:var(--primary);font-size:.72rem"></i>' : ''}${it.codigo ? `<div style="font-size:.74rem;color:var(--text-muted);font-family:monospace">${esc(it.codigo)}</div>` : ''}</td>
                    <td class="text-right">${cant}${it.unidad_medida ? ' <span style="font-size:.72rem;color:var(--text-muted)">' + esc(it.unidad_medida) + '</span>' : ''}</td>
                    <td class="text-right">${money(it.precio_unitario)}</td>
                    <td class="text-right"><strong>${money(it.subtotal)}</strong></td>
                </tr>`;
            }).join('');
        })
        .catch(() => { showToast('Error al cargar los ítems', 'error'); closeModal('modal-oc-items'); });
}

// Resumen histórico de compras de un producto (todo el periodo).
function ccVerResumenProducto(prodId) {
    document.getElementById('pr-titulo').textContent = 'Resumen de compras';
    document.getElementById('pr-body').innerHTML =
        '<div style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></div>';
    openModal('modal-prod-resumen');
    fetch(API + '?action=compra_producto_resumen&producto_id=' + prodId)
        .then(r => r.json())
        .then(d => {
            if (d.error) { showToast(d.message, 'error'); closeModal('modal-prod-resumen'); return; }
            const p = d.producto, r = d.resumen || {}, prov = d.por_proveedor || [], hist = d.historial || [];
            document.getElementById('pr-titulo').textContent = p.nombre + (p.codigo ? ' · ' + p.codigo : '');
            if (!hist.length) {
                document.getElementById('pr-body').innerHTML =
                    '<div style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-box-open"></i><br><br>Este producto no tiene compras registradas.</div>';
                return;
            }
            const stat = (label, val) => `<div style="flex:1;min-width:120px;background:var(--surface-2);border-radius:var(--radius);padding:10px 12px"><div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em">${label}</div><div style="font-size:1.05rem;font-weight:700">${val}</div></div>`;
            const cards = `<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
                ${stat('Total comprado', parseFloat(r.total_cantidad || 0).toFixed(2) + ' u')}
                ${stat('Total gastado', money(r.total_gastado))}
                ${stat('N° compras', r.n_compras || 0)}
                ${stat('P. promedio', money(r.precio_prom))}
                ${stat('P. mín / máx', money(r.precio_min) + ' / ' + money(r.precio_max))}
                ${stat('Último precio', r.precio_ultimo != null ? money(r.precio_ultimo) : '—')}
            </div>`;
            const provHtml = `<div style="font-weight:700;font-size:.85rem;margin:6px 0">Por proveedor</div>
                <div class="table-wrap" style="margin-bottom:14px"><table><thead><tr><th>Proveedor</th><th class="text-right">Cant.</th><th class="text-right">Gastado</th><th class="text-right">Compras</th><th class="text-right">P. mín/máx</th></tr></thead><tbody>
                ${prov.map(pv => `<tr><td>${esc(pv.proveedor)}</td><td class="text-right">${parseFloat(pv.cantidad || 0).toFixed(2)}</td><td class="text-right">${money(pv.gastado)}</td><td class="text-right">${pv.compras}</td><td class="text-right">${money(pv.precio_min)} / ${money(pv.precio_max)}</td></tr>`).join('')}
                </tbody></table></div>`;
            const histHtml = `<div style="font-weight:700;font-size:.85rem;margin:6px 0">Historial (${hist.length})</div>
                <div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Orden</th><th>Proveedor</th><th class="text-right">Cant.</th><th class="text-right">P. Unit.</th><th class="text-right">Subtotal</th></tr></thead><tbody>
                ${hist.map(h => `<tr><td style="font-size:.82rem">${new Date(h.created_at).toLocaleDateString('es-PE')}</td><td style="font-size:.82rem">${esc(h.numero_orden)}</td><td style="font-size:.82rem">${esc(h.proveedor)}</td><td class="text-right">${parseFloat(h.cantidad || 0).toFixed(2)}</td><td class="text-right">${money(h.precio_unitario)}</td><td class="text-right">${money(h.subtotal)}</td></tr>`).join('')}
                </tbody></table></div>`;
            document.getElementById('pr-body').innerHTML = cards + provHtml + histHtml;
        })
        .catch(() => { showToast('Error al cargar el resumen', 'error'); closeModal('modal-prod-resumen'); });
}

// ================================================================
// VENTAS
// ================================================================

function vtParams(extra = {}) {
    return new URLSearchParams({
        desde: document.getElementById('vt-desde').value,
        hasta: document.getElementById('vt-hasta').value,
        agrupar: document.getElementById('vt-agrupar').value,
        ...extra
    }).toString();
}

function vtBuscar() {
    const agrupar = document.getElementById('vt-agrupar').value;
    const labels = { vendedor: 'Vendedor', dia: 'Día', comprobante: 'Tipo de comprobante' };
    document.getElementById('vt-th-etiqueta').textContent = labels[agrupar] || 'Vendedor';
    document.getElementById('vt-tabla-titulo').textContent = 'Ventas por ' + (labels[agrupar] || 'Vendedor').toLowerCase();

    document.getElementById('vt-tabla-body').innerHTML =
        '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';

    fetch(API + '?action=ventas_reporte&' + vtParams())
        .then(r => r.json())
        .then(data => {
            const rows = Array.isArray(data) ? data : [];

            const totVentas = rows.reduce((a,r) => a + parseInt(r.total_ventas||0), 0);
            const totIngresos = rows.reduce((a,r) => a + parseFloat(r.total_ingresos||0), 0);
            const totAnuladas = rows.reduce((a,r) => a + parseInt(r.total_anuladas||0), 0);
            document.getElementById('vt-st-ventas').textContent    = totVentas;
            document.getElementById('vt-st-ingresos').textContent  = money(totIngresos);
            document.getElementById('vt-st-ticket').textContent    = money(totVentas ? totIngresos/totVentas : 0);
            document.getElementById('vt-st-anuladas').textContent  = totAnuladas;

            if (!rows.length) {
                document.getElementById('vt-tabla-body').innerHTML =
                    '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)">Sin resultados</td></tr>';
                return;
            }
            document.getElementById('vt-tabla-body').innerHTML = rows.map(r => {
                const vsProm = parseFloat(r.pct_vs_promedio);
                const vsPromCls = vsProm >= 100 ? 'color:var(--success);font-weight:600' : 'color:var(--danger);font-weight:600';
                return `<tr>
                <td style="text-transform:capitalize">${esc(r.etiqueta)}</td>
                <td class="text-right">${r.total_ventas}</td>
                <td class="text-right"><strong>${money(r.total_ingresos)}</strong></td>
                <td class="text-right">${money(r.total_igv)}</td>
                <td class="text-right">${money(r.ticket_promedio)}</td>
                <td class="text-right">${parseFloat(r.pct_participacion).toFixed(1)}%</td>
                <td class="text-right" style="${vsPromCls}">${vsProm.toFixed(0)}%</td>
                <td class="text-right">${r.total_anuladas}</td>
            </tr>`;
            }).join('');
        })
        .catch(() => showToast('Error al cargar el reporte de ventas', 'error'));
}

function vtExportar() { repDownload(API + '?action=ventas_exportar&' + vtParams()); }

// ================================================================
// INVENTARIO (Valorización)
// ================================================================

function invCargarCategorias() {
    fetch(API + '?action=categorias_lista')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('inv-categoria');
            (data || []).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id; opt.textContent = c.nombre;
                sel.appendChild(opt);
            });
        })
        .catch(() => {});
}

function invParams(extra = {}) {
    return new URLSearchParams({
        q: document.getElementById('inv-q').value,
        categoria_id: document.getElementById('inv-categoria').value,
        solo_activos: document.getElementById('inv-solo-activos').value,
        dias_venta: document.getElementById('inv-dias-venta').value || 30,
        orden: document.getElementById('inv-orden').value,
        ...extra
    }).toString();
}

function invBuscar() {
    document.getElementById('inv-tabla-body').innerHTML =
        '<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';

    fetch(API + '?action=inventario_valorizacion_stats&' + invParams())
        .then(r => r.json())
        .then(s => {
            document.getElementById('inv-st-productos').textContent   = s.total_productos ?? 0;
            document.getElementById('inv-st-valor-compra').textContent = money(s.valor_total_compra);
            document.getElementById('inv-st-valor-venta').textContent  = money(s.valor_total_venta);
            document.getElementById('inv-st-alertas').textContent      = `${s.agotados ?? 0} / ${s.stock_bajo ?? 0}`;
            document.getElementById('inv-st-riesgo').textContent       = s.en_riesgo_quiebre ?? 0;
        })
        .catch(() => {});

    fetch(API + '?action=inventario_valorizacion&' + invParams())
        .then(r => r.json())
        .then(data => {
            const rows = Array.isArray(data) ? data : [];
            document.getElementById('inv-result-count').textContent = rows.length + ' producto(s)';
            if (!rows.length) {
                document.getElementById('inv-tabla-body').innerHTML =
                    '<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-light)">Sin resultados</td></tr>';
                return;
            }
            document.getElementById('inv-tabla-body').innerHTML = rows.map(p => {
                const dias = p.dias_cobertura;
                let coberturaTxt = '<span style="color:var(--text-light)">Sin ventas</span>';
                let coberturaCls = '';
                if (dias !== null && dias !== undefined) {
                    coberturaCls = parseFloat(dias) <= 7 ? 'color:var(--danger);font-weight:700' :
                                   (parseFloat(dias) <= 15 ? 'color:var(--warning,#f59e0b);font-weight:600' : '');
                    coberturaTxt = `<span style="${coberturaCls}">${parseFloat(dias).toFixed(0)} d</span>`;
                }
                return `<tr>
                <td style="font-size:.82rem;color:var(--text-muted)">${esc(p.codigo)}</td>
                <td>${esc(p.nombre)}</td>
                <td>${esc(p.categoria)}</td>
                <td class="text-right">${p.stock}</td>
                <td class="text-right">${money(p.precio_compra)}</td>
                <td class="text-right">${money(p.precio_venta)}</td>
                <td class="text-right"><strong>${money(p.valor_inventario)}</strong></td>
                <td class="text-right">${p.cantidad_vendida_periodo}</td>
                <td class="text-right">${coberturaTxt}</td>
            </tr>`;
            }).join('');
        })
        .catch(() => showToast('Error al cargar la valorización de inventario', 'error'));
}

function invExportar() { repDownload(API + '?action=inventario_valorizacion_exportar&' + invParams()); }
function invExportarNombreStock() { repDownload(API + '?action=inventario_nombre_stock_exportar&' + invParams()); }

// ================================================================
// CAJA (Movimientos)
// ================================================================

function cjParams(extra = {}) {
    return new URLSearchParams({
        desde: document.getElementById('cj-desde').value,
        hasta: document.getElementById('cj-hasta').value,
        tipo: document.getElementById('cj-tipo').value,
        ...extra
    }).toString();
}

function cjBuscar() {
    document.getElementById('cj-tabla-body').innerHTML =
        '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';

    fetch(API + '?action=caja_movimientos_stats&' + cjParams())
        .then(r => r.json())
        .then(s => {
            document.getElementById('cj-st-movimientos').textContent = s.total_movimientos ?? 0;
            document.getElementById('cj-st-ingresos').textContent    = money(s.total_ingresos);
            document.getElementById('cj-st-egresos').textContent     = money(s.total_egresos);
            document.getElementById('cj-st-neto').textContent        = money(s.neto);
        })
        .catch(() => {});

    fetch(API + '?action=caja_movimientos&' + cjParams())
        .then(r => r.json())
        .then(data => {
            const rows = Array.isArray(data) ? data : [];
            if (!rows.length) {
                document.getElementById('cj-tabla-body').innerHTML =
                    '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-light)">Sin resultados</td></tr>';
                return;
            }
            document.getElementById('cj-tabla-body').innerHTML = rows.map(m => {
                const dt = new Date(m.created_at);
                const fechaStr = dt.toLocaleDateString('es-PE') + ' ' + dt.toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit'});
                return `<tr>
                    <td style="font-size:.82rem;color:var(--text-muted)">${fechaStr}</td>
                    <td>${esc(m.caja_nombre)}</td>
                    <td><span class="badge ${m.tipo === 'ingreso' ? 'badge-success' : 'badge-danger'}" style="text-transform:capitalize">${esc(m.tipo)}</span></td>
                    <td>${esc(m.concepto)}</td>
                    <td style="font-size:.85rem">${esc(m.usuario)}</td>
                    <td class="text-right"><strong>${money(m.monto)}</strong></td>
                </tr>`;
            }).join('');
        })
        .catch(() => showToast('Error al cargar los movimientos de caja', 'error'));
}

function cjExportar() { repDownload(API + '?action=caja_movimientos_exportar&' + cjParams()); }

// ================================================================
// PRODUCTOS (Ranking ABC / Pareto)
// ================================================================

function prCargarCategorias() {
    fetch(API + '?action=categorias_lista')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('pr-categoria');
            (data || []).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id; opt.textContent = c.nombre;
                sel.appendChild(opt);
            });
        })
        .catch(() => {});
}

function prParams(extra = {}) {
    return new URLSearchParams({
        desde: document.getElementById('pr-desde').value,
        hasta: document.getElementById('pr-hasta').value,
        categoria_id: document.getElementById('pr-categoria').value,
        orden: document.getElementById('pr-orden').value,
        ...extra
    }).toString();
}

const _prBadgeClase = { A: 'badge-success', B: 'badge-warning', C: 'badge-gray' };

function prBuscar() {
    document.getElementById('pr-tabla-body').innerHTML =
        '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';

    fetch(API + '?action=productos_ranking_stats&' + prParams())
        .then(r => r.json())
        .then(s => {
            document.getElementById('pr-st-productos').textContent = s.total_productos ?? 0;
            document.getElementById('pr-st-ingreso').textContent   = money(s.ingreso_total);
            document.getElementById('pr-st-clase-a').textContent   = s.productos_clase_a ?? 0;
            document.getElementById('pr-st-top').textContent       = s.top_nombre ? `${esc(s.top_nombre)} (${money(s.top_ingreso)})` : '—';
        })
        .catch(() => {});

    fetch(API + '?action=productos_ranking&' + prParams())
        .then(r => r.json())
        .then(data => {
            const rows = Array.isArray(data) ? data : [];
            document.getElementById('pr-result-count').textContent = rows.length + ' producto(s)';
            if (!rows.length) {
                document.getElementById('pr-tabla-body').innerHTML =
                    '<tr><td colspan="8"><div class="empty-state"><i class="fas fa-ranking-star"></i>Sin ventas en el periodo seleccionado</div></td></tr>';
                return;
            }
            document.getElementById('pr-tabla-body').innerHTML = rows.map(p => `<tr>
                <td style="font-size:.82rem;color:var(--text-muted)">${esc(p.codigo)}</td>
                <td>${esc(p.nombre)}</td>
                <td>${esc(p.categoria)}</td>
                <td class="text-right">${p.cantidad_vendida}</td>
                <td class="text-right"><strong>${money(p.ingreso)}</strong></td>
                <td class="text-right">${money(p.margen)}</td>
                <td class="text-right">${parseFloat(p.pct_acumulado).toFixed(1)}%</td>
                <td style="text-align:center"><span class="badge ${_prBadgeClase[p.clase_abc] || 'badge-gray'}">${esc(p.clase_abc)}</span></td>
            </tr>`).join('');
        })
        .catch(() => showToast('Error al cargar el ranking de productos', 'error'));
}

function prExportar() { repDownload(API + '?action=productos_ranking_exportar&' + prParams()); }

// ================================================================
// ANULACIONES
// ================================================================

function anParams(extra = {}) {
    return new URLSearchParams({
        desde: document.getElementById('an-desde').value,
        hasta: document.getElementById('an-hasta').value,
        ...extra
    }).toString();
}

function anBuscar() {
    document.getElementById('an-tabla-body').innerHTML =
        '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';

    fetch(API + '?action=anulaciones_stats&' + anParams())
        .then(r => r.json())
        .then(s => {
            document.getElementById('an-st-total').textContent    = s.total_anuladas ?? 0;
            document.getElementById('an-st-monto').textContent    = money(s.monto_anulado);
            document.getElementById('an-st-vendedor').textContent = s.vendedor_top ? `${esc(s.vendedor_top)} (${s.vendedor_top_count})` : '—';
            document.getElementById('an-st-producto').textContent = s.producto_top ? `${esc(s.producto_top)} (${s.producto_top_count})` : '—';
        })
        .catch(() => {});

    fetch(API + '?action=anulaciones_listar&' + anParams())
        .then(r => r.json())
        .then(data => {
            const rows = Array.isArray(data) ? data : [];
            document.getElementById('an-result-count').textContent = rows.length + ' anulación(es)';
            if (!rows.length) {
                document.getElementById('an-tabla-body').innerHTML =
                    '<tr><td colspan="6"><div class="empty-state"><i class="fas fa-ban"></i>No hay ventas anuladas en el periodo seleccionado</div></td></tr>';
                return;
            }
            document.getElementById('an-tabla-body').innerHTML = rows.map(v => {
                const dt = new Date(v.created_at);
                const fechaStr = dt.toLocaleDateString('es-PE') + ' ' + dt.toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit'});
                return `<tr>
                <td><strong>${esc(v.numero_venta)}</strong></td>
                <td style="font-size:.82rem;color:var(--text-muted)">${fechaStr}</td>
                <td>${esc(v.vendedor)}</td>
                <td class="text-right">${money(v.total)}</td>
                <td style="font-size:.85rem">${esc(v.motivo_anulacion)}</td>
                <td style="font-size:.85rem;color:var(--text-muted)">${esc(v.productos)}</td>
            </tr>`;
            }).join('');
        })
        .catch(() => showToast('Error al cargar las anulaciones', 'error'));
}

function anExportar() { repDownload(API + '?action=anulaciones_exportar&' + anParams()); }

// ================================================================
// STOCK PARALIZADO
// ================================================================

let pzSeleccionados = new Set();
let pzUltimaLista = [];     // lo que devuelve el servidor, sin filtrar
let pzListaFiltrada = [];   // lo que se muestra/pagina, tras aplicar el buscador

// dias_sin_venta queda fijo en 60 por ahora -- el filtro de dias/categoria se
// quito de la UI a pedido del usuario (se reemplaza mas adelante por tarjetas
// nuevas); el buscador de abajo filtra en el cliente sobre lo ya traido.
function pzParams(extra = {}) {
    return new URLSearchParams({
        dias_sin_venta: 60,
        ...extra
    }).toString();
}

function pzFiltrar() {
    const q = document.getElementById('pz-q').value.toLowerCase().trim();
    pzListaFiltrada = !q ? pzUltimaLista : pzUltimaLista.filter(p =>
        (p.nombre || '').toLowerCase().includes(q) || (p.codigo || '').toLowerCase().includes(q)
    );
    document.getElementById('pz-result-count').textContent = pzListaFiltrada.length + ' producto(s)';
    if (!pzListaFiltrada.length) {
        document.getElementById('pz-tabla-body').innerHTML =
            '<tr><td colspan="7"><div class="empty-state"><i class="fas fa-circle-check"></i>No se encontraron productos</div></td></tr>';
        document.getElementById('pz-pagination').innerHTML = '';
        return;
    }
    pzRenderPage(1);
}

function pzActualizarBotonPromo() {
    document.getElementById('pz-btn-crear-promo').disabled = pzSeleccionados.size === 0;
}

// La seleccion (pzSeleccionados) persiste entre paginas -- "check all" solo
// marca/desmarca las filas de la pagina actual, igual que el resto de la
// seleccion visible; el contador y el boton de crear promocion reflejan el
// total acumulado, no solo lo que se ve en pantalla.
function pzToggleAll(checkboxTodos) {
    document.querySelectorAll('.pz-check-item').forEach(cb => {
        cb.checked = checkboxTodos.checked;
        const id = parseInt(cb.dataset.id);
        if (checkboxTodos.checked) pzSeleccionados.add(id); else pzSeleccionados.delete(id);
    });
    pzActualizarBotonPromo();
}

function pzToggleUno(cb) {
    const id = parseInt(cb.dataset.id);
    if (cb.checked) pzSeleccionados.add(id); else pzSeleccionados.delete(id);
    document.getElementById('pz-check-all').checked =
        document.querySelectorAll('.pz-check-item').length > 0 &&
        document.querySelectorAll('.pz-check-item:not(:checked)').length === 0;
    pzActualizarBotonPromo();
}

const PZ_PAGE_SIZE = 50;
let pzPage = 1;

function pzBuscar() {
    pzSeleccionados = new Set();
    pzActualizarBotonPromo();
    pzPage = 1;
    document.getElementById('pz-q').value = '';
    document.getElementById('pz-tabla-body').innerHTML =
        '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    document.getElementById('pz-pagination').innerHTML = '';

    fetch(API + '?action=stock_paralizado&' + pzParams())
        .then(r => r.json())
        .then(data => {
            pzUltimaLista = Array.isArray(data) ? data : [];
            if (!pzUltimaLista.length) {
                document.getElementById('pz-result-count').textContent = '0 producto(s)';
                document.getElementById('pz-tabla-body').innerHTML =
                    '<tr><td colspan="7"><div class="empty-state"><i class="fas fa-circle-check"></i>No hay productos paralizados en este periodo</div></td></tr>';
                return;
            }
            pzFiltrar();
        })
        .catch(() => showToast('Error al cargar el stock paralizado', 'error'));
}

function pzRenderPage(page) {
    pzPage = page;
    const start = (page - 1) * PZ_PAGE_SIZE;
    const slice = pzListaFiltrada.slice(start, start + PZ_PAGE_SIZE);

    document.getElementById('pz-tabla-body').innerHTML = slice.map(p => `<tr>
        <td><input type="checkbox" class="pz-check-item" data-id="${p.id}" ${pzSeleccionados.has(p.id) ? 'checked' : ''} onchange="pzToggleUno(this)"></td>
        <td style="font-size:.82rem;color:var(--text-muted)">${esc(p.codigo)}</td>
        <td>${esc(p.nombre)}</td>
        <td>${esc(p.categoria)}</td>
        <td class="text-right">${p.stock}</td>
        <td class="text-right"><strong>${money(p.valor_inventario)}</strong></td>
        <td style="font-size:.85rem;color:var(--text-muted)">${p.ultima_venta ? new Date(p.ultima_venta).toLocaleDateString('es-PE') : 'Nunca'}</td>
    </tr>`).join('');

    document.getElementById('pz-check-all').checked =
        slice.length > 0 && slice.every(p => pzSeleccionados.has(p.id));

    pzRenderPagination(pzListaFiltrada.length, page);
}

function pzRenderPagination(total, page) {
    const pages = Math.ceil(total / PZ_PAGE_SIZE);
    const el = document.getElementById('pz-pagination');
    if (pages <= 1) { el.innerHTML = ''; return; }

    const shown = new Set([1, pages]);
    for (let i = Math.max(1, page - 1); i <= Math.min(pages, page + 1); i++) shown.add(i);
    const sorted = [...shown].sort((a, b) => a - b);

    const items = [];
    let prev = 0;
    sorted.forEach(n => {
        if (n - prev > 1) items.push('...');
        items.push(n);
        prev = n;
    });

    let html = `<button class="pg-btn" onclick="pzRenderPage(${page - 1})" ${page === 1 ? 'disabled' : ''}>&#8249;</button>`;
    items.forEach(item => {
        if (item === '...') {
            html += `<span class="pg-ellipsis">…</span>`;
        } else {
            html += `<button class="pg-btn${item === page ? ' active' : ''}" onclick="pzRenderPage(${item})">${item}</button>`;
        }
    });
    html += `<button class="pg-btn" onclick="pzRenderPage(${page + 1})" ${page === pages ? 'disabled' : ''}>&#8250;</button>`;

    el.innerHTML = html;
}

function pzExportar() { repDownload(API + '?action=stock_paralizado_exportar&' + pzParams()); }

// La gestion de Promociones vive ahora en el modulo Inventario. Este boton
// guarda los productos seleccionados en sessionStorage y redirige a
// Inventario -> tab Promociones, que abre el modal de "Nueva Promoción" ya
// precargado con ellos (ver inventario/index.php: pmConsumirPreseleccion()).
function pzAbrirPromoConSeleccionados() {
    if (!pzSeleccionados.size) return;
    const productos = (pzUltimaLista || [])
        .filter(p => pzSeleccionados.has(p.id))
        .map(p => ({ id: p.id, nombre: p.nombre, codigo: p.codigo }));
    try {
        sessionStorage.setItem('promo_preseleccion', JSON.stringify(productos));
    } catch (e) { /* modo privado: seguimos igual, abrira el modal vacio */ }
    window.location.href = BASE + 'modules/inventario/index.php?tab=promociones&promo=nueva';
}

// NOTA: la gestión de Promociones (listado, alta y activar/desactivar) se
// movió al módulo Inventario (modules/inventario/index.php, tab Promociones).
// Aquí solo queda el atajo pzAbrirPromoConSeleccionados(), que redirige allá.

let _pzBuscarTimer;
document.getElementById('pz-q').addEventListener('input', () => {
    clearTimeout(_pzBuscarTimer);
    _pzBuscarTimer = setTimeout(pzFiltrar, 200);
});

// ================================================================
// TOASTS
// ================================================================
function showToast(msg, type = 'info') {
    const icons = { success: 'check-circle', error: 'exclamation-circle', info: 'info-circle' };
    const toast = document.createElement('div');
    toast.className = `app-toast ${type}`;
    toast.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i> ${msg}`;
    document.getElementById('toast-container').appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}

// ================================================================
// INIT
// ================================================================
ccCargarProveedores();
ccBuscar();
_repTabLoaded.costos = true;
</script>

<?php include '../../includes/footer.php'; ?>
