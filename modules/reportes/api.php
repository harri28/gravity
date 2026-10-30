<?php
// ============================================================
// ARCHIVO: farmacia/modules/reportes/api.php
// DESCRIPCION: API REST para el modulo de Reportes (solo admin/gerente)
//              Costos de compras, ventas por periodo/vendedor,
//              valorizacion de inventario y movimientos de caja.
// ============================================================

header('Content-Type: application/json; charset=UTF-8');
require_once '../../config/database.php';
requireApiAuth(['admin', 'gerente']);

$action = $_GET['action'] ?? '';
$db     = getDB();

function reportesRangoFechas(): array
{
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    return [$desde, $hasta . ' 23:59:59'];
}

function reportesCostosComprasFiltro(): array
{
    [$desde, $hastaDt] = reportesRangoFechas();
    $proveedorId = intval($_GET['proveedor_id'] ?? 0);
    $estado      = trim((string) ($_GET['estado'] ?? ''));

    $where  = "WHERE oc.created_at BETWEEN :desde AND :hasta";
    $params = [':desde' => $desde, ':hasta' => $hastaDt];
    if ($proveedorId) { $where .= " AND oc.proveedor_id = :proveedor_id"; $params[':proveedor_id'] = $proveedorId; }
    if ($estado !== '') { $where .= " AND oc.estado = :estado"; $params[':estado'] = $estado; }

    return [$where, $params];
}

function reportesAnulacionesFiltro(): array
{
    [$desde, $hastaDt] = reportesRangoFechas();
    return [
        "WHERE v.estado = 'anulada' AND v.created_at BETWEEN :desde AND :hasta",
        [':desde' => $desde, ':hasta' => $hastaDt],
    ];
}

// CTE compartida por anulaciones_stats -- la lista de ventas anuladas del periodo
// se calcula UNA vez y el resto son subconsultas de solo lectura sobre ella.
// Nota: no existe en el esquema una columna "quien anulo" ni "fecha de anulacion"
// ('anular_venta' en modules/ventas/api.php solo guarda estado + motivo_anulacion) --
// "vendedor" aqui es el dueño original de la venta (quien la registro), no
// necesariamente quien hizo clic en anular. Se deja asi, con el nombre de columna
// honesto en vez de inventar precision que el dato no tiene.
function reportesAnulacionesCTE(string $where): string
{
    return "
        WITH anuladas AS (
            SELECT v.id, v.numero_venta, v.created_at, v.total, v.motivo_anulacion,
                   COALESCE(v.vendedor, 'Sin asignar') AS vendedor
            FROM ventas v
            $where
        )
    ";
}

// Dias hacia atras sin ninguna venta para considerar un producto "paralizado".
// Parametro ?dias_sin_venta=, acotado a un rango razonable (idea gemela de
// reportesInventarioDiasVenta, pero aqui busca ausencia de venta, no velocidad).
function reportesStockParalizadoDias(): int
{
    $dias = intval($_GET['dias_sin_venta'] ?? 60);
    if ($dias < 7)   { $dias = 7; }
    if ($dias > 365) { $dias = 365; }
    return $dias;
}

// Productos con stock > 0 que no tuvieron NINGUNA venta completada en los
// ultimos N dias -- capital "dormido" en estanteria. Distinto de "dias de
// cobertura" (Bloque 3): ese mide que tan rapido se agota lo que SI se
// vende; esto detecta lo que directamente dejo de venderse.
function reportesStockParalizadoFiltro(): array
{
    $categoriaId = intval($_GET['categoria_id'] ?? 0);
    $dias = reportesStockParalizadoDias();

    $where = "
        WHERE p.activo = TRUE AND p.eliminado = FALSE AND p.stock > 0
          AND NOT EXISTS (
              SELECT 1 FROM venta_detalles vd
              JOIN ventas v ON v.id = vd.venta_id
              WHERE vd.producto_id = p.id AND v.estado = 'completada'
                AND v.created_at >= NOW() - make_interval(days => :dias_sin_venta::int)
          )
    ";
    $params = [':dias_sin_venta' => $dias];
    if ($categoriaId) { $where .= " AND p.categoria_id = :categoria_id"; $params[':categoria_id'] = $categoriaId; }

    return [$where, $params];
}

function reportesCajaMovimientosFiltro(): array
{
    [$desde, $hastaDt] = reportesRangoFechas();
    $tipo = trim((string) ($_GET['tipo'] ?? ''));

    $where  = "WHERE cm.created_at BETWEEN :desde AND :hasta";
    $params = [':desde' => $desde, ':hasta' => $hastaDt];
    if ($tipo !== '') { $where .= " AND cm.tipo = :tipo"; $params[':tipo'] = $tipo; }

    return [$where, $params];
}

function reportesVentasAgrupacion(string $agrupar): array
{
    switch ($agrupar) {
        case 'dia':
            return ["TO_CHAR(v.created_at, 'YYYY-MM-DD')", 'Fecha'];
        case 'comprobante':
            return ['v.tipo_comprobante', 'Comprobante'];
        default:
            return ["COALESCE(v.vendedor, 'Sin asignar')", 'Vendedor'];
    }
}

