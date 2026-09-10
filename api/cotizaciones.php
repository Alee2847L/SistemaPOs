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

// --- 3. GENERAR ÓRDENES DE COMPRA AGRUPADAS POR PROVEEDOR ---
        // Agrupar los materiales por proveedor_id
        $ordenes_por_proveedor = [];

        foreach ($input['detalles'] as $item) {
            $tipo_item      = $item['tipo_item']; // 'MATERIAL' o 'MANO_OBRA'
            $descripcion    = trim($item['descripcion']);
            $unidad         = trim($item['unidad']);
            $cantidad       = floatval($item['cantidad']);
            $costo_unitario = floatval($item['costo_unitario']);
            $margen         = floatval($item['margen_porcentaje']);
            $proveedor_id   = intval($item['proveedor_id'] ?? 1); // Proveedor asociado al producto
            
            $subtotal_linea = $cantidad * $costo_unitario;
            $total_linea_margen = $subtotal_linea * (1 + ($margen / 100));

            // Guardar detalle de la cotización
            $stmt_det->execute([
                $cotizacion_id, $tipo_item, $descripcion, $unidad, 
                $cantidad, $costo_unitario, $margen, $subtotal_linea, $total_linea_margen
            ]);

            // Si es un material, lo agrupamos para las órdenes de compra a proveedores
            if ($tipo_item === 'MATERIAL') {
                if (!isset($ordenes_por_proveedor[$proveedor_id])) {
                    $ordenes_por_proveedor[$proveedor_id] = [];
                }
                $ordenes_por_proveedor[$proveedor_id][] = [
                    'descripcion'    => $descripcion,
                    'unidad'         => $unidad,
                    'cantidad'       => $cantidad,
                    'costo_unitario' => $costo_unitario,
                    'subtotal'       => $subtotal_linea
                ];
            }
        }

        // Crear una Orden de Compra por cada Proveedor único encontrado
        $contador_oc = 1;
        foreach ($ordenes_por_proveedor as $prov_id => $materiales_prov) {
            $numero_orden = 'OC-' . $numero_cotizacion . '-' . $contador_oc;
            $total_oc = array_sum(array_column($materiales_prov, 'subtotal'));

            $sql_oc = "INSERT INTO ordenes_compra (numero_orden, cotizacion_id, proveedor_id, fecha_orden, estado, total_orden) 
                       VALUES (?, ?, ?, ?, 'PENDIENTE', ?)";
            
            $stmt_oc = $pdo->prepare($sql_oc);
            $stmt_oc->execute([$numero_orden, $cotizacion_id, $prov_id, $fecha_cotizacion, $total_oc]);
            $orden_compra_id = $pdo->lastInsertId();

            $sql_oc_det = "INSERT INTO orden_compra_detalles (orden_compra_id, descripcion, unidad, cantidad_solicitada, costo_unitario, subtotal) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_oc_det = $pdo->prepare($sql_oc_det);

            foreach ($materiales_prov as $mat) {
                $stmt_oc_det->execute([
                    $orden_compra_id, 
                    $mat['descripcion'], 
                    $mat['unidad'], 
                    $mat['cantidad'], 
                    $mat['costo_unitario'], 
                    $mat['subtotal']
                ]);
            }
            $contador_oc++;
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

// --- 5. LISTAR PROVEEDORES (Para el selector de la orden de compra) ---
if ($accion === 'listar_proveedores') {
    try {
        $stmt = $pdo->query("SELECT id, nombre_empresa FROM proveedores ORDER BY nombre_empresa ASC");
        $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $proveedores]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al listar proveedores: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
exit;
?>