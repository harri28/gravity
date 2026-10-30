<?php
// ============================================================
// ARCHIVO: farmacia/modules/dashboard/api.php
// DESCRIPCIÓN: API REST para el módulo de Dashboard
// ============================================================

header('Content-Type: application/json; charset=UTF-8');
require_once '../../config/database.php';
requireApiAuth(['admin', 'gerente', 'cajero']);

$action = $_GET['action'] ?? '';
$db     = getDB();

// ---- Rango de fechas del filtro global (periodo=hoy|semana|mes|mes_ant|rango) ----
// Devuelve el rango pedido y el "periodo anterior" de igual duracion que lo
// precede inmediatamente (para los comparativos de las tarjetas).
function dashRango(): array {
    $p   = $_GET['periodo'] ?? 'hoy';
    $hoy = new DateTime('today');
    $d   = clone $hoy;
    $h   = clone $hoy;

    switch ($p) {
        case 'semana':
            $d = (clone $hoy)->modify('monday this week');
            break;
        case 'mes':
            $d = new DateTime('first day of this month 00:00:00');
            break;
        case 'mes_ant':
            $d = new DateTime('first day of last month 00:00:00');
            $h = new DateTime('last day of last month 00:00:00');
            break;
        case 'rango':
            $re = '/^\d{4}-\d{2}-\d{2}$/';
            $gd = $_GET['desde'] ?? '';
            $gh = $_GET['hasta'] ?? '';
            if (preg_match($re, $gd) && preg_match($re, $gh)) {
                $d = new DateTime($gd);
                $h = new DateTime($gh);
                if ($d > $h) { $t = $d; $d = $h; $h = $t; }
                // Tope de 366 dias para no armar series enormes
                $minD = (clone $h)->modify('-365 days');
                if ($d < $minD) $d = $minD;
            } else {
                $p = 'hoy';
            }
            break;
        default:
            $p = 'hoy';
    }

    $dias = (int) $d->diff($h)->days + 1;
    $hp   = (clone $d)->modify('-1 day');
    $dp   = (clone $hp)->modify('-' . ($dias - 1) . ' days');

    return [
        'periodo'   => $p,
        'dias'      => $dias,
        'desde'     => $d->format('Y-m-d'),
        'hasta'     => $h->format('Y-m-d'),
        'ini'       => $d->format('Y-m-d') . ' 00:00:00',
        'fin'       => $h->format('Y-m-d') . ' 23:59:59',
        'ini_prev'  => $dp->format('Y-m-d') . ' 00:00:00',
        'fin_prev'  => $hp->format('Y-m-d') . ' 23:59:59',
    ];
}

