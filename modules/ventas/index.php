<?php
// ============================================================
// ARCHIVO: modules/ventas/index.php
// MODULO:  Ventas -> Grilla de MESAS (modelo karaoke/bar/licoreria).
//
// Fase C del rebrand de Ventas: reemplaza el POS directo por la
// grilla "mesas libres / mesas ocupadas" con cronometro vivo.
//
// Flujos:
//   - Clic en una mesa libre  -> confirma + POST abrir_comanda
//                                -> refresca la grilla (y en Fase D
//                                   redirigira a mesa.php?id=N)
//   - Clic en una mesa ocupada -> ira a mesa.php?id=N (Fase D).
//                                Por ahora muestra "en construccion".
//
// El POS original quedo en pos.php (preservado como referencia
// y como acceso directo al "Historial" via pos.php?tab=ventas).
// ============================================================

require_once '../../config/database.php';

$base_path      = '../../';
$current_module = 'ventas';
$current_page   = 'mesas';
$page_title     = 'Mesas — Gravity';
$breadcrumb     = '<strong>Mesas</strong>';
// Admin, gerente y cajero (en el UI: "Mozo") pueden operar mesas.
$required_roles = ['admin', 'gerente', 'cajero'];

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title">
            <i class="fas fa-chair" style="color:var(--primary);margin-right:8px"></i>Mesas
        </div>
        <div class="page-subtitle">
            Grilla de mesas libres y ocupadas · <span id="hora-actual"></span>
        </div>
    </div>
</div>

<div class="vt-tabs">
    <a class="vt-tab active" href="index.php"><i class="fas fa-chair"></i> Mesas</a>
    <a class="vt-tab" href="pos.php"><i class="fas fa-store"></i> Punto de venta</a>
    <a class="vt-tab" href="pos.php?tab=ventas"><i class="fas fa-history"></i> Historial</a>
</div>

<!-- Resumen -->
<div class="mesas-summary">
    <div class="mesas-stat">
        <div class="mesas-stat-num" id="count-libres">0</div>
        <div class="mesas-stat-lbl">Libres</div>
    </div>
    <div class="mesas-stat ocupadas">
        <div class="mesas-stat-num" id="count-ocupadas">0</div>
        <div class="mesas-stat-lbl">Ocupadas</div>
    </div>
    <div class="mesas-stat total">
        <div class="mesas-stat-num" id="total-abierto">S/ 0.00</div>
        <div class="mesas-stat-lbl">En comandas abiertas</div>
    </div>
</div>

<!-- Grilla -->
<div class="mesa-grid" id="mesa-grid">
    <div class="mesa-empty">
        <i class="fas fa-spinner fa-spin"></i> Cargando mesas...
    </div>
</div>

<div class="app-toast-container" id="toast-container"></div>

