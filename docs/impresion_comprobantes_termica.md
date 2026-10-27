# Comprobantes borrosos al imprimir desde Chrome — solución

**Audiencia:** otra sesión de Claude (o devs) con este mismo bug en un sistema
POS/comprobantes que construye el HTML del ticket inline y lo manda a imprimir
abriendo un `window.open()` con `window.print()`.

**Sistema donde se resolvió:** Gravity (PHP + vanilla JS multi-tenant POS).
Las técnicas aplican a cualquier app que imprima HTML desde Chrome; los
nombres de función son los nuestros, el diagnóstico y el CSS son portables.

---

## 1. Síntomas (en orden en que aparecen al ir arreglando)

| # | Síntoma visual | Causa |
|---|---|---|
| 1 | **Todo el texto se ve lavado/borroso**, bordes de glifo "neblinosos". El QR a veces no se escanea. Pasa en todos los tenants con distintas marcas de impresora. | Chrome aplica sub-pixel antialiasing al rasterizar el HTML para la impresora. Lo que es ideal para pantalla, en papel sale como glifo lavado. Combinado con `font-size:XXpx` (unidad lógica, re-escalada al DPI de la impresora) multiplica el efecto. |
| 2 | Después del fix 1, **los textos en gris** (`color:#555`, `#888`, `#aaa`) salen **casi invisibles** en térmica. En A4 láser se ven normales. | Las impresoras térmicas no imprimen gris. Intentan simularlo con dither y el resultado en 203 DPI es prácticamente blanco. |
| 3 | Después del fix 2, **"stripes" horizontales** cortan algunos glifos por la mitad (típico en Courier New en productos como "NEUROBION"/"FREDNISONA"). | Courier New tiene stroke de ~1 px a 9 pt. A 203 DPI el stroke cae entre celdas del cabezal térmico y se "pierde" en bandas. |
| 4 | Después del fix 3, **franja negra continua debajo de cada línea** ("todo se subraya en negro"). | `font-weight:600` + `-webkit-font-smoothing:none` + Arial Narrow bold hace que Chrome rasterice la baseline como una fila de píxeles sólida (perdió el half-tone de la curva inferior del glifo), y la térmica la imprime como franja. |

Si al llegar vas a un ticket mostrando el síntoma #1, aplicá los 4 fixes de una
sola vez. No necesitás reproducirlos en orden — el orden de arriba refleja
cómo se descubrieron, no cómo se arregla.

---

## 2. Causa raíz, en una frase

**Chrome imprime HTML asumiendo que el display es pantalla**: aplica
sub-pixel antialiasing, respeta grises con dither, y rasteriza en unidades
lógicas (`px`) que después se re-escalan al DPI real de la impresora. Para
una térmica de 203 DPI con cabezal binario (puntito encendido/apagado, sin
tonos), **cada una de esas decisiones es la contraria a la que querés**.

El fix es decirle a Chrome: "imprimí para papel, no para pantalla".

---

## 3. Qué NO hacer

- **No cambies la fuente a Times Roman / Georgia / cualquier serif** para
  "mejorar" la impresión térmica. **La empeora.** Las serifas son terminaciones
  finas que a 203 DPI se emborronan igual o peor que un sans. Serif solo tiene
  sentido en A4 láser.
- **No subas el `font-weight` a 700/900** pensando que "más negro = más nítido".
  Con `font-smoothing:none` el bold pesado genera la franja negra bajo la
  baseline. `500` (medium) es el sweet spot.
- **No agregues `transform: scale(X)`** al wrapper del ticket para "ampliar".
  Chrome rasteriza el transform y pierde más calidad.
- **No pongas el `font-size` en `em`/`rem`/`%`** — se re-escalan con el zoom
  del navegador. Usá `pt` (unidad física absoluta).
- **No controles el DPI con `@media (resolution: ...)` o similares** — Chrome
  no respeta esos queries en el pipeline de impresión. El único control real
  es el CSS que te muestro abajo.

---

## 4. Fix completo (CSS listo para pegar)

