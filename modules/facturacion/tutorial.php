<?php
// ============================================================
// ARCHIVO: farmacia/modules/facturacion/tutorial.php
// MÓDULO:  Facturación → Manual de SUNAT y Certificado Digital
// ============================================================

require_once '../../config/database.php';

$base_path      = '../../';
$required_roles = ['admin', 'gerente'];
$current_module = 'facturacion';
$current_page   = 'facturacion';
$page_title     = 'Manual SUNAT — Gravity';
$breadcrumb     = '<strong>Facturación</strong> / Manual SUNAT';

include '../../includes/header.php';
?>

<style>
.tut-hero {
    background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 60%, #2563eb 100%);
    border-radius: var(--radius);
    padding: 36px 40px;
    color: #fff;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
}
.tut-hero::after {
    content: '';
    position: absolute;
    right: -40px; top: -40px;
    width: 260px; height: 260px;
    border-radius: 50%;
    background: rgba(255,255,255,.06);
}
.tut-hero h1 { font-size: 1.55rem; font-weight: 800; margin: 0 0 8px; }
.tut-hero p  { font-size: .92rem; opacity: .85; margin: 0; max-width: 620px; }

.tut-toc {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px 24px;
    margin-bottom: 28px;
}
.tut-toc-title { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); margin-bottom: 12px; }
.tut-toc ol { margin: 0; padding-left: 20px; }
.tut-toc li { margin-bottom: 6px; }
.tut-toc a  { color: var(--primary); text-decoration: none; font-size: .88rem; font-weight: 500; }
.tut-toc a:hover { text-decoration: underline; }

