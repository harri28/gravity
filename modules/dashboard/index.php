<?php
// ============================================================
// ARCHIVO: farmacia/modules/dashboard/index.php
// DESCRIPCIÓN: Dashboard principal — resumen ejecutivo
// ============================================================

require_once '../../config/database.php';

$required_roles = ['admin', 'gerente', 'cajero'];
$base_path      = '../../';
$current_module = 'dashboard';
$current_page   = 'inicio';
$page_title     = 'Dashboard — Gravity';
$breadcrumb     = '<strong>Dashboard</strong>';

include '../../includes/header.php';
?>

<style>
/* ---- Layout ---- */
.dash-wrap     { padding: 24px; display: flex; flex-direction: column; gap: 24px; }
.section-title { font-size: .75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .08em; margin-bottom: 12px; }

/* ---- KPI cards ---- */
.kpi-grid      { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; }
.kpi-card      {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 14px 16px;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
    transition: box-shadow var(--transition);
}
.kpi-card:hover { box-shadow: var(--shadow-md); }
.kpi-card .accent {
    position: absolute; top: 0; left: 0;
    width: 4px; height: 100%;
    border-radius: var(--radius-lg) 0 0 var(--radius-lg);
}
.kpi-icon {
    width: 32px; height: 32px;
    border-radius: var(--radius);
    display: flex; align-items: center; justify-content: center;
    font-size: .85rem; margin-bottom: 8px;
}
.kpi-label { font-size: .72rem; color: var(--text-muted); font-weight: 500; margin-bottom: 3px; }
.kpi-value { font-size: 1.25rem; font-weight: 700; color: var(--text); line-height: 1; }
.kpi-sub   { font-size: .68rem; color: var(--text-light); margin-top: 4px; }
.kpi-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 20px;
    font-size: .68rem; font-weight: 700;
}

