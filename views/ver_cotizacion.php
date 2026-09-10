<?php
// views/ver_cotizacion.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$cotizacion_id = intval($_GET['id'] ?? 0);

try {
    // 1. Obtener datos de la cotización
    $stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ?");
    $stmt->execute([$cotizacion_id]);
    $cot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cot) {
        die("Cotización no encontrada.");
    }

    // 2. Obtener detalles de la cotización
    $stmtDet = $pdo->prepare("SELECT * FROM cotizacion_detalles WHERE cotizacion_id = ?");
    $stmtDet->execute([$cotizacion_id]);
    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    // 3. Obtener órdenes de compra vinculadas y sus proveedores
    $stmtOc = $pdo->prepare("
        SELECT oc.*, p.nombre_empresa, p.telefono as prov_tel, p.correo as prov_correo, p.rtn as prov_rtn, p.direccion as prov_dir 
        FROM ordenes_compra oc
        JOIN proveedores p ON oc.proveedor_id = p.id
        WHERE oc.cotizacion_id = ?
    ");
    $stmtOc->execute([$cotizacion_id]);
    $ordenes_compra = $stmtOc->fetchAll(PDO::FETCH_ASSOC);

    // 4. Obtener nombre de la empresa desde configuración
    $nombre_empresa = "INVERSIONES J.A.";
    $stmt_cfg = $pdo->query("SELECT nombre_empresa FROM configuracion LIMIT 1");
    if ($row_cfg = $stmt_cfg->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row_cfg['nombre_empresa'])) {
            $nombre_empresa = $row_cfg['nombre_empresa'];
        }
    }

} catch (Exception $e) {
    die("Error al cargar la información: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización #<?php echo $cot['numero_cotizacion']; ?> - <?php echo $nombre_empresa; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { 
                background: white !important; 
                color: black !important; 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact; 
            }
            .max-w-4xl { 
                max-width: 100% !important; 
                width: 100% !important; 
                margin: 0 !important; 
                padding: 10px !important; 
                box-shadow: none !important;
                border: 1px solid #94a3b8 !important;
            }
            .page-break { 
                page-break-before: always; 
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased p-4 sm:p-8">

    <!-- BOTONERA SUPERIOR (No se imprime) -->
    <div class="max-w-4xl mx-auto mb-6 flex justify-between items-center no-print">
        <a href="cotizaciones.php" class="bg-white border border-slate-200 text-slate-700 px-4 py-2 rounded-xl text-sm font-semibold hover:bg-slate-50 transition shadow-xs">
            <i class="fa-solid fa-arrow-left me-1"></i> Volver al Módulo
        </a>
        
        <div class="flex gap-2">
            <?php if (empty($ordenes_compra)): ?>
            <button onclick="generarOrdenes(<?php echo $cotizacion_id; ?>)" class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-xl text-sm font-semibold transition shadow-md flex items-center gap-2">
                <i class="fa-solid fa-file-invoice-dollar"></i> Generar Órdenes de Compra
            </button>
            <?php endif; ?>

            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-xl text-sm font-semibold transition shadow-md flex items-center gap-2">
                <i class="fa-solid fa-print"></i> Imprimir / Guardar PDF
            </button>
        </div>
    </div>

    <!-- DOCUMENTO PRINCIPAL: COTIZACIÓN -->
    <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-200 p-8 sm:p-12 mb-8">
        
        <!-- Encabezado Factura/Cotización -->
        <div class="flex justify-between items-start border-b border-slate-200 pb-6 mb-6">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight"><?php echo $nombre_empresa; ?></h1>
                <p class="text-xs text-slate-500 mt-0.5">Construcción, Electricidad y Acabados</p>
            </div>
            <div class="text-right">
                <span class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide">COTIZACIÓN DE PROYECTO</span>
                <p class="text-xl font-bold text-slate-900 mt-2">#<?php echo $cot['numero_cotizacion']; ?></p>
                <p class="text-xs text-slate-500">Fecha: <?php echo date('d/m/Y', strtotime($cot['fecha_cotizacion'])); ?></p>
            </div>
        </div>

        <!-- Datos del Cliente y Proyecto -->
        <div class="grid grid-cols-2 gap-6 bg-slate-50 p-4 rounded-xl border border-slate-100 mb-6 text-sm">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase">Cliente:</p>
                <p class="font-bold text-slate-900 mt-0.5"><?php echo htmlspecialchars($cot['cliente_nombre']); ?></p>
                <p class="text-xs text-slate-600">RTN / DNI: <?php echo htmlspecialchars($cot['cliente_rtn'] ?: 'N/D'); ?></p>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase">Proyecto:</p>
                <p class="font-bold text-slate-900 mt-0.5"><?php echo htmlspecialchars($cot['proyecto_nombre']); ?></p>
                <p class="text-xs text-slate-600">Clasificación: <?php echo htmlspecialchars($cot['clasificacion_proyecto'] ?: 'General'); ?> | Área: <?php echo $cot['ancho']; ?> x <?php echo $cot['longitud']; ?> M2</p>
            </div>
        </div>

        <!-- Tabla de Detalles -->
        <div class="overflow-x-auto mb-6">
            <table class="w-full text-left border-collapse text-xs">
                <thead class="bg-slate-100 border-b border-slate-200 text-slate-700 uppercase font-semibold">
                    <tr>
                        <th class="py-2.5 px-3">Tipo</th>
                        <th class="py-2.5 px-3">Descripción</th>
                        <th class="py-2.5 px-3 text-center">Unidad</th>
                        <th class="py-2.5 px-3 text-center">Cant.</th>
                        <th class="py-2.5 px-3 text-right">Costo Unit.</th>
                        <th class="py-2.5 px-3 text-right">Total con Margen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($detalles as $det): 
                        $isMat = ($det['tipo_item'] === 'MATERIAL');
                    ?>
                    <tr>
                        <td class="py-3 px-3">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $isMat ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700'; ?>">
                                <?php echo $det['tipo_item']; ?>
                            </span>
                        </td>
                        <td class="py-3 px-3 font-medium text-slate-900"><?php echo htmlspecialchars($det['descripcion']); ?></td>
                        <td class="py-3 px-3 text-center text-slate-600"><?php echo htmlspecialchars($det['unidad']); ?></td>
                        <td class="py-3 px-3 text-center text-slate-600"><?php echo $det['cantidad']; ?></td>
                        <td class="py-3 px-3 text-right text-slate-600">L. <?php echo number_format($det['costo_unitario'], 2); ?></td>
                        <td class="py-3 px-3 text-right font-bold text-slate-900">L. <?php echo number_format($det['total_con_margen'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totales de la Cotización -->
        <div class="flex justify-end pt-4 border-t border-slate-200">
            <div class="w-72 space-y-1 text-right">
                <div class="flex justify-between text-xs text-slate-500">
                    <span>Subtotal Sin Margen:</span>
                    <span>L. <?php echo number_format($cot['subtotal_general'], 2); ?></span>
                </div>
                <div class="flex justify-between text-base font-extrabold text-slate-900 pt-2 border-t border-slate-100">
                    <span>Total General:</span>
                    <span class="text-blue-600">L. <?php echo number_format($cot['total_general'], 2); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- DOCUMENTOS ADJUNTOS: ÓRDENES DE COMPRA POR PROVEEDOR -->
    <?php foreach ($ordenes_compra as $oc): 
        // Obtener detalles de esta orden de compra específica de manera segura con ->
        $stmtOcDet = $pdo->prepare("SELECT * FROM orden_compra_detalles WHERE orden_compra_id = ?");
        $stmtOcDet->execute([$oc['id']]);
        $oc_detalles = $stmtOcDet->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-200 p-8 sm:p-12 mb-8 page-break">
        
        <div class="flex justify-between items-start border-b border-slate-200 pb-6 mb-6">
            <div>
                <span class="bg-amber-100 text-amber-800 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide">ORDEN DE COMPRA A PROVEEDOR</span>
                <h2 class="text-xl font-bold text-slate-900 mt-2">Orden: <?php echo $oc['numero_orden']; ?></h2>
                <p class="text-xs text-slate-500">Ref. Cotización: #<?php echo $cot['numero_cotizacion']; ?></p>
            </div>
            <div class="text-right">
                <p class="text-xs font-bold text-slate-400 uppercase">Proveedor:</p>
                <p class="font-bold text-slate-900"><?php echo htmlspecialchars($oc['nombre_empresa']); ?></p>
                <p class="text-xs text-slate-500">Tel: <?php echo htmlspecialchars($oc['prov_tel'] ?: 'N/D'); ?> | RTN: <?php echo htmlspecialchars($oc['prov_rtn'] ?: 'N/D'); ?></p>
            </div>
        </div>

        <div class="overflow-x-auto mb-6">
            <table class="w-full text-left border-collapse text-xs">
                <thead class="bg-slate-100 border-b border-slate-200 text-slate-700 uppercase font-semibold">
                    <tr>
                        <th class="py-2.5 px-3">Descripción de Material</th>
                        <th class="py-2.5 px-3 text-center">Unidad</th>
                        <th class="py-2.5 px-3 text-center">Cantidad Solicitada</th>
                        <th class="py-2.5 px-3 text-right">Costo Unitario</th>
                        <th class="py-2.5 px-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($oc_detalles as $mat): ?>
                    <tr>
                        <td class="py-3 px-3 font-medium text-slate-900"><?php echo htmlspecialchars($mat['descripcion']); ?></td>
                        <td class="py-3 px-3 text-center text-slate-600"><?php echo htmlspecialchars($mat['unidad']); ?></td>
                        <td class="py-3 px-3 text-center font-bold text-slate-900"><?php echo $mat['cantidad_solicitada']; ?></td>
                        <td class="py-3 px-3 text-right text-slate-600">L. <?php echo number_format($mat['costo_unitario'], 2); ?></td>
                        <td class="py-3 px-3 text-right font-bold text-slate-900">L. <?php echo number_format($mat['subtotal'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end pt-4 border-t border-slate-200">
            <div class="w-72 text-right">
                <div class="flex justify-between text-base font-extrabold text-slate-900">
                    <span>Total Orden de Compra:</span>
                    <span class="text-amber-600">L. <?php echo number_format($oc['total_orden'], 2); ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Script JavaScript para activar la generación manual -->
    <script>
    function generarOrdenes(id) {
        if (!confirm('¿Desea generar las órdenes de compra para los proveedores con los materiales de esta cotización?')) return;

        fetch(`../api/cotizaciones.php?accion=generar_ordenes&id=${id}`, {
            method: 'POST'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error de conexión con el servidor.');
        });
    }
    </script>
</body>
</html>