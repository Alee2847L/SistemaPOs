<?php
// views/prestamos.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rolActual =$_SESSION['usuario_rol'] ?? 'vendedor';
$es_admin = (isset($_SESSION['usuario_rol']) && (strtolower($_SESSION['usuario_rol']) === 'admin' \vert{}\vert{} strtolower($_SESSION['usuario_rol']) === 'administrador'));

$nombre_empresa = "INVERSIONES J.";
try {
    $stmt_config =$pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1");
    if ($row_config =$stmt_config->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row_config['nombre_empresa'])) {
            $nombre_empresa = htmlspecialchars($row_config['nombre_empresa']);
        }
    }
} catch (Exception $e) { }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Préstamos y Créditos — <?php echo $nombre_empresa; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-3.5 flex justify-between items-center sticky top-0 z-40 shadow-xs">
        <div class="flex items-center gap-2">
            <div class="bg-purple-600 text-white p-2 rounded-xl shadow-sm"><i class="fa-solid fa-file-invoice-dollar text-sm"></i></div>
            <div>
                <span class="font-bold text-sm sm:text-base text-slate-900 block leading-none">Módulo de Préstamos y Créditos</span>
                <span class="text-[11px] text-slate-400 font-medium"><?php echo $nombre_empresa; ?></span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="clientes.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-xs px-3.5 py-2 rounded-xl transition flex items-center gap-1.5">
                <i class="fa-solid fa-users"></i> Ir a Clientes
            </a>
            <a href="pos.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-xs px-3.5 py-2 rounded-xl transition flex items-center gap-1.5">
                <i class="fa-solid fa-cash-register"></i> Ir al POS
            </a>
        </div>
    </header>

    <main class="max-w-[1400px] w-full mx-auto px-4 sm:px-6 py-6 flex-grow flex flex-col">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 flex-grow flex flex-col">
            <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-4 mb-6">
                <div class="relative flex-1 max-w-md">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400"><i class="fa-solid fa-magnifying-glass text-xs"></i></span>
                    <input type="text" id="inputBuscarPrestamo" placeholder="Buscar por cliente, DNI o descripción..." onkeyup="filtrarPrestamos()" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-600/20 focus:border-purple-600 transition">
                </div>
                <button onclick="abrirModalNuevoPrestamo()" class="bg-purple-600 hover:bg-purple-700 text-white font-medium text-sm px-4 py-2.5 rounded-xl transition flex items-center justify-center gap-2 shadow-xs">
                    <i class="fa-solid fa-plus-circle"></i> Nuevo Préstamo / Crédito
                </button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 flex-grow">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-semibold">
                        <tr>
                            <th class="py-3.5 px-4">ID</th>
                            <th class="py-3.5 px-4">Cliente (BP / Nombre)</th>
                            <th class="py-3.5 px-4">Descripción / Concepto</th>
                            <th class="py-3.5 px-4">Monto Financiar</th>
                            <th class="py-3.5 px-4">Total con Interés</th>
                            <th class="py-3.5 px-4">Plazo / Cuotas</th>
                            <th class="py-3.5 px-4">Estado</th>
                            <th class="py-3.5 px-4 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaPrestamos" class="divide-y divide-slate-200 text-sm text-slate-700 bg-white">
                        <tr><td colspan="8" class="text-center py-6 text-slate-400">Cargando préstamos...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- MODAL NUEVO PRÉSTAMO -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden" id="modalNuevoPrestamo">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-2xl overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-purple-600 text-white">
                <h4 class="font-bold text-base flex items-center gap-2">
                    <i class="fa-solid fa-calculator"></i> Crear Nuevo Préstamo / Crédito
                </h4>
                <button type="button" onclick="cerrarModalNuevoPrestamo()" class="text-white/80 hover:text-white p-1 text-lg"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <form id="formNuevoPrestamo" class="p-6 space-y-4 text-sm">
                <div class="relative search-container">
                    <label class="block font-semibold text-xs text-slate-700 mb-1">
                        <i class="fa-solid fa-user text-purple-600 mr-1"></i> Seleccionar Cliente:
                    </label>
                    <div class="flex gap-2">
                        <div class="flex-grow relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input type="text" id="input_buscar_cliente_prestamo" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-2.5 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition" placeholder="Buscar por BP, DNI o Nombre..." onkeyup="buscarClientePrestamo(this.value)" onfocus="buscarClientePrestamo(this.value)" autocomplete="off">
                            <div id="sugerencias_cliente_prestamo" class="hidden absolute top-full left-0 w-full bg-white border border-slate-200 max-h-48 overflow-y-auto z-50 shadow-lg rounded-xl mt-1"></div>
                        </div>
                    </div>
                    <div class="mt-2 p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs flex justify-between items-center">
                        <span class="text-slate-600">Cliente: <b id="lbl_prestamo_cli_nombre" class="text-slate-900">Ninguno seleccionado</b> (<span id="lbl_prestamo_cli_bp">BP000</span>)</span>
                        <span class="text-slate-600">Límite Disp.: <b id="lbl_prestamo_cli_limite" class="text-purple-600">L. 0.00</b></span>
                    </div>
                    <input type="hidden" id="prestamo_codigo_bp" value="">
                    <input type="hidden" id="prestamo_limite_disp" value="0">
                </div>

                <div>
                    <label class="block font-semibold text-xs text-slate-700 mb-1">Concepto o Descripción:</label>
                    <input type="text" id="prestamo_descripcion" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition" placeholder="Ej: Préstamo en efectivo / Financiamiento" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-semibold text-xs text-slate-700 mb-1">Monto Total (L.):</label>
                        <input type="number" step="0.01" min="0.01" id="prestamo_total_factura" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition" placeholder="0.00" oninput="recalcularSimulacion()" required>
                    </div>
                    <div>
                        <label class="block font-semibold text-xs text-slate-700 mb-1">Monto de Prima (L.):</label>
                        <input type="number" step="0.01" min="0" id="prestamo_prima" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition" placeholder="0.00" value="0.00" oninput="recalcularSimulacion()">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block font-semibold text-xs text-slate-700 mb-1">Tasa Interés Anual (%):</label>
                        <input type="number" step="0.5" min="0" id="prestamo_tasa" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition" value="25" oninput="recalcularSimulacion()">
                    </div>
                    <div>
                        <label class="block font-semibold text-xs text-slate-700 mb-1">Frecuencia de Pago:</label>
                        <select id="prestamo_frecuencia" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition" onchange="actualizarOpcionesPlazo()">
                            <option value="mensual">Mensual</option>
                            <option value="quincenal">Quincenal</option>
                            <option value="semanal">Semanal</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold text-xs text-slate-700 mb-1" id="lbl_plazo_titulo">Número de Cuotas:</label>
                        <select id="prestamo_plazo" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition" onchange="recalcularSimulacion()">
                        </select>
                    </div>
                </div>

                <div class="bg-purple-50 border border-purple-200 p-4 rounded-xl space-y-2">
                    <div class="flex justify-between text-slate-700 text-xs sm:text-sm">
                        <span>Capital Financiar:</span>
                        <span id="sim_capital" class="font-semibold">L. 0.00</span>
                    </div>
                    <div class="flex justify-between text-slate-700 text-xs sm:text-sm">
                        <span>Interés Total:</span>
                        <span id="sim_interes" class="font-semibold">L. 0.00</span>
                    </div>
                    <div class="flex justify-between text-slate-900 font-bold text-base border-t border-purple-200 pt-2">
                        <span id="sim_lbl_cuota_titulo">Cuota Estimada:</span>
                        <span id="sim_cuota" class="text-purple-600 text-lg">L. 0.00</span>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition" onclick="cerrarModalNuevoPrestamo()">Cancelar</button>
                    <button type="submit" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl transition shadow-xs flex items-center gap-1.5">
                        <i class="fa-solid fa-check"></i> Guardar y Generar Cuotas
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let listaPrestamosOriginal = [];
        let timeoutClientePrestamo = null;
        let datosCalculadosPrestamo = null;

        document.addEventListener('DOMContentLoaded', () => {
            actualizarOpcionesPlazo();
            cargarPrestamos();
        });

        function cargarPrestamos() {
            fetch('../api/prestamos.php?accion=listar')
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        listaPrestamosOriginal = res.data;
                        renderizarTablaPrestamos(res.data);
                    }
                })
                .catch(err => console.error("Error al cargar préstamos:", err));
        }

        function renderizarTablaPrestamos(prestamos) {
            const tbody = document.getElementById('tablaPrestamos');
            if (prestamos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-6 text-slate-400">No hay préstamos o contratos registrados</td></tr>';
                return;
            }

            let html = '';
            prestamos.forEach(p => {
                const estadoBadge = p.estado === 'ACTIVO' 
                    ? '<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">ACTIVO</span>'
                    : '<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">FINALIZADO</span>';

                html += `
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
                        <td class="py-3 px-4 font-bold text-slate-900">#${p.id}</td>
                        <td class="py-3 px-4">
                            <b class="text-slate-900">${p.codigo_bp}</b><br>
                            <span class="text-xs text-slate-500">${escapeHtml(p.cliente_nombre || 'Cliente')}</span>
                        </td>
                        <td class="py-3 px-4 text-xs text-slate-600">${escapeHtml(p.producto_descripcion)}</td>
                        <td class="py-3 px-4 font-semibold text-slate-800">L. ${Number(p.monto_financiar).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td class="py-3 px-4 font-bold text-purple-600">L. ${Number(p.total_credito).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td class="py-3 px-4 text-xs">${p.plazo_meses} cuotas</td>
                        <td class="py-3 px-4">${estadoBadge}</td>
                        <td class="py-3 px-4 text-center">
                            <button type="button" class="bg-purple-50 hover:bg-purple-100 text-purple-700 text-xs px-3 py-1.5 rounded-lg font-medium transition inline-flex items-center gap-1" onclick="alert('Funcionalidad de cuotas')">
                                <i class="fa-solid fa-list-check"></i> Ver Cuotas
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function buscarClientePrestamo(query) {
            clearTimeout(timeoutClientePrestamo);
            timeoutClientePrestamo = setTimeout(() => {
                fetch(`../api/pos_clientes.php?accion=buscar&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(res => {
                    const div = document.getElementById('sugerencias_cliente_prestamo');
                    if (res.success && res.data.length > 0) {
                        let html = '';
                        res.data.forEach(c => {
                            const nombreEscaped = escapeHtml(c.Nombre);
                            const limiteVal = parseFloat(c.limite_credito || 0);
                            html += `
                                <div class="p-3 border-b border-slate-100 hover:bg-slate-50 cursor-pointer flex justify-between items-center text-xs" onclick="seleccionarClientePrestamo('${c.codigo_bp}', '${nombreEscaped}', ${limiteVal})">
                                    <div><b>${c.codigo_bp}</b> - ${nombreEscaped}</div>
                                    <span class="text-purple-600 font-bold">Límite: L. ${limiteVal.toFixed(2)}</span>
                                </div>
                            `;
                        });
                        div.innerHTML = html;
                        div.classList.remove('hidden');
                    } else {
                        div.innerHTML = '<div class="p-3 text-slate-400 text-center text-xs">No se encontró el cliente</div>';
                        div.classList.remove('hidden');
                    }
                });
            }, 200);
        }

        function seleccionarClientePrestamo(codigo_bp, nombre, limite) {
            document.getElementById('prestamo_codigo_bp').value = codigo_bp;
            document.getElementById('prestamo_limite_disp').value = limite;
            document.getElementById('lbl_prestamo_cli_nombre').innerText = nombre;
            document.getElementById('lbl_prestamo_cli_bp').innerText = codigo_bp;
            document.getElementById('lbl_prestamo_cli_limite').innerText = 'L. ' + limite.toFixed(2);
            document.getElementById('input_buscar_cliente_prestamo').value = '';
            document.getElementById('sugerencias_cliente_prestamo').classList.add('hidden');
        }

        function actualizarOpcionesPlazo() {
            const frecuencia = document.getElementById('prestamo_frecuencia').value;
            const selectPlazo = document.getElementById('prestamo_plazo');
            
            let max = frecuencia === 'mensual' ? 24 : (frecuencia === 'quincenal' ? 48 : 52);
            let label = frecuencia === 'mensual' ? 'Meses' : (frecuencia === 'quincenal' ? 'Quincenas' : 'Semanas');
            document.getElementById('lbl_plazo_titulo').innerText = `Número de Cuotas (${label}):`;

            let html = '';
            for (let i = 1; i <= max; i++) {
                html += `<option value="${i}" ${i === (frecuencia === 'mensual' ? 12 : 24) ? 'selected' : ''}>${i} ${label}</option>`;
            }
            selectPlazo.innerHTML = html;
            recalcularSimulacion();
        }

        function recalcularSimulacion() {
            const totalFactura = parseFloat(document.getElementById('prestamo_total_factura').value) || 0;
            const prima = parseFloat(document.getElementById('prestamo_prima').value) || 0;
            const tasaAnual = (parseFloat(document.getElementById('prestamo_tasa').value) || 25) / 100;
            const frecuencia = document.getElementById('prestamo_frecuencia').value;
            const numeroCuotas = parseInt(document.getElementById('prestamo_plazo').value) || 1;

            if (prima >= totalFactura && totalFactura > 0) {
                alert('La prima no puede ser mayor o igual al total.');
                document.getElementById('prestamo_prima').value = 0;
                return;
            }

            const capitalFinanciable = totalFactura - prima;
            let periodosAnio = frecuencia === 'mensual' ? 12 : (frecuencia === 'quincenal' ? 24 : 52);
            let tasaPeriodo = tasaAnual / periodosAnio;
            let interesTotal = capitalFinanciable * tasaPeriodo * numeroCuotas;
            let totalConInteres = capitalFinanciable + interesTotal;
            let valorCuota = numeroCuotas > 0 ? (totalConInteres / numeroCuotas) : totalConInteres;

            document.getElementById('sim_capital').innerText = 'L. ' + capitalFinanciable.toFixed(2);
            document.getElementById('sim_interes').innerText = 'L. ' + interesTotal.toFixed(2);
            document.getElementById('sim_cuota').innerText = 'L. ' + valorCuota.toFixed(2);
            document.getElementById('sim_lbl_cuota_titulo').innerText = `Cuota por ${frecuencia.charAt(0).toUpperCase() + frecuencia.slice(1)}:`;

            datosCalculadosPrestamo = {
                capitalFinanciable,
                interesTotal,
                totalConInteres,
                valorCuota,
                numeroCuotas,
                frecuencia,
                tasaAnual: (tasaAnual * 100),
                totalFactura,
                prima
            };
        }

        function abrirModalNuevoPrestamo() {
            document.getElementById('formNuevoPrestamo').reset();
            document.getElementById('prestamo_codigo_bp').value = '';
            document.getElementById('lbl_prestamo_cli_nombre').innerText = 'Ninguno seleccionado';
            document.getElementById('lbl_prestamo_cli_bp').innerText = 'BP000';
            document.getElementById('lbl_prestamo_cli_limite').innerText = 'L. 0.00';
            actualizarOpcionesPlazo();
            document.getElementById('modalNuevoPrestamo').classList.remove('hidden');
        }

        function cerrarModalNuevoPrestamo() {
            document.getElementById('modalNuevoPrestamo').classList.add('hidden');
        }

        document.getElementById('formNuevoPrestamo').addEventListener('submit', async function(e) {
            e.preventDefault();
            const codigoBp = document.getElementById('prestamo_codigo_bp').value;
            if (!codigoBp || codigoBp === '') {
                alert('Debe seleccionar un cliente válido de la base de datos.');
                return;
            }

            const limiteDisp = parseFloat(document.getElementById('prestamo_limite_disp').value) || 0;
            const capitalFinanciable = datosCalculadosPrestamo.capitalFinanciable;

            if (limiteDisp <= 0) {
                alert('❌ Este cliente no tiene límite de crédito configurado.');
                return;
            }

            if (capitalFinanciable > limiteDisp) {
                alert(`⚠️ LÍMITE EXCEDIDO:\nCapital a financiar: L. ${capitalFinanciable.toFixed(2)}\nLímite disponible: L. ${limiteDisp.toFixed(2)}`);
                return;
            }

            const payload = {
                codigo_bp: codigoBp,
                producto_descripcion: document.getElementById('prestamo_descripcion').value.trim(),
                total_factura: datosCalculadosPrestamo.totalFactura,
                prima: datosCalculadosPrestamo.prima,
                monto_financiar: capitalFinanciable,
                porcentaje_interes: datosCalculadosPrestamo.tasaAnual,
                total_credito: datosCalculadosPrestamo.totalConInteres,
                numero_cuotas: datosCalculadosPrestamo.numeroCuotas,
                plazo_meses: datosCalculadosPrestamo.numeroCuotas,
                frecuencia: datosCalculadosPrestamo.frecuencia
            };

            try {
                const res = await fetch('../api/prestamos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(r => r.json());

                if (res.success) {
                    alert('✅ Préstamo y cuotas generados exitosamente.');
                    cerrarModalNuevoPrestamo();
                    cargarPrestamos();
                } else {
                    alert('❌ Error: ' + res.message);
                }
            } catch (err) {
                console.error(err);
                alert('Error de conexión con el servidor.');
            }
        });

        function filtrarPrestamos() {
            let texto = document.getElementById('inputBuscarPrestamo').value.toLowerCase();
            let filtrados = listaPrestamosOriginal.filter(p => 
                (p.cliente_nombre && p.cliente_nombre.toLowerCase().includes(texto)) || 
                p.codigo_bp.toLowerCase().includes(texto) || 
                p.producto_descripcion.toLowerCase().includes(texto)
            );
            renderizarTablaPrestamos(filtrados);
        }

        function escapeHtml(text) {
            return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                const sug = document.getElementById('sugerencias_cliente_prestamo');
                if (sug) sug.classList.add('hidden');
            }
        });
    </script>
</body>
</html>