# Migración Gravity → KaraokeSystem

Playbook para clonar `c:\xampp\htdocs\farmacia` a `c:\xampp\htdocs\karaoke`, reutilizando todos los módulos excepto el flujo de **Ventas** (que pasa a ser **Comandas de mesa/sala**).

> **Cómo usar este archivo**
> - Fases 1–3: pasos ejecutables, uno detrás del otro.
> - Fase 4: referencia de qué módulos quedan intactos (contexto para no re-trabajar).
> - Secciones finales: lista exhaustiva de archivos `.md` y reglas de negocio que cambian.
> - Este archivo vive dentro del proyecto farmacia a propósito: al copiar la carpeta, viaja con el clon y queda disponible en `karaoke/KARAOKE_MIGRATION.md`.
> - **Importante**: antes de cualquier edición de código, esperar `sí`/`impleméntalo` del usuario (regla `feedback_confirm_before_coding`).

---

## FASE 1 — Copia física del proyecto

### 1.1 Copia la carpeta

```powershell
# Desde PowerShell, con XAMPP corriendo (Apache puede quedar prendido, no estorba)
Copy-Item -Path "c:\xampp\htdocs\farmacia" -Destination "c:\xampp\htdocs\karaoke" -Recurse
```

### 1.2 Limpieza post-copia (dentro de `c:\xampp\htdocs\karaoke`)

Borrar lo que no debe arrastrarse de la farmacia:

- `.git/` → `Remove-Item -Recurse -Force .git` (después `git init` si querés repo propio).
- `facturacion\storage\xml\*` y `facturacion\storage\cdr\*` → XMLs/CDRs de la farmacia (dejar las carpetas vacías).
- `facturacion\certs\*.pfx` y `*.pfx.bak_legacy` → certificado SUNAT de la farmacia (el karaoke usa el suyo).
- `conexion_sunat\vendor\` y `conexion_sunat\.env` si existieran (es un sub-app Laravel aparte; si no la vas a usar, podés borrar la carpeta entera).
- `assets\uploads\` (logos/imágenes de productos de la farmacia).
- `datos_sistema_origen\`, `nueva_botica\`, `resumen_sesion_*.md`, `Presentacion_Gravity*.pptx`, `aprendizaje_importacion_productos.md`, `avcance xd.docx`, `Requisitos funcionales Farmacia.docx`, `conexion_sunat.zip` → ruido histórico de la farmacia.
- `.claude\worktrees\` → sesiones viejas de Claude Code.
- `MIGRACION_NUEVO_DISCO.md` → irrelevante para el karaoke.

### 1.3 Verificación de que levanta

- Abrir `http://localhost/karaoke/` → debería mostrar el landing (todavía dice "Gravity"; eso se arregla en Fase 3).
- Abrir `http://localhost/karaoke/modules/auth/login.php` → debería renderizar el login (va a fallar al autenticar porque la BD aún apunta a `farmacia`; eso se arregla en Fase 2).

### 1.4 Aislar la sesión PHP (crítico — comparten mismo Apache)

Para que loguearse en `/karaoke` no pise la sesión de `/farmacia` y viceversa:

- Editar `config/auth.php` **del karaoke** y poner `session_name('KARAOKESESSID');` **antes** del `session_start()`.
- Dejar la farmacia como está (usa el nombre default `PHPSESSID`).

Sin esto, las sesiones se solapan al tener la misma cookie bajo `localhost`.

---

## FASE 2 — Base de datos nueva

### 2.1 Crear la BD

```powershell
& "C:\Program Files\PostgreSQL\17\bin\psql.exe" -U postgres -c "CREATE DATABASE karaoke;"
```

### 2.2 Apuntar la copia a la BD nueva

Editar `c:\xampp\htdocs\karaoke\config\database.php` → cambiar `$dbname` de `farmacia` a `karaoke`.

### 2.3 Desplegar schema base

```powershell
$env:PGPASSWORD = "1234"
& "C:\Program Files\PostgreSQL\17\bin\psql.exe" -U postgres -d karaoke -f "c:\xampp\htdocs\karaoke\database\deploy_production.sql"
```

Esto crea `public` (tenants, usuarios, catálogos SUNAT, ubigeos) y provisiona `sucursal_001`.

### 2.4 Setup inicial (primer tenant/usuario)

Abrir `http://localhost/karaoke/database/setup.php` y seguir el wizard. Datos del tenant → los del karaoke (RUC, razón social, dirección).

### 2.5 Ligeros cambios de BD (zona del usuario)