/* ---- Two-column layout ---- */
.dash-cols   { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
.dash-cols-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
@media (max-width: 1100px) {
    .dash-cols   { grid-template-columns: 1fr; }
    .dash-cols-3 { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 680px) {
    .dash-cols-3 { grid-template-columns: 1fr; }
    .kpi-grid    { grid-template-columns: 1fr 1fr; }
}

/* ---- Card ---- */
.d-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;            /* llena la columna -> tarjetas de igual altura por fila */
}
.d-card-head {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-light);
    display: flex; align-items: center; justify-content: space-between;
}
.d-card-head h3 { font-size: .92rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.d-card-body    { padding: 20px; flex: 1 1 auto; min-height: 0; }
/* Tarjetas "cortas": centran su contenido en el alto igualado (Opcion A) */
#metodo-body, #caja-body { display: flex; flex-direction: column; justify-content: center; }
/* Alertas: cuerpo en columna para que el pie de acciones quede pegado abajo */
#alerta-body { display: flex; flex-direction: column; }
/* Botones de accion de las tarjetas */
.btn-wa { background:#25d366; color:#fff; border:none; border-radius:8px; padding:7px 12px;
          font-size:.78rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-wa:hover { filter:brightness(.95); }
.btn-wa:disabled { opacity:.5; cursor:not-allowed; }
.btn-mini { background:var(--surface-2); border:1px solid var(--border); border-radius:8px;
            padding:7px 9px; cursor:pointer; color:var(--text-muted); font-size:.78rem; }
.btn-mini:hover { color:var(--text); }

/* ---- Table ---- */
.dash-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.dash-table th {
    padding: 8px 12px; text-align: left;
    font-size: .72rem; font-weight: 600; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: .06em;
    border-bottom: 1px solid var(--border);
}
.dash-table td  { padding: 10px 12px; border-bottom: 1px solid var(--border-light); vertical-align: middle; }
.dash-table tr:last-child td { border-bottom: none; }
.dash-table tr:hover td { background: var(--surface-2); }

/* ---- Stock badge ---- */
.badge-danger  { background: var(--danger-light);  color: var(--danger);  }
.badge-warning { background: var(--warning-light); color: var(--warning); }
.badge-success { background: var(--success-light); color: var(--success); }
.badge-info    { background: var(--info-light);    color: var(--info);    }
.badge-muted   { background: var(--surface-2);     color: var(--text-muted); }

/* ---- Caja status ---- */
.caja-status {
    display: flex; flex-direction: column; gap: 10px;
}
.caja-row {
    display: flex; justify-content: space-between; align-items: center;
    font-size: .85rem;
}
.caja-row .label { color: var(--text-muted); }
.caja-row .val   { font-weight: 600; }
.caja-closed {
    text-align: center; padding: 20px 0;
    color: var(--text-muted); font-size: .88rem;
}
.caja-closed i { font-size: 2rem; display: block; margin-bottom: 8px; color: var(--border); }

/* ---- Método pago list ---- */
.metodo-list { display: flex; flex-direction: column; gap: 10px; }
.metodo-row  { display: flex; align-items: center; gap: 10px; }
.metodo-bar-bg { flex: 1; height: 6px; background: var(--surface-2); border-radius: 99px; overflow: hidden; }
.metodo-bar    { height: 100%; border-radius: 99px; background: var(--primary); transition: width .5s ease; }
.metodo-label  { font-size: .78rem; min-width: 80px; font-weight: 500; }
.metodo-val    { font-size: .78rem; color: var(--text-muted); min-width: 70px; text-align: right; }

/* ---- Skeleton loader ---- */
.skel { background: linear-gradient(90deg, var(--surface-2) 25%, var(--border-light) 50%, var(--surface-2) 75%); background-size: 400% 100%; animation: skel-shine 1.4s ease infinite; border-radius: 6px; }
@keyframes skel-shine { 0%{background-position:100% 50%} 100%{background-position:0% 50%} }

/* ---- Chart container ---- */
.chart-wrap { position: relative; height: 220px; }

/* ---- Filtro de periodo ---- */
.per-bar {
    display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 10px 14px; box-shadow: var(--shadow);
}
.per-bar .per-lbl { font-size: .75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; margin-right: 4px; }
.per-btn {
    border: 1px solid var(--border); background: var(--surface); color: var(--text);
    border-radius: 99px; padding: 5px 14px; font-size: .8rem; font-weight: 500; cursor: pointer;
    transition: all var(--transition);
}
.per-btn:hover  { background: var(--surface-2); }
.per-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }
.per-rango { display: none; align-items: center; gap: 6px; font-size: .8rem; }
.per-rango.show { display: inline-flex; }
.per-rango input { border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 4px 8px; background: var(--surface); color: var(--text); font-size: .8rem; }
.per-auto { margin-left: auto; font-size: .72rem; color: var(--text-light); display: flex; align-items: center; gap: 6px; }
.per-refresh { border: none; background: transparent; color: var(--text-muted); cursor: pointer; padding: 4px; }
.per-refresh:hover { color: var(--primary); }

/* ---- Comparativo ---- */
.kpi-cmp { display: inline-flex; align-items: center; gap: 3px; font-size: .68rem; font-weight: 700; margin-top: 5px; }
.kpi-cmp.up   { color: var(--success); }
.kpi-cmp.down { color: var(--danger); }
.kpi-cmp.flat { color: var(--text-light); }
.kpi-cmp small { font-weight: 400; color: var(--text-light); }

/* ---- Filas clicables ---- */
.clickable { cursor: pointer; }
.clickable:hover { background: var(--surface-2); }

/* ---- Toggle pequeno ---- */
.seg { display: inline-flex; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; }
.seg button { border: none; background: var(--surface); color: var(--text-muted); padding: 4px 8px; font-size: .7rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
.d-card-head { gap: 8px; flex-wrap: wrap; }
.d-card-head h3 { white-space: nowrap; }
.kpi-cmp { white-space: nowrap; }
.seg button + button { border-left: 1px solid var(--border); }
.seg button.active { background: var(--primary); color: #fff; }

/* ---- Por vencer ---- */
.venc-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; padding: 16px 20px 4px; }
.venc-box  { border-radius: var(--radius); padding: 10px 12px; text-align: center; }
.venc-box .n { font-size: 1.4rem; font-weight: 700; line-height: 1.1; }
.venc-box .t { font-size: .7rem; font-weight: 600; margin-top: 2px; }
@media (max-width: 680px) { .venc-grid { grid-template-columns: 1fr 1fr; } }

/* ---- Modal detalle ---- */
.dm-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.45); display: none; align-items: center; justify-content: center; z-index: 1200; padding: 16px; }
.dm-overlay.show { display: flex; }
.dm-box { background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); width: 100%; max-width: 760px; max-height: 84vh; display: flex; flex-direction: column; }
.dm-head { padding: 14px 20px; border-bottom: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.dm-head h3 { font-size: .95rem; font-weight: 600; }
.dm-close { border: none; background: transparent; font-size: 1.1rem; color: var(--text-muted); cursor: pointer; }
.dm-body { overflow: auto; }
</style>

<?php $esAdmin = isAdmin(); ?>
<div class="dash-wrap">

    <!-- Filtro global de periodo -->
    <div class="per-bar">
        <span class="per-lbl"><i class="fas fa-calendar-alt"></i> Período</span>
        <button class="per-btn" data-per="hoy"     onclick="setPeriodo('hoy')">Hoy</button>
        <button class="per-btn" data-per="semana"  onclick="setPeriodo('semana')">Esta semana</button>
        <button class="per-btn" data-per="mes"     onclick="setPeriodo('mes')">Este mes</button>
        <button class="per-btn" data-per="mes_ant" onclick="setPeriodo('mes_ant')">Mes anterior</button>
        <button class="per-btn" data-per="rango"   onclick="setPeriodo('rango')">Rango…</button>
        <span class="per-rango" id="per-rango">
            <input type="date" id="per-desde" onchange="aplicarRango()">
            <span>a</span>
            <input type="date" id="per-hasta" onchange="aplicarRango()">
        </span>
        <span class="per-auto">
            <span id="per-actualizado"></span>
            <button class="per-refresh" onclick="refreshAll()" title="Actualizar ahora"><i class="fas fa-sync-alt" id="per-spin"></i></button>
        </span>
    </div>

    <!-- KPI Row -->
    <div>
        <p class="section-title">Resumen · <span id="per-titulo"></span></p>
        <div class="row g-3" id="kpi-grid">
            <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="kpi-card">
                    <div class="accent skel" style="background:transparent"></div>
                    <div class="skel" style="width:40px;height:40px;margin-bottom:12px"></div>
                    <div class="skel" style="width:60%;height:.78rem;margin-bottom:6px"></div>
                    <div class="skel" style="width:80%;height:1.6rem"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Chart + Caja -->
    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="d-card">
                <div class="d-card-head">
                    <h3><i class="fas fa-chart-line" style="color:var(--primary)"></i> Ventas del período</h3>
                    <span style="font-size:.72rem;color:var(--text-light)">Clic en una barra para ver las ventas del día</span>
                </div>
                <div class="d-card-body">
                    <div class="chart-wrap"><canvas id="chart-ventas"></canvas></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="d-card">
                <div class="d-card-head">
                    <h3><i class="fas fa-cash-register" style="color:var(--success)"></i> Estado de Caja</h3>
                    <span id="caja-badge" class="kpi-badge badge-muted">Cargando…</span>
                </div>
                <div class="d-card-body" id="caja-body">
                    <div class="caja-closed"><i class="fas fa-lock"></i>Cargando…</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Métodos de pago + Top + Alertas stock -->
    <div class="row g-4">
        <div class="col-12 col-md-6 col-lg-4">
            <div class="d-card">
                <div class="d-card-head">
                    <h3><i class="fas fa-wallet" style="color:var(--info)"></i> Métodos de Pago</h3>
                    <span style="font-size:.72rem;color:var(--text-muted)" id="metodo-per"></span>
                </div>
                <div class="d-card-body" id="metodo-body">
                    <div class="skel" style="height:140px"></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
            <div class="d-card">
                <div class="d-card-head">
                    <h3><i class="fas fa-fire" style="color:var(--warning)"></i> Top 5 Productos</h3>
                    <div class="seg" id="top-seg">
                        <button data-m="unidades" class="active" onclick="setTopMetrica('unidades')">Uds.</button>
                        <button data-m="ingresos" onclick="setTopMetrica('ingresos')">Ingresos</button>
                        <?php if ($esAdmin): ?><button data-m="ganancia" onclick="setTopMetrica('ganancia')">Ganancia</button><?php endif; ?>
                    </div>
                </div>
                <div class="d-card-body" id="top-body" style="padding:0">
                    <div class="skel" style="height:200px;margin:20px"></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
            <div class="d-card">
                <div class="d-card-head">
                    <h3><i class="fas fa-exclamation-triangle" style="color:var(--danger)"></i> Alertas de Stock</h3>
                    <span id="alerta-count" class="kpi-badge badge-danger" style="display:none"></span>
                </div>
                <div class="d-card-body" id="alerta-body" style="padding:0">
                    <div class="skel" style="height:200px;margin:20px"></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($esAdmin): ?>
    <!-- Productos por vencer -->
    <div class="d-card">
        <div class="d-card-head">
            <h3><i class="fas fa-hourglass-half" style="color:var(--warning)"></i> Productos por vencer</h3>
            <a href="<?= $base_path ?>modules/inventario/index.php" style="font-size:.78rem;color:var(--primary);font-weight:500">
                Ir a inventario <i class="fas fa-arrow-right" style="font-size:.68rem"></i>
            </a>
        </div>
        <div id="venc-body"><div class="skel" style="height:120px;margin:20px"></div></div>
    </div>
    <?php endif; ?>

    <!-- Últimas ventas del día -->
    <div class="d-card">
        <div class="d-card-head">
            <h3><i class="fas fa-receipt" style="color:var(--primary)"></i> Últimas ventas de hoy</h3>
            <a href="<?= $base_path ?>modules/ventas/historial.php" style="font-size:.78rem;color:var(--primary);font-weight:500">
                Ver todo <i class="fas fa-arrow-right" style="font-size:.68rem"></i>
            </a>
        </div>
        <div style="overflow-x:auto">
            <table class="dash-table" id="tbl-ventas">
                <thead>
                    <tr>
                        <th>N° Venta</th>
                        <th>Cliente</th>
                        <th>Método</th>
                        <th>Comprobante</th>
                        <th>Hora</th>
                        <th>Total</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted)">
                        <i class="fas fa-spinner fa-spin"></i> Cargando…
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal detalle de ventas -->
<div class="dm-overlay" id="dm-overlay" onclick="if(event.target===this)cerrarDetalle()">
    <div class="dm-box">
        <div class="dm-head">
            <h3 id="dm-titulo">Ventas</h3>
            <button class="dm-close" onclick="cerrarDetalle()" aria-label="Cerrar"><i class="fas fa-times"></i></button>
        </div>
        <div class="dm-body" id="dm-body"></div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<script>
