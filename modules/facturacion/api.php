<?php
// ============================================================
// ARCHIVO: farmacia/modules/facturacion/api.php
// DESCRIPCIÓN: API REST para el módulo de Facturación / Reportes
// ============================================================

require_once '../../config/database.php';
require_once __DIR__ . '/_data_helpers.php';
requireApiAuth(['admin', 'gerente']);

$action = $_GET['action'] ?? '';
$db     = getDB();

function facturacionSerieNotaCreditoSegunOrigen(array $origen): array
{
    $codigoOrigen = trim((string) ($origen['codigo_tipo_documento'] ?? ''));

    if ($codigoOrigen === '01') {
        return [
            'tipo' => 'nota_credito_factura',
            'serie' => 'FC01',
            'descripcion' => 'Nota de crédito factura',
        ];
    }

    if ($codigoOrigen === '03') {
        return [
            'tipo' => 'nota_credito_boleta',
            'serie' => 'BC01',
            'descripcion' => 'Nota de crédito boleta',
        ];
    }

    return [
        'tipo' => 'nota_credito',
        'serie' => 'NC01',
        'descripcion' => 'Nota de crédito',
    ];
}

function facturacionMarcarOrigenComoAnulado(PDO $db, int $origenComprobanteId, int $origenVentaId, string $motivo = 'Anulado con nota de credito'): void
{
    $db->prepare("
        UPDATE ventas
        SET estado = 'anulada'
        WHERE id = :id
    ")->execute([':id' => $origenVentaId]);

    $db->prepare("
        UPDATE comprobantes_electronicos
        SET estado_sunat = :estado
        WHERE id = :id
    ")->execute([
        ':estado' => $motivo,
        ':id' => $origenComprobanteId,
    ]);
}


