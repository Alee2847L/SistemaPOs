<?php
// api/procesar_recaudo.php
session_start();
header('Content-Type: application/json');
require_once '../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$codigo_bp = $input['codigo_bp'] ?? '';
$id_contrato = $input['id_contrato'] ?? 0;
$cuotas = $input['cuotas'] ?? []; // IDs de cuotas_contrato
$pagos = $input['pagos'] ?? [];
$usuario_id = $_SESSION['usuario_id'];

if (empty($codigo_bp) || empty($id_contrato) || empty($cuotas) || empty($pagos)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios para procesar el recaudo.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $total_abonado = 0;
    foreach ($pagos as $p) {
        $total_abonado += floatval($p['monto']);
    }

    // ID de respaldo o registro de cabecera si existe la tabla transacciones_recaudo
    $recaudo_id = rand(1000, 9999);
    if ($pdo->query("SHOW TABLES LIKE 'transacciones_recaudo'")->rowCount() > 0) {
        $stmtRecaudo = $pdo->prepare("INSERT INTO transacciones_recaudo (contrato_id, usuario_id, monto_total, fecha) VALUES (?, ?, ?, NOW())");
        $stmtRecaudo->execute([$id_contrato, $usuario_id, $total_abonado]);
        $recaudo_id = $pdo->lastInsertId();
    }

    // Actualizamos las cuotas en cuotas_contrato guardando también el usuario_id que realizó el cobro
    $placeholders = implode(',', array_fill(0, count($cuotas), '?'));
    
    $sqlCuotas = "UPDATE cuotas_contrato 
                  SET estado = 'PAGADO', 
                      monto_pagado = monto_cuota, 
                      fecha_pago = NOW(), 
                      usuario_id = ? 
                  WHERE id IN ($placeholders) AND contrato_id = ?";
    
    $params = [$usuario_id];
    foreach ($cuotas as $c_id) {
        $params[] = $c_id;
    }
    $params[] = $id_contrato;

    $stmtUpdateCuotas = $pdo->prepare($sqlCuotas);
    $stmtUpdateCuotas->execute($params);

    // NUEVO: Sumar el total abonado al límite de crédito del cliente usando codigo_bp
    $stmtUpdateLimite = $pdo->prepare("UPDATE clientes SET limite_credito = limite_credito + ? WHERE codigo_bp = ?");
    $stmtUpdateLimite->execute([$total_abonado, $codigo_bp]);

    // Verificar si quedan cuotas pendientes en cuotas_contrato para finalizar el contrato
    $stmtVerificar = $pdo->prepare("SELECT COUNT(*) FROM cuotas_contrato WHERE contrato_id = ? AND estado != 'PAGADO'");
    $stmtVerificar->execute([$id_contrato]);
    $cuotasPendientesRestantes = $stmtVerificar->fetchColumn();

    if ($cuotasPendientesRestantes == 0) {
        $stmtFinalizarContrato = $pdo->prepare("UPDATE contratos SET estado = 'FINALIZADO' WHERE id = ?");
        $stmtFinalizarContrato->execute([$id_contrato]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Recaudo procesado con éxito y límite de crédito actualizado',
        'recaudo_id' => $recaudo_id
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error en base de datos: ' . $e->getMessage()]);
}