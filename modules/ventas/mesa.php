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
$page_title     = 'Mesa — Gravity';
$breadcrumb     = '<a href="index.php" style="color:var(--text-muted);text-decoration:none">Mesas</a> / <strong>Mesa</strong>';
$required_roles = ['admin', 'gerente', 'cajero'];

include '../../includes/header.php';
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:14px">
        <a href="index.php" class="mesa-back-btn" title="Volver a Mesas">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <div class="page-title" id="mesa-title">
                <i class="fas fa-chair" style="color:var(--primary);margin-right:8px"></i>
                Mesa <span id="mesa-num-txt">—</span>
                <small id="mesa-nom-txt" style="color:var(--text-muted);font-weight:500;font-size:.75em"></small>
            </div>
            <div class="page-subtitle">
                Abierta hace <strong id="mesa-crono" data-crono="">00:00:00</strong>
                · <span id="mesa-mozo-txt"></span>
            </div>
        </div>
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

<!-- ======================================================
     MODAL: Informacion de la venta (replica del modal del POS directo)
     Diseno identico al #modal-cobro de pos.php para que el flujo visual
     sea el mismo en POS y en Mesas. Dividir cuenta y Credito desde mesa
     quedan ocultos por ahora (ver docs/implementaciones_futuras.md).
     ====================================================== -->
