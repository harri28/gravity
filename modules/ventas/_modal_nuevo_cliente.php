<?php
// ============================================================
// _modal_nuevo_cliente.php
// Partial compartido con la modal "Nuevo Cliente" y toda su JS.
// Lo incluye modules/ventas/mesa.php (y a futuro, cuando se de-
// duplique pos.php, tambien el POS). Hace guardas con typeof para
// no pisar funciones si el host ya las define.
//
// Contrato con el host:
//   - debe existir una funcion global setSelectedCliente(cliente)
//     que reciba el cliente recien creado (shape de clientes/api
//     action=listar) y lo deje seleccionado en la UI del host.
//   - requiere jQuery/Select2 solo si el host lo necesita en
//     setSelectedCliente; esta modal en si no lo usa.
//   - escHtml / showToast / openModal / closeModal deben existir
//     (ya existen en header.php o en el modulo que lo incluye).
// ============================================================
?>
<!-- MODAL: Nuevo Cliente (compartido) -->
<div class="modal-overlay" id="modal-nuevo-cliente">
    <div class="modal" style="max-width:860px;width:min(860px,calc(100vw - 32px))">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-user-plus" style="color:var(--primary);margin-right:8px"></i>Nuevo Cliente</h3>
            <button class="modal-close" onclick="closeModal('modal-nuevo-cliente')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding:20px 24px;max-height:72vh;overflow-y:auto">
            <input type="hidden" id="nc-ubigeo">
            <div class="cliente-form-grid c2">
                <div class="cliente-form-group">
                    <label>Tipo de documento *</label>
                    <select id="nc-tipo-documento" onchange="actualizarEstadoNuevoCliente()"></select>
                </div>
                <div class="cliente-form-group">
                    <label>Numero de documento *</label>
                    <div class="cliente-input-lookup">
                        <input type="text" id="nc-numero-documento" placeholder="Ingresa el documento" autocomplete="off">
                        <button type="button" class="btn btn-outline" id="btn-consultar-cliente" onclick="consultarDocumentoNuevoCliente()">
                            <i class="fas fa-search"></i> Consultar
                        </button>
                    </div>
                    <div class="cliente-lookup-note" id="nc-lookup-note">Consulta automatica disponible para DNI y RUC.</div>
                </div>
            </div>
            <div style="margin:14px 0">
                <span class="cliente-doc-chip" id="nc-doc-chip"><i class="fas fa-id-card"></i> Documento</span>
            </div>
            <div class="cliente-form-grid c2">
                <div class="cliente-form-group">
                    <label id="nc-label-nombres">Nombres o razon social *</label>
                    <input type="text" id="nc-nombres" placeholder="Nombre del cliente" autocomplete="off">
                </div>
                <div class="cliente-form-group" id="nc-grupo-apellidos">
                    <label>Apellidos</label>
                    <input type="text" id="nc-apellidos" placeholder="Apellidos del cliente" autocomplete="off">
                </div>
            </div>
            <div class="cliente-form-grid c2" style="margin-top:14px">
                <div class="cliente-form-group">
                    <label>Telefono</label>
                    <input type="text" id="nc-telefono" maxlength="20" placeholder="987654321" autocomplete="off">
                </div>
                <div class="cliente-form-group">
                    <label>Correo</label>
                    <input type="email" id="nc-email" placeholder="correo@ejemplo.com" autocomplete="off">
                </div>
            </div>
            <div class="cliente-form-grid" style="margin-top:14px">
                <div class="cliente-form-group">
                    <label>Direccion</label>
                    <textarea id="nc-direccion" placeholder="Direccion del cliente"></textarea>
                </div>
            </div>
            <div class="cliente-ubigeo-box" id="nc-ubigeo-info">Sin ubigeo consultado.</div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('modal-nuevo-cliente')">Cancelar</button>
            <button class="btn btn-primary" id="btn-guardar-cliente" onclick="guardarNuevoCliente()">
                <i class="fas fa-save"></i> Guardar Cliente
            </button>
        </div>
    </div>
</div>

<script>
// ----------------------------------------------------------------
// JS de la modal Nuevo Cliente. Guardado con "if (typeof X === 'undefined')"
// para no pisar las versiones de pos.php cuando ambos coexistan.
// ----------------------------------------------------------------
(function() {
    if (typeof window.CLIENTES_API === 'undefined') {
        // Ruta relativa que funciona desde cualquier modules/<x>/*.php (pos, mesa, etc.)
        // sin depender de un BASE absoluto (que varia entre XAMPP local y VPS).
        window.CLIENTES_API = '../clientes/api.php';
    }
    if (typeof window.tiposDocumentoCliente === 'undefined') {
        window.tiposDocumentoCliente = [];
    }
})();