// KPIs del periodo. El filtro de ventas ($cond sin alias / $condV con alias "v.")
// puede ser por rango de fechas o por caja (turno). Las compras no estan amarradas
// a la caja, asi que siempre se filtran por una ventana de tiempo ($comprasIni/$comprasFin).
function dashKpisPeriodo(PDO $db, string $cond, string $condV, array $params, string $comprasIni, string $comprasFin, bool $admin): array {
    $s = $db->prepare("
        SELECT
            COUNT(*) FILTER (WHERE estado = 'completada') AS total_ventas,
            COALESCE(SUM(total) FILTER (WHERE estado = 'completada'), 0) AS ingresos,
            COALESCE(AVG(total) FILTER (WHERE estado = 'completada'), 0) AS ticket_promedio,
            COUNT(*) FILTER (WHERE estado = 'anulada') AS anuladas
        FROM ventas
        WHERE $cond
    ");
    $s->execute($params);
    $r = $s->fetch();
    $out = [
        'total_ventas'    => (int) $r['total_ventas'],
        'ingresos'        => (float) $r['ingresos'],
        'ticket_promedio' => (float) $r['ticket_promedio'],
        'anuladas'        => (int) $r['anuladas'],
    ];

    if ($admin) {
        // Mismo calculo de costo que Rentabilidad (precio_compra actual).
        $g = $db->prepare("
            SELECT COALESCE(SUM(vd.subtotal - vd.cantidad * p.precio_compra), 0) AS ganancia
            FROM ventas v
            JOIN venta_detalles vd ON vd.venta_id = v.id
            JOIN productos p       ON p.id = vd.producto_id
            WHERE v.estado = 'completada' AND $condV
        ");
        $g->execute($params);
        $out['ganancia'] = (float) $g->fetchColumn();

        $c = $db->prepare("
            SELECT COALESCE(SUM(total), 0) AS monto, COUNT(*) AS n
            FROM ordenes_compra
            WHERE estado <> 'cancelada' AND created_at BETWEEN :cini AND :cfin
        ");
        $c->execute([':cini' => $comprasIni, ':cfin' => $comprasFin]);
        $cr = $c->fetch();
        $out['compras']   = (float) $cr['monto'];
        $out['compras_n'] = (int) $cr['n'];
    }
    return $out;
}

// Estructura de KPIs en cero (cuando no hay turno anterior con que comparar).
function dashKpisVacio(bool $admin): array {
    $o = ['total_ventas' => 0, 'ingresos' => 0.0, 'ticket_promedio' => 0.0, 'anuladas' => 0];
    if ($admin) { $o['ganancia'] = 0.0; $o['compras'] = 0.0; $o['compras_n'] = 0; }
    return $o;
}

// Caja abierta del usuario en sesion (el "turno" actual). Null si no tiene ninguna.
function dashTurnoCaja(PDO $db): ?array {
    $s = $db->prepare("SELECT * FROM cajas WHERE estado = 'abierta' AND usuario_id = :uid ORDER BY apertura_at DESC LIMIT 1");
    $s->execute([':uid' => (int) sesionId()]);
    $c = $s->fetch();
    return $c ?: null;
}

// Ultimo turno cerrado del usuario (para el comparativo "vs turno anterior").
function dashTurnoCajaPrev(PDO $db): ?array {
    $s = $db->prepare("SELECT * FROM cajas WHERE estado = 'cerrada' AND usuario_id = :uid AND cierre_at IS NOT NULL ORDER BY cierre_at DESC LIMIT 1");
    $s->execute([':uid' => (int) sesionId()]);
    $c = $s->fetch();
    return $c ?: null;
}

// Filtro de ventas para serie/metodos/top: por caja (turno) o por rango de fechas.
// Si piden 'turno' pero no hay caja abierta, cae a 'hoy'.
function dashVentasFiltro(PDO $db): array {
    $p = $_GET['periodo'] ?? 'hoy';
    if ($p === 'turno') {
        $caja = dashTurnoCaja($db);
        if ($caja) {
            return ['modo' => 'turno', 'cond' => 'caja_id = :cid', 'condV' => 'v.caja_id = :cid',
                    'params' => [':cid' => (int) $caja['id']], 'caja' => $caja];
        }
        $_GET['periodo'] = 'hoy';
    }
    $r = dashRango();
    return ['modo' => 'rango', 'cond' => 'created_at BETWEEN :ini AND :fin', 'condV' => 'v.created_at BETWEEN :ini AND :fin',
            'params' => [':ini' => $r['ini'], ':fin' => $r['fin']], 'rango' => $r];
}

switch ($action) {

    // ---- GET: Resumen general del día ----
    case 'resumen':
        $hoy = date('Y-m-d');

        // Stats ventas hoy
        $ventas = $db->query("
            SELECT
                COUNT(*) FILTER (WHERE estado = 'completada') AS total_ventas,
                COALESCE(SUM(total) FILTER (WHERE estado = 'completada'), 0) AS ingresos,
                COALESCE(AVG(total) FILTER (WHERE estado = 'completada'), 0) AS ticket_promedio,
                COUNT(*) FILTER (WHERE estado = 'anulada') AS anuladas
            FROM ventas
            WHERE DATE(created_at) = '$hoy'
        ")->fetch();

        // Stats inventario
        $inventario = $db->query("
            SELECT
                COUNT(*) FILTER (WHERE activo = TRUE)                                         AS total_activos,
                COUNT(*) FILTER (WHERE activo = TRUE AND stock = 0)                           AS agotados,
                COUNT(*) FILTER (WHERE activo = TRUE AND stock > 0 AND stock <= stock_minimo) AS stock_bajo,
                COALESCE(SUM(stock * precio_compra) FILTER (WHERE activo = TRUE), 0)          AS valor_inventario
            FROM productos
        ")->fetch();

        // Stats almacén (mes actual)
        $mes_inicio = date('Y-m-01');
        $mes_fin    = date('Y-m-d');
        $almacen = $db->query("
            SELECT
                COUNT(*) FILTER (WHERE estado = 'completado'
                    AND DATE(created_at) BETWEEN '$mes_inicio' AND '$mes_fin') AS ingresos_mes,
                COALESCE(SUM(total) FILTER (WHERE estado = 'completado'
                    AND DATE(created_at) BETWEEN '$mes_inicio' AND '$mes_fin'), 0) AS valor_mes,
                (SELECT COUNT(*) FROM proveedores WHERE activo = TRUE) AS proveedores_activos
            FROM ingresos
        ")->fetch();

        // Estado caja (del usuario en sesión)
        $caja = $db->prepare("SELECT * FROM cajas WHERE estado = 'abierta' AND usuario_id = :uid ORDER BY apertura_at DESC LIMIT 1");
        $caja->execute([':uid' => (int) sesionId()]);
        $caja = $caja->fetch();
        $caja_info = ['abierta' => false];
        if ($caja) {
            $v = $db->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS total FROM ventas WHERE caja_id = :id AND estado = 'completada'");
            $v->execute([':id' => $caja['id']]);
            $cv = $v->fetch();
            $caja_info = [
                'abierta'        => true,
                'ventas_count'   => intval($cv['cnt']),
                'ventas_total'   => floatval($cv['total']),
                'saldo_inicial'  => floatval($caja['saldo_inicial']),
                'saldo_esperado' => floatval($caja['saldo_inicial']) + floatval($cv['total']),
                'apertura_at'    => $caja['apertura_at'],
                'responsable'    => $caja['usuario_apertura'],
            ];
        }

        echo json_encode([
            'ventas'     => $ventas,
            'inventario' => $inventario,
            'almacen'    => $almacen,
            'caja'       => $caja_info,
        ]);
        break;

    // ---- GET: Ventas agrupadas por día (últimos N días) ----
    case 'ventas_semana':
        $dias   = min(intval($_GET['dias'] ?? 7), 30);
        $result = $db->query("
            SELECT
                DATE(created_at) AS fecha,
                COUNT(*) FILTER (WHERE estado = 'completada') AS total_ventas,
                COALESCE(SUM(total) FILTER (WHERE estado = 'completada'), 0) AS ingresos
            FROM ventas
            WHERE DATE(created_at) >= CURRENT_DATE - INTERVAL '$dias days'
            GROUP BY DATE(created_at)
            ORDER BY fecha ASC
        ")->fetchAll();

        // Fill missing days with zeros
        $map = [];
        foreach ($result as $r) $map[$r['fecha']] = $r;
        $series = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $fecha = date('Y-m-d', strtotime("-$i days"));
            $series[] = [
                'fecha'        => $fecha,
                'total_ventas' => intval($map[$fecha]['total_ventas'] ?? 0),
                'ingresos'     => floatval($map[$fecha]['ingresos']   ?? 0),
            ];
        }
        echo json_encode($series);
        break;

    // ---- GET: Ventas por método de pago (hoy o semana) ----
    case 'ventas_metodo_pago':
        $periodo = $_GET['periodo'] ?? 'hoy';
        $where   = $periodo === 'semana'
            ? "DATE(created_at) >= CURRENT_DATE - INTERVAL '7 days'"
            : "DATE(created_at) = CURRENT_DATE";

        $rows = $db->query("
            SELECT tipo_pago, COUNT(*) AS total_ventas, COALESCE(SUM(total), 0) AS monto
            FROM ventas
            WHERE estado = 'completada' AND $where
            GROUP BY tipo_pago
            ORDER BY monto DESC
        ")->fetchAll();
        echo json_encode($rows);
        break;

    // ---- GET: Productos con stock bajo o agotado ----
    case 'stock_alertas':
        // full=1 -> lista completa (para el modal "Ver todas"); por defecto, tope de 20 (vista compacta de la tarjeta).
        $full  = ($_GET['full'] ?? '') === '1';
        $limit = $full ? '' : 'LIMIT 20';
        $rows = $db->query("
            SELECT p.id, p.codigo, p.nombre, p.laboratorio,
                   p.stock, p.stock_minimo, c.nombre AS categoria,
                   CASE
                       WHEN p.stock = 0 THEN 'agotado'
                       ELSE 'bajo'
                   END AS alerta
            FROM productos p
            LEFT JOIN public.categorias c ON c.id = p.categoria_id
            WHERE p.activo = TRUE AND p.stock <= p.stock_minimo
            ORDER BY p.stock ASC, p.nombre ASC
            $limit
        ")->fetchAll();
        echo json_encode($rows);
        break;

    // ---- GET: Top 5 productos más vendidos ----
    case 'top_vendidos':
        $rows = $db->query("
            SELECT p.id, p.nombre, p.laboratorio, p.total_vendido,
                   p.precio_venta, c.nombre AS categoria
            FROM productos p
            LEFT JOIN public.categorias c ON c.id = p.categoria_id
            WHERE p.activo = TRUE AND p.total_vendido > 0
            ORDER BY p.total_vendido DESC
            LIMIT 5
        ")->fetchAll();
        echo json_encode($rows);
        break;

    // ---- GET: Últimas ventas del día ----
    case 'ultimas_ventas':
        $rows = $db->query("
            SELECT v.numero_venta, v.total, v.tipo_pago, v.tipo_comprobante, v.estado, v.created_at,
                   COALESCE(c.nombres || ' ' || COALESCE(c.apellidos,''), 'Cliente General') AS cliente
            FROM ventas v
            LEFT JOIN clientes c ON c.id = v.cliente_id
            WHERE DATE(v.created_at) = CURRENT_DATE
            ORDER BY v.created_at DESC
            LIMIT 8
        ")->fetchAll();
        echo json_encode($rows);
        break;

    // ---- GET: KPIs del periodo elegido + periodo anterior (comparativo) ----
    case 'kpis':
        $admin   = isAdmin();
        $periodo = $_GET['periodo'] ?? 'hoy';

        // --- Turno: filtra por la caja abierta del usuario; compara vs su ultimo turno cerrado ---
        if ($periodo === 'turno' && ($caja = dashTurnoCaja($db))) {
            $ahora  = date('Y-m-d H:i:s');
            $actual = dashKpisPeriodo($db, 'caja_id = :cid', 'v.caja_id = :cid', [':cid' => (int) $caja['id']],
                                      $caja['apertura_at'], $ahora, $admin);
            $prev = dashTurnoCajaPrev($db);
            if ($prev) {
                $anterior = dashKpisPeriodo($db, 'caja_id = :cid', 'v.caja_id = :cid', [':cid' => (int) $prev['id']],
                                            $prev['apertura_at'], ($prev['cierre_at'] ?: $prev['apertura_at']), $admin);
            } else {
                $anterior = dashKpisVacio($admin);
            }
            echo json_encode([
                'modo'           => 'turno',
                'caja'           => ['apertura_at' => $caja['apertura_at'], 'responsable' => $caja['usuario_apertura']],
                'tiene_anterior' => (bool) $prev,
                'actual'         => $actual,
                'anterior'       => $anterior,
            ]);
            break;
        }

        // --- Rango (incluye el fallback de 'turno' sin caja abierta) ---
        if ($periodo === 'turno') $_GET['periodo'] = 'hoy';
        $r = dashRango();
        echo json_encode([
            'modo'           => 'rango',
            'rango'          => $r,
            'tiene_anterior' => true,
            'actual'         => dashKpisPeriodo($db, 'created_at BETWEEN :ini AND :fin', 'v.created_at BETWEEN :ini AND :fin',
                                                [':ini' => $r['ini'], ':fin' => $r['fin']], $r['ini'], $r['fin'], $admin),
            'anterior'       => dashKpisPeriodo($db, 'created_at BETWEEN :ini AND :fin', 'v.created_at BETWEEN :ini AND :fin',
                                                [':ini' => $r['ini_prev'], ':fin' => $r['fin_prev']], $r['ini_prev'], $r['fin_prev'], $admin),
        ]);
        break;

    // ---- GET: Serie diaria (ingresos, ventas y ganancia si es admin) ----
    case 'serie':
        $f = dashVentasFiltro($db);

        // --- Turno: serie por HORA desde la apertura de caja hasta ahora ---
        if ($f['modo'] === 'turno') {
            $s = $db->prepare("
                SELECT date_trunc('hour', created_at) AS hora,
                       COUNT(*) FILTER (WHERE estado = 'completada') AS total_ventas,
                       COALESCE(SUM(total) FILTER (WHERE estado = 'completada'), 0) AS ingresos
                FROM ventas
                WHERE {$f['cond']}
                GROUP BY 1
            ");
            $s->execute($f['params']);
            $map = [];
            foreach ($s->fetchAll() as $row) $map[substr($row['hora'], 0, 13)] = $row;

            $gan = [];
            if (isAdmin()) {
                $g = $db->prepare("
                    SELECT date_trunc('hour', v.created_at) AS hora,
                           COALESCE(SUM(vd.subtotal - vd.cantidad * p.precio_compra), 0) AS ganancia
                    FROM ventas v
                    JOIN venta_detalles vd ON vd.venta_id = v.id
                    JOIN productos p       ON p.id = vd.producto_id
                    WHERE v.estado = 'completada' AND {$f['condV']}
                    GROUP BY 1
                ");
                $g->execute($f['params']);
                foreach ($g->fetchAll() as $row) $gan[substr($row['hora'], 0, 13)] = (float) $row['ganancia'];
            }

            $serie = [];
            $cur = new DateTime($f['caja']['apertura_at']);
            $cur->setTime((int) $cur->format('H'), 0, 0);   // alinear a la hora en punto
            $fin = new DateTime('now');
            $guard = 0;
            while ($cur <= $fin && $guard < 48) {            // tope de 48 buckets por seguridad
                $k = $cur->format('Y-m-d H');
                $item = [
                    'fecha'        => $cur->format('Y-m-d H:i'),
                    'hora_label'   => $cur->format('H:i'),
                    'total_ventas' => (int) ($map[$k]['total_ventas'] ?? 0),
                    'ingresos'     => (float) ($map[$k]['ingresos'] ?? 0),
                ];
                if (isAdmin()) $item['ganancia'] = $gan[$k] ?? 0;
                $serie[] = $item;
                $cur->modify('+1 hour');
                $guard++;
            }
            echo json_encode($serie);
            break;
        }

        // --- Rango: serie diaria ---
        $r = $f['rango'];
        $s = $db->prepare("
            SELECT DATE(created_at) AS fecha,
                   COUNT(*) FILTER (WHERE estado = 'completada') AS total_ventas,
                   COALESCE(SUM(total) FILTER (WHERE estado = 'completada'), 0) AS ingresos
            FROM ventas
            WHERE created_at BETWEEN :ini AND :fin
            GROUP BY DATE(created_at)
        ");
        $s->execute([':ini' => $r['ini'], ':fin' => $r['fin']]);
        $map = [];
        foreach ($s->fetchAll() as $row) $map[$row['fecha']] = $row;

        $gan = [];
        if (isAdmin()) {
            $g = $db->prepare("
                SELECT DATE(v.created_at) AS fecha,
                       COALESCE(SUM(vd.subtotal - vd.cantidad * p.precio_compra), 0) AS ganancia
                FROM ventas v
                JOIN venta_detalles vd ON vd.venta_id = v.id
                JOIN productos p       ON p.id = vd.producto_id
                WHERE v.estado = 'completada' AND v.created_at BETWEEN :ini AND :fin
                GROUP BY DATE(v.created_at)
            ");
            $g->execute([':ini' => $r['ini'], ':fin' => $r['fin']]);
            foreach ($g->fetchAll() as $row) $gan[$row['fecha']] = (float) $row['ganancia'];
        }

        $serie = [];
        $cur   = new DateTime($r['desde']);
        for ($i = 0; $i < $r['dias']; $i++) {
            $fe = $cur->format('Y-m-d');
            $item = [
                'fecha'        => $fe,
                'total_ventas' => (int) ($map[$fe]['total_ventas'] ?? 0),
                'ingresos'     => (float) ($map[$fe]['ingresos'] ?? 0),
            ];
            if (isAdmin()) $item['ganancia'] = $gan[$fe] ?? 0;
            $serie[] = $item;
            $cur->modify('+1 day');
        }
        echo json_encode($serie);
        break;

    // ---- GET: Metodos de pago del periodo ----
    case 'metodos_periodo':
        $f = dashVentasFiltro($db);
        $s = $db->prepare("
            SELECT tipo_pago, COUNT(*) AS total_ventas, COALESCE(SUM(total), 0) AS monto
            FROM ventas
            WHERE estado = 'completada' AND {$f['cond']}
            GROUP BY tipo_pago
            ORDER BY monto DESC
        ");
        $s->execute($f['params']);
        echo json_encode($s->fetchAll());
        break;

    // ---- GET: Top 5 productos del periodo (metrica: unidades|ingresos|ganancia) ----
    case 'top_periodo':
        $f       = dashVentasFiltro($db);
        $metrica = $_GET['metrica'] ?? 'unidades';
        if (!in_array($metrica, ['unidades', 'ingresos', 'ganancia'], true)) $metrica = 'unidades';
        if ($metrica === 'ganancia' && !isAdmin()) $metrica = 'unidades';
        $s = $db->prepare("
            SELECT p.id, p.nombre, p.laboratorio, c.nombre AS categoria,
                   SUM(vd.cantidad) AS unidades,
                   SUM(vd.subtotal) AS ingresos,
                   SUM(vd.subtotal - vd.cantidad * p.precio_compra) AS ganancia
            FROM ventas v
            JOIN venta_detalles vd ON vd.venta_id = v.id
            JOIN productos p       ON p.id = vd.producto_id
            LEFT JOIN public.categorias c ON c.id = p.categoria_id
            WHERE v.estado = 'completada' AND {$f['condV']}
            GROUP BY p.id, p.nombre, p.laboratorio, c.nombre
            ORDER BY $metrica DESC
            LIMIT 5
        ");
        $s->execute($f['params']);
        $rows = $s->fetchAll();
        if (!isAdmin()) foreach ($rows as &$row) unset($row['ganancia']);
        unset($row);
        echo json_encode(['metrica' => $metrica, 'items' => $rows]);
        break;

    // ---- GET: Detalle de ventas (drill-down al hacer clic en un dia / metodo) ----
    case 'ventas_lista':
        $f      = dashVentasFiltro($db);
        $params = $f['params'];
        $where  = '';
        $tp = trim($_GET['tipo_pago'] ?? '');
        if ($tp !== '') { $where = ' AND v.tipo_pago = :tp'; $params[':tp'] = $tp; }
        $s = $db->prepare("
            SELECT v.numero_venta, v.total, v.tipo_pago, v.tipo_comprobante, v.estado, v.created_at,
                   COALESCE(c.nombres || ' ' || COALESCE(c.apellidos,''), 'Cliente General') AS cliente
            FROM ventas v
            LEFT JOIN clientes c ON c.id = v.cliente_id
            WHERE {$f['condV']} $where
            ORDER BY v.created_at DESC
            LIMIT 200
        ");
        $s->execute($params);
        echo json_encode($s->fetchAll());
        break;

    // ---- GET: Productos por vencer (solo admin/gerente) ----
    case 'por_vencer':
        requireApiAuth(['admin', 'gerente']);
        $rows = $db->query("
            SELECT p.id, p.nombre, p.laboratorio, p.stock, p.fecha_vencimiento,
                   (p.fecha_vencimiento - CURRENT_DATE) AS dias
            FROM productos p
            WHERE p.activo = TRUE AND p.stock > 0
              AND p.fecha_vencimiento IS NOT NULL
              AND p.fecha_vencimiento <= CURRENT_DATE + 90
            ORDER BY p.fecha_vencimiento ASC, p.nombre ASC
        ")->fetchAll();
        $cnt = ['vencidos' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0];
        foreach ($rows as $row) {
            $d = (int) $row['dias'];
            if ($d < 0)       $cnt['vencidos']++;
            elseif ($d <= 30) $cnt['d30']++;
            elseif ($d <= 60) $cnt['d60']++;
            else              $cnt['d90']++;
        }
        echo json_encode(['conteo' => $cnt, 'lista' => array_slice($rows, 0, 20)]);
        break;

    default:
        jsonResponse(['error' => true, 'message' => 'Acción no válida'], 404);
}
