<?php
// api/enviar_plan_pagos.php
// Envía al cliente, por correo, el plan de pagos de un contrato.
// Uso: require_once __DIR__ . '/enviar_plan_pagos.php';
//      enviarPlanPagosPorCorreo($pdo, $contratoId);   // DESPUÉS de confirmar (commit) el contrato y sus cuotas

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

/**
 * Devuelve true si el correo se envió. Nunca lanza excepciones: si algo falla,
 * lo registra en el log de PHP (etiqueta [plan_pagos]) para no interrumpir la creación del contrato.
 */
function enviarPlanPagosPorCorreo(PDO $pdo, int $contratoId): bool {
    try {
        // 1. Contrato + cliente
        $stmt = $pdo->prepare("
            SELECT co.id, co.producto_descripcion, co.total_factura, co.prima, co.monto_financiar,
                   co.porcentaje_interes, co.total_credito, co.plazo_meses, co.fecha_inicio,
                   co.metodo_entrega, co.banco_entrega, co.numero_cuenta_entrega,
                   cl.Nombre AS cliente_nombre, cl.Correo AS cliente_correo
              FROM contratos co
              JOIN clientes cl ON co.codigo_bp = cl.codigo_bp
             WHERE co.id = ?
        ");
        $stmt->execute([$contratoId]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$c || empty($c['cliente_correo']) || !filter_var($c['cliente_correo'], FILTER_VALIDATE_EMAIL)) {
            error_log("[plan_pagos] Contrato #{$contratoId}: el cliente no tiene un correo válido; no se envió el plan.");
            return false;
        }

        // 2. Cuotas
        $stmt = $pdo->prepare("
            SELECT numero_cuota, fecha_vencimiento, monto_cuota
              FROM cuotas_contrato
             WHERE contrato_id = ?
             ORDER BY numero_cuota ASC
        ");
        $stmt->execute([$contratoId]);
        $cuotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($cuotas)) {
            error_log("[plan_pagos] Contrato #{$contratoId}: no tiene cuotas; no se envió el plan.");
            return false;
        }

        // 3. Nombre de la empresa
        $empresa = 'Sistema POS';
        try {
            $row = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['nombre_empresa'])) $empresa = $row['nombre_empresa'];
        } catch (Throwable $e) { /* se mantiene el valor por defecto */ }

        // 4. Cuerpo del correo (todo lo que viene de la BD se escapa)
        $h   = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $L   = fn($n) => 'L. ' . number_format((float)$n, 2);
        $fmt = fn($f) => date('d/m/Y', strtotime($f));

        $filas = '';
        foreach ($cuotas as $q) {
            $filas .= "<tr>
                <td style='padding:6px 8px; border-bottom:1px solid #e2e8f0; text-align:center;'>{$h($q['numero_cuota'])}</td>
                <td style='padding:6px 8px; border-bottom:1px solid #e2e8f0;'>{$fmt($q['fecha_vencimiento'])}</td>
                <td style='padding:6px 8px; border-bottom:1px solid #e2e8f0; text-align:right;'>{$L($q['monto_cuota'])}</td>
            </tr>";
        }

        // Detalle de la entrega del préstamo: efectivo o transferencia (banco + cuenta).
        if (($c['metodo_entrega'] ?? 'efectivo') === 'transferencia') {
            $entregaDetalle = 'Transferencia bancaria — ' . $h($c['banco_entrega'] ?? '-') . ', Cuenta: ' . $h($c['numero_cuenta_entrega'] ?? '-');
        } else {
            $entregaDetalle = 'Efectivo';
        }

        cargarEnvParaCorreo();
        $enlacePortal = '';
        if (!empty($_ENV['PORTAL_URL'])) {   // opcional: PORTAL_URL=https://tudominio.com/views/portal.php en el .env
            $enlacePortal = "<p>Puedes consultar tus cuotas y recibos en el
                <a href='{$h($_ENV['PORTAL_URL'])}'>portal de clientes</a>.</p>";
        }

        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #4f46e5;'>Plan de pagos</h2>
            <p>Hola <strong>{$h($c['cliente_nombre'])}</strong>,</p>
            <p>Tu crédito con <strong>{$h($empresa)}</strong> fue registrado. Este es el resumen y tu plan de pagos:</p>

            <div style='background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin:16px 0;'>
                <p style='margin:4px 0;'><strong>Contrato:</strong> #{$h($c['id'])}</p>
                <p style='margin:4px 0;'><strong>Concepto:</strong> {$h($c['producto_descripcion'])}</p>
                <p style='margin:4px 0;'><strong>Fecha de inicio:</strong> {$fmt($c['fecha_inicio'])}</p>
                <p style='margin:4px 0;'><strong>Valor total:</strong> {$L($c['total_factura'])}</p>
                <p style='margin:4px 0;'><strong>Prima:</strong> {$L($c['prima'])}</p>
                <p style='margin:4px 0;'><strong>Monto financiado:</strong> {$L($c['monto_financiar'])}</p>
                <p style='margin:4px 0;'><strong>Interés:</strong> {$h(rtrim(rtrim(number_format((float)$c['porcentaje_interes'], 2), '0'), '.'))}%</p>
                <p style='margin:4px 0;'><strong>Plazo:</strong> {$h($c['plazo_meses'])} meses</p>
                <p style='margin:4px 0;'><strong>Entrega del préstamo:</strong> {$entregaDetalle}</p>
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
            </table>

            {$enlacePortal}
            <p>Por favor realiza tus pagos a tiempo para evitar cargos por mora.</p>
            <hr style='border:none; border-top:1px solid #e2e8f0; margin:24px 0;'>
            <p style='font-size:12px; color:#64748b;'>Este es un mensaje automático de <strong>{$h($empresa)}</strong>.</p>
        </div>";

        // 5. Envío (mismo SMTP que los recordatorios)
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
            $mail->addAddress($c['cliente_correo'], $c['cliente_nombre']);

            $mail->isHTML(true);
            $mail->Subject = "Plan de pagos - Contrato #{$c['id']} - {$empresa}";
            $mail->Body    = $body;
            $mail->AltBody = "Tu crédito (contrato #{$c['id']}) fue registrado. Total del crédito: " . $L($c['total_credito'])
                           . ". Consulta tu plan de pagos con {$empresa}.";
            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log("[plan_pagos] Contrato #{$contratoId}: error enviando correo: " . $mail->ErrorInfo . ' | ' . $e->getMessage());
            return false;
        }
    } catch (Throwable $e) {
        error_log("[plan_pagos] Contrato #{$contratoId}: " . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        return false;
    }
}