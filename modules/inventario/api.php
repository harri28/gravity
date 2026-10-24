<?php
// ============================================================
// ARCHIVO: farmacia/modules/inventario/api.php
// DESCRIPCION: API REST para el modulo de Inventario
// ============================================================

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once '../../config/database.php';
requireApiAuth(['admin', 'gerente', 'cajero']);

$action = $_GET['action'] ?? '';
$db     = getDB();

function inventarioColumnaExiste(PDO $db, string $tabla, string $columna): bool
{
    static $cache = [];
    $key = $tabla . '.' . $columna;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $db->prepare("
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = CURRENT_SCHEMA() AND table_name = :t AND column_name = :c
        LIMIT 1
    ");
    $stmt->execute([':t' => $tabla, ':c' => $columna]);
    return $cache[$key] = (bool) $stmt->fetch();
}

function resolverCatalogosProducto(PDO $db, string $unidadCodigo, string $afectacionCodigo): array
{
    $stmtUnidad = $db->prepare("
        SELECT id, codigo, descripcion
        FROM public.fe_unidades
        WHERE codigo = :codigo AND estado = TRUE
        LIMIT 1
    ");
    $stmtUnidad->execute([':codigo' => $unidadCodigo]);
    $unidad = $stmtUnidad->fetch();

    if (!$unidad) {
        jsonResponse(['error' => true, 'message' => 'La unidad SUNAT seleccionada no existe'], 422);
    }

    $stmtAfectacion = $db->prepare("
        SELECT id, codigo, descripcion, tipo
        FROM public.fe_tipos_afectacion_igv
        WHERE codigo = :codigo AND estado = TRUE
        LIMIT 1
    ");
    $stmtAfectacion->execute([':codigo' => $afectacionCodigo]);
    $afectacion = $stmtAfectacion->fetch();

    if (!$afectacion) {
        jsonResponse(['error' => true, 'message' => 'La afectacion IGV seleccionada no existe'], 422);
    }

    return [
        'unidad_id' => intval($unidad['id']),
        'unidad_codigo' => $unidad['codigo'],
        'afectacion_id' => intval($afectacion['id']),
        'afectacion_codigo' => $afectacion['codigo'],
        'afectacion_tipo' => $afectacion['tipo'],
    ];
}

function guardarPreciosUnidadProducto(PDO $db, int $productoId, array $precios): void
{
    $db->prepare("DELETE FROM producto_precios_unidad WHERE producto_id = :pid")
       ->execute([':pid' => $productoId]);

    $stmt = $db->prepare("
        INSERT INTO producto_precios_unidad (producto_id, unidad_medida, abreviacion, cantidad, precio_venta)
        VALUES (:pid, :unidad, :abrev, :cantidad, :precio)
    ");
    foreach ($precios as $p) {
        $unidad = trim($p['unidad_medida'] ?? '');
        $cantidad = intval($p['cantidad'] ?? 0);
        $precio = floatval($p['precio_venta'] ?? 0);
        if ($unidad === '' || $cantidad <= 0 || $precio <= 0) {
            continue;
        }
        $stmt->execute([
            ':pid' => $productoId,
            ':unidad' => $unidad,
            ':abrev' => trim($p['abreviacion'] ?? '') ?: null,
            ':cantidad' => $cantidad,
            ':precio' => $precio,
        ]);
    }
}

function inventarioPurgarEliminados(PDO $db): void
{
    // Purga perezosa: se llama al abrir la pestaña "Eliminados". Borra
    // definitivamente los productos con mas de 30 dias en la papelera,
    // uno por uno (no en bloque) porque un producto con historial de
    // ventas/ingresos/salidas/compras no se puede borrar fisicamente
    // (venta_detalles.producto_id etc. no tienen ON DELETE CASCADE, a
    // proposito, para no perder el detalle de comprobantes ya emitidos)
    // -- ese producto simplemente se queda en la papelera indefinidamente.
    $ids = $db->query("
        SELECT id FROM productos
        WHERE eliminado = TRUE AND eliminado_at < NOW() - INTERVAL '30 days'
    ")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $id) {
        try {
            $db->prepare("DELETE FROM productos WHERE id = :id")->execute([':id' => $id]);
        } catch (PDOException $e) {
            // Tiene historial -> se queda en la papelera. Seguimos con el resto.
            continue;
        }
    }
}

function tomaInvGenerarCodigo(PDO $db): string
{
    $prefijo = 'TI' . date('Ymd') . '-';
    $stmt = $db->prepare("
        SELECT COALESCE(MAX(CAST(SUBSTRING(codigo FROM '[0-9]+$') AS INTEGER)), 0) AS ultimo
        FROM toma_inventario_sesiones
        WHERE codigo LIKE :prefijo
    ");
    $stmt->execute([':prefijo' => $prefijo . '%']);
    $ultimo = (int) ($stmt->fetch()['ultimo'] ?? 0);

    return $prefijo . str_pad((string) ($ultimo + 1), 4, '0', STR_PAD_LEFT);
}

try {

switch ($action) {

    case 'stats':
        $row = $db->query("
            SELECT
                COUNT(*) FILTER (WHERE activo = TRUE) AS total_activos,
                COUNT(*) FILTER (WHERE activo = TRUE AND stock = 0) AS agotados,
                COUNT(*) FILTER (WHERE activo = TRUE AND stock > 0 AND stock <= stock_minimo) AS stock_bajo,
                COUNT(*) FILTER (WHERE activo = FALSE AND eliminado = FALSE) AS inactivos,
                COUNT(*) FILTER (WHERE eliminado = TRUE) AS eliminados,
                COALESCE(SUM(stock * precio_compra) FILTER (WHERE activo = TRUE), 0) AS valor_inventario
            FROM productos
        ")->fetch();
        echo json_encode($row);
        break;

    case 'listar':
        $q            = '%' . trim($_GET['q'] ?? '') . '%';
        $categoria_id = intval($_GET['categoria_id'] ?? 0);
        $stock_status = $_GET['stock_status'] ?? '';

        $where  = [
            '(p.nombre ILIKE :q OR p.codigo ILIKE :q OR COALESCE(p.laboratorio, \'\') ILIKE :q OR COALESCE(p.codigo_barras, \'\') ILIKE :q)',
            'p.eliminado = FALSE', // los eliminados solo se ven en la pestaña "Eliminados" (accion eliminados_listar)
        ];
        $params = [':q' => $q];

        if ($categoria_id > 0) {
            $where[] = 'p.categoria_id = :cat';
            $params[':cat'] = $categoria_id;
        }

        switch ($stock_status) {
            case 'agotado':
                $where[] = 'p.stock = 0';
                break;
            case 'bajo':
                $where[] = 'p.stock > 0 AND p.stock <= p.stock_minimo';
                break;
            case 'ok':
                $where[] = 'p.stock > p.stock_minimo';
                break;
            case 'inactivo':
                $where[] = 'p.activo = FALSE';
                break;
            default:
                $where[] = 'p.activo = TRUE';
                break;
        }

        $sql = "
            SELECT
                p.id,
                p.codigo,
                p.codigo_interno,
                p.codigo_barras,
                p.codigo_sunat,
                p.nombre,
                p.laboratorio,
                p.presentacion,
                p.precio_compra,
                p.precio_venta,
                p.stock,
                p.stock_minimo,
                p.unidad,
                p.unidad_id,
                p.unidad_codigo,
                COALESCE(u.descripcion, 'Unidad') AS unidad_sunat,
                p.afectacion_igv_id,
                p.afectacion_igv_codigo,
                a.tipo AS afectacion_igv_tipo,
                COALESCE(a.descripcion, 'Gravado - Operacion onerosa') AS afectacion_igv,
                p.porcentaje_igv,
                p.incluye_igv,
                p.icbper_activo,
                p.factor_icbper,
                p.product_type,
                p.requiere_receta,
                p.favorito,
                p.activo,
                p.total_vendido,
                p.categoria_id,
                c.nombre AS categoria,
                p.fecha_vencimiento
            FROM productos p
            LEFT JOIN public.categorias c ON c.id = p.categoria_id
            LEFT JOIN public.fe_unidades u ON u.id = p.unidad_id
            LEFT JOIN public.fe_tipos_afectacion_igv a ON a.id = p.afectacion_igv_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.nombre ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    case 'categorias':
        $rows = $db->query("
            SELECT c.id, c.nombre, COUNT(p.id) AS total_productos
            FROM public.categorias c
            LEFT JOIN productos p ON p.categoria_id = c.id AND p.activo = TRUE
            WHERE c.activo = TRUE
            GROUP BY c.id, c.nombre
            ORDER BY c.nombre
        ")->fetchAll();
        echo json_encode($rows);
        break;

    case 'catalogos_facturacion':
        $unidades = $db->query("
            SELECT id, codigo, descripcion
            FROM public.fe_unidades
            WHERE estado = TRUE
            ORDER BY descripcion
        ")->fetchAll();

        $afectaciones = $db->query("
            SELECT id, codigo, descripcion, tipo
            FROM public.fe_tipos_afectacion_igv
            WHERE estado = TRUE
            ORDER BY codigo
        ")->fetchAll();

        jsonResponse([
            'error' => false,
            'unidades' => $unidades,
            'afectaciones_igv' => $afectaciones,
        ]);

    case 'crear_categoria':
        if (!isAdmin()) jsonResponse(['error' => true, 'message' => 'Solo administradores pueden gestionar categorías'], 403);
        $data   = json_decode(file_get_contents('php://input'), true);
        $nombre = trim($data['nombre'] ?? '');
        if (!$nombre) {
            jsonResponse(['error' => true, 'message' => 'El nombre es requerido'], 400);
        }

        $check = $db->prepare("SELECT id FROM public.categorias WHERE LOWER(nombre) = LOWER(:nombre)");
        $check->execute([':nombre' => $nombre]);
        if ($check->fetch()) {
            jsonResponse(['error' => true, 'message' => 'Ya existe una categoria con ese nombre'], 409);
        }

        $stmt = $db->prepare("INSERT INTO public.categorias (nombre) VALUES (:nombre) RETURNING id, nombre");
        $stmt->execute([':nombre' => $nombre]);
        $cat = $stmt->fetch();
        jsonResponse(['error' => false, 'message' => 'Categoria creada', 'id' => $cat['id'], 'nombre' => $cat['nombre']]);
        break;

    case 'editar_categoria':
        if (!isAdmin()) jsonResponse(['error' => true, 'message' => 'Solo administradores pueden gestionar categorías'], 403);
        $data   = json_decode(file_get_contents('php://input'), true);
        $id     = intval($data['id'] ?? 0);
        $nombre = trim($data['nombre'] ?? '');
        if (!$id || !$nombre) {
            jsonResponse(['error' => true, 'message' => 'Datos invalidos'], 400);
        }

        $check = $db->prepare("SELECT id FROM public.categorias WHERE LOWER(nombre) = LOWER(:nombre) AND id != :id");
        $check->execute([':nombre' => $nombre, ':id' => $id]);
        if ($check->fetch()) {
            jsonResponse(['error' => true, 'message' => 'Ya existe una categoria con ese nombre'], 409);
        }

        $db->prepare("UPDATE public.categorias SET nombre = :nombre WHERE id = :id")
           ->execute([':nombre' => $nombre, ':id' => $id]);
        jsonResponse(['error' => false, 'message' => 'Categoria actualizada', 'id' => $id, 'nombre' => $nombre]);
        break;

    case 'eliminar_categoria':
        if (!isAdmin()) jsonResponse(['error' => true, 'message' => 'Solo administradores pueden gestionar categorías'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = intval($data['id'] ?? 0);
        if (!$id) {
            jsonResponse(['error' => true, 'message' => 'ID invalido'], 400);
        }

        $uso = $db->prepare("SELECT COUNT(*) AS total FROM productos WHERE categoria_id = :id AND activo = TRUE");
        $uso->execute([':id' => $id]);
        $total = intval($uso->fetch()['total']);
        if ($total > 0) {
            jsonResponse(['error' => true, 'message' => "No se puede eliminar: $total producto(s) usan esta categoria"], 409);
        }

        $db->prepare("DELETE FROM public.categorias WHERE id = :id")->execute([':id' => $id]);
        jsonResponse(['error' => false, 'message' => 'Categoria eliminada']);
        break;

    case 'crear':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['nombre']) || empty($data['codigo']) || !isset($data['precio_venta'])) {
            jsonResponse(['error' => true, 'message' => 'Campos requeridos: codigo, nombre, precio de venta'], 400);
        }

        $codigo = trim($data['codigo']);
        $check = $db->prepare("SELECT id FROM productos WHERE codigo = :codigo");
        $check->execute([':codigo' => $codigo]);
        if ($check->fetch()) {
            jsonResponse(['error' => true, 'message' => 'Ya existe un producto con ese codigo'], 409);
        }

        $codigoSunat = preg_match('/^\d{8}$/', trim($data['codigo_sunat'] ?? '')) ? trim($data['codigo_sunat']) : '00000000';
        $unidadTexto = trim($data['unidad'] ?? 'unidad') ?: 'unidad';
        $unidadCodigo = strtoupper(trim($data['unidad_codigo'] ?? 'NIU')) ?: 'NIU';
        $afectacionCodigo = trim($data['afectacion_igv_codigo'] ?? '10') ?: '10';
        $porcentajeIgv = round(floatval($data['porcentaje_igv'] ?? 18), 2);
        $incluyeIgv = !array_key_exists('incluye_igv', $data) || (bool) $data['incluye_igv'];
        $codigoBarras = trim($data['codigo_barras'] ?? '');
        // Nota historica: aqui habia un pre-chequeo de codigo_barras duplicado,
        // retirado junto a migration_55 (ver migration_57) -- varias presentaciones
        // del mismo producto comparten codigo de barras a proposito en el catalogo
        // real.
        $catalogos = resolverCatalogosProducto($db, $unidadCodigo, $afectacionCodigo);
        $esGravado = ($catalogos['afectacion_tipo'] ?? '') === 'GRAV';

        $stmt = $db->prepare("
            INSERT INTO productos
                (codigo, codigo_interno, codigo_barras, codigo_sunat, nombre, categoria_id, laboratorio, presentacion, unidad,
                 unidad_id, unidad_codigo, afectacion_igv_id, afectacion_igv_codigo, porcentaje_igv, incluye_igv,
                 precio_compra, precio_venta, stock, stock_minimo, requiere_receta, favorito, activo, fecha_vencimiento)
            VALUES
                (:codigo, :codigo_interno, :codigo_barras, :codigo_sunat, :nombre, :cat, :lab, :pres, :unidad,
                 :unidad_id, :unidad_codigo, :afectacion_igv_id, :afectacion_igv_codigo, :porcentaje_igv, :incluye_igv,
                 :p_compra, :p_venta, :stock, :stock_min, :receta, :favorito, TRUE, :fvenc)
            RETURNING id
        ");
        $sku = trim($data['sku'] ?? '') ?: $codigo;
        $stmt->execute([
            ':codigo' => $codigo,
            ':codigo_interno' => $sku,
            ':codigo_barras' => $codigoBarras ?: null,
            ':codigo_sunat' => $codigoSunat,
            ':nombre' => trim($data['nombre']),
            ':cat' => empty($data['categoria_id']) ? null : $data['categoria_id'],
            ':lab' => trim($data['laboratorio'] ?? ''),
            ':pres' => trim($data['presentacion'] ?? ''),
            ':unidad' => $unidadTexto,
            ':unidad_id' => $catalogos['unidad_id'],
            ':unidad_codigo' => $catalogos['unidad_codigo'],
            ':afectacion_igv_id' => $catalogos['afectacion_id'],
            ':afectacion_igv_codigo' => $catalogos['afectacion_codigo'],
            ':porcentaje_igv' => $esGravado ? ($porcentajeIgv > 0 ? $porcentajeIgv : 18) : 0,
            ':incluye_igv' => ($esGravado && $incluyeIgv) ? 'TRUE' : 'FALSE',
            ':p_compra' => floatval($data['precio_compra'] ?? 0),
            ':p_venta' => floatval($data['precio_venta']),
            ':stock' => intval($data['stock'] ?? 0),
            ':stock_min' => intval($data['stock_minimo'] ?? 5),
            ':receta' => !empty($data['requiere_receta']) ? 'TRUE' : 'FALSE',
            ':favorito' => !empty($data['favorito']) ? 'TRUE' : 'FALSE',
            ':fvenc' => empty($data['fecha_vencimiento']) ? null : $data['fecha_vencimiento'],
        ]);
        $id = $stmt->fetch()['id'];
        if (!empty($data['precios_unidad']) && is_array($data['precios_unidad'])) {
            guardarPreciosUnidadProducto($db, $id, $data['precios_unidad']);
        }
        jsonResponse(['error' => false, 'message' => 'Producto creado correctamente', 'id' => $id]);

    case 'actualizar':
        if (isCajero()) {
            jsonResponse(['error' => true, 'message' => 'No tienes permiso para editar productos'], 403);
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = intval($data['id'] ?? 0);
        if (!$id || empty($data['nombre']) || empty($data['codigo']) || !isset($data['precio_venta'])) {
            jsonResponse(['error' => true, 'message' => 'Datos invalidos'], 400);
        }

        $codigo = trim($data['codigo']);
        $check = $db->prepare("SELECT id FROM productos WHERE codigo = :codigo AND id != :id");
        $check->execute([':codigo' => $codigo, ':id' => $id]);
        if ($check->fetch()) {
            jsonResponse(['error' => true, 'message' => 'Ya existe otro producto con ese codigo'], 409);
        }

        $codigoSunat = preg_match('/^\d{8}$/', trim($data['codigo_sunat'] ?? '')) ? trim($data['codigo_sunat']) : '00000000';
        $unidadTexto = trim($data['unidad'] ?? 'unidad') ?: 'unidad';
        $unidadCodigo = strtoupper(trim($data['unidad_codigo'] ?? 'NIU')) ?: 'NIU';
        $afectacionCodigo = trim($data['afectacion_igv_codigo'] ?? '10') ?: '10';
        $porcentajeIgv = round(floatval($data['porcentaje_igv'] ?? 18), 2);
        $incluyeIgv = !array_key_exists('incluye_igv', $data) || (bool) $data['incluye_igv'];
        $codigoBarras = trim($data['codigo_barras'] ?? '');
        // Pre-chequeo de codigo_barras duplicado retirado -- ver nota en 'crear'
        // y migration_57.
        $catalogos = resolverCatalogosProducto($db, $unidadCodigo, $afectacionCodigo);
        $esGravado = ($catalogos['afectacion_tipo'] ?? '') === 'GRAV';

        if (isAdmin()) {
            $nuevoStock = intval($data['stock'] ?? 0);
        } else {
            $stockActual = $db->prepare("SELECT stock FROM productos WHERE id = :id");
            $stockActual->execute([':id' => $id]);
            $nuevoStock = intval($stockActual->fetch()['stock'] ?? 0);
        }

        $stmt = $db->prepare("
            UPDATE productos SET
                codigo = :codigo,
                codigo_interno = :codigo_interno,
                codigo_barras = :codigo_barras,
                codigo_sunat = :codigo_sunat,
                nombre = :nombre,
                categoria_id = :cat,
                laboratorio = :lab,
                presentacion = :pres,
                unidad = :unidad,
                unidad_id = :unidad_id,
                unidad_codigo = :unidad_codigo,
                afectacion_igv_id = :afectacion_igv_id,
                afectacion_igv_codigo = :afectacion_igv_codigo,
                porcentaje_igv = :porcentaje_igv,
                incluye_igv = :incluye_igv,
                precio_compra = :p_compra,
                precio_venta = :p_venta,
                stock = :stock,
                stock_minimo = :stock_min,
                requiere_receta = :receta,
                favorito = :favorito,
                fecha_vencimiento = :fvenc,
                updated_at = NOW()
            WHERE id = :id
        ");
        $sku = trim($data['sku'] ?? '') ?: $codigo;
        $stmt->execute([
            ':id' => $id,
            ':codigo' => $codigo,
            ':codigo_interno' => $sku,
            ':codigo_barras' => $codigoBarras ?: null,
            ':codigo_sunat' => $codigoSunat,
            ':nombre' => trim($data['nombre']),
            ':cat' => empty($data['categoria_id']) ? null : $data['categoria_id'],
            ':lab' => trim($data['laboratorio'] ?? ''),
            ':pres' => trim($data['presentacion'] ?? ''),
            ':unidad' => $unidadTexto,
            ':unidad_id' => $catalogos['unidad_id'],
            ':unidad_codigo' => $catalogos['unidad_codigo'],
            ':afectacion_igv_id' => $catalogos['afectacion_id'],
            ':afectacion_igv_codigo' => $catalogos['afectacion_codigo'],
            ':porcentaje_igv' => $esGravado ? ($porcentajeIgv > 0 ? $porcentajeIgv : 18) : 0,
            ':incluye_igv' => ($esGravado && $incluyeIgv) ? 'TRUE' : 'FALSE',
            ':p_compra' => floatval($data['precio_compra'] ?? 0),
            ':p_venta' => floatval($data['precio_venta']),
            ':stock' => $nuevoStock,
            ':stock_min' => intval($data['stock_minimo'] ?? 5),
            ':receta' => !empty($data['requiere_receta']) ? 'TRUE' : 'FALSE',
            ':favorito' => !empty($data['favorito']) ? 'TRUE' : 'FALSE',
            ':fvenc' => empty($data['fecha_vencimiento']) ? null : $data['fecha_vencimiento'],
        ]);
        guardarPreciosUnidadProducto($db, $id, is_array($data['precios_unidad'] ?? null) ? $data['precios_unidad'] : []);
        jsonResponse(['error' => false, 'message' => 'Producto actualizado correctamente']);

    case 'unidades_medida_listar':
        $rows = $db->query("SELECT id, nombre FROM unidades_medida_venta ORDER BY nombre")->fetchAll();
        echo json_encode($rows);
        break;

    case 'unidad_medida_crear':
        $data   = json_decode(file_get_contents('php://input'), true);
        $nombre = strtoupper(trim($data['nombre'] ?? ''));
        if (!$nombre) {
            jsonResponse(['error' => true, 'message' => 'El nombre es requerido'], 400);
        }

        $check = $db->prepare("SELECT id FROM unidades_medida_venta WHERE nombre = :nombre");
        $check->execute([':nombre' => $nombre]);
        if ($check->fetch()) {
            jsonResponse(['error' => true, 'message' => 'Ya existe esa unidad de medida'], 409);
        }

        $stmt = $db->prepare("INSERT INTO unidades_medida_venta (nombre) VALUES (:nombre) RETURNING id, nombre");
        $stmt->execute([':nombre' => $nombre]);
        $u = $stmt->fetch();
        jsonResponse(['error' => false, 'message' => 'Unidad de medida creada', 'id' => $u['id'], 'nombre' => $u['nombre']]);

    case 'precios_unidad_listar':
        $productoId = intval($_GET['producto_id'] ?? 0);
        if (!$productoId) { echo json_encode([]); break; }

        $stmt = $db->prepare("
            SELECT id, unidad_medida, abreviacion, cantidad, precio_venta
            FROM producto_precios_unidad
            WHERE producto_id = :pid
            ORDER BY cantidad ASC
        ");
        $stmt->execute([':pid' => $productoId]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'ajustar_stock':
        if (!isAdmin()) jsonResponse(['error' => true, 'message' => 'Solo administradores pueden ajustar stock manualmente'], 403);
        $data     = json_decode(file_get_contents('php://input'), true);
        $id       = intval($data['id'] ?? 0);
        $tipo     = $data['tipo'] ?? '';
        $cantidad = floatval($data['cantidad'] ?? 0);
        $motivo   = trim($data['motivo'] ?? '');

        if (!$id || !in_array($tipo, ['entrada', 'salida'], true) || $cantidad <= 0) {
            jsonResponse(['error' => true, 'message' => 'Datos invalidos'], 400);
        }

        $prod = $db->prepare("SELECT stock, nombre, COALESCE(precio_compra, 0) AS precio_compra FROM productos WHERE id = :id");
        $prod->execute([':id' => $id]);
        $producto = $prod->fetch();
        if (!$producto) {
            jsonResponse(['error' => true, 'message' => 'Producto no encontrado'], 404);
        }
        if ($tipo === 'salida' && $producto['stock'] < $cantidad) {
            jsonResponse(['error' => true, 'message' => "Stock insuficiente. Disponible: {$producto['stock']}"], 422);
        }

        $costo = (float) $producto['precio_compra'];
        $total = round($cantidad * $costo, 2);
        $observacion = 'Ajuste manual desde Inventario' . ($motivo !== '' ? ": {$motivo}" : '');

        // Este "ajuste rapido" desde Inventario registra un ingreso/salida real
        // (no solo un UPDATE a productos.stock), para que quede visible en el
        // historial de Almacen -> Ingresos/Salidas, igual que cualquier otro
        // movimiento de stock.
        $db->beginTransaction();
        try {
            if ($tipo === 'entrada') {
                $numero = generarNumeroIngreso($db);
                $columnas = ['numero_ingreso', 'proveedor_id', 'total', 'observaciones', 'estado'];
                $values   = [':num', 'NULL', ':total', ':obs', "'completado'"];
                $params   = [':num' => $numero, ':total' => $total, ':obs' => $observacion];

                if (inventarioColumnaExiste($db, 'ingresos', 'subtotal')) {
                    $columnas[] = 'subtotal'; $values[] = ':sub'; $params[':sub'] = $total;
                }
                if (inventarioColumnaExiste($db, 'ingresos', 'usuario')) {
                    $columnas[] = 'usuario'; $values[] = ':usuario'; $params[':usuario'] = sesionNombre();
                }
                if (inventarioColumnaExiste($db, 'ingresos', 'usuario_id') && !empty($_SESSION['usuario_id'])) {
                    $columnas[] = 'usuario_id'; $values[] = ':usuario_id'; $params[':usuario_id'] = (int) $_SESSION['usuario_id'];
                }

                $stmt = $db->prepare("
                    INSERT INTO ingresos (" . implode(', ', $columnas) . ")
                    VALUES (" . implode(', ', $values) . ")
                    RETURNING id
                ");
                $stmt->execute($params);
                $movimientoId = $stmt->fetch()['id'];

                $db->prepare("
                    INSERT INTO ingreso_detalles (ingreso_id, producto_id, cantidad, precio_unitario, subtotal)
                    VALUES (:mid, :pid, :qty, :precio, :sub)
                ")->execute([':mid' => $movimientoId, ':pid' => $id, ':qty' => $cantidad, ':precio' => $costo, ':sub' => $total]);

                $stmtStock = $db->prepare("UPDATE productos SET stock = stock + :qty, updated_at = NOW() WHERE id = :id RETURNING stock");
                $stmtStock->execute([':qty' => $cantidad, ':id' => $id]);
            } else {
                $numero = generarNumeroSalida($db);
                $stmt = $db->prepare("
                    INSERT INTO salidas (numero_salida, motivo, observaciones, estado, total, usuario, usuario_id)
                    VALUES (:num, 'otro', :obs, 'completado', :total, :usuario, :usuario_id)
                    RETURNING id
                ");
                $stmt->execute([
                    ':num' => $numero,
                    ':obs' => $observacion,
                    ':total' => $total,
                    ':usuario' => sesionNombre(),
                    ':usuario_id' => $_SESSION['usuario_id'] ?? null,
                ]);
                $movimientoId = $stmt->fetch()['id'];

                $db->prepare("
                    INSERT INTO salida_detalles (salida_id, producto_id, cantidad, costo_unitario, subtotal)
                    VALUES (:mid, :pid, :qty, :costo, :sub)
                ")->execute([':mid' => $movimientoId, ':pid' => $id, ':qty' => $cantidad, ':costo' => $costo, ':sub' => $total]);

                $stmtStock = $db->prepare("UPDATE productos SET stock = stock - :qty, updated_at = NOW() WHERE id = :id RETURNING stock");
                $stmtStock->execute([':qty' => $cantidad, ':id' => $id]);
            }

            $nuevo_stock = $stmtStock->fetch()['stock'];
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => true, 'message' => 'No se pudo registrar el ajuste: ' . $e->getMessage()], 422);
        }

        registrarAuditoria(
            'Ajuste de stock (' . $tipo . ')',
            'inventario',
            "Producto: {$producto['nombre']} | Cantidad: {$cantidad} | Motivo: {$motivo} | Nuevo stock: {$nuevo_stock} | " .
            ($tipo === 'entrada' ? "Ingreso: {$numero}" : "Salida: {$numero}")
        );

        jsonResponse(['error' => false, 'message' => 'Stock ajustado correctamente', 'nuevo_stock' => $nuevo_stock]);

    case 'toggle_activo':
        if (!isAdmin()) jsonResponse(['error' => true, 'message' => 'Solo administradores pueden activar o desactivar productos'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = intval($data['id'] ?? 0);
        if (!$id) {
            jsonResponse(['error' => true, 'message' => 'ID invalido'], 400);
        }

        $stmt = $db->prepare("UPDATE productos SET activo = NOT activo, updated_at = NOW() WHERE id = :id RETURNING activo");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        jsonResponse(['error' => false, 'activo' => $row['activo']]);

    case 'eliminar':
        if (!isAdmin()) jsonResponse(['error' => true, 'message' => 'Solo administradores pueden eliminar productos'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = intval($data['id'] ?? 0);
        if (!$id) {
            jsonResponse(['error' => true, 'message' => 'ID invalido'], 400);
        }

        $stmt = $db->prepare("
            UPDATE productos
            SET activo = FALSE, eliminado = TRUE, eliminado_at = NOW(), eliminado_por = :uid, updated_at = NOW()
            WHERE id = :id AND eliminado = FALSE
            RETURNING nombre
        ");
        $stmt->execute([':id' => $id, ':uid' => sesionId()]);
        $row = $stmt->fetch();
        if (!$row) {
            jsonResponse(['error' => true, 'message' => 'Producto no encontrado o ya estaba eliminado'], 404);
        }

        registrarAuditoria('Eliminación de producto', 'inventario', "Producto: {$row['nombre']} (ID {$id}) | Pasa a Eliminados, se purga en 30 días si no tiene historial");
        jsonResponse(['error' => false, 'message' => 'Producto eliminado. Quedará en "Eliminados" 30 días antes de borrarse definitivamente']);

    case 'eliminados_listar':
        if (!isAdmin()) jsonResponse(['error' => true, 'message' => 'Solo administradores pueden ver los productos eliminados'], 403);

        inventarioPurgarEliminados($db);

        $rows = $db->query("
            SELECT
                p.id, p.codigo, p.codigo_interno, p.codigo_barras, p.nombre, p.stock, p.precio_venta,
                c.nombre AS categoria,
                p.eliminado_at,
                u.nombre AS eliminado_por_nombre,
                GREATEST(0, 30 - FLOOR(EXTRACT(EPOCH FROM (NOW() - p.eliminado_at)) / 86400))::int AS dias_restantes
            FROM productos p
            LEFT JOIN public.categorias c ON c.id = p.categoria_id
            LEFT JOIN public.usuarios u ON u.id = p.eliminado_por
            WHERE p.eliminado = TRUE
            ORDER BY p.eliminado_at DESC
        ")->fetchAll();
        echo json_encode($rows);
        break;

    case 'restaurar':
        if (!isAdmin()) jsonResponse(['error' => true, 'message' => 'Solo administradores pueden restaurar productos'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = intval($data['id'] ?? 0);
        if (!$id) {
            jsonResponse(['error' => true, 'message' => 'ID invalido'], 400);
        }

        $stmt = $db->prepare("
            UPDATE productos
            SET eliminado = FALSE, eliminado_at = NULL, eliminado_por = NULL, activo = TRUE, updated_at = NOW()
            WHERE id = :id AND eliminado = TRUE
            RETURNING nombre
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            jsonResponse(['error' => true, 'message' => 'Producto no encontrado en Eliminados'], 404);
        }

        registrarAuditoria('Restauración de producto eliminado', 'inventario', "Producto: {$row['nombre']} (ID {$id})");
        jsonResponse(['error' => false, 'message' => 'Producto restaurado correctamente']);

    case 'toma_crear':
        if (!isAdmin()) jsonResponse(['error' => true, 'message' => 'Solo administradores pueden crear una toma de inventario'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        $categoriaIds = array_values(array_unique(array_filter(array_map('intval', $data['categorias_ids'] ?? []))));
        $nombre = trim($data['nombre'] ?? '');

        if (empty($categoriaIds)) {
            jsonResponse(['error' => true, 'message' => 'Selecciona al menos una categoría'], 400);
        }

        $catsLiteral = '{' . implode(',', $categoriaIds) . '}';

        $db->beginTransaction();
        try {
            $productosStmt = $db->prepare("
                SELECT p.id, p.codigo, p.nombre, p.categoria_id, p.unidad, p.stock, c.nombre AS categoria_nombre
                FROM productos p
                LEFT JOIN public.categorias c ON c.id = p.categoria_id
                WHERE p.activo = TRUE AND p.categoria_id = ANY(:cats::integer[])
            ");
            $productosStmt->execute([':cats' => $catsLiteral]);
            $productos = $productosStmt->fetchAll();

            if (!$productos) {
                throw new Exception('Las categorías seleccionadas no tienen productos activos');
            }

            $codigo = tomaInvGenerarCodigo($db);

            $stmt = $db->prepare("
                INSERT INTO toma_inventario_sesiones
                    (codigo, nombre, categorias_ids, total_productos, usuario_creador_id)
                VALUES
                    (:codigo, :nombre, :cats::integer[], :total, :uid)
                RETURNING id
            ");
            $stmt->execute([
                ':codigo'   => $codigo,
                ':nombre'   => $nombre !== '' ? $nombre : null,
                ':cats'     => $catsLiteral,
                ':total'    => count($productos),
                ':uid'      => sesionId(),
            ]);
            $sesionId = $stmt->fetch()['id'];

            $detStmt = $db->prepare("
                INSERT INTO toma_inventario_detalles
                    (sesion_id, producto_id, producto_codigo, producto_nombre, categoria_id, categoria_nombre, unidad, stock_sistema)
                VALUES
                    (:sesion_id, :producto_id, :producto_codigo, :producto_nombre, :categoria_id, :categoria_nombre, :unidad, :stock_sistema)
            ");
            foreach ($productos as $p) {
                $detStmt->execute([
                    ':sesion_id'        => $sesionId,
                    ':producto_id'      => $p['id'],
                    ':producto_codigo'  => $p['codigo'],
                    ':producto_nombre'  => $p['nombre'],
                    ':categoria_id'     => $p['categoria_id'],
                    ':categoria_nombre' => $p['categoria_nombre'],
                    ':unidad'           => $p['unidad'],
                    ':stock_sistema'    => $p['stock'],
                ]);
            }

            $db->commit();

            registrarAuditoria(
                'Creación de toma de inventario',
                'inventario',
                "Sesión: {$codigo} | Categorías: " . implode(',', $categoriaIds) . " | Productos: " . count($productos)
            );

            jsonResponse(['error' => false, 'message' => 'Toma de inventario creada', 'id' => $sesionId, 'codigo' => $codigo, 'total_productos' => count($productos)]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 422);
        }

    case 'toma_listar':
        $rows = $db->query("
            SELECT s.id, s.codigo, s.nombre, s.categorias_ids, s.fecha_inicio,
                   s.estado, s.total_productos, s.total_contados, s.fecha_cierre, s.created_at,
                   (
                       SELECT string_agg(c.nombre, ', ' ORDER BY c.nombre)
                       FROM public.categorias c
                       WHERE c.id = ANY(s.categorias_ids)
                   ) AS categorias_nombres,
                   (
                       SELECT COALESCE(SUM(d.stock_sistema), 0)
                       FROM toma_inventario_detalles d
                       WHERE d.sesion_id = s.id
                   ) AS stock_inicial,
                   (
                       SELECT COALESCE(SUM(p.stock), 0)
                       FROM toma_inventario_detalles d
                       JOIN productos p ON p.id = d.producto_id
                       WHERE d.sesion_id = s.id
                   ) AS stock_actual
            FROM toma_inventario_sesiones s
            ORDER BY s.created_at DESC
        ")->fetchAll();
        echo json_encode($rows);
        break;

    case 'toma_detalle':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) jsonResponse(['error' => true, 'message' => 'ID inválido'], 400);

        $sesionStmt = $db->prepare("
            SELECT id, codigo, nombre, categorias_ids, fecha_inicio,
                   estado, total_productos, total_contados, observaciones, fecha_cierre, created_at
            FROM toma_inventario_sesiones WHERE id = :id
        ");
        $sesionStmt->execute([':id' => $id]);
        $sesion = $sesionStmt->fetch();
        if (!$sesion) jsonResponse(['error' => true, 'message' => 'Sesión no encontrada'], 404);

        $detStmt = $db->prepare("
            SELECT d.id, d.producto_id, d.producto_codigo, d.producto_nombre, d.categoria_id, d.categoria_nombre,
                   d.unidad, d.stock_sistema, d.cantidad_contada, d.diferencia, d.contado_en, d.aplicado, d.aplicado_en,
                   p.stock_minimo, p.codigo_barras
            FROM toma_inventario_detalles d
            LEFT JOIN productos p ON p.id = d.producto_id
            WHERE d.sesion_id = :id
            ORDER BY d.producto_nombre ASC
        ");
        $detStmt->execute([':id' => $id]);
        $sesion['detalles'] = $detStmt->fetchAll();

        echo json_encode($sesion);
        break;

    case 'toma_guardar_conteo':
        if (!isAdmin()) jsonResponse(['error' => true, 'message' => 'Solo administradores pueden registrar el conteo'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        $detalleId = intval($data['detalle_id'] ?? 0);
        $cantidadRaw = array_key_exists('cantidad', $data) ? $data['cantidad'] : null;
        $cantidad = ($cantidadRaw === null || $cantidadRaw === '') ? null : floatval($cantidadRaw);

        if (!$detalleId) {
            jsonResponse(['error' => true, 'message' => 'Detalle inválido'], 400);
        }
        if ($cantidad !== null && $cantidad < 0) {
            jsonResponse(['error' => true, 'message' => 'La cantidad no puede ser negativa'], 400);
        }

        $check = $db->prepare("
            SELECT d.id, d.stock_sistema, d.aplicado, s.estado
            FROM toma_inventario_detalles d
            JOIN toma_inventario_sesiones s ON s.id = d.sesion_id
            WHERE d.id = :id
        ");
        $check->execute([':id' => $detalleId]);
        $row = $check->fetch();
        if (!$row) {
            jsonResponse(['error' => true, 'message' => 'Renglón no encontrado'], 404);
        }
        if ($row['estado'] !== 'activa') {
            jsonResponse(['error' => true, 'message' => 'No se puede modificar una sesión que no está activa'], 422);
        }
        if ($row['aplicado']) {
            jsonResponse(['error' => true, 'message' => 'Este producto ya fue aplicado al stock, no se puede modificar'], 422);
        }

        $diferencia = $cantidad === null ? null : round($cantidad - (float) $row['stock_sistema'], 2);

        $upd = $db->prepare("
            UPDATE toma_inventario_detalles
            SET cantidad_contada = :cantidad,
                diferencia = :diferencia,
                usuario_conteo_id = :uid,
                contado_en = :contado_en
            WHERE id = :id
            RETURNING sesion_id, cantidad_contada, diferencia, contado_en
        ");
        $upd->execute([
            ':cantidad'   => $cantidad,
            ':diferencia' => $diferencia,
            ':uid'        => $cantidad === null ? null : sesionId(),
            ':contado_en' => $cantidad === null ? null : date('Y-m-d H:i:s'),
            ':id'         => $detalleId,
        ]);
        $result = $upd->fetch();

        $db->prepare("
            UPDATE toma_inventario_sesiones
            SET total_contados = (SELECT COUNT(*) FROM toma_inventario_detalles WHERE sesion_id = :sid AND cantidad_contada IS NOT NULL),
                updated_at = NOW()
            WHERE id = :sid
        ")->execute([':sid' => $result['sesion_id']]);

        jsonResponse([
            'error'            => false,
            'cantidad_contada' => $result['cantidad_contada'],
            'diferencia'       => $result['diferencia'],
            'contado_en'       => $result['contado_en'],
        ]);

    case 'toma_aplicar_producto':
        if (!isAdmin()) jsonResponse(['error' => true, 'message' => 'Solo administradores pueden aplicar un producto al stock'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        $detalleId = intval($data['detalle_id'] ?? 0);
        if (!$detalleId) jsonResponse(['error' => true, 'message' => 'Detalle inválido'], 400);

        $check = $db->prepare("
            SELECT d.id, d.producto_id, d.cantidad_contada, d.diferencia, d.aplicado, d.sesion_id, s.estado
            FROM toma_inventario_detalles d
            JOIN toma_inventario_sesiones s ON s.id = d.sesion_id
            WHERE d.id = :id
        ");
        $check->execute([':id' => $detalleId]);
        $row = $check->fetch();
        if (!$row) jsonResponse(['error' => true, 'message' => 'Renglón no encontrado'], 404);
        if ($row['estado'] !== 'activa') {
            jsonResponse(['error' => true, 'message' => 'No se puede modificar una sesión que no está activa'], 422);
        }
        if ($row['aplicado']) {
            jsonResponse(['error' => true, 'message' => 'Este producto ya fue aplicado al stock'], 422);
        }
        if ($row['cantidad_contada'] === null) {
            jsonResponse(['error' => true, 'message' => 'Primero ingresa el conteo físico de este producto'], 422);
        }
        if (!$row['producto_id']) {
            jsonResponse(['error' => true, 'message' => 'El producto de esta fila ya no existe'], 422);
        }

        $db->beginTransaction();
        try {
            // El conteo fisico reemplaza directamente el stock (no es un
            // ajuste por diferencia contra la foto de cuando se creo la
            // sesion) -- si el stock real ya habia cambiado mientras tanto
            // (ej. una venta), el resultado antes no coincidia con el
            // numero contado. Se guarda el stock previo para que "Editar"
            // pueda revertir exactamente a ese valor.
            $stockAntesStmt = $db->prepare("SELECT stock FROM productos WHERE id = :pid");
            $stockAntesStmt->execute([':pid' => $row['producto_id']]);
            $stockAntes = $stockAntesStmt->fetch()['stock'];

            $db->prepare("UPDATE productos SET stock = :nuevo, updated_at = NOW() WHERE id = :pid")
               ->execute([':nuevo' => $row['cantidad_contada'], ':pid' => $row['producto_id']]);

            $db->prepare("UPDATE toma_inventario_detalles SET aplicado = TRUE, aplicado_en = NOW(), stock_antes_aplicar = :antes WHERE id = :id")
               ->execute([':antes' => $stockAntes, ':id' => $detalleId]);

            $db->commit();

            registrarAuditoria(
                'Aplicación individual de conteo (toma de inventario)',
                'inventario',
                "Producto ID: {$row['producto_id']} | Stock: {$stockAntes} -> {$row['cantidad_contada']} | Sesión ID: {$row['sesion_id']}"
            );

            jsonResponse(['error' => false, 'message' => 'Producto aplicado al stock']);
        } catch (Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 422);
        }

    case 'toma_reabrir_producto':
        if (!isAdmin()) jsonResponse(['error' => true, 'message' => 'Solo administradores pueden corregir un producto ya aplicado'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        $detalleId = intval($data['detalle_id'] ?? 0);
        if (!$detalleId) jsonResponse(['error' => true, 'message' => 'Detalle inválido'], 400);

        $check = $db->prepare("
            SELECT d.id, d.producto_id, d.stock_antes_aplicar, d.aplicado, d.sesion_id, s.estado
            FROM toma_inventario_detalles d
            JOIN toma_inventario_sesiones s ON s.id = d.sesion_id
            WHERE d.id = :id
        ");
        $check->execute([':id' => $detalleId]);
        $row = $check->fetch();
        if (!$row) jsonResponse(['error' => true, 'message' => 'Renglón no encontrado'], 404);
        if ($row['estado'] !== 'activa') {
            jsonResponse(['error' => true, 'message' => 'No se puede modificar una sesión que no está activa'], 422);
        }
        if (!$row['aplicado']) {
            jsonResponse(['error' => true, 'message' => 'Este producto todavía no fue aplicado'], 422);
        }
        if (!$row['producto_id']) {
            jsonResponse(['error' => true, 'message' => 'El producto de esta fila ya no existe'], 422);
        }

        $db->beginTransaction();
        try {
            // Restaura el stock exacto que tenia el producto justo antes de
            // esta aplicacion (no una diferencia), dejandolo listo para un
            // nuevo conteo/aplicacion con el valor corregido.
            $db->prepare("UPDATE productos SET stock = :antes, updated_at = NOW() WHERE id = :pid")
               ->execute([':antes' => $row['stock_antes_aplicar'], ':pid' => $row['producto_id']]);

            $db->prepare("UPDATE toma_inventario_detalles SET aplicado = FALSE, aplicado_en = NULL, stock_antes_aplicar = NULL WHERE id = :id")
               ->execute([':id' => $detalleId]);

            $db->commit();

            registrarAuditoria(
                'Corrección de conteo aplicado (toma de inventario)',
                'inventario',
                "Producto ID: {$row['producto_id']} | Stock restaurado a: {$row['stock_antes_aplicar']} | Sesión ID: {$row['sesion_id']}"
            );

            jsonResponse(['error' => false, 'message' => 'Producto reabierto para corrección']);
        } catch (Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 422);
        }

    case 'toma_cancelar':
        if (!isAdmin()) jsonResponse(['error' => true, 'message' => 'Solo administradores pueden cancelar una toma de inventario'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        $motivo = trim($data['motivo'] ?? '');
        if (!$id) jsonResponse(['error' => true, 'message' => 'ID inválido'], 400);

        $sesion = $db->prepare("SELECT codigo, estado FROM toma_inventario_sesiones WHERE id = :id");
        $sesion->execute([':id' => $id]);
        $row = $sesion->fetch();
        if (!$row) jsonResponse(['error' => true, 'message' => 'Sesión no encontrada'], 404);
        if ($row['estado'] !== 'activa') {
            jsonResponse(['error' => true, 'message' => 'Solo se puede cancelar una sesión activa'], 422);
        }

        $db->prepare("
            UPDATE toma_inventario_sesiones
            SET estado = 'cancelada', fecha_cierre = NOW(), usuario_cierre_id = :uid, updated_at = NOW()
            WHERE id = :id
        ")->execute([':uid' => sesionId(), ':id' => $id]);

        registrarAuditoria('Cancelación de toma de inventario', 'inventario', "Sesión: {$row['codigo']}" . ($motivo !== '' ? " | Motivo: {$motivo}" : ''));

        jsonResponse(['error' => false, 'message' => 'Sesión cancelada']);

    case 'toma_aplicar':
        if (!isAdmin()) jsonResponse(['error' => true, 'message' => 'Solo administradores pueden cerrar una toma de inventario'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id'] ?? 0);
        if (!$id) jsonResponse(['error' => true, 'message' => 'ID inválido'], 400);

        $sesion = $db->prepare("SELECT codigo, estado, total_productos FROM toma_inventario_sesiones WHERE id = :id");
        $sesion->execute([':id' => $id]);
        $sesionRow = $sesion->fetch();
        if (!$sesionRow) jsonResponse(['error' => true, 'message' => 'Sesión no encontrada'], 404);
        if ($sesionRow['estado'] !== 'activa') {
            jsonResponse(['error' => true, 'message' => 'La sesión ya fue cerrada o cancelada'], 422);
        }

        $db->beginTransaction();
        try {
            $statsStmt = $db->prepare("
                SELECT
                    COUNT(*) FILTER (WHERE cantidad_contada IS NOT NULL)         AS contados,
                    COUNT(*) FILTER (WHERE aplicado)                            AS aplicados
                FROM toma_inventario_detalles WHERE sesion_id = :id
            ");
            $statsStmt->execute([':id' => $id]);
            $stats = $statsStmt->fetch();
            $contados  = (int) $stats['contados'];
            $aplicados = (int) $stats['aplicados'];
            $sinContar = (int) $sesionRow['total_productos'] - $contados;

            $db->prepare("
                UPDATE toma_inventario_sesiones
                SET estado = 'completada', fecha_cierre = NOW(), usuario_cierre_id = :uid, updated_at = NOW()
                WHERE id = :id
            ")->execute([':uid' => sesionId(), ':id' => $id]);

            $db->commit();

            registrarAuditoria(
                'Cierre de toma de inventario',
                'inventario',
                "Sesión: {$sesionRow['codigo']} | Aplicados: {$aplicados} | Contados sin aplicar: " . ($contados - $aplicados) . " | Sin contar: {$sinContar}"
            );

            jsonResponse(['error' => false, 'message' => 'Sesión cerrada correctamente', 'aplicados' => $aplicados, 'sin_contar' => $sinContar]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 422);
        }

    default:
        jsonResponse(['error' => true, 'message' => 'Accion no valida'], 404);
}

} catch (PDOException $e) {
    jsonResponse(['error' => true, 'message' => 'Error de base de datos: ' . $e->getMessage()], 500);
} catch (Throwable $e) {
    jsonResponse(['error' => true, 'message' => 'Error interno: ' . $e->getMessage()], 500);
}
