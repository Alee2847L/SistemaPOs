<?php
// api/procesar_devolucion.php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['usuario_rol']) || (strtolower($_SESSION['usuario_rol']) !== 'admin' && strtolower($_SESSION['usuario_rol']) !== 'administrador')) {
    header("Location: ../views/devoluciones.php?err=" . urlencode("Acceso no autorizado."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idTransaccion = intval($_POST['id_transaccion'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? '');
    $comentario = trim($_POST['comentario'] ?? '');
    $passwordAdmin = $_POST['password_admin'] ?? '';
    $adminId = $_SESSION['usuario_id'];

    try {
        $pdo->beginTransaction();

        // 1. Validar clave del administrador
        $stmtAdmin = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmtAdmin->execute([$adminId]);
        $adminData = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

        if (!$adminData || !password_verify($passwordAdmin, $adminData['password'])) {
            throw new Exception("La contraseña de administrador es incorrecta.");
        }

        // 2. Obtener la venta original
        $stmtVenta = $pdo->prepare("SELECT * FROM ventas WHERE id_transaccion = ?");
        $stmtVenta->execute([$idTransaccion]);
        $ventaOriginal = $stmtVenta->fetch(PDO::FETCH_ASSOC);

        if (!$ventaOriginal) {
            throw new Exception("La transacción especificada no existe.");
        }

        // 2.1 Validación estricta: Verificar si la transacción ya tiene una devolución registrada
        $stmtCheckYaDevuelta = $pdo->prepare("SELECT id FROM devoluciones WHERE venta_id = ?");
        $stmtCheckYaDevuelta->execute([$idTransaccion]);
        if ($stmtCheckYaDevuelta->fetch()) {
            throw new Exception("Esta transacción ya fue procesada como devolución con anterioridad.");
        }

        // 3. Obtener los detalles de los productos vendidos originalmente
        $stmtDetalles = $pdo->prepare("SELECT * FROM detalle_ventas WHERE venta_id = ?");
        $stmtDetalles->execute([$idTransaccion]);
        $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

        if (empty($detalles)) {
            throw new Exception("La transacción no contiene productos registrados en su detalle.");
        }

        $metodoOriginal = "Devolución por: " . $motivo . ($comentario ? " - " . $comentario : "");
        $totalReembolso = abs($ventaOriginal['total']);

        // 4. Registrar primero en la tabla 'devoluciones' para obtener el ID principal
        $stmtLogDev = $pdo->prepare("
            INSERT INTO devoluciones (venta_id, motivo, total_reembolso, usuario_id, cliente) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtLogDev->execute([
            $idTransaccion, 
            $metodoOriginal,
            $totalReembolso,
            $adminId,
            $ventaOriginal['cliente_nombre'] ?? 'Consumidor Final'
        ]);
        
        $devolucionId = $pdo->lastInsertId(); // Obtenemos el ID recién creado

        // 5. Registrar la devolución como una nueva transacción en NEGATIVO en la tabla 'ventas'
        $stmtInsDev = $pdo->prepare("
            INSERT INTO ventas (cliente_identidad, cliente_codigo_bp, usuario_id, total, estado_caja, cliente_rtn, ahorro_total, monto_abonado, monto_recibido, cambio_entregado, cliente_nombre, tipo_comprobante, metodo_pago) 
            VALUES (?, ?, ?, ?, 'abierta', ?, ?, ?, ?, ?, ?, 'Devolución', ?)
        ");
        
        $totalNegativo = -1 * $totalReembolso;
        $montoRecibidoDev = -1 * abs($ventaOriginal['monto_recibido'] ?? 0);
        
        // Aquí asignamos el total del reembolso a la columna cambio_entregado
        $cambioDevolucion = $totalReembolso; 

        $stmtInsDev->execute([
            $ventaOriginal['cliente_identidad'],
            $ventaOriginal['cliente_codigo_bp'],
            $adminId,
            $totalNegativo,
            $ventaOriginal['cliente_rtn'] ?? '0000000000000',
            $ventaOriginal['ahorro_total'] ?? 0,
            $ventaOriginal['monto_abonado'] ?? 0,
            $montoRecibidoDev,
            $cambioDevolucion,
            $ventaOriginal['cliente_nombre'] ?? 'Consumidor Final',
            $metodoOriginal
        ]);

        // 6. Devolver cantidades al inventario y registrar en 'detalle_devoluciones'
        foreach ($detalles as $det) {
            $prodId = $det['producto_id'] ?? null;
            $cantDevolver = $det['cantidad'] ?? 0;
            $precioUnitario = $det['precio_unitario'] ?? 0;

            if ($prodId && $cantDevolver > 0) {
                // A. Actualizar stock del producto sumando lo devuelto
                $stmtStock = $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
                $stmtStock->execute([$cantDevolver, $prodId]);

                // B. Guardar el desglose en detalle_devoluciones
                $stmtDetDev = $pdo->prepare("
                    INSERT INTO detalle_devoluciones (devolucion_id, producto_id, cantidad, precio_unitario) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmtDetDev->execute([$devolucionId, $prodId, $cantDevolver, $precioUnitario]);
            }
        }

        $pdo->commit();
        header("Location: ../views/devoluciones.php?msg=" . urlencode("¡Devolución procesada con éxito! Se reincorporó el inventario, se registró el detalle y se ajustó la caja."));
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: ../views/devoluciones.php?id_transaccion={$idTransaccion}&err=" . urlencode($e->getMessage()));
        exit();
    }
}