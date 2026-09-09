<?php
// views/imprimir_factura.php
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

// Capturamos el ID ya sea como 'id' o como 'id_transaccion'
$venta_id = $_GET['id'] ?? $_GET['id_transaccion'] ?? null;

if (!$venta_id) {
    die("Error: No se proporcionó el ID de la venta.");
}

try {
    // 1. Obtener la cabecera de la venta mediante id_transaccion
    $stmtVenta = $pdo->prepare("SELECT * FROM ventas WHERE id_transaccion = ?");
    $stmtVenta->execute([$venta_id]);
    $venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        die("Error: La venta con el ID #" . htmlspecialchars($venta_id) . " no existe.");
    }

    // 2. Obtener los productos haciendo JOIN con productos para traer el nombre real
    $stmtDetalle = $pdo->prepare("
        SELECT 
            dv.*, 
            p.nombre AS nombre_producto 
        FROM detalle_ventas dv
        LEFT JOIN productos p ON dv.producto_id = p.id
        WHERE dv.venta_id = ?
    ");
    $stmtDetalle->execute([$venta_id]);
    $detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

    // 3. Obtener los detalles de pago filtrando únicamente por venta_id
    $stmtPago = $pdo->prepare("SELECT * FROM pagos_ventas WHERE venta_id = ?");
    $stmtPago->execute([$venta_id]);
    $pagoInfo = $stmtPago->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error en la base de datos: " . $e->getMessage());
}

// Cálculos del ISV (15%) y totales
$totalVenta = (float)($venta['total'] ?? 0);
$subtotalSinISV = $totalVenta / 1.15;
$isv15 = $totalVenta - $subtotalSinISV;
$ahorroTotal = (float)($venta['ahorro_total'] ?? 0);
$montoAbonado = (float)($venta['monto_abonado'] ?? 0);
$cambioEntregado = (float)($venta['cambio_entregado'] ?? 0);

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
    <title>Comprobante #<?php echo str_pad($venta['id_transaccion'], 6, '0', STR_PAD_LEFT); ?></title>
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
            🖨️ Imprimir Factura
        </button>
        <button onclick="window.close()" style="padding: 8px 15px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 5px;">
            ❌ Cerrar
        </button>
    </div>

    <div class="header text-center">
        <h3><?php echo $nombre_empresa; ?></h3>
        <p>Venta de Productos y Servicios</p>
        <p>Honduras</p>
        <div class="divider"></div>
        <p class="fw-bold"><?php echo strtoupper(htmlspecialchars($venta['tipo_comprobante'] ?? 'FACTURA')); ?></p>
        
        <!-- Número de Factura -->
        <p><b>Factura N°:</b> <?php echo htmlspecialchars($venta['numero_factura'] ?? $venta['id'] ?? 'S/N'); ?></p>
        
        <!-- Número de Transacción -->
        <p><b>Transacción N°:</b> #<?php echo str_pad($venta['id_transaccion'], 6, '0', STR_PAD_LEFT); ?></p>
        
        <p><b>Fecha:</b> <?php echo date('d/m/Y h:i A', strtotime($venta['fecha_venta'])); ?></p>
    </div>

    <div class="divider"></div>

    <div class="info-cliente">
        <p><b>Cliente:</b> <?php echo htmlspecialchars($venta['cliente_nombre'] ?? 'Consumidor Final'); ?></p>
        <p><b>RTN/DNI:</b> <?php echo htmlspecialchars(!empty($venta['cliente_rtn']) ? $venta['cliente_rtn'] : ($venta['cliente_identidad'] ?? '0000000000000')); ?></p>
        <p><b>Código BP:</b> <?php echo htmlspecialchars($venta['cliente_codigo_bp'] ?? 'BP000'); ?></p>
        <p><b>Método Pago:</b> <?php echo ucfirst(htmlspecialchars($venta['metodo_pago'] ?? 'Efectivo')); ?></p>

        <!-- Detalles de tarjeta condicionales -->
        <?php if (strtolower($venta['metodo_pago'] ?? '') === 'tarjeta' && !empty($pagoInfo['detalle'])): ?>
            <p><b>Ref. Tarjeta:</b> <?php echo htmlspecialchars($pagoInfo['detalle']); ?></p>
        <?php endif; ?>
    </div>

    <div class="divider"></div>

    <table>
        <thead>
            <tr>
                <th class="text-start">Cant</th>
                <th class="text-start">Prod</th>
                <th class="text-end">P.Unit</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($detalles)): ?>
                <?php foreach ($detalles as $item): 
                    $cant = (int)($item['cantidad'] ?? 1);
                    $precioUnit = (float)($item['precio_unitario'] ?? $item['precio'] ?? 0);
                    $subtotalItem = (float)($item['subtotal'] ?? ($cant * $precioUnit));
                    $nombreProd = $item['nombre_producto'] ?? $item['producto_nombre'] ?? $item['nombre'] ?? 'Producto';
                ?>
                <tr>
                    <td class="text-start"><?php echo $cant; ?></td>
                    <td class="text-start"><?php echo htmlspecialchars($nombreProd); ?></td>
                    <td class="text-end"><?php echo number_format($precioUnit, 2); ?></td>
                    <td class="text-end"><?php echo number_format($subtotalItem, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center">Sin detalles registrados</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="divider"></div>

    <table class="totales-tabla">
        <tr>
            <td class="text-start">Subtotal (Sin ISV):</td>
            <td class="text-end">L. <?php echo number_format($subtotalSinISV, 2); ?></td>
        </tr>
        <tr>
            <td class="text-start">ISV (15%):</td>
            <td class="text-end">L. <?php echo number_format($isv15, 2); ?></td>
        </tr>
        <?php if ($ahorroTotal > 0): ?>
        <tr>
            <td class="text-start fw-bold">Descuento Total:</td>
            <td class="text-end fw-bold">- L. <?php echo number_format($ahorroTotal, 2); ?></td>
        </tr>
        <?php endif; ?>
        <tr style="font-size: 13px; font-weight: bold;">
            <td class="text-start">TOTAL A PAGAR:</td>
            <td class="text-end">L. <?php echo number_format($totalVenta, 2); ?></td>
        </tr>
        <tr>
            <td class="text-start">Abonado/Recibido:</td>
            <td class="text-end">L. <?php echo number_format($montoAbonado, 2); ?></td>
        </tr>
        <tr>
            <td class="text-start">Cambio:</td>
            <td class="text-end">L. <?php echo number_format($cambioEntregado, 2); ?></td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="footer text-center">
        <p class="fw-bold">¡Gracias por su preferencia!</p>
        <p>*** Conserve este comprobante ***</p>
    </div>

</body>
</html>