// Helper local; usa escH si existe (mesa.php), sino define uno local.
if (typeof window.ncEscHtml !== 'function') {
    window.ncEscHtml = (typeof escHtml === 'function') ? escHtml
        : (typeof escH === 'function') ? escH
        : function (s) {
            return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        };
}

if (typeof window.loadTiposDocumentoCliente !== 'function') {
    window.loadTiposDocumentoCliente = async function () {
        try {
            const r = await fetch(CLIENTES_API + '?action=document_types');
            const data = await r.json();
            if (data.error) throw new Error(data.message || 'No se pudieron cargar los tipos de documento');

            tiposDocumentoCliente = data.items || [];
            const select = document.getElementById('nc-tipo-documento');
            if (!select) return;

            select.innerHTML = tiposDocumentoCliente.map(item =>
                `<option value="${item.id}" data-codigo="${ncEscHtml(item.codigo)}" data-label="${ncEscHtml(item.descripcion_documento || item.descripcion)}">${ncEscHtml(item.descripcion_documento || item.descripcion)}</option>`
            ).join('');

            const predeterminado = tiposDocumentoCliente.find(item => item.codigo === '1') || tiposDocumentoCliente[0];
            if (predeterminado) select.value = String(predeterminado.id);
            actualizarEstadoNuevoCliente();
        } catch (error) {
            if (typeof showToast === 'function') showToast('No se pudieron cargar los tipos de documento', 'error');
        }
    };
}

if (typeof window.getTipoDocumentoNuevoCliente !== 'function') {
    window.getTipoDocumentoNuevoCliente = function () {
        const select = document.getElementById('nc-tipo-documento');
        const option = select?.options?.[select.selectedIndex];
        if (!option) return null;
        return {
            id: parseInt(option.value, 10),
            codigo: option.dataset.codigo || '',
            label: option.dataset.label || option.textContent || '',
        };
    };
}

if (typeof window.actualizarEstadoNuevoCliente !== 'function') {
    window.actualizarEstadoNuevoCliente = function () {
        const tipo = getTipoDocumentoNuevoCliente();
        const codigo = tipo?.codigo || '0';
        const esRuc = codigo === '6';
        const esConsultable = codigo === '1' || codigo === '6';

        document.getElementById('nc-label-nombres').textContent = esRuc ? 'Razon social *' : 'Nombres o razon social *';
        document.getElementById('nc-grupo-apellidos').style.display = esRuc ? 'none' : '';
        document.getElementById('nc-numero-documento').placeholder = codigo === '1' ? '12345678' : (codigo === '6' ? '20123456789' : 'Ingresa el documento');
        document.getElementById('nc-numero-documento').maxLength = codigo === '1' ? 8 : (codigo === '6' ? 11 : 15);
        document.getElementById('btn-consultar-cliente').disabled = !esConsultable;
        document.getElementById('nc-lookup-note').textContent = esConsultable
            ? `Consulta automatica disponible para ${tipo.label}.`
            : 'Para este tipo de documento el registro es manual.';
        document.getElementById('nc-doc-chip').innerHTML = `<i class="fas fa-id-card"></i> ${ncEscHtml(tipo?.label || 'Documento')}`;

        if (esRuc) document.getElementById('nc-apellidos').value = '';
    };
}

if (typeof window.actualizarUbigeoNuevoCliente !== 'function') {
    window.actualizarUbigeoNuevoCliente = function (ubigeo) {
        const box = document.getElementById('nc-ubigeo-info');
        const codigo = String(ubigeo || '').trim();
        box.textContent = codigo ? `Ubigeo detectado: ${codigo}` : 'Sin ubigeo consultado.';
    };
}

