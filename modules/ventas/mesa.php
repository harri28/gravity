<?php
// ============================================================
// ARCHIVO: modules/ventas/mesa.php
// VISTA:   Mesa abierta -- comanda a la izquierda, catalogo a la derecha.
//
// Fase D del rebrand de Ventas. Punto de entrada:
//   mesa.php?id=<mesa_id>    (si tiene comanda abierta, la carga; si no, redirige)
//   mesa.php?comanda=<id>    (alternativa: cargar comanda por id directo)
//
// Reglas:
//   - Agregar producto -> POST comanda_agregar_item -> descuenta stock
//   - Quitar producto  -> POST comanda_quitar_item  -> repone stock
//   - Cambiar cantidad -> POST comanda_cambiar_cantidad -> ajusta stock por delta
//   - Barcode HID: evento 'barcodescan' -> busca por codigo_barras -> agrega 1
//   - Boton COBRAR -> Fase E (por ahora toast "proxima fase")
// ============================================================

require_once '../../config/database.php';

$mesaId    = intval($_GET['id']      ?? 0);
$comandaId = intval($_GET['comanda'] ?? 0);

// Resolver la mesa + (opcionalmente) la comanda abierta.
// Si la mesa existe pero no tiene comanda abierta, renderizamos igual la
// vista en "modo preview" (sin cronometro, sin total): la comanda se crea
// recien cuando se agregue el primer producto desde el catalogo.
if (!$mesaId && !$comandaId) {
    header('Location: index.php');
    exit;
}

$db = getDB();
$resueltaMesaId    = 0;
$resueltaComandaId = null;

if ($comandaId) {
    // Entrada por comanda explicita: tiene que existir y estar abierta.
    $ck = $db->prepare("SELECT id, mesa_id FROM comandas WHERE id = :c AND estado = 'abierta'");
    $ck->execute([':c' => $comandaId]);
    $resuelta = $ck->fetch();
    if (!$resuelta) {
        header('Location: index.php?msg=' . urlencode('La comanda ya no esta abierta.'));
        exit;
    }
    $resueltaMesaId    = (int) $resuelta['mesa_id'];
    $resueltaComandaId = (int) $resuelta['id'];
} else {
    // Entrada por mesa: la mesa tiene que existir, pero puede no tener comanda.
    $mk = $db->prepare("SELECT id FROM mesas WHERE id = :m AND activa = TRUE");
    $mk->execute([':m' => $mesaId]);
    if (!$mk->fetch()) {
        header('Location: index.php?msg=' . urlencode('La mesa no existe o esta inactiva.'));
        exit;
    }
    $resueltaMesaId = $mesaId;

    $ck = $db->prepare("SELECT id FROM comandas WHERE mesa_id = :m AND estado = 'abierta'");
    $ck->execute([':m' => $mesaId]);
    $cRow = $ck->fetch();
    $resueltaComandaId = $cRow ? (int) $cRow['id'] : null;
}

// Datos basicos de la mesa (numero + nombre), independientes de que haya o no
// comanda abierta -- se usan para mostrar el header incluso en modo preview.
$mesaInfoSt = $db->prepare("SELECT numero, nombre, capacidad FROM mesas WHERE id = :m");
$mesaInfoSt->execute([':m' => $resueltaMesaId]);
$mesaInfo = $mesaInfoSt->fetch() ?: ['numero' => '?', 'nombre' => '', 'capacidad' => 0];

$base_path      = '../../';
$current_module = 'ventas';
$current_page   = 'mesas';

// Labels configurables por tenant (migration_64).
$_labels     = getTenantConfig();
$L_mesa_s    = $_labels['label_mesa_singular'];
$L_mesa_p    = $_labels['label_mesa_plural'];

$page_title     = htmlspecialchars($L_mesa_s) . ' — Gravity';
$breadcrumb     = '<a href="index.php" style="color:var(--text-muted);text-decoration:none">' . htmlspecialchars($L_mesa_p) . '</a> / <strong>' . htmlspecialchars($L_mesa_s) . '</strong>';
$required_roles = ['admin', 'gerente', 'cajero'];

include '../../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:14px">
        <a href="index.php" class="mesa-back-btn" title="Volver a <?= htmlspecialchars($L_mesa_p) ?>">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <div class="page-title" id="mesa-title">
                <i class="fas fa-chair" style="color:var(--primary);margin-right:8px"></i>
                <?= htmlspecialchars($L_mesa_s) ?> <span id="mesa-num-txt">—</span>
                <small id="mesa-nom-txt" style="color:var(--text-muted);font-weight:500;font-size:.75em"></small>
            </div>
            <div class="page-subtitle">
                Abierta hace <strong id="mesa-crono" data-crono="">00:00:00</strong>
                · <span id="mesa-mozo-txt"></span>
            </div>
        </div>
        <button id="btn-unir-mesa" class="btn btn-outline btn-sm" style="margin-left:auto" onclick="unirConMesa()" title="Fusionar otra <?= htmlspecialchars(mb_strtolower($L_mesa_s)) ?> en esta">
            <i class="fas fa-code-merge"></i> Unir <?= htmlspecialchars(mb_strtolower($L_mesa_s)) ?>
        </button>
    </div>
</div>

<style>
.mesa-back-btn {
    display:inline-flex; align-items:center; justify-content:center;
    width:40px; height:40px; flex-shrink:0;
    border-radius:50%;
    background:var(--surface-2);
    border:1px solid var(--border);
    color:var(--text);
    font-size:1rem;
    text-decoration:none;
    transition:background .15s, color .15s, transform .1s, border-color .15s;
}
.mesa-back-btn:hover {
    background:var(--primary);
    color:#fff;
    border-color:var(--primary);
    transform:translateX(-2px);
}
</style>

<div class="mesa-layout">
    <!-- ================================
         IZQUIERDA: COMANDA
         ================================ -->
    <div class="mesa-comanda card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-receipt" style="color:var(--primary);margin-right:6px"></i>Comanda
            </div>
            <span style="font-size:.75rem;color:var(--text-muted)" id="items-count-txt">0 items</span>
        </div>

        <div id="items-list" class="items-list">
            <div class="items-empty"><i class="fas fa-utensils"></i><br>Agrega el primer pedido desde el catalogo</div>
        </div>

        <div class="comanda-footer">
            <div class="comanda-total">
                <span>Total</span>
                <strong id="comanda-total">S/ 0.00</strong>
            </div>
            <button class="btn btn-outline" id="btn-precuenta" onclick="imprimirPrecuenta()" style="margin-right:8px">
                <i class="fas fa-receipt"></i> PRECUENTA
            </button>
            <button class="btn btn-primary btn-cobrar" id="btn-cobrar" onclick="cobrar()">
                <i class="fas fa-cash-register"></i> COBRAR
            </button>
        </div>
    </div>

    <!-- ================================
         DERECHA: CATALOGO
         ================================ -->
    <div class="mesa-catalogo card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-list" style="color:var(--primary);margin-right:6px"></i>Catalogo
            </div>
            <span style="font-size:.72rem;color:var(--text-muted)">
                <i class="fas fa-barcode"></i> Escaner HID activo
            </span>
        </div>

        <div class="catalogo-filtros">
            <input type="text" id="buscador" class="form-control" placeholder="Buscar producto por nombre o codigo..." autocomplete="off">
            <div id="categorias-pills" class="categorias-pills">
                <button class="cat-pill active" data-cat="0" onclick="filtrarCat(0, this)">Todos</button>
            </div>
        </div>

        <div id="productos-grid" class="productos-grid">
            <div class="items-empty" style="grid-column:1/-1">
                <i class="fas fa-spinner fa-spin"></i> Cargando catalogo...
            </div>
        </div>
    </div>
</div>

<!-- ======================================================
     MODAL: Elegir unidad de medida (CAJA, PACK, etc.)
     Se abre al agregar un producto que tiene presentaciones alternativas
     en producto_precios_unidad. Mismo flujo que el POS directo.
     ====================================================== -->
<div class="modal-overlay" id="modal-unidad-mesa">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-boxes" style="color:var(--primary);margin-right:8px"></i>
                Unidad de venta
            </h3>
            <button class="modal-close" onclick="closeModal('modal-unidad-mesa')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom:14px">
                <div style="font-size:.78rem;color:var(--text-muted)">Producto</div>
                <div id="unidad-mesa-producto-nombre" style="font-weight:700;font-size:1rem;color:var(--text)">—</div>
            </div>
            <div style="font-size:.75rem;color:var(--text-muted);margin-bottom:10px">
                ¿Cómo lo vas a cargar a la comanda?
            </div>
            <div id="unidad-mesa-opciones"></div>
        </div>
    </div>
