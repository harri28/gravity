# Configuración SUNAT — Notas de implementación

Guía técnica agnóstica del tenant: cómo configurar SUNAT en el sistema, errores comunes y recetas de solución. Las credenciales reales y datos de cada empresa (RUC, Usuario SOL, Clave SOL, certificados) **no** se documentan acá — van en `credenciales_sunat.md` (gitignored) o directamente en Admin → Configuración de cada tenant.

---

## Causa raíz típica de "Rejected by policy" en emisores nuevos

Un Usuario SOL secundario al que se le modificó el mismo permiso varias veces en distintas sesiones (crear → asignar → volver a confirmar días después → agregarle más permisos encima) suele quedar en un estado inconsistente del lado de SUNAT que no se arregla volviendo a marcar las mismas casillas. **La solución probada**: crear un **Usuario SOL secundario nuevo, nunca antes tocado**, y asignarle **todos los permisos necesarios de una sola vez, en una sola sesión** — no en pasos separados a lo largo de varios días.

Receta:

1. En el portal SOL (con la Clave SOL del RUC **principal**), crear un **usuario secundario nuevo** — no reusar uno que ya se haya tocado antes. Usuario en **mayúsculas** (dato de la comunidad Greenter, no confirmado como obligatorio pero es gratis cumplirlo).
2. En una sola pasada de "Modificar/Asignar Programas", marcar **ambos** grupos de permisos (no solo uno):
   - **TRIBUTARIOS → Comprobantes de pago → SEE - Del Contribuyente y Envío de Documentos**: Servicio de Envío de Documentos Electrónicos, Certificado Digital, Consultar Envíos de CPE.
   - **TRIBUTARIOS → Comprobantes de pago → SEE - SOL**: Factura Electrónica → Emitir Factura (+ Nota de Crédito/Débito, Consultar), Boleta de Venta Electrónica → Emitir Boleta de Venta (+ Nota de Crédito/Débito, Consultar). **Este segundo grupo es importante** aunque el envío real sea por servicio web: SUNAT parece exigir también el perfil base de emisión de SEE-SOL como prerequisito.
3. Completar el asistente hasta el final y confirmar que aparece el mensaje explícito **"El Usuario Secundario y sus Opciones han sido registrados satisfactoriamente."** — no basta con ver las casillas marcadas en el árbol, hay que llegar a esa confirmación.
4. Configurar ese usuario/clave nuevo en Admin → Configuración → SUNAT y certificado (el RUC y el certificado **no cambian**, siguen siendo los mismos ya validados).
5. Esperar **algunas horas** (no necesariamente 24-48h completas) antes de que la aceptación sea consistente — es normal ver 1-2 rechazos `0111` intermitentes en las primeras horas mientras SUNAT termina de propagar el nuevo usuario en todos sus servidores; después de eso, aceptación estable.

---

## Certificado digital