<div class="modal-overlay" id="modal-cobrar">
    <div class="modal" style="max-width:500px">
        <div class="modal-header" style="padding:14px 18px">
            <h3 class="modal-title" style="font-size:.88rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase">
                Información de la Venta
                <span id="cobro-mesa-num" style="color:var(--primary);font-weight:600;margin-left:6px"></span>
            </h3>
            <button class="modal-close" onclick="closeModal('modal-cobrar')" style="display:flex;align-items:center;gap:5px;font-size:.8rem;background:none;border:none;color:var(--text-muted);cursor:pointer">
                Cerrar <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" style="padding:16px 18px">

            <!-- Fila 1: Tipo documento | Tipo impresion | Serie -->
            <div style="display:grid;grid-template-columns:2fr 1.2fr 1fr;gap:10px;margin-bottom:12px">
                <div>
                    <label class="form-label" style="font-size:.71rem">Tipo de documento</label>
                    <select class="form-control" id="cm-tipo-comp" style="font-size:.82rem" onchange="cmOnComprobanteChange()">
                        <option value="ticket">Ticket</option>
                        <option value="boleta">Boleta Electrónica</option>
                        <option value="factura">Factura Electrónica</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:.71rem">Tipo impresión</label>
                    <select class="form-control" id="cm-tipo-impresion" style="font-size:.82rem">
                        <option value="ticket">Ticket</option>
                        <option value="a4">A4</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:.71rem">Serie</label>
                    <select class="form-control" id="cm-serie" style="font-size:.82rem">
                        <option value="">—</option>
                    </select>
                </div>
            </div>

            <!-- Cliente -->
            <div style="margin-bottom:14px" id="cm-row-cliente">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                    <label class="form-label" style="font-size:.71rem;margin:0">
                        Cliente <small id="cm-cliente-req-hint" style="color:var(--text-muted);font-weight:400"></small>
                    </label>
                    <button type="button" class="btn btn-outline btn-sm" style="white-space:nowrap;font-size:.72rem" onclick="cmUsarClientesVarios()" title="Para boleta rápida sin DNI">
                        <i class="fas fa-user"></i> Varios
                    </button>
                </div>
                <div class="cliente-search-wrap">
                    <input type="text" id="cm-cliente-input" class="form-control" placeholder="Buscar por DNI, RUC o nombre..." autocomplete="off" oninput="cmBuscarClienteDebounced()" style="font-size:.82rem">
                </div>
                <div id="cm-cliente-sugerencias" class="cliente-sugerencias"></div>
                <div id="cm-cliente-seleccionado" class="cliente-pill" style="display:none;margin-top:6px">
                    <span id="cm-cliente-sel-nombre"></span>
                    <button type="button" onclick="cmLimpiarCliente()" aria-label="Quitar"><i class="fas fa-times"></i></button>
                </div>
                <small style="color:var(--text-muted);font-size:.68rem">Busca por nombre, DNI o RUC.</small>
            </div>

            <!-- Dividir cuenta (OCULTO en mesa por ahora: ver docs/implementaciones_futuras.md) -->
            <!-- Se habilitara cuando se implemente cobrar_comanda_split (N comprobantes).
            <div style="text-align:right;margin-bottom:14px">
                <button class="btn btn-primary btn-sm" style="font-size:.77rem">
                    <i class="fas fa-plus"></i> Dividir cuenta
                </button>
            </div>
            -->

            <div style="border-top:1px solid var(--border);margin-bottom:14px"></div>

            <!-- Pago simple: forma + referencia + monto cobrado -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:12px">
                <div>
                    <label class="form-label" style="font-size:.71rem">Forma de pago</label>
                    <select class="form-control" id="cm-tipo-pago" style="font-size:.82rem" onchange="cmOnTipoPagoChange()">
                        <option value="efectivo">Efectivo</option>
                        <option value="yape">Yape</option>
                        <option value="plin">Plin</option>
                        <option value="tarjeta">Visa/Mastercard</option>
                        <option value="transferencia">Transferencia</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:.71rem">Referencia</label>
                    <select class="form-control" id="cm-cuenta-banco" style="font-size:.82rem">
                        <option value="caja_chica">CAJA CHICA</option>
                        <option value="banco">BANCO</option>
                        <option value="yape_cuenta">YAPE</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:.71rem">Monto cobrado</label>
                    <div class="input-group">
                        <span class="input-group-icon" style="font-size:.78rem;font-weight:700;color:var(--text-muted)">S/</span>
                        <input type="text" id="cm-monto-cobrado" class="form-control" readonly
                            style="font-size:.82rem;padding-left:28px;background:var(--surface-2);color:var(--text-muted);cursor:default">
                    </div>
                </div>
            </div>

            <!-- Efectivo recibido (solo si pago en efectivo) -->
            <div id="cm-row-efectivo" style="margin-bottom:12px">
                <label class="form-label" style="font-size:.71rem">Efectivo recibido</label>
                <div class="input-group">
                    <span class="input-group-icon" style="font-size:.85rem;font-weight:700;color:var(--primary)">S/</span>
                    <input type="number" id="cm-monto-recibido" class="form-control" placeholder="0.00"
                        step="0.10" min="0" oninput="cmCalcVuelto()"
                        style="font-size:1rem;padding-left:30px;font-weight:600;border-color:var(--primary)"
                        autocomplete="off">
                </div>
            </div>

            <!-- Observacion -->
            <div style="margin-bottom:14px">
                <label class="form-label" style="font-size:.71rem">Observación</label>
                <input type="text" id="cm-observacion" class="form-control" placeholder="glosa" style="font-size:.82rem">
            </div>

            <!-- Toggle imprimir obs -->
            <div style="margin-bottom:10px">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:.81rem;color:var(--text)">
                    <span id="cm-track-obs" onclick="cmToggleSwitch('cm-check-obs','cm-track-obs', event)"
                        style="width:38px;height:21px;background:var(--primary);border-radius:11px;position:relative;flex-shrink:0;cursor:pointer;transition:background .2s">
                        <span style="position:absolute;top:3px;left:3px;width:15px;height:15px;background:#fff;border-radius:50%;transition:transform .2s;transform:translateX(17px)"></span>
                    </span>
                    <input type="checkbox" id="cm-check-obs" checked style="display:none">
                    Imprimir la observación en el comprobante
                </label>
            </div>

            <!-- Toggle credito (OCULTO en mesa por ahora: ver docs/implementaciones_futuras.md) -->
            <!--
            <div style="margin-bottom:16px">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:.81rem;color:var(--text)">
                    <span style="width:38px;height:21px;background:#d1d5db;border-radius:11px"></span>
                    ¿La venta es a crédito?
                </label>
            </div>
            -->

            <!-- Totales: Total venta | Total pagar | Vuelto -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;border-top:2px solid var(--border);padding-top:14px;text-align:center">
                <div>
                    <div style="font-size:.71rem;color:var(--text-muted);margin-bottom:4px">Total de venta</div>
                    <div style="font-size:1.15rem;font-weight:700;color:var(--primary)" id="cm-total-venta">S/ 0.00</div>
                </div>
                <div style="border-left:1px solid var(--border);border-right:1px solid var(--border)">
                    <div style="font-size:.71rem;color:var(--text-muted);margin-bottom:4px">Total a pagar</div>
                    <div style="font-size:1.15rem;font-weight:700;color:var(--primary)" id="cm-total-pagar">S/ 0.00</div>
                </div>
                <div>
                    <div style="font-size:.71rem;color:var(--text-muted);margin-bottom:4px">Vuelto</div>
                    <div style="font-size:1.15rem;font-weight:700;color:var(--success)" id="cm-vuelto">S/ 0.00</div>
                </div>
            </div>
        </div>

        <div class="modal-footer" style="justify-content:flex-end;padding:12px 18px;gap:8px">
            <button class="btn btn-primary" id="btn-confirmar-cobro" onclick="confirmarCobro()" style="font-size:.85rem">
                <i class="fas fa-check"></i> Pagar y emitir comprobante
            </button>
            <button class="btn btn-outline" onclick="closeModal('modal-cobrar')" style="font-size:.85rem">Cancelar</button>
        </div>
    </div>
