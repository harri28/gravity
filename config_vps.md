# Configuración del VPS

Guía técnica agnóstica del sitio desplegado: Apache, DNS, SSL, PHP, permisos. Las credenciales reales del VPS actual (IP, host SSH, dominio) **no** se documentan acá — se guardan en un gestor de secretos aparte o en un archivo gitignored propio del operador.

---

## Acceso

- **Usuario/host**: `root@<VPS_HOST>` — reemplazar `<VPS_HOST>` por el host real del servidor de este sistema.
- **Ruta del proyecto**: `/var/www/gravity` (ajustar si se despliega bajo otro nombre).

## Despliegue

- El proyecto se despliega vía git, rama `main`, sincronizado con `origin/main`.
- Flujo de actualización típico:
  ```bash
  cd /var/www/gravity
  git status
  git pull origin main
  ```

---

## PHP

- **Local (XAMPP, este repo en Windows)**: PHP 8.2.12 (`php.exe -v`).
- **VPS — Apache/mod_php (sirve el sistema en producción)**: históricamente **PHP 7.x** en VPS similares — confirmar con `apache2ctl -M | grep php` y `php -v` al preparar un nuevo VPS.
- **Importante para todo código nuevo**: no usar sintaxis exclusiva de PHP 8+ (`str_ends_with`, `str_starts_with`, `str_contains`, `match`, `enum`, operador `?->`, etc.) mientras Apache siga sirviendo con PHP 7 — rompe en producción sin previo aviso con `[php7:error]` en el log de Apache. Si se migra el VPS a PHP 8+, confirmar explícitamente en este archivo antes de empezar a usar esa sintaxis.

---

## Servidor web

