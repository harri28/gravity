<?php
// ============================================================
// ARCHIVO: scripts/emitir_notas_venta.php
// DESCRIPCION: Convierte en LOTE las notas de venta (tickets) ya registradas
//              en boleta/factura y las envia a SUNAT. Reusa exactamente la
//              misma logica que el boton de Facturacion y que registrar_venta
//              (helper facturacionEmitirComprobanteDesdeVenta en
//              modules/facturacion/_data_helpers.php).
//
//              El stock NO se toca (ya se desconto al crear el ticket). Cada
//              comprobante se emite con la FECHA ORIGINAL de la venta.
//
// USO:
//   php scripts/emitir_notas_venta.php [schema] [--emitir] [--limit=N] [--tipo=auto|boleta|factura]
//
//   - Sin --emitir  => DRY RUN: solo lista las notas de venta candidatas, no
//                      toca nada. (modo por defecto, seguro)
//   - --emitir      => emite de verdad (convierte + envia a SUNAT).
//   - [schema]      => procesa solo esa sucursal; si se omite, todas.
//   - --limit=N     => maximo de notas por sucursal (default 200).
//   - --tipo=X      => auto (default: factura si el cliente tiene RUC, si no
//                      boleta) | boleta | factura.
//
// IMPORTANTE: NO correr con --emitir hasta que SUNAT acepte los comprobantes
// del RUC (si el RUC no esta habilitado como emisor electronico, se generarian
// boletas RECHAZADAS y se quemarian correlativos B001). En ese caso primero
// resolver el error 0151 en el portal SOL.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo CLI\n");
}

date_default_timezone_set('America/Lima');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sunat.php';
require_once __DIR__ . '/../modules/facturacion/_data_helpers.php';

// --- Parse de argumentos ---
$schemaFiltro = null;
$emitir       = false;
$limit        = 200;
$tipo         = 'auto';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--emitir') {
        $emitir = true;
    } elseif (strpos($arg, '--limit=') === 0) {
        $limit = max(1, (int) substr($arg, 8));
    } elseif (strpos($arg, '--tipo=') === 0) {
        $tipo = strtolower(trim(substr($arg, 7)));
    } elseif (strpos($arg, '--') !== 0) {
        $schemaFiltro = preg_replace('/[^a-z0-9_]/', '', strtolower($arg));
    }
}
if (!in_array($tipo, ['auto', 'boleta', 'factura'], true)) {
    exit("tipo invalido: usa --tipo=auto|boleta|factura\n");
}

function _log(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

// --- Lock para evitar corridas superpuestas (una emision real puede tardar) ---
$lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gravity_emitir_notas_venta.lock';
$lockFp   = fopen($lockFile, 'c');
if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
    _log('Otra corrida sigue viva, salgo sin hacer nada.');
    exit(0);
}

$pdo = getDB();

_log($emitir
    ? '*** MODO EMISION REAL (--emitir): se convertira y enviara a SUNAT ***'
    : '--- DRY RUN (sin --emitir): solo listado, NO se emite nada ---');
_log("tipo={$tipo} | limite_por_sucursal={$limit}" . ($schemaFiltro ? " | schema={$schemaFiltro}" : ' | todas las sucursales'));

// --- Sucursales a recorrer ---
$sql = "SELECT id, tenant_id, nombre, schema_name FROM public.sucursales WHERE COALESCE(activo, TRUE) = TRUE";
if ($schemaFiltro) {
    $sql .= " AND schema_name = " . $pdo->quote($schemaFiltro);
}
$sql .= " ORDER BY tenant_id, id";
$sucursales = $pdo->query($sql)->fetchAll();

$tot = ['tickets' => 0, 'emitidos' => 0, 'aceptados' => 0, 'encolados' => 0, 'errores' => 0];

foreach ($sucursales as $suc) {
    $schema = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $suc['schema_name']));
    if ($schema === '') {
        continue;
    }

    // Contexto para enviar_sunat (lee $_SESSION['tenant_id']) + search_path
    $_SESSION['tenant_id']       = (int) $suc['tenant_id'];
    $_SESSION['sucursal_id']     = (int) $suc['id'];
    $_SESSION['sucursal_schema'] = $schema;
    try {
        $pdo->exec("SET search_path TO {$schema}, public");
    } catch (Throwable $e) {
        _log("[{$schema}] no se pudo SET search_path: " . $e->getMessage());
        continue;
    }

    // Notas de venta (ticket) completadas y sin comprobante electronico
    try {
        $stmt = $pdo->prepare("
            SELECT v.id, v.numero_venta, v.total, v.created_at
            FROM ventas v
            WHERE v.tipo_comprobante = 'ticket' AND v.estado = 'completada'
              AND NOT EXISTS (
                  SELECT 1 FROM comprobantes_electronicos ce
                  WHERE ce.venta_id = v.id AND ce.tipo IN ('boleta','factura')
              )
            ORDER BY v.created_at ASC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $tickets = $stmt->fetchAll();
    } catch (Throwable $e) {
        _log("[{$schema}] query de tickets fallo: " . $e->getMessage());
        continue;
    }

    if (!$tickets) {
        continue;
    }

    _log("[{$schema}] " . count($tickets) . " nota(s) de venta candidata(s)");
    $tot['tickets'] += count($tickets);

    if (!$emitir) {
        foreach ($tickets as $t) {
            _log(sprintf('  (dry) %s  S/ %s  %s',
                $t['numero_venta'], number_format((float) $t['total'], 2), substr((string) $t['created_at'], 0, 10)));
        }
        continue;
    }

    foreach ($tickets as $t) {
        $vid = (int) $t['id'];
        try {
            $r = facturacionEmitirComprobanteDesdeVenta($pdo, $vid, $tipo);
            $tot['emitidos']++;
            if (!empty($r['sunat_ok'])) {
                $tot['aceptados']++;
                _log("  OK {$t['numero_venta']} -> {$r['numero_completo']} ACEPTADO");
            } else {
                $tot['encolados']++;
                _log("  .. {$t['numero_venta']} -> {$r['numero_completo']} PENDIENTE: " . ($r['mensaje'] ?? $r['estado_sunat']));
            }
        } catch (Throwable $e) {
            $tot['errores']++;
            _log("  XX {$t['numero_venta']} ERROR: " . $e->getMessage());
        }
    }
}

_log(sprintf('Resumen: candidatos=%d emitidos=%d aceptados=%d encolados=%d errores=%d',
    $tot['tickets'], $tot['emitidos'], $tot['aceptados'], $tot['encolados'], $tot['errores']));
if (!$emitir) {
    _log('Fue DRY RUN. Para emitir de verdad agrega --emitir (solo DESPUES de habilitar el RUC en SUNAT).');
}

flock($lockFp, LOCK_UN);
fclose($lockFp);
@unlink($lockFile);
exit(0);
