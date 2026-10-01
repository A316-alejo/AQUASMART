<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Models\Pond;
use App\Models\Sensor;
use App\Models\WaterQualityReading;
use App\Services\SensorReadingSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PondReadingController extends Controller
{
    /**
     * Últimas lecturas de calidad del agua de los sensores activos del estanque, para el
     * polling en vivo de la vista del estanque (paso 13). Misma autorización que ponds.show:
     * un estanque ajeno responde 403, nunca expone datos de otra finca.
     */
    public function latest(Pond $pond, SensorReadingSummary $summary): JsonResponse
    {
        Gate::authorize('view', $pond);

        $sensors = $pond->sensors()
            ->active()
            ->with(['metric:id,code,name,unit,decimals,min_value,max_value', 'device:id,code,is_active,last_connected_at'])
            ->orderBy('code')
            ->get();

        $summaries = $summary->forSensors($sensors);

        $readings = $sensors->map(function (Sensor $sensor) use ($summaries) {
            $info = $summaries->get($sensor->id);
            /** @var WaterQualityReading|null $reading */
            $reading = $info['reading'] ?? null;
            $status = $info['status'] ?? null;

            return [
                'sensor_code' => $sensor->code,
                'sensor_name' => $sensor->name,
                'metric_code' => $sensor->metric->code,
                'metric_name' => $sensor->metric->name,
                'unit' => $sensor->metric->unit,
                'value' => $reading ? number_format((float) $reading->value, $sensor->metric->decimals, ',', '.') : null,
                'measured_at' => $reading?->measured_at?->toIso8601String(),
                'status' => $status?->value,
                'device_code' => $sensor->device->code,
                'device_active' => $sensor->device->is_active,
                'device_last_connected_at' => $sensor->device->last_connected_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'pond_code' => $pond->code,
            'has_active_batch' => $pond->batches()->where('status', BatchStatus::Active->value)->exists(),
            'readings' => $readings,
        ]);
    }
}
