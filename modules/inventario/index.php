<?php
// ============================================================
// ARCHIVO: farmacia/modules/inventario/index.php
// MÓDULO:  Inventario → Gestión de Productos
// ============================================================

require_once '../../config/database.php';

$base_path      = '../../';
$required_roles = ['admin', 'gerente', 'cajero'];
$current_module = 'inventario';
$current_page   = 'inventario';
$page_title     = 'Inventario — Gravity';
$breadcrumb     = '<strong>Inventario</strong> / Gestión de Productos';

include '../../includes/header.php';
?>

<style>
.inv-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 20px;
    background: var(--surface-2);
    border-radius: var(--radius);
    padding: 5px;
    width: fit-content;
}
.inv-tab {
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
.inv-tab:hover { background: var(--surface); color: var(--text); }
.inv-tab.active { background: var(--primary); color: #fff; }
.inv-tab.active:hover { background: var(--primary-dark, var(--primary)); }

/* Paginación */
#inv-pagination {
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
        <div class="page-title" id="inv-page-title">
            <i class="fas fa-boxes" style="color:var(--primary);margin-right:8px"></i>Inventario
        </div>
        <div class="page-subtitle" id="inv-page-subtitle">Gestiona productos, precios y niveles de stock</div>
    </div>
    <div class="page-actions" id="inv-page-actions">
        <button class="btn btn-primary" onclick="openProductoModal()">
            <i class="fas fa-plus"></i> Nuevo Producto
        </button>
    </div>
</div>

<!-- Botón Regresar (solo visible al crear/editar producto) -->
<button id="btn-regresar" onclick="cerrarTabProducto()" style="display:none;align-items:center;gap:7px;padding:6px 14px;margin-bottom:12px;border:1.5px solid var(--border);border-radius:var(--radius);background:var(--surface-2);color:var(--text-muted);font-size:.85rem;font-weight:500;cursor:pointer;transition:background .15s,color .15s">
    <i class="fas fa-arrow-left"></i> Regresar
</button>

<!-- Tabs -->
<div class="inv-tabs" id="inv-tabs-bar">
    <button class="inv-tab active" id="tab-btn-inventario" onclick="switchTab('inventario')">
        <i class="fas fa-boxes"></i> Inventario
    </button>
    <button class="inv-tab" id="tab-btn-categorias" onclick="switchTab('categorias')">
        <i class="fas fa-tags"></i> Categorías
    </button>
    <?php if (isAdmin()): ?>
    <button class="inv-tab" id="tab-btn-toma" onclick="switchTab('toma')">
        <i class="fas fa-clipboard-check"></i> Toma de Inventario
    </button>
    <button class="inv-tab" id="tab-btn-eliminados" onclick="switchTab('eliminados')">
        <i class="fas fa-trash"></i> Eliminados
    </button>
    <?php endif; ?>
</div>

<!-- ===================== TAB: INVENTARIO ===================== -->
<div id="tab-inventario">

<!-- Stat cards -->
<div class="row g-3 mb-4" id="stats-container">
    <?php foreach (['blue','green','yellow','red'] as $c): ?>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon <?= $c ?>"><i class="fas fa-spinner fa-spin"></i></div>
            <div><div class="stat-value">—</div><div class="stat-label">...</div></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-5">
            <label class="form-label">Buscar</label>
            <div class="input-group">
                <span class="input-group-icon"><i class="fas fa-search"></i></span>
                <input type="text" id="f-q" class="form-control" placeholder="Nombre, código o laboratorio...">
            </div>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Categoría</label>
            <select class="form-control" id="f-categoria">
                <option value="0">Todas</option>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Estado de stock</label>
            <select class="form-control" id="f-stock">
                <option value="">Activos</option>
                <option value="bajo">⚠ Stock bajo</option>
                <option value="agotado">🔴 Agotado</option>
                <option value="ok">✅ Stock OK</option>
                <option value="inactivo">Inactivos</option>
            </select>
        </div>
        <div class="col-12 col-md-auto">
            <button class="btn btn-outline w-100" onclick="resetFiltros()">
                <i class="fas fa-times"></i> Limpiar
            </button>
        </div>
    </div>
</div>

<!-- Tabla de productos -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Productos</div>
        <span style="font-size:.82rem;color:var(--text-muted)" id="result-count">Cargando...</span>
    </div>
    <div class="table-wrap table-responsive">
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th class="text-right" style="padding-left:32px;min-width:110px">P. Venta</th>
                    <th style="text-align:center;padding-left:32px;min-width:90px">Stock</th>
                    <th style="padding-left:32px;min-width:100px">Unidad</th>
                    <th class="text-right" style="padding-left:32px;min-width:80px">Mín.</th>
                </tr>
            </thead>
            <tbody id="tabla-body">
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-light)">
                    <i class="fas fa-spinner fa-spin"></i>
                </td></tr>
            </tbody>
        </table>
    </div>
    <div id="inv-pagination"></div>
</div>

</div><!-- /tab-inventario -->

<!-- ===================== TAB: CATEGORÍAS ===================== -->
<div id="tab-categorias" style="display:none">

    <div class="card mb-4">
        <label class="form-label">Buscar categoría</label>
        <div class="input-group">
            <span class="input-group-icon"><i class="fas fa-search"></i></span>
            <input type="text" id="cat-q" class="form-control" placeholder="Nombre...">
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Categorías de productos</div>
            <span style="font-size:.82rem;color:var(--text-muted)" id="cat-count">—</span>
        </div>
        <div class="table-wrap table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th style="text-align:center;width:120px">Productos</th>
                        <th style="width:140px">Acciones</th>
                    </tr>
                </thead>
                <tbody id="cat-tabla-body">
                    <tr><td colspan="3" style="text-align:center;padding:30px;color:var(--text-light)">
                        <i class="fas fa-spinner fa-spin"></i>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /tab-categorias -->

<!-- ===================== TAB: TOMA DE INVENTARIO (lista de sesiones) ===================== -->
<div id="tab-toma" style="display:none">
    <div class="card">
        <div class="card-header">
            <div class="card-title">Sesiones de Toma de Inventario</div>
            <span style="font-size:.82rem;color:var(--text-muted)" id="toma-count">—</span>
        </div>
        <div class="table-wrap table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Categorías</th>
                        <th style="text-align:center;width:120px">Progreso</th>
                        <th style="text-align:center;width:140px">Vs</th>
                        <th style="width:110px">Estado</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody id="toma-tabla-body">
                    <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-light)">
                        <i class="fas fa-spinner fa-spin"></i>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div><!-- /tab-toma -->

<!-- ===================== TAB: TOMA DE INVENTARIO (detalle / grilla de conteo) ===================== -->
<div id="tab-toma-detalle" style="display:none">
    <button onclick="switchTab('toma')" style="display:flex;align-items:center;gap:7px;padding:6px 14px;margin-bottom:12px;border:1.5px solid var(--border);border-radius:var(--radius);background:var(--surface-2);color:var(--text-muted);font-size:.85rem;font-weight:500;cursor:pointer">
        <i class="fas fa-arrow-left"></i> Volver a sesiones
    </button>

    <div class="card mb-4">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
            <div>
                <div style="font-weight:700;font-size:1.05rem" id="toma-detalle-codigo">—</div>
                <div style="font-size:.85rem;color:var(--text-muted);margin-top:2px" id="toma-detalle-subinfo">—</div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap" id="toma-detalle-acciones"></div>
        </div>
    </div>

    <div class="card mb-4" style="display:flex;flex-direction:row;gap:14px;align-items:center;flex-wrap:wrap">
        <div class="input-group" style="flex:1;min-width:220px;margin:0">
            <span class="input-group-icon"><i class="fas fa-search"></i></span>
            <input type="text" id="toma-detalle-q" class="form-control" placeholder="Buscar por nombre o código...">
        </div>
        <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;color:var(--text-muted);cursor:pointer">
            <input type="checkbox" id="toma-detalle-solo-pendientes"> Ver solo pendientes
        </label>
    </div>

    <div class="card">
        <div class="table-wrap table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th style="width:80px">Unidad</th>
                        <th class="text-right" style="width:100px">Stock</th>
                        <th class="text-right" style="width:120px">Conteo físico</th>
                        <th style="width:150px">Estado</th>
                        <th style="width:130px">Actualizado</th>
                        <th style="width:90px"></th>
                    </tr>
                </thead>
                <tbody id="toma-detalle-body">
                    <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-light)">
                        <i class="fas fa-spinner fa-spin"></i>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div><!-- /tab-toma-detalle -->

<!-- ===================== TAB: ELIMINADOS (papelera, 30 días) ===================== -->
<div id="tab-eliminados" style="display:none">
    <div class="card mb-4" style="background:var(--surface-2);border:1px dashed var(--border)">
        <div style="display:flex;gap:10px;align-items:flex-start;font-size:.85rem;color:var(--text-muted)">
            <i class="fas fa-circle-info" style="color:var(--primary);margin-top:2px"></i>
            <div>Los productos eliminados se guardan aquí durante <strong>30 días</strong> antes de borrarse
            definitivamente. Un producto con ventas, ingresos, salidas u órdenes de compra registradas no se
            borra nunca físicamente (se conserva el historial de comprobantes) — simplemente queda oculto.
            Puedes restaurarlo en cualquier momento mientras esté en esta lista.</div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <div class="card-title">Productos eliminados</div>
            <span style="font-size:.82rem;color:var(--text-muted)" id="eliminados-count">Cargando...</span>
        </div>
        <div class="table-wrap table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th style="text-align:center">Stock</th>
                        <th>Eliminado por</th>
                        <th>Fecha</th>
                        <th style="text-align:center">Vence en</th>
                        <th style="width:110px"></th>
                    </tr>
                </thead>
                <tbody id="eliminados-tabla-body">
                    <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)">
                        <i class="fas fa-spinner fa-spin"></i>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div><!-- /tab-eliminados -->

<!-- ===================== TAB: VER PRODUCTO (solo lectura) ===================== -->
<div id="tab-producto-vista" style="display:none">
    <div class="card">
        <div class="card-header">
            <div class="card-title" id="tab-producto-vista-card-title">
                <i class="fas fa-eye" style="color:var(--primary);margin-right:8px"></i>Información de Producto
            </div>
        </div>
        <div style="padding:20px;display:flex;gap:28px;flex-wrap:wrap-reverse;justify-content:flex-end">
            <div style="flex:1;min-width:280px;display:flex;flex-direction:column" id="pv-campos">
                <!-- poblado por JS -->
            </div>
            <div style="flex-shrink:0;width:180px">
                <div style="width:180px;height:180px;border:1px solid var(--border);border-radius:var(--radius);
                            background:var(--surface-2);display:flex;align-items:center;justify-content:center;overflow:hidden">
                    <img id="pv-imagen" src="" style="width:100%;height:100%;object-fit:contain;display:none">
                    <i id="pv-imagen-placeholder" class="fas fa-image" style="font-size:2.2rem;color:var(--text-light)"></i>
                </div>
            </div>
        </div>
        <div id="pv-precios-unidad-wrap" style="display:none;padding:0 20px 20px">
            <div style="font-weight:700;font-size:.85rem;margin-bottom:6px">Precios por unidad de medida:</div>
            <div id="pv-precios-unidad"></div>
        </div>
    </div>
</div>

