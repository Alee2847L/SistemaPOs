<?php
// api/enviar_plan_pagos_endpoint.php
//
// Envía por correo el plan de pagos de un contrato. Se llama de forma
// independiente ("fire and forget": el navegador NO espera su respuesta)
// justo después de crear un contrato sin prima, o justo después de cobrar
// una prima pendiente en el POS. Así, la pantalla nunca depende de cuánto
// tarde el correo en salir (SMTP lento, internet lento, etc.) — el contrato
// o la venta ya quedaron guardados en la base de datos antes de llegar aquí.
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$contratoId = intval($_GET['contrato_id'] ?? $_POST['contrato_id'] ?? 0);
if ($contratoId <= 0) {
    echo json_encode(['success' => false, 'message' => 'contrato_id inválido']);
    exit;
}

require_once __DIR__ . '/enviar_plan_pagos.php';
$enviado = enviarPlanPagosPorCorreo($pdo, $contratoId);
echo json_encode(['success' => $enviado]);