</div>

<!-- MODAL: Elegir mesa (reutilizado para mover item y unir mesa) -->
<div class="modal-overlay" id="modal-pick-mesa">
    <div class="modal" style="max-width:720px">
        <div class="modal-header">
            <h3 class="modal-title" id="pick-mesa-titulo">
                <i class="fas fa-chair" style="color:var(--primary);margin-right:8px"></i>
                Elegir mesa
            </h3>
            <button class="modal-close" onclick="closeModal('modal-pick-mesa')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="pick-mesa-subtitulo" style="font-size:.78rem;color:var(--text-muted);margin-bottom:12px"></div>
            <div class="pick-mesa-grid" id="pick-mesa-grid">
                <div class="pick-mesa-empty"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Cobro / Pago -- usa el MISMO partial que pos.php -->
<link rel="stylesheet" href="<?= $base_path ?>assets/vendor/select2/select2.min.css">
<link rel="stylesheet" href="<?= $base_path ?>assets/css/cobro-modal.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/cobro-modal.css') ?: 1 ?>">
<?php include __DIR__ . '/_modal_cobro.php'; ?>
<?php include __DIR__ . '/_modal_nuevo_cliente.php'; ?>

<div class="app-toast-container" id="toast-container"></div>

<style>
/* Layout 2 columnas en desktop, stack en mobile */
.mesa-layout {
    display: grid;
    grid-template-columns: minmax(320px, 420px) 1fr;
    gap: 16px;
    align-items: start;
}
@media (max-width: 820px) {
    .mesa-layout { grid-template-columns: 1fr; }
    .mesa-comanda { order: 2; }
    .mesa-catalogo { order: 1; max-height: 60vh; }
}

/* Panel comanda */
.mesa-comanda { display: flex; flex-direction: column; max-height: calc(100vh - 180px); position: sticky; top: 10px; }
.items-list { padding: 8px 0; overflow-y: auto; flex: 1; min-height: 180px; }
.items-empty { text-align: center; padding: 30px 20px; color: var(--text-muted); font-size: .9rem; }
.items-empty i { font-size: 1.8rem; color: var(--border); margin-bottom: 8px; display: block; }

.item-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 4px 10px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--border-light);
    animation: fadeIn .15s;
}
@keyframes fadeIn { from { opacity: 0; transform: translateY(-4px); } }
.item-row:last-child { border-bottom: none; }