<!-- ===================== TAB: NUEVO / EDITAR PRODUCTO ===================== -->
<div id="tab-producto" style="display:none">
    <div class="card">
        <div class="card-header">
            <div class="card-title" id="tab-producto-card-title">
                <i class="fas fa-plus" style="color:var(--primary);margin-right:8px"></i>Información de Producto
            </div>
        </div>
        <div style="display:flex;align-items:stretch">

            <!-- ── Columna izquierda: todos los campos ── -->
            <div style="flex:1;padding:20px;display:flex;flex-direction:column;gap:14px">

                <div style="display:flex;gap:14px">
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Código <span style="color:var(--danger)">*</span></label>
                        <div class="input-group">
                            <span class="input-group-icon" title="Escanea el código de barras o escríbelo manualmente">
                                <i class="fas fa-barcode" style="color:var(--primary)"></i>
                            </span>
                            <input type="text" id="p-codigo" class="form-control" placeholder="MED001 o escanea el código">
                        </div>
                    </div>
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">SKU <span style="font-size:.78rem;color:var(--text-muted)">(código interno)</span></label>
                        <input type="text" id="p-sku" class="form-control" placeholder="Ej: FAR-001">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Nombre <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="p-nombre" class="form-control" placeholder="Paracetamol 500mg x 10 tab">
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label" style="display:flex;justify-content:space-between;align-items:center">
                        Categoría
                        <span style="display:flex;gap:10px">
                            <button type="button" onclick="toggleNuevaCategoria()"
                                    style="font-size:.75rem;color:var(--primary);background:none;border:none;cursor:pointer;padding:0;font-weight:600">
                                <i class="fas fa-plus-circle"></i> Nueva
                            </button>
                            <button type="button" onclick="cerrarTabProducto();switchTab('categorias')"
                                    style="font-size:.75rem;color:var(--text-muted);background:none;border:none;cursor:pointer;padding:0;font-weight:600">
                                <i class="fas fa-cog"></i> Gestionar
                            </button>
                        </span>
                    </label>
                    <select id="p-categoria" class="form-control">
                        <option value="">Sin categoría</option>
                    </select>
                    <div id="nueva-cat-form" style="display:none;margin-top:8px">
                        <div style="display:flex;gap:6px">
                            <input type="text" id="nueva-cat-nombre" class="form-control"
                                   placeholder="Nombre de la categoría"
                                   style="flex:1;font-size:.85rem"
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();guardarCategoria();}
                                              if(event.key==='Escape'){toggleNuevaCategoria();}">
                            <button type="button" class="btn btn-primary btn-sm" onclick="guardarCategoria()" title="Guardar">
                                <i class="fas fa-check"></i>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="toggleNuevaCategoria()" title="Cancelar">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:14px">
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Presentación</label>
                        <input type="text" id="p-presentacion" class="form-control" placeholder="Tabletas x 10">
                    </div>
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Fecha de Vencimiento <span style="font-size:.78rem;color:var(--text-muted)">(opcional)</span></label>
                        <input type="date" id="p-fecha-vencimiento" class="form-control">
                    </div>
                </div>

                <div style="display:flex;gap:14px">
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Precio de Compra (S/)</label>
                        <input type="number" id="p-precio-compra" class="form-control" placeholder="0.00" step="0.01" min="0">
                    </div>
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Precio de Venta (S/) <span style="color:var(--danger)">*</span></label>
                        <input type="number" id="p-precio-venta" class="form-control" placeholder="0.00" step="0.01" min="0">
                    </div>
                </div>

                <div style="display:flex;gap:14px">
                    <div class="form-group" id="stock-inicial-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Stock</label>
                        <input type="number" id="p-stock" class="form-control" placeholder="0" min="0">
                    </div>
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Alerta de Stock</label>
                        <input type="number" id="p-stock-minimo" class="form-control" placeholder="5" min="0">
                    </div>
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label" style="display:flex;justify-content:space-between;align-items:center">
                            <span>Unidad de medida <span style="color:var(--danger)">*</span></span>
                            <button type="button" onclick="toggleNuevaUnidad()"
                                    style="font-size:.75rem;color:var(--primary);background:none;border:none;cursor:pointer;padding:0;font-weight:600">
                                <i class="fas fa-plus-circle"></i> Nueva
                            </button>
                        </label>
                        <select id="p-unidad" class="form-control">
                            <option value="unidad">unidad</option>
                            <option value="caja">caja</option>
                            <option value="caja x 10">caja x 10</option>
                            <option value="caja x 20">caja x 20</option>
                            <option value="caja x 30">caja x 30</option>
                            <option value="caja x 100">caja x 100</option>
                            <option value="blíster x 10">blíster x 10</option>
                            <option value="blíster x 14">blíster x 14</option>
                            <option value="blíster x 20">blíster x 20</option>
                            <option value="blíster x 30">blíster x 30</option>
                            <option value="paquete">paquete</option>
                            <option value="frasco">frasco</option>
                            <option value="frasco 60ml">frasco 60ml</option>
                            <option value="frasco 100ml">frasco 100ml</option>
                            <option value="frasco 120ml">frasco 120ml</option>
                            <option value="frasco 250ml">frasco 250ml</option>
                            <option value="ampolla">ampolla</option>
                            <option value="sobre">sobre</option>
                            <option value="sachet">sachet</option>
                            <option value="tubo">tubo</option>
                            <option value="parche">parche</option>
                            <option value="kilogramo">kilogramo</option>
                            <option value="gramo">gramo</option>
                            <option value="litro">litro</option>
                            <option value="mililitro">mililitro</option>
                        </select>
                        <div id="nueva-unidad-form" style="display:none;margin-top:8px">
                            <div style="display:flex;gap:6px">
                                <input type="text" id="nueva-unidad-nombre" class="form-control"
                                       placeholder="Ej: blíster x 28"
                                       style="flex:1;font-size:.85rem"
                                       onkeydown="if(event.key==='Enter'){event.preventDefault();guardarNuevaUnidad();}
                                                  if(event.key==='Escape'){toggleNuevaUnidad();}">
                                <button type="button" class="btn btn-primary btn-sm" onclick="guardarNuevaUnidad()" title="Agregar">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button type="button" class="btn btn-ghost btn-sm" onclick="toggleNuevaUnidad()" title="Cancelar">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
                    <div class="form-group" style="flex:1.5;min-width:200px;margin-bottom:0">
                        <label class="form-label">Afectacion IGV</label>
                        <select id="p-afectacion-igv-codigo" class="form-control">
                            <option value="20">20 - Exonerado</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:.8;min-width:120px;margin-bottom:0">
                        <label class="form-label">IGV (%)</label>
                        <input type="number" id="p-porcentaje-igv" class="form-control" placeholder="18.00" step="0.01" min="0">
                    </div>
                    <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0">
                        <label class="form-label">Precio</label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.88rem;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:var(--surface-2)">
                            <input type="checkbox" id="p-incluye-igv" style="width:16px;height:16px;cursor:pointer" checked>
                            <span id="p-incluye-igv-texto">El precio de venta guardado ya incluye IGV.</span>
                        </label>
                    </div>
                </div>

                <div style="display:flex;gap:24px;align-items:center;padding:6px 0">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.88rem">
                        <input type="checkbox" id="p-receta" style="width:16px;height:16px;cursor:pointer">
                        <span>Requiere receta médica</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.88rem">
                        <input type="checkbox" id="p-favorito" style="width:16px;height:16px;cursor:pointer">
                        <span><i class="fas fa-star" style="color:#f59e0b"></i> Producto favorito</span>
                    </label>
                </div>

            </div>

            <!-- ── Columna derecha: imagen ── -->
            <div style="flex:1;padding:20px;border-left:1px solid var(--border);display:flex;flex-direction:column;align-items:center;justify-content:center">
                <label class="form-label" style="align-self:center;margin-bottom:10px">Imagen del producto</label>
                <div id="p-imagen-zona"
                     onclick="document.getElementById('p-imagen-input').click()"
                     ondragover="event.preventDefault();this.style.borderColor='var(--primary)'"
                     ondragleave="this.style.borderColor='var(--border)'"
                     ondrop="handleImagenDrop(event)"
                     style="width:180px;height:180px;border:2px dashed var(--border);border-radius:var(--radius);display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;background:var(--surface-2);transition:border-color .2s,background .2s;position:relative;overflow:hidden">
                    <div id="p-imagen-placeholder" style="text-align:center;color:var(--text-muted);padding:12px">
                        <i class="fas fa-image" style="font-size:2.5rem;margin-bottom:8px;display:block"></i>
                        <span style="font-size:.78rem">Clic o arrastra</span>
                    </div>
                    <img id="p-imagen-preview" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover">
                    <button type="button" id="p-imagen-quitar" onclick="event.stopPropagation();quitarImagen()"
                            style="display:none;position:absolute;top:6px;right:6px;background:rgba(0,0,0,.5);color:#fff;border:none;border-radius:50%;width:26px;height:26px;cursor:pointer;font-size:.8rem;align-items:center;justify-content:center">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <input type="file" id="p-imagen-input" accept="image/*" style="display:none" onchange="handleImagenSelect(this)">
            </div>

        </div>
    </div><!-- /card Información de Producto -->

    <!-- ── Card: Información de Medicamentos ── -->
    <div class="card" style="margin-top:20px">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-pills" style="color:var(--primary);margin-right:8px"></i>Información de Medicamentos
            </div>
        </div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px">

                <div style="display:flex;gap:14px">
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Código de Producto DIGEMID</label>
                        <input type="text" id="p-digemid-codigo" class="form-control" placeholder="Ej: 123456">
                    </div>
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Precio de empaque DIGEMID (S/)</label>
                        <input type="number" id="p-digemid-precio" class="form-control" placeholder="0.00" step="0.01" min="0">
                    </div>
                </div>

                <div style="display:flex;gap:14px">
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Registro Sanitario</label>
                        <input type="text" id="p-registro-sanitario" class="form-control" placeholder="Ej: E.F.A. N° 12345">
                    </div>
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Vencimiento del Registro Sanitario</label>
                        <input type="date" id="p-vencimiento-registro" class="form-control">
                    </div>
                </div>

                <div style="display:flex;gap:14px">
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Condición de venta</label>
                        <select id="p-condicion-venta" class="form-control">
                            <option value="">— Seleccionar —</option>
                            <option value="sin_receta">Sin receta médica</option>
                            <option value="con_receta">Con receta médica</option>
                            <option value="con_receta_retenida">Con receta médica retenida</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Principio activo</label>
                        <input type="text" id="p-principio-activo" class="form-control" placeholder="Ej: Paracetamol">
                    </div>
                </div>

                <div style="display:flex;gap:14px">
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Laboratorio</label>
                        <input type="text" id="p-laboratorio" class="form-control" placeholder="Ej: Bayer">
                    </div>
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Concentración</label>
                        <input type="text" id="p-concentracion" class="form-control" placeholder="Ej: 500mg">
                    </div>
                </div>

                <div style="display:flex;gap:14px">
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Forma farmacéutica</label>
                        <input type="text" id="p-forma-farmaceutica" class="form-control" placeholder="Ej: Tableta, Jarabe, Ampolla">
                    </div>
                    <div class="form-group" style="flex:1;margin-bottom:0">
                        <label class="form-label">Presentación farmacéutica</label>
                        <input type="text" id="p-presentacion-farmaceutica" class="form-control" placeholder="Ej: Caja x 10 tabletas">
                    </div>
                </div>

        </div>
    </div><!-- /card Información de Medicamentos -->

    <!-- ── Card: Precios por unidad de medida ── -->
    <div class="card" style="margin-top:20px">
        <div class="card-header">
            <div>
                <div class="card-title">
                    <i class="fas fa-boxes-stacked" style="color:var(--primary);margin-right:8px"></i>Precios por unidad de medidas
                </div>
                <div style="font-size:.78rem;color:var(--text-muted);margin-top:2px">Agrega varios precios a tus productos</div>
            </div>
        </div>
        <div style="padding:20px">
            <div style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
                <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0">
                    <label class="form-label" style="display:flex;justify-content:space-between;align-items:center">
                        Unidad de medida
                        <button type="button" onclick="toggleNuevaUnidadMedida()"
                                style="font-size:.75rem;color:var(--primary);background:none;border:none;cursor:pointer;padding:0;font-weight:600">
                            <i class="fas fa-plus-circle"></i> Nueva
                        </button>
                    </label>
                    <select id="pu-unidad-select" class="form-control" onchange="agregarPrecioUnidad()">
                        <option value="">Seleccione</option>
                    </select>
                    <div id="nueva-unidad-medida-form" style="display:none;margin-top:8px">
                        <div style="display:flex;gap:6px">
                            <input type="text" id="nueva-unidad-medida-nombre" class="form-control"
                                   placeholder="Ej: SACHET"
                                   style="flex:1;font-size:.85rem"
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();guardarNuevaUnidadMedida();}
                                              if(event.key==='Escape'){toggleNuevaUnidadMedida();}">
                            <button type="button" class="btn btn-primary btn-sm" onclick="guardarNuevaUnidadMedida()" title="Agregar">
                                <i class="fas fa-check"></i>
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="toggleNuevaUnidadMedida()" title="Cancelar">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-wrap" style="margin-top:16px">
                <table>
                    <thead>
                        <tr>
                            <th>Unidad de medida</th>
                            <th>Abreviación</th>
                            <th class="text-right" style="width:110px">Cantidad</th>
                            <th class="text-right" style="width:120px">Precio (S/)</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody id="precios-unidad-body">
                        <tr id="precios-unidad-empty">
                            <td colspan="5" style="text-align:center;padding:20px;color:var(--text-light)">
                                Sin precios por unidad de medida configurados
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Botones -->
    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;padding-bottom:8px">
        <button class="btn btn-outline" onclick="cerrarTabProducto()">
            <i class="fas fa-arrow-left"></i> Cancelar
        </button>
        <button class="btn btn-primary" id="btn-guardar-producto" onclick="saveProducto()">
            <i class="fas fa-save"></i> Guardar
        </button>
    </div>
</div><!-- /tab-producto -->

