<?php
// views/transacciones.php
session_start();
require_once '../config/conexion.php';

// Validar sesión activa
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Validar rol para saber si es administrador o vendedor
$rolActual = strtolower($_SESSION['usuario_rol'] ?? 'vendedor');
$esAdmin = ($rolActual === 'admin' || $rolActual === 'administrador');

// Capturar parámetros de filtro enviados por GET
$busqueda = trim($_GET['buscar'] ?? '');
$fechaInicio = $_GET['fecha_inicio'] ?? '';
$fechaFin = $_GET['fecha_fin'] ?? '';

// Construir la consulta SQL dinámica con filtros
$sql = "SELECT v.*, u.nombre as cajero FROM ventas v JOIN usuarios u ON v.usuario_id = u.id WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $sql .= " AND (v.id_transaccion LIKE ? OR v.cliente_codigo_bp LIKE ? OR v.numero_factura LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if (!empty($fechaInicio)) {
    $sql .= " AND DATE(v.fecha_venta) >= ?";
    $params[] = $fechaInicio;
}

if (!empty($fechaFin)) {
    $sql .= " AND DATE(v.fecha_venta) <= ?";
    $params[] = $fechaFin;
}

$sql .= " ORDER BY v.fecha_venta DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ventas = $stmt;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Transacciones - INVERSIONES J.A</title>
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
        @media print {
            nav, .no-print, button, form { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .max-w-\[1300px\] { max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .bg-white { border: none !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col selection:bg-blue-500 selection:text-white p-4 sm:p-8">

    <div class="max-w-[1300px] w-full mx-auto flex-grow flex flex-col">
        <!-- Cabecera del Módulo -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-blue-600"></i> Historial de Transacciones y Órdenes
                </h2>
                <p class="text-slate-500 text-xs sm:text-sm mt-0.5">Supervisa ventas y órdenes pendientes de facturar.</p>
            </div>
            <div class="flex items-center gap-2 no-print">
                <?php if ($esAdmin): ?>
                <button onclick="window.print()" class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-medium text-xs sm:text-sm px-3.5 py-2 rounded-xl transition shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-print text-xs"></i> Imprimir Reporte
                </button>
                <?php endif; ?>
                <!-- Botón Actualizar que limpia los filtros -->
                <a href="transacciones.php" class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-medium text-xs sm:text-sm px-3.5 py-2 rounded-xl transition shadow-xs flex items-center gap-1.5 no-underline">
                    <i class="fa-solid fa-sync text-xs"></i> Actualizar
                </a>
            </div>
        </div>

        <!-- FILTROS AVANZADOS (BÚSQUEDA Y FECHAS) -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-6 no-print">
            <form method="GET" action="transacciones.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                <div class="lg:col-span-5">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Buscar (ID, Factura o Código BP):</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input type="text" name="buscar" id="txtBuscarTransaccion" value="<?php echo htmlspecialchars($busqueda); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="Nº transacción, factura o BP...">
                    </div>
                </div>
                <div class="lg:col-span-3">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Desde la fecha:</label>
                    <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($fechaInicio); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                <div class="lg:col-span-3">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Hasta la fecha:</label>
                    <input type="date" name="fecha_fin" value="<?php echo htmlspecialchars($fechaFin); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
                <div class="lg:col-span-1 flex gap-2">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm py-2 rounded-xl transition shadow-xs flex items-center justify-center">
                        <i class="fa-solid fa-filter"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- TABLA PRINCIPAL DE TRANSACCIONES -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex-grow">
            <div class="overflow-x-auto">
                <table id="tablaTransacciones" class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 text-[11px] uppercase tracking-wider font-semibold">
                            <th class="px-6 py-3.5">ID / Tipo</th>
                            <th class="px-6 py-3.5">Nº Factura / Estado</th>
                            <th class="px-6 py-3.5">Cliente (BP)</th>
                            <th class="px-6 py-3.5">Cajero</th>
                            <th class="px-6 py-3.5">Fecha y Hora</th>
                            <th class="px-6 py-3.5 text-end">Total</th>
                            <th class="px-6 py-3.5 text-center no-print">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php 
                        $hayResultados = false;
                        while($v = $ventas->fetch(PDO::FETCH_ASSOC)): 
                            $hayResultados = true;
                            $totalVenta = (float)$v['total'];
                            $esDevolucion = ($totalVenta < 0);
                            $esOrdenPendiente = !$esDevolucion && (empty($v['numero_factura']) || $v['tipo_comprobante'] === 'Orden Pendiente');
                        ?>
                        <tr class="hover:bg-slate-50/50 transition border-b border-slate-100 last:border-none">
                            <td class="px-6 py-4 font-bold text-slate-900">
                                #<?php echo htmlspecialchars($v['id_transaccion']); ?>
                                <?php if($esDevolucion): ?>
                                    <span class="block text-[10px] text-red-600 font-semibold uppercase">Devolución</span>
                                <?php elseif($esOrdenPendiente): ?>
                                    <span class="block text-[10px] text-amber-600 font-semibold uppercase">Orden Pendiente</span>
                                <?php else: ?>
                                    <span class="block text-[10px] text-blue-600 font-semibold uppercase">Factura</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if($esDevolucion): ?>
                                    <span class="bg-red-50 text-red-700 border border-red-200 text-xs font-semibold px-2.5 py-1 rounded-lg">
                                        N/A (Devolución)
                                    </span>
                                <?php elseif($esOrdenPendiente): ?>
                                    <span class="bg-amber-50 text-amber-700 border border-amber-200 text-xs font-semibold px-2.5 py-1 rounded-lg">
                                        Sin Facturar
                                    </span>
                                <?php else: ?>
                                    <span class="bg-blue-50 text-blue-700 border border-blue-200 text-xs font-mono font-semibold px-2.5 py-1 rounded-lg">
                                        <?php echo htmlspecialchars($v['numero_factura']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="bg-slate-100 text-slate-700 border border-slate-200 text-xs font-semibold px-2.5 py-1 rounded-lg">
                                    <?php echo htmlspecialchars($v['cliente_codigo_bp']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 font-medium text-slate-700 flex items-center gap-1.5 mt-2">
                                <i class="fa-solid fa-user text-slate-400 text-xs"></i> 
                                <?php echo htmlspecialchars($v['cajero']); ?>
                            </td>
                            <td class="px-6 py-4 text-slate-600 text-xs sm:text-sm">
                                <?php echo htmlspecialchars($v['fecha_venta']); ?>
                            </td>
                            <td class="px-6 py-4 text-end font-bold <?php echo $esDevolucion ? 'text-red-600' : 'text-emerald-600'; ?>">
                                L. <?php echo number_format($totalVenta, 2); ?>
                            </td>
                            <td class="px-6 py-4 text-center no-print">
                                <?php if($esDevolucion): ?>
                                    <a href="imprimir_factura.php?id_transaccion=<?php echo $v['id_transaccion']; ?>" target="_blank" class="inline-flex items-center gap-1.5 bg-red-50 hover:bg-red-100 text-red-700 font-medium text-xs px-3.5 py-2 rounded-xl transition shadow-xs">
                                        <i class="fa-solid fa-file-invoice text-xs"></i> Ver Devolución
                                    </a>
                                <?php elseif($esOrdenPendiente): ?>
                                    <a href="pos.php?id_transaccion=<?php echo $v['id_transaccion']; ?>" class="inline-flex items-center gap-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 font-medium text-xs px-3.5 py-2 rounded-xl transition shadow-xs">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i> Editar / Facturar
                                    </a>
                                <?php else: ?>
                                    <a href="imprimir_factura.php?id_transaccion=<?php echo $v['id_transaccion']; ?>" target="_blank" class="inline-flex items-center gap-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium text-xs px-3.5 py-2 rounded-xl transition shadow-xs">
                                        <i class="fa-solid fa-eye text-xs"></i> Ver Factura
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; 
                        if (!$hayResultados):
                        ?>
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-slate-400">
                                No se encontraron transacciones con los filtros seleccionados.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>