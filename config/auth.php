<?php
// ============================================================
// ARCHIVO: farmacia/config/auth.php
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    // La sesion no debe cerrarse por inactividad, solo cuando el usuario
    // cierre sesion manualmente -- se extiende su duracion a 30 dias tanto
    // en la cookie del navegador como en el garbage collector de PHP.
    $sessionLifetime = 60 * 60 * 24 * 30;
    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
    session_set_cookie_params($sessionLifetime, '/', '', false, true);
    session_start();
}

function requireAuth(array $roles = []): void
{
    if (empty($_SESSION['usuario_id'])) {
        header('Location: ' . _loginUrl());
        exit;
    }
    // Superadmin fuera de su módulo → redirigir al panel superadmin
    if (isSuperadmin() && !in_array($roles[0] ?? '', ['superadmin'], true)) {
        header('Location: ' . _appRoot() . '/modules/superadmin/index.php');
        exit;
    }
    if (!empty($roles) && !in_array($_SESSION['rol'] ?? '', $roles, true)) {
        http_response_code(403);
        _render403();
        exit;
    }
}

function requireApiAuth(array $roles = []): void
{
    header('Content-Type: application/json; charset=UTF-8');
    if (empty($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode(['error' => true, 'message' => 'Sesión expirada. Por favor inicia sesión.']);
        exit;
    }
    if (!empty($roles) && !in_array($_SESSION['rol'] ?? '', $roles, true)) {
        http_response_code(403);
        echo json_encode(['error' => true, 'message' => 'No tienes permiso para realizar esta acción.']);
        exit;
    }
}

// ---- Helpers de sesión ----

function isAdmin(): bool    { return in_array($_SESSION['rol'] ?? '', ['admin', 'gerente'], true); }
function isGerente(): bool  { return ($_SESSION['rol'] ?? '') === 'gerente'; }
function isCajero(): bool   { return ($_SESSION['rol'] ?? '') === 'cajero'; }
function isSuperadmin(): bool { return ($_SESSION['rol'] ?? '') === 'superadmin'; }

function sesionId(): int           { return (int)($_SESSION['usuario_id']      ?? 0); }
function sesionNombre(): string    { return $_SESSION['nombre']                 ?? 'Usuario'; }
function sesionUsername(): string  { return $_SESSION['username']               ?? ''; }
function sesionRol(): string       { return $_SESSION['rol']                    ?? ''; }
function sesionRolLabel(): string
{
    switch ($_SESSION['rol'] ?? '') {
        case 'gerente':    return 'Superadmin';
        case 'admin':      return 'Administrador';
        case 'cajero':     return 'Cajero';
        case 'superadmin': return 'Superadmin Sistema';
        default:           return 'Usuario';
    }
}
function sesionSucursal(): string  { return $_SESSION['sucursal_nombre']        ?? ''; }
function sesionSucursalId(): int   { return (int)($_SESSION['sucursal_id']      ?? 0); }
function sesionSchema(): string    { return $_SESSION['sucursal_schema']        ?? ''; }
function sesionTenantId(): int     { return (int)($_SESSION['tenant_id']        ?? 0); }
function sesionTenantNombre(): string { return $_SESSION['tenant_nombre']       ?? ''; }
function sesionTenantSlug(): string   { return $_SESSION['tenant_slug']         ?? ''; }

// ---- Internos ----

function _appRoot(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (preg_match('#^(.*?)/(modules|test|config|includes|assets|database)/#', $script, $m)) {
        return $m[1];
    }
    return '/farmacia';
}

function _loginUrl(): string { return _appRoot() . '/modules/auth/login.php'; }

function _render403(): void { ?>
    <!DOCTYPE html><html lang="es"><head>
    <meta charset="UTF-8"><title>Acceso denegado — Gravity</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body{font-family:'Segoe UI',system-ui,sans-serif;background:#f1f5f9;display:flex;align-items:center;
             justify-content:center;min-height:100vh;margin:0}
        .box{background:#fff;border-radius:16px;padding:48px 40px;text-align:center;max-width:400px;
             box-shadow:0 4px 20px rgba(0,0,0,.08)}
        .icon{width:72px;height:72px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;
              justify-content:center;margin:0 auto 20px;font-size:1.8rem;color:#dc2626}
        h1{font-size:1.3rem;color:#1e293b;margin:0 0 8px}
        p{color:#64748b;font-size:.9rem;margin:0 0 24px}
        a{display:inline-flex;align-items:center;gap:8px;background:#6366f1;color:#fff;padding:10px 22px;
          border-radius:8px;text-decoration:none;font-weight:600;font-size:.9rem}
        a:hover{background:#4f46e5}
    </style></head><body>
    <div class="box">
        <div class="icon"><i class="fas fa-lock"></i></div>
        <h1>Acceso denegado</h1>
        <p>No tienes permiso para acceder a esta sección.<br>Contacta al administrador del sistema.</p>
        <a href="javascript:history.back()"><i class="fas fa-arrow-left"></i> Volver</a>
    </div></body></html>
<?php }
