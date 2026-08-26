<?php
// views/pos.php
session_start();
require_once '../config/conexion.php';

// Validar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
$rolActual = $_SESSION['usuario_rol'] ?? 'vendedor';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta (POS) - INVERSIONES J.A</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    },
                    boxShadow: {
                        'xs': '0 1px 2px 0 rgb(0 0 0 / 0.05)',
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts & FontAwesome Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col selection:bg-blue-500 selection:text-white">

    <!-- Navegación Superior -->
    <nav class="bg-white border-b border-slate-200 px-6 py-3.5 flex justify-between items-center no-print">
        <div class="flex items-center gap-2 font-bold text-slate-900 text-sm sm:text-base">
            <i class="fa-solid fa-cash-register text-blue-600"></i> Punto de Venta (POS) - INVERSIONES J.A
        </div>
    </nav>

    <!-- Layout Principal del POS -->
    <div class="max-w-[1400px] w-full mx-auto flex flex-col lg:flex-row gap-6 p-4 sm:p-6 flex-grow">
        
        <!-- COLUMNA IZQUIERDA: Búsqueda y Carrito -->
        <div class="flex-2 flex flex-col gap-4">
            
            <!-- Buscador de Clientes -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 search-container relative">
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                    <i class="fa-solid fa-user text-blue-600 mr-1"></i> Cliente Seleccionado:
                </label>
                <div class="flex gap-2">
                    <div class="flex-grow relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input type="text" id="pos_input_cliente" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="Buscar cliente por BP, DNI/RTN o Nombre..." onkeyup="buscarClientePOS(this.value)" onfocus="buscarClientePOS(this.value)">
                        <div id="pos_sugerencias_cliente" class="hidden absolute top-full left-0 w-full bg-white border border-slate-200 max-h-56 overflow-y-auto z-50 shadow-lg rounded-xl mt-1"></div>
                    </div>
                    <button type="button" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs px-4 py-2 rounded-xl transition shadow-xs flex items-center gap-1.5" onclick="abrirModalClienteRapido()">
                        <i class="fa-solid fa-user-plus text-xs"></i> Crear
                    </button>
                </div>
                
                <div class="mt-3 p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between text-xs sm:text-sm">
                    <span class="text-slate-600">Cliente Actual: <b id="lbl_cliente_nombre" class="text-slate-900">Consumidor Final</b> (<span id="lbl_cliente_bp" class="text-slate-700">BP000</span> - RTN/DNI: <span id="lbl_cliente_rtn" class="text-slate-700">0000000000000</span>)</span>
                </div>
                <input type="hidden" id="pos_cliente_bp_seleccionado" value="BP000">
                <input type="hidden" id="pos_cliente_rtn_seleccionado" value="0000000000000">
            </div>

            <!-- Buscador de Productos -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 search-container relative">
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                    <i class="fa-solid fa-barcode text-blue-600 mr-1"></i> Agregar Producto (Lector o Nombre):
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                        <i class="fa-solid fa-search text-xs"></i>
                    </span>
                    <input type="text" 
                           id="pos_input_producto" 
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" 
                           placeholder="Escanea el código de barras o escribe el nombre del producto..." 
                           onkeyup="manejarInputProducto(event)" 
                           autocomplete="off"
                           autofocus>
                    <div id="pos_sugerencias_producto" class="hidden absolute top-full left-0 w-full bg-white border border-slate-200 max-h-56 overflow-y-auto z-50 shadow-lg rounded-xl mt-1"></div>
                </div>
            </div>

            <!-- Tabla del Carrito -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 flex-grow flex flex-col">
                <h3 class="text-base font-bold text-slate-900 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-cart-shopping text-blue-600"></i> Productos en el Carrito
                </h3>
                <div class="overflow-x-auto flex-grow">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] uppercase tracking-wider font-semibold">
                                <th class="px-4 py-3">Código / Producto</th>
                                <th class="px-4 py-3">Stock</th>
                                <th class="px-4 py-3">Precio Orig.</th>
                                <th class="px-4 py-3 text-center">Descuento 🔒</th>
                                <th class="px-4 py-3 text-center" style="width: 80px;">Cant.</th>
                                <th class="px-4 py-3">Subtotal</th>
                                <th class="px-4 py-3 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tablaCarrito" class="divide-y divide-slate-100 text-sm">
                            <tr>
                                <td colspan="7" class="text-center text-slate-400 py-6">No hay productos agregados al carrito</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- COLUMNA DERECHA: Resumen de Venta y Pagos -->
        <div class="flex-1 min-w-[380px] flex flex-col gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col gap-4">
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice-dollar text-blue-600"></i> Resumen de Venta
                </h3>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Tipo de Comprobante:</label>
                    <select id="pos_tipo_comprobante" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                        <option value="Factura">Factura</option>
                        <option value="Ticket">Ticket de Venta</option>
                    </select>
                </div>

                <!-- Sección de Registrar Pago -->
                <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl">
                    <h5 class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                        <i class="fa-solid fa-plus-circle"></i> Registrar Pago
                    </h5>
                    <div class="mb-3">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Método:</label>
                        <select id="pos_metodo_pago" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 transition" onchange="cambiarMetodoPago()">
                            <option value="efectivo">💵 Efectivo</option>
                            <option value="tarjeta">💳 Tarjeta de Crédito / Débito</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Monto a Abonar (L.):</label>
                        <input type="number" id="pago_monto_input" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 transition" step="0.01" placeholder="0.00">
                    </div>

                    <div id="seccion_tarjeta_detalles" style="display: none;" class="space-y-3 mb-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre Titular:</label>
                            <input type="text" id="pago_tarjeta_nombre" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="Nombre en tarjeta">
                        </div>
                        <div class="flex gap-2">
                            <div class="flex-grow">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Últimos 4 Dig.:</label>
                                <input type="text" id="pago_tarjeta_digitos" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 transition" maxlength="4" placeholder="4589">
                            </div>
                            <div class="flex-grow">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Voucher/Ref:</label>
                                <input type="text" id="pago_tarjeta_voucher" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="987654">
                            </div>
                        </div>
                    </div>

                    <button type="button" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs py-2.5 rounded-xl transition shadow-xs flex items-center justify-center gap-1.5" onclick="agregarPago()">
                        <i class="fa-solid fa-plus text-xs"></i> Agregar Pago
                    </button>
                </div>

                <!-- Pagos Registrados -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-2">📌 Pagos Registrados:</label>
                    <div class="border border-slate-200 rounded-xl overflow-hidden">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold">
                                    <th class="p-2.5">Método</th>
                                    <th class="p-2.5">Detalle</th>
                                    <th class="p-2.5">Monto</th>
                                    <th class="p-2.5 text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tablaPagosRegistrados" class="divide-y divide-slate-100">
                                <tr>
                                    <td colspan="4" class="text-center text-slate-400 py-3">Sin pagos agregados</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Resumen de Totales -->
                <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl space-y-2 text-sm">
                    <div class="flex justify-between text-slate-600">
                        <span>Subtotal (Sin ISV):</span>
                        <span id="lbl_subtotal" class="font-medium text-slate-800">L. 0.00</span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>ISV (15%):</span>
                        <span id="lbl_isv" class="font-medium text-slate-800">L. 0.00</span>
                    </div>
                    <div class="flex justify-between text-emerald-600 font-medium" id="linea_ahorro" style="display: none;">
                        <span>🎉 Ahorro Total:</span>
                        <span id="lbl_ahorro">L. 0.00</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold text-slate-900 border-t border-slate-200 pt-2 mt-2">
                        <span>TOTAL A PAGAR:</span>
                        <span id="lbl_total" class="text-blue-600">L. 0.00</span>
                    </div>
                    <hr class="border-slate-200 my-1">
                    <div class="flex justify-between text-emerald-600 font-semibold text-xs">
                        <span>Total Abonado:</span>
                        <span id="lbl_total_abonado">L. 0.00</span>
                    </div>
                    <div class="flex justify-between text-rose-600 font-semibold text-xs" id="contenedor_saldo">
                        <span>Pendiente / Cambio:</span>
                        <span id="lbl_saldo_pendiente">L. 0.00</span>
                    </div>
                </div>

                <!-- Botones de Acción Final -->
                <div class="flex flex-col gap-2">
                    <!-- Botón condicional para Crédito -->
                    <div id="contenedor_btn_credito" style="display: none;">
                        <button type="button" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2.5 rounded-xl transition shadow-xs flex items-center justify-center gap-2 text-sm mb-2" onclick="abrirModalCredito()">
                            <i class="fa-solid fa-calculator"></i> Calcular Crédito
                        </button>
                    </div>

                    <button type="button" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2.5 rounded-xl transition shadow-xs flex items-center justify-center gap-2 text-sm" onclick="crearOrdenPendiente()">
                        <i class="fa-solid fa-file-lines"></i> <span id="btn_texto_orden">Crear / Guardar Orden</span>
                    </button>
                    
                    <button type="button" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl transition shadow-sm flex items-center justify-center gap-2 text-base" onclick="procesarVenta()">
                        <i class="fa-solid fa-circle-check"></i> Procesar Venta
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- MODAL DE DESCUENTO Y AUTORIZACIÓN -->
    <div id="modalClaveDescuento" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs hidden">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full mx-4 overflow-hidden">
            <div class="bg-blue-600 text-white px-5 py-4 flex justify-between items-center">
                <h5 class="font-bold text-sm sm:text-base flex items-center gap-2">
                    <i class="fa-solid fa-tags"></i> Aplicar Descuento Autorizado
                </h5>
                <button type="button" class="text-white/80 hover:text-white transition" onclick="cerrarModalDescuento()">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tipo de Descuento:</label>
                        <select id="select_tipo_descuento" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                            <option value="lempiras">Lempiras (L.)</option>
                            <option value="porcentaje">Porcentaje (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Valor del Descuento:</label>
                        <input type="number" id="input_valor_descuento" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>

                <hr class="border-slate-200">

                <div>
                    <label class="block text-xs font-semibold text-rose-600 mb-1 flex items-center gap-1">
                        <i class="fa-solid fa-shield-halved"></i> Clave Autorización (Admin/Supervisor):
                    </label>
                    <input type="password" id="input_clave_descuento" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="Escribe tu contraseña..." autocomplete="new-password">
                </div>
            </div>
            <div class="bg-slate-50 border-t border-slate-200 px-5 py-3 flex justify-end gap-2">
                <button type="button" class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-medium text-xs px-4 py-2 rounded-xl transition shadow-xs" onclick="cerrarModalDescuento()">Cancelar</button>
                <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition shadow-xs flex items-center gap-1" onclick="validarClaveYAplicarDescuento()">
                    <i class="fa-solid fa-key"></i> Autorizar y Aplicar
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL REGISTRO RÁPIDO DE CLIENTE -->
    <div id="modalClienteRapido" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs hidden">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full mx-4 overflow-hidden">
            <div class="bg-emerald-600 text-white px-5 py-4 flex justify-between items-center">
                <h5 class="font-bold text-sm sm:text-base flex items-center gap-2">
                    <i class="fa-solid fa-user-plus"></i> Registro Rápido de Cliente
                </h5>
                <button type="button" class="text-white/80 hover:text-white transition" onclick="cerrarModalClienteRapido()">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <form id="formClienteRapido">
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Tipo de Cliente:</label>
                        <select id="pos_cli_tipo" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition" onchange="cambiarEtiquetaRapida()">
                            <option value="natural">Persona Natural</option>
                            <option value="juridico">Persona Jurídica</option>
                        </select>
                    </div>

                    <div>
                        <label id="lbl_pos_doc" class="block text-xs font-semibold text-slate-700 mb-1">DNI / Identidad:</label>
                        <input type="text" id="pos_cli_rtn" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition" placeholder="Ej: 0801199012345" required>
                    </div>

                    <div>
                        <label id="lbl_pos_nombre" class="block text-xs font-semibold text-slate-700 mb-1">Nombre Completo:</label>
                        <input type="text" id="pos_cli_nombre" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition" required>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Teléfono (Opcional):</label>
                        <input type="text" id="pos_cli_telefono" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                    </div>
                </div>
                <div class="bg-slate-50 border-t border-slate-200 px-5 py-3 flex justify-end gap-2">
                    <button type="button" class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-medium text-xs px-4 py-2 rounded-xl transition shadow-xs" onclick="cerrarModalClienteRapido()">Cancelar</button>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition shadow-xs">Guardar y Seleccionar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DE CÁLCULO DE CRÉDITO -->
    <div id="modalCalculoCredito" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs hidden">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full mx-4 overflow-hidden">
            <div class="bg-blue-600 text-white px-5 py-4 flex justify-between items-center">
                <h5 class="font-bold text-sm sm:text-base flex items-center gap-2">
                    <i class="fa-solid fa-calculator"></i> Simulación y Cálculo de Crédito
                </h5>
                <button type="button" class="text-white/80 hover:text-white transition" onclick="cerrarModalCredito()">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="p-5 space-y-4 text-sm">
                <div class="grid grid-cols-2 gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <div>
                        <span class="text-xs text-slate-500 block">Total de Venta:</span>
                        <b id="credito_lbl_total" class="text-slate-900 text-base">L. 0.00</b>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500 block">Tasa de Interés:</span>
                        <b class="text-blue-600 text-base">25% Anual</b>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Monto de Prima (Opcional - L.):</label>
                    <input type="number" id="credito_input_prima" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" step="0.01" min="0" placeholder="0.00" oninput="calcularCuotasCredito()">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Plazo del Crédito (Meses):</label>
                    <select id="credito_select_plazo" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" onchange="calcularCuotasCredito()">
                        <!-- Se generan opciones del 1 al 24 dinámicamente -->
                    </select>
                </div>

                <div class="bg-blue-50 border border-blue-200 p-4 rounded-xl space-y-2">
                    <div class="flex justify-between text-slate-700">
                        <span>Monto Financiable (Capital / Contrato):</span>
                        <span id="credito_lbl_capital" class="font-semibold">L. 0.00</span>
                    </div>
                    <div class="flex justify-between text-slate-700">
                        <span>Interés Total Acumulado:</span>
                        <span id="credito_lbl_interes_total" class="font-semibold">L. 0.00</span>
                    </div>
                    <div class="flex justify-between text-slate-900 font-bold text-base border-t border-blue-200 pt-2">
                        <span>Cuota Estimada Mensual:</span>
                        <span id="credito_lbl_cuota" class="text-blue-600 text-lg">L. 0.00</span>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 border-t border-slate-200 px-5 py-3 flex justify-end gap-2">
                <button type="button" class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-medium text-xs px-4 py-2 rounded-xl transition shadow-xs" onclick="cerrarModalCredito()">Cerrar</button>
                <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition shadow-xs flex items-center gap-1" onclick="aplicarCreditoEnVenta()">
                    <i class="fa-solid fa-check"></i> Aplicar y Guardar Crédito
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts funcionales -->
    <script>
        let carrito = [];
        let listaPagos = [];
        let totalVentaActual = 0;
        let totalAhorroActual = 0;
        let timeoutCliente = null;
        let timeoutProducto = null;
        let productoPendienteDescId = null;
        let tipoModalidadVenta = 'contado'; // 'contado' o 'credito'
        let datosCreditoSeleccionado = null;
        
        // Variable global para almacenar el límite de crédito del cliente actual
        let limiteDisponibleCliente = 0;

        function escapeHtml(text) {
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        document.addEventListener('DOMContentLoaded', () => {
            const inputClave = document.getElementById('input_clave_descuento');
            if (inputClave) {
                inputClave.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.keyCode === 13) {
                        e.preventDefault();
                        validarClaveYAplicarDescuento();
                    }
                });
            }

            // Poblar opciones del 1 al 24 en el select de plazos
            const selectPlazo = document.getElementById('credito_select_plazo');
            if (selectPlazo) {
                let optionsHtml = '';
                for (let i = 1; i <= 24; i++) {
                    optionsHtml += `<option value="${i}">${i} ${i === 1 ? 'Mes' : 'Meses'}</option>`;
                }
                selectPlazo.innerHTML = optionsHtml;
            }

            const urlParams = new URLSearchParams(window.location.search);
            const idTransaccion = urlParams.get('id_transaccion') || urlParams.get('id');
            
            if (idTransaccion) {
                const btnTexto = document.getElementById('btn_texto_orden');
                if (btnTexto) btnTexto.innerText = `Actualizar Orden #${idTransaccion}`;
                
                cargarTransaccionEnPOS(idTransaccion);
            }

            const codigoBpParam = urlParams.get('codigo_bp') || urlParams.get('cliente');
            if (codigoBpParam && !idTransaccion) {
                fetch(`../api/pos_clientes.php?accion=buscar&q=${encodeURIComponent(codigoBpParam)}`)
                    .then(res => res.json())
                    .then(res => {
                        if (res.success && res.data.length > 0) {
                            const clienteEncontrado = res.data.find(c => c.codigo_bp === codigoBpParam || c.rtn_dni === codigoBpParam) || res.data[0];
                            seleccionarClientePOS(clienteEncontrado.codigo_bp, clienteEncontrado.rtn_dni, clienteEncontrado.Nombre, clienteEncontrado.estado, clienteEncontrado.limite_credito);
                        }
                    });
            }
        });

        // --- GESTIÓN DE CLIENTES ---
        function buscarClientePOS(query) {
            clearTimeout(timeoutCliente);
            timeoutCliente = setTimeout(() => {
                fetch(`../api/pos_clientes.php?accion=buscar&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(res => {
                    const div = document.getElementById('pos_sugerencias_cliente');
                    if (res.success && res.data.length > 0) {
                        let html = '';
                        res.data.forEach(c => {
                            const nombreEscaped = escapeHtml(c.Nombre);
                            const estadoBadge = c.estado === 'ACT' ? '<span class="text-emerald-600 font-bold">[ACT]</span>' : '<span class="text-slate-400">[INA]</span>';
                            const limiteCreditoVal = c.limite_credito || 0;
                            html += `
                                <div class="p-3 border-b border-slate-100 hover:bg-slate-50 cursor-pointer flex justify-between items-center text-xs sm:text-sm" onclick="seleccionarClientePOS('${c.codigo_bp}', '${c.rtn_dni}', '${nombreEscaped}', '${c.estado}', ${limiteCreditoVal})">
                                    <div><b>${c.codigo_bp}</b> - ${nombreEscaped} ${estadoBadge}</div>
                                    <span class="text-slate-500">${c.rtn_dni}</span>
                                </div>
                            `;
                        });
                        div.innerHTML = html;
                        div.classList.remove('hidden');
                    } else {
                        div.innerHTML = '<div class="p-3 text-slate-400 text-center text-xs">No se encontró cliente. <a href="#" onclick="abrirModalClienteRapido()" class="text-emerald-600 font-medium">¿Crear nuevo?</a></div>';
                        div.classList.remove('hidden');
                    }
                });
            }, 200);
        }

        function seleccionarClientePOS(codigo_bp, rtn_dni, nombre, estadoCliente = 'ACT', limiteCredito = 0) {
            document.getElementById('pos_cliente_bp_seleccionado').value = codigo_bp;
            document.getElementById('pos_cliente_rtn_seleccionado').value = rtn_dni || '0000000000000';
            document.getElementById('lbl_cliente_nombre').innerText = nombre;
            document.getElementById('lbl_cliente_bp').innerText = codigo_bp;
            document.getElementById('lbl_cliente_rtn').innerText = rtn_dni || '0000000000000';
            
            // Asignar el límite de crédito real del cliente
            limiteDisponibleCliente = parseFloat(limiteCredito) || 0;
            
            document.getElementById('pos_input_cliente').value = '';
            const divSugerencias = document.getElementById('pos_sugerencias_cliente');
            if(divSugerencias) divSugerencias.classList.add('hidden');

            // REGLA: Si el cliente es ACT, preguntar si es de crédito o contado
            if (estadoCliente === 'ACT' && codigo_bp !== 'BP000') {
                setTimeout(() => {
                    const esCredito = confirm(`El cliente ${nombre} se encuentra ACTIVO (Límite: L. ${limiteDisponibleCliente.toFixed(2)}).\n\n¿Desea realizar la venta bajo la modalidad de CRÉDITO?\n\n- Presione 'Aceptar' para Crédito.\n- Presione 'Cancelar' para Contado.`);
                    
                    if (esCredito) {
                        tipoModalidadVenta = 'credito';
                        document.getElementById('contenedor_btn_credito').style.display = 'block';
                        alert('📌 Modalidad de Crédito activada. Haga clic en "Calcular Crédito" cuando esté listo.');
                    } else {
                        tipoModalidadVenta = 'contado';
                        document.getElementById('contenedor_btn_credito').style.display = 'none';
                    }
                }, 100);
            } else {
                tipoModalidadVenta = 'contado';
                document.getElementById('contenedor_btn_credito').style.display = 'none';
            }
        }

        function resetearClienteConsumidorFinal() {
            seleccionarClientePOS('BP000', '0000000000000', 'Consumidor Final', 'INA', 0);
        }

        // --- GESTIÓN DE PRODUCTOS ---
        function manejarInputProducto(e) {
            const query = e.target.value.trim();

            if (e.key === 'Enter' && query !== '') {
                e.preventDefault();
                clearTimeout(timeoutProducto);
                
                fetch(`../api/pos_productos.php?accion=buscar&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(res => {
                    if (res.success && res.data.length > 0) {
                        const prod = res.data[0];
                        agregarAlCarrito(prod.id, prod.nombre, prod.precio, prod.stock);
                        document.getElementById('pos_input_producto').value = '';
                        document.getElementById('pos_sugerencias_producto').classList.add('hidden');
                    } else {
                        alert('Producto no encontrado con el código ingresado');
                    }
                });
                return;
            }

            clearTimeout(timeoutProducto);
            if (query === '') {
                document.getElementById('pos_sugerencias_producto').classList.add('hidden');
                return;
            }

            timeoutProducto = setTimeout(() => {
                fetch(`../api/pos_productos.php?accion=buscar&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(res => {
                    const div = document.getElementById('pos_sugerencias_producto');
                    if (res.success && res.data.length > 0) {
                        let html = '';
                        res.data.forEach(p => {
                            const codigoBadge = p.codigo ? `<span class="bg-slate-100 text-slate-700 border border-slate-200 text-[10px] font-semibold px-2 py-0.5 rounded-md me-1">🏷️ ${p.codigo}</span>` : '';
                            const nombreEscaped = escapeHtml(p.nombre);
                            html += `
                                <div class="p-3 border-b border-slate-100 hover:bg-slate-50 cursor-pointer flex justify-between items-center text-xs sm:text-sm" onclick="agregarAlCarrito(${p.id}, '${nombreEscaped}', ${p.precio}, ${p.stock})">
                                    <div>
                                        ${codigoBadge}<b class="text-slate-800">${nombreEscaped}</b> 
                                        <span class="text-slate-400 text-xs">(Stock: ${p.stock})</span>
                                    </div>
                                    <b class="text-emerald-600">L. ${parseFloat(p.precio).toFixed(2)}</b>
                                </div>
                            `;
                        });
                        div.innerHTML = html;
                        div.classList.remove('hidden');
                    } else {
                        div.innerHTML = '<div class="p-3 text-slate-400 text-center text-xs">No se encontraron productos</div>';
                        div.classList.remove('hidden');
                    }
                });
            }, 200);
        }

        function agregarAlCarrito(id, nombre, precio, stock) {
            const existe = carrito.find(item => item.id === id);
            if (existe) {
                if (existe.cantidad + 1 > stock) {
                    alert(`Supera el stock disponible (${stock})`);
                    return;
                }
                existe.cantidad++;
            } else {
                if (stock < 1) {
                    alert('Producto sin stock disponible');
                    return;
                }
                carrito.push({ id, nombre, precio: parseFloat(precio), descuento_unitario: 0, cantidad: 1, stock });
            }
            document.getElementById('pos_input_producto').value = '';
            document.getElementById('pos_sugerencias_producto').classList.add('hidden');
            document.getElementById('pos_input_producto').focus();
            renderizarCarrito();
        }

        function cambiarCantidad(id, nuevaCant) {
            const cant = parseInt(nuevaCant);
            const item = carrito.find(i => i.id === id);
            if (item) {
                if (cant > item.stock) {
                    alert(`El stock máximo disponible es ${item.stock}`);
                    item.cantidad = item.stock;
                } else if (cant <= 0 || isNaN(cant)) {
                    item.cantidad = 1;
                } else {
                    item.cantidad = cant;
                }
                renderizarCarrito();
            }
        }

        // --- GESTIÓN DE DESCUENTO ---
        function abrirModalDescuento(id) {
            const item = carrito.find(i => i.id === id);
            if (!item) return;

            productoPendienteDescId = id;
            document.getElementById('select_tipo_descuento').value = 'lempiras';
            document.getElementById('input_valor_descuento').value = item.descuento_unitario > 0 ? item.descuento_unitario : '';
            document.getElementById('input_clave_descuento').value = '';

            document.getElementById('modalClaveDescuento').classList.remove('hidden');
            setTimeout(() => {
                const inputVal = document.getElementById('input_valor_descuento');
                if (inputVal) {
                    inputVal.focus();
                    inputVal.select();
                }
            }, 50);
        }

        function cerrarModalDescuento() {
            document.getElementById('modalClaveDescuento').classList.add('hidden');
            productoPendienteDescId = null;
        }

        function validarClaveYAplicarDescuento() {
            const item = carrito.find(i => i.id === productoPendienteDescId);
            if (!item) return;

            const tipoDesc = document.getElementById('select_tipo_descuento').value;
            const valorIngresado = parseFloat(document.getElementById('input_valor_descuento').value) || 0;
            const clave = document.getElementById('input_clave_descuento').value.trim();

            let descUnitarioLempiras = 0;

            if (tipoDesc === 'porcentaje') {
                if (valorIngresado < 0 || valorIngresado > 100) {
                    alert('El porcentaje debe estar entre 0% y 100%.');
                    return;
                }
                descUnitarioLempiras = (item.precio * valorIngresado) / 100;
            } else {
                if (valorIngresado < 0 || valorIngresado > item.precio) {
                    alert('El descuento no puede superar el precio original (L. ' + item.precio.toFixed(2) + ').');
                    return;
                }
                descUnitarioLempiras = valorIngresado;
            }

            if (descUnitarioLempiras === 0) {
                item.descuento_unitario = 0;
                cerrarModalDescuento();
                renderizarCarrito();
                return;
            }

            if (!clave) {
                alert('Ingresa la clave de autorización.');
                return;
            }

            const formData = new FormData();
            formData.append('accion', 'validar_clave_descuento');
            formData.append('clave', clave);

            fetch('../api/pos_productos.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    item.descuento_unitario = descUnitarioLempiras;
                    cerrarModalDescuento();
                    renderizarCarrito();
                } else {
                    alert('❌ Clave incorrecta o no autorizada.');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error al verificar la clave.');
            });
        }

        function eliminarDelCarrito(id) {
            carrito = carrito.filter(i => i.id !== id);
            renderizarCarrito();
        }

        function renderizarCarrito() {
            const tbody = document.getElementById('tablaCarrito');
            if (carrito.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-slate-400 py-6">No hay productos agregados al carrito</td></tr>';
                document.getElementById('lbl_subtotal').innerText = 'L. 0.00';
                document.getElementById('lbl_isv').innerText = 'L. 0.00';
                document.getElementById('lbl_total').innerText = 'L. 0.00';
                document.getElementById('linea_ahorro').style.display = 'none';
                totalVentaActual = 0;
                totalAhorroActual = 0;
                actualizarResumenPagos();
                return;
            }

            let html = '';
            let totalConImpuestos = 0;
            let acumuladoAhorro = 0;

            carrito.forEach(item => {
                const descUnit = item.descuento_unitario || 0;
                const precioFinalUnit = Math.max(0, item.precio - descUnit);
                const subtotalItem = precioFinalUnit * item.cantidad;
                
                totalConImpuestos += subtotalItem;
                acumuladoAhorro += (descUnit * item.cantidad);

                const descBadge = descUnit > 0 
                    ? `<span class="bg-amber-100 text-amber-800 border border-amber-200 text-xs font-semibold px-2 py-0.5 rounded-lg">-L. ${(descUnit * item.cantidad).toFixed(2)}</span>` 
                    : `<span class="bg-slate-100 text-slate-600 border border-slate-200 text-xs font-medium px-2 py-0.5 rounded-lg">Sin desc.</span>`;

                html += `
                    <tr class="hover:bg-slate-50/50 transition border-b border-slate-100 last:border-none">
                        <td class="px-4 py-3 font-semibold text-slate-900">${escapeHtml(item.nombre)}</td>
                        <td class="px-4 py-3"><span class="bg-slate-100 text-slate-700 border border-slate-200 text-xs font-semibold px-2 py-0.5 rounded-lg">${item.stock} un.</span></td>
                        <td class="px-4 py-3 text-slate-600">L. ${item.precio.toFixed(2)}</td>
                        <td class="px-4 py-3 text-center">
                            <button type="button" class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-medium text-xs px-2.5 py-1.5 rounded-xl transition shadow-xs inline-flex items-center gap-1" onclick="abrirModalDescuento(${item.id})">
                                <i class="fa-solid fa-tag text-xs text-blue-600"></i> ${descBadge}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <input type="number" class="w-16 bg-slate-50 border border-slate-200 rounded-lg text-center py-1 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition mx-auto" value="${item.cantidad}" min="1" max="${item.stock}" onchange="cambiarCantidad(${item.id}, this.value)">
                        </td>
                        <td class="px-4 py-3 font-bold text-slate-900">L. ${subtotalItem.toFixed(2)}</td>
                        <td class="px-4 py-3 text-center">
                            <button class="bg-rose-50 hover:bg-rose-100 text-rose-600 font-medium text-xs px-2.5 py-1.5 rounded-xl transition shadow-xs" onclick="eliminarDelCarrito(${item.id})">
                                <i class="fa-solid fa-trash text-xs"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            totalVentaActual = totalConImpuestos;
            totalAhorroActual = acumuladoAhorro;

            const subtotalSinISV = totalVentaActual / 1.15;
            const isvDesglosado = totalVentaActual - subtotalSinISV;

            tbody.innerHTML = html;
            document.getElementById('lbl_subtotal').innerText = 'L. ' + subtotalSinISV.toFixed(2);
            document.getElementById('lbl_isv').innerText = 'L. ' + isvDesglosado.toFixed(2);
            document.getElementById('lbl_total').innerText = 'L. ' + totalVentaActual.toFixed(2);

            if (totalAhorroActual > 0) {
                document.getElementById('linea_ahorro').style.display = 'flex';
                document.getElementById('lbl_ahorro').innerText = 'L. ' + totalAhorroActual.toFixed(2);
            } else {
                document.getElementById('linea_ahorro').style.display = 'none';
            }

            actualizarResumenPagos();
        }

        // --- CARGAR TRANSACCIÓN DESDE LA API ---
        function cargarTransaccionEnPOS(idTransaccion) {
            fetch(`../api/obtener_detalle_venta.php?id_transaccion=${idTransaccion}`)
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        const venta = res.data;

                        if (venta.cliente_codigo_bp) {
                            seleccionarClientePOS(
                                venta.cliente_codigo_bp, 
                                venta.cliente_rtn || venta.cliente_identidad, 
                                venta.cliente_nombre || 'Cliente',
                                'INA',
                                venta.limite_credito || 0
                            );
                        }

                        if (venta.detalles && venta.detalles.length > 0) {
                            carrito = venta.detalles.map(item => ({
                                id: parseInt(item.producto_id || item.id_producto),
                                nombre: item.nombre_producto || item.nombre,
                                precio: parseFloat(item.precio_unitario || item.precio),
                                descuento_unitario: parseFloat(item.descuento_unitario || 0),
                                cantidad: parseInt(item.cantidad),
                                stock: parseInt(item.stock || 999)
                            }));

                            renderizarCarrito();
                        }
                    } else {
                        alert('❌ No se pudo cargar la transacción: ' + res.message);
                    }
                })
                .catch(err => {
                    console.error("Error al conectar con la API:", err);
                });
        }

        // --- GESTIÓN DE MÉTODOS DE PAGO ---
        function cambiarMetodoPago() {
            const metodo = document.getElementById('pos_metodo_pago').value;
            if (metodo === 'tarjeta') {
                document.getElementById('seccion_tarjeta_detalles').style.display = 'block';
            } else {
                document.getElementById('seccion_tarjeta_detalles').style.display = 'none';
            }
        }

        function agregarPago() {
            const monto = parseFloat(document.getElementById('pago_monto_input').value);
            if (isNaN(monto) || monto <= 0) {
                alert('Ingresa un monto válido para abonar.');
                return;
            }

            const metodo = document.getElementById('pos_metodo_pago').value;
            let detalle = 'Efectivo';
            let tarjetaInfo = null;

            if (metodo === 'tarjeta') {
                const nombre = document.getElementById('pago_tarjeta_nombre').value.trim();
                const digitos = document.getElementById('pago_tarjeta_digitos').value.trim();
                const voucher = document.getElementById('pago_tarjeta_voucher').value.trim();

                if (!nombre || !digitos || !voucher) {
                    alert('Completa los campos de la tarjeta (Titular, 4 Dígitos y Voucher).');
                    return;
                }
                detalle = `Tarjeta (****${digitos} - V: ${voucher})`;
                tarjetaInfo = { titular: nombre, digitos: digitos, voucher: voucher };
            }

            listaPagos.push({
                metodo: metodo === 'efectivo' ? 'Efectivo' : 'Tarjeta',
                monto: monto,
                detalle: detalle,
                detalles_tarjeta: tarjetaInfo
            });

            document.getElementById('pago_monto_input').value = '';
            document.getElementById('pago_tarjeta_nombre').value = '';
            document.getElementById('pago_tarjeta_digitos').value = '';
            document.getElementById('pago_tarjeta_voucher').value = '';

            renderizarPagos();
        }

        function eliminarPago(index) {
            listaPagos.splice(index, 1);
            renderizarPagos();
        }

        function renderizarPagos() {
            const tbody = document.getElementById('tablaPagosRegistrados');
            if (listaPagos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-slate-400 py-3">Sin pagos agregados</td></tr>';
                actualizarResumenPagos();
                return;
            }

            let html = '';
            listaPagos.forEach((pago, index) => {
                const badgeColor = pago.es_contrato_credito ? 'text-purple-600 font-extrabold' : 'text-emerald-600 font-bold';
                html += `
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-2.5 font-bold text-slate-800">${pago.metodo}</td>
                        <td class="p-2.5 text-slate-600">${escapeHtml(pago.detalle)}</td>
                        <td class="p-2.5 ${badgeColor}">L. ${pago.monto.toFixed(2)}</td>
                        <td class="p-2.5 text-center">
                            <button class="bg-rose-50 hover:bg-rose-100 text-rose-600 font-medium text-xs px-2 py-1 rounded-lg transition shadow-xs" onclick="eliminarPago(${index})">
                                <i class="fa-solid fa-trash text-xs"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
            actualizarResumenPagos();
        }

        function actualizarResumenPagos() {
            const totalAbonado = listaPagos
                .reduce((sum, item) => sum + item.monto, 0);

            const saldoDiferencia = totalVentaActual - totalAbonado;

            document.getElementById('lbl_total_abonado').innerText = 'L. ' + totalAbonado.toFixed(2);

            const lblSaldo = document.getElementById('lbl_saldo_pendiente');
            const contSaldo = document.getElementById('contenedor_saldo');

            if (saldoDiferencia > 0) {
                lblSaldo.innerText = 'Faltan L. ' + saldoDiferencia.toFixed(2);
                contSaldo.className = 'flex justify-between text-rose-600 font-semibold text-xs';
            } else if (saldoDiferencia < 0) {
                lblSaldo.innerText = 'Cambio: L. ' + Math.abs(saldoDiferencia).toFixed(2);
                contSaldo.className = 'flex justify-between text-emerald-600 font-semibold text-xs';
            } else {
                lblSaldo.innerText = 'L. 0.00 (Completado)';
                contSaldo.className = 'flex justify-between text-emerald-600 font-semibold text-xs';
            }
        }

        // --- CÁLCULO DE CRÉDITO ---
        function abrirModalCredito() {
            if (totalVentaActual <= 0) {
                alert('El carrito está vacío. Agregue productos antes de calcular un crédito.');
                return;
            }
            document.getElementById('credito_lbl_total').innerText = 'L. ' + totalVentaActual.toFixed(2);
            document.getElementById('credito_input_prima').value = '';
            document.getElementById('credito_select_plazo').value = '12';
            calcularCuotasCredito();
            document.getElementById('modalCalculoCredito').classList.remove('hidden');
        }

        function cerrarModalCredito() {
            document.getElementById('modalCalculoCredito').classList.add('hidden');
        }

        function calcularCuotasCredito() {
            const totalVenta = totalVentaActual;
            const prima = parseFloat(document.getElementById('credito_input_prima').value) || 0;
            const meses = parseInt(document.getElementById('credito_select_plazo').value) || 1;

            if (prima >= totalVenta) {
                alert('La prima no puede ser mayor o igual al total de la venta.');
                document.getElementById('credito_input_prima').value = 0;
                return;
            }

            const capitalFinanciable = totalVenta - prima;
            const tasaInteresAnual = 0.25; // 25% anual
            const anos = meses / 12;
            const interesTotal = capitalFinanciable * tasaInteresAnual * anos;
            const montoTotalConInteres = capitalFinanciable + interesTotal;
            const cuotaMensual = montoTotalConInteres / meses;

            document.getElementById('credito_lbl_capital').innerText = 'L. ' + capitalFinanciable.toFixed(2);
            document.getElementById('credito_lbl_interes_total').innerText = 'L. ' + interesTotal.toFixed(2);
            document.getElementById('credito_lbl_cuota').innerText = 'L. ' + cuotaMensual.toFixed(2);

            datosCreditoSeleccionado = {
                totalVenta,
                prima,
                capitalFinanciable,
                interesTotal,
                montoTotalConInteres,
                meses,
                cuotaMensual
            };
        }

        function aplicarCreditoEnVenta() {
            if (!datosCreditoSeleccionado) {
                alert('Primero debe realizar el cálculo del crédito.');
                return;
            }

            const montoAFacturarCredito = parseFloat(datosCreditoSeleccionado.capitalFinanciable);
            const limiteMaximoCliente = parseFloat(limiteDisponibleCliente) || 0;

            // Validación 1: Si el límite es 0 o no está configurado
            if (limiteMaximoCliente <= 0) {
                alert('❌ Error: Este cliente no tiene un límite de crédito configurado (L. 0.00).');
                return;
            }

            // Validación 2: Si el monto a financiar supera el límite del cliente
            if (montoAFacturarCredito > limiteMaximoCliente) {
                alert(
                    `⚠️ LÍMITE DE CRÉDITO EXCEDIDO:\n\n` +
                    `- Monto a Financiar: L. ${montoAFacturarCredito.toFixed(2)}\n` +
                    `- Límite del Cliente: L. ${limiteMaximoCliente.toFixed(2)}\n\n` +
                    `Por favor, aumente la prima para reducir el capital a financiar.`
                );
                return; // Bloquea y evita que se procese el crédito
            }

            listaPagos = listaPagos.filter(p => !p.es_contrato_credito);

            listaPagos.push({
                metodo: 'Crédito',
                monto: datosCreditoSeleccionado.capitalFinanciable,
                detalle: `Contrato Crédito (${datosCreditoSeleccionado.meses} meses - Cuota: L. ${datosCreditoSeleccionado.cuotaMensual.toFixed(2)})`,
                es_contrato_credito: true
            });

            renderizarPagos();

            alert(`✅ Contrato de Crédito aplicado con éxito:\n- Capital Financiable (Contrato): L. ${datosCreditoSeleccionado.capitalFinanciable.toFixed(2)}\n- Plazo: ${datosCreditoSeleccionado.meses} meses\n- Cuota Mensual: L. ${datosCreditoSeleccionado.cuotaMensual.toFixed(2)}\n⚠️ La prima (L. ${datosCreditoSeleccionado.prima.toFixed(2)}) queda pendiente para ser registrada manualmente.`);
            cerrarModalCredito();
        }

        // --- CREAR / ACTUALIZAR ORDEN PENDIENTE ---
        function crearOrdenPendiente() {
            if (carrito.length === 0) {
                alert('El carrito está vacío. Agregue productos para guardar la orden.');
                return;
            }

            const urlParams = new URLSearchParams(window.location.search);
            const idTransaccion = urlParams.get('id_transaccion') || urlParams.get('id');
            const esEdicion = Boolean(idTransaccion);

            const clienteNombre = document.getElementById('lbl_cliente_nombre').innerText;
            const accionMsg = esEdicion ? `actualizar la orden #${idTransaccion}` : 'crear una nueva orden o cotización pendiente';

            if (!confirm(`❓ ¿Desea ${accionMsg} para el cliente ${clienteNombre}?`)) {
                return;
            }

            let montoEfectivoTotal = 0;
            let montoTarjetaTotal = 0;
            listaPagos.forEach(p => {
                if (p.metodo.toLowerCase().includes('efectivo')) montoEfectivoTotal += p.monto;
                else if (p.metodo.toLowerCase().includes('tarjeta')) montoTarjetaTotal += p.monto;
            });

            const esVentaCredito = (tipoModalidadVenta === 'credito' && datosCreditoSeleccionado !== null);

            const payload = {
                codigo_bp: document.getElementById('pos_cliente_bp_seleccionado').value,
                cliente_identidad: document.getElementById('pos_cliente_rtn_seleccionado').value,
                cliente_rtn: document.getElementById('pos_cliente_rtn_seleccionado').value,
                tipo_comprobante: document.getElementById('pos_tipo_comprobante').value,
                monto_efectivo: montoEfectivoTotal,
                monto_tarjeta: montoTarjetaTotal,
                modalidad: tipoModalidadVenta,
                
                // 🚀 CAMPOS DE CRÉDITO REQUERIDOS POR EL BACKEND DE PHP
                es_credito: esVentaCredito,
                plazo_meses: esVentaCredito ? datosCreditoSeleccionado.meses : 0,
                prima: esVentaCredito ? datosCreditoSeleccionado.prima : 0,
                monto_financiar: esVentaCredito ? datosCreditoSeleccionado.capitalFinanciable : 0,
                total_credito: esVentaCredito ? datosCreditoSeleccionado.montoTotalConInteres : 0,

                datos_credito: datosCreditoSeleccionado,
                carrito: carrito,
                pagos: listaPagos,
                ahorro_total: totalAhorroActual,
                estado: 'pendiente'
            };

            let endpoint = '../api/procesar_venta.php';
            if (esEdicion) {
                payload.id_orden = idTransaccion;
            }

            fetch(endpoint, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(async res => {
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Respuesta del servidor no es JSON:", text);
                    throw new Error("Error en el servidor: " + text);
                }
            })
            .then(data => {
                if (data.success) {
                    if (esEdicion) {
                        alert(`✅ Orden #${idTransaccion} actualizada exitosamente.`);
                        window.location.href = 'transacciones.php';
                    } else {
                        alert('✅ ' + (data.message || 'Orden creada exitosamente con ID: ' + (data.venta_id || 'N/D')));
                        carrito = [];
                        listaPagos = [];
                        renderizarCarrito();
                        renderizarPagos();
                        resetearClienteConsumidorFinal();
                    }
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error("Error en Fetch:", err);
                alert(err.message);
            });
        }

        // --- PROCESAR VENTA ---
        function procesarVenta() {
            if (carrito.length === 0) {
                alert('El carrito está vacío');
                return;
            }

            if (listaPagos.length === 0) {
                alert('Debes agregar al menos un método de pago o contrato de crédito.');
                return;
            }

            const clienteNombre = document.getElementById('lbl_cliente_nombre').innerText;
            const clienteRTN = document.getElementById('lbl_cliente_rtn').innerText;

            let desglosePagosText = listaPagos.map(p => `- ${p.metodo}: L. ${p.monto.toFixed(2)} (${p.detalle})`).join('\n');

            const mensajeConfirmacion = 
                `❓ ¿Estás seguro de procesar esta venta?\n\n` +
                `👤 Cliente: ${clienteNombre}\n` +
                `🆔 DNI/RTN: ${clienteRTN}\n` +
                `📌 Modalidad: ${tipoModalidadVenta.toUpperCase()}\n` +
                `💵 Total Venta: L. ${totalVentaActual.toFixed(2)}\n` +
                `🎉 Ahorro Total: L. ${totalAhorroActual.toFixed(2)}\n\n` +
                `Desglose / Pagos Registrados:\n${desglosePagosText}\n\n` +
                `Presiona 'Aceptar' para guardar e imprimir comprobante.`;

            if (!confirm(mensajeConfirmacion)) {
                return;
            }

            const urlParams = new URLSearchParams(window.location.search);
            const idTransaccion = urlParams.get('id_transaccion') || urlParams.get('id');
            const esEdicion = Boolean(idTransaccion);

            let montoEfectivoBruto = 0;
            let montoTarjetaTotal = 0;

            listaPagos.forEach(p => {
                const metodoLower = p.metodo.toLowerCase();
                if (metodoLower.includes('efectivo')) {
                    montoEfectivoBruto += p.monto;
                } else if (metodoLower.includes('tarjeta')) {
                    montoTarjetaTotal += p.monto;
                }
            });

            const esVentaCredito = (tipoModalidadVenta === 'credito' && datosCreditoSeleccionado !== null);

            const payload = {
                codigo_bp: document.getElementById('pos_cliente_bp_seleccionado').value,
                cliente_identidad: document.getElementById('pos_cliente_rtn_seleccionado').value,
                cliente_rtn: document.getElementById('pos_cliente_rtn_seleccionado').value,
                tipo_comprobante: document.getElementById('pos_tipo_comprobante').value,
                monto_efectivo: montoEfectivoBruto,
                monto_tarjeta: montoTarjetaTotal,
                modalidad: tipoModalidadVenta,
                
                // 🚀 CAMPOS DE CRÉDITO REQUERIDOS POR EL BACKEND DE PHP
                es_credito: esVentaCredito,
                plazo_meses: esVentaCredito ? datosCreditoSeleccionado.meses : 0,
                prima: esVentaCredito ? datosCreditoSeleccionado.prima : 0,
                monto_financiar: esVentaCredito ? datosCreditoSeleccionado.capitalFinanciable : 0,
                total_credito: esVentaCredito ? datosCreditoSeleccionado.montoTotalConInteres : 0,

                datos_credito: datosCreditoSeleccionado,
                carrito: carrito,
                pagos: listaPagos,
                ahorro_total: totalAhorroActual
            };

            if (esEdicion) {
                payload.id_orden = idTransaccion;
            }

            fetch('../api/procesar_venta.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(async res => {
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Respuesta del servidor no es JSON:", text);
                    throw new Error("Error en el servidor: " + text);
                }
            })
            .then(data => {
                if (data.success) {
                    let mensajeExito = '✅ Venta procesada exitosamente.';
                    if (data.alerta_sar) {
                        mensajeExito += '\n' + data.alerta_sar;
                    }

                    alert(mensajeExito);
                    window.open(`imprimir_factura.php?id=${data.venta_id}`, '_blank', 'width=400,height=600');

                    if (esEdicion) {
                        window.location.href = 'transacciones.php';
                    } else {
                        carrito = [];
                        listaPagos = [];
                        renderizarCarrito();
                        renderizarPagos();
                        resetearClienteConsumidorFinal();
                    }
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(err => {
                console.error("Error en Fetch:", err);
                alert(err.message);
            });
        }

        // --- CLIENTE RÁPIDO ---
        function cambiarEtiquetaRapida() {
            const tipo = document.getElementById('pos_cli_tipo').value;
            document.getElementById('lbl_pos_doc').innerText = tipo === 'juridico' ? 'RTN (Empresa):' : 'DNI / Identidad:';
            document.getElementById('lbl_pos_nombre').innerText = tipo === 'juridico' ? 'Razón Social:' : 'Nombre Completo:';
        }

        function abrirModalClienteRapido() {
            document.getElementById('formClienteRapido').reset();
            cambiarEtiquetaRapida();
            document.getElementById('modalClienteRapido').classList.remove('hidden');
        }

        function cerrarModalClienteRapido() {
            document.getElementById('modalClienteRapido').classList.add('hidden');
        }

        document.getElementById('formClienteRapido').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('accion', 'crear_rapido');
            formData.append('tipo_cliente', document.getElementById('pos_cli_tipo').value);
            formData.append('rtn_dni', document.getElementById('pos_cli_rtn').value);
            formData.append('nombre', document.getElementById('pos_cli_nombre').value);
            formData.append('telefono', document.getElementById('pos_cli_telefono').value);

            fetch('../api/pos_clientes.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    seleccionarClientePOS(data.data.codigo_bp, data.data.rtn_dni, data.data.Nombre, data.data.estado, data.data.limite_credito || 0);
                    cerrarModalClienteRapido();
                } else {
                    alert(data.message);
                }
            });
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                const sugC = document.getElementById('pos_sugerencias_cliente');
                const sugP = document.getElementById('pos_sugerencias_producto');
                if (sugC) sugC.classList.add('hidden');
                if (sugP) sugP.classList.add('hidden');
            }
        });
    </script>
</body>
</html>