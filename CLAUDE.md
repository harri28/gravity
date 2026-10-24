# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Gravity** is a multi-tenant pharmacy Point of Sale (POS) web application built with PHP, vanilla JavaScript, and PostgreSQL. It runs on XAMPP (Apache + PHP). It integrates with Peru's SUNAT electronic invoicing system (direct SOAP and via Nubefact API).

## Running the Application

**Prerequisites**: XAMPP with Apache running, PostgreSQL 17 running with the database initialized.

**Database credentials** in `config/database.php`: host `localhost:5432`, db `farmacia`, user `postgres`, pass `1234`.

**First-time production setup**:
```powershell
# Create the database
& "C:\Program Files\PostgreSQL\17\bin\psql.exe" -U postgres -c "CREATE DATABASE farmacia;"
# Run the one-shot deploy script
& "C:\Program Files\PostgreSQL\17\bin\psql.exe" -U postgres -d farmacia -f database/deploy_production.sql
# Then visit: http://localhost/farmacia/database/setup.php for initial tenant/user setup
```

**Applying a migration**:
```powershell
$env:PGPASSWORD = "1234"; & "C:\Program Files\PostgreSQL\17\bin\psql.exe" -U postgres -d farmacia -f database/migration_XX_name.sql
```

**Provisioning a new sucursal schema** (after creating the branch record via Admin UI):
```powershell
$env:PGPASSWORD = "1234"; & "C:\Program Files\PostgreSQL\17\bin\psql.exe" -U postgres -d farmacia -c "SET search_path = sucursal_001; $(Get-Content database/schema_sucursal.sql -Raw)"
# Or in psql: SET search_path = sucursal_001; \i database/schema_sucursal.sql
```

**Login URL**: `http://localhost/farmacia/modules/auth/login.php`  
Login is a 2-step, credentials-first flow: Username/password → then a sucursal picker scoped to that user's own tenant (see "Login flow" below for the full detail — this replaced an older 3-step "pick company → pick branch → enter credentials" wizard that leaked the full sucursal list pre-auth).

