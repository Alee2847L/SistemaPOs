<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/conexion.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la BD: ' . $e->getMessage()]);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuarioId = $_SESSION['usuario_id'] ?? null;
if (!$usuarioId) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada. Por favor vuelve a iniciar sesión.']);
    exit;
}

$accion = $_GET['accion'] ?? $_POST['accion'] ?? 'listar';

// --- ACCIÓN 1: LISTAR LOTES DE ENTRADA ---
if ($accion === 'listar') {
    $busqueda = trim($_GET['busqueda'] ?? '');
    $fecha_inicio = trim($_GET['fecha_inicio'] ?? '');
    $fecha_fin = trim($_GET['fecha_fin'] ?? '');

    $sql = "SELECT e.id, e.numero_entrada, e.fecha_ingreso, e.total_items, e.total_unidades, 
                   IFNULL(e.estado, 'ACTIVO') AS estado,
                   u.nombre AS usuario_nombre
            FROM entradas_inventario e
            INNER JOIN usuarios u ON e.usuario_id = u.id
            WHERE 1=1";

    $params = [];

    if (!empty($busqueda)) {
        $sql .= " AND (e.numero_entrada LIKE ? OR u.nombre LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }

    if (!empty($fecha_inicio)) {
        $sql .= " AND DATE(e.fecha_ingreso) >= ?";
        $params[] = $fecha_inicio;
    }

    if (!empty($fecha_fin)) {
        $sql .= " AND DATE(e.fecha_ingreso) <= ?";
        $params[] = $fecha_fin;
    }

    $sql .= " ORDER BY e.fecha_ingreso DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $entradas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $entradas]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $e->getMessage()]);
    }
    exit;
}

// --- ACCIÓN 2: VER DETALLE DEL LOTE ---
if ($accion === 'ver_detalle') {
    $entrada_id = intval($_GET['id'] ?? 0);

    try {
        $stmtCab = $pdo->prepare("SELECT e.*, IFNULL(e.estado, 'ACTIVO') AS estado, u.nombre AS usuario_nombre 
                                  FROM entradas_inventario e 
                                  INNER JOIN usuarios u ON e.usuario_id = u.id 
                                  WHERE e.id = ?");
        $stmtCab->execute([$entrada_id]);
        $cabecera = $stmtCab->fetch(PDO::FETCH_ASSOC);

        if (!$cabecera) {
            echo json_encode(['success' => false, 'message' => 'Lote no encontrado.']);
            exit;
        }

        $stmtDet = $pdo->prepare("SELECT d.*, p.nombre AS producto_nombre, p.codigo_barra 
                                  FROM detalle_entrada_inventario d
                                  INNER JOIN productos p ON d.producto_id = p.id
                                  WHERE d.entrada_id = ?");
        $stmtDet->execute([$entrada_id]);
        $productos = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'cabecera' => $cabecera, 'productos' => $productos]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $e->getMessage()]);
    }
    exit;
}

// --- ACCIÓN 3: ANULAR LOTE DE MERCADERÍA CON VALIDACIÓN DE ADMIN ---
if ($accion === 'anular_lote') {
    $entrada_id = intval($_POST['entrada_id'] ?? 0);
    $claveAdmin = trim($_POST['clave_admin'] ?? '');

    if ($entrada_id <= 0 || empty($claveAdmin)) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos para la anulación.']);
        exit;
    }

    try {
        $stmtAdmin = $pdo->prepare("SELECT id, password, rol FROM usuarios WHERE id = ?");
        $stmtAdmin->execute([$usuarioId]);
        $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
            exit;
        }

        $rol = strtolower($admin['rol']);
        if ($rol !== 'admin' && $rol !== 'administrador') {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos de administrador.']);
            exit;
        }

        $passwordBD = $admin['password'];
        $esValida = ($claveAdmin === $passwordBD || password_verify($claveAdmin, $passwordBD));

        if (!$esValida) {
            echo json_encode(['success' => false, 'message' => 'Contraseña de confirmación incorrecta.']);
            exit;
        }

        $stmtLote = $pdo->prepare("SELECT * FROM entradas_inventario WHERE id = ?");
        $stmtLote->execute([$entrada_id]);
        $lote = $stmtLote->fetch(PDO::FETCH_ASSOC);

        if (!$lote) {
            echo json_encode(['success' => false, 'message' => 'El lote no existe.']);
            exit;
        }

        if (($lote['estado'] ?? 'ACTIVO') === 'ANULADO') {
            echo json_encode(['success' => false, 'message' => 'Este lote ya está anulado.']);
            exit;
        }

        $pdo->beginTransaction();

        // Restar el stock correspondiente a los productos del lote
        $stmtDet = $pdo->prepare("SELECT producto_id, cantidad FROM detalle_entrada_inventario WHERE entrada_id = ?");
        $stmtDet->execute([$entrada_id]);
        $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

        $stmtRestar = $pdo->prepare("UPDATE productos SET stock = GREATEST(0, stock - ?) WHERE id = ?");
        foreach ($detalles as $det) {
            $stmtRestar->execute([$det['cantidad'], $det['producto_id']]);
        }

        // Marcar lote como ANULADO
        $stmtAnular = $pdo->prepare("UPDATE entradas_inventario SET estado = 'ANULADO' WHERE id = ?");
        $stmtAnular->execute([$entrada_id]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => "El lote {$lote['numero_entrada']} ha sido anulado con éxito y el stock fue descontado."]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Error al anular lote: ' . $e->getMessage()]);
    }
    exit;
}