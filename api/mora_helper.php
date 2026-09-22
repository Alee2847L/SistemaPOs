<?php
// api/mora_helper.php
// Helper compartido para saber si un cliente tiene cuotas en mora antes de
// otorgarle un nuevo préstamo o una venta a crédito.
//
// Regla de negocio: se considera en mora si tiene al menos una cuota PENDIENTE
// (de un contrato ACTIVO) cuya fecha_vencimiento sea HOY o anterior. Si la
// próxima cuota que le toca pagar vence mañana o después, el cliente está al
// día y puede seguir financiando normalmente.

if (!function_exists('clienteTieneMora')) {
    function clienteTieneMora(PDO $pdo, string $codigo_bp): array {
        $stmt = $pdo->prepare("
            SELECT cu.id, cu.numero_cuota, cu.fecha_vencimiento, cu.monto_cuota, co.id AS contrato_id
            FROM cuotas_contrato cu
            INNER JOIN contratos co ON cu.contrato_id = co.id
            WHERE co.codigo_bp = ?
              AND co.estado = 'ACTIVO'
              AND cu.estado = 'PENDIENTE'
              AND cu.fecha_vencimiento <= CURDATE()
            ORDER BY cu.fecha_vencimiento ASC
        ");
        $stmt->execute([$codigo_bp]);
        $cuotasVencidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'en_mora' => count($cuotasVencidas) > 0,
            'cantidad_cuotas_vencidas' => count($cuotasVencidas),
            'cuotas' => $cuotasVencidas,
        ];
    }
}