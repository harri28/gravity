<?php
// ============================================================
// ARCHIVO: farmacia/modules/admin/index.php
// MÓDULO:  Administración — Usuarios y Sucursales
// ============================================================

require_once '../../config/database.php';

$base_path      = '../../';
$current_module = 'admin';
$current_page   = 'admin';
$page_title     = 'Administración — Gravity';
$breadcrumb     = '<strong>Administración</strong>';
$required_roles = ['admin', 'gerente'];

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title">
            <i class="fas fa-cogs" style="color:var(--primary);margin-right:8px"></i>Administración
        </div>
        <div class="page-subtitle">Gestión de usuarios, roles y sucursales</div>
    </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid var(--border);padding-bottom:0">
    <button class="tab-btn active" id="tab-usuarios" onclick="switchTab('usuarios')">
        <i class="fas fa-users"></i> Usuarios
    </button>
    <button class="tab-btn" id="tab-sucursales" onclick="switchTab('sucursales')">
        <i class="fas fa-store"></i> Sucursales
    </button>
    <button class="tab-btn" id="tab-mesas" onclick="switchTab('mesas')">
        <i class="fas fa-chair"></i> Mesas
    </button>
    <button class="tab-btn" id="tab-configuracion" onclick="switchTab('configuracion')">
        <i class="fas fa-paint-brush"></i> Configuración
    </button>
    <button class="tab-btn" id="tab-auditoria" onclick="switchTab('auditoria')">
        <i class="fas fa-shield-alt"></i> Auditoría
    </button>
    <button class="tab-btn" id="tab-temas" onclick="switchTab('temas')">
        <i class="fas fa-moon"></i> Temas
    </button>
</div>

<!-- ======================================================
     TAB: USUARIOS
     ====================================================== -->
<div id="pane-usuarios">
    <div class="card">
        <div class="card-header">
            <div class="card-title">Usuarios del sistema</div>
            <button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="abrirModalUsuario()">
                <i class="fas fa-plus"></i> Nuevo usuario
            </button>
        </div>
        <div class="table-wrap table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Username</th>
                        <th>Accesos / Roles</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbody-usuarios">
                    <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======================================================
     TAB: SUCURSALES
     ====================================================== -->
<div id="pane-sucursales" style="display:none">
    <div class="card">
        <div class="card-header">
            <div class="card-title">Sucursales registradas</div>
            <button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="abrirModalSucursal()">
                <i class="fas fa-plus"></i> Nueva sucursal
            </button>
        </div>
        <div class="table-wrap table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Schema</th>
                        <th>Dirección</th>
                        <th>Teléfono</th>
                        <th>Usuarios</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbody-sucursales">
                    <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======================================================
     TAB: MESAS (catalogo de mesas de la sucursal actual)
     ====================================================== -->
<div id="pane-mesas" style="display:none">
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-chair" style="color:var(--primary);margin-right:8px"></i>Mesas de la sucursal
            </div>
            <button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="abrirModalMesa()">
                <i class="fas fa-plus"></i> Nueva mesa
            </button>
        </div>
        <div style="padding:0 20px 10px;font-size:.8rem;color:var(--text-muted)">
            Catalogo editable de mesas / salas donde se abren las comandas. Numero
            unico por sucursal; nombre y capacidad son opcionales.
        </div>
        <div class="table-wrap table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Numero</th>
                        <th>Nombre</th>
                        <th>Capacidad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbody-mesas">
                    <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======================================================
     MODAL: Mesa
     ====================================================== -->
<div class="modal-overlay" id="modal-mesa">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-mesa-titulo">Nueva mesa</h3>
            <button class="modal-close" onclick="closeModal('modal-mesa')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="m-id">
            <div class="row g-3">
                <div class="col-12 col-sm-5">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Numero *</label>
                        <input type="text" id="m-numero" class="form-control" placeholder="Ej: 1, VIP-3" maxlength="20">
                    </div>
                </div>
                <div class="col-12 col-sm-7">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Nombre</label>
                        <input type="text" id="m-nombre" class="form-control" placeholder="Opcional (ej: Sala Dorada)" maxlength="80">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Capacidad (personas)</label>
                <input type="number" id="m-capacidad" class="form-control" placeholder="Opcional" min="1" max="999">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-mesa')">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-mesa" onclick="guardarMesa()">
                <i class="fas fa-save"></i> Guardar
            </button>
        </div>
    </div>
</div>

<!-- ======================================================
     MODAL: Usuario
     ====================================================== -->
<div class="modal-overlay" id="modal-usuario">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-usuario-titulo">Nuevo usuario</h3>
            <button class="modal-close" onclick="closeModal('modal-usuario')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="u-id">
            <div class="row g-3">
                <div class="col-12 col-sm-6">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Nombre *</label>
                        <input type="text" id="u-nombre" class="form-control" placeholder="Nombre">
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Apellido</label>
                        <input type="text" id="u-apellido" class="form-control" placeholder="Apellido">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Nombre de usuario *</label>
                <input type="text" id="u-username" class="form-control" placeholder="usuario123" autocomplete="off">
            </div>
            <div class="form-group" id="row-password">
                <label class="form-label">Contraseña *</label>
                <div class="input-group">
                    <input type="password" id="u-password" class="form-control" placeholder="Mínimo 4 caracteres" autocomplete="new-password">
                </div>
                <small id="lbl-password-opcional" style="color:var(--text-muted);font-size:.76rem;display:none">Dejar vacío para no cambiar la contraseña</small>
            </div>
            <hr style="border:none;border-top:1px solid var(--border);margin:16px 0">
            <div style="font-size:.82rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04rem;margin-bottom:12px">
                Acceso a sucursal
            </div>
            <div class="row g-3">
                <div class="col-12 col-sm-6">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Sucursal</label>
                        <select id="u-sucursal" class="form-control">
                            <option value="">— Sin asignar —</option>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Rol</label>
                        <select id="u-rol" class="form-control">
                            <option value="cajero">Cajero</option>
                            <option value="admin">Administrador</option>
                            <option value="gerente">Superadmin</option>
                        </select>
                    </div>
                </div>
            </div>
            <div style="font-size:.76rem;color:var(--text-muted);margin-top:8px">
                <i class="fas fa-info-circle"></i> Puedes agregar más accesos desde el menú de acciones del usuario.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-usuario')">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-usuario" onclick="guardarUsuario()">
                <i class="fas fa-save"></i> Guardar
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Cambiar contraseña -->
<div class="modal-overlay" id="modal-password">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <h3 class="modal-title">Cambiar contraseña</h3>
            <button class="modal-close" onclick="closeModal('modal-password')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="cp-id">
            <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:16px">
                Usuario: <strong id="cp-username"></strong>
            </p>
            <div class="form-group">
                <label class="form-label">Nueva contraseña *</label>
                <input type="password" id="cp-password" class="form-control" placeholder="Mínimo 4 caracteres">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-password')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarPassword()">
                <i class="fas fa-key"></i> Actualizar
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Asignar acceso adicional -->
<div class="modal-overlay" id="modal-acceso">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <h3 class="modal-title">Asignar acceso a sucursal</h3>
            <button class="modal-close" onclick="closeModal('modal-acceso')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="ac-usuario-id">
            <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:16px">
                Usuario: <strong id="ac-username"></strong>
            </p>
            <div class="form-group">
                <label class="form-label">Sucursal</label>
                <select id="ac-sucursal" class="form-control"></select>
            </div>
            <div class="form-group">
                <label class="form-label">Rol</label>
                <select id="ac-rol" class="form-control">
                    <option value="cajero">Cajero</option>
                    <option value="admin">Administrador</option>
                    <option value="gerente">Superadmin</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-acceso')">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarAcceso()">
                <i class="fas fa-plus"></i> Asignar
            </button>
        </div>
    </div>
</div>

<!-- ======================================================
     MODAL: Sucursal
     ====================================================== -->
