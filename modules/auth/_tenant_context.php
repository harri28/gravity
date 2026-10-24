<?php
// Detecta el tenant desde el subdominio (ej: generycpharma.gravityty.space ->
// slug "generycpharma"). Usado por login.php (para el guard de host) y
// api.php (para restringir el login a ese tenant). Requiere que $db ya
// exista (getDB()) antes de incluir este archivo.

$host       = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$host_parts = explode('.', $host);

$detected_tenant = null;
$subdomain_mode  = false;

if (count($host_parts) >= 3 && $host_parts[0] !== 'www') {
    $stmt = $db->prepare('SELECT id, nombre FROM public.tenants WHERE slug = :s AND activo = TRUE');
    $stmt->execute([':s' => $host_parts[0]]);
    $t = $stmt->fetch();
    if ($t) {
        $detected_tenant = $t;
        $subdomain_mode  = true;
    }
}
