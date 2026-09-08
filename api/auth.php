<?php
// Endurecer la seguridad de las cookies de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

session_start();
require_once '../config/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$accion = $_POST['accion'] ?? '';

// --- 1. INICIAR SESIÓN ---
if ($accion === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; // Sin trim para respetar espacios si los tuviera

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Por favor complete todos los campos']);
        exit;
    }

    // Buscamos al usuario por correo
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si el usuario no existe
    if (!$user) {
        usleep(500000); // Retardo para evitar enumeración de correos
        echo json_encode(['success' => false, 'message' => 'Correo o contraseña incorrectos']);
        exit;
    }

    // Validar si el usuario está inactivo
    if ((int)$user['estado'] === 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Usuario inactivo, comuníquese com su administrador.'
        ]);
        exit;
    }

    // Verificar contraseña
    if (password_verify($password, $user['password'])) {
        // Contraseña correcta: Reiniciamos los intentos fallidos a 0
        $stmtReset = $pdo->prepare("UPDATE usuarios SET intentos_fallidos = 0 WHERE id = ?");
        $stmtReset->execute([$user['id']]);

        // Prevenir fijación de sesión
        session_regenerate_id(true);

        $_SESSION['usuario_id']     = $user['id'];
        $_SESSION['usuario_nombre'] = $user['nombre'];
        $_SESSION['usuario_email']  = $user['email'];
        $_SESSION['usuario_rol']    = $user['rol'];

        echo json_encode(['success' => true, 'rol' => $user['rol']]);
    } else {
        // Contraseña incorrecta: Normalizamos si arrastraba un contador alto estando activo
        $intentosActuales = ((int)$user['intentos_fallidos'] >= 3) ? 0 : (int)$user['intentos_fallidos'];
        $nuevosIntentos = $intentosActuales + 1;

        if ($nuevosIntentos >= 3) {
            // Si llega a 3 intentos fallidos, bloqueamos la cuenta (estado = 0)
            $stmtBloquear = $pdo->prepare("UPDATE usuarios SET intentos_fallidos = ?, estado = 0 WHERE id = ?");
            $stmtBloquear->execute([$nuevosIntentos, $user['id']]);

            echo json_encode([
                'success' => false, 
                'message' => 'Usuario inactivo, comuníquese con su administrador.'
            ]);
        } else {
            // Aún tiene intentos disponibles
            $stmtActualizarIntentos = $pdo->prepare("UPDATE usuarios SET intentos_fallidos = ? WHERE id = ?");
            $stmtActualizarIntentos->execute([$nuevosIntentos, $user['id']]);

            $intentosRestantes = 3 - $nuevosIntentos;
            echo json_encode([
                'success' => false, 
                'message' => "Correo o contraseña incorrectos. Te quedan $intentosRestantes intento(s) antes de que la cuenta sea desactivada."
            ]);
        }
        
        usleep(500000); // Retardo artificial anti-fuerza bruta
    }
    exit;
}

// --- 2. SOLICITAR RECUPERACIÓN DE CONTRASEÑA ---
if ($accion === 'solicitar_recuperacion') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Por favor ingrese un correo electrónico']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE email = ? AND estado = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Por seguridad, respondemos genéricamente aunque el correo no exista (evita filtración de usuarios)
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmtUpdate = $pdo->prepare("UPDATE usuarios SET token_recuperacion = ?, token_expiracion = ? WHERE id = ?");
        $stmtUpdate->execute([$token, $expiracion, $user['id']]);

        $enlace = "http://bryanmayorga.great-site.net/views/reset-password.php?token=" . $token;

        // --- CONFIGURACIÓN DE PHPMailer CON MICROSOFT 365 SMTP (USANDO .ENV) ---
        require '../phpmailer/Exception.php';
        require '../phpmailer/PHPMailer.php';
        require '../phpmailer/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $_ENV['MAIL_HOST'] ?? 'smtp.office365.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USER'] ?? '';         
            $mail->Password   = $_ENV['MAIL_PASS'] ?? ''; 
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $_ENV['MAIL_PORT'] ?? 587;

            // Remitente utilizando tu correo corporativo
            $mail->setFrom($_ENV['MAIL_FROM'] ?? 'inversionesja@misistemapos.com', $_ENV['MAIL_NAME'] ?? 'Sistema POS');
            $mail->addAddress($email, $user['nombre']);

            // Contenido del correo
            $mail->isHTML(true);
            $mail->Subject = 'Recuperar Contrasena - Sistema POS';
            $mail->Body    = "Hola <b>{$user['nombre']}</b>, <br><br> Has solicitado restablecer tu contrasena. Haz clic en el siguiente enlace para continuar (expira en 1 hora): <br><br><a href='$enlace'>$enlace</a>";

            $mail->send();
        } catch (Exception $e) {
            // Para depurar (luego puedes registrarlo en un log o archivo)
            echo json_encode(['success' => false, 'message' => 'Error al enviar correo: ' . $mail->ErrorInfo]);
            exit;
        }
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Si el correo está registrado, se han enviado las instrucciones a su bandeja.'
    ]);
    exit;
}

// --- 3. ACTUALIZAR CONTRASEÑA TRAS RECUPERACIÓN ---
if ($accion === 'actualizar_password') {
    $token = $_POST['token'] ?? '';
    $nuevoPassword = $_POST['password'] ?? '';

    if (empty($token) || empty($nuevoPassword)) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    // Verificar que el token sea válido y no haya expirado
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE token_recuperacion = ? AND token_expiracion > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'El enlace de recuperación es inválido o ha expirado.']);
        exit;
    }

    $passwordHash = password_hash($nuevoPassword, PASSWORD_DEFAULT);

    // Actualizar contraseña, limpiar tokens, resetear intentos y reactivar el estado
    $stmtUpdate = $pdo->prepare("UPDATE usuarios SET password = ?, token_recuperacion = NULL, token_expiracion = NULL, intentos_fallidos = 0, estado = 1 WHERE id = ?");
    $stmtUpdate->execute([$passwordHash, $user['id']]);

    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada con éxito. Ya puedes iniciar sesión.']);
    exit;
}

// --- 4. CERRAR SESIÓN (LOGOUT) ---
if ($accion === 'logout') {
    session_unset();
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}
?>