**Entry point after login**: `http://localhost/farmacia/modules/ventas/index.php`  
Root `index.php` routes by hostname (production has vanity subdomains per tenant, e.g. `generycpharma.genpharma.cloud`), based on whether the host has 3+ dot-labels and isn't `www`:
- `admin.genpharma.cloud` → `modules/superadmin/login.php`.
- 3+ labels, not `www`, first label matches an **active** `public.tenants.slug` → `modules/auth/login.php`.
- 3+ labels, not `www`, no matching tenant → `includes/error_tenant.php` (standalone "Empresa no encontrada", HTTP 404, no redirect — doesn't hint that a superadmin panel exists).
- Anything else (bare domain, `www`, localhost/dev) → `includes/landing.php` (public marketing page, brand "Gravity", links to `modules/auth/login.php`).

**Important**: the subdomain is purely cosmetic for login purposes — it does **not** restrict which tenant's users can log in there. `modules/auth/login.php` always resolves the tenant from the authenticated user's own `usuarios.tenant_id` (via `modules/auth/api.php`), regardless of which subdomain (or the bare domain) was used to reach it. The only URL-driven rule is that a subdomain matching *no* active tenant slug shows the 404 error page instead of a login form.

The tenant-slug lookup in `index.php` uses its own PDO connection (not `getDB()`), since `getDB()` hard-`die()`s with raw JSON on connection failure instead of throwing — unacceptable on the public landing page. `modules/auth/login.php` enforces the same routing rule independently via `modules/auth/_tenant_context.php` (in case it's hit directly, bypassing `index.php`).

**Login flow (credentials-first, no data leak)**: `modules/auth/login.php` no longer queries or embeds any sucursal data on page load — it's just a username/password form. `modules/auth/api.php` has two actions:
- `validar_credenciales` — checks the password, then returns **every active sucursal of the user's own tenant** (`usuarios.tenant_id`), not filtered by `usuario_sucursal`. Sets `$_SESSION['pending_login_user_id']` (a temporary pre-auth state; `requireAuth()` still rejects everywhere else until step 2 completes).
- `confirmar_sucursal` — re-verifies server-side that the chosen `sucursal_id` belongs to the pending user's tenant (never trusts the browser). **Any user can enter any active sucursal of their own tenant** — there is no per-branch access restriction (deliberate: covering shifts at other branches shouldn't require admin pre-approval per branch). The session's `rol` is taken from *any one* active `usuario_sucursal` row for that user (role is effectively global per user in practice, even though the column is schema-wise per-branch) — if the user has zero `usuario_sucursal` rows at all, login is rejected ("Tu usuario no tiene un rol asignado").

This replaced an older "pick sucursal, then enter credentials" wizard that rendered every sucursal (across all tenants) into the page's HTML/DOM before any authentication, and that also gated entry per-branch via `usuario_sucursal`.

**Production PHP caveat**: the VPS serves this app via Apache mod_php **7.x**, not PHP 8 — confirmed 2026-07-12 after `str_ends_with()` (PHP 8.0+) caused a live 500. Don't use PHP 8+-only syntax (`str_ends_with`/`str_starts_with`/`str_contains`, `match`, `enum`, `?->`, etc.) anywhere in this app (the PHP 8.2 upgrade mentioned in ops notes was for the separate `conexion_sunat/` Laravel app, not this one).

There is no build step, no package manager, and no test runner.

**`scripts/`** — CLI scripts run via `php scripts/<name>.php`. One-shots: `create_superadmin.php [username] [password]`, `list_sucursales.php`, `create_fe_all_schemas.php`/`create_fe_tables.php`/`check_fe_tables.php` (electronic-invoicing table bootstrap/verification per schema). **Recurring (cron)**: `reenviar_sunat_pendientes.php` — worker que reintenta comprobantes sin CDR cada 5 min (ver "Auto-retry a SUNAT" más abajo).

## Architecture

### Multi-Tenancy

The core architectural pattern is PostgreSQL schema-based multi-tenancy:

- **`public` schema** — Global tables shared across all tenants: `tenants`, `sucursales`, `usuarios`, `usuario_sucursal`, `superadmins`, `ubigeo_*`, `fe_tipos_*` catalogs, `ordenes_traslado`, `categorias` (moved from per-branch in migration 21), `cuentas_banco`/`banco_movimientos` (tenant-level bank accounts, migration 28)
- **Per-branch schema** (e.g., `sucursal_001`) — Branch-specific tables: `productos`, `ventas`, `caja`, `ingresos`, `comprobantes_electronicos`, etc.
- `getDB()` runs `SET search_path TO {schema}, public` after login, switching context to the active branch
- Session variables: `tenant_id`, `sucursal_id`, `sucursal_schema`, `rol`

### Migration Pattern

`database/schema_sucursal.sql` defines the full schema for **new** sucursales only. Existing schemas are never touched by it. `migration_22_campos_facturacion_all_schemas.sql` is the authoritative backfill that applied all electronic invoicing columns (on `clientes`, `productos`, `ventas`, `venta_detalles`, `comprobantes_electronicos`, `series_comprobantes`) to pre-existing schemas — run this first if you see "column does not exist" errors for invoicing fields. **When adding columns to `schema_sucursal.sql`, you must also create a numbered migration** (`database/migration_XX_name.sql`) that applies those same changes to all existing schemas using the `DO $$ FOR s IN SELECT schema_name FROM information_schema.schemata WHERE ... LOOP EXECUTE format('ALTER TABLE %I... ADD COLUMN IF NOT EXISTS ...', s); END LOOP; END $$` pattern. Omitting the migration causes "column does not exist" errors in production for all existing branches. **The current highest migration number is 58 (collisions exist on 29 and 50 — don't reuse those numbers); the next migration should be `migration_59_*.sql`.**

**Fixed 2026-09-12** (was a known gap since 2026-07-12): `schema_sucursal.sql` was missing essentially all electronic-invoicing columns that `migration_09`/`migration_22`/`migration_26`/`migration_30` backfill onto existing schemas, plus `productos.fecha_vencimiento` and the `cuentas_por_cobrar`/`cobros_cliente` tables — a brand-new sucursal provisioned before this date needs those migrations (and `migration_21_categorias_global.sql`, see below) re-run against it manually. The base file now includes all of that directly, verified by applying it to a throwaway schema and diffing columns. **Also fixed the same day**: `schema_sucursal.sql` still created a per-branch `categorias` table with `productos.categoria_id` referencing it locally — a leftover from before `migration_21_categorias_global.sql` centralized categories into `public.categorias`. Since `search_path` is `{schema}, public`, a new sucursal's local `categorias` table silently shadowed the shared one. Fixed: `schema_sucursal.sql` no longer creates a local `categorias` table; `productos.categoria_id` now references `public.categorias(id)` directly. Any sucursal created before 2026-09-12 still has the old local table and needs `migration_21_categorias_global.sql` re-run (it's idempotent — skips schemas that no longer have a local `categorias` table).

Some older migrations use unnumbered names (`migration_compras.sql`, `migration_gastos.sql`, `migration_cuentas_cobrar.sql`, `migration_fecha_vencimiento.sql`, etc.) — these predate the numbered scheme and are already applied to the production database.

### Role System

Four roles with separate access gates:

| Role | Access |
|---|---|
| `superadmin` | Multi-tenant panel (`/modules/superadmin/`) — auto-redirected on login |
| `gerente` | Full access including admin panel; treated as admin by `isAdmin()` |
| `admin` | Full pharmacy: ventas, caja, clientes, inventario, almacén, compras, traslados, facturación, admin |
| `cajero` | Restricted: ventas + caja only |

`isAdmin()` returns true for both `admin` and `gerente`. `isGerente()` is a separate check for gerente-only gates.

Page-level gate: set `$required_roles` before including `header.php`; the header calls `requireAuth($required_roles ?? [])`. If `$required_roles` is not set, any authenticated user may access the page.  
API-level gate: `requireApiAuth(['admin'])` inside `api.php` switch cases.

### Request Flow

```
Browser → modules/*/index.php (sets $base_path, $current_module, $current_page, $page_title, $breadcrumb)
              ↓ includes header.php (renders sidebar + topbar based on role)
              ↓ Vanilla JS makes fetch() calls
              ↓ modules/*/api.php?action=<name>
              ↓ PDO queries via getDB()
              ↓ jsonResponse([...]) → JSON
```

### Page Convention

Every PHP page must set these variables **before** `include '../../includes/header.php'`:
- `$base_path` — relative path back to project root (e.g. `'../../'`)
- `$current_module` — controls sidebar active state (e.g. `'ventas'`)
- `$current_page` — controls sub-nav active state (e.g. `'pos'`, `'historial'`)
- `$page_title` — HTML `<title>`
- `$breadcrumb` — topbar breadcrumb HTML string
- `$required_roles` *(optional)* — array of roles allowed; `header.php` calls `requireAuth($required_roles ?? [])`. If omitted, any authenticated user can access. The superadmin module does **not** use `header.php` — it has its own standalone HTML layout and calls `requireAuth(['superadmin'])` directly.

### Key Files

- **`config/database.php`** — PDO singleton (`getDB()`), shared utilities: `jsonResponse()`, `formatMoney()`, `generarNumeroVenta()`, `generarNumeroIngreso()`, `getTenantConfig()` (reads only `nombre_sistema`/`logo_path` from `public.tenant_config`), `registrarAuditoria()` (writes to `public.audit_log`). Note: SUNAT/billing config (RUC, razon social, certificate path, sunat_server, credentials) lives on `public.tenants`, not `tenant_config`.
- **`config/auth.php`** — Session start, `requireAuth()`, `requireApiAuth()`, role helpers (`isAdmin()`, `isGerente()`, `isCajero()`, `isSuperadmin()`), session accessors (`sesionId()`, `sesionNombre()`, `sesionRol()`, `sesionSucursal()`, `sesionTenantId()`, `sesionTenantNombre()`, `sesionSchema()`)
- **`config/sunat.php`** — SUNAT direct integration: `enviar_sunat()` orchestrator, XML builders for UBL 2.1 Invoices and Credit Notes, SOAP/CURL sender, CDR parser
- **`config/mail.php`** — Raw SMTP/STARTTLS implementation (no PHPMailer); `sendMail(to, subject, htmlBody)`. Configure `MAIL_HOST/USERNAME/PASSWORD` constants. Used by `modules/auth/recuperar.php` + `restablecer.php` for password reset.
- **`includes/header.php`** — Outputs full `<html><head>`, sidebar (role-filtered nav), topbar, opens `<main>`. Calls `requireAuth($required_roles ?? [])` — this is the actual page-level auth gate. `style.css` is cache-busted with `?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>` — must use `__DIR__`, not a hardcoded `DOCUMENT_ROOT . '/farmacia/...'` path (that only exists on XAMPP local, not the VPS, and silently breaks the version param, letting production serve stale cached CSS indefinitely — confirmed as a real production bug 2026-07-22).
- **`includes/footer.php`** — Closes `<main>`, sidebar toggle JS, date display
- **`assets/css/style.css`** — Complete design system with CSS custom properties (`--primary`, `--surface-2`, etc.)
- **`assets/js/barcode-scanner.js`** — Global HID keyboard barcode scanner detection; fires `CustomEvent('barcodescan', { detail: { code } })` on `document`. Included by header.php. Keystroke timing: < 50ms between chars + Enter + ≥ 3 chars = scan event.
- **`assets/vendor/`** — Bundled frontend libraries: jQuery 3.7.1, DataTables with Bootstrap 5 skin, Select2. Loaded per-page (not globally) by pages that need them.
- **`modules/compras/pdf.php`** — Renders a printable HTML purchase order; accessed via `?id=N` by the compras module.

### Database Schema

**Number formats:**
- Sale: `V20260317-0001` (from `generarNumeroVenta()`)
- Ingreso: `I20260317-0001` (from `generarNumeroIngreso()`)
- SUNAT boleta: `B001-00001`, factura: `F001-00001` (tracked in `series_comprobantes`)

**`series_comprobantes`** tracks 5 tipos: `boleta` (B001), `factura` (F001), `nota_credito` (NC01), `nota_credito_boleta` (BC01), `nota_credito_factura` (FC01). The nota_credito_boleta/factura series are used by `crear_nota_credito` to determine the series based on the origin document type.

**Product fields:** `codigo` = primary lookup code, `codigo_interno` = internal SKU (defaults to `codigo` if no separate `sku` value is sent), `codigo_barras` = barcode for HID scanner, `product_type` = `'product'` or `'service'` (services use unit code `ZZ` for SUNAT).

**Product tax fields** (used by `ventas/api.php` to build SUNAT line items):
- `afectacion_tipo` — `GRAV` (taxed), `EXO` (exonerated), `INA` (unaffected), `EXP` (export). Derived from `afectacion_igv_codigo` if blank.
- `afectacion_igv_codigo` — SUNAT catalog code (e.g. `'10'` = gravado, `'20'`/`'21'` = exonerado, `'30'-'36'` = inafecto)
- `incluye_igv` — boolean; if false and `GRAV`, price is net and `precio_venta * 1.18` is charged
- `porcentaje_igv` — defaults to 18; stored per-product to support future rate changes
- `icbper_activo` + `factor_icbper` — plastic bag tax (ICBPER), added as a separate charge per unit

**"Clientes Varios" default customer:** Quick ticket sales use a special client record with `numero_documento = '00000000'` and name containing `'CLIENTES VARIOS'`. Created by `migration_12_cliente_varios_default.sql`. `ventasEsClienteVarios()` checks for this pattern; SUNAT requires the buyer to be identified for boletas over S/ 700 and all facturas, so this customer is only allowed for tickets/nota_venta.

**Key stock rules:**
- Stock decremented on `registrar_venta`, restored on `anular_venta`
- `total_vendido` on `productos` increments on sale but is **not** decremented on cancellation
- `cajas` must be `estado = 'abierta'` before sales can be registered — **caja is per-user, not per-branch**: any number of users (cajero, admin, gerente) can each have their own caja open at the same time in the same sucursal; a sale always resolves to the caja opened by `sesionId()`, never "the" open caja. A user still can't have two cajas open at once (same branch or across branches — checked in `caja/api.php`'s `aperturar`)

