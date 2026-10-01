<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Models\AlertRule;
use App\Models\Batch;
use App\Models\Pond;
use App\Models\Sensor;
use App\Models\WaterQualityReading;
use Illuminate\Support\Collection;

/**
 * Empareja cada sensor con la última lectura de su parámetro (manual o de sensor, la que sea más
 * reciente: es el mismo dato de calidad del agua) y su estado normal/advertencia/crítico.
 *
 * Reutilizado por el Centro de IoT (vista estática) y por el endpoint de lecturas en vivo de un
 * estanque (paso 13): la clasificación no se vuelve a implementar en ninguno de los dos.
 *
 * No hace una consulta por sensor: agrupa por lote activo y por regla de alerta.
 */
class SensorReadingSummary
{
    public function __construct(private readonly WaterQualityEvaluator $evaluator) {}

    /**
     * @param  Collection<int, Sensor>  $sensors  Deben traer cargada la relación 'metric'.
     * @return Collection<int, array{reading: ?WaterQualityReading, status: ?\App\Enums\WaterQualityStatus, has_active_batch: bool}>
     *                                                                                                                              indexada por el id del sensor.
     */
    public function forSensors(Collection $sensors): Collection
    {
        if ($sensors->isEmpty()) {
            return collect();
        }

        $pondIds = $sensors->pluck('pond_id')->unique()->values()->all();

        $batchesByPond = Batch::query()
            ->where('status', BatchStatus::Active->value)
            ->whereIn('pond_id', $pondIds)
            ->get(['id', 'pond_id'])
            ->keyBy('pond_id');

        $batchIds = $batchesByPond->pluck('id')->all();
        $metricIds = $sensors->pluck('metric_id')->unique()->values()->all();

        $latestReadingByBatchAndMetric = $batchIds
            ? WaterQualityReading::query()
                ->whereIn('batch_id', $batchIds)
                ->whereIn('metric_id', $metricIds)
                ->orderByDesc('measured_at')->orderByDesc('id')
                ->get()
                ->groupBy(fn (WaterQualityReading $reading) => $reading->batch_id.'-'.$reading->metric_id)
                ->map(fn (Collection $readings) => $readings->first())
            : collect();

        $farmIdByPond = Pond::query()->whereIn('id', $pondIds)->pluck('farm_id', 'id');
        $rulesByFarm = AlertRule::query()->whereIn('farm_id', $farmIdByPond->unique()->values())->get()->groupBy('farm_id');

        return $sensors->mapWithKeys(function (Sensor $sensor) use ($batchesByPond, $latestReadingByBatchAndMetric, $farmIdByPond, $rulesByFarm) {
            $batch = $batchesByPond->get($sensor->pond_id);
            $reading = $batch ? $latestReadingByBatchAndMetric->get($batch->id.'-'.$sensor->metric_id) : null;

            $farmId = $farmIdByPond->get($sensor->pond_id);
            $rule = $this->evaluator->ruleFor($rulesByFarm->get($farmId, collect()), $sensor->metric_id);
            $status = $reading ? $this->evaluator->evaluate($sensor->metric, (float) $reading->value, $rule) : null;

            return [$sensor->id => [
                'reading' => $reading,
                'status' => $status,
                'has_active_batch' => $batch !== null,
            ]];
        });
    }
}
