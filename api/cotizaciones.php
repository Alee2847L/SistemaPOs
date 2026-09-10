<?php
// api/cotizaciones.php
session_start();
require_once '../config/conexion.php';
header('Content-Type: application/json; charset=utf-8');

// Validar que exista la sesión del usuario
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$accion = $_REQUEST['accion'] ?? '';
$rolUsuario = $_SESSION['usuario_rol'] ?? 'vendedor';

// --- 1. LISTAR COTIZACIONES ---
if ($accion === 'listar') {
    try {
        $sql = "SELECT c.*, 
                       (SELECT COUNT(*) FROM ordenes_compra oc WHERE oc.cotizacion_id = c.id) as tiene_orden
                FROM cotizaciones c
                ORDER BY c.id DESC";
        $stmt = $pdo->query($sql);
        $cotizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $cotizaciones, 'rol' => $rolUsuario]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al listar cotizaciones: ' . $e->getMessage()]);
    }
    exit;
}

// --- 2. OBTENER UNA COTIZACIÓN Y SUS DETALLES ---
if ($accion === 'obtener') {
    $id = intval($_GET['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ?");
        $stmt->execute([$id]);
        $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cotizacion) {
            echo json_encode(['success' => false, 'message' => 'Cotización no encontrada']);
            exit;
        }

        $stmtDet = $pdo->prepare("SELECT * FROM cotizacion_detalles WHERE cotizacion_id = ?");
        $stmtDet->execute([$id]);
        $detalles = $stmtDet.fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'cotizacion' => $cotizacion, 'detalles' => $detalles]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener cotización: ' . $e->getMessage()]);
    }
    exit;
}

// --- 3. GUARDAR / CREAR COTIZACIÓN Y ORDEN DE COMPRA ---
if ($accion === 'guardar') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'No se recibieron datos válidos.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Obtener número de cotización automático o manual
        $stmtNum = $pdo->query("SELECT MAX(numero_cotizacion) as ultimo FROM cotizaciones");
        $rowNum = $stmtNum->fetch(PDO::FETCH_ASSOC);
        $numero_cotizacion = ($rowNum['ultimo'] ?? 429) + 1;

        $fecha_cotizacion       = $input['fecha_cotizacion'] ?? date('Y-m-d');
        $cliente_nombre         = trim($input['cliente_nombre'] ?? '');
        $cliente_rtn            = trim($input['cliente_rtn'] ?? '');
        $cliente_telefono       = trim($input['cliente_telefono'] ?? '');
        $proyecto_nombre        = trim($input['proyecto_nombre'] ?? '');
        $clasificacion_proyecto = trim($input['clasificacion_proyecto'] ?? '');
        $ancho                  = floatval($input['ancho'] ?? 0);
        $longitud               = floatval($input['longitud'] ?? 0);
        $subtotal_general       = floatval($input['subtotal_general'] ?? 0);
        $total_general          = floatval($input['total_general'] ?? 0);
        $proveedor_id_default   = intval($input['proveedor_id_default'] ?? 1);

        if (empty($cliente_nombre) || empty($proyecto_nombre)) {
            echo json_encode(['success' => false, 'message' => 'El nombre del cliente y el proyecto son obligatorios']);
            exit;
        }

        // Insertar Cotización principal
        $sql_cot = "INSERT INTO cotizaciones (numero_cotizacion, fecha_cotizacion, cliente_nombre, cliente_rtn, cliente_telefono, proyecto_nombre, clasificacion_proyecto, ancho, longitud, subtotal_general, total_general, estado, usuario_creacion) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'GUARDADA', ?)";
        
        $stmt_cot = $pdo->prepare($sql_cot);
        $stmt_cot->execute([
            $numero_cotizacion, $fecha_cotizacion, $cliente_nombre, $cliente_rtn, 
            $cliente_telefono, $proyecto_nombre, $clasificacion_proyecto, 
            $ancho, $longitud, $subtotal_general, $total_general, $_SESSION['usuario_id']
        ]);
        
        $cotizacion_id = $pdo->lastInsertId();

        // Insertar Detalles
        $sql_det = "INSERT INTO cotizacion_detalles (cotizacion_id, tipo_item, descripcion, unidad, cantidad, costo_unitario, margen_porcentaje, subtotal, total_con_margen) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_det = $pdo->prepare($sql_det);

        $materiales_para_orden = [];

        foreach ($input['detalles'] as $item) {
            $tipo_item      = $item['tipo_item']; // 'MATERIAL' o 'MANO_OBRA'
            $descripcion    = trim($item['descripcion']);
            $unidad         = trim($item['unidad']);
            $cantidad       = floatval($item['cantidad']);
            $costo_unitario = floatval($item['costo_unitario']);
            $margen         = floatval($item['margen_porcentaje']);
            
            $subtotal_linea = $cantidad * $costo_unitario;
            $total_linea_margen = $subtotal_linea * (1 + ($margen / 100));

            $stmt_det->execute([
                $cotizacion_id, $tipo_item, $descripcion, $unidad, 
                $cantidad, $costo_unitario, $margen, $subtotal_linea, $total_linea_margen
            ]);

            // Acumular si es material para la orden de compra automática
            if ($tipo_item === 'MATERIAL') {
                $materiales_para_orden[] = [
                    'descripcion'    => $descripcion,
                    'unidad'         => $unidad,
                    'cantidad'       => $cantidad,
                    'costo_unitario' => $costo_unitario,
                    'subtotal'       => $subtotal_linea
                ];
            }
        }

        // Generar Orden de Compra Automática para Proveedores
        if (count($materiales_para_orden) > 0) {
            $numero_orden = 'OC-' . $numero_cotizacion;
            $total_oc = array_sum(array_column($materiales_para_orden, 'subtotal'));

            $sql_oc = "INSERT INTO ordenes_compra (numero_orden, cotizacion_id, proveedor_id, fecha_orden, estado, total_orden) 
                       VALUES (?, ?, ?, ?, 'PENDIENTE', ?)";
            
            $stmt_oc = $pdo->prepare($sql_oc);
            $stmt_oc->execute([$numero_orden, $cotizacion_id, $proveedor_id_default, $fecha_cotizacion, $total_oc]);
            $orden_compra_id = $pdo->lastInsertId();

            $sql_oc_det = "INSERT INTO orden_compra_detalles (orden_compra_id, descripcion, unidad, cantidad_solicitada, costo_unitario, subtotal) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_oc_det = $pdo->prepare($sql_oc_det);

            foreach ($materiales_para_orden as $mat) {
                $stmt_oc_det->execute([
                    $orden_compra_id, 
                    $mat['descripcion'], 
                    $mat['unidad'], 
                    $mat['cantidad'], 
                    $mat['costo_unitario'], 
                    $mat['subtotal']
                ]);
            }
        }

        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => '¡Cotización #' . $numero_cotizacion . ' guardada y Orden de Compra generada con éxito!',
            'cotizacion_id' => $cotizacion_id
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al procesar la cotización: ' . $e->getMessage()]);
    }
    exit;
}

// --- 4. ELIMINAR COTIZACIÓN ---
if ($accion === 'eliminar') {
    if ($rolUsuario !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Solo el administrador puede eliminar cotizaciones']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("DELETE FROM cotizaciones WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Cotización eliminada con éxito']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar la cotización porque tiene registros vinculados']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
exit;
?>