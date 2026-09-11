<?php
// api/proveedores.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../config/conexion.php';

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {
    case 'listar':
        try {
            $stmt = $pdo->query("SELECT * FROM proveedores ORDER BY nombre_empresa ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'obtener':
        $id = $_GET['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'guardar':
        $id = $_POST['id'] ?? '';
        $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
        $contacto = trim($_POST['contacto'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $rtn = trim($_POST['rtn'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        if (empty($nombre_empresa)) {
            echo json_encode(['success' => false, 'message' => 'El nombre de la empresa es obligatorio.']);
            exit;
        }

        try {
            if (empty($id)) {
                $stmt = $pdo->prepare("INSERT INTO proveedores (nombre_empresa, contacto, telefono, correo, rtn, direccion) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre_empresa, $contacto, $telefono, $correo, $rtn, $direccion]);
            } else {
                $stmt = $pdo->prepare("UPDATE proveedores SET nombre_empresa = ?, contacto = ?, telefono = ?, correo = ?, rtn = ?, direccion = ? WHERE id = ?");
                $stmt->execute([$nombre_empresa, $contacto, $telefono, $correo, $rtn, $direccion, $id]);
            }
            echo json_encode(['success' => true, 'message' => 'Proveedor guardado correctamente.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'eliminar':
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM proveedores WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Proveedor eliminado.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar porque tiene registros asociados.']);
        }
        break;

    case 'productos_proveedor':
        $proveedor_id = $_GET['proveedor_id'] ?? 0;
        try {
            $stmt = $pdo->prepare("
                SELECT pp.id, p.codigo_barra, p.nombre, pp.precio 
                FROM producto_proveedor pp 
                JOIN productos p ON pp.producto_id = p.id 
                WHERE pp.proveedor_id = ?
            ");
            $stmt->execute([$proveedor_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'vincular_producto':
        $proveedor_id = $_POST['proveedor_id'] ?? 0;
        $producto_id = $_POST['producto_id'] ?? 0;
        $precio = $_POST['precio'] ?? 0;

        try {
            // Verificar si ya existe la relación
            $stmtCheck = $pdo->prepare("SELECT id FROM producto_proveedor WHERE producto_id = ? AND proveedor_id = ?");
            $stmtCheck->execute([$producto_id, $proveedor_id]);
            if ($stmtCheck->fetch()) {
                $stmtUpd = $pdo->prepare("UPDATE producto_proveedor SET precio = ? WHERE producto_id = ? AND proveedor_id = ?");
                $stmtUpd->execute([$precio, $producto_id, $proveedor_id]);
            } else {
                $stmtIns = $pdo->prepare("INSERT INTO producto_proveedor (producto_id, proveedor_id, precio) VALUES (?, ?, ?)");
                $stmtIns->execute([$producto_id, $proveedor_id, $precio]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'desvincular_producto':
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM producto_proveedor WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}