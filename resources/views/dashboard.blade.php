@extends('layouts.app')
@section('title', 'Dashboard general — AquaSmart')

@php
    $int = fn ($value) => number_format((int) $value, 0, ',', '.');
    $dec = fn ($value, $decimals = 2) => number_format((float) $value, $decimals, ',', '.');
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Dashboard general</h1>
            <p>Hola, {{ auth()->user()->name }}. Este es el resumen de la operación que puedes consultar.</p>
        </div>
    </div>

    <div class="grid g4" style="margin-bottom:14px">
        <div class="card kpi">
            <div class="label">Fincas</div>
            <div class="value">{{ $int($farmsCount) }}</div>
            <div class="foot">Visibles según tu rol</div>
        </div>
        <div class="card kpi">
            <div class="label">Estanques</div>
            <div class="value">{{ $int($pondsCount) }}</div>
            <div class="foot">Visibles según tu rol</div>
        </div>
        <div class="card kpi">
            <div class="label">Lotes activos</div>
            <div class="value">{{ $int($activeBatchesCount) }}</div>
            <div class="foot">En cultivo ahora mismo</div>
        </div>
        <div class="card kpi">
            <div class="label">Peces sembrados</div>
            <div class="value">{{ $int($stockedFish) }}</div>
            <div class="foot">Cantidad inicial de los lotes activos</div>
        </div>
    </div>

    <div class="grid g4" style="margin-bottom:14px">
        <div class="card kpi">
            <div class="label">Peces vivos estimados</div>
            <div class="value">{{ $int($liveFish) }}</div>
            <div class="foot">Sembrados menos mortalidad acumulada</div>
        </div>
        <div class="card kpi">
            <div class="label">Biomasa estimada</div>
            <div class="value">{{ $dec($biomassKg) }} <small>kg</small></div>
            <div class="foot">Peces vivos × último peso registrado</div>
        </div>
        <div class="card kpi">
            <div class="label">Alertas pendientes</div>
            <div class="value">{{ $int($pendingAlerts) }}</div>
            <div class="foot">Por reconocer o resolver</div>
        </div>
        <div class="card kpi">
            <div class="label">Alertas críticas pendientes</div>
            <div class="value" style="{{ $criticalPendingAlerts > 0 ? 'color:var(--crit)' : '' }}">{{ $int($criticalPendingAlerts) }}</div>
            <div class="foot">Requieren atención inmediata</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:14px">
        <header><h3>Calidad del agua</h3></header>
        @if ($waterQualitySummary['total'] === 0)
            <p class="muted-line">No se han registrado mediciones de calidad del agua en los últimos 7 días.</p>
        @else
            <div class="grid g4">
                <div class="metric">
                    <div class="m-lab">Mediciones (7 días)</div>
                    <div class="m-val">{{ $int($waterQualitySummary['total']) }}</div>
                </div>
                <div class="metric">
                    <div class="m-lab">Normales</div>
                    <div class="m-val">{{ $int($waterQualitySummary['normal']) }}</div>
                </div>
                <div class="metric">
                    <div class="m-lab">Advertencia</div>
                    <div class="m-val is-warn">{{ $int($waterQualitySummary['advertencia']) }}</div>
                </div>
                <div class="metric">
                    <div class="m-lab">Crítico</div>
                    <div class="m-val is-crit">{{ $int($waterQualitySummary['critico']) }}</div>
                </div>
            </div>
        @endif
    </div>

    <div class="grid g-2-1">
        <div class="card" style="align-self:start">
            <header><h3>Actividad productiva reciente</h3></header>
            @forelse ($recentActivity as $item)
                <div class="list-row">
                    <span class="sev info" aria-hidden="true"><x-icon :name="$item['icon']" style="width:18px;height:18px" /></span>
                    <span class="body">
                        <b>{{ $item['text'] }}</b>
                        <span>{{ $item['at']->format('d/m/Y') }}</span>
                    </span>
                </div>
            @empty
                <p class="muted-line">No hay actividad productiva en los últimos 7 días.</p>
            @endforelse
        </div>

        <div class="grid" style="align-content:start">
            <div class="card">
                <header><h3>Accesos rápidos</h3></header>
                <div class="grid" style="gap:8px">
                    @can('viewAny', App\Models\Batch::class)
                        <a class="btn block sm" href="{{ route('batches.index') }}">Lotes</a>
                        <a class="btn block sm" href="{{ $quickAccessBatch ? route('batches.show', $quickAccessBatch).'#water-quality' : route('batches.index') }}">Calidad del agua</a>
                    @endcan
                    @can('viewAny', App\Models\Alert::class)
                        <a class="btn block sm" href="{{ route('alerts.index') }}">Centro de alertas</a>
                    @endcan
                    @cannot('viewAny', App\Models\Batch::class)
                        @cannot('viewAny', App\Models\Alert::class)
                            <p class="muted-line">Todavía no tienes fincas asignadas.</p>
                        @endcannot
                    @endcannot
                </div>
            </div>

            <div class="card">
                <header>
                    <h3>Últimas alertas</h3>
                    <span class="tag info plain">{{ $latestAlerts->count() }}</span>
                </header>
                @forelse ($latestAlerts as $alert)
                    <div class="list-row">
                        <x-quality-status :status="$alert->severity" />
                        <span class="body">
                            <b>{{ $alert->batch->code }} · {{ $alert->metric->name }}</b>
                            <span>{{ $alert->created_at->format('d/m/Y H:i') }}</span>
                        </span>
                        <x-alert-status :status="$alert->status" />
                    </div>
                @empty
                    <p class="muted-line">No hay alertas registradas.</p>
                @endforelse
                @if ($latestAlerts->isNotEmpty())
                    <a class="btn block sm" style="margin-top:12px" href="{{ route('alerts.index') }}">Ver todas</a>
                @endif
            </div>
        </div>
    </div>
@endsection