<div class="modal-overlay" id="modal-sucursal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-sucursal-titulo">Nueva sucursal</h3>
            <button class="modal-close" onclick="closeModal('modal-sucursal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="s-id">
            <div class="form-group">
                <label class="form-label">Nombre de la sucursal *</label>
                <input type="text" id="s-nombre" class="form-control" placeholder="Ej: Farmacia Central">
            </div>
            <div class="row g-3">
                <div class="col-12 col-sm-6">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Teléfono</label>
                        <input type="text" id="s-telefono" class="form-control" placeholder="01-123-4567">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Dirección</label>
                <input type="text" id="s-direccion" class="form-control" placeholder="Av. Principal 123, Lima">
            </div>
            <div id="row-schema-info" style="display:none;background:var(--surface-2);border-radius:8px;padding:10px 14px;font-size:.8rem;color:var(--text-muted)">
                <i class="fas fa-database" style="color:var(--primary)"></i>
                Schema: <code id="s-schema-display" style="font-weight:600;color:var(--primary)"></code>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-sucursal')">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-sucursal" onclick="guardarSucursal()">
                <i class="fas fa-save"></i> Guardar
            </button>
        </div>
    </div>
</div>

<!-- ======================================================
     TAB: CONFIGURACIÓN DE MARCA
     ====================================================== -->
<div id="pane-configuracion" style="display:none">
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-building" style="color:var(--primary);margin-right:8px"></i>Configuración de empresa
            </div>
        </div>
        <div style="padding:24px;max-width:920px">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap;margin-bottom:22px;padding:18px;border:1px solid var(--border);border-radius:16px;background:var(--surface-2)">
                <div style="display:flex;gap:16px;align-items:center;min-width:280px;flex:1">
                    <div style="width:78px;height:78px;border-radius:16px;border:1px dashed var(--border);background:var(--surface);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0">
                        <i class="fas fa-store-alt" id="cfg-header-icon" style="font-size:2rem;color:var(--primary)"></i>
                        <img id="cfg-header-logo" src="" alt="Logo empresa" style="max-width:78px;max-height:78px;object-fit:contain;display:none">
                    </div>
                    <div>
                        <div id="cfg-header-business-name" style="font-size:1.1rem;font-weight:700;color:var(--text);margin-bottom:6px">Datos de la botica</div>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;color:var(--text-muted);font-size:.85rem">
                            <span><i class="fas fa-id-card" style="margin-right:6px"></i><span id="cfg-header-ruc">RUC pendiente</span></span>
                            <span><i class="fas fa-map-marker-alt" style="margin-right:6px"></i><span id="cfg-header-address">Direccion pendiente</span></span>
                        </div>
                    </div>
                </div>
                <div style="min-width:260px;flex:1;color:var(--text-muted);font-size:.84rem;line-height:1.55">
                    Aqui puedes completar los datos principales del negocio y la informacion que saldra en los
                    comprobantes electronicos. Tambien se guardan las credenciales de SUNAT y el certificado digital.
                </div>
            </div>

            <div style="display:flex;gap:8px;margin-bottom:18px;border-bottom:1px solid var(--border);padding-bottom:10px;flex-wrap:wrap">
                <button class="tab-btn active" id="cfg-subtab-general" onclick="switchConfigSubtab('general')">
                    <i class="fas fa-building"></i> Datos de la botica
                </button>
                <button class="tab-btn" id="cfg-subtab-sunat" onclick="switchConfigSubtab('sunat')">
                    <i class="fas fa-file-invoice"></i> SUNAT y certificado
                </button>
            </div>

            <div id="cfg-pane-general">

            <div style="font-size:.82rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04rem;margin-bottom:14px">
                Datos principales
            </div>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Razon social para emitir *</label>
                        <input type="text" id="cfg-business-name" class="form-control" maxlength="255"
                               placeholder="MYTEMS E.I.R.L.">
                        <small style="color:var(--text-muted);font-size:.76rem">
                            Es el nombre legal que saldra en la boleta o factura.
                        </small>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Nombre del negocio</label>
                        <input type="text" id="cfg-trade-name" class="form-control" maxlength="255"
                               placeholder="Generic Pharma">
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-12 col-md-4">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">RUC</label>
                        <input type="text" id="cfg-ruc" class="form-control" inputmode="numeric" maxlength="11"
                               placeholder="20610316884">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Correo</label>
                        <input type="email" id="cfg-email" class="form-control" maxlength="150"
                               placeholder="admin@mytems.cloud">
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Telefono</label>
                        <input type="text" id="cfg-telefono" class="form-control" maxlength="20"
                               placeholder="950772205">
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-12 col-md-9">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Direccion principal</label>
                        <input type="text" id="cfg-direccion" class="form-control"
                               placeholder="Alonso de Alvarado 209, Tarapoto">
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Codigo pais</label>
                        <input type="text" id="cfg-country-code" class="form-control" maxlength="2"
                               placeholder="PE">
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-6 col-md-3">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Ubigeo</label>
                        <input type="text" id="cfg-ubigeo" class="form-control" inputmode="numeric" maxlength="6"
                               placeholder="220901">
                        <small id="cfg-ubigeo-help" style="color:var(--text-muted);font-size:.76rem">
                            Ejemplo: 220901 corresponde a Tarapoto.
                        </small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Departamento</label>
                        <input type="text" id="cfg-departamento" class="form-control" maxlength="100"
                               placeholder="San Martin">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Provincia</label>
                        <input type="text" id="cfg-provincia" class="form-control" maxlength="100"
                               placeholder="San Martin">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Distrito</label>
                        <input type="text" id="cfg-distrito" class="form-control" maxlength="100"
                               placeholder="Tarapoto">
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-top:4px">
                <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer">
                    <input type="checkbox" id="cfg-tax-enabled" checked>
                    La empresa factura con IGV
                </label>
                <small style="color:var(--text-muted);font-size:.76rem">
                    Si tu precio ya incluye IGV, mantenlo activado.
                </small>
            </div>

            <div class="form-group" style="margin-top:4px">
                <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer">
                    <input type="checkbox" id="cfg-igv-exonerado">
                    Empresa exonerada de IGV (Ley de Amazonía - Región Selva)
                </label>
                <small style="color:var(--text-muted);font-size:.76rem">
                    Actívalo solo si el local está en la Región Selva (Ley 27037). Al activarlo, TODOS los
                    productos existentes en TODAS las sucursales de esta empresa se marcan de inmediato como
                    Exonerado de IGV. Desactivarlo no revierte los productos ya marcados.
                </small>
            </div>

            <div style="font-size:.82rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04rem;margin:22px 0 14px">
                Sistema e identidad visual
            </div>

            <!-- Nombre -->
            <div class="form-group">
                <label class="form-label">Nombre del sistema *</label>
                <input type="text" id="cfg-nombre" class="form-control"
                       placeholder="Ej: Farmacia San Juan" maxlength="100">
                <small style="color:var(--text-muted);font-size:.76rem">
                    Aparece en la barra lateral y el título del navegador.
                </small>
            </div>

            <!-- Logo -->
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Logo de la empresa</label>

                <!-- Vista previa centrada -->
                <div style="display:flex;align-items:center;justify-content:center;
                            padding:20px;border:2px dashed var(--border);border-radius:12px;
                            background:var(--surface-2);margin-bottom:12px">
                    <div id="logo-preview-wrap"
                         style="width:160px;height:80px;display:flex;align-items:center;justify-content:center;overflow:hidden">
                        <i class="fas fa-pills" id="logo-placeholder" style="font-size:2.4rem;color:var(--text-light)"></i>
                        <img id="logo-img" src="" alt="Logo"
                             style="max-width:160px;max-height:80px;object-fit:contain;display:none">
                    </div>
                </div>

                <!-- Selector de archivo + eliminar -->
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <label for="logo-file-input" class="btn btn-outline btn-sm" style="cursor:pointer;margin:0">
                        <i class="fas fa-folder-open"></i> Elegir imagen
                    </label>
                    <input type="file" id="logo-file-input"
                           accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml"
                           style="display:none" onchange="previewLogoLocal(this)">
                    <button class="btn btn-outline btn-sm" id="btn-eliminar-logo" onclick="eliminarLogo()"
                            style="display:none;color:#dc2626;border-color:#dc2626">
                        <i class="fas fa-trash"></i> Eliminar logo
                    </button>
                    <span id="logo-file-name" style="font-size:.82rem;color:var(--text-muted)">Ningún archivo seleccionado</span>
                </div>
                <small style="color:var(--text-muted);font-size:.76rem;margin-top:6px;display:block">
                    JPG, PNG, GIF, WEBP o SVG — máx. 2 MB.
                </small>
            </div>

            <!-- Botón único -->
            <div style="border-top:1px solid var(--border);margin-top:24px;padding-top:20px;display:flex;justify-content:flex-end">
                <button class="btn btn-primary" id="btn-guardar-config" onclick="guardarConfig()">
                    <i class="fas fa-save"></i> Guardar y Actualizar
                </button>
            </div>
            </div>

            <div id="cfg-pane-sunat" style="display:none">
                <div style="border:1px solid var(--border);border-radius:16px;padding:18px;background:var(--surface-2);margin-bottom:18px">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap">
                        <div>
                        <div style="font-size:1rem;font-weight:700;color:var(--text);margin-bottom:6px">
                            Datos para emitir en SUNAT
                        </div>
                        <div style="font-size:.84rem;color:var(--text-muted);max-width:620px;line-height:1.55">
                                Completa aqui el usuario SOL, el entorno de pruebas o produccion
                                y el certificado digital que usara la empresa para emitir.
                        </div>
                    </div>
                    <span class="badge badge-info" style="padding:8px 12px;font-size:.72rem">Emision electronica</span>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="form-group" style="margin:0">
                            <label class="form-label">Nombre del negocio</label>
                            <input type="text" id="cfg-sunat-trade-name" class="form-control" placeholder="Nombre que saldra como referencia comercial" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group" style="margin:0">
                            <label class="form-label">Usuario SOL</label>
                            <input type="text" id="cfg-sunat-username" class="form-control" placeholder="Usuario secundario SOL" autocomplete="off">
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12 col-md-4">
                        <div class="form-group" style="margin:0">
                            <label class="form-label">Clave SOL</label>
                            <input type="text" id="cfg-sunat-password" class="form-control" placeholder="Clave SOL" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group" style="margin:0">
                            <label class="form-label">Clave certificado</label>
                            <input type="text" id="cfg-certificate-password" class="form-control" placeholder="Clave del certificado" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group" style="margin:0">
                            <label class="form-label">Vencimiento certificado</label>
                            <input type="date" id="cfg-certificate-expires-at" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12 col-md-4">
                        <div class="form-group" style="margin:0">
                            <label class="form-label">GRE Client ID</label>
                            <input type="text" id="cfg-gre-client-id" class="form-control" placeholder="Client ID GRE">
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group" style="margin:0">
                            <label class="form-label">GRE Client Secret</label>
                            <input type="text" id="cfg-gre-client-secret" class="form-control" placeholder="Client Secret GRE">
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group" style="margin:0">
                            <label class="form-label">Certificado digital (.pfx)</label>
                            <input type="text" id="cfg-certificate-path" class="form-control" placeholder="Ruta del certificado .pfx">
                            <small id="cfg-certificate-current" style="color:var(--text-muted);font-size:.76rem;display:block;margin-top:6px">
                                Archivo actual: no configurado
                            </small>
                        </div>
                    </div>
                </div>

                <div style="border:1px dashed var(--border);border-radius:12px;padding:14px 16px;background:var(--surface-2);margin:4px 0 18px">
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                        <label for="certificate-file-input" class="btn btn-outline btn-sm" style="cursor:pointer;margin:0">
                            <i class="fas fa-file-upload"></i> Cargar certificado .pfx
                        </label>
                        <input type="file" id="certificate-file-input" accept=".pfx,application/x-pkcs12" style="display:none" onchange="previewCertificateFile(this)">
                        <span id="certificate-file-name" style="font-size:.82rem;color:var(--text-muted)">Ningún archivo seleccionado</span>
                    </div>
                    <small style="color:var(--text-muted);font-size:.76rem;margin-top:8px;display:block">
                        Puedes subir aquí el certificado digital y el sistema guardará la ruta automáticamente.
                    </small>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12 col-md-6">
                        <div class="form-group" style="margin:0">
                            <label class="form-label">Entorno SUNAT</label>
                            <select id="cfg-sunat-server" class="form-control">
                                <option value="3">Beta / Pruebas</option>
                                <option value="1">Produccion</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group" style="margin:0">
                            <label class="form-label">Estado</label>
                            <div style="padding:11px 14px;border:1px solid var(--border);border-radius:10px;background:var(--surface-2);color:var(--text-muted);font-size:.84rem">
                                Estos datos se usan para emitir comprobantes desde esta empresa.
                            </div>
                        </div>
                    </div>
                </div>

                <div style="border-top:1px solid var(--border);margin-top:24px;padding-top:20px;display:flex;justify-content:flex-end">
                    <button class="btn btn-primary" id="btn-guardar-sunat" onclick="guardarConfig()">
                        <i class="fas fa-save"></i> Guardar datos SUNAT
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================
     TAB: AUDITORÍA
     ====================================================== -->
