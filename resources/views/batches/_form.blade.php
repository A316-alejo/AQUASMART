@php /** @var \App\Models\Batch $batch */ @endphp

<section class="form-section">
    <h3>Ubicación y especie</h3>
    <p>El estanque no se puede cambiar después de registrar el lote.</p>
    <div class="grid g2">
        @if ($batch->exists)
            <div class="field">
                <label>Estanque</label>
                <div class="static">{{ $batch->pond->farm->name }} — {{ $batch->pond->code }} · {{ $batch->pond->name }}</div>
            </div>
        @else
            <x-form.select name="pond_id" label="Estanque" :options="$ponds" placeholder="Selecciona un estanque"
                           :value="$batch->pond_id" required help="Solo aparecen estanques activos de fincas activas." />
        @endif
        <x-form.select name="species_id" label="Especie" :options="$species" placeholder="Selecciona una especie"
                       :value="$batch->species_id" required />
    </div>
</section>

<section class="form-section">
    <h3>Identificación</h3>
    <p>El código es único en toda la plataforma (por ejemplo L-2026-014).</p>
    <div class="grid g2">
        <x-form.input name="code" label="Código" :value="$batch->code" required maxlength="30" autocomplete="off"
                      help="Letras, números y guiones. Se guarda en mayúsculas." />
        <x-form.input name="name" label="Nombre" :value="$batch->name" required maxlength="100" />
    </div>
</section>

<section class="form-section">
    <h3>Siembra</h3>
    <p>Datos iniciales del ciclo. Puedes usar punto o coma decimal en el peso.</p>
    <div class="grid g2">
        <x-form.input name="stocked_at" label="Fecha de siembra" type="date" :value="$batch->stocked_at?->toDateString()"
                      max="{{ today()->toDateString() }}" required />
        <x-form.input name="initial_quantity" label="Cantidad sembrada (peces)" inputmode="numeric" :value="$batch->initial_quantity" required />
        <x-form.input name="initial_average_weight_g" label="Peso promedio inicial (g)" inputmode="decimal"
                      :value="$batch->initial_average_weight_g" required />
    </div>
    <x-form.textarea name="notes" label="Observaciones (opcional)" :value="$batch->notes" maxlength="1000" />
</section>
