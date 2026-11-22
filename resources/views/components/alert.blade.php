@props(['type' => 'info', 'dismissible' => false])

@php
    $icons = ['success' => 'check', 'danger' => 'warning', 'warning' => 'warning', 'info' => 'info'];
    $roles = ['danger' => 'alert', 'warning' => 'alert', 'success' => 'status', 'info' => 'status'];
@endphp

{{-- `$attributes` must be merged or an id/tabindex passed by the caller is
     silently dropped: the error summary needed tabindex="-1" so focus can be
     moved to it after a failed submit, and it never reached the DOM. --}}
<div {{ $attributes->class(['v-alert', 'v-alert--' . $type]) }} role="{{ $roles[$type] ?? 'status' }}" @if($dismissible) data-alert @endif>
    <x-icon :name="$icons[$type] ?? 'info'" :size="18" class="v-alert__icon" />
    <div class="v-alert__body">{{ $slot }}</div>
    @if ($dismissible)
        <button type="button" class="v-alert__close" data-alert-close aria-label="بستن پیام">
            <x-icon name="x" :size="16" />
        </button>
    @endif
</div>
