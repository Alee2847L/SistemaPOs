<?php
require_once '../config/conexion.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// --- 1. BÚSQUEDA RÁPIDA DE CLIENTES (AUTOCOMPLETAR) ---
if ($accion === 'buscar') {
    $q = trim($_GET['q'] ?? '');
    try {
        if (empty($q)) {
            // Se agregaron 'limite_credito' y 'estado' en el SELECT
            $stmt = $pdo->query("SELECT codigo_bp, tipo_cliente, rtn_dni, Nombre, limite_credito, estado FROM clientes ORDER BY codigo_bp ASC LIMIT 10");
        } else {
            // Se agregaron 'limite_credito' y 'estado' en el SELECT
            $stmt = $pdo->prepare("SELECT codigo_bp, tipo_cliente, rtn_dni, Nombre, limite_credito, estado FROM clientes 
                                  WHERE codigo_bp LIKE ? OR rtn_dni LIKE ? OR Nombre LIKE ? 
                                  ORDER BY codigo_bp ASC LIMIT 10");
            $term = "%$q%";
            $stmt->execute([$term, $term, $term]);
        }
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $clientes]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// --- 2. REGISTRO RÁPIDO DESDE EL POS (ACCESIBLE PARA CUALQUIER ROL) ---
if ($accion === 'crear_rapido') {
    $tipo_cliente = trim($_POST['tipo_cliente'] ?? 'natural');
    $rtn_dni      = trim($_POST['rtn_dni'] ?? '');
    $nombre       = trim($_POST['nombre'] ?? '');
    $telefono     = trim($_POST['telefono'] ?? '');

    if (empty($rtn_dni) || empty($nombre)) {
        echo json_encode(['success' => false, 'message' => 'El DNI/RTN y el Nombre son obligatorios']);
        exit;
    }

    try {
        // Generar Código BP Automático (ej. BP008)
        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(codigo_bp, 3) AS UNSIGNED)) as ultimo_num FROM clientes WHERE codigo_bp LIKE 'BP%'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $siguiente_num = ($row['ultimo_num'] ?? 0) + 1;
        $nuevo_codigo_bp = 'BP' . str_pad($siguiente_num, 3, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("INSERT INTO clientes (codigo_bp, tipo_cliente, rtn_dni, Nombre, Telefono, limite_credito, estado) VALUES (?, ?, ?, ?, ?, 0.00, 'ACT')");
        $stmt->execute([$nuevo_codigo_bp, $tipo_cliente, $rtn_dni, $nombre, $telefono]);

        echo json_encode([
            'success' => true, 
            'message' => 'Cliente creado con éxito',
            'data' => [
                'codigo_bp' => $nuevo_codigo_bp,
                'rtn_dni' => $rtn_dni,
                'Nombre' => $nombre,
                'tipo_cliente' => $tipo_cliente,
                'limite_credito' => 0.00,
                'estado' => 'ACT'
            ]
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['success' => false, 'message' => 'El DNI/RTN ya está registrado']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
        }
    }
    exit;
}
?>