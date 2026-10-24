<?php

require_once __DIR__ . '/config/database.php';

$host = strtolower(
    preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '')
);

if ($host === 'admin.genpharma.cloud') {
    header('Location: /modules/superadmin/login.php');
    exit;
}

// Un host con 3+ labels que no sea "www" se trata como intento de subdominio
// de tenant: coincide con un tenant activo -> login restringido a ese tenant;
// si no coincide -> error "empresa no encontrada" (no se redirige a ningún
// panel, para no insinuar que existe uno detrás). Todo lo demás (dominio
// raíz, "www", localhost/desarrollo) es tráfico genérico -> página principal.
$host_parts = explode('.', $host);

if (count($host_parts) < 3 || $host_parts[0] === 'www') {
    require __DIR__ . '/includes/landing.php';
}

$tenant_found = false;
try {
    // Conexión propia (no getDB()): si la BD está caída, getDB() mata la
    // página con un JSON crudo en vez de una excepción capturable. Aquí
    // basta con degradar a "tenant no encontrado" y mostrar el error igual.
    $pdo = new PDO(
        'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $pdo->prepare('SELECT 1 FROM public.tenants WHERE slug = :s AND activo = TRUE');
    $stmt->execute([':s' => $host_parts[0]]);
    $tenant_found = (bool) $stmt->fetch();
} catch (Throwable $e) {
    $tenant_found = false;
}

if ($tenant_found) {
    header('Location: /modules/auth/login.php');
    exit;
}

require __DIR__ . '/includes/error_tenant.php';
