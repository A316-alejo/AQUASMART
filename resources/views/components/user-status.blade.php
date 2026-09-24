@props(['active', 'on' => 'Activo', 'off' => 'Inactivo'])

<span {{ $attributes->class(['tag', $active ? 'ok' : 'mute']) }}>{{ $active ? $on : $off }}</span>