function reportesProductosRankingFiltro(): array
{
    [$desde, $hastaDt] = reportesRangoFechas();
    $categoriaId = intval($_GET['categoria_id'] ?? 0);

    $where  = "WHERE v.estado = 'completada' AND v.created_at BETWEEN :desde AND :hasta";
    $params = [':desde' => $desde, ':hasta' => $hastaDt];
    if ($categoriaId) { $where .= " AND p.categoria_id = :categoria_id"; $params[':categoria_id'] = $categoriaId; }

    return [$where, $params];
}

// CTE compartida por productos_ranking / productos_ranking_stats / productos_ranking_exportar:
// agrupa venta_detalles por producto en el periodo, calcula margen (ingreso - costo
// estimado al precio_compra actual) y clasifica ABC por ingreso acumulado (Pareto 80/15/5).
function reportesProductosRankingCTE(string $where): string
{
    return "
        WITH ventas_prod AS (
            SELECT
                p.id, p.codigo, p.nombre,
                COALESCE(cat.nombre, 'Sin categoría') AS categoria,
                SUM(vd.cantidad)                            AS cantidad_vendida,
                SUM(vd.subtotal)                             AS ingreso,
                SUM(vd.cantidad * COALESCE(p.precio_compra, 0)) AS costo_estimado
            FROM venta_detalles vd
            JOIN ventas v     ON v.id = vd.venta_id
            JOIN productos p  ON p.id = vd.producto_id
            LEFT JOIN public.categorias cat ON cat.id = p.categoria_id
            $where
            GROUP BY p.id, p.codigo, p.nombre, cat.nombre
        ),
        clasificado AS (
            SELECT
                *,
                (ingreso - costo_estimado) AS margen,
                ROUND((ingreso / NULLIF(SUM(ingreso) OVER (), 0)) * 100, 2) AS pct_ingreso,
                ROUND((SUM(ingreso) OVER (ORDER BY ingreso DESC ROWS UNBOUNDED PRECEDING)
                       / NULLIF(SUM(ingreso) OVER (), 0)) * 100, 2)         AS pct_acumulado
            FROM ventas_prod
        )
        SELECT *,
            CASE WHEN pct_acumulado <= 80 THEN 'A'
                 WHEN pct_acumulado <= 95 THEN 'B'
                 ELSE 'C' END AS clase_abc
        FROM clasificado
    ";
}

// Whitelist de orden -- nunca se interpola el parametro del navegador directo en el SQL.
function reportesProductosRankingOrden(string $orden): string
{
    $map = [
        'ingreso_desc'  => 'ingreso DESC',
        'ingreso_asc'   => 'ingreso ASC',
        'cantidad_desc' => 'cantidad_vendida DESC',
        'cantidad_asc'  => 'cantidad_vendida ASC',
    ];
    return $map[$orden] ?? $map['ingreso_desc'];
}

function reportesInventarioFiltro(): array
{
    $categoriaId = intval($_GET['categoria_id'] ?? 0);
    $soloActivos = ($_GET['solo_activos'] ?? '1') !== '0';
    $q           = '%' . trim((string) ($_GET['q'] ?? '')) . '%';

    $where  = "WHERE (p.nombre ILIKE :q OR p.codigo ILIKE :q OR COALESCE(p.codigo_interno,'') ILIKE :q)";
    $params = [':q' => $q];
    if ($categoriaId) { $where .= " AND p.categoria_id = :categoria_id"; $params[':categoria_id'] = $categoriaId; }
    if ($soloActivos) { $where .= " AND p.activo = TRUE"; }

    return [$where, $params];
}

// Dias hacia atras para calcular la velocidad de venta (idea "dias de cobertura
// de stock"). Parametro ?dias_venta=, acotado a un rango razonable para evitar
// consultas absurdas (ej. 0 o 50 años).
function reportesInventarioDiasVenta(): int
{
    $dias = intval($_GET['dias_venta'] ?? 30);
    if ($dias < 7)   { $dias = 7; }
    if ($dias > 365) { $dias = 365; }
    return $dias;
}

// Subconsulta reutilizable: cantidad vendida por producto en los ultimos N dias
// (solo ventas completadas), para derivar velocidad de venta diaria y, con eso,
// en cuantos dias se agotaria el stock actual al ritmo reciente -- mas util que
// un umbral fijo de "stock bajo" porque avisa antes si la venta se acelero.
function reportesInventarioVelocidadJoin(): string
{
    return "
        LEFT JOIN (
            SELECT vd.producto_id, SUM(vd.cantidad) AS cantidad_vendida
            FROM venta_detalles vd
            JOIN ventas v ON v.id = vd.venta_id
            WHERE v.estado = 'completada' AND v.created_at >= NOW() - make_interval(days => :dias_venta::int)
            GROUP BY vd.producto_id
        ) vel ON vel.producto_id = p.id
    ";
}

