<?php
// ============================================================
// ARCHIVO: farmacia/modules/auth/restablecer.php
// DESCRIPCIÓN: Paso 2 de recuperación — establecer nueva contraseña
// ============================================================

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Si ya está logueado, redirigir
if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . (isSuperadmin() ? '../superadmin/index.php' : '../ventas/index.php'));
    exit;
}

$token   = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error   = '';
$success = false;
$reset   = null;   // fila de password_resets válida

// Función auxiliar: validar el token y retornar la fila o null
function validarToken(PDO $db, string $token): ?array
{
    if ($token === '') return null;
    // Buscar token de superadmin
    $stmt = $db->prepare("
        SELECT pr.id, pr.superadmin_id AS owner_id, sa.nombre, 'superadmin' AS tipo
        FROM public.password_resets pr
        JOIN public.superadmins sa ON sa.id = pr.superadmin_id
        WHERE pr.token = :tok AND pr.used = FALSE AND pr.expires_at > NOW() AND sa.activo = TRUE
        LIMIT 1
    ");
    $stmt->execute([':tok' => $token]);
    $row = $stmt->fetch();
    if ($row) return $row;

    // Buscar token de usuario de empresa
    $stmt = $db->prepare("
        SELECT pr.id, pr.usuario_id AS owner_id, u.nombre, 'usuario' AS tipo
        FROM public.password_resets pr
        JOIN public.usuarios u ON u.id = pr.usuario_id
        WHERE pr.token = :tok AND pr.used = FALSE AND pr.expires_at > NOW() AND u.activo = TRUE
        LIMIT 1
    ");
    $stmt->execute([':tok' => $token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

if ($token === '') {
    header('Location: recuperar.php');
    exit;
}

try {
    $db = getDB();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pass1 = $_POST['password']  ?? '';
        $pass2 = $_POST['password2'] ?? '';

        if (strlen($pass1) < 4) {
            $error = 'La contraseña debe tener al menos 4 caracteres.';
        } elseif ($pass1 !== $pass2) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $reset = validarToken($db, $token);
            if (!$reset) {
                $error = 'El enlace ha expirado o ya fue utilizado. Solicita uno nuevo.';
            } else {
                $db->beginTransaction();
                $table = $reset['tipo'] === 'superadmin' ? 'superadmins' : 'usuarios';
                $db->prepare("UPDATE public.{$table} SET password_hash = :h WHERE id = :id")
                   ->execute([':h' => password_hash($pass1, PASSWORD_BCRYPT), ':id' => $reset['owner_id']]);
                $db->prepare("
                    UPDATE public.password_resets SET used = TRUE WHERE id = :id
                ")->execute([':id' => $reset['id']]);
                $db->commit();
                $success = true;
            }
        }

        if (!$success && !$reset) {
            $reset = validarToken($db, $token); // para repoblar el nombre en el form
        }
    } else {
        $reset = validarToken($db, $token);
        if (!$reset) {
            $error = 'El enlace de restablecimiento ha expirado o ya fue utilizado.';
        }
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    $error = 'Ocurrió un error. Intenta nuevamente.';
}

$nombre = htmlspecialchars($reset['nombre'] ?? '');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva contraseña — Gravity</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/img/logos/favicon.svg">
    <link rel="icon" type="image/png" href="../../assets/img/logos/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 24px 16px;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0,0,0,.4);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            padding: 26px 32px 22px;
            text-align: center;
            color: #fff;
        }
        .brand-icon { width:52px;height:52px;background:rgba(255,255,255,.18);border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin:0 auto 10px; }
        .brand-name { font-size:1.4rem;font-weight:700; }
        .brand-sub  { font-size:.8rem;opacity:.75;margin-top:2px; }
        .card-body  { padding: 28px 32px; }
        .card-footer { border-top:1px solid #f1f5f9;padding:12px 32px;text-align:center;font-size:.75rem;color:#94a3b8; }

        .page-title { font-size:.95rem;font-weight:700;color:#1e293b;margin-bottom:4px; }
        .page-sub   { font-size:.82rem;color:#64748b;margin-bottom:22px;line-height:1.5; }

        .form-group { margin-bottom:16px; }
        .form-label { display:block;font-size:.78rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.04rem;margin-bottom:6px; }
        .input-wrap { position:relative; }
        .input-icon  { position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.88rem; }
        .form-input  { width:100%;padding:11px 14px 11px 37px;border:2px solid #e2e8f0;border-radius:9px;font-size:.93rem;font-family:inherit;outline:none;transition:border-color .15s;color:#1e293b; }
        .form-input:focus { border-color:#6366f1; }
        .toggle-pass { position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8; }
        .toggle-pass:hover { color:#475569; }
        .form-input.with-toggle { padding-right:42px; }

        .btn-submit { width:100%;padding:13px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;border-radius:9px;font-size:1rem;font-weight:700;font-family:inherit;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .15s;margin-top:4px; }
        .btn-submit:hover { opacity:.92; }
        .btn-primary { display:inline-flex;align-items:center;gap:8px;background:#6366f1;color:#fff;padding:11px 28px;border-radius:9px;text-decoration:none;font-weight:700;font-size:.9rem;margin-top:16px; }
        .btn-primary:hover { background:#4f46e5; }

        .alert { border-radius:9px;padding:10px 14px;font-size:.83rem;margin-bottom:16px;display:flex;align-items:flex-start;gap:8px;line-height:1.5; }
        .alert.err { background:#fef2f2;border:1px solid #fecaca;color:#dc2626; }

        .success-box { text-align:center;padding:8px 0 8px; }
        .success-icon { width:64px;height:64px;background:#f0fdf4;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.6rem;color:#16a34a; }
        .success-title { font-size:1rem;font-weight:700;color:#1e293b;margin-bottom:8px; }
        .success-sub   { font-size:.85rem;color:#64748b;line-height:1.5; }

        .back-link { text-align:center;margin-top:18px; }
        .back-link a { font-size:.8rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:6px; }
        .back-link a:hover { color:#1e293b; }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <div class="brand-icon"><i class="fas fa-lock-open"></i></div>
        <div class="brand-name">Gravity</div>
        <div class="brand-sub">Nueva contraseña</div>
    </div>
    <div class="card-body">

        <?php if ($success): ?>
        <div class="success-box">
            <div class="success-icon"><i class="fas fa-check"></i></div>
            <p class="success-title">¡Contraseña actualizada!</p>
            <p class="success-sub">Tu contraseña fue cambiada correctamente. Ya puedes iniciar sesión con tu nueva contraseña.</p>
            <a href="login.php?reset=ok" class="btn-primary">
                <i class="fas fa-sign-in-alt"></i> Ir al inicio de sesión
            </a>
        </div>

        <?php elseif ($error && !$reset): ?>
        <!-- Token inválido o expirado -->
        <p class="page-title">Enlace no válido</p>
        <div class="alert err">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <div class="back-link">
            <a href="recuperar.php"><i class="fas fa-redo"></i> Solicitar nuevo enlace</a>
        </div>

        <?php else: ?>
        <!-- Formulario de nueva contraseña -->
        <p class="page-title">Crear nueva contraseña<?= $nombre ? ' · ' . $nombre : '' ?></p>
        <p class="page-sub">Elige una contraseña segura de al menos 4 caracteres.</p>

        <?php if ($error): ?>
        <div class="alert err"><i class="fas fa-exclamation-circle"></i><span><?= htmlspecialchars($error) ?></span></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <div class="form-group">
                <label class="form-label">Nueva contraseña</label>
                <div class="input-wrap">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" id="pass1" class="form-input with-toggle"
                           placeholder="••••••••" autocomplete="new-password" autofocus>
                    <button type="button" class="toggle-pass" onclick="togglePass('pass1','icon1')">
                        <i class="fas fa-eye" id="icon1"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Confirmar contraseña</label>
                <div class="input-wrap">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password2" id="pass2" class="form-input with-toggle"
                           placeholder="••••••••" autocomplete="new-password">
                    <button type="button" class="toggle-pass" onclick="togglePass('pass2','icon2')">
                        <i class="fas fa-eye" id="icon2"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-submit">
                <i class="fas fa-save"></i> Guardar nueva contraseña
            </button>
        </form>

        <div class="back-link">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Volver al inicio de sesión</a>
        </div>
        <?php endif; ?>

    </div>
    <div class="card-footer">Gravity &copy; <?= date('Y') ?> · v1.0</div>
</div>
<script>
function togglePass(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    inp.type   = inp.type === 'password' ? 'text' : 'password';
    icon.className = inp.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>
</body>
</html>
