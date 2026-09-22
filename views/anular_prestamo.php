<?php
// views/anular_prestamo.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

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

if (!isset($_SESSION['usuario_rol']) || (strtolower($_SESSION['usuario_rol']) !== 'admin' && strtolower($_SESSION['usuario_rol']) !== 'administrador')) {
    die("Acceso denegado. Solo los administradores pueden ver este módulo.");
}

const DIAS_MAXIMOS_PARA_ANULAR = 7; // debe coincidir con api/procesar_anulacion_prestamo.php

$mensaje = isset($_GET['msg']) ? urldecode($_GET['msg']) : "";
$error = isset($_GET['err']) ? urldecode($_GET['err']) : "";
$contrato = null;
$cuotas = [];
$diasTranscurridos = null;
$puedeAnular = false;
$motivoBloqueo = '';

if (isset($_GET['contrato_id']) && !empty($_GET['contrato_id'])) {
    $idBuscado = intval($_GET['contrato_id']);

    $stmt = $pdo->prepare("
        SELECT co.*, cl.Nombre AS cliente_nombre, cl.rtn_dni AS cliente_dni
          FROM contratos co
          LEFT JOIN clientes cl ON co.codigo_bp = cl.codigo_bp
         WHERE co.id = ?
    ");
    $stmt->execute([$idBuscado]);
    $contrato = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($contrato) {
        $stmtCuotas = $pdo->prepare("SELECT * FROM cuotas_contrato WHERE contrato_id = ? ORDER BY numero_cuota ASC");
        $stmtCuotas->execute([$idBuscado]);
        $cuotas = $stmtCuotas->fetchAll(PDO::FETCH_ASSOC);

        $fechaInicio = new DateTime($contrato['fecha_inicio']);
        $hoy = new DateTime(date('Y-m-d'));
        $diasTranscurridos = (int)$fechaInicio->diff($hoy)->days;

        $tieneCuotasPagadas = false;
        foreach ($cuotas as $c) {
            if ($c['estado'] === 'PAGADO' || (float)$c['monto_pagado'] > 0) {
                $tieneCuotasPagadas = true;
                break;
            }
        }

        if ($contrato['estado'] !== 'ACTIVO') {
            $motivoBloqueo = "Este contrato no está activo (estado actual: {$contrato['estado']}).";
        } elseif ($diasTranscurridos > DIAS_MAXIMOS_PARA_ANULAR) {
            $motivoBloqueo = "Ya pasaron {$diasTranscurridos} días desde su creación (máximo permitido: " . DIAS_MAXIMOS_PARA_ANULAR . ").";
        } elseif ($tieneCuotasPagadas) {
            $motivoBloqueo = "Este contrato ya tiene cuotas pagadas; gestiónalo como una devolución.";
        } else {
            $puedeAnular = true;
        }
    } else {
        $error = "No se encontró ningún contrato con el ID #{$idBuscado}.";
    }
}

$stmtHistorial = $pdo->query("
    SELECT a.*, u.nombre AS administrador
      FROM anulaciones_prestamo a
      JOIN usuarios u ON a.usuario_id = u.id
     ORDER BY a.fecha DESC
     LIMIT 20
");
$historialAnulaciones = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);

$nombre_empresa = "INVERSIONES J.A";
try {
    $stmt_config = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1");
    if ($row_config = $stmt_config->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row_config['nombre_empresa'])) {
            $nombre_empresa = htmlspecialchars($row_config['nombre_empresa']);
        }
    }
} catch (Exception $e) { /* se mantiene el valor por defecto */ }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anular Préstamo — <?php echo $nombre_empresa; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-3.5 flex justify-between items-center sticky top-0 z-50">
        <div class="flex items-center gap-2">
            <div class="bg-rose-600 text-white p-2 rounded-xl shadow-sm">
                <i class="fa-solid fa-file-circle-xmark text-sm"></i>
            </div>
            <div>
                <span class="font-bold text-sm sm:text-base text-slate-900 block leading-none">Anular Préstamo / Contrato de Crédito</span>
                <span class="text-[11px] text-slate-400 font-medium"><?php echo $nombre_empresa; ?></span>
            </div>
        </div>
        <a href="prestamos.php" class="text-xs font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <i class="fa-solid fa-arrow-left"></i> Ir a Préstamos
        </a>
    </header>

    <main class="max-w-[1200px] w-full mx-auto px-4 py-8 flex-grow flex flex-col gap-6">
        <div>
            <h3 class="font-bold text-slate-900 text-xl">Anulación de Contratos de Crédito</h3>
            <p class="text-slate-500 text-sm">
                Solo se puede anular un contrato dentro de los primeros <?php echo DIAS_MAXIMOS_PARA_ANULAR; ?> días de su creación,
                y siempre que no tenga cuotas pagadas.
            </p>
        </div>

        <?php if ($mensaje): ?>
            <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-medium"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-sm font-medium"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <form method="GET" class="flex flex-col sm:flex-row gap-3 items-end">
                <div class="w-full">
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">ID del Contrato a Anular</label>
                    <input type="number" name="contrato_id" required value="<?php echo $_GET['contrato_id'] ?? ''; ?>" placeholder="Ej. 15" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <button type="submit" class="w-full sm:w-auto bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm px-6 py-2.5 rounded-xl transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-magnifying-glass"></i> Buscar Contrato
                </button>
            </form>
        </div>

        <?php if ($contrato): ?>
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 pb-4 border-b border-slate-100">
                <div>
                    <h4 class="font-bold text-slate-900 text-base">Contrato Encontrado: #<?php echo $contrato['id']; ?></h4>
                    <p class="text-xs text-slate-500">
                        Cliente: <?php echo htmlspecialchars($contrato['cliente_nombre'] ?? 'Cliente General'); ?>
                        (<?php echo htmlspecialchars($contrato['cliente_dni'] ?? ''); ?>)
                        | Creado: <?php echo htmlspecialchars($contrato['fecha_inicio']); ?>
                        (hace <?php echo $diasTranscurridos; ?> día<?php echo $diasTranscurridos == 1 ? '' : 's'; ?>)
                    </p>
                </div>
                <div class="text-right">
                    <span class="text-xs text-slate-400 block">Monto Financiado</span>
                    <span class="text-lg font-extrabold text-slate-900">L. <?php echo number_format($contrato['monto_financiar'], 2); ?></span>
                </div>
            </div>

            <h5 class="font-semibold text-slate-800 text-sm">Cuotas del Contrato</h5>
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-semibold">
                            <th class="p-3">Cuota</th>
                            <th class="p-3">Vencimiento</th>
                            <th class="p-3">Monto</th>
                            <th class="p-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php foreach ($cuotas as $c): ?>
                        <tr>
                            <td class="p-3">N° <?php echo htmlspecialchars($c['numero_cuota']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($c['fecha_vencimiento']); ?></td>
                            <td class="p-3">L. <?php echo number_format($c['monto_cuota'], 2); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($c['estado']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pt-4 flex justify-end">
                <?php if ($puedeAnular): ?>
                    <button onclick="abrirModalAnulacion()" class="bg-rose-600 hover:bg-rose-700 text-white font-semibold text-sm px-6 py-3 rounded-xl transition flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-file-circle-xmark"></i> Anular este Contrato
                    </button>
                <?php else: ?>
                    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-xs font-semibold flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-amber-600 text-sm"></i>
                        No se puede anular: <?php echo htmlspecialchars($motivoBloqueo); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col gap-4 mt-4">
            <div>
                <h4 class="font-bold text-slate-900 text-base">Historial de Anulaciones Recientes</h4>
                <p class="text-xs text-slate-500">Últimas anulaciones de contratos registradas en el sistema.</p>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-semibold">
                            <th class="p-3">ID Anul.</th>
                            <th class="p-3">Contrato Ref.</th>
                            <th class="p-3">Cliente</th>
                            <th class="p-3">Motivo / Detalle</th>
                            <th class="p-3">Monto Restaurado</th>
                            <th class="p-3">Autorizado por</th>
                            <th class="p-3">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php if (empty($historialAnulaciones)): ?>
                        <tr>
                            <td colspan="7" class="p-4 text-center text-slate-400">No hay anulaciones registradas todavía.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($historialAnulaciones as $a): ?>
                            <tr>
                                <td class="p-3 font-semibold">#<?php echo $a['id']; ?></td>
                                <td class="p-3 text-blue-600 font-semibold">#<?php echo $a['contrato_id']; ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($a['cliente_nombre']); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($a['motivo']); ?><?php echo $a['comentario'] ? ' - ' . htmlspecialchars($a['comentario']) : ''; ?></td>
                                <td class="p-3 font-extrabold text-rose-600">L. <?php echo number_format($a['monto_restaurado'], 2); ?></td>
                                <td class="p-3"><?php echo htmlspecialchars($a['administrador']); ?></td>
                                <td class="p-3 text-slate-500"><?php echo $a['fecha']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal de Credenciales y Motivo -->
    <div id="modalAnulacion" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs hidden items-center justify-center p-4 z-50">
        <div class="bg-white max-w-lg w-full rounded-2xl p-6 shadow-xl flex flex-col gap-4">
            <h3 class="font-bold text-slate-900 text-lg flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-rose-600"></i> Autorización y Motivo de Anulación
            </h3>
            <p class="text-xs text-slate-500">
                Esta acción cancelará el contrato de crédito y las cuotas pendientes, y devolverá
                L. <?php echo $contrato ? number_format($contrato['monto_financiar'], 2) : '0.00'; ?>
                al límite de crédito del cliente. No afecta ninguna factura ni el inventario.
            </p>

            <form action="../api/procesar_anulacion_prestamo.php" method="POST" class="flex flex-col gap-3">
                <input type="hidden" name="contrato_id" value="<?php echo $contrato['id'] ?? ''; ?>">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Motivo de la Anulación</label>
                    <select name="motivo" required class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">Seleccione un motivo...</option>
                        <option value="Error al Crear el Contrato">Error al Crear el Contrato</option>
                        <option value="Cambio de Opinión del Cliente">Cambio de Opinión del Cliente</option>
                        <option value="Datos Incorrectos">Datos Incorrectos (montos, plazo, cliente)</option>
                        <option value="Duplicado">Contrato Duplicado</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Comentario Explicativo</label>
                    <textarea name="comentario" rows="3" required placeholder="Escriba los detalles del porqué se anula este contrato..." class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Contraseña de Administrador</label>
                    <input type="password" name="password_admin" required placeholder="Ingrese su contraseña de admin" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex gap-3 pt-3 border-t border-slate-100">
                    <button type="button" onclick="cerrarModalAnulacion()" class="w-1/2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-sm py-2.5 rounded-xl transition">Cancelar</button>
                    <button type="submit" class="w-1/2 bg-rose-600 hover:bg-rose-700 text-white font-semibold text-sm py-2.5 rounded-xl transition">Confirmar Anulación</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalAnulacion() {
            document.getElementById('modalAnulacion').classList.remove('hidden');
            document.getElementById('modalAnulacion').classList.add('flex');
        }
        function cerrarModalAnulacion() {
            document.getElementById('modalAnulacion').classList.remove('flex');
            document.getElementById('modalAnulacion').classList.add('hidden');
        }
    </script>
</body>
</html>