// Whitelist de orden -- nunca se interpola el query string directo en el SQL.
function reportesInventarioOrden(string $orden): string
{
    $map = [
        'valor_desc'     => 'valor_inventario DESC',
        'valor_asc'      => 'valor_inventario ASC',
        'cobertura_asc'  => 'dias_cobertura ASC NULLS LAST',
        'cobertura_desc' => 'dias_cobertura DESC NULLS LAST',
    ];
    return $map[$orden] ?? $map['valor_desc'];
}

function reportesCsvOutput(string $filenameBase, array $rows): void
{
    $filename = $filenameBase . '_' . date('Ymd_His') . '.csv';

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
}

switch ($action) {

    // ----------------------------------------------------------------
    // COSTOS DE COMPRAS
    // ----------------------------------------------------------------
    case 'costos_compras':
        try {
            [$where, $params] = reportesCostosComprasFiltro();
            $stmt = $db->prepare("
                SELECT oc.id, oc.numero_orden, oc.estado, oc.created_at,
                       oc.subtotal, oc.igv, oc.costo_envio, oc.total,
                       COALESCE(p.razon_social, 'Sin proveedor') AS proveedor
                FROM ordenes_compra oc
                LEFT JOIN proveedores p ON p.id = oc.proveedor_id
                $where
                ORDER BY oc.created_at DESC
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => 'Error al cargar el reporte: ' . $e->getMessage()], 500);
        }
        break;

    case 'costos_compras_stats':
        try {
            [$where, $params] = reportesCostosComprasFiltro();
            $stmt = $db->prepare("
                SELECT
                    COUNT(*)                          AS total_ordenes,
                    COALESCE(SUM(oc.subtotal), 0)     AS total_subtotal,
                    COALESCE(SUM(oc.igv), 0)          AS total_igv,
                    COALESCE(SUM(oc.costo_envio), 0)  AS total_envio,
                    COALESCE(SUM(oc.total), 0)        AS total_general
                FROM ordenes_compra oc
                $where
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetch());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'costos_compras_exportar':
        [$where, $params] = reportesCostosComprasFiltro();
        $stmt = $db->prepare("
            SELECT
                oc.numero_orden                                  AS \"N° Orden\",
                TO_CHAR(oc.created_at, 'DD/MM/YYYY')            AS \"Fecha\",
                COALESCE(p.razon_social, 'Sin proveedor')        AS \"Proveedor\",
                UPPER(oc.estado)                                 AS \"Estado\",
                ROUND(oc.subtotal::numeric, 2)                   AS \"Subtotal\",
                ROUND(oc.igv::numeric, 2)                        AS \"IGV\",
                ROUND(oc.costo_envio::numeric, 2)                AS \"Costo Envío\",
                ROUND(oc.total::numeric, 2)                      AS \"Total\"
            FROM ordenes_compra oc
            LEFT JOIN proveedores p ON p.id = oc.proveedor_id
            $where
            ORDER BY oc.created_at ASC
        ");
        $stmt->execute($params);
        reportesCsvOutput('costos_compras', $stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // Items de una orden de compra (para el drill: orden -> items -> resumen).
    case 'compra_orden_items':
        $ordenId = (int) ($_GET['orden_id'] ?? 0);
        if ($ordenId <= 0) {
            jsonResponse(['error' => true, 'message' => 'Orden no válida'], 422);
        }
        try {
            $ocStmt = $db->prepare("
                SELECT oc.id, oc.numero_orden, oc.estado, oc.created_at,
                       oc.subtotal, oc.igv, oc.costo_envio, oc.total,
                       COALESCE(p.razon_social, 'Sin proveedor') AS proveedor
                FROM ordenes_compra oc
                LEFT JOIN proveedores p ON p.id = oc.proveedor_id
                WHERE oc.id = :id
            ");
            $ocStmt->execute([':id' => $ordenId]);
            $orden = $ocStmt->fetch();
            if (!$orden) {
                jsonResponse(['error' => true, 'message' => 'Orden no encontrada'], 404);
            }
            $itStmt = $db->prepare("
                SELECT d.producto_id,
                       COALESCE(pr.nombre, d.descripcion, 'Producto') AS nombre,
                       COALESCE(pr.codigo, '') AS codigo,
                       d.unidad_medida, d.cantidad, d.precio_unitario, d.subtotal
                FROM orden_compra_detalles d
                LEFT JOIN productos pr ON pr.id = d.producto_id
                WHERE d.orden_id = :id
                ORDER BY d.id
            ");
            $itStmt->execute([':id' => $ordenId]);
            echo json_encode(['orden' => $orden, 'items' => $itStmt->fetchAll()]);
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    // Resumen histórico (todo el periodo) de las compras de un producto.
    // Excluye ordenes canceladas. Totales + por proveedor + historial.
    case 'compra_producto_resumen':
        $prodId = (int) ($_GET['producto_id'] ?? 0);
        if ($prodId <= 0) {
            jsonResponse(['error' => true, 'message' => 'Producto no válido'], 422);
        }
        try {
            $prod = $db->prepare("SELECT nombre, COALESCE(codigo,'') AS codigo FROM productos WHERE id = :id");
            $prod->execute([':id' => $prodId]);
            $producto = $prod->fetch() ?: ['nombre' => '(producto eliminado)', 'codigo' => ''];

            $res = $db->prepare("
                SELECT COALESCE(SUM(d.cantidad), 0)        AS total_cantidad,
                       COALESCE(SUM(d.subtotal), 0)        AS total_gastado,
                       COUNT(DISTINCT d.orden_id)          AS n_compras,
                       COALESCE(AVG(d.precio_unitario), 0) AS precio_prom,
                       COALESCE(MIN(d.precio_unitario), 0) AS precio_min,
                       COALESCE(MAX(d.precio_unitario), 0) AS precio_max
                FROM orden_compra_detalles d
                JOIN ordenes_compra oc ON oc.id = d.orden_id
                WHERE d.producto_id = :id AND oc.estado <> 'cancelada'
            ");
            $res->execute([':id' => $prodId]);
            $resumen = $res->fetch();

            $ult = $db->prepare("
                SELECT d.precio_unitario
                FROM orden_compra_detalles d
                JOIN ordenes_compra oc ON oc.id = d.orden_id
                WHERE d.producto_id = :id AND oc.estado <> 'cancelada'
                ORDER BY oc.created_at DESC LIMIT 1
            ");
            $ult->execute([':id' => $prodId]);
            $resumen['precio_ultimo'] = $ult->fetch()['precio_unitario'] ?? null;

            $prov = $db->prepare("
                SELECT COALESCE(p.razon_social, 'Sin proveedor') AS proveedor,
                       SUM(d.cantidad) AS cantidad, SUM(d.subtotal) AS gastado,
                       COUNT(DISTINCT d.orden_id) AS compras,
                       MIN(d.precio_unitario) AS precio_min, MAX(d.precio_unitario) AS precio_max
                FROM orden_compra_detalles d
                JOIN ordenes_compra oc ON oc.id = d.orden_id
                LEFT JOIN proveedores p ON p.id = oc.proveedor_id
                WHERE d.producto_id = :id AND oc.estado <> 'cancelada'
                GROUP BY 1 ORDER BY gastado DESC
            ");
            $prov->execute([':id' => $prodId]);

            $hist = $db->prepare("
                SELECT oc.numero_orden, oc.created_at, oc.estado,
                       COALESCE(p.razon_social, 'Sin proveedor') AS proveedor,
                       d.cantidad, d.precio_unitario, d.subtotal
                FROM orden_compra_detalles d
                JOIN ordenes_compra oc ON oc.id = d.orden_id
                LEFT JOIN proveedores p ON p.id = oc.proveedor_id
                WHERE d.producto_id = :id AND oc.estado <> 'cancelada'
                ORDER BY oc.created_at DESC
            ");
            $hist->execute([':id' => $prodId]);

            echo json_encode([
                'producto'      => $producto,
                'resumen'       => $resumen,
                'por_proveedor' => $prov->fetchAll(),
                'historial'     => $hist->fetchAll(),
            ]);
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'proveedores_lista':
        try {
            $rows = $db->query("
                SELECT id, razon_social
                FROM proveedores
                WHERE activo = TRUE
                ORDER BY razon_social
            ")->fetchAll();
            echo json_encode($rows);
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;

    // ----------------------------------------------------------------
    // VENTAS POR PERIODO / VENDEDOR
    // ----------------------------------------------------------------
    case 'ventas_reporte':
        try {
            [$desde, $hastaDt] = reportesRangoFechas();
            $agrupar = trim((string) ($_GET['agrupar'] ?? 'vendedor'));
            [$groupExpr, ] = reportesVentasAgrupacion($agrupar);

            // Ranking: ademas de los totales por grupo, calcula cuanto representa
            // cada uno sobre el ingreso total del periodo (pct_participacion) y
            // como se compara contra el promedio del propio grupo (pct_vs_promedio)
            // -- ambos con funciones de ventana sobre el resultado ya agrupado, sin
            // una segunda consulta.
            $stmt = $db->prepare("
                WITH base AS (
                    SELECT
                        $groupExpr                                                          AS etiqueta,
                        COUNT(*) FILTER (WHERE v.estado = 'completada')                     AS total_ventas,
                        COALESCE(SUM(v.total) FILTER (WHERE v.estado = 'completada'), 0)    AS total_ingresos,
                        COALESCE(SUM(v.igv)   FILTER (WHERE v.estado = 'completada'), 0)    AS total_igv,
                        COALESCE(AVG(v.total) FILTER (WHERE v.estado = 'completada'), 0)    AS ticket_promedio,
                        COUNT(*) FILTER (WHERE v.estado = 'anulada')                        AS total_anuladas
                    FROM ventas v
                    WHERE v.created_at BETWEEN :desde AND :hasta
                    GROUP BY $groupExpr
                )
                SELECT
                    *,
                    ROUND((total_ingresos / NULLIF(SUM(total_ingresos) OVER (), 0)) * 100, 2) AS pct_participacion,
                    ROUND(AVG(total_ingresos) OVER (), 2)                                      AS promedio_grupo,
                    CASE WHEN AVG(total_ingresos) OVER () > 0
                         THEN ROUND((total_ingresos / AVG(total_ingresos) OVER ()) * 100, 1)
                         ELSE 0 END                                                             AS pct_vs_promedio
                FROM base
                ORDER BY total_ingresos DESC
            ");
            $stmt->execute([':desde' => $desde, ':hasta' => $hastaDt]);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    // Drill del reporte de Ventas agrupado por vendedor: ventas individuales
    // de un vendedor dentro del mismo rango de fechas del filtro.
    case 'ventas_por_vendedor':
        [$desde, $hastaDt] = reportesRangoFechas();
        $vendedor = trim((string) ($_GET['vendedor'] ?? ''));
        if ($vendedor === '') {
            jsonResponse(['error' => true, 'message' => 'Vendedor no indicado'], 422);
        }
        try {
            $stmt = $db->prepare("
                SELECT v.numero_venta, v.created_at, v.total, v.tipo_comprobante, v.estado,
                       COALESCE(NULLIF(TRIM(c.nombres || ' ' || COALESCE(c.apellidos,'')),''),
                                c.razon_social, c.nombre_completo, 'Cliente General') AS cliente,
                       COALESCE(ce.numero_completo, '') AS comprobante_numero
                FROM ventas v
                LEFT JOIN clientes c ON c.id = v.cliente_id
                LEFT JOIN comprobantes_electronicos ce ON ce.venta_id = v.id AND ce.tipo IN ('boleta','factura')
                WHERE v.created_at BETWEEN :desde AND :hasta
                  AND COALESCE(v.vendedor, 'Sin asignar') = :vend
                ORDER BY v.created_at DESC
            ");
            $stmt->execute([':desde' => $desde, ':hasta' => $hastaDt, ':vend' => $vendedor]);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'ventas_exportar':
        [$desde, $hastaDt] = reportesRangoFechas();
        $agrupar = trim((string) ($_GET['agrupar'] ?? 'vendedor'));
        [$groupExpr, $groupLabel] = reportesVentasAgrupacion($agrupar);

        $stmt = $db->prepare("
            WITH base AS (
                SELECT
                    $groupExpr                                                          AS etiqueta,
                    COUNT(*) FILTER (WHERE v.estado = 'completada')                     AS total_ventas,
                    COALESCE(SUM(v.total) FILTER (WHERE v.estado = 'completada'), 0)    AS total_ingresos,
                    COALESCE(SUM(v.igv)   FILTER (WHERE v.estado = 'completada'), 0)    AS total_igv,
                    COALESCE(AVG(v.total) FILTER (WHERE v.estado = 'completada'), 0)    AS ticket_promedio,
                    COUNT(*) FILTER (WHERE v.estado = 'anulada')                        AS total_anuladas
                FROM ventas v
                WHERE v.created_at BETWEEN :desde AND :hasta
                GROUP BY $groupExpr
            )
            SELECT
                etiqueta                                                                  AS \"$groupLabel\",
                total_ventas                                                               AS \"N° Ventas\",
                ROUND(total_ingresos::numeric, 2)                                          AS \"Total Ingresos\",
                ROUND(total_igv::numeric, 2)                                               AS \"Total IGV\",
                ROUND(ticket_promedio::numeric, 2)                                         AS \"Ticket Promedio\",
                ROUND((total_ingresos / NULLIF(SUM(total_ingresos) OVER (), 0) * 100)::numeric, 2) AS \"% Participación\",
                total_anuladas                                                             AS \"Anuladas\"
            FROM base
            ORDER BY \"Total Ingresos\" DESC
        ");
        $stmt->execute([':desde' => $desde, ':hasta' => $hastaDt]);
        reportesCsvOutput('ventas', $stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // ----------------------------------------------------------------
    // VALORIZACION DE INVENTARIO
    // ----------------------------------------------------------------
    case 'inventario_valorizacion':
        try {
            [$where, $params] = reportesInventarioFiltro();
            $params[':dias_venta'] = reportesInventarioDiasVenta();
            $orden = reportesInventarioOrden(trim((string) ($_GET['orden'] ?? 'valor_desc')));
            $velJoin = reportesInventarioVelocidadJoin();
            $stmt = $db->prepare("
                SELECT p.id, p.codigo, p.nombre, COALESCE(cat.nombre, 'Sin categoría') AS categoria,
                       p.stock, p.stock_minimo,
                       COALESCE(p.precio_compra, 0) AS precio_compra,
                       p.precio_venta,
                       ROUND((p.stock * COALESCE(p.precio_compra, 0))::numeric, 2) AS valor_inventario,
                       p.activo,
                       COALESCE(vel.cantidad_vendida, 0) AS cantidad_vendida_periodo,
                       ROUND((COALESCE(vel.cantidad_vendida, 0) / :dias_venta::numeric)::numeric, 3) AS velocidad_diaria,
                       CASE WHEN COALESCE(vel.cantidad_vendida, 0) > 0
                            THEN ROUND((p.stock / (vel.cantidad_vendida / :dias_venta::numeric))::numeric, 1)
                            ELSE NULL END AS dias_cobertura
                FROM productos p
                LEFT JOIN public.categorias cat ON cat.id = p.categoria_id
                $velJoin
                $where
                ORDER BY $orden
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'inventario_valorizacion_stats':
        try {
            [$where, $params] = reportesInventarioFiltro();
            $params[':dias_venta'] = reportesInventarioDiasVenta();
            $velJoin = reportesInventarioVelocidadJoin();
            $stmt = $db->prepare("
                SELECT
                    COUNT(*)                                                              AS total_productos,
                    COALESCE(SUM(p.stock * COALESCE(p.precio_compra, 0)), 0)               AS valor_total_compra,
                    COALESCE(SUM(p.stock * p.precio_venta), 0)                             AS valor_total_venta,
                    COUNT(*) FILTER (WHERE p.stock = 0)                                     AS agotados,
                    COUNT(*) FILTER (WHERE p.stock > 0 AND p.stock <= p.stock_minimo)       AS stock_bajo,
                    COUNT(*) FILTER (
                        WHERE p.stock > 0 AND COALESCE(vel.cantidad_vendida, 0) > 0
                          AND (p.stock / (vel.cantidad_vendida / :dias_venta::numeric)) <= 7
                    )                                                                       AS en_riesgo_quiebre
                FROM productos p
                $velJoin
                $where
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetch());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'inventario_valorizacion_exportar':
        [$where, $params] = reportesInventarioFiltro();
        $params[':dias_venta'] = reportesInventarioDiasVenta();
        $ordenExportMap = [
            'valor_desc'     => '"Valor Inventario" DESC',
            'valor_asc'      => '"Valor Inventario" ASC',
            'cobertura_asc'  => '"Días de Cobertura" ASC NULLS LAST',
            'cobertura_desc' => '"Días de Cobertura" DESC NULLS LAST',
        ];
        $orden = $ordenExportMap[trim((string) ($_GET['orden'] ?? 'valor_desc'))] ?? $ordenExportMap['valor_desc'];
        $velJoin = reportesInventarioVelocidadJoin();
        $stmt = $db->prepare("
            SELECT
                p.codigo                                                          AS \"Código\",
                p.nombre                                                          AS \"Producto\",
                COALESCE(cat.nombre, 'Sin categoría')                             AS \"Categoría\",
                p.stock                                                           AS \"Stock\",
                ROUND(COALESCE(p.precio_compra, 0)::numeric, 2)                   AS \"Precio Compra\",
                ROUND(p.precio_venta::numeric, 2)                                 AS \"Precio Venta\",
                ROUND((p.stock * COALESCE(p.precio_compra, 0))::numeric, 2)       AS \"Valor Inventario\",
                COALESCE(vel.cantidad_vendida, 0)                                 AS \"Vendido en Periodo\",
                CASE WHEN COALESCE(vel.cantidad_vendida, 0) > 0
                     THEN ROUND((p.stock / (vel.cantidad_vendida / :dias_venta::numeric))::numeric, 1)
                     ELSE NULL END                                               AS \"Días de Cobertura\",
                CASE WHEN p.activo THEN 'Activo' ELSE 'Inactivo' END              AS \"Estado\"
            FROM productos p
            $velJoin
            LEFT JOIN public.categorias cat ON cat.id = p.categoria_id
            $where
            ORDER BY $orden
        ");
        $stmt->execute($params);
        reportesCsvOutput('valorizacion_inventario', $stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'inventario_nombre_stock_exportar':
        [$where, $params] = reportesInventarioFiltro();
        $stmt = $db->prepare("
            SELECT
                p.nombre AS \"Producto\",
                p.stock  AS \"Stock\"
            FROM productos p
            $where
            ORDER BY p.nombre ASC
        ");
        $stmt->execute($params);
        reportesCsvOutput('productos_stock', $stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'categorias_lista':
        try {
            $rows = $db->query("SELECT id, nombre FROM public.categorias ORDER BY nombre")->fetchAll();
            echo json_encode($rows);
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;

    // ----------------------------------------------------------------
    // MOVIMIENTOS DE CAJA
    // ----------------------------------------------------------------
    case 'caja_movimientos':
        try {
            [$where, $params] = reportesCajaMovimientosFiltro();
            $stmt = $db->prepare("
                SELECT cm.id, cm.tipo, cm.monto, cm.concepto, cm.usuario, cm.created_at,
                       c.nombre AS caja_nombre
                FROM caja_movimientos cm
                JOIN cajas c ON c.id = cm.caja_id
                $where
                ORDER BY cm.created_at DESC
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'caja_movimientos_stats':
        try {
            [$where, $params] = reportesCajaMovimientosFiltro();
            $stmt = $db->prepare("
                SELECT
                    COUNT(*)                                                            AS total_movimientos,
                    COALESCE(SUM(cm.monto) FILTER (WHERE cm.tipo = 'ingreso'), 0)       AS total_ingresos,
                    COALESCE(SUM(cm.monto) FILTER (WHERE cm.tipo = 'egreso'), 0)        AS total_egresos
                FROM caja_movimientos cm
                $where
            ");
            $stmt->execute($params);
            $row = $stmt->fetch();
            $row['neto'] = (float) $row['total_ingresos'] - (float) $row['total_egresos'];
            echo json_encode($row);
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'caja_movimientos_exportar':
        [$where, $params] = reportesCajaMovimientosFiltro();
        $stmt = $db->prepare("
            SELECT
                TO_CHAR(cm.created_at, 'DD/MM/YYYY HH24:MI')  AS \"Fecha\",
                c.nombre                                       AS \"Caja\",
                UPPER(cm.tipo)                                 AS \"Tipo\",
                COALESCE(cm.concepto, '')                      AS \"Concepto\",
                COALESCE(cm.usuario, '')                        AS \"Usuario\",
                ROUND(cm.monto::numeric, 2)                    AS \"Monto\"
            FROM caja_movimientos cm
            JOIN cajas c ON c.id = cm.caja_id
            $where
            ORDER BY cm.created_at ASC
        ");
        $stmt->execute($params);
        reportesCsvOutput('movimientos_caja', $stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // ----------------------------------------------------------------
    // PRODUCTOS MAS / MENOS VENDIDOS (ranking ABC / Pareto 80-15-5)
    // ----------------------------------------------------------------
    case 'productos_ranking':
        try {
            [$where, $params] = reportesProductosRankingFiltro();
            $orden = reportesProductosRankingOrden(trim((string) ($_GET['orden'] ?? 'ingreso_desc')));
            $stmt = $db->prepare(reportesProductosRankingCTE($where) . " ORDER BY $orden");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'productos_ranking_stats':
        try {
            [$where, $params] = reportesProductosRankingFiltro();
            // 'ranked' envuelve la CTE completa UNA sola vez -- el filtro de fechas/categoria
            // (:desde/:hasta[/:categoria_id]) solo aparece una vez en toda la consulta, el resto
            // son subconsultas que leen de 'ranked' sin volver a filtrar nada.
            $stmt = $db->prepare("
                WITH ranked AS (" . reportesProductosRankingCTE($where) . ")
                SELECT
                    (SELECT COUNT(*) FROM ranked)                                AS total_productos,
                    (SELECT COALESCE(SUM(ingreso), 0) FROM ranked)               AS ingreso_total,
                    (SELECT COALESCE(SUM(margen), 0) FROM ranked)                AS margen_total,
                    (SELECT COUNT(*) FROM ranked WHERE clase_abc = 'A')         AS productos_clase_a,
                    (SELECT nombre  FROM ranked ORDER BY ingreso DESC LIMIT 1)   AS top_nombre,
                    (SELECT ingreso FROM ranked ORDER BY ingreso DESC LIMIT 1)   AS top_ingreso
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetch());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'productos_ranking_exportar':
        [$where, $params] = reportesProductosRankingFiltro();
        $orden = reportesProductosRankingOrden(trim((string) ($_GET['orden'] ?? 'ingreso_desc')));
        $stmt = $db->prepare("
            SELECT
                codigo                            AS \"Código\",
                nombre                            AS \"Producto\",
                categoria                         AS \"Categoría\",
                cantidad_vendida                  AS \"Cantidad Vendida\",
                ROUND(ingreso::numeric, 2)        AS \"Ingreso\",
                ROUND(margen::numeric, 2)         AS \"Margen Estimado\",
                pct_acumulado                     AS \"% Acumulado\",
                clase_abc                         AS \"Clase ABC\"
            FROM (" . reportesProductosRankingCTE($where) . ") r
            ORDER BY $orden
        ");
        $stmt->execute($params);
        reportesCsvOutput('productos_ranking', $stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // ----------------------------------------------------------------
    // ANULACIONES
    // ----------------------------------------------------------------
    case 'anulaciones_listar':
        try {
            [$where, $params] = reportesAnulacionesFiltro();
            $stmt = $db->prepare("
                SELECT
                    v.id, v.numero_venta, v.created_at, v.total, v.motivo_anulacion, v.vendedor,
                    (
                        SELECT STRING_AGG(DISTINCT p.nombre, ', ')
                        FROM venta_detalles vd
                        JOIN productos p ON p.id = vd.producto_id
                        WHERE vd.venta_id = v.id
                    ) AS productos
                FROM ventas v
                $where
                ORDER BY v.created_at DESC
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'anulaciones_stats':
        try {
            [$where, $params] = reportesAnulacionesFiltro();
            $stmt = $db->prepare(
                reportesAnulacionesCTE($where) . ",
                por_vendedor AS (
                    SELECT vendedor, COUNT(*) AS n FROM anuladas GROUP BY vendedor ORDER BY n DESC, vendedor LIMIT 1
                ),
                por_producto AS (
                    SELECT p.nombre, COUNT(*) AS n
                    FROM venta_detalles vd
                    JOIN productos p ON p.id = vd.producto_id
                    WHERE vd.venta_id IN (SELECT id FROM anuladas)
                    GROUP BY p.nombre ORDER BY n DESC, p.nombre LIMIT 1
                )
                SELECT
                    (SELECT COUNT(*) FROM anuladas)               AS total_anuladas,
                    (SELECT COALESCE(SUM(total), 0) FROM anuladas) AS monto_anulado,
                    (SELECT vendedor FROM por_vendedor)            AS vendedor_top,
                    (SELECT n FROM por_vendedor)                   AS vendedor_top_count,
                    (SELECT nombre FROM por_producto)              AS producto_top,
                    (SELECT n FROM por_producto)                   AS producto_top_count
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetch());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'anulaciones_exportar':
        [$where, $params] = reportesAnulacionesFiltro();
        $stmt = $db->prepare("
            SELECT
                v.numero_venta                                       AS \"N° Venta\",
                TO_CHAR(v.created_at, 'DD/MM/YYYY HH24:MI')           AS \"Fecha de Venta\",
                COALESCE(v.vendedor, 'Sin asignar')                   AS \"Vendedor\",
                ROUND(v.total::numeric, 2)                            AS \"Monto\",
                COALESCE(v.motivo_anulacion, '')                      AS \"Motivo\",
                COALESCE((
                    SELECT STRING_AGG(DISTINCT p.nombre, ', ')
                    FROM venta_detalles vd
                    JOIN productos p ON p.id = vd.producto_id
                    WHERE vd.venta_id = v.id
                ), '')                                                 AS \"Productos\"
            FROM ventas v
            $where
            ORDER BY v.created_at DESC
        ");
        $stmt->execute($params);
        reportesCsvOutput('anulaciones', $stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // ----------------------------------------------------------------
    // STOCK PARALIZADO (capital inmovilizado)
    // ----------------------------------------------------------------
    case 'stock_paralizado':
        try {
            [$where, $params] = reportesStockParalizadoFiltro();
            $stmt = $db->prepare("
                SELECT
                    p.id, p.codigo, p.codigo_interno, p.nombre,
                    COALESCE(cat.nombre, 'Sin categoría') AS categoria,
                    p.stock, COALESCE(p.precio_compra, 0) AS precio_compra, p.precio_venta,
                    ROUND((p.stock * COALESCE(p.precio_compra, 0))::numeric, 2) AS valor_inventario,
                    (
                        SELECT MAX(v2.created_at)
                        FROM venta_detalles vd2
                        JOIN ventas v2 ON v2.id = vd2.venta_id
                        WHERE vd2.producto_id = p.id AND v2.estado = 'completada'
                    ) AS ultima_venta
                FROM productos p
                LEFT JOIN public.categorias cat ON cat.id = p.categoria_id
                $where
                ORDER BY valor_inventario DESC
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'stock_paralizado_stats':
        try {
            [$where, $params] = reportesStockParalizadoFiltro();
            $stmt = $db->prepare("
                SELECT
                    COUNT(*)                                                        AS total_productos,
                    COALESCE(SUM(p.stock * COALESCE(p.precio_compra, 0)), 0)        AS valor_inmovilizado
                FROM productos p
                $where
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetch());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'stock_paralizado_exportar':
        [$where, $params] = reportesStockParalizadoFiltro();
        $stmt = $db->prepare("
            SELECT
                p.codigo                                                          AS \"Código\",
                p.nombre                                                          AS \"Producto\",
                COALESCE(cat.nombre, 'Sin categoría')                             AS \"Categoría\",
                p.stock                                                           AS \"Stock\",
                ROUND(COALESCE(p.precio_compra, 0)::numeric, 2)                   AS \"Precio Compra\",
                ROUND((p.stock * COALESCE(p.precio_compra, 0))::numeric, 2)       AS \"Valor Inmovilizado\",
                COALESCE(TO_CHAR((
                    SELECT MAX(v2.created_at)
                    FROM venta_detalles vd2
                    JOIN ventas v2 ON v2.id = vd2.venta_id
                    WHERE vd2.producto_id = p.id AND v2.estado = 'completada'
                ), 'DD/MM/YYYY'), 'Nunca')                                        AS \"Última Venta\"
            FROM productos p
            LEFT JOIN public.categorias cat ON cat.id = p.categoria_id
            $where
            ORDER BY \"Valor Inmovilizado\" DESC
        ");
        $stmt->execute($params);
        reportesCsvOutput('stock_paralizado', $stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    // NOTA: la gestión de Promociones se movió al módulo Inventario
    // (modules/inventario/api.php: promociones_listar / promocion_crear /
    // promocion_toggle_activo). El botón "Crear promoción con seleccionados"
    // del tab Stock Paralizado redirige allá con los productos preseleccionados.
    default:
        jsonResponse(['error' => true, 'message' => 'Acción no válida'], 400);
}
