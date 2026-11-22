@props(['title' => 'موردی یافت نشد', 'description' => null, 'icon' => 'empty'])

{{-- §9 UX: an empty state explains why it is empty and offers the action that
     would fill it, or states plainly that the user may not create one. --}}
<div class="v-empty">
    <span class="v-empty__icon" aria-hidden="true">
        <x-icon :name="$icon" :size="26" />
    </span>
    <p class="v-empty__title">{{ $title }}</p>
    @if ($description)
        <p class="v-empty__desc">{{ $description }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="v-empty__actions">{{ $slot }}</div>
    @endif
</div>
