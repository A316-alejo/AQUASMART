@extends('layouts.app')
@section('title', $batch->code.' — AquaSmart')

@php
    /** @var \App\Services\BiomassEstimate $estimate */
    $int = fn ($value) => number_format((int) $value, 0, ',', '.');
    $dec = fn ($value, $decimals = 2) => number_format((float) $value, $decimals, ',', '.');
    $today = today()->toDateString();
    $stocked = $batch->stocked_at->toDateString();
@endphp

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
            Este lote fue {{ Str::lower($batch->status->label()) }} el {{ $batch->closed_at?->format('d/m/Y') }}. Se conserva como registro histórico: ya no se puede editar ni admite nuevos registros.
        </div>
    @endunless

    <nav class="chips tabs" aria-label="Secciones del lote">
        <a class="chip" href="#summary">Resumen productivo</a>
        <a class="chip" href="#feeding">Alimentación <span class="count">{{ $feedings->total() }}</span></a>
        <a class="chip" href="#mortality">Mortalidad <span class="count">{{ $mortalities->total() }}</span></a>
        <a class="chip" href="#sampling">Biometría <span class="count">{{ $samplings->total() }}</span></a>
    </nav>

    <section id="summary" class="anchor-target" aria-label="Resumen productivo">
        <div class="grid g4" style="margin-bottom:14px">
            <div class="card kpi">
                <div class="label">Peces iniciales</div>
                <div class="value">{{ $int($estimate->initialQuantity) }}</div>
                <div class="foot">Sembrados el {{ $batch->stocked_at->format('d/m/Y') }}</div>
            </div>
            <div class="card kpi">
                <div class="label">Mortalidad acumulada</div>
                <div class="value">{{ $int($estimate->cumulativeMortality) }}</div>
                <div class="foot">{{ $dec(100 - $estimate->survivalPct(), 1) }} % de la siembra</div>
            </div>
            <div class="card kpi">
                <div class="label">Peces vivos estimados</div>
                <div class="value">{{ $int($estimate->liveFish) }}</div>
                <div class="foot">Sobrevivencia {{ $dec($estimate->survivalPct(), 1) }} %</div>
            </div>
            <div class="card kpi">
                <div class="label">Días de cultivo</div>
                <div class="value">{{ $int($batch->daysInCulture()) }}</div>
                <div class="foot">{{ $batch->isActive() ? 'Desde la siembra hasta hoy' : 'Duración del ciclo' }}</div>
            </div>
        </div>

        <div class="grid g3" style="margin-bottom:14px">
            <div class="card kpi">
                <div class="label">Peso promedio actual</div>
                <div class="value">{{ $dec($estimate->averageWeightG) }} <small>g</small></div>
                <div class="foot">
                    {{ $estimate->usesSampling() ? 'Biometría del '.$estimate->weightSampledOn->format('d/m/Y') : 'Peso de siembra (aún sin biometrías)' }}
                </div>
            </div>
            <div class="card kpi">
                <div class="label">Biomasa estimada</div>
                <div class="value">{{ $dec($estimate->biomassKg) }} <small>kg</small></div>
                <div class="foot">Peces vivos × peso promedio actual</div>
            </div>
            <div class="card kpi">
                <div class="label">Alimento suministrado</div>
                <div class="value">{{ $dec(((float) $batch->feedings_sum_quantity_g) / 1000) }} <small>kg</small></div>
                <div class="foot">Total registrado en el ciclo</div>
            </div>
        </div>
    </section>

    <div class="card" style="margin-bottom:14px">
        <header><h3>Información del lote</h3></header>
        <dl class="def">
            <dt>Especie</dt>
            <dd>{{ $batch->species->common_name }}@if ($batch->species->scientific_name) · <i>{{ $batch->species->scientific_name }}</i>@endif</dd>
            <dt>Estanque</dt>
            <dd>{{ $batch->pond->code }} · {{ $batch->pond->name }}</dd>
            <dt>Finca</dt>
            <dd>{{ $batch->pond->farm->name }} · {{ $batch->pond->farm->producer->name }}</dd>
            <dt>Siembra</dt>
            <dd>{{ $batch->stocked_at->format('d/m/Y') }} · {{ $int($batch->initial_quantity) }} peces de {{ $dec($batch->initial_average_weight_g) }} g</dd>
            <dt>Estado</dt>
            <dd>{{ $batch->status->label() }}@if ($batch->closed_at) · {{ $batch->closed_at->format('d/m/Y') }}@endif</dd>
            <dt>Observaciones</dt>
            <dd style="white-space:pre-line">{{ $batch->notes ?: 'Sin observaciones' }}</dd>
            <dt>Registrado</dt>
            <dd>{{ $batch->created_at?->format('d/m/Y H:i') }}</dd>
        </dl>
    </div>

    {{-- Alimentación --}}
    <section id="feeding" class="card anchor-target" style="margin-bottom:14px">
        <header>
            <h3>Alimentación</h3>
            <span class="tag info plain">{{ $feedings->total() }}</span>
        </header>

        @if ($canRecord)
            <form class="record-form" method="POST" action="{{ route('batches.feedings.store', $batch) }}" novalidate>
                @csrf
                <div class="grid g3">
                    <x-form.input name="feeding_date" label="Fecha" type="date" :value="$today" min="{{ $stocked }}" max="{{ $today }}" required />
                    <x-form.select name="feed_type_id" label="Tipo de alimento" :options="$feedTypes" placeholder="Selecciona un alimento" required />
                    <x-form.input name="feeding_quantity_g" label="Cantidad (g)" inputmode="decimal" required help="En gramos. Ejemplo: 12500 = 12,5 kg." />
                </div>
                <x-form.textarea name="feeding_notes" label="Observaciones (opcional)" maxlength="1000" rows="2" />
                <div class="form-actions"><button type="submit" class="btn primary">Registrar alimentación</button></div>
            </form>
        @endif

        @if ($feedings->isEmpty())
            <p class="muted-line">Todavía no hay registros de alimentación.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Fecha</th>
                            <th scope="col">Alimento</th>
                            <th scope="col" class="num">Cantidad</th>
                            <th scope="col">Registrado por</th>
                            <th scope="col">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($feedings as $feeding)
                            <tr>
                                <td class="nowrap">{{ $feeding->fed_on->format('d/m/Y') }}</td>
                                <td>{{ $feeding->feedType->catalogLabel() }}</td>
                                <td class="num nowrap">
                                    {{ $dec($feeding->quantity_g / 1000) }} kg
                                    <span class="sub-line">{{ $dec($feeding->quantity_g) }} g</span>
                                </td>
                                <td>{{ $feeding->user?->name ?? '—' }}</td>
                                <td class="notes-cell">{{ $feeding->notes ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $feedings->links() }}
        @endif
    </section>

    {{-- Mortalidad --}}
    <section id="mortality" class="card anchor-target" style="margin-bottom:14px">
        <header>
            <h3>Mortalidad</h3>
            <span class="tag info plain">{{ $mortalities->total() }}</span>
        </header>

        @if ($canRecord)
            <form class="record-form" method="POST" action="{{ route('batches.mortalities.store', $batch) }}" novalidate>
                @csrf
                <div class="grid g3">
                    <x-form.input name="mortality_date" label="Fecha" type="date" :value="$today" min="{{ $stocked }}" max="{{ $today }}" required />
                    <x-form.select name="mortality_cause_id" label="Causa" :options="$causes" placeholder="Selecciona una causa" required />
                    <x-form.input name="mortality_quantity" label="Peces muertos" type="number" min="1" max="{{ $estimate->liveFish }}" required
                                  help="Peces vivos estimados: {{ $int($estimate->liveFish) }}." />
                </div>
                <x-form.textarea name="mortality_notes" label="Observaciones (opcional)" maxlength="1000" rows="2" />
                <div class="form-actions"><button type="submit" class="btn primary">Registrar mortalidad</button></div>
            </form>
        @endif

        @if ($mortalities->isEmpty())
            <p class="muted-line">Todavía no hay registros de mortalidad.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Fecha</th>
                            <th scope="col">Causa</th>
                            <th scope="col" class="num">Peces</th>
                            <th scope="col">Registrado por</th>
                            <th scope="col">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mortalities as $mortality)
                            <tr>
                                <td class="nowrap">{{ $mortality->recorded_on->format('d/m/Y') }}</td>
                                <td>{{ $mortality->cause->name }}</td>
                                <td class="num">{{ $int($mortality->quantity) }}</td>
                                <td>{{ $mortality->user?->name ?? '—' }}</td>
                                <td class="notes-cell">{{ $mortality->notes ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $mortalities->links() }}
        @endif
    </section>

    {{-- Biometría --}}
    <section id="sampling" class="card anchor-target">
        <header>
            <h3>Biometría</h3>
            <span class="tag info plain">{{ $samplings->total() }}</span>
        </header>

        @if ($canRecord)
            <form class="record-form" method="POST" action="{{ route('batches.samplings.store', $batch) }}" novalidate>
                @csrf
                <div class="grid g4">
                    <x-form.input name="sampling_date" label="Fecha" type="date" :value="$today" min="{{ $stocked }}" max="{{ $today }}" required />
                    <x-form.input name="sample_size" label="Peces muestreados" type="number" min="1" max="{{ $estimate->liveFish }}" required />
                    <x-form.input name="average_weight_g" label="Peso promedio (g)" inputmode="decimal" required />
                    <x-form.input name="average_length_cm" label="Longitud promedio (cm, opcional)" inputmode="decimal" />
                </div>
                <x-form.textarea name="sampling_notes" label="Observaciones (opcional)" maxlength="1000" rows="2" />
                <div class="form-actions"><button type="submit" class="btn primary">Registrar biometría</button></div>
            </form>
        @endif

        @if ($samplings->isEmpty())
            <p class="muted-line">Todavía no hay biometrías. La biomasa se estima con el peso promedio de siembra.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Fecha</th>
                            <th scope="col" class="num">Muestra</th>
                            <th scope="col" class="num">Peso prom.</th>
                            <th scope="col" class="num">Longitud prom.</th>
                            <th scope="col">Registrado por</th>
                            <th scope="col">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($samplings as $sampling)
                            <tr>
                                <td class="nowrap">{{ $sampling->sampled_on->format('d/m/Y') }}</td>
                                <td class="num">{{ $int($sampling->sample_size) }}</td>
                                <td class="num nowrap">{{ $dec($sampling->average_weight_g) }} g</td>
                                <td class="num nowrap">{{ $sampling->average_length_cm !== null ? $dec($sampling->average_length_cm).' cm' : '—' }}</td>
                                <td>{{ $sampling->user?->name ?? '—' }}</td>
                                <td class="notes-cell">{{ $sampling->notes ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $samplings->links() }}
        @endif
    </section>
@endsection
