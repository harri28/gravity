<?php
// ============================================================
// ARCHIVO: farmacia/modules/ventas/api.php
// DESCRIPCION: API REST para el modulo de Ventas
// ============================================================

header('Content-Type: application/json; charset=UTF-8');
require_once '../../config/database.php';
requireApiAuth();

$action = $_GET['action'] ?? '';
$db     = getDB();

function ventasMetaComprobante(string $tipo): array
{
    switch ($tipo) {
        case 'factura': return ['codigo' => '01', 'descripcion' => 'Factura Electronica'];
        case 'boleta':  return ['codigo' => '03', 'descripcion' => 'Boleta Electronica'];
        default:        return ['codigo' => '02', 'descripcion' => 'Nota de venta'];
    }
}

function ventasNormalizarAfectacionTipo(?string $tipo, ?string $codigo): string
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

function ventasEsClienteVarios(?array $cliente): bool
{
    if (!$cliente) {
        return false;
    }

    $documento = trim((string) ($cliente['numero_documento'] ?? $cliente['dni'] ?? $cliente['ruc'] ?? ''));
    $nombre = strtoupper(trim((string) ($cliente['razon_social'] ?? $cliente['nombre_completo'] ?? trim(($cliente['nombres'] ?? '') . ' ' . ($cliente['apellidos'] ?? '')))));

    return $documento === '00000000' || strpos($nombre, 'CLIENTES VARIOS') !== false;
}

function ventasPrecioUnitarioFinal(array $producto): float
{
    $precio = round((float) ($producto['precio_venta'] ?? 0), 2);
    $tipoAfectacion = ventasNormalizarAfectacionTipo($producto['afectacion_tipo'] ?? null, $producto['afectacion_igv_codigo'] ?? null);
    $porcentajeIgv = max(0, (float) ($producto['porcentaje_igv'] ?? 0));
    $incluyeIgv = filter_var($producto['incluye_igv'] ?? false, FILTER_VALIDATE_BOOL);

    if ($tipoAfectacion === 'GRAV' && !$incluyeIgv) {
        return round($precio * (1 + ($porcentajeIgv / 100)), 2);
    }

    return $precio;
}

