<?php
// ============================================================
// ARCHIVO: farmacia/modules/ventas/favoritos.php
// MÓDULO:  Ventas → Favoritos y Más Vendidos
// ============================================================

require_once '../../config/database.php';

$base_path      = '../../';
$current_module = 'ventas';
$current_page   = 'favoritos';
$page_title     = 'Favoritos y Más Vendidos — Gravity';
$breadcrumb     = '<strong>Ventas</strong> / Favoritos y Más Vendidos';

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-star" style="color:#F59E0B;margin-right:8px"></i>Favoritos y Más Vendidos</div>
        <div class="page-subtitle">Productos destacados y con mayor rotación en ventas</div>
    </div>
    <div class="page-actions">
        <a href="index.php" class="btn btn-primary btn-sm">
            <i class="fas fa-store"></i> Ir al POS
        </a>
    </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid var(--border);padding-bottom:0">
    <button class="tab-btn active" onclick="switchTab('tab-mas-vendidos',this)" style="padding:10px 20px;background:none;border:none;font-size:.875rem;font-weight:600;color:var(--primary);border-bottom:2px solid var(--primary);margin-bottom:-2px;cursor:pointer">
        <i class="fas fa-fire" style="margin-right:6px;color:#F59E0B"></i>Más Vendidos
    </button>
    <button class="tab-btn" onclick="switchTab('tab-favoritos',this)" style="padding:10px 20px;background:none;border:none;font-size:.875rem;font-weight:600;color:var(--text-muted);border-bottom:2px solid transparent;margin-bottom:-2px;cursor:pointer">
        <i class="fas fa-star" style="margin-right:6px"></i>Mis Favoritos
    </button>
</div>

<!-- TAB: Más Vendidos -->
<div id="tab-mas-vendidos">
    <div class="row g-4">
        <!-- Ranking Top 10 -->
        <div class="col-12 col-lg-6"><div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-trophy" style="color:#F59E0B;margin-right:7px"></i>Ranking Top 10</div>
                <select class="form-control" style="width:auto;font-size:.8rem" id="limite-top" onchange="loadTopVendidos()">
                    <option value="10">Top 10</option>
                    <option value="20">Top 20</option>
                    <option value="50">Top 50</option>
                </select>
            </div>
            <div id="top-list">
                <div style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></div>
            </div>
        </div></div>

        <!-- Stats generales -->
        <div class="col-12 col-lg-6"><div class="d-flex flex-column gap-4">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--primary);margin-right:7px"></i>Resumen General</div>
                </div>
                <div id="resumen-general">
                    <div style="text-align:center;padding:20px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-tags" style="color:var(--info);margin-right:7px"></i>Por Categoría</div>
                </div>
                <div id="cat-stats">
                    <div style="text-align:center;padding:20px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></div>
                </div>
            </div>
        </div></div>
    </div>

    <!-- Tabla completa -->
    <div class="card" style="margin-top:20px">
        <div class="card-header">
            <div class="card-title">Tabla completa de ventas por producto</div>
            <div class="input-group" style="width:240px">
                <span class="input-group-icon"><i class="fas fa-search"></i></span>
                <input type="text" id="search-top" class="form-control" placeholder="Buscar..." oninput="filterTop(this.value)">
            </div>
        </div>
        <div class="table-wrap table-responsive">
            <table id="tabla-top">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th class="text-right">Vendidos</th>
                        <th class="text-right">Precio</th>
                        <th class="text-right">Ingresos Est.</th>
                        <th>Favorito</th>
                    </tr>
                </thead>
                <tbody id="tabla-top-body">
                    <tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TAB: Favoritos -->
<div id="tab-favoritos" style="display:none">
    <div class="card" style="margin-bottom:20px;background:var(--warning-light);border-color:#FDE68A">
        <div style="display:flex;align-items:center;gap:12px">
            <i class="fas fa-info-circle" style="color:var(--warning);font-size:1.2rem"></i>
            <div style="font-size:.875rem">
                <strong>Productos Favoritos</strong> — Estos productos aparecen resaltados en el Punto de Venta con una estrella ⭐ para facilitar su búsqueda y selección rápida.
            </div>
        </div>
    </div>

    <div id="favoritos-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px">
        <div style="grid-column:1/-1;text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin" style="font-size:1.3rem"></i></div>
    </div>
</div>

<div class="app-toast-container" id="toast-container"></div>

<script>
const BASE = '../../';
let allTopData = [];

document.addEventListener('DOMContentLoaded', () => {
    loadTopVendidos();
    loadResumenGeneral();
    loadCatStats();
});

