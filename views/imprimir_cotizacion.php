<?php
// views/imprimir_cotizacion.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    die('No autorizado');
}

require_once '../config/conexion.php';

$id = intval($_GET['id_transaccion'] ?? 0);
if ($id <= 0) {
    die('ID de transacción no válido');
}

// Obtener la venta
$stmt = $pdo->prepare("SELECT * FROM ventas WHERE id_transaccion = ?");
$stmt->execute([$id]);
$venta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    die('Orden no encontrada');
}

// Obtener detalles
$stmtDet = $pdo->prepare("
    SELECT d.*, p.nombre, p.codigo 
    FROM detalle_ventas d 
    LEFT JOIN productos p ON d.producto_id = p.id 
    WHERE d.venta_id = ?
");
$stmtDet->execute([$id]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

// Nombre de la empresa
$nombre_empresa = "INVERSIONES J.A";
try {
    $stmtConf = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1");
    if ($row = $stmtConf->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['nombre_empresa'])) {
            $nombre_empresa = $row['nombre_empresa'];
        }
    }
} catch (Exception $e) {}

$esCredito = (int)$venta['es_credito'] === 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización #<?php echo $venta['id_transaccion']; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #222; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; margin-bottom: 25px; border-bottom: 2px solid #2563eb; padding-bottom: 15px; }
        .empresa { font-size: 20px; font-weight: bold; color: #1e40af; }
        .titulo { font-size: 18px; font-weight: bold; text-align: right; color: #1e40af; }
        .info-box { display: flex; gap: 30px; margin-bottom: 20px; }
        .info-box > div { flex: 1; }
        .label { font-size: 11px; color: #666; text-transform: uppercase; }
        .valor { font-weight: bold; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #f1f5f9; text-align: left; padding: 8px 10px; font-size: 12px; border-bottom: 1px solid #cbd5e1; }
        td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
        .totales { margin-top: 15px; text-align: right; }
        .totales div { margin: 4px 0; }
        .total-final { font-size: 16px; font-weight: bold; color: #1e40af; }
        .credito-box { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 15px; margin-top: 25px; }
        .credito-box h3 { margin-bottom: 12px; color: #1e40af; font-size: 15px; }
        .credito-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .credito-item { background: white; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .footer { margin-top: 40px; text-align: center; font-size: 11px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 15px; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <div class="empresa"><?php echo htmlspecialchars($nombre_empresa); ?></div>
                <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Cotización / Orden Pendiente</div>
            </div>
            <div class="titulo">
                Cotización #<?php echo $venta['id_transaccion']; ?><br>
                <span style="font-size: 13px; font-weight: normal; color: #64748b;">
                    <?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?>
                </span>
            </div>
        </div>

        <div class="info-box">
            <div>
                <div class="label">Cliente</div>
                <div class="valor"><?php echo htmlspecialchars($venta['cliente_nombre']); ?></div>
                <div style="font-size: 12px; margin-top: 3px;">
                    BP: <?php echo htmlspecialchars($venta['cliente_codigo_bp']); ?> | 
                    RTN/DNI: <?php echo htmlspecialchars($venta['cliente_rtn']); ?>
                </div>
            </div>
            <div>
                <div class="label">Tipo</div>
                <div class="valor"><?php echo $esCredito ? 'Crédito' : 'Contado'; ?></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th style="text-align:center;">Cant.</th>
                    <th style="text-align:right;">Precio</th>
                    <th style="text-align:right;">Desc.</th>
                    <th style="text-align:right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $d): ?>
                <tr>
                    <td><?php echo htmlspecialchars($d['nombre'] ?? 'Producto'); ?></td>
                    <td style="text-align:center;"><?php echo $d['cantidad']; ?></td>
                    <td style="text-align:right;">L. <?php echo number_format($d['precio_unitario'], 2); ?></td>
                    <td style="text-align:right;">L. <?php echo number_format($d['descuento_unitario'], 2); ?></td>
                    <td style="text-align:right;">L. <?php echo number_format($d['subtotal'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totales">
            <div>Subtotal: <strong>L. <?php echo number_format($venta['total'], 2); ?></strong></div>
            <?php if ($venta['ahorro_total'] > 0): ?>
            <div style="color: #059669;">Ahorro: L. <?php echo number_format($venta['ahorro_total'], 2); ?></div>
            <?php endif; ?>
            <div class="total-final">TOTAL: L. <?php echo number_format($venta['total'], 2); ?></div>
        </div>

        <?php if ($esCredito): ?>
        <div class="credito-box">
            <h3> Condiciones de Crédito Cotizadas</h3>
            <div class="credito-grid">
                <div class="credito-item">
                    <div class="label">Prima / Enganche</div>
                    <div class="valor">L. <?php echo number_format($venta['prima'], 2); ?></div>
                </div>
                <div class="credito-item">
                    <div class="label">Capital a Financiar</div>
                    <div class="valor">L. <?php echo number_format($venta['monto_financiar'], 2); ?></div>
                </div>
                <div class="credito-item">
                    <div class="label">Interés Total</div>
                    <div class="valor">L. <?php echo number_format($venta['interes_total'], 2); ?></div>
                </div>
                <div class="credito-item">
                    <div class="label">Total Crédito</div>
                    <div class="valor">L. <?php echo number_format($venta['total_credito'], 2); ?></div>
                </div>
                <div class="credito-item">
                    <div class="label">Plazo</div>
                    <div class="valor"><?php echo $venta['plazo_meses']; ?> meses</div>
                </div>
                <div class="credito-item">
                    <div class="label">Cuota Mensual</div>
                    <div class="valor" style="color: #1e40af; font-size: 15px;">
                        L. <?php echo number_format($venta['cuota_mensual'], 2); ?>
                    </div>
                </div>
            </div>
            <p style="margin-top: 12px; font-size: 11px; color: #64748b;">
                * Esta cotización es válida por 15 días. Las condiciones de crédito están sujetas a aprobación final.
            </p>
        </div>
        <?php endif; ?>

        <div class="footer">
            Documento generado el <?php echo date('d/m/Y H:i'); ?> — <?php echo htmlspecialchars($nombre_empresa); ?>
        </div>

        <div class="no-print" style="margin-top: 30px; text-align: center;">
            <button onclick="window.print()" style="background:#2563eb;color:white;border:none;padding:10px 25px;border-radius:8px;cursor:pointer;font-size:14px;">
                Imprimir Cotización
            </button>
        </div>
    </div>

    <script>
        // Auto-imprimir al abrir (opcional)
        // window.onload = () => window.print();
    </script>
</body>
</html>