<!-- ===================== MODAL: Ajuste de Stock ===================== -->
<div class="modal-overlay" id="modal-ajuste">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-boxes" style="color:var(--primary);margin-right:8px"></i>Ajuste de Stock
            </h3>
            <button class="modal-close" onclick="closeModal('modal-ajuste')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div style="background:var(--surface-2);border-radius:var(--radius);padding:12px 16px;margin-bottom:16px">
                <div style="font-size:.8rem;color:var(--text-muted)">Producto</div>
                <div style="font-weight:600;font-size:.95rem" id="ajuste-nombre">—</div>
                <div style="font-size:.82rem;color:var(--text-muted);margin-top:4px">
                    Stock actual: <strong id="ajuste-stock-actual" style="color:var(--primary)">—</strong>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Tipo de ajuste</label>
                <div style="display:flex;gap:10px">
                    <label style="flex:1;display:flex;align-items:center;gap:10px;padding:12px 14px;border:2px solid var(--border);border-radius:var(--radius);cursor:pointer;transition:.15s" id="tipo-entrada-label">
                        <input type="radio" name="tipo-ajuste" value="entrada" id="tipo-entrada" checked style="accent-color:var(--success)">
                        <span><i class="fas fa-arrow-down" style="color:var(--success)"></i> <strong>Entrada</strong><br><small style="color:var(--text-muted)">Sumar al stock</small></span>
                    </label>
                    <label style="flex:1;display:flex;align-items:center;gap:10px;padding:12px 14px;border:2px solid var(--border);border-radius:var(--radius);cursor:pointer;transition:.15s" id="tipo-salida-label">
                        <input type="radio" name="tipo-ajuste" value="salida" id="tipo-salida" style="accent-color:var(--danger)">
                        <span><i class="fas fa-arrow-up" style="color:var(--danger)"></i> <strong>Salida</strong><br><small style="color:var(--text-muted)">Restar del stock</small></span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Cantidad</label>
                <input type="number" id="ajuste-cantidad" class="form-control" placeholder="0" min="1" style="font-size:1.1rem">
            </div>
            <div class="form-group">
                <label class="form-label">Motivo <span style="font-size:.8rem;color:var(--text-muted)">(opcional)</span></label>
                <input type="text" id="ajuste-motivo" class="form-control" placeholder="Ej: Compra, Devolución, Merma, Conteo físico...">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-ajuste')">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-ajuste" onclick="saveAjuste()">
                <i class="fas fa-check"></i> Aplicar Ajuste
            </button>
        </div>
    </div>
</div>


<!-- ===================== MODAL: Confirmar Eliminación ===================== -->
<div class="modal-overlay" id="modal-confirmar-eliminar">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-trash" style="color:var(--danger);margin-right:8px"></i>Eliminar categoría
            </h3>
            <button class="modal-close" onclick="closeModal('modal-confirmar-eliminar')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p style="font-size:.93rem;color:var(--text)">
                ¿Estás seguro de que deseas eliminar la categoría
                <strong id="confirmar-eliminar-nombre" style="color:var(--danger)"></strong>?
            </p>
            <p style="font-size:.82rem;color:var(--text-muted);margin-top:8px">
                Esta acción no se puede deshacer. Solo se puede eliminar si ningún producto activo la usa.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-confirmar-eliminar')">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirmar-eliminar" onclick="confirmarEliminarCategoria()">
                <i class="fas fa-trash"></i> Eliminar
            </button>
        </div>
    </div>
</div>

<!-- ===================== MODAL: Confirmar Eliminación de Producto ===================== -->
<div class="modal-overlay" id="modal-confirmar-eliminar-producto">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-trash" style="color:var(--danger);margin-right:8px"></i>Eliminar producto
            </h3>
            <button class="modal-close" onclick="closeModal('modal-confirmar-eliminar-producto')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p style="font-size:.93rem;color:var(--text)">
                ¿Estás seguro de que deseas eliminar
                <strong id="confirmar-eliminar-producto-nombre" style="color:var(--danger)"></strong>?
            </p>
            <p style="font-size:.82rem;color:var(--text-muted);margin-top:8px">
                Pasará a "Eliminados" y se borrará definitivamente en 30 días (antes puedes restaurarlo desde ahí).
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-confirmar-eliminar-producto')">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirmar-eliminar-producto" onclick="eliminarProductoConfirmado()">
                <i class="fas fa-trash"></i> Eliminar
            </button>
        </div>
    </div>
</div>

<!-- ===================== MODAL: Nueva Categoría ===================== -->
<div class="modal-overlay" id="modal-nueva-categoria">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-tag" style="color:var(--primary);margin-right:8px"></i>Nueva Categoría
            </h3>
            <button class="modal-close" onclick="closeModal('modal-nueva-categoria')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Nombre <span style="color:var(--danger)">*</span></label>
                <input type="text" id="nueva-cat-modal-nombre" class="form-control"
                       placeholder="Ej: Antibióticos"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();guardarNuevaCategoriaModal();}
                                  if(event.key==='Escape'){closeModal('modal-nueva-categoria');}">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-nueva-categoria')">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-nueva-cat" onclick="guardarNuevaCategoriaModal()">
                <i class="fas fa-save"></i> Guardar
            </button>
        </div>
    </div>
</div>

<!-- ===================== MODAL: Nueva Toma de Inventario ===================== -->
<div class="modal-overlay" id="modal-nueva-toma">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-clipboard-check" style="color:var(--primary);margin-right:8px"></i>Nueva Toma de Inventario
            </h3>
            <button class="modal-close" onclick="closeModal('modal-nueva-toma')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Nombre <span style="font-size:.8rem;color:var(--text-muted)">(opcional)</span></label>
                <input type="text" id="nt-nombre" class="form-control" placeholder="Ej: Conteo trimestral">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Categorías a incluir</label>
                <div style="border:1px solid var(--border);border-radius:var(--radius);padding:10px;max-height:220px;overflow-y:auto">
                    <label style="display:flex;align-items:center;gap:8px;padding:4px 0;font-weight:600;border-bottom:1px solid var(--border);margin-bottom:6px">
                        <input type="checkbox" id="nt-todas" onchange="toggleTodasCategorias(this.checked)"> Seleccionar todas
                    </label>
                    <div id="nt-categorias-list"></div>
                </div>
                <div style="font-size:.78rem;color:var(--text-muted);margin-top:4px">
                    Se incluirán los productos activos de las categorías seleccionadas al momento de crear la sesión.
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-nueva-toma')">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-nueva-toma" onclick="guardarNuevaTomaModal()">
                <i class="fas fa-save"></i> Crear sesión
            </button>
        </div>
    </div>
</div>

<!-- ===================== MODAL: Confirmar cierre de Toma de Inventario ===================== -->
<div class="modal-overlay" id="modal-confirmar-aplicar-toma">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-triangle-exclamation" style="color:var(--danger);margin-right:8px"></i>Cerrar sesión
            </h3>
            <button class="modal-close" onclick="closeModal('modal-confirmar-aplicar-toma')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p style="font-size:1.05rem;font-weight:700;color:var(--text);margin:0 0 8px">
                ¿Estás seguro de cerrar sesión?
            </p>
            <p style="font-size:.85rem;color:var(--text-muted);margin:0">
                Los productos que ya aplicaste quedarán registrados.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-confirmar-aplicar-toma')">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirmar-aplicar-toma" onclick="confirmarAplicarToma()">
                <i class="fas fa-check"></i> Cerrar sesión
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Confirmar reapertura de producto ya aplicado (Toma de Inventario) -->
<div class="modal-overlay" id="modal-confirmar-editar-toma">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-triangle-exclamation" style="color:var(--danger);margin-right:8px"></i>Corregir stock físico
            </h3>
            <button class="modal-close" onclick="closeModal('modal-confirmar-editar-toma')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p style="font-size:.93rem;color:var(--text)">
                Esto revierte el ajuste de stock ya aplicado para este producto y lo deja disponible para volver a contar y aplicar. ¿Continuar?
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-confirmar-editar-toma')">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirmar-editar-toma" onclick="confirmarEditarProductoToma()">
                <i class="fas fa-pen"></i> Corregir
            </button>
        </div>
    </div>
</div>

<div class="app-toast-container" id="toast-container"></div>

<link href="<?= $base_path ?>assets/vendor/select2/select2.min.css" rel="stylesheet">
<script src="<?= $base_path ?>assets/vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="<?= $base_path ?>assets/vendor/select2/select2.min.js"></script>
<style>
.select2-container { width: 100% !important; }
.select2-container .select2-selection--single {
    height: 48px;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 9px 12px;
    background: #fff;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 28px;
    color: var(--text-dark);
    padding-left: 0;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 46px;
    right: 10px;
}
.select2-dropdown {
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}
.select2-search--dropdown .select2-search__field {
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 8px 10px;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background: var(--primary);
    color: #fff;
}
#toma-detalle-body tr.toma-fila-escaneada {
    background: var(--primary-light);
    box-shadow: inset 3px 0 0 var(--primary);
    transition: background .3s;
}
</style>

<script>
// ============================================================
// Inventario JavaScript
// ============================================================

const BASE = '../../';
const esAdmin = <?= isAdmin() ? 'true' : 'false' ?>;
let categorias  = [];
let editingId   = null;
let ajusteProducto = null;
let facturacionCatalogos = { unidades: [], afectaciones_igv: [] };
let empresaFacturaConIgv = true;
let empresaIgvExonerado = false;
let tabAnterior = 'inventario';
let productoEnVista = null;
let unidadesMedidaCatalogo = [];
let preciosUnidadEditando = [];

// Paginación
let allProductos = [];
let currentPage  = 1;
const PAGE_SIZE  = 50;

// Toma de Inventario
let tomaSesiones = [];
let tomaSesionActual = null;
let tomaDetallesActuales = [];
let tomaRefreshInterval = null;

// ---- Tabs ----
function switchTab(tab) {
    ['inventario', 'categorias', 'producto', 'producto-vista', 'toma', 'toma-detalle', 'eliminados'].forEach(t => {
        document.getElementById('tab-' + t).style.display = t === tab ? '' : 'none';
        const btn = document.getElementById('tab-btn-' + t);
        if (btn) btn.classList.toggle('active', t === tab);
    });

    if (tab !== 'toma-detalle') detenerRefrescoBadgesToma();

    const esProducto = tab === 'producto' || tab === 'producto-vista';
    document.getElementById('inv-tabs-bar').style.display = (esProducto || tab === 'toma-detalle') ? 'none' : '';
    document.getElementById('btn-regresar').style.display = esProducto ? 'flex' : 'none';

    if (tab === 'inventario') {
        document.getElementById('inv-page-title').innerHTML     = '<i class="fas fa-boxes" style="color:var(--primary);margin-right:8px"></i>Inventario';
        document.getElementById('inv-page-subtitle').textContent = 'Gestiona productos, precios y niveles de stock';
        document.getElementById('inv-page-actions').innerHTML   = '<button class="btn btn-primary" onclick="openProductoModal()"><i class="fas fa-plus"></i> Nuevo Producto</button>';
    } else if (tab === 'categorias') {
        document.getElementById('inv-page-title').innerHTML     = '<i class="fas fa-tags" style="color:var(--primary);margin-right:8px"></i>Categorías';
        document.getElementById('inv-page-subtitle').textContent = 'Gestiona las categorías de productos';
        document.getElementById('inv-page-actions').innerHTML   = '<button class="btn btn-primary" onclick="abrirNuevaCategoria()"><i class="fas fa-plus"></i> Nueva Categoría</button>';
        renderCatTabla();
    } else if (tab === 'producto') {
        document.getElementById('inv-page-actions').innerHTML = '';
    } else if (tab === 'producto-vista') {
        document.getElementById('inv-page-title').innerHTML     = '<i class="fas fa-eye" style="color:var(--primary);margin-right:8px"></i>Detalle de Producto';
        document.getElementById('inv-page-subtitle').textContent = 'Información del producto';
        document.getElementById('inv-page-actions').innerHTML   = esAdmin
            ? `<button class="btn btn-outline" style="color:var(--danger);border-color:var(--danger)" onclick="confirmarEliminarProducto()"><i class="fas fa-trash"></i> Eliminar</button>
               <button class="btn btn-primary" onclick="openProductoModal(productoEnVista)"><i class="fas fa-edit"></i> Editar</button>`
            : '';
    } else if (tab === 'toma') {
        document.getElementById('inv-page-title').innerHTML     = '<i class="fas fa-clipboard-check" style="color:var(--primary);margin-right:8px"></i>Toma de Inventario';
        document.getElementById('inv-page-subtitle').textContent = 'Conteo físico de productos por categoría';
        document.getElementById('inv-page-actions').innerHTML   = '<button class="btn btn-primary" onclick="abrirNuevaTomaModal()"><i class="fas fa-plus"></i> Nueva Toma</button>';
        cargarTomaSesiones();
    } else if (tab === 'toma-detalle') {
        document.getElementById('inv-page-actions').innerHTML = '';
    } else if (tab === 'eliminados') {
        document.getElementById('inv-page-title').innerHTML     = '<i class="fas fa-trash" style="color:var(--primary);margin-right:8px"></i>Eliminados';
        document.getElementById('inv-page-subtitle').textContent = 'Productos eliminados — se purgan automáticamente a los 30 días';
        document.getElementById('inv-page-actions').innerHTML   = '';
        cargarEliminados();
    }
}

