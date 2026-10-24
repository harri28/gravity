<?php
// ============================================================
// ARCHIVO: farmacia/modules/caja/index.php
// MÓDULO:  Caja
// ============================================================

require_once '../../config/database.php';

$base_path      = '../../';
$current_module = 'caja';
$current_page   = 'caja';
$page_title     = 'Caja — Gravity';
$breadcrumb     = '<strong>Caja</strong>';

$db = getDB();

// Estado actual de la caja del usuario en sesión (server-side para renderizado inicial)
$caja = $db->prepare("
    SELECT * FROM cajas WHERE estado = 'abierta' AND usuario_id = :uid
    ORDER BY apertura_at DESC LIMIT 1
");
$caja->execute([':uid' => (int) sesionId()]);
$caja = $caja->fetch();

// Usuarios previos para datalist
$usuarios_previos = $db->query("
    SELECT DISTINCT usuario_apertura FROM cajas
    WHERE usuario_apertura IS NOT NULL AND usuario_apertura != ''
    ORDER BY usuario_apertura
")->fetchAll(PDO::FETCH_COLUMN);

include '../../includes/header.php';
?>

<?php if (!$caja): ?>
<!-- ============================================================
     ESTADO: CAJA CERRADA
     ============================================================ -->

<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-cash-register" style="color:var(--primary);margin-right:8px"></i>Caja</div>
        <div class="page-subtitle">Apertura y cierre de turno</div>
    </div>
</div>

<!-- Formulario de apertura centrado -->
<div style="display:flex;justify-content:center;margin:20px 0 32px">
    <div class="card" style="max-width:440px;width:100%;text-align:center">

        <div style="padding:32px 32px 24px">
            <div style="width:72px;height:72px;background:var(--surface-2);border-radius:50%;
                        display:flex;align-items:center;justify-content:center;margin:0 auto 20px;
                        border:2px solid var(--border)">
                <i class="fas fa-lock" style="font-size:1.8rem;color:var(--text-light)"></i>
            </div>
            <div style="font-size:1.15rem;font-weight:700;margin-bottom:6px">Caja Cerrada</div>
            <div style="font-size:.85rem;color:var(--text-muted);margin-bottom:28px">
                Apertura la caja para comenzar a registrar ventas
            </div>

            <div class="form-group" style="text-align:left">
                <label class="form-label">Usuario que apertura <span style="color:var(--danger)">*</span></label>
                <div class="input-group">
                    <span class="input-group-icon"><i class="fas fa-user"></i></span>
                    <input type="text" id="ap-usuario" class="form-control"
                        placeholder="Nombre del cajero" autocomplete="off"
                        list="usuarios-list" style="padding-left:36px"
                        value="<?= htmlspecialchars(sesionNombre()) ?>">
                </div>
                <datalist id="usuarios-list">
                    <?php foreach ($usuarios_previos as $u): ?>
                    <option value="<?= htmlspecialchars($u) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="form-group" style="text-align:left">
                <label class="form-label">Monto inicial en caja</label>
                <div class="input-group">
                    <span class="input-group-icon" style="font-weight:700;font-size:.85rem;color:var(--text)">S/</span>
                    <input type="number" id="ap-monto" class="form-control"
                        value="0.00" min="0" step="0.50" placeholder="0.00"
                        onfocus="this.select()"
                        style="font-size:1.2rem;font-weight:700;text-align:right">
                </div>
                <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap" id="montos-rapidos">
                    <?php foreach ([0, 50, 100, 200, 500] as $m): ?>
                    <button type="button" class="btn btn-outline btn-sm"
                        onclick="document.getElementById('ap-monto').value='<?= $m ?>.00'">
                        S/ <?= $m ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <button class="btn btn-success w-100 btn-lg" onclick="aperturarCaja()" id="btn-aperturar"
                style="margin-top:8px;font-size:1rem">
                <i class="fas fa-unlock"></i> Aperturar Caja
            </button>
        </div>
    </div>
</div>

<?php else:
    // Calcular resumen del turno
    $ventas_stmt = $db->prepare("
        SELECT COUNT(*) AS count, COALESCE(SUM(total),0) AS total
        FROM ventas WHERE caja_id = :id AND estado = 'completada'
    ");
    $ventas_stmt->execute([':id' => $caja['id']]);
    $ventas = $ventas_stmt->fetch();

    // Ventas del cajero actualmente conectado (para la tarjeta "Ventas
    // Totales" y el reporte imprimible -- distinto del $ventas de arriba,
    // que es de TODA la caja y se usa para el cuadre de efectivo)
    $ventas_usuario_stmt = $db->prepare("
        SELECT COUNT(*) AS count, COALESCE(SUM(total),0) AS total
        FROM ventas WHERE caja_id = :id AND estado = 'completada' AND usuario_id = :uid
    ");
    $ventas_usuario_stmt->execute([':id' => $caja['id'], ':uid' => sesionId()]);
    $ventas_usuario = $ventas_usuario_stmt->fetch();

    $movs_stmt = $db->prepare("
        SELECT tipo, COALESCE(SUM(monto),0) AS total
        FROM caja_movimientos WHERE caja_id = :id GROUP BY tipo
    ");
    $movs_stmt->execute([':id' => $caja['id']]);
    $ingresos_adic = 0; $egresos = 0;
    foreach ($movs_stmt->fetchAll() as $r) {
        if ($r['tipo'] === 'ingreso') $ingresos_adic = floatval($r['total']);
        else                          $egresos        = floatval($r['total']);
    }
    $saldo_esperado = floatval($caja['saldo_inicial']) + floatval($ventas['total']) + $ingresos_adic - $egresos;
    $apertura_dt    = new DateTime($caja['apertura_at']);
    $esPropia       = ((int) $caja['usuario_id'] === (int) sesionId());

    // Desglose por metodo de pago del turno (lee payment_breakdown, cubre
    // pagos simples y divididos; el credito no aporta porque queda vacio)
    $metodos_stmt = $db->prepare("
        SELECT elem->>'method' AS metodo, COALESCE(SUM((elem->>'amount')::numeric), 0) AS total
        FROM ventas v
        CROSS JOIN LATERAL jsonb_array_elements(COALESCE(v.payment_breakdown, '[]'::jsonb)) AS elem
        WHERE v.caja_id = :id AND v.estado = 'completada' AND v.usuario_id = :uid
        GROUP BY elem->>'method'
        ORDER BY total DESC
    ");
    $metodos_stmt->execute([':id' => $caja['id'], ':uid' => sesionId()]);
    $metodos_pago_turno = $metodos_stmt->fetchAll();
    $METODO_PAGO_LABELS = ['efectivo' => 'Efectivo', 'yape' => 'Yape', 'plin' => 'Plin', 'tarjeta' => 'Visa/Mastercard', 'transferencia' => 'Transferencia'];
?>

<!-- ============================================================
     ESTADO: CAJA ABIERTA
     ============================================================ -->

<div class="page-header">
    <div>
        <div class="page-title">
            <i class="fas fa-cash-register" style="color:var(--primary);margin-right:8px"></i>Caja
        </div>
        <div class="page-subtitle">
            <span style="color:var(--success);font-weight:600">
                <i class="fas fa-circle" style="font-size:.55rem;vertical-align:middle;margin-right:5px"></i>Abierta
            </span>
            &nbsp;·&nbsp; Usuario: <strong><?= htmlspecialchars($caja['usuario_apertura']) ?></strong>
            &nbsp;·&nbsp; Desde: <?= $apertura_dt->format('d/m/Y H:i') ?>
        </div>
    </div>
    <div class="page-actions">
        <?php if ($esPropia): ?>
        <button class="btn btn-outline" onclick="openModal('modal-gasto')" style="border-color:var(--danger);color:var(--danger)">
            <i class="fas fa-receipt"></i> Registrar Gasto
        </button>
        <button class="btn btn-danger" onclick="openCerrarModal()">
            <i class="fas fa-lock"></i> Cerrar Caja
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!$esPropia): ?>
<div class="alert" style="background:var(--surface-2);border:1px solid var(--border);border-left:4px solid var(--warning, #f59e0b);border-radius:var(--radius);padding:12px 16px;margin-bottom:20px;font-size:.88rem;display:flex;align-items:center;gap:10px">
    <i class="fas fa-eye" style="color:var(--warning, #f59e0b)"></i>
    <span>Caja abierta por <strong><?= htmlspecialchars($caja['usuario_apertura']) ?></strong> — estás en modo de solo lectura, no puedes operar sobre esta caja.</span>
</div>
<?php endif; ?>

<!-- Stats del turno -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg">
        <div class="stat-card" style="cursor:pointer;background:linear-gradient(135deg, var(--primary), var(--primary-dark));border-color:transparent" onclick="abrirReporteCaja()" title="Ver reporte de caja imprimible">
            <div class="stat-icon" style="background:rgba(255,255,255,.2);color:#fff"><i class="fas fa-cash-register"></i></div>
            <div>
                <div class="stat-value" style="color:#fff"><?= intval($ventas_usuario['count']) ?></div>
                <div class="stat-label" style="color:rgba(255,255,255,.85)">Reporte de Ventas</div>
                <div style="font-size:.68rem;color:rgba(255,255,255,.7);margin-top:2px">clic para imprimir</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-dollar-sign"></i></div>
            <div>
                <div class="stat-value">S/ <?= number_format(floatval($ventas['total']), 2) ?></div>
                <div class="stat-label">Ventas Totales</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fas fa-hand-holding-usd"></i></div>
            <div>
                <div class="stat-value">S/ <?= number_format($saldo_esperado, 2) ?></div>
                <div class="stat-label">Total esperado en caja</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="stat-card">
            <div class="stat-icon <?= $egresos > 0 ? 'red' : 'blue' ?>"><i class="fas fa-exchange-alt"></i></div>
            <div>
                <div class="stat-value"><?= $ingresos_adic + $egresos > 0 ? ($ingresos_adic + $egresos) : '0' ?></div>
                <div class="stat-label">Movimientos adicionales</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="stat-card" style="cursor:pointer" onclick="document.getElementById('section-gastos').scrollIntoView({behavior:'smooth'})">
            <div class="stat-icon red"><i class="fas fa-receipt"></i></div>
            <div>
                <div class="stat-value" id="stat-gastos-total">S/ 0.00</div>
                <div class="stat-label">Gastos del turno</div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($metodos_pago_turno)): ?>
<!-- Detalle por metodo de pago del turno -->
<div class="row g-3 mb-4">
    <?php foreach ($metodos_pago_turno as $mp): ?>
    <div class="col-6 col-md-4 col-lg">
        <div style="border:1px solid var(--border);border-radius:var(--radius);padding:8px 14px;display:flex;justify-content:space-between;align-items:center;font-size:.85rem;background:var(--surface-2)">
            <span style="color:var(--text-muted)"><?= htmlspecialchars($METODO_PAGO_LABELS[$mp['metodo']] ?? ucfirst($mp['metodo'])) ?></span>
            <span style="font-weight:700">S/ <?= number_format(floatval($mp['total']), 2) ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-4 align-items-start">

    <!-- Columna izquierda: movimientos -->
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Movimientos del turno</div>
                <span id="movs-count" style="font-size:.82rem;color:var(--text-muted)"></span>
            </div>
            <div class="table-wrap table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Tipo</th>
                            <th>Concepto</th>
                            <th>Usuario</th>
                            <th class="text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody id="movs-body">
                        <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-light)">
                            <i class="fas fa-spinner fa-spin"></i>
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Columna derecha: resumen + nuevo movimiento -->
    <div class="col-12 col-lg-4"><div class="d-flex flex-column gap-4">

        <!-- Resumen financiero -->
        <div class="card">
            <div class="card-header" style="padding-bottom:0">
                <div class="card-title" style="font-size:.9rem">Resumen financiero</div>
            </div>
            <div style="padding:0 16px 16px;font-size:.88rem">
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
                    <span style="color:var(--text-muted)">Monto inicial</span>
                    <span>S/ <?= number_format(floatval($caja['saldo_inicial']), 2) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
                    <span style="color:var(--text-muted)">Ventas (<?= intval($ventas['count']) ?>)</span>
                    <span style="color:var(--success)">+ S/ <?= number_format(floatval($ventas['total']), 2) ?></span>
                </div>
                <?php if ($ingresos_adic > 0): ?>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
                    <span style="color:var(--text-muted)">Ingresos adicionales</span>
                    <span style="color:var(--success)">+ S/ <?= number_format($ingresos_adic, 2) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($egresos > 0): ?>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
                    <span style="color:var(--text-muted)">Egresos</span>
                    <span style="color:var(--danger)">- S/ <?= number_format($egresos, 2) ?></span>
                </div>
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between;padding:10px 0 0;font-weight:700;font-size:1rem">
                    <span>Total esperado</span>
                    <span style="color:var(--success)">S/ <?= number_format($saldo_esperado, 2) ?></span>
                </div>
            </div>
        </div>

    </div>
</div><!-- /row g-4 -->

<!-- ---- MODAL: Cerrar Caja ---- -->
<div class="modal-overlay" id="modal-cerrar">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-lock" style="color:var(--danger);margin-right:8px"></i>Cerrar Caja
            </h3>
            <button class="modal-close" onclick="closeModal('modal-cerrar')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div style="background:var(--surface-2);border-radius:var(--radius);padding:14px 16px;margin-bottom:16px;font-size:.87rem">
                <div style="display:flex;justify-content:space-between;padding:4px 0">
                    <span style="color:var(--text-muted)">Monto inicial</span>
                    <span>S/ <?= number_format(floatval($caja['saldo_inicial']), 2) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:4px 0">
                    <span style="color:var(--text-muted)">Ventas del turno</span>
                    <span style="color:var(--success)">+ S/ <?= number_format(floatval($ventas['total']), 2) ?></span>
                </div>
                <?php if ($ingresos_adic > 0): ?>
                <div style="display:flex;justify-content:space-between;padding:4px 0">
                    <span style="color:var(--text-muted)">Ingresos adicionales</span>
                    <span style="color:var(--success)">+ S/ <?= number_format($ingresos_adic, 2) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($egresos > 0): ?>
                <div style="display:flex;justify-content:space-between;padding:4px 0">
                    <span style="color:var(--text-muted)">Egresos</span>
                    <span style="color:var(--danger)">- S/ <?= number_format($egresos, 2) ?></span>
                </div>
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between;padding:8px 0 0;border-top:1px solid var(--border);
                            margin-top:6px;font-weight:700">
                    <span>Total esperado</span>
                    <span style="color:var(--success)">S/ <?= number_format($saldo_esperado, 2) ?></span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-cerrar')">Cancelar</button>
            <button class="btn btn-danger" id="btn-confirmar-cierre" onclick="confirmarCierre()">
                <i class="fas fa-lock"></i> Confirmar Cierre
            </button>
        </div>
    </div>
</div>

<!-- ---- MODAL: Reporte de Caja ---- -->
<div class="modal-overlay" id="modal-reporte-caja">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-receipt" style="color:var(--primary);margin-right:8px"></i>Reporte de Caja
            </h3>
            <button class="modal-close" onclick="closeModal('modal-reporte-caja')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="reporte-caja-body" style="max-height:60vh;overflow-y:auto;border:1px solid var(--border);border-radius:var(--radius);padding:10px">
                <div style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-reporte-caja')">Cerrar</button>
            <button class="btn btn-primary" onclick="imprimirReporteCaja()">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ---- MODAL: Registrar Gasto ---- -->
<div class="modal-overlay" id="modal-gasto">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-receipt" style="color:var(--danger);margin-right:8px"></i>Registrar Gasto
            </h3>
            <button class="modal-close" onclick="closeModal('modal-gasto')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">

                <div class="form-group" style="grid-column:1/-1">
                    <label class="form-label">Descripción <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="g-descripcion" class="form-control"
                           placeholder="Ej: Escoba y recogedor, Factura de agua marzo...">
                </div>

                <div class="form-group">
                    <label class="form-label">Proveedor / Emisor</label>
                    <input type="text" id="g-proveedor" class="form-control"
                           placeholder="Nombre de quien emite la factura">
                </div>
                <div class="form-group">
                    <label class="form-label">N° Comprobante (factura / boleta)</label>
                    <input type="text" id="g-comprobante" class="form-control"
                           placeholder="Ej: F001-00123">
                </div>

                <div class="form-group">
                    <label class="form-label">Monto (S/) <span style="color:var(--danger)">*</span></label>
                    <input type="number" id="g-monto" class="form-control"
                           placeholder="0.00" min="0.01" step="0.01" style="font-size:1.05rem;font-weight:600">
                </div>
                <div class="form-group">
                    <label class="form-label">Método de pago</label>
                    <select id="g-metodo" class="form-control">
                        <option value="efectivo">Efectivo</option>
                        <option value="yape">Yape</option>
                        <option value="plin">Plin</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="tarjeta">Tarjeta</option>
                    </select>
                </div>

            </div>
            <?php if ($caja): ?>
            <div style="background:#fef3c7;border-radius:var(--radius);padding:10px 14px;margin-top:4px;font-size:.83rem;color:#92400e">
                <i class="fas fa-info-circle"></i>
                Este gasto se descontará automáticamente del saldo de caja del turno actual.
            </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-gasto')">Cancelar</button>
            <button class="btn btn-danger" id="btn-guardar-gasto" onclick="registrarGasto()">
                <i class="fas fa-save"></i> Registrar Gasto
            </button>
        </div>
    </div>
</div>

<!-- ======== GASTOS OPERATIVOS ======== -->
<div class="card" style="margin-top:28px" id="section-gastos">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-receipt" style="color:var(--danger);margin-right:6px"></i>Gastos Operativos
        </div>
        <button class="btn btn-outline btn-sm" onclick="openModal('modal-gasto')"
                style="border-color:var(--danger);color:var(--danger)">
            <i class="fas fa-plus"></i> Nuevo gasto
        </button>
    </div>
    <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <span style="font-size:.8rem;font-weight:600;color:var(--text-muted);white-space:nowrap">Período:</span>
        <input type="date" id="g-filtro-desde" class="form-control" style="width:auto;max-width:160px" value="<?= date('Y-m-d') ?>">
        <span style="color:var(--text-muted)">—</span>
        <input type="date" id="g-filtro-hasta" class="form-control" style="width:auto;max-width:160px" value="<?= date('Y-m-d') ?>">
        <button class="btn btn-primary btn-sm" onclick="loadGastos()">
            <i class="fas fa-search"></i> Filtrar
        </button>
    </div>
    <div class="table-wrap table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Fecha / Hora</th>
                    <th>Descripción</th>
                    <th>Proveedor</th>
                    <th>Comprobante</th>
                    <th>Método</th>
                    <th class="text-right">Monto</th>
                </tr>
            </thead>
            <tbody id="gastos-body">
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-light)">
                    <i class="fas fa-spinner fa-spin"></i>
                </td></tr>
            </tbody>
            <tfoot id="gastos-tfoot"></tfoot>
        </table>
    </div>
</div>

<!-- ======== HISTORIAL DE SESIONES (siempre visible abajo) ========
-->
<div class="card" style="margin-top:28px">
    <div class="card-header">
        <div class="card-title">Historial de sesiones</div>
    </div>
    <div class="table-wrap table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Apertura</th>
                    <th>Cierre</th>
                    <th class="text-right">Monto Inicial</th>
                    <th class="text-right">Ventas</th>
                    <th class="text-right">Monto Final</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody id="historial-body">
                <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-light)">
                    <i class="fas fa-spinner fa-spin"></i>
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="app-toast-container" id="toast-container"></div>

<script>
const BASE    = '../../';
const CAJA_ID = <?= $caja ? intval($caja['id']) : 'null' ?>;
const USUARIO = <?= $caja ? json_encode(htmlspecialchars($caja['usuario_apertura'])) : 'null' ?>;

document.addEventListener('DOMContentLoaded', () => {
    loadHistorial();
    loadGastos();
    <?php if ($caja): ?>
    loadMovimientos();
    <?php endif; ?>
});

// ---- Apertura ----
function aperturarCaja() {
    const usuario = document.getElementById('ap-usuario').value.trim();
    const monto   = parseFloat(document.getElementById('ap-monto').value) || 0;

    if (!usuario) { showToast('Ingresa el nombre del usuario', 'error'); return; }

    const btn = document.getElementById('btn-aperturar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aperturando...';

    fetch(BASE + 'modules/caja/api.php?action=aperturar', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ usuario, monto_inicial: monto }),
    })
    .then(r => r.json())
    .then(d => {
        if (d.error) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-unlock"></i> Aperturar Caja';
            showToast(d.message, 'error');
            return;
        }
        showToast('¡Caja aperturada correctamente!', 'success');
        setTimeout(() => location.reload(), 800);
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-unlock"></i> Aperturar Caja';
        showToast('Error al aperturar caja', 'error');
    });
}