const API      = '../../modules/dashboard/api.php';
const ES_ADMIN = <?= $esAdmin ? 'true' : 'false' ?>;
const EMPRESA  = <?= json_encode(sesionTenantNombre(), JSON_UNESCAPED_UNICODE) ?>;
const SUCURSAL = <?= json_encode(sesionSucursal(), JSON_UNESCAPED_UNICODE) ?>;
const AUTO_REFRESH_MS = 120000;
let alertasData = [];
let chartVentas = null;
let serieActual = [];

// ---- Helpers ----
const fmt  = (n) => 'S/ ' + parseFloat(n).toLocaleString('es-PE', {minimumFractionDigits:2, maximumFractionDigits:2});
const fmtN = (n) => parseFloat(n).toLocaleString('es-PE');
const esc  = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

// ---- Toast minimo (el dashboard no traia uno propio) ----
function showToast(msg, tipo) {
    const colores = { success:'#16a34a', error:'#dc2626', info:'#2563eb', warning:'#d97706' };
    const el = document.createElement('div');
    el.textContent = msg;
    el.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:9999;'
        + 'background:' + (colores[tipo] || '#374151') + ';color:#fff;padding:10px 18px;border-radius:10px;'
        + 'font-size:.85rem;box-shadow:0 6px 24px rgba(0,0,0,.2);max-width:90vw;text-align:center;opacity:0;transition:opacity .2s';
    document.body.appendChild(el);
    requestAnimationFrame(() => { el.style.opacity = '1'; });
    setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 250); }, 3000);
}

const METODO_LABEL = { efectivo:'Efectivo', yape:'Yape', plin:'Plin', tarjeta:'Visa/Mastercard', transferencia:'Transferencia', credito:'Crédito' };
const METODO_COLOR = { efectivo:'var(--success)', yape:'#8b5cf6', plin:'#06b6d4', tarjeta:'var(--primary)', transferencia:'var(--warning)', credito:'var(--danger)' };
const COMP_LABEL   = { ticket:'Ticket', boleta:'Boleta', factura:'Factura' };
const PAGO_ICONS   = { efectivo:'💵', yape:'📱', plin:'📱', tarjeta:'💳', transferencia:'🏦' };

// ---- Estado del filtro (se recuerda en el navegador) ----
const PERIODOS = ['hoy','semana','mes','mes_ant','rango'];
const filtro = { periodo:'hoy', desde:'', hasta:'', topMetrica:'unidades' };

function guardarFiltro() { try { localStorage.setItem('dash_filtro', JSON.stringify(filtro)); } catch (e) {} }
function cargarFiltro() {
    try {
        const s = JSON.parse(localStorage.getItem('dash_filtro') || 'null');
        if (s && PERIODOS.includes(s.periodo)) {
            filtro.periodo = s.periodo;
            filtro.desde   = /^\d{4}-\d{2}-\d{2}$/.test(s.desde || '') ? s.desde : '';
            filtro.hasta   = /^\d{4}-\d{2}-\d{2}$/.test(s.hasta || '') ? s.hasta : '';
            if (['unidades','ingresos'].includes(s.topMetrica) || (ES_ADMIN && s.topMetrica === 'ganancia')) filtro.topMetrica = s.topMetrica;
        }
    } catch (e) {}
    if (filtro.periodo === 'rango' && !(filtro.desde && filtro.hasta)) filtro.periodo = 'hoy';
}

function qsPeriodo(extra) {
    const p = new URLSearchParams({ periodo: filtro.periodo });
    if (filtro.periodo === 'rango') { p.set('desde', filtro.desde); p.set('hasta', filtro.hasta); }
    if (extra) Object.keys(extra).forEach(k => p.set(k, extra[k]));
    return p.toString();
}