function cerrarTabProducto() {
    switchTab(tabAnterior);
}

// ---- Init ----
document.addEventListener('DOMContentLoaded', () => {
    Promise.all([loadCategorias(), loadFacturacionCatalogos(), loadEmpresaConfig()]).then(() => {
        loadStats();
        loadProductos();
    }).catch(() => {
        showToast('No se pudieron cargar los catalogos de facturacion', 'error');
    });
    cargarUnidadesMedidaCatalogo();
    setupSearch();
    setupCatSearch();
    setupTipoAjuste();
    setupBarcodeScanner();
    setupFacturacionProducto();
    setupTomaDetalleFiltros();
});

// ---- Stats ----
function loadStats() {
    fetch(BASE + 'modules/inventario/api.php?action=stats')
        .then(r => r.json())
        .then(d => {
            const cfg = [
                { icon: 'boxes',          color: 'blue',   val: d.total_activos, label: 'Productos activos' },
                { icon: 'dollar-sign',    color: 'green',  val: 'S/ ' + parseFloat(d.valor_inventario).toFixed(2), label: 'Valor en inventario' },
                { icon: 'exclamation-triangle', color: 'yellow', val: d.stock_bajo,    label: 'Stock bajo' },
                { icon: 'times-circle',   color: 'red',    val: d.agotados,      label: 'Agotados' },
            ];
            document.getElementById('stats-container').innerHTML = cfg.map(c => `
                <div class="col-6 col-lg-3">
                    <div class="stat-card" style="cursor:pointer" onclick="filtrarPor('${c.label}')">
                        <div class="stat-icon ${c.color}"><i class="fas fa-${c.icon}"></i></div>
                        <div><div class="stat-value">${c.val}</div><div class="stat-label">${c.label}</div></div>
                    </div>
                </div>`).join('');
        });
}

function filtrarPor(label) {
    const map = { 'Stock bajo': 'bajo', 'Agotados': 'agotado' };
    if (map[label]) {
        document.getElementById('f-stock').value = map[label];
        loadProductos();
    }
}

// ---- Categorías ----
async function loadCategorias() {
    const data = await fetch(BASE + 'modules/inventario/api.php?action=categorias').then(r => r.json());
    categorias = data;
    const sel1 = document.getElementById('f-categoria');
    const sel2 = document.getElementById('p-categoria');
    data.forEach(c => {
        sel1.innerHTML += `<option value="${c.id}">${c.nombre}</option>`;
        sel2.innerHTML += `<option value="${c.id}">${c.nombre}</option>`;
    });
}

async function loadFacturacionCatalogos() {
    const data = await fetch(BASE + 'modules/inventario/api.php?action=catalogos_facturacion').then(r => r.json());
    if (data.error) {
        throw new Error(data.message || 'No se pudieron cargar los catalogos de facturacion');
    }

    facturacionCatalogos = data;

    initUnidadComercialSelect();

    const afectacionSel = document.getElementById('p-afectacion-igv-codigo');
    afectacionSel.innerHTML = data.afectaciones_igv.map(item =>
        `<option value="${item.codigo}" data-tipo="${item.tipo}">${item.codigo} - ${item.descripcion}</option>`
    ).join('');
}

async function loadEmpresaConfig() {
    const data = await fetch(BASE + 'modules/admin/api.php?action=config_get').then(r => r.json());
    empresaFacturaConIgv = data.tax_enabled === true || data.tax_enabled === 't' || data.tax_enabled === 1 || data.tax_enabled === '1';
    empresaIgvExonerado = data.igv_exonerado === true || data.igv_exonerado === 't' || data.igv_exonerado === 1 || data.igv_exonerado === '1';
}

function initUnidadComercialSelect() {
    if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
        return;
    }

    const $select = window.jQuery('#p-unidad');
    if ($select.data('select2')) {
        $select.select2('destroy');
    }

    $select.select2({
        width: '100%',
        tags: true,
        dropdownParent: window.jQuery('#tab-producto'),
        placeholder: 'Selecciona o escribe una unidad',
        language: {
            noResults: () => 'Escribe para crear una unidad personalizada',
            searching: () => 'Buscando...'
        }
    });
}

function setupFacturacionProducto() {
    const afectacionSel = document.getElementById('p-afectacion-igv-codigo');
    const incluyeCheckbox = document.getElementById('p-incluye-igv');

    afectacionSel.addEventListener('change', () => aplicarReglasIgvPorAfectacion());
    incluyeCheckbox.addEventListener('change', actualizarTextoIncluyeIgv);
}

function getAfectacionSeleccionada() {
    const codigo = document.getElementById('p-afectacion-igv-codigo').value;
    return facturacionCatalogos.afectaciones_igv.find(item => item.codigo === codigo) || null;
}

function aplicarDefaultFacturacionProducto() {
    document.getElementById('p-afectacion-igv-codigo').value = '20';
    document.getElementById('p-porcentaje-igv').value = '0.00';
    document.getElementById('p-incluye-igv').checked = false;
    aplicarReglasIgvPorAfectacion(true);
}

function aplicarReglasIgvPorAfectacion(restaurarPorDefecto = false) {
    const afectacion = getAfectacionSeleccionada();
    const esGravado = afectacion && afectacion.tipo === 'GRAV';
    const igvInput = document.getElementById('p-porcentaje-igv');
    const incluyeCheckbox = document.getElementById('p-incluye-igv');
    const estabaBloqueado = incluyeCheckbox.disabled;

    if (!esGravado) {
        igvInput.value = '0.00';
        igvInput.disabled = true;
        incluyeCheckbox.checked = false;
        incluyeCheckbox.disabled = true;
    } else {
        igvInput.disabled = false;
        incluyeCheckbox.disabled = false;

        if (restaurarPorDefecto || parseFloat(igvInput.value || '0') <= 0) {
            igvInput.value = '18.00';
        }
        if (restaurarPorDefecto || estabaBloqueado) {
            incluyeCheckbox.checked = empresaFacturaConIgv;
        }
    }

    actualizarTextoIncluyeIgv();
}

function actualizarTextoIncluyeIgv() {
    const afectacion = getAfectacionSeleccionada();
    const esGravado = afectacion && afectacion.tipo === 'GRAV';
    const texto = document.getElementById('p-incluye-igv-texto');

    if (!texto) {
        return;
    }

    if (!esGravado) {
        texto.textContent = 'Este producto no usa IGV por la afectacion elegida.';
        return;
    }

    texto.textContent = document.getElementById('p-incluye-igv').checked
        ? 'El precio de venta guardado ya incluye IGV.'
        : 'El precio de venta se guardara sin IGV incluido.';
}

// ---- Listar productos ----
function loadProductos() {
    const params = new URLSearchParams({
        action:       'listar',
        q:            document.getElementById('f-q').value,
        categoria_id: document.getElementById('f-categoria').value,
        stock_status: document.getElementById('f-stock').value,
    });

    document.getElementById('tabla-body').innerHTML =
        '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    document.getElementById('inv-pagination').innerHTML = '';

    fetch(BASE + 'modules/inventario/api.php?' + params, { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            if (!Array.isArray(data)) throw new Error('unexpected');
            allProductos = data;
            currentPage  = 1;
            document.getElementById('result-count').textContent = data.length + ' producto(s)';
            if (!data.length) {
                const hayFiltros = params.get('q') ||
                    (params.get('categoria_id') && params.get('categoria_id') !== '0') ||
                    (params.get('stock_status') && params.get('stock_status') !== '');
                document.getElementById('tabla-body').innerHTML = hayFiltros
                    ? `<tr><td colspan="7"><div class="empty-state"><i class="fas fa-search"></i>No se encontraron productos con esos filtros</div></td></tr>`
                    : `<tr><td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <div>No hay productos registrados</div>
                            <a onclick="openProductoModal()" style="font-size:inherit;color:var(--primary);text-decoration:underline;cursor:pointer">Agregar productos</a>
                        </div>
                       </td></tr>`;
                return;
            }
            renderPage(1);
        })
        .catch(() => {
            document.getElementById('tabla-body').innerHTML =
                '<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-exclamation-circle" style="font-size:1.3rem;color:var(--danger)"></i><br><br>Error al cargar los productos. Intenta recargar la página.</td></tr>';
        });
}

function renderPage(page) {
    currentPage = page;
    const start = (page - 1) * PAGE_SIZE;
    const slice = allProductos.slice(start, start + PAGE_SIZE);

    document.getElementById('tabla-body').innerHTML = slice.map(p => {
        const agotado   = parseInt(p.stock) === 0;
        const stockBajo = parseInt(p.stock) > 0 && parseInt(p.stock) <= parseInt(p.stock_minimo);
        const stockCls  = agotado ? 'color:var(--danger);font-weight:700' :
                          (stockBajo ? 'color:var(--warning,#f59e0b);font-weight:700' : 'color:var(--success);font-weight:600');
        const stockBadge = '';
        const favIcon = (p.favorito == 't' || p.favorito === true)
            ? '<i class="fas fa-star" style="color:#f59e0b;margin-left:4px" title="Favorito"></i>' : '';
        const recetaIcon = (p.requiere_receta == 't' || p.requiere_receta === true)
            ? '<i class="fas fa-prescription" style="color:var(--primary);margin-left:4px" title="Requiere receta"></i>' : '';

        return `<tr style="font-size:14px">
            <td style="font-family:monospace;color:var(--text-muted)">${p.codigo_interno || '<span style="color:var(--text-light)">—</span>'}</td>
            <td style="font-weight:500">
                <span onclick='verProducto(${JSON.stringify(p)})'
                      style="color:var(--primary);cursor:pointer">${p.nombre}</span>${favIcon}${recetaIcon}
            </td>
            <td>${p.categoria || '<span style="color:var(--text-light)">—</span>'}</td>
            <td class="text-right" style="font-weight:600;white-space:nowrap;padding-left:32px;min-width:110px">S/ ${parseFloat(p.precio_venta).toFixed(2)}</td>
            <td style="text-align:center;padding-left:32px;min-width:90px">
                <span style="${stockCls}">${p.stock}</span>
                ${stockBadge}
                ${esAdmin ? `<button type="button" onclick='openAjusteModal(${JSON.stringify(p)})' title="Ajustar stock"
                    style="background:none;border:none;color:var(--primary);cursor:pointer;padding:2px 4px;margin-left:4px">
                    <i class="fas fa-sliders"></i>
                </button>` : ''}
            </td>
            <td style="color:var(--text-muted);padding-left:32px;min-width:100px">${p.unidad || 'unidad'}</td>
            <td class="text-right" style="color:var(--text-muted);padding-left:32px;min-width:80px">${p.stock_minimo}</td>
        </tr>`;
    }).join('');

    renderPagination(allProductos.length, page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function renderPagination(total, page) {
    const pages = Math.ceil(total / PAGE_SIZE);
    const el = document.getElementById('inv-pagination');
    if (pages <= 1) { el.innerHTML = ''; return; }

    // Build list: always show 1, last, and ±1 around current
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

    let html = `<button class="pg-btn" onclick="renderPage(${page - 1})" ${page === 1 ? 'disabled' : ''}>&#8249;</button>`;
    items.forEach(item => {
        if (item === '...') {
            html += `<span class="pg-ellipsis">…</span>`;
        } else {
            html += `<button class="pg-btn${item === page ? ' active' : ''}" onclick="renderPage(${item})">${item}</button>`;
        }
    });
    html += `<button class="pg-btn" onclick="renderPage(${page + 1})" ${page === pages ? 'disabled' : ''}>&#8250;</button>`;

    el.innerHTML = html;
}

// ---- Búsqueda con debounce ----
function setupSearch() {
    let timer;
    document.getElementById('f-q').addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(loadProductos, 250);
    });
    document.getElementById('f-categoria').addEventListener('change', loadProductos);
    document.getElementById('f-stock').addEventListener('change', loadProductos);
}

function resetFiltros() {
    document.getElementById('f-q').value = '';
    document.getElementById('f-categoria').value = '0';
    document.getElementById('f-stock').value = '';
    loadProductos();
}

