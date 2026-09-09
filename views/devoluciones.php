<?php
// views/devoluciones.php
session_start();
require_once '../config/conexion.php';

// Validar que sea administrador
if (!isset($_SESSION['usuario_rol']) || (strtolower($_SESSION['usuario_rol']) !== 'admin' && strtolower($_SESSION['usuario_rol']) !== 'administrador')) {
    die("Acceso denegado. Solo los administradores pueden ver este módulo.");
}

$mensaje = isset($_GET['msg']) ? urldecode($_GET['msg']) : "";
$error = isset($_GET['err']) ? urldecode($_GET['err']) : "";
$ventaBuscada = null;
$detallesVenta = [];
$yaDevuelta = null;

// Búsqueda de transacción
if (isset($_GET['id_transaccion']) && !empty($_GET['id_transaccion'])) {
    $idBuscado = intval($_GET['id_transaccion']);
    
    // Buscar la venta
    $stmt = $pdo->prepare("SELECT v.*, u.nombre as cajero FROM ventas v JOIN usuarios u ON v.usuario_id = u.id WHERE v.id_transaccion = ?");
    $stmt->execute([$idBuscado]);
    $ventaBuscada = $stmt->fetch(PDO::FETCH_ASSOC);

   if ($ventaBuscada) {
        // Verificar si esta transacción ya fue devuelta anteriormente
        $stmtCheckDev = $pdo->prepare("SELECT id FROM devoluciones WHERE venta_id = ?");
        $stmtCheckDev->execute([$idBuscado]);
        $yaDevuelta = $stmtCheckDev->fetch(PDO::FETCH_ASSOC);

        // Buscar los productos/detalles haciendo un JOIN con productos para obtener el nombre
        $stmtDetalles = $pdo->prepare("
            SELECT dv.*, p.nombre as producto_nombre 
            FROM detalle_ventas dv 
            LEFT JOIN productos p ON dv.producto_id = p.id 
            WHERE dv.venta_id = ?
        ");
        $stmtDetalles->execute([$idBuscado]);
        $detallesVenta = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);
    }else {
        $error = "No se encontró ninguna transacción con el ID #{$idBuscado}.";
    }
}