> El usuario mencionó que acá hará ajustes puntuales. Dejar documentados acá los que decida hacer para que queden trazables:
>
> - [ ] (pendiente) …
> - [ ] (pendiente) …
>
> Sugeridos (no aplicar sin confirmación):
> - Renombrar `productos.codigo_barras` a algo neutro o esconderlo del UI (en barra de karaoke el escáner HID casi no se usa).
> - Dejar `fecha_vencimiento` igual (algunos insumos sí vencen: lácteos para piqueos).
> - **No tocar** `comprobantes_electronicos`, `series_comprobantes`, catálogos SUNAT — se reutilizan 1:1.

---

## FASE 3 — Rebrand (obligatorio)

### 3.1 Nombre del sistema y logo

- Panel Admin → Configuración → cambiar `nombre_sistema` (lo lee `getTenantConfig()`) y subir logo. Esto afecta header/topbar sin tocar código.

### 3.2 Reemplazos textuales en el código

Hacer buscar-y-reemplazar (verificando archivo por archivo, no ciego) de estos strings dentro de `c:\xampp\htdocs\karaoke\`:

| Buscar | Reemplazar por | Dónde aparece (parcial) |
|---|---|---|
| `Gravity` | `KaraokeSystem` (o nombre elegido) | `includes/landing.php`, `includes/header.php`, `modules/auth/login.php`, `includes/error_tenant.php` |
| `/farmacia/` | `/karaoke/` | `config/database.php` (`app_public_prefix`), `config/sunat.php` (`sunat_public_base_url`), posibles rutas en JS |
| `farmacia` (como palabra suelta en textos UI) | `karaoke` | Revisar grep caso por caso — no todos son texto UI |
| `botica` / `Botica` | `karaoke` / `Karaoke` | Textos de UI, títulos |

Comando sugerido para localizar ocurrencias antes de reemplazar:

```powershell
# Desde c:\xampp\htdocs\karaoke
Select-String -Path "*.php","**\*.php","**\*.js","**\*.html" -Pattern "Gravity|/farmacia/|Botica" -SimpleMatch
```

### 3.3 Favicon, títulos, meta

- `includes/header.php` → `<title>` y `<link rel="icon">`.
- `includes/landing.php` → copy del landing (hero, features). Reescribir para karaoke.
- `includes/error_tenant.php` → "Empresa no encontrada" se queda, pero el footer del brand cambia.

### 3.4 Sidebar y nav

En `includes/header.php` se arma el menú por rol. Renombrar entradas:

- "Ventas" → "Mesas" o "Comandas".
- "Almacén" / "Compras" / "Caja" / "Clientes" / "Admin" / "Facturación" / "Banco" / "Dashboard" → se quedan igual.
- "Traslados" → esconder si es un solo local; dejar si hay varias sedes.
- "Ecommerce" → esconder (es específico de Selvadigital, no aplica a karaoke).

### 3.5 Archivos `.md` del proyecto a reescribir / borrar

Ver sección **"Archivos .md a modificar"** más abajo.

---

## FASE 4 — Reutilización de módulos (contexto, no acción)

Lo que **no se toca** y sirve igual en el karaoke:

| Módulo | Estado en karaoke |
|---|---|
| `modules/auth/` (login 2-step, recuperar password) | Idéntico |
| `modules/caja/` (apertura/cierre, movimientos, caja por usuario) | Idéntico |
| `modules/clientes/` (DNI/RUC, Factiliza, Clientes Varios) | Idéntico — SUNAT exige lo mismo |
| `modules/inventario/` (productos, categorías, toma de inventario, ajuste rápido) | Idéntico |
| `modules/almacen/` (ingresos, salidas, proveedores) | Idéntico |
| `modules/compras/` (OCs, cuentas por pagar, cuentas por cobrar) | Idéntico |
| `modules/facturacion/` (SUNAT, notas de crédito, rentabilidad, auto-retry worker) | Idéntico (solo cambia el certificado `.pfx`) |
| `modules/dashboard/` | Idéntico (los nombres de métricas sirven igual) |
| `modules/banco/` (cuentas bancarias tenant-level) | Idéntico |
| `modules/admin/` (usuarios, sucursales, auditoría, config) | Idéntico |
| `modules/superadmin/` (panel multi-tenant) | Idéntico |
| `mi-comprobante.php` + `comprobante_tokens` + QR en ticket | Idéntico |
| `scripts/reenviar_sunat_pendientes.php` (worker + cron) | Idéntico |
| `assets/js/barcode-scanner.js` | Queda (poco uso, pero no estorba) |

**Lo único que se reemplaza**: `modules/ventas/` → convertirlo en flujo de **comandas** (mesa/sala abierta con items que se agregan en el tiempo, cierre dispara la boleta/factura al mismo `registrar_venta` SUNAT que ya existe).

### Modelo de comandas sugerido (para fase futura, no es parte de la copia)

Tablas nuevas en el schema de sucursal:

- `salas` (id, nombre, capacidad, tarifa_hora, estado)
- `comandas` (id, sala_id, mozo_id, hora_apertura, hora_cierre, estado, propina_monto, total, venta_id nullable)
- `comanda_items` (id, comanda_id, producto_id, cantidad, precio_unitario, hora_pedido, estado)

El cierre de comanda → arma el payload que hoy consume `registrar_venta` → reutiliza TODO el pipeline SUNAT sin tocar.

---

## Archivos `.md` a modificar

### Reescribir totalmente

| Archivo | Qué hacer |
|---|---|
| `CLAUDE.md` | Reescribir para describir el karaoke (modelo de comandas, salas, propina, cobro por hora). Mantener secciones: Multi-tenancy, Role System, Request Flow, Page Convention, Key Files, DB Schema, API Modules, Electronic Invoicing. Reemplazar la sección "Modules Status" y la de "Ventas" por el flujo de comandas. |
| `README.md` | Rebrand y propósito del sistema. |
| `AGENTS.md` | Si contiene reglas de agentes atadas al dominio farmacia, revisar. Si es genérico, dejar. |
| `reglas del negocio.md` | Reescribir sección "Reglas de ventas" por "Reglas de comandas" + agregar reglas de salas, cobro por hora, propina, división de cuenta. Resto (multi-tenencia, roles, caja, stock, clientes, impuestos, SUNAT) queda igual. |

### Dejar igual (son documentación técnica agnóstica)

| Archivo | Motivo |
|---|---|
| `database/orden_facturacion_electronica.md` | Orden de aplicación de migraciones SUNAT — vale igual |
| `facturacion/README.md` | Doc técnica de la carpeta facturación — vale igual |
| `config_sunat.md` | Guía de configuración SUNAT — vale igual |
| `credenciales_sunat.md` | Reemplazar credenciales por las del karaoke, pero el formato queda |
| `config_vps.md` | Deployment al VPS — vale igual |
| `conexion_sunat/README.md` | Sub-app Laravel aparte |

### Borrar (ruido histórico de la farmacia)

- `resumen_sesion_2026-07-12.md`
- `resumen_sesion_2026-07-28.md`
- `MIGRACION_NUEVO_DISCO.md`
- `aprendizaje_importacion_productos.md` (si todavía existiera)

### Crear nuevos (en el karaoke)

- `KARAOKE_COMANDAS.md` (futuro) — spec detallada del modelo de comandas, salas y cobro por hora.
- `reglas del negocio.md` → mantener nombre pero reescrito (ver arriba).

---

## Reglas de negocio a cambiar

> Base: `reglas del negocio.md` del farmacia. Se listan solo las secciones que **cambian**; el resto se queda igual.

### CAMBIA — "Reglas de ventas" → "Reglas de comandas"

- La venta ya no es un cobro inmediato. Se abre una **comanda** por sala/mesa, se agregan ítems en el tiempo, y al cerrar la comanda se emite el comprobante (ticket/boleta/factura) con el total consolidado.
- Una comanda tiene estados: `abierta` → `cerrada` (= cobrada, documento emitido) o `anulada`.
- Mientras la comanda está `abierta`, los ítems se pueden agregar/quitar sin tocar stock de forma "comprometida" (decisión a tomar: reservar stock al pedir vs descontar solo al cerrar — recomendado: descontar al cerrar, igual que hoy).
- Una sala no puede tener dos comandas `abierta` en simultáneo.
- La propina se registra aparte y **no va a SUNAT**.
- División de cuenta: una comanda puede cerrarse con múltiples métodos de pago (ya soportado por caja; falta UI).

### CAMBIA — "Reglas de productos"

- Dos familias explícitas:
  - **Consumibles** (`product_type = 'product'`): tragos, cervezas, piqueos — con stock.
  - **Servicios** (`product_type = 'service'`): hora de sala, cover, cargo por canción extra — sin stock, unidad SUNAT `ZZ`.
- `codigo_barras` queda como campo opcional (poco uso en karaoke).
- `fecha_vencimiento` se mantiene (lácteos, insumos perecibles de piqueos).

### CAMBIA — "Cobro por tiempo de sala" (regla nueva)

- La hora de sala se cobra como un ítem `service` auto-generado al cerrar la comanda.
- Base: `tarifa_hora` de la tabla `salas` × horas transcurridas (fracción se cobra completa, redondeo hacia arriba por configuración).
- Si hay happy hour / tarifa diferenciada por horario, es lógica a definir en fase 2.

### CAMBIA — "Roles"

- Agregar rol opcional `mozo` (toma pedidos, no cobra ni abre caja). Alternativa: no agregar rol y usar `cajero` para todos los operativos.

### CAMBIA — "Ecommerce (API pública)"

- Esconder del sidebar. Si no se va a usar, considerar borrar `modules/ecommerce/` entero.

### SE QUEDA IGUAL

- Multi-tenencia (schema por sucursal).
- Roles base: `superadmin`, `gerente`, `admin`, `cajero`.
- Reglas de caja (apertura por usuario, caja abierta para operar).
- Reglas de stock (descuento al vender, restauración al anular, traslados, toma de inventario, ajuste rápido).
- Reglas de clientes (Clientes Varios, S/700 boleta, factura siempre identificada).
- Reglas de impuestos (`GRAV`/`EXO`/`INA`/`EXP`, `incluye_igv`, ICBPER).
- Numeración de documentos (B001, F001, NC01, BC01, FC01).
- Flujo SUNAT (envío directo + auto-retry worker).
- Mi Comprobante público (`mi-comprobante.php` + QR + token).

---

## Checklist consolidado

```
Fase 1 — Copia
[ ] 1.1 Carpeta copiada farmacia → karaoke
[ ] 1.2 Limpieza (.git, storage SUNAT, certs, uploads, md históricos)
[ ] 1.3 Levanta http://localhost/karaoke/
[ ] 1.4 session_name('KARAOKESESSID') en config/auth.php del karaoke

