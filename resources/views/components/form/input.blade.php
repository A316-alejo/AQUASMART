@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null])

@php
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);
    $describedBy = collect([$help ? $id.'-help' : null, $hasError ? $id.'-error' : null])->filter()->implode(' ');
@endphp

<div class="field">
    <label for="{{ $id }}">{{ $label }}</label>
    <input
        {{ $attributes->class(['input', 'is-invalid' => $hasError])->merge([
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'value' => $type === 'password' ? null : old($name, $value),
            'aria-invalid' => $hasError ? 'true' : null,
            'aria-describedby' => $describedBy ?: null,
        ]) }}
    >
    @if ($help)
        <span class="help" id="{{ $id }}-help">{{ $help }}</span>
    @endif
    @error($name)
        <span class="error" id="{{ $id }}-error">{{ $message }}</span>
    @enderror
</div>
