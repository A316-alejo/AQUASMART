<?php

namespace App\Services;

use App\Enums\AlertStatus;
use App\Enums\WaterQualityStatus;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\WaterQualityReading;

/**
 * Convierte una medición de calidad del agua en una alerta persistente cuando corresponde.
 *
 * Reutiliza WaterQualityEvaluator y AlertRule: no vuelve a decidir umbrales, solo decide si,
 * dado el resultado ya calculado, hace falta dejar un registro gestionable en el Centro de alertas.
 */
class AlertGenerator
{
    public function __construct(private readonly WaterQualityEvaluator $evaluator) {}

    /**
     * Evalúa la lectura y crea su alerta si resulta advertencia o crítico.
     * No genera nada para una lectura normal. Es idempotente: una misma lectura nunca produce dos alertas.
     *
     * Espera la lectura con `batch.pond.farm` y `metric` cargados para no ejecutar consultas de más.
     */
    public function generateFor(WaterQualityReading $reading): ?Alert
    {
        $rule = AlertRule::query()
            ->where('farm_id', $reading->batch->pond->farm_id)
            ->where('metric_id', $reading->metric_id)
            ->first();

        $severity = $this->evaluator->evaluate($reading->metric, (float) $reading->value, $rule);

        if ($severity === WaterQualityStatus::Normal) {
            return null;
        }

        return Alert::query()->firstOrCreate(
            ['water_quality_reading_id' => $reading->id],
            [
                'batch_id' => $reading->batch_id,
                'metric_id' => $reading->metric_id,
                'value' => $reading->value,
                'severity' => $severity,
                'status' => AlertStatus::Pending,
            ],
        );
    }
}
