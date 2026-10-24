# Reglas del Negocio — Gravity

## Multi-tenencia
- Cada empresa (tenant) puede tener varias sucursales; cada sucursal tiene su propio schema de PostgreSQL (`sucursal_001`, `sucursal_002`, ...).
- Las tablas globales (`tenants`, `sucursales`, `usuarios`, `categorias`, etc.) viven en el schema `public` y son compartidas entre todas las sucursales del tenant.
- Cada sucursal tiene su propio inventario, ventas, caja y comprobantes — no se comparten entre sucursales salvo por traslados.

## Roles y accesos
| Rol | Acceso |
|---|---|
| `superadmin` | Panel multi-tenant, gestiona todas las empresas |
| `gerente` | Acceso completo, igual que admin (incluye panel de administración) |
| `admin` | Ventas, caja, clientes, inventario, almacén, compras, traslados, facturación, admin |
| `cajero` | Solo ventas y caja |

## Reglas de caja
- No se puede registrar una venta si la caja no está en estado `abierta`.
- Los movimientos de caja (ingresos/egresos manuales) quedan registrados por separado de las ventas.

## Reglas de stock
- El stock se descuenta al registrar una venta y se restaura al anularla.
- `total_vendido` se incrementa con cada venta pero **no** se revierte al anular (es un contador histórico, no de stock).
- Los traslados entre sucursales siguen el flujo: `Borrador → Enviado` (bloquea ítems y descuenta stock de origen) → `Recibido` (incrementa stock en destino) o `Anulado`.
- El stock "formal" (con rastro/auditoría) entra o sale por **Almacén** (Ingresos con proveedor/costo, Salidas con motivo) o por una venta/traslado.
- Además existen dos formas de corrección manual **rápida**, sin generar registro en Ingresos/Salidas de Almacén, y **restringidas solo a `admin`/`gerente`**:
  - El botón de ajuste rápido (ícono ⚙) en Inventario → tabla de productos.
  - El campo Stock del formulario "Editar Producto" (al crear un producto nuevo, el campo sí es editable por cualquier rol con acceso a Inventario, ya que ahí no hay valor previo que sobrescribir).
- Estas dos correcciones manuales están pensadas como ajustes puntuales (ej. conteo físico, error de digitación), no como reemplazo del flujo de Ingresos/Salidas de Almacén.
- **Pendiente de decisión (feedback de cliente, 2026-07-23):** hoy la venta se bloquea si `stock < cantidad` (`ventas/api.php`, acción `registrar_venta`), incluso con stock 0 — aplica a todos los roles por igual. El cliente reporta que el cajero muchas veces no puede actualizar el stock (lo hace la jefa al cierre de turno) y hay productos que se registran tarde, por lo que pide poder vender igual aunque el stock figure en 0. No implementado aún porque permitir stock negativo rompe las alertas actuales de "agotados" (`stock = 0` exacto) y "stock bajo" (`stock > 0 AND stock <= stock_minimo`), que dejarían de detectar esos productos. Opciones a evaluar más adelante: (a) permitir stock negativo y ajustar esas alertas a `stock <= 0`/`stock <= stock_minimo` sin exigir `stock > 0`, (b) agregar una confirmación explícita al vender sin stock en vez de bloquear en silencio, o ambas.

## Toma de Inventario (conteo físico por sesión)
- Reemplaza el viejo flujo de descarga/subida de Excel. Vive como una pestaña dentro de Inventario, **visible solo para admin/gerente**.
- Se crea una **sesión** (`toma_inventario_sesiones`) eligiendo solo categorías (sin plazo/fecha límite — se quitó esa dependencia, ver más abajo). Al crearla se siembra una fila por cada producto activo de esas categorías (`toma_inventario_detalles`), con el stock del sistema en ese momento como referencia — es un snapshot fijo: productos agregados a la categoría después no aparecen en esa sesión.
- **Ya no existe plazo/fecha límite ni el estado "Vencida"** (quitado en `migration_44_toma_inventario_sin_plazo.sql`): una sesión "Activa" lo sigue siendo indefinidamente hasta que un admin la cierra o cancela manualmente, sin presión de tiempo. Las sesiones creadas antes de este cambio conservan su `plazo_dias`/`fecha_limite` histórico en la base (no se borró), pero el sistema ya no los lee ni los usa.
- El conteo físico se guarda aparte de `productos.stock` (autosave por fila, sin botón "Guardar" general) y **no toca el stock real** hasta que se aplica esa fila puntual.
- **El ajuste de stock se aplica producto por producto** (botón ✓ en cada fila, `toma_aplicar_producto`), no todo junto al cerrar la sesión — y **reemplaza directamente el stock por el número contado** (`stock = cantidad_contada`), no es un ajuste por diferencia contra la foto de cuando se creó la sesión. Se cambió de "por delta" a "reemplazo directo" (`migration_45_toma_inventario_stock_absoluto.sql`) porque si el stock real cambiaba entre crear la sesión y aplicar el conteo (ej. una venta en el medio), el resultado no coincidía con el número físicamente contado — confirmado con datos reales de producción. Antes de sobrescribir, se guarda el stock previo en `stock_antes_aplicar` para que "Editar" pueda revertir exactamente a ese valor (no al snapshot original de la sesión). Una fila ya aplicada no se puede volver a editar directamente — hay que usar el botón ✏️ "Editar" para reabrirla (revierte a `stock_antes_aplicar` y permite volver a contar/aplicar).
- **Cerrar la sesión** (`toma_aplicar`, botón "Cerrar sesión") **no aplica nada por sí solo** — solo marca la sesión como `completada`. Los productos contados pero no aplicados (✓) y los que no se contaron **no modifican el stock**; el modal de confirmación lo advierte explícitamente antes de cerrar. Una sesión cerrada o cancelada no se puede reabrir ni editar — si algo quedó sin aplicar, la corrección posterior se hace con el Ajuste de Stock normal.
- **No hay bloqueo técnico de un solo usuario**: cualquier admin/gerente puede abrir una sesión activa y seguir contando, aunque en la práctica la trabaje una sola persona. Si dos personas cuentan el mismo producto casi al mismo tiempo, gana el último guardado — riesgo conocido y aceptado, no un bug.
- El cierre y cada aplicación individual quedan registrados en Auditoría (`public.audit_log`), pero el conteo línea-por-línea no genera ningún registro en Ingresos/Salidas de Almacén — es, igual que el Ajuste de Stock rápido, una corrección paralela al flujo formal de Almacén.

