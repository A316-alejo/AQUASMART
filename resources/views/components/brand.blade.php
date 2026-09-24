@props(['subtitle' => 'Piscícola'])

<div {{ $attributes->class(['brand']) }}>
    <span class="mark">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
            <path d="M2 12c3-4 6-4 9 0-3 4-6 4-9 0Z"/><path d="M19 7v3M19 14v3M17.5 8.5h3M17.5 15.5h3"/>
        </svg>
    </span>
    <span><b>AquaSmart</b><span>{{ $subtitle }}</span></span>
</div>
