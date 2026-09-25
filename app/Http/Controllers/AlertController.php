<?php

namespace App\Http\Controllers;

use App\Enums\AlertStatus;
use App\Enums\WaterQualityStatus;
use App\Models\Alert;
use App\Models\Batch;
use App\Models\Farm;
use App\Models\Metric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Alert::class);

        $filters = $request->validate([
            'farm' => ['nullable', 'integer'],
            'batch' => ['nullable', 'integer'],
            'metric' => ['nullable', 'integer'],
            'severity' => ['nullable', Rule::enum(WaterQualityStatus::class)],
            'status' => ['nullable', Rule::enum(AlertStatus::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            // Atajos de los chips del Centro de alertas: fijan severidad o estado sin duplicar filtros.
            'view' => ['nullable', Rule::in(['pending', 'critical', 'warning', 'resolved'])],
        ]);

        match ($filters['view'] ?? null) {
            'pending' => $filters['status'] = AlertStatus::Pending->value,
            'critical' => $filters['severity'] = WaterQualityStatus::Critical->value,
            'warning' => $filters['severity'] = WaterQualityStatus::Warning->value,
            'resolved' => $filters['status'] = AlertStatus::Resolved->value,
            default => null,
        };

        $visible = Alert::query()->visibleTo($request->user());

        $alerts = (clone $visible)
            ->when($filters['farm'] ?? null, fn (Builder $query, int $farmId) => $query->whereHas('batch.pond', fn (Builder $query) => $query->where('farm_id', $farmId)))
            ->when($filters['batch'] ?? null, fn (Builder $query, int $batchId) => $query->where('batch_id', $batchId))
            ->when($filters['metric'] ?? null, fn (Builder $query, int $metricId) => $query->where('metric_id', $metricId))
            ->when($filters['severity'] ?? null, fn (Builder $query, string $severity) => $query->where('severity', $severity))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->with([
                'batch:id,code,name,pond_id',
                'batch.pond:id,code,farm_id',
                // producer_id y users se usan solo para las abilities acknowledge/resolve (evita N+1 por fila).
                'batch.pond.farm:id,name,producer_id',
                'batch.pond.farm.users:id',
                'metric:id,code,name,unit,decimals',
            ])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('alerts.index', [
            'alerts' => $alerts,
            'filters' => $filters,
            'counts' => [
                'pending' => (clone $visible)->where('status', AlertStatus::Pending)->count(),
                'critical' => (clone $visible)->where('severity', WaterQualityStatus::Critical)->count(),
                'warning' => (clone $visible)->where('severity', WaterQualityStatus::Warning)->count(),
                'resolved' => (clone $visible)->where('status', AlertStatus::Resolved)->count(),
            ],
            'farms' => Farm::query()->visibleTo($request->user())->orderBy('name')->pluck('name', 'id')->all(),
            'batches' => Batch::query()->visibleTo($request->user())->orderBy('code')->pluck('code', 'id')->all(),
            'metrics' => Metric::query()->ordered()->pluck('name', 'id')->all(),
        ]);
    }
}
