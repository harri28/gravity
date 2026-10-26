<?php
// ============================================================
// ARCHIVO: farmacia/modules/admin/api.php
// DESCRIPCIÓN: API para gestión de usuarios y sucursales
//              Solo accesible por rol 'admin'
// ============================================================

header('Content-Type: application/json; charset=UTF-8');
require_once '../../config/database.php';
requireApiAuth(['admin', 'gerente']);

$action = $_GET['action'] ?? '';
$db     = getDB();

// Acciones exclusivas del rol admin
$admin_only = [
    'usuarios_listar', 'usuario_crear', 'usuario_actualizar',
    'usuario_cambiar_password', 'usuario_toggle_activo',
    'asignar_acceso', 'revocar_acceso',
    'sucursales_listar', 'sucursal_crear', 'sucursal_actualizar', 'sucursal_toggle_activo',
    'mesa_crear', 'mesa_actualizar', 'mesa_toggle_activa',
    'config_guardar', 'logo_subir', 'logo_eliminar', 'certificate_subir',
    'tema_guardar',
];
if (in_array($action, $admin_only, true) && !isAdmin()) {
    jsonResponse(['error' => true, 'message' => 'No tienes permiso para realizar esta acción'], 403);
}

switch ($action) {

    case 'ubigeo_resolver':
        $codigo = trim($_GET['codigo'] ?? '');
        if (!preg_match('/^\d{6}$/', $codigo)) {
            jsonResponse(['error' => true, 'message' => 'El ubigeo debe tener 6 digitos'], 400);
        }

        $tablaUbigeo = $db->query("SELECT to_regclass('public.ubigeo_distritos') AS tabla")->fetch();
        if (empty($tablaUbigeo['tabla'])) {
            jsonResponse(['error' => true, 'message' => 'El catalogo de ubigeo aun no esta cargado'], 404);
        }

        $stmt = $db->prepare("
            SELECT
                d.codigo,
                d.nombre AS distrito,
                p.nombre AS provincia,
                dep.nombre AS departamento
            FROM public.ubigeo_distritos d
            INNER JOIN public.ubigeo_provincias p
                ON p.codigo = d.provincia_codigo
            INNER JOIN public.ubigeo_departamentos dep
                ON dep.codigo = d.departamento_codigo
            WHERE d.codigo = :codigo
            LIMIT 1
        ");
        $stmt->execute([':codigo' => $codigo]);
        $row = $stmt->fetch();

        if (!$row) {
            jsonResponse(['error' => true, 'message' => 'No se encontro el ubigeo solicitado'], 404);
        }

        jsonResponse([
            'error' => false,
            'codigo' => $row['codigo'],
            'departamento' => $row['departamento'],
            'provincia' => $row['provincia'],
            'distrito' => $row['distrito'],
        ]);

    // ================================================================
    // USUARIOS
    // ================================================================

    case 'usuarios_listar':
        $stmt = $db->prepare("
            SELECT u.id, u.nombre, u.apellido, u.username, u.activo, u.created_at,
                   COALESCE(
                       json_agg(
                           json_build_object('sucursal_id', s.id, 'sucursal', s.nombre, 'rol', us.rol, 'activo', us.activo)
                           ORDER BY s.nombre
                       ) FILTER (WHERE s.id IS NOT NULL),
                       '[]'
                   ) AS accesos
            FROM public.usuarios u
            LEFT JOIN public.usuario_sucursal us ON us.usuario_id = u.id
            LEFT JOIN public.sucursales s        ON s.id = us.sucursal_id AND s.tenant_id = :tid
            WHERE u.tenant_id = :tid
            GROUP BY u.id
            ORDER BY u.nombre ASC
        ");
        $stmt->execute([':tid' => sesionTenantId()]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'usuario_crear':
        $d = json_decode(file_get_contents('php://input'), true);
        if (empty($d['nombre']) || empty($d['username']) || empty($d['password'])) {
            jsonResponse(['error' => true, 'message' => 'Nombre, usuario y contraseña son requeridos'], 400);
        }
        // Verificar username único
        $ck = $db->prepare("SELECT id FROM public.usuarios WHERE username = :u");
        $ck->execute([':u' => trim($d['username'])]);
        if ($ck->fetch()) {
            jsonResponse(['error' => true, 'message' => 'El nombre de usuario ya existe'], 409);
        }
        $stmt = $db->prepare("
            INSERT INTO public.usuarios (tenant_id, nombre, apellido, username, password_hash)
            VALUES (:tid, :n, :a, :u, :h)
            RETURNING id
        ");
        $stmt->execute([
            ':tid' => sesionTenantId(),
            ':n'   => trim($d['nombre']),
            ':a'   => trim($d['apellido'] ?? ''),
            ':u'   => trim($d['username']),
            ':h'   => password_hash($d['password'], PASSWORD_BCRYPT),
        ]);
        $nuevo_id = $stmt->fetch()['id'];

        // Asignar sucursal + rol si se envían
        if (!empty($d['sucursal_id']) && !empty($d['rol'])) {
            $db->prepare("
                INSERT INTO public.usuario_sucursal (usuario_id, sucursal_id, rol)
                VALUES (:uid, :sid, :rol)
                ON CONFLICT (usuario_id, sucursal_id) DO UPDATE SET rol = EXCLUDED.rol, activo = TRUE
            ")->execute([':uid' => $nuevo_id, ':sid' => intval($d['sucursal_id']), ':rol' => $d['rol']]);
        }

        registrarAuditoria('Creación de usuario', 'admin', "Usuario: {$d['username']} | Nombre: {$d['nombre']}");

        jsonResponse(['error' => false, 'message' => 'Usuario creado correctamente', 'id' => $nuevo_id]);

    case 'usuario_actualizar':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        if (!$id || empty($d['nombre']) || empty($d['username'])) {
            jsonResponse(['error' => true, 'message' => 'Datos incompletos'], 400);
        }
        // Verificar username único (excluyendo el mismo usuario)
        $ck = $db->prepare("SELECT id FROM public.usuarios WHERE username = :u AND id <> :id");
        $ck->execute([':u' => trim($d['username']), ':id' => $id]);
        if ($ck->fetch()) {
            jsonResponse(['error' => true, 'message' => 'El nombre de usuario ya existe'], 409);
        }
        $db->prepare("
            UPDATE public.usuarios SET nombre = :n, apellido = :a, username = :u
            WHERE id = :id AND tenant_id = :tid
        ")->execute([':n' => trim($d['nombre']), ':a' => trim($d['apellido'] ?? ''), ':u' => trim($d['username']), ':id' => $id, ':tid' => sesionTenantId()]);

        registrarAuditoria('Edición de usuario', 'admin', "Usuario ID: {$id} | Nuevo username: {$d['username']}");

        jsonResponse(['error' => false, 'message' => 'Usuario actualizado']);

    case 'usuario_cambiar_password':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        if (!$id || empty($d['password']) || strlen($d['password']) < 4) {
            jsonResponse(['error' => true, 'message' => 'La contraseña debe tener al menos 4 caracteres'], 400);
        }
        $db->prepare("UPDATE public.usuarios SET password_hash = :h WHERE id = :id")
           ->execute([':h' => password_hash($d['password'], PASSWORD_BCRYPT), ':id' => $id]);

        registrarAuditoria('Cambio de contraseña de usuario', 'admin', "Usuario ID: {$id}");

        jsonResponse(['error' => false, 'message' => 'Contraseña actualizada']);

    case 'usuario_toggle_activo':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        // No permitir desactivar al propio usuario
        if ($id === sesionId()) {
            jsonResponse(['error' => true, 'message' => 'No puedes desactivar tu propia cuenta'], 400);
        }
        $stmt = $db->prepare("UPDATE public.usuarios SET activo = NOT activo WHERE id = :id AND tenant_id = :tid RETURNING activo");
        $stmt->execute([':id' => $id, ':tid' => sesionTenantId()]);
        $row = $stmt->fetch();

        registrarAuditoria('Cambio de estado de usuario', 'admin', "Usuario ID: {$id} | Activo: " . ($row['activo'] ? 'si' : 'no'));

        jsonResponse(['error' => false, 'activo' => $row['activo']]);

    case 'asignar_acceso':
        $d = json_decode(file_get_contents('php://input'), true);
        if (empty($d['usuario_id']) || empty($d['sucursal_id']) || empty($d['rol'])) {
            jsonResponse(['error' => true, 'message' => 'Datos incompletos'], 400);
        }
        $db->prepare("
            INSERT INTO public.usuario_sucursal (usuario_id, sucursal_id, rol, activo)
            VALUES (:uid, :sid, :rol, TRUE)
            ON CONFLICT (usuario_id, sucursal_id)
            DO UPDATE SET rol = EXCLUDED.rol, activo = TRUE
        ")->execute([':uid' => intval($d['usuario_id']), ':sid' => intval($d['sucursal_id']), ':rol' => $d['rol']]);
        jsonResponse(['error' => false, 'message' => 'Acceso asignado']);

    case 'revocar_acceso':
        $d = json_decode(file_get_contents('php://input'), true);
        $db->prepare("
            UPDATE public.usuario_sucursal SET activo = FALSE
            WHERE usuario_id = :uid AND sucursal_id = :sid
        ")->execute([':uid' => intval($d['usuario_id']), ':sid' => intval($d['sucursal_id'])]);
        jsonResponse(['error' => false, 'message' => 'Acceso revocado']);

    // ================================================================
    // SUCURSALES
    // ================================================================

    case 'sucursales_listar':
        $stmt = $db->prepare("
            SELECT s.id, s.nombre, s.schema_name, s.direccion, s.telefono, s.activo, s.created_at,
                   COUNT(us.id) AS total_usuarios
            FROM public.sucursales s
            LEFT JOIN public.usuario_sucursal us ON us.sucursal_id = s.id AND us.activo = TRUE
            WHERE s.tenant_id = :tid
            GROUP BY s.id
            ORDER BY s.nombre ASC
        ");
        $stmt->execute([':tid' => sesionTenantId()]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'sucursal_crear':
        $d = json_decode(file_get_contents('php://input'), true);
        if (empty($d['nombre'])) {
            jsonResponse(['error' => true, 'message' => 'El nombre es requerido'], 400);
        }

        // Generar slug de la sucursal desde el nombre
        $suc_slug = strtolower(trim($d['nombre']));
        $suc_slug = iconv('UTF-8', 'ASCII//TRANSLIT', $suc_slug) ?: $suc_slug;
        $suc_slug = preg_replace('/[^a-z0-9]+/', '_', $suc_slug);
        $suc_slug = trim($suc_slug, '_');
        $suc_slug = substr($suc_slug, 0, 20) ?: 'suc';

        // Prefijo del tenant para garantizar aislamiento entre tenants
        $tenant_slug = sesionTenantSlug();
        $schema      = $tenant_slug . '_' . $suc_slug;
        $schema      = substr($schema, 0, 55); // PostgreSQL max identifier 63 chars

        // Verificar unicidad del schema_name
        $ck = $db->prepare("SELECT id FROM public.sucursales WHERE schema_name = :s");
        $ck->execute([':s' => $schema]);
        if ($ck->fetch()) {
            $schema .= '_' . (time() % 1000);
        }

        $sql_file = __DIR__ . '/../../database/schema_sucursal.sql';
        $facturacion_file = __DIR__ . '/../../database/migration_09_facturacion_campos_operativos.sql';
        if (!file_exists($sql_file)) {
            jsonResponse(['error' => true, 'message' => 'No se encontró schema_sucursal.sql'], 500);
        }

        try {
            // ---- DDL fuera de transacción ----
            $db->exec("CREATE SCHEMA IF NOT EXISTS \"{$schema}\"");
            $db->exec("SET search_path TO \"{$schema}\", public");

            $sql_raw = file_get_contents($sql_file);
            $sql_raw = preg_replace('/--[^\n]*/', '', $sql_raw);
            foreach (array_filter(array_map('trim', explode(';', $sql_raw))) as $stmt_sql) {
                $db->exec($stmt_sql);
            }

            // Restaurar search_path de la sesión actual
            $sql_facturacion = file_get_contents($facturacion_file);
            $sql_facturacion = preg_replace('/--[^\n]*/', '', $sql_facturacion);
            foreach (array_filter(array_map('trim', explode(';', $sql_facturacion))) as $stmt_sql) {
                $db->exec($stmt_sql);
            }

            $mySchema = preg_replace('/[^a-z0-9_]/', '', strtolower($_SESSION['sucursal_schema'] ?? ''));
            $db->exec($mySchema ? "SET search_path TO \"{$mySchema}\", public" : "SET search_path TO public");

            // ---- INSERT en transacción ----
            $db->beginTransaction();
            $ins = $db->prepare("
                INSERT INTO public.sucursales (tenant_id, nombre, schema_name, direccion, telefono)
                VALUES (:tid, :n, :s, :d, :t) RETURNING id
            ");
            $ins->execute([
                ':tid' => sesionTenantId(),
                ':n'   => trim($d['nombre']),
                ':s'   => $schema,
                ':d'   => trim($d['direccion'] ?? ''),
                ':t'   => trim($d['telefono']  ?? ''),
            ]);
            $nueva_id = $ins->fetch()['id'];
            $db->commit();

            registrarAuditoria('Creación de sucursal', 'admin', "Sucursal: {$d['nombre']} | Schema: {$schema}");

            jsonResponse(['error' => false, 'message' => 'Sucursal creada correctamente', 'id' => $nueva_id, 'schema_name' => $schema]);

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            try { $db->exec("DROP SCHEMA IF EXISTS \"{$schema}\" CASCADE"); } catch (Exception $ignored) {}
            jsonResponse(['error' => true, 'message' => 'Error al crear sucursal: ' . $e->getMessage()], 500);
        }

    case 'sucursal_actualizar':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        if (!$id || empty($d['nombre'])) {
            jsonResponse(['error' => true, 'message' => 'Datos incompletos'], 400);
        }
        $db->prepare("
            UPDATE public.sucursales SET nombre = :n, direccion = :d, telefono = :t
            WHERE id = :id AND tenant_id = :tid
        ")->execute([':n' => trim($d['nombre']), ':d' => trim($d['direccion'] ?? ''), ':t' => trim($d['telefono'] ?? ''), ':id' => $id, ':tid' => sesionTenantId()]);

        registrarAuditoria('Edición de sucursal', 'admin', "Sucursal ID: {$id} | Nuevo nombre: {$d['nombre']}");

        jsonResponse(['error' => false, 'message' => 'Sucursal actualizada']);

    case 'sucursal_toggle_activo':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        // No permitir desactivar la sucursal de la sesión activa
        if ($id === sesionSucursalId()) {
            jsonResponse(['error' => true, 'message' => 'No puedes desactivar tu sucursal activa'], 400);
        }
        $stmt = $db->prepare("UPDATE public.sucursales SET activo = NOT activo WHERE id = :id AND tenant_id = :tid RETURNING activo");
        $stmt->execute([':id' => $id, ':tid' => sesionTenantId()]);
        $suc_row = $stmt->fetch();

        registrarAuditoria('Cambio de estado de sucursal', 'admin', "Sucursal ID: {$id} | Activo: " . ($suc_row['activo'] ? 'si' : 'no'));

        jsonResponse(['error' => false, 'activo' => $suc_row['activo']]);

    // ================================================================
    // MESAS (catalogo de mesas de la sucursal actual, migration_59)
    // getDB() ya aplico SET search_path al schema de la sucursal activa,
    // asi que un SELECT/INSERT/UPDATE sin esquema explicito ya opera
    // sobre las mesas de ESTA sucursal (no de todas).
    // ================================================================

    case 'mesas_listar':
        // Join con la comanda abierta (si la hay) para que el UI pueda
        // mostrar "ocupada" sin pedir otro endpoint y bloquear la
        // desactivacion de mesas en uso.
        // Orden natural: numeros puros primero y ordenados como enteros
        // (asi 2 va antes de 10), luego los alfanumericos (VIP-01, Normal...)
        // ordenados como texto. Sin esto, '10' iba despues de '1' porque
        // numero es VARCHAR y se ordenaba lexicograficamente.
        $stmt = $db->query("
            SELECT m.id, m.numero, m.nombre, m.capacidad, m.activa, m.created_at,
                   c.id AS comanda_abierta_id
            FROM mesas m
            LEFT JOIN comandas c ON c.mesa_id = m.id AND c.estado = 'abierta'
            ORDER BY (m.numero ~ '^[0-9]+$') DESC,
                     CASE WHEN m.numero ~ '^[0-9]+$' THEN m.numero::int ELSE NULL END ASC,
                     m.numero ASC
        ");
        echo json_encode($stmt->fetchAll());
        break;

    case 'mesa_crear':
        $d        = json_decode(file_get_contents('php://input'), true);
        $numero   = trim($d['numero'] ?? '');
        $nombre   = trim($d['nombre'] ?? '');
        $capacidad = $d['capacidad'] ?? null;

        if ($numero === '') {
            jsonResponse(['error' => true, 'message' => 'El numero de mesa es requerido'], 400);
        }
        if (mb_strlen($numero) > 20) {
            jsonResponse(['error' => true, 'message' => 'El numero no puede superar 20 caracteres'], 400);
        }
        if ($capacidad !== null && $capacidad !== '' && (!ctype_digit((string)$capacidad) || (int)$capacidad < 1)) {
            jsonResponse(['error' => true, 'message' => 'La capacidad debe ser un entero positivo'], 400);
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO mesas (numero, nombre, capacidad)
                VALUES (:n, :nom, :cap)
                RETURNING id
            ");
            $stmt->execute([
                ':n'   => $numero,
                ':nom' => $nombre !== '' ? $nombre : null,
                ':cap' => ($capacidad === null || $capacidad === '') ? null : (int)$capacidad,
            ]);
            $nueva_id = $stmt->fetch()['id'];
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') { // unique_violation
                jsonResponse(['error' => true, 'message' => "Ya existe una mesa con el numero '{$numero}'"], 400);
            }
            throw $e;
        }

        registrarAuditoria('Creacion de mesa', 'admin', "Mesa #{$numero}" . ($nombre !== '' ? " ({$nombre})" : ''));
        jsonResponse(['error' => false, 'message' => 'Mesa creada', 'id' => $nueva_id]);

    case 'mesa_actualizar':
        $d         = json_decode(file_get_contents('php://input'), true);
        $id        = intval($d['id'] ?? 0);
        $numero    = trim($d['numero'] ?? '');
        $nombre    = trim($d['nombre'] ?? '');
        $capacidad = $d['capacidad'] ?? null;

        if (!$id || $numero === '') {
            jsonResponse(['error' => true, 'message' => 'Datos incompletos'], 400);
        }
        if (mb_strlen($numero) > 20) {
            jsonResponse(['error' => true, 'message' => 'El numero no puede superar 20 caracteres'], 400);
        }
        if ($capacidad !== null && $capacidad !== '' && (!ctype_digit((string)$capacidad) || (int)$capacidad < 1)) {
            jsonResponse(['error' => true, 'message' => 'La capacidad debe ser un entero positivo'], 400);
        }

        try {
            $db->prepare("
                UPDATE mesas SET numero = :n, nombre = :nom, capacidad = :cap
                WHERE id = :id
            ")->execute([
                ':n'   => $numero,
                ':nom' => $nombre !== '' ? $nombre : null,
                ':cap' => ($capacidad === null || $capacidad === '') ? null : (int)$capacidad,
                ':id'  => $id,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                jsonResponse(['error' => true, 'message' => "Ya existe otra mesa con el numero '{$numero}'"], 400);
            }
            throw $e;
        }

        registrarAuditoria('Edicion de mesa', 'admin', "Mesa ID: {$id} | Nuevo numero: {$numero}");
        jsonResponse(['error' => false, 'message' => 'Mesa actualizada']);

    case 'mesa_toggle_activa':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        if (!$id) {
            jsonResponse(['error' => true, 'message' => 'Mesa no especificada'], 400);
        }
        // No permitir desactivar una mesa con comanda abierta: cobraria
        // raro ver una mesa "inactiva" en Admin y "ocupada" en Ventas al mismo tiempo.
        $check = $db->prepare("SELECT 1 FROM comandas WHERE mesa_id = :id AND estado = 'abierta' LIMIT 1");
        $check->execute([':id' => $id]);
        if ($check->fetch()) {
            jsonResponse(['error' => true, 'message' => 'No se puede desactivar: la mesa tiene una comanda abierta. Cobra o anula primero.'], 400);
        }

        $stmt = $db->prepare("UPDATE mesas SET activa = NOT activa WHERE id = :id RETURNING activa, numero");
        $stmt->execute([':id' => $id]);
        $mesa_row = $stmt->fetch();
        if (!$mesa_row) {
            jsonResponse(['error' => true, 'message' => 'Mesa no encontrada'], 404);
        }

        registrarAuditoria('Cambio de estado de mesa', 'admin', "Mesa #{$mesa_row['numero']} | Activa: " . ($mesa_row['activa'] ? 'si' : 'no'));
        jsonResponse(['error' => false, 'activa' => $mesa_row['activa']]);

    case 'labels_mesa_guardar':
        // Guarda los 4 labels configurables de "Mesa"/"Comanda" para el tenant
        // actual (migration_64). Un tenant restaurante puede dejarlo en
        // "Mesa"/"Comanda"; una peluqueria lo cambia a "Estacion"/"Servicio";
        // un spa a "Cabina"/"Sesion". Afecta solo UI/UX, nada de la DB.
        $d = json_decode(file_get_contents('php://input'), true);
        $vals = [
            'label_mesa_singular'    => trim((string)($d['label_mesa_singular']    ?? '')),
            'label_mesa_plural'      => trim((string)($d['label_mesa_plural']      ?? '')),
            'label_comanda_singular' => trim((string)($d['label_comanda_singular'] ?? '')),
            'label_comanda_plural'   => trim((string)($d['label_comanda_plural']   ?? '')),
        ];

        // Resolucion de vacios:
        //   1. Si singular vacio       -> cae al default ("Mesa" o "Comanda").
        //   2. Si plural vacio PERO singular lleno -> plural = singular + 's'
        //      (ej. usuario escribe "Servicio" y nada mas -> plural = "Servicios").
        //   3. Si plural vacio y singular tambien vacio -> plural cae al default.
        // Esto evita que al escribir solo singular, el plural quede en "Mesas"
        // y la UI muestre media mezcla.
        if ($vals['label_mesa_singular'] === '')    $vals['label_mesa_singular']    = 'Mesa';
        if ($vals['label_comanda_singular'] === '') $vals['label_comanda_singular'] = 'Comanda';
        if ($vals['label_mesa_plural'] === '') {
            $vals['label_mesa_plural'] = $vals['label_mesa_singular'] === 'Mesa'
                ? 'Mesas'
                : $vals['label_mesa_singular'] . 's';
        }
        if ($vals['label_comanda_plural'] === '') {
            $vals['label_comanda_plural'] = $vals['label_comanda_singular'] === 'Comanda'
                ? 'Comandas'
                : $vals['label_comanda_singular'] . 's';
        }
        foreach ($vals as $v) {
            if (mb_strlen($v) > 32) {
                jsonResponse(['error' => true, 'message' => 'Cada label puede tener como maximo 32 caracteres'], 400);
            }
        }

        $tid = sesionTenantId();
        if (!$tid) {
            jsonResponse(['error' => true, 'message' => 'Tenant no resuelto en la sesion'], 400);
        }

        // Upsert: si el tenant no tiene fila en tenant_config, la crea con
        // defaults + los labels; si la tiene, actualiza solo los 4 labels.
        $sql = "
            INSERT INTO public.tenant_config (tenant_id, label_mesa_singular, label_mesa_plural, label_comanda_singular, label_comanda_plural)
            VALUES (:tid, :ms, :mp, :cs, :cp)
            ON CONFLICT (tenant_id) DO UPDATE SET
                label_mesa_singular    = EXCLUDED.label_mesa_singular,
                label_mesa_plural      = EXCLUDED.label_mesa_plural,
                label_comanda_singular = EXCLUDED.label_comanda_singular,
                label_comanda_plural   = EXCLUDED.label_comanda_plural,
                updated_at             = NOW()
        ";
        $db->prepare($sql)->execute([
            ':tid' => $tid,
            ':ms'  => $vals['label_mesa_singular'],
            ':mp'  => $vals['label_mesa_plural'],
            ':cs'  => $vals['label_comanda_singular'],
            ':cp'  => $vals['label_comanda_plural'],
        ]);

        registrarAuditoria('Cambio de labels mesa/comanda', 'admin',
            "Mesa: {$vals['label_mesa_singular']}/{$vals['label_mesa_plural']} | Comanda: {$vals['label_comanda_singular']}/{$vals['label_comanda_plural']}");

        jsonResponse(['error' => false, 'labels' => $vals, 'message' => 'Labels actualizados']);

    case 'labels_mesa_get':
        // Lectura de los labels actuales (para precargar el modal de config).
        $tid = sesionTenantId();
        $stmt = $db->prepare("
            SELECT label_mesa_singular, label_mesa_plural, label_comanda_singular, label_comanda_plural
              FROM public.tenant_config WHERE tenant_id = :tid
        ");
        $stmt->execute([':tid' => $tid]);
        $row = $stmt->fetch();
        jsonResponse(['error' => false, 'labels' => $row ?: [
            'label_mesa_singular'    => 'Mesa',
            'label_mesa_plural'      => 'Mesas',
            'label_comanda_singular' => 'Comanda',
            'label_comanda_plural'   => 'Comandas',
        ]]);

    // ================================================================
    // CONFIGURACIÓN DE MARCA
    // ================================================================

    case 'config_get':
        $stmt = $db->prepare("
            SELECT
                t.nombre,
                t.ruc,
                t.business_name,
                t.trade_name,
                t.email,
                t.telefono,
                t.direccion,
                t.country_code,
                t.ubigeo,
                t.departamento,
                t.provincia,
                t.distrito,
                t.api_url,
                t.whatsapp_instance,
                COALESCE(t.tax_enabled, TRUE) AS tax_enabled,
                COALESCE(t.igv_exonerado, FALSE) AS igv_exonerado,
                t.sunat_username,
                t.sunat_password,
                t.gre_client_id,
                t.gre_client_secret,
                t.certificate_path,
                t.certificate_password,
                t.certificate_expires_at,
                t.sunat_server,
                COALESCE(tc.nombre_sistema, 'Gravity') AS nombre_sistema,
                tc.logo_path
            FROM public.tenants t
            LEFT JOIN public.tenant_config tc ON tc.tenant_id = t.id
            WHERE t.id = :tid
        ");
        $stmt->execute([':tid' => sesionTenantId()]);
        $row = $stmt->fetch() ?: [
            'nombre' => '',
            'ruc' => '',
            'business_name' => '',
            'trade_name' => '',
            'email' => '',
            'telefono' => '',
            'direccion' => '',
            'country_code' => 'PE',
            'ubigeo' => '',
            'departamento' => '',
            'provincia' => '',
            'distrito' => '',
            'api_url' => '',
            'whatsapp_instance' => '',
            'tax_enabled' => true,
            'igv_exonerado' => false,
            'sunat_username' => '',
            'sunat_password' => '',
            'gre_client_id' => '',
            'gre_client_secret' => '',
            'certificate_path' => '',
            'certificate_password' => '',
            'certificate_expires_at' => '',
            'sunat_server' => '3',
            'nombre_sistema' => 'Gravity',
            'logo_path' => null,
        ];
        echo json_encode($row);
        break;

    case 'config_guardar':
        $d = json_decode(file_get_contents('php://input'), true);

        $nombreSistema = trim($d['nombre_sistema'] ?? '');
        $businessName  = trim($d['business_name'] ?? '');
        $tradeName     = trim($d['trade_name'] ?? '');
        $ruc           = trim($d['ruc'] ?? '');
        $email         = trim($d['email'] ?? '');
        $telefono      = trim($d['telefono'] ?? '');
        $direccion     = trim($d['direccion'] ?? '');
        $countryCode   = strtoupper(trim($d['country_code'] ?? 'PE'));
        $ubigeo        = trim($d['ubigeo'] ?? '');
        $departamento  = trim($d['departamento'] ?? '');
        $provincia     = trim($d['provincia'] ?? '');
        $distrito      = trim($d['distrito'] ?? '');
        $apiUrl        = trim($d['api_url'] ?? '');
        $whatsapp      = trim($d['whatsapp_instance'] ?? '');
        $taxEnabled    = !array_key_exists('tax_enabled', $d) || (bool) $d['tax_enabled'];
        $igvExonerado  = array_key_exists('igv_exonerado', $d) && (bool) $d['igv_exonerado'];
        $sunatUsername = trim($d['sunat_username'] ?? '');
        $sunatPassword = trim($d['sunat_password'] ?? '');
        $greClientId   = trim($d['gre_client_id'] ?? '');
        $greSecret     = trim($d['gre_client_secret'] ?? '');
        $certPath      = trim($d['certificate_path'] ?? '');
        $certPassword  = trim($d['certificate_password'] ?? '');
        $certExpiresAt = trim($d['certificate_expires_at'] ?? '');
        $sunatServer   = trim($d['sunat_server'] ?? '3');

        if ($nombreSistema === '') {
            jsonResponse(['error' => true, 'message' => 'El nombre del sistema es requerido'], 400);
        }
        if (mb_strlen($nombreSistema) > 100) {
            jsonResponse(['error' => true, 'message' => 'El nombre no puede superar 100 caracteres'], 400);
        }
        if ($businessName === '') {
            jsonResponse(['error' => true, 'message' => 'La razon social es requerida para facturacion'], 400);
        }
        if ($ruc !== '' && !preg_match('/^\d{11}$/', $ruc)) {
            jsonResponse(['error' => true, 'message' => 'El RUC debe tener 11 digitos'], 400);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => true, 'message' => 'El correo de la empresa no es valido'], 400);
        }
        if ($countryCode === '') {
            $countryCode = 'PE';
        }
        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            jsonResponse(['error' => true, 'message' => 'El codigo de pais debe tener 2 letras'], 400);
        }
        if ($ubigeo !== '' && !preg_match('/^\d{6}$/', $ubigeo)) {
            jsonResponse(['error' => true, 'message' => 'El ubigeo debe tener 6 digitos'], 400);
        }
        if ($apiUrl !== '' && !filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            jsonResponse(['error' => true, 'message' => 'La URL API no es valida'], 400);
        }
        if ($certExpiresAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $certExpiresAt)) {
            jsonResponse(['error' => true, 'message' => 'La fecha de vencimiento del certificado no es valida'], 400);
        }
        if (!in_array($sunatServer, ['1', '3'], true)) {
            jsonResponse(['error' => true, 'message' => 'El entorno SUNAT debe ser 1 o 3'], 400);
        }

        if ($ubigeo !== '') {
            $tablaUbigeo = $db->query("SELECT to_regclass('public.ubigeo_distritos') AS tabla")->fetch();
            if (!empty($tablaUbigeo['tabla'])) {
                $ubigeoStmt = $db->prepare("
                    SELECT
                        d.nombre AS distrito,
                        p.nombre AS provincia,
                        dep.nombre AS departamento
                    FROM public.ubigeo_distritos d
                    INNER JOIN public.ubigeo_provincias p
                        ON p.codigo = d.provincia_codigo
                    INNER JOIN public.ubigeo_departamentos dep
                        ON dep.codigo = d.departamento_codigo
                    WHERE d.codigo = :codigo
                    LIMIT 1
                ");
                $ubigeoStmt->execute([':codigo' => $ubigeo]);
                $ubigeoInfo = $ubigeoStmt->fetch();

                if (!$ubigeoInfo) {
                    jsonResponse(['error' => true, 'message' => 'El ubigeo no existe en el catalogo cargado'], 400);
                }

                $departamento = $ubigeoInfo['departamento'];
                $provincia = $ubigeoInfo['provincia'];
                $distrito = $ubigeoInfo['distrito'];
            }
        }

        $sunatServerAnterior = $db->prepare("SELECT sunat_server FROM public.tenants WHERE id = :tid");
        $sunatServerAnterior->execute([':tid' => sesionTenantId()]);
        $sunatServerAnterior = trim((string) ($sunatServerAnterior->fetch()['sunat_server'] ?? ''));

        $igvExoneradoAnteriorStmt = $db->prepare("SELECT COALESCE(igv_exonerado, FALSE) AS v FROM public.tenants WHERE id = :tid");
        $igvExoneradoAnteriorStmt->execute([':tid' => sesionTenantId()]);
        $igvExoneradoAnterior = (bool) ($igvExoneradoAnteriorStmt->fetch()['v'] ?? false);

        try {
            $db->beginTransaction();

            $db->prepare("
                UPDATE public.tenants
                SET ruc = :ruc,
                    business_name = :business_name,
                    trade_name = :trade_name,
                    email = :email,
                    telefono = :telefono,
                    direccion = :direccion,
                    country_code = :country_code,
                    ubigeo = :ubigeo,
                    departamento = :departamento,
                    provincia = :provincia,
                    distrito = :distrito,
                    api_url = :api_url,
                    whatsapp_instance = :whatsapp_instance,
                    tax_enabled = :tax_enabled,
                    igv_exonerado = :igv_exonerado,
                    sunat_username = :sunat_username,
                    sunat_password = :sunat_password,
                    gre_client_id = :gre_client_id,
                    gre_client_secret = :gre_client_secret,
                    certificate_path = :certificate_path,
                    certificate_password = :certificate_password,
                    certificate_expires_at = :certificate_expires_at,
                    sunat_server = :sunat_server
                WHERE id = :tid
            ")->execute([
                ':tid' => sesionTenantId(),
                ':ruc' => $ruc ?: null,
                ':business_name' => $businessName,
                ':trade_name' => $tradeName ?: null,
                ':email' => $email ?: null,
                ':telefono' => $telefono ?: null,
                ':direccion' => $direccion ?: null,
                ':country_code' => $countryCode,
                ':ubigeo' => $ubigeo ?: null,
                ':departamento' => $departamento ?: null,
                ':provincia' => $provincia ?: null,
                ':distrito' => $distrito ?: null,
                ':api_url' => $apiUrl ?: null,
                ':whatsapp_instance' => $whatsapp ?: null,
                ':tax_enabled' => $taxEnabled ? 'true' : 'false',
                ':igv_exonerado' => $igvExonerado ? 'true' : 'false',
                ':sunat_username' => $sunatUsername ?: null,
                ':sunat_password' => $sunatPassword ?: null,
                ':gre_client_id' => $greClientId ?: null,
                ':gre_client_secret' => $greSecret ?: null,
                ':certificate_path' => $certPath ?: null,
                ':certificate_password' => $certPassword ?: null,
                ':certificate_expires_at' => $certExpiresAt ?: null,
                ':sunat_server' => $sunatServer,
            ]);

            $db->prepare("
                INSERT INTO public.tenant_config (tenant_id, nombre_sistema, updated_at)
                VALUES (:tid, :n, NOW())
                ON CONFLICT (tenant_id) DO UPDATE
                SET nombre_sistema = EXCLUDED.nombre_sistema,
                    updated_at = NOW()
            ")->execute([
                ':tid' => sesionTenantId(),
                ':n' => $nombreSistema,
            ]);

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            jsonResponse(['error' => true, 'message' => 'No se pudo guardar la configuracion: ' . $e->getMessage()], 500);
        }

        $productosExonerados = 0;
        $sucursalesAfectadas = 0;
        if ($igvExonerado && !$igvExoneradoAnterior) {
            $exoIdStmt = $db->query("SELECT id FROM public.fe_tipos_afectacion_igv WHERE codigo = '20' LIMIT 1");
            $exoId = $exoIdStmt->fetch()['id'] ?? null;

            if ($exoId) {
                $sucursalesStmt = $db->prepare("SELECT schema_name FROM public.sucursales WHERE tenant_id = :tid");
                $sucursalesStmt->execute([':tid' => sesionTenantId()]);
                foreach ($sucursalesStmt->fetchAll(PDO::FETCH_COLUMN) as $schemaSucursal) {
                    $schemaSucursal = preg_replace('/[^a-z0-9_]/', '', strtolower($schemaSucursal));
                    if ($schemaSucursal === '') {
                        continue;
                    }
                    try {
                        $updStmt = $db->prepare("
                            UPDATE {$schemaSucursal}.productos
                            SET afectacion_igv_id = :exo_id,
                                afectacion_igv_codigo = '20',
                                updated_at = NOW()
                            WHERE afectacion_igv_codigo IS DISTINCT FROM '20'
                        ");
                        $updStmt->execute([':exo_id' => $exoId]);
                        $productosExonerados += $updStmt->rowCount();
                        $sucursalesAfectadas++;
                    } catch (Exception $e) {
                        // Sucursal sin tabla productos o schema inconsistente: no bloquear el guardado.
                    }
                }
            }
        }

        $detalleConfig = "Razon social: {$businessName}";
        if ($sunatServerAnterior !== $sunatServer) {
            $etiquetaAmbiente = ['1' => 'producción', '3' => 'beta'];
            $detalleConfig .= " | Entorno SUNAT cambiado: " . ($etiquetaAmbiente[$sunatServerAnterior] ?? $sunatServerAnterior) . " -> " . ($etiquetaAmbiente[$sunatServer] ?? $sunatServer);
        }
        if ($igvExonerado !== $igvExoneradoAnterior) {
            $detalleConfig .= $igvExonerado
                ? " | Exoneracion de IGV activada: {$productosExonerados} producto(s) marcados como Exonerado en {$sucursalesAfectadas} sucursal(es)"
                : " | Exoneracion de IGV desactivada (los productos existentes conservan su afectacion actual)";
        }
        registrarAuditoria('Actualización de configuración de empresa', 'admin', $detalleConfig);

        jsonResponse([
            'error' => false,
            'message' => 'Configuracion guardada correctamente',
            'productos_exonerados' => $productosExonerados,
        ]);

    case 'tema_get':
        $stmt = $db->prepare("SELECT COALESCE(tema_oscuro, FALSE) AS tema_oscuro FROM public.tenant_config WHERE tenant_id = :tid");
        $stmt->execute([':tid' => sesionTenantId()]);
        $row = $stmt->fetch() ?: ['tema_oscuro' => false];
        jsonResponse(['error' => false, 'tema_oscuro' => in_array($row['tema_oscuro'], [true, 't', 1, '1'], true)]);

    case 'tema_guardar':
        $d = json_decode(file_get_contents('php://input'), true);
        $temaOscuro = array_key_exists('tema_oscuro', $d) && (bool) $d['tema_oscuro'];

        $db->prepare("
            INSERT INTO public.tenant_config (tenant_id, tema_oscuro, updated_at)
            VALUES (:tid, :tema, NOW())
            ON CONFLICT (tenant_id) DO UPDATE
            SET tema_oscuro = EXCLUDED.tema_oscuro,
                updated_at = NOW()
        ")->execute([
            ':tid' => sesionTenantId(),
            ':tema' => $temaOscuro ? 'true' : 'false',
        ]);

        registrarAuditoria('Cambio de tema del sistema', 'admin', $temaOscuro ? 'Modo oscuro activado (aplica a toda la empresa)' : 'Modo oscuro desactivado');

        jsonResponse(['error' => false, 'message' => 'Tema guardado correctamente']);

    case 'logo_subir':
        if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            $err = $_FILES['logo']['error'] ?? -1;
            $msg = $err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE
                ? 'El archivo supera el tamaño máximo permitido (2 MB)'
                : 'No se recibió ningún archivo';
            jsonResponse(['error' => true, 'message' => $msg], 400);
        }
        $file    = $_FILES['logo'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!in_array($file['type'], $allowed, true)) {
            jsonResponse(['error' => true, 'message' => 'Tipo no permitido. Use JPG, PNG, GIF, WEBP o SVG'], 400);
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            jsonResponse(['error' => true, 'message' => 'El logo no puede superar 2 MB'], 400);
        }
        $ext_map = [
            'image/jpeg'    => 'jpg',
            'image/png'     => 'png',
            'image/gif'     => 'gif',
            'image/webp'    => 'webp',
            'image/svg+xml' => 'svg',
        ];
        $ext = $ext_map[$file['type']] ?? 'png';
        $tid = sesionTenantId();
        $dir = __DIR__ . '/../../assets/img/logos/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        // Eliminar logo anterior
        $old_stmt = $db->prepare("SELECT logo_path FROM public.tenant_config WHERE tenant_id = :tid");
        $old_stmt->execute([':tid' => $tid]);
        $old = $old_stmt->fetch();
        if ($old && $old['logo_path']) {
            $old_file = __DIR__ . '/../../' . $old['logo_path'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
        $filename  = "logo_{$tid}.{$ext}";
        $dest      = $dir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            jsonResponse(['error' => true, 'message' => 'Error al guardar el archivo en el servidor'], 500);
        }
        $logo_path = "assets/img/logos/{$filename}";
        $db->prepare("
            INSERT INTO public.tenant_config (tenant_id, logo_path, updated_at)
            VALUES (:tid, :p, NOW())
            ON CONFLICT (tenant_id) DO UPDATE SET logo_path = EXCLUDED.logo_path, updated_at = NOW()
        ")->execute([':tid' => $tid, ':p' => $logo_path]);
        jsonResponse(['error' => false, 'message' => 'Logo actualizado correctamente', 'logo_path' => $logo_path]);

    case 'certificate_subir':
        if (empty($_FILES['certificate']) || $_FILES['certificate']['error'] !== UPLOAD_ERR_OK) {
            $err = $_FILES['certificate']['error'] ?? -1;
            $msg = $err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE
                ? 'El certificado supera el tamano maximo permitido (5 MB)'
                : 'No se recibio ningun certificado';
            jsonResponse(['error' => true, 'message' => $msg], 400);
        }

        $file = $_FILES['certificate'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext !== 'pfx') {
            jsonResponse(['error' => true, 'message' => 'Solo se permite subir archivos .pfx'], 400);
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            jsonResponse(['error' => true, 'message' => 'El certificado no puede superar 5 MB'], 400);
        }

        $tid = sesionTenantId();
        $stmtTenant = $db->prepare("SELECT slug, ruc FROM public.tenants WHERE id = :tid LIMIT 1");
        $stmtTenant->execute([':tid' => $tid]);
        $tenant = $stmtTenant->fetch();
        if (!$tenant) {
            jsonResponse(['error' => true, 'message' => 'No se encontro la empresa activa'], 404);
        }

        $dir = __DIR__ . '/../../facturacion/certs/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $baseName = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($tenant['ruc'] ?: $tenant['slug'] ?: ('tenant_' . $tid)));
        if ($baseName === '') {
            $baseName = 'tenant_' . $tid;
        }

        $certificatePath = 'facturacion/certs/' . $baseName . '.pfx';
        $destAbsolute = __DIR__ . '/../../' . $certificatePath;

        $old_stmt = $db->prepare("SELECT certificate_path FROM public.tenants WHERE id = :tid");
        $old_stmt->execute([':tid' => $tid]);
        $old = $old_stmt->fetch();
        if ($old && !empty($old['certificate_path'])) {
            $oldFile = __DIR__ . '/../../' . ltrim($old['certificate_path'], '/\\');
            if (file_exists($oldFile) && realpath($oldFile) !== realpath($destAbsolute)) {
                unlink($oldFile);
            }
        }

        if (!move_uploaded_file($file['tmp_name'], $destAbsolute)) {
            jsonResponse(['error' => true, 'message' => 'No se pudo guardar el certificado en el servidor'], 500);
        }

        $db->prepare("
            UPDATE public.tenants
            SET certificate_path = :path
            WHERE id = :tid
        ")->execute([
            ':tid' => $tid,
            ':path' => $certificatePath,
        ]);

        jsonResponse([
            'error' => false,
            'message' => 'Certificado actualizado correctamente',
            'certificate_path' => $certificatePath,
        ]);

    case 'logo_eliminar':
        $tid      = sesionTenantId();
        $old_stmt = $db->prepare("SELECT logo_path FROM public.tenant_config WHERE tenant_id = :tid");
        $old_stmt->execute([':tid' => $tid]);
        $old = $old_stmt->fetch();
        if ($old && $old['logo_path']) {
            $old_file = __DIR__ . '/../../' . $old['logo_path'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
        $db->prepare("
            UPDATE public.tenant_config SET logo_path = NULL, updated_at = NOW()
            WHERE tenant_id = :tid
        ")->execute([':tid' => $tid]);
        jsonResponse(['error' => false, 'message' => 'Logo eliminado']);

    case 'auditoria_listar':
        $desde  = $_GET['desde'] ?? date('Y-m-d', strtotime('-7 days'));
        $hasta  = $_GET['hasta'] ?? date('Y-m-d');
        $q      = trim($_GET['q']      ?? '');
        $accion = trim($_GET['accion'] ?? '');
        $tid    = sesionTenantId();

        $where  = [
            'al.tenant_id = :tid',
            "al.created_at >= :desde::date",
            "al.created_at <  (:hasta::date + INTERVAL '1 day')",
        ];
        $params = [':tid' => $tid, ':desde' => $desde, ':hasta' => $hasta];

        if ($q !== '') {
            $where[] = "(al.username ILIKE :q OR al.nombre_usuario ILIKE :q OR al.detalle ILIKE :q)";
            $params[':q'] = "%{$q}%";
        }
        if ($accion !== '') {
            $where[] = 'al.accion = :accion';
            $params[':accion'] = $accion;
        }

        $stmt = $db->prepare("
            SELECT al.id, al.usuario_id, al.username, al.nombre_usuario, al.rol,
                   al.accion, al.modulo, al.detalle, al.ip_address,
                   al.created_at AT TIME ZONE 'America/Lima' AS created_at
            FROM public.audit_log al
            WHERE " . implode(' AND ', $where) . "
            ORDER BY al.created_at DESC
            LIMIT 500
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        break;

    default:
        jsonResponse(['error' => true, 'message' => 'Acción no válida'], 404);
}