.item-nombre { font-weight: 600; color: var(--text); font-size: .9rem; }
.item-meta   { font-size: .72rem; color: var(--text-muted); grid-column: 1 / -1; }
.item-right  { display: flex; align-items: center; gap: 4px; }
.item-cant {
    display: inline-flex; align-items: center; gap: 2px;
    background: var(--surface-2); border-radius: 6px; padding: 2px;
}
.item-cant button {
    width: 24px; height: 24px; border: none; background: var(--surface);
    color: var(--primary); border-radius: 4px; cursor: pointer; font-weight: 700;
    transition: background .12s;
}
.item-cant button:hover { background: var(--primary-light); }
.item-cant span { min-width: 24px; text-align: center; font-size: .85rem; font-weight: 700; font-variant-numeric: tabular-nums; }
.item-subtotal { font-weight: 700; color: var(--text); font-size: .9rem; min-width: 70px; text-align: right; font-variant-numeric: tabular-nums; }
.item-del {
    background: none; border: none; cursor: pointer;
    color: var(--text-light); padding: 4px 6px; border-radius: 4px;
    transition: color .12s, background .12s;
}
.item-del:hover { color: var(--danger); background: #fef2f2; }

.comanda-footer {
    border-top: 2px solid var(--border);
    padding: 14px 18px;
    background: var(--surface-2);
    border-radius: 0 0 var(--radius) var(--radius);
}
.comanda-total {
    display: flex; justify-content: space-between; align-items: baseline;
    margin-bottom: 12px;
    font-size: 1rem; color: var(--text-muted);
}
.comanda-total strong {
    font-size: 1.6rem; color: var(--primary);
    font-variant-numeric: tabular-nums;
}
.btn-cobrar {
    width: 100%; font-size: 1.05rem; font-weight: 800;
    padding: 14px;
    background: linear-gradient(135deg, #048af5, #02386b);
    box-shadow: 0 4px 14px rgba(4,113,205,.35);
    letter-spacing: .04em;
}
.btn-cobrar:disabled { opacity: .5; cursor: not-allowed; box-shadow: none; }

/* Panel catalogo */
.mesa-catalogo { display: flex; flex-direction: column; max-height: calc(100vh - 180px); }
.catalogo-filtros { padding: 10px 14px; border-bottom: 1px solid var(--border-light); display: flex; flex-direction: column; gap: 8px; }
.categorias-pills { display: flex; flex-wrap: wrap; gap: 6px; }
.cat-pill {
    background: var(--surface-2); border: 1px solid var(--border);
    color: var(--text-muted); padding: 5px 12px; border-radius: 999px;
    font-size: .76rem; font-weight: 600; cursor: pointer;
    transition: .12s;
}
.cat-pill:hover { color: var(--primary); border-color: var(--primary); }
.cat-pill.active { background: var(--primary); color: #fff; border-color: var(--primary); }

.productos-grid {
    padding: 10px; overflow-y: auto; flex: 1;
    display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px;
}
.producto-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm);
    padding: 10px 12px; cursor: pointer; display: flex; flex-direction: column;
    transition: .12s; min-height: 92px;
    position: relative;
}
.producto-card:hover { border-color: var(--primary); background: var(--primary-light); transform: translateY(-1px); }
.producto-card:active { transform: translateY(0); }
.producto-card.sin-stock { opacity: .5; cursor: not-allowed; }
.producto-card.sin-stock:hover { border-color: var(--border); background: var(--surface); transform: none; }
.prod-nombre { font-weight: 600; color: var(--text); font-size: .82rem; line-height: 1.25; margin-bottom: 4px; }
.prod-precio { font-weight: 800; color: var(--primary); font-size: .95rem; margin-top: auto; font-variant-numeric: tabular-nums; }
.prod-stock  { font-size: .68rem; color: var(--text-muted); }
.prod-stock.bajo { color: var(--warning); font-weight: 600; }
.prod-service-badge {
    position: absolute; top: 6px; right: 6px;
    background: var(--info); color: #fff; font-size: .55rem; padding: 1px 5px;
    border-radius: 3px; font-weight: 700; letter-spacing: .04em;
}

.app-toast {
    background: var(--text); color: var(--surface); padding: 10px 16px;
    border-radius: 8px; margin-bottom: 8px; font-size: .9rem;
    display: flex; align-items: center; gap: 8px;
    box-shadow: 0 4px 14px rgba(0,0,0,.2);
    animation: toastIn .15s;
}
.app-toast.success { background: var(--success); color: #fff; }
.app-toast.error   { background: var(--danger); color: #fff; }
@keyframes toastIn { from { transform: translateY(-10px); opacity: 0; } }

/* Modal cobrar */
.cobro-total-box {
    background: linear-gradient(135deg, #048af5, #02386b);
    color: #fff; padding: 18px 22px; border-radius: var(--radius);
    margin-bottom: 18px; text-align: center;
}
.cobro-total-box > div:first-child { font-size: .78rem; opacity: .85; text-transform: uppercase; letter-spacing: .05em; }
.cobro-total-num { font-size: 2rem; font-weight: 800; margin: 4px 0; font-variant-numeric: tabular-nums; }
.cobro-total-sub { font-size: .78rem; opacity: .78; }

.tipo-pills { display: flex; gap: 6px; flex-wrap: wrap; }
.tipo-pill {
    flex: 1; min-width: 90px;
    background: var(--surface-2); color: var(--text-muted);
    border: 2px solid var(--border); border-radius: 10px;
    padding: 10px 8px; font-size: .82rem; font-weight: 600;
    cursor: pointer; transition: .12s;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.tipo-pill:hover { border-color: var(--primary); color: var(--primary); }
.tipo-pill.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.tipo-pill.disabled { opacity: .4; cursor: not-allowed; pointer-events: none; }

.cobro-vuelto-row {
    display: flex; justify-content: space-between; align-items: baseline;
    margin-top: 8px; padding: 8px 14px;
    background: var(--surface-2); border-radius: 8px;
    font-size: .85rem; color: var(--text-muted);
}
.cobro-vuelto-row strong { color: var(--success); font-size: 1.1rem; font-variant-numeric: tabular-nums; }
.cobro-vuelto-row.negativo strong { color: var(--danger); }

/* Cliente widget (solo visible en boleta/factura) */
.cliente-search-wrap { display: flex; gap: 6px; }
.cliente-search-wrap input { flex: 1; }
.cliente-sugerencias {
    margin-top: 4px; background: var(--surface);
    border: 1px solid var(--border); border-radius: var(--radius-sm);
    max-height: 180px; overflow-y: auto;
}
.cliente-sugerencias:empty { display: none; }
.cliente-sugerencias .sugerencia {
    padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-light);
    font-size: .85rem; transition: background .1s;
}
.cliente-sugerencias .sugerencia:hover { background: var(--primary-light); color: var(--primary); }
.cliente-sugerencias .sugerencia:last-child { border-bottom: none; }
.cliente-sugerencias .sug-doc { font-size: .72rem; color: var(--text-muted); font-family: monospace; }
.cliente-pill {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 7px 12px; background: var(--primary-light); color: var(--primary);
    border-radius: 8px; margin-top: 6px; font-size: .88rem; font-weight: 600;
}
.cliente-pill button {
    background: none; border: none; cursor: pointer;
    color: var(--primary); padding: 2px; border-radius: 4px;
    font-size: .75rem;
}
.cliente-pill button:hover { background: rgba(0,0,0,.08); }

/* === Modal "Elegir mesa" (reutilizado para mover item y unir mesa) === */
.pick-mesa-grid {
    display: grid; gap: 10px;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    max-height: 55vh; overflow-y: auto; padding: 2px;
}
.pick-mesa-card {
    background: var(--surface); border: 2px solid var(--border);
    border-radius: 10px; padding: 12px 10px; cursor: pointer;
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    transition: all .12s;
}
.pick-mesa-card:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,.08); }
.pick-mesa-card.libre    { border-color: #16a34a; background: rgba(22,163,74,.05); }
.pick-mesa-card.libre:hover    { background: rgba(22,163,74,.12); }
.pick-mesa-card.ocupada  { border-color: #0471cd; background: rgba(4,113,205,.06); }
.pick-mesa-card.ocupada:hover  { background: rgba(4,113,205,.14); }
.pick-mesa-numero { font-size: 1.4rem; font-weight: 800; color: var(--text); }
.pick-mesa-nombre { font-size: .72rem; color: var(--text-muted); text-align: center; min-height: 1em; }
.pick-mesa-estado { font-size: .68rem; font-weight: 600; padding: 1px 7px; border-radius: 10px; }
.pick-mesa-card.libre   .pick-mesa-estado { background: #dcfce7; color: #15803d; }
.pick-mesa-card.ocupada .pick-mesa-estado { background: #dbeafe; color: #0471cd; }
.pick-mesa-empty { padding: 30px; text-align: center; color: var(--text-muted); font-size: .9rem; }

/* Boton mover item (va al lado del tacho) */
.item-move {
    background: none; border: none; cursor: pointer;
    color: var(--text-muted); padding: 4px 6px; border-radius: 4px;
    font-size: .95rem; transition: all .1s;
}
.item-move:hover { color: var(--primary); background: var(--primary-light); }
</style>

<script>
// ---------------------------------------------------------------
// Estado
// ---------------------------------------------------------------
const MESA_ID    = <?= json_encode($resueltaMesaId) ?>;
// COMANDA_ID puede ser null al entrar (mesa libre sin pedidos todavia).
// Se crea recien cuando se agrega el primer producto desde el catalogo.
let COMANDA_ID   = <?= json_encode($resueltaComandaId) ?>;
const MESA_INFO  = <?= json_encode(['numero' => $mesaInfo['numero'], 'nombre' => $mesaInfo['nombre']], JSON_UNESCAPED_UNICODE) ?>;
const PRECUENTA_EMPRESA = <?= json_encode(sesionTenantNombre() ?: ($mesaInfo['nombre'] ?? 'Negocio'), JSON_UNESCAPED_UNICODE) ?>;
const API = 'api.php';

let COMANDA = null;
let ITEMS = [];
let PRODUCTOS = [];
let CATEGORIA_FILTRO = 0;
let TEXT_FILTRO = '';

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------
function escH(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function soles(n) { return 'S/ ' + parseFloat(n || 0).toFixed(2); }
function parsePg(ts) { return ts ? new Date(String(ts).replace(' ', 'T')) : null; }
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function formatCrono(ms) {
    const t = Math.max(0, Math.floor(ms / 1000));
    const h = String(Math.floor(t / 3600)).padStart(2,'0');
    const m = String(Math.floor((t % 3600) / 60)).padStart(2,'0');
    const s = String(t % 60).padStart(2,'0');
    return `${h}:${m}:${s}`;
}
function showToast(msg, type = 'info') {
    const icons = { success:'check-circle', error:'exclamation-circle', info:'info-circle' };
    const t = document.createElement('div');
    t.className = `app-toast ${type}`;
    t.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'}"></i> ${escH(msg)}`;
    const c = document.getElementById('toast-container');
    if (c) { c.appendChild(t); setTimeout(() => t.remove(), 2800); }
}

async function postJson(url, body) {
    const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body || {}),
    });
    try {
        return await r.json();
    } catch (error) {
        throw new Error(`La API devolvio una respuesta no valida (HTTP ${r.status}).`);
    }
}

// ---------------------------------------------------------------
// Carga inicial y refresh
// ---------------------------------------------------------------
async function cargarComanda() {
    // Modo preview: mesa libre sin comanda abierta todavia. No se llama al
    // backend; se renderiza el header con MESA_INFO y la lista vacia con el
    // placeholder "Agrega el primer pedido...".
    if (!COMANDA_ID) {
        COMANDA = null;
        ITEMS   = [];
        renderHeader();
        renderItems();
        return;
    }
    try {
        const d = await (await fetch(`${API}?action=comanda_detalle&id=${COMANDA_ID}`)).json();
        if (d.error) {
            showToast(d.message, 'error');
            setTimeout(() => window.location = 'index.php', 1500);
            return;
        }
        COMANDA = d.comanda;
        ITEMS   = d.items || [];
        renderHeader();
        renderItems();
    } catch (err) {
        showToast('Error al cargar la comanda', 'error');
    }
}

async function cargarProductos() {
    try {
        const data = await (await fetch(`${API}?action=productos`)).json();
        if (!Array.isArray(data)) { showToast('Error cargando productos', 'error'); return; }
        PRODUCTOS = data.filter(p => (p.product_type || 'product') === 'service' || parseFloat(p.stock) >= 0);
        renderCategorias();
        renderProductos();
    } catch (err) {
        showToast('Error de red cargando productos', 'error');
    }
}

// ---------------------------------------------------------------
// Render: cabecera
// ---------------------------------------------------------------
function renderHeader() {
    // En modo preview (sin comanda) usamos MESA_INFO y no mostramos cronometro.
    const numero = COMANDA ? COMANDA.mesa_numero : MESA_INFO.numero;
    const nombre = COMANDA ? COMANDA.mesa_nombre : MESA_INFO.nombre;
    document.getElementById('mesa-num-txt').textContent = numero;
    document.getElementById('mesa-nom-txt').textContent = nombre ? ' — ' + nombre : '';

    if (!COMANDA) {
        document.getElementById('mesa-mozo-txt').textContent = `${(window.LABELS?.mesa?.s || 'Mesa')} libre — agrega el primer producto para abrirla`;
        const crono = document.getElementById('mesa-crono');
        crono.textContent = '—';
        crono.dataset.crono = '';
        return;
    }
    document.getElementById('mesa-mozo-txt').textContent = 'por ' + (COMANDA.usuario_apertura_nombre || '—');
    document.getElementById('mesa-crono').dataset.crono = COMANDA.hora_apertura || '';
    tickCrono();
}
function tickCrono() {
    const el = document.getElementById('mesa-crono');
    const start = parsePg(el.dataset.crono);
    if (!start || isNaN(start)) return;
    el.textContent = formatCrono(Date.now() - start.getTime());
}

// ---------------------------------------------------------------
// Render: lista de items
// ---------------------------------------------------------------
function renderItems() {
    const list = document.getElementById('items-list');
    const totalItems = ITEMS.length;
    document.getElementById('items-count-txt').textContent =
        `${totalItems} ${totalItems === 1 ? 'item' : 'items'}`;

    if (!totalItems) {
        list.innerHTML = `<div class="items-empty"><i class="fas fa-utensils"></i><br>Agrega el primer pedido desde el catalogo</div>`;
    } else {
        list.innerHTML = ITEMS.map(renderItem).join('');
    }

    const total = ITEMS.reduce((s, it) => s + parseFloat(it.subtotal || 0), 0);
    document.getElementById('comanda-total').textContent = soles(total);
    document.getElementById('btn-cobrar').disabled = totalItems === 0;
    document.getElementById('btn-precuenta').disabled = totalItems === 0;
}

// === Precuenta (solo front, documento NO fiscal) ===
// Imprime el consumo actual de la mesa como pre-ticket para que el cliente lo
// revise antes de pagar. NO cierra la mesa, NO crea venta, NO toca stock ni va
// a SUNAT. Se puede reimprimir las veces que haga falta.
function buildPrecuentaHTML() {
    const total = ITEMS.reduce((s, it) => s + parseFloat(it.subtotal || 0), 0);
    const now = new Date();
    const fecha = now.toLocaleDateString('es-PE', {day:'2-digit', month:'2-digit', year:'numeric'});
    const hora  = now.toLocaleTimeString('es-PE', {hour:'2-digit', minute:'2-digit'});
    const mesaNum = COMANDA ? COMANDA.mesa_numero : MESA_INFO.numero;
    const itemsHtml = ITEMS.map(it => {
        const cant = parseFloat(it.cantidad) || 0;
        const cantTxt = Number.isInteger(cant) ? String(cant) : cant.toFixed(2);
        const pu = parseFloat(it.precio_unitario) || 0;
        const sub = parseFloat(it.subtotal) || 0;
        return `<div style="margin-bottom:5px"><div style="display:flex;justify-content:space-between;font-size:9pt"><span style="flex:1;padding-right:6px;word-break:break-word">${escH(it.producto_nombre)}</span><span style="white-space:nowrap;font-weight:700">S/${sub.toFixed(2)}</span></div><div style="font-size:8pt">${cantTxt} x S/${pu.toFixed(2)}</div></div>`;
    }).join('');
    return `<div style="font-family:'Arial Narrow',Arial,Helvetica,sans-serif;color:#000;font-size:10pt;line-height:1.5;width:100%;font-weight:500">
        <div style="text-align:center;margin-bottom:8px">
            <div style="font-size:12pt;font-weight:900;text-transform:uppercase;letter-spacing:1px">${escH(PRECUENTA_EMPRESA)}</div>
        </div>
        <div style="border-top:2px solid #000;border-bottom:2px solid #000;text-align:center;padding:5px 0;margin-bottom:8px">
            <div style="font-size:11pt;font-weight:800;letter-spacing:2px">PRECUENTA</div>
            <div style="font-size:7.5pt;font-weight:700">DOCUMENTO NO VÁLIDO COMO COMPROBANTE DE PAGO</div>
        </div>
        <div style="font-size:9pt;margin-bottom:6px">
            <div>Mesa  : ${escH(String(mesaNum))}</div>
            <div>Fecha : ${fecha} ${hora}</div>
        </div>
        <div style="border-top:1px dashed #000;margin:6px 0"></div>
        <div style="display:flex;justify-content:space-between;font-size:9pt;font-weight:700;margin-bottom:3px"><span>DESCRIPCIÓN</span><span>TOTAL</span></div>
        <div style="border-top:1px dashed #000;margin:6px 0"></div>
        <div style="margin-bottom:4px">${itemsHtml}</div>
        <div style="border-top:2px solid #000;margin:6px 0"></div>
        <div style="display:flex;justify-content:space-between;font-size:12pt;font-weight:800"><span>TOTAL:</span><span>S/ ${total.toFixed(2)}</span></div>
        <div style="border-top:2px solid #000;margin:6px 0"></div>
        <div style="text-align:center;font-size:8pt;margin-top:6px">** Esto NO es una boleta ni factura **<br>Pida su comprobante al momento de pagar</div>
    </div>`;
}

function imprimirPrecuenta() {
    if (!ITEMS.length) { showToast('No hay items en la mesa', 'error'); return; }
    const inner = buildPrecuentaHTML();
    // Mismo criterio anti-borroso + 80mm termico que el ticket del POS.
    const style = `*{margin:0;padding:0;box-sizing:border-box}
        html,body{-webkit-print-color-adjust:exact !important;print-color-adjust:exact !important;-webkit-font-smoothing:none;text-rendering:geometricPrecision}
        body{font-family:'Arial Narrow',Arial,Helvetica,sans-serif;font-size:9pt;font-weight:500;line-height:1.5;color:#000;width:80mm;margin:0 auto;padding:4mm 3mm}`;
    const w = window.open('', '_blank', 'width=420,height=700');
    if (!w) { showToast('Habilita las ventanas emergentes para imprimir', 'error'); return; }
    w.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>Precuenta</title><style>${style}</style></head><body>${inner}</body></html>`);
    w.document.close();
    w.focus();
    setTimeout(() => {
        // Alto de pagina dinamico segun el contenido (termica no respeta "auto").
        const heightMm = Math.ceil(w.document.body.scrollHeight * 0.2646) + 15;
        const ps = w.document.createElement('style');
        ps.textContent = `@media print { @page { size:80mm ${heightMm}mm; margin:4mm 3mm; } body { padding:0; } }`;
        w.document.head.appendChild(ps);
        w.print();
    }, 300);
}

function renderItem(it) {
    const hora = parsePg(it.hora_pedido);
    const horaTxt = hora ? hora.toLocaleTimeString('es-PE', {hour:'2-digit', minute:'2-digit'}) : '';
    const cant = parseFloat(it.cantidad);
    const cantTxt = Number.isInteger(cant) ? String(cant) : cant.toFixed(2);
    const nombreTxt = it.unidad_medida
        ? `${escH(it.producto_nombre)} <span style="color:#4f46e5;font-weight:600">(${escH(it.unidad_medida)})</span>`
        : escH(it.producto_nombre);
    return `
        <div class="item-row">
            <div>
                <div class="item-nombre">${nombreTxt}</div>
                <div class="item-meta">${horaTxt} · ${soles(it.precio_unitario)} c/u${it.mozo_nombre ? ' · ' + escH(it.mozo_nombre) : ''}</div>
            </div>
            <div class="item-right">
                <div class="item-cant">
                    <button onclick="cambiarCant(${it.id}, ${cant - 1})" ${cant <= 1 ? 'disabled' : ''}>-</button>
                    <span>${cantTxt}</span>
                    <button onclick="cambiarCant(${it.id}, ${cant + 1})">+</button>
                </div>
                <div class="item-subtotal">${soles(it.subtotal)}</div>
                <button class="item-move" title="Mover a otra ${(window.LABELS?.mesa?.s || 'mesa').toLowerCase()}" onclick="moverItemAMesa(${it.id})"><i class="fas fa-arrow-right-arrow-left"></i></button>
                <button class="item-del" title="Quitar" onclick="quitarItem(${it.id})"><i class="fas fa-trash"></i></button>
            </div>
        </div>`;
}

// ---------------------------------------------------------------
// Render: catalogo (categorias + grid)
// ---------------------------------------------------------------
function renderCategorias() {
    const seen = new Map();
    PRODUCTOS.forEach(p => {
        const id = parseInt(p.categoria_id || 0, 10);
        if (!id) return;
        if (!seen.has(id)) seen.set(id, p.categoria || `Cat ${id}`);
    });
    const pills = document.getElementById('categorias-pills');
    const sorted = Array.from(seen.entries()).sort((a,b) => a[1].localeCompare(b[1]));
    pills.innerHTML =
        `<button class="cat-pill ${CATEGORIA_FILTRO === 0 ? 'active' : ''}" data-cat="0" onclick="filtrarCat(0, this)">Todos</button>` +
        sorted.map(([id, nom]) =>
            `<button class="cat-pill ${CATEGORIA_FILTRO === id ? 'active' : ''}" data-cat="${id}" onclick="filtrarCat(${id}, this)">${escH(nom)}</button>`
        ).join('');
}

function filtrarCat(id, btnEl) {
    CATEGORIA_FILTRO = id;
    document.querySelectorAll('.cat-pill').forEach(b => b.classList.toggle('active', b === btnEl));
    renderProductos();
}

function renderProductos() {
    const grid = document.getElementById('productos-grid');
    const texto = TEXT_FILTRO.trim().toLowerCase();
    const filtrados = PRODUCTOS.filter(p => {
        if (CATEGORIA_FILTRO && parseInt(p.categoria_id || 0, 10) !== CATEGORIA_FILTRO) return false;
        if (!texto) return true;
        return (p.nombre || '').toLowerCase().includes(texto)
            || (p.codigo || '').toLowerCase().includes(texto)
            || (p.codigo_barras || '').toLowerCase().includes(texto);
    });
    if (!filtrados.length) {
        grid.innerHTML = `<div class="items-empty" style="grid-column:1/-1"><i class="fas fa-search"></i><br>Sin resultados</div>`;
        return;
    }
    grid.innerHTML = filtrados.map(p => {
        const esServ = (p.product_type || 'product') === 'service';
        const stock  = parseFloat(p.stock || 0);
        const sinStock = !esServ && stock <= 0;
        const stockBajo = !esServ && stock > 0 && p.stock_minimo && stock <= parseFloat(p.stock_minimo);
        const click = sinStock ? '' : `onclick="agregarProducto(${p.id})"`;
        return `
            <div class="producto-card ${sinStock ? 'sin-stock' : ''}" ${click}>
                ${esServ ? '<span class="prod-service-badge">SERV</span>' : ''}
                <div class="prod-nombre">${escH(p.nombre)}</div>
                <div class="prod-precio">${soles(p.precio_venta)}</div>
                ${esServ
                    ? '<div class="prod-stock">servicio</div>'
                    : `<div class="prod-stock ${stockBajo ? 'bajo' : ''}">Stock: ${stock}</div>`}
            </div>`;
    }).join('');
}

// ---------------------------------------------------------------
// Acciones (agregar / quitar / cambiar cantidad)
// ---------------------------------------------------------------
let accionEnCurso = false;

// Cache de presentaciones por producto_id para no repetir fetch si el mozo
// agrega el mismo producto varias veces en la sesion.
const _presentacionesCacheMesa = {};
let _productoParaUnidadMesa = null;
let _presentacionesActualesMesa = [];

async function agregarProducto(productoId, cantidad = 1) {
    if (accionEnCurso) return;
    const producto = PRODUCTOS.find(p => parseInt(p.id) === productoId);
    if (!producto) return;

    // Si ya tenemos las presentaciones cacheadas, saltar el fetch
    if (_presentacionesCacheMesa[productoId] !== undefined) {
        return continuarAgregarAlComanda(producto, _presentacionesCacheMesa[productoId], cantidad);
    }

    try {
        const r = await fetch(`../inventario/api.php?action=precios_unidad_listar&producto_id=${productoId}`);
        const data = await r.json();
        _presentacionesCacheMesa[productoId] = Array.isArray(data) ? data : [];
        return continuarAgregarAlComanda(producto, _presentacionesCacheMesa[productoId], cantidad);
    } catch (e) {
        _presentacionesCacheMesa[productoId] = [];
        return continuarAgregarAlComanda(producto, [], cantidad);
    }
}

// Si no hay presentaciones alternativas, agregar directo. Si hay, abrir modal.
function continuarAgregarAlComanda(producto, presentaciones, cantidad) {
    if (!presentaciones || !presentaciones.length) {
        return agregarAlComandaConUnidad(producto, null, cantidad);
    }
    _productoParaUnidadMesa     = producto;
    _presentacionesActualesMesa = presentaciones;

    document.getElementById('unidad-mesa-producto-nombre').textContent = producto.nombre;
    const base = { unidad_medida: null, precio_venta: producto.precio_venta };
    const opciones = [base, ...presentaciones];
    document.getElementById('unidad-mesa-opciones').innerHTML = opciones.map(op => {
        const label  = op.unidad_medida || 'Unidad';
        const precio = parseFloat(op.precio_venta || 0).toFixed(2);
        const attr   = op.unidad_medida ? `'${op.unidad_medida.replace(/'/g, "\\'")}'` : 'null';
        return `<button type="button" class="btn btn-outline" style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;width:100%;margin-bottom:8px"
            onclick="seleccionarUnidadMesa(${attr})">
            <span style="font-weight:600">${label}</span>
            <span style="color:var(--primary);font-weight:700">S/ ${precio}</span>
        </button>`;
    }).join('');
    openModal('modal-unidad-mesa');
}

