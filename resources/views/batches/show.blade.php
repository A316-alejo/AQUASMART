@extends('layouts.app')
@section('title', $batch->code.' — AquaSmart')

@section('content')
    <div class="page-head">
        <div>
            <div class="breadcrumb"><a href="{{ route('batches.index') }}">Lotes</a> / Detalle</div>
            <h1>{{ $batch->code }} · {{ $batch->name }}</h1>
            <p>
                <a class="link" href="{{ route('ponds.show', $batch->pond) }}">{{ $batch->pond->code }} · {{ $batch->pond->name }}</a>
                · <a class="link" href="{{ route('farms.show', $batch->pond->farm) }}">{{ $batch->pond->farm->name }}</a>
                · {{ $batch->species->common_name }}
            </p>
        </div>
        <div class="actions">
            <x-batch-status :status="$batch->status" />
            @can('update', $batch)
                <a class="btn" href="{{ route('batches.edit', $batch) }}">Editar</a>
            @endcan
            @can('cancel', $batch)
                <a class="btn" href="{{ route('batches.cancel.confirm', $batch) }}">Cancelar lote</a>
            @endcan
            @can('close', $batch)
                <a class="btn primary" href="{{ route('batches.close.confirm', $batch) }}">Cerrar lote</a>
            @endcan
        </div>
    </div>

    @unless ($batch->isActive())
        <div @class(['notice', 'crit' => $batch->status === App\Enums\BatchStatus::Cancelled, 'info' => $batch->status !== App\Enums\BatchStatus::Cancelled]) role="status">
            Este lote fue {{ Str::lower($batch->status->label()) }} el {{ $batch->closed_at?->format('d/m/Y') }}. Se conserva como registro histórico y ya no se puede editar.
        </div>
    @endunless

    <div class="grid g3" style="margin-bottom:14px">
        <div class="card kpi">
            <div class="label">Peces sembrados</div>
            <div class="value">{{ number_format($batch->initial_quantity, 0, ',', '.') }}</div>
            <div class="foot">Cantidad inicial</div>
        </div>
        <div class="card kpi">
            <div class="label">Peso promedio inicial</div>
            <div class="value">{{ number_format((float) $batch->initial_average_weight_g, 2, ',', '.') }} <small>g</small></div>
            <div class="foot">Al momento de la siembra</div>
        </div>
        <div class="card kpi">
            <div class="label">Días de cultivo</div>
            <div class="value">{{ number_format($batch->daysInCulture(), 0, ',', '.') }}</div>
            <div class="foot">{{ $batch->isActive() ? 'Desde la siembra hasta hoy' : 'Duración del ciclo' }}</div>
        </div>
    </div>

    <div class="card">
        <header><h3>Información del lote</h3></header>
        <dl class="def">
            <dt>Código</dt>
            <dd>{{ $batch->code }}</dd>
            <dt>Nombre</dt>
            <dd>{{ $batch->name }}</dd>
            <dt>Especie</dt>
            <dd>{{ $batch->species->common_name }}@if ($batch->species->scientific_name) · <i>{{ $batch->species->scientific_name }}</i>@endif</dd>
            <dt>Estanque</dt>
            <dd>{{ $batch->pond->code }} · {{ $batch->pond->name }}</dd>
            <dt>Finca</dt>
            <dd>{{ $batch->pond->farm->name }} · {{ $batch->pond->farm->producer->name }}</dd>
            <dt>Fecha de siembra</dt>
            <dd>{{ $batch->stocked_at->format('d/m/Y') }}</dd>
            <dt>Estado</dt>
            <dd>{{ $batch->status->label() }}@if ($batch->closed_at) · {{ $batch->closed_at->format('d/m/Y') }}@endif</dd>
            <dt>Observaciones</dt>
            <dd style="white-space:pre-line">{{ $batch->notes ?: 'Sin observaciones' }}</dd>
            <dt>Registrado</dt>
            <dd>{{ $batch->created_at?->format('d/m/Y H:i') }}</dd>
        </dl>
    </div>
@endsection
