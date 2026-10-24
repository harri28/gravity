<?php
// ============================================================
// ARCHIVO: farmacia/modules/auth/recuperar.php
// DESCRIPCIÓN: Paso 1 de recuperación — solicitar enlace por correo
// ============================================================

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/mail.php';

// Si ya está logueado, redirigir
if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . (isSuperadmin() ? '../superadmin/index.php' : '../ventas/index.php'));
    exit;
}

$mensaje = '';
$tipo    = '';   // 'ok' | 'err'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = trim($_POST['input'] ?? '');

    if ($input === '') {
        $mensaje = 'Ingresa tu usuario o correo electrónico.';
        $tipo    = 'err';
    } else {
        try {
            $db = getDB();

            // Limpiar tokens expirados de paso (mantenimiento pasivo)
            $db->exec("DELETE FROM public.password_resets WHERE expires_at < NOW()");

            // Buscar primero en superadmins, luego en usuarios de empresa
            $user       = null;
            $esSuperadmin = false;

            $stmt = $db->prepare("
                SELECT id, nombre, email FROM public.superadmins
                WHERE (LOWER(username) = LOWER(:u) OR LOWER(email) = LOWER(:u)) AND activo = TRUE
                LIMIT 1
            ");
            $stmt->execute([':u' => $input]);
            $row = $stmt->fetch();
            if ($row) { $user = $row; $esSuperadmin = true; }

            if (!$user) {
                $stmt = $db->prepare("
                    SELECT id, nombre, email FROM public.usuarios
                    WHERE (LOWER(username) = LOWER(:u) OR LOWER(email) = LOWER(:u)) AND activo = TRUE
                    LIMIT 1
                ");
                $stmt->execute([':u' => $input]);
                $user = $stmt->fetch() ?: null;
            }

            // Procesar solo si existe Y tiene correo configurado
            if ($user && !empty($user['email'])) {
                // Eliminar tokens previos no usados
                if ($esSuperadmin) {
                    $db->prepare("DELETE FROM public.password_resets WHERE superadmin_id = :id AND used = FALSE")
                       ->execute([':id' => $user['id']]);
                } else {
                    $db->prepare("DELETE FROM public.password_resets WHERE usuario_id = :id AND used = FALSE")
                       ->execute([':id' => $user['id']]);
                }

                // Generar token seguro
                $token     = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                if ($esSuperadmin) {
                    $db->prepare("INSERT INTO public.password_resets (superadmin_id, token, expires_at) VALUES (:id, :tok, :exp)")
                       ->execute([':id' => $user['id'], ':tok' => $token, ':exp' => $expiresAt]);
                } else {
                    $db->prepare("INSERT INTO public.password_resets (usuario_id, token, expires_at) VALUES (:id, :tok, :exp)")
                       ->execute([':id' => $user['id'], ':tok' => $token, ':exp' => $expiresAt]);
                }

                // Construir URL de restablecimiento
                $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host     = $_SERVER['HTTP_HOST'];
                $dir      = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                $resetUrl = "{$scheme}://{$host}{$dir}/restablecer.php?token={$token}";

                // Construir correo HTML
                $nombre   = htmlspecialchars($user['nombre']);
                $year     = date('Y');
                $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',system-ui,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 16px">
  <tr><td>
    <table width="100%" cellpadding="0" cellspacing="0"
           style="max-width:500px;margin:0 auto;background:#ffffff;border-radius:16px;
                  overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)">
      <tr>
        <td style="background:linear-gradient(135deg,#6366f1,#4f46e5);padding:28px 32px;text-align:center">
          <p style="margin:0;font-size:1.3rem;font-weight:700;color:#fff">&#128138; Gravity</p>
          <p style="margin:6px 0 0;font-size:.82rem;color:rgba(255,255,255,.8)">Punto de venta</p>
        </td>
      </tr>
      <tr>
        <td style="padding:32px">
          <p style="margin:0 0 14px;font-size:1rem;font-weight:700;color:#1e293b">Restablecer contraseña</p>
          <p style="margin:0 0 14px;font-size:.9rem;color:#475569;line-height:1.6">
            Hola <strong>{$nombre}</strong>, recibimos una solicitud para restablecer la contraseña de tu cuenta en Gravity.
          </p>
          <p style="margin:0 0 24px;font-size:.9rem;color:#475569;line-height:1.6">
            Haz clic en el botón de abajo para crear una nueva contraseña.
            Este enlace es válido por <strong>1 hora</strong>.
          </p>
          <table cellpadding="0" cellspacing="0" style="margin:0 auto 24px">
            <tr>
              <td style="background:#6366f1;border-radius:9px">
                <a href="{$resetUrl}"
                   style="display:inline-block;padding:13px 32px;color:#fff;font-weight:700;
                          font-size:.95rem;text-decoration:none;letter-spacing:.01em">
                  Restablecer contraseña
                </a>
              </td>
            </tr>
          </table>
          <p style="margin:0 0 10px;font-size:.78rem;color:#94a3b8;line-height:1.5">
            Si no solicitaste este cambio, ignora este correo. Tu contraseña permanecerá igual.
          </p>
          <p style="margin:0;font-size:.75rem;color:#94a3b8;line-height:1.5">
            O copia y pega este enlace en tu navegador:<br>
            <a href="{$resetUrl}" style="color:#6366f1;word-break:break-all">{$resetUrl}</a>
          </p>
        </td>
      </tr>
      <tr>
        <td style="border-top:1px solid #e2e8f0;padding:14px 32px;text-align:center">
          <p style="margin:0;font-size:.73rem;color:#94a3b8">
            Gravity &copy; {$year} &nbsp;&middot;&nbsp; Correo automático, no responder
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
                sendMail($user['email'], 'Restablecer contraseña — Gravity', $html);
            }

            // Respuesta genérica para no revelar si el usuario/email existe
            $mensaje = 'Si existe una cuenta con esos datos y tiene correo configurado, recibirás un enlace de restablecimiento en breve.';
            $tipo    = 'ok';

        } catch (Exception $e) {
            $mensaje = 'Ocurrió un error. Intenta nuevamente.';
            $tipo    = 'err';
        }
    }
}

