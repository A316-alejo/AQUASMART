@props(['active'])

<span {{ $attributes->class(['tag', $active ? 'ok' : 'mute']) }}>{{ $active ? 'Activo' : 'Inactivo' }}</span>
