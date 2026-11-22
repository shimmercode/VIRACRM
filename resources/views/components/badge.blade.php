@props(['tone' => 'neutral', 'dot' => false])

{{-- Status badge. `dot` must be declared in @props: an undeclared prop is
     treated as a raw HTML attribute, so `dot="dot"` was being rendered onto
     the <span> instead of drawing the indicator. --}}
<span {{ $attributes->class(['v-badge', 'v-badge--' . $tone]) }}>
    @if ($dot)
        <span class="v-badge__dot" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
