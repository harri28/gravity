<?php
// ============================================================
// ARCHIVO: farmacia/modules/facturacion/_data_helpers.php
// DESCRIPCION: Helpers de lectura compartidos entre el api.php del
//              modulo y el worker scripts/reenviar_sunat_pendientes.php.
//              Separados aqui para evitar duplicacion.
// ============================================================

function facturacionNormalizarAfectacionTipo(?string $tipo, ?string $codigo): string
{
    $tipo = strtoupper(trim((string) $tipo));
    if ($tipo !== '') {
        return $tipo;
    }

    switch (trim((string) $codigo)) {
        case '20': case '21': return 'EXO';
        case '30': case '31': case '32': case '33': case '34': case '35': case '36': return 'INA';
        case '40': return 'EXP';
        default:   return 'GRAV';
    }
}

function facturacionCargarComprobanteVenta(PDO $db, int $comprobanteId): array
{
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
            ce.referencia_comprobante_id,
            ce.tipo_nota_credito_id,
            ce.codigo_tipo_nota_credito,
            ce.motivo_nota_credito,
            ce.descripcion_nota_credito,
            ce.documento_modificado_tipo_documento_codigo,
            ce.documento_modificado_serie,
            ce.documento_modificado_numero,
            ce.documento_modificado_numero_completo,
            ce.documento_modificado_fecha,
            v.numero_venta,
            v.tipo_pago,
            v.sunat_forma_pago,
            v.fecha_emision,
            v.hora_emision,
            v.moneda_codigo,
            v.total,
            v.cliente_id
        FROM comprobantes_electronicos ce
        INNER JOIN ventas v ON v.id = ce.venta_id
        WHERE ce.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $comprobanteId]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new RuntimeException('No se encontro el comprobante seleccionado.');
    }

    return $row;
}

function facturacionCargarClienteVenta(PDO $db, ?int $clienteId): ?array
{
    if (!$clienteId) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT
            id, nombres, apellidos, dni, ruc, telefono, email, direccion,
            tipo_documento_id, tipo_documento_codigo, numero_documento,
            nombre_completo, razon_social, ubigeo
        FROM clientes
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $clienteId]);

    return $stmt->fetch() ?: null;
}

function facturacionCargarItemsVenta(PDO $db, int $ventaId): array
{
    $stmt = $db->prepare("
        SELECT
            vd.producto_id,
            COALESCE(vd.descripcion, p.nombre, 'Producto') AS nombre,
            COALESCE(vd.codigo_interno, p.codigo_interno, p.codigo, '') AS codigo,
            vd.codigo_sunat,
            vd.cantidad,
            vd.precio_unitario,
            vd.valor_unitario,
            vd.valor_total,
            vd.precio_total,
            vd.igv,
            vd.unidad_codigo,
            vd.afectacion_igv_codigo,
            COALESCE(a.tipo, '') AS afectacion_tipo,
            CASE
                WHEN COALESCE(vd.igv, 0) > 0 AND COALESCE(vd.valor_total, 0) > 0
                    THEN ROUND((vd.igv / NULLIF(vd.valor_total, 0)) * 100, 2)
                ELSE 0
            END AS porcentaje_igv
        FROM venta_detalles vd
        LEFT JOIN productos p ON p.id = vd.producto_id
        LEFT JOIN public.fe_tipos_afectacion_igv a ON a.id = vd.afectacion_igv_id
        WHERE vd.venta_id = :venta_id
        ORDER BY vd.id
    ");
    $stmt->execute([':venta_id' => $ventaId]);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['afectacion_tipo'] = facturacionNormalizarAfectacionTipo(
            $row['afectacion_tipo'] ?? null,
            $row['afectacion_igv_codigo'] ?? null
        );
        $items[] = $row;
    }

    if (!$items) {
        throw new RuntimeException('La venta no tiene detalle para reenviar a SUNAT.');
    }

    return $items;
}

/**
 * Emite un comprobante electronico (boleta/factura) A PARTIR de una nota de
 * venta (ticket) ya registrada: convierte el ticket, reserva serie B001/F001,
 * crea la fila en comprobantes_electronicos y lo envia a SUNAT (mismo pipeline
 * que una venta nueva). El stock NO se toca (ya se desconto al crear el ticket).
 * Usa la FECHA ORIGINAL de la venta.
 *
 * $tipoSolicitado: 'boleta' | 'factura' | 'auto' (auto = factura si el cliente
 * tiene RUC, si no boleta). Lanza RuntimeException en errores de validacion.
 * Devuelve: ['cpe_id','tipo','numero_completo','sunat_ok','estado_sunat','mensaje','response_code'].
 *
 * Requisitos del caller: config/sunat.php y config/database.php cargados, y el
 * search_path del PDO apuntando al schema de la sucursal del comprobante.
 */
