<?php
// api/portal.php — Datos del cliente autenticado (SOLO lectura)
session_start();
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ALL);
set_exception_handler(function (Throwable $e) {
    error_log('[portal] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
    exit;
});

require_once __DIR__ . '/../config/conexion.php';

const SESION_MAX_INACTIVIDAD = 1800; // 30 min

function responder(bool $ok, string $msg, array $extra = [], int $http = 200): void {
    http_response_code($http);
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

// --- Sesión de cliente obligatoria ---
if (empty($_SESSION['cliente_id'])) {
    responder(false, 'No autorizado', [], 401);
}
if (time() - ($_SESSION['cliente_ultima_actividad'] ?? 0) > SESION_MAX_INACTIVIDAD) {
    unset($_SESSION['cliente_id'], $_SESSION['cliente_nombre'], $_SESSION['cliente_ultima_actividad']);
    responder(false, 'Sesión expirada', [], 401);
}
$_SESSION['cliente_ultima_actividad'] = time();

// El cliente SIEMPRE sale de la sesión, nunca de la petición.
$codigoBp = $_SESSION['cliente_id'];
$accion   = $_GET['accion'] ?? '';

/** Devuelve el contrato solo si pertenece al cliente logueado. */
function contratoDelCliente(PDO $pdo, int $contratoId, string $codigoBp): ?array {
    $stmt = $pdo->prepare(
        "SELECT id, producto_descripcion, total_factura, prima, monto_financiar,
                porcentaje_interes, total_credito, plazo_meses, fecha_inicio, estado
           FROM contratos
          WHERE id = ? AND codigo_bp = ?"
    );
    $stmt->execute([$contratoId, $codigoBp]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    return $c ?: null;
}

// ---------------------------------------------------------------
if ($accion === 'mis_contratos') {
    $stmt = $pdo->prepare(
        "SELECT c.id, c.producto_descripcion, c.total_credito, c.plazo_meses,
                c.fecha_inicio, c.estado,
                (SELECT COUNT(*) FROM cuotas_contrato q WHERE q.contrato_id = c.id) AS numero_cuotas,
                (SELECT COUNT(*) FROM cuotas_contrato q WHERE q.contrato_id = c.id AND q.estado = 'PAGADO') AS cuotas_pagadas
           FROM contratos c
          WHERE c.codigo_bp = ?
          ORDER BY c.id DESC"
    );
    $stmt->execute([$codigoBp]);
    responder(true, 'OK', [
        'cliente'   => $_SESSION['cliente_nombre'] ?? '',
        'contratos' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
}

// ---------------------------------------------------------------
if ($accion === 'ver_cuotas') {
    $contratoId = (int)($_GET['contrato_id'] ?? 0);
    $contrato = contratoDelCliente($pdo, $contratoId, $codigoBp);
    if (!$contrato) responder(false, 'Contrato no encontrado', [], 404);

    $stmt = $pdo->prepare(
        "SELECT id AS cuota_id, numero_cuota, fecha_vencimiento, monto_cuota,
                COALESCE(monto_pagado, 0) AS monto_pagado, fecha_pago, estado, recaudo_id
           FROM cuotas_contrato
          WHERE contrato_id = ?
          ORDER BY numero_cuota ASC"
    );
    $stmt->execute([$contratoId]);
    $cuotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pagado = 0.0; $pendiente = 0.0;
    foreach ($cuotas as $q) {
        $cuota = (float)$q['monto_cuota'];
        $abono = (float)$q['monto_pagado'];
        if ($q['estado'] === 'PAGADO') {
            $pagado += $abono > 0 ? $abono : $cuota;   // por si alguna cuota pagada quedó con monto_pagado en 0
        } else {
            $pagado    += $abono;                       // abonos parciales
            $pendiente += max($cuota - $abono, 0);
        }
    }

    responder(true, 'OK', [
        'contrato' => $contrato,
        'cuotas'   => $cuotas,
        'resumen'  => ['pagado' => $pagado, 'pendiente' => $pendiente],
    ]);
}

responder(false, 'Acción no válida', [], 400);