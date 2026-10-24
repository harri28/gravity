<?php
// ============================================================
// ARCHIVO: farmacia/mi-comprobante.php
// Página PÚBLICA (sin login) para que el cliente vea su propio
// comprobante -- SUNAT exige que el comprador pueda consultarlo.
// Se llega acá vía un token aleatorio (QR / enlace de WhatsApp),
// nunca por ID secuencial, para que no se puedan enumerar ventas
// ajenas. El token resuelve el schema de sucursal sin necesitar
// login ni pasar por el subdominio del tenant.
// ============================================================

require_once __DIR__ . '/config/database.php';

$token = trim($_GET['t'] ?? '');

function mostrarError(string $mensaje): void
{
    http_response_code(404);
    ?><!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Comprobante no encontrado</title>
        <style>
            *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
            body{font-family:-apple-system,'Segoe UI',system-ui,sans-serif;background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
            .card{background:#fff;border-radius:16px;box-shadow:0 12px 30px rgba(15,23,42,.1);max-width:380px;width:100%;padding:36px 28px;text-align:center}
            .icon{width:52px;height:52px;margin:0 auto 18px;background:#fee2e2;color:#dc2626;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700}
            h1{font-size:1.05rem;color:#1e293b;margin-bottom:8px}
            p{font-size:.86rem;color:#64748b;line-height:1.5}
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon">&times;</div>
            <h1>Comprobante no encontrado</h1>
            <p><?= htmlspecialchars($mensaje) ?></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    mostrarError('El enlace no es válido.');
}

try {
    $pdo = new PDO(
        'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    mostrarError('El servicio no está disponible en este momento. Intenta más tarde.');
}

$stmt = $pdo->prepare('SELECT schema_name, venta_id FROM public.comprobante_tokens WHERE token = :t LIMIT 1');
$stmt->execute([':t' => $token]);
$tokenRow = $stmt->fetch();

if (!$tokenRow) {
    mostrarError('Este enlace no corresponde a ningún comprobante.');
}

// El schema viene de nuestra propia tabla (nunca del usuario), pero se
// valida igual por seguridad antes de interpolarlo en SQL.
$schema = $tokenRow['schema_name'];
if (!preg_match('/^[a-zA-Z0-9_]+$/', $schema)) {
    mostrarError('Este enlace no corresponde a ningún comprobante.');
}
$ventaId = (int) $tokenRow['venta_id'];

$brandStmt = $pdo->prepare('
    SELECT s.nombre AS sucursal_nombre, s.direccion AS sucursal_direccion, s.telefono AS sucursal_telefono,
           t.nombre AS tenant_nombre, t.business_name, t.trade_name, t.ruc AS tenant_ruc
    FROM public.sucursales s
    JOIN public.tenants t ON t.id = s.tenant_id
    WHERE s.schema_name = :schema
    LIMIT 1
');
$brandStmt->execute([':schema' => $schema]);
$brand = $brandStmt->fetch() ?: [];

$ventaStmt = $pdo->prepare('
    SELECT v.id, v.numero_venta, v.tipo_comprobante, v.tipo_pago, v.subtotal, v.igv, v.descuento,
           v.total, v.estado, v.created_at,
           c.numero_completo, c.tipo AS comp_tipo, c.estado_sunat, c.enlace_del_cdr,
           cl.nombres AS cliente_nombres, cl.apellidos AS cliente_apellidos,
           cl.razon_social AS cliente_razon_social, cl.numero_documento AS cliente_documento,
           cl.direccion AS cliente_direccion
    FROM "' . $schema . '".ventas v
    LEFT JOIN "' . $schema . '".comprobantes_electronicos c ON c.venta_id = v.id
    LEFT JOIN "' . $schema . '".clientes cl ON cl.id = v.cliente_id
    WHERE v.id = :vid
    LIMIT 1
');
$ventaStmt->execute([':vid' => $ventaId]);
$venta = $ventaStmt->fetch();

if (!$venta) {
    mostrarError('Este enlace no corresponde a ningún comprobante.');
}

$detStmt = $pdo->prepare('
    SELECT d.cantidad, d.precio_unitario, d.subtotal, p.nombre AS producto_nombre
    FROM "' . $schema . '".venta_detalles d
    JOIN "' . $schema . '".productos p ON p.id = d.producto_id
    WHERE d.venta_id = :vid
    ORDER BY d.id
');
$detStmt->execute([':vid' => $ventaId]);
$items = $detStmt->fetchAll();

$empresaNombre = $brand['business_name'] ?: ($brand['trade_name'] ?: ($brand['tenant_nombre'] ?? 'Gravity'));
$numComp = $venta['numero_completo'] ?: $venta['numero_venta'];
$COMP_LABELS = ['boleta' => 'Boleta de Venta Electrónica', 'factura' => 'Factura Electrónica', 'ticket' => 'Ticket de Venta'];
$compLabel = $COMP_LABELS[$venta['tipo_comprobante']] ?? 'Comprobante de Venta';
$fecha = date('d/m/Y H:i', strtotime($venta['created_at']));
$clienteNombre = trim($venta['cliente_razon_social'] ?: trim(($venta['cliente_nombres'] ?? '') . ' ' . ($venta['cliente_apellidos'] ?? '')));
$anulado = $venta['estado'] === 'anulada';
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante <?= htmlspecialchars($numComp) ?></title>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,'Segoe UI',system-ui,sans-serif;background:#f1f5f9;min-height:100vh;padding:24px 16px}
        .card{background:#fff;border-radius:16px;box-shadow:0 12px 30px rgba(15,23,42,.1);max-width:420px;margin:0 auto;overflow:hidden;position:relative}
        .anulado-banner{background:#fef2f2;color:#dc2626;text-align:center;font-weight:700;font-size:.8rem;padding:8px;letter-spacing:.03em}
        .header{background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;padding:24px;text-align:center}
        .header .empresa{font-size:1.05rem;font-weight:700}
        .header .sucursal{font-size:.8rem;opacity:.85;margin-top:2px}
        .header .comp{margin-top:14px;font-size:.78rem;opacity:.9;text-transform:uppercase;letter-spacing:.04em}
        .header .num{font-size:1.15rem;font-weight:700;margin-top:2px}
        .body{padding:22px}
        .meta{font-size:.82rem;color:#64748b;margin-bottom:16px;line-height:1.6}
        .meta strong{color:#1e293b}
        table{width:100%;border-collapse:collapse;margin-bottom:16px;font-size:.84rem}
        thead th{text-align:left;color:#94a3b8;font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;padding-bottom:8px;border-bottom:1px solid #e2e8f0}
        tbody td{padding:8px 0;border-bottom:1px solid #f1f5f9}
        .text-right{text-align:right}
        .totales{margin-left:auto;width:100%;max-width:220px;margin-top:8px}
        .totales div{display:flex;justify-content:space-between;font-size:.84rem;padding:3px 0;color:#475569}
        .totales .total{font-weight:700;font-size:1rem;color:#1e293b;border-top:1px solid #e2e8f0;margin-top:6px;padding-top:8px}
        .footer{text-align:center;padding:16px;font-size:.72rem;color:#94a3b8;border-top:1px solid #f1f5f9}
    </style>
</head>
<body>
<div class="card">
    <?php if ($anulado): ?>
    <div class="anulado-banner">COMPROBANTE ANULADO</div>
    <?php endif; ?>
    <div class="header">
        <div class="empresa"><?= htmlspecialchars($empresaNombre) ?></div>
        <?php if (!empty($brand['sucursal_nombre'])): ?>
        <div class="sucursal"><?= htmlspecialchars($brand['sucursal_nombre']) ?></div>
        <?php endif; ?>
        <div class="comp"><?= htmlspecialchars($compLabel) ?></div>
        <div class="num"><?= htmlspecialchars($numComp) ?></div>
    </div>
    <div class="body">
        <div class="meta">
            <div><strong>Fecha:</strong> <?= htmlspecialchars($fecha) ?></div>
            <?php if ($clienteNombre): ?>
            <div><strong>Cliente:</strong> <?= htmlspecialchars($clienteNombre) ?></div>
            <?php endif; ?>
            <?php if (!empty($venta['cliente_documento'])): ?>
            <div><strong>Documento:</strong> <?= htmlspecialchars($venta['cliente_documento']) ?></div>
            <?php endif; ?>
        </div>

        <table>
            <thead><tr><th>Producto</th><th class="text-right">Cant.</th><th class="text-right">Subtotal</th></tr></thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= htmlspecialchars($it['producto_nombre']) ?></td>
                    <td class="text-right"><?= (int) $it['cantidad'] ?></td>
                    <td class="text-right">S/ <?= number_format((float) $it['subtotal'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totales">
            <div><span>Subtotal</span><span>S/ <?= number_format((float) $venta['subtotal'], 2) ?></span></div>
            <?php if ((float) $venta['igv'] > 0): ?>
            <div><span>IGV</span><span>S/ <?= number_format((float) $venta['igv'], 2) ?></span></div>
            <?php endif; ?>
            <?php if ((float) $venta['descuento'] > 0): ?>
            <div><span>Descuento</span><span>-S/ <?= number_format((float) $venta['descuento'], 2) ?></span></div>
            <?php endif; ?>
            <div class="total"><span>Total</span><span>S/ <?= number_format((float) $venta['total'], 2) ?></span></div>
        </div>
    </div>
    <div class="footer">Comprobante consultado el <?= date('d/m/Y H:i') ?></div>
</div>
</body>
</html>
