<?php
// api/enviar_comprobante_venta_endpoint.php
//
// Envía por correo el comprobante de una venta (y el plan de pagos, si es un
// crédito nuevo). Se llama de forma independiente ("fire and forget": el
// navegador NO espera su respuesta) justo después de que el POS confirma que
// la venta ya se guardó. Así, la pantalla nunca depende de cuánto tarde el
// correo en salir.
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$ventaId = intval($_GET['venta_id'] ?? $_POST['venta_id'] ?? 0);
if ($ventaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'venta_id inválido']);
    exit;
}
$contratoIdRaw = $_GET['contrato_id'] ?? $_POST['contrato_id'] ?? null;
$contratoId = ($contratoIdRaw !== null && $contratoIdRaw !== '') ? intval($contratoIdRaw) : null;

require_once __DIR__ . '/enviar_comprobante_venta.php';
$enviado = enviarComprobanteVentaPorCorreo($pdo, $ventaId, $contratoId);
echo json_encode(['success' => $enviado]);