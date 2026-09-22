<?php
// api/enviar_comprobante_venta.php
// Envía al cliente, por correo, la factura de una venta y (si es a crédito) el plan de pagos.
// Uso: require_once __DIR__ . '/enviar_comprobante_venta.php';
//      enviarComprobanteVentaPorCorreo($pdo, $ventaId, $contratoId);  // DESPUÉS del commit()
//      $contratoId es opcional; pásalo solo si en esta venta se creó un contrato de crédito.

if (!function_exists('cargarEnvParaCorreo')) {
    /** Carga el .env (misma ruta y formato que enviar_recordatorios_cuotas.php) si aún no está cargado. */
    function cargarEnvParaCorreo(): void {
        if (!empty($_ENV['MAIL_HOST']) || !empty($_ENV['MAIL_USER'])) return;
        $envPath = __DIR__ . '/../../.env';
        if (!file_exists($envPath)) return;
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
            [$name, $value] = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}

if (!function_exists('nombreEmpresaParaCorreo')) {
    function nombreEmpresaParaCorreo(PDO $pdo): string {
        $empresa = 'Sistema POS';
        try {
            $row = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['nombre_empresa'])) $empresa = $row['nombre_empresa'];
        } catch (Throwable $e) { /* se mantiene el valor por defecto */ }
        return $empresa;
    }
}

/** Arma la tabla HTML de una factura (subtotal, ISV, descuento, total, abono, cambio). */
function construirHtmlFactura(array $venta, array $detalles, string $empresa): string {
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $L = fn($n) => 'L. ' . number_format((float)$n, 2);

    $totalVenta     = (float)($venta['total'] ?? 0);
    $subtotalSinISV = $totalVenta / 1.15;
    $isv15          = $totalVenta - $subtotalSinISV;
    $ahorroTotal    = (float)($venta['ahorro_total'] ?? 0);
    $montoAbonado   = (float)($venta['monto_abonado'] ?? 0);
    $cambio         = (float)($venta['cambio_entregado'] ?? 0);

    $filas = '';
    foreach ($detalles as $item) {
        $cant         = (int)($item['cantidad'] ?? 1);
        $precioUnit   = (float)($item['precio_unitario'] ?? 0);
        $subtotalItem = (float)($item['subtotal'] ?? ($cant * $precioUnit));
        $nombreProd   = $item['nombre_producto'] ?? 'Producto';
        $filas .= "<tr>
            <td style='padding:6px 8px; border-bottom:1px solid #e2e8f0; text-align:center;'>{$h($cant)}</td>
            <td style='padding:6px 8px; border-bottom:1px solid #e2e8f0;'>{$h($nombreProd)}</td>
            <td style='padding:6px 8px; border-bottom:1px solid #e2e8f0; text-align:right;'>{$L($precioUnit)}</td>
            <td style='padding:6px 8px; border-bottom:1px solid #e2e8f0; text-align:right;'>{$L($subtotalItem)}</td>
        </tr>";
    }

    $descuentoFila = '';
    if ($ahorroTotal > 0) {
        $descuentoFila = "<p style='margin:4px 0;'><strong>Descuento total:</strong> -{$L($ahorroTotal)}</p>";
    }

    return "
        <h2 style='color:#4f46e5;'>Comprobante de venta</h2>
        <div style='background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin:16px 0;'>
            <p style='margin:4px 0;'><strong>" . $h(strtoupper($venta['tipo_comprobante'] ?? 'FACTURA')) . ":</strong> " . $h($venta['numero_factura'] ?? ('#' . $venta['id_transaccion'])) . "</p>
            <p style='margin:4px 0;'><strong>Fecha:</strong> " . $h(date('d/m/Y h:i A', strtotime($venta['fecha_venta'] ?? 'now'))) . "</p>
            <p style='margin:4px 0;'><strong>Método de pago:</strong> " . $h($venta['metodo_pago'] ?? 'Efectivo') . "</p>
        </div>

        <table style='width:100%; border-collapse:collapse; font-size:14px;'>
            <thead>
                <tr style='background:#f1f5f9;'>
                    <th style='padding:8px; text-align:center;'>Cant</th>
                    <th style='padding:8px; text-align:left;'>Producto</th>
                    <th style='padding:8px; text-align:right;'>P. Unit.</th>
                    <th style='padding:8px; text-align:right;'>Subtotal</th>
                </tr>
            </thead>
            <tbody>{$filas}</tbody>
        </table>

        <div style='margin-top:12px; font-size:14px;'>
            <p style='margin:4px 0;'><strong>Subtotal (sin ISV):</strong> {$L($subtotalSinISV)}</p>
            <p style='margin:4px 0;'><strong>ISV (15%):</strong> {$L($isv15)}</p>
            {$descuentoFila}
            <p style='margin:8px 0 4px; font-size:16px; color:#4f46e5;'><strong>Total a pagar: {$L($totalVenta)}</strong></p>
            <p style='margin:4px 0;'><strong>Abonado/Recibido:</strong> {$L($montoAbonado)}</p>
            <p style='margin:4px 0;'><strong>Cambio:</strong> {$L($cambio)}</p>
        </div>";
}

