<?php
// api/prestamos.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$accion = $_GET['accion'] ?? '';

// 1. Listar contratos existentes haciendo JOIN con clientes
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'listar') {
    try {
        $stmt = $pdo->query("
            SELECT c.*, cl.Nombre as cliente_nombre 
            FROM contratos c
            LEFT JOIN clientes cl ON c.codigo_bp = cl.codigo_bp
            ORDER BY c.id DESC
        ");
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $contratos]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 1.5 Listar cuotas de un contrato específico para el plan de pagos
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'ver_cuotas') {
    $contrato_id = intval($_GET['contrato_id'] ?? 0);
    try {
        // Obtener datos generales del contrato y cliente (Corregido cl.rtn_dni en lugar de cl.dni)
        $stmt_c = $pdo->prepare("
            SELECT c.*, cl.Nombre as cliente_nombre, cl.rtn_dni as cliente_dni, cl.Telefono as cliente_telefono
            FROM contratos c
            LEFT JOIN clientes cl ON c.codigo_bp = cl.codigo_bp
            WHERE c.id = ?
        ");
        $stmt_c->execute([$contrato_id]);
        $contrato = $stmt_c->fetch(PDO::FETCH_ASSOC);

        if (!$contrato) {
            echo json_encode(['success' => false, 'message' => 'Contrato no encontrado']);
            exit;
        }

        // Obtener las cuotas
        $stmt_cuotas = $pdo->prepare("
            SELECT * FROM cuotas_contrato 
            WHERE contrato_id = ? 
            ORDER BY numero_cuota ASC
        ");
        $stmt_cuotas->execute([$contrato_id]);
        $cuotas = $stmt_cuotas->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true, 
            'contrato' => $contrato, 
            'cuotas' => $cuotas
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 2. Guardar nuevo contrato (préstamo) y sus cuotas automáticamente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $codigo_bp = $input['codigo_bp'] ?? '';
    $producto_descripcion = $input['producto_descripcion'] ?? '';
    $total_factura = floatval($input['total_factura'] ?? 0);
    $prima = floatval($input['prima'] ?? 0);
    $monto_financiar = floatval($input['monto_financiar'] ?? 0);
    $porcentaje_interes = floatval($input['porcentaje_interes'] ?? 0);
    $total_credito = floatval($input['total_credito'] ?? 0);
    $numero_cuotas = intval($input['numero_cuotas'] ?? 1);
    $frecuencia = $input['frecuencia'] ?? 'mensual';
    
    if (empty($codigo_bp) || $monto_financiar <= 0) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Insertar en contratos especificando tipo_contrato = 'prestamo'
        $stmt = $pdo->prepare("
            INSERT INTO contratos (codigo_bp, producto_descripcion, total_factura, prima, monto_financiar, porcentaje_interes, total_credito, plazo_meses, fecha_inicio, estado, tipo_contrato)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'ACTIVO', 'prestamo')
        ");
        $stmt->execute([
            $codigo_bp,
            $producto_descripcion,
            $total_factura,
            $prima,
            $monto_financiar,
            $porcentaje_interes,
            $total_credito,
            $numero_cuotas
        ]);
        
        $contrato_id = $pdo->lastInsertId();

        // Generar las cuotas automáticamente en cuotas_contrato
        $monto_cuota = $numero_cuotas > 0 ? ($total_credito / $numero_cuotas) : $total_credito;
        
        $stmtCuota = $pdo->prepare("
            INSERT INTO cuotas_contrato (contrato_id, numero_cuota, monto_cuota, fecha_vencimiento, estado)
            VALUES (?, ?, ?, ?, 'PENDIENTE')
        ");

        for ($i = 1; $i <= $numero_cuotas; $i++) {
            if ($frecuencia === 'mensual') {
                $fechaVencimiento = date('Y-m-d', strtotime("+$i month"));
            } else if ($frecuencia === 'quincenal') {
                $dias = $i * 15;
                $fechaVencimiento = date('Y-m-d', strtotime("+$dias days"));
            } else { // semanal
                $dias = $i * 7;
                $fechaVencimiento = date('Y-m-d', strtotime("+$dias days"));
            }

            $stmtCuota->execute([$contrato_id, $i, $monto_cuota, $fechaVencimiento]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Préstamo y cuotas registradas con éxito']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
    }
    exit;
}