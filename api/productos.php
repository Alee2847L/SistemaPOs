<?php
// api/productos.php
session_start();
require_once '../config/conexion.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$rolUsuario = $_SESSION['usuario_rol'] ?? 'vendedor';

// --- 1. LISTAR PRODUCTOS ---
if ($accion === 'listar') {
    $stmt = $pdo->query("SELECT * FROM productos ORDER BY id DESC");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'data' => $productos,
        'rol' => $rolUsuario
    ]);
    exit;
}

// --- 2. OBTENER UN SOLO PRODUCTO POR ID ---
if ($accion === 'obtener') {
    $id = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($producto) {
        echo json_encode(['success' => true, 'data' => $producto]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
    }
    exit;
}

// --- 3. BUSCAR EXACTO POR CÓDIGO DE BARRA ---
if ($accion === 'buscar_exacto') {
    $codigo = trim($_GET['codigo'] ?? '');
    if (empty($codigo)) {
        echo json_encode(['success' => false, 'data' => null]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM productos WHERE codigo_barra = ? LIMIT 1");
    $stmt->execute([$codigo]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($producto) {
        echo json_encode(['success' => true, 'data' => $producto]);
    } else {
        echo json_encode(['success' => false, 'data' => null]);
    }
    exit;
}

// --- GUARDAR LOTE DE MERCADERÍA CON TRAZABILIDAD ---
if ($accion === 'guardar_lote') {
    if ($rolUsuario !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado: Solo el administrador puede agregar lotes.']);
        exit;
    }

    $usuarioId = $_SESSION['usuario_id'] ?? null;
    if (!$usuarioId) {
        echo json_encode(['success' => false, 'message' => 'Sesión no válida o caducada.']);
        exit;
    }

    $productosJSON = $_POST['productos'] ?? '[]';
    $productos = json_decode($productosJSON, true);

    if (empty($productos) || !is_array($productos)) {
        echo json_encode(['success' => false, 'message' => 'No se recibieron productos válidos en el lote.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Generar número de inventario correlativo (ej: INV-20260818-0001)
        $prefijo = "INV-" . date("Ymd") . "-";
        $stmtNum = $pdo->prepare("SELECT COUNT(*) FROM entradas_inventario WHERE numero_entrada LIKE ?");
        $stmtNum->execute([$prefijo . '%']);
        $correlativo = $stmtNum->fetchColumn() + 1;
        $numeroEntrada = $prefijo . str_pad($correlativo, 4, '0', STR_PAD_LEFT);

        // 2. Calcular totales del lote
        $totalItems = count($productos);
        $totalUnidades = 0;
        foreach ($productos as $p) {
            $totalUnidades += intval($p['cantidad']);
        }

        // 3. Insertar la cabecera del lote/inventario
        $stmtCabecera = $pdo->prepare("INSERT INTO entradas_inventario (numero_entrada, usuario_id, total_items, total_unidades) VALUES (?, ?, ?, ?)");
        $stmtCabecera->execute([$numeroEntrada, $usuarioId, $totalItems, $totalUnidades]);
        $entradaId = $pdo->lastInsertId();

        // 4. Procesar cada producto y guardar su detalle
        $stmtDetalle = $pdo->prepare("INSERT INTO detalle_entrada_inventario (entrada_id, producto_id, cantidad, precio_compra, precio_venta) VALUES (?, ?, ?, ?, ?)");

        foreach ($productos as $item) {
            $id = intval($item['id'] ?? 0);
            $codigo_barra = trim($item['codigo_barra'] ?? '');
            $nombre = trim($item['nombre'] ?? '');
            $precio_compra = floatval($item['precio_compra'] ?? 0);
            $precio_venta = floatval($item['precio_venta'] ?? 0);
            $cantidad = intval($item['cantidad'] ?? 0);

            $productoIdFinal = $id;

            if ($id > 0) {
                // Producto existente: Actualizar stock y precios si es necesario
                $stmt = $pdo->prepare("UPDATE productos SET stock = stock + ?, precio_compra = ?, precio_venta = ? WHERE id = ?");
                $stmt->execute([$cantidad, $precio_compra, $precio_venta, $id]);
            } else {
                // Verificar si existe por código
                $stmtCheck = $pdo->prepare("SELECT id FROM productos WHERE codigo_barra = ?");
                $stmtCheck->execute([$codigo_barra]);
                $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($existente) {
                    $productoIdFinal = $existente['id'];
                    $stmt = $pdo->prepare("UPDATE productos SET stock = stock + ?, precio_compra = ?, precio_venta = ? WHERE id = ?");
                    $stmt->execute([$cantidad, $precio_compra, $precio_venta, $productoIdFinal]);
                } else {
                    // Producto completamente nuevo
                    $stmt = $pdo->prepare("INSERT INTO productos (codigo_barra, nombre, precio_compra, precio_venta, stock) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$codigo_barra, $nombre, $precio_compra, $precio_venta, $cantidad]);
                    $productoIdFinal = $pdo->lastInsertId();
                }
            }

            // Guardar detalle de la entrada
            $stmtDetalle->execute([$entradaId, $productoIdFinal, $cantidad, $precio_compra, $precio_venta]);
        }

        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => "Lote ingresado con éxito.\nNº de Inventario: {$numeroEntrada}"
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al procesar el lote: ' . $e->getMessage()]);
    }
    exit;
}

// --- 5. GUARDAR INDIVIDUAL ---
if ($accion === 'guardar') {
    if ($rolUsuario !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }

    $id            = trim($_POST['id'] ?? '');
    $codigo_barra  = trim($_POST['codigo_barra'] ?? '');
    $nombre        = trim($_POST['nombre'] ?? '');
    $precio_compra = floatval($_POST['precio_compra'] ?? 0);
    $precio_venta  = floatval($_POST['precio_venta'] ?? 0);
    $stock         = intval($_POST['stock'] ?? 0);

    if (empty($codigo_barra) || empty($nombre) || $precio_venta <= 0) {
        echo json_encode(['success' => false, 'message' => 'Complete los campos obligatorios']);
        exit;
    }

    $stmtCheck = $pdo->prepare("SELECT * FROM productos WHERE codigo_barra = ? LIMIT 1");
    $stmtCheck->execute([$codigo_barra]);
    $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        $mismoNombre = ($existente['nombre'] === $nombre);
        $mismoPrecioC = (floatval($existente['precio_compra']) == $precio_compra);
        $mismoPrecioV = (floatval($existente['precio_venta']) == $precio_venta);

        if ($mismoNombre && $mismoPrecioC && $mismoPrecioV) {
            $nuevoStock = intval($existente['stock']) + $stock;
            $stmtUpdate = $pdo->prepare("UPDATE productos SET stock = ? WHERE id = ?");
            $stmtUpdate->execute([$nuevoStock, $existente['id']]);

            echo json_encode(['success' => true, 'message' => "Se sumaron {$stock} unidades al stock."]);
            exit;
        }

        $idActualizar = !empty($id) ? $id : $existente['id'];
        $stmt = $pdo->prepare("UPDATE productos SET codigo_barra = ?, nombre = ?, precio_compra = ?, precio_venta = ?, stock = ? WHERE id = ?");
        $stmt->execute([$codigo_barra, $nombre, $precio_compra, $precio_venta, $stock, $idActualizar]);
        echo json_encode(['success' => true, 'message' => 'Producto actualizado correctamente']);
        exit;
    }

    if (!empty($id)) {
        $stmt = $pdo->prepare("UPDATE productos SET codigo_barra = ?, nombre = ?, precio_compra = ?, precio_venta = ?, stock = ? WHERE id = ?");
        $stmt->execute([$codigo_barra, $nombre, $precio_compra, $precio_venta, $stock, $id]);
        echo json_encode(['success' => true, 'message' => 'Producto actualizado']);
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO productos (codigo_barra, nombre, precio_compra, precio_venta, stock) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$codigo_barra, $nombre, $precio_compra, $precio_venta, $stock]);
            echo json_encode(['success' => true, 'message' => 'Producto registrado exitosamente']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
    exit;
}

/// --- 6. ELIMINAR PRODUCTO ---
if ($accion === 'eliminar') {
    if ($rolUsuario !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }

    $id          = intval($_POST['id'] ?? 0);
    $clave_admin = trim($_POST['clave_admin'] ?? '');

    if (empty($clave_admin)) {
        echo json_encode(['success' => false, 'message' => 'Debe ingresar la contraseña de administrador']);
        exit;
    }

    // === AQUÍ COLOCAS EL SCRIPT DE VERIFICACIÓN ===
    try {
        $stmtUser = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmtUser->execute([$_SESSION['usuario_id']]);
        $usuarioDb = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$usuarioDb) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit;
        }

        $passwordAlmacenada = $usuarioDb['password'];

        $esValidaHash = password_get_info($passwordAlmacenada)['algo'] !== 0 && password_verify($clave_admin, $passwordAlmacenada);
        $esValidaPlano = ($passwordAlmacenada === $clave_admin);

        if (!$esValidaHash && !$esValidaPlano) {
            echo json_encode(['success' => false, 'message' => 'Contraseña de administrador incorrecta']);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al verificar credenciales: ' . $e->getMessage()]);
        exit;
    }
    // ===============================================

    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Producto eliminado con éxito']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar el producto porque tiene registros o ventas asociadas']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ID no válido']);
    }
    exit;
}
?>