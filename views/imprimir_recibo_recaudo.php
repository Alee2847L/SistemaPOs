<?php
// views/imprimir_recibo_recaudo.php
session_start();
require_once '../config/conexion.php';

// Habilitar reporte de errores para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ajuste de zona horaria
date_default_timezone_set('America/Tegucigalpa');

// Validar si la sesión está activa
if (!isset($_SESSION['usuario_id'])) {
    die("Error: Sesión no iniciada.");
}

$recaudo_id = $_GET['id'] ?? null;

if (!$recaudo_id) {
    die("Error: No se proporcionó el ID del recaudo.");
}

try {
    // 1. Obtener la cabecera del pago desde la tabla maestra transacciones_recaudo
    $stmtRecaudo = $pdo->prepare("
        SELECT tr.*, co.id AS id_contrato, c.Nombre AS cliente_nombre, c.rtn_dni, c.codigo_bp, u.nombre AS cajero_nombre
        FROM transacciones_recaudo tr
        JOIN contratos co ON tr.contrato_id = co.id
        LEFT JOIN clientes c ON co.codigo_bp = c.codigo_bp
        LEFT JOIN usuarios u ON tr.usuario_id = u.id
        WHERE tr.id = ?
    ");
    $stmtRecaudo->execute([$recaudo_id]);
    $recaudo = $stmtRecaudo->fetch(PDO::FETCH_ASSOC);

    if (!$recaudo) {
        die("Error: El registro de recaudo con el ID #" . htmlspecialchars($recaudo_id) . " no existe.");
    }

    // 2. Obtener TODAS las cuotas individuales asociadas a este recibo global (recaudo_id)
    $stmtCuotas = $pdo->prepare("
        SELECT numero_cuota, monto_pagado AS monto, fecha_vencimiento 
        FROM cuotas_contrato 
        WHERE recaudo_id = ?
        ORDER BY numero_cuota ASC
    ");
    $stmtCuotas->execute([$recaudo_id]);
    $cuotasPagadas = $stmtCuotas->fetchAll(PDO::FETCH_ASSOC);

    // 3. Obtener los detalles de los métodos de pago (Efectivo / Tarjeta) si existe la tabla
    $detallesPagos = [];
    $stmtCheckTabla = $pdo->query("SHOW TABLES LIKE 'recaudo_pagos_detalle'");
    if ($stmtCheckTabla->rowCount() > 0) {
        $stmtPagos = $pdo->prepare("SELECT * FROM recaudo_pagos_detalle WHERE id_recaudo = ?");
        $stmtPagos->execute([$recaudo_id]);
        $detallesPagos = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Error en la base de datos: " . $e->getMessage());
}

$totalAbonadoRecaudo = (float)($recaudo['monto_total'] ?? 0);
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
    <title>Comprobante Recaudo #<?php echo str_pad($recaudo['id'], 6, '0', STR_PAD_LEFT); ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Courier New', Courier, monospace;
        }
        body {
            width: 280px;
            margin: 0 auto;
            padding: 10px;
            color: #000;
            background: #fff;
            font-size: 12px;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .text-start { text-align: left; }
        .fw-bold { font-weight: bold; }
        
        .header { margin-bottom: 10px; }
        .header h3 { font-size: 16px; text-transform: uppercase; margin-bottom: 3px; }
        .header p { font-size: 11px; line-height: 1.2; }
        
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }

        .info-cliente { font-size: 11px; line-height: 1.4; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            font-size: 11px;
        }
        th {
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
            text-transform: uppercase;
        }
        td {
            padding: 3px 0;
            vertical-align: top;
        }

        .totales-tabla {
            width: 100%;
            margin-top: 5px;
            font-size: 11px;
        }
        .totales-tabla td {
            padding: 2px 0;
        }

        .footer {
            margin-top: 15px;
            font-size: 11px;
        }

        @media print {
            .no-print { display: none !important; }
            body { width: 100%; padding: 0; }
        }
    </style>
</head>
<body onload="window.print();">

    <div class="no-print text-center" style="margin-bottom: 15px;">
        <button onclick="window.print()" style="padding: 8px 15px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
            🖨️ Imprimir Recibo
        </button>
        <button onclick="window.close()" style="padding: 8px 15px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 5px;">
            ❌ Cerrar
        </button>
    </div>

    <div class="header text-center">
        <h3><?php echo $nombre_empresa; ?></h3>
        <p>Módulo de Recaudo y Cuotas</p>
        <p>Honduras</p>
        <div class="divider"></div>
        <p class="fw-bold">COMPROBANTE DE RECAUDO</p>
        
        <!-- Número de Recibo Global -->
        <p><b>Recibo N°:</b> #<?php echo str_pad($recaudo['id'], 6, '0', STR_PAD_LEFT); ?></p>
        
        <!-- Número de Contrato -->
        <p><b>Contrato N°:</b> #<?php echo htmlspecialchars($recaudo['id_contrato'] ?? 'N/A'); ?></p>
        
        <p><b>Fecha:</b> <?php echo date('d/m/Y h:i A', strtotime($recaudo['fecha'] ?? 'now')); ?></p>
        <?php if (!empty($recaudo['cajero_nombre'])): ?>
            <p><b>Cajero:</b> <?php echo htmlspecialchars($recaudo['cajero_nombre']); ?></p>
        <?php endif; ?>
    </div>

    <div class="divider"></div>

    <div class="info-cliente">
        <p><b>Cliente:</b> <?php echo htmlspecialchars($recaudo['cliente_nombre'] ?? 'Cliente General'); ?></p>
        <p><b>RTN/DNI:</b> <?php echo htmlspecialchars($recaudo['rtn_dni'] ?? '0000000000000'); ?></p>
        <p><b>Código BP:</b> <?php echo htmlspecialchars($recaudo['codigo_bp'] ?? 'BP000'); ?></p>
    </div>

    <div class="divider"></div>

    <!-- Tabla de Cuotas Aplicadas -->
    <table>
        <thead>
            <tr>
                <th class="text-start">Cuota</th>
                <th class="text-start">Vencimiento</th>
                <th class="text-end">Monto</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($cuotasPagadas)): ?>
                <?php foreach ($cuotasPagadas as $cuota): ?>
                <tr>
                    <td class="text-start">N° <?php echo htmlspecialchars($cuota['numero_cuota']); ?></td>
                    <td class="text-start"><?php echo htmlspecialchars($cuota['fecha_vencimiento']); ?></td>
                    <td class="text-end">L. <?php echo number_format((float)$cuota['monto'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="text-center">Abono general a contrato</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="divider"></div>

    <!-- Desglose de Métodos de Pago y Totales -->
    <table class="totales-tabla">
        <?php if (!empty($detallesPagos)): ?>
            <?php foreach ($detallesPagos as $pago): ?>
            <tr>
                <td class="text-start"><?php echo ucfirst(htmlspecialchars($pago['metodo'])); ?> <?php echo !empty($pago['detalle']) ? '('.htmlspecialchars($pago['detalle']).')' : ''; ?>:</td>
                <td class="text-end">L. <?php echo number_format((float)$pago['monto'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td class="text-start">Efectivo / Pago Registrado:</td>
                <td class="text-end">L. <?php echo number_format($totalAbonadoRecaudo, 2); ?></td>
            </tr>
        <?php endif; ?>

        <tr><td colspan="2"><div class="divider"></div></td></tr>

        <tr style="font-size: 13px; font-weight: bold;">
            <td class="text-start">TOTAL ABONADO:</td>
            <td class="text-end">L. <?php echo number_format($totalAbonadoRecaudo, 2); ?></td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="footer text-center">
        <p class="fw-bold">¡Gracias por su puntualidad!</p>
        <p>*** Conserve este comprobante ***</p>
    </div>

</body>
</html>