**PostgreSQL quirk:** Boolean columns return string `'t'`/`'f'` in PDO — JS checks `p.favorito == 't'`.

**All sale/ingreso mutations use** `PDO::beginTransaction()` with rollback on failure.

**`cuentas_por_cobrar` and `cobros_cliente`** — Per-branch tables for accounts receivable. Included in `schema_sucursal.sql` for new branches. For existing branches, apply `migration_19_cuentas_cobrar_todos_esquemas.sql` and `migration_19b`.

### API Modules

Each module has `api.php` with a `switch ($action)` dispatcher:

- **`ventas/api.php`** — `productos`, `buscar_cliente`, `registrar_venta`, `anular_venta`, `historial`, `detalle_venta`, `stats_dia`, `top_vendidos`, `favoritos`, `toggle_favorito`. Contains key business logic helpers: `ventasNormalizarAfectacionTipo()` (derives `GRAV`/`EXO`/`INA`/`EXP` from `afectacion_igv_codigo`), `ventasPrecioUnitarioFinal()` (applies `incluye_igv` gross-up), `ventasEsClienteVarios()` (detects the "00000000" default customer used for ticket sales).
- **`inventario/api.php`** — `stats`, `listar`, `categorias`, `categoria_crear`, `categoria_actualizar`, `categoria_eliminar`, `crear`, `actualizar`, `ajustar_stock`, `toggle_activo`
- **`almacen/api.php`** — `proveedores_listar`, `proveedor_crear`, `proveedor_actualizar`, `proveedor_toggle_activo`, `stats_almacen`, `ingresos_listar`, `ingreso_detalle`, `buscar_producto`, `registrar_ingreso`, `anular_ingreso`, `salidas_listar`, `salida_detalle`, `registrar_salida`, `anular_salida` (bajas de stock sin destino — merma/vencimiento/devolución/otro; transferencias entre sucursales van por el módulo Traslados, no por acá — ver tablas `salidas`/`salida_detalles`, migration_31)
- **`caja/api.php`** — `estado`, `usuarios`, `aperturar`, `cerrar`, `registrar_movimiento`, `movimientos`, `historial`
- **`clientes/api.php`** — `listar_tipos_documento`, `cliente_listar`, `cliente_obtener`, `cliente_crear`, `cliente_actualizar`, `cliente_toggle_activo`, `cliente_consultar_documento` (calls Factiliza API for DNI/RUC lookup)
- **`admin/api.php`** — `ubigeo_resolver`, `usuarios_listar`, `usuario_crear`, `usuario_actualizar`, `usuario_cambiar_password`, `usuario_toggle_activo`, `asignar_acceso`, `revocar_acceso`, `sucursales_listar`, `sucursal_crear`, `sucursal_actualizar`, `sucursal_toggle_activo`, `config_obtener`, `config_guardar`, `logo_subir`, `logo_eliminar`, `certificate_subir`, `auditoria_listar`
- **`facturacion/api.php`** — `stats`, `reporte`, `exportar` (CSV download), `detalle_venta_ticket`, `notas_reporte`, `tipos_nota_credito`, `documentos_origen_nota_credito`, `crear_nota_credito` (calls `enviar_sunat()`), `reenviar_sunat`, `stats_usuario`, `usuarios_lista`, `categorias_lista`, `rentabilidad_stats`, `rentabilidad_categorias`, `rentabilidad_productos`, `rentabilidad_tendencia`
- **`compras/api.php`** — Purchase orders: `stats`, `ordenes_listar`, `orden_detalle`, `orden_crear`, `orden_cambiar_estado`, `orden_recibir` (converts approved OC → ingreso + optional `cuentas_por_pagar`); Accounts payable: `cuentas_listar`, `cuenta_detalle`, `registrar_pago`; Accounts receivable: `stats_cobrar`, `cuentas_cobrar_listar`, `cuenta_cobrar_detalle`, `registrar_cobro`, `cuenta_cobrar_crear`
- **`dashboard/api.php`** — `resumen` (sales/inventory/caja daily summary), `ventas_semana`, `ventas_metodo_pago`, `stock_alertas`, `top_vendidos`, `ultimas_ventas`
- **`banco/api.php`** — Tenant-level bank accounts (`public.cuentas_banco`/`banco_movimientos`): `cuentas_listar`, `cuenta_crear`, `cuenta_actualizar`, `cuenta_imagen_subir`, `cuenta_detalle`, `movimientos_listar`, `grafico_mensual`, `movimiento_crear`
- **`ecommerce/api.php`** — `categorias`, `productos`, `stock`. **Unauthenticated and CORS-open by design** (no `requireApiAuth`, `Access-Control-Allow-Origin: *`) — it's a public read-only catalog/stock feed meant for an external storefront (Selvadigital), takes `?schema=` directly from the query string (sanitized to `[a-zA-Z0-9_]`) instead of the session. Don't add write actions or assume it's behind auth like the other modules.

