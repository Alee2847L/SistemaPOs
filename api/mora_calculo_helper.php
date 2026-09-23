<?php
// api/mora_calculo_helper.php
// Calcula el recargo por mora diaria de una cuota vencida, cuando la empresa
// (una fila de `configuracion` por base de datos) lo tiene activado. Ver
// migracion_mora_diaria.sql. Si no está activado, todo esto da 0 y no cambia
// nada del comportamiento de siempre.
//
// Regla de negocio:
//   - Si la cuota vence HOY o todavía no vence: 0 días de mora, sin recargo
//     (no se cobra mora el mismo día que vence la cuota).
//   - Si la cuota venció ANTES de hoy: días de mora = hoy - fecha_vencimiento,
//     recargo = monto_cuota * (porcentaje_diario / 100) * días_mora.

if (!function_exists('obtenerConfigMoraDiaria')) {
    function obtenerConfigMoraDiaria(PDO $pdo): array {
        try {
            $row = $pdo->query("SELECT mora_diaria_activa, mora_diaria_porcentaje FROM configuracion LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            return [
                'activa' => !empty($row['mora_diaria_activa']),
                'porcentaje' => (float)($row['mora_diaria_porcentaje'] ?? 0),
            ];
        } catch (Throwable $e) {
            // Falta la migración (columnas mora_diaria_*) o algo falló: se trata
            // como si la mora diaria estuviera apagada, para no romper nada.
            return ['activa' => false, 'porcentaje' => 0.0];
        }
    }
}

if (!function_exists('calcularDiasMora')) {
    function calcularDiasMora(string $fechaVencimiento, ?string $fechaHoy = null): int {
        $hoy = $fechaHoy ?? date('Y-m-d');
        $dias = (strtotime($hoy) - strtotime($fechaVencimiento)) / 86400;
        return max((int)round($dias), 0);
    }
}

if (!function_exists('calcularMontoMora')) {
    /** Recargo por mora (0.00 si no aplica) de UNA cuota, redondeado a centavos. */
    function calcularMontoMora(float $montoCuota, string $fechaVencimiento, array $configMora, ?string $fechaHoy = null): float {
        if (empty($configMora['activa']) || (float)($configMora['porcentaje'] ?? 0) <= 0) {
            return 0.0;
        }
        $diasMora = calcularDiasMora($fechaVencimiento, $fechaHoy);
        if ($diasMora <= 0) {
            return 0.0;
        }
        return round($montoCuota * ((float)$configMora['porcentaje'] / 100) * $diasMora, 2);
    }
}