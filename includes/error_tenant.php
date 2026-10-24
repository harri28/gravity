<?php
http_response_code(404);
$__bgPath = __DIR__ . '/../assets/img/gravity-bg.jpg';
$__bgVer = @filemtime($__bgPath) ?: 0;
$__bgUrl = (function_exists('app_public_prefix') ? app_public_prefix() : '/gravity') . '/assets/img/gravity-bg.jpg?v=' . $__bgVer;
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empresa no encontrada — Gravity</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
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
            width: 100%; max-width: 420px;
            padding: 40px 32px;
            text-align: center;
        }
        .icon {
            width: 56px; height: 56px; margin: 0 auto 20px;
            background: #fee2e2; color: #dc2626;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem;
            font-weight: 700;
        }
        h1 { font-size: 1.1rem; color: #1e293b; margin-bottom: 8px; }
        p { font-size: .88rem; color: #64748b; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&times;</div>
        <h1>Empresa no encontrada</h1>
        <p>Este subdominio no corresponde a ninguna empresa registrada en el sistema.</p>
    </div>
</body>
</html>
<?php exit; ?>