### "Mi Comprobante" (public receipt lookup)

`mi-comprobante.php` (project root, **unauthenticated by design**, same rationale as `ecommerce/api.php`) — lets a customer view their own receipt without logging in (SUNAT requires the buyer be able to consult their comprobante). Reached via `?t=<32-hex token>`, never by sequential ID — a receipt can reveal what someone bought (potentially sensitive at a pharmacy), so lookup must not be enumerable.

- `public.comprobante_tokens` (token, schema_name, venta_id) is the resolution table — it exists specifically so this page can find the right per-branch schema **without** knowing the tenant/subdomain first. The link works from the bare root domain, not the tenant subdomain.
- The token is generated in `ventas/api.php`'s `registrar_venta` (`bin2hex(random_bytes(16))`) for every sale (ticket/boleta/factura alike) and returned as `comprobante_token` in the API response.
- `modules/ventas/index.php`'s `enviarWhatsApp()` builds the public URL via `buildComprobanteUrl()`, which strips the tenant subdomain down to the root domain (`generycpharma.genpharma.cloud` → `genpharma.cloud`) so the link works regardless of which subdomain the sale was made from.
- `app_public_prefix()` (`config/database.php`) computes the local-vs-production path prefix (same technique as `sunat_public_base_url()` in `config/sunat.php`) so the generated links work on both XAMPP (`/farmacia/...`) and the VPS (no prefix) without hardcoding either.
- Also rendered as a QR code on the printed ticket (80mm) and A4 formats (`buildTicketHTML()`/`buildTicketA4HTML()`), bottom of the receipt, 40% QR / 60% URL text split — generated client-side via the vendored `assets/vendor/qrcode/qrcode.min.js` (davidshimjs/qrcodejs), no external network call at print time. Only rendered on the real post-sale ticket (`showTicket()`), not on the pre-sale preview (no token exists yet).

