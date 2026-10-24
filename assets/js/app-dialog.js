// ============================================================
// ARCHIVO: assets/js/app-dialog.js
// Reemplaza los dialogos nativos del browser (confirm, alert) por
// modales propios con estilo Gravity. Compatibles light/dark theme.
//
// API:
//   await appConfirm('¿Seguro?', {titulo, textoOk, textoNo, tipo})
//     -> Promise<boolean>
//   await appAlert('Mensaje', {titulo, textoOk, tipo})
//     -> Promise<void>
//
// tipo: 'warning' (default confirm) | 'danger' | 'info' | 'success' | 'error'
// ============================================================

(function () {
    'use strict';

    // Inyectar el CSS una sola vez.
    const CSS = `
    .app-dialog-overlay {
        position: fixed; inset: 0; z-index: 10000;
        background: rgba(15, 23, 42, 0.55);
        display: flex; align-items: center; justify-content: center;
        padding: 20px;
        opacity: 0; transition: opacity .18s ease;
        -webkit-backdrop-filter: blur(2px); backdrop-filter: blur(2px);
    }
    .app-dialog-overlay.open { opacity: 1; }
    .app-dialog-modal {
        background: var(--surface, #fff);
        color: var(--text, #0f172a);
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(2, 6, 23, 0.25);
        width: 100%; max-width: 420px;
        padding: 24px 24px 20px;
        text-align: center;
        transform: translateY(10px) scale(.98);
        transition: transform .18s ease;
    }
    .app-dialog-overlay.open .app-dialog-modal { transform: translateY(0) scale(1); }
    .app-dialog-icon {
        width: 56px; height: 56px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 1.4rem; margin: 0 auto 14px;
    }
    .app-dialog-title {
        font-size: 1.05rem; font-weight: 700;
        color: var(--text, #0f172a);
        margin-bottom: 6px;
    }
    .app-dialog-msg {
        font-size: .9rem; line-height: 1.5;
        color: var(--text-muted, #475569);
        margin-bottom: 20px;
    }
    .app-dialog-actions {
        display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;
    }
    .app-dialog-actions button {
        min-width: 110px;
    }
    @media (max-width: 480px) {
        .app-dialog-actions { flex-direction: column-reverse; }
        .app-dialog-actions button { width: 100%; }
    }`;

    const style = document.createElement('style');
    style.textContent = CSS;
    document.head.appendChild(style);

    // Config por tipo.
    const TIPOS = {
        warning: { icon: 'fa-triangle-exclamation', color: '#f59e0b' },
        danger:  { icon: 'fa-trash-can',            color: '#ef4444' },
        info:    { icon: 'fa-circle-info',          color: '#048af5' },
        success: { icon: 'fa-circle-check',         color: '#16a34a' },
        error:   { icon: 'fa-circle-exclamation',   color: '#ef4444' },
    };

    function buildOverlay(opts, botones) {
        const tipo  = TIPOS[opts.tipo] || TIPOS.info;
        const overlay = document.createElement('div');
        overlay.className = 'app-dialog-overlay';
        overlay.innerHTML = `
            <div class="app-dialog-modal" role="dialog" aria-modal="true">
                <div class="app-dialog-icon" style="background:${tipo.color}22;color:${tipo.color}">
                    <i class="fas ${tipo.icon}"></i>
                </div>
                <div class="app-dialog-title">${escapeHtml(opts.titulo)}</div>
                <div class="app-dialog-msg">${escapeHtml(opts.mensaje)}</div>
                <div class="app-dialog-actions">${botones}</div>
            </div>`;
        document.body.appendChild(overlay);
        requestAnimationFrame(() => overlay.classList.add('open'));
        return overlay;
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function cerrar(overlay) {
        overlay.classList.remove('open');
        setTimeout(() => overlay.remove(), 200);
    }

    window.appConfirm = function (mensaje, opciones = {}) {
        return new Promise((resolve) => {
            const opts = {
                mensaje,
                titulo:  opciones.titulo  || 'Confirmar acción',
                textoOk: opciones.textoOk || 'Aceptar',
                textoNo: opciones.textoNo || 'Cancelar',
                tipo:    opciones.tipo    || 'warning',
            };
            const botones = `
                <button type="button" class="btn btn-outline" data-no>${escapeHtml(opts.textoNo)}</button>
                <button type="button" class="btn btn-primary" data-yes>${escapeHtml(opts.textoOk)}</button>`;
            const overlay = buildOverlay(opts, botones);

            const finish = (val) => {
                document.removeEventListener('keydown', keyHandler);
                cerrar(overlay);
                resolve(val);
            };
            const keyHandler = (e) => {
                if (e.key === 'Escape') finish(false);
                if (e.key === 'Enter')  finish(true);
            };

            overlay.querySelector('[data-yes]').onclick = () => finish(true);
            overlay.querySelector('[data-no]').onclick  = () => finish(false);
            overlay.addEventListener('click', (e) => { if (e.target === overlay) finish(false); });
            document.addEventListener('keydown', keyHandler);
            // Autofocus en el boton primario para que Enter funcione natural.
            setTimeout(() => overlay.querySelector('[data-yes]').focus(), 50);
        });
    };

    window.appAlert = function (mensaje, opciones = {}) {
        return new Promise((resolve) => {
            const opts = {
                mensaje,
                titulo:  opciones.titulo  || 'Aviso',
                textoOk: opciones.textoOk || 'Entendido',
                tipo:    opciones.tipo    || 'info',
            };
            const botones = `
                <button type="button" class="btn btn-primary" data-ok>${escapeHtml(opts.textoOk)}</button>`;
            const overlay = buildOverlay(opts, botones);

            const finish = () => {
                document.removeEventListener('keydown', keyHandler);
                cerrar(overlay);
                resolve();
            };
            const keyHandler = (e) => {
                if (e.key === 'Escape' || e.key === 'Enter') finish();
            };

            overlay.querySelector('[data-ok]').onclick = finish;
            overlay.addEventListener('click', (e) => { if (e.target === overlay) finish(); });
            document.addEventListener('keydown', keyHandler);
            setTimeout(() => overlay.querySelector('[data-ok]').focus(), 50);
        });
    };
})();
