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
$cuotasParam = $_GET['cuotas'] ?? '';
$contratoParam = $_GET['contrato'] ?? null;

if (!$anulacion_id) {
    die("Error: ID de anulación no proporcionado.");
}

try {
    // 1. Obtener los datos del registro de anulación negativo (Usando LEFT JOIN para evitar fallos si el contrato varía)
    $stmtAnulacion = $pdo->prepare("
        SELECT tr.*, co.id AS id_contrato, c.Nombre AS cliente_nombre, c.rtn_dni, c.codigo_bp, u.nombre AS cajero_nombre
        FROM transacciones_recaudo tr
        LEFT JOIN contratos co ON tr.contrato_id = co.id
        LEFT JOIN clientes c ON co.codigo_bp = c.codigo_bp
        LEFT JOIN usuarios u ON tr.usuario_id = u.id
        WHERE tr.id = ?
    ");
    $stmtAnulacion->execute([$anulacion_id]);
    $anulacion = $stmtAnulacion->fetch(PDO::FETCH_ASSOC);

    if (!is_array($anulacion)) {
        die("Error: Registro de anulación no encontrado (ID: " . htmlspecialchars($anulacion_id) . ").");
    }

    if (!$contratoParam) {
        $contratoParam = $anulacion['id_contrato'] ?? null;
    }

    // 2. Obtener el detalle de las cuotas devueltas
    $cuotasDevueltas = [];
    
    if (!empty($cuotasParam)) {
        $idsArray = array_map('intval', explode(',', $cuotasParam));
        if (!empty($idsArray) && $idsArray[0] > 0) {
            $placeholders = implode(',', array_fill(0, count($idsArray), '?'));
            $stmtCuotas = $pdo->prepare("
                SELECT numero_cuota, monto_cuota AS monto, fecha_vencimiento 
                FROM cuotas_contrato 
                WHERE id IN ($placeholders)
                ORDER BY numero_cuota ASC
            ");
            $stmtCuotas->execute($idsArray);
            $cuotasDevueltas = $stmtCuotas->fetchAll(PDO::FETCH_ASSOC);
        }
    } 
    
    // Fallback si no vinieron cuotas por URL pero tenemos contrato
    if (empty($cuotasDevueltas) && $contratoParam) {
        $montoBuscado = abs((float)$anulacion['monto_total']);
        $stmtCuotasAprox = $pdo->prepare("
            SELECT numero_cuota, monto_cuota AS monto, fecha_vencimiento 
            FROM cuotas_contrato 
            WHERE contrato_id = ? 
            ORDER BY fecha_vencimiento DESC
            LIMIT 5
        ");
        $stmtCuotasAprox->execute([$contratoParam]);
        $cuotasDevueltas = $stmtCuotasAprox->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Error en base de datos: " . $e->getMessage());
}

$montoRevertido = abs((float)($anulacion['monto_total'] ?? 0));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Anulación #<?php echo str_pad($anulacion_id, 6, '0', STR_PAD_LEFT); ?></title>
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
        <p class="fw-bold" style="font-size: 13px;">COMPROBANTE DE ANULACIÓN</p>
        
        <p><b>Recibo Original:</b> #<?php echo ($original_id !== 'N/A' ? str_pad($original_id, 6, '0', STR_PAD_LEFT) : 'N/A'); ?></p>
        <p><b>Nota de Ajuste N°:</b> #<?php echo str_pad($anulacion['id'], 6, '0', STR_PAD_LEFT); ?></p>
        <p><b>Contrato N°:</b> #<?php echo htmlspecialchars($anulacion['id_contrato'] ?? 'N/A'); ?></p>
        <p><b>Fecha:</b> <?php echo date('d/m/Y h:i A', strtotime($anulacion['fecha'] ?? 'now')); ?></p>
        <?php if (!empty($anulacion['cajero_nombre'])): ?>
            <p><b>Autorizó:</b> <?php echo htmlspecialchars($anulacion['cajero_nombre']); ?></p>
        <?php endif; ?>
    </div>

    <div class="divider"></div>

    <div class="info-cliente">
        <p><b>Cliente:</b> <?php echo htmlspecialchars($anulacion['cliente_nombre'] ?? 'Cliente General'); ?></p>
        <p><b>RTN/DNI:</b> <?php echo htmlspecialchars($anulacion['rtn_dni'] ?? '0000000000000'); ?></p>
        <p><b>Código BP:</b> <?php echo htmlspecialchars($anulacion['codigo_bp'] ?? 'BP000'); ?></p>
    </div>

    <div class="divider"></div>

    <!-- Tabla de Cuotas Devueltas -->
    <table>
        <thead>
            <tr>
                <th class="text-start">Cuota Dev.</th>
                <th class="text-start">Vencimiento</th>
                <th class="text-end">Monto</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($cuotasDevueltas)): ?>
                <?php foreach ($cuotasDevueltas as $cuota): ?>
                <tr>
                    <td class="text-start">N° <?php echo htmlspecialchars($cuota['numero_cuota'] ?? 'N/A'); ?></td>
                    <td class="text-start"><?php echo htmlspecialchars($cuota['fecha_vencimiento'] ?? '-'); ?></td>
                    <td class="text-end" style="color: red;">- L. <?php echo number_format((float)($cuota['monto'] ?? 0), 2); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="text-center">Reversión aplicada al contrato</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="divider"></div>

    <!-- Totales de la Anulación -->
    <table class="totales-tabla">
        <tr>
            <td class="text-start">Devolución en Efectivo / Caja:</td>
            <td class="text-end" style="color: red;">- L. <?php echo number_format($montoRevertido, 2); ?></td>
        </tr>

        <tr><td colspan="2"><div class="divider"></div></td></tr>

        <tr style="font-size: 13px; font-weight: bold;">
            <td class="text-start">TOTAL REVERTIDO:</td>
            <td class="text-end" style="color: red;">- L. <?php echo number_format($montoRevertido, 2); ?></td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="footer text-center">
        <p class="fw-bold">REGISTRO DE AUDITORÍA INTERNA</p>
        <p>*** Documento para control de caja ***</p>
    </div>

</body>
</html>