function facturacionCargarTiposNotaCredito(PDO $db): array
{
    $stmt = $db->query("
        SELECT id, codigo, descripcion
        FROM public.fe_tipos_nota_credito
        WHERE COALESCE(estado, TRUE) = TRUE
        ORDER BY codigo
    ");
    return $stmt->fetchAll() ?: [];
}

function facturacionCargarOrigenesNotaCredito(PDO $db, string $q = ''): array
{
    $params = [];
    $where = "WHERE ce.tipo IN ('boleta', 'factura') AND COALESCE(ce.enlace_del_cdr, '') <> ''";
    if ($q !== '') {
        $where .= " AND (
            ce.numero_completo ILIKE :q OR
            v.numero_venta ILIKE :q OR
            COALESCE(c.nombres, '') ILIKE :q OR
            COALESCE(c.apellidos, '') ILIKE :q OR
            COALESCE(c.razon_social, '') ILIKE :q OR
            COALESCE(c.nombre_completo, '') ILIKE :q
        )";
        $params[':q'] = '%' . trim($q) . '%';
    }

    $stmt = $db->prepare("
        SELECT
            ce.id,
            ce.tipo,
            ce.serie,
            ce.numero,
            ce.numero_completo,
            ce.codigo_tipo_documento,
            ce.estado_sunat,
            v.numero_venta,
            v.total,
            v.created_at,
            COALESCE(c.razon_social, c.nombre_completo, c.nombres || ' ' || COALESCE(c.apellidos,''), 'CLIENTES VARIOS') AS cliente,
            COALESCE(c.dni, '') AS dni,
            COALESCE(c.ruc, '') AS ruc
        FROM comprobantes_electronicos ce
        INNER JOIN ventas v ON v.id = ce.venta_id
        LEFT JOIN clientes c ON c.id = v.cliente_id
        $where
        ORDER BY ce.created_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function facturacionCargarNotasCredito(PDO $db, string $desde, string $hasta, string $estado = '', string $q = ''): array
{
    $params = [':desde' => $desde, ':hasta' => $hasta . ' 23:59:59'];
    $where = "WHERE ce.tipo = 'nota_credito' AND ce.created_at BETWEEN :desde AND :hasta";
    if ($estado !== '') {
        $where .= " AND LOWER(COALESCE(ce.estado_sunat, '')) LIKE :estado";
        $params[':estado'] = '%' . strtolower(trim($estado)) . '%';
    }
    if ($q !== '') {
        $where .= " AND (
            ce.numero_completo ILIKE :q OR
            COALESCE(c.nombres, '') ILIKE :q OR
            COALESCE(c.apellidos, '') ILIKE :q OR
            COALESCE(c.razon_social, '') ILIKE :q OR
            COALESCE(c.nombre_completo, '') ILIKE :q OR
            COALESCE(ref.numero_completo, '') ILIKE :q
        )";
        $params[':q'] = '%' . trim($q) . '%';
    }

    $stmt = $db->prepare("
        SELECT
            ce.id,
            ce.venta_id,
            ce.tipo,
            ce.serie,
            ce.numero,
            ce.numero_completo,
            ce.codigo_tipo_documento,
            ce.estado_sunat,
            ce.enlace_del_pdf,
            ce.enlace_del_xml,
            ce.enlace_del_cdr,
            ce.nubefact_response,
            ce.codigo_tipo_nota_credito,
            ce.motivo_nota_credito,
            ce.descripcion_nota_credito,
            ce.documento_modificado_tipo_documento_codigo,
            ce.documento_modificado_serie,
            ce.documento_modificado_numero,
            ce.documento_modificado_numero_completo,
            ce.documento_modificado_fecha,
            ce.created_at,
            v.numero_venta,
            v.total,
            COALESCE(c.razon_social, c.nombre_completo, c.nombres || ' ' || COALESCE(c.apellidos,''), 'CLIENTES VARIOS') AS cliente,
            COALESCE(c.dni, '') AS dni,
            COALESCE(c.ruc, '') AS ruc,
            COALESCE(ref.numero_completo, '') AS referencia_numero_completo,
            COALESCE(ref.codigo_tipo_documento, '') AS referencia_codigo_tipo_documento
        FROM comprobantes_electronicos ce
        INNER JOIN ventas v ON v.id = ce.venta_id
        LEFT JOIN clientes c ON c.id = v.cliente_id
        LEFT JOIN comprobantes_electronicos ref ON ref.id = ce.referencia_comprobante_id
        $where
        ORDER BY ce.created_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

// ---- Parámetros de filtro comunes ----
$desde     = $_GET['desde']     ?? date('Y-m-d');
$hasta     = $_GET['hasta']     ?? date('Y-m-d');
$estado    = $_GET['estado']    ?? '';
$tipo_comp = $_GET['tipo_comp'] ?? '';
$tipo_pago = $_GET['tipo_pago'] ?? '';
$vendedor    = $_GET['vendedor']    ?? '';
$categoria_id = intval($_GET['categoria_id'] ?? 0);
$q           = '%' . trim($_GET['q'] ?? '') . '%';

$hasta_dt = $hasta . ' 23:59:59';

// Filtros extra de Rentabilidad (tipo de comprobante, metodo de pago,
// laboratorio, tipo de producto). Se agregan al WHERE de las 4 consultas
// rentabilidad_* para que stats, categorias, productos y tendencia
// siempre reflejen el mismo conjunto de ventas.
function rentFiltrosExtra(array &$params): string {
    $sql = '';
    $tc = trim($_GET['tipo_comp'] ?? '');
    if ($tc !== '')  { $sql .= " AND v.tipo_comprobante = :f_tc";  $params[':f_tc'] = $tc; }
    $tp = trim($_GET['tipo_pago'] ?? '');
    if ($tp !== '')  { $sql .= " AND v.tipo_pago = :f_tp";         $params[':f_tp'] = $tp; }
    $lab = trim($_GET['laboratorio'] ?? '');
    if ($lab !== '') { $sql .= " AND p.laboratorio ILIKE :f_lab";  $params[':f_lab'] = '%' . $lab . '%'; }
    $pt = trim($_GET['product_type'] ?? '');
    if ($pt === 'product' || $pt === 'service') { $sql .= " AND p.product_type = :f_pt"; $params[':f_pt'] = $pt; }
    return $sql;
}

switch ($action) {

    // ---- GET: Estadísticas del período ----
    case 'stats':
        header('Content-Type: application/json; charset=UTF-8');

        $params = [':desde' => $desde, ':hasta' => $hasta_dt];

        $where_extra = '';
        if ($estado)    { $where_extra .= " AND v.estado = :estado";              $params[':estado']    = $estado; }
        if ($tipo_comp) { $where_extra .= " AND v.tipo_comprobante = :tipo_comp"; $params[':tipo_comp'] = $tipo_comp; }
        if ($tipo_pago) { $where_extra .= " AND v.tipo_pago = :tipo_pago";        $params[':tipo_pago'] = $tipo_pago; }
        if ($vendedor)  { $where_extra .= " AND v.vendedor = :vendedor";          $params[':vendedor']  = $vendedor; }

        $stmt = $db->prepare("
            SELECT
                COUNT(*) FILTER (WHERE v.estado = 'completada')  AS total_completadas,
                COUNT(*) FILTER (WHERE v.estado = 'anulada')     AS total_anuladas,
                COALESCE(SUM(v.total)    FILTER (WHERE v.estado = 'completada'), 0) AS total_ingresos,
                COALESCE(SUM(v.igv)      FILTER (WHERE v.estado = 'completada'), 0) AS total_igv,
                COALESCE(SUM(v.descuento)FILTER (WHERE v.estado = 'completada'), 0) AS total_descuentos,
                COALESCE(AVG(v.total)    FILTER (WHERE v.estado = 'completada'), 0) AS ticket_promedio,
                COALESCE(SUM(v.total) FILTER (WHERE v.estado = 'completada' AND v.tipo_pago = 'efectivo'),     0) AS pago_efectivo,
                COALESCE(SUM(v.total) FILTER (WHERE v.estado = 'completada' AND v.tipo_pago = 'yape'),         0) AS pago_yape,
                COALESCE(SUM(v.total) FILTER (WHERE v.estado = 'completada' AND v.tipo_pago = 'plin'),         0) AS pago_plin,
                COALESCE(SUM(v.total) FILTER (WHERE v.estado = 'completada' AND v.tipo_pago = 'tarjeta'),      0) AS pago_tarjeta,
                COALESCE(SUM(v.total) FILTER (WHERE v.estado = 'completada' AND v.tipo_pago = 'transferencia'),0) AS pago_transferencia,
                COUNT(*) FILTER (WHERE v.estado = 'completada' AND v.tipo_comprobante = 'ticket')  AS comp_ticket,
                COUNT(*) FILTER (WHERE v.estado = 'completada' AND v.tipo_comprobante = 'boleta')  AS comp_boleta,
                COUNT(*) FILTER (WHERE v.estado = 'completada' AND v.tipo_comprobante = 'factura') AS comp_factura
            FROM ventas v
            WHERE v.created_at BETWEEN :desde AND :hasta
            $where_extra
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetch());
        break;

    // ---- GET: Stats por vendedor (comparativa) ----
    case 'stats_usuario':
        header('Content-Type: application/json; charset=UTF-8');

        $params = [':desde' => $desde, ':hasta' => $hasta_dt];
        $where_extra = '';
        if ($estado)    { $where_extra .= " AND v.estado = :estado";              $params[':estado']    = $estado; }
        if ($tipo_comp) { $where_extra .= " AND v.tipo_comprobante = :tipo_comp"; $params[':tipo_comp'] = $tipo_comp; }
        if ($tipo_pago) { $where_extra .= " AND v.tipo_pago = :tipo_pago";        $params[':tipo_pago'] = $tipo_pago; }
        if ($vendedor)  { $where_extra .= " AND v.vendedor = :vendedor";          $params[':vendedor']  = $vendedor; }

        try {
            $stmt = $db->prepare("
                SELECT
                    COALESCE(v.vendedor, 'Sin asignar')                                     AS vendedor,
                    COUNT(*) FILTER (WHERE v.estado = 'completada')                         AS total_ventas,
                    COALESCE(SUM(v.total) FILTER (WHERE v.estado = 'completada'), 0)        AS total_ingresos,
                    COALESCE(SUM(v.igv)   FILTER (WHERE v.estado = 'completada'), 0)        AS total_igv,
                    COALESCE(AVG(v.total) FILTER (WHERE v.estado = 'completada'), 0)        AS ticket_promedio,
                    COUNT(*) FILTER (WHERE v.estado = 'anulada')                            AS total_anuladas,
                    COALESCE(SUM(v.total) FILTER (WHERE v.estado = 'completada' AND v.tipo_pago = 'efectivo'),     0) AS pago_efectivo,
                    COALESCE(SUM(v.total) FILTER (WHERE v.estado = 'completada' AND v.tipo_pago = 'yape'),         0) AS pago_yape,
                    COALESCE(SUM(v.total) FILTER (WHERE v.estado = 'completada' AND v.tipo_pago = 'plin'),         0) AS pago_plin,
                    COALESCE(SUM(v.total) FILTER (WHERE v.estado = 'completada' AND v.tipo_pago = 'tarjeta'),      0) AS pago_tarjeta,
                    COALESCE(SUM(v.total) FILTER (WHERE v.estado = 'completada' AND v.tipo_pago = 'transferencia'),0) AS pago_transferencia
                FROM ventas v
                WHERE v.created_at BETWEEN :desde AND :hasta
                $where_extra
                GROUP BY COALESCE(v.vendedor, 'Sin asignar')
                ORDER BY total_ingresos DESC
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    // ---- GET: Lista de vendedores para el filtro ----
    case 'usuarios_lista':
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $rows = $db->query("
                SELECT DISTINCT COALESCE(vendedor, 'Sin asignar') AS vendedor
                FROM ventas
                WHERE vendedor IS NOT NULL AND vendedor != ''
                ORDER BY vendedor
            ")->fetchAll();
            echo json_encode($rows);
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;

    case 'tipos_nota_credito':
        header('Content-Type: application/json; charset=UTF-8');
        try {
            echo json_encode(facturacionCargarTiposNotaCredito($db));
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;

    case 'documentos_origen_nota_credito':
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $qOrigen = trim($_GET['q'] ?? ($_POST['q'] ?? ''));
            echo json_encode(facturacionCargarOrigenesNotaCredito($db, $qOrigen));
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;

    case 'notas_reporte':
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $rows = facturacionCargarNotasCredito($db, $desde, $hasta, $estado, trim($_GET['q'] ?? ''));
            echo json_encode($rows);
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => 'Error al cargar notas de credito: ' . $e->getMessage()], 500);
        }
        break;

    case 'detalle_venta_ticket':
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $ventaId = (int) ($_GET['id'] ?? 0);
            if ($ventaId <= 0) {
                jsonResponse(['error' => true, 'message' => 'Venta no válida.'], 422);
            }

            $stmt = $db->prepare("
                SELECT
                    v.id,
                    v.numero_venta,
                    v.created_at,
                    v.subtotal,
                    v.descuento,
                    v.igv,
                    v.total,
                    v.tipo_pago,
                    v.tipo_comprobante,
                    v.estado,
                    v.vendedor,
                    v.fecha_emision,
                    v.hora_emision,
                    COALESCE(c.nombres || ' ' || COALESCE(c.apellidos,''), COALESCE(c.razon_social, c.nombre_completo, 'CLIENTES VARIOS')) AS cliente,
                    COALESCE(c.dni, '') AS dni,
                    COALESCE(c.ruc, '') AS ruc,
                    COALESCE(c.direccion, '') AS direccion,
                    COALESCE(ce.numero_completo, '') AS comprobante_numero,
                    COALESCE(ce.estado_sunat, '') AS estado_sunat,
                    COALESCE(ce.enlace_del_pdf, '') AS enlace_pdf,
                    COALESCE(ce.enlace_del_xml, '') AS enlace_xml,
                    COALESCE(ce.enlace_del_cdr, '') AS enlace_cdr
                FROM ventas v
                LEFT JOIN clientes c ON c.id = v.cliente_id
                LEFT JOIN comprobantes_electronicos ce ON ce.venta_id = v.id
                WHERE v.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $ventaId]);
            $venta = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$venta) {
                jsonResponse(['error' => true, 'message' => 'No se encontró la venta.'], 404);
            }

            $venta['items'] = facturacionCargarItemsVenta($db, $ventaId);
            echo json_encode($venta);
        } catch (Throwable $e) {
            jsonResponse(['error' => true, 'message' => 'Error al preparar ticket: ' . $e->getMessage()], 500);
        }
        break;

    case 'crear_nota_credito':
        header('Content-Type: application/json; charset=UTF-8');

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];
        $origenId = (int) ($data['comprobante_origen_id'] ?? 0);
        $tipoNotaCreditoId = (int) ($data['tipo_nota_credito_id'] ?? 0);
        $descripcion = trim((string) ($data['descripcion'] ?? ''));

        if ($origenId <= 0 || $tipoNotaCreditoId <= 0) {
            jsonResponse(['error' => true, 'message' => 'Debes seleccionar un comprobante origen y un motivo.'], 422);
        }
        if ($descripcion === '') {
            jsonResponse(['error' => true, 'message' => 'La descripcion es obligatoria.'], 422);
        }

        try {
            $origen = facturacionCargarComprobanteVenta($db, $origenId);
            if (!in_array($origen['tipo'], ['boleta', 'factura'], true)) {
                throw new RuntimeException('Solo se pueden anular boletas o facturas con nota de credito.');
            }
            $responseCode = facturacionExtraerResponseCode($origen['nubefact_response'] ?? null);
            $estadoSunat = strtolower(trim((string) ($origen['estado_sunat'] ?? '')));
            $yaEnviado = !empty($origen['enlace_del_cdr'])
                || $responseCode !== null
                || strpos($estadoSunat, 'acept') !== false
                || strpos($estadoSunat, 'observ') !== false;
            if (!$yaEnviado) {
                throw new RuntimeException('El comprobante origen debe estar enviado y con CDR antes de emitir una nota de credito.');
            }

            $tipoNota = null;
            foreach (facturacionCargarTiposNotaCredito($db) as $row) {
                if ((int) $row['id'] === $tipoNotaCreditoId) {
                    $tipoNota = $row;
                    break;
                }
            }
            if (!$tipoNota) {
                throw new RuntimeException('El motivo de la nota de credito no es valido.');
            }

            $cliente = facturacionCargarClienteVenta($db, !empty($origen['cliente_id']) ? (int) $origen['cliente_id'] : null);
            $items = facturacionCargarItemsVenta($db, (int) $origen['venta_id']);

            $db->beginTransaction();
            $serieConfig = facturacionSerieNotaCreditoSegunOrigen($origen);
            $serieStmt = $db->prepare("
                SELECT tipo, serie, ultimo_numero, codigo_tipo_documento, tipo_documento_id
                FROM series_comprobantes
                WHERE tipo = :tipo AND serie = :serie AND COALESCE(activo, TRUE) = TRUE
                LIMIT 1
                FOR UPDATE
            ");
            $serieStmt->execute([
                ':tipo' => $serieConfig['tipo'],
                ':serie' => $serieConfig['serie'],
            ]);
            $serieRow = $serieStmt->fetch();
            if (!$serieRow) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw new RuntimeException('No hay una serie de nota de crédito configurada para esta sucursal.');
            }
            $nextNumero = (int) ($serieRow['ultimo_numero'] ?? 0) + 1;
            $serie = (string) $serieRow['serie'];
            $correlativo = str_pad((string) $nextNumero, 8, '0', STR_PAD_LEFT);
            $numeroCompleto = $serie . '-' . $correlativo;
            $notaCodigo = (string) $tipoNota['codigo'];

            $db->prepare("
                UPDATE series_comprobantes
                SET ultimo_numero = :ultimo
                WHERE tipo = :tipo AND serie = :serie
            ")->execute([
                ':ultimo' => $nextNumero,
                ':tipo' => (string) $serieRow['tipo'],
                ':serie' => (string) $serieRow['serie'],
            ]);

            $compStmt = $db->prepare("
                INSERT INTO comprobantes_electronicos (
                    venta_id, tipo, serie, numero, numero_completo, estado_sunat,
                    codigo_tipo_documento, referencia_comprobante_id,
                    tipo_nota_credito_id, codigo_tipo_nota_credito,
                    motivo_nota_credito, descripcion_nota_credito,
                    documento_modificado_tipo_documento_codigo,
                    documento_modificado_serie,
                    documento_modificado_numero,
                    documento_modificado_numero_completo,
                    documento_modificado_fecha, created_at
                )
                VALUES (
                    :venta_id, 'nota_credito', :serie, :numero, :numero_completo, 'pendiente',
                    '07', :referencia_id,
                    :tipo_nota_credito_id, :codigo_tipo_nota_credito,
                    :motivo_nota_credito, :descripcion_nota_credito,
                    :doc_mod_tipo,
                    :doc_mod_serie,
                    :doc_mod_numero,
                    :doc_mod_numero_completo,
                    :doc_mod_fecha,
                    CURRENT_TIMESTAMP
                )
                RETURNING id
            ");
            $compStmt->execute([
                ':venta_id' => (int) $origen['venta_id'],
                ':serie' => $serie,
                ':numero' => $nextNumero,
                ':numero_completo' => $numeroCompleto,
                ':referencia_id' => (int) $origen['id'],
                ':tipo_nota_credito_id' => $tipoNotaCreditoId,
                ':codigo_tipo_nota_credito' => $notaCodigo,
                ':motivo_nota_credito' => $tipoNota['descripcion'],
                ':descripcion_nota_credito' => $descripcion,
                ':doc_mod_tipo' => (string) ($origen['codigo_tipo_documento'] ?: '03'),
                ':doc_mod_serie' => (string) ($origen['serie'] ?: ''),
                ':doc_mod_numero' => (string) ($origen['numero'] ?: ''),
                ':doc_mod_numero_completo' => (string) ($origen['numero_completo'] ?: ''),
                ':doc_mod_fecha' => !empty($origen['fecha_emision']) ? $origen['fecha_emision'] : date('Y-m-d'),
            ]);

            $compId = (int) ($compStmt->fetch()['id'] ?? 0);
            if ($compId <= 0) {
                throw new RuntimeException('No se pudo crear la nota de credito.');
            }

            $db->commit();

            registrarAuditoria('Creación de nota de crédito', 'facturacion', "Nota: {$numeroCompleto} | Origen: {$origen['numero_completo']} | Motivo: {$tipoNota['descripcion']}");

            require_once '../../config/sunat.php';
            $compData = [
                'id' => $compId,
                'tipo' => 'nota_credito',
                'serie' => $serie,
                'numero' => $nextNumero,
                'numero_completo' => $numeroCompleto,
                'codigo_tipo_documento' => '07',
            ];
            $ventaInfo = [
                'numero_venta' => (string) $origen['numero_venta'],
                'tipo_pago' => (string) ($origen['tipo_pago'] ?? 'efectivo'),
                'sunat_forma_pago' => 'Contado',
                'fecha_emision' => date('Y-m-d'),
                'hora_emision' => date('H:i:s'),
                'moneda' => 'PEN',
                'totales' => ['total' => (float) $origen['total']],
                'documento_modificado_tipo_documento_codigo' => (string) ($origen['codigo_tipo_documento'] ?: '03'),
                'documento_modificado_numero_completo' => (string) ($origen['numero_completo'] ?: ''),
                'motivo_codigo' => $notaCodigo,
                'motivo_descripcion' => $tipoNota['descripcion'],
            ];

            $resultado = enviar_sunat($db, $compData, $cliente, $items, $ventaInfo);

            if (($resultado['error_nubefact'] ?? true) === false && (string) ($resultado['response_code'] ?? '') === '0') {
                facturacionMarcarOrigenComoAnulado(
                    $db,
                    (int) $origen['id'],
                    (int) $origen['venta_id'],
                    'Anulado con nota de credito'
                );
            }
            jsonResponse([
                'error' => false,
                'message' => 'Nota de credito enviada a SUNAT correctamente.',
                'comprobante' => $resultado,
            ]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            jsonResponse([
                'error' => true,
                'message' => 'Error al emitir nota de credito: ' . $e->getMessage(),
            ], 422);
        }
        break;

    // ---- GET: Reporte de ventas con filtros ----
    case 'reporte':
        header('Content-Type: application/json; charset=UTF-8');

        $params = [':desde' => $desde, ':hasta' => $hasta_dt, ':q' => $q];
        $where_extra = '';
        if ($estado)    { $where_extra .= " AND v.estado = :estado";              $params[':estado']    = $estado; }
        if ($tipo_comp) { $where_extra .= " AND v.tipo_comprobante = :tipo_comp"; $params[':tipo_comp'] = $tipo_comp; }
        if ($tipo_pago) { $where_extra .= " AND v.tipo_pago = :tipo_pago";        $params[':tipo_pago'] = $tipo_pago; }
        if ($vendedor)  { $where_extra .= " AND v.vendedor = :vendedor";          $params[':vendedor']  = $vendedor; }

        // Detectar si existe la tabla comprobantes_electronicos
        $tiene_ce = false;
        try {
            $db->query("SELECT 1 FROM comprobantes_electronicos LIMIT 0");
            $tiene_ce = true;
        } catch (Exception $e) {}

        $join_ce  = $tiene_ce ? "LEFT JOIN comprobantes_electronicos ce ON ce.venta_id = v.id AND ce.tipo IN ('boleta', 'factura')" : "";
        $sel_ce   = $tiene_ce
            ? "COALESCE(ce.id, 0)               AS comprobante_id,
               COALESCE(ce.numero_completo, '') AS comprobante_numero,
               COALESCE(ce.estado_sunat, '')    AS estado_sunat,
               COALESCE(ce.ambiente_sunat, '')  AS ambiente_sunat,
               COALESCE(ce.nubefact_response, '') AS nubefact_response,
               COALESCE(ce.enlace_del_pdf, '')  AS enlace_pdf,
               COALESCE(ce.enlace_del_xml, '')  AS enlace_xml,
               COALESCE(ce.enlace_del_cdr, '')  AS enlace_cdr"
            : "0 AS comprobante_id, '' AS comprobante_numero, '' AS estado_sunat, '' AS ambiente_sunat, '' AS nubefact_response, '' AS enlace_pdf, '' AS enlace_xml, '' AS enlace_cdr";
        $group_ce = $tiene_ce ? ", ce.id, ce.numero_completo, ce.estado_sunat, ce.ambiente_sunat, ce.nubefact_response, ce.enlace_del_pdf, ce.enlace_del_xml, ce.enlace_del_cdr" : "";

        try {
            $stmt = $db->prepare("
                SELECT
                    v.id,
                    v.numero_venta,
                    v.created_at,
                    v.subtotal,
                    v.descuento,
                    v.igv,
                    v.total,
                    v.tipo_pago,
                    v.tipo_comprobante,
                    v.estado,
                    COALESCE(v.vendedor, '—')                                                  AS vendedor,
                    COALESCE(c.nombres || ' ' || COALESCE(c.apellidos, ''), 'Cliente General') AS cliente,
                    COALESCE(c.dni, '') AS dni,
                    COALESCE(c.ruc, '') AS ruc,
                    COUNT(vd.id)        AS num_items,
                    {$sel_ce}
                FROM ventas v
                LEFT JOIN clientes c        ON c.id = v.cliente_id
                LEFT JOIN venta_detalles vd ON vd.venta_id = v.id
                {$join_ce}
                WHERE v.created_at BETWEEN :desde AND :hasta
                  AND (v.numero_venta ILIKE :q
                       OR COALESCE(c.nombres,  '') ILIKE :q
                       OR COALESCE(c.apellidos,'') ILIKE :q
                       OR COALESCE(c.dni,      '') ILIKE :q
                       OR COALESCE(c.ruc,      '') ILIKE :q
                       OR COALESCE(v.vendedor, '') ILIKE :q)
                  {$where_extra}
                GROUP BY v.id, v.vendedor, c.nombres, c.apellidos, c.dni, c.ruc
                         {$group_ce}
                ORDER BY v.created_at DESC
                LIMIT 1000
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => 'Error al cargar reporte: ' . $e->getMessage()], 500);
        }
        break;

    // ---- GET: Exportar a CSV (Excel) ----
    case 'exportar':
        $params = [':desde' => $desde, ':hasta' => $hasta_dt, ':q' => $q];
        $where_extra = '';
        if ($estado)    { $where_extra .= " AND v.estado = :estado";              $params[':estado']    = $estado; }
        if ($tipo_comp) { $where_extra .= " AND v.tipo_comprobante = :tipo_comp"; $params[':tipo_comp'] = $tipo_comp; }
        if ($tipo_pago) { $where_extra .= " AND v.tipo_pago = :tipo_pago";        $params[':tipo_pago'] = $tipo_pago; }
        if ($vendedor)  { $where_extra .= " AND v.vendedor = :vendedor";          $params[':vendedor']  = $vendedor; }

        $tiene_ce_exp = false;
        try {
            $db->query("SELECT 1 FROM comprobantes_electronicos LIMIT 0");
            $tiene_ce_exp = true;
        } catch (Exception $e) {}

        $join_ce_exp  = $tiene_ce_exp ? "LEFT JOIN comprobantes_electronicos ce ON ce.venta_id = v.id AND ce.tipo IN ('boleta', 'factura')" : "";
        $sel_ce_exp   = $tiene_ce_exp
            ? "COALESCE(ce.numero_completo, '') AS \"N° Comprobante\",
               COALESCE(ce.estado_sunat, '')    AS \"Estado SUNAT\","
            : "'' AS \"N° Comprobante\", '' AS \"Estado SUNAT\",";
        $group_ce_exp = $tiene_ce_exp ? ", ce.numero_completo, ce.estado_sunat" : "";

        $stmt = $db->prepare("
            SELECT
                v.numero_venta                                                              AS \"N° Venta\",
                TO_CHAR(v.created_at, 'DD/MM/YYYY')                                        AS \"Fecha\",
                TO_CHAR(v.created_at, 'HH24:MI')                                           AS \"Hora\",
                COALESCE(v.vendedor, 'Sin asignar')                                        AS \"Vendedor\",
                COALESCE(c.nombres || ' ' || COALESCE(c.apellidos,''), 'Cliente General')  AS \"Cliente\",
                COALESCE(c.dni, '')                                                         AS \"DNI\",
                COALESCE(c.ruc, '')                                                         AS \"RUC\",
                COUNT(vd.id)                                                                AS \"N° Items\",
                UPPER(v.tipo_pago)                                                          AS \"Método Pago\",
                UPPER(v.tipo_comprobante)                                                   AS \"Comprobante\",
                {$sel_ce_exp}
                ROUND(v.subtotal::numeric, 2)                                               AS \"Subtotal\",
                ROUND(v.descuento::numeric, 2)                                              AS \"Descuento\",
                ROUND(v.igv::numeric, 2)                                                    AS \"IGV\",
                ROUND(v.total::numeric, 2)                                                  AS \"Total\",
                UPPER(v.estado)                                                             AS \"Estado\"
            FROM ventas v
            LEFT JOIN clientes c        ON c.id = v.cliente_id
            LEFT JOIN venta_detalles vd ON vd.venta_id = v.id
            {$join_ce_exp}
            WHERE v.created_at BETWEEN :desde AND :hasta
              AND (v.numero_venta ILIKE :q
                   OR COALESCE(c.nombres,  '') ILIKE :q
                   OR COALESCE(c.apellidos,'') ILIKE :q
                   OR COALESCE(c.dni,      '') ILIKE :q
                   OR COALESCE(c.ruc,      '') ILIKE :q
                   OR COALESCE(v.vendedor, '') ILIKE :q)
              {$where_extra}
            GROUP BY v.id, v.vendedor, c.nombres, c.apellidos, c.dni, c.ruc
                     {$group_ce_exp}
            ORDER BY v.created_at ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filename = 'ventas_' . str_replace('-', '', $desde) . '_' . str_replace('-', '', $hasta) . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");

        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]), ';');
            foreach ($rows as $row) {
                fputcsv($out, array_values($row), ';');
            }
        } else {
            fputcsv($out, ['Sin resultados para los filtros aplicados'], ';');
        }

        fclose($out);
        exit;

    // ---- GET: Categorías para filtro ----
    case 'reenviar_sunat':
        header('Content-Type: application/json; charset=UTF-8');

        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];
        $comprobanteId = (int) ($data['comprobante_id'] ?? $_POST['comprobante_id'] ?? $_GET['comprobante_id'] ?? 0);

        if ($comprobanteId <= 0) {
            jsonResponse(['error' => true, 'message' => 'Comprobante no valido.'], 422);
        }

        try {
            require_once '../../config/sunat.php';

            $comp = facturacionCargarComprobanteVenta($db, $comprobanteId);
            if (strtolower(trim((string) ($comp['estado'] ?? ''))) !== 'completada') {
                jsonResponse(['error' => true, 'message' => 'El comprobante está anulado; no se puede reenviar.'], 422);
            }
            $responseCode = facturacionExtraerResponseCode($comp['nubefact_response'] ?? null);
            $estadoSunat = strtolower(trim((string) ($comp['estado_sunat'] ?? '')));
            $yaEnviado = !empty($comp['enlace_del_cdr'])
                || $responseCode !== null
                || strpos($estadoSunat, 'acept') !== false
                || strpos($estadoSunat, 'observ') !== false;

            if ($yaEnviado) {
                jsonResponse([
                    'error' => true,
                    'message' => 'Este comprobante ya tiene CDR y no se puede reenviar.',
                ], 422);
            }

            $cliente = facturacionCargarClienteVenta($db, !empty($comp['cliente_id']) ? (int) $comp['cliente_id'] : null);
            $items = facturacionCargarItemsVenta($db, (int) $comp['venta_id']);

            $result = enviar_sunat(
                $db,
                [
                    'id' => (int) $comp['id'],
                    'tipo' => (string) $comp['tipo'],
                    'serie' => (string) $comp['serie'],
                    'numero' => (int) $comp['numero'],
                    'numero_completo' => (string) $comp['numero_completo'],
                    'codigo_tipo_documento' => (string) $comp['codigo_tipo_documento'],
                ],
                $cliente,
                $items,
                [
                    'numero_venta' => (string) $comp['numero_venta'],
                    'tipo_pago' => (string) $comp['tipo_pago'],
                    'sunat_forma_pago' => (string) ($comp['sunat_forma_pago'] ?: 'Contado'),
                    'fecha_emision' => (string) $comp['fecha_emision'],
                    'hora_emision' => (string) $comp['hora_emision'],
                    'moneda' => (string) ($comp['moneda_codigo'] ?: 'PEN'),
                    'totales' => ['total' => (float) $comp['total']],
                    'documento_modificado_tipo_documento_codigo' => (string) ($comp['documento_modificado_tipo_documento_codigo'] ?? ''),
                    'documento_modificado_numero_completo' => (string) ($comp['documento_modificado_numero_completo'] ?? ''),
                    'motivo_codigo' => (string) ($comp['codigo_tipo_nota_credito'] ?? ''),
                    'motivo_descripcion' => (string) ($comp['motivo_nota_credito'] ?? ''),
                ]
            );

            // Registrar el intento manual en las columnas de tracking (igual
            // que hace el worker). Antes solo el worker incrementaba el
            // contador, entonces los reintentos manuales no se reflejaban en
            // la UI (0/20 estancado).
            if (!empty($result['error_nubefact'])) {
                $db->prepare("
                    UPDATE comprobantes_electronicos
                    SET reenvio_intentos    = COALESCE(reenvio_intentos, 0) + 1,
                        reenvio_ultimo_at   = NOW(),
                        reenvio_ultimo_error = :err
                    WHERE id = :id
                ")->execute([
                    ':err' => (string) ($result['estado_sunat'] ?? $result['mensaje'] ?? 'Error desconocido'),
                    ':id'  => (int) $comp['id'],
                ]);

                jsonResponse([
                    'error' => true,
                    'message' => 'SUNAT rechazó el comprobante: ' . (string) ($result['estado_sunat'] ?? $result['mensaje'] ?? 'Error desconocido'),
                    'comprobante' => $result,
                ], 422);
            }

            // Exito: tambien registrar y limpiar la programacion
            $db->prepare("
                UPDATE comprobantes_electronicos
                SET reenvio_intentos    = COALESCE(reenvio_intentos, 0) + 1,
                    reenvio_ultimo_at   = NOW(),
                    reenvio_proximo_at  = NULL,
                    reenvio_ultimo_error = NULL
                WHERE id = :id
            ")->execute([':id' => (int) $comp['id']]);

            registrarAuditoria('Reenvío de comprobante a SUNAT', 'facturacion', "Comprobante: {$comp['numero_completo']}");

            jsonResponse([
                'error' => false,
                'message' => 'Comprobante enviado a SUNAT correctamente.',
                'comprobante' => $result,
            ]);
        } catch (Throwable $e) {
            jsonResponse([
                'error' => true,
                'message' => 'Error SUNAT: ' . $e->getMessage(),
            ], 422);
        }
        break;

    case 'categorias_lista':
        header('Content-Type: application/json; charset=UTF-8');
        try {
            echo json_encode($db->query(
                "SELECT id, nombre FROM public.categorias WHERE activo = TRUE ORDER BY nombre"
            )->fetchAll());
        } catch (Exception $e) { echo json_encode([]); }
        break;

    // ---- GET: Stats globales de rentabilidad ----
    case 'rentabilidad_stats':
        header('Content-Type: application/json; charset=UTF-8');

        $params = [':desde' => $desde, ':hasta' => $hasta_dt];
        $where  = '';
        if ($vendedor)     { $where .= " AND v.vendedor = :vendedor";       $params[':vendedor']     = $vendedor; }
        if ($categoria_id) { $where .= " AND p.categoria_id = :cat_id";    $params[':cat_id']       = $categoria_id; }
        $where .= rentFiltrosExtra($params);

        try {
            $stmt = $db->prepare("
                SELECT
                    COALESCE(SUM(vd.subtotal), 0)                                                       AS total_ingresos,
                    COALESCE(SUM(vd.cantidad * p.precio_compra), 0)                                     AS total_costo,
                    COALESCE(SUM(vd.subtotal - vd.cantidad * p.precio_compra), 0)                       AS ganancia_bruta,
                    COALESCE(SUM(vd.cantidad), 0)                                                       AS total_unidades,
                    COUNT(DISTINCT v.id)                                                                AS total_ventas,
                    ROUND(COALESCE(
                        SUM(vd.subtotal - vd.cantidad * p.precio_compra) / NULLIF(SUM(vd.subtotal), 0) * 100
                    , 0)::numeric, 2)                                                                   AS margen_pct,
                    ROUND(COALESCE(
                        SUM(vd.subtotal - vd.cantidad * p.precio_compra) / NULLIF(SUM(vd.cantidad * p.precio_compra), 0) * 100
                    , 0)::numeric, 2)                                                                   AS roi_pct
                FROM ventas v
                JOIN venta_detalles vd ON vd.venta_id = v.id
                JOIN productos p       ON p.id = vd.producto_id
                WHERE v.estado = 'completada'
                  AND v.created_at BETWEEN :desde AND :hasta
                $where
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetch());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    // ---- GET: Rentabilidad por categoría ----
    case 'rentabilidad_categorias':
        header('Content-Type: application/json; charset=UTF-8');

        $params = [':desde' => $desde, ':hasta' => $hasta_dt];
        $where  = '';
        if ($vendedor)     { $where .= " AND v.vendedor = :vendedor";    $params[':vendedor'] = $vendedor; }
        if ($categoria_id) { $where .= " AND p.categoria_id = :cat_id"; $params[':cat_id']   = $categoria_id; }
        $where .= rentFiltrosExtra($params);

        try {
            $stmt = $db->prepare("
                SELECT
                    COALESCE(cat.nombre, 'Sin categoría')                                               AS categoria,
                    COUNT(DISTINCT v.id)                                                                AS num_ventas,
                    COALESCE(SUM(vd.cantidad), 0)                                                       AS unidades,
                    ROUND(COALESCE(SUM(vd.subtotal), 0)::numeric, 2)                                    AS ingresos,
                    ROUND(COALESCE(SUM(vd.cantidad * p.precio_compra), 0)::numeric, 2)                  AS costo,
                    ROUND(COALESCE(SUM(vd.subtotal - vd.cantidad * p.precio_compra), 0)::numeric, 2)    AS ganancia,
                    ROUND(COALESCE(
                        SUM(vd.subtotal - vd.cantidad * p.precio_compra) / NULLIF(SUM(vd.subtotal), 0) * 100
                    , 0)::numeric, 2)                                                                   AS margen_pct
                FROM ventas v
                JOIN venta_detalles vd ON vd.venta_id = v.id
                JOIN productos p       ON p.id = vd.producto_id
                LEFT JOIN public.categorias cat ON cat.id = p.categoria_id
                WHERE v.estado = 'completada'
                  AND v.created_at BETWEEN :desde AND :hasta
                $where
                GROUP BY COALESCE(cat.nombre, 'Sin categoría')
                ORDER BY ganancia DESC
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    // ---- GET: Rentabilidad por producto ----
    case 'rentabilidad_productos':
        header('Content-Type: application/json; charset=UTF-8');

        $params = [':desde' => $desde, ':hasta' => $hasta_dt];
        $where  = '';
        if ($vendedor)     { $where .= " AND v.vendedor = :vendedor";    $params[':vendedor'] = $vendedor; }
        if ($categoria_id) { $where .= " AND p.categoria_id = :cat_id"; $params[':cat_id']   = $categoria_id; }
        $where .= rentFiltrosExtra($params);

        // Margen minimo/maximo: se evalua sobre el margen agregado del producto.
        $having = [];
        $margenExpr = "COALESCE(SUM(vd.subtotal - vd.cantidad * p.precio_compra) / NULLIF(SUM(vd.subtotal), 0) * 100, 0)";
        if (isset($_GET['margen_min']) && is_numeric($_GET['margen_min'])) {
            $having[] = "$margenExpr >= :m_min"; $params[':m_min'] = floatval($_GET['margen_min']);
        }
        if (isset($_GET['margen_max']) && is_numeric($_GET['margen_max'])) {
            $having[] = "$margenExpr <= :m_max"; $params[':m_max'] = floatval($_GET['margen_max']);
        }
        $having = $having ? 'HAVING ' . implode(' AND ', $having) : '';

        try {
            $stmt = $db->prepare("
                SELECT
                    p.id,
                    p.nombre                                                                            AS producto,
                    p.codigo,
                    p.precio_compra                                                                     AS costo_unitario,
                    COALESCE(cat.nombre, 'Sin categoría')                                               AS categoria,
                    COALESCE(SUM(vd.cantidad), 0)                                                       AS unidades,
                    ROUND(COALESCE(SUM(vd.subtotal), 0)::numeric, 2)                                    AS ingresos,
                    ROUND(COALESCE(SUM(vd.cantidad * p.precio_compra), 0)::numeric, 2)                  AS costo,
                    ROUND(COALESCE(SUM(vd.subtotal - vd.cantidad * p.precio_compra), 0)::numeric, 2)    AS ganancia,
                    ROUND(COALESCE(
                        SUM(vd.subtotal - vd.cantidad * p.precio_compra) / NULLIF(SUM(vd.subtotal), 0) * 100
                    , 0)::numeric, 2)                                                                   AS margen_pct,
                    ROUND(COALESCE(
                        SUM(vd.subtotal - vd.cantidad * p.precio_compra) / NULLIF(SUM(vd.cantidad * p.precio_compra), 0) * 100
                    , 0)::numeric, 2)                                                                   AS roi_pct,
                    p.laboratorio
                FROM ventas v
                JOIN venta_detalles vd ON vd.venta_id = v.id
                JOIN productos p       ON p.id = vd.producto_id
                LEFT JOIN public.categorias cat ON cat.id = p.categoria_id
                WHERE v.estado = 'completada'
                  AND v.created_at BETWEEN :desde AND :hasta
                $where
                GROUP BY p.id, p.nombre, p.codigo, p.precio_compra, p.laboratorio, cat.nombre
                $having
                ORDER BY ganancia DESC
                LIMIT 2000
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    // ---- GET: Tendencia diaria de ganancia ----
    case 'rentabilidad_tendencia':
        header('Content-Type: application/json; charset=UTF-8');

        $params = [':desde' => $desde, ':hasta' => $hasta_dt];
        $where  = '';
        if ($vendedor)     { $where .= " AND v.vendedor = :vendedor";    $params[':vendedor'] = $vendedor; }
        if ($categoria_id) { $where .= " AND p.categoria_id = :cat_id"; $params[':cat_id']   = $categoria_id; }
        $where .= rentFiltrosExtra($params);

        // Agrupar por día o semana según el rango
        $dias = max(1, (strtotime($hasta) - strtotime($desde)) / 86400);
        $trunc = $dias > 62 ? 'week' : 'day';

        try {
            $stmt = $db->prepare("
                SELECT
                    DATE_TRUNC('$trunc', v.created_at)::date                                            AS fecha,
                    ROUND(SUM(vd.subtotal)::numeric, 2)                                                 AS ingresos,
                    ROUND(SUM(vd.cantidad * p.precio_compra)::numeric, 2)                               AS costo,
                    ROUND(SUM(vd.subtotal - vd.cantidad * p.precio_compra)::numeric, 2)                 AS ganancia
                FROM ventas v
                JOIN venta_detalles vd ON vd.venta_id = v.id
                JOIN productos p       ON p.id = vd.producto_id
                WHERE v.estado = 'completada'
                  AND v.created_at BETWEEN :desde AND :hasta
                $where
                GROUP BY DATE_TRUNC('$trunc', v.created_at)::date
                ORDER BY fecha ASC
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    // ---- GET: Comprobantes SIN CDR (viejos + nuevos), con filtros ----
    case 'reenvios_pendientes':
        header('Content-Type: application/json; charset=UTF-8');
        try {
            // Un comprobante NO está pendiente si ya tiene CDR, o si su estado
            // dice "aceptado"/"observado" (SUNAT ya lo recibió aunque el enlace
            // CDR no se haya guardado por un fallo intermitente).
            $where  = [
                "COALESCE(ce.enlace_del_cdr,'') = ''",
                "LOWER(COALESCE(ce.estado_sunat,'')) NOT LIKE '%acept%'",
                "LOWER(COALESCE(ce.estado_sunat,'')) NOT LIKE '%observ%'",
            ];
            $params = [];

            $fDesde = trim((string) ($_GET['desde'] ?? ''));
            $fHasta = trim((string) ($_GET['hasta'] ?? ''));
            $fTipo  = trim((string) ($_GET['tipo']  ?? ''));
            $fEstado = trim((string) ($_GET['estado'] ?? ''));

            if ($fDesde !== '') { $where[] = "v.created_at >= :desde";                 $params[':desde'] = $fDesde . ' 00:00:00'; }
            if ($fHasta !== '') { $where[] = "v.created_at <= :hasta";                 $params[':hasta'] = $fHasta . ' 23:59:59'; }
            if ($fTipo  !== '') { $where[] = "ce.tipo = :tipo";                        $params[':tipo']  = $fTipo; }

            // Filtro por estado derivado (programado/pendiente/agotado/rechazado)
            if ($fEstado === 'programado')  { $where[] = "ce.reenvio_proximo_at IS NOT NULL"; }
            if ($fEstado === 'pendiente')   { $where[] = "ce.reenvio_proximo_at IS NULL AND COALESCE(ce.reenvio_intentos,0) = 0"; }
            if ($fEstado === 'agotado')     { $where[] = "ce.reenvio_proximo_at IS NULL AND COALESCE(ce.reenvio_intentos,0) >= 20"; }
            if ($fEstado === 'rechazado')   { $where[] = "ce.reenvio_proximo_at IS NULL AND COALESCE(ce.reenvio_intentos,0) > 0 AND COALESCE(ce.reenvio_intentos,0) < 20"; }

            $limit    = 1000;
            $whereSql = implode(' AND ', $where);

            // Total real sin LIMIT (para mostrar "mostrando N de M")
            $countStmt = $db->prepare("
                SELECT COUNT(*)
                FROM comprobantes_electronicos ce
                INNER JOIN ventas v ON v.id = ce.venta_id
                WHERE $whereSql
            ");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $sql = "
                SELECT
                    ce.id,
                    ce.numero_completo,
                    ce.tipo,
                    ce.estado_sunat,
                    ce.ambiente_sunat,
                    ce.reenvio_intentos,
                    ce.reenvio_proximo_at,
                    ce.reenvio_ultimo_at,
                    ce.reenvio_ultimo_error,
                    ce.created_at AS comprobante_created_at,
                    v.created_at AS venta_created_at,
                    v.numero_venta,
                    v.total,
                    COALESCE(c.nombre_completo, c.razon_social, TRIM(COALESCE(c.nombres,'') || ' ' || COALESCE(c.apellidos,''))) AS cliente,
                    COALESCE(c.numero_documento, c.ruc, c.dni, '') AS documento,
                    CASE
                        WHEN COALESCE(ce.enlace_del_cdr,'') <> '' THEN 'aceptado'
                        WHEN ce.reenvio_proximo_at IS NULL AND COALESCE(ce.reenvio_intentos,0) >= 20 THEN 'agotado'
                        WHEN ce.reenvio_proximo_at IS NULL AND COALESCE(ce.reenvio_intentos,0) > 0 THEN 'rechazado'
                        WHEN ce.reenvio_proximo_at IS NOT NULL THEN 'programado'
                        ELSE 'pendiente'
                    END AS estado_reenvio
                FROM comprobantes_electronicos ce
                INNER JOIN ventas v ON v.id = ce.venta_id
                LEFT JOIN clientes c ON c.id = v.cliente_id
                WHERE $whereSql
                ORDER BY v.created_at DESC
                LIMIT $limit
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            echo json_encode([
                'total' => $total,
                'limit' => $limit,
                'rows'  => $rows,
            ]);
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    // ---- POST: Cancelar programacion del worker en los seleccionados ----
    //         (los deja en la lista pero sin reenvio_proximo_at, para enviar
    //          manualmente con el boton "Reintentar ahora")
    case 'cancelar_programacion_batch':
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: [];
            $ids  = array_values(array_filter(array_map('intval', (array) ($data['comprobante_ids'] ?? []))));

            if (!$ids) {
                jsonResponse(['error' => true, 'message' => 'Debes seleccionar al menos un comprobante.'], 422);
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("
                UPDATE comprobantes_electronicos
                SET reenvio_proximo_at = NULL
                WHERE id IN ($placeholders)
                  AND reenvio_proximo_at IS NOT NULL
            ");
            $stmt->execute($ids);
            $afectados = $stmt->rowCount();

            registrarAuditoria('Cancelación de programación SUNAT', 'facturacion', "Comprobantes: {$afectados}/" . count($ids));

            jsonResponse([
                'error' => false,
                'message' => "Programación cancelada en {$afectados} comprobante(s). Puedes enviarlos manualmente cuando quieras.",
                'cancelados' => $afectados,
                'solicitados' => count($ids),
            ]);
        } catch (Throwable $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    // ---- POST: Programar reintento masivo (los recoge el worker) ----
    case 'reenviar_sunat_batch':
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: [];
            $ids  = array_values(array_filter(array_map('intval', (array) ($data['comprobante_ids'] ?? []))));

            if (!$ids) {
                jsonResponse(['error' => true, 'message' => 'Debes seleccionar al menos un comprobante.'], 422);
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("
                UPDATE comprobantes_electronicos
                SET reenvio_proximo_at = NOW(),
                    reenvio_intentos   = 0,
                    reenvio_ultimo_error = NULL
                WHERE id IN ($placeholders)
                  AND COALESCE(enlace_del_cdr, '') = ''
            ");
            $stmt->execute($ids);
            $afectados = $stmt->rowCount();

            registrarAuditoria('Programación masiva de reenvío SUNAT', 'facturacion', "Comprobantes: {$afectados}/" . count($ids));

            jsonResponse([
                'error' => false,
                'message' => "Programados {$afectados} comprobante(s) para reintento. El worker los recogerá en los próximos minutos.",
                'programados' => $afectados,
                'solicitados' => count($ids),
            ]);
        } catch (Throwable $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    // ---- POST: Descargar ZIP maestro con los ZIP firmados de los seleccionados ----
    //         (para subir manualmente al portal SUNAT SOL)
    case 'descargar_sunat_pending_zip':
        try {
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: [];
            $ids  = array_values(array_filter(array_map('intval', (array) ($data['comprobante_ids'] ?? []))));

            if (!$ids) {
                header('Content-Type: application/json; charset=UTF-8');
                jsonResponse(['error' => true, 'message' => 'Debes seleccionar al menos un comprobante.'], 422);
            }
            // Tope defensivo: 100 comprobantes por descarga (evita ZIPs enormes).
            if (count($ids) > 100) {
                header('Content-Type: application/json; charset=UTF-8');
                jsonResponse(['error' => true, 'message' => 'Máximo 100 comprobantes por descarga.'], 422);
            }

            require_once '../../config/sunat.php';
            require_once __DIR__ . '/_data_helpers.php';

            $facturacionDir = realpath(__DIR__ . '/../../facturacion') ?: (__DIR__ . '/../../facturacion');
            $xmlStorageDir  = $facturacionDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'xml';

            $tmpZip = tempnam(sys_get_temp_dir(), 'sunat_bundle_') . '.zip';
            $master = new ZipArchive();
            if ($master->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                @unlink($tmpZip);
                header('Content-Type: application/json; charset=UTF-8');
                jsonResponse(['error' => true, 'message' => 'No se pudo crear el ZIP maestro'], 500);
            }

            $incluidos = 0;
            $fallos    = [];
            foreach ($ids as $compId) {
                try {
                    $comp = facturacionCargarComprobanteVenta($db, (int) $compId);

                    // 1) Intentar encontrar el ZIP ya firmado en disco
                    $xmlUrl = trim((string) ($comp['enlace_del_xml'] ?? ''));
                    $zipPath = null;
                    if ($xmlUrl !== '') {
                        $xmlName = rawurldecode(basename($xmlUrl));
                        // El ZIP comparte base con el XML, solo cambia la extension
                        $candidateZip = $xmlStorageDir . DIRECTORY_SEPARATOR
                            . preg_replace('/\.XML$/i', '.ZIP', $xmlName);
                        if (is_file($candidateZip)) {
                            $zipPath = $candidateZip;
                        }
                    }

                    // 2) Si no esta en disco, regenerarlo on-demand sin tocar SUNAT
                    if (!$zipPath) {
                        $cliente = facturacionCargarClienteVenta($db, !empty($comp['cliente_id']) ? (int) $comp['cliente_id'] : null);
                        $items   = facturacionCargarItemsVenta($db, (int) $comp['venta_id']);

                        $gen = sunat_generate_signed_zip(
                            $db,
                            [
                                'id'                    => (int) $comp['id'],
                                'tipo'                  => (string) $comp['tipo'],
                                'serie'                 => (string) $comp['serie'],
                                'numero'                => (int) $comp['numero'],
                                'numero_completo'       => (string) $comp['numero_completo'],
                                'codigo_tipo_documento' => (string) $comp['codigo_tipo_documento'],
                            ],
                            $cliente,
                            $items,
                            [
                                'numero_venta'      => (string) $comp['numero_venta'],
                                'tipo_pago'         => (string) $comp['tipo_pago'],
                                'sunat_forma_pago'  => (string) ($comp['sunat_forma_pago'] ?: 'Contado'),
                                'fecha_emision'     => (string) $comp['fecha_emision'],
                                'hora_emision'      => (string) $comp['hora_emision'],
                                'moneda'            => (string) ($comp['moneda_codigo'] ?: 'PEN'),
                                'totales'           => ['total' => (float) $comp['total']],
                                'documento_modificado_tipo_documento_codigo' => (string) ($comp['documento_modificado_tipo_documento_codigo'] ?? ''),
                                'documento_modificado_numero_completo'       => (string) ($comp['documento_modificado_numero_completo'] ?? ''),
                                'motivo_codigo'      => (string) ($comp['codigo_tipo_nota_credito'] ?? ''),
                                'motivo_descripcion' => (string) ($comp['motivo_nota_credito'] ?? ''),
                            ]
                        );
                        $zipPath = $gen['zip_path'];
                    }

                    if (!is_file($zipPath)) {
                        $fallos[] = ($comp['numero_completo'] ?? "ID {$compId}") . ': ZIP no disponible';
                        continue;
                    }

                    $entryName = basename($zipPath);
                    $master->addFile($zipPath, $entryName);
                    $incluidos++;
                } catch (Throwable $e) {
                    $fallos[] = "ID {$compId}: " . $e->getMessage();
                }
            }

            // Si hubo fallos, agregar un README dentro del ZIP maestro
            if ($fallos) {
                $master->addFromString(
                    'LEEME_fallos.txt',
                    "Los siguientes comprobantes no se pudieron incluir:\n\n" . implode("\n", $fallos) . "\n"
                );
            }

            $master->close();

            if ($incluidos === 0) {
                @unlink($tmpZip);
                header('Content-Type: application/json; charset=UTF-8');
                jsonResponse([
                    'error' => true,
                    'message' => 'Ningún comprobante se pudo preparar. Detalle: ' . implode(' | ', $fallos),
                ], 500);
            }

            registrarAuditoria('Descarga manual de ZIP SUNAT', 'facturacion', "Incluidos: {$incluidos}, fallidos: " . count($fallos));

            $downloadName = 'sunat_pendientes_' . date('Ymd_His') . '.zip';

            while (ob_get_level() > 0) { ob_end_clean(); }

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $downloadName . '"');
            header('Content-Length: ' . filesize($tmpZip));
            header('Cache-Control: no-store');
            readfile($tmpZip);
            @unlink($tmpZip);
        } catch (Throwable $e) {
            header('Content-Type: application/json; charset=UTF-8');
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    // ---- GET: Stats agregadas de reenvios para la cabecera del tab ----
    case 'reenvios_stats':
        header('Content-Type: application/json; charset=UTF-8');
        try {
            // Mismo criterio de "pendiente" que reenvios_pendientes
            $stmt = $db->query("
                WITH base AS (
                    SELECT *
                    FROM comprobantes_electronicos
                    WHERE COALESCE(enlace_del_cdr,'') = ''
                      AND LOWER(COALESCE(estado_sunat,'')) NOT LIKE '%acept%'
                      AND LOWER(COALESCE(estado_sunat,'')) NOT LIKE '%observ%'
                )
                SELECT
                    COUNT(*) FILTER (WHERE reenvio_proximo_at IS NOT NULL) AS programados,
                    COUNT(*) FILTER (WHERE reenvio_proximo_at IS NULL AND COALESCE(reenvio_intentos,0) >= 20) AS agotados,
                    COUNT(*) FILTER (WHERE reenvio_proximo_at IS NULL AND COALESCE(reenvio_intentos,0) > 0 AND COALESCE(reenvio_intentos,0) < 20) AS rechazados,
                    MIN(reenvio_proximo_at) FILTER (WHERE reenvio_proximo_at IS NOT NULL) AS proximo_at
                FROM base
            ");
            echo json_encode($stmt->fetch());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    // ---- GET: Descargar ZIP con XML + CDR de un comprobante ----
    case 'descargar_sunat_zip':
        $comprobanteId = (int) ($_GET['comprobante_id'] ?? 0);
        if ($comprobanteId <= 0) {
            header('Content-Type: application/json; charset=UTF-8');
            jsonResponse(['error' => true, 'message' => 'comprobante_id requerido'], 400);
            break;
        }

        try {
            $stmt = $db->prepare("
                SELECT numero_completo, enlace_del_xml, enlace_del_cdr
                FROM comprobantes_electronicos
                WHERE id = :id
            ");
            $stmt->execute([':id' => $comprobanteId]);
            $comp = $stmt->fetch();

            if (!$comp) {
                header('Content-Type: application/json; charset=UTF-8');
                jsonResponse(['error' => true, 'message' => 'Comprobante no encontrado'], 404);
                break;
            }

            $facturacionDir = realpath(__DIR__ . '/../../facturacion') ?: (__DIR__ . '/../../facturacion');
            $xmlDir = $facturacionDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'xml';
            $cdrDir = $facturacionDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cdr';

            $files = [];
            $xmlUrl = trim((string) ($comp['enlace_del_xml'] ?? ''));
            if ($xmlUrl !== '') {
                $xmlName = rawurldecode(basename($xmlUrl));
                $xmlPath = $xmlDir . DIRECTORY_SEPARATOR . $xmlName;
                if (is_file($xmlPath)) {
                    $files[$xmlName] = $xmlPath;
                }
            }
            $cdrUrl = trim((string) ($comp['enlace_del_cdr'] ?? ''));
            if ($cdrUrl !== '') {
                $cdrName = rawurldecode(basename($cdrUrl));
                $cdrPath = $cdrDir . DIRECTORY_SEPARATOR . $cdrName;
                if (is_file($cdrPath)) {
                    $files[$cdrName] = $cdrPath;
                }
            }

            if (!$files) {
                header('Content-Type: application/json; charset=UTF-8');
                jsonResponse(['error' => true, 'message' => 'No hay XML ni CDR disponibles para este comprobante'], 404);
                break;
            }

            $tmpZip = tempnam(sys_get_temp_dir(), 'sunat_') . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                @unlink($tmpZip);
                header('Content-Type: application/json; charset=UTF-8');
                jsonResponse(['error' => true, 'message' => 'No se pudo crear el ZIP temporal'], 500);
                break;
            }
            foreach ($files as $name => $path) {
                $zip->addFile($path, $name);
            }
            $zip->close();

            $numero = (string) ($comp['numero_completo'] ?? '');
            $baseName = preg_replace('/[^A-Za-z0-9._-]/', '_', $numero);
            if ($baseName === '' || $baseName === null) {
                $baseName = 'comprobante_' . $comprobanteId;
            }
            $downloadName = $baseName . '_sunat.zip';

            while (ob_get_level() > 0) { ob_end_clean(); }

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $downloadName . '"');
            header('Content-Length: ' . filesize($tmpZip));
            header('Cache-Control: no-store');
            readfile($tmpZip);
            @unlink($tmpZip);
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=UTF-8');
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    // Emitir boleta/factura a SUNAT a partir de una nota de venta (ticket) ya
    // registrada. La logica vive en facturacionEmitirComprobanteDesdeVenta()
    // (modules/facturacion/_data_helpers.php), compartida con el script CLI
    // scripts/emitir_notas_venta.php. OJO plazo SUNAT: se emite con la fecha
    // original de la venta (ver el helper).
    case 'emitir_desde_venta':
        header('Content-Type: application/json; charset=UTF-8');

        $data         = json_decode(file_get_contents('php://input'), true) ?: [];
        $ventaId      = (int) ($data['venta_id'] ?? 0);
        $tipo         = strtolower(trim((string) ($data['tipo'] ?? 'boleta')));
        $clienteIdSel = !empty($data['cliente_id']) ? (int) $data['cliente_id'] : null;

        if ($ventaId <= 0) {
            jsonResponse(['error' => true, 'message' => 'Venta no válida.'], 422);
        }
        if (!in_array($tipo, ['boleta', 'factura'], true)) {
            jsonResponse(['error' => true, 'message' => 'Tipo de comprobante inválido.'], 422);
        }

        try {
            $r = facturacionEmitirComprobanteDesdeVenta($db, $ventaId, $tipo, $clienteIdSel);
        } catch (RuntimeException $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 422);
        }

        registrarAuditoria('Emisión de comprobante desde nota de venta', 'facturacion',
            "Venta #{$ventaId} -> {$r['numero_completo']} ({$r['tipo']})");

        jsonResponse([
            'error'           => false,
            'message'         => $r['sunat_ok']
                ? "Comprobante {$r['numero_completo']} emitido y aceptado por SUNAT."
                : "Comprobante {$r['numero_completo']} generado. SUNAT: " . ($r['mensaje'] ?? $r['estado_sunat']) . ". Puedes reintentar con \"Reenviar a SUNAT\".",
            'numero_completo' => $r['numero_completo'],
            'sunat_estado'    => $r['estado_sunat'],
            'sunat_ok'        => $r['sunat_ok'],
        ]);

    default:
        header('Content-Type: application/json; charset=UTF-8');
        jsonResponse(['error' => true, 'message' => 'Acción no válida'], 404);
}
