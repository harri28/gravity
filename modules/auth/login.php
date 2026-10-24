<?php
// ============================================================
// ARCHIVO: farmacia/modules/auth/login.php
// Flujo: Credenciales -> Sucursal
//        (las sucursales NUNCA se envían al navegador antes de validar
//        usuario/contraseña -- ver api.php)
// ============================================================

require_once __DIR__ . '/../../config/auth.php';

if (!empty($_SESSION['usuario_id'])) {
    if (isSuperadmin()) {
        header('Location: ../superadmin/index.php');
    } elseif (isAdmin()) {
        header('Location: ../dashboard/index.php');
    } else {
        header('Location: ../ventas/index.php');
    }
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$db = getDB();
require __DIR__ . '/_tenant_context.php'; // -> $host, $host_parts, $detected_tenant, $subdomain_mode

if ($host === 'admin.gravityty.space') {
    header('Location: ../superadmin/login.php');
    exit;
}

// Un intento de subdominio de tenant (3+ labels, no "www") que no coincidió
// con ningún tenant activo muestra error. El dominio raíz, "www" y
// localhost/desarrollo caen al login genérico (subdomain_mode queda false),
// donde el tenant se determina por el usuario que inicia sesión, no por la URL.
if (!$subdomain_mode && count($host_parts) >= 3 && $host_parts[0] !== 'www') {
    require __DIR__ . '/../../includes/error_tenant.php';
}

$__bgPath = __DIR__ . '/../../assets/img/gravity-bg.jpg';
$__bgVer = @filemtime($__bgPath) ?: 0;
$__bgUrl = (function_exists('app_public_prefix') ? app_public_prefix() : '/gravity') . '/assets/img/gravity-bg.jpg?v=' . $__bgVer;
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — Gravity</title>
    <link rel="icon" type="image/svg+xml" href="../../assets/img/logos/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, sans-serif;
            background:
                linear-gradient(135deg, rgba(3,3,8,.78) 0%, rgba(2,56,107,.55) 50%, rgba(3,3,8,.78) 100%),
                url('<?= htmlspecialchars($__bgUrl) ?>') center center / cover no-repeat fixed;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 24px 16px;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0,0,0,.55), 0 0 0 1px rgba(4,138,245,.1);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            transition: max-width .3s ease;
        }
        .card.wide { max-width: 680px; }
        .card-header {
            background: linear-gradient(135deg, #048af5, #02386b);
            padding: 26px 32px 22px;
            text-align: center;
            color: #fff;
        }
        .brand-icon { width:52px;height:52px;background:rgba(255,255,255,.18);border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin:0 auto 10px; }
        .brand-name { font-size:1.4rem;font-weight:700; }
        .brand-sub  { font-size:.8rem;opacity:.75;margin-top:2px; }
        .card-body  { padding: 28px 32px; }
        .card-footer { border-top:1px solid #f1f5f9;padding:12px 32px;text-align:center;font-size:.75rem;color:#94a3b8; }

        /* Steps */
        .step { display:none; }
        .step.active { display:block;animation:fadeIn .2s ease; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }

        .step-title { font-size:1rem;font-weight:700;color:#1e293b;margin-bottom:4px; }
        .step-sub   { font-size:.82rem;color:#64748b;margin-bottom:20px; }

        /* Sucursal cards */
        .sel-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:10px;margin-bottom:4px; }
        .sel-card {
            border:2px solid #e2e8f0;border-radius:11px;padding:14px 12px;cursor:pointer;
            transition:border-color .15s,background .15s,transform .1s;
        }
        .sel-card:hover { border-color:#0471cd;background:#f4f9ff;transform:translateY(-1px); }
        .sel-card.selected { border-color:#0471cd;background:#e6efff; }
        .sel-icon { width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.95rem;margin-bottom:9px; }
        .sel-icon.branch  { background:#f0fdf4;color:#16a34a; }
        .sel-nombre  { font-size:.88rem;font-weight:700;color:#1e293b; }
        .sel-sub     { font-size:.73rem;color:#64748b;margin-top:3px;line-height:1.3; }

        .empty-state { text-align:center;padding:28px 0;color:#94a3b8;font-size:.85rem; }

        /* Formulario */
        .form-group { margin-bottom:16px; }
        .form-label { display:block;font-size:.78rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.04rem;margin-bottom:6px; }
        .input-wrap { position:relative; }
        .input-icon { position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.88rem; }
        .form-input { width:100%;padding:11px 14px 11px 37px;border:2px solid #e2e8f0;border-radius:9px;font-size:.93rem;font-family:inherit;outline:none;transition:border-color .15s;color:#1e293b; }
        .form-input:focus { border-color:#0471cd; }
        .form-input.with-toggle { padding-right:42px; }
        .toggle-pass { position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8; }
        .toggle-pass:hover { color:#475569; }
        .btn-submit { width:100%;padding:13px;background:linear-gradient(135deg,#048af5,#02386b);color:#fff;border:none;border-radius:9px;font-size:1rem;font-weight:700;font-family:inherit;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .15s;box-shadow:0 4px 14px rgba(4,113,205,.35); }
        .btn-submit:hover { opacity:.92; }
        .btn-submit:disabled { opacity:.6;cursor:default; }

        /* Alert */
        .alert { background:#fef2f2;border:1px solid #fecaca;color:#dc2626;border-radius:9px;padding:10px 14px;font-size:.83rem;margin-bottom:16px;display:flex;align-items:center;gap:8px; }

        /* Nav pills (back) */
        .back-btn { display:inline-flex;align-items:center;gap:6px;background:none;border:none;cursor:pointer;font-size:.8rem;color:#64748b;font-family:inherit;margin-bottom:16px;padding:0; }
        .back-btn:hover { color:#1e293b; }
    </style>
</head>
<body>
<div class="card" id="login-card">

    <div class="card-header">
        <div class="brand-icon"><i class="fas fa-rocket"></i></div>
        <div class="brand-name">Gravity</div>
        <div class="brand-sub">Punto de venta</div>
    </div>

    <div class="card-body">

        <div id="alert-box">
            <?php if (($_GET['reset'] ?? '') === 'ok'): ?>
            <div class="alert" style="background:#f0fdf4;border-color:#bbf7d0;color:#15803d">
                <i class="fas fa-check-circle"></i> Contraseña actualizada correctamente. Ya puedes iniciar sesión.
            </div>
            <?php endif; ?>
        </div>

        <!-- ============================================================
             PASO 1: Credenciales
             ============================================================ -->
        <div class="step active" id="step-creds">

            <div class="step-title">Iniciar sesión</div>
            <div class="step-sub">Ingresa tu usuario y contraseña</div>

            <form id="form-creds" onsubmit="return false;">
                <div class="form-group">
                    <label class="form-label">Usuario</label>
                    <div class="input-wrap">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" id="inp-username" class="form-input"
                            placeholder="Tu nombre de usuario" autocomplete="username">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="inp-password" class="form-input with-toggle"
                            placeholder="••••••••" autocomplete="current-password">
                        <button type="button" class="toggle-pass" onclick="togglePass()">
                            <i class="fas fa-eye" id="icon-pass"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-submit" id="btn-validar">
                    <i class="fas fa-arrow-right"></i> Continuar
                </button>
                <div style="text-align:center;margin-top:14px">
                    <a href="recuperar.php" style="font-size:.78rem;color:#0471cd;text-decoration:none;display:inline-flex;align-items:center;gap:5px">
                        <i class="fas fa-key" style="font-size:.7rem"></i> ¿Olvidaste tu contraseña?
                    </a>
                </div>
            </form>
        </div>

        <!-- ============================================================
             PASO 2: Selección de sucursal (poblado por JS tras validar)
             ============================================================ -->
        <div class="step" id="step-branch">

            <button type="button" class="back-btn" onclick="goTo('step-creds')">
                <i class="fas fa-arrow-left"></i> Volver
            </button>

            <div class="step-title">Selecciona tu sucursal</div>
            <div class="step-sub">Elige el local en el que trabajarás hoy</div>

            <div class="sel-grid" id="sucursales-grid"></div>

        </div>

    </div>

    <div class="card-footer">Gravity &copy; <?= date('Y') ?> · v1.0</div>
</div>

<script>
const API  = 'api.php';
const card = document.getElementById('login-card');

function goTo(stepId) {
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
    document.getElementById(stepId).classList.add('active');
    clearAlert();
    if (stepId === 'step-creds') setTimeout(() => document.getElementById('inp-username').focus(), 80);
}

function showAlert(msg) {
    document.getElementById('alert-box').innerHTML =
        '<div class="alert"><i class="fas fa-exclamation-circle"></i><span></span></div>';
    document.querySelector('#alert-box .alert span').textContent = msg;
}

function clearAlert() {
    document.getElementById('alert-box').innerHTML = '';
}

function togglePass() {
    const inp  = document.getElementById('inp-password');
    const icon = document.getElementById('icon-pass');
    inp.type   = inp.type === 'password' ? 'text' : 'password';
    icon.className = inp.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}

document.getElementById('form-creds').addEventListener('submit', async () => {
    clearAlert();
    const username = document.getElementById('inp-username').value.trim();
    const password = document.getElementById('inp-password').value;
    if (!username || !password) { showAlert('Ingresa usuario y contraseña.'); return; }

    const btn = document.getElementById('btn-validar');
    btn.disabled = true;
    try {
        const r = await fetch(`${API}?action=validar_credenciales`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password }),
        });
        const d = await r.json();
        if (d.error) { showAlert(d.message); return; }
        renderSucursales(d.sucursales);
        goTo('step-branch');
    } catch (err) {
        showAlert('Error de conexión. Intenta de nuevo.');
    } finally {
        btn.disabled = false;
    }
});

function renderSucursales(list) {
    card.classList.toggle('wide', list.length > 2);
    const grid = document.getElementById('sucursales-grid');
    grid.innerHTML = '';
    list.forEach(s => {
        const el = document.createElement('div');
        el.className = 'sel-card';
        el.innerHTML =
            '<div class="sel-icon branch"><i class="fas fa-store"></i></div>' +
            '<div class="sel-nombre"></div><div class="sel-sub"></div>';
        el.querySelector('.sel-nombre').textContent = s.nombre;
        el.querySelector('.sel-sub').textContent    = s.direccion || '';
        el.addEventListener('click', () => confirmarSucursal(s.id, el));
        grid.appendChild(el);
    });
}

async function confirmarSucursal(id, el) {
    document.querySelectorAll('.sel-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    clearAlert();
    try {
        const r = await fetch(`${API}?action=confirmar_sucursal`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sucursal_id: id }),
        });
        const d = await r.json();
        if (d.error) { showAlert(d.message); return; }
        window.location.href = d.redirect;
    } catch (err) {
        showAlert('Error de conexión. Intenta de nuevo.');
    }
}
</script>
</body>
</html>
