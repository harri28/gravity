# Implementaciones futuras

Registro de decisiones de scope que **se dejaron fuera a propósito** del
alcance actual, con los puntos de código ya preparados para engancharlas
cuando se habiliten.

---

## 1. Rol separado "Mozo" (hoy = Cajero)

**Estado actual**: no existe un rol "Mozo" distinto del `cajero`. Toda
operación de mesa (abrir comanda, agregar items, cobrar) la hace el usuario
con rol `cajero` logueado. En la UI, "cajero" y "mozo" son el mismo rol.

**Lo que ya está preparado para cuando se separe**:

- La tabla `comandas` tiene `usuario_apertura_id` apuntando a
  `public.usuarios(id)`: hoy guarda el cajero del momento, pero puede
  apuntar al mozo real sin cambio de schema.
- La tabla `comanda_items` tiene `usuario_id` también por item: permite
  trazabilidad "quién tomó cada pedido" aunque la comanda la haya abierto
  otra persona.
- `modules/ventas/api.php` → acción `comanda_detalle` ya devuelve
  `usuario_apertura_nombre` como `mozo_nombre` (al JS le llega como "mozo"
  por consistencia visual).
- Las queries de `top_vendidos`/stats se agruparán por `usuario_id` sin
  cambios.

**Qué habría que hacer para habilitarlo**:

1. Agregar un rol `'mozo'` al constraint o enum de `usuarios.rol`
   (asumiendo que hoy es un `VARCHAR` libre; ver migración).
2. En `config/auth.php` agregar `isMozo()` y ajustar `isCajero()` si se
   quiere diferenciar.
3. En `modules/admin/index.php` (tab Usuarios) permitir asignar el rol.
4. En la UI de la mesa, al tocar "Nuevo pedido" el sistema podría pedir
   la cédula/código del mozo que atiende si el usuario logueado es cajero
   principal (no se autoasigna).

**Decisión del cliente (2026-10-24)**: por ahora todos los usuarios
operativos son `cajero`. Se evaluará separar si el negocio crece y
necesita trazabilidad "qué mozo atendió qué mesa".

---

## 2. Venta a crédito desde una mesa

**Estado actual**: el modal grande del POS directo (`#modal-cobro` en
`modules/ventas/pos.php`) ya tiene el toggle **"¿La venta es a crédito?"**
que, al activarse, abre el panel de cuotas (`#credit-payment-panel`). Esto
funciona para la venta directa desde el POS.

**Lo que falta para habilitarlo en mesas**:

- Cuando se reutilice el `#modal-cobro` desde `mesa.php` para cobrar una
  comanda, el toggle de crédito debe seguir disponible.
- El endpoint `cobrar_comanda` (hoy en `modules/ventas/api.php`) tiene
  que aceptar el payload con cuotas (`credit_installments: [{monto, fecha}]`),
  crear la venta con `metodo_pago = 'credito'`, generar los registros en
  `cuentas_por_cobrar` + `cobros_cliente` igual que hace `registrar_venta`.
- Validar que el cliente de la comanda NO sea "Clientes Varios" (el crédito
  requiere identificar al deudor por DNI/RUC real).

**Decisión del cliente (2026-10-24)**: por ahora no hay venta a crédito en
el flujo de mesas. En un bar/karaoke la cuenta se cobra al cierre; cliente
que no paga se maneja con un ticket "mesa pendiente" aparte. Se evaluará
habilitarlo si aparece el caso de uso real.

---

## 3. Dividir cuenta genera N comprobantes

**Estado actual**: el POS directo tiene "+ Dividir cuenta" pero genera
**una sola venta** con pago fraccionado (varios métodos para el mismo
cliente/mismo comprobante).

**Lo deseado para mesas** (ver captura 2026-10-24 del cliente): cada
división genera **una venta con su propio comprobante** (ej: Juan paga
S/50 con factura a su RUC + Ana paga S/70 con boleta a su DNI → 2
comprobantes separados contra la misma comanda).

**Lo que falta**:

- UI dentro del modal grande para asignar items (no solo monto) a cada
  split: "este item lo paga Juan, este item lo paga Ana".
- Endpoint `cobrar_comanda_split` que reciba un array de divisiones:
  `[{cliente_id, items: [...], metodo_pago, ...}, ...]` y genere N ventas
  en una sola transacción, cerrando la comanda al final cuando todas las
  divisiones estén cubiertas.
- La comanda queda `estado = 'cerrada'` solo cuando la suma de divisiones
  cubre el total; permite dividir en varios pasos (ir cobrando por partes
  si no todos pagan juntos).

**Decisión del cliente (2026-10-24)**: sí es parte del plan, va en el
commit del modal grande (siguiente). Documentar aquí por si se necesita
re-planear.
