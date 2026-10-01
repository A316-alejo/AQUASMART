<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReadingsRequest;
use App\Services\AlertGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReadingIngestController extends Controller
{
    /**
     * Guarda una o varias mediciones de calidad del agua enviadas por un dispositivo autenticado
     * por token (AuthenticateDevice). Reutiliza AlertGenerator: no se vuelve a decidir si una
     * medición genera alerta, es la misma regla que usan las mediciones manuales (paso 10).
     */
    public function store(StoreReadingsRequest $request, AlertGenerator $generator): JsonResponse
    {
        $device = $request->device();
        $created = [];
        $alerts = [];

        DB::transaction(function () use ($request, $device, $generator, &$created, &$alerts) {
            foreach ($request->resolvedReadings() as $data) {
                $reading = $data['batch']->waterQualityReadings()->create([
                    'metric_id' => $data['sensor']->metric_id,
                    'measured_at' => $data['measured_at'],
                    'value' => $data['value'],
                    'source' => 'sensor',
                ]);

                $created[] = $reading->id;

                $alert = $generator->generateFor($reading->load(['batch.pond.farm', 'metric']));

                if ($alert) {
                    $alerts[] = [
                        'sensor' => $data['sensor']->code,
                        'metric' => $reading->metric->code,
                        'severity' => $alert->severity->value,
                    ];
                }
            }

            $device->touchConnection();
        });

        return response()->json([
            'created' => count($created),
            'alerts' => $alerts,
        ], 201);
    }
}
