<?php
// api/enviar_recordatorios_cuotas.php
// Script para enviar recordatorios de cuotas (compatible con multi-empresa)

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cargar .env
$envPath = __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

require __DIR__ . '/../phpmailer/Exception.php';
require __DIR__ . '/../phpmailer/PHPMailer.php';
require __DIR__ . '/../phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Conexión a la base de datos CENTRAL
try {
    $pdoCentral = new PDO(
        "mysql:host=" . ($_ENV['DB_CENTRAL_HOST'] ?? 'localhost') . ";dbname=" . ($_ENV['DB_CENTRAL_NAME'] ?? 'pos_central') . ";charset=utf8mb4",
        $_ENV['DB_CENTRAL_USER'] ?? 'admin',
        $_ENV['DB_CENTRAL_PASS'] ?? 'admin123',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("Error conectando a pos_central: " . $e->getMessage() . "\n");
}

// Obtener todas las empresas activas
$stmtEmpresas = $pdoCentral->query("SELECT nombre_bd, nombre_empresa FROM clientes WHERE estado = 'activo'");
$empresas = $stmtEmpresas->fetchAll(PDO::FETCH_ASSOC);

if (empty($empresas)) {
    echo "No hay empresas activas.\n";
    exit;
}

$manana = date('Y-m-d', strtotime('+1 day'));
$totalEnviados = 0;
$totalErrores = 0;

foreach ($empresas as $empresa) {
    $nombreBd = $empresa['nombre_bd'];
    $nombreEmpresa = $empresa['nombre_empresa'] ?: 'Sistema POS';

    echo "Procesando empresa: {$nombreEmpresa} ({$nombreBd})...\n";

    try {
        // Conectar a la base de datos de la empresa
        $pdo = new PDO(
            "mysql:host=localhost;dbname={$nombreBd};charset=utf8mb4",
            $_ENV['DB_CENTRAL_USER'] ?? 'admin',
            $_ENV['DB_CENTRAL_PASS'] ?? 'admin123',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Buscar cuotas que vencen mañana
        $stmt = $pdo->prepare("
            SELECT 
                cu.id as cuota_id,
                cu.numero_cuota,
                cu.monto_cuota,
                cu.fecha_vencimiento,
                c.id as contrato_id,
                c.codigo_bp,
                c.producto_descripcion,
                cl.Nombre as cliente_nombre,
                cl.Correo as cliente_correo
            FROM cuotas_contrato cu
            INNER JOIN contratos c ON cu.contrato_id = c.id
            INNER JOIN clientes cl ON c.codigo_bp = cl.codigo_bp
            WHERE cu.fecha_vencimiento = ?
              AND cu.estado = 'PENDIENTE'
              AND cl.Correo IS NOT NULL 
              AND cl.Correo != ''
              AND c.estado = 'ACTIVO'
        ");
        $stmt->execute([$manana]);
        $cuotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cuotas)) {
            echo "  → No hay cuotas para mañana.\n";
            continue;
        }

        foreach ($cuotas as $cuota) {
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = $_ENV['MAIL_HOST'] ?? 'smtp-relay.brevo.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = $_ENV['MAIL_USER'] ?? '';
                $mail->Password   = $_ENV['MAIL_PASS'] ?? '';
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $_ENV['MAIL_PORT'] ?? 587;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom(
                    $_ENV['MAIL_FROM'] ?? 'noreply@misistemapos.com',
                    $_ENV['MAIL_NAME'] ?? $nombreEmpresa
                );

                $mail->addAddress($cuota['cliente_correo'], $cuota['cliente_nombre']);

                $mail->isHTML(true);
                $mail->Subject = "Recordatorio de pago - Cuota #{$cuota['numero_cuota']} - {$nombreEmpresa}";

                $montoFormateado = number_format($cuota['monto_cuota'], 2);
                $fechaFormateada = date('d/m/Y', strtotime($cuota['fecha_vencimiento']));

                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <h2 style='color: #4f46e5;'>Recordatorio de Pago</h2>
                        <p>Hola <strong>{$cuota['cliente_nombre']}</strong>,</p>
                        <p>Te recordamos que <strong>mañana ({$fechaFormateada})</strong> vence tu cuota:</p>
                        
                        <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0;'>
                            <p style='margin: 5px 0;'><strong>Empresa:</strong> {$nombreEmpresa}</p>
                            <p style='margin: 5px 0;'><strong>Contrato:</strong> #{$cuota['contrato_id']}</p>
                            <p style='margin: 5px 0;'><strong>Concepto:</strong> {$cuota['producto_descripcion']}</p>
                            <p style='margin: 5px 0;'><strong>Cuota N°:</strong> {$cuota['numero_cuota']}</p>
                            <p style='margin: 5px 0; font-size: 18px; color: #4f46e5;'>
                                <strong>Monto a pagar: L. {$montoFormateado}</strong>
                            </p>
                            <p style='margin: 5px 0;'><strong>Fecha de vencimiento:</strong> {$fechaFormateada}</p>
                        </div>

                        <p>Por favor realiza tu pago a tiempo para evitar cargos por mora.</p>
                        <p>Si ya realizaste el pago, puedes ignorar este mensaje.</p>
                        
                        <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
                        <p style='font-size: 12px; color: #64748b;'>
                            Este es un mensaje automático de <strong>{$nombreEmpresa}</strong>.
                        </p>
                    </div>
                ";

                $mail->send();
                $totalEnviados++;
                echo "  → Correo enviado a: {$cuota['cliente_correo']}\n";

            } catch (Exception $e) {
                $totalErrores++;
                echo "  → Error enviando a {$cuota['cliente_correo']}: {$mail->ErrorInfo}\n";
            }
        }

    } catch (Exception $e) {
        echo "  → Error en empresa {$nombreBd}: " . $e->getMessage() . "\n";
    }
}

echo "\n=== RESUMEN ===\n";
echo "Correos enviados: {$totalEnviados}\n";
echo "Errores: {$totalErrores}\n";