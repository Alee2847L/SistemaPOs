<?php
// api/prestamos.php
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
    echo json_encode(['success' => false, 'message' => 'Sesión expirada.']);
    exit;
}

$accion = $_GET['accion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'listar') {
    try {
        $stmt = $pdo->query("
            SELECT c.*, cl.Nombre AS cliente_nombre, cl.rtn_dni 
            FROM contratos c 
            LEFT JOIN clientes cl ON c.codigo_bp = cl.codigo_bp 
            ORDER BY c.id DESC
        ");
        $prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $prestamos]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data || empty($data['codigo_bp']) || empty($data['monto_financiar'])) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos para procesar el préstamo.']);
        exit;
    }

    $codigo_bp = trim($data['codigo_bp']);
    $producto_descripcion = trim($data['producto_descripcion'] ?? 'Préstamo personal / Financiamiento');
    $total_factura = floatval($data['total_factura'] ?? 0);
    $prima = floatval($data['prima'] ?? 0);
    $monto_financiar = floatval($data['monto_financiar'] ?? 0);
    $porcentaje_interes = floatval($data['porcentaje_interes'] ?? 25);
    $total_credito = floatval($data['total_credito'] ?? $monto_financiar);
    $numero_cuotas = intval($data['numero_cuotas'] ?? 1);
    $frecuencia = strtolower(trim($data['frecuencia'] ?? 'mensual'));
    $fecha_inicio = date('Y-m-d');

    try {
        $pdo->beginTransaction();

        // 1. Insertar contrato
        $sqlContrato = "INSERT INTO contratos (
                            codigo_bp, producto_descripcion, total_factura, prima, 
                            monto_financiar, porcentaje_interes, total_credito, 
                            plazo_meses, fecha_inicio, estado
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO'
                        )";
        
        $stmtContrato = $pdo->prepare($sqlContrato);
        $stmtContrato->execute([
            $codigo_bp,
            $producto_descripcion,
            $total_factura,
            $prima,
            $monto_financiar,
            $porcentaje_interes,
            $total_credito,
            $numero_cuotas,
            $fecha_inicio
        ]);

        $contrato_id = $pdo->lastInsertId();

        // 2. Generar cuotas con fechas exactas según frecuencia
        $monto_cuota = $numero_cuotas > 0 ? ($total_credito / $numero_cuotas) : $total_credito;
        
        $sqlCuota = "INSERT INTO cuotas_contrato (
                        contrato_id, numero_cuota, monto_cuota, fecha_vencimiento, monto_pagado, estado
                     ) VALUES (
                        ?, ?, ?, ?, 0.00, 'PENDIENTE'
                     )";
        $stmtCuota = $pdo->prepare($sqlCuota);

        for ($i = 1; $i <= $numero_cuotas; $i++) {
            if ($frecuencia === 'semanal') {
                $dias = $i * 7;
                $fecha_vencimiento = date('Y-m-d', strtotime("+$dias days", strtotime($fecha_inicio)));
            } elseif ($frecuencia === 'quincenal') {
                $dias = $i * 15;
                $fecha_vencimiento = date('Y-m-d', strtotime("+$dias days", strtotime($fecha_inicio)));
            } else {
                $fecha_vencimiento = date('Y-m-d', strtotime("+$i month", strtotime($fecha_inicio)));
            }
            
            $stmtCuota->execute([
                $contrato_id,
                $i,
                $monto_cuota,
                $fecha_vencimiento
            ]);
        }

        // 3. Descontar del límite de crédito del cliente
        $sqlRestarLimite = "UPDATE clientes SET limite_credito = GREATEST(0, limite_credito - ?) WHERE codigo_bp = ?";
        $stmtRestar = $pdo->prepare($sqlRestarLimite);
        $stmtRestar->execute([$monto_financiar, $codigo_bp]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'contrato_id' => $contrato_id,
            'message' => 'Préstamo creado con éxito.'
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $e->getMessage()]);
    }
    exit;
}