function seleccionarUnidadMesa(unidadMedida) {
    const producto = _productoParaUnidadMesa;
    closeModal('modal-unidad-mesa');
    if (!producto) return;
    agregarAlComandaConUnidad(producto, unidadMedida, 1);
}

async function agregarAlComandaConUnidad(producto, unidadMedida, cantidad) {
    if (accionEnCurso) return;
    accionEnCurso = true;

    // Modo preview: la mesa no tiene comanda abierta todavia -- crearla ahora.
    if (!COMANDA_ID) {
        const open = await postJson(`${API}?action=abrir_comanda`, { mesa_id: MESA_ID });
        if (open.error) {
            accionEnCurso = false;
            showToast(open.message || 'No se pudo abrir la mesa', 'error');
            return;
        }
        COMANDA_ID = parseInt(open.id);
    }

    const d = await postJson(`${API}?action=comanda_agregar_item`, {
        comanda_id: COMANDA_ID,
        producto_id: parseInt(producto.id),
        cantidad: cantidad,
        unidad_medida: unidadMedida,
    });
    accionEnCurso = false;
    if (d.error) { showToast(d.message, 'error'); return; }
    showToast(d.message, 'success');
    await Promise.all([cargarComanda(), cargarProductos()]);
}

async function quitarItem(itemId) {
    if (accionEnCurso) return;
    const ok = await appConfirm('¿Quitar este item de la comanda? El stock será repuesto.', {
        titulo: 'Quitar item',
        textoOk: 'Sí, quitar',
        textoNo: 'Cancelar',
        tipo: 'danger',
    });
    if (!ok) return;
    accionEnCurso = true;
    const d = await postJson(`${API}?action=comanda_quitar_item`, { item_id: itemId });
    accionEnCurso = false;
    if (d.error) { showToast(d.message, 'error'); return; }
    showToast(d.message, 'success');
    if (d.comanda_cerrada) {
        // Era el ultimo item: la comanda se auto-cerro para liberar la mesa.
        // Volvemos al grid para que se vea como libre y el cronometro parta de 0.
        setTimeout(() => { window.location = 'index.php'; }, 600);
        return;
    }
    await Promise.all([cargarComanda(), cargarProductos()]);
}

