@php
    /** @var \App\Models\User $user */
    $isEdit = $user->exists;
    $isSelf = $isEdit && $user->is(auth()->user());
    $roleOptions = collect($roles)->mapWithKeys(fn ($role) => [$role->value => $role->label()])->all();
@endphp

<section class="form-section">
    <h3>Datos personales</h3>
    <p>Así se identificará a la persona dentro de AquaSmart.</p>
    <div class="grid g2">
        <x-form.input name="name" label="Nombre completo" :value="$user->name" required maxlength="120" autocomplete="name" />
        <x-form.input name="email" label="Correo electrónico" type="email" :value="$user->email" required maxlength="255" autocomplete="email" />
    </div>
</section>

<section class="form-section">
    <h3>Acceso</h3>
    <p>El rol define qué puede ver y hacer.</p>
    <div class="grid g2">
        @if ($isSelf)
            <div class="field">
                <label>Rol</label>
                <div class="static"><x-role-tag :role="$user->role" /></div>
                <span class="help">No puedes cambiar tu propio rol. Pídeselo a otro administrador.</span>
            </div>
        @else
            <x-form.select name="role" label="Rol" :options="$roleOptions" :value="$user->role?->value" required
                           help="Administrador: control total · Productor: sus fincas · Técnico: registra datos · Consulta: solo lectura." />
        @endif

        @unless ($isSelf)
            <x-form.select name="producer_id" label="Productor asociado" :options="$producers" placeholder="Ninguno"
                           :value="$user->producer_id"
                           help="Obligatorio para el rol Productor; no aplica a administradores. Define qué información puede consultar." />
        @endunless

        @unless ($isEdit)
            <x-form.select name="is_active" label="Estado" :options="['1' => 'Activo', '0' => 'Inactivo']"
                           :value="$user->is_active ? '1' : '0'" required
                           help="Una cuenta inactiva no puede iniciar sesión." />
        @endunless
    </div>
</section>

<section class="form-section">
    <h3>Contraseña</h3>
    <p>
        @if ($isEdit)
            Déjala vacía para mantener la contraseña actual.
        @else
            Mínimo 8 caracteres, con letras y números. Compártela con la persona por un medio seguro.
        @endif
    </p>
    <div class="grid g2">
        @if ($isSelf)
            <x-form.input name="current_password" label="Contraseña actual" type="password" autocomplete="current-password"
                          help="Necesaria solo si vas a cambiar tu contraseña." />
            <div></div>
        @endif
        <x-form.input name="password" :label="$isEdit ? 'Nueva contraseña' : 'Contraseña'" type="password"
                      autocomplete="new-password" :required="! $isEdit" />
        <x-form.input name="password_confirmation" label="Confirmar contraseña" type="password"
                      autocomplete="new-password" :required="! $isEdit" />
    </div>
</section>
