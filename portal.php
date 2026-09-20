<?php
// api/portal.php — Datos del cliente autenticado (SOLO lectura)
session_start();
header('Content-Type: application/json; charset=utf-8');
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
    // AJUSTA nombres de tabla/columnas a tu esquema
    $stmt = $pdo->prepare(
        "SELECT id, producto_descripcion, monto_financiar, total_credito,
                numero_cuotas, frecuencia, estado
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
        "SELECT id, producto_descripcion, monto_financiar, total_credito,
                numero_cuotas, frecuencia, estado
           FROM contratos
          WHERE codigo_bp = ?
          ORDER BY id DESC"
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
        "SELECT numero_cuota, fecha_vencimiento, monto_cuota, estado
           FROM cuotas
          WHERE contrato_id = ?
          ORDER BY numero_cuota ASC"
    );
    $stmt->execute([$contratoId]);
    $cuotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pagado = 0.0; $pendiente = 0.0;
    foreach ($cuotas as $q) {
        if ($q['estado'] === 'PAGADO') $pagado += (float)$q['monto_cuota'];
        else                           $pendiente += (float)$q['monto_cuota'];
    }

    responder(true, 'OK', [
        'contrato' => $contrato,
        'cuotas'   => $cuotas,
        'resumen'  => ['pagado' => $pagado, 'pendiente' => $pendiente],
    ]);
}

// ---------------------------------------------------------------
// Descarga del contrato (OPCIONAL): solo si guardas el archivo en disco.
// Requiere una columna, p. ej. contratos.archivo_contrato con el nombre del PDF,
// guardado FUERA de la carpeta pública o protegido con .htaccess.
if ($accion === 'descargar_contrato') {
    $contratoId = (int)($_GET['contrato_id'] ?? 0);
    if (!contratoDelCliente($pdo, $contratoId, $codigoBp)) {
        responder(false, 'Contrato no encontrado', [], 404);
    }

    $stmt = $pdo->prepare("SELECT archivo_contrato FROM contratos WHERE id = ? AND codigo_bp = ?");
    $stmt->execute([$contratoId, $codigoBp]);
    $archivo = basename((string)$stmt->fetchColumn());   // basename evita ../
    $ruta = __DIR__ . '/../storage/contratos/' . $archivo;

    if ($archivo === '' || !is_file($ruta)) {
        responder(false, 'El documento aún no está disponible.', [], 404);
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="contrato-' . $contratoId . '.pdf"');
    header('Content-Length: ' . filesize($ruta));
    readfile($ruta);
    exit;
}

responder(false, 'Acción no válida', [], 400);