Dos piezas separadas: el `<style>` que inyectás en el `window.open()` **y**
los `font-size` inline del HTML que genera el ticket. Las dos son necesarias:
sin el CSS, los defaults de Chrome ganan; sin convertir los inline, los `px`
sobreescriben lo que puso el CSS.

### 4.1. Reglas globales en el `<style>` del print window

```css
*{margin:0;padding:0;box-sizing:border-box}
html,body{
    -webkit-print-color-adjust:exact !important;  /* no aclarar negros */
    print-color-adjust:exact !important;
    -webkit-font-smoothing:none;                   /* sin sub-pixel AA */
    text-rendering:geometricPrecision;             /* glifos crisp */
}
img{image-rendering:-webkit-optimize-contrast}

/* Ticket 80mm (termica) */
body{
    font-family:'Arial Narrow',Arial,Helvetica,sans-serif;
    font-size:9pt;
    font-weight:500;     /* medium, NO 600+ */
    line-height:1.5;
    color:#000;
    width:80mm;
    margin:0 auto;
    padding:4mm 3mm;
}

@media print{
    @page{size:80mm auto;margin:4mm 3mm}
    body{padding:0}
}
```

### 4.2. Para A4 (laser/inkjet)

En A4 el fix es más leve: cambia solo las unidades y el color-adjust.
No hace falta eliminar grises (láser los imprime bien), no hace falta
desactivar font-smoothing (láser tiene halftoning), no hace falta Arial
Narrow (Arial o Helvetica normal se ven igual de bien).

```css
*{margin:0;padding:0;box-sizing:border-box}
html,body{
    -webkit-print-color-adjust:exact !important;
    print-color-adjust:exact !important;
    -webkit-font-smoothing:none;
    text-rendering:geometricPrecision;
}
img{image-rendering:-webkit-optimize-contrast}
body{
    font-family:Arial,Helvetica,sans-serif;
    font-size:10pt;
    color:#111;
    padding:20mm 15mm;
}
@media print{
    @page{size:A4;margin:15mm}
    body{padding:0}
}
```

### 4.3. QR code (crítico)

El QR es imagen binaria 1-bit. Cualquier interpolación lo vuelve ilegible al
escáner. En el `<img>` del QR, **inline** (no en CSS global):

```html
<img src="data:image/png;base64,..." style="...;image-rendering:pixelated">
```

`image-rendering:pixelated` fuerza nearest-neighbor en vez de bilineal.
Importante que vaya **inline** sobre el `<img>`: el CSS global del print
window a veces no se aplica al escalar imágenes `data:` en popups.

### 4.4. Logo (PNG raster)

Similar al QR pero con `-webkit-optimize-contrast` (menos agresivo que
`pixelated`, para que no se vea "cuadriculado"):

```html
<img src="LOGO_URL" style="...;image-rendering:-webkit-optimize-contrast">
```

### 4.5. Convertir el HTML inline de `px` a `pt`

Si tu ticket se construye con un template string lleno de
`style="font-size:11px"`, hay que convertir todos. Tabla de equivalencias
que usamos:

| px   | pt (ticket 80 mm) | pt (A4) |
|------|-------------------|---------|
| 8    | 7                 | 7       |
| 10   | 8                 | 8       |
| 11   | 9                 | 9       |
| 12   | 10 (base body)    | 10      |
| 14   | 11                | 11      |
| 15   | 12                | 12      |
| 16   | 13                | 13      |
| 18   | 14                | 14      |

**Importante:** si el ticket tiene **grises** (`color:#555`, `#888`, etc.)
en el ticket 80 mm, cambialos todos a `#000`. En A4, dejalos.

### 4.6. Alto de página dinámico (térmica)

Si tenés el bug clásico de que el ticket se corta en dos hojas con
`@page size:80mm auto`, calculá el alto después de renderizar y seteá el
`@page` dinámicamente:

