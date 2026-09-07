<?php
// api/recaudo.php
session_start();
header('Content-Type: application/json');
require_once '../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$accion = $_GET['accion'] ?? '';

if ($accion === 'listar_contratos') {
    $codigo_bp = $_GET['codigo_bp'] ?? '';
    
    if (empty($codigo_bp)) {
        echo json_encode(['success' => false, 'data' => []]);
        exit;
    }

    try {
        // Incluimos el campo 'estado' en la consulta para que el frontend pueda filtrar correctamente
        $stmt = $pdo->prepare("SELECT id AS id_contrato, producto_descripcion, total_credito, plazo_meses, monto_financiar, estado, (total_credito / NULLIF(plazo_meses, 0)) AS cuota_mensual FROM contratos WHERE codigo_bp = ?");
        $stmt->execute([$codigo_bp]);
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalizamos el estado a minúsculas para asegurar consistencia
        foreach ($contratos as &$cont) {
            $cont['estado'] = strtolower($cont['estado'] ?? 'activo');
        }

        echo json_encode(['success' => true, 'data' => $contratos]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} 
elseif ($accion === 'listar_cuotas') {
    $id_contrato = $_GET['id_contrato'] ?? 0;

    if (empty($id_contrato)) {
        echo json_encode(['success' => false, 'data' => []]);
        exit;
    }

    try {
        // Consultamos las cuotas de la tabla cuotas_contrato
        $stmt = $pdo->prepare("SELECT id AS id_cuota, numero_cuota, monto_cuota AS monto, fecha_vencimiento, estado FROM cuotas_contrato WHERE contrato_id = ? ORDER BY numero_cuota ASC");
        $stmt->execute([$id_contrato]);
        $cuotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cuotas as &$c) {
            $c['estado'] = strtolower($c['estado']);
        }

        echo json_encode(['success' => true, 'data' => $cuotas]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}