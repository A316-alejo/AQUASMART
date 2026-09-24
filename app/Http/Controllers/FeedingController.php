<?php

namespace App\Http\Controllers;

use App\Http\Requests\Production\StoreFeedingRequest;
use App\Models\Batch;
use Illuminate\Http\RedirectResponse;

class FeedingController extends Controller
{
    public function store(StoreFeedingRequest $request, Batch $batch): RedirectResponse
    {
        $batch->feedings()->create([...$request->record(), 'user_id' => $request->user()->id]);

        return redirect()->to(route('batches.show', $batch).'#feeding')
            ->with('status', 'Se registró la alimentación del lote '.$batch->code.'.');
    }
}