if (typeof window.consultarDocumentoNuevoCliente !== 'function') {
    window.consultarDocumentoNuevoCliente = async function () {
        const tipo = getTipoDocumentoNuevoCliente();
        const numero = document.getElementById('nc-numero-documento').value.trim();
        if (!tipo)   { if (typeof showToast === 'function') showToast('Debes seleccionar un tipo de documento', 'error'); return; }
        if (!numero) { if (typeof showToast === 'function') showToast('Debes ingresar el numero de documento', 'error'); return; }

        const btn = document.getElementById('btn-consultar-cliente');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando';
        try {
            const r = await fetch(CLIENTES_API + '?action=lookup_document', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tipo_documento_id: tipo.id, numero_documento: numero }),
            });
            const d = await r.json();
            if (d.error) { if (typeof showToast === 'function') showToast(d.message, 'error'); return; }

            document.getElementById('nc-nombres').value = d.cliente?.nombres ?? '';
            document.getElementById('nc-apellidos').value = d.cliente?.apellidos ?? '';
            document.getElementById('nc-direccion').value = d.cliente?.direccion ?? '';
            document.getElementById('nc-ubigeo').value = d.cliente?.ubigeo ?? '';
            actualizarUbigeoNuevoCliente(d.cliente?.ubigeo ?? '');
            if (typeof showToast === 'function') showToast(d.message || 'Documento consultado correctamente', 'success');
        } catch (error) {
            if (typeof showToast === 'function') showToast('No se pudo consultar el documento', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search"></i> Consultar';
        }
    };
}

if (typeof window.obtenerClienteCreado !== 'function') {
    window.obtenerClienteCreado = async function (id, numeroDocumento) {
        const r = await fetch(`${CLIENTES_API}?action=listar&q=${encodeURIComponent(numeroDocumento || '')}&estado=activo`);
        const data = await r.json();
        if (!Array.isArray(data)) return null;
        return data.find(item => Number(item.id) === Number(id)) || data[0] || null;
    };
}

if (typeof window.openModalNuevoCliente !== 'function') {
    window.openModalNuevoCliente = function () {
        ['nc-numero-documento','nc-nombres','nc-apellidos','nc-telefono','nc-email','nc-direccion','nc-ubigeo']
            .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });

        const select = document.getElementById('nc-tipo-documento');
        if (select && tiposDocumentoCliente.length) {
            const predeterminado = tiposDocumentoCliente.find(item => item.codigo === '1') || tiposDocumentoCliente[0];
            if (predeterminado) select.value = String(predeterminado.id);
        }
        actualizarEstadoNuevoCliente();
        actualizarUbigeoNuevoCliente('');
        openModal('modal-nuevo-cliente');
        setTimeout(() => document.getElementById('nc-numero-documento')?.focus(), 100);
    };
}

if (typeof window.guardarNuevoCliente !== 'function') {
    window.guardarNuevoCliente = async function () {
        const tipo = getTipoDocumentoNuevoCliente();
        const numeroDocumento = document.getElementById('nc-numero-documento')?.value.trim() || '';
        const payload = {
            tipo_documento_id: tipo?.id,
            numero_documento: numeroDocumento,
            nombres: document.getElementById('nc-nombres')?.value.trim() || '',
            apellidos: document.getElementById('nc-apellidos')?.value.trim() || '',
            telefono: document.getElementById('nc-telefono')?.value.trim() || '',
            email: document.getElementById('nc-email')?.value.trim() || '',
            direccion: document.getElementById('nc-direccion')?.value.trim() || '',
            ubigeo: document.getElementById('nc-ubigeo')?.value.trim() || '',
        };
        if (!payload.tipo_documento_id) {
            if (typeof showToast === 'function') showToast('Debes seleccionar un tipo de documento', 'error');
            return;
        }

        const btn = document.getElementById('btn-guardar-cliente');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        try {
            const response = await fetch(CLIENTES_API + '?action=crear', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Guardar Cliente';
            if (data.error) { if (typeof showToast === 'function') showToast(data.message, 'error'); return; }

            const clienteCreado = await obtenerClienteCreado(data.id, numeroDocumento);
            if (clienteCreado && typeof window.setSelectedCliente === 'function') {
                window.setSelectedCliente(clienteCreado);
            }
            closeModal('modal-nuevo-cliente');
            if (typeof showToast === 'function') showToast('Cliente guardado y seleccionado', 'success');
        } catch (error) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Guardar Cliente';
            if (typeof showToast === 'function') showToast('Error al guardar el cliente', 'error');
        }
    };
}

// Precarga de tipos de documento al cargar la pagina.
document.addEventListener('DOMContentLoaded', () => {
    if (!tiposDocumentoCliente.length) loadTiposDocumentoCliente();
});
</script>
