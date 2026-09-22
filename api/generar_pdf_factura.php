<?php
// api/generar_pdf_factura.php
// Genera el PDF de una factura/venta (y su plan de pagos si es a crédito), reutilizando
// el mismo HTML que ya se usaba para el cuerpo del correo. Lo usan:
//   - api/enviar_comprobante_venta.php   (adjunto del correo)
//   - api/descargar_factura_pdf.php      (botón "Descargar PDF" / imprimir_factura.php)
//
// Uso:
//   require_once __DIR__ . '/generar_pdf_factura.php';
//   $pdfBinario = generarPdfFactura($pdo, $ventaId, $contratoId); // $contratoId opcional

require_once __DIR__ . '/enviar_comprobante_venta.php'; // reutiliza construirHtmlFactura() / construirHtmlPlanPagos() / nombreEmpresaParaCorreo()
require_once __DIR__ . '/../dompdf_vendor/autoload.php';

if (!function_exists('generarPdfFactura')) {
    /**
     * Devuelve el contenido binario del PDF, o null si la venta no existe.
     */
    function generarPdfFactura(PDO $pdo, int $ventaId, ?int $contratoId = null): ?string {
        $stmt = $pdo->prepare("SELECT * FROM ventas WHERE id_transaccion = ?");
        $stmt->execute([$ventaId]);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$venta) return null;

        $stmt = $pdo->prepare("
            SELECT dv.*, p.nombre AS nombre_producto
              FROM detalle_ventas dv
              LEFT JOIN productos p ON dv.producto_id = p.id
             WHERE dv.venta_id = ?
        ");
        $stmt->execute([$ventaId]);
        $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $empresa = nombreEmpresaParaCorreo($pdo);
        $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        // Si no nos pasaron el contrato explícitamente pero la venta fue a crédito,
        // intentamos localizar el contrato asociado (mismo cliente, mismo total y fecha).
        if (!$contratoId && !empty($venta['es_credito'])) {
            $stmtC = $pdo->prepare("
                SELECT id FROM contratos
                 WHERE codigo_bp = ? AND ABS(total_credito - ?) < 0.01
                 ORDER BY ABS(TIMESTAMPDIFF(SECOND, fecha_inicio, ?)) ASC
                 LIMIT 1
            ");
            $stmtC->execute([$venta['cliente_codigo_bp'], (float)($venta['total_credito'] ?? 0), $venta['fecha_venta'] ?? date('Y-m-d')]);
            $contratoId = (int)($stmtC->fetchColumn() ?: 0) ?: null;
        }

        $htmlFactura = construirHtmlFactura($venta, $detalles, $empresa);
        $htmlPlan    = $contratoId ? construirHtmlPlanPagos($pdo, $contratoId) : null;

        $numero = $venta['numero_factura'] ?: ('#' . $venta['id_transaccion']);

        $htmlCompleto = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Helvetica', Arial, sans-serif; color: #1e293b; }
                h2 { margin-bottom: 4px; }
            </style>
        </head>
        <body>
            <div style='text-align:center; margin-bottom:10px;'>
                <h1 style='margin:0; font-size:18px;'>{$h($empresa)}</h1>
                <p style='margin:2px 0; font-size:12px; color:#64748b;'>Comprobante {$h($numero)}</p>
            </div>
            {$htmlFactura}
            " . ($htmlPlan ?? '') . "
        </body>
        </html>";

        // Blindaje: dompdf usa internamente algunas construcciones que PHP 8.x marca
        // como "Deprecated". Si el servidor tiene display_errors activo, esos avisos
        // se imprimirían mezclados con el PDF/JSON de respuesta y romperían todo.
        // Bajamos temporalmente el nivel de errores y además atrapamos cualquier
        // salida accidental con un buffer, para que NUNCA se filtre nada aquí.
        $nivelErrorPrevio = error_reporting();
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
        ob_start();
        try {
            $dompdf = new Dompdf\Dompdf(['isRemoteEnabled' => false]);
            $dompdf->loadHtml($htmlCompleto, 'UTF-8');
            $dompdf->setPaper('letter', 'portrait');
            $dompdf->render();
            $pdfBinario = $dompdf->output();
        } finally {
            ob_end_clean(); // descarta cualquier aviso/advertencia que se haya impreso
            error_reporting($nivelErrorPrevio);
        }

        return $pdfBinario;
    }
}