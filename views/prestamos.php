<?php
// views/prestamos.php
// 1. INICIAR LA SESIÓN PRIMERO QUE TODO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. VALIDAR QUE EXISTA LA SESIÓN DEL USUARIO ANTES DE NADA
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// 3. VALIDAR QUE LA EMPRESA TENGA CONTRATADO EL MÓDULO
$modulos_permitidos = $_SESSION['modulos_activos'] ?? [];

if (!in_array('prestamos', $modulos_permitidos)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false, 
        'message' => 'Acceso denegado: El módulo de Préstamos no está incluido en el plan de su empresa.'
    ]);
    exit;
}

require_once __DIR__ . '/../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$rolActual = $_SESSION['usuario_rol'] ?? 'vendedor';
$es_admin = (isset($_SESSION['usuario_rol']) && (strtolower($_SESSION['usuario_rol']) === 'admin' || strtolower($_SESSION['usuario_rol']) === 'administrador'));

$nombre_empresa = "INVERSIONES J.";
try {
    $stmt_config = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1");
    if ($row_config = $stmt_config->fetch(PDO::FETCH_ASSOC)) {
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
            <button onclick="imprimirReportePrestamos()" 
                    class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-medium text-xs px-3.5 py-2 rounded-xl transition flex items-center gap-1.5">
                <i class="fa-solid fa-print"></i> Imprimir Reporte
            </button>
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
            
            <!-- BARRA DE BÚSQUEDA + FILTROS DE FECHA -->
            <div class="flex flex-col lg:flex-row justify-between items-stretch lg:items-end gap-4 mb-6">
                
                <!-- Buscador de texto -->
                <div class="relative flex-1 max-w-md">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </span>
                    <input type="text" id="inputBuscarPrestamo" 
                           placeholder="Buscar por cliente, DNI o descripción..." 
                           onkeyup="filtrarPrestamos()" 
                           class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-600/20 focus:border-purple-600 transition">
                </div>

                <!-- Filtros de fecha -->
                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Desde:</label>
                        <input type="date" id="filtroFechaInicio" 
                               class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 transition">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Hasta:</label>
                        <input type="date" id="filtroFechaFin" 
                               class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 transition">
                    </div>
                    <button onclick="cargarPrestamos()" 
                            class="bg-purple-600 hover:bg-purple-700 text-white font-medium text-sm px-4 py-2 rounded-xl transition flex items-center gap-1.5 h-[42px]">
                        <i class="fa-solid fa-filter text-xs"></i> Filtrar
                    </button>
                    <button onclick="limpiarFiltros()" 
                            class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-sm px-3 py-2 rounded-xl transition h-[42px]">
                        Limpiar
                    </button>
                </div>

                <!-- Botón Nuevo Préstamo -->
                <button onclick="abrirModalNuevoPrestamo()" 
                        class="bg-purple-600 hover:bg-purple-700 text-white font-medium text-sm px-4 py-2.5 rounded-xl transition flex items-center justify-center gap-2 shadow-xs">
                    <i class="fa-solid fa-plus-circle"></i> Nuevo Préstamo / Crédito
                </button>
            </div>

            <!-- TABLA -->
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
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-purple-600 text-white flex-shrink-0">
                <h4 class="font-bold text-base flex items-center gap-2">
                    <i class="fa-solid fa-calculator"></i> Crear Nuevo Préstamo / Crédito
                </h4>
                <button type="button" onclick="cerrarModalNuevoPrestamo()" class="text-white/80 hover:text-white p-1 text-lg"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form id="formNuevoPrestamo" class="flex flex-col flex-1 min-h-0 text-sm">
            <div class="p-6 space-y-4 overflow-y-auto flex-1 min-h-0">
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
                    <div id="banner_mora_prestamo" class="hidden mt-2 p-3 bg-rose-50 border border-rose-300 rounded-xl text-xs text-rose-700 font-semibold flex items-start gap-2">
                        <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
                        <span id="banner_mora_prestamo_texto"></span>
                    </div>
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
                        <input type="number" min="1" id="prestamo_plazo" value="12" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition" oninput="recalcularSimulacion()">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-xs text-slate-700 mb-1">Fecha del Primer Pago:</label>
                    <input type="date" id="prestamo_fecha_primer_pago" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 transition" oninput="validarFechaPrimerPago()">
                    <p class="text-[11px] text-slate-400 mt-1">Por defecto, 15 días después de hoy. Puedes elegir otra fecha, hasta un máximo de 40 días.</p>
                </div>

                <div id="aviso_prima_pendiente" class="hidden bg-violet-50 border border-violet-200 p-3 rounded-xl text-xs text-violet-700">
                    <i class="fa-solid fa-circle-info"></i> Esta prima quedará <b>pendiente de cobro</b>: se cobra después en el POS, buscando a este cliente, como una venta normal (efectivo/tarjeta).
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
            </div>

            <div class="flex justify-end gap-2 px-6 py-4 border-t border-slate-100 bg-white flex-shrink-0">
                <button type="button" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition" onclick="cerrarModalNuevoPrestamo()">Cancelar</button>
                <button type="submit" id="btn_submit_prestamo" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl transition shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-check"></i> Guardar y Generar Cuotas
                </button>
            </div>
            </form>
        </div>
    </div>

    <!-- MODAL PLAN DE PAGOS / CUOTAS -->
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4 hidden" id="modalPlanPagos">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-purple-600 text-white">
                <h4 class="font-bold text-base flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice"></i> Plan de Pagos del Cliente
                </h4>
                <div class="flex items-center gap-2">
                    <button id="btn_imprimir_plan_pagos" onclick="imprimirPlanPagos()" class="bg-white/20 hover:bg-white/30 text-white text-xs px-3 py-1.5 rounded-lg font-medium transition flex items-center gap-1">
                        <i class="fa-solid fa-print"></i> Imprimir
                    </button>
                    <button type="button" onclick="cerrarModalPlanPagos()" class="text-white/80 hover:text-white p-1 text-lg"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            
            <div class="p-6 overflow-y-auto space-y-4 text-sm flex-grow" id="areaImpresionPlan">
                <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-slate-500 block">Cliente:</span>
                        <b id="plan_cli_nombre" class="text-slate-900 text-sm">-</b>
                        <span id="plan_cli_bp" class="text-slate-500 block">BP000</span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Concepto / Producto:</span>
                        <b id="plan_concepto" class="text-slate-900 text-sm">-</b>
                        <span class="text-slate-500 block">Contrato #<span id="plan_contrato_id">0</span></span>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Resumen Financiero:</span>
                        <span class="text-slate-700">Monto: <b id="plan_monto_fin">L. 0.00</b></span><br>
                        <span class="text-slate-700">Total con Interés: <b id="plan_total_cred">L. 0.00</b></span><br>
                        <span id="plan_prima_linea" class="text-slate-700 hidden">
                            Prima: <b id="plan_prima_monto">L. 0.00</b>
                            <span id="plan_prima_badge" class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold"></span>
                        </span>
                    </div>
                </div>

                <div id="aviso_prima_pendiente_plan" class="hidden bg-amber-50 border border-amber-200 text-amber-800 text-xs p-4 rounded-xl flex items-start gap-2">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
                    <span>Este contrato tiene una <b>prima pendiente de cobro</b>. El plan de pagos y las cuotas se muestran una vez que la prima se cobre en el POS (buscando a este cliente).</span>
                </div>

                <div id="contenedor_tabla_plan_cuotas" class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-semibold">
                            <tr>
                                <th class="py-3 px-3 text-center"># Cuota</th>
                                <th class="py-3 px-3">Fecha de Vencimiento</th>
                                <th class="py-3 px-3 text-right">Capital</th>
                                <th class="py-3 px-3 text-right">Lo que Paga (Cuota)</th>
                                <th class="py-3 px-3 text-right">Saldo Restante</th>
                                <th class="py-3 px-3 text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="tablaPlanCuotas" class="divide-y divide-slate-200 text-xs text-slate-700 bg-white">
                            <tr><td colspan="6" class="text-center py-6 text-slate-400">Cargando plan de pagos...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 flex justify-end">
                <button type="button" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium rounded-xl transition text-xs" onclick="cerrarModalPlanPagos()">Cerrar</button>
            </div>
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
            const fechaInicio = document.getElementById('filtroFechaInicio')?.value || '';
            const fechaFin = document.getElementById('filtroFechaFin')?.value || '';

            let url = '../api/prestamos.php?accion=listar';
            if (fechaInicio) url += `&fecha_inicio=${fechaInicio}`;
            if (fechaFin) url += `&fecha_fin=${fechaFin}`;

            fetch(url)
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        listaPrestamosOriginal = res.data;
                        renderizarTablaPrestamos(res.data);
                    } else {
                        alert('Error al cargar préstamos: ' + (res.message || 'Error desconocido'));
                    }
                })
                .catch(err => {
                    console.error("Error al cargar préstamos:", err);
                    alert('Error de conexión al cargar los préstamos');
                });
        }

        function limpiarFiltros() {
            document.getElementById('filtroFechaInicio').value = '';
            document.getElementById('filtroFechaFin').value = '';
            document.getElementById('inputBuscarPrestamo').value = '';
            cargarPrestamos();
        }

        function imprimirReportePrestamos() {
    const fechaInicio = document.getElementById('filtroFechaInicio')?.value || '';
    const fechaFin = document.getElementById('filtroFechaFin')?.value || '';
    const busqueda = document.getElementById('inputBuscarPrestamo')?.value || '';

    // Datos que se están mostrando actualmente
    const datos = listaPrestamosOriginal.length > 0 ? listaPrestamosOriginal : [];

    if (datos.length === 0) {
        alert('No hay préstamos para imprimir con los filtros actuales.');
        return;
    }

    let filas = '';
    datos.forEach(p => {
        filas += `
            <tr>
                <td style="padding:8px;border-bottom:1px solid #e2e8f0;">#${p.id}</td>
                <td style="padding:8px;border-bottom:1px solid #e2e8f0;">
                    <b>${p.codigo_bp}</b><br>
                    <span style="font-size:11px;color:#64748b;">${p.cliente_nombre || 'Cliente'}</span>
                </td>
                <td style="padding:8px;border-bottom:1px solid #e2e8f0;font-size:12px;">${p.producto_descripcion || '-'}</td>
                <td style="padding:8px;border-bottom:1px solid #e2e8f0;text-align:right;">L. ${Number(p.monto_financiar).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                <td style="padding:8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:bold;color:#7c3aed;">L. ${Number(p.total_credito).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                <td style="padding:8px;border-bottom:1px solid #e2e8f0;text-align:center;">${p.plazo_meses} cuotas</td>
                <td style="padding:8px;border-bottom:1px solid #e2e8f0;text-align:center;">${p.estado}</td>
            </tr>
        `;
    });

    const rangoFechas = (fechaInicio || fechaFin) 
        ? `Período: ${fechaInicio || 'Inicio'} → ${fechaFin || 'Hoy'}` 
        : 'Todos los registros';

    const ventana = window.open('', '', 'width=900,height=700');
    ventana.document.write(`
        <html>
        <head>
            <title>Reporte de Préstamos</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 13px; color: #1e293b; padding: 25px; }
                h1 { color: #6d28d9; margin-bottom: 5px; }
                .subtitulo { color: #64748b; font-size: 13px; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th { background: #f1f5f9; text-align: left; padding: 10px 8px; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #cbd5e1; }
                td { font-size: 12px; }
                .footer { margin-top: 30px; font-size: 11px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 12px; }
                @media print {
                    body { padding: 10px; }
                }
            </style>
        </head>
        <body>
            <h1>Reporte de Préstamos y Créditos</h1>
            <div class="subtitulo">
                ${rangoFechas}<br>
                Generado el: ${new Date().toLocaleString('es-HN')}
                ${busqueda ? `<br>Filtro de búsqueda: "${busqueda}"` : ''}
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Descripción</th>
                        <th style="text-align:right;">Monto Financiar</th>
                        <th style="text-align:right;">Total con Interés</th>
                        <th style="text-align:center;">Plazo</th>
                        <th style="text-align:center;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    ${filas}
                </tbody>
            </table>

            <div class="footer">
                Total de registros: ${datos.length} — Sistema POS
            </div>
        </body>
        </html>
    `);
    ventana.document.close();

    setTimeout(() => {
        ventana.print();
    }, 400);
}

        function renderizarTablaPrestamos(prestamos) {
            const tbody = document.getElementById('tablaPrestamos');
            if (prestamos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-6 text-slate-400">No hay préstamos o contratos registrados</td></tr>';
                return;
            }

            let html = '';
            prestamos.forEach(p => {
                let estadoBadge;
                if (p.estado === 'ACTIVO') {
                    estadoBadge = '<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">ACTIVO</span>';
                } else if (p.estado === 'CANCELADO') {
                    estadoBadge = '<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">CANCELADO</span>';
                } else {
                    estadoBadge = '<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">FINALIZADO</span>';
                }

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
                            <button type="button" class="bg-purple-50 hover:bg-purple-100 text-purple-700 text-xs px-3 py-1.5 rounded-lg font-medium transition inline-flex items-center gap-1" onclick="abrirPlanPagos(${p.id})">
                                <i class="fa-solid fa-list-check"></i> Plan de Pagos
                            </button>
                            ${p.estado === 'ACTIVO' ? `
                            <a href="anular_prestamo.php?contrato_id=${p.id}" class="bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs px-3 py-1.5 rounded-lg font-medium transition inline-flex items-center gap-1 ml-1">
                                <i class="fa-solid fa-file-circle-xmark"></i> Anular
                            </a>` : ''}
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

            // Verificar si el cliente tiene cuotas vencidas (mora) antes de permitir
            // continuar con el préstamo. Solo se considera mora si la cuota venció
            // hoy o antes; si la próxima cuota es de mañana en adelante, está al día.
            const bannerMora = document.getElementById('banner_mora_prestamo');
            const bannerMoraTexto = document.getElementById('banner_mora_prestamo_texto');
            const btnSubmit = document.getElementById('btn_submit_prestamo');
            bannerMora.classList.add('hidden');
            btnSubmit.disabled = false;
            btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');

            fetch(`../api/prestamos.php?accion=verificar_mora&codigo_bp=${encodeURIComponent(codigo_bp)}`)
                .then(res => res.json())
                .then(resMora => {
                    if (resMora.success && resMora.en_mora) {
                        bannerMoraTexto.innerText = `Este cliente tiene ${resMora.cantidad_cuotas_vencidas} cuota(s) en mora (vencida(s) sin pagar). Debe ponerse al día antes de otorgarle un nuevo préstamo.`;
                        bannerMora.classList.remove('hidden');
                        btnSubmit.disabled = true;
                        btnSubmit.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                })
                .catch(() => { /* si falla la verificación, el backend igual bloqueará al guardar */ });
        }

        function actualizarOpcionesPlazo() {
            const frecuencia = document.getElementById('prestamo_frecuencia').value;
            let label = frecuencia === 'mensual' ? 'Meses' : (frecuencia === 'quincenal' ? 'Quincenas' : 'Semanas');
            document.getElementById('lbl_plazo_titulo').innerText = `Número de Cuotas (${label}):`;
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

            // Mostrar/ocultar el aviso de "esto queda pendiente de cobro en POS".
            document.getElementById('aviso_prima_pendiente').classList.toggle('hidden', prima <= 0);
        }

        // Formatea una fecha en 'YYYY-MM-DD' usando la fecha LOCAL del navegador
        // (evita el corrimiento de un día que da toISOString() al convertir a UTC).
        function formatearFechaISO(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        }

        function fechaPorDefectoPrimerPago() {
            const f = new Date();
            f.setDate(f.getDate() + 15);
            return formatearFechaISO(f);
        }

        // La fecha del primer pago no puede elegirse a más de 40 días desde hoy.
        function fechaMaximaPrimerPago() {
            const f = new Date();
            f.setDate(f.getDate() + 40);
            return formatearFechaISO(f);
        }

        function validarFechaPrimerPago() {
            const input = document.getElementById('prestamo_fecha_primer_pago');
            const maximo = fechaMaximaPrimerPago();
            if (input.value && input.value > maximo) {
                alert(`⚠️ La fecha del primer pago no puede ser mayor a 40 días desde hoy (máximo: ${maximo}).`);
                input.value = maximo;
            }
        }

        function abrirModalNuevoPrestamo() {
            document.getElementById('formNuevoPrestamo').reset();
            document.getElementById('prestamo_fecha_primer_pago').value = fechaPorDefectoPrimerPago();
            document.getElementById('prestamo_fecha_primer_pago').max = fechaMaximaPrimerPago();
            document.getElementById('prestamo_codigo_bp').value = '';
            document.getElementById('lbl_prestamo_cli_nombre').innerText = 'Ninguno seleccionado';
            document.getElementById('lbl_prestamo_cli_bp').innerText = 'BP000';
            document.getElementById('lbl_prestamo_cli_limite').innerText = 'L. 0.00';
            document.getElementById('banner_mora_prestamo').classList.add('hidden');
            const btnSubmit = document.getElementById('btn_submit_prestamo');
            btnSubmit.disabled = false;
            btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');

            document.getElementById('aviso_prima_pendiente').classList.add('hidden');

            actualizarOpcionesPlazo();
            document.getElementById('modalNuevoPrestamo').classList.remove('hidden');
        }

        function cerrarModalNuevoPrestamo() {
            document.getElementById('modalNuevoPrestamo').classList.add('hidden');
        }

        function abrirPlanPagos(contratoId) {
            document.getElementById('modalPlanPagos').classList.remove('hidden');
            document.getElementById('tablaPlanCuotas').innerHTML = '<tr><td colspan="6" class="text-center py-6 text-slate-400">Cargando cuotas...</td></tr>';

            fetch(`../api/prestamos.php?accion=ver_cuotas&contrato_id=${contratoId}`)
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        const c = res.contrato;
                        const cuotas = res.cuotas;

                        document.getElementById('plan_cli_nombre').innerText = c.cliente_nombre || 'Cliente General';
                        window.planClienteDni = c.cliente_dni || '';
                        document.getElementById('plan_cli_bp').innerText = c.codigo_bp;
                        document.getElementById('plan_concepto').innerText = c.producto_descripcion;
                        document.getElementById('plan_contrato_id').innerText = c.id;
                        document.getElementById('plan_monto_fin').innerText = 'L. ' + Number(c.monto_financiar).toLocaleString('en-US', {minimumFractionDigits: 2});
                        document.getElementById('plan_total_cred').innerText = 'L. ' + Number(c.total_credito).toLocaleString('en-US', {minimumFractionDigits: 2});

                        // --- Estado de la prima: si el contrato tiene prima y todavía no
                        // se ha cobrado en POS (contratos.prima_venta_id sigue en NULL),
                        // no se muestra el plan de pagos hasta que se cobre. Un contrato ya
                        // ANULADO/CANCELADO nunca cuenta como "pendiente" aunque su prima
                        // nunca se haya cobrado: ya no hay nada que cobrar ni que financiar.
                        const prima = parseFloat(c.prima) || 0;
                        const contratoActivo = c.estado === 'ACTIVO';
                        // prima_venta_id: null/0 = no cobrada, un id real (> 0) = cobrada.
                        const primaCobrada = !!c.prima_venta_id && parseInt(c.prima_venta_id) > 0;
                        const primaPendiente = contratoActivo && prima > 0 && !primaCobrada;

                        const lineaPrima = document.getElementById('plan_prima_linea');
                        const badgePrima = document.getElementById('plan_prima_badge');
                        const avisoPendiente = document.getElementById('aviso_prima_pendiente_plan');
                        const contenedorTabla = document.getElementById('contenedor_tabla_plan_cuotas');
                        const btnImprimir = document.getElementById('btn_imprimir_plan_pagos');

                        if (prima > 0) {
                            lineaPrima.classList.remove('hidden');
                            document.getElementById('plan_prima_monto').innerText = 'L. ' + prima.toLocaleString('en-US', {minimumFractionDigits: 2});
                            if (primaCobrada) {
                                badgePrima.innerText = 'COBRADA';
                                badgePrima.className = 'ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700';
                            } else if (!contratoActivo) {
                                badgePrima.innerText = 'N/A (CONTRATO ANULADO)';
                                badgePrima.className = 'ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600';
                            } else {
                                badgePrima.innerText = 'PENDIENTE';
                                badgePrima.className = 'ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700';
                            }
                        } else {
                            lineaPrima.classList.add('hidden');
                        }

                        if (primaPendiente) {
                            avisoPendiente.classList.remove('hidden');
                            contenedorTabla.classList.add('hidden');
                            btnImprimir.disabled = true;
                            btnImprimir.classList.add('opacity-50', 'cursor-not-allowed');
                            document.getElementById('tablaPlanCuotas').innerHTML = '';
                            return;
                        }

                        avisoPendiente.classList.add('hidden');
                        contenedorTabla.classList.remove('hidden');
                        btnImprimir.disabled = false;
                        btnImprimir.classList.remove('opacity-50', 'cursor-not-allowed');

                        let html = '';
                        let saldoRestante = parseFloat(c.total_credito);
                        let totalCuotas = cuotas.length;
                        let capitalPorCuota = totalCuotas > 0 ? (parseFloat(c.monto_financiar) / totalCuotas) : 0;

                        cuotas.forEach((cuota) => {
                            let montoCuota = parseFloat(cuota.monto_cuota);
                            saldoRestante -= montoCuota;
                            if (saldoRestante < 0) saldoRestante = 0;

                            const estadoBadge = cuota.estado === 'PAGADO'
                                ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">PAGADO</span>'
                                : '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">PENDIENTE</span>';

                            html += `
                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="py-2.5 px-3 text-center font-bold text-slate-900">${cuota.numero_cuota}</td>
                                    <td class="py-2.5 px-3 text-slate-600">${cuota.fecha_vencimiento}</td>
                                    <td class="py-2.5 px-3 text-right font-medium text-slate-700">L. ${capitalPorCuota.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                    <td class="py-2.5 px-3 text-right font-bold text-purple-600">L. ${montoCuota.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                    <td class="py-2.5 px-3 text-right font-semibold text-slate-800">L. ${saldoRestante.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                    <td class="py-2.5 px-3 text-center">${estadoBadge}</td>
                                </tr>
                            `;
                        });
                        document.getElementById('tablaPlanCuotas').innerHTML = html;
                    } else {
                        alert('Error al cargar el plan de pagos: ' + res.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error de conexión al cargar las cuotas.');
                });
        }

        function cerrarModalPlanPagos() {
            document.getElementById('modalPlanPagos').classList.add('hidden');
        }

        function imprimirPlanPagos() {
            const contenido = document.getElementById('areaImpresionPlan').innerHTML;
            const nombreEmpresa = <?php echo json_encode($nombre_empresa, JSON_HEX_TAG); ?>; // ya viene escapado para HTML
            const nombreCliente = escapeHtml(document.getElementById('plan_cli_nombre').innerText || '');
            const dniCliente    = escapeHtml(window.planClienteDni || '');

            const firmas = `
                <div style="margin-top:70px; page-break-inside:avoid; break-inside:avoid; font-family:Arial, sans-serif;">
                    <div style="display:flex; justify-content:space-between; gap:50px;">
                        <div style="flex:1; text-align:center; font-size:12px;">
                            <div style="border-top:1px solid #000; padding-top:6px;">
                                <strong>Cliente (Deudor)</strong><br>
                                ${nombreCliente}<br>
                                ${dniCliente ? 'RTN/DNI: ' + dniCliente : ''}
                            </div>
                            <div style="margin-top:16px;">Fecha: ____ / ____ / ________</div>
                        </div>
                        <div style="flex:1; text-align:center; font-size:12px;">
                            <div style="border-top:1px solid #000; padding-top:6px;">
                                <strong>Por la empresa (Acreedor)</strong><br>
                                ${nombreEmpresa}<br>
                                Nombre: ______________________
                            </div>
                            <div style="margin-top:16px;">Fecha: ____ / ____ / ________</div>
                        </div>
                    </div>
                </div>`;

            const ventana = window.open('', '', 'height=700,width=800');
            ventana.document.write('<html><head><title>Plan de Pagos</title>');
            ventana.document.write('<script src="https://cdn.tailwindcss.com"><\/script>');
            ventana.document.write('</head><body class="p-8 bg-white">');
            ventana.document.write('<h2 class="text-xl font-bold mb-4 text-purple-700">Plan de Pagos y Cuotas - ' + nombreEmpresa + '</h2>');
            ventana.document.write(contenido);
            ventana.document.write(firmas);
            ventana.document.write('</body></html>');
            ventana.document.close();
            setTimeout(() => {
                ventana.print();
            }, 500);
        }

        document.getElementById('formNuevoPrestamo').addEventListener('submit', async function(e) {
            e.preventDefault();
            const codigoBp = document.getElementById('prestamo_codigo_bp').value;
            if (!codigoBp || codigoBp === '') {
                alert('Debe seleccionar un cliente válido de la base de datos.');
                return;
            }

            const fechaPrimerPagoElegida = document.getElementById('prestamo_fecha_primer_pago').value || fechaPorDefectoPrimerPago();
            if (fechaPrimerPagoElegida > fechaMaximaPrimerPago()) {
                alert(`⚠️ La fecha del primer pago no puede ser mayor a 40 días desde hoy (máximo: ${fechaMaximaPrimerPago()}).`);
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
                frecuencia: datosCalculadosPrestamo.frecuencia,
                fecha_primer_pago: document.getElementById('prestamo_fecha_primer_pago').value || fechaPorDefectoPrimerPago()
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
                (p.codigo_bp && p.codigo_bp.toLowerCase().includes(texto)) || 
                (p.producto_descripcion && p.producto_descripcion.toLowerCase().includes(texto))
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