function switchTab(tabId, btn) {
    document.querySelectorAll('[id^="tab-"]').forEach(t => t.style.display = 'none');
    document.getElementById(tabId).style.display = 'block';
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.style.color = 'var(--text-muted)';
        b.style.borderBottomColor = 'transparent';
    });
    btn.style.color = 'var(--primary)';
    btn.style.borderBottomColor = 'var(--primary)';

    if (tabId === 'tab-favoritos') loadFavoritos();
}

function loadTopVendidos() {
    const limite = document.getElementById('limite-top').value;
    fetch(BASE + `modules/ventas/api.php?action=top_vendidos&limite=${limite}`)
        .then(r => r.json())
        .then(data => {
            allTopData = data;
            renderTopList(data.slice(0, 10));
            renderTablaTop(data);
        });
}

function renderTopList(data) {
    const el = document.getElementById('top-list');
    if (!data.length) { el.innerHTML = '<p style="text-align:center;color:var(--text-light);padding:20px">Sin datos</p>'; return; }
    const max = parseFloat(data[0].total_vendido) || 1;
    const rankClasses = ['gold','silver','bronze'];
    el.innerHTML = data.map((p, i) => `
        <div class="top-product-row">
            <div class="top-rank ${rankClasses[i]||''}">${i+1}</div>
            <div class="top-bar-wrap">
                <div class="top-bar-label">
                    <span style="font-weight:600;font-size:.83rem">${p.nombre}</span>
                    <span style="color:var(--text-muted);font-size:.78rem">${p.total_vendido} uds</span>
                </div>
                <div class="top-bar-track">
                    <div class="top-bar-fill" style="width:${Math.round(parseFloat(p.total_vendido)/max*100)}%"></div>
                </div>
            </div>
            <span style="font-size:.8rem;font-weight:700;color:var(--success);min-width:60px;text-align:right">
                S/ ${(parseFloat(p.precio_venta)*parseFloat(p.total_vendido)).toFixed(0)}
            </span>
        </div>`).join('');
}

function renderTablaTop(data) {
    if (!data.length) {
        document.getElementById('tabla-top-body').innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-light)">Sin datos</td></tr>';
        return;
    }
    document.getElementById('tabla-top-body').innerHTML = data.map((p, i) => `
        <tr data-name="${p.nombre.toLowerCase()}">
            <td style="font-weight:700;color:var(--text-muted)">${i+1}</td>
            <td>
                <div style="font-weight:600;font-size:.875rem">${p.nombre}</div>
                <div style="font-size:.75rem;color:var(--text-light)">${p.laboratorio||''}</div>
            </td>
            <td><span class="badge badge-gray">${p.categoria||'-'}</span></td>
            <td class="text-right"><strong>${p.total_vendido}</strong></td>
            <td class="text-right">S/ ${parseFloat(p.precio_venta).toFixed(2)}</td>
            <td class="text-right" style="color:var(--success);font-weight:600">
                S/ ${(parseFloat(p.precio_venta)*parseFloat(p.total_vendido)).toFixed(2)}
            </td>
            <td>
                <button onclick="toggleFav(${p.id},this)" class="btn btn-ghost btn-sm"
                    title="${p.favorito=='t'?'Quitar favorito':'Agregar favorito'}"
                    style="color:${p.favorito=='t'?'#F59E0B':'var(--text-light)'}">
                    <i class="fas fa-star"></i>
                </button>
            </td>
        </tr>`).join('');
}

function filterTop(q) {
    const rows = document.querySelectorAll('#tabla-top-body tr[data-name]');
    rows.forEach(row => {
        row.style.display = row.dataset.name.includes(q.toLowerCase()) ? '' : 'none';
    });
}

function loadResumenGeneral() {
    fetch(BASE + 'modules/ventas/api.php?action=top_vendidos&limite=1000')
        .then(r => r.json())
        .then(data => {
            const totalUds  = data.reduce((s,p) => s + parseFloat(p.total_vendido), 0);
            const totalIngs = data.reduce((s,p) => s + parseFloat(p.precio_venta)*parseFloat(p.total_vendido), 0);
            const conVentas = data.filter(p => parseFloat(p.total_vendido) > 0).length;
            document.getElementById('resumen-general').innerHTML = `
                <div class="row g-2 p-3">
                    <div class="col-6"><div style="background:var(--primary-light);border-radius:var(--radius-sm);padding:14px;text-align:center">
                        <div style="font-size:1.4rem;font-weight:800;color:var(--primary)">${totalUds.toLocaleString()}</div>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:3px">Unidades totales vendidas</div>
                    </div></div>
                    <div class="col-6"><div style="background:var(--success-light);border-radius:var(--radius-sm);padding:14px;text-align:center">
                        <div style="font-size:1.2rem;font-weight:800;color:var(--success)">S/ ${totalIngs.toFixed(0)}</div>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:3px">Ingresos estimados</div>
                    </div></div>
                    <div class="col-6"><div style="background:var(--warning-light);border-radius:var(--radius-sm);padding:14px;text-align:center">
                        <div style="font-size:1.4rem;font-weight:800;color:var(--warning)">${conVentas}</div>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:3px">Productos con ventas</div>
                    </div></div>
                    <div class="col-6"><div style="background:var(--info-light);border-radius:var(--radius-sm);padding:14px;text-align:center">
                        <div style="font-size:1.2rem;font-weight:800;color:var(--info)">${conVentas ? (totalUds/conVentas).toFixed(1) : 0}</div>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:3px">Promedio uds/producto</div>
                    </div></div>
                </div>`;
        });
}

