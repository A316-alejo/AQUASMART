@php /** @var \App\Models\Device $device */ @endphp

<section class="form-section">
    <h3>Identificación</h3>
    <p>El código es único en toda la plataforma. La finca no se puede cambiar después de registrar el dispositivo.</p>
    <div class="grid g2">
        @if ($device->exists)
            <div class="field">
                <label>Finca</label>
                <div class="static">{{ $device->farm->name }} — {{ $device->farm->producer->name }}</div>
            </div>
        @else
            <x-form.select name="farm_id" label="Finca" :options="$farms" placeholder="Selecciona una finca"
                           :value="$device->farm_id" required help="Solo aparecen fincas activas." />
        @endif
        <x-form.select name="type" label="Tipo de dispositivo" :options="App\Enums\DeviceType::options()"
                       :value="$device->type?->value" required />
        <x-form.input name="code" label="Código" :value="$device->code" required maxlength="30" autocomplete="off"
                      help="Letras, números y guiones. Se guarda en mayúsculas." />
        <x-form.input name="name" label="Nombre" :value="$device->name" required maxlength="100" />
    </div>
</section>

@unless ($device->exists)
    <section class="form-section">
        <h3>Estado</h3>
        <p>El token de autenticación se genera automáticamente y se muestra una sola vez al registrar el dispositivo.</p>
        <div class="grid g2">
            <x-form.select name="is_active" label="Estado" :options="['1' => 'Activo', '0' => 'Inactivo']"
                           :value="$device->is_active ? '1' : '0'" required />
        </div>
    </section>
@endunless