<div id="pane-auditoria" style="display:none">

    <!-- Filtros -->
    <div class="card" style="margin-bottom:16px">
        <div style="padding:16px 20px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin:0;flex:1;min-width:130px">
                <label class="form-label">Desde</label>
                <input type="date" id="aud-desde" class="form-control">
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:130px">
                <label class="form-label">Hasta</label>
                <input type="date" id="aud-hasta" class="form-control">
            </div>
            <div class="form-group" style="margin:0;flex:2;min-width:180px">
                <label class="form-label">Buscar usuario o detalle</label>
                <input type="text" id="aud-q" class="form-control" placeholder="Ej: juan, intento fallido...">
            </div>
            <div class="form-group" style="margin:0;flex:1.5;min-width:160px">
                <label class="form-label">Tipo de evento</label>
                <select id="aud-accion" class="form-control">
                    <option value="">Todos</option>
                    <option value="login">Inicio de sesión</option>
                    <option value="logout">Cierre de sesión</option>
                    <option value="login_fallido">Intento fallido</option>
                </select>
            </div>
            <button class="btn btn-primary" onclick="loadAuditoria()" style="margin-bottom:0">
                <i class="fas fa-search"></i> Buscar
            </button>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-shield-alt" style="color:var(--primary);margin-right:8px"></i>Registro de actividad
            </div>
            <span id="aud-count" style="font-size:.82rem;color:var(--text-muted)"></span>
        </div>
        <div class="table-wrap table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Evento</th>
                        <th>Módulo</th>
                        <th>Detalle</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody id="tbody-auditoria">
                    <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)">
                        Selecciona un rango de fechas y presiona Buscar
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======================================================
     TAB: TEMAS
     ====================================================== -->
<div id="pane-temas" style="display:none">
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-moon" style="color:var(--primary);margin-right:8px"></i>Apariencia
            </div>
        </div>
        <div style="padding:24px;max-width:640px">
            <div class="form-group" style="margin:0">
                <label class="form-label" style="margin-bottom:10px">Apariencia del sistema</label>
                <div style="display:flex;align-items:center;gap:14px">
                    <label class="ios-switch">
                        <input type="checkbox" id="tema-switch" onchange="aplicarTema(this.checked)">
                        <span class="ios-switch-slider"></span>
                    </label>
                    <span id="tema-switch-label" style="font-weight:600;color:var(--text)">
                        <i class="fas fa-sun"></i> Modo claro
                    </span>
                </div>
                <small style="color:var(--text-muted);font-size:.76rem;display:block;margin-top:10px">
                    Se aplica de inmediato para todos los usuarios de esta empresa, en todas las sucursales.
                    Puedes cambiar entre uno y otro cuando quieras.
                </small>
            </div>
        </div>
    </div>