- Apache 2.4+ con mod_php. No se asume Nginx.
- Vhost del sistema: `/etc/apache2/sites-available/<DOMINIO>.conf` (+ `<DOMINIO>-le-ssl.conf` para HTTPS, gestionado por Certbot/Let's Encrypt).
- Si se usan subdominios por tenant: `ServerName <DOMINIO>`, `ServerAlias www.<DOMINIO>` + `*.<DOMINIO>` (wildcard — ver sección "Subdominios por tenant" abajo).
- Redirección forzada HTTP → HTTPS vía `mod_rewrite`, sin depender de `SERVER_NAME` exacto (cubre cualquier subdominio).
- Un VPS puede alojar varios sitios no relacionados en la misma IP (vhosts separados) — tener en cuenta antes de un `restart` de Apache (afecta a todos los sitios del VPS).

**Reiniciar Apache**:

```bash
# Recomendado — recarga config sin cortar conexiones activas
systemctl reload apache2

# Reinicio completo — corta todas las conexiones activas de TODOS los sitios
# del VPS, no solo este. Usar solo si reload no resuelve el problema.
systemctl restart apache2
```

---

## DNS

Para un dominio propio apuntando al VPS:

- `A @ → <IP_VPS>`
- `CNAME www → <DOMINIO>`
- Si se usan subdominios por tenant: `A * → <IP_VPS>` (wildcard — cubre `*.dominio`)

Confirmar la IP del VPS con `curl ifconfig.me` desde el propio servidor, o consultando el proveedor de hosting.

---

## Certificado SSL wildcard (opcional, para subdominios de tenant)

- Cubre `*.dominio` + `dominio`. Se emite con Certbot DNS-01 **manual** (requiere crear registros TXT `_acme-challenge` en el proveedor de DNS):
  ```bash
  certbot certonly --manual --preferred-challenges dns \
    -d '*.<DOMINIO>' -d '<DOMINIO>' \
    --cert-name <DOMINIO>
  ```
- Validación DNS manual → **Certbot no renueva solo**. Repetir el proceso manual (o armar un hook con la API del proveedor de DNS) antes de la fecha de vencimiento del certificado.
- Los registros TXT `_acme-challenge` creados para la validación pueden borrarse después de emitido el certificado (ya no se usan hasta la próxima renovación).
- Para un dominio sin subdominios de tenant alcanza con `certbot --apache -d <DOMINIO> -d www.<DOMINIO>` (renovación automática).

---

## Subdominios por tenant (opcional)

Si el sistema usa subdominios por tenant (ej. `empresa1.midominio.com`):

- Agregar `ServerAlias *.<DOMINIO>` en `<DOMINIO>.conf` y `<DOMINIO>-le-ssl.conf`.
- Simplificar el vhost HTTP para que el redirect a HTTPS no dependa de `SERVER_NAME` exacto:
  ```apache
  RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [END,NE,R=permanent]
  ```
- Verificar con un subdominio cualquiera (ej. `prueba.<DOMINIO>`): HTTP → 301 → HTTPS, certificado válido, Apache sirve el docroot del sistema.
- El routing por hostname lo resuelve `index.php` (ver `CLAUDE.md`, sección "Entry point").

---

## Datos de empresa (tenant) — RUC y razón social, dos campos que se confunden

`public.tenants` tiene **varios** campos parecidos que se editan desde pantallas distintas — es un bug recurrente dejar valores de prueba/placeholder en uno de ellos:

| Campo (columna en `tenants`) | Dónde se edita | Para qué sirve |
|---|---|---|
| `nombre` | Superadmin → Empresas → Editar empresa → "Nombre de la empresa" | Branding/visualización general del sistema (login, sidebar) |
| `business_name` | Admin → Configuración → "Razón social para emitir" | **Es el que se manda a SUNAT** en el XML del comprobante (`AccountingSupplierParty`) |
| `trade_name` | Admin → Configuración → "Nombre del negocio" | Nombre comercial, informativo |
| `ruc` | **Ambas pantallas** (Superadmin y Admin → Configuración) editan la **misma columna** — corregirlo en cualquiera de las dos alcanza |

**Patrón de bug a vigilar**: al dar de alta una empresa nueva, verificar explícitamente que `ruc` y `business_name` (no solo `nombre`) queden con los datos reales de la empresa — es fácil dejarlos con datos de prueba/placeholder sin darse cuenta, ya que "Nombre de la empresa" (Superadmin) no es el mismo campo que alimenta el comprobante SUNAT. SUNAT rechaza el envío (`0111 Rejected by policy`) si el RUC del XML no coincide con el RUC del certificado que firma — ver `config_sunat.md` para el detalle de ese rechazo.

---

## Base de datos de producción

Convención: mismas credenciales que local (`config/database.php` idéntico entre entornos, para no tener que mantener dos configs separados). Credenciales por defecto que espera el sistema: host `localhost`, puerto `5432`, usuario `postgres`, password `1234`, db `gravity`. En un VPS real, cambiar al menos la password de `postgres` **o** aislar el acceso del Postgres solo a `localhost` (lo segundo es lo normal en Linux: `postgresql.conf` → `listen_addresses = 'localhost'`).

Para consultar directo desde el VPS (ya en `/var/www/gravity`):

```bash
PGPASSWORD='<password>' psql -U postgres -d gravity -c "..."
```

---

## Permisos de archivos escribibles por Apache

Después de un `git pull` ejecutado como `root` (lo normal), algunos directorios quedan como `root:root` y Apache (`www-data`) no puede escribir. Corregir después de cada despliegue si se tocaron esos paths:

```bash
chown -R www-data:www-data \
  /var/www/gravity/facturacion/certs/ \
  /var/www/gravity/facturacion/storage/xml/ \
  /var/www/gravity/facturacion/storage/cdr/ \
  /var/www/gravity/modules/banco/img/ \
  /var/www/gravity/assets/uploads/ \
  /var/www/gravity/assets/img/logos/
```

Si al subir un certificado `.pfx` o un logo desde el panel Admin da error de escritura, revisar primero `ls -la` sobre esos directorios en el VPS.

---

## Patrón: default server + certbot --apache para dominios nuevos

Si el VPS aloja varios sitios en la misma IP, **el primer vhost `:443` que Apache carga queda como `default server`**. Cualquier dominio nuevo que se agregue al VPS **sin su propio vhost SSL** va a caer en ese default — sirviendo el certificado y el docroot de otro sitio no relacionado (síntoma: "No es seguro" en el navegador + contenido del sitio equivocado).

**Fix al dar de alta un sitio nuevo con DNS ya apuntando al VPS**:

```bash
certbot --apache -d <DOMINIO_NUEVO> -d www.<DOMINIO_NUEVO>
# opción 2 (Redirect) cuando pregunta por forzar HTTPS
```

Certbot crea `<DOMINIO_NUEVO>-le-ssl.conf`, lo habilita y despliega el certificado. Confirmar con `apache2ctl -S`: el nuevo dominio debe aparecer como `namevhost` propio en `*:443` (ya no cae en el default).

Si el comando falla con `no valid A records found` / `NXDOMAIN`, el dominio nuevo no tiene DNS apuntando al VPS — agregar los registros `A` (y `www`) en el proveedor de DNS primero y reintentar.

---

## Patrón: `schema_name` con límite de 63 caracteres en PostgreSQL

Al crear una sucursal con nombre largo, el `schema_name` generado por `modules/admin/api.php` (acción `sucursal_crear`) puede exceder el límite de **63 caracteres de PostgreSQL para identificadores** — si la lógica concatena/trunca mal, el resultado puede ser inconsistente (ej. slug duplicado o truncado a la mitad de una palabra), dejando una sucursal apuntando a un schema que no es el que uno espera.

Mitigación: validar que `schema_name` quede dentro de los 63 caracteres y sea un identificador único **antes** del INSERT en `public.sucursales`. Si una sucursal existente tiene el schema mal, revisar con:

```bash
PGPASSWORD='1234' psql -U postgres -d gravity -c \
  "SELECT id, nombre, schema_name FROM public.sucursales;"
```
