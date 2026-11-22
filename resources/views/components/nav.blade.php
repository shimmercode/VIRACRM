{{-- REQ-11.01 — permission-filtered sidebar. Right side in RTL (§11). --}}
<aside class="v-sidebar" data-sidebar aria-label="ناوبری اصلی">
    <div class="v-sidebar__head">
        <a class="v-brand" href="{{ route('dashboard') }}" aria-label="ویرا، صفحه اصلی">
            <span class="v-brand__mark" aria-hidden="true">و</span>
            <span class="v-brand__text">
                <span class="v-brand__name">ویرا</span>
                <span class="v-brand__sub">سیستم مدیریت ارتباط با مشتری</span>
            </span>
        </a>
    </div>

    <nav class="v-sidebar__nav" aria-label="فهرست ماژول‌ها">
        @foreach ($primary as $item)
            @if (! empty($item['enabled']))
                <a href="{{ route($item['route']) }}"
                   class="v-navlink {{ $isActive($item) ? 'is-active' : '' }}"
                   @if ($isActive($item)) aria-current="page" @endif>
                    <x-icon :name="$item['icon']" />
                    <span class="v-navlink__label">{{ $item['label'] }}</span>
                </a>
            @else
                <span class="v-navlink is-disabled" aria-disabled="true" title="در نسخه بعدی فعال می‌شود">
                    <x-icon :name="$item['icon']" />
                    <span class="v-navlink__label">{{ $item['label'] }}</span>
                    <span class="v-navlink__note">{{ $item['note'] ?? '' }}</span>
                </span>
            @endif
        @endforeach

        @if (count($secondary))
            <div class="v-nav__divider" role="separator"></div>

            @foreach ($secondary as $item)
                @if (! empty($item['enabled']))
                    <a href="{{ route($item['route']) }}" class="v-navlink {{ $isActive($item) ? 'is-active' : '' }}">
                        <x-icon :name="$item['icon']" />
                        <span class="v-navlink__label">{{ $item['label'] }}</span>
                    </a>
                @else
                    <span class="v-navlink is-disabled" aria-disabled="true">
                        <x-icon :name="$item['icon']" />
                        <span class="v-navlink__label">{{ $item['label'] }}</span>
                        <span class="v-navlink__note">{{ $item['note'] ?? '' }}</span>
                    </span>
                @endif
            @endforeach
        @endif
    </nav>

    <div class="v-sidebar__foot">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="v-navlink v-navlink--button">
                <x-icon name="logout" />
                <span class="v-navlink__label">خروج از حساب</span>
            </button>
        </form>
    </div>
</aside>

{{-- Mobile: the same navigation in a slide-over, so no menu item is lost on
     small screens (§12). --}}
<div class="v-drawer" data-drawer hidden>
    <button class="v-drawer__scrim" data-drawer-close aria-label="بستن منو" tabindex="-1"></button>
    <div class="v-drawer__panel" role="dialog" aria-modal="true" aria-label="منوی اصلی">
        <div class="v-drawer__head">
            <span class="v-brand__name">ویرا</span>
            <button class="v-iconbtn" data-drawer-close aria-label="بستن منو">
                <x-icon name="x" />
            </button>
        </div>
        <nav class="v-sidebar__nav">
            @foreach ($primary as $item)
                @if (! empty($item['enabled']))
                    <a href="{{ route($item['route']) }}" class="v-navlink {{ $isActive($item) ? 'is-active' : '' }}">
                        <x-icon :name="$item['icon']" />
                        <span class="v-navlink__label">{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
            @foreach ($secondary as $item)
                @if (! empty($item['enabled']))
                    <a href="{{ route($item['route']) }}" class="v-navlink {{ $isActive($item) ? 'is-active' : '' }}">
                        <x-icon :name="$item['icon']" />
                        <span class="v-navlink__label">{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </nav>
    </div>
</div>
