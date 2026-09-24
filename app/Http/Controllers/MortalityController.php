<?php

namespace App\Http\Controllers;

use App\Http\Requests\Production\StoreMortalityRequest;
use App\Models\Batch;
use App\Services\BiomassCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MortalityController extends Controller
{
    public function store(StoreMortalityRequest $request, Batch $batch, BiomassCalculator $calculator): RedirectResponse
    {
        $record = $request->record();

        // Se vuelve a comprobar con el lote bloqueado: dos registros simultáneos no pueden superar la población.
        DB::transaction(function () use ($batch, $calculator, $record, $request) {
            $locked = Batch::query()->lockForUpdate()->findOrFail($batch->id);
            $liveFish = $calculator->liveFish($locked);

            if ($record['quantity'] > $liveFish) {
                throw ValidationException::withMessages([
                    'mortality_quantity' => 'La cantidad supera los peces vivos del lote ('.number_format($liveFish, 0, ',', '.').').',
                ])->redirectTo(route('batches.show', $batch).'#mortality');
            }

            $locked->mortalities()->create([...$record, 'user_id' => $request->user()->id]);
        });

        return redirect()->to(route('batches.show', $batch).'#mortality')
            ->with('status', 'Se registró la mortalidad del lote '.$batch->code.'.');
    }
}