// ---- Tab Producto (crear / editar) ----
function verProducto(producto) {
    productoEnVista = producto;

    tabAnterior = document.getElementById('tab-categorias').style.display !== 'none'
        ? 'categorias' : 'inventario';

    document.getElementById('tab-producto-vista-card-title').innerHTML =
        `<i class="fas fa-eye" style="color:var(--primary);margin-right:8px"></i>${producto.nombre}`;

    const img = document.getElementById('pv-imagen');
    const imgPlaceholder = document.getElementById('pv-imagen-placeholder');
    if (producto.imagen_url) {
        img.src = producto.imagen_url;
        img.style.display = 'block';
        imgPlaceholder.style.display = 'none';
    } else {
        img.style.display = 'none';
        imgPlaceholder.style.display = '';
    }

    const boolLabel = v => (v == 't' || v === true) ? 'Sí' : 'No';
    const money = v => 'S/ ' + parseFloat(v || 0).toFixed(2);

    const campos = [
        ['Código', producto.codigo],
        ['SKU (código interno)', producto.codigo_interno],
        ['Nombre', producto.nombre],
        ['Categoría', producto.categoria || '—'],
        ['Unidad de medida', producto.unidad],
        ['Stock', producto.stock],
        ['Alerta', producto.stock_minimo],
        ['Precio de venta', money(producto.precio_venta)],
        ['Precio de compra', money(producto.precio_compra)],
        ['Laboratorio', producto.laboratorio || '—'],
        ['Presentación', producto.presentacion || '—'],
        ['Afectación IGV', producto.afectacion_igv || '—'],
        ['% IGV', parseFloat(producto.porcentaje_igv || 18).toFixed(2) + '%'],
        ['Incluye IGV', boolLabel(producto.incluye_igv)],
        ['Requiere receta', boolLabel(producto.requiere_receta)],
        ['Favorito', boolLabel(producto.favorito)],
        ['Fecha de vencimiento', producto.fecha_vencimiento || '—'],
    ];

    document.getElementById('pv-campos').innerHTML = campos.map(([label, valor]) => `
        <div style="display:flex;gap:6px;padding:3px 0">
            <span style="font-weight:700;font-size:.85rem">${label}:</span>
            <span style="font-weight:400;font-size:.85rem">${valor ?? '—'}</span>
        </div>`).join('');

    const puWrap = document.getElementById('pv-precios-unidad-wrap');
    puWrap.style.display = 'none';
    fetch(BASE + `modules/inventario/api.php?action=precios_unidad_listar&producto_id=${producto.id}`)
        .then(r => r.json())
        .then(data => {
            if (!data || !data.length) return;
            puWrap.style.display = '';
            document.getElementById('pv-precios-unidad').innerHTML = data.map(p => `
                <div style="display:flex;gap:6px;padding:3px 0">
                    <span style="font-weight:400;font-size:.85rem">${p.unidad_medida}${p.abreviacion ? ' (' + p.abreviacion + ')' : ''} x${p.cantidad}:</span>
                    <span style="font-weight:400;font-size:.85rem">S/ ${parseFloat(p.precio_venta).toFixed(2)}</span>
                </div>`).join('');
        })
        .catch(() => {});

    switchTab('producto-vista');
}

// ---- Precios por unidad de medida ----
function cargarUnidadesMedidaCatalogo() {
    return fetch(BASE + 'modules/inventario/api.php?action=unidades_medida_listar')
        .then(r => r.json())
        .then(data => {
            unidadesMedidaCatalogo = data || [];
            const select = document.getElementById('pu-unidad-select');
            select.innerHTML = '<option value="">Seleccione</option>' +
                unidadesMedidaCatalogo.map(u => `<option value="${u.nombre}">${u.nombre}</option>`).join('');
        });
}

function toggleNuevaUnidadMedida() {
    const form = document.getElementById('nueva-unidad-medida-form');
    const abrir = form.style.display === 'none';
    form.style.display = abrir ? 'block' : 'none';
    if (abrir) {
        document.getElementById('nueva-unidad-medida-nombre').value = '';
        setTimeout(() => document.getElementById('nueva-unidad-medida-nombre').focus(), 50);
    }
}

function guardarNuevaUnidadMedida() {
    const nombre = document.getElementById('nueva-unidad-medida-nombre').value.trim();
    if (!nombre) { showToast('El nombre es requerido', 'error'); return; }

    fetch(BASE + 'modules/inventario/api.php?action=unidad_medida_crear', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nombre }),
    })
    .then(r => r.json())
    .then(d => {
        if (d.error) { showToast(d.message, 'error'); return; }
        toggleNuevaUnidadMedida();
        cargarUnidadesMedidaCatalogo().then(() => {
            document.getElementById('pu-unidad-select').value = d.nombre;
            agregarPrecioUnidad();
        });
        showToast('Unidad de medida creada', 'success');
    })
    .catch(() => showToast('Error al crear la unidad de medida', 'error'));
}

function agregarPrecioUnidad() {
    const select = document.getElementById('pu-unidad-select');
    const unidad = select.value;
    if (!unidad) return;

    if (preciosUnidadEditando.some(p => p.unidad_medida === unidad)) {
        showToast('Esa unidad de medida ya está configurada para este producto', 'error');
        select.value = '';
        return;
    }

    preciosUnidadEditando.push({ unidad_medida: unidad, abreviacion: '', cantidad: '', precio_venta: '' });
    renderPreciosUnidadEditor();
    select.value = '';
}

function eliminarPrecioUnidad(idx) {
    preciosUnidadEditando.splice(idx, 1);
    renderPreciosUnidadEditor();
}

function actualizarPrecioUnidad(idx, campo, valor) {
    preciosUnidadEditando[idx][campo] = valor;
}

function renderPreciosUnidadEditor() {
    const tbody = document.getElementById('precios-unidad-body');
    const empty = document.getElementById('precios-unidad-empty');
    tbody.querySelectorAll('.precio-unidad-row').forEach(r => r.remove());

    if (!preciosUnidadEditando.length) {
        empty.style.display = '';
        return;
    }
    empty.style.display = 'none';

    preciosUnidadEditando.forEach((p, idx) => {
        const tr = document.createElement('tr');
        tr.className = 'precio-unidad-row';
        tr.innerHTML = `
            <td style="font-weight:600">${p.unidad_medida}</td>
            <td>
                <input type="text" value="${p.abreviacion ?? ''}" placeholder="Ej: BLS"
                    style="width:100%;padding:6px 8px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:.85rem"
                    oninput="actualizarPrecioUnidad(${idx}, 'abreviacion', this.value)">
            </td>
            <td class="text-right">
                <input type="number" value="${p.cantidad ?? ''}" min="1" placeholder="0"
                    style="width:90px;text-align:right;padding:6px 8px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:.85rem"
                    oninput="actualizarPrecioUnidad(${idx}, 'cantidad', this.value)">
            </td>
            <td class="text-right">
                <input type="number" value="${p.precio_venta ?? ''}" min="0" step="0.01" placeholder="0.00"
                    style="width:100px;text-align:right;padding:6px 8px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:.85rem"
                    oninput="actualizarPrecioUnidad(${idx}, 'precio_venta', this.value)">
            </td>
            <td>
                <button type="button" onclick="eliminarPrecioUnidad(${idx})"
                    style="background:none;border:none;color:var(--danger);cursor:pointer;padding:4px 6px;font-size:.9rem">
                    <i class="fas fa-trash"></i>
                </button>
            </td>`;
        tbody.insertBefore(tr, empty);
    });
}

function openProductoModal(producto = null) {
    editingId = producto ? producto.id : null;

    if (editingId) {
        fetch(BASE + `modules/inventario/api.php?action=precios_unidad_listar&producto_id=${editingId}`)
            .then(r => r.json())
            .then(data => { preciosUnidadEditando = data || []; renderPreciosUnidadEditor(); })
            .catch(() => { preciosUnidadEditando = []; renderPreciosUnidadEditor(); });
    } else {
        preciosUnidadEditando = [];
        renderPreciosUnidadEditor();
    }

    // Recordar desde qué tab se abrió para poder volver
    if (document.getElementById('tab-producto-vista').style.display !== 'none') {
        tabAnterior = 'producto-vista';
    } else {
        tabAnterior = document.getElementById('tab-categorias').style.display !== 'none'
            ? 'categorias' : 'inventario';
    }

    const esNuevo = !editingId;
    const titulo  = 'Información de Producto';
    const icono   = esNuevo ? 'fa-plus' : 'fa-edit';

    document.getElementById('tab-producto-card-title').innerHTML =
        `<i class="fas ${icono}" style="color:var(--primary);margin-right:8px"></i>${titulo}`;
    document.getElementById('inv-page-title').innerHTML =
        `<i class="fas ${icono}" style="color:var(--primary);margin-right:8px"></i>${titulo}`;
    document.getElementById('inv-page-subtitle').textContent =
        esNuevo ? 'Completa los datos para registrar un nuevo producto' : 'Actualiza los datos del producto';

    const stockGroup = document.getElementById('stock-inicial-group');
    const afectacionCodigoProducto = producto?.afectacion_igv_codigo ?? '20';
    const afectacionProducto = facturacionCatalogos.afectaciones_igv.find(item => item.codigo === afectacionCodigoProducto);
    const productoEsGravado = !afectacionProducto || afectacionProducto.tipo === 'GRAV';
    const incluyeIgvGuardado = producto?.incluye_igv == 't' || producto?.incluye_igv === true;

    stockGroup.style.display = 'block';
    document.getElementById('p-stock').disabled = !!(editingId && !esAdmin);

    // Poblar campos
    document.getElementById('p-codigo').value        = producto?.codigo        ?? '';
    document.getElementById('p-sku').value           = producto?.codigo_interno ?? '';
    document.getElementById('p-nombre').value        = producto?.nombre        ?? '';
    document.getElementById('p-categoria').value     = producto?.categoria_id  ?? '';


    document.getElementById('p-presentacion').value  = producto?.presentacion  ?? '';
    quitarImagen();
    if (producto?.imagen_url) {
        document.getElementById('p-imagen-preview').src = producto.imagen_url;
        document.getElementById('p-imagen-preview').style.display = 'block';
        document.getElementById('p-imagen-placeholder').style.display = 'none';
        document.getElementById('p-imagen-quitar').style.display = 'flex';
    }
    document.getElementById('p-precio-compra').value = producto ? parseFloat(producto.precio_compra).toFixed(2) : '';
    document.getElementById('p-precio-venta').value  = producto ? parseFloat(producto.precio_venta).toFixed(2)  : '';
    document.getElementById('p-stock').value         = producto?.stock         ?? '';
    document.getElementById('p-stock-minimo').value  = producto?.stock_minimo  ?? '5';
    const unidadVal = producto?.unidad ?? 'unidad';
    if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.select2 !== 'undefined') {
        const $unidadSel = window.jQuery('#p-unidad');
        if ($unidadSel.find(`option[value="${unidadVal}"]`).length === 0) {
            $unidadSel.append(new Option(unidadVal, unidadVal, true, true));
        }
        $unidadSel.val(unidadVal).trigger('change.select2');
    } else {
        document.getElementById('p-unidad').value = unidadVal;
    }
    document.getElementById('p-afectacion-igv-codigo').value = producto?.afectacion_igv_codigo ?? (empresaIgvExonerado ? '20' : '10');
    document.getElementById('p-porcentaje-igv').value = producto ? parseFloat(producto.porcentaje_igv || 18).toFixed(2) : '18.00';
    document.getElementById('p-incluye-igv').checked = !producto
        ? empresaFacturaConIgv
        : (productoEsGravado ? (incluyeIgvGuardado || empresaFacturaConIgv) : false);
    document.getElementById('p-receta').checked           = producto?.requiere_receta == 't' || producto?.requiere_receta === true;
    document.getElementById('p-favorito').checked         = producto?.favorito == 't'        || producto?.favorito === true;
    document.getElementById('p-fecha-vencimiento').value  = producto?.fecha_vencimiento ?? '';

    if (!producto) {
        aplicarDefaultFacturacionProducto();
    } else {
        aplicarReglasIgvPorAfectacion();
    }

    if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.select2 !== 'undefined') {
        window.jQuery('#p-unidad').trigger('change.select2');
    }

    switchTab('producto');
    setTimeout(() => document.getElementById('p-codigo').focus(), 100);
}

function saveProducto() {
    const codigo      = document.getElementById('p-codigo').value.trim();
    const nombre      = document.getElementById('p-nombre').value.trim();
    const precio_venta = parseFloat(document.getElementById('p-precio-venta').value);
    const porcentajeIgvInput = parseFloat(document.getElementById('p-porcentaje-igv').value);
    const porcentajeIgv = isNaN(porcentajeIgvInput) ? 18 : porcentajeIgvInput;

    if (!codigo)                 { showToast('El código es requerido', 'error'); return; }
    if (!nombre)                 { showToast('El nombre es requerido', 'error'); return; }
    if (!precio_venta || precio_venta <= 0) { showToast('El precio de venta debe ser mayor a 0', 'error'); return; }

    const payload = {
        id:              editingId,
        codigo,
        sku:             document.getElementById('p-sku').value.trim() || null,
        nombre,
        categoria_id:    document.getElementById('p-categoria').value     || null,


        presentacion:    document.getElementById('p-presentacion').value,
        precio_compra:   parseFloat(document.getElementById('p-precio-compra').value) || 0,
        precio_venta,
        stock:           parseInt(document.getElementById('p-stock').value)        || 0,
        stock_minimo:    parseInt(document.getElementById('p-stock-minimo').value) || 5,
        unidad:          document.getElementById('p-unidad').value.trim() || 'unidad',
        afectacion_igv_codigo: document.getElementById('p-afectacion-igv-codigo').value || '20',
        porcentaje_igv:  porcentajeIgv,
        incluye_igv:     document.getElementById('p-incluye-igv').checked,
        requiere_receta:    document.getElementById('p-receta').checked,
        favorito:           document.getElementById('p-favorito').checked,
        fecha_vencimiento:  document.getElementById('p-fecha-vencimiento').value || null,
        precios_unidad: preciosUnidadEditando
            .filter(p => (p.unidad_medida || '').trim() && parseInt(p.cantidad) > 0 && parseFloat(p.precio_venta) > 0)
            .map(p => ({
                unidad_medida: p.unidad_medida.trim(),
                abreviacion: (p.abreviacion || '').trim(),
                cantidad: parseInt(p.cantidad),
                precio_venta: parseFloat(p.precio_venta),
            })),
    };

    const action = editingId ? 'actualizar' : 'crear';
    const btn    = document.getElementById('btn-guardar-producto');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    fetch(BASE + `modules/inventario/api.php?action=${action}`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Guardar';
        if (data.error) { showToast(data.message, 'error'); return; }
        showToast(data.message, 'success');
        switchTab('inventario');
        loadProductos();
        loadStats();
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Guardar';
        showToast('Error al guardar', 'error');
    });
}