// Promociones (Reportes, Bloque 2, migration_56). Devuelve el precio base
// (sin IGV, misma escala que productos.precio_venta) mas barato entre todas
// las promociones vigentes para este producto, o null si no hay ninguna.
// Solo se llama cuando se vende a la unidad base del producto (ver el punto
// de uso en 'registrar_venta') -- una promocion se define sobre precio_venta
// base, no sobre el precio independiente de una presentacion (ej. CAJA x10),
// asi que mezclarlas arriesgaria un calculo incorrecto; en ese caso la
// promocion simplemente no se aplica.
function ventasPromoPrecioMinimo(PDO $db, int $productoId, float $precioBase): ?float
{
    $stmt = $db->prepare("
        SELECT MIN(
            CASE pr.tipo_descuento
                WHEN 'porcentaje' THEN :precio1::numeric * (1 - pr.valor_descuento / 100.0)
                ELSE GREATEST(:precio2::numeric - pr.valor_descuento, 0.01)
            END
        ) AS precio_promo
        FROM promocion_productos pp
        JOIN promociones pr ON pr.id = pp.promocion_id
        WHERE pp.producto_id = :pid AND pr.activo = TRUE
          AND CURRENT_DATE BETWEEN pr.fecha_inicio AND pr.fecha_fin
    ");
    $stmt->execute([':precio1' => $precioBase, ':precio2' => $precioBase, ':pid' => $productoId]);
    $precioPromo = $stmt->fetch()['precio_promo'] ?? null;

    return $precioPromo !== null ? round((float) $precioPromo, 2) : null;
}

function ventasCalcularDetalleTributario(array $producto, float $cantidad): array
{
    $cantidad = round(max(0.01, $cantidad), 2);
    $precioBase = round((float) ($producto['precio_venta'] ?? 0), 2);
    $tipoAfectacion = ventasNormalizarAfectacionTipo($producto['afectacion_tipo'] ?? null, $producto['afectacion_igv_codigo'] ?? null);
    $afectacionCodigo = trim((string) ($producto['afectacion_igv_codigo'] ?? '10')) ?: '10';
    $porcentajeIgv = $tipoAfectacion === 'GRAV'
        ? round(max(0, (float) ($producto['porcentaje_igv'] ?? 18)), 2)
        : 0.0;
    $incluyeIgv = $tipoAfectacion === 'GRAV'
        ? filter_var($producto['incluye_igv'] ?? false, FILTER_VALIDATE_BOOL)
        : false;
    $factorIcbper = filter_var($producto['icbper_activo'] ?? false, FILTER_VALIDATE_BOOL)
        ? round(max(0, (float) ($producto['factor_icbper'] ?? 0)), 4)
        : 0.0;

    if ($tipoAfectacion === 'GRAV') {
        if ($incluyeIgv) {
            $valorUnitario = round($precioBase / (1 + ($porcentajeIgv / 100)), 10);
            $precioUnitario = $precioBase;
            $igvUnitario = round($precioUnitario - $valorUnitario, 10);
        } else {
            $valorUnitario = round($precioBase, 10);
            $igvUnitario = round($valorUnitario * ($porcentajeIgv / 100), 10);
            $precioUnitario = round($valorUnitario + $igvUnitario, 10);
        }
    } else {
        $valorUnitario = round($precioBase, 10);
        $precioUnitario = round($precioBase, 10);
        $igvUnitario = 0.0;
    }

    $valorTotal = round($valorUnitario * $cantidad, 2);
    $igv = round($igvUnitario * $cantidad, 2);
    $icbper = round($factorIcbper * $cantidad, 2);
    $precioTotal = round(($precioUnitario * $cantidad) + $icbper, 2);

    return [
        'cantidad' => $cantidad,
        'precio_unitario' => round($precioUnitario, 2),
        'valor_unitario' => $valorUnitario,
        'valor_total' => $valorTotal,
        'igv' => $igv,
        'icbper' => $icbper,
        'precio_total' => $precioTotal,
        'afectacion_codigo' => $afectacionCodigo,
        'afectacion_tipo' => $tipoAfectacion,
        'porcentaje_igv' => $porcentajeIgv,
        'incluye_igv' => $incluyeIgv,
        'unidad_codigo' => trim((string) ($producto['unidad_codigo'] ?? 'NIU')) ?: 'NIU',
        'unidad_id' => !empty($producto['unidad_id']) ? (int) $producto['unidad_id'] : null,
        'afectacion_id' => !empty($producto['afectacion_igv_id']) ? (int) $producto['afectacion_igv_id'] : null,
        'codigo_sunat' => trim((string) ($producto['codigo_sunat'] ?? '00000000')) ?: '00000000',
        'codigo_interno' => trim((string) ($producto['codigo_interno'] ?? $producto['codigo'] ?? '')),
        'descripcion' => trim((string) ($producto['nombre'] ?? 'Producto')),
        'factor_icbper' => $factorIcbper,
    ];
}

function ventasResumenDesdeDetalles(array $detalles): array
{
    $gravada = 0.0;
    $exonerada = 0.0;
    $inafecta = 0.0;
    $igv = 0.0;
    $icbper = 0.0;
    $total = 0.0;

    foreach ($detalles as $detalle) {
        $valorTotal = (float) ($detalle['valor_total'] ?? 0);
        $lineIgv = (float) ($detalle['igv'] ?? 0);
        $lineIcbper = (float) ($detalle['icbper'] ?? 0);
        $tipoAfectacion = ventasNormalizarAfectacionTipo($detalle['afectacion_tipo'] ?? null, $detalle['afectacion_codigo'] ?? null);

        if ($tipoAfectacion === 'GRAV') {
            $gravada += $valorTotal;
        } elseif ($tipoAfectacion === 'EXO') {
            $exonerada += $valorTotal;
        } else {
            $inafecta += $valorTotal;
        }

        $igv += $lineIgv;
        $icbper += $lineIcbper;
        $total += (float) ($detalle['precio_total'] ?? 0);
    }

    $subtotal = round($gravada + $exonerada + $inafecta, 2);

    return [
        'subtotal' => $subtotal,
        'gravada' => round($gravada, 2),
        'exonerada' => round($exonerada, 2),
        'inafecta' => round($inafecta, 2),
        'igv' => round($igv, 2),
        'icbper' => round($icbper, 2),
        'total' => round($total, 2),
    ];
}

function ventasAplicarDescuentoGlobal(array $detalles, float $descuento): array
{
    $descuento = round(max(0, $descuento), 2);
    if ($descuento <= 0 || empty($detalles)) {
        return [$detalles, 0.0];
    }

    $grossTotal = 0.0;
    foreach ($detalles as $detalle) {
        $grossTotal += (float) ($detalle['precio_total'] ?? 0);
    }
    $grossTotal = round($grossTotal, 2);

    if ($grossTotal <= 0) {
        return [$detalles, 0.0];
    }

    if ($descuento > $grossTotal + 0.01) {
        throw new Exception('El descuento no puede ser mayor al importe de la venta.');
    }

    $remaining = $descuento;
    $adjusted = [];
    $lastIndex = count($detalles) - 1;

    foreach ($detalles as $index => $detalle) {
        $lineGross = round((float) ($detalle['precio_total'] ?? 0), 2);
        $lineDiscount = $index === $lastIndex
            ? round(max(0, $remaining), 2)
            : round($descuento > 0 ? ($lineGross / $grossTotal) * $descuento : 0, 2);

        $lineDiscount = min($lineDiscount, $lineGross);
        $remaining = round($remaining - $lineDiscount, 2);

        $lineBase = round((float) ($detalle['valor_total'] ?? 0), 2);
        $lineIgv = round((float) ($detalle['igv'] ?? 0), 2);
        $lineRatio = $lineGross > 0 ? ($lineBase / $lineGross) : 1.0;
        $baseDiscount = round($lineDiscount * $lineRatio, 2);
        $igvDiscount = round($lineDiscount - $baseDiscount, 2);

        $detalle['descuento'] = $lineDiscount;
        $detalle['valor_total'] = max(0, round($lineBase - $baseDiscount, 2));
        $detalle['igv'] = max(0, round($lineIgv - $igvDiscount, 2));
        $detalle['precio_total'] = max(0, round($lineGross - $lineDiscount, 2));
        $detalle['subtotal'] = $detalle['precio_total'];

        $adjusted[] = $detalle;
    }

    return [$adjusted, $descuento];
}

/**
 * Cobra una comanda emitiendo boleta/factura SUNAT. Reutiliza los helpers
 * tributarios de este archivo (ventasCalcularDetalleTributario, ventasResumen...)
 * y el pipeline de enviar_sunat() de config/sunat.php, PERO no descuenta stock
 * (ya se descontó item-por-item en comanda_agregar_item en Fase D).
 *
 * Devuelve el array que jsonResponse() va a emitir. Si algo falla hace
 * rollback y lanza. El llamador decide el jsonResponse.
 */
function ventasCobrarComandaConSunat(
    PDO $db,
    int $comandaId,
    string $tipoComp,
    string $tipoPago,
    ?int $clienteId,
    float $montoRecibido,
    string $observaciones,
    int $cajaId
): array {
    // --- Lock de comanda + items + info de mesa ---
    $cSt = $db->prepare("
        SELECT c.id, c.mesa_id, m.numero AS mesa_num
        FROM comandas c JOIN mesas m ON m.id = c.mesa_id
        WHERE c.id = :id AND c.estado = 'abierta'
        FOR UPDATE
    ");
    $cSt->execute([':id' => $comandaId]);
    $comanda = $cSt->fetch();
    if (!$comanda) {
        throw new Exception('La comanda ya no esta abierta');
    }

    $itSt = $db->prepare("
        SELECT id, producto_id, cantidad, precio_unitario, subtotal
        FROM comanda_items WHERE comanda_id = :c ORDER BY id ASC FOR UPDATE
    ");
    $itSt->execute([':c' => $comandaId]);
    $itemsComanda = $itSt->fetchAll();
    if (!$itemsComanda) {
        throw new Exception('La comanda esta vacia -- no hay nada que cobrar');
    }

    // --- Calcular detalle tributario item por item ---
    // Usamos el producto actual para los metadatos de SUNAT, pero overrideamos
    // precio_venta con el que quedó guardado en comanda_items (el precio del
    // momento del pedido). Si cambió el precio despues, se cobra lo pedido.
    $prodSt = $db->prepare("
        SELECT p.id, p.nombre, p.codigo, p.codigo_interno, p.codigo_sunat,
               p.precio_venta, p.unidad_id, p.unidad_codigo,
               p.afectacion_igv_id, p.afectacion_igv_codigo,
               p.porcentaje_igv, p.incluye_igv,
               p.icbper_activo, p.factor_icbper,
               a.tipo AS afectacion_tipo
        FROM productos p
        LEFT JOIN public.fe_tipos_afectacion_igv a ON a.id = p.afectacion_igv_id
        WHERE p.id = :id
    ");

    $detallesVenta = [];
    foreach ($itemsComanda as $itc) {
        $prodSt->execute([':id' => $itc['producto_id']]);
        $prod = $prodSt->fetch();
        if (!$prod) {
            throw new Exception("Producto ID {$itc['producto_id']} de la comanda ya no existe");
        }
        // Override con el precio congelado al momento del pedido.
        $prod['precio_venta'] = (float) $itc['precio_unitario'];

        $cantidad = (float) $itc['cantidad'];
        $detalle = ventasCalcularDetalleTributario($prod, $cantidad);
        $detalle['producto_id'] = (int) $prod['id'];
        $detalle['nombre']      = $prod['nombre'];
        $detalle['codigo']      = $prod['codigo'];
        $detalle['unidad_medida_vendida'] = null;  // comandas no manejan multi-unidad
        $detalle['factor_equivalencia']   = 1;
        $detalle['cantidad_base']         = $cantidad;  // sin equivalencia -> mismo valor
        $detallesVenta[] = $detalle;
    }

    $resumen  = ventasResumenDesdeDetalles($detallesVenta);
    $subtotal = $resumen['subtotal'];
    $igv      = $resumen['igv'];
    $total    = $resumen['total'];
    $vuelto   = ($tipoPago === 'efectivo') ? round(max(0, $montoRecibido - $total), 2) : 0.0;

    // --- Cliente lookup y validaciones SUNAT ---
    $clienteInfo = null;
    if ($clienteId) {
        $cliSt = $db->prepare("
            SELECT id, nombres, apellidos, dni, ruc, telefono, email, direccion,
                   tipo_documento_id, tipo_documento_codigo, numero_documento,
                   nombre_completo, razon_social, ubigeo
            FROM clientes WHERE id = :id
        ");
        $cliSt->execute([':id' => $clienteId]);
        $clienteInfo = $cliSt->fetch() ?: null;
    }
    if ($tipoComp === 'factura' && (!$clienteInfo || empty($clienteInfo['ruc']))) {
        throw new Exception('Para emitir factura debe seleccionar un cliente con RUC registrado');
    }
    // Boleta: si no vino cliente, buscar "Clientes Varios" (requisito SUNAT
    // para boletas < S/700 que pueden ir sin DNI). Si no existe, dejar NULL.
    if ($tipoComp === 'boleta' && !$clienteInfo) {
        $cvSt = $db->query("
            SELECT id, nombres, apellidos, dni, ruc, telefono, email, direccion,
                   tipo_documento_id, tipo_documento_codigo, numero_documento,
                   nombre_completo, razon_social, ubigeo
            FROM clientes
            WHERE COALESCE(numero_documento,'') = '00000000'
               OR nombres ILIKE '%CLIENTES VARIOS%'
               OR nombre_completo ILIKE '%CLIENTES VARIOS%'
            LIMIT 1
        ");
        $clienteInfo = $cvSt->fetch() ?: null;
        if ($clienteInfo) {
            $clienteId = (int) $clienteInfo['id'];
        }
    }

    // --- Reservar serie + correlativo (atomico via UPDATE RETURNING) ---
    $comprobanteMeta = ventasMetaComprobante($tipoComp);
    $sunatFormaPago  = 'Contado';
    $formaPagoId     = ventasResolverFormaPagoId($db, $tipoPago);

    $seriesSt = $db->prepare("
        UPDATE series_comprobantes
        SET ultimo_numero = COALESCE(ultimo_numero, 0) + 1
        WHERE tipo = :tipo AND COALESCE(activo, TRUE) = TRUE
        RETURNING serie, ultimo_numero,
                  COALESCE(codigo_tipo_documento, :ctd) AS codigo_tipo_documento,
                  tipo_documento_id
    ");
    $seriesSt->execute([':tipo' => $tipoComp, ':ctd' => $comprobanteMeta['codigo']]);
    $serieRow = $seriesSt->fetch();
    if (!$serieRow) {
        throw new Exception("No hay serie configurada para {$comprobanteMeta['descripcion']}.");
    }
    $serie       = $serieRow['serie'];
    $correlativo = str_pad((string) intval($serieRow['ultimo_numero']), 8, '0', STR_PAD_LEFT);
    $tipoDocId   = $serieRow['tipo_documento_id'] ?? null;

    // --- INSERT venta con todos los campos SUNAT ---
    $numeroVenta = generarNumeroVenta($db);
    $vIns = $db->prepare("
        INSERT INTO ventas (
            numero_venta, cliente_id, caja_id, usuario_id,
            subtotal, descuento, igv, total,
            tipo_pago, tipo_comprobante, estado, vendedor, observaciones,
            fecha_emision, fecha_vencimiento, hora_emision, moneda_codigo,
            tipo_documento_id, codigo_tipo_documento, serie, correlativo,
            operacion_tipo_codigo, forma_pago_id, sunat_forma_pago,
            exonerada, inafecta, gravada, gratuita, anticipo, otros_cargos,
            icbper, monto_credito, vuelto, cuotas, payment_breakdown,
            comanda_id
        ) VALUES (
            :nv, :ci, :cj, :ui,
            :sub, 0, :igv, :tot,
            :tp, :tc, 'completada', :vn, :ob,
            CURRENT_DATE, CURRENT_DATE, CURRENT_TIME, 'PEN',
            :tdoc, :ctd, :srie, :cor,
            '0101', :fp, :sfp,
            :exo, :ina, :grav, 0, 0, 0,
            :icb, 0, :vu, :cuo, :pb,
            :cmd
        ) RETURNING id
    ");
    $vIns->execute([
        ':nv'  => $numeroVenta,
        ':ci'  => $clienteId ?: null,
        ':cj'  => $cajaId,
        ':ui'  => sesionId() ?: null,
        ':sub' => $subtotal,
        ':igv' => $igv,
        ':tot' => $total,
        ':tp'  => $tipoPago,
        ':tc'  => $tipoComp,
        ':vn'  => sesionNombre() ?: 'Operador',
        ':ob'  => $observaciones !== '' ? $observaciones : "Mesa #{$comanda['mesa_num']}",
        ':tdoc' => $tipoDocId,
        ':ctd' => $comprobanteMeta['codigo'],
        ':srie' => $serie,
        ':cor' => $correlativo,
        ':fp'  => $formaPagoId,
        ':sfp' => $sunatFormaPago,
        ':exo' => $resumen['exonerada'],
        ':ina' => $resumen['inafecta'],
        ':grav' => $resumen['gravada'],
        ':icb' => $resumen['icbper'],
        ':vu'  => $vuelto,
        ':cuo' => json_encode([], JSON_UNESCAPED_UNICODE),
        ':pb'  => json_encode([[
            'method'     => $tipoPago,
            'amount'     => $total,
            'account_id' => null,
        ]], JSON_UNESCAPED_UNICODE),
        ':cmd' => $comandaId,
    ]);
    $ventaId = (int) $vIns->fetch()['id'];

    // Token publico para "Mi Comprobante"
    $comprobanteToken = bin2hex(random_bytes(16));
    $db->prepare("
        INSERT INTO public.comprobante_tokens (token, schema_name, venta_id)
        VALUES (:t, :s, :v)
    ")->execute([':t' => $comprobanteToken, ':s' => sesionSchema(), ':v' => $ventaId]);

    // --- INSERT venta_detalles (SIN descontar stock, SI incrementar total_vendido) ---
    $dIns = $db->prepare("
        INSERT INTO venta_detalles (
            venta_id, producto_id, cantidad, precio_unitario, descuento, subtotal,
            unidad_id, unidad_codigo, afectacion_igv_id, afectacion_igv_codigo,
            descripcion, codigo_interno, codigo_sunat, igv, valor_unitario,
            valor_total, precio_total, icbper, factor_icbper,
            unidad_medida_vendida, factor_equivalencia
        ) VALUES (
            :v, :p, :q, :pu, 0, :sub,
            :uid, :uco, :aid, :aco,
            :desc, :cint, :csun, :igv, :vu,
            :vt, :pt, :icb, :fic,
            :umv, :feq
        )
    ");
    $incVendido = $db->prepare("UPDATE productos SET total_vendido = total_vendido + :q, updated_at = NOW() WHERE id = :p");
    foreach ($detallesVenta as $det) {
        $dIns->execute([
            ':v'   => $ventaId,
            ':p'   => $det['producto_id'],
            ':q'   => $det['cantidad'],
            ':pu'  => $det['precio_unitario'],
            ':sub' => $det['precio_total'],
            ':uid' => $det['unidad_id'],
            ':uco' => $det['unidad_codigo'],
            ':aid' => $det['afectacion_id'],
            ':aco' => $det['afectacion_codigo'],
            ':desc' => $det['descripcion'],
            ':cint' => $det['codigo_interno'],
            ':csun' => $det['codigo_sunat'],
            ':igv' => $det['igv'],
            ':vu'  => $det['valor_unitario'],
            ':vt'  => $det['valor_total'],
            ':pt'  => $det['precio_total'],
            ':icb' => $det['icbper'],
            ':fic' => $det['factor_icbper'],
            ':umv' => $det['unidad_medida_vendida'],
            ':feq' => $det['factor_equivalencia'],
        ]);
        $incVendido->execute([':q' => $det['cantidad'], ':p' => $det['producto_id']]);
    }

    // --- UPDATE caja (saldo_actual += total) ---
    $db->prepare("UPDATE cajas SET saldo_actual = saldo_actual + :t WHERE id = :id")
        ->execute([':t' => $total, ':id' => $cajaId]);

    // --- INSERT comprobante_electronico (pendiente de SUNAT) ---
    $numeroCompleto = $serie . '-' . $correlativo;
    $cpeIns = $db->prepare("
        INSERT INTO comprobantes_electronicos (
            venta_id, tipo, serie, numero, numero_completo, estado_sunat,
            codigo_tipo_documento, fecha_emision
        ) VALUES (
            :v, :t, :s, :n, :nc, 'pendiente', :ctd, CURRENT_DATE
        ) RETURNING id
    ");
    $cpeIns->execute([
        ':v'  => $ventaId,
        ':t'  => $tipoComp,
        ':s'  => $serie,
        ':n'  => intval($correlativo),
        ':nc' => $numeroCompleto,
        ':ctd' => $comprobanteMeta['codigo'],
    ]);
    $cpeId = (int) $cpeIns->fetch()['id'];

    // --- Cerrar la comanda (libera la mesa via indice parcial unico) ---
    $db->prepare("
        UPDATE comandas
        SET estado='cerrada', hora_cierre=CURRENT_TIMESTAMP,
            usuario_cierre_id=:u, venta_id=:v, total=:t
        WHERE id = :id
    ")->execute([
        ':u' => sesionId(), ':v' => $ventaId, ':t' => $total, ':id' => $comandaId,
    ]);

    return [
        'venta_id'          => $ventaId,
        'cpe_id'            => $cpeId,
        'numero_venta'      => $numeroVenta,
        'numero_completo'   => $numeroCompleto,
        'serie'             => $serie,
        'correlativo'       => $correlativo,
        'tipo_comprobante'  => $tipoComp,
        'total'             => $total,
        'vuelto'            => $vuelto,
        'mesa_numero'       => $comanda['mesa_num'],
        'mesa_id'           => (int) $comanda['mesa_id'],
        'comprobante_token' => $comprobanteToken,
        'cliente_info'      => $clienteInfo,
        'items_sunat'       => $detallesVenta,
        'resumen'           => $resumen,
        'sunat_forma_pago'  => $sunatFormaPago,
        'tipo_pago'         => $tipoPago,
    ];
}

function ventasResolverFormaPagoId(PDO $db, string $tipoPago): ?int
{
    static $cache = [];

    $tipoPago = strtolower(trim($tipoPago));
    if (isset($cache[$tipoPago])) {
        return $cache[$tipoPago];
    }

    switch ($tipoPago) {
        case 'tarjeta':       $descripcion = 'Tarjeta de credito'; break;
        case 'transferencia': $descripcion = 'Transferencia';      break;
        case 'yape':          $descripcion = 'Yape';               break;
        case 'plin':          $descripcion = 'Plin';               break;
        default:              $descripcion = 'Efectivo';           break;
    }

    $stmt = $db->prepare("
        SELECT id
        FROM public.fe_formas_pago
        WHERE LOWER(descripcion) = LOWER(:descripcion)
        LIMIT 1
    ");
    $stmt->execute([':descripcion' => $descripcion]);

    return $cache[$tipoPago] = (($row = $stmt->fetch()) ? (int) $row['id'] : null);
}

switch ($action) {

    // ================================================================
    // MESAS / COMANDAS  (modelo karaoke/bar — migration_59)
    // ================================================================

    case 'mesas_estado':
        // Listado de mesas activas con join a la comanda abierta (si la hay).
        // El frontend usa esto para pintar la grilla "libres vs ocupadas" y
        // actualizar los contadores de items/total por mesa en el polling.
        $stmt = $db->query("
            SELECT m.id,
                   m.numero,
                   m.nombre,
                   m.capacidad,
                   c.id                 AS comanda_id,
                   c.hora_apertura,
                   c.usuario_apertura_id,
                   c.total              AS total_parcial,
                   (SELECT COUNT(*) FROM comanda_items ci WHERE ci.comanda_id = c.id) AS items_count,
                   u.nombre             AS usuario_apertura_nombre
            FROM mesas m
            LEFT JOIN comandas c ON c.mesa_id = m.id AND c.estado = 'abierta'
            LEFT JOIN public.usuarios u ON u.id = c.usuario_apertura_id
            WHERE m.activa = TRUE
            ORDER BY m.numero ASC
        ");
        echo json_encode($stmt->fetchAll());
        break;

    case 'abrir_comanda':
        $d       = json_decode(file_get_contents('php://input'), true);
        $mesaId  = intval($d['mesa_id'] ?? 0);
        if (!$mesaId) {
            jsonResponse(['error' => true, 'message' => 'Mesa no especificada'], 400);
        }

        // Validar que la mesa existe y esta activa antes de insertar.
        $chk = $db->prepare("SELECT numero, activa FROM mesas WHERE id = :id");
        $chk->execute([':id' => $mesaId]);
        $mesa = $chk->fetch();
        if (!$mesa) {
            jsonResponse(['error' => true, 'message' => 'Mesa no encontrada'], 404);
        }
        if ($mesa['activa'] !== true && $mesa['activa'] !== 't') {
            jsonResponse(['error' => true, 'message' => 'La mesa esta inactiva'], 400);
        }

        try {
            $ins = $db->prepare("
                INSERT INTO comandas (mesa_id, usuario_apertura_id)
                VALUES (:m, :u)
                RETURNING id, hora_apertura
            ");
            $ins->execute([':m' => $mesaId, ':u' => sesionId()]);
            $row = $ins->fetch();
        } catch (PDOException $e) {
            // 23505 = unique_violation — el indice parcial unico
            // uq_<schema>_comandas_mesa_abierta bloquea abrir una 2a
            // comanda sobre una mesa que ya tiene una abierta (puede
            // pasar si dos mozos clickean "abrir" en paralelo).
            if ($e->getCode() === '23505') {
                jsonResponse(['error' => true, 'message' => 'Esa mesa ya tiene una comanda abierta'], 409);
            }
            throw $e;
        }

        registrarAuditoria('Apertura de comanda', 'ventas', "Mesa #{$mesa['numero']} | Comanda ID: {$row['id']}");
        jsonResponse([
            'error'         => false,
            'id'            => (int)$row['id'],
            'hora_apertura' => $row['hora_apertura'],
            'message'       => "Mesa #{$mesa['numero']} abierta",
        ]);

    case 'comanda_detalle':
        // Devuelve la comanda (por id o por mesa_id derivando la abierta) + sus items.
        // mesa.php la invoca al cargar y despues de cada agregar/quitar para resincronizar.
        $comandaId = intval($_GET['id'] ?? 0);
        $mesaId    = intval($_GET['mesa_id'] ?? 0);
        if (!$comandaId && !$mesaId) {
            jsonResponse(['error' => true, 'message' => 'Falta id de comanda o mesa'], 400);
        }
        if ($comandaId) {
            $stmt = $db->prepare("
                SELECT c.id, c.mesa_id, c.hora_apertura, c.estado, c.total,
                       c.usuario_apertura_id,
                       m.numero AS mesa_numero, m.nombre AS mesa_nombre, m.capacidad AS mesa_capacidad,
                       u.nombre AS usuario_apertura_nombre
                FROM comandas c
                JOIN mesas m ON m.id = c.mesa_id
                LEFT JOIN public.usuarios u ON u.id = c.usuario_apertura_id
                WHERE c.id = :id
            ");
            $stmt->execute([':id' => $comandaId]);
        } else {
            $stmt = $db->prepare("
                SELECT c.id, c.mesa_id, c.hora_apertura, c.estado, c.total,
                       c.usuario_apertura_id,
                       m.numero AS mesa_numero, m.nombre AS mesa_nombre, m.capacidad AS mesa_capacidad,
                       u.nombre AS usuario_apertura_nombre
                FROM comandas c
                JOIN mesas m ON m.id = c.mesa_id
                LEFT JOIN public.usuarios u ON u.id = c.usuario_apertura_id
                WHERE c.mesa_id = :m AND c.estado = 'abierta'
            ");
            $stmt->execute([':m' => $mesaId]);
        }
        $comanda = $stmt->fetch();
        if (!$comanda) {
            jsonResponse(['error' => true, 'message' => 'Comanda no encontrada'], 404);
        }

        $itSt = $db->prepare("
            SELECT ci.id, ci.producto_id, ci.cantidad, ci.precio_unitario, ci.subtotal,
                   ci.hora_pedido, ci.notas, ci.unidad_medida, COALESCE(ci.factor_unidad, 1) AS factor_unidad,
                   p.nombre AS producto_nombre, p.codigo, p.product_type,
                   u.nombre AS mozo_nombre
            FROM comanda_items ci
            JOIN productos p ON p.id = ci.producto_id
            LEFT JOIN public.usuarios u ON u.id = ci.usuario_id
            WHERE ci.comanda_id = :c
            ORDER BY ci.hora_pedido ASC, ci.id ASC
        ");
        $itSt->execute([':c' => $comanda['id']]);
        jsonResponse([
            'error'   => false,
            'comanda' => $comanda,
            'items'   => $itSt->fetchAll(),
        ]);

    case 'comanda_agregar_item':
        // Agrega un producto a la comanda y descuenta stock en la MISMA transaccion
        // (requisito: stock se descuenta al pedir, no al cobrar). Usa FOR UPDATE
        // sobre productos+comandas para evitar sobre-ventas en concurrencia.
        $d          = json_decode(file_get_contents('php://input'), true);
        $comandaId  = intval($d['comanda_id'] ?? 0);
        $productoId = intval($d['producto_id'] ?? 0);
        // Cantidad como float -- productos.stock es NUMERIC (ej. 0.5 kg de piqueo).
        $cantidad   = (float)($d['cantidad'] ?? 1);
        if ($cantidad <= 0) $cantidad = 1;
        $notas      = trim($d['notas'] ?? '');
        // Si viene unidad_medida, el item representa una presentacion alternativa
        // (ej. "CAJA" = 12 unidades a precio propio). Se busca en producto_precios_unidad.
        $unidadMedida = isset($d['unidad_medida']) ? trim((string)$d['unidad_medida']) : '';
        if ($unidadMedida === '') $unidadMedida = null;

        if (!$comandaId || !$productoId) {
            jsonResponse(['error' => true, 'message' => 'Datos incompletos'], 400);
        }

        try {
            $db->beginTransaction();

            $ck = $db->prepare("SELECT id, mesa_id FROM comandas WHERE id = :id AND estado = 'abierta' FOR UPDATE");
            $ck->execute([':id' => $comandaId]);
            if (!$ck->fetch()) {
                $db->rollBack();
                jsonResponse(['error' => true, 'message' => 'La comanda ya no esta abierta'], 400);
            }

            $prodSt = $db->prepare("SELECT id, nombre, precio_venta, stock, product_type FROM productos WHERE id = :id AND activo = TRUE FOR UPDATE");
            $prodSt->execute([':id' => $productoId]);
            $prod = $prodSt->fetch();
            if (!$prod) {
                $db->rollBack();
                jsonResponse(['error' => true, 'message' => 'Producto no encontrado o inactivo'], 404);
            }

            // Resolver precio/factor segun unidad elegida. Si no vino unidad_medida,
            // es la unidad base (factor 1, precio del producto).
            $precio = (float)$prod['precio_venta'];
            $factor = 1.0;
            if ($unidadMedida !== null) {
                $pres = $db->prepare("SELECT cantidad, precio_venta FROM producto_precios_unidad WHERE producto_id = :pid AND unidad_medida = :um LIMIT 1");
                $pres->execute([':pid' => $productoId, ':um' => $unidadMedida]);
                $presRow = $pres->fetch();
                if (!$presRow) {
                    $db->rollBack();
                    jsonResponse(['error' => true, 'message' => "Presentacion '{$unidadMedida}' no existe para este producto"], 400);
                }
                $precio = (float)$presRow['precio_venta'];
                $factor = (float)$presRow['cantidad'];
                if ($factor <= 0) $factor = 1.0;
            }

            $esService    = (($prod['product_type'] ?? 'product') === 'service');
            $stockDescuento = $cantidad * $factor;  // unidades base a descontar
            if (!$esService && (float)$prod['stock'] < $stockDescuento) {
                $db->rollBack();
                jsonResponse(['error' => true, 'message' => "Sin stock de {$prod['nombre']} (disponible: " . rtrim(rtrim((string)$prod['stock'], '0'), '.') . ')'], 400);
            }

            $subtotal = $precio * $cantidad;

            $ins = $db->prepare("
                INSERT INTO comanda_items (comanda_id, producto_id, cantidad, precio_unitario, subtotal, usuario_id, notas, unidad_medida, factor_unidad)
                VALUES (:c, :p, :q, :pr, :s, :u, :n, :um, :f)
                RETURNING id, hora_pedido
            ");
            $ins->execute([
                ':c' => $comandaId, ':p' => $productoId,
                ':q' => $cantidad, ':pr' => $precio, ':s' => $subtotal,
                ':u' => sesionId(),
                ':n' => $notas !== '' ? $notas : null,
                ':um' => $unidadMedida,
                ':f' => $factor,
            ]);
            $itemRow = $ins->fetch();

            if (!$esService) {
                $db->prepare("UPDATE productos SET stock = stock - :q WHERE id = :id")
                    ->execute([':q' => $stockDescuento, ':id' => $productoId]);
            }

            // Recalcular total con un solo UPDATE (no hace falta sumar en PHP)
            $db->prepare("UPDATE comandas SET total = COALESCE((SELECT SUM(subtotal) FROM comanda_items WHERE comanda_id = :c), 0) WHERE id = :c")
                ->execute([':c' => $comandaId]);

            $db->commit();
            jsonResponse([
                'error'   => false,
                'item_id' => (int)$itemRow['id'],
                'message' => "{$prod['nombre']} agregado",
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonResponse(['error' => true, 'message' => 'Error al agregar item: ' . $e->getMessage()], 500);
        }

    case 'comanda_quitar_item':
        $d      = json_decode(file_get_contents('php://input'), true);
        $itemId = intval($d['item_id'] ?? 0);
        if (!$itemId) {
            jsonResponse(['error' => true, 'message' => 'Item no especificado'], 400);
        }

        try {
            $db->beginTransaction();

            $itSt = $db->prepare("
                SELECT ci.id, ci.comanda_id, ci.producto_id, ci.cantidad,
                       COALESCE(ci.factor_unidad, 1) AS factor_unidad,
                       p.product_type
                FROM comanda_items ci
                JOIN productos p ON p.id = ci.producto_id
                WHERE ci.id = :id FOR UPDATE
            ");
            $itSt->execute([':id' => $itemId]);
            $item = $itSt->fetch();
            if (!$item) {
                $db->rollBack();
                jsonResponse(['error' => true, 'message' => 'Item no encontrado'], 404);
            }

            $cSt = $db->prepare("SELECT id FROM comandas WHERE id = :c AND estado = 'abierta' FOR UPDATE");
            $cSt->execute([':c' => $item['comanda_id']]);
            if (!$cSt->fetch()) {
                $db->rollBack();
                jsonResponse(['error' => true, 'message' => 'La comanda ya no esta abierta'], 400);
            }

            $db->prepare("DELETE FROM comanda_items WHERE id = :id")->execute([':id' => $itemId]);

            // Reponer stock si no era servicio: cantidad * factor (ej. 1 CAJA => +12 unidades)
            if (($item['product_type'] ?? 'product') !== 'service') {
                $stockRepone = (float)$item['cantidad'] * (float)$item['factor_unidad'];
                $db->prepare("UPDATE productos SET stock = stock + :q WHERE id = :id")
                    ->execute([':q' => $stockRepone, ':id' => $item['producto_id']]);
            }

            $db->prepare("UPDATE comandas SET total = COALESCE((SELECT SUM(subtotal) FROM comanda_items WHERE comanda_id = :c), 0) WHERE id = :c")
                ->execute([':c' => $item['comanda_id']]);

            // Si fue el ultimo item, cerrar la comanda (anulada) para que la mesa
            // vuelva a aparecer libre y el cronometro arranque de cero la proxima
            // apertura. Evita que una comanda "fantasma" de 0 items siga ocupando
            // la mesa indefinidamente.
            $cntSt = $db->prepare("SELECT COUNT(*) FROM comanda_items WHERE comanda_id = :c");
            $cntSt->execute([':c' => $item['comanda_id']]);
            $comandaVaciada = ((int)$cntSt->fetchColumn()) === 0;
            if ($comandaVaciada) {
                $db->prepare("UPDATE comandas SET estado='anulada', hora_cierre=NOW(), motivo_anulacion='Comanda vaciada (ultimo item removido)' WHERE id = :c")
                    ->execute([':c' => $item['comanda_id']]);
            }

            $db->commit();
            jsonResponse([
                'error'            => false,
                'message'          => 'Item eliminado',
                'comanda_cerrada'  => $comandaVaciada,
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonResponse(['error' => true, 'message' => 'Error al quitar item: ' . $e->getMessage()], 500);
        }

    case 'cobrar_comanda':
        // Cierra la comanda emitiendo venta + comprobante.
        //   - tipo='ticket'  -> venta local sin SUNAT (Fase E MVP)
        //   - tipo='boleta'  -> genera serie/correlativo + envia a SUNAT (E.1)
        //   - tipo='factura' -> idem + requiere cliente con RUC
        // Stock no se descuenta aqui (ya se hace en comanda_agregar_item).
        $d           = json_decode(file_get_contents('php://input'), true);
        $comandaId   = intval($d['comanda_id'] ?? 0);
        $tipoComp    = strtolower(trim($d['tipo_comprobante'] ?? 'ticket'));
        $tipoPago    = strtolower(trim($d['tipo_pago']        ?? 'efectivo'));
        $montoRec    = round((float)($d['monto_recibido']     ?? 0), 2);
        $observac    = trim($d['observaciones'] ?? '');
        $clienteId   = !empty($d['cliente_id']) ? (int) $d['cliente_id'] : null;

        if (!$comandaId) {
            jsonResponse(['error' => true, 'message' => 'Comanda no especificada'], 400);
        }
        if (!in_array($tipoComp, ['ticket','boleta','factura'], true)) {
            jsonResponse(['error' => true, 'message' => 'Tipo de comprobante invalido'], 400);
        }

        // Caja requerida -- es la primera vez que entra plata al sistema por esta comanda.
        $cajaStmt = $db->prepare("SELECT id FROM cajas WHERE estado = 'abierta' AND usuario_id = :uid LIMIT 1");
        $cajaStmt->execute([':uid' => (int) sesionId()]);
        $caja = $cajaStmt->fetch();
        if (!$caja) {
            $otraCajaStmt = $db->query("
                SELECT id, usuario_apertura
                FROM cajas
                WHERE estado = 'abierta'
                ORDER BY apertura_at DESC
                LIMIT 1
            ");
            $otraCaja = $otraCajaStmt->fetch();
            if ($otraCaja) {
                jsonResponse([
                    'error'        => true,
                    'message'      => 'Hay una caja abierta en esta sucursal, pero fue aperturada por otro usuario'
                        . ($otraCaja['usuario_apertura'] ? ' (' . $otraCaja['usuario_apertura'] . ')' : '')
                        . '. El cobro debe realizarse con el usuario que aperturo esa caja.',
                    'caja_cerrada' => false,
                ], 409);
            }

            jsonResponse([
                'error'        => true,
                'message'      => 'No tienes una caja abierta para tu usuario en esta sucursal. Verifica que estés en la sucursal donde aperturaste la caja.',
                'caja_cerrada' => true,
            ], 422);
        }
        $cajaId = (int) $caja['id'];

        // ============================================================
        // Rama SUNAT (boleta / factura)
        // ============================================================
        if ($tipoComp !== 'ticket') {
            try {
                $db->beginTransaction();
                $result = ventasCobrarComandaConSunat(
                    $db, $comandaId, $tipoComp, $tipoPago,
                    $clienteId, $montoRec, $observac, $cajaId
                );
                $db->commit();
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                jsonResponse(['error' => true, 'message' => 'Error al cobrar: ' . $e->getMessage()], 500);
            }

            // Fuera de la transaccion: envio a SUNAT. Si falla por red, el
            // worker scripts/reenviar_sunat_pendientes.php lo retomara.
            require_once '../../config/sunat.php';
            $cpeData = [
                'id'                   => $result['cpe_id'],
                'tipo'                 => $tipoComp,
                'serie'                => $result['serie'],
                'numero'               => (int) $result['correlativo'],
                'numero_completo'      => $result['numero_completo'],
                'codigo_tipo_documento' => ventasMetaComprobante($tipoComp)['codigo'],
            ];
            $ventaInfo = [
                'numero_venta'     => $result['numero_venta'],
                'tipo_pago'        => $result['tipo_pago'],
                'sunat_forma_pago' => $result['sunat_forma_pago'],
                'fecha_emision'    => date('Y-m-d'),
                'hora_emision'     => date('H:i:s'),
                'moneda'           => 'PEN',
                'totales'          => $result['resumen'],
            ];
            try {
                $sunatResult = enviar_sunat($db, $cpeData, $result['cliente_info'], $result['items_sunat'], $ventaInfo);
            } catch (Throwable $e) {
                $sunatResult = ['error_nubefact' => true, 'mensaje' => $e->getMessage()];
            }

            $falloRed = $sunatResult
                && !empty($sunatResult['error_nubefact'])
                && empty($sunatResult['enlace_del_cdr'])
                && ($sunatResult['response_code'] ?? null) === null;
            if ($falloRed) {
                $db->prepare("
                    UPDATE comprobantes_electronicos
                    SET reenvio_intentos = 0,
                        reenvio_proximo_at = NOW() + interval '5 minutes',
                        reenvio_ultimo_error = :e
                    WHERE id = :id
                ")->execute([
                    ':e'  => (string) ($sunatResult['mensaje'] ?? 'Error desconocido'),
                    ':id' => (int) $result['cpe_id'],
                ]);
            }

            registrarAuditoria(
                'Cobro de comanda',
                'ventas',
                "Mesa #{$result['mesa_numero']} | {$result['numero_completo']} | Total: " . number_format((float) $result['total'], 2) . " | Pago: {$tipoPago}"
            );

            jsonResponse([
                'error'           => false,
                'message'         => "Mesa #{$result['mesa_numero']} cobrada. {$result['numero_completo']}",
                'venta_id'        => $result['venta_id'],
                'numero_venta'    => $result['numero_venta'],
                'numero_completo' => $result['numero_completo'],
                'mesa_id'         => $result['mesa_id'],
                'mesa_numero'     => $result['mesa_numero'],
                'total'           => $result['total'],
                'vuelto'          => $result['vuelto'],
                'tipo_comprobante' => $tipoComp,
                'sunat_estado'    => $sunatResult['estado_sunat'] ?? 'pendiente',
                'sunat_ok'        => empty($sunatResult['error_nubefact']) && !empty($sunatResult['enlace_del_cdr']),
                'sunat_mensaje'   => $sunatResult['mensaje'] ?? null,
            ]);
        }

        // ============================================================
        // Rama TICKET (MVP sin SUNAT, flujo original)
        // ============================================================
        try {
            $db->beginTransaction();

            // Lock de la comanda abierta
            $cSt = $db->prepare("
                SELECT c.id, c.mesa_id, c.total, m.numero AS mesa_num
                FROM comandas c
                JOIN mesas m ON m.id = c.mesa_id
                WHERE c.id = :id AND c.estado = 'abierta'
                FOR UPDATE
            ");
            $cSt->execute([':id' => $comandaId]);
            $comanda = $cSt->fetch();
            if (!$comanda) {
                $db->rollBack();
                jsonResponse(['error' => true, 'message' => 'La comanda ya no esta abierta'], 400);
            }

            // Lock de los items -- la venta solo refleja lo que estaba en el
            // momento del cobro (si otro mozo quiere agregar algo mas tarde,
            // la comanda ya estara cerrada y el agregar fallara)
            $itSt = $db->prepare("
                SELECT producto_id, cantidad, precio_unitario, subtotal
                FROM comanda_items
                WHERE comanda_id = :c
                ORDER BY id ASC
                FOR UPDATE
            ");
            $itSt->execute([':c' => $comandaId]);
            $items = $itSt->fetchAll();
            if (!$items) {
                $db->rollBack();
                jsonResponse(['error' => true, 'message' => 'La comanda esta vacia -- no hay nada que cobrar'], 400);
            }

            $total = 0.0;
            foreach ($items as $it) {
                $total += (float) $it['subtotal'];
            }
            $total = round($total, 2);
            $vuelto = max(0.0, round($montoRec - $total, 2));

            // Numero de venta correlativo del dia (V20261022-NNNN)
            $numeroVenta = generarNumeroVenta($db);

            // INSERT venta local. Ventas de ticket no van a SUNAT; igv=0,
            // tipo_comprobante=ticket, estado=completada. ventas.monto_recibido
            // no existe en el schema (ver registrar_venta en linea 1183+); el
            // "recibido" se infiere como total + vuelto cuando hace falta.
            $vIns = $db->prepare("
                INSERT INTO ventas (
                    numero_venta, caja_id, usuario_id,
                    subtotal, igv, total,
                    tipo_pago, tipo_comprobante,
                    vuelto,
                    observaciones, vendedor, comanda_id,
                    estado
                ) VALUES (
                    :nv, :ci, :ui,
                    :sub, 0, :tot,
                    :tp, :tc,
                    :vu,
                    :ob, :vn, :cm,
                    'completada'
                ) RETURNING id, numero_venta
            ");
            $vIns->execute([
                ':nv' => $numeroVenta, ':ci' => $cajaId, ':ui' => sesionId(),
                ':sub' => $total, ':tot' => $total,
                ':tp' => $tipoPago, ':tc' => $tipoComp,
                ':vu' => $vuelto,
                ':ob' => $observac !== '' ? $observac : "Mesa #{$comanda['mesa_num']}",
                ':vn' => sesionNombre(), ':cm' => $comandaId,
            ]);
            $venta = $vIns->fetch();

            // INSERT venta_detalles (SIN tocar stock -- ya se descontaron item
            // por item al agregarse a la comanda). total_vendido si se incrementa:
            // es un contador de "cuanto se vendio" para dashboards, no de stock.
            $dIns = $db->prepare("
                INSERT INTO venta_detalles (
                    venta_id, producto_id, cantidad, precio_unitario, subtotal
                ) VALUES (:v, :p, :q, :pr, :s)
            ");
            $incVendido = $db->prepare("UPDATE productos SET total_vendido = total_vendido + :q WHERE id = :p");
            foreach ($items as $it) {
                $dIns->execute([
                    ':v'  => $venta['id'],
                    ':p'  => $it['producto_id'],
                    ':q'  => $it['cantidad'],
                    ':pr' => $it['precio_unitario'],
                    ':s'  => $it['subtotal'],
                ]);
                $incVendido->execute([':q' => $it['cantidad'], ':p' => $it['producto_id']]);
            }

            $db->prepare("UPDATE cajas SET saldo_actual = saldo_actual + :total WHERE id = :id")
                ->execute([':total' => $total, ':id' => $cajaId]);

            // Cerrar la comanda. El indice parcial unico (WHERE estado='abierta')
            // libera automaticamente la mesa al cambiar estado.
            $db->prepare("
                UPDATE comandas
                SET estado            = 'cerrada',
                    hora_cierre       = CURRENT_TIMESTAMP,
                    usuario_cierre_id = :u,
                    venta_id          = :v,
                    total             = :t
                WHERE id = :id
            ")->execute([
                ':u' => sesionId(), ':v' => $venta['id'],
                ':t' => $total, ':id' => $comandaId,
            ]);

            $db->commit();

            registrarAuditoria(
                'Cobro de comanda',
                'ventas',
                "Mesa #{$comanda['mesa_num']} | Venta: {$numeroVenta} | Total: " . number_format($total, 2) . " | Pago: {$tipoPago}"
            );

            jsonResponse([
                'error'        => false,
                'message'      => "Mesa #{$comanda['mesa_num']} cobrada. Venta {$numeroVenta}",
                'venta_id'     => (int) $venta['id'],
                'numero_venta' => $numeroVenta,
                'mesa_id'      => (int) $comanda['mesa_id'],
                'mesa_numero'  => $comanda['mesa_num'],
                'total'        => $total,
                'vuelto'       => $vuelto,
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonResponse(['error' => true, 'message' => 'Error al cobrar: ' . $e->getMessage()], 500);
        }

    case 'comanda_cambiar_cantidad':
        // Delta de stock: positivo descuenta, negativo repone. Valida stock
        // disponible contra el delta cuando el producto no es servicio.
        $d          = json_decode(file_get_contents('php://input'), true);
        $itemId     = intval($d['item_id'] ?? 0);
        $nuevaCant  = (float)($d['cantidad'] ?? 0);
        if (!$itemId || $nuevaCant <= 0) {
            jsonResponse(['error' => true, 'message' => 'Cantidad invalida'], 400);
        }

        try {
            $db->beginTransaction();

            $itSt = $db->prepare("
                SELECT ci.id, ci.comanda_id, ci.producto_id, ci.cantidad, ci.precio_unitario,
                       COALESCE(ci.factor_unidad, 1) AS factor_unidad,
                       p.product_type, p.stock, p.nombre
                FROM comanda_items ci
                JOIN productos p ON p.id = ci.producto_id
                WHERE ci.id = :id FOR UPDATE
            ");
            $itSt->execute([':id' => $itemId]);
            $item = $itSt->fetch();
            if (!$item) {
                $db->rollBack();
                jsonResponse(['error' => true, 'message' => 'Item no encontrado'], 404);
            }

            $cSt = $db->prepare("SELECT id FROM comandas WHERE id = :c AND estado = 'abierta' FOR UPDATE");
            $cSt->execute([':c' => $item['comanda_id']]);
            if (!$cSt->fetch()) {
                $db->rollBack();
                jsonResponse(['error' => true, 'message' => 'La comanda ya no esta abierta'], 400);
            }

            $factor      = (float)$item['factor_unidad'];
            $delta       = $nuevaCant - (float)$item['cantidad'];
            $deltaStock  = $delta * $factor;  // delta en unidades base
            $esService   = (($item['product_type'] ?? 'product') === 'service');

            if (!$esService && $deltaStock > 0 && (float)$item['stock'] < $deltaStock) {
                $db->rollBack();
                jsonResponse(['error' => true, 'message' => "Sin stock de {$item['nombre']} para aumentar"], 400);
            }

            if (!$esService && $deltaStock != 0.0) {
                $db->prepare("UPDATE productos SET stock = stock - :d WHERE id = :id")
                    ->execute([':d' => $deltaStock, ':id' => $item['producto_id']]);
            }

            $nuevoSubtotal = (float)$item['precio_unitario'] * $nuevaCant;
            $db->prepare("UPDATE comanda_items SET cantidad = :q, subtotal = :s WHERE id = :id")
                ->execute([':q' => $nuevaCant, ':s' => $nuevoSubtotal, ':id' => $itemId]);

            $db->prepare("UPDATE comandas SET total = COALESCE((SELECT SUM(subtotal) FROM comanda_items WHERE comanda_id = :c), 0) WHERE id = :c")
                ->execute([':c' => $item['comanda_id']]);

            $db->commit();
            jsonResponse(['error' => false, 'message' => 'Cantidad actualizada']);
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonResponse(['error' => true, 'message' => 'Error: ' . $e->getMessage()], 500);
        }

    case 'productos':
        $stmt = $db->query("
            SELECT
                p.id, p.codigo, p.codigo_interno, p.codigo_barras, p.codigo_sunat,
                p.nombre, p.precio_venta, p.stock, p.stock_minimo,
                p.laboratorio, p.presentacion, p.categoria_id, p.favorito, p.total_vendido,
                p.unidad_id, p.unidad_codigo, p.afectacion_igv_id, p.afectacion_igv_codigo,
                p.porcentaje_igv, p.incluye_igv, p.icbper_activo, p.factor_icbper,
                a.tipo AS afectacion_tipo, a.descripcion AS afectacion_descripcion,
                c.nombre AS categoria,
                EXISTS (
                    SELECT 1 FROM toma_inventario_sesiones s
                    WHERE s.estado = 'activa' AND p.categoria_id = ANY(s.categorias_ids)
                ) AS en_conteo_inventario,
                -- Promociones (Reportes, Bloque 2, migration_56): precio base (sin IGV
                -- aplicado todavia, misma semantica que p.precio_venta) resultante de la
                -- promocion mas barata vigente para este producto, o NULL si no hay
                -- ninguna. El front recalcula el IGV con getProductUnitSalePrice() igual
                -- que con el precio normal -- no se duplica esa logica aqui.
                (
                    SELECT MIN(
                        CASE pr.tipo_descuento
                            WHEN 'porcentaje' THEN p.precio_venta * (1 - pr.valor_descuento / 100.0)
                            ELSE GREATEST(p.precio_venta - pr.valor_descuento, 0.01)
                        END
                    )
                    FROM promocion_productos pp
                    JOIN promociones pr ON pr.id = pp.promocion_id
                    WHERE pp.producto_id = p.id AND pr.activo = TRUE
                      AND CURRENT_DATE BETWEEN pr.fecha_inicio AND pr.fecha_fin
                ) AS precio_promocional_base
            FROM productos p
            LEFT JOIN public.categorias c ON c.id = p.categoria_id
            LEFT JOIN public.fe_tipos_afectacion_igv a ON a.id = p.afectacion_igv_id
            WHERE p.activo = TRUE
            ORDER BY p.favorito DESC, p.nombre ASC
        ");
        echo json_encode($stmt->fetchAll());
        break;

    case 'series_disponibles':
        $stmt = $db->query("
            SELECT
                tipo,
                serie,
                COALESCE(ultimo_numero, 0) AS ultimo_numero,
                COALESCE(codigo_tipo_documento, CASE tipo
                    WHEN 'factura' THEN '01'
                    WHEN 'boleta' THEN '03'
                    ELSE '02'
                END) AS codigo_tipo_documento,
                COALESCE(descripcion, CASE tipo
                    WHEN 'factura' THEN 'Factura'
                    WHEN 'boleta' THEN 'Boleta'
                    ELSE 'Nota de venta'
                END) AS descripcion
            FROM series_comprobantes
            WHERE COALESCE(activo, TRUE) = TRUE
            ORDER BY tipo, serie
        ");

        $series = [
            'boleta' => [],
            'factura' => [],
            'ticket' => [],
        ];

        foreach ($stmt->fetchAll() as $row) {
            $tipo = trim((string) ($row['tipo'] ?? ''));
            if (!isset($series[$tipo])) {
                $series[$tipo] = [];
            }
            $series[$tipo][] = $row;
        }

        echo json_encode($series);
        break;

    case 'crear_cliente':
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        $nombres = trim($data['nombres'] ?? '');
        if (!$nombres) {
            jsonResponse(['error' => true, 'message' => 'El nombre es obligatorio'], 400);
        }

        $stmt = $db->prepare("
            INSERT INTO clientes (nombres, apellidos, dni, ruc, telefono, email, direccion, activo)
            VALUES (:nombres, :apellidos, NULLIF(:dni,''), NULLIF(:ruc,''), NULLIF(:telefono,''), NULLIF(:email,''), NULLIF(:direccion,''), TRUE)
            RETURNING id, nombres, apellidos, dni, ruc, telefono, email, direccion
        ");
        $stmt->execute([
            ':nombres'   => $nombres,
            ':apellidos' => trim($data['apellidos'] ?? ''),
            ':dni'       => trim($data['dni']       ?? ''),
            ':ruc'       => trim($data['ruc']       ?? ''),
            ':telefono'  => trim($data['telefono']  ?? ''),
            ':email'     => trim($data['email']     ?? ''),
            ':direccion' => trim($data['direccion'] ?? ''),
        ]);
        $cliente = $stmt->fetch();
        echo json_encode(['error' => false, 'cliente' => $cliente]);
        break;

    case 'default_cliente':
        $stmt = $db->query("
            SELECT
                id, nombres, apellidos, dni, ruc, telefono, email, direccion,
                tipo_documento_id, tipo_documento_codigo, numero_documento,
                nombre_completo, razon_social
            FROM clientes
            WHERE activo = TRUE
              AND (
                    id = 1
                    OR COALESCE(numero_documento, '') = '00000000'
                    OR UPPER(COALESCE(razon_social, '')) = 'CLIENTES VARIOS'
                  )
            ORDER BY CASE
                WHEN id = 1 THEN 0
                WHEN COALESCE(numero_documento, '') = '00000000' THEN 1
                ELSE 2
            END
            LIMIT 1
        ");
        $row = $stmt->fetch();
        echo json_encode($row ?: null);
        break;

    case 'buscar_cliente':
        $q = '%' . trim($_GET['q'] ?? '') . '%';
        $stmt = $db->prepare("
            SELECT
                id, nombres, apellidos, dni, ruc, telefono, email, direccion,
                tipo_documento_id, tipo_documento_codigo, numero_documento,
                nombre_completo, razon_social
            FROM clientes
            WHERE activo = TRUE
              AND (
                    nombres ILIKE :q
                    OR apellidos ILIKE :q
                    OR COALESCE(nombre_completo,'') ILIKE :q
                    OR COALESCE(razon_social,'') ILIKE :q
                    OR dni ILIKE :q
                    OR COALESCE(ruc,'') ILIKE :q
                    OR COALESCE(numero_documento,'') ILIKE :q
                  )
              AND COALESCE(numero_documento, '') <> '00000000'
            LIMIT 1
        ");
        $stmt->execute([':q' => $q]);
        $row = $stmt->fetch();
        echo json_encode($row ?: null);
        break;

    case 'registrar_venta':
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!$data || empty($data['items'])) {
            jsonResponse(['error' => true, 'message' => 'Datos invalidos'], 400);
        }

        $cajaStmt = $db->prepare("SELECT id FROM cajas WHERE estado = 'abierta' AND usuario_id = :uid LIMIT 1");
        $cajaStmt->execute([':uid' => (int) sesionId()]);
        $caja = $cajaStmt->fetch();
        if (!$caja) {
            jsonResponse(['error' => true, 'message' => 'No hay una caja abierta. Debes aperturar la caja antes de registrar ventas.', 'caja_cerrada' => true], 422);
        }
        $cajaId = $caja['id'];

        $tipoComp = trim((string) ($data['tipo_comprobante'] ?? 'ticket')) ?: 'ticket';
        $tipoPago = trim((string) ($data['tipo_pago'] ?? 'efectivo')) ?: 'efectivo';
        $serieSolicitada = trim((string) ($data['serie'] ?? ''));
        $esCredito = !empty($data['es_credito']);
        if ($esCredito) {
            $tipoPago = 'credito';
        }
        $montoRecibido = round((float) ($data['monto_recibido'] ?? 0), 2);
        $observaciones = trim((string) ($data['observaciones'] ?? ''));
        $cuotas = [];
        $paymentBreakdown = [];
        if (!empty($data['cuotas']) && is_array($data['cuotas'])) {
            foreach ($data['cuotas'] as $cuota) {
                $amount = round((float) ($cuota['amount'] ?? 0), 2);
                $dueDate = trim((string) ($cuota['due_date'] ?? ''));
                if ($amount <= 0 || $dueDate === '') {
                    continue;
                }
                $cuotas[] = [
                    'amount' => $amount,
                    'due_date' => $dueDate,
                ];
            }
        }
        if (!empty($data['payment_breakdown']) && is_array($data['payment_breakdown'])) {
            foreach ($data['payment_breakdown'] as $payment) {
                $method = strtolower(trim((string) ($payment['method'] ?? '')));
                $amount = round((float) ($payment['amount'] ?? 0), 2);
                $accountId = isset($payment['account_id']) && $payment['account_id'] !== ''
                    ? (int) $payment['account_id']
                    : null;

                if ($method === '' || $amount <= 0) {
                    continue;
                }

                $paymentBreakdown[] = [
                    'method' => $method,
                    'amount' => $amount,
                    'account_id' => $accountId,
                ];
            }
        }

        $db->beginTransaction();
        try {
            $productosInfo = [];
            $detallesVenta = [];

            foreach ($data['items'] as $item) {
                $productoId = (int) ($item['producto_id'] ?? 0);
                $cantidad = round((float) ($item['cantidad'] ?? 0), 2);
                if ($productoId <= 0 || $cantidad <= 0) {
                    throw new Exception('Hay productos invalidos en la venta.');
                }

                $stockStmt = $db->prepare("
                    SELECT
                        p.id, p.stock, p.nombre, p.codigo, p.codigo_interno, p.codigo_sunat,
                        p.precio_venta, p.unidad_id, p.unidad_codigo, p.afectacion_igv_id,
                        p.afectacion_igv_codigo, p.porcentaje_igv, p.incluye_igv,
                        p.icbper_activo, p.factor_icbper, p.categoria_id,
                        a.tipo AS afectacion_tipo
                    FROM productos p
                    LEFT JOIN public.fe_tipos_afectacion_igv a ON a.id = p.afectacion_igv_id
                    WHERE p.id = :id AND p.activo = TRUE
                ");
                $stockStmt->execute([':id' => $productoId]);
                $producto = $stockStmt->fetch();

                if (!$producto) {
                    throw new Exception("Producto ID {$productoId} no encontrado");
                }

                // Bloquea la venta si la categoria del producto tiene una
                // Toma de Inventario activa -- desde que "Aplicar" reemplaza
                // el stock directamente (no suma/resta), una venta hecha
                // mientras se cuenta esa categoria se perderia al aplicar.
                if ($producto['categoria_id']) {
                    $tomaStmt = $db->prepare("
                        SELECT COALESCE(c.nombre, 'La categoría de este producto') AS categoria_nombre
                        FROM toma_inventario_sesiones s
                        LEFT JOIN public.categorias c ON c.id = :cat_id1
                        WHERE s.estado = 'activa' AND :cat_id2 = ANY(s.categorias_ids)
                        LIMIT 1
                    ");
                    $tomaStmt->execute([
                        ':cat_id1' => $producto['categoria_id'],
                        ':cat_id2' => $producto['categoria_id'],
                    ]);
                    $tomaRow = $tomaStmt->fetch();
                    if ($tomaRow) {
                        throw new Exception("\"{$producto['nombre']}\" no se puede vender: la categoría \"{$tomaRow['categoria_nombre']}\" está en conteo de inventario. Espera a que se cierre la sesión.");
                    }
                }

                // Si el item viene con una unidad de medida configurada (ej.
                // BLISTER), se resuelve su precio y factor del lado del
                // servidor (nunca se confia en lo que mande el navegador).
                $unidadVendida = trim((string) ($item['unidad_medida'] ?? ''));
                $factorEquivalencia = 1.0;
                if ($unidadVendida !== '') {
                    $puStmt = $db->prepare("
                        SELECT unidad_medida, cantidad, precio_venta
                        FROM producto_precios_unidad
                        WHERE producto_id = :pid AND unidad_medida = :unidad
                    ");
                    $puStmt->execute([':pid' => $productoId, ':unidad' => $unidadVendida]);
                    $presentacion = $puStmt->fetch();
                    if (!$presentacion) {
                        throw new Exception("La unidad de medida '{$unidadVendida}' no esta configurada para: {$producto['nombre']}");
                    }
                    $factorEquivalencia = (float) $presentacion['cantidad'];
                    $producto['precio_venta'] = $presentacion['precio_venta'];
                } else {
                    $unidadVendida = null;
                    // Promociones: nunca sube el precio, solo lo baja si conviene.
                    $precioPromo = ventasPromoPrecioMinimo($db, $productoId, (float) $producto['precio_venta']);
                    if ($precioPromo !== null && $precioPromo < (float) $producto['precio_venta']) {
                        $producto['precio_venta'] = $precioPromo;
                    }
                }

                $cantidadBase = round($cantidad * $factorEquivalencia, 2);
                if ((float) $producto['stock'] < $cantidadBase) {
                    throw new Exception("Stock insuficiente para: {$producto['nombre']} (disponible: {$producto['stock']})");
                }

                $detalle = ventasCalcularDetalleTributario($producto, $cantidad);
                $detalle['producto_id'] = $productoId;
                $detalle['nombre'] = $producto['nombre'];
                $detalle['codigo'] = $producto['codigo'];
                $detalle['unidad_medida_vendida'] = $unidadVendida;
                $detalle['factor_equivalencia'] = $factorEquivalencia;
                $detalle['cantidad_base'] = $cantidadBase;

                $productosInfo[$productoId] = $detalle;
                $detallesVenta[] = $detalle;
            }

            $descuento = round((float) ($data['descuento'] ?? 0), 2);
            [$detallesVenta, $descuentoAplicado] = ventasAplicarDescuentoGlobal($detallesVenta, $descuento);
            $resumen = ventasResumenDesdeDetalles($detallesVenta);
            $subtotal = $resumen['subtotal'];
            $descuento = $descuentoAplicado;
            $igv = $resumen['igv'];
            $total = $resumen['total'];

            if ($esCredito) {
                if (empty($cuotas)) {
                    throw new Exception('Debes registrar al menos una cuota para la venta a crédito.');
                }

                $creditTotal = round(array_sum(array_map(fn ($row) => (float) ($row['amount'] ?? 0), $cuotas)), 2);
                if (abs($creditTotal - $total) > 0.01) {
                    throw new Exception('La suma de las cuotas debe coincidir con el total de la venta.');
                }

                $paymentBreakdown = [];
                $montoRecibido = 0.0;
                $vuelto = 0.0;
                $tipoPago = 'credito';
            } elseif ($paymentBreakdown) {
                $paymentTotal = round(array_sum(array_map(fn ($payment) => (float) ($payment['amount'] ?? 0), $paymentBreakdown)), 2);
                if (abs($paymentTotal - $total) > 0.01) {
                    throw new Exception('La suma de los métodos de pago debe coincidir con el total de la venta.');
                }

                $cashReceived = round(array_sum(array_map(fn ($payment) => strtolower((string) ($payment['method'] ?? '')) === 'efectivo'
                    ? (float) ($payment['amount'] ?? 0)
                    : 0, $paymentBreakdown)), 2);
                $montoRecibido = $cashReceived;
            } else {
                $paymentBreakdown = [[
                    'method' => $tipoPago,
                    'amount' => $total,
                    'account_id' => $tipoPago === 'efectivo' ? null : ((int) ($data['payment_account_id'] ?? 0) ?: null),
                ]];
                $montoRecibido = $montoRecibido > 0 ? $montoRecibido : $total;
            }

            $vuelto = $tipoPago === 'efectivo' ? round(max(0, $montoRecibido - $total), 2) : 0.0;

            $clienteInfo = null;
            if (!empty($data['cliente_id'])) {
                $cliStmt = $db->prepare("
                    SELECT
                        id, nombres, apellidos, dni, ruc, telefono, email, direccion,
                        tipo_documento_id, tipo_documento_codigo, numero_documento,
                        nombre_completo, razon_social, ubigeo
                    FROM clientes
                    WHERE id = :id
                ");
                $cliStmt->execute([':id' => $data['cliente_id']]);
                $clienteInfo = $cliStmt->fetch() ?: null;
            }

            if ($tipoComp === 'factura' && (!$clienteInfo || empty($clienteInfo['ruc']))) {
                throw new Exception('Para emitir factura debe seleccionar un cliente con RUC registrado');
            }

            if ($esCredito && (!$clienteInfo || ventasEsClienteVarios($clienteInfo))) {
                throw new Exception('Para vender a crédito debes seleccionar un cliente real, no Clientes Varios.');
            }

            $comprobanteMeta = ventasMetaComprobante($tipoComp);
            $formaPagoId = $esCredito ? null : ventasResolverFormaPagoId($db, $tipoPago);
            $sunatFormaPago = $esCredito ? 'Credito' : 'Contado';

            $serie = null;
            $correlativo = null;
            $tipoDocumentoId = null;
            if (in_array($tipoComp, ['boleta', 'factura', 'ticket'], true)) {
                $seriesSql = "
                    UPDATE series_comprobantes
                    SET ultimo_numero = COALESCE(ultimo_numero, 0) + 1
                    WHERE tipo = :tipo
                      AND COALESCE(activo, TRUE) = TRUE
                ";
                $seriesParams = [':tipo' => $tipoComp];

                if ($serieSolicitada !== '') {
                    $seriesSql .= " AND serie = :serie";
                    $seriesParams[':serie'] = $serieSolicitada;
                }

                $seriesSql .= "
                    RETURNING serie,
                              ultimo_numero,
                              COALESCE(codigo_tipo_documento, :codigo_tipo_documento) AS codigo_tipo_documento,
                              tipo_documento_id,
                              COALESCE(descripcion, :descripcion) AS descripcion
                ";
                $seriesParams[':codigo_tipo_documento'] = $comprobanteMeta['codigo'];
                $seriesParams[':descripcion'] = $comprobanteMeta['descripcion'];

                $seriesStmt = $db->prepare($seriesSql);
                $seriesStmt->execute($seriesParams);
                $serieRow = $seriesStmt->fetch();

                if (!$serieRow) {
                    throw new Exception("No hay serie configurada para {$comprobanteMeta['descripcion']}.");
                }

                $serie = $serieRow['serie'];
                $correlativoDigitos = $tipoComp === 'ticket' ? 4 : 8;
                $correlativo = str_pad((string) intval($serieRow['ultimo_numero']), $correlativoDigitos, '0', STR_PAD_LEFT);
                $tipoDocumentoId = $serieRow['tipo_documento_id'] ?? null;
            }

            $numeroVenta = generarNumeroVenta($db);
            $ventaStmt = $db->prepare("
                INSERT INTO ventas (
                    numero_venta, cliente_id, caja_id, usuario_id, subtotal, descuento, igv, total,
                    tipo_pago, tipo_comprobante, estado, vendedor, observaciones,
                    fecha_emision, fecha_vencimiento, hora_emision, moneda_codigo,
                    tipo_documento_id, codigo_tipo_documento, serie, correlativo,
                    operacion_tipo_codigo, forma_pago_id, sunat_forma_pago,
                    exonerada, inafecta, gravada, gratuita, anticipo, otros_cargos,
                    icbper, monto_credito, vuelto, cuotas, payment_breakdown
                )
                VALUES (
                    :numero_venta, :cliente_id, :caja_id, :usuario_id, :subtotal, :descuento, :igv, :total,
                    :tipo_pago, :tipo_comprobante, 'completada', :vendedor, :observaciones,
                    CURRENT_DATE, CURRENT_DATE, CURRENT_TIME, 'PEN',
                    :tipo_documento_id, :codigo_tipo_documento, :serie, :correlativo,
                    '0101', :forma_pago_id, :sunat_forma_pago,
                    :exonerada, :inafecta, :gravada, 0, 0, 0,
                    :icbper, :monto_credito, :vuelto, :cuotas, :payment_breakdown
                )
                RETURNING id
            ");
            $ventaStmt->execute([
                ':numero_venta' => $numeroVenta,
                ':cliente_id' => $data['cliente_id'] ?: null,
                ':caja_id' => $cajaId,
                ':usuario_id' => sesionId() ?: null,
                ':vendedor' => sesionNombre() ?: 'Administrador',
                ':subtotal' => $subtotal,
                ':descuento' => $descuento,
                ':igv' => $igv,
                ':total' => $total,
                ':tipo_pago' => $tipoPago,
                ':tipo_comprobante' => $tipoComp,
                ':observaciones' => $observaciones !== '' ? $observaciones : null,
                ':tipo_documento_id' => $tipoDocumentoId,
                ':codigo_tipo_documento' => $comprobanteMeta['codigo'],
                ':serie' => $serie,
                ':correlativo' => $correlativo,
                ':forma_pago_id' => $formaPagoId,
                ':sunat_forma_pago' => $sunatFormaPago,
                ':exonerada' => $resumen['exonerada'],
                ':inafecta' => $resumen['inafecta'],
                ':gravada' => $resumen['gravada'],
                ':icbper' => $resumen['icbper'],
                ':monto_credito' => $esCredito ? $total : 0,
                ':vuelto' => $vuelto,
                ':cuotas' => json_encode($cuotas, JSON_UNESCAPED_UNICODE),
                ':payment_breakdown' => json_encode($paymentBreakdown, JSON_UNESCAPED_UNICODE),
            ]);
            $ventaId = $ventaStmt->fetch()['id'];

            // Token público para "Mi Comprobante" -- permite al cliente ver
            // su comprobante sin login, resuelto vía public.comprobante_tokens.
            $comprobanteToken = bin2hex(random_bytes(16));
            $db->prepare("
                INSERT INTO public.comprobante_tokens (token, schema_name, venta_id)
                VALUES (:token, :schema, :venta_id)
            ")->execute([
                ':token'    => $comprobanteToken,
                ':schema'   => sesionSchema(),
                ':venta_id' => $ventaId,
            ]);

            foreach ($detallesVenta as $detalle) {
                $detalleStmt = $db->prepare("
                    INSERT INTO venta_detalles (
                        venta_id, producto_id, cantidad, precio_unitario, descuento, subtotal,
                        unidad_id, unidad_codigo, afectacion_igv_id, afectacion_igv_codigo,
                        descripcion, codigo_interno, codigo_sunat, igv, valor_unitario,
                        valor_total, precio_total, icbper, factor_icbper,
                        unidad_medida_vendida, factor_equivalencia
                    )
                    VALUES (
                        :venta_id, :producto_id, :cantidad, :precio_unitario, :descuento, :subtotal,
                        :unidad_id, :unidad_codigo, :afectacion_id, :afectacion_codigo,
                        :descripcion, :codigo_interno, :codigo_sunat, :igv, :valor_unitario,
                        :valor_total, :precio_total, :icbper, :factor_icbper,
                        :unidad_medida_vendida, :factor_equivalencia
                    )
                ");
                $detalleStmt->execute([
                    ':venta_id' => $ventaId,
                    ':producto_id' => $detalle['producto_id'],
                    ':cantidad' => $detalle['cantidad'],
                    ':precio_unitario' => $detalle['precio_unitario'],
                    ':subtotal' => $detalle['precio_total'],
                    ':descuento' => $detalle['descuento'] ?? 0,
                    ':unidad_id' => $detalle['unidad_id'],
                    ':unidad_codigo' => $detalle['unidad_codigo'],
                    ':afectacion_id' => $detalle['afectacion_id'],
                    ':afectacion_codigo' => $detalle['afectacion_codigo'],
                    ':descripcion' => $detalle['descripcion'],
                    ':codigo_interno' => $detalle['codigo_interno'],
                    ':codigo_sunat' => $detalle['codigo_sunat'],
                    ':igv' => $detalle['igv'],
                    ':valor_unitario' => $detalle['valor_unitario'],
                    ':valor_total' => $detalle['valor_total'],
                    ':precio_total' => $detalle['precio_total'],
                    ':icbper' => $detalle['icbper'],
                    ':factor_icbper' => $detalle['factor_icbper'],
                    ':unidad_medida_vendida' => $detalle['unidad_medida_vendida'],
                    ':factor_equivalencia' => $detalle['factor_equivalencia'],
                ]);

                $db->prepare("
                    UPDATE productos
                    SET stock = stock - :cantidad_base,
                        total_vendido = total_vendido + :cantidad_base,
                        updated_at = NOW()
                    WHERE id = :id
                ")->execute([
                    ':cantidad_base' => $detalle['cantidad_base'],
                    ':id' => $detalle['producto_id'],
                ]);
            }

            $db->prepare("UPDATE cajas SET saldo_actual = saldo_actual + :total WHERE id = :id")
                ->execute([':total' => $total, ':id' => $cajaId]);

            $compData = null;
            if (in_array($tipoComp, ['boleta', 'factura'], true)) {
                $numeroCompleto = $serie . '-' . $correlativo;

                $cpeStmt = $db->prepare("
                    INSERT INTO comprobantes_electronicos (
                        venta_id, tipo, serie, numero, numero_completo, estado_sunat,
                        codigo_tipo_documento, fecha_emision
                    )
                    VALUES (
                        :venta_id, :tipo, :serie, :numero, :numero_completo, 'pendiente',
                        :codigo_tipo_documento, CURRENT_DATE
                    )
                    RETURNING id
                ");
                $cpeStmt->execute([
                    ':venta_id' => $ventaId,
                    ':tipo' => $tipoComp,
                    ':serie' => $serie,
                    ':numero' => intval($correlativo),
                    ':numero_completo' => $numeroCompleto,
                    ':codigo_tipo_documento' => $comprobanteMeta['codigo'],
                ]);

                $compData = [
                    'id' => $cpeStmt->fetch()['id'],
                    'tipo' => $tipoComp,
                    'serie' => $serie,
                    'numero' => intval($correlativo),
                    'numero_completo' => $numeroCompleto,
                    'codigo_tipo_documento' => $comprobanteMeta['codigo'],
                ];
            }

            $db->commit();

            $comprobanteResult = null;
            if ($compData) {
                require_once '../../config/sunat.php';
                $itemsSunat = array_values($productosInfo);
                $ventaInfo = [
                    'numero_venta' => $numeroVenta,
                    'tipo_pago' => $tipoPago,
                    'sunat_forma_pago' => $sunatFormaPago,
                    'fecha_emision' => date('Y-m-d'),
                    'hora_emision' => date('H:i:s'),
                    'moneda' => 'PEN',
                    'totales' => $resumen,
                ];
                $comprobanteResult = enviar_sunat($db, $compData, $clienteInfo, $itemsSunat, $ventaInfo);
                // Si fallo por error de red/timeout (no hay CDR y no hubo respuesta
                // de SUNAT), programar el primer reintento para dentro de 5 minutos.
                // El worker scripts/reenviar_sunat_pendientes.php lo tomara.
                $falloRed = $comprobanteResult
                    && !empty($comprobanteResult['error_nubefact'])
                    && empty($comprobanteResult['enlace_del_cdr'])
                    && ($comprobanteResult['response_code'] ?? null) === null;
                if ($falloRed) {
                    $db->prepare("
                        UPDATE comprobantes_electronicos
                        SET reenvio_intentos = 0,
                            reenvio_proximo_at = NOW() + interval '5 minutes',
                            reenvio_ultimo_error = :err
                        WHERE id = :id
                    ")->execute([
                        ':err' => (string) ($comprobanteResult['mensaje'] ?? 'Error desconocido'),
                        ':id'  => (int) $compData['id'],
                    ]);
                }
            }

            echo json_encode([
                'error' => false,
                'numero_venta' => $numeroVenta,
                'venta_id' => $ventaId,
                'subtotal' => $subtotal,
                'gravada' => $resumen['gravada'],
                'exonerada' => $resumen['exonerada'],
                'inafecta' => $resumen['inafecta'],
                'igv' => $igv,
                'icbper' => $resumen['icbper'],
                'descuento' => $descuento,
                'total' => $total,
                'tipo_pago' => $tipoPago,
                'tipo_comprobante' => $tipoComp,
                'serie' => $serie,
                'correlativo' => $correlativo,
                'monto_recibido' => $esCredito ? 0 : ($montoRecibido > 0 ? $montoRecibido : $total),
                'vuelto' => $vuelto,
                'cuotas' => $cuotas,
                'payment_breakdown' => $paymentBreakdown,
                'items' => array_map(fn($i) => [
                    'producto_id' => $i['producto_id'],
                    'nombre' => $i['nombre'],
                    'codigo' => $i['codigo'],
                    'cantidad' => $i['cantidad'],
                    'qty' => $i['cantidad'],
                    'precio' => $i['precio_unitario'],
                    'valor_unitario' => $i['valor_unitario'],
                    'valor_total' => $i['valor_total'],
                    'precio_total' => $i['precio_total'],
                    'igv' => $i['igv'],
                    'afectacion_igv_codigo' => $i['afectacion_codigo'],
                    'afectacion_tipo' => $i['afectacion_tipo'],
                    'unidad_codigo' => $i['unidad_codigo'],
                    'unidad_medida_vendida' => $i['unidad_medida_vendida'] ?? null,
                ], $detallesVenta),
                'comprobante' => $comprobanteResult,
                'comprobante_token' => $comprobanteToken,
            ]);
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 422);
        }
        break;

    case 'historial':
        $desde  = $_GET['desde']  ?? date('Y-m-d', strtotime('-30 days'));
        $hasta  = $_GET['hasta']  ?? date('Y-m-d');
        $estado = $_GET['estado'] ?? '';
        $q      = '%' . ($_GET['q'] ?? '') . '%';

        $params = [':desde' => $desde, ':hasta' => $hasta . ' 23:59:59', ':q' => $q];
        $estFilter = $estado ? "AND v.estado = :estado" : '';
        if ($estado) {
            $params[':estado'] = $estado;
        }

        $stmt = $db->prepare("
            SELECT
                v.id, v.numero_venta, v.total, v.subtotal, v.descuento, v.igv,
                v.tipo_pago, v.tipo_comprobante, v.estado, v.vendedor, v.created_at,
                COALESCE(c.razon_social, c.nombre_completo, c.nombres || ' ' || COALESCE(c.apellidos,''), 'CLIENTES VARIOS') AS cliente,
                COUNT(vd.id) AS num_items
            FROM ventas v
            LEFT JOIN clientes c ON c.id = v.cliente_id
            LEFT JOIN venta_detalles vd ON vd.venta_id = v.id
            WHERE v.created_at BETWEEN :desde AND :hasta
              AND (
                    v.numero_venta ILIKE :q
                    OR COALESCE(c.nombres,'') ILIKE :q
                    OR COALESCE(c.razon_social,'') ILIKE :q
                  )
              $estFilter
            GROUP BY v.id, c.razon_social, c.nombre_completo, c.nombres, c.apellidos
            ORDER BY v.created_at DESC
            LIMIT 200
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    case 'detalle_venta':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $db->prepare("
            SELECT vd.*, p.nombre AS producto_nombre, p.codigo
            FROM venta_detalles vd
            JOIN productos p ON p.id = vd.producto_id
            WHERE vd.venta_id = :id
        ");
        $stmt->execute([':id' => $id]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'detalle_venta_ticket':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => true, 'message' => 'ID inválido'], 400);

        $ventaStmt = $db->prepare("
            SELECT v.id, v.numero_venta, v.total, v.subtotal, v.descuento, v.igv,
                   v.gravada, v.exonerada, v.inafecta, v.vuelto, v.cuotas, v.payment_breakdown,
                   v.tipo_pago, v.tipo_comprobante, v.estado, v.serie, v.correlativo, v.created_at,
                   c.nombres, c.apellidos, c.razon_social, c.numero_documento, c.tipo_documento_codigo,
                   c.direccion, c.telefono
            FROM ventas v
            LEFT JOIN clientes c ON c.id = v.cliente_id
            WHERE v.id = :id
        ");
        $ventaStmt->execute([':id' => $id]);
        $venta = $ventaStmt->fetch();
        if (!$venta) jsonResponse(['error' => true, 'message' => 'Venta no encontrada'], 404);

        $itemsStmt = $db->prepare("
            SELECT vd.cantidad, vd.precio_unitario, vd.precio_total, vd.unidad_medida_vendida,
                   p.nombre AS producto_nombre, p.codigo
            FROM venta_detalles vd
            JOIN productos p ON p.id = vd.producto_id
            WHERE vd.venta_id = :id
            ORDER BY vd.id
        ");
        $itemsStmt->execute([':id' => $id]);
        $venta['items'] = $itemsStmt->fetchAll();

        $tokenStmt = $db->prepare("SELECT token FROM public.comprobante_tokens WHERE venta_id = :id AND schema_name = :schema LIMIT 1");
        $tokenStmt->execute([':id' => $id, ':schema' => sesionSchema()]);
        $tokenRow = $tokenStmt->fetch();
        $venta['comprobante_token'] = $tokenRow['token'] ?? null;

        echo json_encode($venta);
        break;

    case 'anular_venta':
        if (isCajero()) {
            jsonResponse(['error' => true, 'message' => 'No tienes permiso para anular ventas'], 403);
        }
        $raw    = file_get_contents('php://input');
        $data   = json_decode($raw, true);
        $id     = intval($data['id'] ?? 0);
        $motivo = trim((string) ($data['motivo'] ?? ''));

        if ($motivo === '') {
            jsonResponse(['error' => true, 'message' => 'Debes indicar el motivo de la anulación'], 400);
        }

        $db->beginTransaction();
        try {
            $check = $db->prepare("SELECT numero_venta, estado, total, caja_id FROM ventas WHERE id = :id");
            $check->execute([':id' => $id]);
            $venta = $check->fetch();

            if (!$venta) {
                throw new Exception('Venta no encontrada');
            }
            if ($venta['estado'] === 'anulada') {
                throw new Exception('La venta ya esta anulada');
            }

            if ($venta['caja_id']) {
                $cajaVenta = $db->prepare("SELECT usuario_id FROM cajas WHERE id = :cid AND estado = 'abierta'");
                $cajaVenta->execute([':cid' => $venta['caja_id']]);
                $cajaVentaRow = $cajaVenta->fetch();
                if ($cajaVentaRow && (int) $cajaVentaRow['usuario_id'] !== (int) sesionId()) {
                    throw new Exception('La caja de esta venta fue aperturada por otro usuario. No puedes anularla.');
                }
            }

            $detalles = $db->prepare("SELECT producto_id, cantidad, COALESCE(factor_equivalencia, 1) AS factor_equivalencia FROM venta_detalles WHERE venta_id = :id");
            $detalles->execute([':id' => $id]);
            foreach ($detalles->fetchAll() as $det) {
                $cantidadBase = (float) $det['cantidad'] * (float) $det['factor_equivalencia'];
                $db->prepare("UPDATE productos SET stock = stock + :qty WHERE id = :pid")
                    ->execute([':qty' => $cantidadBase, ':pid' => $det['producto_id']]);
            }

            $db->prepare("UPDATE ventas SET estado = 'anulada', motivo_anulacion = :motivo WHERE id = :id")
                ->execute([':motivo' => $motivo, ':id' => $id]);

            if ($venta['caja_id']) {
                $db->prepare("UPDATE cajas SET saldo_actual = saldo_actual - :total WHERE id = :cid")
                    ->execute([':total' => $venta['total'], ':cid' => $venta['caja_id']]);
            }

            $db->commit();

            registrarAuditoria('Anulación de venta', 'ventas', "Venta: {$venta['numero_venta']} | Total: {$venta['total']} | Motivo: {$motivo}");

            echo json_encode(['error' => false, 'message' => 'Venta anulada correctamente']);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 422);
        }
        break;

    case 'stats_dia':
        $hoy = date('Y-m-d');
        $row = $db->query("
            SELECT
                COUNT(*) FILTER (WHERE estado = 'completada') AS total_ventas,
                COALESCE(SUM(total) FILTER (WHERE estado = 'completada'), 0) AS ingresos,
                COALESCE(AVG(total) FILTER (WHERE estado = 'completada'), 0) AS ticket_promedio,
                COUNT(*) FILTER (WHERE estado = 'anulada') AS anuladas
            FROM ventas
            WHERE DATE(created_at) = '$hoy'
        ")->fetch();
        echo json_encode($row);
        break;

    case 'top_vendidos':
        $limite = intval($_GET['limite'] ?? 10);
        $stmt = $db->prepare("
            SELECT
                p.id, p.nombre, p.laboratorio, p.total_vendido, p.precio_venta,
                p.favorito, c.nombre AS categoria
            FROM productos p
            LEFT JOIN public.categorias c ON c.id = p.categoria_id
            WHERE p.activo = TRUE AND p.total_vendido > 0
            ORDER BY p.total_vendido DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode($stmt->fetchAll());
        break;

    case 'favoritos':
        $stmt = $db->query("
            SELECT
                p.id, p.nombre, p.laboratorio, p.total_vendido, p.precio_venta, p.stock,
                c.nombre AS categoria
            FROM productos p
            LEFT JOIN public.categorias c ON c.id = p.categoria_id
            WHERE p.activo = TRUE AND p.favorito = TRUE
            ORDER BY p.total_vendido DESC
        ");
        echo json_encode($stmt->fetchAll());
        break;

    case 'toggle_favorito':
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);
        $id   = intval($data['id'] ?? 0);
        $stmt = $db->prepare("UPDATE productos SET favorito = NOT favorito WHERE id = :id RETURNING favorito");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        echo json_encode(['error' => false, 'favorito' => $row['favorito']]);
        break;

    default:
        jsonResponse(['error' => true, 'message' => 'Accion no valida'], 404);
}
