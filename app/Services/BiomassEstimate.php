<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Resultado del cálculo de biomasa de un lote en un momento dado. No se guarda en la base de datos.
 */
final readonly class BiomassEstimate
{
    public function __construct(
        public int $initialQuantity,
        public int $cumulativeMortality,
        public int $liveFish,
        public float $averageWeightG,
        public ?Carbon $weightSampledOn,
        public float $biomassKg,
    ) {}

    /**
     * El peso proviene de una biometría; si es null, se usa el peso promedio de la siembra.
     */
    public function usesSampling(): bool
    {
        return $this->weightSampledOn !== null;
    }

    public function survivalPct(): float
    {
        return $this->initialQuantity > 0 ? round($this->liveFish / $this->initialQuantity * 100, 1) : 0.0;
    }
}