async function cambiarCant(itemId, nuevaCant) {
    if (accionEnCurso || nuevaCant < 1) return;
    accionEnCurso = true;
    const d = await postJson(`${API}?action=comanda_cambiar_cantidad`, {
        item_id: itemId, cantidad: nuevaCant,
    });
    accionEnCurso = false;
    if (d.error) { showToast(d.message, 'error'); return; }
    await Promise.all([cargarComanda(), cargarProductos()]);
}

// --- Elegir mesa (modal reutilizable para mover item y unir mesa) ---
// opts: { titulo, subtitulo, filtro: 'todas'|'ocupadas', excluirId, onPick(mesa) }
async function openPickMesa(opts) {
    document.getElementById('pick-mesa-titulo').innerHTML =
        `<i class="fas fa-chair" style="color:var(--primary);margin-right:8px"></i> ${escH(opts.titulo || 'Elegir mesa')}`;
    document.getElementById('pick-mesa-subtitulo').textContent = opts.subtitulo || '';
    const grid = document.getElementById('pick-mesa-grid');
    grid.innerHTML = '<div class="pick-mesa-empty"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';
    openModal('modal-pick-mesa');
    let mesas = [];
    try {
        const r = await fetch(`${API}?action=mesas_estado`);
        mesas = await r.json();
        if (!Array.isArray(mesas)) mesas = [];
    } catch (e) { mesas = []; }

    const excluirId = parseInt(opts.excluirId || 0, 10);
    const filtradas = mesas.filter(m => {
        if (parseInt(m.id, 10) === excluirId) return false;
        if (opts.filtro === 'ocupadas' && !m.comanda_id) return false;
        return true;
    });
    if (!filtradas.length) {
        const lbl = (window.LABELS?.mesa?.p || 'mesas').toLowerCase();
        grid.innerHTML = `<div class="pick-mesa-empty">No hay ${lbl} disponibles.</div>`;
        return;
    }
    grid.innerHTML = filtradas.map(m => {
        const ocupada = !!m.comanda_id;
        const cls   = ocupada ? 'ocupada' : 'libre';
        const estado = ocupada ? 'Ocupada' : 'Libre';
        const nombre = escH(m.nombre || '');
        return `
            <div class="pick-mesa-card ${cls}" onclick="_pickMesaChoose(${m.id})">
                <div class="pick-mesa-numero">${escH(m.numero)}</div>
                <div class="pick-mesa-nombre">${nombre || '&nbsp;'}</div>
                <span class="pick-mesa-estado">${estado}</span>
            </div>
        `;
    }).join('');
    _pickMesaCache = filtradas;
    _pickMesaCallback = opts.onPick || null;
}

