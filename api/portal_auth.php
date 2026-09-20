<?php
// api/portal_auth.php  — Login de CLIENTES por código (OTP) al correo
session_start();
header('Content-Type: application/json; charset=utf-8');

// Nunca imprimir errores de PHP en la respuesta (rompen el JSON); se registran en el log.
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_exception_handler(function (Throwable $e) {
    error_log('[portal_auth] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
    exit;
});

require_once __DIR__ . '/../config/conexion.php';

const OTP_MINUTOS            = 10;  // vigencia del código
const OTP_MAX_INTENTOS       = 5;   // intentos de verificación por código
const OTP_MAX_POR_CLIENTE_H  = 5;   // códigos por cliente por hora
const OTP_MAX_POR_IP_H       = 15;  // códigos por IP por hora

function responder(bool $ok, string $msg, array $extra = []): void {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

function hashCodigo(string $codigo, string $bp): string {
    return hash('sha256', $codigo . '|' . $bp);
}

/** Carga el .env (mismo archivo y formato que usa enviar_recordatorios_cuotas.php) si aún no está cargado. */
function cargarEnvSiHaceFalta(): void {
    if (!empty($_ENV['MAIL_HOST']) || !empty($_ENV['MAIL_USER'])) return;
    $envPath = __DIR__ . '/../../.env';
    if (!file_exists($envPath)) return;
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

/** Envía el código por SMTP (PHPMailer + Brevo), igual que los recordatorios de cuotas. */
function enviarCodigoPorCorreo(string $email, string $nombre, string $codigo): bool {
    cargarEnvSiHaceFalta();
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

        $mail->setFrom($_ENV['MAIL_FROM'] ?? 'noreply@misistemapos.com', $_ENV['MAIL_NAME'] ?? 'Portal de Clientes');
        $mail->addAddress($email, $nombre);

        $mail->isHTML(true);
        $mail->Subject = 'Tu código de acceso: ' . $codigo;
        $nombreSeguro  = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #4f46e5;'>Código de acceso</h2>
                <p>Hola <strong>{$nombreSeguro}</strong>,</p>
                <p>Usa este código para ingresar al portal de clientes:</p>
                <p style='font-size: 32px; letter-spacing: 8px; font-weight: bold; color: #4f46e5; text-align: center;
                          background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;'>{$codigo}</p>
                <p>Vence en " . OTP_MINUTOS . " minutos. Si no lo solicitaste, ignora este mensaje.</p>
            </div>";
        $mail->AltBody = "Tu código de acceso es: $codigo (vence en " . OTP_MINUTOS . " minutos).";

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('[portal_auth] Error enviando correo: ' . $mail->ErrorInfo . ' | ' . $e->getMessage());
        return false;
    }
}

function buscarCliente(PDO $pdo, string $identificador): ?array {
    // Acepta DNI/RTN (con o sin guiones/espacios) o correo. Solo clientes activos con correo.
    $limpio = str_replace(['-', ' '], '', $identificador);
    $stmt = $pdo->prepare(
        "SELECT codigo_bp, Nombre AS nombre, Correo AS email
           FROM clientes
          WHERE estado = 'ACT'
            AND Correo IS NOT NULL AND Correo <> ''
            AND (rtn_dni = ? OR REPLACE(REPLACE(rtn_dni, '-', ''), ' ', '') = ? OR Correo = ?)
          LIMIT 2"
    );
    $stmt->execute([$identificador, $limpio, $identificador]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Si el dato coincide con más de un cliente (p. ej. correo compartido) no es seguro adivinar: se rechaza.
    return count($filas) === 1 ? $filas[0] : null;
}

$accion = $_POST['accion'] ?? '';
$ip     = $_SERVER['REMOTE_ADDR'] ?? '';

// ---------------------------------------------------------------
// 1) SOLICITAR CÓDIGO
// ---------------------------------------------------------------
if ($accion === 'solicitar_codigo') {
    $identificador = trim($_POST['identificador'] ?? '');
    if ($identificador === '') responder(false, 'Ingresa tu número de identidad o correo.');

    // Mensaje idéntico exista o no el cliente (evita enumerar clientes)
    $generico = 'Si los datos son correctos, enviamos un código al correo registrado.';

    $cliente = buscarCliente($pdo, $identificador);
    if (!$cliente) {
        error_log('[portal_auth] solicitar_codigo: no hay un cliente activo con correo que coincida con el dato ingresado.');
        responder(true, $generico);
    }

    // Límites de frecuencia
    $q = $pdo->prepare("SELECT COUNT(*) FROM cliente_otp WHERE codigo_bp = ? AND creado_en > (NOW() - INTERVAL 1 HOUR)");
    $q->execute([$cliente['codigo_bp']]);
    $porCliente = (int)$q->fetchColumn();

    $q = $pdo->prepare("SELECT COUNT(*) FROM cliente_otp WHERE ip = ? AND creado_en > (NOW() - INTERVAL 1 HOUR)");
    $q->execute([$ip]);
    $porIp = (int)$q->fetchColumn();

    if ($porCliente >= OTP_MAX_POR_CLIENTE_H || $porIp >= OTP_MAX_POR_IP_H) {
        responder(false, 'Demasiadas solicitudes. Intenta de nuevo más tarde.');
    }

    // Invalida códigos anteriores y crea uno nuevo
    $pdo->prepare("UPDATE cliente_otp SET usado = 1 WHERE codigo_bp = ? AND usado = 0")
        ->execute([$cliente['codigo_bp']]);

    $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $pdo->prepare(
        "INSERT INTO cliente_otp (codigo_bp, codigo_hash, expira_en, ip)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL " . OTP_MINUTOS . " MINUTE), ?)"
    )->execute([$cliente['codigo_bp'], hashCodigo($codigo, $cliente['codigo_bp']), $ip]);

    enviarCodigoPorCorreo($cliente['email'], $cliente['nombre'] ?? 'cliente', $codigo);

    responder(true, $generico);
}

// ---------------------------------------------------------------
// 2) VERIFICAR CÓDIGO
// ---------------------------------------------------------------
if ($accion === 'verificar_codigo') {
    $identificador = trim($_POST['identificador'] ?? '');
    $codigo        = trim($_POST['codigo'] ?? '');
    $error         = 'Código inválido o vencido.';

    if ($identificador === '' || !preg_match('/^\d{6}$/', $codigo)) responder(false, $error);

    $cliente = buscarCliente($pdo, $identificador);
    if (!$cliente) responder(false, $error);

    $stmt = $pdo->prepare(
        "SELECT id, codigo_hash, intentos
           FROM cliente_otp
          WHERE codigo_bp = ? AND usado = 0 AND expira_en > NOW()
          ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$cliente['codigo_bp']]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$otp) responder(false, $error);

    if ((int)$otp['intentos'] >= OTP_MAX_INTENTOS) {
        $pdo->prepare("UPDATE cliente_otp SET usado = 1 WHERE id = ?")->execute([$otp['id']]);
        responder(false, 'Demasiados intentos. Solicita un nuevo código.');
    }

    $pdo->prepare("UPDATE cliente_otp SET intentos = intentos + 1 WHERE id = ?")->execute([$otp['id']]);

    if (!hash_equals($otp['codigo_hash'], hashCodigo($codigo, $cliente['codigo_bp']))) {
        responder(false, $error);
    }

    // Código correcto: se consume (un solo uso)
    $pdo->prepare("UPDATE cliente_otp SET usado = 1 WHERE id = ?")->execute([$otp['id']]);

    session_regenerate_id(true);
    // Claves DISTINTAS a las del personal: 'usuario_id' NO se toca,
    // así un cliente jamás pasa las validaciones del sistema interno.
    $_SESSION['cliente_id']     = $cliente['codigo_bp'];
    $_SESSION['cliente_nombre'] = $cliente['nombre'] ?? '';
    $_SESSION['cliente_ultima_actividad'] = time();

    responder(true, 'Acceso concedido.');
}

// ---------------------------------------------------------------
// 3) CERRAR SESIÓN DEL CLIENTE
// ---------------------------------------------------------------
if ($accion === 'logout') {
    unset($_SESSION['cliente_id'], $_SESSION['cliente_nombre'], $_SESSION['cliente_ultima_actividad']);
    responder(true, 'Sesión cerrada.');
}

responder(false, 'Acción no válida.');