Fase 2 — BD
[ ] 2.1 CREATE DATABASE karaoke
[ ] 2.2 config/database.php → $dbname = 'karaoke'
[ ] 2.3 deploy_production.sql ejecutado sobre karaoke
[ ] 2.4 setup.php completado (tenant + usuario inicial)
[ ] 2.5 Cambios menores de BD documentados (pendiente)

Fase 3 — Rebrand
[ ] 3.1 nombre_sistema + logo cargados desde Admin
[ ] 3.2 Reemplazos textuales (Gravity, /farmacia/, botica)
[ ] 3.3 Favicon + title + landing reescritos
[ ] 3.4 Sidebar: "Ventas" → "Mesas", Ecommerce oculto, Traslados oculto si aplica
[ ] 3.5 .md reescritos (CLAUDE.md, README.md, reglas del negocio.md) + ruido borrado

Fase 4 — Comandas (fase posterior, fuera del alcance de la copia)
[ ] 4.1 Diseñar tablas: salas, comandas, comanda_items
[ ] 4.2 Migración numerada que las crea en public/sucursal_001
[ ] 4.3 UI "Mesas abiertas" + abrir/agregar/cerrar comanda
[ ] 4.4 Cierre de comanda → reutilizar registrar_venta existente
[ ] 4.5 Cobro por hora de sala (service item auto-generado)
[ ] 4.6 Propina (campo aparte, fuera de SUNAT)
[ ] 4.7 División de cuenta (UI de cierre con múltiples métodos de pago)
```

---

## Riesgos conocidos

- **Sesiones compartidas** si no se cambia `session_name()` (Fase 1.4) — el mismo browser logueado en `/farmacia` pisa `/karaoke`.
- **PHP 7 en producción** (VPS): no usar `str_contains`, `match`, enums, `?->`, `str_ends_with`, `str_starts_with` en nada del karaoke tampoco. Mismo caveat.
- **Certificado SUNAT**: el `.pfx` del farmacia no sirve para el karaoke (RUC distinto). Subir el del karaoke por Admin → Configuración → Certificado.
- **Numeración de comprobantes**: `series_comprobantes` arranca desde 1 en la BD nueva. Si el karaoke ya emitía comprobantes antes (sistema anterior), hay que setear el correlativo manualmente para no repetir números.
- **`conexion_sunat/`**: si no se usa, borrarla; si se usa, es un Laravel aparte que necesita su propio `composer install` y `.env`.
- **Worker cron de auto-retry**: en producción habrá que configurar un cron idéntico pero con la ruta del karaoke y su propio archivo de lock (`/tmp/karaoke_reenviar_sunat.lock`, no el del farmacia).
