@props(['name', 'label', 'options' => [], 'value' => null, 'help' => null, 'placeholder' => null])

@php
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);
    $selected = (string) old($name, $value);
    $describedBy = collect([$help ? $id.'-help' : null, $hasError ? $id.'-error' : null])->filter()->implode(' ');
@endphp

<div class="field">
    <label for="{{ $id }}">{{ $label }}</label>
    <select
        {{ $attributes->class(['input', 'is-invalid' => $hasError])->merge([
            'id' => $id,
            'name' => $name,
            'aria-invalid' => $hasError ? 'true' : null,
            'aria-describedby' => $describedBy ?: null,
        ]) }}
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($help)
        <span class="help" id="{{ $id }}-help">{{ $help }}</span>
    @endif
    @error($name)
        <span class="error" id="{{ $id }}-error">{{ $message }}</span>
    @enderror
</div>
