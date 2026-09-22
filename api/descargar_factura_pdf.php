<?php
// api/descargar_factura_pdf.php
// Entrega la factura de una venta ya guardada como archivo PDF.
// Uso: descargar_factura_pdf.php?id=123           -> factura simple
//      descargar_factura_pdf.php?id=123&contrato=45 -> factura + plan de pagos
//      descargar_factura_pdf.php?id=123&descargar=1 -> fuerza la descarga (Content-Disposition: attachment)
//      Sin &descargar=1, se muestra dentro del navegador (para imprimir directo).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo 'No autorizado';
    exit;
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/generar_pdf_factura.php';

$ventaId    = (int)($_GET['id'] ?? 0);
$contratoId = isset($_GET['contrato']) ? (int)$_GET['contrato'] : null;
$forzarDescarga = !empty($_GET['descargar']);

if ($ventaId <= 0) {
    http_response_code(400);
    echo 'Falta el id de la venta';
    exit;
}

try {
    $pdfBinario = generarPdfFactura($pdo, $ventaId, $contratoId);

    if ($pdfBinario === null) {
        http_response_code(404);
        echo 'Venta no encontrada';
        exit;
    }

    $nombreArchivo = "Factura_{$ventaId}.pdf";
    $disposicion = $forzarDescarga ? 'attachment' : 'inline';

    header('Content-Type: application/pdf');
    header("Content-Disposition: {$disposicion}; filename=\"{$nombreArchivo}\"");
    header('Content-Length: ' . strlen($pdfBinario));
    echo $pdfBinario;
} catch (Throwable $e) {
    error_log('[descargar_factura_pdf] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo 'Error generando el PDF';
}