// ---- Cierre ----
function openCerrarModal() {
    openModal('modal-cerrar');
}

function confirmarCierre() {
    const btn = document.getElementById('btn-confirmar-cierre');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cerrando...';

    fetch(BASE + 'modules/caja/api.php?action=cerrar', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ caja_id: CAJA_ID }),
    })
    .then(r => r.json())
    .then(d => {
        if (d.error) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock"></i> Confirmar Cierre';
            showToast(d.message, 'error');
            return;
        }
        showToast('Caja cerrada correctamente', 'success');
        setTimeout(() => location.reload(), 800);
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock"></i> Confirmar Cierre';
        showToast('Error al cerrar caja', 'error');
    });
}

// ---- Reporte de caja ----
const PAGO_LABELS = { efectivo: 'EFECTIVO', yape: 'YAPE', plin: 'PLIN', tarjeta: 'TARJETA', transferencia: 'TRANSFERENCIA' };

function abrirReporteCaja() {
    if (!CAJA_ID) return;
    openModal('modal-reporte-caja');
    const body = document.getElementById('reporte-caja-body');
    body.innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-light)"><i class="fas fa-spinner fa-spin"></i></div>';

    fetch(BASE + `modules/caja/api.php?action=reporte_cierre&caja_id=${CAJA_ID}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) { showToast(data.message, 'error'); closeModal('modal-reporte-caja'); return; }
            _ultimoReporteCaja = data;
            body.innerHTML = buildReporteCajaHTML(data);
        })
        .catch(() => {
            showToast('Error al cargar el reporte de caja', 'error');
            closeModal('modal-reporte-caja');
        });
}

let _ultimoReporteCaja = null;

function buildReporteCajaHTML(data) {
    const now   = new Date();
    const fecha = now.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const hora  = now.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', hour12: true });

    const row = (l, r, bold = false) => `<div style="display:flex;justify-content:space-between;font-size:11px;${bold ? 'font-weight:700' : ''}">
        <span>${l}</span><span>${r}</span></div>`;

    const metodosHTML = (data.metodos_pago || [])
        .map(m => row(`(+) ${PAGO_LABELS[m.metodo] || m.metodo.toUpperCase()} (S/)`, parseFloat(m.total).toFixed(2)))
        .join('');

    return `<div id="reporte-caja-ticket" style="font-family:'Courier New',Courier,monospace;color:#000;font-size:11px;line-height:1.5">
        <div style="text-align:center">${fecha} ${hora}</div>
        <div style="text-align:center;font-weight:700;margin-top:2px">${(data.caja.usuario_apertura || '').toUpperCase()}</div>
        <div style="border-top:1px dashed #000;margin:6px 0"></div>
        <div style="text-align:center;font-weight:700">RESUMEN GENERAL:</div>
        <div style="border-top:1px dashed #000;margin:6px 0"></div>

        <div style="font-weight:700">METODOS DE PAGOS</div>
        ${metodosHTML}
        ${row('(=) TOTAL COBRADO (S/)', parseFloat(data.total_cobrado).toFixed(2), true)}
        <div style="border-top:1px dashed #000;margin:6px 0"></div>

        <div style="font-weight:700">VENTAS</div>
        ${row('(+) SUBTOTAL VENTAS (S/)', parseFloat(data.ventas.subtotal).toFixed(2))}
        ${row('(-) DESCUENTOS (S/)', parseFloat(data.ventas.descuento).toFixed(2))}
        ${row('(=) TOTAL VENTAS (S/)', parseFloat(data.ventas.total).toFixed(2), true)}
        ${row('TOTAL CREDITO (S/)', parseFloat(data.ventas.total_credito).toFixed(2))}
        <div style="border-top:1px dashed #000;margin:6px 0"></div>

        <div style="font-weight:700">SALDO EFECTIVO</div>
        ${row('(+) EFECTIVO (S/)', parseFloat(data.efectivo_ventas || 0).toFixed(2))}
        ${row('(+) OTROS INGRESOS (S/)', parseFloat(data.ingresos_adicionales).toFixed(2))}
        ${row('(-) EGRESOS (S/)', parseFloat(data.egresos).toFixed(2))}
        ${row('(=) SALDO EFECTIVO (S/)', parseFloat(data.saldo_efectivo).toFixed(2), true)}
        <div style="border-top:1px dashed #000;margin:6px 0"></div>

        <div style="font-weight:700">OBSERVACIONES</div>
        ${row('TOTAL ARTICULOS', data.articulos)}
        ${row('TOTAL ANULADOS (S/)', parseFloat(data.anulados.total).toFixed(2))}
        ${row('TRANSACCIONES REALIZADAS', data.ventas.count)}
    </div>`;
}

function imprimirReporteCaja() {
    if (!_ultimoReporteCaja) return;
    const innerHtml = document.getElementById('reporte-caja-ticket').outerHTML;
    const style = `* { margin:0; padding:0; box-sizing:border-box; } body { font-family:'Courier New',Courier,monospace; font-size:11px; color:#000; width:80mm; margin:0 auto; padding:4mm 3mm; } @media print { @page { size:80mm auto; margin:4mm 3mm; } body { padding:0; } }`;
    const w = window.open('', '_blank', 'width=420,height=700');
    w.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><style>${style}</style></head><body>${innerHtml}</body></html>`);
    w.document.close();
    w.focus();
    setTimeout(() => { w.print(); }, 400);
}

// ---- Movimientos ----
function loadMovimientos() {
    if (!CAJA_ID) return;
    fetch(BASE + `modules/caja/api.php?action=movimientos&caja_id=${CAJA_ID}`)
        .then(r => r.json())
        .then(data => {
            const count = document.getElementById('movs-count');
            if (count) count.textContent = data.length + ' movimiento(s)';
            const tbody = document.getElementById('movs-body');
            if (!tbody) return;
            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-light)">Sin movimientos adicionales registrados</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(m => {
                const dt    = new Date(m.created_at);
                const hora  = dt.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
                const color = m.tipo === 'ingreso' ? 'var(--success)' : 'var(--danger)';
                const icono = m.tipo === 'ingreso' ? 'arrow-down' : 'arrow-up';
                const signo = m.tipo === 'ingreso' ? '+' : '-';
                return `<tr>
                    <td style="font-size:.82rem;color:var(--text-muted)">${hora}</td>
                    <td>
                        <span class="badge" style="background:${m.tipo === 'ingreso' ? 'var(--success-light,#dcfce7)' : '#fee2e2'};
                              color:${color};font-size:.75rem">
                            <i class="fas fa-${icono}"></i> ${m.tipo}
                        </span>
                    </td>
                    <td style="font-size:.85rem">${m.concepto}</td>
                    <td style="font-size:.82rem;color:var(--text-muted)">${m.usuario}</td>
                    <td class="text-right" style="font-weight:700;color:${color}">
                        ${signo} S/ ${parseFloat(m.monto).toFixed(2)}
                    </td>
                </tr>`;
            }).join('');
        });
}


// ---- Historial de sesiones ----
function loadHistorial() {
    fetch(BASE + 'modules/caja/api.php?action=historial')
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('historial-body');
            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-light)">Sin sesiones registradas</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(c => {
                const apertura = c.apertura_at ? new Date(c.apertura_at).toLocaleString('es-PE', {dateStyle:'short',timeStyle:'short'}) : '—';
                const cierre   = c.cierre_at   ? new Date(c.cierre_at).toLocaleString('es-PE',   {dateStyle:'short',timeStyle:'short'}) : '—';
                const badge    = (c.estado === 'abierta')
                    ? '<span class="badge badge-success">Abierta</span>'
                    : '<span class="badge badge-gray">Cerrada</span>';
                return `<tr>
                    <td style="font-weight:500">${c.usuario_apertura || '—'}</td>
                    <td style="font-size:.82rem;color:var(--text-muted)">${apertura}</td>
                    <td style="font-size:.82rem;color:var(--text-muted)">${cierre}</td>
                    <td class="text-right">S/ ${parseFloat(c.saldo_inicial).toFixed(2)}</td>
                    <td class="text-right" style="color:var(--success)">${c.total_ventas} venta(s)</td>
                    <td class="text-right" style="font-weight:700">S/ ${parseFloat(c.saldo_actual).toFixed(2)}</td>
                    <td>${badge}</td>
                </tr>`;
            }).join('');
        });
}

// ---- Gastos operativos ----
const CATS_GASTO = {
    limpieza:      '🧹 Limpieza',
    papeleria:     '📋 Papelería',
    servicios:     '💡 Servicios',
    alquiler:      '🏠 Alquiler',
    mantenimiento: '🔧 Mantenimiento',
    transporte:    '🚗 Transporte',
    publicidad:    '📢 Publicidad',
    personal:      '👤 Personal',
    otros:         '📦 Otros',
};

function loadGastos() {
    const desde = document.getElementById('g-filtro-desde').value;
    const hasta = document.getElementById('g-filtro-hasta').value;
    const params = new URLSearchParams({ action: 'gastos_listar', desde, hasta });
    if (CAJA_ID) params.set('caja_id', CAJA_ID);

    fetch(BASE + 'modules/caja/api.php?' + params)
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('gastos-body');
            const tfoot = document.getElementById('gastos-tfoot');

            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-light)">Sin gastos registrados en este período</td></tr>';
                tfoot.innerHTML = '';
                const stat = document.getElementById('stat-gastos-total');
                if (stat) stat.textContent = 'S/ 0.00';
                return;
            }

            let totalGastos = 0;
            tbody.innerHTML = data.map(g => {
                totalGastos += parseFloat(g.monto);
                const dt  = new Date(g.created_at);
                const fh  = dt.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' });
                return `<tr>
                    <td style="font-size:.82rem;color:var(--text-muted)">${fh}</td>
                    <td style="font-weight:500">${g.descripcion}</td>
                    <td style="font-size:.82rem;color:var(--text-muted)">${g.proveedor || '—'}</td>
                    <td style="font-size:.82rem;font-family:monospace">${g.numero_comprobante || '—'}</td>
                    <td style="font-size:.82rem">${g.metodo_pago}</td>
                    <td class="text-right" style="font-weight:700;color:var(--danger)">
                        - S/ ${parseFloat(g.monto).toFixed(2)}
                    </td>
                </tr>`;
            }).join('');

            tfoot.innerHTML = `
                <tr style="background:var(--surface-2)">
                    <td colspan="5" style="text-align:right;font-weight:600;font-size:.88rem;padding:10px 16px">
                        Total gastos del período:
                    </td>
                    <td class="text-right" style="font-weight:700;color:var(--danger);padding:10px 16px">
                        - S/ ${totalGastos.toFixed(2)}
                    </td>
                </tr>`;

            const stat = document.getElementById('stat-gastos-total');
            if (stat) stat.textContent = 'S/ ' + totalGastos.toFixed(2);
        });
}

function registrarGasto() {
    const descripcion = document.getElementById('g-descripcion').value.trim();
    const monto       = parseFloat(document.getElementById('g-monto').value);

    if (!descripcion)         { showToast('La descripción es requerida', 'error'); return; }
    if (!monto || monto <= 0) { showToast('Ingresa un monto válido', 'error'); return; }

    const btn = document.getElementById('btn-guardar-gasto');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    fetch(BASE + 'modules/caja/api.php?action=registrar_gasto', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            caja_id: CAJA_ID,
            descripcion,
            proveedor:          document.getElementById('g-proveedor').value.trim(),
            numero_comprobante: document.getElementById('g-comprobante').value.trim(),
            monto,
            metodo_pago:        document.getElementById('g-metodo').value,
        }),
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Registrar Gasto';
        if (d.error) { showToast(d.message, 'error'); return; }

        showToast('Gasto registrado correctamente', 'success');
        closeModal('modal-gasto');

        // Limpiar formulario
        document.getElementById('g-descripcion').value = '';
        document.getElementById('g-proveedor').value   = '';
        document.getElementById('g-comprobante').value = '';
        document.getElementById('g-monto').value       = '';

        loadGastos();
        if (CAJA_ID) loadMovimientos();
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Registrar Gasto';
        showToast('Error al registrar gasto', 'error');
    });
}

// ---- Helpers ----
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function showToast(msg, type = 'info') {
    const icons = { success: 'check-circle', error: 'exclamation-circle', info: 'info-circle' };
    const t = document.createElement('div');
    t.className = `app-toast ${type}`;
    t.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i> ${msg}`;
    document.getElementById('toast-container').appendChild(t);
    setTimeout(() => t.remove(), 3500);
}
</script>

<?php include '../../includes/footer.php'; ?>
