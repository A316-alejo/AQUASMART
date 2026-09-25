<?php

namespace App\Http\Controllers;

use App\Enums\AlertStatus;
use App\Enums\BatchStatus;
use App\Enums\WaterQualityStatus;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Batch;
use App\Models\Farm;
use App\Models\Feeding;
use App\Models\Mortality;
use App\Models\Pond;
use App\Models\Sampling;
use App\Models\User;
use App\Models\WaterQualityReading;
use App\Services\BiomassCalculator;
use App\Services\WaterQualityEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Resumen operativo de AquaSmart: cifras agregadas de las fincas, estanques, lotes, calidad del
 * agua y alertas que el usuario puede consultar. No es un módulo de estadísticas: todas las cifras
 * se calculan al vuelo con los mismos servicios y scopes de visibilidad que el resto de la app
 * (App\Services\BiomassCalculator, App\Services\WaterQualityEvaluator, Batch::visibleTo, …);
 * nada se guarda como un valor fijo que pueda quedar desactualizado.
 */
class DashboardController extends Controller
{
    /**
     * Ventana de "reciente": calidad del agua y actividad productiva de los últimos 7 días.
     */
    private const RECENT_DAYS = 7;

    /**
     * Elementos que se muestran en cada lista corta (actividad reciente, últimas alertas).
     */
    private const FEED_LIMIT = 8;

    public function __invoke(Request $request, BiomassCalculator $calculator, WaterQualityEvaluator $evaluator): View
    {
        $user = $request->user();

        $farmIds = Farm::query()->visibleTo($user)->pluck('id');

        $activeBatches = Batch::query()
            ->visibleTo($user)
            ->where('status', BatchStatus::Active->value)
            ->withSum('mortalities', 'quantity')
            ->with('latestSampling')
            ->get(['id', 'pond_id', 'species_id', 'code', 'name', 'stocked_at', 'initial_quantity', 'initial_average_weight_g', 'status']);

        $estimates = $activeBatches->map(fn (Batch $batch) => $calculator->estimate($batch));

        return view('dashboard', [
            'farmsCount' => $farmIds->count(),
            'pondsCount' => Pond::query()->visibleTo($user)->count(),
            'activeBatchesCount' => $activeBatches->count(),
            'stockedFish' => $estimates->sum('initialQuantity'),
            'liveFish' => $estimates->sum('liveFish'),
            'biomassKg' => round($estimates->sum('biomassKg'), 2),
            'pendingAlerts' => Alert::query()->visibleTo($user)->where('status', AlertStatus::Pending->value)->count(),
            'criticalPendingAlerts' => Alert::query()->visibleTo($user)
                ->where('status', AlertStatus::Pending->value)
                ->where('severity', WaterQualityStatus::Critical->value)
                ->count(),
            'waterQualitySummary' => $this->waterQualitySummary($user, $farmIds, $evaluator),
            'recentActivity' => $this->recentActivity($user),
            'latestAlerts' => Alert::query()->visibleTo($user)
                ->with(['batch:id,code', 'metric:id,name'])
                ->orderByDesc('created_at')->orderByDesc('id')
                ->limit(self::FEED_LIMIT)
                ->get(),
            'quickAccessBatch' => $activeBatches->first(),
        ]);
    }

    /**
     * Mediciones de los últimos días, clasificadas con WaterQualityEvaluator (no se reimplementan
     * los umbrales: se reutilizan las mismas reglas por finca que usa el Centro de alertas).
     *
     * @param  Collection<int, int>  $farmIds
     * @return array{total: int, normal: int, advertencia: int, critico: int}
     */
    private function waterQualitySummary(User $user, Collection $farmIds, WaterQualityEvaluator $evaluator): array
    {
        $readings = WaterQualityReading::query()
            ->whereHas('batch', fn (Builder $query) => $query->visibleTo($user))
            ->where('measured_at', '>=', Carbon::today()->subDays(self::RECENT_DAYS))
            ->with(['metric:id,code,name,unit,decimals,min_value,max_value', 'batch:id,pond_id', 'batch.pond:id,farm_id'])
            ->get();

        $rulesByFarm = AlertRule::query()->whereIn('farm_id', $farmIds)->get()->groupBy('farm_id');

        $summary = ['total' => $readings->count(), 'normal' => 0, 'advertencia' => 0, 'critico' => 0];

        foreach ($readings as $reading) {
            $rules = $rulesByFarm->get($reading->batch->pond->farm_id, collect());
            $status = $evaluator->evaluate($reading->metric, (float) $reading->value, $evaluator->ruleFor($rules, $reading->metric_id));
            $summary[$status->value]++;
        }

        return $summary;
    }

    /**
     * Últimos eventos productivos (alimentación, mortalidad, biometría, calidad del agua) de los
     * lotes visibles, combinados y ordenados por fecha. Cada consulta trae solo lo necesario para
     * describir el evento, sin recorrer los lotes uno por uno.
     *
     * @return list<array{at: \Illuminate\Support\Carbon, icon: string, text: string}>
     */
    private function recentActivity(User $user): array
    {
        $since = Carbon::today()->subDays(self::RECENT_DAYS);
        $visible = fn (Builder $query): Builder => $query->whereHas('batch', fn (Builder $query) => $query->visibleTo($user));

        $feedings = $visible(Feeding::query())->where('fed_on', '>=', $since)
            ->with(['batch:id,code', 'feedType:id,name,brand'])
            ->orderByDesc('fed_on')->orderByDesc('id')->limit(self::FEED_LIMIT)->get()
            ->map(fn (Feeding $feeding) => [
                'at' => $feeding->fed_on, 'icon' => 'fish',
                'text' => "Alimentación en {$feeding->batch->code}: ".number_format((float) $feeding->quantity_g / 1000, 2, ',', '.').' kg de '.$feeding->feedType->catalogLabel(),
            ]);

        $mortalities = $visible(Mortality::query())->where('recorded_on', '>=', $since)
            ->with(['batch:id,code', 'cause:id,name'])
            ->orderByDesc('recorded_on')->orderByDesc('id')->limit(self::FEED_LIMIT)->get()
            ->map(fn (Mortality $mortality) => [
                'at' => $mortality->recorded_on, 'icon' => 'alert',
                'text' => "Mortalidad en {$mortality->batch->code}: {$mortality->quantity} peces ({$mortality->cause->name})",
            ]);

        $samplings = $visible(Sampling::query())->where('sampled_on', '>=', $since)
            ->with('batch:id,code')
            ->orderByDesc('sampled_on')->orderByDesc('id')->limit(self::FEED_LIMIT)->get()
            ->map(fn (Sampling $sampling) => [
                'at' => $sampling->sampled_on, 'icon' => 'stats',
                'text' => "Biometría en {$sampling->batch->code}: peso promedio ".number_format((float) $sampling->average_weight_g, 2, ',', '.').' g',
            ]);

        $readings = $visible(WaterQualityReading::query())->where('measured_at', '>=', $since)
            ->with(['batch:id,code', 'metric:id,name,unit,decimals'])
            ->orderByDesc('measured_at')->orderByDesc('id')->limit(self::FEED_LIMIT)->get()
            ->map(fn (WaterQualityReading $reading) => [
                'at' => $reading->measured_at, 'icon' => 'pond',
                'text' => "Medición en {$reading->batch->code}: {$reading->metric->name} ".$reading->metric->format($reading->value),
            ]);

        return $feedings->concat($mortalities)->concat($samplings)->concat($readings)
            ->sortByDesc('at')->take(self::FEED_LIMIT)->values()->all();
    }
}
