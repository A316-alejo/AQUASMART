@extends('layouts.app')
@section('title', $pond->code.' — AquaSmart')

@php
    $num = fn ($value, $unit) => $value !== null ? number_format((float) $value, 2, ',', '.').' '.$unit : 'Sin registrar';
@endphp

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('ponds.index') }}">Estanques</a> / Detalle</div>
            <h1>{{ $pond->code }} · {{ $pond->name }}</h1>
            <p>
                <a class="link" href="{{ route('farms.show', $pond->farm) }}">{{ $pond->farm->name }}</a>
                · {{ $pond->farm->producer->name }} · {{ $pond->type->label() }}
            </p>
        </div>
        <div class="actions">
            <x-user-status :active="$pond->is_active" />
            @can('update', $pond)
                <a class="btn" href="{{ route('ponds.edit', $pond) }}">Editar</a>
            @endcan
            @can('deactivate', $pond)
                <a class="btn" href="{{ route('ponds.deactivate.confirm', $pond) }}">Desactivar</a>
            @endcan
            @can('activate', $pond)
                <form class="inline-form" method="POST" action="{{ route('ponds.activate', $pond) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn primary">Activar</button>
                </form>
            @endcan
        </div>
    </div>

    @unless ($pond->farm->is_active)
        <div class="notice crit" role="status">La finca {{ $pond->farm->name }} está inactiva. Mientras lo esté, no se pueden crear ni reactivar estanques en ella.</div>
    @elseif (! $pond->is_active)
        <div class="notice crit" role="status">Este estanque está inactivo: no está en operación.</div>
    @endunless

    <div class="grid g3" style="margin-bottom:14px">
        <div class="card kpi">
            <div class="label">Superficie</div>
            <div class="value">{{ $pond->surface_m2 !== null ? number_format((float) $pond->surface_m2, 0, ',', '.') : '—' }} <small>m²</small></div>
            <div class="foot">Área del espejo de agua</div>
        </div>
        <div class="card kpi">
            <div class="label">Volumen</div>
            <div class="value">{{ $pond->volume_m3 !== null ? number_format((float) $pond->volume_m3, 0, ',', '.') : '—' }} <small>m³</small></div>
            <div class="foot">Capacidad de agua</div>
        </div>
        <div class="card kpi">
            <div class="label">Profundidad promedio</div>
            <div class="value">{{ $pond->depth_m !== null ? number_format((float) $pond->depth_m, 2, ',', '.') : '—' }} <small>m</small></div>
            <div class="foot">Columna de agua</div>
        </div>
    </div>

    <div class="card">
        <header><h3>Información del estanque</h3></header>
        <dl class="def">
            <dt>Código</dt>
            <dd>{{ $pond->code }}</dd>
            <dt>Nombre</dt>
            <dd>{{ $pond->name }}</dd>
            <dt>Tipo</dt>
            <dd>{{ $pond->type->label() }}</dd>
            <dt>Finca</dt>
            <dd>{{ $pond->farm->name }} · {{ $pond->farm->municipality }}, {{ $pond->farm->department }}</dd>
            <dt>Productor</dt>
            <dd>{{ $pond->farm->producer->name }}</dd>
            <dt>Superficie</dt>
            <dd>{{ $num($pond->surface_m2, 'm²') }}</dd>
            <dt>Volumen</dt>
            <dd>{{ $num($pond->volume_m3, 'm³') }}</dd>
            <dt>Profundidad</dt>
            <dd>{{ $num($pond->depth_m, 'm') }}</dd>
            <dt>Registrado</dt>
            <dd>{{ $pond->created_at?->format('d/m/Y H:i') }}</dd>
        </dl>
    </div>

    <div class="card" style="margin-top:14px" data-live-readings data-poll-url="{{ route('ponds.readings', $pond) }}">
        <header><h3>Calidad del agua en tiempo real</h3></header>
        <p class="muted-line" data-live-status>Cargando lecturas…</p>
        <div data-live-body>
            <p class="muted-line">Cargando lecturas…</p>
        </div>
    </div>

    <div class="card" style="margin-top:14px">
        <header>
            <h3>Lotes</h3>
            <span class="tag info plain">{{ $pond->batches->count() }}</span>
        </header>
        @forelse ($pond->batches as $batch)
            <div class="list-row">
                <span class="sev info" aria-hidden="true"><x-icon name="fish" style="width:18px;height:18px" /></span>
                <span class="body">
                    <b>{{ $batch->code }} · {{ $batch->name }}</b>
                    <span>
                        {{ $batch->species->common_name }} · sembrado el {{ $batch->stocked_at->format('d/m/Y') }}
                        · {{ number_format($batch->initial_quantity, 0, ',', '.') }} peces
                    </span>
                </span>
                <x-batch-status :status="$batch->status" />
                <a class="btn sm" href="{{ route('batches.show', $batch) }}">Ver</a>
            </div>
        @empty
            <p style="color:var(--ink-3);font-size:13px;margin:0">Este estanque todavía no tiene lotes registrados.</p>
        @endforelse
        @can('create', App\Models\Batch::class)
            @if ($pond->is_active && $pond->farm->is_active)
                <a class="btn block sm" style="margin-top:12px" href="{{ route('batches.create', ['pond' => $pond->id]) }}">Nuevo lote</a>
            @endif
        @endcan
    </div>
@endsection