```js
setTimeout(() => {
    // 0.2646 = 25.4mm/pulgada / 96px/pulgada = mm por px
    const heightMm = Math.ceil(w.document.body.scrollHeight * 0.2646) + 15;
    const pageStyle = w.document.createElement('style');
    pageStyle.textContent = `@media print{@page{size:80mm ${heightMm}mm;margin:4mm 3mm}body{padding:0}}`;
    w.document.head.appendChild(pageStyle);
    w.print();
}, 400);
```

---

## 5. Cómo validar el fix

1. **Imprimir una boleta de prueba con 3+ productos** (uno con nombre
   largo, para forzar word-wrap).
2. **Mirar la foto con luz lateral** (identifica mejor "stripes" y "franja
   negra" que luz frontal directa).
3. **Escanear el QR con un celular** desde ~15 cm — tiene que leerse al
   primer intento. Si falla, el `image-rendering:pixelated` no se aplicó.
4. **Comparar con la vista previa en pantalla** — tienen que verse muy
   parecidos (en pantalla no vas a notar la mejora, el fix es para papel).

---

## 6. Troubleshooting incremental

**Si después de todo el fix el texto todavía se ve débil (no borroso, débil):**
subí `font-weight` de 500 a 550. No pases de 600 o vuelve la franja negra.

**Si queda franja negra bajo las líneas:** bajá `font-weight` a 400 (regular)
y mantené `line-height: 1.6`. Arial regular a 9 pt con `font-smoothing:none`
ya es decente.

**Si el QR no se escanea aunque se ve bien:** verificá que el `<img>` del QR
tiene `image-rendering:pixelated` **inline**, no en CSS externo. Chrome print
a veces ignora CSS externo en popups cuando hay `data:` URIs.

**Si Chrome rechaza `@page { size: 80mm auto }`:** algunos drivers lo
ignoran y centran el ticket en A4. Calculá el alto dinámico (sección 4.6)
y pasá valor exacto en mm.

**Si la impresora todavía tira "stripes" horizontales con Arial Narrow:**
subí la **densidad de impresión** en el driver de la térmica (panel de
control de Windows → propiedades de impresora → ESC/POS density). Esto ya
no es tema de CSS. Pero antes de tocar driver, probá con `font-weight:600`
+ `line-height:1.7` para dar más aire — a veces alcanza.

**Si el user dice "la fuente antes se veía más tipo recibo clásico":** era
Courier (serif monospace). No vuelvas a Courier aunque te lo pida — el bug
regresa. Si quiere look de recibo, podés probar `Consolas, 'Lucida Console',
monospace` + bold 500: es monospace **sans-serif**, mantiene el look de
"cada carácter alineado" sin las serifas que crean los stripes.

---

## 7. Resumen de commits (referencia)

En Gravity el fix fueron 3 commits incrementales sobre `modules/ventas/pos.php`
y `modules/facturacion/index.php`:

1. **px → pt + print-color-adjust + font-smoothing + image-rendering**
   (arregló el borroso de anti-aliasing)
2. **Courier → Arial Narrow bold + grises → negros** en ticket 80 mm
   (arregló stripes horizontales y textos invisibles)
3. **font-weight 600 → 500 + line-height 1.4 → 1.5**
   (arregló la franja negra bajo cada línea)

Un solo PR aplicando los 3 juntos funciona exactamente igual — los commits
están separados porque descubrimos los síntomas de a uno.

Funciones tocadas (buscá análogas en tu codebase):

- `pos.php`:
  - `buildTicketHTML(opts)` — ticket 80 mm
  - `buildTicketA4HTML(opts)` — ticket A4
  - `printReceipt(innerHtml, formato)` — abre popup y manda a imprimir
- `facturacion/index.php`:
  - `openPrintWindow(title, contentHtml)` — abre popup e imprime
  - `rptBuildTicketHtml(data)` — reimpresión de boleta/factura
  - `ncBuildTicketHtml(row)` — reimpresión de nota de crédito

Si tu codebase tiene funciones con nombres parecidos ("buildReceipt",
"printTicket", "openInvoicePrint"), ahí es donde van los cambios.