// Obtener el historial de devoluciones realizadas
$stmtHistorial = $pdo->query("
    SELECT d.*, u.nombre as administrador 
    FROM devoluciones d 
    JOIN usuarios u ON d.usuario_id = u.id 
    ORDER BY d.fecha DESC
    LIMIT 20
");
$historialDevoluciones = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Módulo de Devoluciones — <?php echo $nombre_empresa; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Header -->
    <header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-3.5 flex justify-between items-center sticky top-0 z-50">
        <div class="flex items-center gap-2">
            <div class="bg-rose-600 text-white p-2 rounded-xl shadow-sm">
                <i class="fa-solid fa-rotate-left text-sm"></i>
            </div>
            <div>
                <span class="font-bold text-sm sm:text-base text-slate-900 block leading-none">Módulo de Devoluciones</span>
                <span class="text-[11px] text-slate-400 font-medium"><?php echo $nombre_empresa; ?></span>
            </div>
        </div>
        <a href="arqueo.php" class="text-xs font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <i class="fa-solid fa-arrow-left"></i> Ir a Arqueo de Caja
        </a>
    </header>

    <main class="max-w-[1200px] w-full mx-auto px-4 py-8 flex-grow flex flex-col gap-6">
        <div>
            <h3 class="font-bold text-slate-900 text-xl">Gestión de Devoluciones y Reembolsos</h3>
            <p class="text-slate-500 text-sm">Busca el ID de la transacción para procesar la devolución e reincorporar inventario.</p>
        </div>

        <?php if($mensaje): ?>
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-medium"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-sm font-medium"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Formulario de Búsqueda -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <form method="GET" class="flex flex-col sm:flex-row gap-3 items-end">
                <div class="w-full">
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">ID de Transacción a Devolver</label>
                    <input type="number" name="id_transaccion" required value="<?php echo $_GET['id_transaccion'] ?? ''; ?>" placeholder="Ej. 28" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <button type="submit" class="w-full sm:w-auto bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm px-6 py-2.5 rounded-xl transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-magnifying-glass"></i> Buscar Venta
                </button>
            </form>
        </div>

        <!-- Resultado de la Búsqueda -->
        <?php if($ventaBuscada): ?>
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row justify-between pb-4 border-b border-slate-100 gap-2">
                <div>
                    <h4 class="font-bold text-slate-900 text-base">Transacción Encontrada: #<?php echo $ventaBuscada['id_transaccion']; ?></h4>
                    <p class="text-xs text-slate-500">Cajero: <?php echo htmlspecialchars($ventaBuscada['cajero']); ?> | Fecha: <?php echo $ventaBuscada['fecha_venta']; ?></p>
                </div>
                <div class="text-right">
                    <span class="text-xs text-slate-400 block">Total Venta Original</span>
                    <span class="text-lg font-extrabold text-slate-900">L. <?php echo number_format($ventaBuscada['total'], 2); ?></span>
                </div>
            </div>

            <h5 class="font-semibold text-slate-800 text-sm">Productos de la Transacción</h5>
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-semibold">
                            <th class="p-3">Producto / Descripción</th>
                            <th class="p-3">Cantidad</th>
                            <th class="p-3">Precio Unitario</th>
                            <th class="p-3">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php foreach($detallesVenta as $det): ?>
                        <tr>
                            <td class="p-3"><?php echo htmlspecialchars($det['producto_nombre'] ?? 'Producto ID: '.$det['producto_id']); ?></td>
                            <td class="p-3"><?php echo $det['cantidad']; ?></td>
                            <td class="p-3">L. <?php echo number_format($det['precio_unitario'], 2); ?></td>
                            <td class="p-3 font-semibold">L. <?php echo number_format($det['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Botón para abrir Modal de Devolución o Aviso de que ya fue devuelta -->
            <div class="pt-4 flex justify-end">
                <?php if ($yaDevuelta): ?>
                    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-xs font-semibold flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-amber-600 text-sm"></i>
                        Esta transacción ya fue devuelta anteriormente (Devolución #<?php echo $yaDevuelta['id']; ?>).
                    </div>
                <?php else: ?>
                    <button onclick="abrirModalDevolucion()" class="bg-rose-600 hover:bg-rose-700 text-white font-semibold text-sm px-6 py-3 rounded-xl transition flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-rotate-left"></i> Procesar Devolución de esta Venta
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Historial de Devoluciones Realizadas -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col gap-4 mt-4">
            <div>
                <h4 class="font-bold text-slate-900 text-base">Historial de Devoluciones Recientes</h4>
                <p class="text-xs text-slate-500">Últimas devoluciones registradas en el sistema.</p>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-semibold">
                            <th class="p-3">ID Dev.</th>
                            <th class="p-3">Transacción Ref.</th>
                            <th class="p-3">Cliente</th>
                            <th class="p-3">Motivo / Detalle</th>
                            <th class="p-3">Total Reembolsado</th>
                            <th class="p-3">Autorizado por</th>
                            <th class="p-3">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php if (empty($historialDevoluciones)): ?>
                        <tr>
                            <td colspan="7" class="p-4 text-center text-slate-400">No hay devoluciones registradas todavía.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach($historialDevoluciones as $dev): ?>
                            <tr>
                                <td class="p-3 font-semibold">#<?php echo $dev['id']; ?></td>
                                <td class="p-3 text-blue-600 font-semibold">#<?php echo $dev['venta_id']; ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($dev['cliente']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($dev['motivo']); ?></td>
                                <td class="p-3 font-extrabold text-rose-600">L. <?php echo number_format($dev['total_reembolso'], 2); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($dev['administrador']); ?></td>
                                <td class="p-3 text-slate-500"><?php echo $dev['fecha']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal de Credenciales y Motivo -->
    <div id="modalDevolucion" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs hidden items-center justify-center p-4 z-50">
        <div class="bg-white max-w-lg w-full rounded-2xl p-6 shadow-xl flex flex-col gap-4">
            <h3 class="font-bold text-slate-900 text-lg flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-rose-600"></i> Autorización y Motivo de Devolución
            </h3>
            <p class="text-xs text-slate-500">Esta acción repondrá el inventario, restará el dinero del arqueo de caja actual y generará un registro negativo.</p>

            <form action="../api/procesar_devolucion.php" method="POST" class="flex flex-col gap-3">
                <input type="hidden" name="id_transaccion" value="<?php echo $ventaBuscada['id_transaccion'] ?? ''; ?>">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Motivo de la Devolución</label>
                    <select name="motivo" required class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">Seleccione un motivo...</option>
                        <option value="Producto Defectuoso">Producto Defectuoso</option>
                        <option value="Cambio de Opinión del Cliente">Cambio de Opinión del Cliente</option>
                        <option value="Error de Cobro en Caja">Error de Cobro en Caja</option>
                        <option value="Garantía Válida">Garantía Válida</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Comentario Explicativo</label>
                    <textarea name="comentario" rows="3" required placeholder="Escriba los detalles del porqué se realiza la devolución..." class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Contraseña de Administrador</label>
                    <input type="password" name="password_admin" required placeholder="Ingrese su contraseña de admin" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex gap-3 pt-3 border-t border-slate-100">
                    <button type="button" onclick="cerrarModalDevolucion()" class="w-1/2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-sm py-2.5 rounded-xl transition">Cancelar</button>
                    <button type="submit" class="w-1/2 bg-rose-600 hover:bg-rose-700 text-white font-semibold text-sm py-2.5 rounded-xl transition">Confirmar Devolución</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalDevolucion() {
            document.getElementById('modalDevolucion').classList.remove('hidden');
            document.getElementById('modalDevolucion').classList.add('flex');
        }
        function cerrarModalDevolucion() {
            document.getElementById('modalDevolucion').classList.remove('flex');
            document.getElementById('modalDevolucion').classList.add('hidden');
        }
    </script>
</body>
</html>