const fmtCorta = (iso) => { const p = iso.split('-'); return p[2] + '/' + p[1]; };
function etiquetaPeriodo() {
    switch (filtro.periodo) {
        case 'hoy':     return 'Hoy · ' + new Date().toLocaleDateString('es-PE', {weekday:'long', day:'numeric', month:'long'});
        case 'semana':  return 'Esta semana';
        case 'mes':     return 'Este mes';
        case 'mes_ant': return 'Mes anterior';
        default:        return fmtCorta(filtro.desde) + ' al ' + fmtCorta(filtro.hasta);
    }
}
const etiquetaCorta = () => ({hoy:'Hoy', semana:'Semana', mes:'Mes', mes_ant:'Mes anterior'}[filtro.periodo] || (fmtCorta(filtro.desde) + ' al ' + fmtCorta(filtro.hasta)));

function pintarFiltro() {
    document.querySelectorAll('.per-btn').forEach(b => b.classList.toggle('active', b.dataset.per === filtro.periodo));
    document.getElementById('per-rango').classList.toggle('show', filtro.periodo === 'rango');
    document.getElementById('per-desde').value = filtro.desde;
    document.getElementById('per-hasta').value = filtro.hasta;
    document.getElementById('per-titulo').textContent = etiquetaPeriodo();
    document.getElementById('metodo-per').textContent = etiquetaCorta();
    document.querySelectorAll('#top-seg button').forEach(b => b.classList.toggle('active', b.dataset.m === filtro.topMetrica));
}

function setPeriodo(p) {
    filtro.periodo = p;
    if (p === 'rango' && !(filtro.desde && filtro.hasta)) {
        const hoy = new Date().toISOString().slice(0, 10);
        filtro.desde = filtro.hasta = hoy;
    }
    guardarFiltro(); pintarFiltro();
    refreshPeriodo();
}
function aplicarRango() {
    const d = document.getElementById('per-desde').value, h = document.getElementById('per-hasta').value;
    if (!d || !h) return;
    filtro.desde = d; filtro.hasta = h;
    guardarFiltro(); pintarFiltro();
    refreshPeriodo();
}
function setTopMetrica(m) {
    filtro.topMetrica = m; guardarFiltro(); pintarFiltro(); loadTop();
}

// ---- Comparativo vs periodo anterior ----
function cmpHtml(act, ant, opts) {
    opts = opts || {};
    act = parseFloat(act || 0); ant = parseFloat(ant || 0);
    if (ant === 0 && act === 0) return '<div class="kpi-cmp flat">— <small>sin movimiento</small></div>';
    if (ant === 0) return '<div class="kpi-cmp flat">nuevo <small>vs. anterior</small></div>';
    const pct = (act - ant) / Math.abs(ant) * 100;
    if (Math.abs(pct) < 0.05) return '<div class="kpi-cmp flat">= <small>igual que antes</small></div>';
    const sube = pct > 0;
    const cls  = opts.neutral ? 'flat' : ((sube !== !!opts.inverso) ? 'up' : 'down');
    return `<div class="kpi-cmp ${cls}"><i class="fas fa-arrow-${sube ? 'up' : 'down'}"></i> ${Math.abs(pct).toFixed(1)}% <small>vs. anterior</small></div>`;
}

// ---- KPIs (periodo + inventario) ----
let _inv = null;
// Contadores para descartar respuestas viejas: al cambiar de periodo varias
// veces seguidas, una respuesta lenta del periodo anterior no debe pisar la actual.
const _seq = { kpis: 0, chart: 0, metodos: 0, top: 0 };

async function loadKpis() {
    const seq = ++_seq.kpis;
    const [rk, rr] = await Promise.all([
        fetch(API + '?action=kpis&' + qsPeriodo()),
        _inv ? Promise.resolve(null) : fetch(API + '?action=resumen'),
    ]);
    const k = await rk.json();
    if (rr) { const r = await rr.json(); _inv = r.inventario; renderCaja(r.caja); }
    if (seq !== _seq.kpis) return;
    const a = k.actual, p = k.anterior, inv = _inv;

    const cards = [
        { label:'Ingresos', value:fmt(a.ingresos), sub:a.anuladas + ' venta(s) anulada(s)', cmp:cmpHtml(a.ingresos, p.ingresos),
          icon:'fas fa-coins', iconBg:'var(--primary-light)', iconColor:'var(--primary)', accent:'var(--primary)' },
        { label:'Ventas realizadas', value:fmtN(a.total_ventas), sub:'Ticket promedio ' + fmt(a.ticket_promedio), cmp:cmpHtml(a.total_ventas, p.total_ventas),
          icon:'fas fa-receipt', iconBg:'var(--success-light)', iconColor:'var(--success)', accent:'var(--success)' },
    ];
    if (ES_ADMIN) {
        cards.push(
            { label:'Ganancia bruta', value:fmt(a.ganancia), sub:'Costo estimado (precio de compra actual)', cmp:cmpHtml(a.ganancia, p.ganancia),
              icon:'fas fa-chart-line', iconBg:'#ecfdf5', iconColor:'#059669', accent:'#059669' },
            { label:'Compras', value:fmt(a.compras), sub:a.compras_n + ' orden(es) de compra', cmp:cmpHtml(a.compras, p.compras, {neutral:true}),
              icon:'fas fa-truck-loading', iconBg:'var(--warning-light)', iconColor:'var(--warning)', accent:'var(--warning)' },
            { label:'Valor de Inventario', value:fmt(inv.valor_inventario), sub:inv.total_activos + ' productos activos', cmp:'',
              icon:'fas fa-boxes', iconBg:'var(--info-light)', iconColor:'var(--info)', accent:'var(--info)' }
        );
    }
    cards.push({ label:'Stock Bajo', value:inv.stock_bajo, sub:inv.agotados + ' producto(s) agotado(s)', cmp:'',
        icon:'fas fa-exclamation-circle',
        iconBg: inv.stock_bajo > 0 ? 'var(--danger-light)' : 'var(--success-light)',
        iconColor: inv.stock_bajo > 0 ? 'var(--danger)' : 'var(--success)',
        accent: inv.stock_bajo > 0 ? 'var(--danger)' : 'var(--success)' });

    document.getElementById('kpi-grid').innerHTML = cards.map(c => `
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card">
                <div class="accent" style="background:${c.accent}"></div>
                <div class="kpi-icon" style="background:${c.iconBg};color:${c.iconColor}"><i class="${c.icon}"></i></div>
                <div class="kpi-label">${c.label}</div>
                <div class="kpi-value">${c.value}</div>
                <div class="kpi-sub">${c.sub}</div>
                ${c.cmp}
            </div>
        </div>`).join('');
}

