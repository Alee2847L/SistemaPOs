<?php
// api/procesar_venta.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
// Buffer de salida desde el inicio: permite cerrar la conexión con el navegador
// justo después de responder (antes de enviar el comprobante o el plan de pagos
// por correo) incluso si el servidor no usa PHP-FPM. Ver cerrar_conexion_http.php.
ob_start();

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

$carrito = $data['carrito'];

// Detectar si el carrito corresponde al cobro de una prima de préstamo pendiente
// (banner "Cobrar Prima" en pos.php, ver verificar_prima_pendiente en prestamos.php).
// Estas ventas son un "recibo interno": no consumen numeración fiscal SAR y no
// afectan inventario, así que no se pueden mezclar con productos normales.
$itemsPrimaPrestamo = array_values(array_filter($carrito, function ($it) {
    return !empty($it['es_prima_prestamo']);
}));
$esPrimaPrestamo = count($itemsPrimaPrestamo) > 0;

if ($esPrimaPrestamo && count($itemsPrimaPrestamo) < count($carrito)) {
    echo json_encode([
        'success' => false,
        'message' => 'No se puede combinar el cobro de una prima de préstamo con otros productos en la misma venta. Procésalos en ventas separadas.'
    ]);
    exit;
}

$tipo_comprobante = $esCotizacion
    ? 'Cotización'
    : ($esPendiente
        ? 'Orden Pendiente'
        : ($esPrimaPrestamo ? 'Prima de Préstamo' : trim($data['tipo_comprobante'] ?? 'Factura')));

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

    // Validación de mora: no se procesa una venta a crédito (nuevo contrato/cuotas)
    // si el cliente ya tiene cuotas vencidas sin pagar de un contrato activo.
    // Se valida ANTES de tocar factura/inventario para no consumir un número de
    // factura ni descontar stock en una venta que de todas formas se va a rechazar.
    if ($esCredito && !$esCotizacion && !$esPendiente) {
        require_once __DIR__ . '/mora_helper.php';
        $mora = clienteTieneMora($pdo, $codigo_bp);
        if ($mora['en_mora']) {
            echo json_encode([
                'success' => false,
                'message' => "No se puede procesar la venta a crédito: el cliente tiene {$mora['cantidad_cuotas_vencidas']} cuota(s) en mora (vencida(s) y sin pagar). Debe ponerse al día antes de financiar una nueva compra.",
                'en_mora' => true
            ]);
            exit;
        }
    }

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

    // Generar factura solo si NO es cotización, orden pendiente, ni cobro de prima
    // (la prima queda como recibo interno, sin numeración fiscal).
    if (!$esCotizacion && !$esPendiente && !$esPrimaPrestamo) {
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

    if (!$esCotizacion && !$esPendiente && !$esPrimaPrestamo) {
        $sqlStock = "UPDATE productos SET stock = GREATEST(0, stock - ?) WHERE id = ?";
        $stmtStock = $pdo->prepare($sqlStock);
    }

    foreach ($carrito as $prod) {
        // La prima de préstamo es un renglón especial (no es un producto real del
        // catálogo): no se guarda en detalle_ventas ni descuenta inventario.
        if (!empty($prod['es_prima_prestamo'])) {
            continue;
        }

        $cant = intval($prod['cantidad']);
        $precioOrig = floatval($prod['precio']);
        $descUnit = floatval($prod['descuento_unitario'] ?? 0);
        $precioFinal = max(0, $precioOrig - $descUnit);
        $subtotal = $precioFinal * $cant;

        $stmtDet->execute([$ventaId, $prod['id'], $cant, $precioOrig, $descUnit, $subtotal]);

        if (!$esCotizacion && !$esPendiente && !$esPrimaPrestamo) {
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

    // 7. CREAR CONTRATO SOLO SI ES VENTA DEFINITIVA (NO pendiente ni cotización ni prima)
    if ($esCredito && !$esCotizacion && !$esPendiente && !$esPrimaPrestamo) {
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

        // Redondeo a centavos: cada cuota se calcula a 2 decimales y la última
        // absorbe la diferencia, para que la suma cuadre exacto con total_credito.
        $monto_cuota_base = $plazo_meses > 0 ? round($total_credito / $plazo_meses, 2) : round($total_credito, 2);

        $sqlCuota = "INSERT INTO cuotas_contrato (
                        contrato_id, numero_cuota, monto_cuota, fecha_vencimiento, monto_pagado, estado
                     ) VALUES (
                        ?, ?, ?, ?, 0.00, 'PENDIENTE'
                     )";
        $stmtCuota = $pdo->prepare($sqlCuota);

        $sumaCuotas = 0.0;
        for ($i = 1; $i <= $plazo_meses; $i++) {
            $fecha_vencimiento = date('Y-m-d', strtotime("+$i month", strtotime($fecha_inicio)));

            if ($i < $plazo_meses) {
                $monto_cuota = $monto_cuota_base;
            } else {
                // Última cuota: lo que falte para llegar exacto al total_credito
                $monto_cuota = round($total_credito - $sumaCuotas, 2);
            }
            $sumaCuotas += $monto_cuota;

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

    // 7.5 Si esta venta corresponde al cobro de una prima de préstamo pendiente,
    // vincular la venta al contrato (contratos.prima_venta_id) para que quede
    // marcada como cobrada, se vea en Arqueo y no se pueda volver a cobrar.
    // El monto se revalida contra lo guardado en el contrato (no se confía en
    // lo que mandó el navegador), con tolerancia para no fallar por redondeo.
    $contratosPrimaCobradaAhora = [];
    if ($esPrimaPrestamo) {
        foreach ($itemsPrimaPrestamo as $itemPrima) {
            $contratoIdPrima = intval($itemPrima['contrato_id'] ?? 0);
            if ($contratoIdPrima <= 0) {
                throw new Exception("Falta el contrato de préstamo asociado a la prima que se está cobrando.");
            }

            $stmtContratoPrima = $pdo->prepare("SELECT id, prima, prima_venta_id, estado FROM contratos WHERE id = ? AND tipo_contrato = 'prestamo' FOR UPDATE");
            $stmtContratoPrima->execute([$contratoIdPrima]);
            $contratoPrima = $stmtContratoPrima->fetch(PDO::FETCH_ASSOC);

            if (!$contratoPrima) {
                throw new Exception("El contrato de préstamo #{$contratoIdPrima} no existe.");
            }
            if ($contratoPrima['estado'] !== 'ACTIVO') {
                throw new Exception("El contrato de préstamo #{$contratoIdPrima} ya no está activo; no se puede cobrar su prima.");
            }
            if (!empty($contratoPrima['prima_venta_id'])) {
                throw new Exception("La prima del contrato #{$contratoIdPrima} ya fue cobrada anteriormente (venta #{$contratoPrima['prima_venta_id']}). Vuelve a seleccionar el cliente para actualizar la información.");
            }

            $montoEsperado = floatval($contratoPrima['prima']);
            $montoCobrado = floatval($itemPrima['precio']);
            if (abs($montoEsperado - $montoCobrado) > 0.01) {
                throw new Exception("El monto de la prima del contrato #{$contratoIdPrima} cambió (esperado L. " . number_format($montoEsperado, 2) . "). Vuelve a seleccionar el cliente en el POS e intenta de nuevo.");
            }

            $pdo->prepare("UPDATE contratos SET prima_venta_id = ? WHERE id = ?")->execute([$ventaId, $contratoIdPrima]);
            $contratosPrimaCobradaAhora[] = $contratoIdPrima;
        }
    }

    $pdo->commit();

    if ($esCotizacion) {
        $msgExito = 'Cotización guardada con éxito.';
    } elseif ($esPendiente) {
        $msgExito = $id_transaccion > 0 ? "Orden #{$id_transaccion} actualizada con éxito." : 'Orden pendiente guardada con éxito.';
    } elseif ($esPrimaPrestamo) {
        $msgExito = 'Prima de préstamo cobrada con éxito. Queda registrada como recibo interno.';
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
    if (!$esCotizacion && !$esPendiente && !$esPrimaPrestamo) {
        $ventaParaCorreo    = $ventaId;
        $contratoParaCorreo = isset($contrato_id) ? (int)$contrato_id : null;

        require_once __DIR__ . '/cerrar_conexion_http.php';
        cerrarConexionHttpYContinuar();
        require_once __DIR__ . '/enviar_comprobante_venta.php';
        enviarComprobanteVentaPorCorreo($pdo, $ventaParaCorreo, $contratoParaCorreo);
    }

    // Si esta venta fue el cobro de una prima de préstamo pendiente, es hasta AHORA
    // (ya cobrada) que se envía el plan de pagos por correo al cliente — no se envía
    // cuando se creó el contrato, para no adelantar un plan que todavía no aplicaba.
    // (Este bloque y el de arriba son mutuamente excluyentes: $esPrimaPrestamo nunca es
    // true al mismo tiempo que la condición del bloque anterior, así que la conexión
    // solo se cierra una vez por petición.)
    if (!empty($contratosPrimaCobradaAhora)) {
        require_once __DIR__ . '/cerrar_conexion_http.php';
        cerrarConexionHttpYContinuar();
        require_once __DIR__ . '/enviar_plan_pagos.php';
        foreach ($contratosPrimaCobradaAhora as $contratoIdCobrado) {
            enviarPlanPagosPorCorreo($pdo, $contratoIdCobrado);
        }
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error en BD: ' . $e->getMessage()]);
}