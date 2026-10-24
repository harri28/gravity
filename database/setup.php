<?php
// ============================================================
// ARCHIVO: farmacia/database/setup.php
// DESCRIPCIÓN: Script de configuración inicial del sistema.
//              Crea las tablas globales, la primera sucursal
//              y los usuarios admin/cajero de ejemplo.
// ACCEDER EN: http://localhost/farmacia/database/setup.php
// ⚠️  ELIMINAR o proteger este archivo tras ejecutarlo.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'farmacia');
define('DB_USER', 'postgres');
define('DB_PASS', '1234');

$log     = [];
$errores = [];

function conectar(): PDO {
    $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function ok(string $msg): void  { global $log;     $log[]     = ['type'=>'ok',    'msg'=>$msg]; }
function err(string $msg): void { global $errores; $errores[] = ['type'=>'error', 'msg'=>$msg]; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $suc_nombre   = trim($_POST['suc_nombre']   ?? 'Farmacia Central');
    $suc_dir      = trim($_POST['suc_dir']      ?? '');
    $suc_tel      = trim($_POST['suc_tel']      ?? '');
    $admin_user   = trim($_POST['admin_user']   ?? 'admin');
    $admin_pass   = trim($_POST['admin_pass']   ?? '');
    $admin_nombre = trim($_POST['admin_nombre'] ?? 'Administrador');
    $cajero_user  = trim($_POST['cajero_user']  ?? 'cajero');
    $cajero_pass  = trim($_POST['cajero_pass']  ?? '');

    if (!$admin_pass || !$cajero_pass) {
        err('Las contraseñas son requeridas.');
    } elseif (strlen($admin_pass) < 4 || strlen($cajero_pass) < 4) {
        err('Las contraseñas deben tener al menos 4 caracteres.');
    } else {
        try {
            $db = conectar();
            ok('Conexión a PostgreSQL exitosa.');

            // ---- 1. Tablas públicas ----
            $schema_public = file_get_contents(__DIR__ . '/schema_public.sql');
            foreach (array_filter(array_map('trim', explode(';', $schema_public))) as $s) {
                if ($s) $db->exec($s);
            }
            ok('Tablas globales (public) creadas/verificadas.');

            // ---- 2. Crear o recuperar tenant ----
            $tenant_nombre_raw = trim($_POST['tenant_nombre'] ?? $suc_nombre);
            $tenant_slug = strtolower($tenant_nombre_raw);
            $tenant_slug = iconv('UTF-8', 'ASCII//TRANSLIT', $tenant_slug) ?: $tenant_slug;
            $tenant_slug = preg_replace('/[^a-z0-9]+/', '_', $tenant_slug);
            $tenant_slug = trim($tenant_slug, '_');
            $tenant_slug = substr($tenant_slug, 0, 25) ?: 'tenant';

            // Verificar si ya existe el tenant (re-run protection)
            $ck_tenant = $db->prepare("SELECT id, slug FROM public.tenants WHERE slug = :s");
            $ck_tenant->execute([':s' => $tenant_slug]);
            $tenant_row = $ck_tenant->fetch();
            if ($tenant_row) {
                $tenant_id   = $tenant_row['id'];
                $tenant_slug = $tenant_row['slug'];
                ok("Tenant '{$tenant_nombre_raw}' ya existe (ID: {$tenant_id}), reutilizado.");
            } else {
                $ins_t = $db->prepare("INSERT INTO public.tenants (nombre, slug, plan) VALUES (:n, :s, 'basico') RETURNING id");
                $ins_t->execute([':n' => $tenant_nombre_raw, ':s' => $tenant_slug]);
                $tenant_id = $ins_t->fetchColumn();
                ok("Tenant '{$tenant_nombre_raw}' creado (slug: {$tenant_slug}, ID: {$tenant_id}).");
            }

            // ---- 3. Generar schema_name con prefijo del tenant ----
            $suc_slug = strtolower($suc_nombre);
            $suc_slug = iconv('UTF-8', 'ASCII//TRANSLIT', $suc_slug) ?: $suc_slug;
            $suc_slug = preg_replace('/[^a-z0-9]+/', '_', $suc_slug);
            $suc_slug = trim($suc_slug, '_');
            $suc_slug = substr($suc_slug, 0, 20) ?: 'suc';
            $schema   = $tenant_slug . '_' . $suc_slug;
            $schema   = substr($schema, 0, 55);

            // Verificar que no exista ya
            $ck = $db->prepare("SELECT id FROM public.sucursales WHERE schema_name = :s");
            $ck->execute([':s' => $schema]);
            if ($ck->fetch()) {
                err("Ya existe una sucursal con schema '{$schema}'. Cambia el nombre.");
            } else {
                // ---- 4. Crear schema y tablas por sucursal ----
                $db->exec("CREATE SCHEMA IF NOT EXISTS \"{$schema}\"");
                ok("Schema '{$schema}' creado.");

                $db->exec("SET search_path TO \"{$schema}\", public");
                $schema_suc = file_get_contents(__DIR__ . '/schema_sucursal.sql');
                $schema_suc = preg_replace('/--[^\n]*/', '', $schema_suc);
                foreach (array_filter(array_map('trim', explode(';', $schema_suc))) as $s) {
                    if ($s) $db->exec($s);
                }
                ok("Tablas de la sucursal '{$suc_nombre}' creadas.");

                // Volver a public para inserts globales
                $db->exec("SET search_path TO public");

                // ---- 5. Registrar sucursal con tenant_id ----
                $db->prepare("
                    INSERT INTO public.sucursales (tenant_id, nombre, schema_name, direccion, telefono)
                    VALUES (:tid, :n, :s, :d, :t)
                    ON CONFLICT (schema_name) DO NOTHING
                ")->execute([':tid' => $tenant_id, ':n' => $suc_nombre, ':s' => $schema, ':d' => $suc_dir, ':t' => $suc_tel]);
                $suc_id = $db->query("SELECT id FROM public.sucursales WHERE schema_name = '{$schema}'")->fetchColumn();
                ok("Sucursal '{$suc_nombre}' registrada (ID: {$suc_id}).");

                // ---- 6. Crear usuarios con tenant_id ----
                foreach ([
                    ['nombre' => $admin_nombre, 'apellido' => 'Sistema', 'username' => $admin_user,  'pass' => $admin_pass,  'rol' => 'admin'],
                    ['nombre' => 'Cajero',       'apellido' => 'Demo',    'username' => $cajero_user, 'pass' => $cajero_pass, 'rol' => 'cajero'],
                ] as $u) {
                    $ck2 = $db->prepare("SELECT id FROM public.usuarios WHERE username = :u");
                    $ck2->execute([':u' => $u['username']]);
                    if ($ck2->fetch()) {
                        ok("Usuario '{$u['username']}' ya existe, omitido.");
                        $uid_stmt = $db->prepare("SELECT id FROM public.usuarios WHERE username = :u");
                        $uid_stmt->execute([':u' => $u['username']]);
                        $uid = $uid_stmt->fetchColumn();
                    } else {
                        $ins = $db->prepare("
                            INSERT INTO public.usuarios (tenant_id, nombre, apellido, username, password_hash)
                            VALUES (:tid, :n, :a, :u, :h) RETURNING id
                        ");
                        $ins->execute([
                            ':tid' => $tenant_id,
                            ':n'   => $u['nombre'],
                            ':a'   => $u['apellido'],
                            ':u'   => $u['username'],
                            ':h'   => password_hash($u['pass'], PASSWORD_BCRYPT),
                        ]);
                        $uid = $ins->fetchColumn();
                        ok("Usuario '{$u['username']}' creado con rol '{$u['rol']}'.");
                    }
                    // Asignar a la sucursal
                    $db->prepare("
                        INSERT INTO public.usuario_sucursal (usuario_id, sucursal_id, rol)
                        VALUES (:uid, :sid, :rol)
                        ON CONFLICT (usuario_id, sucursal_id) DO UPDATE SET rol = EXCLUDED.rol
                    ")->execute([':uid' => $uid, ':sid' => $suc_id, ':rol' => $u['rol']]);
                    ok("Acceso de '{$u['username']}' asignado a '{$suc_nombre}' como {$u['rol']}.");
                }
            }
        } catch (Exception $e) {
            err('Error: ' . $e->getMessage());
        }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup inicial — Gravity</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f1f5f9; padding: 32px 16px; color: #1e293b; }
        .container { max-width: 640px; margin: 0 auto; }
        .header { background: linear-gradient(135deg,#6366f1,#4f46e5); color:#fff; border-radius:14px; padding:24px 28px; margin-bottom:24px; }
        .header h1 { font-size:1.2rem; font-weight:700; margin-bottom:4px; }
        .header p  { font-size:.85rem; opacity:.8; }
        .warn { background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:12px 16px; font-size:.82rem; color:#92400e; margin-bottom:20px; }
        .card { background:#fff; border-radius:12px; box-shadow:0 1px 4px rgba(0,0,0,.08); margin-bottom:20px; overflow:hidden; }
        .card-header { padding:14px 20px; border-bottom:1px solid #e2e8f0; font-weight:700; font-size:.9rem; }
        .card-body { padding:20px; }
        .form-group { margin-bottom:14px; }
        label { display:block; font-size:.8rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:.03rem; margin-bottom:5px; }
        input { width:100%; padding:10px 12px; border:2px solid #e2e8f0; border-radius:8px; font-size:.9rem; outline:none; transition:.15s; }
        input:focus { border-color:#6366f1; }
        .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .btn { width:100%; padding:13px; background:linear-gradient(135deg,#6366f1,#4f46e5); color:#fff; border:none; border-radius:9px; font-size:1rem; font-weight:700; cursor:pointer; }
        .btn:hover { opacity:.93; }
        .log { margin-top:20px; }
        .log-item { display:flex; align-items:flex-start; gap:10px; padding:9px 14px; border-radius:8px; margin-bottom:6px; font-size:.85rem; }
        .log-item.ok    { background:#f0fdf4; color:#15803d; }
        .log-item.error { background:#fef2f2; color:#dc2626; }
        .success-box { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:20px 24px; text-align:center; }
        .success-box h2 { color:#15803d; font-size:1.1rem; margin-bottom:8px; }
        .success-box a { display:inline-block; margin-top:16px; background:#16a34a; color:#fff; padding:11px 28px; border-radius:9px; font-weight:700; text-decoration:none; }
    </style>
</head>
<body>
<div class="container">

    <div class="header">
        <h1><i class="fas fa-cogs"></i> Setup inicial — Gravity</h1>
        <p>Configura la primera sucursal y crea los usuarios de acceso.</p>
    </div>

    <div class="warn">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Importante:</strong> Ejecuta este script solo una vez. Después de completar la configuración, elimina o protege el acceso a este archivo.
    </div>

    <?php if (!empty($errores)): ?>
    <div class="log">
        <?php foreach ($errores as $e): ?>
        <div class="log-item error"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($e['msg']) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($log)): ?>
    <div class="log">
        <?php foreach ($log as $l): ?>
        <div class="log-item ok"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($l['msg']) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php $exito = !empty($log) && empty($errores); ?>

    <?php if ($exito): ?>
    <div class="success-box">
        <h2><i class="fas fa-check-circle"></i> ¡Configuración completada!</h2>
        <p style="color:#166534;font-size:.88rem">
            El sistema está listo. Inicia sesión con las credenciales que configuraste.
        </p>
        <a href="../modules/auth/login.php"><i class="fas fa-sign-in-alt"></i> Ir al Login</a>
    </div>
    <?php else: ?>
    <form method="POST">
        <div class="card">
            <div class="card-header"><i class="fas fa-building" style="color:#6366f1;margin-right:6px"></i>Empresa (Tenant)</div>
            <div class="card-body">
                <div class="form-group">
                    <label>Nombre de la empresa *</label>
                    <input type="text" name="tenant_nombre" value="<?= htmlspecialchars($_POST['tenant_nombre'] ?? 'Mi Farmacia') ?>" placeholder="Mi Farmacia S.A.C." required>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-store" style="color:#6366f1;margin-right:6px"></i>Primera sucursal</div>
            <div class="card-body">
                <div class="form-group">
                    <label>Nombre de la sucursal *</label>
                    <input type="text" name="suc_nombre" value="<?= htmlspecialchars($_POST['suc_nombre'] ?? 'Farmacia Central') ?>" placeholder="Farmacia Central" required>
                </div>
                <div class="grid2">
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="suc_tel" value="<?= htmlspecialchars($_POST['suc_tel'] ?? '') ?>" placeholder="01-123-4567">
                    </div>
                    <div></div>
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" name="suc_dir" value="<?= htmlspecialchars($_POST['suc_dir'] ?? '') ?>" placeholder="Av. Principal 123, Lima">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-user-shield" style="color:#6366f1;margin-right:6px"></i>Usuario Administrador</div>
            <div class="card-body">
                <div class="form-group">
                    <label>Nombre completo</label>
                    <input type="text" name="admin_nombre" value="<?= htmlspecialchars($_POST['admin_nombre'] ?? 'Administrador') ?>">
                </div>
                <div class="grid2">
                    <div class="form-group">
                        <label>Usuario *</label>
                        <input type="text" name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? 'admin') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Contraseña *</label>
                        <input type="password" name="admin_pass" placeholder="Mínimo 4 caracteres" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-user" style="color:#6366f1;margin-right:6px"></i>Usuario Cajero</div>
            <div class="card-body">
                <div class="grid2">
                    <div class="form-group">
                        <label>Usuario *</label>
                        <input type="text" name="cajero_user" value="<?= htmlspecialchars($_POST['cajero_user'] ?? 'cajero') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Contraseña *</label>
                        <input type="password" name="cajero_pass" placeholder="Mínimo 4 caracteres" required>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn"><i class="fas fa-rocket"></i> Inicializar sistema</button>
    </form>
    <?php endif; ?>

</div>
</body>
</html>
