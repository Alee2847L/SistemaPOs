<?php
// api/cobros.php — Módulo de Cobros: lista (solo lectura) de clientes con
// cuotas en mora o que vencen hoy, para que el cobrador priorice sus visitas.
// El cobro real de la cuota se sigue haciendo en el módulo de Recaudo; aquí
// no se modifica nada en la base de datos.
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/mora_calculo_helper.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$modulos_permitidos = $_SESSION['modulos_activos'] ?? [];
if (!in_array('cobros', $modulos_permitidos)) {
    echo json_encode([
        'success' => false,
        'message' => 'Acceso denegado: el módulo de Cobros no está incluido en el plan de su empresa.'
    ]);
    exit;
}

$rolUsuario = $_SESSION['usuario_rol'] ?? 'vendedor';
if (!in_array($rolUsuario, ['admin', 'administrador', 'cobrador'], true)) {
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para ver este módulo.']);
    exit;
}

$accion = $_GET['accion'] ?? '';

// --- 1. LISTAR CLIENTES CON CUOTAS EN MORA O QUE VENCEN HOY ---
if ($accion === 'listar') {
    // hoy       -> solo cuotas que vencen exactamente hoy
    // atrasadas -> solo cuotas ya vencidas (antes de hoy)
    // proximos  -> cuotas que todavía no vencen, dentro de los próximos N días
    //              (para priorizar visitas antes de que caigan en mora)
    // todos     -> en mora + vencen hoy (por defecto; no incluye "próximos")
    $filtro = $_GET['filtro'] ?? 'todos';
    $hoy = date('Y-m-d');
    $diasProximos = max(1, min(30, (int)($_GET['dias'] ?? 7))); // ventana configurable, 7 días por defecto

    $paramsFecha = [$hoy];
    if ($filtro === 'hoy') {
        $condicionFecha = 'cu.fecha_vencimiento = ?';
    } elseif ($filtro === 'atrasadas') {
        $condicionFecha = 'cu.fecha_vencimiento < ?';
    } elseif ($filtro === 'proximos') {
        $condicionFecha = 'cu.fecha_vencimiento > ? AND cu.fecha_vencimiento <= DATE_ADD(?, INTERVAL ' . $diasProximos . ' DAY)';
        $paramsFecha = [$hoy, $hoy];
    } else {
        $condicionFecha = 'cu.fecha_vencimiento <= ?';
    }

    // Un cobrador solo ve los clientes que le fueron asignados, más los que
    // todavía no se le han asignado a nadie (cobrador_asignado IS NULL). Un
    // administrador ve la cartera completa, para tener una vista general.
    $filtroCobrador = '';
    $paramsCobrador = [];
    if ($rolUsuario === 'cobrador') {
        $filtroCobrador = ' AND (c.cobrador_asignado = ? OR c.cobrador_asignado IS NULL) ';
        $paramsCobrador[] = $_SESSION['usuario_id'];
    }

    try {
        $sql = "SELECT c.codigo_bp, c.Nombre, c.Telefono, c.Direccion, c.cobrador_asignado,
                       cu.id AS cuota_id, cu.numero_cuota, cu.monto_cuota, cu.fecha_vencimiento
                  FROM clientes c
                  JOIN contratos con ON con.codigo_bp = c.codigo_bp AND con.estado = 'ACTIVO'
                  JOIN cuotas_contrato cu ON cu.contrato_id = con.id
                       AND cu.estado = 'PENDIENTE'
                       AND {$condicionFecha}
                       {$filtroCobrador}
                 ORDER BY c.Nombre ASC, cu.fecha_vencimiento ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($paramsFecha, $paramsCobrador));
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error en la consulta: ' . $e->getMessage()]);
        exit;
    }

    // Se agrupa por cliente: un cobrador necesita una lista de personas a
    // visitar, no una lista de cuotas sueltas. La "cuota exigible" de cada
    // cliente es la suma de todas sus cuotas pendientes en mora/hoy, cada
    // una con su propio recargo por mora (mismo cálculo que Recaudo/Clientes/Portal).
    $configMora = obtenerConfigMoraDiaria($pdo);
    $clientesPorBp = [];

    foreach ($filas as $f) {
        $bp = $f['codigo_bp'];
        if (!isset($clientesPorBp[$bp])) {
            $clientesPorBp[$bp] = [
                'codigo_bp'             => $bp,
                'nombre'                => $f['Nombre'],
                'telefono'              => $f['Telefono'],
                'direccion'             => $f['Direccion'],
                'cobrador_asignado'     => $f['cobrador_asignado'],
                'cuotas_pendientes'     => 0,
                'cuota_exigible_total'  => 0.0,
                'dias_mora_max'         => 0,
                'dias_para_vencer_min'  => null, // solo aplica al filtro "proximos"
                'fecha_mas_antigua'     => $f['fecha_vencimiento'],
                'tiene_atrasadas'       => false,
                'tiene_hoy'             => false,
                'tiene_proximas'        => false,
            ];
        }

        $montoCuota    = (float)$f['monto_cuota'];
        $montoMora     = calcularMontoMora($montoCuota, $f['fecha_vencimiento'], $configMora, $hoy);
        $montoExigible = round($montoCuota + $montoMora, 2);
        $diasMora      = calcularDiasMora($f['fecha_vencimiento'], $hoy);
        // Días que faltan para vencer (positivo = todavía no vence). Se usa para
        // el filtro "Próximos a vencer": la cuota más cercana define la prioridad.
        $diasParaVencer = (int)round((strtotime($f['fecha_vencimiento']) - strtotime($hoy)) / 86400);

        $clientesPorBp[$bp]['cuotas_pendientes']++;
        $clientesPorBp[$bp]['cuota_exigible_total'] += $montoExigible;

        if ($diasMora > $clientesPorBp[$bp]['dias_mora_max']) {
            $clientesPorBp[$bp]['dias_mora_max'] = $diasMora;
        }
        if ($f['fecha_vencimiento'] < $clientesPorBp[$bp]['fecha_mas_antigua']) {
            $clientesPorBp[$bp]['fecha_mas_antigua'] = $f['fecha_vencimiento'];
        }
        if ($diasMora > 0) {
            $clientesPorBp[$bp]['tiene_atrasadas'] = true;
        } elseif ($diasParaVencer <= 0) {
            $clientesPorBp[$bp]['tiene_hoy'] = true;
        } else {
            $clientesPorBp[$bp]['tiene_proximas'] = true;
            if ($clientesPorBp[$bp]['dias_para_vencer_min'] === null || $diasParaVencer < $clientesPorBp[$bp]['dias_para_vencer_min']) {
                $clientesPorBp[$bp]['dias_para_vencer_min'] = $diasParaVencer;
            }
        }
    }

    foreach ($clientesPorBp as &$c) {
        $c['cuota_exigible_total'] = round($c['cuota_exigible_total'], 2);
    }
    unset($c);

    echo json_encode([
        'success'                => true,
        'data'                   => array_values($clientesPorBp),
        'mora_diaria_activa'     => $configMora['activa'],
        'mora_diaria_porcentaje' => $configMora['porcentaje'],
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);