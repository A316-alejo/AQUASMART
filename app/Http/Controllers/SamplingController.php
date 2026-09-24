<?php

namespace App\Http\Controllers;

use App\Http\Requests\Production\StoreSamplingRequest;
use App\Models\Batch;
use Illuminate\Http\RedirectResponse;

class SamplingController extends Controller
{
    public function store(StoreSamplingRequest $request, Batch $batch): RedirectResponse
    {
        $batch->samplings()->create([...$request->record(), 'user_id' => $request->user()->id]);

        return redirect()->to(route('batches.show', $batch).'#sampling')
            ->with('status', 'Se registró la biometría del lote '.$batch->code.'.');
    }
}