function renderCaja(caja) {
    const badge = document.getElementById('caja-badge');
    const body  = document.getElementById('caja-body');

    if (!caja.abierta) {
        badge.textContent = 'Cerrada';
        badge.className   = 'kpi-badge badge-danger';
        body.innerHTML    = `<div class="caja-closed"><i class="fas fa-lock"></i>No hay caja abierta</div>`;
        return;
    }

    badge.textContent = 'Abierta';
    badge.className   = 'kpi-badge badge-success';

    const hora = caja.apertura_at
        ? new Date(caja.apertura_at).toLocaleTimeString('es-PE', {hour:'2-digit', minute:'2-digit'})
        : '—';

    body.innerHTML = `
        <div class="caja-status">
            <div class="caja-row"><span class="label">Responsable</span><span class="val">${esc(caja.responsable) || '—'}</span></div>
            <div class="caja-row"><span class="label">Apertura</span><span class="val">${hora}</span></div>
            <div class="caja-row"><span class="label">Saldo inicial</span><span class="val">${fmt(caja.saldo_inicial)}</span></div>
            <div class="caja-row"><span class="label">Ventas del turno</span><span class="val">${caja.ventas_count} · ${fmt(caja.ventas_total)}</span></div>
            <hr style="border:none;border-top:1px solid var(--border-light);margin:4px 0">
            <div class="caja-row">
                <span class="label" style="font-weight:600">Saldo esperado</span>
                <span class="val" style="color:var(--success);font-size:1.05rem">${fmt(caja.saldo_esperado)}</span>
            </div>
        </div>`;
}

// ---- Ventas chart ----
async function loadChart() {
    const seq  = ++_seq.chart;
    const res  = await fetch(API + '?action=serie&' + qsPeriodo());
    const data = await res.json();
    if (seq !== _seq.chart) return;
    serieActual = data;

    const labels   = data.map(d => fmtCorta(d.fecha));
    const ingresos = data.map(d => parseFloat(d.ingresos));
    const ventas   = data.map(d => parseInt(d.total_ventas));
    const ganancia = data.map(d => parseFloat(d.ganancia || 0));

    if (chartVentas) {
        chartVentas.data.labels = labels;
        chartVentas.data.datasets[0].data = ingresos;
        chartVentas.data.datasets[1].data = ventas;
        if (ES_ADMIN) chartVentas.data.datasets[2].data = ganancia;
        chartVentas.update();
        return;
    }

    const datasets = [
        { label:'Ingresos (S/)', data:ingresos, backgroundColor:'rgba(37,99,235,.15)', borderColor:'rgba(37,99,235,.8)',
          borderWidth:2, borderRadius:6, yAxisID:'y' },
        { label:'N° Ventas', data:ventas, type:'line', borderColor:'#16a34a', backgroundColor:'transparent',
          borderWidth:2, pointBackgroundColor:'#16a34a', pointRadius:3, tension:.3, yAxisID:'y2' },
    ];
    if (ES_ADMIN) {
        datasets.push({ label:'Ganancia (S/)', data:ganancia, type:'line', borderColor:'#f59e0b', backgroundColor:'transparent',
            borderWidth:2, pointBackgroundColor:'#f59e0b', pointRadius:3, tension:.3, yAxisID:'y' });
    }

    chartVentas = new Chart(document.getElementById('chart-ventas').getContext('2d'), {
        type: 'bar',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            onClick: (evt, els) => {
                if (!els.length) return;
                const d = serieActual[els[0].index];
                if (d) abrirDetalle({ periodo:'rango', desde:d.fecha, hasta:d.fecha }, 'Ventas del ' + fmtCorta(d.fecha));
            },
            onHover: (evt, els) => { evt.native.target.style.cursor = els.length ? 'pointer' : 'default'; },
            plugins: {
                legend: { position: 'top', labels: { font: { size: 11 }, padding: 12 } },
                tooltip: { callbacks: { label: c => c.dataset.yAxisID === 'y2' ? ' ' + c.parsed.y + ' ventas' : ' ' + c.dataset.label.split(' (')[0] + ': ' + fmt(c.parsed.y) } }
            },
            scales: {
                y:  { position:'left',  ticks:{ callback: v => 'S/ ' + v, font:{ size:11 } }, grid:{ color:'rgba(0,0,0,.04)' } },
                y2: { position:'right', ticks:{ stepSize:1, font:{ size:11 } }, grid:{ display:false }, beginAtZero:true },
                x:  { ticks:{ font:{ size:11 }, maxTicksLimit:16 }, grid:{ display:false } },
            }
        }
    });
}

// ---- Métodos de pago ----
async function loadMetodos() {
    const seq  = ++_seq.metodos;
    const res  = await fetch(API + '?action=metodos_periodo&' + qsPeriodo());
    const data = await res.json();
    if (seq !== _seq.metodos) return;
    const body = document.getElementById('metodo-body');

    if (!data.length) {
        body.innerHTML = '<p style="color:var(--text-muted);font-size:.83rem;text-align:center;padding:20px 0">Sin ventas en el período</p>';
        return;
    }

    const max = Math.max(...data.map(d => parseFloat(d.monto)));
    body.innerHTML = `<div class="metodo-list">${
        data.map(d => {
            const pct = max > 0 ? (parseFloat(d.monto) / max * 100).toFixed(1) : 0;
            const lbl = METODO_LABEL[d.tipo_pago] ?? d.tipo_pago;
            const col = METODO_COLOR[d.tipo_pago]  ?? 'var(--primary)';
            return `<div class="metodo-row clickable" title="Ver las ventas con ${esc(lbl)}" onclick="abrirDetalleMetodo('${esc(d.tipo_pago)}')">
                <span class="metodo-label">${esc(lbl)}</span>
                <div class="metodo-bar-bg"><div class="metodo-bar" style="width:${pct}%;background:${col}"></div></div>
                <span class="metodo-val">${fmt(d.monto)}</span>
            </div>`;
        }).join('')
    }</div>`;
}
function abrirDetalleMetodo(tp) {
    const o = { periodo: filtro.periodo, desde: filtro.desde, hasta: filtro.hasta };
    abrirDetalle(o, 'Ventas · ' + (METODO_LABEL[tp] ?? tp) + ' · ' + etiquetaCorta(), tp);
}

