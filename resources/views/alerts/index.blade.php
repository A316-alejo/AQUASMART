@extends('layouts.app')
@section('title', 'Centro de alertas — AquaSmart')

@php
    $activeView = $filters['view'] ?? null;
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Centro de alertas</h1>
            <p>Alertas generadas por las mediciones de calidad del agua fuera de los umbrales configurados.</p>
        </div>
    </div>

    <nav class="chips tabs" aria-label="Vistas del centro de alertas">
        <a class="chip" href="{{ route('alerts.index') }}" @unless ($activeView) aria-current="page" @endunless>Todas</a>
        <a class="chip" href="{{ route('alerts.index', ['view' => 'pending']) }}" @if ($activeView === 'pending') aria-current="page" @endif>
            Pendientes <span class="count">{{ $counts['pending'] }}</span>
        </a>
        <a class="chip" href="{{ route('alerts.index', ['view' => 'critical']) }}" @if ($activeView === 'critical') aria-current="page" @endif>
            Críticas <span class="count">{{ $counts['critical'] }}</span>
        </a>
        <a class="chip" href="{{ route('alerts.index', ['view' => 'warning']) }}" @if ($activeView === 'warning') aria-current="page" @endif>
            Advertencias <span class="count">{{ $counts['warning'] }}</span>
        </a>
        <a class="chip" href="{{ route('alerts.index', ['view' => 'resolved']) }}" @if ($activeView === 'resolved') aria-current="page" @endif>
            Resueltas <span class="count">{{ $counts['resolved'] }}</span>
        </a>
    </nav>

    <div class="card">
        <form class="filters" method="GET" action="{{ route('alerts.index') }}" role="search">
            <x-form.select name="farm" label="Finca" placeholder="Todas" :options="$farms" :value="$filters['farm'] ?? null" />
            <x-form.select name="batch" label="Lote" placeholder="Todos" :options="$batches" :value="$filters['batch'] ?? null" />
            <x-form.select name="metric" label="Parámetro" placeholder="Todos" :options="$metrics" :value="$filters['metric'] ?? null" />
            <x-form.select name="severity" label="Severidad" placeholder="Todas"
                           :options="['advertencia' => 'Advertencia', 'critico' => 'Crítico']" :value="$filters['severity'] ?? null" />
            <x-form.select name="status" label="Estado" placeholder="Todos" :options="App\Enums\AlertStatus::options()" :value="$filters['status'] ?? null" />
            <x-form.input name="from" label="Desde" type="date" :value="$filters['from'] ?? null" />
            <x-form.input name="to" label="Hasta" type="date" :value="$filters['to'] ?? null" />
            <div class="btns">
                <button type="submit" class="btn">Filtrar</button>
                @if (array_filter(collect($filters)->except('view')->all()))
                    <a class="btn ghost" href="{{ route('alerts.index') }}">Limpiar</a>
                @endif
            </div>
        </form>

        @if ($alerts->isEmpty())
            <div class="empty">
                <div class="glyph"><x-icon name="alert" /></div>
                @if (array_filter($filters))
                    <h3>No hay alertas que coincidan</h3>
                    <p>Prueba con otra finca, lote, parámetro, severidad, estado o fecha.</p>
                @else
                    <h3>No hay alertas registradas</h3>
                    <p>Las mediciones de calidad del agua fuera de los umbrales configurados aparecerán aquí.</p>
                @endif
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Fecha</th>
                            <th scope="col">Finca</th>
                            <th scope="col">Lote</th>
                            <th scope="col">Parámetro</th>
                            <th scope="col" class="num">Valor</th>
                            <th scope="col">Severidad</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="num">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($alerts as $alert)
                            <tr>
                                <td class="nowrap">{{ $alert->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $alert->batch->pond->farm->name }}</td>
                                <td>
                                    <a class="link" style="color:inherit" href="{{ route('batches.show', $alert->batch) }}">{{ $alert->batch->code }}</a>
                                    <span class="sub-line">{{ $alert->batch->pond->code }}</span>
                                </td>
                                <td>{{ $alert->metric->name }}</td>
                                <td class="num nowrap">{{ $alert->metric->format($alert->value) }}</td>
                                <td><x-quality-status :status="$alert->severity" /></td>
                                <td><x-alert-status :status="$alert->status" /></td>
                                <td>
                                    <div class="row-actions">
                                        @can('acknowledge', $alert)
                                            <form class="inline-form" method="POST" action="{{ route('alerts.acknowledge', $alert) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn sm">Reconocer</button>
                                            </form>
                                        @endcan
                                        @can('resolve', $alert)
                                            <form class="inline-form" method="POST" action="{{ route('alerts.resolve', $alert) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn sm primary">Resolver</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $alerts->links() }}
        @endif
    </div>
@endsection
