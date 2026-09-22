<?php
// api/procesar_anulacion_prestamo.php
session_start();
require_once '../config/conexion.php';

// Límite de días desde la creación del contrato dentro del cual se permite anular.
// Pasado este plazo, la anulación queda bloqueada.
const DIAS_MAXIMOS_PARA_ANULAR = 7;

if (!isset($_SESSION['usuario_rol']) || (strtolower($_SESSION['usuario_rol']) !== 'admin' && strtolower($_SESSION['usuario_rol']) !== 'administrador')) {
    header("Location: ../views/anular_prestamo.php?err=" . urlencode("Acceso no autorizado."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contratoId = intval($_POST['contrato_id'] ?? 0);
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

        // 2. Obtener el contrato y bloquear la fila mientras dura la transacción
        $stmtContrato = $pdo->prepare("
            SELECT co.*, cl.Nombre AS cliente_nombre
              FROM contratos co
              LEFT JOIN clientes cl ON co.codigo_bp = cl.codigo_bp
             WHERE co.id = ?
             FOR UPDATE
        ");
        $stmtContrato->execute([$contratoId]);
        $contrato = $stmtContrato->fetch(PDO::FETCH_ASSOC);

        if (!$contrato) {
            throw new Exception("El contrato especificado no existe.");
        }

        if ($contrato['estado'] !== 'ACTIVO') {
            throw new Exception("Este contrato no está activo (estado actual: {$contrato['estado']}); no se puede anular.");
        }

        // 3. Validar el plazo de 7 días desde la creación del contrato
        $fechaInicio = new DateTime($contrato['fecha_inicio']);
        $hoy = new DateTime(date('Y-m-d'));
        $diasTranscurridos = (int)$fechaInicio->diff($hoy)->days;

        if ($diasTranscurridos > DIAS_MAXIMOS_PARA_ANULAR) {
            throw new Exception(
                "No se puede anular: han pasado {$diasTranscurridos} días desde la creación del contrato " .
                "(máximo permitido: " . DIAS_MAXIMOS_PARA_ANULAR . " días)."
            );
        }

        // 4. Validar que no tenga cuotas ya pagadas (evita anular un contrato con dinero ya cobrado)
        $stmtPagos = $pdo->prepare("
            SELECT COUNT(*) FROM cuotas_contrato WHERE contrato_id = ? AND (estado = 'PAGADO' OR monto_pagado > 0)
        ");
        $stmtPagos->execute([$contratoId]);
        if ((int)$stmtPagos->fetchColumn() > 0) {
            throw new Exception("Este contrato ya tiene cuotas pagadas; no se puede anular directamente. Gestiónalo como una devolución.");
        }

        // 5. Restaurar el límite de crédito del cliente
        $montoARestaurar = (float)$contrato['monto_financiar'];
        if ($montoARestaurar > 0 && !empty($contrato['codigo_bp'])) {
            $stmtCredito = $pdo->prepare("UPDATE clientes SET limite_credito = limite_credito + ? WHERE codigo_bp = ?");
            $stmtCredito->execute([$montoARestaurar, $contrato['codigo_bp']]);
        }

        // 6. Marcar el contrato como CANCELADO (las cuotas quedan como historial, ya no se cobrarán)
        $pdo->prepare("UPDATE contratos SET estado = 'CANCELADO' WHERE id = ?")->execute([$contratoId]);

        // 6.5 Si el contrato tenía una prima que nunca llegó a cobrarse en POS
        // (prima_venta_id seguía en NULL = pendiente), se marca explícitamente como
        // "cancelada sin cobrar" (prima_venta_id = 0). Esto es un refuerzo, además del
        // filtro por estado = 'ACTIVO' que ya usan verificar_prima_pendiente (POS) y el
        // plan de pagos: así, aunque solo se revise prima_venta_id, un contrato anulado
        // nunca vuelve a ofrecerse como "prima pendiente de cobro".
        // 0 es un valor imposible para un id real de venta (autoincremental desde 1), así
        // que queda claramente distinguido de NULL (pendiente) y de un id real (cobrada).
        if (empty($contrato['prima_venta_id']) && (float)($contrato['prima'] ?? 0) > 0) {
            $pdo->prepare("UPDATE contratos SET prima_venta_id = 0 WHERE id = ?")->execute([$contratoId]);
        }

        // 7. Registrar la anulación
        $stmtLog = $pdo->prepare("
            INSERT INTO anulaciones_prestamo (contrato_id, codigo_bp, cliente_nombre, motivo, comentario, monto_restaurado, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtLog->execute([
            $contratoId,
            $contrato['codigo_bp'],
            $contrato['cliente_nombre'] ?? 'Cliente General',
            $motivo,
            $comentario,
            $montoARestaurar,
            $adminId
        ]);

        // 8. Si la prima de este contrato ya se había cobrado en POS
        // (contratos.prima_venta_id apunta a esa venta), revertirla: se crea una
        // venta espejo con los montos en negativo. Al tener total < 0 queda
        // clasificada automáticamente como "Devolución" (misma convención que usan
        // transacciones.php y arqueo.php: $esDevolucion = total < 0), por lo que se
        // resta sola del Arqueo de caja sin necesidad de una columna de estado nueva.
        $primaRevertida = false;
        $primaVentaId = intval($contrato['prima_venta_id'] ?? 0);
        if ($primaVentaId > 0) {
            $stmtVentaPrima = $pdo->prepare("SELECT * FROM ventas WHERE id_transaccion = ? LIMIT 1");
            $stmtVentaPrima->execute([$primaVentaId]);
            $ventaPrima = $stmtVentaPrima->fetch(PDO::FETCH_ASSOC);

            if ($ventaPrima) {
                $sqlReversaPrima = "INSERT INTO ventas (
                                        numero_factura, usuario_id, cliente_codigo_bp, cliente_identidad,
                                        cliente_nombre, cliente_rtn, tipo_comprobante, total,
                                        ahorro_total, metodo_pago, monto_efectivo, monto_tarjeta,
                                        monto_abonado, monto_recibido, cambio_entregado, fecha_venta
                                    ) VALUES (
                                        NULL, ?, ?, ?,
                                        ?, ?, 'Prima de Préstamo (Anulada)', ?,
                                        0, ?, ?, ?,
                                        ?, ?, 0, NOW()
                                    )";
                $stmtReversaPrima = $pdo->prepare($sqlReversaPrima);
                $stmtReversaPrima->execute([
                    $adminId,
                    $ventaPrima['cliente_codigo_bp'],
                    $ventaPrima['cliente_identidad'],
                    $ventaPrima['cliente_nombre'],
                    $ventaPrima['cliente_rtn'],
                    -abs(floatval($ventaPrima['total'])),
                    $ventaPrima['metodo_pago'],
                    -abs(floatval($ventaPrima['monto_efectivo'])),
                    -abs(floatval($ventaPrima['monto_tarjeta'])),
                    -abs(floatval($ventaPrima['monto_abonado'] ?? $ventaPrima['total'])),
                    -abs(floatval($ventaPrima['monto_recibido'] ?? 0))
                ]);
                $primaRevertida = true;
            }
        }

        $pdo->commit();

        $mensajeExito = "Contrato #{$contratoId} anulado con éxito.";
        if ($montoARestaurar > 0) {
            $mensajeExito .= " Se restituyeron L. " . number_format($montoARestaurar, 2) . " al límite de crédito del cliente.";
        }
        if ($primaRevertida) {
            $mensajeExito .= " Se anuló la prima cobrada y se restó del Arqueo de caja.";
        }

        header("Location: ../views/anular_prestamo.php?msg=" . urlencode($mensajeExito));
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: ../views/anular_prestamo.php?contrato_id={$contratoId}&err=" . urlencode($e->getMessage()));
        exit();
    }
}