### Electronic Invoicing

Two integration paths:

**Direct SUNAT SOAP (`config/sunat.php`):**
- Builds UBL 2.1 XML in memory
- Signs XML with RSA certificate (`.pfx` from `facturacion/certs/`)
- ZIPs and POSTs via SOAP CURL to SUNAT endpoint
- Parses CDR response and updates `comprobantes_electronicos`
- Endpoint selected by `sunat_server` field on `public.tenants`: `'1'` = production (`e-factura.sunat.gob.pe`), `'3'` (default) = beta sandbox (`e-beta.sunat.gob.pe`). Configure via Admin → Configuración tab.

**`facturacion/` directory:**
- `index.php` — Reporte de Ventas (sales report + inline nota_credito modal)
- `notas_credito.php` — Standalone Notas de Crédito management page
- `rentabilidad.php` — Profitability analysis (margin, ROI, trends by category/product)
- `signature.php` + `api_signature/XMLSec*.php` — RSA XML signing library
- `certs/` — `.pfx` certificate files
- `storage/xml/` — Generated XML/ZIP files
- `storage/cdr/` — SUNAT CDR response files

**`comprobantes_electronicos` nota_credito columns** — `referencia_comprobante_id`, `tipo_nota_credito_id`, `codigo_tipo_nota_credito`, `motivo_nota_credito`, `descripcion_nota_credito`, `documento_modificado_*` — these power both `notas_reporte` and `crear_nota_credito`. They exist in `schema_sucursal.sql` but required `migration_23_notas_credito_comprobantes.sql` to be added to pre-existing schemas.

