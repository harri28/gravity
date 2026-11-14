<?php
// ============================================================
// ARCHIVO: farmacia/modules/almacen/api.php
// DESCRIPCIÓN: API REST para el módulo de Almacén
// Entidades: proveedores, ingresos de stock
// ============================================================

header('Content-Type: application/json; charset=UTF-8');
require_once '../../config/database.php';
requireApiAuth(['admin', 'gerente', 'cajero']);

$action = $_GET['action'] ?? '';
$db     = getDB();

function almacenTablaTieneColumna(PDO $db, string $tabla, string $columna): bool
{
    static $cache = [];
    $key = $tabla . '.' . $columna;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $db->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = CURRENT_SCHEMA()
          AND table_name = :tabla
          AND column_name = :columna
        LIMIT 1
    ");
    $stmt->execute([
        ':tabla' => $tabla,
        ':columna' => $columna,
    ]);

    return $cache[$key] = (bool) $stmt->fetchColumn();
}

// Mismo servicio (Factiliza) y logica que consultarDocumentoExterno() en
// modules/clientes/api.php -- se duplica aqui porque cada api.php de este
// proyecto es autocontenido (no se importan funciones entre modulos).
function almacenConsultarDocumentoExterno(string $numero): array
{
    $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI0MTc0OSJ9.EpZ7TjGQEqgCK-4c2XT1vNntWSlwWprh5Z_xgJ61B5Y';
    $numero = trim($numero);
    $url = strlen($numero) === 8
        ? 'https://api.factiliza.com/pe/v1/dni/info/' . $numero
        : 'https://api.factiliza.com/pe/v1/ruc/info/' . $numero;

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpCode = intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
    curl_close($curl);

    if ($response === false || $curlError) {
        return [
            'ok' => false,
            'message' => 'No se pudo consultar el documento: ' . ($curlError ?: 'error de conexion'),
        ];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return [
            'ok' => false,
            'message' => 'La respuesta del servicio de documentos no fue valida.',
        ];
    }

    if ($httpCode >= 400 || intval($data['status'] ?? 200) >= 400) {
        return [
            'ok' => false,
            'message' => $data['message'] ?? 'No se encontro informacion para el documento consultado.',
        ];
    }

    return [
        'ok' => true,
        'data' => $data['data'] ?? null,
    ];
}

