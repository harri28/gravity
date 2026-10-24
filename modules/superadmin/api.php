<?php
// ============================================================
// ARCHIVO: farmacia/modules/superadmin/api.php
// DESCRIPCIÓN: API exclusiva del superadmin — gestión de tenants
// ============================================================

header('Content-Type: application/json; charset=UTF-8');
require_once '../../config/database.php';
requireApiAuth(['superadmin']);

$action = $_GET['action'] ?? '';
$db     = getDB();

switch ($action) {

    // ---- GET: Listar todos los tenants con métricas ----
    case 'tenants_listar':
        $stmt = $db->query("
            SELECT t.id, t.nombre, t.slug, t.plan, t.url,
                   t.ruc, t.telefono, t.direccion, t.logo,
                   t.activo, t.created_at,
                   COUNT(DISTINCT s.id)  AS total_sucursales,
                   COUNT(DISTINCT u.id)  AS total_usuarios
            FROM public.tenants t
            LEFT JOIN public.sucursales s ON s.tenant_id = t.id AND s.activo = TRUE
            LEFT JOIN public.usuarios   u ON u.tenant_id = t.id AND u.activo = TRUE
            GROUP BY t.id
            ORDER BY t.created_at DESC, t.id DESC
        ");
        echo json_encode($stmt->fetchAll());
        break;

    // ---- GET: Stats globales ----
    case 'stats':
        $row = $db->query("
            SELECT
                (SELECT COUNT(*) FROM public.tenants WHERE activo = TRUE)    AS tenants_activos,
                (SELECT COUNT(*) FROM public.tenants)                        AS tenants_total,
                (SELECT COUNT(*) FROM public.sucursales WHERE activo = TRUE) AS sucursales_activas,
                (SELECT COUNT(*) FROM public.usuarios WHERE activo = TRUE
                   AND tenant_id IS NOT NULL)                                AS usuarios_activos
        ")->fetch();
        echo json_encode($row);
        break;

    // ---- POST: Crear tenant ----
    case 'tenant_crear':
        $d = json_decode(file_get_contents('php://input'), true);
        if (empty($d['nombre'])) {
            jsonResponse(['error' => true, 'message' => 'El nombre es requerido'], 400);
        }

        // Generar slug desde el nombre
        $slug = strtolower(trim($d['nombre']));
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $slug) ?: $slug;
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');
        $slug = substr($slug, 0, 25) ?: 'tenant';

        // Verificar unicidad
        $ck = $db->prepare("SELECT id FROM public.tenants WHERE slug = :s");
        $ck->execute([':s' => $slug]);
        if ($ck->fetch()) {
            $slug .= '_' . (time() % 1000);
        }

        $stmt = $db->prepare("
            INSERT INTO public.tenants (nombre, slug, plan, url, ruc, telefono, direccion)
            VALUES (:n, :s, :p, :url, :ruc, :tel, :dir)
            RETURNING id, slug
        ");
        $stmt->execute([
            ':n'   => trim($d['nombre']),
            ':s'   => $slug,
            ':p'   => $d['plan'] ?? 'basico',
            ':url' => trim($d['url']       ?? ''),
            ':ruc' => trim($d['ruc']       ?? ''),
            ':tel' => trim($d['telefono']  ?? ''),
            ':dir' => trim($d['direccion'] ?? ''),
        ]);
        $row = $stmt->fetch();
        jsonResponse(['error' => false, 'message' => 'Empresa creada', 'id' => $row['id'], 'slug' => $row['slug']]);

    // ---- POST: Actualizar tenant ----
    case 'tenant_actualizar':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        if (!$id || empty($d['nombre'])) {
            jsonResponse(['error' => true, 'message' => 'Datos incompletos'], 400);
        }
        // Este endpoint lo llaman 3 pantallas con campos distintos (lista de
        // empresas, pantalla Empresa y "subir de plan"): solo se actualizan los
        // campos que vienen en la peticion. Antes los ausentes se guardaban
        // como vacio y se borraban RUC, telefono, direccion y url.
        $sets   = ['nombre = :n', 'plan = :p'];
        $params = [':n' => trim($d['nombre']), ':p' => $d['plan'] ?? 'basico', ':id' => $id];
        foreach (['url', 'ruc', 'telefono', 'direccion'] as $campo) {
            if (array_key_exists($campo, $d)) {
                $sets[] = "$campo = :$campo";
                $params[":$campo"] = trim((string) $d[$campo]);
            }
        }
        if (array_key_exists('plan_vence_at', $d)) {
            $sets[] = 'plan_vence_at = :pv';
            $params[':pv'] = !empty($d['plan_vence_at']) ? trim($d['plan_vence_at']) : null;
        }
        if (array_key_exists('plan_banner_activo', $d)) {
            $sets[] = 'plan_banner_activo = :pb';
            $params[':pb'] = !empty($d['plan_banner_activo']) ? 'true' : 'false';
        }
        $db->prepare('UPDATE public.tenants SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        jsonResponse(['error' => false, 'message' => 'Empresa actualizada']);

    // ---- POST: Marcar el plan como pagado (registra el pago y corre el vencimiento) ----
    case 'tenant_marcar_pagado':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['tenant_id'] ?? 0);
        $nuevo = trim((string) ($d['vence_nuevo'] ?? ''));
        if (!$id) jsonResponse(['error' => true, 'message' => 'Empresa inválida'], 400);
        $dt = DateTime::createFromFormat('Y-m-d', $nuevo);
        if (!$dt || $dt->format('Y-m-d') !== $nuevo) {
            jsonResponse(['error' => true, 'message' => 'Fecha de vencimiento no válida'], 400);
        }
        if ($nuevo <= date('Y-m-d')) {
            jsonResponse(['error' => true, 'message' => 'La nueva fecha de vencimiento debe ser posterior a hoy'], 400);
        }
        $monto = null;
        if (isset($d['monto']) && $d['monto'] !== '' && $d['monto'] !== null) {
            if (!is_numeric($d['monto']) || floatval($d['monto']) < 0) {
                jsonResponse(['error' => true, 'message' => 'Monto no válido'], 400);
            }
            $monto = round(floatval($d['monto']), 2);
        }

        $db->beginTransaction();
        try {
            $s = $db->prepare("SELECT plan_vence_at FROM public.tenants WHERE id = :id FOR UPDATE");
            $s->execute([':id' => $id]);
            $t = $s->fetch();
            if (!$t) {
                $db->rollBack();
                jsonResponse(['error' => true, 'message' => 'Empresa no encontrada'], 404);
            }
            $db->prepare("
                INSERT INTO public.tenant_pagos (tenant_id, monto, vence_anterior, vence_nuevo, registrado_por)
                VALUES (:tid, :monto, :ant, :nuevo, :por)
            ")->execute([
                ':tid'   => $id,
                ':monto' => $monto,
                ':ant'   => $t['plan_vence_at'] ?: null,
                ':nuevo' => $nuevo,
                ':por'   => sesionUsername() ?: sesionNombre(),
            ]);
            $db->prepare("UPDATE public.tenants SET plan_vence_at = :nuevo WHERE id = :id")
               ->execute([':nuevo' => $nuevo, ':id' => $id]);
            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonResponse(['error' => true, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
        jsonResponse(['error' => false, 'message' => 'Pago registrado', 'vence_nuevo' => $nuevo]);

    // ---- GET: Historial de pagos del plan ----
    case 'tenant_pagos_listar':
        $id = intval($_GET['tenant_id'] ?? 0);
        if (!$id) jsonResponse(['error' => true, 'message' => 'Empresa inválida'], 400);
        $s = $db->prepare("
            SELECT id, fecha_pago, monto, vence_anterior, vence_nuevo, registrado_por, created_at
            FROM public.tenant_pagos
            WHERE tenant_id = :id
            ORDER BY created_at DESC, id DESC
            LIMIT 50
        ");
        $s->execute([':id' => $id]);
        echo json_encode($s->fetchAll());
        break;

    // ---- GET: Datos SUNAT (solo lectura) + notas internas de superadmin ----
    case 'tenant_sunat_info':
        $id = intval($_GET['tenant_id'] ?? 0);
        if (!$id) jsonResponse(['error' => true, 'message' => 'Empresa inválida'], 400);

        $stmt = $db->prepare("
            SELECT ruc, sunat_username, sunat_password, notas_superadmin
            FROM public.tenants
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(['error' => true, 'message' => 'Empresa no encontrada'], 404);

        echo json_encode(['error' => false] + $row);
        break;

    // ---- POST: Guardar notas internas de superadmin (no toca RUC/SOL) ----
    case 'tenant_notas_guardar':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['tenant_id'] ?? 0);
        if (!$id) jsonResponse(['error' => true, 'message' => 'Empresa inválida'], 400);

        $db->prepare("UPDATE public.tenants SET notas_superadmin = :n WHERE id = :id")
           ->execute([':n' => trim($d['notas'] ?? '') ?: null, ':id' => $id]);
        jsonResponse(['error' => false, 'message' => 'Notas guardadas']);

    // ---- POST: Guardar RUC/Usuario SOL/Clave SOL + notas desde Superadmin ----
    case 'tenant_sunat_guardar':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['tenant_id'] ?? 0);
        if (!$id) jsonResponse(['error' => true, 'message' => 'Empresa inválida'], 400);

        $db->prepare("
            UPDATE public.tenants
            SET ruc = :ruc, sunat_username = :usr, sunat_password = :pass, notas_superadmin = :n
            WHERE id = :id
        ")->execute([
            ':ruc'  => trim($d['ruc'] ?? '') ?: null,
            ':usr'  => trim($d['sunat_username'] ?? '') ?: null,
            ':pass' => trim($d['sunat_password'] ?? '') ?: null,
            ':n'    => trim($d['notas'] ?? '') ?: null,
            ':id'   => $id,
        ]);
        jsonResponse(['error' => false, 'message' => 'Datos SUNAT guardados']);

    // ---- POST: Toggle activo ----
    case 'tenant_toggle_activo':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        $stmt = $db->prepare("UPDATE public.tenants SET activo = NOT activo WHERE id = :id RETURNING activo");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        jsonResponse(['error' => false, 'activo' => $row['activo']]);

    // ---- POST: Crear primer admin de un tenant ----
    case 'crear_admin_tenant':
        $d = json_decode(file_get_contents('php://input'), true);
        if (empty($d['tenant_id']) || empty($d['nombre']) || empty($d['username']) || empty($d['password'])) {
            jsonResponse(['error' => true, 'message' => 'Faltan datos requeridos'], 400);
        }
        if (strlen($d['password']) < 4) {
            jsonResponse(['error' => true, 'message' => 'La contraseña debe tener mínimo 4 caracteres'], 400);
        }
        // Verificar que el username no exista
        $ck = $db->prepare("SELECT id FROM public.usuarios WHERE username = :u");
        $ck->execute([':u' => trim($d['username'])]);
        if ($ck->fetch()) {
            jsonResponse(['error' => true, 'message' => 'El nombre de usuario ya existe'], 409);
        }
        // Crear usuario
        $ins = $db->prepare("
            INSERT INTO public.usuarios (tenant_id, nombre, apellido, username, password_hash, email, observaciones)
            VALUES (:tid, :n, :a, :u, :h, :e, :o) RETURNING id
        ");
        $ins->execute([
            ':tid' => intval($d['tenant_id']),
            ':n'   => trim($d['nombre']),
            ':a'   => trim($d['apellido'] ?? ''),
            ':u'   => trim($d['username']),
            ':h'   => password_hash($d['password'], PASSWORD_BCRYPT),
            ':e'   => trim($d['email'] ?? '') ?: null,
            ':o'   => trim($d['observaciones'] ?? '') ?: null,
        ]);
        $uid = $ins->fetchColumn();

        // Si se especifica una sucursal, asignar con el rol indicado
        if (!empty($d['sucursal_id'])) {
            $rol = in_array($d['rol'] ?? '', ['admin', 'cajero']) ? $d['rol'] : 'cajero';
            $db->prepare("
                INSERT INTO public.usuario_sucursal (usuario_id, sucursal_id, rol)
                VALUES (:uid, :sid, :rol)
                ON CONFLICT (usuario_id, sucursal_id) DO UPDATE SET rol = :rol, activo = TRUE
            ")->execute([':uid' => $uid, ':sid' => intval($d['sucursal_id']), ':rol' => $rol]);
        }

        jsonResponse(['error' => false, 'message' => 'Administrador creado', 'id' => $uid]);

    // ---- GET: Listar usuarios de un tenant ----
    case 'usuarios_listar':
        $tenant_id = intval($_GET['tenant_id'] ?? 0);
        if (!$tenant_id) jsonResponse(['error' => true, 'message' => 'tenant_id requerido'], 400);
        $stmt = $db->prepare("
            SELECT u.id, u.nombre, u.apellido, u.username, u.activo, u.created_at, u.observaciones,
                   COALESCE(
                       json_agg(
                           json_build_object('sucursal_id', s.id, 'sucursal', s.nombre, 'rol', us.rol)
                           ORDER BY s.nombre
                       ) FILTER (WHERE s.id IS NOT NULL AND us.activo = TRUE),
                       '[]'
                   ) AS accesos
            FROM public.usuarios u
            LEFT JOIN public.usuario_sucursal us ON us.usuario_id = u.id
            LEFT JOIN public.sucursales s        ON s.id = us.sucursal_id
            WHERE u.tenant_id = :tid
            GROUP BY u.id
            ORDER BY u.nombre ASC
        ");
        $stmt->execute([':tid' => $tenant_id]);
        echo json_encode($stmt->fetchAll());
        break;

    // ---- POST: Actualizar usuario ----
    case 'usuario_actualizar':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        if (!$id || empty($d['nombre']) || empty($d['username'])) {
            jsonResponse(['error' => true, 'message' => 'Datos incompletos'], 400);
        }
        $ck = $db->prepare("SELECT id FROM public.usuarios WHERE username = :u AND id <> :id");
        $ck->execute([':u' => trim($d['username']), ':id' => $id]);
        if ($ck->fetch()) jsonResponse(['error' => true, 'message' => 'El usuario ya existe'], 409);

        $db->prepare("UPDATE public.usuarios SET nombre = :n, apellido = :a, username = :u, email = :e, observaciones = :o WHERE id = :id")
           ->execute([':n' => trim($d['nombre']), ':a' => trim($d['apellido'] ?? ''), ':u' => trim($d['username']), ':e' => trim($d['email'] ?? '') ?: null, ':o' => trim($d['observaciones'] ?? '') ?: null, ':id' => $id]);
        jsonResponse(['error' => false, 'message' => 'Usuario actualizado']);

    // ---- POST: Asignar / actualizar sucursal de un usuario ----
    case 'usuario_asignar_sucursal':
        $d  = json_decode(file_get_contents('php://input'), true);
        $uid = intval($d['usuario_id'] ?? 0);
        $sid = intval($d['sucursal_id'] ?? 0);
        $rol = in_array($d['rol'] ?? '', ['admin', 'cajero']) ? $d['rol'] : 'cajero';
        if (!$uid) jsonResponse(['error' => true, 'message' => 'usuario_id requerido'], 400);

        if ($sid) {
            $db->prepare("
                INSERT INTO public.usuario_sucursal (usuario_id, sucursal_id, rol)
                VALUES (:uid, :sid, :rol)
                ON CONFLICT (usuario_id, sucursal_id) DO UPDATE SET rol = :rol, activo = TRUE
            ")->execute([':uid' => $uid, ':sid' => $sid, ':rol' => $rol]);
        } else {
            // Sin asignar: desactivar todas las asignaciones del usuario en este tenant
            $db->prepare("
                UPDATE public.usuario_sucursal us
                SET activo = FALSE
                FROM public.sucursales s
                WHERE us.sucursal_id = s.id
                  AND us.usuario_id  = :uid
                  AND s.tenant_id    = (SELECT tenant_id FROM public.usuarios WHERE id = :uid2)
            ")->execute([':uid' => $uid, ':uid2' => $uid]);
        }
        jsonResponse(['error' => false, 'message' => 'Sucursal actualizada']);

    // ---- POST: Toggle activo usuario ----
    case 'usuario_toggle_activo':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        $stmt = $db->prepare("UPDATE public.usuarios SET activo = NOT activo WHERE id = :id RETURNING activo");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        jsonResponse(['error' => false, 'activo' => $row['activo']]);

    // ---- POST: Cambiar contraseña de usuario ----
    case 'usuario_cambiar_password':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        if (!$id || empty($d['password']) || strlen($d['password']) < 4) {
            jsonResponse(['error' => true, 'message' => 'Contraseña mínimo 4 caracteres'], 400);
        }
        $db->prepare("UPDATE public.usuarios SET password_hash = :h WHERE id = :id")
           ->execute([':h' => password_hash($d['password'], PASSWORD_BCRYPT), ':id' => $id]);
        jsonResponse(['error' => false, 'message' => 'Contraseña actualizada']);

    // ---- POST: Actualizar sucursal ----
    case 'sucursal_actualizar':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        if (!$id || empty($d['nombre'])) jsonResponse(['error' => true, 'message' => 'Datos incompletos'], 400);
        $db->prepare("UPDATE public.sucursales SET nombre = :n, direccion = :d, telefono = :t WHERE id = :id")
           ->execute([':n' => trim($d['nombre']), ':d' => trim($d['direccion'] ?? ''), ':t' => trim($d['telefono'] ?? ''), ':id' => $id]);
        jsonResponse(['error' => false, 'message' => 'Sucursal actualizada']);

    // ---- GET: Listar sucursales de un tenant ----
    case 'sucursales_listar':
        $tenant_id = intval($_GET['tenant_id'] ?? 0);
        if (!$tenant_id) jsonResponse(['error' => true, 'message' => 'tenant_id requerido'], 400);
        $stmt = $db->prepare("
            SELECT s.id, s.nombre, s.schema_name, s.direccion, s.telefono, s.activo, s.archivada, s.created_at,
                   COUNT(us.id) AS total_usuarios
            FROM public.sucursales s
            LEFT JOIN public.usuario_sucursal us ON us.sucursal_id = s.id AND us.activo = TRUE
            WHERE s.tenant_id = :tid
            GROUP BY s.id
            ORDER BY s.archivada ASC, s.nombre ASC
        ");
        $stmt->execute([':tid' => $tenant_id]);
        echo json_encode($stmt->fetchAll());
        break;

    // ---- POST: Crear sucursal para un tenant ----
    case 'sucursal_crear':
        $d = json_decode(file_get_contents('php://input'), true);
        $tenant_id = intval($d['tenant_id'] ?? 0);
        if (!$tenant_id || empty($d['nombre'])) {
            jsonResponse(['error' => true, 'message' => 'tenant_id y nombre son requeridos'], 400);
        }

        // Límites de sucursales por plan
        $limites_plan = ['basico' => 1, 'pro' => 3, 'enterprise' => PHP_INT_MAX];

        // Obtener slug y plan del tenant
        $t = $db->prepare("SELECT slug, plan FROM public.tenants WHERE id = :id AND activo = TRUE");
        $t->execute([':id' => $tenant_id]);
        $tenant = $t->fetch();
        if (!$tenant) jsonResponse(['error' => true, 'message' => 'Empresa no encontrada'], 404);

        // Verificar límite del plan
        $plan_actual  = $tenant['plan'];
        $limite       = $limites_plan[$plan_actual] ?? 1;
        $count_stmt   = $db->prepare("SELECT COUNT(*) FROM public.sucursales WHERE tenant_id = :tid AND archivada = FALSE");
        $count_stmt->execute([':tid' => $tenant_id]);
        $total_actual = (int) $count_stmt->fetchColumn();

        if ($total_actual >= $limite) {
            $planes_orden  = ['basico', 'pro', 'enterprise'];
            $idx           = array_search($plan_actual, $planes_orden);
            $plan_siguiente = $planes_orden[$idx + 1] ?? null;
            jsonResponse([
                'error'          => true,
                'upgrade'        => true,
                'plan_actual'    => $plan_actual,
                'plan_siguiente' => $plan_siguiente,
                'limite'         => $limite,
                'total_actual'   => $total_actual,
                'message'        => "Has alcanzado el límite de {$limite} sucursal(es) del plan " . ucfirst($plan_actual) . '.',
            ], 422);
        }

        $tenant_slug = $tenant['slug'];
        $suc_slug    = strtolower(trim($d['nombre']));
        $suc_slug    = iconv('UTF-8', 'ASCII//TRANSLIT', $suc_slug) ?: $suc_slug;
        $suc_slug    = preg_replace('/[^a-z0-9]+/', '_', $suc_slug);
        $suc_slug    = trim($suc_slug, '_');
        $suc_slug    = substr($suc_slug, 0, 20) ?: 'suc';
        $schema      = substr($tenant_slug . '_' . $suc_slug, 0, 55);

        // Verificar unicidad
        $ck = $db->prepare("SELECT id FROM public.sucursales WHERE schema_name = :s");
        $ck->execute([':s' => $schema]);
        if ($ck->fetch()) $schema .= '_' . (time() % 1000);

        $sql_file = __DIR__ . '/../../database/schema_sucursal.sql';
        if (!file_exists($sql_file)) {
            jsonResponse(['error' => true, 'message' => 'No se encontró schema_sucursal.sql'], 500);
        }

        try {
            // ---- DDL fuera de transacción (CREATE SCHEMA + tablas) ----
            $db->exec("CREATE SCHEMA IF NOT EXISTS \"{$schema}\"");
            $db->exec("SET search_path TO \"{$schema}\", public");

            // Ejecutar el archivo completo en un solo exec: PDO_PGSQL soporta
            // multi-statement nativo. Partir por ';' rompe los bloques
            // DO $$ ... $$; (tienen ';' internos) y provoca "unterminated
            // dollar-quoted string".
            $sql_raw = file_get_contents($sql_file);
            $db->exec($sql_raw);
            $db->exec("SET search_path TO public");

            // ---- Registrar en public.sucursales (transacción solo para el INSERT) ----
            $db->beginTransaction();
            $ins = $db->prepare("
                INSERT INTO public.sucursales (tenant_id, nombre, schema_name, direccion, telefono)
                VALUES (:tid, :n, :s, :d, :t) RETURNING id
            ");
            $ins->execute([
                ':tid' => $tenant_id,
                ':n'   => trim($d['nombre']),
                ':s'   => $schema,
                ':d'   => trim($d['direccion'] ?? ''),
                ':t'   => trim($d['telefono']  ?? ''),
            ]);
            $nueva_id = $ins->fetch()['id'];
            $db->commit();

            jsonResponse(['error' => false, 'message' => 'Sucursal creada', 'id' => $nueva_id, 'schema_name' => $schema]);

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            // Limpiar schema si fue creado pero el INSERT falló
            try { $db->exec("DROP SCHEMA IF EXISTS \"{$schema}\" CASCADE"); } catch (Exception $ignored) {}
            jsonResponse(['error' => true, 'message' => 'Error al crear sucursal: ' . $e->getMessage()], 500);
        }

    // ---- POST: Toggle activo sucursal ----
    case 'sucursal_toggle_activo':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        $stmt = $db->prepare("UPDATE public.sucursales SET activo = NOT activo WHERE id = :id RETURNING activo");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        jsonResponse(['error' => false, 'activo' => $row['activo']]);

    // ---- POST: Archivar sucursal + auto-downgrade de plan ----
    case 'sucursal_archivar':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        if (!$id) jsonResponse(['error' => true, 'message' => 'id requerido'], 400);

        $limites_plan = ['basico' => 1, 'pro' => 3, 'enterprise' => PHP_INT_MAX];

        // Archivar y desactivar
        $db->prepare("UPDATE public.sucursales SET archivada = TRUE, activo = FALSE WHERE id = :id")
           ->execute([':id' => $id]);

        // Obtener tenant del plan actual
        $tRow = $db->prepare("
            SELECT t.id, t.plan,
                   COUNT(s.id) AS activas
            FROM public.tenants t
            JOIN public.sucursales s ON s.tenant_id = t.id AND s.archivada = FALSE
            WHERE s.id = :id OR t.id = (SELECT tenant_id FROM public.sucursales WHERE id = :id2)
            GROUP BY t.id
        ");
        $tRow->execute([':id' => $id, ':id2' => $id]);
        // Consulta más simple y directa
        $tData = $db->prepare("
            SELECT t.id AS tenant_id, t.plan,
                   (SELECT COUNT(*) FROM public.sucursales WHERE tenant_id = t.id AND archivada = FALSE) AS activas
            FROM public.tenants t
            JOIN public.sucursales s ON s.tenant_id = t.id
            WHERE s.id = :id
        ");
        $tData->execute([':id' => $id]);
        $tenant = $tData->fetch();

        $plan_nuevo = null;
        if ($tenant) {
            $activas = (int) $tenant['activas'];
            $plan    = $tenant['plan'];
            // Auto-downgrade: si ya no necesita enterprise → pro; si solo 1 → basico
            if ($plan === 'enterprise' && $activas <= $limites_plan['pro']) {
                $plan_nuevo = $activas <= $limites_plan['basico'] ? 'basico' : 'pro';
                $db->prepare("UPDATE public.tenants SET plan = :p WHERE id = :id")
                   ->execute([':p' => $plan_nuevo, ':id' => $tenant['tenant_id']]);
            }
        }

        jsonResponse(['error' => false, 'message' => 'Sucursal archivada', 'plan_nuevo' => $plan_nuevo]);

    // ---- POST: Restaurar sucursal archivada + auto-upgrade de plan ----
    case 'sucursal_restaurar':
        $d  = json_decode(file_get_contents('php://input'), true);
        $id = intval($d['id'] ?? 0);
        if (!$id) jsonResponse(['error' => true, 'message' => 'id requerido'], 400);

        $limites_plan = ['basico' => 1, 'pro' => 3, 'enterprise' => PHP_INT_MAX];

        // Contar activas actuales (sin contar esta)
        $tData = $db->prepare("
            SELECT t.id AS tenant_id, t.plan,
                   (SELECT COUNT(*) FROM public.sucursales WHERE tenant_id = t.id AND archivada = FALSE) AS activas
            FROM public.tenants t
            JOIN public.sucursales s ON s.tenant_id = t.id
            WHERE s.id = :id
        ");
        $tData->execute([':id' => $id]);
        $tenant = $tData->fetch();
        if (!$tenant) jsonResponse(['error' => true, 'message' => 'Sucursal no encontrada'], 404);

        $activas_tras_restaurar = (int) $tenant['activas'] + 1;
        $plan_actual = $tenant['plan'];
        $limite_actual = $limites_plan[$plan_actual] ?? 1;

        if ($activas_tras_restaurar > $limite_actual) {
            // Necesita upgrade
            $planes_orden = ['basico', 'pro', 'enterprise'];
            $plan_nuevo = 'enterprise';
            foreach ($planes_orden as $p) {
                if (($limites_plan[$p] ?? 0) >= $activas_tras_restaurar) { $plan_nuevo = $p; break; }
            }
            $db->prepare("UPDATE public.tenants SET plan = :p WHERE id = :id")
               ->execute([':p' => $plan_nuevo, ':id' => $tenant['tenant_id']]);
        } else {
            $plan_nuevo = $plan_actual;
        }

        // Restaurar
        $db->prepare("UPDATE public.sucursales SET archivada = FALSE, activo = TRUE WHERE id = :id")
           ->execute([':id' => $id]);

        jsonResponse(['error' => false, 'message' => 'Sucursal restaurada', 'plan_nuevo' => $plan_nuevo]);

    // ---- GET: Datos del perfil propio (superadmin) ----
    case 'perfil':
        $stmt = $db->prepare("SELECT nombre, apellido, username, email FROM public.superadmins WHERE id = :id");
        $stmt->execute([':id' => sesionId()]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(['error' => true, 'message' => 'Usuario no encontrado'], 404);
        echo json_encode(['error' => false] + $row);
        break;

    // ---- POST: Actualizar datos personales propios (superadmin) ----
    case 'perfil_actualizar':
        $d = json_decode(file_get_contents('php://input'), true);
        if (empty($d['nombre']) || empty($d['username'])) {
            jsonResponse(['error' => true, 'message' => 'Nombre y usuario son requeridos'], 400);
        }
        $ck = $db->prepare("SELECT id FROM public.superadmins WHERE username = :u AND id <> :id");
        $ck->execute([':u' => trim($d['username']), ':id' => sesionId()]);
        if ($ck->fetch()) jsonResponse(['error' => true, 'message' => 'Ese nombre de usuario ya está en uso'], 409);

        $nombre   = trim($d['nombre']);
        $apellido = trim($d['apellido'] ?? '');
        $db->prepare("
            UPDATE public.superadmins
            SET nombre = :n, apellido = :a, username = :u, email = :e
            WHERE id = :id
        ")->execute([
            ':n'  => $nombre,
            ':a'  => $apellido,
            ':u'  => trim($d['username']),
            ':e'  => trim($d['email'] ?? '') ?: null,
            ':id' => sesionId(),
        ]);

        $_SESSION['nombre']   = trim($nombre . ' ' . $apellido);
        $_SESSION['username'] = trim($d['username']);

        jsonResponse(['error' => false, 'message' => 'Datos actualizados', 'nombre_completo' => $_SESSION['nombre']]);

    // ---- POST: Cambiar contraseña propia (superadmin) ----
    case 'perfil_cambiar_password':
        $d = json_decode(file_get_contents('php://input'), true);
        if (empty($d['password_actual']) || empty($d['password_nueva'])) {
            jsonResponse(['error' => true, 'message' => 'Datos incompletos'], 400);
        }
        if (strlen($d['password_nueva']) < 4) {
            jsonResponse(['error' => true, 'message' => 'La nueva contraseña debe tener mínimo 4 caracteres'], 400);
        }
        $stmt = $db->prepare("SELECT password_hash FROM public.superadmins WHERE id = :id");
        $stmt->execute([':id' => sesionId()]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($d['password_actual'], $row['password_hash'])) {
            jsonResponse(['error' => true, 'message' => 'La contraseña actual es incorrecta'], 401);
        }
        $db->prepare("UPDATE public.superadmins SET password_hash = :h WHERE id = :id")
           ->execute([':h' => password_hash($d['password_nueva'], PASSWORD_BCRYPT), ':id' => sesionId()]);
        jsonResponse(['error' => false, 'message' => 'Contraseña actualizada']);

    // ---- GET: Preferencia de tema propia (superadmin) ----
    case 'tema_get':
        $stmt = $db->prepare("SELECT COALESCE(tema_oscuro, FALSE) AS tema_oscuro FROM public.superadmins WHERE id = :id");
        $stmt->execute([':id' => sesionId()]);
        $row = $stmt->fetch() ?: ['tema_oscuro' => false];
        jsonResponse(['error' => false, 'tema_oscuro' => in_array($row['tema_oscuro'], [true, 't', 1, '1'], true)]);

    // ---- POST: Guardar preferencia de tema propia (superadmin) ----
    case 'tema_guardar':
        $d = json_decode(file_get_contents('php://input'), true);
        $temaOscuro = array_key_exists('tema_oscuro', $d) && (bool) $d['tema_oscuro'];

        $db->prepare("UPDATE public.superadmins SET tema_oscuro = :tema WHERE id = :id")->execute([
            ':tema' => $temaOscuro ? 'true' : 'false',
            ':id'   => sesionId(),
        ]);

        jsonResponse(['error' => false, 'message' => 'Tema guardado correctamente']);

    // ---- POST: Subir logo de un tenant ----
    case 'tenant_logo_upload':
        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        if (!$tenant_id) jsonResponse(['error' => true, 'message' => 'tenant_id requerido'], 400);

        if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['error' => true, 'message' => 'No se recibió archivo'], 400);
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mime    = mime_content_type($_FILES['logo']['tmp_name']);
        if (!in_array($mime, $allowed)) {
            jsonResponse(['error' => true, 'message' => 'Solo se permiten imágenes (JPG, PNG, WEBP, GIF)'], 400);
        }
        if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
            jsonResponse(['error' => true, 'message' => 'El archivo no debe superar 2 MB'], 400);
        }

        // Obtener slug del tenant para nombrar el archivo
        $t = $db->prepare("SELECT slug FROM public.tenants WHERE id = :id");
        $t->execute([':id' => $tenant_id]);
        $tenant = $t->fetch();
        if (!$tenant) jsonResponse(['error' => true, 'message' => 'Empresa no encontrada'], 404);

        $ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
        $filename = 'logo_' . $tenant['slug'] . '.' . $ext;
        $dir      = __DIR__ . '/../../assets/img/logos/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // Eliminar logos anteriores del mismo tenant
        foreach (glob($dir . 'logo_' . $tenant['slug'] . '.*') as $old) {
            unlink($old);
        }

        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $dir . $filename)) {
            jsonResponse(['error' => true, 'message' => 'Error al guardar el archivo'], 500);
        }

        $logo_path = 'assets/img/logos/' . $filename;
        $db->prepare("UPDATE public.tenants SET logo = :l WHERE id = :id")
           ->execute([':l' => $logo_path, ':id' => $tenant_id]);

        jsonResponse(['error' => false, 'message' => 'Logo actualizado', 'logo' => $logo_path]);

    default:
        jsonResponse(['error' => true, 'message' => 'Acción no válida'], 404);
}
