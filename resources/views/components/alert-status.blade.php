@props(['status'])

<span {{ $attributes->class(['tag', $status->tone()]) }}>{{ $status->label() }}</span>
