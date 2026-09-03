<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "127.0.0.1";
$dbname = "pos_db_empresa1_0";
$user = "administrador";
$pass = "admin123";


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]));
}

// Función helper para verificar roles
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