// ---- Top productos del período ----
async function loadTop() {
    const seq  = ++_seq.top;
    const res  = await fetch(API + '?action=top_periodo&' + qsPeriodo({ metrica: filtro.topMetrica }));
    const r    = await res.json();
    if (seq !== _seq.top) return;
    const data = r.items || [];
    const m    = r.metrica || 'unidades';
    const body = document.getElementById('top-body');

    if (!data.length) {
        body.innerHTML = '<p style="color:var(--text-muted);font-size:.83rem;text-align:center;padding:30px">Sin ventas en el período</p>';
        return;
    }

    const val = (p) => m === 'unidades' ? fmtN(p.unidades) : fmt(p[m]);
    const max = Math.max(...data.map(p => Math.abs(parseFloat(p[m]))), 0);
    body.innerHTML = `<table class="dash-table">
        <thead><tr><th>#</th><th>Producto</th><th style="text-align:right">${m === 'unidades' ? 'Uds.' : (m === 'ingresos' ? 'Ingresos' : 'Ganancia')}</th></tr></thead>
        <tbody>${data.map((p, i) => {
            const pct = max > 0 ? (Math.abs(parseFloat(p[m])) / max * 100).toFixed(0) : 0;
            return `<tr>
                <td style="color:var(--text-muted);font-weight:700">${i+1}</td>
                <td>
                    <div style="font-weight:500;font-size:.83rem">${esc(p.nombre)}</div>
                    <div style="font-size:.72rem;color:var(--text-light)">${esc(p.laboratorio || '—')} · ${esc(p.categoria || '—')}</div>
                    <div style="margin-top:4px;height:4px;background:var(--surface-2);border-radius:99px;overflow:hidden">
                        <div style="height:100%;width:${pct}%;background:var(--warning);border-radius:99px"></div>
                    </div>
                </td>
                <td style="text-align:right;font-weight:700">${val(p)}</td>
            </tr>`;
        }).join('')}</tbody>
    </table>`;
}

// ---- Alertas de stock ----
async function loadAlertas() {
    const res  = await fetch(API + '?action=stock_alertas');
    const data = await res.json();
    const body = document.getElementById('alerta-body');
    const cnt  = document.getElementById('alerta-count');

    if (!data.length) {
        cnt.style.display = 'none';
        body.innerHTML = `<p style="color:var(--success);font-size:.83rem;text-align:center;padding:30px">
            <i class="fas fa-check-circle" style="display:block;font-size:1.6rem;margin-bottom:8px"></i>Stock en orden</p>`;
        return;
    }

    cnt.style.display = 'inline-flex';
    cnt.textContent   = data.length + ' alerta' + (data.length > 1 ? 's' : '');
    alertasData = data;

    body.innerHTML = `<div style="display:flex;flex-direction:column;flex:1 1 auto;min-height:0">
        <div style="flex:1 1 auto;min-height:0;overflow-y:auto">
        <table class="dash-table">
        <thead><tr>
            <th style="width:30px;text-align:center"><input type="checkbox" id="alerta-all" checked onclick="alertasToggleAll(this)"></th>
            <th>Producto</th><th style="text-align:right">Stock</th><th style="text-align:right">Mín</th>
        </tr></thead>
        <tbody>${data.map((p, i) => {
            const cls = p.alerta === 'agotado' ? 'badge-danger' : 'badge-warning';
            return `<tr style="cursor:pointer" onclick="alertasRowToggle(event, ${i})">
                <td style="text-align:center"><input type="checkbox" class="alerta-chk" data-i="${i}" checked onclick="event.stopPropagation();alertasUpdateCount()"></td>
                <td>
                    <div style="font-weight:500;font-size:.82rem">${esc(p.nombre)}
                        <a href="../inventario/index.php" onclick="event.stopPropagation()" title="Ver en inventario" style="color:var(--text-light);margin-left:4px;font-size:.68rem"><i class="fas fa-arrow-up-right-from-square"></i></a>
                    </div>
                    <div style="font-size:.7rem;color:var(--text-light)">${esc(p.categoria || '—')}</div>
                </td>
                <td style="text-align:right"><span class="kpi-badge ${cls}">${p.stock}</span></td>
                <td style="text-align:right;color:var(--text-muted)">${p.stock_minimo}</td>
            </tr>`;
        }).join('')}</tbody>
        </table>
        </div>
        <div style="padding:10px 16px;border-top:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;gap:8px">
            <span style="font-size:.74rem;color:var(--text-muted)"><span id="alerta-sel">${data.length}</span> seleccionado(s)</span>
            <div style="display:flex;gap:6px">
                <button class="btn-mini" title="Número de WhatsApp del proveedor (por defecto)" onclick="alertasConfigNumero()"><i class="fas fa-gear"></i></button>
                <button class="btn-wa" id="alerta-wa-btn" onclick="alertasEnviarImagen()"><i class="fab fa-whatsapp"></i> Enviar imagen</button>
            </div>
        </div>
    </div>`;
}

// ---- Alertas: seleccion ----
function alertasToggleAll(src) {
    document.querySelectorAll('.alerta-chk').forEach(c => { c.checked = src.checked; });
    alertasUpdateCount();
}
function alertasRowToggle(ev, i) {
    const c = document.querySelector('.alerta-chk[data-i="' + i + '"]');
    if (c) { c.checked = !c.checked; alertasUpdateCount(); }
}
function alertasUpdateCount() {
    const chks = Array.from(document.querySelectorAll('.alerta-chk'));
    const sel  = chks.filter(c => c.checked).length;
    const lbl  = document.getElementById('alerta-sel');
    if (lbl) lbl.textContent = sel;
    const all = document.getElementById('alerta-all');
    if (all) all.checked = sel === chks.length && chks.length > 0;
    const btn = document.getElementById('alerta-wa-btn');
    if (btn) btn.disabled = sel === 0;
}
function alertasSeleccionados() {
    return Array.from(document.querySelectorAll('.alerta-chk'))
        .filter(c => c.checked)
        .map(c => alertasData[parseInt(c.dataset.i, 10)])
        .filter(Boolean);
}

