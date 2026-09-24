<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Http\Requests\Batches\CloseBatchRequest;
use App\Models\Batch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Cierre (cosecha o fin del ciclo) y cancelación de lotes. Ambos son definitivos.
 */
class BatchClosureController extends Controller
{
    public function confirmClose(Batch $batch): View
    {
        Gate::authorize('close', $batch);

        return view('batches.close', ['batch' => $batch->load('pond.farm')]);
    }

    public function close(CloseBatchRequest $request, Batch $batch): RedirectResponse
    {
        $batch->close(BatchStatus::Closed, Carbon::parse($request->validated('closed_at')));

        return redirect()->route('batches.show', $batch)
            ->with('status', "El lote {$batch->code} fue cerrado.");
    }

    public function confirmCancel(Batch $batch): View
    {
        Gate::authorize('cancel', $batch);

        return view('batches.cancel', ['batch' => $batch->load('pond.farm')]);
    }

    public function cancel(Batch $batch): RedirectResponse
    {
        Gate::authorize('cancel', $batch);

        $batch->close(BatchStatus::Cancelled, Carbon::today());

        return redirect()->route('batches.show', $batch)
            ->with('status', "El lote {$batch->code} fue cancelado.");
    }
}
