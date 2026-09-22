<?php
// api/prestamos.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$accion = $_GET['accion'] ?? '';

// 1. Listar contratos con filtro de fechas
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'listar') {
    try {
        $fechaInicio = $_GET['fecha_inicio'] ?? '';
        $fechaFin    = $_GET['fecha_fin'] ?? '';

        $sql = "
            SELECT c.*, cl.Nombre as cliente_nombre 
            FROM contratos c
            LEFT JOIN clientes cl ON c.codigo_bp = cl.codigo_bp
            WHERE 1=1
        ";
        $params = [];

        if (!empty($fechaInicio)) {
            $sql .= " AND DATE(c.fecha_inicio) >= ?";
            $params[] = $fechaInicio;
        }

        if (!empty($fechaFin)) {
            $sql .= " AND DATE(c.fecha_inicio) <= ?";
            $params[] = $fechaFin;
        }

        $sql .= " ORDER BY c.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $contratos]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 1.5 Listar cuotas de un contrato específico
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'ver_cuotas') {
    $contrato_id = intval($_GET['contrato_id'] ?? 0);
    try {
        $stmt_c = $pdo->prepare("
            SELECT c.*, cl.Nombre as cliente_nombre, cl.rtn_dni as cliente_dni, cl.Telefono as cliente_telefono
            FROM contratos c
            LEFT JOIN clientes cl ON c.codigo_bp = cl.codigo_bp
            WHERE c.id = ?
        ");
        $stmt_c->execute([$contrato_id]);
        $contrato = $stmt_c->fetch(PDO::FETCH_ASSOC);

        if (!$contrato) {
            echo json_encode(['success' => false, 'message' => 'Contrato no encontrado']);
            exit;
        }

        $stmt_cuotas = $pdo->prepare("
            SELECT * FROM cuotas_contrato 
            WHERE contrato_id = ? 
            ORDER BY numero_cuota ASC
        ");
        $stmt_cuotas->execute([$contrato_id]);
        $cuotas = $stmt_cuotas->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true, 
            'contrato' => $contrato, 
            'cuotas' => $cuotas
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 1.6 Contratos de un cliente específico (con sus cuotas)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'contratos_por_cliente') {
    $codigo_bp = trim($_GET['codigo_bp'] ?? '');
    
    if (empty($codigo_bp)) {
        echo json_encode(['success' => false, 'message' => 'Código BP requerido']);
        exit;
    }

    try {
        // Solo contratos ACTIVOS
        $stmt = $pdo->prepare("
            SELECT * FROM contratos 
            WHERE codigo_bp = ? AND estado = 'ACTIVO'
            ORDER BY id DESC
        ");
        $stmt->execute([$codigo_bp]);
        $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Para cada contrato, traer sus cuotas
        foreach ($contratos as &$contrato) {
            $stmtCuotas = $pdo->prepare("
                SELECT * FROM cuotas_contrato 
                WHERE contrato_id = ? 
                ORDER BY numero_cuota ASC
            ");
            $stmtCuotas->execute([$contrato['id']]);
            $contrato['cuotas'] = $stmtCuotas->fetchAll(PDO::FETCH_ASSOC);

            // Calcular cuota promedio
            if (count($contrato['cuotas']) > 0) {
                $contrato['cuota_promedio'] = $contrato['cuotas'][0]['monto_cuota'];
            } else {
                $contrato['cuota_promedio'] = $contrato['plazo_meses'] > 0 
                    ? ($contrato['total_credito'] / $contrato['plazo_meses']) 
                    : 0;
            }
        }

        echo json_encode(['success' => true, 'data' => $contratos]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 1.7 Verificar si un cliente está en mora (cuotas vencidas sin pagar), usado
// tanto por el formulario de nuevo préstamo como por pos.php antes de ofrecer
// la modalidad de crédito.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'verificar_mora') {
    $codigo_bp = trim($_GET['codigo_bp'] ?? '');
    if (empty($codigo_bp)) {
        echo json_encode(['success' => false, 'message' => 'Código BP requerido']);
        exit;
    }
    try {
        require_once __DIR__ . '/mora_helper.php';
        $mora = clienteTieneMora($pdo, $codigo_bp);
        echo json_encode(array_merge(['success' => true], $mora));
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 1.8 Verificar si un cliente tiene una prima de préstamo pendiente de cobro
// (contrato activo, con prima > 0 y todavía no cobrada en POS). Lo usa pos.php
// al seleccionar el cliente, para ofrecer cobrarla ahí mismo como venta normal.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'verificar_prima_pendiente') {
    $codigo_bp = trim($_GET['codigo_bp'] ?? '');
    if (empty($codigo_bp)) {
        echo json_encode(['success' => false, 'message' => 'Código BP requerido']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT id, prima
            FROM contratos
            WHERE codigo_bp = ?
              AND tipo_contrato = 'prestamo'
              AND estado = 'ACTIVO'
              AND prima > 0
              AND prima_venta_id IS NULL
            ORDER BY fecha_inicio DESC
            LIMIT 1
        ");
        $stmt->execute([$codigo_bp]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode([
                'success' => true,
                'tiene_prima_pendiente' => true,
                'contrato_id' => (int)$row['id'],
                'monto' => (float)$row['prima']
            ]);
        } else {
            echo json_encode(['success' => true, 'tiene_prima_pendiente' => false]);
        }
    } catch (Exception $e) {
        // Si la columna prima_venta_id aún no existe (falta la migración), no se
        // rompe el flujo del POS: simplemente se reporta que no hay prima pendiente.
        echo json_encode(['success' => true, 'tiene_prima_pendiente' => false]);
    }
    exit;
}

// 2. Guardar nuevo contrato (préstamo)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $codigo_bp = $input['codigo_bp'] ?? '';
    $producto_descripcion = $input['producto_descripcion'] ?? '';
    $total_factura = floatval($input['total_factura'] ?? 0);
    $prima = floatval($input['prima'] ?? 0);
    $monto_financiar = floatval($input['monto_financiar'] ?? 0);
    $porcentaje_interes = floatval($input['porcentaje_interes'] ?? 0);
    $total_credito = floatval($input['total_credito'] ?? 0);
    $numero_cuotas = intval($input['numero_cuotas'] ?? 1);
    $frecuencia = $input['frecuencia'] ?? 'mensual';

    // Fecha del primer pago: por defecto, 15 días después de hoy. El usuario puede elegir otra.
    $fecha_primer_pago_input = trim($input['fecha_primer_pago'] ?? '');
    if ($fecha_primer_pago_input !== '') {
        $partesFecha = DateTime::createFromFormat('Y-m-d', $fecha_primer_pago_input);
        if (!$partesFecha || $partesFecha->format('Y-m-d') !== $fecha_primer_pago_input) {
            echo json_encode(['success' => false, 'message' => 'La fecha del primer pago no es válida.']);
            exit;
        }
        $fechaPrimerPago = $partesFecha;
    } else {
        $fechaPrimerPago = new DateTime('+15 days');
    }

    // La fecha del primer pago no puede elegirse a más de 40 días desde hoy.
    $fechaMaximaPrimerPago = new DateTime('+40 days');
    if ($fechaPrimerPago > $fechaMaximaPrimerPago) {
        echo json_encode([
            'success' => false,
            'message' => 'La fecha del primer pago no puede ser mayor a 40 días desde hoy (máximo: ' . $fechaMaximaPrimerPago->format('Y-m-d') . ').'
        ]);
        exit;
    }

    if (empty($codigo_bp) || $monto_financiar <= 0) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos.']);
        exit;
    }

    // La prima (si la hay) NO se cobra aquí: queda marcada como "pendiente de cobro"
    // en el contrato (prima_venta_id = NULL) hasta que el cajero la cobre en el POS
    // como una venta normal (efectivo/tarjeta), momento en que procesar_venta.php
    // vincula contratos.prima_venta_id con esa venta.

    // Validación de mora: no se otorgan préstamos nuevos a un cliente que ya
    // tenga cuotas vencidas sin pagar en un contrato activo.
    require_once __DIR__ . '/mora_helper.php';
    $mora = clienteTieneMora($pdo, $codigo_bp);
    if ($mora['en_mora']) {
        echo json_encode([
            'success' => false,
            'message' => "No se puede otorgar el préstamo: el cliente tiene {$mora['cantidad_cuotas_vencidas']} cuota(s) en mora (vencida(s) y sin pagar). Debe ponerse al día antes de procesar un nuevo préstamo.",
            'en_mora' => true
        ]);
        exit;
    }

    $contratoParaPlan = null;   // se llena solo si el contrato se guardó bien

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO contratos (codigo_bp, producto_descripcion, total_factura, prima, monto_financiar, porcentaje_interes, total_credito, plazo_meses, fecha_inicio, estado, tipo_contrato)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'ACTIVO', 'prestamo')
        ");
        $stmt->execute([
            $codigo_bp,
            $producto_descripcion,
            $total_factura,
            $prima,
            $monto_financiar,
            $porcentaje_interes,
            $total_credito,
            $numero_cuotas
        ]);
        
        $contrato_id = $pdo->lastInsertId();

        // Redondeo a centavos: cada cuota se calcula a 2 decimales y la última
        // absorbe la diferencia, para que la suma cuadre exacto con total_credito.
        $monto_cuota_base = $numero_cuotas > 0 ? round($total_credito / $numero_cuotas, 2) : round($total_credito, 2);

        $stmtCuota = $pdo->prepare("
            INSERT INTO cuotas_contrato (contrato_id, numero_cuota, monto_cuota, fecha_vencimiento, estado)
            VALUES (?, ?, ?, ?, 'PENDIENTE')
        ");

        // Las fechas de vencimiento se calculan a partir de la fecha del PRIMER pago elegida
        // (no desde "hoy"): la cuota 1 vence en $fechaPrimerPago, y las siguientes se espacian
        // según la frecuencia. Para "mensual" se ajusta el día si el mes destino es más corto
        // (ej. un primer pago el 31 no puede caer en un 31 de un mes de 30 días).
        $anioBase = (int)$fechaPrimerPago->format('Y');
        $mesBase  = (int)$fechaPrimerPago->format('n');
        $diaBase  = (int)$fechaPrimerPago->format('j');

        $sumaCuotas = 0.0;
        for ($i = 1; $i <= $numero_cuotas; $i++) {
            if ($frecuencia === 'mensual') {
                $mes  = $mesBase + ($i - 1);
                $anio = $anioBase + intdiv($mes - 1, 12);
                $mes  = (($mes - 1) % 12) + 1;
                $ultimoDiaMes = (int)(new DateTime("$anio-$mes-01"))->format('t');
                $dia = min($diaBase, $ultimoDiaMes);
                $fechaVencimiento = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
            } else if ($frecuencia === 'quincenal') {
                $dias = ($i - 1) * 15;
                $fechaVencimiento = (clone $fechaPrimerPago)->modify("+$dias days")->format('Y-m-d');
            } else {
                $dias = ($i - 1) * 7;
                $fechaVencimiento = (clone $fechaPrimerPago)->modify("+$dias days")->format('Y-m-d');
            }

            if ($i < $numero_cuotas) {
                $monto_cuota = $monto_cuota_base;
            } else {
                // Última cuota: lo que falte para llegar exacto al total_credito
                $monto_cuota = round($total_credito - $sumaCuotas, 2);
            }
            $sumaCuotas += $monto_cuota;

            $stmtCuota->execute([$contrato_id, $i, $monto_cuota, $fechaVencimiento]);
        }

        // La prima (contratos.prima, si es > 0) queda pendiente de cobro:
        // contratos.prima_venta_id se queda en NULL hasta que se cobre en POS.

        $pdo->commit();
        // El plan de pagos por correo solo se envía de inmediato si el contrato NO tiene
        // prima (nada que cobrar antes). Si tiene prima, queda pendiente de cobro en POS,
        // y es procesar_venta.php quien envía el plan justo cuando esa prima se cobra.
        if ($prima <= 0) {
            $contratoParaPlan = (int)$contrato_id;
        }
        echo json_encode(['success' => true, 'message' => 'Préstamo y cuotas registradas con éxito']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
    }

    // Enviar el plan de pagos al cliente por correo (solo cuando no hay prima pendiente).
    // Ya se respondió al usuario: si el servidor lo permite (PHP-FPM) se cierra la respuesta
    // antes de enviar, así la pantalla no espera al SMTP. Si el correo falla, el contrato queda igual.
    if ($contratoParaPlan) {
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        require_once __DIR__ . '/enviar_plan_pagos.php';
        enviarPlanPagosPorCorreo($pdo, $contratoParaPlan);
    }
    exit;
}