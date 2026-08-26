<?php
// api/usuarios.php
session_start();
require_once '../config/conexion.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {
    case 'listar':
        try {
            $stmt = $pdo->query("SELECT id, nombre, email, rol, estado, fecha_creacion FROM usuarios ORDER BY id DESC");
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $usuarios]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al listar: ' . $e->getMessage()]);
        }
        break;

    case 'obtener':
        $id = $_GET['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("SELECT id, nombre, email, rol, estado FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario) {
                echo json_encode(['success' => true, 'data' => $usuario]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al obtener: ' . $e->getMessage()]);
        }
        break;

    case 'guardar':
        $id = $_POST['id'] ?? '';
        $nombre = $_POST['nombre'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $rol = $_POST['rol'] ?? 'vendedor';
        $estado = $_POST['estado'] ?? 1;

        if (empty($nombre) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'El nombre y el email son obligatorios']);
            exit;
        }

        try {
            if (empty($id)) {
                // Insertar nuevo usuario
                if (empty($password)) {
                    echo json_encode(['success' => false, 'message' => 'La contraseña es obligatoria para nuevos usuarios']);
                    exit;
                }
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $email, $passwordHash, $rol, $estado]);
                
                echo json_encode(['success' => true, 'message' => 'Usuario creado correctamente']);
            } else {
                // Actualizar usuario existente
                if (!empty($password)) {
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, password = ?, rol = ?, estado = ? WHERE id = ?");
                    $stmt->execute([$nombre, $email, $passwordHash, $rol, $estado, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, estado = ? WHERE id = ?");
                    $stmt->execute([$nombre, $email, $rol, $estado, $id]);
                }
                
                echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
        }
        break;

    case 'eliminar':
        $id = $_POST['id'] ?? 0;
        
        // Evitar que el usuario activo se elimine a sí mismo accidentalmente
        if ($id == $_SESSION['usuario_id']) {
            echo json_encode(['success' => false, 'message' => 'No puedes eliminar tu propio usuario activo']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}