let _pickMesaCache = [];
let _pickMesaCallback = null;

function _pickMesaChoose(mesaId) {
    const mesa = _pickMesaCache.find(m => parseInt(m.id, 10) === parseInt(mesaId, 10));
    closeModal('modal-pick-mesa');
    if (mesa && typeof _pickMesaCallback === 'function') {
        _pickMesaCallback(mesa);
    }
}

async function moverItemAMesa(itemId) {
    if (accionEnCurso) return;
    const labelMesa = (window.LABELS?.mesa?.s || 'mesa').toLowerCase();
    await openPickMesa({
        titulo: `Mover item a otra ${labelMesa}`,
        subtitulo: `El item completo pasa a la ${labelMesa} elegida. Si la ${labelMesa} esta libre, se abre una nueva comanda ahi.`,
        filtro: 'todas',
        excluirId: MESA_ID,
        onPick: async (mesa) => {
            accionEnCurso = true;
            const d = await postJson(`${API}?action=comanda_mover_item`, {
                item_id: itemId, mesa_destino_id: mesa.id,
            });
            accionEnCurso = false;
            if (d.error) { showToast(d.message, 'error'); return; }
            showToast(d.message, 'success');
            if (d.comanda_cerrada) {
                setTimeout(() => { window.location = 'index.php'; }, 600);
                return;
            }
            await Promise.all([cargarComanda(), cargarProductos()]);
        },
    });
}

// --- Unir mesa: esta mesa absorbe otra que ya tiene comanda abierta ---
async function unirConMesa() {
    if (accionEnCurso) return;
    const labelMesa = (window.LABELS?.mesa?.s || 'mesa').toLowerCase();
    if (!COMANDA_ID) {
        showToast(`Primero agrega un item a esta ${labelMesa} para poder unir otra.`, 'error');
        return;
    }
    await openPickMesa({
        titulo: `Unir otra ${labelMesa} en esta`,
        subtitulo: `Los items de la ${labelMesa} elegida pasan a esta comanda y aquella queda libre.`,
        filtro: 'ocupadas',
        excluirId: MESA_ID,
        onPick: async (mesa) => {
            const ok = await appConfirm(
                `Vas a fusionar la ${labelMesa} #${mesa.numero} en esta. Los items de la ${labelMesa} #${mesa.numero} pasaran aqui y esa ${labelMesa} quedara libre. ¿Confirmas?`,
                { titulo: 'Confirmar union', textoOk: 'Si, unir', textoNo: 'Cancelar', tipo: 'warning' }
            );
            if (!ok) return;
            accionEnCurso = true;
            const d = await postJson(`${API}?action=comanda_unir_mesa`, {
                comanda_destino_id: COMANDA_ID, mesa_fuente_id: mesa.id,
            });
            accionEnCurso = false;
            if (d.error) { showToast(d.message, 'error'); return; }
            showToast(d.message, 'success');
            await Promise.all([cargarComanda(), cargarProductos()]);
        },
    });
}

// ---------------------------------------------------------------
// Cobro (Fase E/E.1)
// ---------------------------------------------------------------
let TIPO_COMP_SELECT = 'ticket';
let CLIENTE_SELECT = null;      // {id, nombre, documento, tipo_doc}
let _clienteBuscarTimer = null;

// ================================================================
// Cobro de mesa -- usa el MISMO modal #modal-cobro que pos.php (via el
// partial _modal_cobro.php). Las funciones JS que invoca el modal
// (onComprobanteChange, onTipoPagoChange, calcularVuelto, toggleSwitch,
// confirmarVenta, previsualizarComprobante, toggleDividirCuenta,
// addSplitPaymentRow, addCreditInstallmentRow, openModalNuevoCliente)
// estan definidas abajo adaptadas al contexto de mesa.
// ================================================================

let _seriesCacheMesa = null;

function cobrar() {
    if (!ITEMS.length) {
        showToast('No hay items para cobrar', 'error');
        return;
    }
    const total = ITEMS.reduce((s, it) => s + parseFloat(it.subtotal || 0), 0);

    document.getElementById('tipo-comprobante').value   = 'boleta';
    document.getElementById('tipo-impresion').value     = 'ticket';
    document.getElementById('tipo-pago').value          = 'efectivo';
    document.getElementById('cuenta-banco').value       = 'caja_chica';
    document.getElementById('monto-recibido').value     = total.toFixed(2);
    document.getElementById('monto-cliente').value      = '';
    document.getElementById('cobro-observacion').value  = '';
    document.getElementById('cobro-total-venta').textContent = soles(total);
    document.getElementById('cobro-total').textContent       = soles(total);
    document.getElementById('vuelto').textContent            = soles(0);

    // Reset toggles al default (obs=ON, credito=OFF).
    document.getElementById('check-obs').checked      = true;
    const trackObs = document.getElementById('track-obs');
    trackObs.style.background = 'var(--primary)';
    trackObs.firstElementChild.style.transform = 'translateX(17px)';
    document.getElementById('check-credito').checked  = false;
    const trackCred = document.getElementById('track-credito');
    trackCred.style.background = '#d1d5db';
    trackCred.firstElementChild.style.transform = 'translateX(0)';

    TIPO_COMP_SELECT = 'boleta';
    CLIENTE_SELECT   = null;

    // Reset del split al abrir -- cada comanda arranca con cobro simple.
    splitPaymentEnabled = false;
    splitPaymentRows    = [];
    renderSplitPaymentPanel();

    cargarSeriesCobro();
    onComprobanteChange();
    onTipoPagoChange();

    const btn = document.getElementById('btn-confirmar-venta');
    btn.disabled  = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Pagar y emitir comprobante';

    openModal('modal-cobro');
    setTimeout(() => document.getElementById('monto-cliente').focus(), 80);
}

// ---------------- Series disponibles ----------------
async function cargarSeriesCobro() {
    if (!_seriesCacheMesa) {
        try {
            const r = await fetch(`${API}?action=series_disponibles`);
            const d = await r.json();
            _seriesCacheMesa = (d && !d.error) ? d : {};
        } catch (e) { _seriesCacheMesa = {}; }
    }
    pintarSerieCobro();
}

function pintarSerieCobro() {
    const tipo = document.getElementById('tipo-comprobante').value;
    const sel  = document.getElementById('cobro-serie');
    const list = (_seriesCacheMesa && _seriesCacheMesa[tipo]) || [];
    sel.innerHTML = list.length
        ? list.map(s => {
            const codigo = (s && typeof s === 'object') ? (s.serie || '') : String(s);
            return `<option value="${codigo}">${codigo}</option>`;
          }).join('')
        : '<option value="">—</option>';
}

// --- Handlers que el HTML del partial invoca directamente (mismos nombres que POS) ---
function onComprobanteChange() {
    TIPO_COMP_SELECT = document.getElementById('tipo-comprobante').value;
    pintarSerieCobro();
}

function onTipoPagoChange() {
    const metodo = document.getElementById('tipo-pago').value;
    document.getElementById('pago-simple-efectivo').style.display = metodo === 'efectivo' ? '' : 'none';
    calcularVuelto();
}

function calcularVuelto() {
    const total     = ITEMS.reduce((s, it) => s + parseFloat(it.subtotal || 0), 0);
    const metodo    = document.getElementById('tipo-pago').value;
    const recibido  = parseFloat(document.getElementById('monto-cliente').value || 0);
    const vueltoEl  = document.getElementById('vuelto');
    if (metodo !== 'efectivo') { vueltoEl.textContent = soles(0); return; }
    const vuelto = recibido - total;
    vueltoEl.textContent = soles(Math.max(0, vuelto));
    vueltoEl.style.color = vuelto < 0 ? 'var(--danger)' : 'var(--success)';
}

