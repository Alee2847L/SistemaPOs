<?php
// api/cotizaciones.php
session_start();
require_once '../config/conexion.php';

// Limpiar cualquier salida previa para evitar errores de sintaxis JSON
if (ob_get_length()) ob_clean();
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

// --- 1.1. LISTAR PRODUCTOS CON SUS MÚLTIPLES PROVEEDORES Y PRECIOS ---
if ($accion === 'listar_productos_proveedores') {
    try {
        // 1. Obtener productos de la base de datos (asegúrate de tener las columnas categoria y factor_rendimiento)
        $stmt = $pdo->query("SELECT id, nombre, unidad, categoria, factor_rendimiento, margen_porcentaje FROM productos");
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($productos as $prod) {
            // 2. Obtener las ferreterías/proveedores y precios asociados a este producto específico desde la tabla pivote
            $stmt_prov = $pdo->prepare("
                SELECT pp.proveedor_id, p.nombre_empresa, pp.precio 
                FROM producto_proveedor pp 
                INNER JOIN proveedores p ON pp.proveedor_id = p.id 
                WHERE pp.producto_id = ?
            ");
            $stmt_prov->execute([$prod['id']]);
            $proveedores_precios = $stmt_prov->fetchAll(PDO::FETCH_ASSOC);

            // Proveedor por defecto (el primero de la lista o el más económico)
            $proveedor_sugerido_id = !empty($proveedores_precios) ? $proveedores_precios[0]['proveedor_id'] : null;
            $costo_sugerido = !empty($proveedores_precios) ? $proveedores_precios[0]['precio'] : 0;

            $resultado[] = [
                'id' => $prod['id'],
                'nombre' => $prod['nombre'],
                'unidad' => $prod['unidad'],
                'categoria' => $prod['categoria'] ?? 'Construcción',
                'factor_rendimiento' => $prod['factor_rendimiento'] ?? 0.5,
                'margen_porcentaje' => $prod['margen_porcentaje'] ?? 20,
                'proveedor_sugerido_id' => $proveedor_sugerido_id,
                'costo_sugerido' => $costo_sugerido,
                'proveedores_precios' => $proveedores_precios
            ];
        }

        echo json_encode(['success' => true, 'data' => $resultado]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al listar catálogo: ' . $e->getMessage()]);
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
        $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'cotizacion' => $cotizacion, 'detalles' => $detalles]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener cotización: ' . $e->getMessage()]);
    }
    exit;
}

// --- 3. GUARDAR / CREAR COTIZACIÓN (CON PRODUCTO Y PROVEEDOR SELECCIONADO) ---
if ($accion === 'guardar') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'No se recibieron datos válidos.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtNum = $pdo->query("SELECT MAX(numero_cotizacion) as ultimo FROM cotizaciones");
        $rowNum = $stmtNum->fetch(PDO::FETCH_ASSOC);
        $numero_cotizacion = ($rowNum['ultimo'] ?? 429) + 1;

        $fecha_cotizacion       = $input['fecha_cotizacion'] ?? date('Y-m-d');
        $cliente_nombre         = trim($input['cliente_nombre'] ?? '');
        $cliente_rtn            = trim($input['cliente_rtn'] ?? '');
        $proyecto_nombre        = trim($input['proyecto_nombre'] ?? '');
        $clasificacion_proyecto = trim($input['clasificacion_proyecto'] ?? '');
        $ancho                  = floatval($input['ancho'] ?? 0);
        $longitud               = floatval($input['longitud'] ?? 0);
        $subtotal_general       = floatval($input['subtotal_general'] ?? 0);
        $total_general          = floatval($input['total_general'] ?? 0);

        if (empty($cliente_nombre) || empty($proyecto_nombre)) {
            echo json_encode(['success' => false, 'message' => 'El nombre del cliente y el proyecto son obligatorios']);
            exit;
        }

        // Insertar Cotización principal
        $sql_cot = "INSERT INTO cotizaciones (numero_cotizacion, fecha_cotizacion, cliente_nombre, cliente_rtn, proyecto_nombre, clasificacion_proyecto, ancho, longitud, subtotal_general, total_general, estado, usuario_creacion) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'GUARDADA', ?)";
        
        $stmt_cot = $pdo->prepare($sql_cot);
        $stmt_cot->execute([
            $numero_cotizacion, $fecha_cotizacion, $cliente_nombre, $cliente_rtn, 
            $proyecto_nombre, $clasificacion_proyecto, $ancho, $longitud, 
            $subtotal_general, $total_general, $_SESSION['usuario_id']
        ]);
        
        $cotizacion_id = $pdo->lastInsertId();

        // Insertar Detalles (Asegúrate de incluir producto_id y proveedor_id en tu tabla cotizacion_detalles si deseas almacenar la trazabilidad)
        $sql_det = "INSERT INTO cotizacion_detalles (cotizacion_id, producto_id, proveedor_id, tipo_item, descripcion, unidad, cantidad, costo_unitario, margen_porcentaje, subtotal, total_con_margen) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_det = $pdo->prepare($sql_det);

        foreach ($input['detalles'] as $item) {
            $producto_id    = !empty($item['producto_id']) ? intval($item['producto_id']) : null;
            $proveedor_id   = !empty($item['proveedor_id']) ? intval($item['proveedor_id']) : null;
            $tipo_item      = $item['tipo_item']; 
            $descripcion    = trim($item['descripcion']);
            $unidad         = trim($item['unidad']);
            $cantidad       = floatval($item['cantidad']);
            $costo_unitario = floatval($item['costo_unitario']);
            $margen         = floatval($item['margen_porcentaje']);
            
            $subtotal_linea = $cantidad * $costo_unitario;
            $total_linea_margen = $subtotal_linea * (1 + ($margen / 100));

            $stmt_det->execute([
                $cotizacion_id, $producto_id, $proveedor_id, $tipo_item, $descripcion, $unidad, 
                $cantidad, $costo_unitario, $margen, $subtotal_linea, $total_linea_margen
            ]);
        }

        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => '¡Cotización #' . $numero_cotizacion . ' creada con éxito!'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
    }
    exit;
}

