@php /** @var \App\Models\Metric $item */ @endphp

<section class="form-section">
    <h3>Parámetro</h3>
    <p>El código identifica el parámetro en las lecturas manuales, los sensores y la API; no se puede cambiar después de crearlo.</p>
    <div class="grid g2">
        @if ($item->exists)
            <div class="field">
                <label>Código</label>
                <div class="static"><code class="key">{{ $item->code }}</code></div>
            </div>
        @else
            <x-form.input name="code" label="Código" :value="$item->code" required maxlength="40" autocomplete="off"
                          help="Minúsculas, números y guion bajo. Ejemplo: dissolved_oxygen." />
        @endif
        <x-form.input name="name" label="Nombre" :value="$item->name" required maxlength="100" />
        <x-form.input name="unit" label="Unidad (opcional)" :value="$item->unit" maxlength="20" help="Ejemplo: °C, mg/L, cm." />
        <x-form.input name="decimals" label="Decimales a mostrar" type="number" min="0" max="4" :value="$item->decimals" required />
    </div>
</section>

<section class="form-section">
    <h3>Rango válido de medición</h3>
    <p>Valores físicamente posibles. Las lecturas fuera de este rango se descartarán. No son los umbrales de alerta, que se configuran por finca.</p>
    <div class="grid g2">
        <x-form.input name="min_value" label="Valor mínimo (opcional)" inputmode="decimal" :value="$item->min_value" />
        <x-form.input name="max_value" label="Valor máximo (opcional)" inputmode="decimal" :value="$item->max_value" />
        @unless ($item->exists)
            <x-form.select name="is_active" label="Estado" :options="['1' => 'Activo', '0' => 'Inactivo']"
                           :value="$item->is_active ? '1' : '0'" required />
        @endunless
    </div>
</section>
