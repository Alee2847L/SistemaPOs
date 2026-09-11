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

// --- 4. GUARDAR LOTE DE MERCADERÍA CON TRAZABILIDAD ---
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

        $prefijo = "INV-" . date("Ymd") . "-";
        $stmtNum = $pdo->prepare("SELECT COUNT(*) FROM entradas_inventario WHERE numero_entrada LIKE ?");
        $stmtNum->execute([$prefijo . '%']);
        $correlativo = $stmtNum->fetchColumn() + 1;
        $numeroEntrada = $prefijo . str_pad($correlativo, 4, '0', STR_PAD_LEFT);

        $totalItems = count($productos);
        $totalUnidades = 0;
        foreach ($productos as $p) {
            $totalUnidades += intval($p['cantidad']);
        }

        $stmtCabecera = $pdo->prepare("INSERT INTO entradas_inventario (numero_entrada, usuario_id, total_items, total_unidades) VALUES (?, ?, ?, ?)");
        $stmtCabecera->execute([$numeroEntrada, $usuarioId, $totalItems, $totalUnidades]);
        $entradaId = $pdo->lastInsertId();

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
                $stmt = $pdo->prepare("UPDATE productos SET stock = stock + ?, precio_compra = ?, precio_venta = ? WHERE id = ?");
                $stmt->execute([$cantidad, $precio_compra, $precio_venta, $id]);
            } else {
                $stmtCheck = $pdo->prepare("SELECT id FROM productos WHERE codigo_barra = ?");
                $stmtCheck->execute([$codigo_barra]);
                $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($existente) {
                    $productoIdFinal = $existente['id'];
                    $stmt = $pdo->prepare("UPDATE productos SET stock = stock + ?, precio_compra = ?, precio_venta = ? WHERE id = ?");
                    $stmt->execute([$cantidad, $precio_compra, $precio_venta, $productoIdFinal]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO productos (codigo_barra, nombre, precio_compra, precio_venta, stock) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$codigo_barra, $nombre, $precio_compra, $precio_venta, $cantidad]);
                    $productoIdFinal = $pdo->lastInsertId();
                }
            }

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

// --- 5. GUARDAR INDIVIDUAL (Con soporte para Proveedor) ---
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
    $proveedor_id  = !empty($_POST['proveedor_id']) ? intval($_POST['proveedor_id']) : null;

    if (empty($codigo_barra) || empty($nombre) || $precio_venta <= 0) {
        echo json_encode(['success' => false, 'message' => 'Complete los campos obligatorios']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $productoIdFinal = null;
        $mensajeRespuesta = '';

        if (!empty($id)) {
            $stmt = $pdo->prepare("UPDATE productos SET codigo_barra = ?, nombre = ?, precio_compra = ?, precio_venta = ?, stock = ? WHERE id = ?");
            $stmt->execute([$codigo_barra, $nombre, $precio_compra, $precio_venta, $stock, $id]);
            $productoIdFinal = intval($id);
            $mensajeRespuesta = 'Producto actualizado correctamente';
        } else {
            $stmtCheck = $pdo->prepare("SELECT id FROM productos WHERE codigo_barra = ? LIMIT 1");
            $stmtCheck->execute([$codigo_barra]);
            $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existente) {
                $productoIdFinal = intval($existente['id']);
                $stmt = $pdo->prepare("UPDATE productos SET nombre = ?, precio_compra = ?, precio_venta = ?, stock = stock + ? WHERE id = ?");
                $stmt->execute([$nombre, $precio_compra, $precio_venta, $stock, $productoIdFinal]);
                $mensajeRespuesta = 'Producto existente encontrado: stock actualizado correctamente.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO productos (codigo_barra, nombre, precio_compra, precio_venta, stock) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$codigo_barra, $nombre, $precio_compra, $precio_venta, $stock]);
                $productoIdFinal = intval($pdo->lastInsertId());
                $mensajeRespuesta = 'Producto registrado exitosamente';
            }
        }

        if ($productoIdFinal && $proveedor_id) {
            $stmtProvCheck = $pdo->prepare("SELECT id FROM producto_proveedor WHERE producto_id = ? AND proveedor_id = ?");
            $stmtProvCheck->execute([$productoIdFinal, $proveedor_id]);
            
            if (!$stmtProvCheck->fetch()) {
                $stmtProvIns = $pdo->prepare("INSERT INTO producto_proveedor (producto_id, proveedor_id, precio) VALUES (?, ?, ?)");
                $stmtProvIns->execute([$productoIdFinal, $proveedor_id, $precio_compra]);
            } else {
                $stmtProvUpd = $pdo->prepare("UPDATE producto_proveedor SET precio = ? WHERE producto_id = ? AND proveedor_id = ?");
                $stmtProvUpd->execute([$precio_compra, $productoIdFinal, $proveedor_id]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => $mensajeRespuesta]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al guardar el producto: ' . $e->getMessage()]);
    }
    exit;
}

// --- 6. ELIMINAR PRODUCTO ---
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

// --- 7. LISTAR PROVEEDORES PARA EL SELECTOR ---
if ($accion === 'listar_proveedores') {
    try {
        $stmt = $pdo->query("SELECT id, nombre_empresa FROM proveedores ORDER BY nombre_empresa ASC");
        $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'data' => $proveedores
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al cargar proveedores: ' . $e->getMessage()]);
    }
    exit;
}

// --- 8. CREAR PROVEEDOR RÁPIDO DESDE EL MODAL ---
if ($accion === 'guardar_proveedor') {
    if ($rolUsuario !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }

    $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
    $contacto       = trim($_POST['contacto'] ?? '');
    $telefono       = trim($_POST['telefono'] ?? '');
    $correo         = trim($_POST['correo'] ?? '');
    $rtn            = trim($_POST['rtn'] ?? '');
    $direccion      = trim($_POST['direccion'] ?? '');

    if (empty($nombre_empresa)) {
        echo json_encode(['success' => false, 'message' => 'El nombre de la empresa es obligatorio']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO proveedores (nombre_empresa, contacto, telefono, correo, rtn, direccion) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre_empresa, $contacto, $telefono, $correo, $rtn, $direccion]);
        
        $nuevoId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true, 
            'message' => 'Proveedor registrado con éxito',
            'id' => $nuevoId,
            'nombre_empresa' => $nombre_empresa
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al registrar el proveedor: ' . $e->getMessage()]);
    }
    exit;
}
?>