// ---- Modal Ajuste de Stock ----
function setupTipoAjuste() {
    document.querySelectorAll('input[name="tipo-ajuste"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.getElementById('tipo-entrada-label').style.borderColor =
                document.getElementById('tipo-entrada').checked ? 'var(--success)' : 'var(--border)';
            document.getElementById('tipo-salida-label').style.borderColor =
                document.getElementById('tipo-salida').checked ? 'var(--danger)' : 'var(--border)';
        });
    });
}

function openAjusteModal(producto) {
    ajusteProducto = producto;
    document.getElementById('ajuste-nombre').textContent       = producto.nombre;
    document.getElementById('ajuste-stock-actual').textContent = producto.stock;
    document.getElementById('ajuste-cantidad').value           = '';
    document.getElementById('ajuste-motivo').value             = '';
    document.getElementById('tipo-entrada').checked            = true;
    document.getElementById('tipo-entrada-label').style.borderColor = 'var(--success)';
    document.getElementById('tipo-salida-label').style.borderColor  = 'var(--border)';
    openModal('modal-ajuste');
    setTimeout(() => document.getElementById('ajuste-cantidad').focus(), 100);
}

function saveAjuste() {
    const cantidad = parseInt(document.getElementById('ajuste-cantidad').value);
    const tipo     = document.querySelector('input[name="tipo-ajuste"]:checked').value;
    const motivo   = document.getElementById('ajuste-motivo').value.trim();

    if (!cantidad || cantidad <= 0) { showToast('Ingresa una cantidad válida', 'error'); return; }

    const btn = document.getElementById('btn-guardar-ajuste');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aplicando...';

    fetch(BASE + 'modules/inventario/api.php?action=ajustar_stock', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id: ajusteProducto.id, tipo, cantidad, motivo }),
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Aplicar Ajuste';
        if (data.error) { showToast(data.message, 'error'); return; }
        showToast(`Stock actualizado → ${data.nuevo_stock} unidades`, 'success');
        closeModal('modal-ajuste');
        loadProductos();
        loadStats();
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Aplicar Ajuste';
        showToast('Error al ajustar stock', 'error');
    });
}

// ---- Toggle activo ----
function toggleActivo(id) {
    fetch(BASE + 'modules/inventario/api.php?action=toggle_activo', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showToast(data.message, 'error'); return; }
        const estado = (data.activo === true || data.activo === 't') ? 'activado' : 'desactivado';
        showToast(`Producto ${estado}`, 'success');
        loadProductos();
        loadStats();
    })
    .catch(() => showToast('Error al cambiar estado', 'error'));
}

// ---- Eliminar / restaurar producto (papelera "Eliminados") ----
function confirmarEliminarProducto() {
    if (!productoEnVista) return;
    document.getElementById('confirmar-eliminar-producto-nombre').textContent = productoEnVista.nombre;
    openModal('modal-confirmar-eliminar-producto');
}

function eliminarProductoConfirmado() {
    if (!productoEnVista) return;
    const btn = document.getElementById('btn-confirmar-eliminar-producto');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';

    fetch(BASE + 'modules/inventario/api.php?action=eliminar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: productoEnVista.id }),
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash"></i> Eliminar';
        if (data.error) { showToast(data.message, 'error'); return; }
        closeModal('modal-confirmar-eliminar-producto');
        showToast(data.message, 'success');
        switchTab('inventario');
        loadProductos();
        loadStats();
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash"></i> Eliminar';
        showToast('Error al eliminar el producto', 'error');
    });
}

function cargarEliminados() {
    document.getElementById('eliminados-tabla-body').innerHTML =
        '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';

    fetch(BASE + 'modules/inventario/api.php?action=eliminados_listar', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            if (!Array.isArray(data)) throw new Error('unexpected');
            document.getElementById('eliminados-count').textContent = data.length + ' producto(s)';

            if (!data.length) {
                document.getElementById('eliminados-tabla-body').innerHTML =
                    `<tr><td colspan="8"><div class="empty-state"><i class="fas fa-trash"></i>No hay productos eliminados</div></td></tr>`;
                return;
            }

            document.getElementById('eliminados-tabla-body').innerHTML = data.map(p => {
                const fecha = p.eliminado_at ? new Date(p.eliminado_at).toLocaleDateString('es-PE') : '—';
                const dias  = parseInt(p.dias_restantes);
                const diasCls = dias <= 3 ? 'color:var(--danger);font-weight:700' : 'color:var(--text-muted)';
                return `<tr style="font-size:14px">
                    <td style="font-family:monospace;color:var(--text-muted)">${p.codigo_interno || p.codigo || '—'}</td>
                    <td style="font-weight:500">${p.nombre}</td>
                    <td>${p.categoria || '<span style="color:var(--text-light)">—</span>'}</td>
                    <td style="text-align:center">${p.stock}</td>
                    <td>${p.eliminado_por_nombre || '<span style="color:var(--text-light)">—</span>'}</td>
                    <td>${fecha}</td>
                    <td style="text-align:center;${diasCls}">${dias} día${dias === 1 ? '' : 's'}</td>
                    <td>
                        <button class="btn btn-outline btn-sm" onclick="restaurarProducto(${p.id}, this)">
                            <i class="fas fa-rotate-left"></i> Restaurar
                        </button>
                    </td>
                </tr>`;
            }).join('');
        })
        .catch(() => {
            document.getElementById('eliminados-tabla-body').innerHTML =
                '<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-exclamation-circle" style="font-size:1.3rem;color:var(--danger)"></i><br><br>Error al cargar los productos eliminados.</td></tr>';
        });
}

function restaurarProducto(id, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch(BASE + 'modules/inventario/api.php?action=restaurar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-rotate-left"></i> Restaurar';
            showToast(data.message, 'error');
            return;
        }
        showToast(data.message, 'success');
        cargarEliminados();
        loadStats();
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-rotate-left"></i> Restaurar';
        showToast('Error al restaurar el producto', 'error');
    });
}

// ---- Toma de Inventario ----
function formatearTiempoRelativo(isoTimestamp) {
    if (!isoTimestamp) return '—';
    const diffMin = Math.floor((Date.now() - new Date(isoTimestamp).getTime()) / 60000);
    if (diffMin < 1)  return 'hace un momento';
    if (diffMin < 60) return `hace ${diffMin} min`;
    const diffH = Math.floor(diffMin / 60);
    if (diffH < 24)   return `hace ${diffH} h`;
    return `hace ${Math.floor(diffH / 24)} d`;
}

// Columna "Actualizado": tiempo relativo (estilo WhatsApp) desde que se
// dio clic en "Aplicar", hasta 3 dias. Desde el 4to dia muestra la fecha
// exacta en vez de "hace N d".
function formatearActualizadoToma(isoTimestamp) {
    if (!isoTimestamp) return '—';
    const fecha = new Date(isoTimestamp);
    const diffMin = Math.floor((Date.now() - fecha.getTime()) / 60000);
    if (diffMin < 1)  return 'hace un momento';
    if (diffMin < 60) return `hace ${diffMin} min`;
    const diffH = Math.floor(diffMin / 60);
    if (diffH < 24)   return `hace ${diffH} h`;
    const diffD = Math.floor(diffH / 24);
    if (diffD < 4)    return `hace ${diffD} día${diffD === 1 ? '' : 's'}`;
    return fecha.toLocaleDateString('es-PE');
}

function badgeEstadoToma(sesion) {
    if (sesion.estado === 'completada') return '<span class="badge" style="background:var(--success);color:#fff">Completada</span>';
    if (sesion.estado === 'cancelada')  return '<span class="badge" style="background:var(--text-light);color:#fff">Cancelada</span>';
    return '<span class="badge" style="background:var(--primary);color:#fff">Activa</span>';
}

function cargarTomaSesiones() {
    document.getElementById('toma-tabla-body').innerHTML =
        '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    fetch(BASE + 'modules/inventario/api.php?action=toma_listar', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            tomaSesiones = Array.isArray(data) ? data : [];
            document.getElementById('toma-count').textContent = tomaSesiones.length + ' sesión(es)';
            renderTomaSesiones();
        })
        .catch(() => showToast('Error al cargar las sesiones', 'error'));
}

function renderTomaSesiones() {
    const tbody = document.getElementById('toma-tabla-body');
    if (!tomaSesiones.length) {
        tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state"><i class="fas fa-clipboard-check"></i>No hay sesiones de toma de inventario</div></td></tr>`;
        return;
    }
    tbody.innerHTML = tomaSesiones.map(s => {
        const stockInicial = parseFloat(s.stock_inicial) || 0;
        const stockActual  = parseFloat(s.stock_actual) || 0;
        const vsColor = stockActual === stockInicial ? 'var(--text-muted)' : (stockActual < stockInicial ? 'var(--danger)' : 'var(--success)');
        return `
        <tr>
            <td style="font-family:monospace">${s.codigo}</td>
            <td>${s.nombre || '<span style="color:var(--text-light)">—</span>'}</td>
            <td style="font-size:.85rem">${s.categorias_nombres || '<span style="color:var(--text-light)">—</span>'}</td>
            <td style="text-align:center">${s.total_contados}/${s.total_productos}</td>
            <td style="text-align:center;font-size:.85rem">
                ${stockInicial.toFixed(2)} <i class="fas fa-arrow-right" style="color:var(--text-light);font-size:.75rem"></i> <span style="font-weight:600;color:${vsColor}">${stockActual.toFixed(2)}</span>
            </td>
            <td>${badgeEstadoToma(s)}</td>
            <td>
                <button class="btn btn-outline btn-sm" onclick="abrirTomaDetalle(${s.id})">
                    <i class="fas fa-eye"></i> Ver
                </button>
            </td>
        </tr>`;
    }).join('');
}

function parsePgIntArray(raw) {
    if (Array.isArray(raw)) return raw.map(Number);
    if (typeof raw !== 'string') return [];
    return raw.replace(/[{}]/g, '').split(',').filter(Boolean).map(Number);
}

function abrirNuevaTomaModal() {
    document.getElementById('nt-nombre').value = '';
    document.getElementById('nt-todas').checked = false;

    const categoriasAbiertas = new Set();
    tomaSesiones.forEach(s => {
        if (s.estado === 'activa') {
            parsePgIntArray(s.categorias_ids).forEach(id => categoriasAbiertas.add(id));
        }
    });

    document.getElementById('nt-categorias-list').innerHTML = categorias.map(c => {
        const abierta = categoriasAbiertas.has(parseInt(c.id));
        return `
        <label style="display:flex;align-items:center;gap:8px;padding:4px 0;font-size:.88rem">
            <input type="checkbox" class="nt-cat-check" value="${c.id}" ${abierta ? 'checked' : ''}>
            ${c.nombre} <span style="color:var(--text-muted)">(${c.total_productos})</span>
            ${abierta ? '<span style="color:var(--primary);font-size:.75rem;font-weight:600"> · ya en sesión activa</span>' : ''}
        </label>`;
    }).join('');
    openModal('modal-nueva-toma');
}

function toggleTodasCategorias(checked) {
    document.querySelectorAll('.nt-cat-check').forEach(chk => { chk.checked = checked; });
}

function guardarNuevaTomaModal() {
    const categoriaIds = Array.from(document.querySelectorAll('.nt-cat-check:checked')).map(chk => parseInt(chk.value));
    const nombre = document.getElementById('nt-nombre').value.trim();

    if (!categoriaIds.length) { showToast('Selecciona al menos una categoría', 'error'); return; }

    const btn = document.getElementById('btn-guardar-nueva-toma');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando...';

    fetch(BASE + 'modules/inventario/api.php?action=toma_crear', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ categorias_ids: categoriaIds, nombre }),
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Crear sesión';
        if (data.error) { showToast(data.message, 'error'); return; }
        closeModal('modal-nueva-toma');
        showToast(`Sesión ${data.codigo} creada con ${data.total_productos} producto(s)`, 'success');
        abrirTomaDetalle(data.id);
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Crear sesión';
        showToast('Error al crear la sesión', 'error');
    });
}