/** Arma la tabla HTML del plan de pagos de un contrato (mismo formato que enviar_plan_pagos.php). */
function construirHtmlPlanPagos(PDO $pdo, int $contratoId): ?string {
    $h   = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $L   = fn($n) => 'L. ' . number_format((float)$n, 2);
    $fmt = fn($f) => date('d/m/Y', strtotime($f));

    $stmt = $pdo->prepare("
        SELECT id, producto_descripcion, total_factura, prima, monto_financiar,
               porcentaje_interes, total_credito, plazo_meses, fecha_inicio
          FROM contratos WHERE id = ?
    ");
    $stmt->execute([$contratoId]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$c) return null;

    $stmt = $pdo->prepare("
        SELECT numero_cuota, fecha_vencimiento, monto_cuota
          FROM cuotas_contrato WHERE contrato_id = ? ORDER BY numero_cuota ASC
    ");
    $stmt->execute([$contratoId]);
    $cuotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($cuotas)) return null;

    $filas = '';
    foreach ($cuotas as $q) {
        $filas .= "<tr>
            <td style='padding:6px 8px; border-bottom:1px solid #e2e8f0; text-align:center;'>{$h($q['numero_cuota'])}</td>
            <td style='padding:6px 8px; border-bottom:1px solid #e2e8f0;'>{$fmt($q['fecha_vencimiento'])}</td>
            <td style='padding:6px 8px; border-bottom:1px solid #e2e8f0; text-align:right;'>{$L($q['monto_cuota'])}</td>
        </tr>";
    }

    return "
        <hr style='border:none; border-top:1px solid #e2e8f0; margin:28px 0;'>
        <h2 style='color:#4f46e5;'>Plan de pagos</h2>
        <div style='background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin:16px 0;'>
            <p style='margin:4px 0;'><strong>Contrato:</strong> #{$h($c['id'])}</p>
            <p style='margin:4px 0;'><strong>Fecha de inicio:</strong> {$fmt($c['fecha_inicio'])}</p>
            <p style='margin:4px 0;'><strong>Prima:</strong> {$L($c['prima'])}</p>
            <p style='margin:4px 0;'><strong>Monto financiado:</strong> {$L($c['monto_financiar'])}</p>
            <p style='margin:4px 0;'><strong>Interés:</strong> " . $h(rtrim(rtrim(number_format((float)$c['porcentaje_interes'], 2), '0'), '.')) . "%</p>
            <p style='margin:4px 0;'><strong>Plazo:</strong> {$h($c['plazo_meses'])} meses</p>
            <p style='margin:8px 0 0; font-size:16px; color:#4f46e5;'><strong>Total del crédito: {$L($c['total_credito'])}</strong></p>
        </div>

        <table style='width:100%; border-collapse:collapse; font-size:14px;'>
            <thead>
                <tr style='background:#f1f5f9;'>
                    <th style='padding:8px; text-align:center;'>Cuota</th>
                    <th style='padding:8px; text-align:left;'>Vencimiento</th>
                    <th style='padding:8px; text-align:right;'>Monto</th>
                </tr>
            </thead>
            <tbody>{$filas}</tbody>
        </table>";
}

/**
 * Devuelve true si el correo se envió. Nunca lanza excepciones: si algo falla,
 * lo registra en el log de PHP (etiqueta [comprobante_venta]) para no afectar la venta.
 */
function enviarComprobanteVentaPorCorreo(PDO $pdo, int $ventaId, ?int $contratoId = null): bool {
    try {
        // 1. Venta + correo del cliente
        $stmt = $pdo->prepare("
            SELECT v.*, cl.Correo AS cliente_correo, cl.Nombre AS cliente_nombre_real
              FROM ventas v
              LEFT JOIN clientes cl ON v.cliente_codigo_bp = cl.codigo_bp
             WHERE v.id_transaccion = ?
        ");
        $stmt->execute([$ventaId]);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$venta || empty($venta['cliente_correo']) || !filter_var($venta['cliente_correo'], FILTER_VALIDATE_EMAIL)) {
            error_log("[comprobante_venta] Venta #{$ventaId}: el cliente no tiene un correo válido; no se envió el comprobante.");
            return false;
        }

        // 2. Detalle de productos
        $stmt = $pdo->prepare("
            SELECT dv.*, p.nombre AS nombre_producto
              FROM detalle_ventas dv
              LEFT JOIN productos p ON dv.producto_id = p.id
             WHERE dv.venta_id = ?
        ");
        $stmt->execute([$ventaId]);
        $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $empresa = nombreEmpresaParaCorreo($pdo);
        $nombreCliente = $venta['cliente_nombre_real'] ?? $venta['cliente_nombre'] ?? 'cliente';
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $L = fn($n) => 'L. ' . number_format((float)$n, 2);

        // El detalle completo (factura + plan de pagos) ya no va en el cuerpo del correo:
        // se genera como PDF adjunto (ver más abajo) para que el cliente lo pueda
        // descargar/imprimir/guardar como documento.
        cargarEnvParaCorreo();
        $enlacePortal = '';
        if ($contratoId && !empty($_ENV['PORTAL_URL'])) {
            $enlacePortal = "<p>Puedes consultar tus cuotas y recibos en el
                <a href='{$h($_ENV['PORTAL_URL'])}'>portal de clientes</a>.</p>";
        }

        $numeroCorto = $venta['numero_factura'] ?: ('#' . $venta['id_transaccion']);
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <p>Hola <strong>{$h($nombreCliente)}</strong>,</p>
            <p>Gracias por tu compra en <strong>{$h($empresa)}</strong>.</p>
            <div style='background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin:16px 0;'>
                <p style='margin:4px 0;'><strong>Comprobante:</strong> {$h($numeroCorto)}</p>
                <p style='margin:4px 0;'><strong>Total:</strong> {$L($venta['total'] ?? 0)}</p>
            </div>
            <p>Tu factura" . ($contratoId ? ' y el plan de pagos vienen adjuntos' : ' viene adjunta') . " en este correo, en formato PDF.</p>
            {$enlacePortal}
            <hr style='border:none; border-top:1px solid #e2e8f0; margin:24px 0;'>
            <p style='font-size:12px; color:#64748b;'>Este es un mensaje automático de <strong>{$h($empresa)}</strong>. Conserva tu comprobante como respaldo fiscal.</p>
        </div>";

        // 3. Envío (mismo SMTP que los recordatorios y el plan de pagos)
        require_once __DIR__ . '/../phpmailer/Exception.php';
        require_once __DIR__ . '/../phpmailer/PHPMailer.php';
        require_once __DIR__ . '/../phpmailer/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $_ENV['MAIL_HOST'] ?? 'smtp-relay.brevo.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USER'] ?? '';
            $mail->Password   = $_ENV['MAIL_PASS'] ?? '';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 587);
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($_ENV['MAIL_FROM'] ?? 'noreply@misistemapos.com', $_ENV['MAIL_NAME'] ?? $empresa);
            $mail->addAddress($venta['cliente_correo'], $nombreCliente);

            $mail->isHTML(true);
            $numero = $venta['numero_factura'] ?: ('#' . $venta['id_transaccion']);
            $mail->Subject = $contratoId
                ? "Comprobante y plan de pagos {$numero} - {$empresa}"
                : "Comprobante de compra {$numero} - {$empresa}";
            $mail->Body    = $body;
            $mail->AltBody = "Gracias por tu compra en {$empresa}. Comprobante: {$numero}, total: "
                           . number_format((float)$venta['total'], 2) . " Lempiras.";

            // Adjuntar la factura (y plan de pagos si aplica) como PDF.
            require_once __DIR__ . '/generar_pdf_factura.php';
            $pdfFactura = generarPdfFactura($pdo, $ventaId, $contratoId);
            if ($pdfFactura !== null) {
                $nombreArchivoPdf = 'Factura_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$numero) . '.pdf';
                $mail->addStringAttachment($pdfFactura, $nombreArchivoPdf, 'base64', 'application/pdf');
            } else {
                error_log("[comprobante_venta] Venta #{$ventaId}: no se pudo generar el PDF adjunto, se envía solo el correo.");
            }

            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log("[comprobante_venta] Venta #{$ventaId}: error enviando correo: " . $mail->ErrorInfo . ' | ' . $e->getMessage());
            return false;
        }
    } catch (Throwable $e) {
        error_log("[comprobante_venta] Venta #{$ventaId}: " . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        return false;
    }
}