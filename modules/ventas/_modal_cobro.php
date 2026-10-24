<?php
// ============================================================
// ARCHIVO: modules/ventas/_modal_cobro.php
// Partial reusable del modal "Informacion de la Venta".
//
// Lo incluyen:
//   - modules/ventas/pos.php   (POS directo: cobra desde carrito en memoria)
//   - modules/ventas/mesa.php  (Mesas:       cobra desde items de comanda)
//
// Dependencias que debe cargar la pagina incluyente:
//   - jQuery + Select2 (para el buscador de cliente #cliente-select)
//   - Las funciones JS: onComprobanteChange, onTipoPagoChange, calcularVuelto,
//     toggleDividirCuenta, addSplitPaymentRow, addCreditInstallmentRow,
//     toggleSwitch, previsualizarComprobante, confirmarVenta,
//     openModalNuevoCliente.
//
// IDs internos (NO cambiar; el JS del POS depende de ellos):
//   tipo-comprobante, tipo-impresion, cobro-serie,
//   cliente-select, split-payment-panel, split-payment-rows,
//   tipo-pago, cuenta-banco, monto-recibido, monto-cliente,
//   cobro-observacion, check-obs, track-obs, check-credito, track-credito,
//   credit-payment-panel, credit-payment-rows,
//   cobro-total-venta, cobro-total, vuelto, btn-confirmar-venta,
//   btn-dividir-cuenta, pago-simple-section, pago-simple-efectivo
// ============================================================
?>
<!-- MODAL: Cobro / Pago -->
<div class="modal-overlay" id="modal-cobro">
    <div class="modal" style="max-width:500px">
        <div class="modal-header" style="padding:14px 18px">
            <h3 class="modal-title" style="font-size:.88rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase">
                Información de la Venta
            </h3>
            <button class="modal-close" onclick="closeModal('modal-cobro')" style="display:flex;align-items:center;gap:5px;font-size:.8rem;background:none;border:none;color:var(--text-muted);cursor:pointer">
                Cerrar <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" style="padding:16px 18px">

            <!-- Fila 1: Tipo de documento | Tipo impresión | Serie -->
            <div style="display:grid;grid-template-columns:2fr 1.2fr 1fr;gap:10px;margin-bottom:12px">
                <div>
                    <label class="form-label" style="font-size:.71rem">Tipo de documento</label>
                    <select class="form-control" id="tipo-comprobante" style="font-size:.82rem" onchange="onComprobanteChange()">
                        <option value="boleta">Boleta Electrónica</option>
                        <option value="factura">Factura Electrónica</option>
                        <option value="ticket">Ticket</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:.71rem">Tipo impresión</label>
                    <select class="form-control" id="tipo-impresion" style="font-size:.82rem">
                        <option value="ticket">Ticket</option>
                        <option value="a4">A4</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:.71rem">Serie</label>
                    <select class="form-control" id="cobro-serie" style="font-size:.82rem">
                        <option value="B001">B001</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:12px">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                    <label class="form-label" style="font-size:.71rem;margin:0">Cliente</label>
                    <button type="button" class="btn btn-outline btn-sm" onclick="openModalNuevoCliente()" style="font-size:.75rem;white-space:nowrap">
                        <i class="fas fa-plus"></i> Nuevo cliente
                    </button>
                </div>
                <div class="cliente-picker-wrap cliente-picker-cobro">
                    <span class="cliente-search-icon"><i class="fas fa-search"></i></span>
                    <select id="cliente-select" style="width:100%">
                        <option value=""></option>
                    </select>
                </div>
                <div class="cliente-help">Busca por nombre, DNI o RUC. Si no existe, usa el botón +.</div>
            </div>

            <!-- Botón dividir cuenta -->
            <div style="text-align:right;margin-bottom:14px">
                <button class="btn btn-primary btn-sm" id="btn-dividir-cuenta" style="font-size:.77rem" onclick="toggleDividirCuenta()">
                    <i class="fas fa-plus"></i> Dividir cuenta
                </button>
            </div>

            <div class="split-pay-panel" id="split-payment-panel" style="display:none">
                <div class="split-pay-head">
                    <div>
                        <div class="split-pay-title">Pago dividido</div>
                        <div class="split-pay-subtitle">Reparte el cobro entre uno o varios métodos. La suma debe coincidir con el total de la venta.</div>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" onclick="addSplitPaymentRow()" style="font-size:.75rem;white-space:nowrap">
                        <i class="fas fa-plus"></i> Agregar forma
                    </button>
                </div>
                <div class="split-pay-list" id="split-payment-rows"></div>
                <div class="split-pay-summary">
                    <div class="split-pay-summary-box">
                        <div class="label">Total de métodos</div>
                        <div class="value" id="split-total-methods">S/ 0.00</div>
                    </div>
                    <div class="split-pay-summary-box danger">
                        <div class="label">Restante</div>
                        <div class="value" id="split-remaining">S/ 0.00</div>
                    </div>
                </div>
            </div>

            <div id="pago-simple-section">
                <div style="border-top:1px solid var(--border);margin-bottom:14px"></div>

                <!-- Pago simple -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:12px">
                    <div>
                        <label class="form-label" style="font-size:.71rem">Forma de pago</label>
                        <select class="form-control" id="tipo-pago" style="font-size:.82rem" onchange="onTipoPagoChange()">
                            <option value="efectivo">Efectivo</option>
                            <option value="yape">Yape</option>
                            <option value="plin">Plin</option>
                            <option value="tarjeta">Visa/Mastercard</option>
                            <option value="transferencia">Transferencia</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="font-size:.71rem">Referencia</label>
                        <select class="form-control" id="cuenta-banco" style="font-size:.82rem">
                            <option value="caja_chica">CAJA CHICA</option>
                            <option value="banco">BANCO</option>
                            <option value="yape_cuenta">YAPE</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="font-size:.71rem">Monto cobrado</label>
                        <div class="input-group">
                            <span class="input-group-icon" style="font-size:.78rem;font-weight:700;color:var(--text-muted)">S/</span>
                            <input type="text" id="monto-recibido" class="form-control" readonly
                                style="font-size:.82rem;padding-left:28px;background:var(--surface-2);color:var(--text-muted);cursor:default">
                        </div>
                    </div>
                </div>

                <!-- Efectivo del cliente -->
                <div id="pago-simple-efectivo" style="margin-bottom:12px">
                    <label class="form-label" style="font-size:.71rem">Efectivo recibido</label>
                    <div class="input-group">
                        <span class="input-group-icon" style="font-size:.85rem;font-weight:700;color:var(--primary)">S/</span>
                        <input type="number" id="monto-cliente" class="form-control" placeholder="0.00"
                            step="0.10" min="0" oninput="calcularVuelto()"
                            style="font-size:1rem;padding-left:30px;font-weight:600;border-color:var(--primary)"
                            autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="credit-pay-panel" id="credit-payment-panel" style="display:none">
                <div class="credit-pay-head">
                    <div>
                        <div class="credit-pay-title">Venta a crédito</div>
                        <div class="credit-pay-subtitle">Registra las cuotas y sus fechas de vencimiento. La suma debe coincidir con el total de la venta.</div>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" onclick="addCreditInstallmentRow()" style="font-size:.75rem;white-space:nowrap">
                        <i class="fas fa-plus"></i> Agregar cuota
                    </button>
                </div>
                <div class="credit-pay-list" id="credit-payment-rows"></div>
                <div class="credit-pay-summary">
                    <div class="credit-pay-summary-box">
                        <div class="label">Total de cuotas</div>
                        <div class="value" id="credit-total-methods">S/ 0.00</div>
                    </div>
                    <div class="credit-pay-summary-box danger">
                        <div class="label">Restante</div>
                        <div class="value" id="credit-remaining">S/ 0.00</div>
                    </div>
                </div>
            </div>

            <!-- Observación -->
            <div style="margin-bottom:14px">
                <label class="form-label" style="font-size:.71rem">Observación</label>
                <input type="text" id="cobro-observacion" class="form-control" placeholder="glosa" style="font-size:.82rem">
            </div>

            <!-- Toggle: imprimir observación -->
            <div style="margin-bottom:10px">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:.81rem;color:var(--text)">
                    <span id="track-obs" onclick="toggleSwitch('check-obs','track-obs', event)"
                        style="width:38px;height:21px;background:var(--primary);border-radius:11px;position:relative;flex-shrink:0;cursor:pointer;transition:background .2s">
                        <span style="position:absolute;top:3px;left:3px;width:15px;height:15px;background:#fff;border-radius:50%;transition:transform .2s;transform:translateX(17px)"></span>
                    </span>
                    <input type="checkbox" id="check-obs" checked style="display:none">
                    Imprimir la observación en el comprobante
                </label>
            </div>
            <!-- Toggle: crédito -->
            <div style="margin-bottom:16px">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:.81rem;color:var(--text)">
                    <span id="track-credito" onclick="toggleSwitch('check-credito','track-credito', event)"
                        style="width:38px;height:21px;background:#d1d5db;border-radius:11px;position:relative;flex-shrink:0;cursor:pointer;transition:background .2s">
                        <span style="position:absolute;top:3px;left:3px;width:15px;height:15px;background:#fff;border-radius:50%;transition:transform .2s"></span>
                    </span>
                    <input type="checkbox" id="check-credito" style="display:none">
                    ¿La venta es a crédito?
                </label>
            </div>

            <!-- Totales: Total de venta | Total a pagar | Vuelto -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;border-top:2px solid var(--border);padding-top:14px;text-align:center">
                <div>
                    <div style="font-size:.71rem;color:var(--text-muted);margin-bottom:4px">Total de venta</div>
                    <div style="font-size:1.15rem;font-weight:700;color:var(--primary)" id="cobro-total-venta">S/ 0.00</div>
                </div>
                <div style="border-left:1px solid var(--border);border-right:1px solid var(--border)">
                    <div style="font-size:.71rem;color:var(--text-muted);margin-bottom:4px">Total a pagar</div>
                    <div style="font-size:1.15rem;font-weight:700;color:var(--primary)" id="cobro-total">S/ 0.00</div>
                </div>
                <div>
                    <div style="font-size:.71rem;color:var(--text-muted);margin-bottom:4px">Vuelto</div>
                    <div style="font-size:1.15rem;font-weight:700;color:var(--success)" id="vuelto">S/ 0.00</div>
                </div>
            </div>
        </div>

        <div class="modal-footer" style="justify-content:space-between;padding:12px 18px">
            <button class="btn btn-outline btn-sm" onclick="previsualizarComprobante()" style="font-size:.8rem">
                Previsualizar comprobante
            </button>
            <div style="display:flex;gap:8px">
                <button class="btn btn-primary" id="btn-confirmar-venta" onclick="confirmarVenta()" style="font-size:.85rem">
                    <i class="fas fa-check"></i> Pagar y emitir comprobante
                </button>
                <button class="btn btn-outline" onclick="closeModal('modal-cobro')" style="font-size:.85rem">Cancelar</button>
            </div>
        </div>
    </div>
</div>
