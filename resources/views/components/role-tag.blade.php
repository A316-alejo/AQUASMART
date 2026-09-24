@props(['role'])

<span {{ $attributes->class(['tag', 'plain', $role === \App\Enums\Role::Admin ? 'info' : 'mute']) }}>{{ $role->label() }}</span>