// --- 3.1. GENERAR ÓRDENES DE COMPRA BAJO DEMANDA ---
if ($accion === 'generar_ordenes') {
    $cotizacion_id = intval($_POST['id'] ?? $_GET['id'] ?? 0);

    try {
        $pdo->beginTransaction();

        $stmtCot = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ?");
        $stmtCot->execute([$cotizacion_id]);
        $cot = $stmtCot->fetch(PDO::FETCH_ASSOC);

        if (!$cot) {
            echo json_encode(['success' => false, 'message' => 'Cotización no encontrada']);
            exit;
        }

        $stmtDet = $pdo->prepare("SELECT * FROM cotizacion_detalles WHERE cotizacion_id = ? AND tipo_item = 'MATERIAL'");
        $stmtDet->execute([$cotizacion_id]);
        $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

        if (empty($detalles)) {
            echo json_encode(['success' => false, 'message' => 'Esta cotización no tiene materiales registrados para ordenar.']);
            exit;
        }

        // Agrupar automáticamente por el proveedor seleccionado en cada línea de la cotización
        $ordenes_por_proveedor = [];
        foreach ($detalles as $item) {
            $proveedor_id = intval($item['proveedor_id'] ?? 0);
            if ($proveedor_id <= 0) continue; 
            
            if (!isset($ordenes_por_proveedor[$proveedor_id])) {
                $ordenes_por_proveedor[$proveedor_id] = [];
            }
            $ordenes_por_proveedor[$proveedor_id][] = [
                'descripcion'    => $item['descripcion'],
                'unidad'         => $item['unidad'],
                'cantidad'       => $item['cantidad'],
                'costo_unitario' => $item['costo_unitario'],
                'subtotal'       => $item['subtotal']
            ];
        }

        $contador_oc = 1;
        foreach ($ordenes_por_proveedor as $prov_id => $materiales_prov) {
            $numero_orden = 'OC-' . $cot['numero_cotizacion'] . '-' . $contador_oc;
            $total_oc = array_sum(array_column($materiales_prov, 'subtotal'));

            $sql_oc = "INSERT INTO ordenes_compra (numero_orden, cotizacion_id, proveedor_id, fecha_orden, estado, total_orden) 
                       VALUES (?, ?, ?, ?, 'PENDIENTE', ?)";
            
            $stmt_oc = $pdo->prepare($sql_oc);
            $stmt_oc->execute([$numero_orden, $cotizacion_id, $prov_id, $cot['fecha_cotizacion'], $total_oc]);
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

        $stmtUp = $pdo->prepare("UPDATE cotizaciones SET estado = 'CONVERTIDA A ORDEN' WHERE id = ?");
        $stmtUp->execute([$cotizacion_id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => '¡Órdenes de compra generadas con éxito por proveedor!']);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_ico([$e->getMessage()]); // Nota: ajustado a formato limpio
        echo json_encode(['success' => false, 'message' => 'Error al generar órdenes: ' . $e->getMessage()]);
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

// --- 5. LISTAR PROVEEDORES ---
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