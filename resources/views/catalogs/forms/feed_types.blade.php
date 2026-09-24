@php /** @var \App\Models\FeedType $item */ @endphp

<section class="form-section">
    <h3>Alimento</h3>
    <p>La combinación de nombre y marca no se puede repetir. Puedes usar punto o coma decimal.</p>
    <div class="grid g2">
        <x-form.input name="name" label="Nombre o etapa" :value="$item->name" required maxlength="100"
                      help="Por ejemplo: Iniciación, Levante, Engorde." />
        <x-form.input name="brand" label="Marca (opcional)" :value="$item->brand" maxlength="100" />
        <x-form.input name="protein_pct" label="Proteína (%)" inputmode="decimal" :value="$item->protein_pct" required />
        <x-form.input name="pellet_mm" label="Tamaño del pellet (mm, opcional)" inputmode="decimal" :value="$item->pellet_mm" />
        @unless ($item->exists)
            <x-form.select name="is_active" label="Estado" :options="['1' => 'Activo', '0' => 'Inactivo']"
                           :value="$item->is_active ? '1' : '0'" required />
        @endunless
    </div>
</section>
