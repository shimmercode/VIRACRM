@props(['status' => 'UI Preview', 'compact' => false])

@php
    // One component, one colour language, four statuses — no others allowed.
    // The whole point of the review environment is that a preview can never be
    // read as working software, so the label is not decoration.
    $map = [
        'Functional' => [
            'tone' => 'v-status--functional',
            'icon' => 'check',
            'label' => 'عملیاتی',
            'hint' => 'Backend و تعامل واقعی موجود است؛ داده ذخیره می‌شود.',
        ],
        'Partial' => [
            'tone' => 'v-status--partial',
            'icon' => 'warning',
            'label' => 'بخشی',
            'hint' => 'بخشی از Backend یا تعامل پیاده‌سازی شده است.',
        ],
        'UI Preview' => [
            'tone' => 'v-status--preview',
            'icon' => 'eye',
            'label' => 'پیش‌نمایش طراحی',
            'hint' => 'فقط برای بررسی طراحی؛ داده ذخیره نمی‌شود و دکمه‌ها عملیات واقعی ندارند.',
        ],
        'Not Implemented' => [
            'tone' => 'v-status--missing',
            'icon' => 'x',
            'label' => 'ساخته نشده',
            'hint' => 'هنوز صفحه و قابلیتی وجود ندارد.',
        ],
    ];

    $s = $map[$status] ?? $map['UI Preview'];
@endphp

<span class="v-status {{ $s['tone'] }} {{ $compact ? 'v-status--compact' : '' }}"
      title="{{ $s['hint'] }}">
    <x-icon :name="$s['icon']" :size="13" />
    <span>{{ $s['label'] }}</span>
</span>
