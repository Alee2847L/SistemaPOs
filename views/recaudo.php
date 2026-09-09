<?php
// views/recaudo.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
$rolActual = $_SESSION['usuario_rol'] ?? 'vendedor';
// --- OBTENER EL NOMBRE DE LA EMPRESA DESDE LA BD ---
$nombre_empresa = "INVERSIONES J.A"; // Valor por defecto
try {
    // Si tu variable de conexión usa otro nombre (ej. $conn), cámbiala aquí
    $stmt_config = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1");
    if ($row_config = $stmt_config->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row_config['nombre_empresa'])) {
            $nombre_empresa = htmlspecialchars($row_config['nombre_empresa']);
        }
    }
} catch (Exception $e) {
    // Si ocurre algún error o la tabla no existe, se mantiene el valor por defecto
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Recaudo — <?php echo $nombre_empresa; ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { 50: '#f8fafc', 100: '#f1f5f9', 600: '#2563eb', 700: '#1d4ed8' }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Barra de Navegación -->
    <header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-3.5 flex justify-between items-center sticky top-0 z-40 shadow-xs">
        <div class="flex items-center gap-2">
            <div class="bg-emerald-600 text-white p-2 rounded-xl shadow-sm"><i class="fa-solid fa-hand-holding-dollar text-sm"></i></div>
            <div>
                <span class="font-bold text-sm sm:text-base text-slate-900 block leading-none">Módulo de Recaudo y Cuotas</span>
                <span class="text-[11px] text-slate-400 font-medium"><?php echo $nombre_empresa; ?></span>
            </div>
        </div>
        <div>
            <a href="clientes.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-xs px-3.5 py-2 rounded-xl transition flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i> Volver a Clientes
            </a>
        </div>
    </header>

    <!-- Contenido Principal -->
    <main class="max-w-[1400px] w-full mx-auto px-4 sm:px-6 py-6 flex-grow flex flex-col lg:flex-row gap-6">
        
        <!-- COLUMNA IZQUIERDA: Búsqueda y Selección de Créditos -->
        <div class="flex-2 flex flex-col gap-5">
            
            <!-- Buscador de Cliente -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 relative search-container">
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                    <i class="fa-solid fa-magnifying-glass text-blue-600 mr-1"></i> Buscar Cliente (por Código BP o DNI/RTN o Nombre):
                </label>
                <div class="relative">
                    <input type="text" id="input_buscar_recaudo" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-4 pr-3.5 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="Escriba para buscar cliente..." onkeyup="buscarClienteRecaudo(this.value)" autocomplete="off">
                    <div id="sugerencias_recaudo" class="hidden absolute top-full left-0 w-full bg-white border border-slate-200 max-h-56 overflow-y-auto z-50 shadow-lg rounded-xl mt-1"></div>
                </div>

                <!-- Info Cliente Seleccionado -->
                <div id="info_cliente_seleccionado" class="mt-4 p-4 bg-slate-50 border border-slate-200 rounded-xl hidden">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="text-xs text-slate-400 font-semibold block">CLIENTE SELECCIONADO</span>
                            <h4 id="lbl_nombre_cliente" class="font-bold text-slate-900 text-base">Nombre del Cliente</h4>
                            <p class="text-xs text-slate-600 mt-0.5">BP: <span id="lbl_bp_cliente" class="font-medium text-slate-800"></span> | DNI/RTN: <span id="lbl_rtn_cliente" class="font-medium text-slate-800"></span></p>
                        </div>
                        <button type="button" onclick="limpiarSeleccionCliente()" class="text-rose-500 hover:text-rose-700 text-xs font-medium bg-rose-50 px-2.5 py-1 rounded-lg">Cambiar</button>
                    </div>
                    <input type="hidden" id="hidden_bp_seleccionado">
                </div>
            </div>

            <!-- Listado de Créditos / Contratos Activos -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex-grow flex flex-col">
                <h3 class="text-base font-bold text-slate-900 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-file-contract text-blue-600"></i> Créditos y Contratos Activos
                </h3>
                <div class="overflow-x-auto flex-grow">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] uppercase tracking-wider font-semibold">
                                <th class="px-4 py-3">ID Contrato</th>
                                <th class="px-4 py-3">Plazo</th>
                                <th class="px-4 py-3">Monto Financiado</th>
                                <th class="px-4 py-3">Cuota Mensual</th>
                                <th class="px-4 py-3 text-center">Acción / Seleccionar</th>
                            </tr>
                        </thead>
                        <tbody id="tablaContratosCliente" class="divide-y divide-slate-100 text-sm">
                            <tr>
                                <td colspan="5" class="text-center text-slate-400 py-8">Seleccione un cliente para ver sus créditos activos</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabla de Cuotas Pendientes del Contrato Seleccionado -->
            <div id="seccion_cuotas" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hidden">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-emerald-600"></i> Cuotas Pendientes (Contrato #<span id="lbl_nro_contrato">0</span>)
                    </h3>
                    <div class="flex gap-2">
                        <select id="select_tipo_pago_modalidad" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs font-semibold text-slate-700" onchange="cambiarTipoModoRecaudo()">
                            <option value="cuota">Pago de Cuota(s)</option>
                            <option value="total">Pago Total del Contrato (Cancelación)</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase tracking-wider font-semibold">
                                <th class="p-2.5 text-center"><input type="checkbox" id="chk_todas_cuotas" onclick="seleccionarTodasCuotas(this)"></th>
                                <th class="p-2.5">N° Cuota</th>
                                <th class="p-2.5">Vencimiento</th>
                                <th class="p-2.5">Monto Cuota</th>
                                <th class="p-2.5">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="tablaCuotasPendientes" class="divide-y divide-slate-100">
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- COLUMNA DERECHA: Resumen de Pago y Métodos -->
        <div class="flex-1 min-w-[380px] flex flex-col gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col gap-4">
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-cash-register text-blue-600"></i> Ejecutar Recaudo / Pago
                </h3>

                <!-- Sección para Agregar Método de Pago -->
                <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl">
                    <h5 class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                        <i class="fa-solid fa-plus-circle"></i> Agregar Método de Pago
                    </h5>
                    <div class="mb-3">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Método:</label>
                        <select id="recaudo_metodo_pago" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 transition" onchange="cambiarMetodoPagoRecaudo()">
                            <option value="efectivo">💵 Efectivo</option>
                            <option value="tarjeta">💳 Tarjeta de Crédito / Débito</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Monto a Abonar (L.):</label>
                        <input type="number" id="recaudo_monto_input" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 transition" step="0.01" placeholder="0.00">
                    </div>

                    <div id="seccion_tarjeta_recaudo" style="display: none;" class="space-y-3 mb-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre Titular:</label>
                            <input type="text" id="recaudo_tarjeta_nombre" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800" placeholder="Nombre en tarjeta">
                        </div>
                        <div class="flex gap-2">
                            <div class="flex-grow">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Últimos 4 Dig.:</label>
                                <input type="text" id="recaudo_tarjeta_digitos" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800" maxlength="4" placeholder="4589">
                            </div>
                            <div class="flex-grow">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Voucher/Ref:</label>
                                <input type="text" id="recaudo_tarjeta_voucher" class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800" placeholder="987654">
                            </div>
                        </div>
                    </div>

                    <button type="button" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs py-2.5 rounded-xl transition shadow-xs flex items-center justify-center gap-1.5" onclick="agregarPagoRecaudo()">
                        <i class="fa-solid fa-plus text-xs"></i> Agregar Pago al Listado
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
                            <tbody id="tablaPagosRecaudo" class="divide-y divide-slate-100">
                                <tr>
                                    <td colspan="4" class="text-center text-slate-400 py-3">Sin pagos agregados</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Totales y Resumen -->
                <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl space-y-2 text-sm">
                    <div class="flex justify-between text-slate-600 font-medium">
                        <span>Total a Cobrar Selección:</span>
                        <span id="lbl_total_cobrar" class="text-slate-900 font-bold">L. 0.00</span>
                    </div>
                    <div class="flex justify-between text-emerald-600 font-semibold text-xs">
                        <span>Total Abonado:</span>
                        <span id="lbl_recaudo_abonado">L. 0.00</span>
                    </div>
                    <div class="flex justify-between text-rose-600 font-semibold text-xs" id="contenedor_saldo_recaudo">
                        <span>Pendiente / Cambio:</span>
                        <span id="lbl_recaudo_saldo">L. 0.00</span>
                    </div>
                </div>

                <!-- Botón Procesar Recaudo -->
                <button type="button" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl transition shadow-sm flex items-center justify-center gap-2 text-base" onclick="procesarPagoRecaudo()">
                    <i class="fa-solid fa-circle-check"></i> Procesar y Registrar Recaudo
                </button>
            </div>
        </div>

    </main>

    <!-- Script de lógica de recaudo -->
    <script>
        let clienteSeleccionadoActual = null;
        let contratoSeleccionadoActual = null;
        let listaCuotasContrato = [];
        let listaPagosRecaudo = [];
        let timeoutBusqueda = null;

        function escapeHtml(text) {
            return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        // --- BUSCADOR DE CLIENTES ---
        function buscarClienteRecaudo(query) {
            clearTimeout(timeoutBusqueda);
            if (!query.trim()) {
                document.getElementById('sugerencias_recaudo').classList.add('hidden');
                return;
            }

            timeoutBusqueda = setTimeout(() => {
                fetch(`/api/pos_clientes.php?accion=buscar&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(res => {
                    const div = document.getElementById('sugerencias_recaudo');
                    if (res.success && res.data.length > 0) {
                        let html = '';
                        res.data.forEach(c => {
                            html += `
                                <div class="p-3 border-b border-slate-100 hover:bg-slate-50 cursor-pointer flex justify-between items-center text-xs sm:text-sm" onclick="seleccionarClienteRecaudo('${c.codigo_bp}', '${c.rtn_dni}', '${escapeHtml(c.Nombre)}')">
                                    <div><b>${c.codigo_bp}</b> - ${escapeHtml(c.Nombre)}</div>
                                    <span class="text-slate-500">${c.rtn_dni}</span>
                                </div>
                            `;
                        });
                        div.innerHTML = html;
                        div.classList.remove('hidden');
                    } else {
                        div.innerHTML = '<div class="p-3 text-slate-400 text-center text-xs">No se encontró ningún cliente</div>';
                        div.classList.remove('hidden');
                    }
                });
            }, 200);
        }

        function seleccionarClienteRecaudo(bp, rtn, nombre) {
            clienteSeleccionadoActual = { bp, rtn, nombre };
            document.getElementById('hidden_bp_seleccionado').value = bp;
            document.getElementById('lbl_bp_cliente').innerText = bp;
            document.getElementById('lbl_rtn_cliente').innerText = rtn;
            document.getElementById('lbl_nombre_cliente').innerText = nombre;
            
            document.getElementById('input_buscar_recaudo').value = '';
            document.getElementById('sugerencias_recaudo').classList.add('hidden');
            document.getElementById('info_cliente_seleccionado').classList.remove('hidden');

            cargarContratosCliente(bp);
        }

        function limpiarSeleccionCliente() {
            clienteSeleccionadoActual = null;
            contratoSeleccionadoActual = null;
            listaCuotasContrato = [];
            listaPagosRecaudo = [];
            document.getElementById('info_cliente_seleccionado').classList.add('hidden');
            document.getElementById('seccion_cuotas').classList.add('hidden');
            document.getElementById('tablaContratosCliente').innerHTML = '<tr><td colspan="5" class="text-center text-slate-400 py-8">Seleccione un cliente para ver sus créditos activos</td></tr>';
            renderizarPagosRecaudo();
        }

        // --- CARGAR CONTRATOS ACTIVOS ---
        function cargarContratosCliente(codigoBp) {
            fetch(`/api/recaudo.php?accion=listar_contratos&codigo_bp=${encodeURIComponent(codigoBp)}`)
            .then(res => res.json())
            .then(res => {
                const tbody = document.getElementById('tablaContratosCliente');
                
                const contratosActivos = res.success && Array.isArray(res.data) 
                    ? res.data.filter(c => (c.estado || '').toLowerCase() === 'activo') 
                    : [];

                if (contratosActivos.length > 0) {
                    let html = '';
                    contratosActivos.forEach(cont => {
                        html += `
                            <tr class="hover:bg-slate-50 transition border-b border-slate-100">
                                <td class="px-4 py-3 font-bold text-slate-900">
                                    #${cont.id_contrato}
                                    <span class="ml-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">ACTIVO</span>
                                </td>
                                <td class="px-4 py-3">${cont.plazo_meses} Meses</td>
                                <td class="px-4 py-3 font-semibold text-slate-700">L. ${Number(cont.monto_financiar).toFixed(2)}</td>
                                <td class="px-4 py-3 font-bold text-blue-600">L. ${Number(cont.cuota_mensual).toFixed(2)}</td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white font-medium text-xs px-3 py-1.5 rounded-xl shadow-xs transition" onclick="seleccionarContrato(${cont.id_contrato})">
                                        Seleccionar
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-slate-400 py-6">El cliente no tiene contratos o créditos activos.</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error al cargar contratos:', error);
                document.getElementById('tablaContratosCliente').innerHTML = '<tr><td colspan="5" class="text-center text-red-500 py-6">Error al consultar los contratos.</td></tr>';
            });
        }

        // --- CARGAR CUOTAS ---
        function seleccionarContrato(idContrato) {
            contratoSeleccionadoActual = idContrato;
            document.getElementById('lbl_nro_contrato').innerText = idContrato;
            document.getElementById('seccion_cuotas').classList.remove('hidden');

            fetch(`/api/recaudo.php?accion=listar_cuotas&id_contrato=${idContrato}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    listaCuotasContrato = res.data;
                    renderizarCuotas();
                }
            });
        }

        function renderizarCuotas() {
            const tbody = document.getElementById('tablaCuotasPendientes');
            if (listaCuotasContrato.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-slate-400 py-4">No hay cuotas pendientes</td></tr>';
                return;
            }

            let html = '';
            listaCuotasContrato.forEach((cuota, index) => {
                const pagada = (cuota.estado || '').toLowerCase() === 'pagado';
                html += `
                    <tr class="hover:bg-slate-50 border-b border-slate-100 ${pagada ? 'bg-slate-100/50 text-slate-400' : ''}">
                        <td class="p-2.5 text-center">
                            <input type="checkbox" class="chk-cuota" value="${cuota.id_cuota}" data-monto="${cuota.monto}" ${pagada ? 'disabled' : ''} onchange="calcularTotalCobrar()">
                        </td>
                        <td class="p-2.5 font-bold">Cuota #${cuota.numero_cuota}</td>
                        <td class="p-2.5">${cuota.fecha_vencimiento}</td>
                        <td class="p-2.5 font-semibold text-slate-800">L. ${Number(cuota.monto).toFixed(2)}</td>
                        <td class="p-2.5"><span class="px-2 py-0.5 rounded-md text-[10px] font-bold ${pagada ? 'bg-slate-200 text-slate-600' : 'bg-amber-100 text-amber-700'}">${(cuota.estado || '').toUpperCase()}</span></td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
            calcularTotalCobrar();
        }

        function seleccionarTodasCuotas(master) {
            const checkboxes = document.querySelectorAll('.chk-cuota:not(:disabled)');
            checkboxes.forEach(chk => chk.checked = master.checked);
            calcularTotalCobrar();
        }

        function cambiarTipoModoRecaudo() {
            const tipo = document.getElementById('select_tipo_pago_modalidad').value;
            const checkboxes = document.querySelectorAll('.chk-cuota:not(:disabled)');
            
            if (tipo === 'total') {
                checkboxes.forEach(chk => chk.checked = true);
            } else {
                checkboxes.forEach(chk => chk.checked = false);
            }
            calcularTotalCobrar();
        }

        function calcularTotalCobrar() {
            let total = 0;
            const checkboxes = document.querySelectorAll('.chk-cuota:checked');
            checkboxes.forEach(chk => {
                total += parseFloat(chk.getAttribute('data-monto') || 0);
            });
            document.getElementById('lbl_total_cobrar').innerText = 'L. ' + total.toFixed(2);
            document.getElementById('recaudo_monto_input').value = total > 0 ? total.toFixed(2) : '';
        }

        // --- GESTIÓN DE PAGOS ---
        function cambiarMetodoPagoRecaudo() {
            const metodo = document.getElementById('recaudo_metodo_pago').value;
            document.getElementById('seccion_tarjeta_recaudo').style.display = (metodo === 'tarjeta') ? 'block' : 'none';
        }

        function agregarPagoRecaudo() {
            const monto = parseFloat(document.getElementById('recaudo_monto_input').value);
            if (isNaN(monto) || monto <= 0) {
                alert('Ingrese un monto válido para abonar.');
                return;
            }

            const metodo = document.getElementById('recaudo_metodo_pago').value;
            let detalle = 'Efectivo';
            let tarjetaInfo = null;

            if (metodo === 'tarjeta') {
                const nombre = document.getElementById('recaudo_tarjeta_nombre').value.trim();
                const digitos = document.getElementById('recaudo_tarjeta_digitos').value.trim();
                const voucher = document.getElementById('recaudo_tarjeta_voucher').value.trim();

                if (!nombre || !digitos || !voucher) {
                    alert('Complete los datos de la tarjeta.');
                    return;
                }
                detalle = `Tarjeta (****${digitos} - V: ${voucher})`;
                tarjetaInfo = { titular: nombre, digitos, voucher };
            }

            listaPagosRecaudo.push({ metodo: metodo === 'efectivo' ? 'Efectivo' : 'Tarjeta', monto, detalle, detalles_tarjeta: tarjetaInfo });
            document.getElementById('recaudo_monto_input').value = '';
            renderizarPagosRecaudo();
        }

        function eliminarPagoRecaudo(index) {
            listaPagosRecaudo.splice(index, 1);
            renderizarPagosRecaudo();
        }

        function renderizarPagosRecaudo() {
            const tbody = document.getElementById('tablaPagosRecaudo');
            if (listaPagosRecaudo.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-slate-400 py-3">Sin pagos agregados</td></tr>';
                actualizarResumenRecaudo();
                return;
            }

            let html = '';
            listaPagosRecaudo.forEach((p, idx) => {
                html += `
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-2.5 font-bold text-slate-800">${p.metodo}</td>
                        <td class="p-2.5 text-slate-600">${escapeHtml(p.detalle)}</td>
                        <td class="p-2.5 font-bold text-emerald-600">L. ${p.monto.toFixed(2)}</td>
                        <td class="p-2.5 text-center">
                            <button class="bg-rose-50 text-rose-600 px-2 py-1 rounded-md text-xs" onclick="eliminarPagoRecaudo(${idx})"><i class="fa-solid fa-trash"></i></button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
            actualizarResumenRecaudo();
        }

        function actualizarResumenRecaudo() {
            const totalCobrar = parseFloat(document.getElementById('lbl_total_cobrar').innerText.replace('L. ', '')) || 0;
            const totalAbonado = listaPagosRecaudo.reduce((sum, p) => sum + p.monto, 0);
            const diferencia = totalCobrar - totalAbonado;

            document.getElementById('lbl_recaudo_abonado').innerText = 'L. ' + totalAbonado.toFixed(2);
            const lblSaldo = document.getElementById('lbl_recaudo_saldo');

            if (diferencia > 0) {
                lblSaldo.innerText = 'Faltan L. ' + diferencia.toFixed(2);
            } else if (diferencia < 0) {
                lblSaldo.innerText = 'Cambio: L. ' + Math.abs(diferencia).toFixed(2);
            } else {
                lblSaldo.innerText = 'L. 0.00 (Exacto)';
            }
        }

        // --- PROCESAR PAGO AL BACKEND Y ABRIR COMPROBANTE ---
        function procesarPagoRecaudo() {
            if (!clienteSeleccionadoActual || !contratoSeleccionadoActual) {
                alert('Seleccione un cliente y un contrato.');
                return;
            }

            const cuotasSeleccionadas = [];
            document.querySelectorAll('.chk-cuota:checked').forEach(chk => cuotasSeleccionadas.push(chk.value));

            if (cuotasSeleccionadas.length === 0) {
                alert('Seleccione al menos una cuota para pagar.');
                return;
            }

            if (listaPagosRecaudo.length === 0) {
                alert('Agregue al menos un método de pago.');
                return;
            }

            const totalCobrar = parseFloat(document.getElementById('lbl_total_cobrar').innerText.replace('L. ', ''));
            const totalAbonado = listaPagosRecaudo.reduce((sum, p) => sum + p.monto, 0);

            if (totalAbonado < totalCobrar) {
                alert('El monto abonado es menor al total de las cuotas seleccionadas.');
                return;
            }

            const tipoModo = document.getElementById('select_tipo_pago_modalidad').value;

            const payload = {
                codigo_bp: clienteSeleccionadoActual.bp,
                id_contrato: contratoSeleccionadoActual,
                cuotas: cuotasSeleccionadas,
                pagos: listaPagosRecaudo,
                es_cancelacion_total: (tipoModo === 'total')
            };

            fetch('/api/procesar_recaudo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // 1. Extraemos de forma segura el ID del recaudo devuelto por la API
                    const idRecaudoGenerado = data.recaudo_id || data.id || data.id_recaudo;

                    if (idRecaudoGenerado) {
                        // 2. Abrimos la vista de impresión en una ventana emergente compacta (estilo ticket)
                        window.open(`imprimir_recibo_recaudo.php?id=${idRecaudoGenerado}`, '_blank', 'width=320,height=600');
                    } else {
                        alert('✅ Recaudo procesado, pero no se detectó el ID para la impresión automática.');
                    }

                    // 3. Recargamos la página actual para limpiar los campos
                    location.reload();
                } else {
                    alert('❌ Error: ' + (data.message || 'No se pudo procesar el recaudo.'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error de conexión al procesar el recaudo.');
            });
        }
    </script>
</body>
</html>