- Formato requerido por el sistema: `.pfx` (renombrar si viene como `.p12` de SUNAT — es el mismo formato PKCS#12, solo cambia la extensión).
- Se sube desde **Admin → Configuración**. Se guarda en `facturacion/certs/<RUC>.pfx`.
- En VPS: requiere permisos de escritura para `www-data` en `facturacion/certs/`, `facturacion/storage/xml/`, `facturacion/storage/cdr/` (ver `config_vps.md`).

---

## Datos obligatorios para poder enviar comprobantes (Admin → Configuración)

Validados en `config/sunat.php` (`sunat_profile_for_current_tenant()`) — sin todos estos, el envío falla:

- RUC
- Razón social
- Usuario SOL
- Clave SOL
- Certificado `.pfx` (subido)
- Contraseña del certificado

Selector adicional: **Servidor SUNAT** — `Beta` (sandbox, no aparece en el portal SUNAT real) o `Producción` (comprobante fiscal real y válido).

Los campos "GRE Client ID/Secret" son para Guías de Remisión Electrónica (trámite aparte) — no se necesitan para boletas/facturas normales.

---

## Error: "No tiene el perfil para enviar comprobantes electrónicos" / "Rejected by policy"

No es un error del sistema — es un permiso faltante del **Usuario SOL secundario** en el propio portal de SUNAT. Se corrige así:

1. Entrar a SUNAT con la **Clave SOL del RUC principal** (no la del usuario secundario).
2. **Administración de Usuarios Secundarios** → seleccionar el usuario (el mismo configurado como `sunat_username` en el sistema) → botón **"Modificar Programas"**.
   - Nota: el botón "Asignar Roles" de esa misma pantalla es para otro tipo de permisos (VUCE, comercio exterior, B2B) — **no es por ahí**.
3. En el árbol de opciones, expandir **TRIBUTARIOS**.
4. Dentro de Tributarios, expandir **"Comprobantes de pago"**.
5. Dentro de ahí hay varias sub-carpetas — ojo, no todas sirven:
   - **"SEE - SOL"** → esto es para usar el *Facturador SUNAT* manualmente desde el portal web (llenar boletas/facturas a mano en la página de SUNAT). Marcar las opciones de emisión acá también como prerequisito (ver receta de arriba).
   - **"SEE - Del Contribuyente y Envío de Documentos"** → **esta es la correcta** para un sistema propio que envía comprobantes directo a SUNAT por servicio web/SOAP, sin pasar por un OSE intermediario. Expandir y marcar las 3 opciones (Servicio de Envío de Documentos Electrónicos, Consultar Envíos de CPE, Certificado Digital).
6. Clic en **Siguiente** → confirmar la asignación hasta ver el mensaje "El Usuario Secundario y sus Opciones han sido registrados satisfactoriamente."

Después de guardar el permiso en SUNAT, no hace falta tocar nada del sistema — solo reintentar el envío desde Facturación (opción "Reenviar a SUNAT" en comprobantes pendientes/rechazados).

---

## Cómo ver la respuesta cruda de SUNAT (para diagnosticar cualquier rechazo)

La tabla de Facturación solo muestra el mensaje recortado. Para ver el `fault_code`/`fault_string` completo y el XML de respuesta, consultar directo en la BD (ajustar `<schema>` por el schema de la sucursal, ej. `sucursal_001`):

```bash
PGPASSWORD='1234' psql -U postgres -d gravity -c "
SET search_path TO <schema>, public;
SELECT id, numero_completo, estado_sunat, nubefact_response
FROM comprobantes_electronicos
ORDER BY id DESC
LIMIT 1;
"
```

Nota: la columna se llama `nubefact_response` por legado del nombre, pero el sistema usa envío directo SOAP a SUNAT (`config/sunat.php`), no Nubefact.

---

## Catálogo de códigos de error SUNAT

Se va llenando con lo que vayamos encontrando. Cada vez que un envío falle, se saca el `fault_code`/`fault_string` real con el query de la sección anterior y se agrega acá — así queda un catálogo propio, explicado en español simple, en vez de tener que volver a investigar el mismo código dos veces.

| Código | `fault_string` textual de SUNAT | Qué significa en la práctica | Cómo se resuelve |
|---|---|---|---|
| **0111** | "No tiene el perfil para enviar comprobantes electronicos - Detalle: Rejected by policy." | El Usuario SOL usado para el envío (o el RUC mismo) no tiene habilitado el perfil de emisor electrónico en el sistema de SUNAT — puede ser el permiso del usuario secundario, o que el RUC no esté afiliado al SEE. Puede aparecer también de forma **intermitente** durante las primeras horas tras asignar un permiso nuevo (propagación desigual en los servidores de SUNAT — ver sección de propagación más abajo). **Caso adicional sin propagación**: si el usuario SOL tiene el permiso confirmado y aun así rechaza con 0111, comparar `public.tenants.ruc` contra el RUC real del certificado (`openssl pkcs12 -in <archivo>.pfx -clcerts -nokeys -passin pass:<clave> \| openssl x509 -noout -subject`, buscar `organizationIdentifier=NTRPE-<RUC>`) — una desalineación entre el RUC guardado y el RUC del certificado genera el mismo rechazo de forma consistente. | Asignar el permiso "Servicio de Envío de Documentos Electrónicos" al usuario secundario, con ambos grupos (SEE-Del Contribuyente **y** SEE-SOL) en una sola sesión del asistente (ver "Causa raíz típica" arriba). Si el permiso ya está bien y sigue rechazando: verificar que `public.tenants.ruc` coincida con el RUC del certificado. Si todo coincide y aun así rechaza por horas: esperar propagación. Si tras 24h sigue 100% rechazado con todo verificado, escalar a Mesa de Ayuda de SUNAT. |
| **0102** | "Usuario o contrasena incorrectos - Detalle: " (sin más detalle) | Las credenciales SOL (Usuario SOL + Clave SOL) que se están enviando no son válidas para SUNAT. Puede ser: la Clave SOL guardada está mal/desactualizada, o el campo "Usuario SOL" quedó con el RUC duplicado (el sistema ya antepone el RUC automáticamente — el campo debe tener *solo* el usuario, ej. `MIUSUARIO`, no `20XXXXXXXXXMIUSUARIO`). | Revisar/re-escribir a mano (no copiar/pegar) Usuario SOL y Clave SOL en Admin → Configuración. Para confirmar si la clave en sí es correcta, probar loguearse directo en SUNAT con ese usuario secundario. |

*(Tabla en construcción — se agrega una fila nueva cada vez que aparezca un código distinto. No hay que memorizar catálogos genéricos de internet: solo se documentan los que realmente nos salieron, con el contexto real de qué se probó y qué funcionó.)*

---

## Cómo obtener el Certificado Digital Tributario (CDT) gratuito de RENIEC/SUNAT

Para un RUC que nunca ha emitido comprobantes electrónicos y no tiene certificado propio comprado, SUNAT ofrece uno gratuito (el CDT). Flujo completo:

1. **Registrar correo** (requisito previo): logueado con la Clave SOL del usuario que va a emitir (puede ser el usuario secundario si ya tiene el permiso "Certificado Digital", o el principal), ir a **Comprobantes de pago → SEE - Del Contribuyente y Envío de Documentos → Certificado Digital → Registro y Mantenimiento de Correo y Certificados Digitales**. Ahí, si no hay correo registrado, clic en "Registrar correo electrónico", ingresar un correo válido → llega un código de verificación al correo → se ingresa en la misma pantalla para confirmar.
2. **Solicitar el CDT**: ir a **Comprobantes de pago → Certificado Digital Tributario - CDT → Solicitar Certificado Digital Tributario**. Confirma los datos del contribuyente (RUC, razón social, correo, celular), aceptar términos y condiciones, "Enviar Solicitud".
3. SUNAT responde con un **número de solicitud** y un **enlace de descarga** (`https://cdt.reniec.gob.pe/...`), válido por unos **30 días** desde la emisión (pasado ese plazo hay que solicitarlo de nuevo). El mismo enlace se reenvía al Buzón Electrónico SOL.
4. Al abrir el enlace, RENIEC entrega el archivo del certificado directamente (confirmación "¡Felicidades! Has descargado tu Certificado Digital Tributario").
5. **La clave (PIN) de instalación del certificado NO se muestra en esa pantalla** — llega aparte, como un mensaje distinto en el **Buzón Electrónico SUNAT** (asunto "Envío de clave (PIN) de instalación del CDT - Número de Solicitud ..."), con un número de solicitud consecutivo al de la emisión (ej. si la emisión fue `...402`, el PIN llega en `...403`). SUNAT sugiere no borrar ese mensaje, sirve para recuperación futura.
6. Subir el certificado descargado en **Admin → Configuración → SUNAT y certificado**: campo "Clave certificado" = el PIN del paso 5, "Certificado digital (.pfx)" = el archivo descargado (si viene como `.p12`, el sistema lo acepta igual, es el mismo formato PKCS#12).

---

## Propagación del permiso SOL no es inmediata

Cuando se asigna el permiso "Servicio de Envío de Documentos Electrónicos" a un usuario SOL (o se emite un certificado nuevo) el mismo día que se necesita usarlo, es normal ver rechazos `0111` **intermitentes** durante varias horas mientras SUNAT propaga el cambio en todos sus servidores — reenviando el mismo comprobante varias veces seguidas, algunas pueden quedar "Aceptado" y otras seguir rechazando con el mismo código sin ningún cambio de configuración entre intentos.

Antes de sospechar de RUC/usuario/certificado mal puestos, **reintentar el envío (botón "Reenviar a SUNAT") varias veces a lo largo del día**; si sigue fallando al 100% después de 24 horas, ahí sí revisar la configuración a fondo.

**Nota adicional**: Beta no valida el permiso real del usuario secundario con el mismo rigor que Producción — un "Aceptado" en Beta **no** confirma que el permiso esté bien en Producción. Siempre validar en el ambiente real antes de dar por resuelto.

---

## Bug conocido: certificados `.pfx` con cifrado legacy (RC2-40-CBC) en OpenSSL 3.x

Algunos certificados `.pfx` emitidos por SUNAT/RENIEC vienen cifrados con `pbeWithSHA1And40BitRC2-CBC`, un algoritmo PKCS#12 que **OpenSSL 3.x deshabilita por defecto** ("legacy provider"). Esto da el error:

```
Failure Signing Data: error:0308010C:digital envelope routines::unsupported
```

Verificación — si al abrir el `.pfx` aparece algo como "PBE-SHA1-RC2-40" en los algoritmos:

```bash
openssl pkcs12 -in <archivo>.pfx -info -noout -passin pass:<clave>
```

**Afecta** entornos con OpenSSL 3.x (XAMPP local reciente con PHP 8.2 usa OpenSSL 3.x). **No suele afectar** entornos VPS con PHP 7.x + OpenSSL 1.0/1.1, donde el algoritmo nunca estuvo restringido.

**Fix** — reconvertir el `.pfx` a AES-256-CBC (mismo certificado y llave privada, solo cambia el cifrado del contenedor; no afecta RUC/Usuario SOL/Clave SOL configurados en el sistema):

```bash
# Guardar el original primero por si acaso
cp <archivo>.pfx <archivo>.pfx.bak_legacy

openssl pkcs12 -in <archivo>.pfx -legacy -nodes -out temp.pem -passin pass:<clave>
openssl pkcs12 -export -in temp.pem -out <archivo>.pfx -passout pass:<clave>
rm temp.pem   # contiene la llave privada sin cifrar — no dejarlo tirado
```

---

## Notas para replicar la configuración en tenants nuevos

- Esta configuración es **por RUC/empresa**, no global al sistema — cada tenant nuevo necesita su propio Usuario SOL con los permisos SEE asignados en el portal SUNAT, además de su propio certificado `.pfx` y credenciales SOL en Admin → Configuración.
- El camino en el portal SUNAT (Administración de Usuarios Secundarios → Modificar Programas → Tributarios → Comprobantes de pago → SEE - Del Contribuyente y Envío de Documentos) es el mismo para cualquier RUC, ya que es la estructura de menú de SUNAT, no algo específico de una empresa.
- Si en el futuro se usa un **OSE** (operador externo) en vez de envío directo, el permiso a asignar sería otro distinto (bajo "Operador de Servicios Electrónicos - OSE" o "Proveedor de Servicios Electrónicos-PSE") — no aplica a este sistema, que usa envío directo (`config/sunat.php`).
