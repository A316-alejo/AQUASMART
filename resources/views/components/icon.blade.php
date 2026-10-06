@props(['name'])

<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>
            @break
        @case('producers')
            <circle cx="9" cy="8" r="3.2"/><path d="M3 20c.6-3.4 3-5.4 6-5.4s5.4 2 6 5.4"/><path d="M16 4.6a3.2 3.2 0 0 1 0 6.2M18 14.8c1.7.7 2.7 2.4 3 5.2"/>
            @break
        @case('farm')
            <path d="M3 10.5 12 4l9 6.5"/><path d="M5 9.3V20h14V9.3"/><path d="M10 20v-5h4v5"/>
            @break
        @case('pond')
            <path d="M2 9c2.5-2 4.5-2 7 0s4.5 2 7 0 4.5-2 6 0"/><path d="M2 15c2.5-2 4.5-2 7 0s4.5 2 7 0 4.5-2 6 0"/>
            @break
        @case('fish')
            <path d="M2 12c3-4 6-4 9 0-3 4-6 4-9 0Z"/><path d="M11 12c2.5-3.5 6.5-4.5 9.5-1.5L22 12l-1.5 1.5C17.5 16.5 13.5 15.5 11 12Z"/>
            @break
        @case('iot')
            <rect x="7" y="7" width="10" height="10" rx="2"/><path d="M10 3v4M14 3v4M10 17v4M14 17v4M3 10h4M3 14h4M17 10h4M17 14h4"/>
            @break
        @case('alert')
            <path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15Z"/><path d="M10 20a2 2 0 0 0 4 0"/>
            @break
        @case('stats')
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>
            @break
        @case('users')
            <circle cx="12" cy="8" r="3.5"/><path d="M5 20c.8-3.8 3.6-6 7-6s6.2 2.2 7 6"/>
            @break
        @case('catalog')
            <path d="M5 5.5A2.5 2.5 0 0 1 7.5 3H19v15H7.5A2.5 2.5 0 0 0 5 20.5Z"/><path d="M5 20.5A2.5 2.5 0 0 1 7.5 18H19v3H7.5"/><path d="M9 7.5h6M9 11h4"/>
            @break
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16"/>
            @break
        @case('logout')
            <path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 16l-4-4 4-4M6 12h10"/>
            @break
        @case('shield')
            <path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6Z"/><path d="m9 12 2 2 4-4"/>
            @break
    @endswitch
</svg>
