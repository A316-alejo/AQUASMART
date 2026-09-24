<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Sampling;

/**
 * Calcula la biomasa estimada de un lote a partir de sus registros:
 *
 *   peces vivos      = cantidad inicial − mortalidad acumulada
 *   biomasa estimada = peces vivos × peso promedio más reciente
 *
 * El peso más reciente es el de la última biometría; sin biometrías se usa el peso promedio inicial.
 * Si el lote trae precargados `mortalities_sum_quantity` (loadSum/withSum) y `latestSampling`, no hace consultas.
 */
class BiomassCalculator
{
    public function estimate(Batch $batch): BiomassEstimate
    {
        $mortality = $this->cumulativeMortality($batch);
        $liveFish = max(0, $batch->initial_quantity - $mortality);
        $sampling = $this->latestSampling($batch);
        $weight = (float) ($sampling?->average_weight_g ?? $batch->initial_average_weight_g);

        return new BiomassEstimate(
            initialQuantity: $batch->initial_quantity,
            cumulativeMortality: $mortality,
            liveFish: $liveFish,
            averageWeightG: $weight,
            weightSampledOn: $sampling?->sampled_on,
            biomassKg: round($liveFish * $weight / 1000, 2),
        );
    }

    /**
     * Peces vivos estimados, consultando siempre la mortalidad actual (para validar nuevos registros).
     */
    public function liveFish(Batch $batch): int
    {
        return max(0, $batch->initial_quantity - (int) $batch->mortalities()->sum('quantity'));
    }

    private function cumulativeMortality(Batch $batch): int
    {
        if (array_key_exists('mortalities_sum_quantity', $batch->getAttributes())) {
            return (int) $batch->getAttribute('mortalities_sum_quantity');
        }

        return (int) $batch->mortalities()->sum('quantity');
    }

    private function latestSampling(Batch $batch): ?Sampling
    {
        return $batch->relationLoaded('latestSampling')
            ? $batch->latestSampling
            : $batch->latestSampling()->first();
    }
}
