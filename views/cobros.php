<?php
// views/cobros.php — Módulo de Cobros: pantalla de solo lectura para que el
// cobrador (o el administrador) vea qué clientes tienen cuotas en mora o que
// vencen hoy, priorice sus visitas, y de ahí vaya a Recaudo a cobrar de
// verdad. No hay ninguna acción que modifique datos en esta pantalla.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$modulos_permitidos = $_SESSION['modulos_activos'] ?? [];

if (!in_array('cobros', $modulos_permitidos)) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/conexion.php';

$rolActual = $_SESSION['usuario_rol'] ?? 'vendedor';
if (!in_array($rolActual, ['admin', 'administrador', 'cobrador'], true)) {
    header('Location: ../index.php');
    exit;
}
$esCobrador = ($rolActual === 'cobrador');

// --- OBTENER EL NOMBRE DE LA EMPRESA DESDE LA BD ---
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
    <title>Cobros - <?php echo $nombre_empresa; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Header -->
    <header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-3.5 flex justify-between items-center sticky top-0 z-40 shadow-xs">
        <div class="flex items-center gap-2">
            <div class="bg-amber-600 text-white p-2 rounded-xl shadow-sm"><i class="fa-solid fa-route text-sm"></i></div>
            <div>
                <span class="font-bold text-sm sm:text-base text-slate-900 block leading-none">Módulo de Cobros</span>
                <span class="text-[11px] text-slate-400 font-medium"><?php echo $nombre_empresa; ?></span>
            </div>
        </div>
    </header>

    <main class="max-w-[1200px] w-full mx-auto px-4 sm:px-6 py-6 flex-grow flex flex-col">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 flex-grow flex flex-col">

            <div class="mb-4">
                <p class="text-slate-500 text-xs sm:text-sm">
                    Clientes con cuotas en mora o que vencen hoy<?php echo $esCobrador ? ', asignados a ti (o sin asignar)' : ''; ?>.
                    Esta pantalla es solo para consulta: para cobrar la cuota ve al módulo de Recaudo con el botón "Cobrar".
                </p>
            </div>

            <!-- Filtros -->
            <div class="flex flex-wrap items-center gap-2 mb-5">
                <button type="button" id="btnFiltroTodos" onclick="cambiarFiltro('todos')" class="filtro-cobros px-3.5 py-2 rounded-xl text-xs font-semibold border transition">Todos</button>
                <button type="button" id="btnFiltroHoy" onclick="cambiarFiltro('hoy')" class="filtro-cobros px-3.5 py-2 rounded-xl text-xs font-semibold border transition">Vencen hoy</button>
                <button type="button" id="btnFiltroAtrasadas" onclick="cambiarFiltro('atrasadas')" class="filtro-cobros px-3.5 py-2 rounded-xl text-xs font-semibold border transition">Atrasadas</button>
                <button type="button" id="btnFiltroProximos" onclick="cambiarFiltro('proximos')" class="filtro-cobros px-3.5 py-2 rounded-xl text-xs font-semibold border transition">Próximos a vencer</button>
                <span class="ml-auto text-xs text-slate-400" id="lblTotalClientes"></span>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto rounded-xl border border-slate-200 flex-grow">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-semibold">
                        <tr>
                            <th class="py-3.5 px-4">Cliente</th>
                            <th class="py-3.5 px-4">Teléfono</th>
                            <th class="py-3.5 px-4">Dirección</th>
                            <th class="py-3.5 px-4" id="thDiasMora">Días de mora</th>
                            <th class="py-3.5 px-4 text-right">Cuota Exigible</th>
                            <th class="py-3.5 px-4 text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="tablaCobros" class="divide-y divide-slate-200 text-sm text-slate-700 bg-white">
                        <tr><td colspan="6" class="text-center py-8 text-slate-400">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        let filtroActual = 'todos';

        document.addEventListener('DOMContentLoaded', () => {
            cambiarFiltro('todos');
        });

        function cambiarFiltro(filtro) {
            filtroActual = filtro;
            ['Todos', 'Hoy', 'Atrasadas', 'Proximos'].forEach(sufijo => {
                const btn = document.getElementById('btnFiltro' + sufijo);
                const activo = sufijo.toLowerCase() === filtro || (sufijo === 'Todos' && filtro === 'todos');
                if (activo) {
                    btn.className = 'filtro-cobros px-3.5 py-2 rounded-xl text-xs font-semibold border transition bg-amber-600 text-white border-amber-600';
                } else {
                    btn.className = 'filtro-cobros px-3.5 py-2 rounded-xl text-xs font-semibold border transition bg-white text-slate-600 border-slate-200 hover:bg-slate-50';
                }
            });
            cargarCobros();
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.innerText = str ?? '';
            return div.innerHTML;
        }

        function cargarCobros() {
            const tbody = document.getElementById('tablaCobros');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando...</td></tr>';

            fetch(`../api/cobros.php?accion=listar&filtro=${encodeURIComponent(filtroActual)}`)
                .then(res => res.json())
                .then(res => {
                    if (!res.success) {
                        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-rose-500">${escapeHtml(res.message || 'No se pudo cargar la lista.')}</td></tr>`;
                        document.getElementById('lblTotalClientes').innerText = '';
                        return;
                    }

                    const data = res.data || [];
                    document.getElementById('lblTotalClientes').innerText = `${data.length} cliente(s)`;

                    const esProximos = filtroActual === 'proximos';
                    document.getElementById('thDiasMora').innerText = esProximos ? 'Vence en' : 'Días de mora';

                    if (data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-slate-400">${esProximos ? 'No hay cuotas próximas a vencer en los próximos días.' : 'No hay clientes con cuotas en mora o que venzan hoy para este filtro.'}</td></tr>`;
                        return;
                    }

                    // Prioridad: en mora/hoy, primero los más atrasados; en "próximos",
                    // primero la fecha más cercana (la que toca visitar antes).
                    if (esProximos) {
                        data.sort((a, b) => (a.dias_para_vencer_min ?? 999) - (b.dias_para_vencer_min ?? 999));
                    } else {
                        data.sort((a, b) => (b.dias_mora_max || 0) - (a.dias_mora_max || 0));
                    }

                    let html = '';
                    data.forEach(c => {
                        let badgeMora;
                        if (esProximos) {
                            const diasFaltan = parseInt(c.dias_para_vencer_min ?? 0);
                            badgeMora = `<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-sky-100 text-sky-700">En ${diasFaltan} día${diasFaltan === 1 ? '' : 's'}</span>`;
                        } else {
                            const diasMora = parseInt(c.dias_mora_max || 0);
                            badgeMora = diasMora > 0
                                ? `<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">${diasMora} días</span>`
                                : `<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">Vence hoy</span>`;
                        }

                        html += `
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="py-3 px-4 font-semibold">
                                ${escapeHtml(c.nombre)}
                                <div class="text-[11px] text-slate-400 font-normal">${c.codigo_bp} · ${c.cuotas_pendientes} cuota(s) pendiente(s)</div>
                            </td>
                            <td class="py-3 px-4">${escapeHtml(c.telefono) || '—'}</td>
                            <td class="py-3 px-4">${escapeHtml(c.direccion) || '—'}</td>
                            <td class="py-3 px-4">${badgeMora}</td>
                            <td class="py-3 px-4 text-right font-bold text-rose-700">L. ${Number(c.cuota_exigible_total || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                            <td class="py-3 px-4 text-center">
                                <button type="button" class="bg-emerald-50 text-emerald-700 text-xs px-3 py-1.5 rounded-lg font-medium cursor-pointer hover:bg-emerald-100 transition" onclick="irACobrar('${c.codigo_bp}')">
                                    <i class="fa-solid fa-hand-holding-dollar"></i> Cobrar
                                </button>
                            </td>
                        </tr>`;
                    });

                    tbody.innerHTML = html;
                })
                .catch(() => {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-rose-500">Error de conexión con el servidor.</td></tr>';
                });
        }

        // Lleva al módulo de Recaudo con este cliente ya seleccionado, para
        // cobrar la cuota ahí mismo (Cobros solo es para consultar y priorizar).
        function irACobrar(codigoBp) {
            window.location.href = `/views/recaudo.php?codigo_bp=${encodeURIComponent(codigoBp)}`;
        }
    </script>
</body>
</html>