function toggleSwitch(checkboxId, trackId, ev) {
    if (ev) ev.preventDefault();
    const chk   = document.getElementById(checkboxId);
    const track = document.getElementById(trackId);
    chk.checked = !chk.checked;
    track.style.background = chk.checked ? 'var(--primary)' : '#d1d5db';
    track.firstElementChild.style.transform = chk.checked ? 'translateX(17px)' : 'translateX(0)';
}

// ===== Dividir cuenta (split payment) =====
// Portado de pos.php. Permite repartir el cobro de UNA comanda entre varios
// metodos (efectivo + yape + tarjeta...). Sigue siendo UNA venta/comprobante
// -- el partido en N comprobantes distintos es feature aparte, ver item #3
// de docs/implementaciones_futuras.md.
let splitPaymentEnabled = false;
let splitPaymentRows    = [];

function round2(value)           { return Math.round((Number(value) || 0) * 100) / 100; }
function formatMoneyValue(value) { return `S/ ${Number(value || 0).toFixed(2)}`; }

function getCheckoutTotalAmount() {
    return round2(ITEMS.reduce((s, it) => s + parseFloat(it.subtotal || 0), 0));
}

function getPaymentMethodOptions(selected = 'efectivo') {
    const methods = [
        ['efectivo', 'Efectivo'], ['yape', 'Yape'], ['plin', 'Plin'],
        ['tarjeta', 'Visa/Mastercard'], ['transferencia', 'Transferencia'],
    ];
    return methods.map(([v, l]) => `<option value="${v}" ${String(selected) === v ? 'selected' : ''}>${l}</option>`).join('');
}

function getSplitPaymentRowsFromState() {
    return splitPaymentRows
        .map(r => ({
            method: String(r.method || 'efectivo').trim() || 'efectivo',
            amount: Math.max(0, round2(r.amount)),
        }))
        .filter(r => r.amount > 0);
}

function getSplitPaymentPayload(total) {
    const rows      = getSplitPaymentRowsFromState();
    const sum       = round2(rows.reduce((acc, r) => acc + r.amount, 0));
    const remainder = round2(total - sum);
    const cashTotal = round2(rows.filter(r => r.method === 'efectivo').reduce((acc, r) => acc + r.amount, 0));
    return { rows, sum, remainder, cashTotal };
}

function toggleDividirCuenta() {
    splitPaymentEnabled = !splitPaymentEnabled;
    if (splitPaymentEnabled) {
        const metodo = document.getElementById('tipo-pago')?.value || 'efectivo';
        splitPaymentRows = [{ method: metodo, amount: getCheckoutTotalAmount() }];
    } else {
        splitPaymentRows = [];
        const tp = document.getElementById('tipo-pago');
        if (tp) tp.value = 'efectivo';
    }
    renderSplitPaymentPanel();
    calcularVuelto();
}

function addSplitPaymentRow() {
    splitPaymentEnabled = true;
    splitPaymentRows.push({ method: 'efectivo', amount: 0 });
    renderSplitPaymentPanel();
    calcularVuelto();
}

function removeSplitPaymentRow(index) {
    splitPaymentRows.splice(index, 1);
    if (!splitPaymentRows.length) {
        splitPaymentEnabled = false;
        const tp = document.getElementById('tipo-pago');
        if (tp) tp.value = 'efectivo';
    }
    renderSplitPaymentPanel();
    calcularVuelto();
}

function updateSplitPaymentRow(index, field, value) {
    if (!splitPaymentRows[index]) return;
    if (field === 'method') {
        splitPaymentRows[index].method = value;
        if (index === 0) {
            const m = document.getElementById('tipo-pago');
            if (m) m.value = value;
        }
    } else if (field === 'amount') {
        splitPaymentRows[index].amount = round2(value);
    }
    updateSplitPaymentSummary();
    calcularVuelto();
}

function updateSplitPaymentSummary() {
    const total        = getCheckoutTotalAmount();
    const breakdown    = getSplitPaymentRowsFromState();
    const totalMethods = breakdown.reduce((s, r) => s + r.amount, 0);
    const remaining    = round2(total - totalMethods);

    const totalEl = document.getElementById('split-total-methods');
    const remEl   = document.getElementById('split-remaining');
    if (totalEl) totalEl.textContent = formatMoneyValue(totalMethods);
    if (remEl) {
        remEl.textContent = formatMoneyValue(Math.max(0, remaining));
        remEl.parentElement?.classList.toggle('danger',  remaining  >  0.01);
        remEl.parentElement?.classList.toggle('success', Math.abs(remaining) <= 0.01);
    }

    const input = document.getElementById('monto-cliente');
    if (splitPaymentEnabled && breakdown.length === 1 && breakdown[0].method === 'efectivo' && input) {
        input.value = breakdown[0].amount.toFixed(2);
    }
}