<style>
/* --- Pestañas Ventas (Mesas / POS / Historial) --- */
.vt-tabs {
    display:flex; gap:6px; margin-bottom:16px;
    background:var(--surface-2); border-radius:var(--radius);
    padding:5px; width:fit-content;
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

/* --- Grilla y tarjetas de mesa --- */
.mesas-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 18px;
}
.mesas-stat {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px 18px;
    text-align: center;
}
.mesas-stat-num {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}
.mesas-stat-lbl {
    font-size: .74rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-top: 4px;
}
.mesas-stat.ocupadas .mesas-stat-num { color: #0471cd; }
.mesas-stat.total .mesas-stat-num    { color: var(--success); }

.mesa-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 14px;
}
.mesa-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 48px 20px;
    color: var(--text-muted);
    background: var(--surface);
    border: 1px dashed var(--border);
    border-radius: var(--radius);
}
.mesa-card {
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 14px;
    cursor: pointer;
    transition: transform .12s, box-shadow .12s, border-color .12s;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 140px;
}
.mesa-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.mesa-card.mesa-libre {
    border-color: #16a34a;
    background: linear-gradient(180deg, var(--surface) 0%, rgba(22,163,74,.06) 100%);
}
.mesa-card.mesa-libre:hover    { border-color: #15803d; background: rgba(22,163,74,.1); }
.mesa-card.mesa-ocupada {
    border-color: #0471cd;
    background: linear-gradient(180deg, var(--surface) 0%, rgba(4,113,205,.08) 100%);
}
.mesa-card.mesa-ocupada:hover  { border-color: #048af5; background: rgba(4,113,205,.14); }

.mesa-numero {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1;
}
.mesa-nombre {
    font-size: .82rem;
    color: var(--text-muted);
    min-height: 1em;
}
.mesa-estado-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: 3px 9px;
    border-radius: 999px;
    width: fit-content;
}
.mesa-libre .mesa-estado-pill    { background: #dcfce7; color: #15803d; }
.mesa-ocupada .mesa-estado-pill  { background: #dbeafe; color: #0471cd; }

.mesa-crono {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--primary);
    font-variant-numeric: tabular-nums;
    letter-spacing: .02em;
}
.mesa-footer {
    margin-top: auto;
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: .78rem;
    color: var(--text-muted);
}
.mesa-footer strong { color: var(--text); font-size: .88rem; }
.mesa-mozo {
    font-size: .7rem;
    color: var(--text-light);
    margin-top: 2px;
    font-style: italic;
}
</style>

<script>
// ----------------------------------------------------------------
// Estado y helpers
// ----------------------------------------------------------------
let MESAS = [];
let polling = null;             // handle del setInterval de refresh
let abriendo = false;           // guard contra doble-click durante POST abrir
const API = 'api.php';

function escH(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function formatSoles(n) {
    const num = parseFloat(n || 0);
    return 'S/ ' + num.toFixed(2);
}
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function showToast(msg, type = 'info') {
    const icons = { success:'check-circle', error:'exclamation-circle', info:'info-circle' };
    const t = document.createElement('div');
    t.className = `app-toast ${type}`;
    t.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'}"></i> ${msg}`;
    const c = document.getElementById('toast-container');
    if (c) { c.appendChild(t); setTimeout(() => t.remove(), 3500); }
    else { console.log('[' + type + '] ' + msg); }
}

// Postgres TIMESTAMP viene como "2026-10-22 19:30:45.123".
// Lo convertimos a ISO ("...T19:30:45") para que new Date() lo
// interprete correcto en todos los browsers (Safari se enoja con el espacio).
function parsePgTimestamp(ts) {
    if (!ts) return null;
    return new Date(String(ts).replace(' ', 'T'));
}
function formatCrono(ms) {
    const total = Math.max(0, Math.floor(ms / 1000));
    const h = String(Math.floor(total / 3600)).padStart(2, '0');
    const m = String(Math.floor((total % 3600) / 60)).padStart(2, '0');
    const s = String(total % 60).padStart(2, '0');
    return `${h}:${m}:${s}`;
}

// ----------------------------------------------------------------
// Carga de mesas (polling) y render
// ----------------------------------------------------------------
async function cargarMesas() {
    try {
        const r = await fetch(API + '?action=mesas_estado');
        const data = await r.json();
        if (!Array.isArray(data)) {
            renderError('No se pudo cargar la lista de mesas.');
            return;
        }
        MESAS = data;
        render();
    } catch (err) {
        renderError('Error de conexion al cargar mesas.');
    }
}

function renderError(msg) {
    document.getElementById('mesa-grid').innerHTML =
        `<div class="mesa-empty"><i class="fas fa-triangle-exclamation" style="color:var(--danger)"></i> ${escH(msg)}</div>`;
}

function render() {
    const libres   = MESAS.filter(m => !m.comanda_id);
    const ocupadas = MESAS.filter(m => !!m.comanda_id);
    document.getElementById('count-libres').textContent   = libres.length;
    document.getElementById('count-ocupadas').textContent = ocupadas.length;
    const totalAbierto = ocupadas.reduce((s, m) => s + parseFloat(m.total_parcial || 0), 0);
    document.getElementById('total-abierto').textContent  = formatSoles(totalAbierto);

    const grid = document.getElementById('mesa-grid');
    if (!MESAS.length) {
        grid.innerHTML =
            `<div class="mesa-empty">
                <i class="fas fa-chair" style="font-size:2rem;color:var(--border);margin-bottom:12px;display:block"></i>
                No hay mesas registradas.<br>
                <small>Dalas de alta en <strong>Administracion -> Mesas</strong>.</small>
            </div>`;
        return;
    }

    grid.innerHTML = MESAS.map(renderMesa).join('');
    updateCronometros();  // pinta de una el valor inicial
}

function renderMesa(m) {
    const ocupada = !!m.comanda_id;
    const cls     = ocupada ? 'mesa-ocupada' : 'mesa-libre';
    const nombre  = escH(m.nombre || '');
    // Libre y ocupada van directo a mesa.php -- el backend decide:
    // para libre, abrir_comanda primero via API; para ocupada, directo.
    const onclick = ocupada
        ? `irAMesa(${m.id}, ${m.comanda_id})`
        : `abrirMesaDirecto(${m.id})`;

    if (ocupada) {
        const itemsCount = parseInt(m.items_count || 0, 10);
        const total      = formatSoles(m.total_parcial);
        const mozo       = escH(m.usuario_apertura_nombre || '');
        return `
            <div class="mesa-card ${cls}" onclick="${onclick}">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
                    <div>
                        <div class="mesa-numero">#${escH(m.numero)}</div>
                        <div class="mesa-nombre">${nombre}</div>
                    </div>
                    <span class="mesa-estado-pill"><i class="fas fa-circle" style="font-size:.5rem"></i> Ocupada</span>
                </div>
                <div class="mesa-crono" data-crono="${escH(m.hora_apertura)}">00:00:00</div>
                <div class="mesa-footer">
                    <span>${itemsCount} ${itemsCount === 1 ? 'item' : 'items'}</span>
                    <strong>${total}</strong>
                </div>
                ${mozo ? `<div class="mesa-mozo"><i class="fas fa-user" style="font-size:.65rem"></i> ${mozo}</div>` : ''}
            </div>`;
    }

    const capacidad = m.capacidad ? `${m.capacidad} personas` : '&nbsp;';
    return `
        <div class="mesa-card ${cls}" onclick="${onclick}">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
                <div>
                    <div class="mesa-numero">#${escH(m.numero)}</div>
                    <div class="mesa-nombre">${nombre || '&nbsp;'}</div>
                </div>
                <span class="mesa-estado-pill"><i class="fas fa-circle" style="font-size:.5rem"></i> Libre</span>
            </div>
            <div class="mesa-footer" style="margin-top:auto">
                <span>${capacidad}</span>
            </div>
        </div>`;
}

function updateCronometros() {
    const now = Date.now();
    document.querySelectorAll('[data-crono]').forEach(el => {
        const start = parsePgTimestamp(el.dataset.crono);
        if (!start || isNaN(start)) return;
        el.textContent = formatCrono(now - start.getTime());
    });
}

// ----------------------------------------------------------------
// Entrar a mesa libre: redirect directo, SIN crear comanda todavia.
// La comanda recien se crea cuando el cajero agrega el primer producto
// (asi el cronometro y el estado "ocupada" no arrancan por un click
// accidental).
// ----------------------------------------------------------------
async function abrirMesaDirecto(mesaId) {
    if (abriendo) return;
    abriendo = true;
    try {
        window.location = `mesa.php?id=${mesaId}`;
    } finally {
        abriendo = false;
    }
}

function irAMesa(mesaId, comandaId) {
    window.location = `mesa.php?id=${mesaId}`;
}

// ----------------------------------------------------------------
// Reloj del header + arranque
// ----------------------------------------------------------------
function tickReloj() {
    const d = new Date();
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yy = d.getFullYear();
    const hh = String(d.getHours()).padStart(2, '0');
    const mi = String(d.getMinutes()).padStart(2, '0');
    document.getElementById('hora-actual').textContent = `${dd}/${mm}/${yy} ${hh}:${mi}`;
}

// Flash message al volver desde mesa.php tras un cobro exitoso
const _flashCobro = sessionStorage.getItem('cobro_ok');
if (_flashCobro) {
    sessionStorage.removeItem('cobro_ok');
    setTimeout(() => showToast(_flashCobro, 'success'), 100);
}

cargarMesas();
tickReloj();
setInterval(updateCronometros, 1000);                 // reloj por mesa
setInterval(tickReloj, 30 * 1000);                    // reloj del header
polling = setInterval(cargarMesas, 7000);             // refresh del server
document.addEventListener('visibilitychange', () => {
    // Dejar de pedir al server cuando la pestana queda en background
    if (document.hidden) { clearInterval(polling); polling = null; }
    else if (!polling) { cargarMesas(); polling = setInterval(cargarMesas, 7000); }
});
</script>

<?php include '../../includes/footer.php'; ?>
