<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Credenciales de tu Servidor MySQL y Base de Datos CENTRAL
$db_host = "localhost";
$db_user = "admin";
$db_pass = "admin123";
$db_central = "pos_central"; // <--- Asegúrate que este sea el nombre de tu BD central en phpMyAdmin

try {
    // 2. Conexión a la BD Central para validar el subdominio
    $pdo_central = new PDO("mysql:host=$db_host;dbname=$db_central;charset=utf8mb4", $db_user, $db_pass);
    $pdo_central->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 3. Capturar y limpiar el subdominio desde la URL
    $host = $_SERVER['HTTP_HOST'];
    $host = explode(':', $host)[0]; // Eliminar puerto si estás en local (ej: localhost:8080)
    $partes = explode('.', $host);
    
    $subdominio = null;
    if (count($partes) >= 3) {
        $subdominio = $partes[0];
    }

    // Si entran al dominio principal sin subdominio (ej: tu-pos.com)
    if (!$subdominio || $subdominio === 'www' || $subdominio === 'localhost') {
        // Redirigir a tu landing page de ventas o mostrar mensaje general
        die("Bienvenido a la plataforma POS. Por favor, ingrese mediante el subdominio de su empresa.");
    }

    // 4. Buscar el cliente en la tabla central
    $stmt = $pdo_central->prepare("SELECT * FROM clientes WHERE subdominio = ? LIMIT 1");
    $stmt->execute([$subdominio]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        http_response_code(404);
        die("<h1>404</h1><p>El subdominio especificado no se encuentra registrado.</p>");
    }

    // 5. Validar el estado del cliente (activo / suspendido)
    if ($cliente['estado'] !== 'activo') {
        http_response_code(403);
        die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>
                <h2 style='color: #d9534f;'>Cuenta Suspendida</h2>
                <p>El acceso a este sistema ha sido suspendido temporalmente por falta de pago.</p>
                <p>Comuníquese con el administrador del servicio.</p>
             </div>");
    }

    // 6. Conexión dinámica a la Base de Datos específica del cliente (ej: misistemaposInversionesJA)
    $dbname = $cliente['nombre_bd'];
    
    $pdo = new PDO("mysql:host=$db_host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Error crítico de conexión: ' . $e->getMessage()]));
}

// Función helper para verificar roles (se mantiene intacta para tu sistema)
function verificarAcceso($rolRequerido = null) {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ../views/login.php');
        exit;
    }
    if ($rolRequerido && $_SESSION['usuario_rol'] !== $rolRequerido) {
        http_response_code(403);
        echo "Acceso denegado: Se requieren permisos de " . $rolRequerido;
        exit;
    }
}
?>