// Cajeros no pueden ver ni mover el Registro de Salida.
if (isCajero() && in_array($action, ['salidas_listar', 'salida_detalle', 'registrar_salida', 'anular_salida'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

switch ($action) {

    // ================================================================
    // PROVEEDORES
    // ================================================================

    case 'proveedor_lookup_documento':
        $data   = json_decode(file_get_contents('php://input'), true);
        $numero = trim((string) ($data['numero'] ?? ''));

        if (!preg_match('/^\d{8}$/', $numero) && !preg_match('/^\d{11}$/', $numero)) {
            jsonResponse(['error' => true, 'message' => 'Ingresa un DNI (8 dígitos) o RUC (11 dígitos) válido'], 422);
        }

        $consulta = almacenConsultarDocumentoExterno($numero);
        if (!$consulta['ok']) {
            jsonResponse(['error' => true, 'message' => $consulta['message']], 404);
        }

        $docData = $consulta['data'];
        if (!$docData) {
            jsonResponse(['error' => true, 'message' => 'No se encontró información para el documento consultado'], 404);
        }

        if (strlen($numero) === 8) {
            $razonSocial = trim(
                (string) ($docData['nombres'] ?? '') . ' ' .
                (string) ($docData['apellido_paterno'] ?? '') . ' ' .
                (string) ($docData['apellido_materno'] ?? '')
            );
        } else {
            $razonSocial = trim((string) ($docData['nombre_o_razon_social'] ?? ''));
        }
        $direccion = trim((string) ($docData['direccion'] ?? ''));
        if ($direccion === '-') { $direccion = ''; }

        jsonResponse([
            'error' => false,
            'message' => 'Documento consultado correctamente',
            'proveedor' => [
                'razon_social' => $razonSocial,
                'direccion'    => $direccion,
            ],
        ]);

    case 'proveedores_listar':
        $q      = '%' . trim($_GET['q'] ?? '') . '%';
        $solo   = $_GET['activos'] ?? '';
        $where  = "(p.razon_social ILIKE :q OR COALESCE(p.ruc,'') ILIKE :q OR COALESCE(p.nombre_comercial,'') ILIKE :q)";
        $params = [':q' => $q];
        if ($solo === '1') { $where .= ' AND p.activo = TRUE'; }

        $stmt = $db->prepare("
            SELECT p.id, p.ruc, p.razon_social, p.nombre_comercial, p.telefono,
                   p.email, p.contacto_nombre, p.direccion, p.activo,
                   (SELECT COUNT(*) FROM ingresos WHERE proveedor_id = p.id) AS total_ingresos
            FROM proveedores p
            WHERE $where
            ORDER BY p.razon_social ASC
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    case 'proveedor_crear':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['razon_social'])) {
            jsonResponse(['error' => true, 'message' => 'La razón social es requerida'], 400);
        }
        if (!empty($data['ruc'])) {
            $ck = $db->prepare("SELECT id FROM proveedores WHERE ruc = :ruc");
            $ck->execute([':ruc' => trim($data['ruc'])]);
            if ($ck->fetch()) jsonResponse(['error' => true, 'message' => 'Ya existe un proveedor con ese RUC'], 409);
        }
        $stmt = $db->prepare("
            INSERT INTO proveedores (ruc, razon_social, nombre_comercial, telefono, email, direccion, contacto_nombre)
            VALUES (:ruc, :rs, :nc, :tel, :email, :dir, :ct)
            RETURNING id
        ");
        $stmt->execute([
            ':ruc'   => trim($data['ruc']   ?? '') ?: null,
            ':rs'    => trim($data['razon_social']),
            ':nc'    => trim($data['nombre_comercial'] ?? ''),
            ':tel'   => trim($data['telefono']  ?? ''),
            ':email' => trim($data['email']     ?? ''),
            ':dir'   => trim($data['direccion'] ?? ''),
            ':ct'    => trim($data['contacto_nombre'] ?? ''),
        ]);
        $id = $stmt->fetch()['id'];
        jsonResponse(['error' => false, 'message' => 'Proveedor creado correctamente', 'id' => $id]);

    case 'proveedor_actualizar':
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = intval($data['id'] ?? 0);
        if (!$id || empty($data['razon_social'])) {
            jsonResponse(['error' => true, 'message' => 'Datos inválidos'], 400);
        }
        if (!empty($data['ruc'])) {
            $ck = $db->prepare("SELECT id FROM proveedores WHERE ruc = :ruc AND id != :id");
            $ck->execute([':ruc' => trim($data['ruc']), ':id' => $id]);
            if ($ck->fetch()) jsonResponse(['error' => true, 'message' => 'Otro proveedor ya usa ese RUC'], 409);
        }
        $stmt = $db->prepare("
            UPDATE proveedores SET
                ruc              = :ruc,
                razon_social     = :rs,
                nombre_comercial = :nc,
                telefono         = :tel,
                email            = :email,
                direccion        = :dir,
                contacto_nombre  = :ct
            WHERE id = :id
        ");
        $stmt->execute([
            ':id'    => $id,
            ':ruc'   => trim($data['ruc']   ?? '') ?: null,
            ':rs'    => trim($data['razon_social']),
            ':nc'    => trim($data['nombre_comercial'] ?? ''),
            ':tel'   => trim($data['telefono']  ?? ''),
            ':email' => trim($data['email']     ?? ''),
            ':dir'   => trim($data['direccion'] ?? ''),
            ':ct'    => trim($data['contacto_nombre'] ?? ''),
        ]);
        jsonResponse(['error' => false, 'message' => 'Proveedor actualizado correctamente']);

    case 'proveedor_toggle_activo':
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = intval($data['id'] ?? 0);
        if (!$id) jsonResponse(['error' => true, 'message' => 'ID inválido'], 400);
        $stmt = $db->prepare("UPDATE proveedores SET activo = NOT activo WHERE id = :id RETURNING activo");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        jsonResponse(['error' => false, 'activo' => $row['activo']]);

    // ================================================================
    // INGRESOS
    // ================================================================

    case 'stats_almacen':
        $mes = date('Y-m');
        $row = $db->query("
            SELECT
                COUNT(*) FILTER (WHERE estado = 'completado' AND TO_CHAR(created_at,'YYYY-MM') = '$mes') AS ingresos_mes,
                COALESCE(SUM(total) FILTER (WHERE estado = 'completado' AND TO_CHAR(created_at,'YYYY-MM') = '$mes'), 0) AS valor_mes,
                COUNT(*) FILTER (WHERE estado = 'anulado'    AND TO_CHAR(created_at,'YYYY-MM') = '$mes') AS anulados_mes,
                (SELECT COUNT(*) FROM proveedores WHERE activo = TRUE) AS proveedores_activos
            FROM ingresos
        ")->fetch();
        echo json_encode($row);
        break;

    case 'movimientos_listar':
        $desde  = $_GET['desde']  ?? date('Y-m-d', strtotime('-30 days'));
        $hasta  = $_GET['hasta']  ?? date('Y-m-d');
        $tipo   = $_GET['tipo']   ?? '';
        $q      = '%' . ($_GET['q'] ?? '') . '%';

        $sql = "
            SELECT * FROM (
                SELECT
                    'entrada' AS tipo,
                    i.id,
                    i.numero_ingreso AS numero,
                    i.created_at,
                    COALESCE(p.nombre_comercial, p.razon_social, 'Ajuste / sin proveedor') AS detalle,
                    COALESCE(i.observaciones, '') AS observaciones,
                    i.total,
                    i.estado,
                    (SELECT COUNT(*) FROM ingreso_detalles d WHERE d.ingreso_id = i.id) AS num_items,
                    (SELECT p2.codigo FROM ingreso_detalles d2 JOIN productos p2 ON p2.id = d2.producto_id
                     WHERE d2.ingreso_id = i.id LIMIT 1) AS producto_codigo
                FROM ingresos i
                LEFT JOIN proveedores p ON p.id = i.proveedor_id
                WHERE i.created_at BETWEEN :desde1 AND :hasta1

                UNION ALL

                SELECT
                    'salida' AS tipo,
                    s.id,
                    s.numero_salida AS numero,
                    s.created_at,
                    CASE s.motivo
                        WHEN 'merma'       THEN 'Merma'
                        WHEN 'vencimiento' THEN 'Vencimiento'
                        WHEN 'devolucion'  THEN 'Devolución'
                        ELSE 'Otro'
                    END AS detalle,
                    COALESCE(s.observaciones, '') AS observaciones,
                    s.total,
                    s.estado,
                    (SELECT COUNT(*) FROM salida_detalles d WHERE d.salida_id = s.id) AS num_items,
                    (SELECT p2.codigo FROM salida_detalles d2 JOIN productos p2 ON p2.id = d2.producto_id
                     WHERE d2.salida_id = s.id LIMIT 1) AS producto_codigo
                FROM salidas s
                WHERE s.created_at BETWEEN :desde2 AND :hasta2
            ) mov
            WHERE (numero ILIKE :q OR detalle ILIKE :q OR observaciones ILIKE :q)
        ";
        $params = [
            ':desde1' => $desde, ':hasta1' => $hasta . ' 23:59:59',
            ':desde2' => $desde, ':hasta2' => $hasta . ' 23:59:59',
            ':q' => $q,
        ];

        if (in_array($tipo, ['entrada', 'salida'], true)) {
            $sql .= ' AND tipo = :tipo';
            $params[':tipo'] = $tipo;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT 300';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    case 'ingresos_listar':
        $desde     = $_GET['desde']       ?? date('Y-m-d', strtotime('-30 days'));
        $hasta     = $_GET['hasta']        ?? date('Y-m-d');
        $estado    = $_GET['estado']       ?? '';
        $prov_id   = intval($_GET['proveedor_id'] ?? 0);
        $q         = '%' . ($_GET['q'] ?? '') . '%';

        $selNumeroFactura = almacenTablaTieneColumna($db, 'ingresos', 'numero_factura')
            ? 'i.numero_factura'
            : "'' AS numero_factura";
        $selFechaFactura = almacenTablaTieneColumna($db, 'ingresos', 'fecha_factura')
            ? 'i.fecha_factura'
            : 'NULL::date AS fecha_factura';
        $selSubtotal = almacenTablaTieneColumna($db, 'ingresos', 'subtotal')
            ? 'i.subtotal'
            : 'i.total AS subtotal';
        $selIgv = almacenTablaTieneColumna($db, 'ingresos', 'igv')
            ? 'i.igv'
            : '0::numeric AS igv';
        $selUsuario = almacenTablaTieneColumna($db, 'ingresos', 'usuario')
            ? 'i.usuario'
            : "'' AS usuario";

        $where  = ['i.created_at BETWEEN :desde AND :hasta', '(i.numero_ingreso ILIKE :q OR COALESCE(p.razon_social,\'\') ILIKE :q)'];
        $params = [':desde' => $desde, ':hasta' => $hasta . ' 23:59:59', ':q' => $q];

        if ($estado)  { $where[] = 'i.estado = :estado';    $params[':estado']  = $estado;  }
        if ($prov_id) { $where[] = 'i.proveedor_id = :pid'; $params[':pid']     = $prov_id; }

        $stmt = $db->prepare("
            SELECT
                i.id,
                i.numero_ingreso,
                i.estado,
                i.created_at,
                COALESCE(p.nombre_comercial, p.razon_social, 'Sin proveedor') AS origen,
                COALESCE(i.observaciones, '') AS motivo
            FROM ingresos i
            LEFT JOIN proveedores p ON p.id = i.proveedor_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.created_at DESC
            LIMIT 200
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    case 'ingreso_detalle':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $db->prepare("
            SELECT d.cantidad, d.precio_unitario, d.subtotal,
                   p.nombre AS producto_nombre, p.codigo
            FROM ingreso_detalles d
            JOIN productos p ON p.id = d.producto_id
            WHERE d.ingreso_id = :id
            ORDER BY p.nombre
        ");
        $stmt->execute([':id' => $id]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'buscar_producto':
        $q = '%' . trim($_GET['q'] ?? '') . '%';
        $stmt = $db->prepare("
            SELECT id, codigo, nombre, precio_compra, precio_venta, stock, presentacion, laboratorio
            FROM productos
            WHERE activo = TRUE AND (nombre ILIKE :q OR codigo ILIKE :q)
            ORDER BY nombre ASC
            LIMIT 10
        ");
        $stmt->execute([':q' => $q]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'registrar_ingreso':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['items'])) {
            jsonResponse(['error' => true, 'message' => 'Debe agregar al menos un producto'], 400);
        }

        $subtotal = 0;
        foreach ($data['items'] as $item) {
            $subtotal += floatval($item['cantidad']) * floatval($item['precio_unitario']);
        }
        $total = $subtotal;

        $db->beginTransaction();
        try {
            $numero = generarNumeroIngreso($db);
            $columnas = ['numero_ingreso', 'proveedor_id', 'total', 'observaciones', 'estado'];
            $values = [':num', ':prov', ':total', ':obs', "'completado'"];
            $params = [
                ':num' => $numero,
                ':prov' => $data['proveedor_id'] ?: null,
                ':total' => $total,
                ':obs' => trim($data['observaciones'] ?? ''),
            ];

            if (almacenTablaTieneColumna($db, 'ingresos', 'numero_factura')) {
                $columnas[] = 'numero_factura';
                $values[] = ':factura';
                $params[':factura'] = trim($data['numero_factura'] ?? '');
            }
            if (almacenTablaTieneColumna($db, 'ingresos', 'fecha_factura')) {
                $columnas[] = 'fecha_factura';
                $values[] = ':fecha';
                $params[':fecha'] = $data['fecha_factura'] ?: date('Y-m-d');
            }
            if (almacenTablaTieneColumna($db, 'ingresos', 'subtotal')) {
                $columnas[] = 'subtotal';
                $values[] = ':sub';
                $params[':sub'] = $subtotal;
            }
            if (almacenTablaTieneColumna($db, 'ingresos', 'usuario')) {
                $columnas[] = 'usuario';
                $values[] = ':usuario';
                $params[':usuario'] = sesionNombre();
            }
            if (almacenTablaTieneColumna($db, 'ingresos', 'usuario_id') && !empty($_SESSION['usuario_id'])) {
                $columnas[] = 'usuario_id';
                $values[] = ':usuario_id';
                $params[':usuario_id'] = (int) $_SESSION['usuario_id'];
            }

            $stmt = $db->prepare("
                INSERT INTO ingresos (" . implode(', ', $columnas) . ")
                VALUES (" . implode(', ', $values) . ")
                RETURNING id
            ");
            $stmt->execute($params);
            $ingresoId = $stmt->fetch()['id'];

            foreach ($data['items'] as $item) {
                $qty    = floatval($item['cantidad']);
                $precio = floatval($item['precio_unitario']);
                $sub    = $qty * $precio;
                $pid    = intval($item['producto_id']);

                // Verificar que el producto existe
                $ck = $db->prepare("SELECT id FROM productos WHERE id = :id AND activo = TRUE");
                $ck->execute([':id' => $pid]);
                if (!$ck->fetch()) throw new Exception("Producto ID $pid no encontrado");

                $db->prepare("
                    INSERT INTO ingreso_detalles (ingreso_id, producto_id, cantidad, precio_unitario, subtotal)
                    VALUES (:iid, :pid, :qty, :precio, :sub)
                ")->execute([':iid' => $ingresoId, ':pid' => $pid, ':qty' => $qty, ':precio' => $precio, ':sub' => $sub]);

                // Actualizar stock
                $db->prepare("UPDATE productos SET stock = stock + :qty, updated_at = NOW() WHERE id = :id")
                   ->execute([':qty' => $qty, ':id' => $pid]);

                // Actualizar precio de compra con el último precio recibido
                if ($precio > 0) {
                    $db->prepare("UPDATE productos SET precio_compra = :precio, updated_at = NOW() WHERE id = :id")
                       ->execute([':precio' => $precio, ':id' => $pid]);
                }

                // Actualizar precio de venta si se ajusto al registrar el ingreso
                $precioVenta = floatval($item['precio_venta'] ?? 0);
                if ($precioVenta > 0) {
                    $db->prepare("UPDATE productos SET precio_venta = :pv, updated_at = NOW() WHERE id = :id")
                       ->execute([':pv' => $precioVenta, ':id' => $pid]);
                }
            }

            $db->commit();
            jsonResponse(['error' => false, 'numero_ingreso' => $numero, 'total' => $total]);

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 422);
        }

    case 'anular_ingreso':
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = intval($data['id'] ?? 0);

        $db->beginTransaction();
        try {
            $ing = $db->prepare("SELECT numero_ingreso, estado FROM ingresos WHERE id = :id");
            $ing->execute([':id' => $id]);
            $row = $ing->fetch();
            if (!$row)                         throw new Exception('Ingreso no encontrado');
            if ($row['estado'] === 'anulado')  throw new Exception('El ingreso ya está anulado');

            // Verificar que revertir stock no deje valores negativos
            $dets = $db->prepare("SELECT producto_id, cantidad FROM ingreso_detalles WHERE ingreso_id = :id");
            $dets->execute([':id' => $id]);
            foreach ($dets->fetchAll() as $det) {
                $prod = $db->prepare("SELECT stock, nombre FROM productos WHERE id = :pid");
                $prod->execute([':pid' => $det['producto_id']]);
                $p = $prod->fetch();
                if ($p && ($p['stock'] - $det['cantidad']) < 0) {
                    throw new Exception("No se puede anular: stock insuficiente en '{$p['nombre']}' (quedaría negativo)");
                }
                // Revertir stock
                $db->prepare("UPDATE productos SET stock = stock - :qty, updated_at = NOW() WHERE id = :pid")
                   ->execute([':qty' => $det['cantidad'], ':pid' => $det['producto_id']]);
            }

            $db->prepare("UPDATE ingresos SET estado = 'anulado' WHERE id = :id")->execute([':id' => $id]);
            $db->commit();

            registrarAuditoria('Anulación de ingreso', 'almacen', "Ingreso: {$row['numero_ingreso']}");

            jsonResponse(['error' => false, 'message' => 'Ingreso anulado correctamente']);

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 422);
        }

    // ================================================================
    // SALIDAS (bajas de stock: merma, vencimiento, devolución, otro --
    // NO transferencias entre sucursales, eso lo maneja el módulo Traslados)
    // ================================================================

    case 'salidas_listar':
        $desde  = $_GET['desde']  ?? date('Y-m-d', strtotime('-30 days'));
        $hasta  = $_GET['hasta']  ?? date('Y-m-d');
        $motivo = $_GET['motivo'] ?? '';
        $q      = '%' . ($_GET['q'] ?? '') . '%';

        $where  = ['s.created_at BETWEEN :desde AND :hasta', 's.numero_salida ILIKE :q'];
        $params = [':desde' => $desde, ':hasta' => $hasta . ' 23:59:59', ':q' => $q];

        if ($motivo) { $where[] = 's.motivo = :motivo'; $params[':motivo'] = $motivo; }

        $stmt = $db->prepare("
            SELECT
                s.id, s.numero_salida, s.motivo, s.estado, s.observaciones,
                s.usuario, s.created_at,
                (SELECT COUNT(*) FROM salida_detalles d WHERE d.salida_id = s.id) AS total_productos
            FROM salidas s
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.created_at DESC
            LIMIT 200
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    case 'salida_detalle':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $db->prepare("
            SELECT d.cantidad, d.costo_unitario, d.subtotal,
                   p.nombre AS producto_nombre, p.codigo
            FROM salida_detalles d
            JOIN productos p ON p.id = d.producto_id
            WHERE d.salida_id = :id
            ORDER BY p.nombre
        ");
        $stmt->execute([':id' => $id]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'registrar_salida':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['items'])) {
            jsonResponse(['error' => true, 'message' => 'Debe agregar al menos un producto'], 400);
        }
        $motivosValidos = ['merma', 'vencimiento', 'devolucion', 'otro'];
        $motivo = in_array($data['motivo'] ?? '', $motivosValidos, true) ? $data['motivo'] : 'otro';

        $db->beginTransaction();
        try {
            // Verificar stock suficiente antes de descontar nada
            foreach ($data['items'] as $item) {
                $pid = intval($item['producto_id']);
                $qty = floatval($item['cantidad']);
                $prod = $db->prepare("SELECT nombre, stock FROM productos WHERE id = :id AND activo = TRUE");
                $prod->execute([':id' => $pid]);
                $p = $prod->fetch();
                if (!$p) throw new Exception("Producto ID $pid no encontrado");
                if ($p['stock'] < $qty) throw new Exception("Stock insuficiente en '{$p['nombre']}' (disponible: {$p['stock']})");
            }

            $total = 0;
            foreach ($data['items'] as $item) {
                $total += floatval($item['cantidad']) * floatval($item['costo_unitario'] ?? 0);
            }

            $numero = generarNumeroSalida($db);
            $stmt = $db->prepare("
                INSERT INTO salidas (numero_salida, motivo, observaciones, estado, total, usuario, usuario_id)
                VALUES (:num, :motivo, :obs, 'completado', :total, :usuario, :usuario_id)
                RETURNING id
            ");
            $stmt->execute([
                ':num'        => $numero,
                ':motivo'     => $motivo,
                ':obs'        => trim($data['observaciones'] ?? ''),
                ':total'      => $total,
                ':usuario'    => sesionNombre(),
                ':usuario_id' => $_SESSION['usuario_id'] ?? null,
            ]);
            $salidaId = $stmt->fetch()['id'];

            foreach ($data['items'] as $item) {
                $pid    = intval($item['producto_id']);
                $qty    = floatval($item['cantidad']);
                $costo  = floatval($item['costo_unitario'] ?? 0);
                $sub    = $qty * $costo;

                $db->prepare("
                    INSERT INTO salida_detalles (salida_id, producto_id, cantidad, costo_unitario, subtotal)
                    VALUES (:sid, :pid, :qty, :costo, :sub)
                ")->execute([':sid' => $salidaId, ':pid' => $pid, ':qty' => $qty, ':costo' => $costo, ':sub' => $sub]);

                $db->prepare("UPDATE productos SET stock = stock - :qty, updated_at = NOW() WHERE id = :id")
                   ->execute([':qty' => $qty, ':id' => $pid]);
            }

            $db->commit();
            jsonResponse(['error' => false, 'numero_salida' => $numero, 'total' => $total]);

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 422);
        }

    case 'anular_salida':
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = intval($data['id'] ?? 0);

        $db->beginTransaction();
        try {
            $sal = $db->prepare("SELECT numero_salida, estado FROM salidas WHERE id = :id");
            $sal->execute([':id' => $id]);
            $row = $sal->fetch();
            if (!$row)                         throw new Exception('Salida no encontrada');
            if ($row['estado'] === 'anulado')  throw new Exception('La salida ya está anulada');

            $dets = $db->prepare("SELECT producto_id, cantidad FROM salida_detalles WHERE salida_id = :id");
            $dets->execute([':id' => $id]);
            foreach ($dets->fetchAll() as $det) {
                // Anular una salida devuelve el stock -- no puede dejarlo negativo
                $db->prepare("UPDATE productos SET stock = stock + :qty, updated_at = NOW() WHERE id = :pid")
                   ->execute([':qty' => $det['cantidad'], ':pid' => $det['producto_id']]);
            }

            $db->prepare("UPDATE salidas SET estado = 'anulado' WHERE id = :id")->execute([':id' => $id]);
            $db->commit();

            registrarAuditoria('Anulación de salida', 'almacen', "Salida: {$row['numero_salida']}");

            jsonResponse(['error' => false, 'message' => 'Salida anulada correctamente']);

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 422);
        }

    default:
        jsonResponse(['error' => true, 'message' => 'Acción no válida'], 404);
}