function abrirTomaDetalle(id) {
    fetch(BASE + `modules/inventario/api.php?action=toma_detalle&id=${id}`, { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            if (data.error) { showToast(data.message, 'error'); return; }
            tomaSesionActual = data;
            tomaDetallesActuales = data.detalles || [];
            document.getElementById('toma-detalle-q').value = '';
            document.getElementById('toma-detalle-solo-pendientes').checked = false;
            switchTab('toma-detalle');
            renderTomaDetalleHeader();
            renderTomaDetalleTabla();
            // Se refresca siempre (activa o cerrada) -- "Actualizado" (columna
            // de aplicado_en) es relevante sin importar el estado de la sesion.
            iniciarRefrescoBadgesToma();
        })
        .catch(() => showToast('Error al cargar el detalle de la sesión', 'error'));
}

function renderTomaDetalleHeader() {
    const s = tomaSesionActual;
    document.getElementById('toma-detalle-codigo').innerHTML =
        `${s.codigo}${s.nombre ? ' — ' + s.nombre : ''} ${badgeEstadoToma(s)}`;
    document.getElementById('toma-detalle-subinfo').textContent =
        `Contados: ${s.total_contados}/${s.total_productos}`;

    const acciones = document.getElementById('toma-detalle-acciones');
    if (esAdmin && s.estado === 'activa') {
        acciones.innerHTML = `
            <button class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger)" onclick="cancelarToma()"><i class="fas fa-ban"></i> Cancelar</button>
            <button class="btn btn-primary btn-sm" onclick="openModal('modal-confirmar-aplicar-toma')"><i class="fas fa-check-double"></i> Cerrar sesión</button>
        `;
    } else {
        acciones.innerHTML = '';
    }
}

function tomaDetalleFiltrados() {
    const q = document.getElementById('toma-detalle-q').value.trim().toLowerCase();
    const soloPendientes = document.getElementById('toma-detalle-solo-pendientes').checked;
    return tomaDetallesActuales.filter(d => {
        if (soloPendientes && d.cantidad_contada !== null) return false;
        if (!q) return true;
        return d.producto_nombre.toLowerCase().includes(q) || d.producto_codigo.toLowerCase().includes(q);
    });
}

function renderTomaDetalleTabla() {
    const tbody = document.getElementById('toma-detalle-body');
    const filas = tomaDetalleFiltrados();
    const sesionActiva = tomaSesionActual.estado === 'activa';
    const editable = esAdmin && sesionActiva;

    if (!filas.length) {
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-light)">Sin resultados</td></tr>`;
        return;
    }

    tbody.innerHTML = filas.map(d => {
        const aplicado = d.aplicado === true || d.aplicado === 't';
        const contado  = d.cantidad_contada !== null;

        const stockVal = parseFloat(d.stock_sistema);

        let estado;
        if (!d.producto_id) {
            estado = '<span class="text-muted">Producto eliminado</span>';
        } else if (aplicado) {
            estado = '<span style="color:var(--success);font-weight:600"><i class="fas fa-check-circle"></i> Aplicado</span>';
        } else if (!contado) {
            estado = '<span style="color:var(--text-muted)">Sin contar</span>';
        } else if (sesionActiva) {
            estado = `<span data-contado-en="${d.contado_en}" class="toma-badge-relativo">${formatearTiempoRelativo(d.contado_en)}</span>`;
        } else {
            // Contado pero nunca aplicado, y la sesion ya no esta activa:
            // no se puede volver a aplicar desde aqui (requiere Ajuste de
            // Stock manual). Debe quedar claro que el stock NO se actualizo.
            estado = '<span style="color:#f59e0b;font-weight:600"><i class="fas fa-triangle-exclamation"></i> Contado, sin aplicar</span>';
        }

        const puedeAccionar = editable && d.producto_id && contado && !aplicado;
        const puedeEditar   = editable && d.producto_id && aplicado;
        const acciones = aplicado ? (puedeEditar ? `
            <button type="button" title="Corregir stock físico" onclick="editarProductoAplicadoToma(${d.id})"
                style="background:none;border:none;color:var(--primary);cursor:pointer;padding:4px 6px;font-size:1rem">
                <i class="fas fa-pen"></i>
            </button>` : '') : `
            <button type="button" id="toma-btn-aplicar-${d.id}" title="Aplicar al stock" ${puedeAccionar ? '' : 'disabled'} onclick="aplicarProductoToma(${d.id})"
                style="display:inline-flex;align-items:center;gap:5px;background:none;border:1px solid ${puedeAccionar ? 'var(--success)' : 'var(--border)'};border-radius:var(--radius-sm);color:${puedeAccionar ? 'var(--success)' : 'var(--text-light)'};cursor:${puedeAccionar ? 'pointer' : 'not-allowed'};padding:4px 10px;font-size:.82rem;font-weight:600">
                <i class="fas fa-check"></i> Aplicar
            </button>`;

        return `
        <tr data-detalle-id="${d.id}">
            <td style="font-family:monospace;color:var(--text-muted)">${d.producto_codigo}</td>
            <td>${d.producto_nombre}</td>
            <td>${d.categoria_nombre || '—'}</td>
            <td>${d.unidad || 'unidad'}</td>
            <td class="text-right" style="font-weight:600;color:var(--text-muted)">${stockVal.toFixed(2)}</td>
            <td class="text-right">
                <input type="text" inputmode="numeric"
                    value="${d.cantidad_contada === null ? '' : parseFloat(d.cantidad_contada)}"
                    ${(editable && d.producto_id && !aplicado) ? '' : 'disabled'}
                    data-detalle-id="${d.id}"
                    style="width:90px;text-align:center;padding:5px 10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:.88rem"
                    onblur="guardarConteoProducto(${d.id}, this.value, this)"
                    oninput="toggleBotonAplicarToma(${d.id}, sanitizarConteoInput(this))"
                    onfocus="cursorAlFinal(this)"
                    onkeydown="if(event.key==='Enter'){this.blur();}">
            </td>
            <td>${estado}</td>
            <td><span data-aplicado-en="${d.aplicado_en || ''}" class="toma-badge-aplicado">${formatearActualizadoToma(d.aplicado_en)}</span></td>
            <td style="white-space:nowrap">${acciones}</td>
        </tr>`;
    }).join('');
}

// Deja solo digitos (sin punto decimal, este sistema no cuenta
// fracciones aqui) y refleja el valor limpio en el input -- type="text"
// en vez de "number" porque Chrome no permite mover el cursor
// programaticamente en inputs type="number".
function sanitizarConteoInput(el) {
    const v = el.value.replace(/[^0-9]/g, '');
    el.value = v;
    return v;
}

function cursorAlFinal(el) {
    // El navegador posiciona el cursor segun el clic DESPUES de disparar
    // "focus" -- si no se difiere, esa posicion pisa la nuestra.
    setTimeout(() => {
        const pos = el.value.length;
        el.setSelectionRange(pos, pos);
    }, 0);
}

function toggleBotonAplicarToma(detalleId, valor) {
    // Habilita/deshabilita "Aplicar" en tiempo real segun lo que hay
    // escrito en el input, sin esperar a que el blur/guardado en el
    // servidor termine -- antes el boton solo se habilitaba despues de
    // ese guardado, y el primer clic (con el boton aun deshabilitado)
    // no hacia nada, dando la sensacion de que hacia falta doble clic.
    const btn = document.getElementById(`toma-btn-aplicar-${detalleId}`);
    if (!btn) return;
    const habilitar = valor !== '' && valor !== null;
    btn.disabled = !habilitar;
    btn.style.borderColor = habilitar ? 'var(--success)' : 'var(--border)';
    btn.style.color = habilitar ? 'var(--success)' : 'var(--text-light)';
    btn.style.cursor = habilitar ? 'pointer' : 'not-allowed';
}

function aplicarProductoToma(detalleId) {
    // Si el input todavia tiene un valor sin guardar (el usuario escribio y
    // dio clic directo en "Aplicar", sin que el blur alcanzara a terminar
    // su guardado), se guarda primero y se espera esa respuesta antes de
    // aplicar -- evita aplicar contra un conteo viejo/vacio en el servidor.
    const input = document.querySelector(`input[data-detalle-id="${detalleId}"]`);
    const guardarPrimero = input ? guardarConteoProducto(detalleId, input.value, input) : Promise.resolve({ error: false });

    guardarPrimero.then(resultadoGuardado => {
        if (resultadoGuardado && resultadoGuardado.error) return; // ya se mostro el toast de error

        fetch(BASE + 'modules/inventario/api.php?action=toma_aplicar_producto', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ detalle_id: detalleId }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) { showToast(data.message, 'error'); return; }
            const detalle = tomaDetallesActuales.find(d => d.id === detalleId);
            if (detalle) detalle.aplicado = true;
            showToast('Producto aplicado al stock', 'success');
            renderTomaDetalleTabla();
        })
        .catch(() => showToast('Error al aplicar el producto', 'error'));
    });
}

let tomaDetalleIdEditarPendiente = null;

function editarProductoAplicadoToma(detalleId) {
    tomaDetalleIdEditarPendiente = detalleId;
    openModal('modal-confirmar-editar-toma');
}

function confirmarEditarProductoToma() {
    if (!tomaDetalleIdEditarPendiente) return;
    const detalleId = tomaDetalleIdEditarPendiente;

    const btn = document.getElementById('btn-confirmar-editar-toma');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Corrigiendo...';

    fetch(BASE + 'modules/inventario/api.php?action=toma_reabrir_producto', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ detalle_id: detalleId }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showToast(data.message, 'error'); return; }
        const detalle = tomaDetallesActuales.find(d => d.id === detalleId);
        if (detalle) detalle.aplicado = false;
        showToast('Producto reabierto. Corrige el stock físico y vuelve a aplicar.', 'success');
        closeModal('modal-confirmar-editar-toma');
        renderTomaDetalleTabla();
    })
    .catch(() => showToast('Error al reabrir el producto', 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        tomaDetalleIdEditarPendiente = null;
    });
}

function guardarConteoProducto(detalleId, valor, inputEl) {
    const detalle = tomaDetallesActuales.find(d => d.id === detalleId);
    if (!detalle) return Promise.resolve({ error: false });

    const cantidad = valor === '' ? null : parseFloat(valor);
    const valorAnterior = detalle.cantidad_contada === null ? null : parseFloat(detalle.cantidad_contada);
    if (cantidad === valorAnterior) return Promise.resolve({ error: false }); // no cambió, no llama a la API

    return fetch(BASE + 'modules/inventario/api.php?action=toma_guardar_conteo', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ detalle_id: detalleId, cantidad }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showToast(data.message, 'error'); return data; }
        detalle.cantidad_contada = data.cantidad_contada;
        detalle.diferencia = data.diferencia;
        detalle.contado_en = data.contado_en;
        if (data.cantidad_contada !== null) {
            tomaSesionActual.total_contados = tomaDetallesActuales.filter(d => d.cantidad_contada !== null).length;
        }
        renderTomaDetalleHeader();
        renderTomaDetalleTabla();
        return data;
    })
    .catch(() => {
        showToast('Error al guardar el conteo', 'error');
        return { error: true };
    });
}

function cancelarToma() {
    if (!confirm('¿Cancelar esta toma de inventario? No se aplicará ningún cambio de stock.')) return;

    fetch(BASE + 'modules/inventario/api.php?action=toma_cancelar', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id: tomaSesionActual.id }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showToast(data.message, 'error'); return; }
        showToast('Sesión cancelada', 'success');
        abrirTomaDetalle(tomaSesionActual.id);
    })
    .catch(() => showToast('Error al cancelar la sesión', 'error'));
}

function confirmarAplicarToma() {
    const btn = document.getElementById('btn-confirmar-aplicar-toma');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aplicando...';

    fetch(BASE + 'modules/inventario/api.php?action=toma_aplicar', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id: tomaSesionActual.id }),
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Cerrar sesión';
        closeModal('modal-confirmar-aplicar-toma');
        if (data.error) { showToast(data.message, 'error'); return; }
        showToast(`Sesión cerrada: ${data.aplicados} producto(s) aplicado(s), ${data.sin_contar} sin contar`, 'success');
        abrirTomaDetalle(tomaSesionActual.id);
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Cerrar sesión';
        closeModal('modal-confirmar-aplicar-toma');
        showToast('Error al cerrar la sesión', 'error');
    });
}

function iniciarRefrescoBadgesToma() {
    detenerRefrescoBadgesToma();
    tomaRefreshInterval = setInterval(() => {
        document.querySelectorAll('.toma-badge-relativo').forEach(el => {
            el.textContent = formatearTiempoRelativo(el.getAttribute('data-contado-en'));
        });
        document.querySelectorAll('.toma-badge-aplicado').forEach(el => {
            el.textContent = formatearActualizadoToma(el.getAttribute('data-aplicado-en') || null);
        });
    }, 30000);
}

