@props(['name' => 'circle', 'size' => 20, 'stroke' => 1.75])

@php
    // REQ-08.02 — one icon set, consistent stroke weight, mirrored only where
    // the shape has directional meaning. Logos and generic glyphs are never
    // mirrored; arrows and chevrons are, which the `rtl-mirror` class handles.
    $paths = [
        'home'      => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V20h13V9.5"/>',
        'users'     => '<circle cx="9" cy="8" r="3.2"/><path d="M3.2 20c0-3.2 2.6-5.4 5.8-5.4s5.8 2.2 5.8 5.4"/><path d="M16 5.4a3.2 3.2 0 0 1 0 6.2"/><path d="M17.4 14.9c2 .6 3.4 2.4 3.4 5.1"/>',
        'document'  => '<path d="M6 2.8h7.5L19 8.3V21H6z"/><path d="M13.2 3v5.5H19"/><path d="M9 12.5h7M9 16h5"/>',
        'folder'    => '<path d="M3 6.5h6l2 2.5h10V20H3z"/>',
        'key'       => '<circle cx="8" cy="15.5" r="4"/><path d="m11 12.5 8-8"/><path d="m16.5 7 2 2M14.5 9l2 2"/>',
        'briefcase' => '<rect x="3" y="7.5" width="18" height="13" rx="2"/><path d="M9 7.5V5.5h6v2"/><path d="M3 12.5h18"/>',
        'banknote'  => '<rect x="2.5" y="6.5" width="19" height="11" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 9.5v5M18 9.5v5"/>',
        'chart'     => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M12 2.8v2.4M12 18.8v2.4M21.2 12h-2.4M5.2 12H2.8M18.5 5.5l-1.7 1.7M7.2 16.8l-1.7 1.7M18.5 18.5l-1.7-1.7M7.2 7.2 5.5 5.5"/>',
        'search'    => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 4 4"/>',
        'bell'      => '<path d="M18 8.5a6 6 0 1 0-12 0c0 6-2 7-2 7h16s-2-1-2-7"/><path d="M10.3 19.5a2 2 0 0 0 3.4 0"/>',
        'help'      => '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.2a2.5 2.5 0 1 1 3.4 2.3c-.7.3-1 .9-1 1.6v.4"/><circle cx="12" cy="16.6" r=".9"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'chevron'   => '<path d="m9 5 7 7-7 7"/>',
        'down'      => '<path d="m5 9 7 7 7-7"/>',
        'check'     => '<path d="m4.5 12.5 5 5 10-11"/>',
        'x'         => '<path d="M6 6l12 12M18 6 6 18"/>',
        'edit'      => '<path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3"/><path d="m14.5 6 3 3"/>',
        'trash'     => '<path d="M4 6.5h16M9.5 6.5V4h5v2.5M6.5 6.5 7.5 21h9l1-14.5"/><path d="M10.5 10.5v6M13.5 10.5v6"/>',
        'logout'    => '<path d="M15 4.5H19a1.5 1.5 0 0 1 1.5 1.5v12A1.5 1.5 0 0 1 19 19.5h-4"/><path d="M11 8 7 12l4 4"/><path d="M7 12h10"/>',
        'user'      => '<circle cx="12" cy="8" r="3.6"/><path d="M5 20.5c0-3.6 3-6 7-6s7 2.4 7 6"/>',
        'lock'      => '<rect x="4.5" y="10.5" width="15" height="10.5" rx="2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'panel'     => '<rect x="3" y="4.5" width="18" height="15" rx="2"/><path d="M15 4.5v15"/>',
        'warning'   => '<path d="M12 3.5 22 20H2z"/><path d="M12 9.5v4.5"/><circle cx="12" cy="17" r=".9"/>',
        'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5"/><circle cx="12" cy="7.8" r=".9"/>',
        'clock'     => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5.2l3.2 2"/>',
        'calendar'  => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M8 3v4M16 3v4M3.5 10h17"/>',
        'download'  => '<path d="M12 3.5v11"/><path d="m8 11 4 4 4-4"/><path d="M4.5 18.5h15"/>',
        'filter'    => '<path d="M3 5.5h18l-7 8v6l-4 2v-8z"/>',
        'sort'      => '<path d="M8 4v16M8 4 5 7M8 4l3 3"/><path d="M16 20V4M16 20l3-3M16 20l-3-3"/>',
        'external'  => '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v5.5H4.5V6H10"/>',
        'eye'       => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
        'file'      => '<path d="M6.5 2.8h7L18 7.3V21H6.5z"/><path d="M13.2 3v4.5H18"/>',
        'mail'      => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>',
        'phone'     => '<path d="M6.5 3.5 9 4l1.2 3.5-2 1.5a12 12 0 0 0 5.8 5.8l1.5-2L19 14l.5 2.5c-.2 1.6-1.6 2.6-3.2 2.2C10.5 17.4 6.6 13.5 5.3 7.7c-.4-1.7.6-3 1.2-4.2"/>',
        'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18"/>',
        'building'  => '<path d="M4 21V5.5L12 2l8 3.5V21"/><path d="M9 21v-5h6v5"/><path d="M8 9h2M14 9h2M8 12.5h2M14 12.5h2"/>',
        'sparkle'   => '<path d="M12 3.5 13.8 9l5.7 1.8-5.7 1.9L12 18.5 10.2 12.7 4.5 10.8 10.2 9z"/>',
        'empty'     => '<rect x="3.5" y="6.5" width="17" height="13" rx="2"/><path d="M3.5 12h4l1.5 2.5h6L16.5 12h4"/>',
        'arrow'     => '<path d="M19 12H5"/><path d="m11 6-6 6 6 6"/>',
    ];

    $svg = $paths[$name] ?? $paths['circle'] ?? '<circle cx="12" cy="12" r="9"/>';
@endphp

<svg
    {{ $attributes->class(['v-icon', 'rtl-mirror' => in_array($name, ['chevron', 'arrow'], true)]) }}
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $stroke }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
>{!! $svg !!}</svg>
