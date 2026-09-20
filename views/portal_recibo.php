<?php
// views/portal_recibo.php — Reimpresión del recibo de recaudo para el CLIENTE (portal)
session_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('America/Tegucigalpa');

require_once __DIR__ . '/../config/conexion.php';

const SESION_MAX_INACTIVIDAD = 1800; // 30 min, igual que api/portal.php

function terminar(string $mensaje, int $http = 400): void {
    http_response_code($http);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Recibo</title></head>'
       . '<body style="font-family: Arial, sans-serif; text-align: center; padding: 40px;">'
       . '<p>' . htmlspecialchars($mensaje) . '</p>'
       . '<p><a href="portal.php">Volver al portal</a></p></body></html>';
    exit;
}

// --- Sesión de CLIENTE obligatoria (no sirve la sesión del personal) ---
if (empty($_SESSION['cliente_id'])) {
    terminar('Sesión no válida. Ingresa nuevamente al portal.', 401);
}
if (time() - ($_SESSION['cliente_ultima_actividad'] ?? 0) > SESION_MAX_INACTIVIDAD) {
    unset($_SESSION['cliente_id'], $_SESSION['cliente_nombre'], $_SESSION['cliente_ultima_actividad']);
    terminar('Tu sesión expiró. Ingresa nuevamente al portal.', 401);
}
$_SESSION['cliente_ultima_actividad'] = time();

$codigoBp   = $_SESSION['cliente_id'];   // siempre de la sesión, nunca de la URL
$recaudo_id = (int)($_GET['id'] ?? 0);
if ($recaudo_id <= 0) {
    terminar('Recibo no encontrado.', 404);
}

try {
    // 1. Cabecera: el recibo solo se devuelve si su contrato pertenece a este cliente
    $stmtRecaudo = $pdo->prepare("
        SELECT tr.*, co.id AS id_contrato, c.Nombre AS cliente_nombre, c.rtn_dni, c.codigo_bp, u.nombre AS cajero_nombre
          FROM transacciones_recaudo tr
          JOIN contratos co ON tr.contrato_id = co.id
          LEFT JOIN clientes c ON co.codigo_bp = c.codigo_bp
          LEFT JOIN usuarios u ON tr.usuario_id = u.id
         WHERE tr.id = ? AND co.codigo_bp = ?
    ");
    $stmtRecaudo->execute([$recaudo_id, $codigoBp]);
    $recaudo = $stmtRecaudo->fetch(PDO::FETCH_ASSOC);

    // Mismo mensaje si no existe o si es de otro cliente
    if (!$recaudo) {
        terminar('Recibo no encontrado.', 404);
    }

    // 2. Cuotas aplicadas a este recibo (también restringidas al cliente)
    $stmtCuotas = $pdo->prepare("
        SELECT cu.numero_cuota, cu.monto_pagado AS monto, cu.fecha_vencimiento
          FROM cuotas_contrato cu
          JOIN contratos co ON cu.contrato_id = co.id
         WHERE cu.recaudo_id = ? AND co.codigo_bp = ?
         ORDER BY cu.numero_cuota ASC
    ");
    $stmtCuotas->execute([$recaudo_id, $codigoBp]);
    $cuotasPagadas = $stmtCuotas->fetchAll(PDO::FETCH_ASSOC);

    // 3. Detalle de métodos de pago (si existe la tabla)
    $detallesPagos = [];
    $stmtCheckTabla = $pdo->query("SHOW TABLES LIKE 'recaudo_pagos_detalle'");
    if ($stmtCheckTabla->rowCount() > 0) {
        $stmtPagos = $pdo->prepare("SELECT * FROM recaudo_pagos_detalle WHERE id_recaudo = ?");
        $stmtPagos->execute([$recaudo_id]);
        $detallesPagos = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('[portal_recibo] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    terminar('No se pudo generar el recibo. Intenta de nuevo más tarde.', 500);
}

$totalAbonadoRecaudo = (float)($recaudo['monto_total'] ?? 0);

// Nombre de la empresa
$nombre_empresa = "INVERSIONES J.A";
try {
    $stmt_config = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1");
    if ($row_config = $stmt_config->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row_config['nombre_empresa'])) {
            $nombre_empresa = htmlspecialchars($row_config['nombre_empresa']);
        }
    }
} catch (Throwable $e) { /* se mantiene el valor por defecto */ }
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
        <p class="fw-bold">*** COPIA / REIMPRESIÓN ***</p>

        <p><b>Recibo N°:</b> #<?php echo str_pad($recaudo['id'], 6, '0', STR_PAD_LEFT); ?></p>
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
