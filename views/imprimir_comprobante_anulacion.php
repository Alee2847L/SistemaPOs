<?php
// views/imprimir_comprobante_anulacion.php
session_start();
require_once '../config/conexion.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('America/Tegucigalpa');

if (!isset($_SESSION['usuario_id'])) {
    die("Error: Sesión no iniciada.");
}

$anulacion_id = $_GET['id'] ?? null;
$original_id = $_GET['original'] ?? 'N/A';

if (!$anulacion_id) {
    die("Error: ID de anulación no proporcionado.");
}

try {
    // Obtener los datos del registro de anulación negativo
    $stmtAnulacion = $pdo->prepare("
        SELECT tr.*, co.id AS id_contrato, c.Nombre AS cliente_nombre, c.rtn_dni, c.codigo_bp, u.nombre AS cajero_nombre
        FROM transacciones_recaudo tr
        JOIN contratos co ON tr.contrato_id = co.id
        LEFT JOIN clientes c ON co.codigo_bp = c.codigo_bp
        LEFT JOIN usuarios u ON tr.usuario_id = u.id
        WHERE tr.id = ?
    ");
    $stmtAnulacion->execute([$anulacion_id]);
    $anulacion = $stmtAnulacion->fetch(PDO::FETCH_ASSOC);

    if (!$anulacion) {
        die("Error: Registro de anulación no encontrado.");
    }

    // Como las cuotas ya se desvincularon (recaudo_id se puso en NULL), podemos consultar el contrato 
    // o almacenar las cuotas afectadas. Para fines de este recibo de anulación, listamos las cuotas del contrato que correspondan o podemos buscarlas si guardamos un respaldo. 
    // Alternativa segura: Mostrar el monto total ajustado y la constancia de reversión.
} catch (PDOException $e) {
    die("Error en base de datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Anulación #<?php echo str_pad($anulacion_id, 6, '0', STR_PAD_LEFT); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Courier New', Courier, monospace; }
        body { width: 280px; margin: 0 auto; padding: 10px; color: #000; background: #fff; font-size: 12px; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .text-start { text-align: left; }
        .fw-bold { font-weight: bold; }
        .header { margin-bottom: 10px; }
        .header h3 { font-size: 16px; text-transform: uppercase; margin-bottom: 3px; }
        .header p { font-size: 11px; line-height: 1.2; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        .info-cliente { font-size: 11px; line-height: 1.4; }
        .footer { margin-top: 15px; font-size: 11px; text-align: center; }
        @media print {
            .no-print { display: none !important; }
            body { width: 100%; padding: 0; }
        }
    </style>
</head>
<body onload="window.print();">

    <div class="no-print text-center" style="margin-bottom: 15px;">
        <button onclick="window.print()" style="padding: 8px 15px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
            🖨️ Imprimir Anulación
        </button>
        <button onclick="window.close()" style="padding: 8px 15px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 5px;">
            ❌ Cerrar
        </button>
    </div>

    <div class="header text-center">
        <h3>INVERSIONES J.A</h3>
        <p>Módulo de Auditoría y Control</p>
        <div class="divider"></div>
        <p class="fw-bold" style="color: red;">COMPROBANTE DE ANULACIÓN</p>
        <p><b>Recibo Original Anulado:</b> #<?php echo str_pad($original_id, 6, '0', STR_PAD_LEFT); ?></p>
        <p><b>Nota de Ajuste N°:</b> #<?php echo str_pad($anulacion['id'], 6, '0', STR_PAD_LEFT); ?></p>
        <p><b>Contrato N°:</b> #<?php echo htmlspecialchars($anulacion['id_contrato']); ?></p>
        <p><b>Fecha de Reversión:</b> <?php echo date('d/m/Y h:i A', strtotime($anulacion['fecha'])); ?></p>
        <p><b>Autorizado por:</b> <?php echo htmlspecialchars($anulacion['cajero_nombre'] ?? 'Administrador'); ?></p>
    </div>

    <div class="divider"></div>

    <div class="info-cliente">
        <p><b>Cliente:</b> <?php echo htmlspecialchars($anulacion['cliente_nombre']); ?></p>
        <p><b>RTN/DNI:</b> <?php echo htmlspecialchars($anulacion['rtn_dni']); ?></p>
        <p><b>Código BP:</b> <?php echo htmlspecialchars($anulacion['codigo_bp']); ?></p>
    </div>

    <div class="divider"></div>

    <div style="font-size: 11px; text-align: justify; margin-bottom: 10px;">
        <p><b>ESTADO:</b> Las cuotas asociadas al recibo original han sido regresadas a estado <b>PENDIENTE</b> y el límite de crédito del cliente ha sido ajustado contablemente.</p>
    </div>

    <div class="divider"></div>

    <table style="width: 100%; font-size: 11px;">
        <tr style="font-size: 13px; font-weight: bold;">
            <td class="text-start">MONTO REVERTIDO:</td>
            <td class="text-end" style="color: red;">- L. <?php echo number_format(abs((float)$anulacion['monto_total']), 2); ?></td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="footer">
        <p class="fw-bold">REGISTRO DE AUDITORÍA INTERNA</p>
        <p>*** Documento para control contable ***</p>
    </div>

</body>
</html>