**Auto-retry a SUNAT (worker + migration_58)** — Cuando `enviar_sunat()` falla por error de red/timeout (sin CDR, sin response_code), `registrar_venta` setea `reenvio_proximo_at = NOW() + 5 min` sobre `comprobantes_electronicos`. El script `scripts/reenviar_sunat_pendientes.php` (CLI, se corre vía cron del VPS cada 5 min) itera sobre todos los esquemas sucursal, llama a `reenviarSunatComprobante()` (en `modules/facturacion/_sunat_resend_helpers.php`) y reintenta. Max 20 intentos cada 5 min por comprobante (~1h40m). Si SUNAT devuelve rechazo permanente (response_code ≠ 0) deja de reintentar al toque. Al agotar los 20 intentos, se setea `reenvio_proximo_at = NULL` y requiere acción manual con el botón "Reenviar a SUNAT". El tab **Reenvíos SUNAT** del módulo Facturación (solo admin/gerente) muestra la lista en vivo con stats, intentos usados, próximo intento y último error. Columnas en `comprobantes_electronicos`: `reenvio_intentos`, `reenvio_proximo_at`, `reenvio_ultimo_at`, `reenvio_ultimo_error`. Crontab a configurar en el VPS: `*/5 * * * * /usr/bin/php /var/www/farmacia/scripts/reenviar_sunat_pendientes.php >> /var/log/farmacia/reenviar_sunat.log 2>&1`. El worker usa `flock()` sobre `/tmp/farmacia_reenviar_sunat.lock` para evitar superposición (una corrida puede tardar más de 5 min si varios comprobantes dan timeout de 60s cada uno). Los helpers `facturacionCargar{Comprobante,Cliente,Items}Venta` y `facturacionNormalizarAfectacionTipo`/`ExtraerResponseCode` viven en `modules/facturacion/_data_helpers.php` (compartidos entre `api.php` y `_sunat_resend_helpers.php`).

