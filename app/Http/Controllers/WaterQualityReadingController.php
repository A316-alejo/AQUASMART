<?php

namespace App\Http\Controllers;

use App\Http\Requests\Production\StoreWaterQualityReadingRequest;
use App\Models\Batch;
use Illuminate\Http\RedirectResponse;

class WaterQualityReadingController extends Controller
{
    public function store(StoreWaterQualityReadingRequest $request, Batch $batch): RedirectResponse
    {
        $batch->waterQualityReadings()->create([...$request->record(), 'user_id' => $request->user()->id]);

        return redirect()->to(route('batches.show', $batch).'#water-quality')
            ->with('status', 'Se registró la medición de calidad del agua del lote '.$batch->code.'.');
    }
}