.tut-section {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    margin-bottom: 24px;
    overflow: hidden;
}
.tut-section-header {
    background: var(--surface-2);
    border-bottom: 1px solid var(--border);
    padding: 16px 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.tut-section-number {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: var(--primary);
    color: #fff;
    font-size: .82rem;
    font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.tut-section-title { font-size: 1rem; font-weight: 700; color: var(--text); }
.tut-section-body  { padding: 24px; }

.tut-step {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px dashed var(--border);
}
.tut-step:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
.tut-step-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    background: #eff6ff;
    color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-size: 1rem;
}
.tut-step-content h4 { margin: 0 0 6px; font-size: .92rem; font-weight: 700; color: var(--text); }
.tut-step-content p  { margin: 0; font-size: .85rem; color: var(--text-muted); line-height: 1.6; }
.tut-step-content p + p { margin-top: 6px; }

.tut-callout {
    border-radius: 12px;
    padding: 14px 18px;
    font-size: .84rem;
    line-height: 1.6;
    margin: 16px 0;
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.tut-callout.info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
.tut-callout.warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
.tut-callout.danger  { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
.tut-callout.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
.tut-callout i { margin-top: 2px; flex-shrink: 0; }

.tut-flow {
    display: flex;
    align-items: center;
    gap: 0;
    flex-wrap: wrap;
    margin: 18px 0;
}
.tut-flow-node {
    background: #f8fafc;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    padding: 10px 14px;
    text-align: center;
    font-size: .78rem;
    font-weight: 600;
    color: var(--text);
    min-width: 100px;
}
.tut-flow-node span { display: block; font-size: .68rem; font-weight: 400; color: var(--text-muted); margin-top: 3px; }
.tut-flow-node.highlight { background: #eff6ff; border-color: var(--primary); color: var(--primary); }
.tut-flow-node.success   { background: #f0fdf4; border-color: #16a34a; color: #166534; }
.tut-flow-arrow { font-size: 1.1rem; color: var(--text-muted); padding: 0 6px; flex-shrink: 0; }

.tut-field-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
.tut-field-table th {
    text-align: left; padding: 8px 12px;
    background: var(--surface-2); color: var(--text-muted);
    font-weight: 600; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em;
    border-bottom: 1px solid var(--border);
}
.tut-field-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: top; }
.tut-field-table tr:last-child td { border-bottom: none; }
.tut-field-table .field-name { font-weight: 600; color: var(--text); white-space: nowrap; }
.tut-field-table .badge-req  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; border-radius: 6px; font-size: .68rem; font-weight: 700; padding: 2px 7px; }
.tut-field-table .badge-opt  { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius: 6px; font-size: .68rem; font-weight: 700; padding: 2px 7px; }

.tut-error-table { width: 100%; border-collapse: collapse; font-size: .83rem; }
.tut-error-table th {
    text-align: left; padding: 8px 12px; background: var(--surface-2);
    font-weight: 600; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em;
    border-bottom: 1px solid var(--border); color: var(--text-muted);
}
.tut-error-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: top; }
.tut-error-table tr:last-child td { border-bottom: none; }

.env-card {
    border: 2px solid var(--border);
    border-radius: 12px;
    padding: 16px 20px;
    flex: 1;
    min-width: 200px;
}
.env-card.beta { border-color: #fde68a; background: #fffbeb; }
.env-card.prod { border-color: #bbf7d0; background: #f0fdf4; }
.env-card .env-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }
.env-card.beta .env-label { color: #92400e; }
.env-card.prod .env-label { color: #166534; }
.env-card .env-title { font-size: .95rem; font-weight: 700; margin-bottom: 8px; color: var(--text); }
.env-card ul { margin: 0; padding-left: 18px; font-size: .82rem; color: var(--text-muted); }
.env-card li { margin-bottom: 4px; }
</style>

<div class="page-header">
    <div>
        <div class="page-title">
            <i class="fas fa-book-open" style="color:var(--primary);margin-right:8px"></i>Manual: SUNAT y Certificado Digital
        </div>
        <div class="page-subtitle">Guía paso a paso para configurar la facturación electrónica</div>
    </div>
    <div class="page-actions">
        <a href="<?= $base_path ?>modules/admin/index.php?tab=configuracion" class="btn btn-primary">
            <i class="fas fa-cog"></i> Ir a Configuración
        </a>
    </div>
</div>

<!-- Hero -->
<div class="tut-hero">
    <h1><i class="fas fa-file-invoice" style="margin-right:10px"></i>Facturación electrónica con SUNAT</h1>
    <p>Este manual explica qué necesitas, cómo configurarlo y qué ocurre internamente cada vez que se emite una boleta o factura electrónica desde el sistema.</p>
</div>

<!-- Tabla de contenidos -->
<div class="tut-toc">
    <div class="tut-toc-title">Contenido</div>
    <ol>
        <li><a href="#sec-requisitos">Requisitos previos</a></li>
        <li><a href="#sec-certificado">El certificado digital (.pfx)</a></li>
        <li><a href="#sec-configuracion">Configurar el sistema (Admin → Configuración)</a></li>
        <li><a href="#sec-entornos">Entorno Beta vs. Producción</a></li>
        <li><a href="#sec-flujo">Cómo funciona internamente la emisión</a></li>
        <li><a href="#sec-errores">Errores frecuentes y cómo resolverlos</a></li>
    </ol>
</div>

<!-- ============================================================
     1. REQUISITOS
     ============================================================ -->
<div class="tut-section" id="sec-requisitos">
    <div class="tut-section-header">
        <div class="tut-section-number">1</div>
        <div class="tut-section-title">Requisitos previos</div>
    </div>
    <div class="tut-section-body">

        <p style="font-size:.88rem;color:var(--text-muted);margin:0 0 20px">
            Antes de emitir cualquier comprobante electrónico necesitas tener preparados tres elementos.
            Sin alguno de ellos el sistema lanzará un error al intentar enviar a SUNAT.
        </p>

        <div class="tut-step">
            <div class="tut-step-icon"><i class="fas fa-id-card"></i></div>
            <div class="tut-step-content">
                <h4>RUC y Razón Social activos</h4>
                <p>Tu empresa debe tener RUC activo y habido ante SUNAT, y debe estar afiliada al sistema de <strong>Emisión Electrónica (SEE)</strong>. Si aún no estás afiliado, entra a <em>SUNAT Operaciones en Línea → Empresas → Comprobantes de pago → Emisión Electrónica</em> y solicita la afiliación. El proceso es gratuito.</p>
            </div>
        </div>

        <div class="tut-step">
            <div class="tut-step-icon"><i class="fas fa-key"></i></div>
            <div class="tut-step-content">
                <h4>Credenciales SOL (usuario secundario)</h4>
                <p>Necesitas un <strong>usuario secundario SOL</strong> creado específicamente para el sistema de facturación. <strong>Nunca uses el usuario principal</strong> del RUC porque si la clave se compromete perderías acceso a todo.</p>
                <p>Para crearlo: <em>SUNAT Operaciones en Línea → Menú de usuario → Mis datos → Usuarios secundarios → Agregar usuario</em>. Asígnale los roles de <em>Facturación Electrónica</em>.</p>
                <div class="tut-callout warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>El usuario secundario tiene el formato <strong>RUC + nombre_usuario</strong>, por ejemplo <code>20610316884SISTEMA1</code>. Introduce solo la parte después del RUC en el campo "Usuario SOL" del sistema.</span>
                </div>
            </div>
        </div>

        <div class="tut-step">
            <div class="tut-step-icon"><i class="fas fa-certificate"></i></div>
            <div class="tut-step-content">
                <h4>Certificado digital en formato .pfx</h4>
                <p>Cada XML que se envía a SUNAT debe ir <strong>firmado digitalmente</strong> con un certificado de empresa. El sistema espera un archivo <code>.pfx</code> (también llamado PKCS#12) protegido con contraseña. En la sección siguiente se explica cómo obtenerlo.</p>
            </div>
        </div>

    </div>
</div>

<!-- ============================================================
     2. CERTIFICADO
     ============================================================ -->
<div class="tut-section" id="sec-certificado">
    <div class="tut-section-header">
        <div class="tut-section-number">2</div>
        <div class="tut-section-title">El certificado digital (.pfx)</div>
    </div>
    <div class="tut-section-body">

        <div class="tut-callout info">
            <i class="fas fa-info-circle"></i>
            <span>El certificado digital es el equivalente electrónico del sello y firma de la empresa. SUNAT exige que cada comprobante esté firmado con él para considerarlo válido.</span>
        </div>

        <div class="tut-step">
            <div class="tut-step-icon"><i class="fas fa-shopping-bag"></i></div>
            <div class="tut-step-content">
                <h4>¿Dónde obtengo el certificado?</h4>
                <p>Debes adquirirlo en una <strong>Entidad de Certificación acreditada</strong> por INDECOPI. Las más utilizadas en Perú son:</p>
                <ul style="margin:8px 0 0 18px;font-size:.84rem;color:var(--text-muted);line-height:1.8">
                    <li><strong>eCert Perú</strong> (CERTICÁMARA)</li>
                    <li><strong>DigiCert / Encriptados</strong></li>
                    <li><strong>Firmaperu</strong></li>
                </ul>
                <p style="margin-top:8px">El proceso de emisión tarda 1-3 días hábiles y requiere verificación de identidad del representante legal.</p>
                <div class="tut-callout success" style="margin-top:12px">
                    <i class="fas fa-lightbulb"></i>
                    <span><strong>Para pruebas:</strong> SUNAT acepta certificados autofirmados en el entorno Beta. Puedes generar uno gratis con OpenSSL:<br>
                    <code style="font-size:.78rem;background:#dcfce7;padding:2px 6px;border-radius:4px">openssl req -x509 -newkey rsa:2048 -keyout key.pem -out cert.pem -days 365 -nodes</code><br>
                    y convertirlo a .pfx con:<br>
                    <code style="font-size:.78rem;background:#dcfce7;padding:2px 6px;border-radius:4px">openssl pkcs12 -export -out certificado.pfx -inkey key.pem -in cert.pem</code></span>
                </div>
            </div>
        </div>

        <div class="tut-step">
            <div class="tut-step-icon"><i class="fas fa-upload"></i></div>
            <div class="tut-step-content">
                <h4>Cómo subir el certificado al sistema</h4>
                <p>Ve a <strong>Administración → Configuración → SUNAT y certificado</strong>. En la sección <em>"Cargar certificado .pfx"</em>, haz clic en el botón y selecciona el archivo. El sistema lo guardará en <code>facturacion/certs/</code> y completará automáticamente el campo "Ruta del certificado".</p>
                <p>Después introduce la <strong>contraseña del certificado</strong> en el campo correspondiente y la <strong>fecha de vencimiento</strong> para que el sistema pueda alertarte cuando esté próximo a expirar.</p>
                <div class="tut-callout warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>El archivo .pfx y su contraseña son confidenciales. No los compartas por correo ni los subas a repositorios de código. El directorio <code>facturacion/certs/</code> está excluido de git.</span>
                </div>
            </div>
        </div>

        <div class="tut-step">
            <div class="tut-step-icon"><i class="fas fa-sync-alt"></i></div>
            <div class="tut-step-content">
                <h4>Renovación del certificado</h4>
                <p>Los certificados tienen vigencia de 1-3 años. Cuando venza debes obtener uno nuevo, subirlo al sistema y actualizar la ruta y contraseña en la configuración. Los comprobantes ya emitidos con el certificado anterior siguen siendo válidos.</p>
            </div>
        </div>

    </div>
</div>

<!-- ============================================================
     3. CONFIGURACIÓN
     ============================================================ -->
<div class="tut-section" id="sec-configuracion">
    <div class="tut-section-header">
        <div class="tut-section-number">3</div>
        <div class="tut-section-title">Configurar el sistema (Admin → Configuración)</div>
    </div>
    <div class="tut-section-body">

        <p style="font-size:.88rem;color:var(--text-muted);margin:0 0 20px">
            Todos los campos se guardan en la tabla <code>public.tenants</code> y aplican a toda la empresa (no por sucursal). Los campos marcados como <span class="badge-req">Requerido</span> son indispensables para emitir; sin ellos el sistema lanzará error.
        </p>

        <div style="font-size:.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px">Pestaña "Datos de la botica"</div>
        <table class="tut-field-table" style="margin-bottom:24px">
            <thead><tr><th>Campo</th><th>Descripción</th><th style="width:90px">Estado</th></tr></thead>
            <tbody>
                <tr><td class="field-name">Razón social</td><td>Nombre legal exacto como aparece en SUNAT. Sale en todas las boletas y facturas.</td><td><span class="badge-req">Requerido</span></td></tr>
                <tr><td class="field-name">RUC</td><td>Número de 11 dígitos de la empresa.</td><td><span class="badge-req">Requerido</span></td></tr>
                <tr><td class="field-name">Dirección</td><td>Domicilio fiscal. Aparece en los comprobantes.</td><td><span class="badge-req">Requerido</span></td></tr>
                <tr><td class="field-name">Ubigeo</td><td>Código INEI de 6 dígitos (distrito). Ej: <code>220901</code> = Tarapoto. Al ingresarlo se auto-completan departamento, provincia y distrito.</td><td><span class="badge-req">Requerido</span></td></tr>
                <tr><td class="field-name">Nombre del sistema</td><td>Nombre que aparece en la barra lateral. No afecta a SUNAT.</td><td><span class="badge-opt">Opcional</span></td></tr>
            </tbody>
        </table>

        <div style="font-size:.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px">Pestaña "SUNAT y certificado"</div>
        <table class="tut-field-table">
            <thead><tr><th>Campo</th><th>Descripción</th><th style="width:90px">Estado</th></tr></thead>
            <tbody>
                <tr><td class="field-name">Usuario SOL</td><td>Usuario secundario creado en SUNAT Operaciones en Línea. Formato: solo la parte después del RUC, ej: <code>SISTEMA1</code>.</td><td><span class="badge-req">Requerido</span></td></tr>
                <tr><td class="field-name">Clave SOL</td><td>Contraseña del usuario secundario SOL.</td><td><span class="badge-req">Requerido</span></td></tr>
                <tr><td class="field-name">Certificado .pfx</td><td>Ruta del archivo dentro del servidor. Se rellena automáticamente al subir el archivo con el botón "Cargar certificado .pfx".</td><td><span class="badge-req">Requerido</span></td></tr>
                <tr><td class="field-name">Clave certificado</td><td>Contraseña con la que fue protegido el archivo .pfx al generarlo.</td><td><span class="badge-req">Requerido</span></td></tr>
                <tr><td class="field-name">Vencimiento certificado</td><td>Fecha de expiración del .pfx. Solo informativa; el sistema no bloquea por esto.</td><td><span class="badge-opt">Opcional</span></td></tr>
                <tr><td class="field-name">Entorno SUNAT</td><td><strong>Beta / Pruebas</strong>: envía al servidor de homologación — no genera comprobantes reales. <strong>Producción</strong>: envía al servidor real — los comprobantes son válidos tributariamente.</td><td><span class="badge-req">Requerido</span></td></tr>
                <tr><td class="field-name">GRE Client ID / Secret</td><td>Credenciales OAuth2 para el módulo de Guías de Remisión Electrónica (GRE). Solo necesario si emites guías de traslado electrónicas.</td><td><span class="badge-opt">Opcional</span></td></tr>
            </tbody>
        </table>

    </div>
</div>

<!-- ============================================================
     4. ENTORNOS
     ============================================================ -->
<div class="tut-section" id="sec-entornos">
    <div class="tut-section-header">
        <div class="tut-section-number">4</div>
        <div class="tut-section-title">Entorno Beta vs. Producción</div>
    </div>
    <div class="tut-section-body">

        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px">
            <div class="env-card beta">
                <div class="env-label">⚙️ Beta / Pruebas</div>
                <div class="env-title">Servidor de homologación</div>
                <ul>
                    <li>Los comprobantes <strong>no tienen validez tributaria</strong></li>
                    <li>Usa el endpoint <code>e-beta.sunat.gob.pe</code></li>
                    <li>Acepta certificados autofirmados</li>
                    <li>Responde con CDR de prueba</li>
                    <li><strong>Recomendado para configuración inicial y testing</strong></li>
                </ul>
            </div>
            <div class="env-card prod">
                <div class="env-label">✅ Producción</div>
                <div class="env-title">Servidor real SUNAT</div>
                <ul>
                    <li>Los comprobantes <strong>son válidos ante SUNAT</strong></li>
                    <li>Usa el endpoint <code>e-factura.sunat.gob.pe</code></li>
                    <li>Requiere certificado emitido por entidad acreditada</li>
                    <li>Responde con CDR oficial</li>
                    <li><strong>Activar solo cuando todo esté validado en Beta</strong></li>
                </ul>
            </div>
        </div>

        <div class="tut-callout danger">
            <i class="fas fa-exclamation-circle"></i>
            <span><strong>Importante:</strong> Al cambiar de Beta a Producción los números de serie continúan desde donde quedaron. Si en Beta emitiste B001-00015, en Producción la primera boleta real será B001-00016. Esto puede ser confuso — considera resetear las series antes de pasar a producción.</span>
        </div>

    </div>
</div>

<!-- ============================================================
     5. FLUJO INTERNO
     ============================================================ -->
<div class="tut-section" id="sec-flujo">
    <div class="tut-section-header">
        <div class="tut-section-number">5</div>
        <div class="tut-section-title">Cómo funciona internamente la emisión</div>
    </div>
    <div class="tut-section-body">

        <p style="font-size:.88rem;color:var(--text-muted);margin:0 0 18px">
            Cada vez que se registra una venta con comprobante electrónico (boleta o factura), el sistema ejecuta este proceso en <code>config/sunat.php</code> a través de la función <code>enviar_sunat()</code>:
        </p>

        <div class="tut-flow">
            <div class="tut-flow-node highlight">Venta registrada<span>BD + correlativo</span></div>
            <div class="tut-flow-arrow">→</div>
            <div class="tut-flow-node">XML UBL 2.1<span>construido en memoria</span></div>
            <div class="tut-flow-arrow">→</div>
            <div class="tut-flow-node">Firma digital<span>.pfx + RSA</span></div>
            <div class="tut-flow-arrow">→</div>
            <div class="tut-flow-node">ZIP<span>RUC-tipo-serie-num.ZIP</span></div>
            <div class="tut-flow-arrow">→</div>
            <div class="tut-flow-node">SOAP CURL<span>billService</span></div>
            <div class="tut-flow-arrow">→</div>
            <div class="tut-flow-node success">CDR SUNAT<span>respuesta + estado</span></div>
        </div>

        <div class="tut-step">
            <div class="tut-step-icon" style="background:#f0f9ff;color:#0369a1"><i class="fas fa-code"></i></div>
            <div class="tut-step-content">
                <h4>1. Construcción del XML (UBL 2.1)</h4>
                <p>El sistema genera el XML según el estándar UBL 2.1 exigido por SUNAT, incluyendo datos del emisor, receptor, líneas de detalle con IGV, ICBPER y totales. Para boletas el receptor puede ser "Clientes Varios" (DNI <code>00000000</code>), pero para facturas el RUC del cliente es obligatorio.</p>
            </div>
        </div>

        <div class="tut-step">
            <div class="tut-step-icon" style="background:#fdf4ff;color:#7c3aed"><i class="fas fa-pen-nib"></i></div>
            <div class="tut-step-content">
                <h4>2. Firma digital con el certificado</h4>
                <p>El XML es firmado con la librería interna <code>facturacion/signature.php</code> (XMLDSig / RSA-SHA1) usando el archivo <code>.pfx</code> y su contraseña. La firma queda embebida dentro del XML en el nodo <code>&lt;ds:Signature&gt;</code>.</p>
                <p>El XML firmado y el ZIP se guardan en <code>facturacion/storage/xml/</code> con el nombre <code>RUC-tipo-serie-correlativo.XML/.ZIP</code>.</p>
            </div>
        </div>

        <div class="tut-step">
            <div class="tut-step-icon" style="background:#fff7ed;color:#c2410c"><i class="fas fa-paper-plane"></i></div>
            <div class="tut-step-content">
                <h4>3. Envío SOAP al Web Service de SUNAT</h4>
                <p>El ZIP se envía vía SOAP/HTTPS al endpoint <code>billService</code> del servidor configurado (Beta o Producción), autenticado con el usuario y clave SOL.</p>
            </div>
        </div>

        <div class="tut-step">
            <div class="tut-step-icon" style="background:#f0fdf4;color:#16a34a"><i class="fas fa-file-download"></i></div>
            <div class="tut-step-content">
                <h4>4. Recepción del CDR</h4>
                <p>SUNAT responde con un <strong>CDR (Constancia de Recepción)</strong>, que es a su vez un ZIP firmado por SUNAT. El sistema lo descomprime y guarda en <code>facturacion/storage/cdr/</code>. El código de respuesta <code>0</code> significa "Aceptado"; cualquier otro valor indica observación o rechazo.</p>
                <p>El estado final se almacena en <code>comprobantes_electronicos.estado_sunat</code> y <code>codigo_respuesta</code>. Desde Facturación → Reporte de Ventas puedes ver el badge de estado de cada comprobante y reenviar si falló.</p>
            </div>
        </div>

        <div class="tut-callout info">
            <i class="fas fa-info-circle"></i>
            <span><strong>Notas de crédito:</strong> Siguen exactamente el mismo flujo pero usan el tipo de documento <code>07</code> y referencian el comprobante original. Se emiten desde Facturación → Notas de crédito o desde el botón de acción en el Reporte de Ventas.</span>
        </div>

    </div>
</div>

<!-- ============================================================
     6. ERRORES FRECUENTES
     ============================================================ -->
<div class="tut-section" id="sec-errores">
    <div class="tut-section-header">
        <div class="tut-section-number">6</div>
        <div class="tut-section-title">Errores frecuentes y cómo resolverlos</div>
    </div>
    <div class="tut-section-body">

        <table class="tut-error-table">
            <thead><tr><th style="width:35%">Mensaje de error</th><th>Causa</th><th>Solución</th></tr></thead>
            <tbody>
                <tr>
                    <td><em>"Faltan credenciales SOL en la configuración de empresa"</em></td>
                    <td>Los campos Usuario SOL o Clave SOL están vacíos.</td>
                    <td>Admin → Configuración → SUNAT y certificado. Completa usuario y clave SOL y guarda.</td>
                </tr>
                <tr>
                    <td><em>"No se encontró el certificado configurado"</em></td>
                    <td>El archivo .pfx no existe en la ruta guardada (se movió o eliminó), o nunca se subió.</td>
                    <td>Vuelve a subir el .pfx desde Admin → Configuración → SUNAT y certificado → "Cargar certificado .pfx".</td>
                </tr>
                <tr>
                    <td><em>"Falta la clave del certificado"</em></td>
                    <td>El campo "Clave certificado" está vacío.</td>
                    <td>Introduce la contraseña que se usó al generar el .pfx y guarda.</td>
                </tr>
                <tr>
                    <td><em>"Faltan datos base de empresa"</em></td>
                    <td>RUC o Razón Social no están configurados.</td>
                    <td>Admin → Configuración → Datos de la botica. Completa RUC y Razón social.</td>
                </tr>
                <tr>
                    <td>SUNAT responde código <strong>1033</strong></td>
                    <td>El usuario SOL no tiene permisos de facturación electrónica.</td>
                    <td>En SUNAT Operaciones en Línea asigna el rol <em>Emisor Electrónico</em> al usuario secundario.</td>
                </tr>
                <tr>
                    <td>SUNAT responde código <strong>0152</strong> o <strong>0153</strong></td>
                    <td>El certificado digital no es reconocido (autofirmado en producción).</td>
                    <td>Usa un certificado emitido por una entidad acreditada INDECOPI para el entorno de Producción.</td>
                </tr>
                <tr>
                    <td>SUNAT responde código <strong>2800</strong></td>
                    <td>El número de serie ya fue usado (correlativo duplicado).</td>
                    <td>En la tabla <code>series_comprobantes</code> verifica el campo <code>ultimo_numero</code> y corrige el valor para que no se repita.</td>
                </tr>
                <tr>
                    <td><em>"cURL error" o timeout</em></td>
                    <td>El servidor no tiene acceso a internet o el firewall bloquea HTTPS saliente.</td>
                    <td>Verifica que Apache/PHP pueda hacer peticiones HTTPS externas. En XAMPP local esto requiere que <code>curl.cainfo</code> apunte a un bundle de certificados CA válido en <code>php.ini</code>.</td>
                </tr>
                <tr>
                    <td>Badge <strong>"Observado"</strong> en el reporte</td>
                    <td>SUNAT aceptó el comprobante con observaciones (no es rechazo). Código CDR distinto de 0.</td>
                    <td>El comprobante es válido. Lee el mensaje completo en <code>comprobantes_electronicos.mensaje_respuesta</code> para conocer el detalle de la observación.</td>
                </tr>
            </tbody>
        </table>

        <div class="tut-callout info" style="margin-top:20px">
            <i class="fas fa-database"></i>
            <span>Para ver el XML generado y la respuesta SOAP completa de cualquier comprobante, consulta las columnas <code>soap_request</code> y <code>soap_response</code> en la tabla <code>comprobantes_electronicos</code>. Desde el Reporte de Ventas también puedes descargar el XML y el CDR con los botones de acción de cada fila.</span>
        </div>

    </div>
</div>

<?php include '../../includes/footer.php'; ?>