$prev_input = htmlspecialchars($_POST['input'] ?? '');
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña — Gravity</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/img/logos/favicon.svg?v=<?= @filemtime(__DIR__ . '/../../assets/img/logos/favicon.svg') ?: 1 ?>">
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
        .input-icon { position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.88rem; }
        .form-input { width:100%;padding:11px 14px 11px 37px;border:2px solid #e2e8f0;border-radius:9px;font-size:.93rem;font-family:inherit;outline:none;transition:border-color .15s;color:#1e293b; }
        .form-input:focus { border-color:#6366f1; }
        .btn-submit { width:100%;padding:13px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;border-radius:9px;font-size:1rem;font-weight:700;font-family:inherit;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .15s;margin-top:4px; }
        .btn-submit:hover { opacity:.92; }
        .alert { border-radius:9px;padding:10px 14px;font-size:.83rem;margin-bottom:16px;display:flex;align-items:flex-start;gap:8px;line-height:1.5; }
        .alert.err { background:#fef2f2;border:1px solid #fecaca;color:#dc2626; }
        .alert.ok  { background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d; }
        .back-link { text-align:center;margin-top:18px; }
        .back-link a { font-size:.8rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:6px; }
        .back-link a:hover { color:#1e293b; }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <div class="brand-icon"><i class="fas fa-key"></i></div>
        <div class="brand-name">Gravity</div>
        <div class="brand-sub">Recuperar contraseña</div>
    </div>
    <div class="card-body">
        <p class="page-title">¿Olvidaste tu contraseña?</p>
        <p class="page-sub">Ingresa tu nombre de usuario o correo electrónico y te enviaremos un enlace para restablecerla.</p>

        <?php if ($mensaje): ?>
        <div class="alert <?= $tipo ?>">
            <i class="fas <?= $tipo === 'ok' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <span><?= htmlspecialchars($mensaje) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($tipo !== 'ok'): ?>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Usuario o correo electrónico</label>
                <div class="input-wrap">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="input" id="inp" class="form-input"
                           value="<?= $prev_input ?>"
                           placeholder="tu_usuario o correo@ejemplo.com"
                           autocomplete="username" autofocus>
                </div>
            </div>
            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane"></i> Enviar enlace de restablecimiento
            </button>
        </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Volver al inicio de sesión</a>
        </div>
    </div>
    <div class="card-footer">Gravity &copy; <?= date('Y') ?> · v1.0</div>
</div>
</body>
</html>
