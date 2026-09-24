@php /** @var \App\Models\Pond $pond */ @endphp

<section class="form-section">
    <h3>Identificación</h3>
    <p>El código es único dentro de la finca (por ejemplo E01, T-03). La finca no se puede cambiar después de registrar el estanque.</p>
    <div class="grid g2">
        @if ($pond->exists)
            <div class="field">
                <label>Finca</label>
                <div class="static">{{ $pond->farm->name }} — {{ $pond->farm->producer->name }}</div>
            </div>
        @else
            <x-form.select name="farm_id" label="Finca" :options="$farms" placeholder="Selecciona una finca"
                           :value="$pond->farm_id" required help="Solo aparecen fincas activas." />
        @endif
        <x-form.select name="type" label="Tipo de estanque" :options="App\Enums\PondType::options()"
                       :value="$pond->type?->value" required />
        <x-form.input name="code" label="Código" :value="$pond->code" required maxlength="20" autocomplete="off"
                      help="Letras, números y guiones. Se guarda en mayúsculas." />
        <x-form.input name="name" label="Nombre" :value="$pond->name" required maxlength="100" />
    </div>
</section>

<section class="form-section">
    <h3>Dimensiones</h3>
    <p>Registra al menos la superficie o el volumen. Puedes usar punto o coma decimal.</p>
    <div class="grid g2">
        <x-form.input name="surface_m2" label="Superficie (m²)" inputmode="decimal" :value="$pond->surface_m2" />
        <x-form.input name="volume_m3" label="Volumen (m³)" inputmode="decimal" :value="$pond->volume_m3" />
        <x-form.input name="depth_m" label="Profundidad promedio (m, opcional)" inputmode="decimal" :value="$pond->depth_m" />
        @unless ($pond->exists)
            <x-form.select name="is_active" label="Estado" :options="['1' => 'Activo', '0' => 'Inactivo']"
                           :value="$pond->is_active ? '1' : '0'" required />
        @endunless
    </div>
</section>
