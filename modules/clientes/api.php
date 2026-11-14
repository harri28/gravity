<?php
header('Content-Type: application/json; charset=UTF-8');
require_once '../../config/database.php';
requireApiAuth(['admin', 'gerente', 'cajero']);

$action = $_GET['action'] ?? '';
$db     = getDB();

function listarTiposDocumento(PDO $db): array
{
    $stmt = $db->query("
        SELECT id, codigo, descripcion, descripcion_documento
        FROM public.fe_tipos_documento_identidad
        WHERE estado = TRUE AND codigo IN ('1', '6', '0')
        ORDER BY CASE codigo
            WHEN '1' THEN 0
            WHEN '6' THEN 1
            ELSE 99
        END
    ");

    return $stmt->fetchAll();
}

function obtenerTipoDocumento(PDO $db, $id): ?array
{
    $stmt = $db->prepare("
        SELECT id, codigo, descripcion, descripcion_documento
        FROM public.fe_tipos_documento_identidad
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => intval($id)]);
    return $stmt->fetch() ?: null;
}

function validarDocumentoPorTipo(string $codigo, string $numero): ?string
{
    $numero = trim($numero);

    if ($codigo === '1' && !preg_match('/^\d{8}$/', $numero)) {
        return 'Para DNI debe ingresar exactamente 8 digitos.';
    }

    if ($codigo === '6' && !preg_match('/^\d{11}$/', $numero)) {
        return 'Para RUC debe ingresar exactamente 11 digitos.';
    }

    if ($codigo !== '1' && $codigo !== '6' && $numero === '') {
        return 'Debe ingresar el numero de documento.';
    }

    return null;
}

function consultarDocumentoExterno(string $numero): array
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

