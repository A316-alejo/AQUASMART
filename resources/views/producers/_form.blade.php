@php
    /** @var \App\Models\Producer $producer */
    $departments = collect(config('aquasmart.departments'))->mapWithKeys(fn ($name) => [$name => $name])->all();
@endphp

<section class="form-section">
    <h3>Identificación</h3>
    <p>Una persona jurídica debe identificarse con NIT. Los puntos y espacios del documento se eliminan al guardar.</p>
    <div class="grid g2">
        <x-form.select name="person_type" label="Tipo de persona" :options="App\Enums\PersonType::options()"
                       :value="$producer->person_type?->value" required />
        <x-form.input name="name" label="Nombre o razón social" :value="$producer->name" required maxlength="150"
                      autocomplete="organization" />
        <x-form.select name="document_type" label="Tipo de documento" :options="App\Enums\DocumentType::options()"
                       :value="$producer->document_type?->value" required />
        <x-form.input name="document_number" label="Número de documento" :value="$producer->document_number" required
                      maxlength="20" autocomplete="off" help="Ejemplos: 1075234567 · 900123456-7" />
    </div>
</section>

<section class="form-section">
    <h3>Contacto</h3>
    <p>Datos para comunicarse con el productor.</p>
    <div class="grid g2">
        <x-form.input name="phone" label="Teléfono" type="tel" :value="$producer->phone" required maxlength="20"
                      autocomplete="tel" help="Celular o fijo. Puede incluir el indicativo, por ejemplo +57." />
        <x-form.input name="email" label="Correo electrónico (opcional)" type="email" :value="$producer->email"
                      maxlength="255" autocomplete="email" />
    </div>
</section>

<section class="form-section">
    <h3>Ubicación</h3>
    <p>Municipio y departamento donde está registrado el productor.</p>
    <div class="grid g2">
        <x-form.select name="department" label="Departamento" :options="$departments" placeholder="Selecciona un departamento"
                       :value="$producer->department" required />
        <x-form.input name="municipality" label="Municipio" :value="$producer->municipality" required maxlength="100"
                      autocomplete="address-level2" />
    </div>
</section>

@unless ($producer->exists)
    <section class="form-section">
        <h3>Estado</h3>
        <p>Si el productor está inactivo, sus usuarios no pueden iniciar sesión.</p>
        <div class="grid g2">
            <x-form.select name="is_active" label="Estado" :options="['1' => 'Activo', '0' => 'Inactivo']"
                           :value="$producer->is_active ? '1' : '0'" required />
        </div>
    </section>
@endunless
