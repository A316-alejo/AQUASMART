@php
    /** @var \App\Models\Farm $farm */
    $departments = collect(config('aquasmart.departments'))->mapWithKeys(fn ($name) => [$name => $name])->all();
    $timezones = collect(DateTimeZone::listIdentifiers(DateTimeZone::AMERICA))->mapWithKeys(fn ($tz) => [$tz => $tz])->all();
@endphp

<section class="form-section">
    <h3>Finca</h3>
    <p>Cada finca pertenece a un solo productor, que no se puede cambiar después de registrarla.</p>
    <div class="grid g2">
        @if ($farm->exists)
            <div class="field">
                <label>Productor</label>
                <div class="static">{{ $farm->producer->name }}</div>
            </div>
        @else
            <x-form.select name="producer_id" label="Productor" :options="$producers" placeholder="Selecciona un productor"
                           :value="$farm->producer_id" required help="Solo aparecen productores activos." />
        @endif
        <x-form.input name="name" label="Nombre de la finca" :value="$farm->name" required maxlength="150" />
    </div>
</section>

<section class="form-section">
    <h3>Ubicación</h3>
    <p>Las coordenadas son opcionales; si registras una, registra ambas. Puedes usar punto o coma decimal.</p>
    <div class="grid g2">
        <x-form.select name="department" label="Departamento" :options="$departments" placeholder="Selecciona un departamento"
                       :value="$farm->department" required />
        <x-form.input name="municipality" label="Municipio" :value="$farm->municipality" required maxlength="100" />
        <x-form.input name="location" label="Vereda o dirección (opcional)" :value="$farm->location" maxlength="255" />
        <div></div>
        <x-form.input name="latitude" label="Latitud (opcional)" inputmode="decimal" :value="$farm->latitude"
                      help="Entre -90 y 90. Ejemplo: 2.8876543" />
        <x-form.input name="longitude" label="Longitud (opcional)" inputmode="decimal" :value="$farm->longitude"
                      help="Entre -180 y 180. Ejemplo: -75.4312345" />
    </div>
</section>

<section class="form-section">
    <h3>Operación</h3>
    <p>La zona horaria se usa para registrar la hora de las mediciones de los estanques.</p>
    <div class="grid g2">
        <x-form.input name="area_ha" label="Área (hectáreas, opcional)" inputmode="decimal" :value="$farm->area_ha" />
        <x-form.select name="timezone" label="Zona horaria" :options="$timezones" :value="$farm->timezone" required />
        @unless ($farm->exists)
            <x-form.select name="is_active" label="Estado" :options="['1' => 'Activa', '0' => 'Inactiva']"
                           :value="$farm->is_active ? '1' : '0'" required />
        @endunless
    </div>
</section>