function facturacionEmitirComprobanteDesdeVenta(PDO $db, int $ventaId, string $tipoSolicitado = 'auto', ?int $clienteIdSel = null): array
{
    require_once __DIR__ . '/../../config/sunat.php';

    $tipoSolicitado = strtolower(trim($tipoSolicitado));
    if (!in_array($tipoSolicitado, ['boleta', 'factura', 'auto'], true)) {
        throw new RuntimeException('Tipo de comprobante inválido.');
    }

    $vStmt = $db->prepare("
        SELECT id, numero_venta, tipo_comprobante, estado, cliente_id, total,
               tipo_pago, sunat_forma_pago, fecha_emision, hora_emision, moneda_codigo, created_at
        FROM ventas WHERE id = :id
    ");
    $vStmt->execute([':id' => $ventaId]);
    $venta = $vStmt->fetch();
    if (!$venta) {
        throw new RuntimeException('No se encontró la venta.');
    }
    if ((string) $venta['estado'] !== 'completada') {
        throw new RuntimeException('La venta no está completada (anulada).');
    }
    if (in_array((string) $venta['tipo_comprobante'], ['boleta', 'factura'], true)) {
        throw new RuntimeException('La venta ya es un comprobante electrónico.');
    }
    $ceChk = $db->prepare("SELECT id FROM comprobantes_electronicos WHERE venta_id = :v AND tipo IN ('boleta','factura') LIMIT 1");
    $ceChk->execute([':v' => $ventaId]);
    if ($ceChk->fetch()) {
        throw new RuntimeException('La venta ya tiene un comprobante electrónico.');
    }

    // Resolver cliente (elegido > de la venta) y decidir tipo si es 'auto'
    $clienteId = $clienteIdSel ?: (!empty($venta['cliente_id']) ? (int) $venta['cliente_id'] : null);
    $cliente   = facturacionCargarClienteVenta($db, $clienteId);
    $rucCli    = trim((string) ($cliente['ruc'] ?? ''));

    $tipo = $tipoSolicitado === 'auto' ? ($rucCli !== '' ? 'factura' : 'boleta') : $tipoSolicitado;

    if ($tipo === 'factura' && $rucCli === '') {
        throw new RuntimeException('Para factura la venta debe tener un cliente con RUC.');
    }
    if ($tipo === 'boleta' && !$cliente) {
        $cliente = $db->query("
            SELECT id, nombres, apellidos, dni, ruc, telefono, email, direccion,
                   tipo_documento_id, tipo_documento_codigo, numero_documento,
                   nombre_completo, razon_social, ubigeo
            FROM clientes
            WHERE COALESCE(numero_documento,'') = '00000000'
               OR nombres ILIKE '%CLIENTES VARIOS%'
               OR nombre_completo ILIKE '%CLIENTES VARIOS%'
            LIMIT 1
        ")->fetch() ?: null;
        if ($cliente) {
            $clienteId = (int) $cliente['id'];
            $rucCli = trim((string) ($cliente['ruc'] ?? ''));
        }
    }

    // Tope S/700 (boleta a comprador no identificado); el generico tiene dni '00000000'
    $totalVenta = (float) $venta['total'];
    $dniCli = trim((string) ($cliente['dni'] ?? ''));
    $identificado = $rucCli !== '' || (preg_match('/^\d{8}$/', $dniCli) && $dniCli !== '00000000');
    if ($tipo === 'boleta' && !$identificado && $totalVenta >= SUNAT_BOLETA_TOPE_SIN_DNI) {
        throw new RuntimeException('Boleta de S/ ' . number_format($totalVenta, 2) . ' (≥ S/ ' . SUNAT_BOLETA_TOPE_SIN_DNI . ') sin DNI: SUNAT exige registrar el DNI o emitir factura.');
    }

    $items = facturacionCargarItemsVenta($db, $ventaId);
    $meta  = $tipo === 'factura' ? ['codigo' => '01', 'descripcion' => 'Factura'] : ['codigo' => '03', 'descripcion' => 'Boleta'];
    $fechaEmision = !empty($venta['fecha_emision']) ? (string) $venta['fecha_emision'] : substr((string) $venta['created_at'], 0, 10);

    $db->beginTransaction();
    try {
        $serieStmt = $db->prepare("
            UPDATE series_comprobantes
            SET ultimo_numero = COALESCE(ultimo_numero, 0) + 1
            WHERE tipo = :tipo AND COALESCE(activo, TRUE) = TRUE
            RETURNING serie, ultimo_numero,
                      COALESCE(codigo_tipo_documento, :cod) AS codigo_tipo_documento,
                      tipo_documento_id
        ");
        $serieStmt->execute([':tipo' => $tipo, ':cod' => $meta['codigo']]);
        $serieRow = $serieStmt->fetch();
        if (!$serieRow) {
            throw new RuntimeException("No hay serie configurada para {$meta['descripcion']}.");
        }
        $serie = (string) $serieRow['serie'];
        $correlativo = str_pad((string) intval($serieRow['ultimo_numero']), 8, '0', STR_PAD_LEFT);
        $numeroCompleto = $serie . '-' . $correlativo;
        $tipoDocumentoId = $serieRow['tipo_documento_id'] ?? null;

        $cpeStmt = $db->prepare("
            INSERT INTO comprobantes_electronicos (
                venta_id, tipo, serie, numero, numero_completo, estado_sunat,
                codigo_tipo_documento, fecha_emision
            )
            VALUES (:v, :tipo, :serie, :numero, :nc, 'pendiente', :cod, :fe)
            RETURNING id
        ");
        $cpeStmt->execute([
            ':v' => $ventaId, ':tipo' => $tipo, ':serie' => $serie,
            ':numero' => intval($correlativo), ':nc' => $numeroCompleto,
            ':cod' => $meta['codigo'], ':fe' => $fechaEmision,
        ]);
        $cpeId = (int) $cpeStmt->fetch()['id'];

        $db->prepare("
            UPDATE ventas
            SET tipo_comprobante = :tipo, serie = :serie, correlativo = :correlativo,
                codigo_tipo_documento = :cod, tipo_documento_id = :tdid,
                cliente_id = COALESCE(:cliid, cliente_id), updated_at = NOW()
            WHERE id = :id
        ")->execute([
            ':tipo' => $tipo, ':serie' => $serie, ':correlativo' => $correlativo,
            ':cod' => $meta['codigo'], ':tdid' => $tipoDocumentoId,
            ':cliid' => $clienteId, ':id' => $ventaId,
        ]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw new RuntimeException('No se pudo generar el comprobante: ' . $e->getMessage());
    }

    $cpeData = [
        'id' => $cpeId, 'tipo' => $tipo, 'serie' => $serie, 'numero' => intval($correlativo),
        'numero_completo' => $numeroCompleto, 'codigo_tipo_documento' => $meta['codigo'],
    ];
    $ventaInfo = [
        'numero_venta'     => (string) $venta['numero_venta'],
        'tipo_pago'        => (string) $venta['tipo_pago'],
        'sunat_forma_pago' => (string) ($venta['sunat_forma_pago'] ?: 'Contado'),
        'fecha_emision'    => $fechaEmision,
        'hora_emision'     => (string) ($venta['hora_emision'] ?: date('H:i:s')),
        'moneda'           => (string) ($venta['moneda_codigo'] ?: 'PEN'),
        'totales'          => ['total' => $totalVenta],
    ];
    try {
        $result = enviar_sunat($db, $cpeData, $cliente, $items, $ventaInfo);
    } catch (Throwable $e) {
        $result = ['error_nubefact' => true, 'mensaje' => $e->getMessage()];
    }

    $falloRed = !empty($result['error_nubefact'])
        && empty($result['enlace_del_cdr'])
        && ($result['response_code'] ?? null) === null;
    if ($falloRed) {
        $db->prepare("
            UPDATE comprobantes_electronicos
            SET reenvio_intentos = 0, reenvio_proximo_at = NOW() + interval '5 minutes',
                reenvio_ultimo_error = :e
            WHERE id = :id
        ")->execute([':e' => (string) ($result['mensaje'] ?? 'Error de red'), ':id' => $cpeId]);
    }

    return [
        'cpe_id'          => $cpeId,
        'tipo'            => $tipo,
        'numero_completo' => $numeroCompleto,
        'sunat_ok'        => empty($result['error_nubefact']) && !empty($result['enlace_del_cdr']),
        'estado_sunat'    => $result['estado_sunat'] ?? 'pendiente',
        'mensaje'         => $result['mensaje'] ?? null,
        'response_code'   => $result['response_code'] ?? null,
    ];
}

function facturacionExtraerResponseCode(?string $json): ?string
{
    $json = trim((string) $json);
    if ($json === '') {
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }

    return isset($data['cdr']['response_code'])
        ? trim((string) $data['cdr']['response_code'])
        : (isset($data['cdr']['responseCode']) ? trim((string) $data['cdr']['responseCode']) : null);
}
