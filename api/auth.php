<?php
session_start();
header('Content-Type: application/json');

require_once '../config/db.php'; // Ajusta la ruta a tu conexión si es necesario

// Recibir datos POST (o JSON)
$data = json_decode(file_get_contents("php://input"), true);
$usuario = $data['usuario'] ?? $_POST['usuario'] ?? '';
$password = $data['password'] ?? $_POST['password'] ?? '';

if (empty($usuario) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "Por favor, completa todos los campos."]);
    exit;
}

try {
    // Consulta para verificar el usuario en la base de datos central o principal
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = :usuario LIMIT 1");
    $stmt->execute(['usuario' => $usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        
        // Guardar datos básicos en la sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['usuario'] = $user['usuario'];
        $_SESSION['nombre'] = $user['nombre'] ?? '';

        // Consultar los módulos activos permitidos para este usuario/rol
        $stmtModulos = $pdo->prepare("SELECT modulo FROM permisos_usuario WHERE usuario_id = :id_usuario");
        $stmtModulos->execute(['id_usuario' => $user['id']]);
        $modulos = $stmtModulos->fetchAll(PDO::FETCH_COLUMN);

        // Guardar los módulos en la sesión para que el menú principal los reconozca
        $_SESSION['modulos_activos'] = $modulos;

        echo json_encode([
            "status" => "success", 
            "message" => "Inicio de sesión exitoso",
            "redirect" => "dashboard.php"
        ]);
    } else {
        echo json_encode([
            "status" => "error", 
            "message" => "Usuario o contraseña incorrectos."
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        "status" => "error", 
        "message" => "Error en el servidor: " . $e->getMessage()
    ]);
}
?>