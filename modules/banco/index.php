<?php
require_once '../../config/database.php';

$base_path      = '../../';
$current_module = 'banco';
$current_page   = 'banco';
$page_title     = 'Banco — Gravity';
$breadcrumb     = '<strong>Banco</strong>';
$required_roles = ['admin', 'gerente'];

include '../../includes/header.php';
?>

<style>
.banco-layout { display: flex; gap: 0; height: calc(100vh - var(--topbar-h) - 48px); }

/* ── Vista: lista de cuentas ─────────────────── */
#view-lista { width: 100%; }
.banco-accounts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    margin-top: 20px;
}
.banco-account-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    cursor: pointer;
    transition: border-color .15s, box-shadow .15s, transform .15s;
    position: relative;
    overflow: hidden;
}
.banco-account-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: var(--primary);
    border-radius: var(--radius) var(--radius) 0 0;
}
.banco-account-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 20px rgba(0,0,0,.08);
    transform: translateY(-2px);
}
.banco-card-icon {
    width: 42px; height: 42px;
    background: var(--primary-light, #eef2ff);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: var(--primary);
    font-size: 1.1rem;
    margin-bottom: 14px;
}
.banco-card-nombre { font-size: .95rem; font-weight: 700; color: var(--text-primary); margin-bottom: 3px; }
.banco-card-banco  { font-size: .78rem; color: var(--text-muted); margin-bottom: 12px; }
.banco-card-meta   { display: flex; justify-content: space-between; align-items: flex-end; }
.banco-card-numero { font-size: .75rem; color: var(--text-muted); }
.banco-card-saldo  { font-size: 1.15rem; font-weight: 800; color: var(--primary); }
.banco-tipo-badge  {
    display: inline-block; padding: 2px 10px;
    border-radius: 20px; font-size: .7rem; font-weight: 700;
    background: var(--surface-2); color: var(--text-muted);
    margin-bottom: 10px;
}

/* ── Vista: detalle de cuenta ────────────────── */
#view-detalle { display: none; width: 100%; }
.detalle-layout {
    display: grid;
    grid-template-columns: 25% 1fr;
    gap: 16px;
    height: 100%;
}
.detalle-left  { display: flex; flex-direction: column; gap: 14px; overflow-y: auto; }
.detalle-right { display: flex; flex-direction: column; overflow: hidden; }

.balance-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 18px;
}
.balance-card-title {
    font-size: .7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .06em; color: var(--text-muted); margin-bottom: 14px;
    display: flex; align-items: center; gap: 7px;
}
.balance-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 7px 0; border-bottom: 1px solid var(--border);
}
.balance-row:last-child { border-bottom: none; }
.balance-row .label { font-size: .8rem; color: var(--text-muted); }
.balance-row .value { font-size: .92rem; font-weight: 700; }
.value.ingreso { color: #16a34a; }
.value.egreso  { color: #dc2626; }
.value.saldo   { color: var(--primary); font-size: 1rem; }

.chart-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 18px;
    flex: 1;
}
.chart-card-title {
    font-size: .7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .06em; color: var(--text-muted); margin-bottom: 14px;
    display: flex; align-items: center; gap: 7px;
}

.mov-tipo-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px;
    font-size: .75rem; font-weight: 600;
}
.mov-tipo-badge.ingreso { background: #f0fdf4; color: #16a34a; }
.mov-tipo-badge.egreso  { background: #fef2f2; color: #dc2626; }

.monto-ingreso { color: #16a34a; font-weight: 700; }
.monto-egreso  { color: #dc2626; font-weight: 700; }

/* ── Imagen cuenta ───────────────────────────────── */
.banco-card-img {
    width: 48px; height: 48px;
    object-fit: contain;
    border-radius: 8px;
    margin-bottom: 14px;
    border: 1.5px solid var(--border);
    padding: 4px;
    background: #fff;
}

/* ── Upload zone ─────────────────────────────────── */
.upload-zone {
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 18px 12px;
    text-align: center;
    cursor: pointer;
    transition: border-color .15s, background .15s;
    background: var(--surface-2, #f8fafc);
    position: relative;
}
.upload-zone:hover { border-color: var(--primary); background: var(--primary-light, #eef2ff); }
.upload-zone.has-image { border-style: solid; border-color: var(--primary); }
.upload-zone i { font-size: 1.5rem; color: var(--text-muted); display: block; margin-bottom: 6px; }
.upload-zone span { font-size: .82rem; color: var(--text-muted); display: block; }
.upload-zone small { font-size: .73rem; color: var(--text-light, #94a3b8); margin-top: 4px; display: block; }
#nc-preview { display: none; max-height: 80px; max-width: 100%; border-radius: 6px; margin: 0 auto; }

/* ── Imagen en detalle ───────────────────────────── */
.detalle-banco-img {
    width: 44px; height: 44px;
    object-fit: contain;
    border-radius: 8px;
    border: 1.5px solid var(--border);
    padding: 3px;
    background: #fff;
    flex-shrink: 0;
}
</style>

<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-university" style="color:var(--primary);margin-right:8px"></i>Banco</div>
        <div class="page-subtitle" id="banco-subtitle">Gestión de cuentas bancarias</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <button class="btn btn-outline btn-sm" id="btn-volver" style="display:none" onclick="volverLista()">
            <i class="fas fa-arrow-left"></i> Volver
        </button>
        <button class="btn btn-primary" id="btn-nueva-cuenta" onclick="openModal('modal-cuenta')">
            <i class="fas fa-plus"></i> Nueva Cuenta
        </button>
        <button class="btn btn-outline btn-sm" id="btn-ajustes" style="display:none" onclick="abrirAjustes()">
            <i class="fas fa-cog"></i> Ajustes
        </button>
        <button class="btn btn-primary btn-sm" id="btn-nuevo-mov" style="display:none" onclick="openModal('modal-movimiento')">
            <i class="fas fa-plus"></i> Nuevo movimiento
        </button>
    </div>
</div>

<!-- ── VISTA LISTA ──────────────────────────────────────────── -->
<div id="view-lista">
    <div class="card" style="margin-bottom:16px">
        <div class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-icon"><i class="fas fa-search"></i></span>
                    <input type="text" id="lista-search" class="form-control" placeholder="Buscar cuenta..." oninput="filtrarCuentas(this.value)">
                </div>
            </div>
        </div>
    </div>

    <div class="banco-accounts-grid" id="accounts-grid">
        <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted)">
            <i class="fas fa-spinner fa-spin" style="font-size:1.5rem"></i>
        </div>
    </div>
</div>

<!-- ── VISTA DETALLE ────────────────────────────────────────── -->
<div id="view-detalle">
    <div class="detalle-layout">

        <!-- Columna izquierda 25% -->
        <div class="detalle-left">

            <!-- Card 1: Balance -->
            <div class="balance-card">
                <div id="detalle-banco-img-wrap" style="margin-bottom:10px"></div>
                <div class="balance-card-title">
                    <i class="fas fa-wallet"></i> Balance de cuenta
                    <span style="margin-left:auto;font-size:.68rem;font-weight:500;color:var(--text-light)" id="balance-mes"></span>
                </div>
                <div class="balance-row">
                    <span class="label"><i class="fas fa-arrow-down" style="color:#16a34a;margin-right:5px"></i>Ingresos</span>
                    <span class="value ingreso" id="bal-ingresos">S/ 0.00</span>
                </div>
                <div class="balance-row">
                    <span class="label"><i class="fas fa-arrow-up" style="color:#dc2626;margin-right:5px"></i>Egresos</span>
                    <span class="value egreso" id="bal-egresos">S/ 0.00</span>
                </div>
                <div class="balance-row">
                    <span class="label"><i class="fas fa-university" style="color:var(--primary);margin-right:5px"></i>Saldo total</span>
                    <span class="value saldo" id="bal-saldo">S/ 0.00</span>
                </div>
            </div>

            <!-- Card 2: Gráfico -->
            <div class="chart-card">
                <div class="chart-card-title">
                    <i class="fas fa-chart-bar"></i> Ventas realizadas
                </div>
                <canvas id="grafico-movimientos" style="width:100%;max-height:200px"></canvas>
            </div>

        </div>

        <!-- Columna derecha 75% -->
        <div class="detalle-right">
            <div class="card" style="margin-bottom:12px;flex-shrink:0">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Desde</label>
                        <input type="date" id="mov-desde" class="form-control" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hasta</label>
                        <input type="date" id="mov-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select id="mov-tipo-filter" class="form-control">
                            <option value="">Todos</option>
                            <option value="ingreso">Ingresos</option>
                            <option value="egreso">Egresos</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100" onclick="loadMovimientos()">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </div>
                </div>
            </div>

            <div class="card" style="flex:1;overflow:hidden;display:flex;flex-direction:column">
                <div class="card-header">
                    <div class="card-title">Movimientos</div>
                    <span id="mov-count" style="font-size:.8rem;color:var(--text-muted)"></span>
                </div>
                <div class="table-wrap table-responsive" style="flex:1;overflow-y:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente / Beneficiario</th>
                                <th>Nro. Comprobante</th>
                                <th>Tipo</th>
                                <th>Concepto</th>
                                <th class="text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody id="mov-tbody">
                            <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">
                                <i class="fas fa-spinner fa-spin"></i>
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ── MODAL: Nueva cuenta ──────────────────────────────────── -->
<div class="modal-overlay" id="modal-cuenta">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-university" style="color:var(--primary);margin-right:8px"></i>Nueva Cuenta</h3>
            <button class="modal-close" onclick="closeModal('modal-cuenta')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding:20px 24px">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Nombre de la cuenta <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="nc-nombre" class="form-control" placeholder="Ej: Cuenta BCP Principal" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Banco</label>
                    <input type="text" id="nc-banco" class="form-control" placeholder="Ej: BCP, BBVA, Interbank" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tipo de cuenta</label>
                    <select id="nc-tipo" class="form-control">
                        <option value="ahorros">Ahorros</option>
                        <option value="corriente">Corriente</option>
                        <option value="tarjeta">Tarjeta de crédito</option>
                        <option value="yape">Yape / Billetera</option>
                        <option value="efectivo">Efectivo / Caja chica</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Número de cuenta</label>
                    <input type="text" id="nc-numero" class="form-control" placeholder="Ej: 191-123456789-0-01" autocomplete="off">
                </div>
                <div class="col-12">
                    <label class="form-label">Saldo inicial</label>
                    <div class="input-group">
                        <span class="input-group-icon" style="font-weight:700;font-size:.8rem">S/</span>
                        <input type="number" id="nc-saldo" class="form-control" placeholder="0.00" step="0.01" min="0">
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Imagen / Logo del banco</label>
                    <div class="upload-zone" id="nc-upload-zone" onclick="document.getElementById('nc-imagen').click()">
                        <div id="nc-upload-placeholder">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Haz clic para subir una imagen</span>
                            <small>PNG, JPG, WebP &middot; Máx. 2 MB</small>
                        </div>
                        <img id="nc-preview" alt="preview">
                    </div>
                    <input type="file" id="nc-imagen" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none" onchange="previewImagen(this)">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="cancelarModalCuenta()">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarCuenta()"><i class="fas fa-save"></i> Guardar</button>
        </div>
    </div>
</div>

<!-- ── MODAL: Nuevo movimiento ──────────────────────────────── -->
<div class="modal-overlay" id="modal-movimiento">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-exchange-alt" style="color:var(--primary);margin-right:8px"></i>Nuevo Movimiento</h3>
            <button class="modal-close" onclick="closeModal('modal-movimiento')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding:20px 24px">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Tipo <span style="color:var(--danger)">*</span></label>
                    <select id="nm-tipo" class="form-control">
                        <option value="ingreso">Ingreso</option>
                        <option value="egreso">Egreso</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fecha <span style="color:var(--danger)">*</span></label>
                    <input type="date" id="nm-fecha" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Cliente / Beneficiario</label>
                    <input type="text" id="nm-beneficiario" class="form-control" placeholder="Nombre del cliente o proveedor" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nro. Comprobante</label>
                    <input type="text" id="nm-comprobante" class="form-control" placeholder="Ej: B001-00001" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Monto <span style="color:var(--danger)">*</span></label>
                    <div class="input-group">
                        <span class="input-group-icon" style="font-weight:700;font-size:.8rem">S/</span>
                        <input type="number" id="nm-monto" class="form-control" placeholder="0.00" step="0.01" min="0.01">
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Concepto</label>
                    <input type="text" id="nm-concepto" class="form-control" placeholder="Descripción del movimiento" autocomplete="off">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-movimiento')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarMovimiento()"><i class="fas fa-save"></i> Guardar</button>
        </div>
    </div>
</div>

<!-- ── MODAL: Ajustes de cuenta ──────────────────────────────── -->
<div class="modal-overlay" id="modal-ajustes">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-cog" style="color:var(--primary);margin-right:8px"></i>Ajustes de cuenta</h3>
            <button class="modal-close" onclick="closeModal('modal-ajustes')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding:20px 24px">
            <input type="hidden" id="aj-id">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Nombre de la cuenta <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="aj-nombre" class="form-control" placeholder="Ej: Cuenta BCP Principal" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Banco</label>
                    <input type="text" id="aj-banco" class="form-control" placeholder="Ej: BCP, BBVA, Interbank" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tipo de cuenta</label>
                    <select id="aj-tipo" class="form-control">
                        <option value="ahorros">Ahorros</option>
                        <option value="corriente">Corriente</option>
                        <option value="tarjeta">Tarjeta de crédito</option>
                        <option value="yape">Yape / Billetera</option>
                        <option value="efectivo">Efectivo / Caja chica</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Número de cuenta</label>
                    <input type="text" id="aj-numero" class="form-control" placeholder="Ej: 191-123456789-0-01" autocomplete="off">
                </div>
                <div class="col-12">
                    <label class="form-label">Imagen / Logo del banco</label>
                    <div class="upload-zone" id="aj-upload-zone" onclick="document.getElementById('aj-imagen').click()">
                        <div id="aj-upload-placeholder">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Haz clic para cambiar la imagen</span>
                            <small>PNG, JPG, WebP &middot; Máx. 2 MB</small>
                        </div>
                        <img id="aj-preview" alt="preview" style="display:none;max-height:80px;max-width:100%;border-radius:6px;margin:0 auto">
                    </div>
                    <input type="file" id="aj-imagen" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none" onchange="previewImagenAj(this)">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-ajustes')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarAjustes()"><i class="fas fa-save"></i> Guardar cambios</button>
        </div>
    </div>
</div>

<div class="app-toast-container" id="toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const API = '../../modules/banco/api.php';
let allCuentas   = [];
let cuentaActiva = null;
let grafico      = null;

// ── Init ───────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadCuentas();
});

// ── Cuentas ────────────────────────────────────────────────────
function loadCuentas() {
    fetch(API + '?action=cuentas_listar')
        .then(r => r.json())
        .then(data => {
            allCuentas = data;
            renderCuentas(data);
        })
        .catch(() => showToast('Error al cargar cuentas', 'error'));
}

function renderCuentas(cuentas) {
    const grid = document.getElementById('accounts-grid');
    if (!cuentas.length) {
        grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text-muted)">
            <i class="fas fa-university" style="font-size:2rem;margin-bottom:12px;display:block;opacity:.3"></i>
            No hay cuentas registradas. Crea la primera.
        </div>`;
        return;
    }
    const tipoIcono = { ahorros:'fa-university', corriente:'fa-building-columns', tarjeta:'fa-credit-card', yape:'fa-mobile-alt', efectivo:'fa-money-bill-wave' };
    grid.innerHTML = cuentas.map(c => `
        <div class="banco-account-card" onclick="abrirDetalle(${c.id})">
            ${c.imagen_path
                ? `<img src="../../${escHtml(c.imagen_path)}" class="banco-card-img" alt="${escHtml(c.nombre_cuenta)}">`
                : `<div class="banco-card-icon"><i class="fas ${tipoIcono[c.tipo_cuenta] || 'fa-university'}"></i></div>`
            }
            <div class="banco-card-nombre">${escHtml(c.nombre_cuenta)}</div>
            <div class="banco-card-banco">${escHtml(c.banco || '—')}</div>
            <span class="banco-tipo-badge">${escHtml(c.tipo_cuenta)}</span>
            <div class="banco-card-meta">
                <div class="banco-card-numero">${c.numero_cuenta ? '••• ' + c.numero_cuenta.slice(-4) : '—'}</div>
                <div class="banco-card-saldo">S/ ${parseFloat(c.saldo).toFixed(2)}</div>
            </div>
        </div>
    `).join('');
}

function filtrarCuentas(q) {
    const filtered = q.trim()
        ? allCuentas.filter(c => c.nombre_cuenta.toLowerCase().includes(q.toLowerCase()) || (c.banco || '').toLowerCase().includes(q.toLowerCase()))
        : allCuentas;
    renderCuentas(filtered);
}

// ── Guardar cuenta ─────────────────────────────────────────────
async function guardarCuenta() {
    const nombre = document.getElementById('nc-nombre').value.trim();
    if (!nombre) { showToast('El nombre es obligatorio', 'error'); return; }

    const r = await fetch(API + '?action=cuenta_crear', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            nombre_cuenta:  nombre,
            banco:          document.getElementById('nc-banco').value.trim(),
            tipo_cuenta:    document.getElementById('nc-tipo').value,
            numero_cuenta:  document.getElementById('nc-numero').value.trim(),
            saldo_inicial:  parseFloat(document.getElementById('nc-saldo').value) || 0,
        })
    });
    const d = await r.json();
    if (d.error) { showToast(d.message, 'error'); return; }

    const file = document.getElementById('nc-imagen').files[0];
    if (file && d.id) {
        const fd = new FormData();
        fd.append('imagen', file);
        fd.append('cuenta_id', d.id);
        const r2 = await fetch(API + '?action=cuenta_imagen_subir', { method: 'POST', body: fd });
        const d2 = await r2.json();
        if (d2.error) showToast('Cuenta creada, pero no se pudo subir la imagen: ' + d2.message, 'error');
    }

    cancelarModalCuenta();
    showToast('Cuenta creada', 'success');
    loadCuentas();
}

function cancelarModalCuenta() {
    closeModal('modal-cuenta');
    document.getElementById('nc-nombre').value = '';
    document.getElementById('nc-banco').value  = '';
    document.getElementById('nc-numero').value = '';
    document.getElementById('nc-saldo').value  = '';
    document.getElementById('nc-imagen').value = '';
    document.getElementById('nc-preview').style.display = 'none';
    document.getElementById('nc-upload-placeholder').style.display = '';
    document.getElementById('nc-upload-zone').classList.remove('has-image');
}

function previewImagen(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const preview = document.getElementById('nc-preview');
        const placeholder = document.getElementById('nc-upload-placeholder');
        preview.src = e.target.result;
        preview.style.display = 'block';
        placeholder.style.display = 'none';
        document.getElementById('nc-upload-zone').classList.add('has-image');
    };
    reader.readAsDataURL(file);
}

// ── Abrir detalle ──────────────────────────────────────────────
function abrirDetalle(id) {
    cuentaActiva = id;
    document.getElementById('view-lista').style.display   = 'none';
    document.getElementById('view-detalle').style.display = 'block';
    document.getElementById('btn-volver').style.display   = '';
    document.getElementById('btn-nueva-cuenta').style.display = 'none';
    document.getElementById('btn-ajustes').style.display      = '';
    document.getElementById('btn-nuevo-mov').style.display    = '';

    fetch(API + '?action=cuenta_detalle&id=' + id)
        .then(r => r.json())
        .then(c => {
            document.getElementById('banco-subtitle').textContent = c.nombre_cuenta + (c.banco ? ' · ' + c.banco : '');
            document.getElementById('bal-ingresos').textContent = 'S/ ' + parseFloat(c.total_ingresos).toFixed(2);
            document.getElementById('bal-egresos').textContent  = 'S/ ' + parseFloat(c.total_egresos).toFixed(2);
            document.getElementById('bal-saldo').textContent    = 'S/ ' + parseFloat(c.saldo).toFixed(2);
            const now = new Date();
            document.getElementById('balance-mes').textContent =
                now.toLocaleString('es-PE', { month: 'long', year: 'numeric' });
            // Imagen en la card de balance
            const imgWrap = document.getElementById('detalle-banco-img-wrap');
            if (c.imagen_path) {
                imgWrap.innerHTML = `<img src="../../${escHtml(c.imagen_path)}" class="detalle-banco-img" alt="">`;
            } else {
                imgWrap.innerHTML = '';
            }
        });

    loadMovimientos();
    loadGrafico(id);
}

function volverLista() {
    cuentaActiva = null;
    document.getElementById('view-lista').style.display   = 'block';
    document.getElementById('view-detalle').style.display = 'none';
    document.getElementById('btn-volver').style.display   = 'none';
    document.getElementById('btn-ajustes').style.display  = 'none';
    document.getElementById('btn-nueva-cuenta').style.display = '';
    document.getElementById('btn-nuevo-mov').style.display    = 'none';
    document.getElementById('banco-subtitle').textContent = 'Gestión de cuentas bancarias';
    loadCuentas();
}

// ── Ajustes de cuenta ──────────────────────────────────────────
function abrirAjustes() {
    fetch(API + '?action=cuenta_detalle&id=' + cuentaActiva)
        .then(r => r.json())
        .then(c => {
            document.getElementById('aj-id').value     = c.id;
            document.getElementById('aj-nombre').value = c.nombre_cuenta;
            document.getElementById('aj-banco').value  = c.banco || '';
            document.getElementById('aj-tipo').value   = c.tipo_cuenta;
            document.getElementById('aj-numero').value = c.numero_cuenta || '';
            // Imagen actual
            const preview     = document.getElementById('aj-preview');
            const placeholder = document.getElementById('aj-upload-placeholder');
            const zone        = document.getElementById('aj-upload-zone');
            if (c.imagen_path) {
                preview.src = '../../' + c.imagen_path;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
                zone.classList.add('has-image');
            } else {
                preview.style.display = 'none';
                placeholder.style.display = '';
                zone.classList.remove('has-image');
            }
            document.getElementById('aj-imagen').value = '';
            openModal('modal-ajustes');
        });
}

async function guardarAjustes() {
    const id     = parseInt(document.getElementById('aj-id').value);
    const nombre = document.getElementById('aj-nombre').value.trim();
    if (!nombre) { showToast('El nombre es obligatorio', 'error'); return; }

    const r = await fetch(API + '?action=cuenta_actualizar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id,
            nombre_cuenta: nombre,
            banco:         document.getElementById('aj-banco').value.trim(),
            tipo_cuenta:   document.getElementById('aj-tipo').value,
            numero_cuenta: document.getElementById('aj-numero').value.trim(),
        })
    });
    const d = await r.json();
    if (d.error) { showToast(d.message, 'error'); return; }

    const file = document.getElementById('aj-imagen').files[0];
    if (file) {
        const fd = new FormData();
        fd.append('imagen', file);
        fd.append('cuenta_id', id);
        const r2 = await fetch(API + '?action=cuenta_imagen_subir', { method: 'POST', body: fd });
        const d2 = await r2.json();
        if (d2.error) showToast('Guardado, pero no se pudo subir la imagen: ' + d2.message, 'error');
    }

    closeModal('modal-ajustes');
    showToast('Cuenta actualizada', 'success');
    abrirDetalle(id);
}

function previewImagenAj(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const preview = document.getElementById('aj-preview');
        preview.src = e.target.result;
        preview.style.display = 'block';
        document.getElementById('aj-upload-placeholder').style.display = 'none';
        document.getElementById('aj-upload-zone').classList.add('has-image');
    };
    reader.readAsDataURL(file);
}

// ── Movimientos ────────────────────────────────────────────────
function loadMovimientos() {
    if (!cuentaActiva) return;
    const desde = document.getElementById('mov-desde').value;
    const hasta = document.getElementById('mov-hasta').value;
    const tipo  = document.getElementById('mov-tipo-filter').value;
    const url   = `${API}?action=movimientos_listar&id=${cuentaActiva}&desde=${desde}&hasta=${hasta}&tipo=${tipo}`;

    document.getElementById('mov-tbody').innerHTML =
        '<tr><td colspan="6" style="text-align:center;padding:20px"><i class="fas fa-spinner fa-spin"></i></td></tr>';

    fetch(url)
        .then(r => r.json())
        .then(rows => {
            document.getElementById('mov-count').textContent = rows.length + ' movimiento(s)';
            if (!rows.length) {
                document.getElementById('mov-tbody').innerHTML =
                    '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">Sin movimientos en el período</td></tr>';
                return;
            }
            document.getElementById('mov-tbody').innerHTML = rows.map(m => `
                <tr>
                    <td style="white-space:nowrap">${formatFecha(m.fecha)}</td>
                    <td>${escHtml(m.beneficiario || '—')}</td>
                    <td><span style="font-family:monospace;font-size:.8rem">${escHtml(m.numero_comprobante || '—')}</span></td>
                    <td><span class="mov-tipo-badge ${m.tipo}">
                        <i class="fas ${m.tipo === 'ingreso' ? 'fa-arrow-down' : 'fa-arrow-up'}"></i>
                        ${m.tipo === 'ingreso' ? 'Ingreso' : 'Egreso'}
                    </span></td>
                    <td>${escHtml(m.concepto || '—')}</td>
                    <td class="text-right monto-${m.tipo}">
                        ${m.tipo === 'egreso' ? '-' : '+'}S/ ${parseFloat(m.monto).toFixed(2)}
                    </td>
                </tr>
            `).join('');
        });
}

// ── Guardar movimiento ─────────────────────────────────────────
function guardarMovimiento() {
    const monto = parseFloat(document.getElementById('nm-monto').value);
    if (!monto || monto <= 0) { showToast('Ingresa un monto válido', 'error'); return; }
    fetch(API + '?action=movimiento_crear', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            cuenta_banco_id:     cuentaActiva,
            tipo:                document.getElementById('nm-tipo').value,
            fecha:               document.getElementById('nm-fecha').value,
            beneficiario:        document.getElementById('nm-beneficiario').value.trim(),
            numero_comprobante:  document.getElementById('nm-comprobante').value.trim(),
            concepto:            document.getElementById('nm-concepto').value.trim(),
            monto,
        })
    })
    .then(r => r.json())
    .then(d => {
        if (d.error) { showToast(d.message, 'error'); return; }
        closeModal('modal-movimiento');
        showToast('Movimiento registrado', 'success');
        document.getElementById('nm-monto').value      = '';
        document.getElementById('nm-beneficiario').value = '';
        document.getElementById('nm-comprobante').value  = '';
        document.getElementById('nm-concepto').value     = '';
        abrirDetalle(cuentaActiva);
    });
}

// ── Gráfico ────────────────────────────────────────────────────
function loadGrafico(id) {
    fetch(API + '?action=grafico_mensual&id=' + id)
        .then(r => r.json())
        .then(rows => {
            const labels   = rows.map(r => r.mes);
            const ingresos = rows.map(r => parseFloat(r.ingresos));
            const egresos  = rows.map(r => parseFloat(r.egresos));

            const ctx = document.getElementById('grafico-movimientos').getContext('2d');
            if (grafico) grafico.destroy();
            const totalIngresos = ingresos.reduce((a, b) => a + b, 0);
            const totalEgresos  = egresos.reduce((a, b) => a + b, 0);
            grafico = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Ingresos', 'Egresos'],
                    datasets: [{
                        data: [totalIngresos, totalEgresos],
                        backgroundColor: ['#22c55e', '#ef4444'],
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 6,
                    }]
                },
                options: {
                    responsive: true,
                    cutout: '65%',
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } },
                        tooltip: {
                            callbacks: {
                                label: ctx => ` S/ ${parseFloat(ctx.raw).toFixed(2)}`
                            }
                        }
                    }
                }
            });
        });
}

// ── Utils ──────────────────────────────────────────────────────
function escHtml(v) {
    return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function formatFecha(f) {
    if (!f) return '—';
    const [y,m,d] = f.split('T')[0].split('-');
    return `${d}/${m}/${y}`;
}
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function showToast(msg, type = 'info') {
    const icons = { success: 'check-circle', error: 'exclamation-circle', info: 'info-circle' };
    const toast = document.createElement('div');
    toast.className = `app-toast ${type}`;
    toast.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i> ${msg}`;
    document.getElementById('toast-container').appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}
</script>

<?php include '../../includes/footer.php'; ?>
