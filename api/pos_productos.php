<?php
session_start();
require_once '../config/conexion.php';
header('Content-Type: application/json; charset=utf-8');

// Desactivar despliegue de errores PHP para no alterar el formato JSON
error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Capturar la acción enviada tanto por GET como por POST
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// --- 1. ACCIÓN: VALIDAR CLAVE DE DESCUENTO (POST) ---
if ($accion === 'validar_clave_descuento') {
    $claveIngresada = trim($_POST['clave'] ?? '');

    if (empty($claveIngresada)) {
        echo json_encode(['success' => false, 'message' => 'Ingrese una clave']);
        exit;
    }

    try {
        // Consultar contraseñas de usuarios administradores o supervisores
        $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE rol IN ('admin', 'supervisor') AND estado = 1");
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $autorizado = false;

        foreach ($usuarios as $usr) {
            $passGuardada = $usr['password'];

            // Comprueba si coincide el hash Bcrypt O en texto plano
            if (password_verify($claveIngresada, $passGuardada) || $claveIngresada === $passGuardada) {
                $autorizado = true;
                break;
            }
        }

        if ($autorizado) {
            echo json_encode(['success' => true, 'message' => 'Autorizado']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Clave incorrecta']);
        }

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $e->getMessage()]);
    }
    exit;
}

// --- 2. ACCIÓN: BÚSQUEDA DE PRODUCTOS (GET) ---
if ($accion === 'buscar') {
    $q = trim($_GET['q'] ?? '');
    try {
        if (!empty($q)) {
            // Se mantienen los alias para compatibilidad con la vista POS
            $stmt = $pdo->prepare("SELECT id, 
                                          codigo_barra AS codigo, 
                                          nombre, 
                                          precio_venta AS precio, 
                                          stock 
                                   FROM productos 
                                   WHERE codigo_barra LIKE ? OR nombre LIKE ? 
                                   LIMIT 10");
            $term = "%$q%";
            $stmt->execute([$term, $term]);
        } else {
            $stmt = $pdo->query("SELECT id, 
                                        codigo_barra AS codigo, 
                                        nombre, 
                                        precio_venta AS precio, 
                                        stock 
                                 FROM productos 
                                 LIMIT 10");
        }
        
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $productos]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $e->getMessage()]);
    }
    exit;
}

// Si la acción enviada no es reconocida
echo json_encode(['success' => false, 'message' => 'Acción no válida']);
exit;
?>