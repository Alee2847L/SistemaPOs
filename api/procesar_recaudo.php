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

    // 1. Crear el registro maestro del Recibo Global
    $stmtRecaudo = $pdo->prepare("INSERT INTO transacciones_recaudo (contrato_id, usuario_id, monto_total, fecha) VALUES (?, ?, ?, NOW())");
    $stmtRecaudo->execute([$id_contrato, $usuario_id, $total_abonado]);
    $recaudo_id = $pdo->lastInsertId();

    // 2. Actualizar cada cuota seleccionada consultando su monto real de la base de datos y vinculándola al recaudo_id
    foreach ($cuotas as $c_id) {
        // Consultar el monto oficial de esta cuota específica
        $stmtMontoCuota = $pdo->prepare("SELECT monto_cuota FROM cuotas_contrato WHERE id = ?");
        $stmtMontoCuota->execute([$c_id]);
        $montoCuotaActual = $stmtMontoCuota->fetchColumn() ?: 0;

        $stmtUpdateCuota = $pdo->prepare("
            UPDATE cuotas_contrato 
            SET estado = 'PAGADO', 
                monto_pagado = ?, 
                fecha_pago = NOW(), 
                usuario_id = ?,
                recaudo_id = ?
            WHERE id = ? AND contrato_id = ?
        ");
        $stmtUpdateCuota->execute([$montoCuotaActual, $usuario_id, $recaudo_id, $c_id, $id_contrato]);
    }

    // 3. Sumar el total abonado al límite de crédito del cliente
    $stmtUpdateLimite = $pdo->prepare("UPDATE clientes SET limite_credito = limite_credito + ? WHERE codigo_bp = ?");
    $stmtUpdateLimite->execute([$total_abonado, $codigo_bp]);

    // 4. Verificar si el contrato quedó totalmente pagado
    $stmtVerificar = $pdo->prepare("SELECT COUNT(*) FROM cuotas_contrato WHERE contrato_id = ? AND estado != 'PAGADO'");
    $stmtVerificar->execute([$id_contrato]);
    if ($stmtVerificar->fetchColumn() == 0) {
        $stmtFinalizarContrato = $pdo->prepare("UPDATE contratos SET estado = 'FINALIZADO' WHERE id = ?");
        $stmtFinalizarContrato->execute([$id_contrato]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Recaudo procesado con éxito',
        'recaudo_id' => $recaudo_id
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error en base de datos: ' . $e->getMessage()]);
}