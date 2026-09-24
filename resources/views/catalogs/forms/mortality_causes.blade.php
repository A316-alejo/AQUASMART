@php /** @var \App\Models\MortalityCause $item */ @endphp

<section class="form-section">
    <h3>Causa de mortalidad</h3>
    <p>Se usará para clasificar los registros de mortalidad de los lotes.</p>
    <div class="grid g2">
        <x-form.input name="name" label="Nombre" :value="$item->name" required maxlength="100" />
        @unless ($item->exists)
            <x-form.select name="is_active" label="Estado" :options="['1' => 'Activa', '0' => 'Inactiva']"
                           :value="$item->is_active ? '1' : '0'" required />
        @endunless
    </div>
</section>