## Reglas de clientes
- Existe un cliente por defecto **"Clientes Varios"** (`numero_documento = '00000000'`) para ventas rápidas (tickets/nota de venta) sin identificar al comprador.
- SUNAT exige identificar al comprador en:
  - Boletas mayores a **S/ 700**
  - Todas las facturas
- Por lo tanto, "Clientes Varios" solo puede usarse en tickets/nota de venta que no superen ese límite y no requieran factura.

## Reglas de impuestos por producto
- `afectacion_tipo`: `GRAV` (gravado con IGV), `EXO` (exonerado), `INA` (inafecto), `EXP` (exportación).
- Si `incluye_igv = false` y el producto es `GRAV`, el precio registrado es neto y se cobra `precio_venta * 1.18`.
- `porcentaje_igv` se guarda por producto (por defecto 18%) para soportar cambios futuros de tasa.
- ICBPER (bolsa plástica): si `icbper_activo` está activo, se cobra un cargo adicional por unidad según `factor_icbper`.

## Numeración de documentos
- Venta interna: `V20260317-0001`
- Ingreso de almacén: `I20260317-0001`
- Boleta SUNAT: `B001-00001`
- Factura SUNAT: `F001-00001`
- Notas de crédito: series `NC01` (genérica), `BC01` (origen boleta), `FC01` (origen factura) — la serie se determina según el tipo de documento que se está afectando.

## Facturación electrónica (SUNAT)
- Todo comprobante (boleta/factura/nota de crédito) se envía a SUNAT vía SOAP directo o Nubefact, según configuración del tenant.
- El ambiente (producción vs beta) se configura por tenant en `sunat_server` (`'1'` = producción, `'3'` = beta/sandbox, valor por defecto).
- Las notas de crédito requieren un documento de origen (boleta o factura) y un motivo/tipo válido del catálogo SUNAT.

## Integridad transaccional
- Todas las mutaciones de ventas e ingresos (registrar, anular) se ejecutan dentro de una transacción (`BEGIN`/`COMMIT`/`ROLLBACK`) para evitar estados inconsistentes entre stock, caja y comprobantes.

## Convención UI: inputs numéricos editables en tablas (Cantidad, P. Unitario, etc.)
- **No usar `<input type="number">`** para campos numéricos editables dentro de filas de tabla — Chrome no permite mover el cursor programáticamente (`setSelectionRange`) en inputs `type="number"`, lo que impide controlar dónde queda el cursor de texto (caret).
- Usar en su lugar `type="text" inputmode="decimal"` (mantiene el teclado numérico en celular) + un filtro en `oninput` que limpie el valor a solo dígitos (y un punto decimal, salvo que el campo sea explícitamente sin decimales — ver Toma de Inventario abajo).
- Al enfocar el campo, el cursor debe quedar **al final del valor** (para poder borrar con backspace de inmediato) — pero el navegador posiciona el cursor según el clic **después** de disparar el evento `focus`, así que hay que diferir el `setSelectionRange` con `setTimeout(fn, 0)` (función `cursorAlFinal()`/`cursorAlFinalOrden()` según el módulo), si no el navegador pisa la posición.
- El input necesita suficiente `padding` y texto centrado (no `text-align: right` pegado al borde) para que el cursor parpadeante no quede encimado sobre el propio dígito (ilegible durante el parpadeo) — ancho mínimo recomendado ~80px con `padding: 5px 10px`.
- Aplicar este mismo patrón (tipo, filtro, cursor al final, padding) la próxima vez que se agregue un input numérico editable dentro de una fila de tabla en cualquier módulo.
- **Implementaciones existentes de referencia** (cada una define sus propias funciones `sanitizar*`/`cursorAlFinal*`, no están compartidas entre módulos):
  - `modules/almacen/index.php` — Cantidad en Ingreso/Salida de stock (`sanitizarCantidadInput()`, `cursorAlFinal()`), permite decimales.
  - `modules/inventario/index.php` — Conteo físico en Toma de Inventario (`sanitizarConteoInput()`, `cursorAlFinal()`) — variante **sin decimales**, este negocio no cuenta fracciones de producto.
  - `modules/compras/index.php` — Cantidad y P. Unitario en Nueva Orden de Compra (`sanitizarDecimalOrden()`, `cursorAlFinalOrden()`), permite decimales.

## Compras y cuentas por pagar/cobrar
- Una orden de compra aprobada y recibida (`orden_recibir`) genera automáticamente un ingreso de almacén y, opcionalmente, una cuenta por pagar al proveedor.
- Las cuentas por cobrar permiten registrar pagos parciales de clientes sobre ventas a crédito.

---
*Pendiente de completar: reglas específicas de descuentos/promociones, políticas de devolución, límites de crédito por cliente, y cualquier regla de negocio particular por tenant que no esté generalizada en el código.*
