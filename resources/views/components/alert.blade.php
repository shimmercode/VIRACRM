@props(['type' => 'info', 'dismissible' => false])

@php
    $icons = ['success' => 'check', 'danger' => 'warning', 'warning' => 'warning', 'info' => 'info'];
    $roles = ['danger' => 'alert', 'warning' => 'alert', 'success' => 'status', 'info' => 'status'];
@endphp

<div class="v-alert v-alert--{{ $type }}" role="{{ $roles[$type] ?? 'status' }}" @if($dismissible) data-alert @endif>
    <x-icon :name="$icons[$type] ?? 'info'" :size="18" class="v-alert__icon" />
    <div class="v-alert__body">{{ $slot }}</div>
    @if ($dismissible)
        <button type="button" class="v-alert__close" data-alert-close aria-label="بستن پیام">
            <x-icon name="x" :size="16" />
        </button>
    @endif
</div>
