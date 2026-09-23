<?php
// views/reportes_prestamos.php — Reporte de préstamos otorgados y ganancias
// generadas, comparando por semana o por mes. Solo lectura, solo admin.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$modulos_permitidos = $_SESSION['modulos_activos'] ?? [];
if (!in_array('prestamos', $modulos_permitidos)) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php';

$rolActual = strtolower($_SESSION['usuario_rol'] ?? 'vendedor');
if ($rolActual !== 'admin' && $rolActual !== 'administrador') {
    header('Location: ../index.php');
    exit;
}

$nombre_empresa = "INVERSIONES J.";
try {
    $stmt_config = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1");
    if ($row_config = $stmt_config->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row_config['nombre_empresa'])) {
            $nombre_empresa = htmlspecialchars($row_config['nombre_empresa']);
        }
    }
} catch (Exception $e) {
    // Se mantiene el valor por defecto
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Préstamos - <?php echo $nombre_empresa; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Header -->
    <header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-3.5 flex justify-between items-center sticky top-0 z-40 shadow-xs">
        <div class="flex items-center gap-2">
            <div class="bg-purple-600 text-white p-2 rounded-xl shadow-sm"><i class="fa-solid fa-chart-line text-sm"></i></div>
            <div>
                <span class="font-bold text-sm sm:text-base text-slate-900 block leading-none">Reporte de Préstamos</span>
                <span class="text-[11px] text-slate-400 font-medium"><?php echo $nombre_empresa; ?></span>
            </div>
        </div>
    </header>

    <main class="max-w-[1300px] w-full mx-auto px-4 sm:px-6 py-6 flex-grow flex flex-col">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 flex-grow flex flex-col">

            <!-- Filtros -->
            <div class="flex flex-wrap items-end gap-3 mb-6">
                <div>
                    <label class="block font-semibold text-xs text-slate-600 mb-1">Desde:</label>
                    <input type="date" id="filtroFechaInicio" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block font-semibold text-xs text-slate-600 mb-1">Hasta:</label>
                    <input type="date" id="filtroFechaFin" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                </div>
                <div>
                    <label class="block font-semibold text-xs text-slate-600 mb-1">Agrupar por:</label>
                    <select id="filtroAgrupar" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                        <option value="mes">Mes</option>
                        <option value="semana">Semana</option>
                    </select>
                </div>
                <button type="button" onclick="cargarReporte()" class="bg-purple-600 hover:bg-purple-700 text-white font-medium text-sm px-4 py-2.5 rounded-xl transition flex items-center gap-2">
                    <i class="fa-solid fa-magnifying-glass"></i> Ver Reporte
                </button>

                <div class="flex items-center gap-1.5 ml-auto">
                    <button type="button" onclick="aplicarPreset(3)" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold px-3 py-2 rounded-xl transition">Últ. 3 meses</button>
                    <button type="button" onclick="aplicarPreset(6)" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold px-3 py-2 rounded-xl transition">Últ. 6 meses</button>
                    <button type="button" onclick="aplicarPresetAnio()" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold px-3 py-2 rounded-xl transition">Este año</button>
                </div>
            </div>

            <!-- Tarjetas de resumen -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
                <div class="bg-purple-50 border border-purple-100 rounded-xl p-4">
                    <div class="text-[11px] font-semibold text-purple-500 uppercase">Préstamos Otorgados</div>
                    <div class="text-xl font-bold text-purple-700 mt-1" id="tot_cantidad">—</div>
                </div>
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                    <div class="text-[11px] font-semibold text-blue-500 uppercase">Total Financiado</div>
                    <div class="text-xl font-bold text-blue-700 mt-1" id="tot_financiado">—</div>
                </div>
                <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4">
                    <div class="text-[11px] font-semibold text-emerald-500 uppercase">Ganancia Generada</div>
                    <div class="text-xl font-bold text-emerald-700 mt-1" id="tot_ganancia">—</div>
                </div>
                <div class="bg-rose-50 border border-rose-100 rounded-xl p-4">
                    <div class="text-[11px] font-semibold text-rose-500 uppercase">Contratos Anulados</div>
                    <div class="text-xl font-bold text-rose-700 mt-1" id="tot_anulados">—</div>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 -mt-4 mb-6">La ganancia generada es el interés total de los contratos otorgados en cada período (total con interés − capital financiado). No incluye los contratos anulados dentro del plazo.</p>

            <!-- Gráfico -->
            <div class="border border-slate-200 rounded-xl p-4 mb-6" style="height: 380px;">
                <canvas id="graficoPrestamos"></canvas>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-semibold">
                        <tr>
                            <th class="py-3 px-4" id="thPeriodo">Mes</th>
                            <th class="py-3 px-4 text-center">Préstamos Otorgados</th>
                            <th class="py-3 px-4 text-right">Total Financiado</th>
                            <th class="py-3 px-4 text-right">Total con Interés</th>
                            <th class="py-3 px-4 text-right">Ganancia Generada</th>
                            <th class="py-3 px-4 text-center">Anulados</th>
                        </tr>
                    </thead>
                    <tbody id="tablaReporte" class="divide-y divide-slate-200 text-sm text-slate-700 bg-white">
                        <tr><td colspan="6" class="text-center py-8 text-slate-400">Selecciona un rango y presiona "Ver Reporte".</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        let grafico = null;

        document.addEventListener('DOMContentLoaded', () => {
            aplicarPresetAnio();
        });

        function aplicarPreset(meses) {
            const hoy = new Date();
            const desde = new Date(hoy.getFullYear(), hoy.getMonth() - (meses - 1), 1);
            document.getElementById('filtroFechaInicio').value = formatoFechaInput(desde);
            document.getElementById('filtroFechaFin').value = formatoFechaInput(hoy);
            cargarReporte();
        }

        function aplicarPresetAnio() {
            const hoy = new Date();
            document.getElementById('filtroFechaInicio').value = `${hoy.getFullYear()}-01-01`;
            document.getElementById('filtroFechaFin').value = formatoFechaInput(hoy);
            cargarReporte();
        }

        function formatoFechaInput(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        }

        function formatoMoneda(n) {
            return 'L. ' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
        }

        function etiquetaPeriodo(fechaISO, agruparPor) {
            const [y, m, d] = fechaISO.split('-').map(Number);
            const fecha = new Date(y, m - 1, d);
            if (agruparPor === 'semana') {
                const fin = new Date(fecha);
                fin.setDate(fin.getDate() + 6);
                const opts = { day: '2-digit', month: 'short' };
                return `${fecha.toLocaleDateString('es-HN', opts)} - ${fin.toLocaleDateString('es-HN', opts)}`;
            }
            return fecha.toLocaleDateString('es-HN', { month: 'long', year: 'numeric' });
        }

        function cargarReporte() {
            const fechaInicio = document.getElementById('filtroFechaInicio').value;
            const fechaFin = document.getElementById('filtroFechaFin').value;
            const agruparPor = document.getElementById('filtroAgrupar').value;

            document.getElementById('thPeriodo').innerText = agruparPor === 'semana' ? 'Semana' : 'Mes';

            const tbody = document.getElementById('tablaReporte');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando...</td></tr>';

            const params = new URLSearchParams({ accion: 'resumen', agrupar_por: agruparPor });
            if (fechaInicio) params.set('fecha_inicio', fechaInicio);
            if (fechaFin) params.set('fecha_fin', fechaFin);

            fetch(`../api/reportes_prestamos.php?${params.toString()}`)
                .then(res => res.json())
                .then(res => {
                    if (!res.success) {
                        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-rose-500">${res.message || 'No se pudo cargar el reporte.'}</td></tr>`;
                        return;
                    }

                    const periodos = res.periodos || [];
                    const totales = res.totales || {};

                    document.getElementById('tot_cantidad').innerText = totales.cantidad_prestamos ?? 0;
                    document.getElementById('tot_financiado').innerText = formatoMoneda(totales.total_financiado);
                    document.getElementById('tot_ganancia').innerText = formatoMoneda(totales.ganancia_generada);
                    document.getElementById('tot_anulados').innerText = totales.cantidad_anulados ?? 0;

                    if (periodos.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-slate-400">No hay préstamos otorgados en este rango de fechas.</td></tr>';
                        renderizarGrafico([], agruparPor);
                        return;
                    }

                    let html = '';
                    periodos.forEach(p => {
                        html += `
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="py-3 px-4 font-semibold capitalize">${etiquetaPeriodo(p.periodo_inicio, agruparPor)}</td>
                            <td class="py-3 px-4 text-center font-bold text-purple-700">${p.cantidad_prestamos}</td>
                            <td class="py-3 px-4 text-right">${formatoMoneda(p.total_financiado)}</td>
                            <td class="py-3 px-4 text-right">${formatoMoneda(p.total_con_interes)}</td>
                            <td class="py-3 px-4 text-right font-bold text-emerald-700">${formatoMoneda(p.ganancia_generada)}</td>
                            <td class="py-3 px-4 text-center ${p.cantidad_anulados > 0 ? 'text-rose-600 font-semibold' : 'text-slate-400'}">${p.cantidad_anulados}</td>
                        </tr>`;
                    });
                    tbody.innerHTML = html;

                    // Aislado en su propio try/catch: si el gráfico falla (por ejemplo, la
                    // librería no cargó), no debe borrar la tabla que ya se mostró bien.
                    try {
                        renderizarGrafico(periodos, agruparPor);
                    } catch (errGrafico) {
                        console.error('No se pudo dibujar el gráfico:', errGrafico);
                    }
                })
                .catch(() => {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-rose-500">Error de conexión con el servidor.</td></tr>';
                });
        }

        function renderizarGrafico(periodos, agruparPor) {
            const ctx = document.getElementById('graficoPrestamos').getContext('2d');
            const etiquetas = periodos.map(p => etiquetaPeriodo(p.periodo_inicio, agruparPor));
            const cantidades = periodos.map(p => p.cantidad_prestamos);
            const ganancias = periodos.map(p => p.ganancia_generada);

            if (grafico) {
                grafico.destroy();
            }

            grafico = new Chart(ctx, {
                data: {
                    labels: etiquetas,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Préstamos Otorgados',
                            data: cantidades,
                            backgroundColor: 'rgba(147, 51, 234, 0.65)',
                            borderRadius: 6,
                            yAxisID: 'yCantidad',
                        },
                        {
                            type: 'line',
                            label: 'Ganancia Generada (L.)',
                            data: ganancias,
                            borderColor: 'rgb(16, 185, 129)',
                            backgroundColor: 'rgba(16, 185, 129, 0.15)',
                            tension: 0.3,
                            fill: true,
                            yAxisID: 'yGanancia',
                            // Radio de punto grande a propósito: cuando el rango elegido
                            // solo tiene datos en un período (un mes o una semana), la
                            // "línea" queda con un solo punto y Chart.js no dibuja ningún
                            // segmento, así que sin esto el punto casi no se ve.
                            pointRadius: 6,
                            pointHoverRadius: 8,
                            pointBackgroundColor: 'rgb(16, 185, 129)',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        yCantidad: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            title: { display: true, text: 'Cantidad de préstamos' },
                            ticks: { precision: 0 }
                        },
                        yGanancia: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            title: { display: true, text: 'Ganancia (L.)' }
                        }
                    },
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.yAxisID === 'yGanancia') {
                                        return `${context.dataset.label}: ${formatoMoneda(context.parsed.y)}`;
                                    }
                                    return `${context.dataset.label}: ${context.parsed.y}`;
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>