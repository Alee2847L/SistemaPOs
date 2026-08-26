<?php
header('Content-Type: application/json');
require_once '../config/conexion.php';

// Capturamos el id_transaccion independientemente de cómo venga en la petición (GET)
$id_transaccion = $_GET['id_transaccion'] ?? $_GET['id'] ?? 0;

if (!$id_transaccion) {
    echo json_encode(['success' => false, 'message' => 'ID de transacción no válido']);
    exit;
}

try {
    // 1. Buscamos la transacción principal
    $stmt = $pdo->prepare("SELECT * FROM ventas WHERE id_transaccion = ?");
    $stmt->execute([$id_transaccion]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        echo json_encode(['success' => false, 'message' => 'Transacción no encontrada']);
        exit;
    }

    // 2. Obtenemos los detalles haciendo JOIN con productos usando 'codigo_barra'
    $stmtDetalles = $pdo->prepare("
        SELECT 
            dv.*, 
            p.nombre AS nombre_producto, 
            p.codigo_barra AS codigo, 
            p.stock AS stock 
        FROM detalle_ventas dv
        INNER JOIN productos p ON dv.producto_id = p.id
        WHERE dv.venta_id = ?
    ");
    $stmtDetalles->execute([$id_transaccion]);
    $venta['detalles'] = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $venta]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}