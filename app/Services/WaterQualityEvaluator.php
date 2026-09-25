<?php

namespace App\Services;

use App\Enums\WaterQualityStatus;
use App\Models\AlertRule;
use App\Models\Metric;
use Illuminate\Support\Collection;

/**
 * Clasifica una lectura de calidad del agua como normal, advertencia o crítica según los umbrales
 * de alerta configurados para la finca (App\Models\AlertRule).
 *
 * Esto es independiente del rango físico válido de Metric (min_value/max_value), que ya se exige
 * al guardar la lectura (App\Http\Requests\Production\StoreWaterQualityReadingRequest): aquello
 * descarta lecturas imposibles, esto clasifica lecturas ya válidas.
 *
 * Sin una regla configurada y habilitada para la finca y el parámetro, la lectura se considera normal.
 */
class WaterQualityEvaluator
{
    public function evaluate(Metric $metric, float $value, ?AlertRule $rule): WaterQualityStatus
    {
        if (! $rule || ! $rule->enabled) {
            return WaterQualityStatus::Normal;
        }

        if (($rule->crit_min !== null && $value <= (float) $rule->crit_min)
            || ($rule->crit_max !== null && $value >= (float) $rule->crit_max)) {
            return WaterQualityStatus::Critical;
        }

        if (($rule->warn_min !== null && $value <= (float) $rule->warn_min)
            || ($rule->warn_max !== null && $value >= (float) $rule->warn_max)) {
            return WaterQualityStatus::Warning;
        }

        return WaterQualityStatus::Normal;
    }

    /**
     * Busca la regla de un parámetro dentro de una colección ya cargada (las reglas de una finca),
     * para no ejecutar una consulta por cada lectura al mostrar un historial.
     *
     * @param  Collection<int, AlertRule>  $rules
     */
    public function ruleFor(Collection $rules, int $metricId): ?AlertRule
    {
        return $rules->firstWhere('metric_id', $metricId);
    }
}