// ---- Alertas: numero de proveedor por defecto (localStorage) ----
function alertasGetNumero() { try { return localStorage.getItem('gravity_wa_proveedor') || ''; } catch (e) { return ''; } }
function alertasConfigNumero() {
    const actual = alertasGetNumero();
    const n = prompt('Número de WhatsApp del proveedor por defecto (con código de país, solo dígitos).\nEj: 51987654321', actual);
    if (n === null) return;
    const limpio = n.replace(/\D/g, '');
    try {
        if (limpio) { localStorage.setItem('gravity_wa_proveedor', limpio); showToast('Número guardado: ' + limpio, 'success'); }
        else { localStorage.removeItem('gravity_wa_proveedor'); showToast('Número por defecto borrado', 'info'); }
    } catch (e) { showToast('No se pudo guardar el número', 'error'); }
}

// ---- Alertas: generar imagen (Canvas) y compartir por WhatsApp ----
function alertasRenderImagen(items) {
    const dpr   = 2;                 // nitidez
    const W     = 720;
    const padX  = 40;
    const rowH  = 54;
    const headH = 150;
    const footH = 40;
    const H     = headH + items.length * rowH + footH;

    const cv = document.createElement('canvas');
    cv.width = W * dpr; cv.height = H * dpr;
    const ctx = cv.getContext('2d');
    ctx.scale(dpr, dpr);

    // Fondo
    ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, W, H);
    // Barra superior
    ctx.fillStyle = '#ef4444'; ctx.fillRect(0, 0, W, 8);

    // Cabecera
    ctx.textBaseline = 'alphabetic';
    ctx.fillStyle = '#111827';
    ctx.font = '700 26px Arial';
    ctx.fillText('Alerta de stock', padX, 54);
    ctx.fillStyle = '#374151';
    ctx.font = '600 16px Arial';
    ctx.fillText((EMPRESA || '') + (SUCURSAL ? ' — ' + SUCURSAL : ''), padX, 82);
    const ahora = new Date();
    const fstr  = ahora.toLocaleDateString('es-PE') + ' ' + ahora.toLocaleTimeString('es-PE', {hour:'2-digit', minute:'2-digit'});
    ctx.fillStyle = '#6b7280';
    ctx.font = '400 13px Arial';
    ctx.fillText('Generado: ' + fstr + '  ·  ' + items.length + ' producto(s)', padX, 104);

    // Encabezado de tabla
    let y = headH - 16;
    ctx.fillStyle = '#9ca3af';
    ctx.font = '600 12px Arial';
    ctx.fillText('PRODUCTO', padX, y);
    ctx.textAlign = 'right';
    ctx.fillText('STOCK', W - padX - 90, y);
    ctx.fillText('MÍN', W - padX, y);
    ctx.textAlign = 'left';
    ctx.strokeStyle = '#e5e7eb'; ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(padX, headH - 6); ctx.lineTo(W - padX, headH - 6); ctx.stroke();

    // Filas
    items.forEach((p, idx) => {
        const top = headH + idx * rowH;
        const midY = top + rowH / 2;
        if (idx % 2 === 1) { ctx.fillStyle = '#f9fafb'; ctx.fillRect(padX - 10, top, W - 2 * padX + 20, rowH); }
        // Nombre
        ctx.fillStyle = '#111827'; ctx.font = '600 16px Arial';
        let nombre = p.nombre || '';
        const maxW = W - 2 * padX - 170;
        while (ctx.measureText(nombre).width > maxW && nombre.length > 4) nombre = nombre.slice(0, -2);
        if (nombre !== (p.nombre || '')) nombre += '…';
        ctx.fillText(nombre, padX, midY - 2);
        if (p.categoria) { ctx.fillStyle = '#9ca3af'; ctx.font = '400 12px Arial'; ctx.fillText(p.categoria, padX, midY + 16); }
        // Badge stock
        const agotado = p.alerta === 'agotado';
        ctx.textAlign = 'right';
        ctx.fillStyle = agotado ? '#dc2626' : '#d97706';
        ctx.font = '700 17px Arial';
        ctx.fillText(agotado ? '0 (agotado)' : String(p.stock), W - padX - 90, midY + 4);
        ctx.fillStyle = '#6b7280'; ctx.font = '400 15px Arial';
        ctx.fillText(String(p.stock_minimo), W - padX, midY + 4);
        ctx.textAlign = 'left';
    });

    // Pie
    ctx.fillStyle = '#9ca3af'; ctx.font = '400 12px Arial'; ctx.textAlign = 'center';
    ctx.fillText('Generado por Gravity', W / 2, H - 16);
    ctx.textAlign = 'left';

    return cv;
}

function alertasEnviarImagen() {
    const items = alertasSeleccionados();
    if (!items.length) { showToast('Selecciona al menos un producto', 'error'); return; }
    const cv = alertasRenderImagen(items);
    cv.toBlob(async (blob) => {
        if (!blob) { showToast('No se pudo generar la imagen', 'error'); return; }
        const fecha = new Date().toISOString().slice(0, 10);
        const file  = new File([blob], 'alerta-stock-' + fecha + '.png', { type: 'image/png' });
        const caption = 'Alerta de stock' + (SUCURSAL ? ' — ' + SUCURSAL : '') + ' (' + items.length + ' productos)';

        // Celular: compartir la imagen real por el menu nativo (WhatsApp incluido)
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            try { await navigator.share({ files: [file], title: 'Alerta de stock', text: caption }); return; }
            catch (e) { if (e && e.name === 'AbortError') return; /* si falla, cae al fallback */ }
        }

        // PC: descarga el PNG y abre WhatsApp al numero por defecto para adjuntarlo
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = file.name; document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 4000);

        let numero = alertasGetNumero();
        const waBase = numero ? 'https://wa.me/' + numero : 'https://wa.me/';
        window.open(waBase + '?text=' + encodeURIComponent(caption + '\n(imagen descargada: adjúntala en el chat)'), '_blank');
        showToast('Imagen descargada. Adjúntala en WhatsApp.', 'info');
    }, 'image/png');
}

