<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Batch;
use App\Models\Farm;
use App\Models\Feeding;
use App\Models\Metric;
use App\Models\Mortality;
use App\Models\MortalityCause;
use App\Models\Pond;
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
 * Estadísticas históricas de AquaSmart: todo se calcula al vuelo a partir de los registros
 * existentes (alimentación, mortalidad, biometría, calidad del agua, alertas) con los mismos
 * scopes de visibilidad y servicios que el resto de la app (Batch::visibleTo, BiomassCalculator,
 * WaterQualityEvaluator). No se guarda ningún valor calculado.
 *
 * Los filtros de finca/estanque/lote se aplican SIEMPRE sobre ->visibleTo($user): un id que el
 * usuario no puede ver queda fuera de la intersección y el resultado es vacío, nunca datos ajenos.
 */
class StatsController extends Controller
{
    private const DEFAULT_DAYS = 30;

    private const MAX_RANGE_DAYS = 366;

    private const TOP_BATCHES = 15;

    public function __invoke(Request $request, BiomassCalculator $calculator, WaterQualityEvaluator $evaluator): View
    {
        $user = $request->user();

        $filters = $request->validate([
            'farm' => ['nullable', 'integer'],
            'pond' => ['nullable', 'integer'],
            'batch' => ['nullable', 'integer'],
            'metric' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        [$from, $to, $rangeClamped] = $this->resolveRange($filters);

        $batchIds = Batch::query()
            ->visibleTo($user)
            ->when($filters['farm'] ?? null, fn (Builder $query, int $farmId) => $query->whereHas('pond', fn (Builder $query) => $query->where('farm_id', $farmId)))
            ->when($filters['pond'] ?? null, fn (Builder $query, int $pondId) => $query->where('pond_id', $pondId))
            ->when($filters['batch'] ?? null, fn (Builder $query, int $batchId) => $query->where('id', $batchId))
            ->pluck('id');

        $batches = Batch::query()
            ->whereIn('id', $batchIds)
            ->withSum('mortalities', 'quantity')
            ->with('latestSampling')
            ->get(['id', 'pond_id', 'species_id', 'code', 'name', 'stocked_at', 'initial_quantity', 'initial_average_weight_g', 'status'])
            ->keyBy('id');

        return view('stats.index', [
            'filters' => $filters,
            'from' => $from,
            'to' => $to,
            'rangeClamped' => $rangeClamped,
            'hasData' => $batchIds->isNotEmpty(),
            'farms' => Farm::query()->visibleTo($user)->orderBy('name')->pluck('name', 'id')->all(),
            'ponds' => Pond::query()->visibleTo($user)->orderBy('code')->pluck('code', 'id')->all(),
            'batchOptions' => Batch::query()->visibleTo($user)->orderByDesc('stocked_at')->pluck('code', 'id')->all(),
            'metrics' => Metric::active()->ordered()->pluck('name', 'id')->all(),
            'production' => $this->productionSummary($batches, $calculator),
            'feeding' => $this->feedingSummary($batchIds, $batches, $from, $to),
            'mortality' => $this->mortalitySummary($batchIds, $batches, $from, $to),
            'waterQuality' => $this->waterQualitySummary($user, $batchIds, $filters['metric'] ?? null, $from, $to, $evaluator),
            'alerts' => $this->alertsSummary($batchIds, $filters['metric'] ?? null, $from, $to),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon, 2: bool}
     */
    private function resolveRange(array $filters): array
    {
        $to = isset($filters['to']) ? Carbon::parse($filters['to']) : today();
        $from = isset($filters['from']) ? Carbon::parse($filters['from']) : $to->copy()->subDays(self::DEFAULT_DAYS - 1);

        $clamped = false;

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            $from = $to->copy()->subDays(self::MAX_RANGE_DAYS);
            $clamped = true;
        }

        return [$from, $to, $clamped];
    }

    /**
     * @return list<string>
     */
    private function dateRange(Carbon $from, Carbon $to): array
    {
        $dates = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    /**
     * Alinea filas agrupadas por día (pueden tener huecos) con el rango completo de fechas,
     * para que el gráfico muestre ceros reales en los días sin registros en vez de saltarlos.
     *
     * @param  Collection<int, object>  $rows
     * @param  list<string>  $dates
     * @return list<float>
     */
    private function seriesFor(Collection $rows, array $dates, string $dayKey = 'day', string $valueKey = 'total'): array
    {
        $byDay = $rows->keyBy(fn ($row) => Carbon::parse($row->{$dayKey})->toDateString());

        return collect($dates)->map(fn (string $date) => (float) ($byDay->get($date)->{$valueKey} ?? 0))->all();
    }

    /**
     * @return list<string>
     */
    private function dateLabels(array $dates): array
    {
        return collect($dates)->map(fn (string $date) => Carbon::parse($date)->format('d/m'))->all();
    }

    /**
     * Producción: siempre sobre los lotes visibles que cumplen el filtro de finca/estanque/lote,
     * sin acotar por fecha (es el estado acumulado actual, igual que en el detalle de cada lote).
     *
     * @param  Collection<int, Batch>  $batches
     * @return array<string, mixed>
     */
    private function productionSummary(Collection $batches, BiomassCalculator $calculator): array
    {
        $estimates = $batches->map(fn (Batch $batch) => $calculator->estimate($batch));

        $stocked = $estimates->sum('initialQuantity');
        $liveFish = $estimates->sum('liveFish');
        $liveWeightSum = $estimates->sum(fn ($estimate) => $estimate->liveFish * $estimate->averageWeightG);

        return [
            'batches' => $batches->count(),
            'stocked' => $stocked,
            'cumulativeMortality' => $estimates->sum('cumulativeMortality'),
            'liveFish' => $liveFish,
            'biomassKg' => round($estimates->sum('biomassKg'), 2),
            'survivalPct' => $stocked > 0 ? round($liveFish / $stocked * 100, 1) : null,
            'averageWeightG' => $liveFish > 0 ? round($liveWeightSum / $liveFish, 2) : null,
        ];
    }

    /**
     * @param  Collection<int, int>  $batchIds
     * @param  Collection<int, Batch>  $batches
     * @return array<string, mixed>
     */
    private function feedingSummary(Collection $batchIds, Collection $batches, Carbon $from, Carbon $to): array
    {
        $query = fn () => Feeding::query()->whereIn('batch_id', $batchIds)->whereBetween('fed_on', [$from->toDateString(), $to->toDateString()]);

        $totalGrams = (float) $query()->sum('quantity_g');

        $byDay = $query()->selectRaw('fed_on as day, SUM(quantity_g) as total')->groupBy('fed_on')->get();
        $dates = $this->dateRange($from, $to);

        $byBatch = $query()->selectRaw('batch_id, SUM(quantity_g) as total')->groupBy('batch_id')
            ->orderByDesc('total')->limit(self::TOP_BATCHES)->get()
            ->map(fn ($row) => ['batch' => $batches->get($row->batch_id), 'totalKg' => round((float) $row->total / 1000, 2)])
            ->filter(fn (array $row) => $row['batch'] !== null)
            ->values();

        return [
            'totalKg' => round($totalGrams / 1000, 2),
            'chart' => ['labels' => $this->dateLabels($dates), 'series' => [
                ['label' => 'Alimento (kg)', 'color' => 'var(--aqua)', 'data' => array_map(fn ($g) => round($g / 1000, 2), $this->seriesFor($byDay, $dates))],
            ]],
            'byBatch' => $byBatch,
        ];
    }

    /**
     * @param  Collection<int, int>  $batchIds
     * @param  Collection<int, Batch>  $batches
     * @return array<string, mixed>
     */
    private function mortalitySummary(Collection $batchIds, Collection $batches, Carbon $from, Carbon $to): array
    {
        $query = fn () => Mortality::query()->whereIn('batch_id', $batchIds)->whereBetween('recorded_on', [$from->toDateString(), $to->toDateString()]);

        $total = (int) $query()->sum('quantity');

        $byDay = $query()->selectRaw('recorded_on as day, SUM(quantity) as total')->groupBy('recorded_on')->get();
        $dates = $this->dateRange($from, $to);

        $byCauseRows = $query()->selectRaw('mortality_cause_id, SUM(quantity) as total')->groupBy('mortality_cause_id')->orderByDesc('total')->get();
        $causeNames = MortalityCause::query()->whereIn('id', $byCauseRows->pluck('mortality_cause_id'))->pluck('name', 'id');
        $byCause = $byCauseRows->map(fn ($row) => ['cause' => $causeNames->get($row->mortality_cause_id, 'Sin causa'), 'total' => (int) $row->total]);

        $byBatch = $query()->selectRaw('batch_id, SUM(quantity) as total')->groupBy('batch_id')
            ->orderByDesc('total')->limit(self::TOP_BATCHES)->get()
            ->map(fn ($row) => ['batch' => $batches->get($row->batch_id), 'total' => (int) $row->total])
            ->filter(fn (array $row) => $row['batch'] !== null)
            ->values();

        return [
            'total' => $total,
            'chart' => ['labels' => $this->dateLabels($dates), 'series' => [
                ['label' => 'Mortalidad', 'color' => 'var(--crit)', 'data' => $this->seriesFor($byDay, $dates)],
            ]],
            'byCause' => $byCause,
            'byBatch' => $byBatch,
        ];
    }

    /**
     * Reutiliza WaterQualityEvaluator y AlertRule exactamente como el dashboard: la clasificación
     * normal/advertencia/crítico no se vuelve a implementar aquí.
     *
     * @param  Collection<int, int>  $batchIds
     * @return array<string, mixed>
     */
    private function waterQualitySummary(
        User $user,
        Collection $batchIds,
        ?int $metricId,
        Carbon $from,
        Carbon $to,
        WaterQualityEvaluator $evaluator
    ): array {
        $readings = WaterQualityReading::query()
            ->whereIn('batch_id', $batchIds)
            ->whereBetween('measured_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($metricId, fn (Builder $query, int $id) => $query->where('metric_id', $id))
            ->with(['metric:id,code,name,unit,decimals,min_value,max_value', 'batch:id,pond_id', 'batch.pond:id,farm_id'])
            ->orderBy('measured_at')
            ->get();

        $farmIds = Farm::query()->visibleTo($user)->pluck('id');
        $rulesByFarm = AlertRule::query()->whereIn('farm_id', $farmIds)->get()->groupBy('farm_id');

        $counts = ['normal' => 0, 'advertencia' => 0, 'critico' => 0];
        $charts = [];

        foreach ($readings->groupBy('metric_id') as $metricReadings) {
            $metric = $metricReadings->first()->metric;
            $labels = [];
            $data = [];

            foreach ($metricReadings as $reading) {
                $rule = $evaluator->ruleFor($rulesByFarm->get($reading->batch->pond->farm_id, collect()), $reading->metric_id);
                $status = $evaluator->evaluate($reading->metric, (float) $reading->value, $rule);
                $counts[$status->value]++;

                $labels[] = $reading->measured_at->format('d/m H:i');
                $data[] = (float) $reading->value;
            }

            $charts[] = [
                'metric' => $metric,
                'labels' => $labels,
                'series' => [['label' => $metric->name, 'color' => 'var(--aqua)', 'data' => $data]],
            ];
        }

        return [
            'total' => $readings->count(),
            'counts' => $counts,
            'charts' => $charts,
        ];
    }

    /**
     * @param  Collection<int, int>  $batchIds
     * @return array<string, mixed>
     */
    private function alertsSummary(Collection $batchIds, ?int $metricId, Carbon $from, Carbon $to): array
    {
        $query = fn () => Alert::query()
            ->whereIn('batch_id', $batchIds)
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($metricId, fn (Builder $query, int $id) => $query->where('metric_id', $id));

        $totals = $query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN severity = 'critico' THEN 1 ELSE 0 END) as critical")
            ->selectRaw("SUM(CASE WHEN severity = 'advertencia' THEN 1 ELSE 0 END) as warning")
            ->selectRaw("SUM(CASE WHEN status = 'pendiente' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'resuelta' THEN 1 ELSE 0 END) as resolved")
            ->first();

        $byDaySeverity = $query()->selectRaw('DATE(created_at) as day, severity, COUNT(*) as total')->groupBy('day', 'severity')->get();
        $dates = $this->dateRange($from, $to);

        return [
            'total' => (int) $totals->total,
            'critical' => (int) $totals->critical,
            'warning' => (int) $totals->warning,
            'pending' => (int) $totals->pending,
            'resolved' => (int) $totals->resolved,
            'chart' => ['labels' => $this->dateLabels($dates), 'series' => [
                ['label' => 'Críticas', 'color' => 'var(--crit)', 'data' => $this->seriesFor($byDaySeverity->where('severity', 'critico')->values(), $dates)],
                ['label' => 'Advertencias', 'color' => 'var(--warn)', 'data' => $this->seriesFor($byDaySeverity->where('severity', 'advertencia')->values(), $dates)],
            ]],
        ];
    }
}