function construirPayloadCliente(array $input, array $tipoDocumento): array
{
    $codigo = trim((string) ($tipoDocumento['codigo'] ?? '0'));
    $numeroDocumento = trim((string) ($input['numero_documento'] ?? ''));
    $nombres = trim((string) ($input['nombres'] ?? ''));
    $apellidos = trim((string) ($input['apellidos'] ?? ''));
    $telefono = trim((string) ($input['telefono'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $direccion = trim((string) ($input['direccion'] ?? ''));
    $ubigeo = trim((string) ($input['ubigeo'] ?? ''));
    $fechaNacimiento = trim((string) ($input['fecha_nacimiento'] ?? ''));
    $motivo = trim((string) ($input['motivo'] ?? ''));

    if ($codigo === '6') {
        $apellidos = '';
    }

    $nombreCompleto = trim($nombres . ' ' . $apellidos);
    if ($nombreCompleto === '') {
        $nombreCompleto = $nombres;
    }

    $razonSocial = $codigo === '6'
        ? $nombres
        : ($nombreCompleto !== '' ? $nombreCompleto : $nombres);

    return [
        'tipo_documento_id' => intval($tipoDocumento['id']),
        'tipo_documento_codigo' => $codigo,
        'numero_documento' => $numeroDocumento,
        'nombres' => $nombres,
        'apellidos' => $apellidos,
        'nombre_completo' => $nombreCompleto,
        'razon_social' => $razonSocial,
        'dni' => $codigo === '1' ? $numeroDocumento : null,
        'ruc' => $codigo === '6' ? $numeroDocumento : null,
        'telefono' => $telefono !== '' ? $telefono : null,
        'email' => $email !== '' ? $email : null,
        'direccion' => $direccion !== '' ? $direccion : null,
        'codigo_pais' => 'PE',
        'ubigeo' => preg_match('/^\d{6}$/', $ubigeo) ? $ubigeo : null,
        'fecha_nacimiento' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNacimiento) ? $fechaNacimiento : null,
        'motivo' => in_array($motivo, ['cumpleanos', 'cortesia', 'promocion', 'otro'], true) ? $motivo : null,
    ];
}

switch ($action) {
    case 'document_types':
        jsonResponse([
            'error' => false,
            'items' => listarTiposDocumento($db),
        ]);

    case 'lookup_document':
        $d = json_decode(file_get_contents('php://input'), true);
        $tipoDocumento = obtenerTipoDocumento($db, $d['tipo_documento_id'] ?? 0);
        if (!$tipoDocumento) {
            jsonResponse(['error' => true, 'message' => 'Tipo de documento invalido'], 422);
        }

        $codigo = trim((string) $tipoDocumento['codigo']);
        $numero = trim((string) ($d['numero_documento'] ?? ''));
        $validacion = validarDocumentoPorTipo($codigo, $numero);
        if ($validacion !== null) {
            jsonResponse(['error' => true, 'message' => $validacion], 422);
        }

        if (!in_array($codigo, ['1', '6'], true)) {
            jsonResponse(['error' => true, 'message' => 'La consulta automatica solo esta disponible para DNI y RUC'], 422);
        }

        $consulta = consultarDocumentoExterno($numero);
        if (!$consulta['ok']) {
            jsonResponse(['error' => true, 'message' => $consulta['message']], 404);
        }

        $data = $consulta['data'];
        if (!$data) {
            jsonResponse(['error' => true, 'message' => 'No se encontro informacion para el documento consultado'], 404);
        }

        if ($codigo === '1') {
            $payload = [
                'nombres' => trim((string) ($data['nombres'] ?? '')),
                'apellidos' => trim((string) (($data['apellido_paterno'] ?? '') . ' ' . ($data['apellido_materno'] ?? ''))),
                'direccion' => trim((string) ($data['direccion'] ?? '')),
                'ubigeo' => (($data['ubigeo_sunat'] ?? '') === '-' ? '' : trim((string) ($data['ubigeo_sunat'] ?? ''))),
            ];
        } else {
            $payload = [
                'nombres' => trim((string) ($data['nombre_o_razon_social'] ?? '')),
                'apellidos' => '',
                'direccion' => trim((string) ($data['direccion'] ?? '')),
                'ubigeo' => (($data['ubigeo_sunat'] ?? '') === '-' ? '' : trim((string) ($data['ubigeo_sunat'] ?? ''))),
            ];
        }

        jsonResponse([
            'error' => false,
            'message' => 'Documento consultado correctamente',
            'cliente' => $payload,
        ]);

    case 'stats':
        try {
            $row = $db->query("
                SELECT
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE activo = TRUE) AS activos,
                    COUNT(*) FILTER (WHERE activo = FALSE) AS inactivos,
                    COUNT(*) FILTER (WHERE DATE_TRUNC('month', created_at) = DATE_TRUNC('month', CURRENT_DATE)) AS nuevos_mes
                FROM clientes
            ")->fetch();
            $ven = $db->query("
                SELECT COALESCE(SUM(total), 0) AS total_facturado
                FROM ventas
                WHERE cliente_id IS NOT NULL AND estado = 'completada'
            ")->fetch();
            echo json_encode(array_merge($row, $ven));
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'listar':
        try {
            $q = '%' . trim($_GET['q'] ?? '') . '%';
            $estado = trim((string) ($_GET['estado'] ?? ''));

            $where = [
                "(COALESCE(c.razon_social,'') ILIKE :q
                  OR COALESCE(c.nombre_completo,'') ILIKE :q
                  OR COALESCE(c.nombres,'') ILIKE :q
                  OR COALESCE(c.apellidos,'') ILIKE :q
                  OR COALESCE(c.numero_documento,'') ILIKE :q
                  OR COALESCE(c.dni,'') ILIKE :q
                  OR COALESCE(c.ruc,'') ILIKE :q
                  OR COALESCE(c.telefono,'') ILIKE :q
                  OR COALESCE(c.email,'') ILIKE :q)"
            ];
            $params = [':q' => $q];

            if ($estado === 'activo') {
                $where[] = 'c.activo = TRUE';
            } elseif ($estado === 'inactivo') {
                $where[] = 'c.activo = FALSE';
            }

            $stmt = $db->prepare("
                SELECT
                    c.id,
                    c.nombres,
                    c.apellidos,
                    c.dni,
                    c.ruc,
                    c.telefono,
                    c.email,
                    c.direccion,
                    c.activo,
                    c.created_at,
                    c.tipo_documento_id,
                    c.tipo_documento_codigo,
                    COALESCE(c.numero_documento, COALESCE(NULLIF(c.ruc,''), NULLIF(c.dni,''), '')) AS numero_documento,
                    c.nombre_completo,
                    c.razon_social,
                    c.ubigeo,
                    c.fecha_nacimiento,
                    c.motivo,
                    td.descripcion_documento,
                    COUNT(v.id) FILTER (WHERE v.estado = 'completada') AS total_compras,
                    COALESCE(SUM(v.total) FILTER (WHERE v.estado = 'completada'), 0) AS total_gastado,
                    MAX(v.created_at) FILTER (WHERE v.estado = 'completada') AS ultima_compra
                FROM clientes c
                LEFT JOIN public.fe_tipos_documento_identidad td ON td.id = c.tipo_documento_id
                LEFT JOIN ventas v ON v.cliente_id = c.id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY c.id, td.descripcion_documento
                ORDER BY
                    CASE
                        WHEN COALESCE(c.numero_documento, '') = '00000000'
                            OR UPPER(COALESCE(c.razon_social, '')) = 'CLIENTES VARIOS'
                            OR UPPER(TRIM(COALESCE(c.nombres, '') || ' ' || COALESCE(c.apellidos, ''))) = 'CLIENTES VARIOS'
                        THEN 0
                        ELSE 1
                    END,
                    COALESCE(c.razon_social, c.nombre_completo, c.nombres) ASC
            ");
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'crear':
    case 'actualizar':
        $d = json_decode(file_get_contents('php://input'), true);
        $esActualizar = $action === 'actualizar';
        $id = intval($d['id'] ?? 0);

        if ($esActualizar && !$id) {
            jsonResponse(['error' => true, 'message' => 'ID invalido'], 400);
        }

        $tipoDocumento = obtenerTipoDocumento($db, $d['tipo_documento_id'] ?? 0);
        if (!$tipoDocumento) {
            jsonResponse(['error' => true, 'message' => 'Debe seleccionar un tipo de documento valido'], 422);
        }

        $codigoTipo = trim((string) $tipoDocumento['codigo']);
        $numeroDocumento = trim((string) ($d['numero_documento'] ?? ''));
        $validacionDocumento = validarDocumentoPorTipo($codigoTipo, $numeroDocumento);
        if ($validacionDocumento !== null) {
            jsonResponse(['error' => true, 'message' => $validacionDocumento], 422);
        }

        $nombres = trim((string) ($d['nombres'] ?? ''));
        $apellidos = trim((string) ($d['apellidos'] ?? ''));
        if ($codigoTipo === '6') {
            $apellidos = '';
        }

        if ($nombres === '') {
            jsonResponse(['error' => true, 'message' => $codigoTipo === '6' ? 'Debe ingresar la razon social' : 'Debe ingresar el nombre del cliente'], 422);
        }

        $email = trim((string) ($d['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => true, 'message' => 'El correo no es valido'], 422);
        }

        try {
            $dupSql = "
                SELECT id
                FROM clientes
                WHERE tipo_documento_codigo = :codigo
                  AND numero_documento = :numero
            ";
            $dupParams = [
                ':codigo' => $codigoTipo,
                ':numero' => $numeroDocumento,
            ];

            if ($esActualizar) {
                $dupSql .= " AND id != :id";
                $dupParams[':id'] = $id;
            }

            $dupStmt = $db->prepare($dupSql);
            $dupStmt->execute($dupParams);
            if ($dupStmt->fetch()) {
                jsonResponse(['error' => true, 'message' => 'Ya existe otro cliente con ese documento'], 422);
            }

            $payload = construirPayloadCliente($d, $tipoDocumento);

            if ($esActualizar) {
                $stmt = $db->prepare("
                    UPDATE clientes SET
                        nombres = :nombres,
                        apellidos = :apellidos,
                        dni = :dni,
                        ruc = :ruc,
                        telefono = :telefono,
                        email = :email,
                        direccion = :direccion,
                        tipo_documento_id = :tipo_documento_id,
                        tipo_documento_codigo = :tipo_documento_codigo,
                        numero_documento = :numero_documento,
                        nombre_completo = :nombre_completo,
                        razon_social = :razon_social,
                        codigo_pais = :codigo_pais,
                        ubigeo = :ubigeo,
                        fecha_nacimiento = COALESCE(CAST(:fecha_nacimiento AS date), fecha_nacimiento),
                        motivo = COALESCE(:motivo, motivo)
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':nombres' => $payload['nombres'],
                    ':apellidos' => $payload['apellidos'],
                    ':dni' => $payload['dni'],
                    ':ruc' => $payload['ruc'],
                    ':telefono' => $payload['telefono'],
                    ':email' => $payload['email'],
                    ':direccion' => $payload['direccion'],
                    ':tipo_documento_id' => $payload['tipo_documento_id'],
                    ':tipo_documento_codigo' => $payload['tipo_documento_codigo'],
                    ':numero_documento' => $payload['numero_documento'],
                    ':nombre_completo' => $payload['nombre_completo'],
                    ':razon_social' => $payload['razon_social'],
                    ':codigo_pais' => $payload['codigo_pais'],
                    ':ubigeo' => $payload['ubigeo'],
                    ':fecha_nacimiento' => $payload['fecha_nacimiento'],
                    ':motivo' => $payload['motivo'],
                ]);

                jsonResponse(['error' => false, 'message' => 'Cliente actualizado correctamente']);
            }

            $stmt = $db->prepare("
                INSERT INTO clientes (
                    nombres, apellidos, dni, ruc, telefono, email, direccion, activo,
                    tipo_documento_id, tipo_documento_codigo, numero_documento,
                    nombre_completo, razon_social, codigo_pais, ubigeo,
                    fecha_nacimiento, motivo
                ) VALUES (
                    :nombres, :apellidos, :dni, :ruc, :telefono, :email, :direccion, TRUE,
                    :tipo_documento_id, :tipo_documento_codigo, :numero_documento,
                    :nombre_completo, :razon_social, :codigo_pais, :ubigeo,
                    :fecha_nacimiento, :motivo
                )
                RETURNING id
            ");
            $stmt->execute([
                ':nombres' => $payload['nombres'],
                ':apellidos' => $payload['apellidos'],
                ':dni' => $payload['dni'],
                ':ruc' => $payload['ruc'],
                ':telefono' => $payload['telefono'],
                ':email' => $payload['email'],
                ':direccion' => $payload['direccion'],
                ':tipo_documento_id' => $payload['tipo_documento_id'],
                ':tipo_documento_codigo' => $payload['tipo_documento_codigo'],
                ':numero_documento' => $payload['numero_documento'],
                ':nombre_completo' => $payload['nombre_completo'],
                ':razon_social' => $payload['razon_social'],
                ':codigo_pais' => $payload['codigo_pais'],
                ':ubigeo' => $payload['ubigeo'],
                ':fecha_nacimiento' => $payload['fecha_nacimiento'],
                ':motivo' => $payload['motivo'],
            ]);

            jsonResponse([
                'error' => false,
                'message' => 'Cliente registrado correctamente',
                'id' => $stmt->fetch()['id'],
            ]);
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
        break;

    case 'toggle_activo':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        if (!$id) {
            jsonResponse(['error' => true, 'message' => 'ID invalido'], 400);
        }

        try {
            $db->prepare("UPDATE clientes SET activo = NOT activo WHERE id = :id")->execute([':id' => $id]);
            $row = $db->prepare("SELECT activo FROM clientes WHERE id = :id");
            $row->execute([':id' => $id]);
            $activo = $row->fetchColumn();
            $activo = ($activo === true || $activo === 't' || $activo === '1');
            jsonResponse([
                'error' => false,
                'activo' => $activo,
                'message' => $activo ? 'Cliente activado' : 'Cliente desactivado',
            ]);
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
        break;

    case 'historial':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(['error' => true, 'message' => 'ID invalido'], 400);
        }

        try {
            $cStmt = $db->prepare("
                SELECT
                    id, nombres, apellidos, dni, ruc, telefono, email, direccion,
                    tipo_documento_id, tipo_documento_codigo,
                    COALESCE(numero_documento, COALESCE(NULLIF(ruc,''), NULLIF(dni,''), '')) AS numero_documento,
                    nombre_completo, razon_social, ubigeo
                FROM clientes
                WHERE id = :id
            ");
            $cStmt->execute([':id' => $id]);
            $cliente = $cStmt->fetch();
            if (!$cliente) {
                jsonResponse(['error' => true, 'message' => 'Cliente no encontrado'], 404);
            }

            $vStmt = $db->prepare("
                SELECT v.id, v.numero_venta, v.total, v.estado, v.metodo_pago, v.created_at,
                       COUNT(d.id) AS num_items
                FROM ventas v
                LEFT JOIN venta_detalles d ON d.venta_id = v.id
                WHERE v.cliente_id = :id
                GROUP BY v.id
                ORDER BY v.created_at DESC
                LIMIT 50
            ");
            $vStmt->execute([':id' => $id]);
            $cliente['ventas'] = $vStmt->fetchAll();
            echo json_encode($cliente);
        } catch (Exception $e) {
            jsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
        break;

    default:
        jsonResponse(['error' => true, 'message' => 'Accion no valida'], 404);
}
