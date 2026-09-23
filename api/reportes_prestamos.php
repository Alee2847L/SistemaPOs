<?php
// api/reportes_prestamos.php — Reporte de préstamos otorgados y ganancias
// generadas, agrupado por semana o por mes, para comparar períodos.
// Es un reporte de solo lectura (no modifica nada); se reutiliza el módulo
// "prestamos" y se restringe a administradores.
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$modulos_permitidos = $_SESSION['modulos_activos'] ?? [];
if (!in_array('prestamos', $modulos_permitidos)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado: El módulo de Préstamos no está incluido en el plan de su empresa.']);
    exit;
}

$rolUsuario = strtolower($_SESSION['usuario_rol'] ?? 'vendedor');
if ($rolUsuario !== 'admin' && $rolUsuario !== 'administrador') {
    echo json_encode(['success' => false, 'message' => 'Solo los administradores pueden ver este reporte.']);
    exit;
}

$accion = $_GET['accion'] ?? '';

if ($accion === 'resumen') {
    // agrupar_por: 'semana' (lunes de cada semana) o 'mes' (día 1 de cada mes).
    // Se valida contra una lista fija: nunca se concatena texto del usuario tal
    // cual dentro del SQL.
    $agruparPor = ($_GET['agrupar_por'] ?? 'mes') === 'semana' ? 'semana' : 'mes';

    $hoy = date('Y-m-d');
    $fechaInicio = trim($_GET['fecha_inicio'] ?? '');
    $fechaFin    = trim($_GET['fecha_fin'] ?? '');

    // Por defecto, el año en curso (para poder comparar meses/semanas de este año).
    if (empty($fechaInicio)) {
        $fechaInicio = date('Y-01-01');
    }
    if (empty($fechaFin)) {
        $fechaFin = $hoy;
    }

    $expresionPeriodo = ($agruparPor === 'semana')
        ? "DATE_SUB(fecha_inicio, INTERVAL WEEKDAY(fecha_inicio) DAY)" // lunes de esa semana
        : "DATE_FORMAT(fecha_inicio, '%Y-%m-01')";                     // día 1 de ese mes

    try {
        $sql = "SELECT
                    {$expresionPeriodo} AS periodo_inicio,
                    COUNT(*) AS cantidad_prestamos,
                    SUM(monto_financiar) AS total_financiado,
                    SUM(total_credito) AS total_con_interes,
                    SUM(CASE WHEN estado != 'CANCELADO' THEN GREATEST(total_credito - monto_financiar, 0) ELSE 0 END) AS ganancia_generada,
                    SUM(CASE WHEN estado = 'CANCELADO' THEN 1 ELSE 0 END) AS cantidad_anulados
                FROM contratos
                WHERE fecha_inicio BETWEEN ? AND ?
                GROUP BY periodo_inicio
                ORDER BY periodo_inicio ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fechaInicio, $fechaFin]);
        $periodos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($periodos as &$p) {
            $p['cantidad_prestamos']  = (int)$p['cantidad_prestamos'];
            $p['total_financiado']    = round((float)$p['total_financiado'], 2);
            $p['total_con_interes']   = round((float)$p['total_con_interes'], 2);
            $p['ganancia_generada']   = round((float)$p['ganancia_generada'], 2);
            $p['cantidad_anulados']   = (int)$p['cantidad_anulados'];
        }
        unset($p);

        // Totales del rango completo, para las tarjetas de resumen arriba del gráfico.
        $totales = [
            'cantidad_prestamos' => array_sum(array_column($periodos, 'cantidad_prestamos')),
            'total_financiado'   => round(array_sum(array_column($periodos, 'total_financiado')), 2),
            'ganancia_generada'  => round(array_sum(array_column($periodos, 'ganancia_generada')), 2),
            'cantidad_anulados'  => array_sum(array_column($periodos, 'cantidad_anulados')),
        ];

        echo json_encode([
            'success'      => true,
            'agrupar_por'  => $agruparPor,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
            'periodos'     => $periodos,
            'totales'      => $totales,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error en la consulta: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);