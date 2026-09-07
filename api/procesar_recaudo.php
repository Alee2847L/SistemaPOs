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
$pagos = $input['pagos'] ?? []; // Detalle de montos por método de pago (Efectivo / Tarjeta)
$usuario_id = $_SESSION['usuario_id'];

if (empty($codigo_bp) || empty($id_contrato) || empty($cuotas) || empty($pagos)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios para procesar el recaudo.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Calcular montos por método de pago recibidos desde el frontend
    $montoEfectivo = 0;
    $montoTarjeta = 0;

    foreach ($pagos as $p) {
        $metodo = strtoupper(trim($p['metodo'] ?? 'EFECTIVO'));
        $monto = floatval($p['monto'] ?? 0);

        if ($metodo === 'EFECTIVO') {
            $montoEfectivo += $monto;
        } elseif ($metodo === 'TARJETA') {
            $montoTarjeta += $monto;
        }
    }

    $total_abonado = $montoEfectivo + $montoTarjeta;

    if ($total_abonado <= 0) {
        throw new Exception("El monto total del recaudo debe ser mayor a cero.");
    }

    // Determinar etiqueta general de tipo_pago
    if ($montoEfectivo > 0 && $montoTarjeta > 0) {
        $tipoPago = 'AMBOS';
    } elseif ($montoTarjeta > 0) {
        $tipoPago = 'TARJETA';
    } else {
        $tipoPago = 'EFECTIVO';
    }

    // 2. Crear el registro maestro del Recibo Global incluyendo los campos de desglose
    $stmtRecaudo = $pdo->prepare("
        INSERT INTO transacciones_recaudo (contrato_id, usuario_id, monto_total, tipo_pago, monto_efectivo, monto_tarjeta, fecha) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmtRecaudo->execute([$id_contrato, $usuario_id, $total_abonado, $tipoPago, $montoEfectivo, $montoTarjeta]);
    $recaudo_id = $pdo->lastInsertId();

    // 3. Actualizar cada cuota seleccionada consultando su monto real de la base de datos y vinculándola al recaudo_id
    foreach ($cuotas as $c_id) {
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

    // 4. Sumar el total abonado al límite de crédito del cliente
    $stmtUpdateLimite = $pdo->prepare("UPDATE clientes SET limite_credito = limite_credito + ? WHERE codigo_bp = ?");
    $stmtUpdateLimite->execute([$total_abonado, $codigo_bp]);

    // 5. Verificar si el contrato quedó totalmente pagado
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
?>