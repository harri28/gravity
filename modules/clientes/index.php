<?php
require_once '../../config/database.php';

$base_path      = '../../';
$required_roles = ['admin', 'gerente', 'cajero'];
$current_module = 'clientes';
$current_page   = 'clientes';
$page_title     = 'Clientes - Gravity';
$breadcrumb     = '<i class="fas fa-users"></i> Clientes';

include '../../includes/header.php';
?>

<style>
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-bottom:24px; }
.stat-card  { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:18px 20px; display:flex; align-items:center; gap:14px; }
.stat-icon  { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.si-blue { background:#dbeafe; color:#2563eb; }
.si-green { background:#dcfce7; color:#16a34a; }
.si-indigo { background:#eef2ff; color:#6366f1; }
.si-amber { background:#fef3c7; color:#d97706; }
.stat-val { font-size:1.45rem; font-weight:700; color:var(--text-primary); line-height:1; }
.stat-lbl { font-size:.74rem; color:var(--text-muted); margin-top:3px; }

.toolbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px; }
.search-wrap { position:relative; flex:1; min-width:220px; max-width:420px; }
.search-wrap input { width:100%; padding:9px 12px 9px 36px; border:1.5px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text-primary); font-size:.87rem; outline:none; transition:border-color .15s; }
.search-wrap input:focus { border-color:var(--primary); }
.search-wrap i { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:.85rem; pointer-events:none; }
.filter-pills { display:flex; gap:6px; }
.pill { padding:6px 14px; border:1.5px solid var(--border); border-radius:20px; background:none; font-size:.78rem; font-weight:600; color:var(--text-muted); cursor:pointer; transition:.15s; }
.pill:hover { border-color:var(--primary); color:var(--primary); }
.pill.active { background:var(--primary); border-color:var(--primary); color:#fff; }

.table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
.data-table { width:100%; border-collapse:collapse; }
.data-table thead th { background:var(--surface-2); padding:11px 16px; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--text-muted); text-align:left; white-space:nowrap; }
.data-table tbody tr { border-top:1px solid var(--border); transition:background .1s; }
.data-table tbody tr:hover { background:var(--surface-2); }
.data-table td { padding:11px 16px; font-size:.86rem; color:var(--text-secondary); vertical-align:middle; }
.td-nombre { font-weight:600; color:var(--text-primary); }
.td-sub { font-size:.75rem; color:var(--text-muted); margin-top:2px; }

.avatar { width:34px; height:34px; border-radius:50%; background:var(--primary); color:#fff; font-size:.85rem; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:.7rem; font-weight:700; text-transform:uppercase; }
.b-activo { background:#dcfce7; color:#15803d; }
.b-inactivo { background:#f1f5f9; color:#64748b; }
.b-completada { background:#dcfce7; color:#15803d; }
.b-anulada { background:#fee2e2; color:#dc2626; }
.b-pendiente { background:#fef3c7; color:#b45309; }

.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:500; align-items:center; justify-content:center; padding:16px; }
.modal-overlay.active { display:flex; }
.modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.15); animation:modalIn .18s ease; overflow:hidden; }
.modal-sm { max-width:760px; }
.modal-lg { max-width:860px; }
@keyframes modalIn { from{opacity:0;transform:translateY(-12px) scale(.97)} to{opacity:1;transform:none} }
.modal-header { padding:18px 24px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.modal-title { font-size:.98rem; font-weight:700; color:var(--text-primary); }
.modal-close { background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1rem; border-radius:6px; padding:4px 6px; transition:color .15s; }
.modal-close:hover { color:var(--text-primary); }
.modal-body { padding:20px 24px; max-height:72vh; overflow-y:auto; }
.modal-footer { padding:14px 24px; border-top:1px solid var(--border); display:flex; gap:10px; justify-content:flex-end; }

.form-row { display:grid; gap:14px; margin-bottom:14px; }
.form-row.c2 { grid-template-columns:1fr 1fr; }
.form-row.c3 { grid-template-columns:1fr 1fr 1fr; }
.form-group label { display:block; font-size:.74rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.04em; margin-bottom:5px; }
.form-group input, .form-group select, .form-group textarea {
    width:100%; padding:9px 12px; background:var(--surface); border:1.5px solid var(--border);
    border-radius:8px; font-size:.88rem; color:var(--text-primary); outline:none; transition:border-color .15s; font-family:inherit; box-sizing:border-box;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:var(--primary); }
.form-group textarea { resize:vertical; min-height:64px; }
.input-lookup { display:grid; grid-template-columns:1fr auto; gap:10px; align-items:end; }
.doc-chip { display:inline-flex; align-items:center; gap:6px; padding:7px 11px; border-radius:999px; background:#eef2ff; color:#4338ca; font-size:.78rem; font-weight:700; }
.lookup-note { font-size:.77rem; color:var(--text-muted); margin-top:6px; }
.ubigeo-box { background:var(--surface-2); border:1px dashed var(--border); border-radius:10px; padding:10px 12px; color:var(--text-muted); font-size:.82rem; margin-bottom:14px; }

.hist-meta { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:18px; }
.hist-meta-item { background:var(--surface-2); border-radius:8px; padding:12px 14px; }
.hist-meta-item .label { font-size:.72rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); margin-bottom:3px; }
.hist-meta-item .value { font-size:1rem; font-weight:700; color:var(--text-primary); }
.empty-state { padding:56px 20px; text-align:center; color:var(--text-muted); }
.empty-state i { font-size:2.4rem; display:block; margin-bottom:12px; }
.btn-sm { padding:6px 12px; font-size:.78rem; }
.btn-icon { padding:6px 9px; }

.cl-tabs { display:flex; gap:6px; margin-bottom:20px; background:var(--surface-2); border-radius:var(--radius); padding:5px; width:fit-content; }
.cl-tab  { display:flex; align-items:center; gap:7px; padding:8px 20px; border:none; border-radius:calc(var(--radius) - 2px); background:transparent; color:var(--text-muted); font-size:.88rem; font-weight:500; cursor:pointer; transition:background .15s,color .15s; white-space:nowrap; }
.cl-tab:hover { background:var(--surface); color:var(--text); }
.cl-tab.active { background:var(--primary); color:#fff; }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title" id="cl-page-title">Clientes</h1>
        <p class="page-subtitle" id="cl-page-subtitle">Administra tus clientes y consulta sus datos tributarios antes de vender o facturar.</p>
    </div>
    <div id="cl-page-actions">
        <button class="btn btn-primary" onclick="abrirModal()">
            <i class="fas fa-user-plus"></i> Nuevo Cliente
        </button>
    </div>
</div>

<!-- Tabs -->
<div class="cl-tabs">
    <button class="cl-tab active" id="tab-btn-clientes" onclick="switchTab('clientes')">
        <i class="fas fa-users"></i> Clientes
    </button>
    <button class="cl-tab" id="tab-btn-proveedores" onclick="switchTab('proveedores')">
        <i class="fas fa-truck"></i> Proveedores
    </button>
</div>

<div id="tab-clientes">

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon si-blue"><i class="fas fa-users"></i></div>
            <div><div class="stat-val" id="sTotal">-</div><div class="stat-lbl">Total registrados</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon si-green"><i class="fas fa-user-check"></i></div>
            <div><div class="stat-val" id="sActivos">-</div><div class="stat-lbl">Clientes activos</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon si-indigo"><i class="fas fa-user-plus"></i></div>
            <div><div class="stat-val" id="sNuevos">-</div><div class="stat-lbl">Nuevos este mes</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon si-amber"><i class="fas fa-receipt"></i></div>
            <div><div class="stat-val" id="sFacturado">-</div><div class="stat-lbl">Total vendido</div></div>
        </div>
    </div>
</div>

<div class="toolbar">
    <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="inputBuscar" placeholder="Nombre, documento, telefono o correo..." oninput="debouncarBusqueda()">
    </div>
    <span id="contadorLabel" style="font-size:.8rem;color:var(--text-muted)"></span>
</div>

<div class="table-wrap table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Documento</th>
                <th>Contacto</th>
                <th style="text-align:center">Compras</th>
                <th style="text-align:right">Total gastado</th>
                <th>Ultima compra</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="tbodyClientes">
            <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>
        </tbody>
    </table>
</div>

</div><!-- /tab-clientes -->

<!-- ===================== TAB: PROVEEDORES ===================== -->
<div id="tab-proveedores" style="display:none">

    <div class="card mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-7">
                <label class="form-label">Buscar</label>
                <div class="input-group">
                    <span class="input-group-icon"><i class="fas fa-search"></i></span>
                    <input type="text" id="pf-q" class="form-control" placeholder="Razón social o RUC...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Estado</label>
                <select class="form-control" id="pf-activos">
                    <option value="">Todos</option>
                    <option value="1">Solo activos</option>
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <button class="btn btn-outline w-100" onclick="resetFiltrosProveedores()"><i class="fas fa-times"></i> Limpiar</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Proveedores registrados</div>
            <span id="prov-count" style="font-size:.82rem;color:var(--text-muted)">Cargando...</span>
        </div>
        <div class="table-wrap table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>RUC</th>
                        <th>Razón Social</th>
                        <th>Nombre Comercial</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Contacto</th>
                        <th class="text-right">Ingresos</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="prov-body">
                    <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted)">
                        <i class="fas fa-spinner fa-spin"></i>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div><!-- /tab-proveedores -->

<!-- ===================== MODAL: Proveedor ===================== -->
<div class="modal-overlay" id="modal-proveedor">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span class="modal-title" id="modal-proveedor-title">
                <i class="fas fa-truck" style="color:var(--primary);margin-right:8px"></i>Nuevo Proveedor
            </span>
            <button class="modal-close" onclick="cerrarModal('modal-proveedor')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-row c2">
                <div class="form-group">
                    <label>RUC / DNI</label>
                    <div class="input-lookup">
                        <input type="text" id="p-ruc" placeholder="20100055858" maxlength="20">
                        <button type="button" class="btn btn-outline" id="btnConsultarProveedor" onclick="consultarDocumentoProveedor()">
                            <i class="fas fa-search"></i> Consultar
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Nombre Comercial</label>
                    <input type="text" id="p-nombre-comercial" placeholder="Bayer">
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Razón Social *</label>
                    <input type="text" id="p-razon-social" placeholder="LABORATORIOS BAYER S.A.">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" id="p-telefono" placeholder="01-6285500">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="p-email" placeholder="ventas@proveedor.com">
                </div>
                <div class="form-group">
                    <label>Persona de Contacto</label>
                    <input type="text" id="p-contacto" placeholder="Juan Pérez">
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" id="p-direccion" placeholder="Av. Industrial 123, Lima">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModal('modal-proveedor')">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-proveedor" onclick="saveProveedor()">
                <i class="fas fa-save"></i> Guardar
            </button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="overlayForm">
    <div class="modal modal-sm">
        <div class="modal-header">
            <span class="modal-title" id="modalFormTitulo"><i class="fas fa-user-plus" style="color:var(--primary);margin-right:8px"></i>Nuevo Cliente</span>
            <button class="modal-close" onclick="cerrarModal('overlayForm')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="fId">
            <input type="hidden" id="fUbigeo">

            <div class="form-row c2">
                <div class="form-group">
                    <label>Tipo de documento *</label>
                    <select id="fTipoDocumento" onchange="actualizarEstadoDocumento()"></select>
                </div>
                <div class="form-group">
                    <label>Numero de documento *</label>
                    <div class="input-lookup">
                        <input type="text" id="fNumeroDocumento" placeholder="Ingresa el documento">
                        <button type="button" class="btn btn-outline" id="btnConsultarDocumento" onclick="consultarDocumento()">
                            <i class="fas fa-search"></i> Consultar
                        </button>
                    </div>
                    <div class="lookup-note" id="lookupNote">Consulta automatica disponible para DNI y RUC.</div>
                </div>
            </div>

            <div style="margin-bottom:14px">
                <span class="doc-chip" id="docChip"><i class="fas fa-id-card"></i> Documento</span>
            </div>

            <div class="form-row c2">
                <div class="form-group">
                    <label id="labelNombres">Nombres o razon social *</label>
                    <input type="text" id="fNombres" placeholder="Nombre del cliente">
                </div>
                <div class="form-group" id="grupoApellidos">
                    <label>Apellidos</label>
                    <input type="text" id="fApellidos" placeholder="Apellidos del cliente">
                </div>
            </div>

            <div class="form-row c2">
                <div class="form-group">
                    <label>Telefono</label>
                    <input type="text" id="fTelefono" maxlength="20" placeholder="987654321">
                </div>
                <div class="form-group">
                    <label>Correo</label>
                    <input type="email" id="fEmail" placeholder="correo@ejemplo.com">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Direccion</label>
                    <textarea id="fDireccion" placeholder="Direccion del cliente"></textarea>
                </div>
            </div>

            <div class="ubigeo-box" id="ubigeoInfo">
                Sin ubigeo consultado.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModal('overlayForm')">Cancelar</button>
            <button class="btn btn-primary" id="btnGuardarCliente" onclick="guardarCliente()"><i class="fas fa-save"></i> Guardar</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="overlayHistorial">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span class="modal-title" id="histTitulo"><i class="fas fa-history" style="color:var(--primary);margin-right:8px"></i>Historial de compras</span>
            <button class="modal-close" onclick="cerrarModal('overlayHistorial')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="histBody">
            <div style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModal('overlayHistorial')">Cerrar</button>
        </div>
    </div>
</div>

<script>
const API          = '<?= $base_path ?>modules/clientes/api.php';
const ALMACEN_API  = '<?= $base_path ?>modules/almacen/api.php';
let debounceBusqueda;
let tiposDocumento = [];
let clientesCache  = [];
let proveedoresLoaded  = false;
let editingProveedorId = null;

// ---- Tabs ----
const CL_TAB_CFG = {
    clientes: {
        title:    'Clientes',
        subtitle: 'Administra tus clientes y consulta sus datos tributarios antes de vender o facturar.',
        actions:  '<button class="btn btn-primary" onclick="abrirModal()"><i class="fas fa-user-plus"></i> Nuevo Cliente</button>',
    },
    proveedores: {
        title:    'Proveedores',
        subtitle: 'Gestiona los proveedores de mercadería',
        actions:  '<button class="btn btn-primary" onclick="openProveedorModal()"><i class="fas fa-plus"></i> Nuevo Proveedor</button>',
    },
};

function switchTab(tab) {
    document.getElementById('tab-clientes').style.display    = tab === 'clientes'    ? '' : 'none';
    document.getElementById('tab-proveedores').style.display = tab === 'proveedores' ? '' : 'none';
    document.getElementById('tab-btn-clientes').classList.toggle('active',    tab === 'clientes');
    document.getElementById('tab-btn-proveedores').classList.toggle('active', tab === 'proveedores');
    const cfg = CL_TAB_CFG[tab];
    document.getElementById('cl-page-title').textContent    = cfg.title;
    document.getElementById('cl-page-subtitle').textContent = cfg.subtitle;
    document.getElementById('cl-page-actions').innerHTML    = cfg.actions;
    if (tab === 'proveedores' && !proveedoresLoaded) {
        loadProveedores();
        setupProveedoresSearch();
        proveedoresLoaded = true;
    }
}

// ---- Proveedores ----
function loadProveedores() {
    const params = new URLSearchParams({
        action:  'proveedores_listar',
        q:       document.getElementById('pf-q').value,
        activos: document.getElementById('pf-activos').value,
    });
    document.getElementById('prov-body').innerHTML =
        '<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    fetch(ALMACEN_API + '?' + params)
        .then(r => r.json())
        .then(data => {
            document.getElementById('prov-count').textContent = data.length + ' proveedor(es)';
            if (!data.length) {
                document.getElementById('prov-body').innerHTML =
                    '<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted)"><i class="fas fa-truck" style="font-size:1.3rem;display:block;margin-bottom:8px"></i>No se encontraron proveedores</td></tr>';
                return;
            }
            document.getElementById('prov-body').innerHTML = data.map(p => {
                const activo = (p.activo === true || p.activo === 't');
                return `<tr>
                    <td style="font-family:monospace;font-size:.82rem">${esc(p.ruc || '—')}</td>
                    <td class="td-nombre">${esc(p.razon_social)}</td>
                    <td style="color:var(--text-muted);font-size:.85rem">${esc(p.nombre_comercial || '—')}</td>
                    <td style="font-size:.82rem">${esc(p.telefono || '—')}</td>
                    <td style="font-size:.82rem">${esc(p.email || '—')}</td>
                    <td style="font-size:.82rem">${esc(p.contacto_nombre || '—')}</td>
                    <td style="text-align:right"><span class="badge b-pendiente">${p.total_ingresos}</span></td>
                    <td>${activo ? '<span class="badge b-activo">Activo</span>' : '<span class="badge b-inactivo">Inactivo</span>'}</td>
                    <td>
                        <div style="display:flex;gap:5px;justify-content:flex-end">
                            <button class="btn btn-secondary btn-sm btn-icon" title="Editar" onclick='openProveedorModal(${JSON.stringify(p)})'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-secondary btn-sm btn-icon"
                                title="${activo ? 'Desactivar' : 'Activar'}"
                                onclick="toggleProveedor(${p.id})"
                                style="color:${activo ? 'var(--danger)' : 'var(--success)'}">
                                <i class="fas fa-${activo ? 'toggle-on' : 'toggle-off'}"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        })
        .catch(() => toast('Error al cargar proveedores', 'err'));
}

function setupProveedoresSearch() {
    let timer;
    document.getElementById('pf-q').addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(loadProveedores, 250);
    });
    document.getElementById('pf-activos').addEventListener('change', loadProveedores);
}

function resetFiltrosProveedores() {
    document.getElementById('pf-q').value       = '';
    document.getElementById('pf-activos').value = '';
    loadProveedores();
}

function openProveedorModal(p = null) {
    editingProveedorId = p ? p.id : null;
    document.getElementById('modal-proveedor-title').innerHTML = editingProveedorId
        ? '<i class="fas fa-edit" style="color:var(--primary);margin-right:8px"></i>Editar Proveedor'
        : '<i class="fas fa-truck" style="color:var(--primary);margin-right:8px"></i>Nuevo Proveedor';
    document.getElementById('p-ruc').value              = p?.ruc              ?? '';
    document.getElementById('p-razon-social').value     = p?.razon_social     ?? '';
    document.getElementById('p-nombre-comercial').value = p?.nombre_comercial ?? '';
    document.getElementById('p-telefono').value         = p?.telefono         ?? '';
    document.getElementById('p-email').value            = p?.email            ?? '';
    document.getElementById('p-contacto').value         = p?.contacto_nombre  ?? '';
    document.getElementById('p-direccion').value        = p?.direccion        ?? '';
    document.getElementById('modal-proveedor').classList.add('active');
    setTimeout(() => document.getElementById('p-ruc').focus(), 100);
}

async function consultarDocumentoProveedor() {
    const numero = document.getElementById('p-ruc').value.trim();
    if (!/^\d{8}$/.test(numero) && !/^\d{11}$/.test(numero)) {
        toast('Ingresa un DNI (8 dígitos) o RUC (11 dígitos) válido', 'err');
        return;
    }

    const btn = document.getElementById('btnConsultarProveedor');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando';

    try {
        const r = await fetch(`${ALMACEN_API}?action=proveedor_lookup_documento`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ numero }),
        });
        const d = await r.json();
        if (d.error) {
            toast(d.message, 'err');
            return;
        }

        document.getElementById('p-razon-social').value = d.proveedor?.razon_social ?? '';
        if (d.proveedor?.direccion) {
            document.getElementById('p-direccion').value = d.proveedor.direccion;
        }
        toast('Documento consultado correctamente', 'ok');
    } catch (e) {
        toast('No se pudo consultar el documento', 'err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-search"></i> Consultar';
    }
}

function saveProveedor() {
    const razon_social = document.getElementById('p-razon-social').value.trim();
    if (!razon_social) { toast('La razón social es requerida', 'err'); return; }
    const payload = {
        id:               editingProveedorId,
        ruc:              document.getElementById('p-ruc').value.trim(),
        razon_social,
        nombre_comercial: document.getElementById('p-nombre-comercial').value.trim(),
        telefono:         document.getElementById('p-telefono').value.trim(),
        email:            document.getElementById('p-email').value.trim(),
        contacto_nombre:  document.getElementById('p-contacto').value.trim(),
        direccion:        document.getElementById('p-direccion').value.trim(),
    };
    const action = editingProveedorId ? 'proveedor_actualizar' : 'proveedor_crear';
    const btn = document.getElementById('btn-guardar-proveedor');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    fetch(ALMACEN_API + '?action=' + action, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Guardar';
        if (d.error) { toast(d.message, 'err'); return; }
        toast(d.message || 'Proveedor guardado');
        cerrarModal('modal-proveedor');
        loadProveedores();
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Guardar'; toast('Error al guardar', 'err'); });
}

function toggleProveedor(id) {
    fetch(ALMACEN_API + '?action=proveedor_toggle_activo', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }),
    })
    .then(r => r.json())
    .then(d => {
        if (d.error) { toast(d.message, 'err'); return; }
        toast('Proveedor ' + ((d.activo === true || d.activo === 't') ? 'activado' : 'desactivado'));
        loadProveedores();
    })
    .catch(() => toast('Error al cambiar estado', 'err'));
}

init();

async function init() {
    try {
        await cargarTiposDocumento();
        actualizarEstadoDocumento();
        await Promise.all([cargarStats(), cargarClientes()]);
    } catch (e) {
        toast('No se pudo iniciar el modulo de clientes', 'err');
    }
}

async function cargarTiposDocumento() {
    const r = await fetch(`${API}?action=document_types`);
    const d = await r.json();
    if (d.error) {
        throw new Error(d.message || 'No se pudieron cargar los tipos de documento');
    }

    tiposDocumento = d.items || [];
    const sel = document.getElementById('fTipoDocumento');
    sel.innerHTML = tiposDocumento.map(item =>
        `<option value="${item.id}" data-codigo="${escAttr(item.codigo)}" data-label="${escAttr(item.descripcion_documento || item.descripcion)}">${esc(item.descripcion_documento || item.descripcion)}</option>`
    ).join('');

    const predeterminado = tiposDocumento.find(item => item.codigo === '1') || tiposDocumento[0];
    if (predeterminado) {
        sel.value = String(predeterminado.id);
    }
}

async function cargarStats() {
    const r = await fetch(`${API}?action=stats`);
    const d = await r.json();
    document.getElementById('sTotal').textContent = d.total ?? '-';
    document.getElementById('sActivos').textContent = d.activos ?? '-';
    document.getElementById('sNuevos').textContent = d.nuevos_mes ?? '-';
    document.getElementById('sFacturado').textContent = 'S/ ' + parseFloat(d.total_facturado || 0).toFixed(2);
}

function debouncarBusqueda() {
    clearTimeout(debounceBusqueda);
    debounceBusqueda = setTimeout(cargarClientes, 280);
}

async function cargarClientes() {
    const tbody = document.getElementById('tbodyClientes');
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    const q = document.getElementById('inputBuscar').value.trim();

    try {
        const r = await fetch(`${API}?action=listar&q=${encodeURIComponent(q)}`);
        const data = await r.json();

        if (data.error) {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:40px;color:#dc2626">${esc(data.message)}</td></tr>`;
            return;
        }

        clientesCache = Array.isArray(data) ? data : [];
        document.getElementById('contadorLabel').textContent = `${clientesCache.length} cliente${clientesCache.length === 1 ? '' : 's'}`;

        if (!clientesCache.length) {
            const sinFiltro = !q;
            tbody.innerHTML = sinFiltro
                ? `<tr><td colspan="7">
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <div>No hay clientes registrados</div>
                        <a onclick="abrirModal()" style="font-size:inherit;color:var(--primary);text-decoration:underline;cursor:pointer">Agregar clientes</a>
                    </div>
                  </td></tr>`
                : '<tr><td colspan="7"><div class="empty-state"><i class="fas fa-search"></i>No se encontraron clientes con ese criterio</div></td></tr>';
            return;
        }

        tbody.innerHTML = clientesCache.map(c => {
            const nombre = obtenerNombreCliente(c);
            const inicial = (nombre[0] || 'C').toUpperCase();
            const documento = c.numero_documento ? `${esc(c.descripcion_documento || 'Documento')}: ${esc(c.numero_documento)}` : '-';

            return `
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="avatar" style="background:${avatarColor(c.id)}">${esc(inicial)}</div>
                            <div>
                                <div class="td-nombre">${esc(nombre)}</div>
                                ${c.created_at ? `<div class="td-sub">Desde ${fmtDate(c.created_at)}</div>` : ''}
                            </div>
                        </div>
                    </td>
                    <td>
                        <div>${documento}</div>
                        ${c.tipo_documento_codigo ? `<div class="td-sub">Codigo SUNAT: ${esc(c.tipo_documento_codigo)}</div>` : ''}
                    </td>
                    <td>
                        ${c.telefono ? `<div><i class="fas fa-phone" style="font-size:.7rem;color:var(--text-muted);margin-right:3px"></i>${esc(c.telefono)}</div>` : ''}
                        ${c.email ? `<div class="td-sub"><i class="fas fa-envelope" style="font-size:.65rem;margin-right:3px"></i>${esc(c.email)}</div>` : ''}
                        ${!c.telefono && !c.email ? '<span style="color:var(--text-muted);font-size:.8rem">-</span>' : ''}
                    </td>
                    <td style="text-align:center;font-weight:600">${c.total_compras ?? 0}</td>
                    <td style="text-align:right;font-weight:600">S/ ${parseFloat(c.total_gastado || 0).toFixed(2)}</td>
                    <td style="font-size:.8rem;color:var(--text-muted)">${c.ultima_compra ? fmtDate(c.ultima_compra) : '-'}</td>
                    <td>
                        <div style="display:flex;gap:5px;justify-content:flex-end">
                            <button class="btn btn-secondary btn-sm btn-icon" title="Historial" onclick="verHistorial(${c.id})"><i class="fas fa-history"></i></button>
                            <button class="btn btn-secondary btn-sm btn-icon" title="Editar" onclick="abrirModalPorId(${c.id})"><i class="fas fa-edit"></i></button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#dc2626">Error al cargar clientes</td></tr>';
    }
}

function obtenerTipoDocumentoSeleccionado() {
    const sel = document.getElementById('fTipoDocumento');
    const option = sel.options[sel.selectedIndex];
    if (!option) {
        return null;
    }

    return {
        id: parseInt(option.value, 10),
        codigo: option.dataset.codigo || '',
        label: option.dataset.label || option.textContent || '',
    };
}

function actualizarEstadoDocumento() {
    const tipo = obtenerTipoDocumentoSeleccionado();
    const codigo = tipo?.codigo || '0';
    const esRuc = codigo === '6';
    const esConsultable = codigo === '1' || codigo === '6';

    document.getElementById('labelNombres').textContent = esRuc ? 'Razon social *' : 'Nombres o razon social *';
    document.getElementById('grupoApellidos').style.display = esRuc ? 'none' : '';
    document.getElementById('fNumeroDocumento').placeholder = codigo === '1'
        ? '12345678'
        : (codigo === '6' ? '20123456789' : 'Ingresa el documento');
    document.getElementById('fNumeroDocumento').maxLength = codigo === '1' ? 8 : (codigo === '6' ? 11 : 15);
    document.getElementById('btnConsultarDocumento').disabled = !esConsultable;
    document.getElementById('lookupNote').textContent = esConsultable
        ? `Consulta automatica disponible para ${tipo.label}.`
        : 'Para este tipo de documento el registro es manual.';
    document.getElementById('docChip').innerHTML = `<i class="fas fa-id-card"></i> ${esc(tipo?.label || 'Documento')}`;

    if (esRuc) {
        document.getElementById('fApellidos').value = '';
    }
}

function abrirModalPorId(id) {
    const cliente = clientesCache.find(item => Number(item.id) === Number(id));
    abrirModal(cliente || null);
}

function abrirModal(cliente = null) {
    const edit = !!(cliente && cliente.id);
    document.getElementById('modalFormTitulo').innerHTML = edit
        ? '<i class="fas fa-user-edit" style="color:var(--primary);margin-right:8px"></i>Editar Cliente'
        : '<i class="fas fa-user-plus" style="color:var(--primary);margin-right:8px"></i>Nuevo Cliente';

    document.getElementById('fId').value = edit ? cliente.id : '';
    document.getElementById('fUbigeo').value = cliente?.ubigeo ?? '';
    document.getElementById('fNombres').value = cliente?.nombres ?? '';
    document.getElementById('fApellidos').value = cliente?.apellidos ?? '';
    document.getElementById('fNumeroDocumento').value = cliente?.numero_documento ?? cliente?.ruc ?? cliente?.dni ?? '';
    document.getElementById('fTelefono').value = cliente?.telefono ?? '';
    document.getElementById('fEmail').value = cliente?.email ?? '';
    document.getElementById('fDireccion').value = cliente?.direccion ?? '';

    if (edit && cliente?.tipo_documento_id) {
        document.getElementById('fTipoDocumento').value = String(cliente.tipo_documento_id);
    } else {
        const pred = tiposDocumento.find(item => item.codigo === '1') || tiposDocumento[0];
        if (pred) {
            document.getElementById('fTipoDocumento').value = String(pred.id);
        }
    }

    actualizarEstadoDocumento();
    actualizarUbigeoInfo(cliente?.ubigeo ?? '');

    document.getElementById('overlayForm').classList.add('active');
    setTimeout(() => document.getElementById('fNumeroDocumento').focus(), 80);
}

async function consultarDocumento() {
    const tipo = obtenerTipoDocumentoSeleccionado();
    const numero = document.getElementById('fNumeroDocumento').value.trim();
    if (!tipo) {
        toast('Debes seleccionar un tipo de documento', 'err');
        return;
    }
    if (!numero) {
        toast('Debes ingresar el numero de documento', 'err');
        return;
    }

    const btn = document.getElementById('btnConsultarDocumento');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando';

    try {
        const r = await fetch(`${API}?action=lookup_document`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tipo_documento_id: tipo.id,
                numero_documento: numero,
            }),
        });
        const d = await r.json();
        if (d.error) {
            toast(d.message, 'err');
            return;
        }

        document.getElementById('fNombres').value = d.cliente?.nombres ?? '';
        document.getElementById('fApellidos').value = d.cliente?.apellidos ?? '';
        document.getElementById('fDireccion').value = d.cliente?.direccion ?? '';
        document.getElementById('fUbigeo').value = d.cliente?.ubigeo ?? '';
        actualizarUbigeoInfo(d.cliente?.ubigeo ?? '');
        toast(d.message || 'Documento consultado correctamente');
    } catch (e) {
        toast('No se pudo consultar el documento', 'err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-search"></i> Consultar';
    }
}

function actualizarUbigeoInfo(ubigeo) {
    const box = document.getElementById('ubigeoInfo');
    const codigo = String(ubigeo || '').trim();
    if (!codigo) {
        box.textContent = 'Sin ubigeo consultado.';
        return;
    }
    box.textContent = `Ubigeo detectado: ${codigo}`;
}

async function guardarCliente() {
    const id = document.getElementById('fId').value;
    const tipo = obtenerTipoDocumentoSeleccionado();
    const payload = {
        id: id ? parseInt(id, 10) : undefined,
        tipo_documento_id: tipo?.id,
        numero_documento: document.getElementById('fNumeroDocumento').value.trim(),
        nombres: document.getElementById('fNombres').value.trim(),
        apellidos: document.getElementById('fApellidos').value.trim(),
        telefono: document.getElementById('fTelefono').value.trim(),
        email: document.getElementById('fEmail').value.trim(),
        direccion: document.getElementById('fDireccion').value.trim(),
        ubigeo: document.getElementById('fUbigeo').value.trim(),
    };

    const btn = document.getElementById('btnGuardarCliente');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando';

    try {
        const action = id ? 'actualizar' : 'crear';
        const r = await fetch(`${API}?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const d = await r.json();
        if (d.error) {
            toast(d.message, 'err');
            return;
        }

        toast(d.message || 'Cliente guardado correctamente');
        cerrarModal('overlayForm');
        await Promise.all([cargarClientes(), cargarStats()]);
    } catch (e) {
        toast('Error de conexion', 'err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Guardar';
    }
}


async function verHistorial(id) {
    document.getElementById('histBody').innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i></div>';
    document.getElementById('overlayHistorial').classList.add('active');

    try {
        const r = await fetch(`${API}?action=historial&id=${id}`);
        const c = await r.json();
        if (c.error) {
            document.getElementById('histBody').innerHTML = `<p style="color:#dc2626;text-align:center">${esc(c.message)}</p>`;
            return;
        }

        const nombre = obtenerNombreCliente(c);
        document.getElementById('histTitulo').innerHTML = `<i class="fas fa-history" style="color:var(--primary);margin-right:8px"></i>${esc(nombre)}`;

        const ventas = c.ventas || [];
        const completadas = ventas.filter(v => v.estado === 'completada');
        const totalGastado = completadas.reduce((sum, item) => sum + parseFloat(item.total || 0), 0);
        const documento = c.numero_documento ? `${c.tipo_documento_codigo || ''} - ${c.numero_documento}` : '-';
        const metodos = { efectivo:'Efectivo', yape:'Yape', plin:'Plin', tarjeta:'Visa/Mastercard', transferencia:'Transferencia' };

        document.getElementById('histBody').innerHTML = `
            <div class="hist-meta">
                <div class="hist-meta-item">
                    <div class="label">Compras completadas</div>
                    <div class="value">${completadas.length}</div>
                </div>
                <div class="hist-meta-item">
                    <div class="label">Total gastado</div>
                    <div class="value">S/ ${totalGastado.toFixed(2)}</div>
                </div>
                <div class="hist-meta-item">
                    <div class="label">Documento</div>
                    <div class="value">${esc(documento)}</div>
                </div>
                <div class="hist-meta-item">
                    <div class="label">Telefono</div>
                    <div class="value">${esc(c.telefono || '-')}</div>
                </div>
            </div>
            ${ventas.length ? `
                <table class="data-table" style="font-size:.84rem">
                    <thead>
                        <tr>
                            <th>N° Venta</th>
                            <th>Fecha</th>
                            <th style="text-align:center">Items</th>
                            <th>Metodo</th>
                            <th style="text-align:right">Total</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${ventas.map(v => `
                            <tr style="border-top:1px solid var(--border)">
                                <td style="font-weight:600;color:var(--text-primary)">${esc(v.numero_venta)}</td>
                                <td style="color:var(--text-muted)">${fmtDate(v.created_at)}</td>
                                <td style="text-align:center">${v.num_items}</td>
                                <td>${esc(metodos[v.metodo_pago] ?? v.metodo_pago ?? '-')}</td>
                                <td style="text-align:right;font-weight:600">S/ ${parseFloat(v.total || 0).toFixed(2)}</td>
                                <td><span class="badge b-${esc(v.estado)}">${esc(capitalize(v.estado))}</span></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            ` : '<div class="empty-state"><i class="fas fa-receipt"></i>Este cliente aun no tiene compras registradas</div>'}
        `;
    } catch (e) {
        document.getElementById('histBody').innerHTML = '<p style="color:#dc2626;text-align:center;padding:20px">Error al cargar historial</p>';
    }
}

function obtenerNombreCliente(cliente) {
    return cliente.razon_social || cliente.nombre_completo || [cliente.nombres, cliente.apellidos].filter(Boolean).join(' ').trim() || 'Cliente';
}

function cerrarModal(id) {
    document.getElementById(id).classList.remove('active');
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            cerrarModal(overlay.id);
        }
    });
});

function fmtDate(dt) {
    if (!dt) {
        return '-';
    }
    return new Date(dt).toLocaleDateString('es-PE', { day:'2-digit', month:'short', year:'numeric' });
}

function capitalize(text) {
    const value = String(text || '');
    return value ? value.charAt(0).toUpperCase() + value.slice(1) : '';
}

function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function escAttr(s) {
    return esc(s).replace(/'/g, '&#39;');
}

function escJs(s) {
    return String(s ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function toast(msg, tipo = 'ok') {
    const el = document.getElementById('app-toast') || (() => {
        const t = document.createElement('div');
        t.id = 'app-toast';
        t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px 18px;font-size:.85rem;display:flex;align-items:center;gap:10px;box-shadow:0 8px 24px rgba(0,0,0,.1);z-index:9999;opacity:0;transform:translateY(8px);transition:opacity .25s,transform .25s;pointer-events:none;max-width:340px';
        document.body.appendChild(t);
        return t;
    })();
    el.style.borderColor = tipo === 'ok' ? '#86efac' : '#fca5a5';
    el.innerHTML = `<i class="fas fa-${tipo === 'ok' ? 'check' : 'exclamation'}-circle" style="color:${tipo === 'ok' ? '#16a34a' : '#dc2626'}"></i><span>${esc(msg)}</span>`;
    el.style.opacity = '1';
    el.style.transform = 'none';
    clearTimeout(el._t);
    el._t = setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(8px)';
    }, 3500);
}

const AVATAR_COLORS = ['#6366f1','#8b5cf6','#ec4899','#0ea5e9','#14b8a6','#f59e0b','#10b981','#ef4444'];
function avatarColor(id) {
    return AVATAR_COLORS[Math.abs(Number(id) || 0) % AVATAR_COLORS.length];
}
</script>

<?php include '../../includes/footer.php'; ?>