</div>

<div class="app-toast-container" id="toast-container"></div>

<style>
.tab-btn {
    padding: 10px 20px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: .88rem;
    font-weight: 600;
    color: var(--text-muted);
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: .15s;
}
.tab-btn:hover { color: var(--primary); }
.tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }

.badge-rol-gerente { background:#fef3c7; color:#d97706; padding:2px 10px; border-radius:20px; font-size:.72rem; font-weight:700; }
.badge-rol-admin   { background:#eef2ff; color:#4f46e5; padding:2px 10px; border-radius:20px; font-size:.72rem; font-weight:700; }
.badge-rol-cajero  { background:#f0fdf4; color:#16a34a; padding:2px 10px; border-radius:20px; font-size:.72rem; font-weight:700; }

.ios-switch { position: relative; display: inline-block; width: 51px; height: 31px; flex-shrink: 0; }
.ios-switch input { opacity: 0; width: 0; height: 0; }
.ios-switch-slider {
    position: absolute; inset: 0; cursor: pointer;
    background-color: #e2e8f0; border-radius: 34px; transition: .25s;
}
.ios-switch-slider::before {
    position: absolute; content: ""; height: 27px; width: 27px; left: 2px; top: 2px;
    background-color: #fff; border-radius: 50%; transition: .25s;
    box-shadow: 0 2px 4px rgba(0,0,0,.25);
}
.ios-switch input:checked + .ios-switch-slider { background-color: #34C759; }
.ios-switch input:checked + .ios-switch-slider::before { transform: translateX(20px); }
.ios-switch input:disabled + .ios-switch-slider { opacity: .6; cursor: default; }
.ios-switch input:focus-visible + .ios-switch-slider { box-shadow: 0 0 0 3px rgba(37,99,235,.3); }
</style>

<script>
const BASE = '../../';
let sucursalesList = [];

// ---- Tabs ----
function switchTab(tab) {
    ['usuarios', 'sucursales', 'mesas', 'configuracion', 'auditoria', 'temas'].forEach(t => {
        document.getElementById('pane-' + t).style.display = t === tab ? '' : 'none';
        document.getElementById('tab-' + t).classList.toggle('active', t === tab);
    });
    if (tab === 'sucursales')    loadSucursales();
    if (tab === 'mesas')         loadMesas();
    if (tab === 'configuracion') loadConfig();
    if (tab === 'auditoria')     loadAuditoria();
    if (tab === 'temas')         loadTema();
}

// ================================================================
// TEMAS
// ================================================================

function loadTema() {
    fetch(BASE + 'modules/admin/api.php?action=tema_get')
        .then(r => r.json())
        .then(data => syncTemaSwitch(!!data.tema_oscuro))
        .catch(() => showToast('Error al cargar el tema', 'error'));
}

function syncTemaSwitch(temaOscuro) {
    document.getElementById('tema-switch').checked = temaOscuro;
    document.getElementById('tema-switch-label').innerHTML = temaOscuro
        ? '<i class="fas fa-moon"></i> Modo oscuro'
        : '<i class="fas fa-sun"></i> Modo claro';
}

async function aplicarTema(temaOscuro) {
    const sw = document.getElementById('tema-switch');
    sw.disabled = true;
    try {
        const r = await post('tema_guardar', { tema_oscuro: temaOscuro });
        if (r.error) { showToast(r.message, 'error'); syncTemaSwitch(!temaOscuro); return; }
        document.documentElement.setAttribute('data-theme', temaOscuro ? 'dark' : 'light');
        syncTemaSwitch(temaOscuro);
        showToast(temaOscuro ? 'Modo oscuro activado' : 'Modo claro activado', 'success');
    } catch (e) {
        showToast('Error al guardar el tema', 'error');
        syncTemaSwitch(!temaOscuro);
    } finally {
        sw.disabled = false;
    }
}

function switchConfigSubtab(tab) {
    const isGeneral = tab === 'general';
    document.getElementById('cfg-pane-general').style.display = isGeneral ? '' : 'none';
    document.getElementById('cfg-pane-sunat').style.display = isGeneral ? 'none' : '';
    document.getElementById('cfg-subtab-general').classList.toggle('active', isGeneral);
    document.getElementById('cfg-subtab-sunat').classList.toggle('active', !isGeneral);
}

// ================================================================
// USUARIOS
// ================================================================

function loadUsuarios() {
    fetch(BASE + 'modules/admin/api.php?action=usuarios_listar')
        .then(r => r.json())
        .then(data => {
            if (data.error || !Array.isArray(data) || !data.length) {
                document.getElementById('tbody-usuarios').innerHTML =
                    '<tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-users"></i><br><br>No hay usuarios registrados</td></tr>';
                return;
            }
            document.getElementById('tbody-usuarios').innerHTML = data.map(u => {
                const accesos = (typeof u.accesos === 'string' ? JSON.parse(u.accesos) : u.accesos) || [];
                const ROL_LABEL = { gerente: 'Superadmin', admin: 'Admin', cajero: 'Cajero' };
                const accBadges = accesos.map(a =>
                    `<span class="badge badge-rol-${a.rol}" style="margin-right:4px;${!a.activo?'opacity:.4':''}">
                        ${a.sucursal} · ${ROL_LABEL[a.rol] ?? a.rol}
                        ${a.activo ? `<i class="fas fa-times" style="cursor:pointer;margin-left:4px" onclick="revocarAcceso(${u.id},'${escH(u.username)}',${a.sucursal_id})" title="Revocar"></i>` : ''}
                     </span>`
                ).join('') || '<span style="color:var(--text-light);font-size:.8rem">Sin acceso</span>';

                return `<tr>
                    <td>
                        <div style="font-weight:600">${escH(u.nombre)} ${escH(u.apellido||'')}</div>
                    </td>
                    <td><code style="font-size:.82rem">${escH(u.username)}</code></td>
                    <td style="max-width:260px">${accBadges}
                        <button class="btn btn-ghost btn-sm" style="padding:2px 8px;font-size:.72rem;margin-left:4px"
                            onclick="abrirModalAcceso(${u.id},'${escH(u.username)}')">
                            <i class="fas fa-plus"></i>
                        </button>
                    </td>
                    <td><span class="badge ${u.activo=='t'||u.activo===true?'badge-success':'badge-danger'}">${u.activo=='t'||u.activo===true?'Activo':'Inactivo'}</span></td>
                    <td>
                        <button class="btn btn-ghost btn-sm" onclick="abrirEditarUsuario(${u.id},'${escH(u.nombre)}','${escH(u.apellido||'')}','${escH(u.username)}')" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-ghost btn-sm" onclick="abrirCambiarPassword(${u.id},'${escH(u.username)}')" title="Cambiar contraseña">
                            <i class="fas fa-key"></i>
                        </button>
                        <button class="btn btn-ghost btn-sm" onclick="toggleActivoUsuario(${u.id})" title="${u.activo=='t'||u.activo===true?'Desactivar':'Activar'}">
                            <i class="fas fa-${u.activo=='t'||u.activo===true?'ban':'check'}"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');
        })
        .catch(() => showToast('Error al cargar usuarios', 'error'));
}

function abrirModalUsuario() {
    document.getElementById('modal-usuario-titulo').textContent = 'Nuevo usuario';
    document.getElementById('u-id').value         = '';
    document.getElementById('u-nombre').value     = '';
    document.getElementById('u-apellido').value   = '';
    document.getElementById('u-username').value   = '';
    document.getElementById('u-password').value   = '';
    document.getElementById('u-sucursal').value   = '';
    document.getElementById('u-rol').value        = 'cajero';
    document.getElementById('row-password').style.display = '';
    document.getElementById('lbl-password-opcional').style.display = 'none';
    openModal('modal-usuario');
}

function abrirEditarUsuario(id, nombre, apellido, username) {
    document.getElementById('modal-usuario-titulo').textContent = 'Editar usuario';
    document.getElementById('u-id').value       = id;
    document.getElementById('u-nombre').value   = nombre;
    document.getElementById('u-apellido').value = apellido;
    document.getElementById('u-username').value = username;
    document.getElementById('u-password').value = '';
    document.getElementById('lbl-password-opcional').style.display = '';
    openModal('modal-usuario');
}

function guardarUsuario() {
    const id       = document.getElementById('u-id').value;
    const nombre   = document.getElementById('u-nombre').value.trim();
    const apellido = document.getElementById('u-apellido').value.trim();
    const username = document.getElementById('u-username').value.trim();
    const password = document.getElementById('u-password').value;
    const sucId    = document.getElementById('u-sucursal').value;
    const rol      = document.getElementById('u-rol').value;

    if (!nombre || !username) { showToast('Nombre y usuario son requeridos', 'error'); return; }

    const action = id ? 'usuario_actualizar' : 'usuario_crear';
    const body   = id
        ? { id, nombre, apellido, username }
        : { nombre, apellido, username, password, sucursal_id: sucId, rol };

    if (!id && !password) { showToast('La contraseña es requerida', 'error'); return; }

    post(action, body).then(d => {
        if (d.error) { showToast(d.message, 'error'); return; }
        showToast(d.message, 'success');
        closeModal('modal-usuario');
        loadUsuarios();
    });
}

function abrirCambiarPassword(id, username) {
    document.getElementById('cp-id').value        = id;
    document.getElementById('cp-username').textContent = username;
    document.getElementById('cp-password').value  = '';
    openModal('modal-password');
}

function guardarPassword() {
    const id       = document.getElementById('cp-id').value;
    const password = document.getElementById('cp-password').value;
    if (!password || password.length < 4) { showToast('Mínimo 4 caracteres', 'error'); return; }
    post('usuario_cambiar_password', { id, password }).then(d => {
        if (d.error) { showToast(d.message, 'error'); return; }
        showToast(d.message, 'success');
        closeModal('modal-password');
    });
}

function toggleActivoUsuario(id) {
    if (!confirm('¿Cambiar el estado de este usuario?')) return;
    post('usuario_toggle_activo', { id }).then(d => {
        if (d.error) { showToast(d.message, 'error'); return; }
        showToast('Estado actualizado', 'success');
        loadUsuarios();
    });
}

function abrirModalAcceso(userId, username) {
    document.getElementById('ac-usuario-id').value = userId;
    document.getElementById('ac-username').textContent = username;
    const sel = document.getElementById('ac-sucursal');
    sel.innerHTML = sucursalesList.filter(s => s.activo === true || s.activo === 't')
        .map(s => `<option value="${s.id}">${escH(s.nombre)}</option>`).join('');
    openModal('modal-acceso');
}

function guardarAcceso() {
    const uid = document.getElementById('ac-usuario-id').value;
    const sid = document.getElementById('ac-sucursal').value;
    const rol = document.getElementById('ac-rol').value;
    post('asignar_acceso', { usuario_id: uid, sucursal_id: sid, rol }).then(d => {
        if (d.error) { showToast(d.message, 'error'); return; }
        showToast('Acceso asignado', 'success');
        closeModal('modal-acceso');
        loadUsuarios();
    });
}

function revocarAcceso(userId, username, sucursalId) {
    if (!confirm(`¿Revocar acceso de "${username}" a esta sucursal?`)) return;
    post('revocar_acceso', { usuario_id: userId, sucursal_id: sucursalId }).then(d => {
        if (d.error) { showToast(d.message, 'error'); return; }
        showToast('Acceso revocado', 'success');
        loadUsuarios();
    });
}

// ================================================================
// SUCURSALES
// ================================================================

function loadSucursales() {
    fetch(BASE + 'modules/admin/api.php?action=sucursales_listar')
        .then(r => r.json())
        .then(data => {
            if (data.error || !Array.isArray(data)) {
                document.getElementById('tbody-sucursales').innerHTML =
                    '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-store"></i><br><br>No hay sucursales registradas</td></tr>';
                return;
            }
            sucursalesList = data;
            // Actualizar select de nuevo usuario también
            const selU = document.getElementById('u-sucursal');
            const curr = selU.value;
            selU.innerHTML = '<option value="">— Sin asignar —</option>' +
                data.filter(s => s.activo === true || s.activo === 't')
                    .map(s => `<option value="${s.id}">${escH(s.nombre)}</option>`).join('');
            if (curr) selU.value = curr;

            if (!data.length) {
                document.getElementById('tbody-sucursales').innerHTML =
                    '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-store"></i><br><br>No hay sucursales registradas</td></tr>';
                return;
            }
            document.getElementById('tbody-sucursales').innerHTML = data.map(s => `<tr>
                <td><strong>${escH(s.nombre)}</strong></td>
                <td><code style="font-size:.8rem;color:var(--primary)">${escH(s.schema_name)}</code></td>
                <td style="font-size:.83rem;color:var(--text-muted)">${escH(s.direccion||'—')}</td>
                <td style="font-size:.83rem">${escH(s.telefono||'—')}</td>
                <td><span class="badge badge-gray">${s.total_usuarios} usuarios</span></td>
                <td><span class="badge ${s.activo=='t'||s.activo===true?'badge-success':'badge-danger'}">${s.activo=='t'||s.activo===true?'Activa':'Inactiva'}</span></td>
                <td>
                    <button class="btn btn-ghost btn-sm" onclick="abrirEditarSucursal(${s.id},'${escH(s.nombre)}','${escH(s.direccion||'')}','${escH(s.telefono||'')}','${escH(s.schema_name)}')" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-ghost btn-sm" onclick="toggleActivoSucursal(${s.id})" title="${s.activo=='t'||s.activo===true?'Desactivar':'Activar'}">
                        <i class="fas fa-${s.activo=='t'||s.activo===true?'ban':'check'}"></i>
                    </button>
                </td>
            </tr>`).join('');
        })
        .catch(() => {
            document.getElementById('tbody-sucursales').innerHTML =
                '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-store"></i><br><br>No hay sucursales registradas</td></tr>';
            showToast('Error al cargar sucursales', 'error');
        });
}

function abrirModalSucursal() {
    document.getElementById('modal-sucursal-titulo').textContent = 'Nueva sucursal';
    document.getElementById('s-id').value        = '';
    document.getElementById('s-nombre').value    = '';
    document.getElementById('s-telefono').value  = '';
    document.getElementById('s-direccion').value = '';
    document.getElementById('row-schema-info').style.display = 'none';
    document.getElementById('btn-guardar-sucursal').innerHTML = '<i class="fas fa-save"></i> Crear sucursal';
    openModal('modal-sucursal');
}

function abrirEditarSucursal(id, nombre, dir, tel, schema) {
    document.getElementById('modal-sucursal-titulo').textContent = 'Editar sucursal';
    document.getElementById('s-id').value        = id;
    document.getElementById('s-nombre').value    = nombre;
    document.getElementById('s-telefono').value  = tel;
    document.getElementById('s-direccion').value = dir;
    document.getElementById('s-schema-display').textContent = schema;
    document.getElementById('row-schema-info').style.display = '';
    document.getElementById('btn-guardar-sucursal').innerHTML = '<i class="fas fa-save"></i> Guardar cambios';
    openModal('modal-sucursal');
}

function guardarSucursal() {
    const id       = document.getElementById('s-id').value;
    const nombre   = document.getElementById('s-nombre').value.trim();
    const telefono = document.getElementById('s-telefono').value.trim();
    const dir      = document.getElementById('s-direccion').value.trim();

    if (!nombre) { showToast('El nombre es requerido', 'error'); return; }

    const btn = document.getElementById('btn-guardar-sucursal');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

    const action = id ? 'sucursal_actualizar' : 'sucursal_crear';
    const body   = id ? { id, nombre, direccion: dir, telefono } : { nombre, direccion: dir, telefono };

    post(action, body).then(d => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Guardar';
        if (d.error) { showToast(d.message, 'error'); return; }
        showToast(d.message, 'success');
        closeModal('modal-sucursal');
        loadSucursales();
    });
}

function toggleActivoSucursal(id) {
    if (!confirm('¿Cambiar el estado de esta sucursal?')) return;
    post('sucursal_toggle_activo', { id }).then(d => {
        if (d.error) { showToast(d.message, 'error'); return; }
        showToast('Estado actualizado', 'success');
        loadSucursales();
    });
}

// ================================================================
// MESAS (catalogo de mesas de la sucursal actual)
// ================================================================

function loadMesas() {
    fetch(BASE + 'modules/admin/api.php?action=mesas_listar')
        .then(r => r.json())
        .then(data => {
            if (data.error || !Array.isArray(data)) {
                document.getElementById('tbody-mesas').innerHTML =
                    '<tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-chair"></i><br><br>Error al cargar mesas</td></tr>';
                return;
            }
            if (!data.length) {
                document.getElementById('tbody-mesas').innerHTML =
                    '<tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-chair"></i><br><br>No hay mesas registradas. Crea la primera con "Nueva mesa".</td></tr>';
                return;
            }
            document.getElementById('tbody-mesas').innerHTML = data.map(m => {
                const activa = m.activa === true || m.activa === 't';
                const ocupada = m.comanda_abierta_id ? true : false;
                return `<tr>
                    <td><strong>${escH(m.numero)}</strong></td>
                    <td style="font-size:.88rem;color:var(--text-muted)">${escH(m.nombre || '—')}</td>
                    <td style="font-size:.88rem">${m.capacidad ? m.capacidad + ' pers.' : '—'}</td>
                    <td>
                        <span class="badge ${activa ? 'badge-success' : 'badge-danger'}">${activa ? 'Activa' : 'Inactiva'}</span>
                        ${ocupada ? ' <span class="badge badge-warning" title="Comanda abierta ahora mismo">Ocupada</span>' : ''}
                    </td>
                    <td>
                        <button class="btn btn-ghost btn-sm" onclick="abrirEditarMesa(${m.id},'${escH(m.numero)}','${escH(m.nombre || '')}',${m.capacidad || 'null'})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-ghost btn-sm" onclick="toggleActivaMesa(${m.id}, ${ocupada ? 'true' : 'false'})" title="${activa ? 'Desactivar' : 'Activar'}">
                            <i class="fas fa-${activa ? 'ban' : 'check'}"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');
        })
        .catch(() => {
            document.getElementById('tbody-mesas').innerHTML =
                '<tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-chair"></i><br><br>Error al cargar mesas</td></tr>';
            showToast('Error al cargar mesas', 'error');
        });
}

function abrirModalMesa() {
    document.getElementById('modal-mesa-titulo').textContent  = 'Nueva mesa';
    document.getElementById('m-id').value         = '';
    document.getElementById('m-numero').value     = '';
    document.getElementById('m-nombre').value     = '';
    document.getElementById('m-capacidad').value  = '';
    document.getElementById('btn-guardar-mesa').innerHTML = '<i class="fas fa-save"></i> Crear mesa';
    openModal('modal-mesa');
    setTimeout(() => document.getElementById('m-numero').focus(), 80);
}

function abrirEditarMesa(id, numero, nombre, capacidad) {
    document.getElementById('modal-mesa-titulo').textContent  = 'Editar mesa';
    document.getElementById('m-id').value        = id;
    document.getElementById('m-numero').value    = numero;
    document.getElementById('m-nombre').value    = nombre;
    document.getElementById('m-capacidad').value = capacidad == null ? '' : capacidad;
    document.getElementById('btn-guardar-mesa').innerHTML = '<i class="fas fa-save"></i> Guardar cambios';
    openModal('modal-mesa');
}

function guardarMesa() {
    const id        = document.getElementById('m-id').value;
    const numero    = document.getElementById('m-numero').value.trim();
    const nombre    = document.getElementById('m-nombre').value.trim();
    const capRaw    = document.getElementById('m-capacidad').value.trim();
    const capacidad = capRaw === '' ? null : parseInt(capRaw, 10);

    if (!numero) { showToast('El numero es requerido', 'error'); return; }
    if (capRaw !== '' && (!Number.isInteger(capacidad) || capacidad < 1)) {
        showToast('La capacidad debe ser un entero positivo', 'error'); return;
    }

    const btn = document.getElementById('btn-guardar-mesa');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

    const action = id ? 'mesa_actualizar' : 'mesa_crear';
    const body   = id ? { id, numero, nombre, capacidad } : { numero, nombre, capacidad };

    post(action, body).then(d => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Guardar';
        if (d.error) { showToast(d.message, 'error'); return; }
        showToast(d.message || 'Mesa guardada', 'success');
        closeModal('modal-mesa');
        loadMesas();
    });
}

function toggleActivaMesa(id, ocupada) {
    if (ocupada) {
        showToast('No se puede desactivar una mesa con comanda abierta. Cobra primero.', 'error');
        return;
    }
    if (!confirm('¿Cambiar el estado de esta mesa?')) return;
    post('mesa_toggle_activa', { id }).then(d => {
        if (d.error) { showToast(d.message, 'error'); return; }
        showToast('Estado actualizado', 'success');
        loadMesas();
    });
}

// ================================================================
// CONFIGURACIÓN DE MARCA
// ================================================================

let _logoPathActual = null;

function loadConfig() {
    fetch(BASE + 'modules/admin/api.php?action=config_get')
        .then(r => r.json())
        .then(data => {
            document.getElementById('cfg-business-name').value = data.business_name || data.nombre || '';
            document.getElementById('cfg-trade-name').value = data.trade_name || data.nombre || '';
            document.getElementById('cfg-ruc').value = data.ruc || '';
            document.getElementById('cfg-email').value = data.email || '';
            document.getElementById('cfg-telefono').value = data.telefono || '';
            document.getElementById('cfg-direccion').value = data.direccion || '';
            document.getElementById('cfg-country-code').value = data.country_code || 'PE';
            document.getElementById('cfg-ubigeo').value = data.ubigeo || '';
            document.getElementById('cfg-departamento').value = data.departamento || '';
            document.getElementById('cfg-provincia').value = data.provincia || '';
            document.getElementById('cfg-distrito').value = data.distrito || '';
            document.getElementById('cfg-tax-enabled').checked = data.tax_enabled === true || data.tax_enabled === 't' || data.tax_enabled === 1 || data.tax_enabled === '1';
            document.getElementById('cfg-igv-exonerado').checked = data.igv_exonerado === true || data.igv_exonerado === 't' || data.igv_exonerado === 1 || data.igv_exonerado === '1';
            document.getElementById('cfg-sunat-trade-name').value = data.trade_name || '';
            document.getElementById('cfg-sunat-username').value = data.sunat_username || '';
            document.getElementById('cfg-sunat-password').value = data.sunat_password || '';
            document.getElementById('cfg-gre-client-id').value = data.gre_client_id || '';
            document.getElementById('cfg-gre-client-secret').value = data.gre_client_secret || '';
            document.getElementById('cfg-certificate-path').value = data.certificate_path || '';
            document.getElementById('cfg-certificate-password').value = data.certificate_password || '';
            document.getElementById('cfg-certificate-expires-at').value = data.certificate_expires_at || '';
            document.getElementById('cfg-sunat-server').value = data.sunat_server || '3';
            document.getElementById('cfg-nombre').value = data.nombre_sistema || 'Gravity';
            updateCertificateSummary(data.certificate_path || '');
            document.getElementById('certificate-file-name').textContent = data.certificate_path
                ? 'Certificado guardado: ' + data.certificate_path.split('/').pop()
                : 'Ningún archivo seleccionado';
            updateUbigeoHelp(data.ubigeo || '', data.departamento || '', data.provincia || '', data.distrito || '');
            _logoPathActual = data.logo_path || null;
            renderLogoPreview(_logoPathActual);
            syncConfigHeader();
        })
        .catch(() => showToast('Error al cargar configuración', 'error'));
}

function renderLogoPreview(logoPath) {
    const img    = document.getElementById('logo-img');
    const icon   = document.getElementById('logo-placeholder');
    const btnDel = document.getElementById('btn-eliminar-logo');
    const headerLogo = document.getElementById('cfg-header-logo');
    const headerIcon = document.getElementById('cfg-header-icon');
    if (logoPath) {
        img.src = BASE + logoPath;
        img.style.display = '';
        icon.style.display = 'none';
        btnDel.style.display = '';
        headerLogo.src = BASE + logoPath;
        headerLogo.style.display = '';
        headerIcon.style.display = 'none';
    } else {
        img.style.display = 'none';
        img.src = '';
        icon.style.display = '';
        btnDel.style.display = 'none';
        headerLogo.style.display = 'none';
        headerLogo.src = '';
        headerIcon.style.display = '';
    }
}

function syncSidebarBrand() {
    const sidebarLogo = document.getElementById('sidebar-brand-logo');
    const sidebarIcon = document.getElementById('sidebar-brand-icon');

    if (sidebarLogo) {
        if (_logoPathActual) {
            sidebarLogo.src = BASE + _logoPathActual;
            sidebarLogo.style.display = '';
        } else {
            sidebarLogo.style.display = 'none';
            sidebarLogo.removeAttribute('src');
        }
    }

    if (sidebarIcon) {
        sidebarIcon.style.display = _logoPathActual ? 'none' : '';
    }
}

function syncConfigHeader() {
    const businessName = document.getElementById('cfg-business-name').value.trim();
    const tradeName = document.getElementById('cfg-trade-name').value.trim();
    const ruc = document.getElementById('cfg-ruc').value.trim();
    const direccion = document.getElementById('cfg-direccion').value.trim();
    document.getElementById('cfg-header-business-name').textContent = tradeName || businessName || 'Datos de la botica';
    document.getElementById('cfg-header-ruc').textContent = ruc || 'RUC pendiente';
    document.getElementById('cfg-header-address').textContent = direccion || 'Direccion pendiente';
    syncSidebarBrand();
}

function updateCertificateSummary(path) {
    const summary = document.getElementById('cfg-certificate-current');
    if (!summary) return;
    summary.textContent = path ? 'Archivo actual: ' + path : 'Archivo actual: no configurado';
}

function previewCertificateFile(input) {
    const file = input.files[0];
    document.getElementById('certificate-file-name').textContent = file ? file.name : 'Ningún archivo seleccionado';
}

function updateUbigeoHelp(ubigeo, departamento, provincia, distrito) {
    const help = document.getElementById('cfg-ubigeo-help');
    if (!help) return;

    if (ubigeo && departamento && provincia && distrito) {
        help.textContent = `${ubigeo} corresponde a ${distrito}, ${provincia}, ${departamento}.`;
        return;
    }

    help.textContent = 'Ejemplo: 220901 corresponde a Tarapoto.';
}

async function autocompletarUbigeo() {
    const ubigeo = document.getElementById('cfg-ubigeo').value.trim();

    if (!/^\d{6}$/.test(ubigeo)) {
        document.getElementById('cfg-departamento').value = '';
        document.getElementById('cfg-provincia').value = '';
        document.getElementById('cfg-distrito').value = '';
        updateUbigeoHelp(ubigeo, '', '', '');
        return;
    }

    try {
        const data = await fetch(BASE + 'modules/admin/api.php?action=ubigeo_resolver&codigo=' + encodeURIComponent(ubigeo))
            .then(r => r.json());

        if (data.error) {
            document.getElementById('cfg-departamento').value = '';
            document.getElementById('cfg-provincia').value = '';
            document.getElementById('cfg-distrito').value = '';
            updateUbigeoHelp(ubigeo, '', '', '');
            return;
        }

        document.getElementById('cfg-departamento').value = data.departamento || '';
        document.getElementById('cfg-provincia').value = data.provincia || '';
        document.getElementById('cfg-distrito').value = data.distrito || '';
        updateUbigeoHelp(ubigeo, data.departamento || '', data.provincia || '', data.distrito || '');
        syncConfigHeader();
    } catch (error) {
        document.getElementById('cfg-departamento').value = '';
        document.getElementById('cfg-provincia').value = '';
        document.getElementById('cfg-distrito').value = '';
        updateUbigeoHelp(ubigeo, '', '', '');
    }
}

function previewLogoLocal(input) {
    const file = input.files[0];
    if (!file) return;
    document.getElementById('logo-file-name').textContent = file.name;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('logo-img').src = e.target.result;
        document.getElementById('logo-img').style.display = '';
        document.getElementById('logo-placeholder').style.display = 'none';
        document.getElementById('cfg-header-logo').src = e.target.result;
        document.getElementById('cfg-header-logo').style.display = '';
        document.getElementById('cfg-header-icon').style.display = 'none';
    };
    reader.readAsDataURL(file);
}

async function guardarConfig() {
    const nombre = document.getElementById('cfg-nombre').value.trim();
    const businessName = document.getElementById('cfg-business-name').value.trim();
    const tradeName = document.getElementById('cfg-trade-name').value.trim();
    const ruc = document.getElementById('cfg-ruc').value.trim();
    const email = document.getElementById('cfg-email').value.trim();
    const telefono = document.getElementById('cfg-telefono').value.trim();
    const direccion = document.getElementById('cfg-direccion').value.trim();
    const countryCode = document.getElementById('cfg-country-code').value.trim().toUpperCase();
    const ubigeo = document.getElementById('cfg-ubigeo').value.trim();
    const departamento = document.getElementById('cfg-departamento').value.trim();
    const provincia = document.getElementById('cfg-provincia').value.trim();
    const distrito = document.getElementById('cfg-distrito').value.trim();
    const taxEnabled = document.getElementById('cfg-tax-enabled').checked;
    const igvExonerado = document.getElementById('cfg-igv-exonerado').checked;
    const sunatTradeName = document.getElementById('cfg-sunat-trade-name').value.trim();
    const sunatUsername = document.getElementById('cfg-sunat-username').value.trim();
    const sunatPassword = document.getElementById('cfg-sunat-password').value.trim();
    const greClientId = document.getElementById('cfg-gre-client-id').value.trim();
    const greClientSecret = document.getElementById('cfg-gre-client-secret').value.trim();
    const certificatePath = document.getElementById('cfg-certificate-path').value.trim();
    const certificatePassword = document.getElementById('cfg-certificate-password').value.trim();
    const certificateExpiresAt = document.getElementById('cfg-certificate-expires-at').value.trim();
    const sunatServer = document.getElementById('cfg-sunat-server').value;
    if (!nombre) { showToast('El nombre del sistema es requerido', 'error'); return; }
    if (!businessName) { showToast('La razon social es requerida', 'error'); return; }

    const buttons = ['btn-guardar-config', 'btn-guardar-sunat']
        .map(id => document.getElementById(id))
        .filter(Boolean);
    buttons.forEach(btn => {
        btn.disabled = true;
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    });

    try {
        const r1 = await post('config_guardar', {
            nombre_sistema: nombre,
            business_name: businessName,
            ruc,
            email,
            telefono,
            direccion,
            country_code: countryCode || 'PE',
            ubigeo,
            departamento,
            provincia,
            distrito,
            tax_enabled: taxEnabled,
            igv_exonerado: igvExonerado,
            trade_name: sunatTradeName || tradeName,
            sunat_username: sunatUsername,
            sunat_password: sunatPassword,
            gre_client_id: greClientId,
            gre_client_secret: greClientSecret,
            certificate_path: certificatePath,
            certificate_password: certificatePassword,
            certificate_expires_at: certificateExpiresAt,
            sunat_server: sunatServer
        });
        if (r1.error) { showToast(r1.message, 'error'); return; }

        const certInput = document.getElementById('certificate-file-input');
        if (certInput.files[0]) {
            const uploadedCertName = certInput.files[0].name;
            const fdCert = new FormData();
            fdCert.append('certificate', certInput.files[0]);
            const rCert = await fetch(BASE + 'modules/admin/api.php?action=certificate_subir', {
                method: 'POST',
                body: fdCert
            }).then(r => r.json());
            if (rCert.error) { showToast(rCert.message, 'error'); return; }
            document.getElementById('cfg-certificate-path').value = rCert.certificate_path || '';
            updateCertificateSummary(rCert.certificate_path || '');
            certInput.value = '';
            document.getElementById('certificate-file-name').textContent = '✓ ' + uploadedCertName + ' guardado';
        }

        const input = document.getElementById('logo-file-input');
        if (input.files[0]) {
            const fd = new FormData();
            fd.append('logo', input.files[0]);
            const r2 = await fetch(BASE + 'modules/admin/api.php?action=logo_subir', {
                method: 'POST', body: fd
            }).then(r => r.json());
            if (r2.error) { showToast(r2.message, 'error'); return; }
            _logoPathActual = r2.logo_path;
            renderLogoPreview(_logoPathActual);
            input.value = '';
            document.getElementById('logo-file-name').textContent = 'Ningún archivo seleccionado';
        }

        showToast(
            r1.productos_exonerados > 0
                ? `Configuración guardada. ${r1.productos_exonerados} producto(s) marcados como Exonerado de IGV.`
                : 'Configuración guardada',
            'success'
        );
        syncConfigHeader();
    } catch (e) {
        showToast('Error al guardar la configuración', 'error');
    } finally {
        buttons.forEach(btn => {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalHtml || '<i class="fas fa-save"></i> Guardar';
            delete btn.dataset.originalHtml;
        });
    }
}

function eliminarLogo() {
    if (!confirm('¿Eliminar el logo actual? Se restaurará el ícono por defecto.')) return;
    post('logo_eliminar', {}).then(d => {
        if (d.error) { showToast(d.message, 'error'); return; }
        showToast('Logo eliminado', 'success');
        _logoPathActual = null;
        renderLogoPreview(null);
        document.getElementById('logo-file-input').value = '';
        document.getElementById('logo-file-name').textContent = 'Ningún archivo seleccionado';
        syncConfigHeader();
    });
}

// ================================================================
// AUDITORÍA
// ================================================================

const ACCION_CFG = {
    'login':         { label: 'Inicio de sesión', icon: 'sign-in-alt',        color: 'var(--success)' },
    'logout':        { label: 'Cierre de sesión', icon: 'sign-out-alt',       color: 'var(--text-muted)' },
    'login_fallido': { label: 'Intento fallido',  icon: 'exclamation-triangle', color: 'var(--danger)' },
};

function loadAuditoria() {
    const params = new URLSearchParams({
        action: 'auditoria_listar',
        desde:  document.getElementById('aud-desde').value,
        hasta:  document.getElementById('aud-hasta').value,
        q:      document.getElementById('aud-q').value,
        accion: document.getElementById('aud-accion').value,
    });

    document.getElementById('tbody-auditoria').innerHTML =
        '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    document.getElementById('aud-count').textContent = '';

    fetch(BASE + 'modules/admin/api.php?' + params)
        .then(r => r.json())
        .then(data => {
            if (data.error || !Array.isArray(data) || !data.length) {
                document.getElementById('tbody-auditoria').innerHTML =
                    '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-shield-alt" style="font-size:1.3rem"></i><br><br>No hay registros en este período</td></tr>';
                return;
            }
            document.getElementById('aud-count').textContent = data.length + ' registro(s)';
            document.getElementById('tbody-auditoria').innerHTML = data.map(r => {
                const cfg = ACCION_CFG[r.accion] || { label: r.accion, icon: 'circle', color: 'var(--primary)' };
                const fecha = new Date(r.created_at);
                const fechaStr = fecha.toLocaleDateString('es-PE', { day:'2-digit', month:'2-digit', year:'numeric' });
                const horaStr  = fecha.toLocaleTimeString('es-PE', { hour:'2-digit', minute:'2-digit' });
                return `<tr>
                    <td style="font-size:.82rem;white-space:nowrap;color:var(--text-muted)">${fechaStr}</td>
                    <td style="font-size:.82rem;white-space:nowrap;color:var(--text-muted)">${horaStr}</td>
                    <td>
                        <div style="font-weight:600;font-size:.88rem">${escH(r.nombre_usuario || r.username || '—')}</div>
                        <div style="font-size:.75rem;color:var(--text-muted)">${escH(r.username || '')}</div>
                    </td>
                    <td>${r.rol ? `<span class="badge badge-rol-${escH(r.rol)}">${{admin:'Administrador',cajero:'Cajero',gerente:'Superadmin'}[r.rol] ?? escH(r.rol)}</span>` : '<span style="color:var(--text-light);font-size:.8rem">—</span>'}</td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:6px;font-size:.82rem;font-weight:600;color:${cfg.color}">
                            <i class="fas fa-${cfg.icon}"></i> ${cfg.label}
                        </span>
                    </td>
                    <td style="font-size:.82rem;color:var(--text-muted)">${escH(r.modulo || '—')}</td>
                    <td style="font-size:.82rem">${escH(r.detalle || '—')}</td>
                    <td style="font-family:monospace;font-size:.78rem;color:var(--text-light)">${escH(r.ip_address || '—')}</td>
                </tr>`;
            }).join('');
        })
        .catch(() => {
            document.getElementById('tbody-auditoria').innerHTML =
                '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-shield-alt" style="font-size:1.3rem"></i><br><br>No hay registros en este período</td></tr>';
        });
}

// ================================================================
// HELPERS
// ================================================================

function post(action, body) {
    return fetch(BASE + 'modules/admin/api.php?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    }).then(r => r.json());
}

function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function showToast(msg, type = 'info') {
    const icons = { success:'check-circle', error:'exclamation-circle', info:'info-circle' };
    const t = document.createElement('div');
    t.className = `app-toast ${type}`;
    t.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'}"></i> ${msg}`;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

document.addEventListener('DOMContentLoaded', () => {
    // Fechas por defecto para auditoría: últimos 7 días
    const hoy   = new Date();
    const hace7 = new Date(hoy); hace7.setDate(hace7.getDate() - 7);
    document.getElementById('aud-hasta').value = hoy.toISOString().split('T')[0];
    document.getElementById('aud-desde').value = hace7.toISOString().split('T')[0];

    switchConfigSubtab('general');
    ['cfg-business-name', 'cfg-trade-name', 'cfg-ruc', 'cfg-direccion'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', syncConfigHeader);
    });
    const ubigeoInput = document.getElementById('cfg-ubigeo');
    if (ubigeoInput) {
        ubigeoInput.addEventListener('input', () => {
            if (ubigeoInput.value.trim().length === 6) {
                autocompletarUbigeo();
            }
        });
        ubigeoInput.addEventListener('change', autocompletarUbigeo);
        ubigeoInput.addEventListener('blur', autocompletarUbigeo);
    }
    loadSucursales();
    loadUsuarios();
});
</script>

<?php include '../../includes/footer.php'; ?>


