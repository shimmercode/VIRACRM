@props(['name' => '', 'size' => 32])

@php
    // Deterministic colour from the name so the same person keeps the same
    // avatar tint between page loads — no random pick, no stored image needed.
    $palette = ['#450073', '#6B3A8F', '#8A5CB0', '#A87B3F', '#3F6070', '#6B4B2A'];
    $hash = crc32($name);
    $color = $palette[$hash % count($palette)];

    $initials = '';
    foreach (preg_split('/\s+/u', trim($name)) as $part) {
        if ($part !== '') {
            $initials = mb_substr($part, 0, 1);
            break;
        }
    }
@endphp

<span class="v-avatar"
      style="--avatar-size: {{ $size }}px; --avatar-bg: {{ $color }}"
      title="{{ $name }}"
      aria-label="{{ $name }}">
    {{ $initials ?: '؟' }}
</span>
