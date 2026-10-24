# Tenant de prueba — Empresa de prueba

Este tenant **existe únicamente para pruebas** del sistema (nuevas features,
regresiones, demos). **No contiene datos reales de ningún cliente.**

Cualquier operación destructiva (purgar BD, revertir migraciones, borrar
tenants, etc.) puede ejecutarse sobre este tenant sin pedir permiso.

---

## Acceso

| Campo | Valor |
|---|---|
| URL login | `https://empresa_de_prueba.gravityty.space` |
| Slug | `empresa_de_prueba` |
| Tenant ID | `2` |
| Razón social | Empresa de prueba |
| RUC | `20616086465` |
| Plan | basico |

**Panel superadmin** (gestión multi-tenant):
`https://admin.gravityty.space/modules/superadmin/login.php`
→ usuario `admin` / password `admin123` (cambiar al primer login real).

---

## Sucursal

- **ID** `2`
- **Nombre** `Jr. Lima`
- **Schema PostgreSQL** `empresa_de_prueba_jr_lima`

---

## Usuario operativo

- **Username** `empresa_prueba`
- **Nombre** Harris Steifer Tinoco Rodas
- **Rol** admin del tenant
- Password: el que se le asignó al crearlo (no se guarda en texto plano).

---

## Productos de prueba cargados (2026-10-24)

5 productos variados para probar el flujo de venta (POS directo y mesas):

| SKU | Código barras | Nombre | P. venta | P. compra | Stock | Stock mín |
|---|---|---|---|---|---|---|
| P001   | 7753749001519 | PILSEN 630 ML          | S/ 10.00 | S/ 7.00  | 20 | 12 |
| C001   | 7753749000680 | Cristal 650 ml         | S/ 10.00 | S/ 7.00  | 20 | 12 |
| CC001  | 7750182006095 | COCA COLA 600 ML       | S/  5.00 | S/ 3.50  | 20 |  5 |
| RB001  | 9002490100070 | Red Bull 250 ml        | S/ 10.00 | S/ 7.00  | 20 |  5 |
| WRL001 | 5000267014005 | WHISKY RED LABEL 750ML | S/ 95.00 | S/ 70.00 | 10 |  3 |

Son réplicas de los productos de Caleta Karaoke (tenant real) con precios
nominales. Si querés recargarlos:

```sql
SET search_path TO empresa_de_prueba_jr_lima, public;

INSERT INTO productos (codigo, codigo_interno, codigo_barras, nombre, precio_venta, precio_compra, stock, stock_minimo) VALUES
('7753749001519', 'P001',   '7753749001519', 'PILSEN 630 ML',          10.00, 7.00,  20, 12),
('7753749000680', 'C001',   '7753749000680', 'Cristal 650 ml',         10.00, 7.00,  20, 12),
('7750182006095', 'CC001',  '7750182006095', 'COCA COLA 600 ML',        5.00, 3.50,  20,  5),
('9002490100070', 'RB001',  '9002490100070', 'Red Bull 250 ml',        10.00, 7.00,  20,  5),
('5000267014005', 'WRL001', '5000267014005', 'WHISKY RED LABEL 750ML', 95.00, 70.00, 10,  3);
```

---

## Para probar

- **POS directo**: `https://empresa_de_prueba.gravityty.space/modules/ventas/pos.php`
- **Mesas**: `https://empresa_de_prueba.gravityty.space/modules/ventas/`
- **Historial**: `https://empresa_de_prueba.gravityty.space/modules/ventas/pos.php?tab=ventas`

Para probar boletas electrónicas SUNAT: configurar primero las credenciales SUNAT
en el panel Admin (certificado .pfx, usuario, clave) y usar el ambiente **beta**
(`sunat_server = '3'`). En beta las boletas no son fiscales — se puede emitir
y anular sin consecuencia real.

**Nunca** apuntar este tenant al ambiente de producción de SUNAT
(`sunat_server = '1'`): si llegara a emitirse un comprobante real con RUC de
este tenant, queda registrado ante SUNAT como venta real y no se puede
"borrar", solo anular con nota de crédito.
