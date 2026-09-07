<?php
// views/historial_recaudos.php
session_start();
require_once '../config/conexion.php';

// Habilitar errores para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Tegucigalpa');

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$mensaje = "";
$tipo_alerta = "";

// Lógica para anular / revertir un recaudo si se solicita
if (isset($_GET['accion']) && $_GET['accion'] == 'anular' && isset($_GET['id'])) {
    $id_cuota_recaudo = $_GET['id'];
    try {
        $pdo->beginTransaction();

        // Revertir el registro en cuotas_contrato (poner estado pendiente y limpiar pago)
        $stmtRevertir = $pdo->prepare("
            UPDATE cuotas_contrato 
            SET monto_pagado = 0, fecha_pago = NULL, estado = 'PENDIENTE', usuario_id = NULL 
            WHERE id = ?
        ");
        $stmtRevertir->execute([$id_cuota_recaudo]);

        $pdo->commit();
        $mensaje = "El recaudo #{$id_cuota_recaudo} ha sido anulado y la cuota regresó a estado PENDIENTE.";
        $tipo_alerta = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "Error al anular el recaudo: " . $e->getMessage();
        $tipo_alerta = "danger";
    }
}

try {
    // Obtener el historial de todos los pagos (cuotas con monto pagado o estado PAGADO)
    $stmtHistorial = $pdo->query("
        SELECT cc.*, co.id AS contrato_id, c.Nombre AS cliente_nombre, c.rtn_dni, c.codigo_bp, u.nombre AS cajero_nombre
        FROM cuotas_contrato cc
        LEFT JOIN contratos co ON cc.contrato_id = co.id
        LEFT JOIN clientes c ON co.codigo_bp = c.codigo_bp
        LEFT JOIN usuarios u ON cc.usuario_id = u.id
        WHERE cc.estado = 'PAGADO' OR cc.monto_pagado > 0
        ORDER BY cc.fecha_pago DESC
    ");
    $historial = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error en la base de datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Recaudos - Inversiones J.A</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fa-solid fa-clock-rotate-left text-primary"></i> Historial de Recaudos</h2>
                <p class="text-muted">Consulta, reimpresión y reversión de pagos aplicados.</p>
            </div>
            <div>
                <a href="recaudos.php" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Volver a Recaudos
                </a>
            </div>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_alerta; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Recibo ID</th>
                                <th>Contrato</th>
                                <th>Cliente</th>
                                <th>Cuota N°</th>
                                <th>Fecha Pago</th>
                                <th>Cajero</th>
                                <th class="text-end">Monto Pagado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($historial)): ?>
                                <?php foreach ($historial as $row): ?>
                                    <tr>
                                        <td><b>#<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></b></td>
                                        <td>#<?php echo htmlspecialchars($row['contrato_id']); ?></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($row['cliente_nombre'] ?? 'N/A'); ?></div>
                                            <small class="text-muted">BP: <?php echo htmlspecialchars($row['codigo_bp'] ?? ''); ?></small>
                                        </td>
                                        <td>N° <?php echo htmlspecialchars($row['numero_cuota']); ?></td>
                                        <td><?php echo date('d/m/Y h:i A', strtotime($row['fecha_pago'] ?? 'now')); ?></td>
                                        <td><?php echo htmlspecialchars($row['cajero_nombre'] ?? 'Sistema'); ?></td>
                                        <td class="text-end fw-bold text-success">L. <?php echo number_format((float)$row['monto_pagado'], 2); ?></td>
                                        <td class="text-center">
                                            <!-- Botón para reimprimir recibo -->
                                            <a href="imprimir_recibo_recaudo.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Imprimir Recibo">
                                                <i class="fa-solid fa-print"></i>
                                            </a>
                                            
                                            <!-- Botón para anular / devolver pago con confirmación -->
                                            <a href="javascript:void(0);" onclick="confirmarAnulacion(<?php echo $row['id']; ?>)" class="btn btn-sm btn-outline-danger" title="Anular / Revertir Pago">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No hay registros de recaudos realizados todavía.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function confirmarAnulacion(id) {
            if (confirm("⚠️ ADVERTENCIA: ¿Estás seguro de anular este recaudo (ID #" + id + ")?\n\nEsta acción regresará la cuota a estado PENDIENTE y dejará el monto pagado en 0.")) {
                window.location.href = "historial_recaudos.php?accion=anular&id=" + id;
            }
        }
    </script>
</body>
</html>