<?php
// api/procesar_venta.php
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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['carrito'])) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos del carrito o productos.']);
    exit;
}

$codigo_bp = trim($data['codigo_bp'] ?? 'BP000');

// Capturar el id_transaccion enviado desde el POS (o id_orden por compatibilidad)
$id_transaccion = isset($data['id_transaccion']) ? intval($data['id_transaccion']) : (isset($data['id_orden']) ? intval($data['id_orden']) : 0);

// Identificamos si es cotización, orden pendiente o venta normal
$esCotizacion = !empty($data['es_cotizacion']) ? true : false; 
$esPendiente = (isset($data['estado']) && $data['estado'] === 'pendiente') ? true : false;

$tipo_comprobante = $esCotizacion ? 'Cotización' : ($esPendiente ? 'Orden Pendiente' : trim($data['tipo_comprobante'] ?? 'Factura'));
$carrito = $data['carrito'];
$pagos = $data['pagos'] ?? [];
$ahorro_total = floatval($data['ahorro_total'] ?? 0);

// ========== DATOS DE CRÉDITO ==========
$esCredito          = !empty($data['es_credito']) || (!empty($data['plazo_meses']) && intval($data['plazo_meses']) > 0);
$prima              = floatval($data['prima'] ?? 0);
$plazo_meses        = intval($data['plazo_meses'] ?? 0);
$monto_financiar    = floatval($data['monto_financiar'] ?? 0);
$interes_total      = floatval($data['interes_total'] ?? 0);
$cuota_mensual      = floatval($data['cuota_mensual'] ?? 0);
$total_credito      = floatval($data['total_credito'] ?? 0);

if ($cuota_mensual <= 0 && $plazo_meses > 0 && $total_credito > 0) {
    $cuota_mensual = $total_credito / $plazo_meses;
}

