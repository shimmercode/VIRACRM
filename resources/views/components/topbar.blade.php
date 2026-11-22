@props(['title' => ''])

{{-- REQ-11.02 — topbar: search, quick create, profile. Notifications and help
     are included only if they have real backing; see the note below. --}}
<header class="v-topbar">
    <div class="v-topbar__start">
        <button class="v-iconbtn v-topbar__burger" data-drawer-open aria-label="باز کردن منو" aria-expanded="false">
            <x-icon name="menu" />
        </button>
        <button class="v-iconbtn v-topbar__collapse" data-shell-collapse aria-label="جمع کردن منو">
            <x-icon name="panel" />
        </button>
    </div>

    {{-- Search is wired to the customers and contracts lists, which are the only
         modules with real data. It is not a fake omnibox: submitting takes the
         user to a real filtered list. --}}
    <form class="v-topbar__search" action="{{ route('customers.index') }}" method="GET" role="search">
        <x-icon name="search" class="v-topbar__searchicon" />
        <input type="search" name="q" value="" placeholder="جست‌وجو در مشتریان…"
               aria-label="جست‌وجو در مشتریان" autocomplete="off">
    </form>

    <div class="v-topbar__end">
        @if (auth()->user()->hasPermission('customers.create') || auth()->user()->hasPermission('contracts.create'))
            <div class="v-menu" data-menu>
                <button class="v-btn v-btn--primary v-btn--sm" data-menu-trigger aria-haspopup="true" aria-expanded="false">
                    <x-icon name="plus" :size="16" />
                    <span>ایجاد</span>
                </button>
                <div class="v-menu__panel" data-menu-panel hidden>
                    @if (auth()->user()->hasPermission('customers.create'))
                        <a class="v-menu__item" href="{{ route('customers.create') }}">
                            <x-icon name="users" :size="16" /> مشتری جدید
                        </a>
                    @endif
                    @if (auth()->user()->hasPermission('contracts.create'))
                        <a class="v-menu__item" href="{{ route('contracts.create') }}">
                            <x-icon name="document" :size="16" /> قرارداد جدید
                        </a>
                    @endif
                </div>
            </div>
        @endif

        <div class="v-menu" data-menu>
            <button class="v-profile" data-menu-trigger aria-haspopup="true" aria-expanded="false">
                <x-avatar :name="auth()->user()->displayName()" :size="30" />
                <span class="v-profile__name">{{ auth()->user()->displayName() }}</span>
                <x-icon name="down" :size="14" />
            </button>
            <div class="v-menu__panel" data-menu-panel hidden>
                <div class="v-menu__head">
                    <div class="v-menu__who">{{ auth()->user()->displayName() }}</div>
                    <div class="v-menu__meta">{{ '@' . auth()->user()->username }}</div>
                </div>
                <a class="v-menu__item" href="{{ route('password.change') }}">
                    <x-icon name="lock" :size="16" /> تغییر رمز عبور
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="v-menu__item v-menu__item--danger">
                        <x-icon name="logout" :size="16" /> خروج
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
