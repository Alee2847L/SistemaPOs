<?php
// views/arqueo.php
 // 1. INICIAR LA SESIÓN PRIMERO QUE TODO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. VALIDAR QUE EXISTA LA SESIÓN DEL USUARIO ANTES DE NADA
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// 3. VALIDAR QUE LA EMPRESA TENGA CONTRATADO EL MÓDULO DE COTIZACIONES
$modulos_permitidos = $_SESSION['modulos_activos'] ?? [];

if (!in_array('arqueo', $modulos_permitidos)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Acceso denegado: El módulo de Cotizaciones no está incluido en el plan de su empresa.'
    ]);
    exit; // Detiene la ejecución por completo
}
require_once '../config/conexion.php';

// Validar que sea administrador
if (!isset($_SESSION['usuario_rol']) || (strtolower($_SESSION['usuario_rol']) !== 'admin' && strtolower($_SESSION['usuario_rol']) !== 'administrador')) {
    die("Acceso denegado. Solo los administradores pueden ver este módulo.");
}

$mensaje = "";
$mostrarModalDiferencia = false;
$datosDiferencia = [];

$ventasHoy = [];
$recaudosHoy = [];

try {
    // 1. Obtener ventas abiertas del POS
    $stmtVentas = $pdo->query("
        SELECT v.id_transaccion as id,
               CASE WHEN v.tipo_comprobante = 'Prima de Préstamo' THEN 'PRIMA' ELSE 'VENTA' END as tipo,
               v.cliente_codigo_bp, v.total, v.monto_efectivo, v.monto_tarjeta, v.fecha_venta as fecha, v.metodo_pago, v.cambio_entregado, u.nombre as cajero
        FROM ventas v
        JOIN usuarios u ON v.usuario_id = u.id
        WHERE v.estado_caja = 'abierta' OR v.estado_caja IS NULL
    ");
    $ventasHoy = $stmtVentas->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener transacciones de recaudo pendientes del turno abierto (con desglose real de efectivo y tarjeta)
    $stmtRecaudos = $pdo->query("
        SELECT tr.id as id, 'RECAUDO' as tipo,
               COALESCE(c.codigo_bp, 'BP000') as cliente_codigo_bp,
               tr.monto_total as total,
               tr.monto_efectivo as monto_efectivo,
               tr.monto_tarjeta as monto_tarjeta,
               tr.fecha as fecha,
               tr.tipo_pago as metodo_pago,
               0 as cambio_entregado,
               COALESCE(u.nombre, 'Sistema') as cajero
        FROM transacciones_recaudo tr
        LEFT JOIN contratos co ON tr.contrato_id = co.id
        LEFT JOIN clientes c ON co.codigo_bp = c.codigo_bp
        LEFT JOIN usuarios u ON tr.usuario_id = u.id
        WHERE tr.estado_caja = 'abierta' OR tr.estado_caja IS NULL
    ");
    $recaudosHoy = $stmtRecaudos->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $mensaje = "<div class='mb-4 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs sm:text-sm font-medium'>Error en consulta SQL: " . $e->getMessage() . "</div>";
}

// Unificar ambos arreglos en una sola lista para el arqueo
$transaccionesHoy = array_merge($ventasHoy, $recaudosHoy);

$granTotalSistema = 0;
$totalEfectivoSistema = 0;
$totalTarjetaSistema = 0;

foreach ($transaccionesHoy as $t) {
    $granTotalSistema += floatval($t['total']);
    $totalEfectivoSistema += floatval($t['monto_efectivo'] ?? 0);
    $totalTarjetaSistema  += floatval($t['monto_tarjeta'] ?? 0);
}

// 2.5 PRÉSTAMOS DEL DÍA — Panel puramente informativo (NO afecta el efectivo/tarjeta
// esperado en caja, porque el dinero de un préstamo nunca ingresa físicamente a la
// caja registradora). Sirve para que el administrador pueda cuadrar/contar cuántos
// préstamos se otorgaron y por cuánto en el día, y cuántas anulaciones hubo.
$prestamosHoyCantidad = 0;
$prestamosHoyTotal = 0.0;
$anulacionesHoyCantidad = 0;
$anulacionesHoyTotal = 0.0;
// NOTA: se filtra por estado_caja (igual que ventas/transacciones_recaudo), no por
// la fecha del día, para que este panel se reinicie exactamente cuando se cierra la
// caja (y no a medianoche). Si el cajero abre un turno que cruza la medianoche, los
// préstamos de ese turno se siguen contando hasta que se haga el cierre.
try {
    $stmtPrestamosHoy = $pdo->query("
        SELECT COUNT(*) AS cantidad, COALESCE(SUM(monto_financiar), 0) AS total
        FROM contratos
        WHERE tipo_contrato = 'prestamo' AND (estado_caja = 'abierta' OR estado_caja IS NULL)
    ");
    $rowPrestamosHoy = $stmtPrestamosHoy->fetch(PDO::FETCH_ASSOC);
    $prestamosHoyCantidad = (int)($rowPrestamosHoy['cantidad'] ?? 0);
    $prestamosHoyTotal = (float)($rowPrestamosHoy['total'] ?? 0);
} catch (Exception $e) {
    // Si la tabla/columna no existe todavía, simplemente no se muestra el dato.
}
try {
    $stmtAnulHoy = $pdo->query("
        SELECT COUNT(*) AS cantidad, COALESCE(SUM(monto_restaurado), 0) AS total
        FROM anulaciones_prestamo
        WHERE (estado_caja = 'abierta' OR estado_caja IS NULL)
    ");
    $rowAnulHoy = $stmtAnulHoy->fetch(PDO::FETCH_ASSOC);
    $anulacionesHoyCantidad = (int)($rowAnulHoy['cantidad'] ?? 0);
    $anulacionesHoyTotal = (float)($rowAnulHoy['total'] ?? 0);
} catch (Exception $e) {
    // La tabla anulaciones_prestamo podría no existir aún; se omite el dato.
}

// 3. PROCESAR CIERRE DE CAJA
if (isset($_POST['accion']) && ($_POST['accion'] === 'hacer_cierre' || $_POST['accion'] === 'forzar_cierre')) {
    try {
        $denominaciones = [
            1   => intval($_POST['billete_1'] ?? 0),
            2   => intval($_POST['billete_2'] ?? 0),
            3   => intval($_POST['billete_3'] ?? 0),
            5   => intval($_POST['billete_5'] ?? 0),
            10  => intval($_POST['billete_10'] ?? 0),
            20  => intval($_POST['billete_20'] ?? 0),
            50  => intval($_POST['billete_50'] ?? 0),
            100 => intval($_POST['billete_100'] ?? 0),
            200 => intval($_POST['billete_200'] ?? 0),
            500 => intval($_POST['billete_500'] ?? 0),
        ];

        $totalEfectivoContado = 0;
        foreach ($denominaciones as $denom => $cantidadBilletes) {
            $totalEfectivoContado += ($denom * $cantidadBilletes);
        }

        $totalTarjetaContado = floatval($_POST['monto_tarjetas'] ?? 0);
        $totalGeneralContado = $totalEfectivoContado + $totalTarjetaContado;

        $difEfectivo = $totalEfectivoContado - $totalEfectivoSistema;
        $difTarjeta = $totalTarjetaContado - $totalTarjetaSistema;

        if (($_POST['accion'] === 'hacer_cierre') && (round($difEfectivo, 2) != 0 || round($difTarjeta, 2) != 0)) {
            $mostrarModalDiferencia = true;
            $datosDiferencia = [
                'efectivo_contado' => $totalEfectivoContado,
                'efectivo_sistema' => $totalEfectivoSistema,
                'dif_efectivo'     => $difEfectivo,
                'tarjeta_contado'  => $totalTarjetaContado,
                'tarjeta_sistema'  => $totalTarjetaSistema,
                'dif_tarjeta'      => $difTarjeta,
                'post_data'        => $_POST
            ];
        } else {
            $pdo->beginTransaction();

            $cantidadTransacciones = count($transaccionesHoy);
            $hayPrestamosPendientes = ($prestamosHoyCantidad > 0 || $anulacionesHoyCantidad > 0);
            if ($cantidadTransacciones === 0 && !$hayPrestamosPendientes) {
                throw new Exception("No hay transacciones, recaudos ni préstamos pendientes de cierre para procesar.");
            }

            $stmtCierre = $pdo->prepare("INSERT INTO cierres_caja (usuario_id, total_ventas, cantidad_transacciones, fecha_cierre, detalle_transacciones) VALUES (?, ?, ?, NOW(), ?)");
            $stmtCierre->execute([
                $_SESSION['usuario_id'],
                $totalGeneralContado,
                $cantidadTransacciones,
                json_encode($transaccionesHoy)
            ]);

            // Actualizar estados a 'cerrada' tanto en ventas como en recaudos
            $pdo->query("UPDATE ventas SET estado_caja = 'cerrada' WHERE estado_caja = 'abierta' OR estado_caja IS NULL");
            $pdo->query("UPDATE transacciones_recaudo SET estado_caja = 'cerrada' WHERE estado_caja = 'abierta' OR estado_caja IS NULL");

            // Lo mismo para el panel informativo de préstamos/anulaciones: al cerrar
            // caja, el conteo del día vuelve a 0 hasta el próximo préstamo/anulación.
            try {
                $pdo->query("UPDATE contratos SET estado_caja = 'cerrada' WHERE tipo_contrato = 'prestamo' AND (estado_caja = 'abierta' OR estado_caja IS NULL)");
            } catch (Exception $e) { /* columna/tabla aún no migrada; se ignora */ }
            try {
                $pdo->query("UPDATE anulaciones_prestamo SET estado_caja = 'cerrada' WHERE (estado_caja = 'abierta' OR estado_caja IS NULL)");
            } catch (Exception $e) { /* columna/tabla aún no migrada; se ignora */ }

            $pdo->commit();
            $mensaje = "<div class='mb-4 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs sm:text-sm font-medium'>¡Cierre de caja realizado con éxito! Total registrado: L. " . number_format($totalGeneralContado, 2) . "</div>";

            $transaccionesHoy = [];
            $granTotalSistema = 0;
            $totalEfectivoSistema = 0;
            $totalTarjetaSistema = 0;

            // Reflejar el reinicio del panel de préstamos en esta misma respuesta,
            // sin esperar a que se recargue la página (esas variables ya se habían
            // leído de la BD más arriba, antes del UPDATE de este cierre).
            $prestamosHoyCantidad = 0;
            $prestamosHoyTotal = 0.0;
            $anulacionesHoyCantidad = 0;
            $anulacionesHoyTotal = 0.0;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $mensaje = "<div class='mb-4 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs sm:text-sm font-medium'>Error al cerrar caja: " . $e->getMessage() . "</div>";
    }
}

$transacciones = $transaccionesHoy;
$totalVentasDia = $granTotalSistema;

$historialCierres = $pdo->query("SELECT c.*, u.nombre as admin_cierra FROM cierres_caja c JOIN usuarios u ON c.usuario_id = u.id ORDER BY c.fecha_cierre DESC");

// --- OBTENER EL NOMBRE DE LA EMPRESA DESDE LA BD ---
$nombre_empresa = "INVERSIONES J.A"; // Valor por defecto
try {
    // Si tu variable de conexión usa otro nombre (ej. $conn), cámbiala aquí
    $stmt_config = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1");
    if ($row_config = $stmt_config->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row_config['nombre_empresa'])) {
            $nombre_empresa = htmlspecialchars($row_config['nombre_empresa']);
        }
    }
} catch (Exception $e) {
    // Si ocurre algún error o la tabla no existe, se mantiene el valor por defecto
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arqueo y Cierre de Caja — <?php echo $nombre_empresa; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family: 'Inter', sans-serif; }</style>
    <script>
    function calcularTotalEfectivo() {
        const denominaciones = [1, 2, 3, 5, 10, 20, 50, 100, 200, 500];
        let sumaEfectivo = 0;

        denominaciones.forEach(d => {
            let cantidad = parseFloat(document.getElementById('billete_' + d).value) || 0;
            sumaEfectivo += (d * cantidad);
        });

        let tarjetas = parseFloat(document.getElementById('monto_tarjetas').value) || 0;
        let totalGeneral = sumaEfectivo + tarjetas;

        document.getElementById('txt_suma_efectivo').innerText = 'L. ' + sumaEfectivo.toFixed(2);
        document.getElementById('txt_suma_general').innerText = 'L. ' + totalGeneral.toFixed(2);
    }

    function abrirModalConteo() {
        document.getElementById('modal-conteo-caja').classList.remove('hidden');
        document.getElementById('modal-conteo-caja').classList.add('flex');
        calcularTotalEfectivo();
    }

    function cerrarModalConteo() {
        document.getElementById('modal-conteo-caja').classList.add('hidden');
        document.getElementById('modal-conteo-caja').classList.remove('flex');
    }

    function reimprimirCierre(idCierre, fecha, admin, total, transaccionesJson) {
        let ventanaImpresion = window.open('', '_blank', 'width=800,height=600');
        let htmlTransacciones = '';
        let transacciones = [];
        try { transacciones = JSON.parse(transaccionesJson); } catch(e) { transacciones = []; }

        transacciones.forEach(t => {
            let clienteBP = t.cliente_codigo_bp || 'BP000';
            let cajeroNombre = t.cajero || 'N/A';
            let tipoTransaccion = t.tipo || 'VENTA';
            let mEfectivo = parseFloat(t.monto_efectivo || 0).toFixed(2);
            let mTarjeta = parseFloat(t.monto_tarjeta || 0).toFixed(2);
            let totalVal = parseFloat(t.total || 0);
            let claseMonto = totalVal < 0 ? 'color: red; font-weight: bold;' : '';
            let textoMonto = totalVal < 0 ? '- L. ' + Math.abs(totalVal).toFixed(2) : 'L. ' + totalVal.toFixed(2);

            htmlTransacciones += `
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">[${tipoTransaccion}] #${t.id}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">${cajeroNombre}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">${clienteBP}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">Efec: L. ${mEfectivo} | Tarj: L. ${mTarjeta}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right; ${claseMonto}">${textoMonto}</td>
                </tr>`;
        });

        let contenido = `<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Reporte de Cierre #${idCierre}</title><style>body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }.header { text-align: center; margin-bottom: 20px; }.header h2 { margin: 0; font-size: 18px; }.header p { margin: 2px 0; color: #666; }.info-box { margin-bottom: 20px; background: #f9f9f9; padding: 10px; border-radius: 5px; }table { width: 100%; border-collapse: collapse; margin-top: 10px; }th { background: #333; color: #fff; padding: 8px; text-align: left; font-size: 11px; }.total-section { text-align: right; margin-top: 15px; font-size: 14px; font-weight: bold; }</style></head><body><div class="header"><h2>INVERSIONES J.A</h2><p>Reporte Histórico de Cierre de Caja</p></div><div class="info-box"><p><strong>ID de Cierre:</strong> #${idCierre}</p><p><strong>Fecha:</strong> ${fecha}</p><p><strong>Administrador:</strong> ${admin}</p></div><h3>Transacciones y Recaudos Incluidos</h3><table><thead><tr><th>Tipo/ID</th><th>Cajero</th><th>Código BP</th><th>Desglose Pago</th><th style="text-align: right;">Total</th></tr></thead><tbody>${htmlTransacciones}</tbody></table><div class="total-section">Total Declarado en Cierre: L. ${parseFloat(total).toFixed(2)}</div><script>window.onload = function() { window.print(); window.close(); }<\/script></body></html>`;
        ventanaImpresion.document.write(contenido);
        ventanaImpresion.document.close();
    }

    window.addEventListener('DOMContentLoaded', () => {
        calcularTotalEfectivo();
    });
    </script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col selection:bg-blue-500 selection:text-white">

    <!-- MODAL DE CONTEO FÍSICO DE CAJA -->
    <div id="modal-conteo-caja" class="<?php echo $mostrarModalDiferencia ? 'hidden' : 'hidden'; ?> fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm px-4 overflow-y-auto py-6">
        <div class="bg-white p-6 rounded-2xl shadow-xl max-w-lg w-full">
            <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                    <i class="fa-solid fa-calculator text-amber-500"></i> Conteo Físico para Cierre de Caja
                </h3>
                <button onclick="cerrarModalConteo()" class="text-slate-400 hover:text-slate-600 font-bold">✕</button>
            </div>

            <form method="POST" id="form-conteo-caja">
                <input type="hidden" name="accion" value="hacer_cierre">

                <p class="text-xs text-slate-500 mb-4">Ingrese la cantidad de piezas/billetes de efectivo contados y el total de los vouchers de tarjetas.</p>

                <div class="grid grid-cols-2 gap-3 mb-4 max-h-[50vh] overflow-y-auto pr-2">
                    <?php
                    $denominacionesLista = [500, 200, 100, 50, 20, 10, 5, 3, 2, 1];
                    foreach($denominacionesLista as $denim):
                        $valAnterior = $datosDiferencia['post_data']['billete_' . $denim] ?? '0';
                    ?>
                    <div class="flex items-center justify-between bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                        <span class="text-xs font-bold text-slate-700">L. <?php echo $denim; ?></span>
                        <input type="number" min="0" value="<?php echo htmlspecialchars($valAnterior); ?>" name="billete_<?php echo $denim; ?>" id="billete_<?php echo $denim; ?>" oninput="calcularTotalEfectivo()" class="w-20 bg-white border border-slate-300 rounded-lg px-2 py-1 text-right text-xs font-semibold focus:outline-none focus:border-blue-500">
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-4 bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <label class="block text-xs font-bold text-slate-700 mb-1">Monto Total en Tarjetas (L.)</label>
                    <?php $tarjetasAnterior = $datosDiferencia['post_data']['monto_tarjetas'] ?? '0.00'; ?>
                    <input type="number" step="0.01" min="0" value="<?php echo htmlspecialchars($tarjetasAnterior); ?>" name="monto_tarjetas" id="monto_tarjetas" oninput="calcularTotalEfectivo()" class="w-full bg-white border border-slate-300 rounded-lg px-3 py-1.5 text-right text-sm font-bold text-blue-600 focus:outline-none focus:border-blue-500">
                </div>

                <div class="bg-blue-50 border border-blue-200 p-3 rounded-xl mb-4 text-xs flex justify-between items-center">
                    <div>
                        <p class="text-blue-900 font-semibold">Efectivo Contado: <span id="txt_suma_efectivo">L. 0.00</span></p>
                        <p class="text-blue-900 font-bold mt-0.5">Total General: <span id="txt_suma_general">L. 0.00</span></p>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="button" onclick="cerrarModalConteo()" class="w-1/2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 rounded-xl transition text-xs">Cancelar</button>
                    <button type="submit" class="w-1/2 bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2.5 rounded-xl transition text-xs shadow-sm">Enviar Cierre</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DE DIFERENCIA ENCONTRADA -->
    <?php if ($mostrarModalDiferencia): ?>
    <div id="modal-diferencia" class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4">
        <div class="bg-white p-6 rounded-2xl shadow-2xl max-w-md w-full border border-amber-200 text-center">
            <i class="fa-solid fa-triangle-exclamation text-amber-500 text-4xl mb-3"></i>
            <h3 class="text-lg font-bold text-slate-900">¡Diferencia Detectada en el Cierre!</h3>
            <p class="text-xs text-slate-500 mt-1 mb-4">Los valores contados no coinciden exactamente con los montos reales registrados por el sistema.</p>

            <div class="bg-slate-50 p-3.5 rounded-xl text-left text-xs space-y-2 mb-6 border border-slate-200">
                <div class="flex justify-between">
                    <span class="text-slate-600">Efectivo en Sistema:</span>
                    <span class="font-bold">L. <?php echo number_format($datosDiferencia['efectivo_sistema'], 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">Efectivo Contado:</span>
                    <span class="font-bold">L. <?php echo number_format($datosDiferencia['efectivo_contado'], 2); ?></span>
                </div>
                <div class="flex justify-between text-rose-600 font-bold border-t border-slate-200 pt-1">
                    <span>Diferencia Efectivo:</span>
                    <span>L. <?php echo number_format($datosDiferencia['dif_efectivo'], 2); ?></span>
                </div>
                <div class="flex justify-between border-t border-slate-200 pt-2">
                    <span class="text-slate-600">Tarjetas en Sistema:</span>
                    <span class="font-bold">L. <?php echo number_format($datosDiferencia['tarjeta_sistema'], 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">Tarjetas Contadas:</span>
                    <span class="font-bold">L. <?php echo number_format($datosDiferencia['tarjeta_contado'], 2); ?></span>
                </div>
                <div class="flex justify-between text-rose-600 font-bold border-t border-slate-200 pt-1">
                    <span>Diferencia Tarjetas:</span>
                    <span>L. <?php echo number_format($datosDiferencia['dif_tarjeta'], 2); ?></span>
                </div>
            </div>

            <div class="flex gap-3">
                <button onclick="document.getElementById('modal-diferencia').style.display='none'; document.getElementById('modal-conteo-caja').classList.remove('hidden'); document.getElementById('modal-conteo-caja').classList.add('flex'); calcularTotalEfectivo();" class="w-1/2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-2.5 rounded-xl transition text-xs">
                    Modificar
                </button>
                <form method="POST" class="w-1/2">
                    <?php foreach($datosDiferencia['post_data'] as $key => $val): ?>
                        <input type="hidden" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($val); ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="accion" value="forzar_cierre">
                    <button type="submit" class="w-full bg-rose-600 hover:bg-rose-700 text-white font-bold py-2.5 rounded-xl transition text-xs shadow-sm">
                        Cerrar caja
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Barra de Navegación -->
    <header class="bg-white border-b border-slate-200 px-4 sm:px-8 py-3.5 flex justify-between items-center sticky top-0 z-50 shadow-xs">
        <div class="flex items-center space-x-3">
            <div class="flex items-center gap-2">
                <div class="bg-yellow-600 text-white p-2 rounded-xl shadow-sm">
                    <i class="fa-solid fa-vault text-sm"></i>
                </div>
                <div>
                    <span class="font-bold text-sm sm:text-base tracking-tight text-slate-900 block leading-none">Módulo de Arqueo y Cierre de Caja</span>
                    <span class="text-[11px] text-slate-400 font-medium"><?php echo $nombre_empresa; ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Contenido Principal -->
    <main class="max-w-[1400px] w-full mx-auto px-4 sm:px-6 py-6 sm:py-8 flex-grow flex flex-col gap-6">
        <div>
            <h3 class="font-bold text-slate-900 text-lg sm:text-xl">Control de Caja, Ventas y Recaudos</h3>
            <p class="text-slate-500 text-xs sm:text-sm mt-0.5">Gestión de turnos, arqueos en tiempo real y cierre de caja histórico.</p>
        </div>

        <?php echo $mensaje; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <h5 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Ingresos Netos Acumulados (Turno Actual)</h5>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-emerald-600 mb-2">L. <?php echo number_format($totalVentasDia, 2); ?></h2>
                    <p class="text-xs sm:text-sm text-slate-600 mb-2">Efectivo esperado en caja (Ventas + Recaudos netos de devoluciones): <strong class="text-slate-900">L. <?php echo number_format($totalEfectivoSistema, 2); ?></strong></p>
                    <p class="text-xs sm:text-sm text-slate-600 mb-6">Tarjetas esperadas: <strong class="text-slate-900">L. <?php echo number_format($totalTarjetaSistema, 2); ?></strong></p>
                </div>

                <button type="button" onclick="abrirModalConteo()" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-semibold text-xs sm:text-sm py-3 px-4 rounded-xl transition shadow-sm flex items-center justify-center gap-2">
                    <i class="fa-solid fa-lock"></i> Ejecutar Cierre de Caja
                </button>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <h5 class="font-bold text-slate-900 text-sm sm:text-base mb-4 pb-3 border-b border-slate-100 flex items-center gap-2">
                        <i class="fa-solid fa-circle-info text-blue-600"></i> Información del Turno
                    </h5>
                    <ul class="space-y-2 text-xs sm:text-sm text-slate-600">
                        <li><strong>Empresa:</strong> INVERSIONES J.A</li>
                        <li><strong>Administrador Activo:</strong> <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Administrador'); ?></li>
                        <li><strong>Fecha Actual:</strong> <?php echo date('Y-m-d H:i:s'); ?></li>
                    </ul>
                </div>
                <div class="pt-4 mt-4 border-t border-slate-100">
                    <button class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs sm:text-sm py-2.5 px-4 rounded-xl transition flex items-center justify-center gap-2" onclick="window.print()">
                        <i class="fa-solid fa-print"></i> Imprimir Vista Actual
                    </button>
                </div>
            </div>
        </div>

        <!-- Panel informativo: Préstamos del Día (NO afecta el Arqueo de Caja) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <h4 class="font-bold text-slate-900 text-base mb-1 flex items-center gap-2">
                <i class="fa-solid fa-hand-holding-dollar text-violet-600"></i> Préstamos del Día
            </h4>
            <p class="text-slate-500 text-xs mb-4">Solo informativo: estos montos <strong>no</strong> se suman ni se restan del efectivo/tarjetas esperado en caja, porque el desembolso de un préstamo no pasa físicamente por la caja registradora.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-violet-50 border border-violet-200 rounded-xl p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-violet-500 mb-1">Préstamos Otorgados Hoy</p>
                    <p class="text-xl font-extrabold text-violet-700"><?php echo $prestamosHoyCantidad; ?> <span class="text-sm font-medium text-violet-500">contrato(s)</span></p>
                    <p class="text-sm text-violet-700 mt-1">Monto financiado total: <strong>L. <?php echo number_format($prestamosHoyTotal, 2); ?></strong></p>
                </div>
                <div class="bg-teal-50 border border-teal-200 rounded-xl p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-teal-500 mb-1">Anulaciones de Préstamo Hoy</p>
                    <p class="text-xl font-extrabold text-teal-700"><?php echo $anulacionesHoyCantidad; ?> <span class="text-sm font-medium text-teal-500">contrato(s)</span></p>
                    <p class="text-sm text-teal-700 mt-1">Monto restituido a crédito: <strong>L. <?php echo number_format($anulacionesHoyTotal, 2); ?></strong></p>
                </div>
            </div>
        </div>

        <!-- Tabla detallada de transacciones y recaudos actuales -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <h4 class="font-bold text-slate-900 text-base mb-4">Detalle de Ventas, Recaudos y Devoluciones (Turno Activo)</h4>
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-900 text-white text-[11px] font-semibold tracking-wider">
                            <th class="py-3.5 px-4">Tipo / ID</th>
                            <th class="py-3.5 px-4">Cajero / Usuario</th>
                            <th class="py-3.5 px-4">Código BP</th>
                            <th class="py-3.5 px-4">Efectivo (L.)</th>
                            <th class="py-3.5 px-4">Tarjeta (L.)</th>
                            <th class="py-3.5 px-4">Fecha</th>
                            <th class="py-3.5 px-4">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-xs sm:text-sm text-slate-700 bg-white">
                        <?php if(count($transacciones) > 0): ?>
                            <?php foreach($transacciones as $t):
                                $esDevolucion = floatval($t['total']) < 0;
                            ?>
                            <tr class="hover:bg-slate-50/80 transition <?php echo $esDevolucion ? 'bg-rose-50/40' : ''; ?>">
                                <td class="py-3 px-4 font-semibold">
                                    <?php if(($t['tipo'] ?? 'VENTA') === 'RECAUDO'): ?>
                                        <?php if($esDevolucion): ?>
                                            <span class="bg-rose-100 text-rose-700 px-2 py-0.5 rounded-md text-[10px] font-bold">ANULACIÓN</span> #<?php echo $t['id']; ?>
                                        <?php else: ?>
                                            <span class="bg-purple-100 text-purple-700 px-2 py-0.5 rounded-md text-[10px] font-bold">RECAUDO</span> #<?php echo $t['id']; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded-md text-[10px] font-bold">VENTA</span> #<?php echo $t['id']; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-slate-600"><?php echo htmlspecialchars($t['cajero'] ?? 'N/A'); ?></td>
                                <td class="py-3 px-4 text-slate-600"><?php echo htmlspecialchars($t['cliente_codigo_bp'] ?? 'BP000'); ?></td>
                                <td class="py-3 px-4 text-slate-600 font-medium">L. <?php echo number_format($t['monto_efectivo'] ?? 0, 2); ?></td>
                                <td class="py-3 px-4 text-slate-600 font-medium">L. <?php echo number_format($t['monto_tarjeta'] ?? 0, 2); ?></td>
                                <td class="py-3 px-4 text-slate-600"><?php echo $t['fecha']; ?></td>
                                <td class="py-3 px-4 font-bold <?php echo $esDevolucion ? 'text-rose-600' : 'text-slate-900'; ?>">
                                    <?php echo $esDevolucion ? '- L. ' . number_format(abs(floatval($t['total'])), 2) : 'L. ' . number_format(floatval($t['total']), 2); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-slate-400 py-6 text-sm">No hay ventas ni recaudos pendientes en este turno. Caja en L. 0.00</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Historial de Cierres Anteriores para Reimpresión -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <h4 class="font-bold text-slate-900 text-base mb-4">Historial de Cierres de Caja (Reimpresión)</h4>
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[11px] font-semibold tracking-wider">
                            <th class="py-3.5 px-4">ID Cierre</th>
                            <th class="py-3.5 px-4">Administrador</th>
                            <th class="py-3.5 px-4">Fecha de Cierre</th>
                            <th class="py-3.5 px-4">Registros</th>
                            <th class="py-3.5 px-4">Total Declarado</th>
                            <th class="py-3.5 px-4 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-xs sm:text-sm text-slate-700 bg-white">
                        <?php while($c = $historialCierres->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3 px-4 font-semibold text-slate-900">#<?php echo $c['id']; ?></td>
                            <td class="py-3 px-4 text-slate-600"><?php echo htmlspecialchars($c['admin_cierra']); ?></td>
                            <td class="py-3 px-4 text-slate-600"><?php echo $c['fecha_cierre']; ?></td>
                            <td class="py-3 px-4 text-slate-600"><?php echo $c['cantidad_transacciones']; ?></td>
                            <td class="py-3 px-4 font-bold text-slate-900">L. <?php echo number_format($c['total_ventas'], 2); ?></td>
                            <td class="py-3 px-4 text-center">
                                <button class="bg-sky-50 hover:bg-sky-100 text-sky-700 font-semibold text-xs px-3 py-1.5 rounded-xl transition flex items-center gap-1 mx-auto"
                                    onclick="reimprimirCierre(
                                        '<?php echo $c['id']; ?>',
                                        '<?php echo $c['fecha_cierre']; ?>',
                                        '<?php echo htmlspecialchars($c['admin_cierra'], ENT_QUOTES); ?>',
                                        '<?php echo $c['total_ventas']; ?>',
                                        '<?php echo htmlspecialchars($c['detalle_transacciones'], ENT_QUOTES); ?>'
                                    )">
                                    <i class="fa-solid fa-print text-[10px]"></i> Reimprimir Cierre
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>