</div>

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
    document.getElementById('mesa-num-txt').textContent = '#' + numero;
    document.getElementById('mesa-nom-txt').textContent = nombre ? ' — ' + nombre : '';

    if (!COMANDA) {
        document.getElementById('mesa-mozo-txt').textContent = 'Mesa libre — agrega el primer producto para abrirla';
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

// ---------------------------------------------------------------
// Cobro (Fase E/E.1)
// ---------------------------------------------------------------
let TIPO_COMP_SELECT = 'ticket';
let CLIENTE_SELECT = null;      // {id, nombre, documento, tipo_doc}
let _clienteBuscarTimer = null;

// ================================================================
// Cobro de mesa — modal "Informacion de la Venta" (replica del POS).
// Toda la logica usa prefijo "cm" (cobro-mesa) para no colisionar con
// nada global. El endpoint sigue siendo cobrar_comanda, extendido para
// aceptar los campos nuevos (serie, cuenta_banco, imprimir_obs) de forma
// opcional (compatibilidad hacia atras).
// ================================================================

let _cmSeriesCache = null; // {boleta: ['B001'], factura: ['F001'], ticket: ['TK01']}

function cobrar() {
    if (!ITEMS.length) {
        showToast('No hay items para cobrar', 'error');
        return;
    }
    const total = ITEMS.reduce((s, it) => s + parseFloat(it.subtotal || 0), 0);

    document.getElementById('cobro-mesa-num').textContent = '#' + (COMANDA?.mesa_numero || '');
    document.getElementById('cm-tipo-comp').value       = 'ticket';
    document.getElementById('cm-tipo-impresion').value  = 'ticket';
    document.getElementById('cm-tipo-pago').value       = 'efectivo';
    document.getElementById('cm-cuenta-banco').value    = 'caja_chica';
    document.getElementById('cm-monto-cobrado').value   = total.toFixed(2);
    document.getElementById('cm-monto-recibido').value  = '';
    document.getElementById('cm-observacion').value     = '';
    document.getElementById('cm-total-venta').textContent = soles(total);
    document.getElementById('cm-total-pagar').textContent = soles(total);
    document.getElementById('cm-vuelto').textContent      = soles(0);

    TIPO_COMP_SELECT = 'ticket';
    CLIENTE_SELECT   = null;
    cmLimpiarCliente();
    cmOnComprobanteChange();
    cmOnTipoPagoChange();
    cmCargarSeries();

    const btn = document.getElementById('btn-confirmar-cobro');
    btn.disabled  = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Pagar y emitir comprobante';

    openModal('modal-cobrar');
    setTimeout(() => document.getElementById('cm-monto-recibido').focus(), 80);
}

// ---------------- Series disponibles por tipo ----------------
async function cmCargarSeries() {
    if (_cmSeriesCache) { cmPintarSerie(); return; }
    try {
        const r = await fetch(`${API}?action=series_disponibles`);
        const d = await r.json();
        _cmSeriesCache = (d && !d.error) ? d : {};
    } catch (e) { _cmSeriesCache = {}; }
    cmPintarSerie();
}

function cmPintarSerie() {
    const tipo = document.getElementById('cm-tipo-comp').value;
    const sel  = document.getElementById('cm-serie');
    const list = (_cmSeriesCache && _cmSeriesCache[tipo]) || [];
    sel.innerHTML = list.length
        ? list.map(s => `<option value="${s}">${s}</option>`).join('')
        : '<option value="">—</option>';
}

function cmOnComprobanteChange() {
    TIPO_COMP_SELECT = document.getElementById('cm-tipo-comp').value;
    cmPintarSerie();

    // Mostrar / ocultar fila de cliente segun el tipo.
    const row  = document.getElementById('cm-row-cliente');
    const hint = document.getElementById('cm-cliente-req-hint');
    if (TIPO_COMP_SELECT === 'ticket') {
        row.style.display = 'none';
    } else {
        row.style.display = '';
        hint.textContent = TIPO_COMP_SELECT === 'factura'
            ? '— requerido (debe tener RUC)'
            : '— opcional (si no eliges, va a Clientes Varios)';
    }
}

function cmOnTipoPagoChange() {
    const metodo = document.getElementById('cm-tipo-pago').value;
    document.getElementById('cm-row-efectivo').style.display = metodo === 'efectivo' ? '' : 'none';
    cmCalcVuelto();
}

function cmCalcVuelto() {
    const total  = ITEMS.reduce((s, it) => s + parseFloat(it.subtotal || 0), 0);
    const metodo = document.getElementById('cm-tipo-pago').value;
    const recibido = parseFloat(document.getElementById('cm-monto-recibido').value || 0);
    const vueltoEl = document.getElementById('cm-vuelto');
    if (metodo !== 'efectivo') { vueltoEl.textContent = soles(0); return; }
    const vuelto = recibido - total;
    vueltoEl.textContent = soles(Math.max(0, vuelto));
    vueltoEl.style.color = vuelto < 0 ? 'var(--danger)' : 'var(--success)';
}

function cmToggleSwitch(checkboxId, trackId, ev) {
    if (ev) ev.preventDefault();
    const chk   = document.getElementById(checkboxId);
    const track = document.getElementById(trackId);
    chk.checked = !chk.checked;
    track.style.background = chk.checked ? 'var(--primary)' : '#d1d5db';
    track.firstElementChild.style.transform = chk.checked ? 'translateX(17px)' : 'translateX(0)';
}

// ---------------- Cliente search ----------------
function cmBuscarClienteDebounced() {
    clearTimeout(_clienteBuscarTimer);
    const q = document.getElementById('cm-cliente-input').value.trim();
    const box = document.getElementById('cm-cliente-sugerencias');
    if (q.length < 2) { box.innerHTML = ''; return; }
    _clienteBuscarTimer = setTimeout(() => cmBuscarCliente(q), 250);
}

async function cmBuscarCliente(q) {
    try {
        const r = await fetch(`${API}?action=buscar_cliente&q=${encodeURIComponent(q)}`);
        const d = await r.json();
        const box = document.getElementById('cm-cliente-sugerencias');
        if (!d || d.error) { box.innerHTML = ''; return; }
        const row = Array.isArray(d) ? d[0] : d;
        if (!row) {
            box.innerHTML = `<div class="sugerencia" style="color:var(--text-muted);cursor:default">Sin resultados para "${escH(q)}"</div>`;
            return;
        }
        const nombre = row.razon_social || row.nombre_completo || `${row.nombres || ''} ${row.apellidos || ''}`.trim();
        const doc    = row.numero_documento || row.ruc || row.dni || '';
        box.innerHTML = `<div class="sugerencia" onclick='cmSeleccionarCliente(${JSON.stringify({id: row.id, nombre, doc, ruc: row.ruc}).replace(/'/g, "&#39;")})'>
            <strong>${escH(nombre)}</strong><br>
            <span class="sug-doc">${escH(doc)}</span>
        </div>`;
    } catch (err) {
        showToast('Error buscando cliente', 'error');
    }
}

function cmSeleccionarCliente(c) {
    CLIENTE_SELECT = c;
    document.getElementById('cm-cliente-sel-nombre').textContent = `${c.nombre} · ${c.doc}`;
    document.getElementById('cm-cliente-seleccionado').style.display = '';
    document.getElementById('cm-cliente-input').value = '';
    document.getElementById('cm-cliente-sugerencias').innerHTML = '';
}

function cmLimpiarCliente() {
    CLIENTE_SELECT = null;
    const sel = document.getElementById('cm-cliente-seleccionado');
    const nom = document.getElementById('cm-cliente-sel-nombre');
    const sug = document.getElementById('cm-cliente-sugerencias');
    const inp = document.getElementById('cm-cliente-input');
    if (sel) sel.style.display = 'none';
    if (nom) nom.textContent = '';
    if (sug) sug.innerHTML = '';
    if (inp) inp.value = '';
}

function cmUsarClientesVarios() {
    cmSeleccionarCliente({ id: null, nombre: 'Clientes Varios', doc: '00000000' });
}

async function confirmarCobro() {
    const tipoComp   = document.getElementById('cm-tipo-comp').value;
    const tipoImpr   = document.getElementById('cm-tipo-impresion').value;
    const serie      = document.getElementById('cm-serie').value;
    const metodo     = document.getElementById('cm-tipo-pago').value;
    const cuentaBanco= document.getElementById('cm-cuenta-banco').value;
    const recibido   = parseFloat(document.getElementById('cm-monto-recibido').value || 0);
    const obs        = document.getElementById('cm-observacion').value.trim();
    const imprimirObs= document.getElementById('cm-check-obs').checked;
    const total      = ITEMS.reduce((s, it) => s + parseFloat(it.subtotal || 0), 0);

    // Validaciones de cliente por tipo
    if (tipoComp === 'factura') {
        if (!CLIENTE_SELECT || !CLIENTE_SELECT.id) {
            showToast('Para factura necesitas seleccionar un cliente con RUC', 'error');
            return;
        }
        if (!CLIENTE_SELECT.ruc) {
            showToast('El cliente seleccionado no tiene RUC. Edita el cliente o elige otro.', 'error');
            return;
        }
    }

    if (metodo === 'efectivo' && recibido < total) {
        const ok = await appConfirm(
            `El monto recibido (${soles(recibido)}) es menor al total (${soles(total)}). ¿Confirmar igual?`,
            { titulo: 'Monto insuficiente', textoOk: 'Confirmar', textoNo: 'Cancelar', tipo: 'warning' }
        );
        if (!ok) return;
    }

    const btn = document.getElementById('btn-confirmar-cobro');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cobrando...';

    let d;
    try {
        d = await postJson(`${API}?action=cobrar_comanda`, {
            comanda_id: COMANDA_ID,
            tipo_comprobante: tipoComp,
            tipo_impresion: tipoImpr,
            serie: serie || null,
            tipo_pago: metodo,
            cuenta_banco: cuentaBanco,
            monto_recibido: metodo === 'efectivo' ? recibido : total,
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
        if (d.caja_cerrada) {
            setTimeout(() => { window.location = '../caja/index.php'; }, 2000);
        }
        return;
    }

    const idRef = d.numero_completo || d.numero_venta;
    const sunatSuffix = d.sunat_estado
        ? ` · SUNAT ${d.sunat_ok ? 'Aceptado' : d.sunat_estado}`
        : '';
    const vueltoSuffix = (d.vuelto > 0) ? ` · Vuelto ${soles(d.vuelto)}` : '';
    const msg = `Mesa #${d.mesa_numero} cobrada · ${idRef}${sunatSuffix}${vueltoSuffix}`;
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

<?php include '../../includes/footer.php'; ?>
