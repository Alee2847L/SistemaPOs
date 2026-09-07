<?php
// views/historial_recaudos.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$rolActual = strtolower($_SESSION['usuario_rol'] ?? 'vendedor');
$esAdmin = ($rolActual === 'admin' || $rolActual === 'administrador');

$mensaje = "";
$tipo_alerta = "";

// Lógica para anular / revertir un recaudo global (SOLO ADMIN)
if (isset($_GET['accion']) && $_GET['accion'] == 'anular' && isset($_GET['id'])) {
    if (!$esAdmin) {
        $mensaje = "Acceso denegado: Solo los administradores pueden anular recaudos.";
        $tipo_alerta = "danger";
    } else {
        $recaudo_id = $_GET['id'];
        try {
            $pdo->beginTransaction();

            // 1. Obtener el monto total del recaudo para descontarlo del límite de crédito del cliente
            $stmtGetRecaudo = $pdo->prepare("
                SELECT tr.*, co.codigo_bp 
                FROM transacciones_recaudo tr 
                JOIN contratos co ON tr.contrato_id = co.id 
                WHERE tr.id = ?
            ");
            $stmtGetRecaudo->execute([$recaudo_id]);
            $datosRecaudo = $stmtGetRecaudo->fetch(PDO::FETCH_ASSOC);

            if ($datosRecaudo) {
                $montoTotal = $datosRecaudo['monto_total'];
                $codigoBp = $datosRecaudo['codigo_bp'];

                // 2. Descontar del límite de crédito del cliente
                $stmtRestarLimite = $pdo->prepare("UPDATE clientes SET limite_credito = limite_credito - ? WHERE codigo_bp = ?");
                $stmtRestarLimite->execute([$montoTotal, $codigoBp]);

                // 3. Regresar todas las cuotas asociadas a este recaudo a estado PENDIENTE
                $stmtRevertirCuotas = $pdo->prepare("
                    UPDATE cuotas_contrato 
                    SET monto_pagado = 0, fecha_pago = NULL, estado = 'PENDIENTE', usuario_id = NULL, recaudo_id = NULL 
                    WHERE recaudo_id = ?
                ");
                $stmtRevertirCuotas->execute([$recaudo_id]);

                // 4. Eliminar o marcar como anulado el registro de la transacción de recaudo
                $stmtEliminarRecaudo = $pdo->prepare("DELETE FROM transacciones_recaudo WHERE id = ?");
                $stmtEliminarRecaudo->execute([$recaudo_id]);

                $pdo->commit();
                $mensaje = "El recibo #{$recaudo_id} ha sido anulado correctamente.";
                $tipo_alerta = "success";
            } else {
                throw new Exception("Recibo no encontrado.");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = "Error al anular el recaudo: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    }
}

// Filtros
$busqueda = trim($_GET['buscar'] ?? '');
$fechaInicio = $_GET['fecha_inicio'] ?? '';
$fechaFin = $_GET['fecha_fin'] ?? '';

// Consulta basada en la tabla maestra transacciones_recaudo
$sql = "
    SELECT tr.*, co.id AS contrato_id, c.Nombre AS cliente_nombre, c.codigo_bp, u.nombre AS cajero_nombre
    FROM transacciones_recaudo tr
    JOIN contratos co ON tr.contrato_id = co.id
    LEFT JOIN clientes c ON co.codigo_bp = c.codigo_bp
    LEFT JOIN usuarios u ON tr.usuario_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($busqueda)) {
    $sql .= " AND (tr.id LIKE ? OR c.Nombre LIKE ? OR c.codigo_bp LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

if (!empty($fechaInicio)) {
    $sql .= " AND DATE(tr.fecha) >= ?";
    $params[] = $fechaInicio;
}

if (!empty($fechaFin)) {
    $sql .= " AND DATE(tr.fecha) <= ?";
    $params[] = $fechaFin;
}

$sql .= " ORDER BY tr.fecha DESC";

try {
    $stmtHistorial = $pdo->prepare($sql);
    $stmtHistorial->execute($params);
} catch (PDOException $e) {
    die("Error en la base de datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Recaudos - INVERSIONES J.A</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col p-4 sm:p-8">

    <div class="max-w-[1300px] w-full mx-auto flex-grow flex flex-col">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-blue-600"></i> Historial de Recaudos
                </h2>
                <p class="text-slate-500 text-xs sm:text-sm mt-0.5">Control y reimpresión de recibos de pago aplicados.</p>
            </div>
            <div class="flex items-center gap-2 no-print">
                <?php if ($esAdmin): ?>
                <button onclick="window.print()" class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-medium text-xs sm:text-sm px-3.5 py-2 rounded-xl transition shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-print text-xs"></i> Imprimir Reporte
                </button>
                <?php endif; ?>
                <a href="historial_recaudos.php" class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-medium text-xs sm:text-sm px-3.5 py-2 rounded-xl transition shadow-xs flex items-center gap-1.5 no-underline">
                    <i class="fa-solid fa-sync text-xs"></i> Actualizar
                </a>
            </div>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="mb-4 p-4 rounded-xl text-sm <?php echo $tipo_alerta === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <!-- FILTROS -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-6 no-print">
            <form method="GET" action="historial_recaudos.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                <div class="lg:col-span-5">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Buscar (Nº Recibo o Cliente):</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input type="text" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-2 text-sm text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition" placeholder="Nº de recibo o nombre del cliente...">
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

        <!-- TABLA -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex-grow">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 text-[11px] uppercase tracking-wider font-semibold">
                            <th class="px-6 py-3.5">Nº Recibo</th>
                            <th class="px-6 py-3.5">Contrato</th>
                            <th class="px-6 py-3.5">Cliente</th>
                            <th class="px-6 py-3.5">Fecha y Hora</th>
                            <th class="px-6 py-3.5">Cajero</th>
                            <th class="px-6 py-3.5 text-end">Monto Total</th>
                            <th class="px-6 py-3.5 text-center no-print">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php 
                        $hayResultados = false;
                        while($row = $stmtHistorial->fetch(PDO::FETCH_ASSOC)): 
                            $hayResultados = true;
                        ?>
                        <tr class="hover:bg-slate-50/50 transition border-b border-slate-100 last:border-none">
                            <td class="px-6 py-4 font-bold text-slate-900">
                                #<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?>
                            </td>
                            <td class="px-6 py-4 font-medium text-slate-700">
                                #<?php echo htmlspecialchars($row['contrato_id']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-900"><?php echo htmlspecialchars($row['cliente_nombre'] ?? 'N/A'); ?></div>
                                <span class="inline-block bg-slate-100 text-slate-600 text-[11px] font-semibold px-2 py-0.5 rounded-md mt-0.5">BP: <?php echo htmlspecialchars($row['codigo_bp'] ?? ''); ?></span>
                            </td>
                            <td class="px-6 py-4 text-slate-600 text-xs sm:text-sm">
                                <?php echo date('d/m/Y h:i A', strtotime($row['fecha'])); ?>
                            </td>
                            <td class="px-6 py-4 text-slate-700 font-medium flex items-center gap-1.5 mt-3">
                                <i class="fa-solid fa-user text-slate-400 text-xs"></i> 
                                <?php echo htmlspecialchars($row['cajero_nombre'] ?? 'Sistema'); ?>
                            </td>
                            <td class="px-6 py-4 text-end font-bold text-emerald-600">
                                L. <?php echo number_format((float)$row['monto_total'], 2); ?>
                            </td>
                            <td class="px-6 py-4 text-center no-print">
                                <div class="flex items-center justify-center gap-1.5">
                                    <!-- Reimpresión (Disponible para Admin y Vendedor) -->
                                    <a href="imprimir_recibo_recaudo.php?id=<?php echo $row['id']; ?>" target="_blank" class="inline-flex items-center gap-1 bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium text-xs px-3 py-2 rounded-xl transition shadow-xs" title="Imprimir Recibo">
                                        <i class="fa-solid fa-print text-xs"></i> Recibo
                                    </a>
                                    
                                    <!-- Anulación (EXCLUSIVO ADMINISTRADOR) -->
                                    <?php if ($esAdmin): ?>
                                    <a href="javascript:void(0);" onclick="confirmarAnulacion(<?php echo $row['id']; ?>)" class="inline-flex items-center gap-1 bg-rose-50 hover:bg-rose-100 text-rose-700 font-medium text-xs px-3 py-2 rounded-xl transition shadow-xs" title="Anular / Revertir Pago">
                                        <i class="fa-solid fa-rotate-left text-xs"></i> Anular
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; 
                        if (!$hayResultados):
                        ?>
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-slate-400">
                                No se encontraron registros de recaudos con los filtros seleccionados.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function confirmarAnulacion(id) {
            if (confirm("⚠️ ADVERTENCIA: ¿Estás seguro de anular el recibo global #" + id + "?\n\nEsto regresará todas las cuotas pagadas de este recibo a estado PENDIENTE y ajustará el límite de crédito del cliente.")) {
                window.location.href = "historial_recaudos.php?accion=anular&id=" + id;
            }
        }
    </script>
</body>
</html>