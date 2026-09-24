@php /** @var \App\Models\Species $item */ @endphp

<section class="form-section">
    <h3>Especie</h3>
    <p>El nombre común es único y es el que se mostrará al registrar lotes.</p>
    <div class="grid g2">
        <x-form.input name="common_name" label="Nombre común" :value="$item->common_name" required maxlength="100" />
        <x-form.input name="scientific_name" label="Nombre científico (opcional)" :value="$item->scientific_name" maxlength="150" />
        @unless ($item->exists)
            <x-form.select name="is_active" label="Estado" :options="['1' => 'Activa', '0' => 'Inactiva']"
                           :value="$item->is_active ? '1' : '0'" required />
        @endunless
    </div>
</section>