// ---- Productos por vencer (admin/gerente) ----
async function loadVencer() {
    if (!ES_ADMIN) return;
    const res = await fetch(API + '?action=por_vencer');
    const r   = await res.json();
    const body = document.getElementById('venc-body');
    if (!r || r.error) { body.innerHTML = ''; return; }
    const c = r.conteo;

    const box = (n, t, bg, col) => `<div class="venc-box" style="background:${bg};color:${col}"><div class="n">${n}</div><div class="t">${t}</div></div>`;
    let html = `<div class="venc-grid">
        ${box(c.vencidos, 'Vencidos', 'var(--danger-light)', 'var(--danger)')}
        ${box(c.d30, 'Vencen en 30 días', 'var(--warning-light)', 'var(--warning)')}
        ${box(c.d60, 'En 31 a 60 días', 'var(--info-light)', 'var(--info)')}
        ${box(c.d90, 'En 61 a 90 días', 'var(--surface-2)', 'var(--text-muted)')}
    </div>`;

    if (!r.lista.length) {
        html += `<p style="color:var(--success);font-size:.83rem;text-align:center;padding:20px">
            <i class="fas fa-check-circle"></i> No hay productos con stock que venzan en los próximos 90 días</p>`;
    } else {
        html += `<div style="overflow-x:auto;max-height:300px;overflow-y:auto"><table class="dash-table">
            <thead><tr><th>Producto</th><th>Laboratorio</th><th style="text-align:right">Stock</th><th>Vence</th><th>Estado</th></tr></thead>
            <tbody>${r.lista.map(p => {
                const d = parseInt(p.dias, 10);
                const cls = d < 0 ? 'badge-danger' : (d <= 30 ? 'badge-warning' : 'badge-info');
                const txt = d < 0 ? 'Vencido hace ' + Math.abs(d) + ' d' : (d === 0 ? 'Vence hoy' : 'En ' + d + ' días');
                return `<tr class="clickable" onclick="location.href='../inventario/index.php'">
                    <td style="font-weight:500">${esc(p.nombre)}</td>
                    <td style="color:var(--text-muted)">${esc(p.laboratorio || '—')}</td>
                    <td style="text-align:right">${fmtN(p.stock)}</td>
                    <td>${p.fecha_vencimiento.split('-').reverse().join('/')}</td>
                    <td><span class="kpi-badge ${cls}">${txt}</span></td>
                </tr>`;
            }).join('')}</tbody></table></div>`;
    }
    body.innerHTML = html;
}

// ---- Últimas ventas ----
async function loadUltimasVentas() {
    const res  = await fetch(API + '?action=ultimas_ventas');
    const data = await res.json();
    const tbody = document.querySelector('#tbl-ventas tbody');

    if (!data.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted)">Sin ventas registradas hoy</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(filaVenta).join('');
}

function filaVenta(v) {
    const hora  = new Date(v.created_at).toLocaleTimeString('es-PE', {hour:'2-digit', minute:'2-digit'});
    const icon  = PAGO_ICONS[v.tipo_pago] ?? '';
    const comp  = COMP_LABEL[v.tipo_comprobante] ?? v.tipo_comprobante;
    const esCan = v.estado === 'anulada';
    const badge = esCan ? '<span class="kpi-badge badge-danger">Anulada</span>' : '<span class="kpi-badge badge-success">Completada</span>';
    return `<tr style="${esCan ? 'opacity:.55' : ''}">
        <td><span style="font-family:monospace;font-size:.8rem">${esc(v.numero_venta)}</span></td>
        <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(v.cliente)}</td>
        <td>${icon} ${esc(METODO_LABEL[v.tipo_pago] ?? v.tipo_pago)}</td>
        <td>${esc(comp)}</td>
        <td style="color:var(--text-muted)">${hora}</td>
        <td style="font-weight:600">${fmt(v.total)}</td>
        <td>${badge}</td>
    </tr>`;
}

// ---- Modal de detalle (drill-down) ----
async function abrirDetalle(rango, titulo, tipoPago) {
    document.getElementById('dm-titulo').textContent = titulo;
    document.getElementById('dm-body').innerHTML = '<p style="text-align:center;padding:30px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i> Cargando…</p>';
    document.getElementById('dm-overlay').classList.add('show');

    const p = new URLSearchParams({ action:'ventas_lista', periodo:rango.periodo });
    if (rango.periodo === 'rango') { p.set('desde', rango.desde); p.set('hasta', rango.hasta); }
    if (tipoPago) p.set('tipo_pago', tipoPago);
    try {
        const data = await (await fetch(API + '?' + p.toString())).json();
        if (!data.length) {
            document.getElementById('dm-body').innerHTML = '<p style="text-align:center;padding:30px;color:var(--text-muted)">Sin ventas</p>';
            return;
        }
        const total = data.filter(v => v.estado === 'completada').reduce((s, v) => s + parseFloat(v.total), 0);
        document.getElementById('dm-body').innerHTML = `
            <div style="padding:10px 20px;font-size:.8rem;color:var(--text-muted);border-bottom:1px solid var(--border-light)">
                ${data.length} venta(s)${data.length >= 200 ? ' (se muestran las 200 más recientes)' : ''} · Total completadas: <strong style="color:var(--text)">${fmt(total)}</strong>
            </div>
            <table class="dash-table"><thead><tr><th>N° Venta</th><th>Cliente</th><th>Método</th><th>Comprobante</th><th>Hora</th><th>Total</th><th>Estado</th></tr></thead>
            <tbody>${data.map(filaVenta).join('')}</tbody></table>`;
    } catch (e) {
        document.getElementById('dm-body').innerHTML = '<p style="text-align:center;padding:30px;color:var(--danger)">Error al cargar</p>';
    }
}
function cerrarDetalle() { document.getElementById('dm-overlay').classList.remove('show'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarDetalle(); });

// ---- Carga ----
// Widgets que dependen del periodo
function refreshPeriodo() {
    return Promise.all([loadKpis(), loadChart(), loadMetodos(), loadTop()]).catch(() => {});
}
// Todo (incluye caja, alertas, por vencer, ultimas ventas)
async function refreshAll() {
    const spin = document.getElementById('per-spin');
    spin.classList.add('fa-spin');
    _inv = null;   // fuerza a recargar inventario y caja
    await Promise.all([refreshPeriodo(), loadAlertas(), loadVencer(), loadUltimasVentas()]).catch(() => {});
    spin.classList.remove('fa-spin');
    document.getElementById('per-actualizado').textContent = 'Actualizado ' + new Date().toLocaleTimeString('es-PE', {hour:'2-digit', minute:'2-digit'});
}

// ---- Init ----
cargarFiltro();
pintarFiltro();
refreshAll();
setInterval(() => { if (!document.hidden) refreshAll(); }, AUTO_REFRESH_MS);
</script>

<?php include '../../includes/footer.php'; ?>
