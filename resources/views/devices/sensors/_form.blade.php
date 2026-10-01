@php
    /** @var \App\Models\Device $device */
    /** @var \App\Models\Sensor $sensor */
@endphp

<section class="form-section">
    <h3>Identificación</h3>
    <p>El código es único dentro del dispositivo. El estanque debe pertenecer a {{ $device->farm->name }}.</p>
    <div class="grid g2">
        <x-form.select name="pond_id" label="Estanque" :options="$ponds" placeholder="Selecciona un estanque"
                       :value="$sensor->pond_id" required help="Solo aparecen estanques activos de la finca del dispositivo." />
        <x-form.select name="metric_id" label="Parámetro" :options="$metrics" placeholder="Selecciona un parámetro"
                       :value="$sensor->metric_id" required help="Tomado del catálogo de calidad del agua." />
        <x-form.input name="code" label="Código" :value="$sensor->code" required maxlength="30" autocomplete="off"
                      help="Letras, números y guiones. Se guarda en mayúsculas." />
        <x-form.input name="name" label="Nombre" :value="$sensor->name" required maxlength="100" />
    </div>
</section>

@unless ($sensor->exists)
    <section class="form-section">
        <h3>Estado</h3>
        <div class="grid g2">
            <x-form.select name="is_active" label="Estado" :options="['1' => 'Activo', '0' => 'Inactivo']"
                           :value="$sensor->is_active ? '1' : '0'" required />
        </div>
    </section>
@endunless