### Stock Transfers (Traslados)

Tables in `public` schema (cross-branch): `ordenes_traslado`, `orden_traslado_detalles`.

Flow: `Borrador → Enviado` (locks items, decrements origin stock) → `Recibido` (increments dest stock) or `Anulado`.

### Modules Status

- **Fully implemented**: Ventas (POS + Historial + Favoritos), Inventario (tabbed: Inventario + Categorías), Almacén (tabbed: Ingresos + Salidas + Proveedores + Traslados — `proveedores.php` is no longer standalone), Caja, Clientes, Admin (4 tabs: Usuarios + Sucursales + Configuración + Auditoría), Facturación (Reporte de Ventas + Notas de Crédito + Rentabilidad), Traslados, Dashboard, Compras (purchase orders + cuentas por pagar + cuentas por cobrar), Banco (bank accounts + movements, admin/gerente only), Ecommerce (public read-only JSON API explorer for an external storefront — see `ecommerce/api.php` note above)

### Co-located Separate App

`conexion_sunat/` is a **standalone Laravel 12 application** (PHP 8.2+, Greenter 5.1) for SUNAT e-invoicing via OAuth2. It is a separate codebase co-located in this repo — it has its own `composer.json`, migrations, and `artisan` commands. It is not loaded by or connected to the main PHP app at runtime.