function detenerRefrescoBadgesToma() {
    if (tomaRefreshInterval) { clearInterval(tomaRefreshInterval); tomaRefreshInterval = null; }
}

function setupTomaDetalleFiltros() {
    let timer;
    document.getElementById('toma-detalle-q').addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(renderTomaDetalleTabla, 150);
    });
    document.getElementById('toma-detalle-solo-pendientes').addEventListener('change', renderTomaDetalleTabla);
}

// ---- Categorías (tab) ----
function abrirGestionCategorias() {
    switchTab('categorias');
}

function setupCatSearch() {
    let timer;
    document.getElementById('cat-q').addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(renderCatTabla, 150);
    });
}

function renderCatTabla() {
    const q     = document.getElementById('cat-q').value.toLowerCase();
    const lista = categorias.filter(c => !q || c.nombre.toLowerCase().includes(q));
    const tbody = document.getElementById('cat-tabla-body');
    document.getElementById('cat-count').textContent = lista.length + ' categoría(s)';

    if (!lista.length) {
        tbody.innerHTML = `<tr><td colspan="3"><div class="empty-state"><i class="fas fa-tags"></i>${q ? 'Sin resultados para "' + q + '"' : 'Sin categorías aún'}</div></td></tr>`;
        return;
    }

    tbody.innerHTML = lista.map(c => `
        <tr id="gcat-row-${c.id}">
            <td>
                <span class="gcat-label" style="font-size:14px">${c.nombre}</span>
                <input class="form-control gcat-input" style="display:none;max-width:320px;font-size:14px" value="${c.nombre}"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();confirmarEdicion(${c.id});}
                                  if(event.key==='Escape'){cancelarEdicion(${c.id});}">
            </td>
            <td style="text-align:center">
                <span style="font-weight:600;color:${parseInt(c.total_productos) > 0 ? 'var(--primary)' : 'var(--text-muted)'}">
                    ${c.total_productos}
                </span>
            </td>
            <td>
                <div class="gcat-actions" style="display:flex;gap:4px">
                    <button class="btn btn-ghost btn-sm" title="Editar" onclick="iniciarEdicion(${c.id})">
                        <i class="fas fa-pencil-alt" style="color:var(--primary)"></i>
                    </button>
                    <button class="btn btn-ghost btn-sm" title="Eliminar" onclick="eliminarCategoria(${c.id},'${c.nombre.replace(/'/g,"\\'")}')">
                        <i class="fas fa-trash" style="color:var(--danger)"></i>
                    </button>
                </div>
                <div class="gcat-edit-actions" style="display:none;gap:4px">
                    <button class="btn btn-primary btn-sm" onclick="confirmarEdicion(${c.id})">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="btn btn-ghost btn-sm" onclick="cancelarEdicion(${c.id})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function abrirNuevaCategoria() {
    document.getElementById('nueva-cat-modal-nombre').value = '';
    openModal('modal-nueva-categoria');
    setTimeout(() => document.getElementById('nueva-cat-modal-nombre').focus(), 80);
}

function guardarNuevaCategoriaModal() {
    const nombre = document.getElementById('nueva-cat-modal-nombre').value.trim();
    if (!nombre) { showToast('Escribe un nombre para la categoría', 'error'); return; }

    const btn = document.getElementById('btn-guardar-nueva-cat');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    fetch(BASE + 'modules/inventario/api.php?action=crear_categoria', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nombre }),
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Guardar';
        if (data.error) { showToast(data.message, 'error'); return; }
        categorias.push({ id: data.id, nombre: data.nombre });
        categorias.sort((a, b) => a.nombre.localeCompare(b.nombre));
        actualizarSelectoresCategorias();
        renderCatTabla();
        closeModal('modal-nueva-categoria');
        showToast('Categoría "' + data.nombre + '" creada', 'success');
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Guardar';
        showToast('Error al crear categoría', 'error');
    });
}

function iniciarEdicion(id) {
    const row = document.getElementById('gcat-row-' + id);
    row.querySelector('.gcat-label').style.display        = 'none';
    row.querySelector('.gcat-input').style.display        = 'inline-block';
    row.querySelector('.gcat-actions').style.display      = 'none';
    row.querySelector('.gcat-edit-actions').style.display = 'flex';
    row.querySelector('.gcat-input').focus();
    row.querySelector('.gcat-input').select();
}

function cancelarEdicion(id) {
    const row = document.getElementById('gcat-row-' + id);
    const cat = categorias.find(c => c.id == id);
    row.querySelector('.gcat-input').value                = cat.nombre;
    row.querySelector('.gcat-label').style.display        = '';
    row.querySelector('.gcat-input').style.display        = 'none';
    row.querySelector('.gcat-actions').style.display      = 'flex';
    row.querySelector('.gcat-edit-actions').style.display = 'none';
}

function confirmarEdicion(id) {
    const row    = document.getElementById('gcat-row-' + id);
    const nombre = row.querySelector('.gcat-input').value.trim();
    if (!nombre) { showToast('El nombre no puede estar vacío', 'error'); return; }

    fetch(BASE + 'modules/inventario/api.php?action=editar_categoria', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, nombre }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showToast(data.message, 'error'); return; }
        const cat = categorias.find(c => c.id == id);
        cat.nombre = nombre;
        actualizarSelectoresCategorias();
        renderCatTabla();
        showToast('Categoría actualizada', 'success');
    })
    .catch(() => showToast('Error al editar', 'error'));
}

let _eliminarCatId = null;

function eliminarCategoria(id, nombre) {
    _eliminarCatId = id;
    document.getElementById('confirmar-eliminar-nombre').textContent = nombre;
    openModal('modal-confirmar-eliminar');
}

function confirmarEliminarCategoria() {
    if (!_eliminarCatId) return;
    const btn = document.getElementById('btn-confirmar-eliminar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';

    fetch(BASE + 'modules/inventario/api.php?action=eliminar_categoria', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: _eliminarCatId }),
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash"></i> Eliminar';
        if (data.error) { showToast(data.message, 'error'); return; }
        categorias = categorias.filter(c => c.id != _eliminarCatId);
        _eliminarCatId = null;
        actualizarSelectoresCategorias();
        renderCatTabla();
        closeModal('modal-confirmar-eliminar');
        showToast('Categoría eliminada', 'success');
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash"></i> Eliminar';
        showToast('Error al eliminar', 'error');
    });
}

function actualizarSelectoresCategorias() {
    const selProd = document.getElementById('p-categoria');
    const valActual = selProd.value;
    selProd.innerHTML = '<option value="">Sin categoría</option>';
    categorias.forEach(c => selProd.add(new Option(c.nombre, c.id)));
    selProd.value = valActual;

    const selFiltro = document.getElementById('f-categoria');
    const valFiltro = selFiltro.value;
    selFiltro.innerHTML = '<option value="0">Todas</option>';
    categorias.forEach(c => selFiltro.add(new Option(c.nombre, c.id)));
    selFiltro.value = valFiltro;
}

// ---- Nueva unidad de medida inline ----
function toggleNuevaUnidad() {
    const form = document.getElementById('nueva-unidad-form');
    const visible = form.style.display !== 'none';
    form.style.display = visible ? 'none' : 'flex';
    form.style.flexDirection = 'column';
    if (!visible) {
        document.getElementById('nueva-unidad-nombre').value = '';
        document.getElementById('nueva-unidad-nombre').focus();
    }
}

function guardarNuevaUnidad() {
    const nombre = document.getElementById('nueva-unidad-nombre').value.trim();
    if (!nombre) { showToast('Escribe el nombre de la unidad', 'error'); return; }

    if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.select2 !== 'undefined') {
        const $sel = window.jQuery('#p-unidad');
        if ($sel.find(`option[value="${nombre}"]`).length === 0) {
            $sel.append(new Option(nombre, nombre, true, true));
        }
        $sel.val(nombre).trigger('change.select2');
    } else {
        document.getElementById('p-unidad').value = nombre;
    }

    toggleNuevaUnidad();
    showToast(`Unidad "${nombre}" agregada`, 'success');
}

// ---- Nueva categoría inline ----
function toggleNuevaCategoria() {
    const form = document.getElementById('nueva-cat-form');
    const visible = form.style.display !== 'none';
    form.style.display = visible ? 'none' : 'flex';
    form.style.flexDirection = 'column';
    if (!visible) {
        document.getElementById('nueva-cat-nombre').value = '';
        document.getElementById('nueva-cat-nombre').focus();
    }
}

function guardarCategoria() {
    const nombre = document.getElementById('nueva-cat-nombre').value.trim();
    if (!nombre) { showToast('Escribe un nombre para la categoría', 'error'); return; }

    fetch(BASE + 'modules/inventario/api.php?action=crear_categoria', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ nombre }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { showToast(data.message, 'error'); return; }

        categorias.push({ id: data.id, nombre: data.nombre });
        categorias.sort((a, b) => a.nombre.localeCompare(b.nombre));
        actualizarSelectoresCategorias();
        document.getElementById('p-categoria').value = data.id;
        toggleNuevaCategoria();
        showToast('Categoría "' + data.nombre + '" creada', 'success');
    })
    .catch(() => showToast('Error al crear categoría', 'error'));
}

// ---- Imagen de producto ----
function handleImagenSelect(input) {
    if (input.files && input.files[0]) mostrarImagenPreview(input.files[0]);
}

function handleImagenDrop(event) {
    event.preventDefault();
    document.getElementById('p-imagen-zona').style.borderColor = 'var(--border)';
    const file = event.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) mostrarImagenPreview(file);
}

function mostrarImagenPreview(file) {
    const reader = new FileReader();
    reader.onload = (e) => {
        document.getElementById('p-imagen-preview').src = e.target.result;
        document.getElementById('p-imagen-preview').style.display = 'block';
        document.getElementById('p-imagen-placeholder').style.display = 'none';
        document.getElementById('p-imagen-quitar').style.display = 'flex';
    };
    reader.readAsDataURL(file);
}

function quitarImagen() {
    document.getElementById('p-imagen-preview').src = '';
    document.getElementById('p-imagen-preview').style.display = 'none';
    document.getElementById('p-imagen-placeholder').style.display = '';
    document.getElementById('p-imagen-quitar').style.display = 'none';
    document.getElementById('p-imagen-input').value = '';
}

// ---- Lector de código de barras ----
function setupBarcodeScanner() {
    document.addEventListener('barcodescan', function (e) {
        const code           = e.detail.code.trim();
        const esTabProducto  = document.getElementById('tab-producto').style.display !== 'none';
        const modalAjuste    = document.getElementById('modal-ajuste').classList.contains('open');
        const esTomaDetalle  = document.getElementById('tab-toma-detalle').style.display !== 'none';

        if (esTomaDetalle) {
            buscarProductoEnTomaDetalle(code);
        } else if (esTabProducto) {
            const campo = document.getElementById('p-codigo');
            campo.value = code;
            campo.style.transition = 'background .2s';
            campo.style.background = 'rgba(var(--primary-rgb,79,70,229),.08)';
            setTimeout(() => { campo.style.background = ''; }, 800);
            document.getElementById('p-nombre').focus();
            showToast('<i class="fas fa-barcode"></i> Código escaneado: ' + code, 'success');
        } else if (!modalAjuste) {
            document.getElementById('f-q').value = code;
            loadProductos();
            showToast('<i class="fas fa-barcode"></i> Buscando: ' + code, 'info');
        }
    });
}

function buscarProductoEnTomaDetalle(code) {
    const upper = code.toUpperCase();
    const detalle = tomaDetallesActuales.find(d =>
        (d.codigo_barras && d.codigo_barras.toUpperCase() === upper) ||
        (d.producto_codigo && d.producto_codigo.toUpperCase() === upper)
    );

    if (!detalle) {
        showToast('Código <strong>' + code + '</strong> no encontrado en esta sesión', 'error');
        return;
    }

    document.getElementById('toma-detalle-q').value = '';
    document.getElementById('toma-detalle-solo-pendientes').checked = false;
    renderTomaDetalleTabla();

    const fila = document.querySelector(`#toma-detalle-body tr[data-detalle-id="${detalle.id}"]`);
    if (fila) {
        fila.classList.add('toma-fila-escaneada');
        fila.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    const aplicado = detalle.aplicado === true || detalle.aplicado === 't';
    if (aplicado) {
        showToast(detalle.producto_nombre + ' — ya fue aplicado, no se puede editar', 'info');
        return;
    }

    const input = document.querySelector(`input[data-detalle-id="${detalle.id}"]`);
    if (input) {
        input.focus();
        input.select();
    }
    showToast('<i class="fas fa-barcode"></i> ' + detalle.producto_nombre, 'success');
}

// ---- Helpers ----
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