try {
    // 1. Obtener datos del cliente
    $stmtCli = $pdo->prepare("SELECT Nombre, rtn_dni, limite_credito FROM clientes WHERE codigo_bp = ? LIMIT 1");
    $stmtCli->execute([$codigo_bp]);
    $cliente = $stmtCli->fetch(PDO::FETCH_ASSOC);

    $cliente_nombre = $cliente['Nombre'] ?? 'Consumidor Final';
    $cliente_rtn = $cliente['rtn_dni'] ?? '0000000000000';
    $cliente_identidad = $cliente['rtn_dni'] ?? '0000000000000';
    $limite_actual_cliente = floatval($cliente['limite_credito'] ?? 0);

    // 2. Calcular total de la venta
    $totalVenta = 0;
    foreach ($carrito as $p) {
        $precioOrig = floatval($p['precio']);
        $descUnit = floatval($p['descuento_unitario'] ?? 0);
        $precioFinal = max(0, $precioOrig - $descUnit);
        $totalVenta += ($precioFinal * intval($p['cantidad']));
    }

    // 3. Calcular montos de pagos
    $totalAbonado = 0;
    $montoRecibidoEfectivo = 0;
    $montoEfectivoBruto = 0;
    $montoTarjetaTotal = 0;
    $metodosUsados = [];

    foreach ($pagos as $p) {
        $monto = floatval($p['monto']);
        $totalAbonado += $monto;
        $metodoLower = strtolower(trim($p['metodo']));
        $metodosUsados[] = $p['metodo'];

        if ($metodoLower === 'efectivo') {
            $montoRecibidoEfectivo += $monto;
            $montoEfectivoBruto += $monto;
        } elseif ($metodoLower === 'tarjeta') {
            $montoTarjetaTotal += $monto;
        }
    }

    $metodoPrincipal = !empty($metodosUsados) ? implode(' / ', array_unique($metodosUsados)) : 'Pendiente';
    $cambio = $totalAbonado > $totalVenta ? ($totalAbonado - $totalVenta) : 0;
    $montoEfectivoTotal = max(0, $montoEfectivoBruto - $cambio);

    $pdo->beginTransaction();

    $numeroFacturaGenerado = null;
    $alertaRango = "";

    // Generar factura solo si NO es cotización ni orden pendiente
    if (!$esCotizacion && !$esPendiente) {
        $stmtConf = $pdo->prepare("SELECT prefijo_factura, siguiente_correlativo, rango_maximo FROM configuracion LIMIT 1");
        $stmtConf->execute();
        $config = $stmtConf->fetch(PDO::FETCH_ASSOC);

        if (!$config) {
            throw new Exception("No se encontró la configuración fiscal en el sistema.");
        }

        $prefijo = $config['prefijo_factura'] ?? '';
        $correlativoActual = (int) $config['siguiente_correlativo'];
        $rangoMaximo = (int) ($config['rango_maximo'] ?? 0);

        if ($rangoMaximo > 0 && $correlativoActual > $rangoMaximo) {
            throw new Exception("¡BLOQUEO FISCAL! Se ha alcanzado o superado el rango máximo autorizado por el SAR.");
        }

        $facturasRestantes = $rangoMaximo > 0 ? ($rangoMaximo - $correlativoActual) : 999999;
        if ($facturasRestantes <= 50 && $rangoMaximo > 0) {
            $alertaRango = " ⚠️ ¡ADVERTENCIA SAR! Quedan {$facturasRestantes} facturas disponibles.";
        }

        $correlativoFormateado = str_pad($correlativoActual, 8, "0", STR_PAD_LEFT);
        $bloqueFijo = !empty($prefijo) ? $prefijo : '000-001-01-';
        $numeroFacturaGenerado = $bloqueFijo . $correlativoFormateado;

        $nuevoCorrelativo = $correlativoActual + 1;
        $stmtUpdateConf = $pdo->prepare("UPDATE configuracion SET siguiente_correlativo = ? LIMIT 1");
        $stmtUpdateConf->execute([$nuevoCorrelativo]);
    }

    // ========== 4. Guardar / Actualizar en ventas ==========
    if ($id_transaccion > 0) {
        // UPDATE
        $sqlVenta = "UPDATE ventas SET  
                        numero_factura = COALESCE(:num_fac, numero_factura), 
                        cliente_codigo_bp = :cli_bp, 
                        cliente_identidad = :cli_ide,
                        cliente_nombre = :cli_nom, 
                        cliente_rtn = :cli_rtn, 
                        tipo_comprobante = :tip_com, 
                        total = :total_v, 
                        ahorro_total = :ahorro, 
                        metodo_pago = :met_pag, 
                        monto_efectivo = :m_efec,
                        monto_tarjeta = :m_tarj,
                        monto_abonado = :m_abon, 
                        monto_recibido = :m_rec, 
                        cambio_entregado = :cambio,
                        es_credito = :es_credito,
                        prima = :prima,
                        plazo_meses = :plazo,
                        monto_financiar = :m_fin,
                        interes_total = :interes,
                        cuota_mensual = :cuota,
                        total_credito = :t_credito,
                        fecha_venta = CASE WHEN :num_fac_not_null IS NOT NULL THEN NOW() ELSE fecha_venta END
                     WHERE id_transaccion = :id_trans";
        
        $stmtV = $pdo->prepare($sqlVenta);
        $stmtV->execute([
            'num_fac'          => $numeroFacturaGenerado,
            'cli_bp'           => $codigo_bp,
            'cli_ide'          => $cliente_identidad,
            'cli_nom'          => $cliente_nombre,
            'cli_rtn'          => $cliente_rtn,
            'tip_com'          => $tipo_comprobante,
            'total_v'          => $totalVenta,
            'ahorro'           => $ahorro_total,
            'met_pag'          => $metodoPrincipal,
            'm_efec'           => $montoEfectivoTotal,
            'm_tarj'           => $montoTarjetaTotal,
            'm_abon'           => $totalAbonado,
            'm_rec'            => $montoRecibidoEfectivo,
            'cambio'           => $cambio,
            'es_credito'       => $esCredito ? 1 : 0,
            'prima'            => $prima,
            'plazo'            => $plazo_meses,
            'm_fin'            => $monto_financiar,
            'interes'          => $interes_total,
            'cuota'            => $cuota_mensual,
            't_credito'        => $total_credito,
            'num_fac_not_null' => $numeroFacturaGenerado,
            'id_trans'         => $id_transaccion
        ]);

        $ventaId = $id_transaccion;

        $pdo->prepare("DELETE FROM detalle_ventas WHERE venta_id = ?")->execute([$ventaId]);
        $pdo->prepare("DELETE FROM pagos_ventas WHERE venta_id = ?")->execute([$ventaId]);

    } else {
        // INSERT
        $sqlVenta = "INSERT INTO ventas (
                        numero_factura, usuario_id, cliente_codigo_bp, cliente_identidad, 
                        cliente_nombre, cliente_rtn, tipo_comprobante, total, 
                        ahorro_total, metodo_pago, monto_efectivo, monto_tarjeta, 
                        monto_abonado, monto_recibido, cambio_entregado,
                        es_credito, prima, plazo_meses, monto_financiar, 
                        interes_total, cuota_mensual, total_credito, fecha_venta
                     ) VALUES (
                        :num_fac, :usu_id, :cli_bp, :cli_ide, 
                        :cli_nom, :cli_rtn, :tip_com, :total_v, 
                        :ahorro, :met_pag, :m_efec, :m_tarj, 
                        :m_abon, :m_rec, :cambio,
                        :es_credito, :prima, :plazo, :m_fin,
                        :interes, :cuota, :t_credito, NOW()
                     )";
        
        $stmtV = $pdo->prepare($sqlVenta);
        $stmtV->execute([
            'num_fac'     => $numeroFacturaGenerado,
            'usu_id'      => $usuarioId,
            'cli_bp'      => $codigo_bp,
            'cli_ide'     => $cliente_identidad,
            'cli_nom'     => $cliente_nombre,
            'cli_rtn'     => $cliente_rtn,
            'tip_com'     => $tipo_comprobante,
            'total_v'     => $totalVenta,
            'ahorro'      => $ahorro_total,
            'met_pag'     => $metodoPrincipal,
            'm_efec'      => $montoEfectivoTotal,
            'm_tarj'      => $montoTarjetaTotal,
            'm_abon'      => $totalAbonado,
            'm_rec'       => $montoRecibidoEfectivo,
            'cambio'      => $cambio,
            'es_credito'  => $esCredito ? 1 : 0,
            'prima'       => $prima,
            'plazo'       => $plazo_meses,
            'm_fin'       => $monto_financiar,
            'interes'     => $interes_total,
            'cuota'       => $cuota_mensual,
            't_credito'   => $total_credito
        ]);

        $ventaId = $pdo->lastInsertId();
    }

    // 5. Guardar Detalles y Descontar Stock
    $sqlDet = "INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, descuento_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)";
    $stmtDet = $pdo->prepare($sqlDet);

    if (!$esCotizacion && !$esPendiente) {
        $sqlStock = "UPDATE productos SET stock = GREATEST(0, stock - ?) WHERE id = ?";
        $stmtStock = $pdo->prepare($sqlStock);
    }

    foreach ($carrito as $prod) {
        $cant = intval($prod['cantidad']);
        $precioOrig = floatval($prod['precio']);
        $descUnit = floatval($prod['descuento_unitario'] ?? 0);
        $precioFinal = max(0, $precioOrig - $descUnit);
        $subtotal = $precioFinal * $cant;

        $stmtDet->execute([$ventaId, $prod['id'], $cant, $precioOrig, $descUnit, $subtotal]);

        if (!$esCotizacion && !$esPendiente) {
            $stmtStock->execute([$cant, $prod['id']]);
        }
    }

    // 6. Guardar Historial de Pagos
    if (!empty($pagos)) {
        $sqlPago = "INSERT INTO pagos_ventas (venta_id, metodo, monto, detalle, titular, digitos, voucher) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmtPago = $pdo->prepare($sqlPago);

        foreach ($pagos as $p) {
            $tarjeta = $p['detalles_tarjeta'] ?? null;
            $stmtPago->execute([
                $ventaId,
                $p['metodo'],
                floatval($p['monto']),
                $p['detalle'] ?? '',
                $tarjeta['titular'] ?? null,
                $tarjeta['digitos'] ?? null,
                $tarjeta['voucher'] ?? null
            ]);
        }
    }

    // 7. CREAR CONTRATO SOLO SI ES VENTA DEFINITIVA (NO pendiente ni cotización)
    if ($esCredito && !$esCotizacion && !$esPendiente) {
        $porcentaje_interes = floatval($data['porcentaje_interes'] ?? 0);
        $fecha_inicio = $data['fecha_inicio'] ?? date('Y-m-d');
        
        $nombres_prods = [];
        foreach ($carrito as $p) {
            $nombres_prods[] = $p['cantidad'] . 'x ' . ($p['nombre'] ?? 'Producto');
        }
        $producto_descripcion = implode(', ', $nombres_prods);

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
            $totalVenta,
            $prima,
            $monto_financiar,
            $porcentaje_interes,
            $total_credito,
            $plazo_meses,
            $fecha_inicio
        ]);

        $contrato_id = $pdo->lastInsertId();

        $monto_cuota = $plazo_meses > 0 ? ($total_credito / $plazo_meses) : $total_credito;
        
        $sqlCuota = "INSERT INTO cuotas_contrato (
                        contrato_id, numero_cuota, monto_cuota, fecha_vencimiento, monto_pagado, estado
                     ) VALUES (
                        ?, ?, ?, ?, 0.00, 'PENDIENTE'
                     )";
        $stmtCuota = $pdo->prepare($sqlCuota);

        for ($i = 1; $i <= $plazo_meses; $i++) {
            $fecha_vencimiento = date('Y-m-d', strtotime("+$i month", strtotime($fecha_inicio)));
            $stmtCuota->execute([
                $contrato_id,
                $i,
                $monto_cuota,
                $fecha_vencimiento
            ]);
        }

        // Restar del límite de crédito del cliente
        $sqlRestarLimite = "UPDATE clientes SET limite_credito = GREATEST(0, limite_credito - ?) WHERE codigo_bp = ?";
        $stmtRestar = $pdo->prepare($sqlRestarLimite);
        $stmtRestar->execute([$monto_financiar, $codigo_bp]);
    }

    $pdo->commit();

    if ($esCotizacion) {
        $msgExito = 'Cotización guardada con éxito.';
    } elseif ($esPendiente) {
        $msgExito = $id_transaccion > 0 ? "Orden #{$id_transaccion} actualizada con éxito." : 'Orden pendiente guardada con éxito.';
    } else {
        $msgExito = 'Venta, contrato, cuotas y actualización de límite de crédito registrados con éxito.' . $alertaRango;
    }

    echo json_encode([
        'success' => true, 
        'venta_id' => $ventaId, 
        'numero_factura' => $numeroFacturaGenerado,
        'alerta_sar' => $alertaRango,
        'message' => $msgExito
    ]);

    // Enviar comprobante (y plan de pagos si es crédito) por correo. Solo en venta definitiva,
    // nunca en cotización ni en orden pendiente. La respuesta ya se envió al POS (arriba);
    // si el servidor lo permite (PHP-FPM) se cierra la conexión antes de enviar el correo.
    if (!$esCotizacion && !$esPendiente) {
        $ventaParaCorreo    = $ventaId;
        $contratoParaCorreo = isset($contrato_id) ? (int)$contrato_id : null;

        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        require_once __DIR__ . '/enviar_comprobante_venta.php';
        enviarComprobanteVentaPorCorreo($pdo, $ventaParaCorreo, $contratoParaCorreo);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $e->getMessage()]);
}