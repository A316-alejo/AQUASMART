<?php

namespace App\Http\Controllers;

use App\Enums\WaterQualityStatus;
use App\Http\Requests\Production\StoreWaterQualityReadingRequest;
use App\Models\Batch;
use App\Services\AlertGenerator;
use Illuminate\Http\RedirectResponse;

class WaterQualityReadingController extends Controller
{
    public function store(StoreWaterQualityReadingRequest $request, Batch $batch, AlertGenerator $generator): RedirectResponse
    {
        $reading = $batch->waterQualityReadings()->create([...$request->record(), 'user_id' => $request->user()->id]);

        $alert = $generator->generateFor($reading->load(['batch.pond.farm', 'metric']));

        $status = 'Se registró la medición de calidad del agua del lote '.$batch->code.'.';

        if ($alert) {
            $status .= match ($alert->severity) {
                WaterQualityStatus::Critical => ' Se generó una alerta crítica.',
                default => ' Se generó una alerta de advertencia.',
            };
        }

        return redirect()->to(route('batches.show', $batch).'#water-quality')
            ->with('status', $status);
    }
}