function loadCatStats() {
    fetch(BASE + 'modules/ventas/api.php?action=top_vendidos&limite=1000')
        .then(r => r.json())
        .then(data => {
            const byCat = {};
            data.forEach(p => {
                const cat = p.categoria || 'Sin categoría';
                if (!byCat[cat]) byCat[cat] = 0;
                byCat[cat] += parseFloat(p.total_vendido);
            });
            const sorted = Object.entries(byCat).sort((a,b) => b[1]-a[1]);
            const maxVal = sorted[0]?.[1] || 1;
            document.getElementById('cat-stats').innerHTML = sorted.map(([cat,qty]) => `
                <div style="margin-bottom:10px">
                    <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:4px">
                        <span style="font-weight:600">${cat}</span>
                        <span style="color:var(--text-muted)">${qty} uds</span>
                    </div>
                    <div class="top-bar-track">
                        <div class="top-bar-fill" style="width:${Math.round(qty/maxVal*100)}%;background:var(--info)"></div>
                    </div>
                </div>`).join('');
        });
}

function loadFavoritos() {
    fetch(BASE + 'modules/ventas/api.php?action=favoritos')
        .then(r => r.json())
        .then(data => {
            const grid = document.getElementById('favoritos-grid');
            if (!data.length) {
                grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-light)"><i class="fas fa-star" style="font-size:2rem;margin-bottom:10px;display:block"></i>No tienes favoritos aún.<br><small>Márcalos desde la tabla de Más Vendidos.</small></div>';
                return;
            }
            grid.innerHTML = data.map(p => `
                <div class="card" style="border-left:3px solid #F59E0B">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start">
                        <div style="font-size:.875rem;font-weight:700;line-height:1.3;flex:1">${p.nombre}</div>
                        <button onclick="toggleFavCard(${p.id},this)" class="btn btn-ghost btn-sm" style="color:#F59E0B;padding:2px 4px">
                            <i class="fas fa-star"></i>
                        </button>
                    </div>
                    <div style="font-size:.75rem;color:var(--text-muted);margin-top:4px">${p.laboratorio||''}</div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                        <span style="font-size:1rem;font-weight:700;color:var(--primary)">S/ ${parseFloat(p.precio_venta).toFixed(2)}</span>
                        <span class="badge ${parseInt(p.stock) <= 0 ? 'badge-danger' : parseInt(p.stock) <= 5 ? 'badge-warning' : 'badge-success'}">
                            Stock: ${p.stock}
                        </span>
                    </div>
                    <div style="margin-top:8px;font-size:.75rem;color:var(--text-muted);background:var(--surface-2);padding:5px 8px;border-radius:var(--radius-sm)">
                        <i class="fas fa-fire" style="color:#F59E0B;margin-right:4px"></i>${p.total_vendido} unidades vendidas
                    </div>
                </div>`).join('');
        });
}

function toggleFav(id, btn) {
    fetch(BASE + 'modules/ventas/api.php?action=toggle_favorito', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(d => {
        if (d.error) return;
        const isFav = d.favorito === true || d.favorito === 't';
        btn.style.color = isFav ? '#F59E0B' : 'var(--text-light)';
        btn.title = isFav ? 'Quitar favorito' : 'Agregar favorito';
        showToast(isFav ? 'Agregado a favoritos ⭐' : 'Quitado de favoritos', 'success');
    });
}

function toggleFavCard(id, btn) {
    toggleFav(id, btn);
    setTimeout(loadFavoritos, 600);
}

function showToast(msg, type='info') {
    const icons = { success:'check-circle', error:'exclamation-circle', info:'info-circle' };
    const t = document.createElement('div');
    t.className = `app-toast ${type}`;
    t.innerHTML = `<i class="fas fa-${icons[type]}"></i> ${msg}`;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => t.remove(), 3000);
}
</script>

<?php include '../../includes/footer.php'; ?>