function renderSplitPaymentPanel() {
    const panel         = document.getElementById('split-payment-panel');
    const rowsEl        = document.getElementById('split-payment-rows');
    const toggleBtn     = document.getElementById('btn-dividir-cuenta');
    const simpleSection = document.getElementById('pago-simple-section');
    if (!panel || !rowsEl) return;

    panel.style.display = splitPaymentEnabled ? 'block' : 'none';
    if (simpleSection) simpleSection.style.display = splitPaymentEnabled ? 'none' : 'block';
    if (toggleBtn) {
        toggleBtn.innerHTML = splitPaymentEnabled
            ? '<i class="fas fa-minus"></i> Volver al cobro simple'
            : '<i class="fas fa-plus"></i> Dividir cuenta';
    }
    if (!splitPaymentEnabled) {
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
    rowsEl.innerHTML = splitPaymentRows.map((row, index) => `
            <div class="split-pay-row">
                <div>
                    <label class="form-label" style="font-size:.71rem">Método ${index + 1}</label>
                    <select class="form-control" style="font-size:.82rem" onchange="updateSplitPaymentRow(${index}, 'method', this.value)">
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
                        title="Eliminar método" onclick="removeSplitPaymentRow(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `).join('');
    updateSplitPaymentSummary();
}

// Stubs de otras features del partial pendientes (ver docs/implementaciones_futuras.md).
function addCreditInstallmentRow() {
    appAlert('Venta a credito desde mesa estara disponible pronto.', { tipo: 'info', titulo: 'Pronto disponible' });
}
function previsualizarComprobante() {
    appAlert('Previsualizar comprobante estara disponible pronto en mesas.', { tipo: 'info', titulo: 'Pronto disponible' });
}
// openModalNuevoCliente ahora vive en _modal_nuevo_cliente.php (partial compartido).
// Esta funcion es el "contrato" que la modal invoca despues de guardar: le pasa
// el cliente recien creado y nosotros lo dejamos seleccionado en el Select2 de
// la modal de cobro + lo copiamos al estado CLIENTE_SELECT que lee confirmarVenta.
function setSelectedCliente(cliente) {
    if (!cliente || !cliente.id) return;
    const nombre = cliente.razon_social || cliente.nombre_completo
        || `${cliente.nombres || ''} ${cliente.apellidos || ''}`.trim();
    const doc    = cliente.numero_documento || cliente.ruc || cliente.dni || '';
    CLIENTE_SELECT = { id: cliente.id, nombre, doc, ruc: cliente.ruc || null };

    // Inyecta la opcion en el Select2 (sino muestra el id crudo) y la selecciona.
    if (typeof window.jQuery !== 'undefined') {
        const $ = window.jQuery;
        const $sel = $('#cliente-select');
        if ($sel.length) {
            if (!$sel.find(`option[value="${cliente.id}"]`).length) {
                $sel.append(new Option(`${nombre} · ${doc}`, cliente.id, true, true));
            } else {
                $sel.val(cliente.id);
            }
            $sel.trigger('change');
        }
    }
}

// --- Select2 del cliente (igual que el POS, pero sin crear nuevo cliente aun) ---
let _mesaCliSelect2 = null;
function initClienteSelect2() {
    if (_mesaCliSelect2) return;
    if (typeof window.jQuery === 'undefined') { setTimeout(initClienteSelect2, 150); return; }
    const $ = window.jQuery;
    $('#cliente-select').select2({
        placeholder: 'Buscar por nombre, DNI o RUC...',
        allowClear: true,
        dropdownParent: $('#modal-cobro'),
        minimumInputLength: 2,
        language: {
            inputTooShort: () => 'Escribe al menos 2 caracteres',
            noResults: () => 'Sin resultados',
            searching: () => 'Buscando...',
            errorLoading: () => 'Error al buscar',
        },
        ajax: {
            delay: 220,
            transport: (params, success, failure) => {
                const q = (params.data.q || '').trim();
                fetch(`${API}?action=buscar_cliente&q=${encodeURIComponent(q)}`)
                    .then(r => r.json())
                    .then(d => {
                        const row = Array.isArray(d) ? d[0] : d;
                        if (!row || !row.id) { success({ results: [] }); return; }
                        const nombre = row.razon_social || row.nombre_completo || `${row.nombres || ''} ${row.apellidos || ''}`.trim();
                        const doc    = row.numero_documento || row.ruc || row.dni || '';
                        success({ results: [{
                            id: row.id,
                            text: `${nombre} · ${doc}`,
                            _row: { id: row.id, nombre, doc, ruc: row.ruc },
                        }] });
                    })
                    .catch(failure);
            },
            processResults: (data) => data,
        },
    });
    $('#cliente-select').on('select2:select', (e) => {
        const data = e.params.data && e.params.data._row;
        CLIENTE_SELECT = data || null;
    });
    $('#cliente-select').on('select2:clear', () => { CLIENTE_SELECT = null; });
    _mesaCliSelect2 = true;
}

// Esta es la funcion que el boton "Pagar y emitir comprobante" del modal invoca.
async function confirmarVenta() {
    const tipoComp   = document.getElementById('tipo-comprobante').value;
    const tipoImpr   = document.getElementById('tipo-impresion').value;
    const serie      = document.getElementById('cobro-serie').value;
    const metodo     = document.getElementById('tipo-pago').value;
    const cuentaBanco= document.getElementById('cuenta-banco').value;
    const recibido   = parseFloat(document.getElementById('monto-cliente').value || 0);
    const obs        = document.getElementById('cobro-observacion').value.trim();
    const imprimirObs= document.getElementById('check-obs').checked;
    const total      = ITEMS.reduce((s, it) => s + parseFloat(it.subtotal || 0), 0);

    if (tipoComp === 'factura') {
        if (!CLIENTE_SELECT || !CLIENTE_SELECT.id) {
            showToast('Para factura necesitas seleccionar un cliente con RUC', 'error'); return;
        }
        if (!CLIENTE_SELECT.ruc) {
            showToast('El cliente seleccionado no tiene RUC. Edita el cliente o elige otro.', 'error'); return;
        }
    }

    // Tope SUNAT: boleta a comprador no identificado exige DNI desde S/<tope>.
    // (El backend lo valida igual; esto solo adelanta el aviso.)
    if (tipoComp === 'boleta') {
        const TOPE = <?= defined('SUNAT_BOLETA_TOPE_SIN_DNI') ? (int) SUNAT_BOLETA_TOPE_SIN_DNI : 700 ?>;
        const ruc = String(CLIENTE_SELECT?.ruc || '').trim();
        const doc = String(CLIENTE_SELECT?.doc || CLIENTE_SELECT?.numero_documento || CLIENTE_SELECT?.dni || '').trim();
        const identificado = ruc !== '' || (/^\d{8}$/.test(doc) && doc !== '00000000');
        if (!identificado && total >= TOPE) {
            showToast(`Para boletas de S/ ${TOPE} a más, registra el DNI del cliente (lo exige SUNAT) o emite factura.`, 'error'); return;
        }
    }

    // Si split esta activo: ignorar el input "efectivo recibido" y usar el
    // breakdown. El tipo_pago/monto_recibido que mandamos a cobrar_comanda
    // son los del primer metodo (fallback) -- el real queda en payment_breakdown.
    const splitData = splitPaymentEnabled ? getSplitPaymentPayload(round2(total)) : null;
    if (splitPaymentEnabled) {
        if (!splitData.rows.length) {
            showToast('Agrega al menos un método de pago', 'error'); return;
        }
        if (Math.abs(splitData.remainder) > 0.01) {
            showToast('La suma de los métodos de pago debe coincidir con el total de la venta', 'error'); return;
        }
    } else if (metodo === 'efectivo' && recibido < total) {
        const ok = await appConfirm(
            `El monto recibido (${soles(recibido)}) es menor al total (${soles(total)}). ¿Confirmar igual?`,
            { titulo: 'Monto insuficiente', textoOk: 'Confirmar', textoNo: 'Cancelar', tipo: 'warning' }
        );
        if (!ok) return;
    }

    const btn = document.getElementById('btn-confirmar-venta');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cobrando...';

    const payloadTipoPago     = splitPaymentEnabled ? splitData.rows[0].method : metodo;
    const payloadMontoRecibido = splitPaymentEnabled
        ? splitData.cashTotal
        : (metodo === 'efectivo' ? recibido : total);

    let d;
    try {
        d = await postJson(`${API}?action=cobrar_comanda`, {
            comanda_id: COMANDA_ID,
            tipo_comprobante: tipoComp,
            tipo_impresion: tipoImpr,
            serie: serie || null,
            tipo_pago: payloadTipoPago,
            cuenta_banco: cuentaBanco,
            monto_recibido: payloadMontoRecibido,
            payment_breakdown: splitPaymentEnabled ? splitData.rows : [],
            observaciones: obs,
            imprimir_observacion: imprimirObs,
            cliente_id: (CLIENTE_SELECT && CLIENTE_SELECT.id) ? CLIENTE_SELECT.id : null,
        });
    } catch (error) {
        showToast(`No se pudo completar el cobro: ${error.message}`, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Pagar y emitir comprobante';
        return;
    }

    if (d.error) {
        showToast(d.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Pagar y emitir comprobante';
        if (d.caja_cerrada) setTimeout(() => { window.location = '../caja/index.php'; }, 2000);
        return;
    }

    const idRef = d.numero_completo || d.numero_venta;
    const sunatSuffix = d.sunat_estado ? ` · SUNAT ${d.sunat_ok ? 'Aceptado' : d.sunat_estado}` : '';
    const vueltoSuffix = (d.vuelto > 0) ? ` · Vuelto ${soles(d.vuelto)}` : '';
    const msg = `${(window.LABELS?.mesa?.s || 'Mesa')} #${d.mesa_numero} cobrada · ${idRef}${sunatSuffix}${vueltoSuffix}`;
    sessionStorage.setItem('cobro_ok', msg);
    window.location = 'index.php';
}

// ---------------------------------------------------------------
// Barcode HID (evento global disparado por assets/js/barcode-scanner.js)
// ---------------------------------------------------------------
document.addEventListener('barcodescan', (e) => {
    const code = (e.detail && e.detail.code || '').trim();
    if (!code) return;
    const prod = PRODUCTOS.find(p =>
        (p.codigo_barras || '').trim() === code ||
        (p.codigo || '').trim() === code ||
        (p.codigo_interno || '').trim() === code
    );
    if (!prod) { showToast(`Codigo no encontrado: ${code}`, 'error'); return; }
    if ((prod.product_type || 'product') !== 'service' && parseFloat(prod.stock) <= 0) {
        showToast(`Sin stock de ${prod.nombre}`, 'error'); return;
    }
    agregarProducto(prod.id);
});

// Buscador: filtro al tipear
document.getElementById('buscador').addEventListener('input', (e) => {
    TEXT_FILTRO = e.target.value;
    renderProductos();
});

// ---------------------------------------------------------------
// Arranque
// ---------------------------------------------------------------
cargarComanda();
cargarProductos();
setInterval(tickCrono, 1000);

// Refresh del header de comanda cada minuto (si otro mozo agrega
// items desde otro navegador, se refleja aca). No es realtime pero
// sirve como safety net sin inflar el server.
setInterval(cargarComanda, 60000);
</script>

<!-- jQuery + Select2 para el cliente dentro del modal #modal-cobro -->
<script src="<?= $base_path ?>assets/vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="<?= $base_path ?>assets/vendor/select2/select2.min.js"></script>
<script>
    // Inicializar Select2 del cliente una vez que jQuery/Select2 estan listos.
    initClienteSelect2();
</script>

<?php include '../../includes/footer.php'; ?>
