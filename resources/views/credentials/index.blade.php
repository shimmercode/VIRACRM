@extends('layouts.app')

@section('title', 'دسترسی‌های پروژه')

@section('content')
    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('projects.index') }}">پروژه‌ها</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">دسترسی‌ها</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">دسترسی‌های پروژه</h1>
            <p class="v-pagehead__desc">
                رمزها با AES-256-GCM رمزنگاری می‌شوند و هر بار مشاهدهٔ رمز در گزارش رویدادها ثبت می‌گردد.
            </p>
        </div>
        @if ($canManage)
            <div class="v-pagehead__actions">
                <a href="{{ route('credentials.create', $project) }}" class="v-btn v-btn--primary">
                    <x-icon name="plus" :size="16" />
                    دسترسی جدید
                </a>
            </div>
        @endif
    </header>

    @if (! $vaultReady)
        <x-alert type="danger">
            کلید رمزنگاری (<code>VIRA_CREDENTIAL_KEY</code>) روی این سرور تنظیم نشده است.
            ذخیرهٔ رمزهای جدید غیرفعال است تا زمانی که کلید تنظیم شود؛
            ذخیره بدون کلید یعنی نوشتن رمز به‌صورت متن ساده در پایگاه داده.
        </x-alert>
    @endif

    @if ($credentials->isEmpty())
        <div class="v-card">
            <div class="v-card__body">
                <x-empty-state
                    title="هنوز دسترسی‌ای ثبت نشده است"
                    description="دسترسی‌های وردپرس، هاست، دامنه و سرویس‌های دیگر را از این‌جا ثبت کنید."
                >
                    @if ($canManage)
                        <a href="{{ route('credentials.create', $project) }}" class="v-btn v-btn--primary">ثبت اولین دسترسی</a>
                    @endif
                </x-empty-state>
            </div>
        </div>
    @else
        <section class="v-card">
            <header class="v-card__head">
                <h2 class="v-card__title">فهرست دسترسی‌ها</h2>
                <span class="v-card__meta">{{ \App\Support\Money::toPersianDigits((string) $credentials->count()) }} مورد</span>
            </header>
            <div class="v-table-wrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th scope="col">عنوان</th>
                            <th scope="col">نوع</th>
                            <th scope="col">نام کاربری</th>
                            <th scope="col">نشانی</th>
                            <th scope="col">رمز</th>
                            <th scope="col">وضعیت</th>
                            <th scope="col" class="v-table__actions"><span class="vira-sr-only">عملیات</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($credentials as $credential)
                            <tr>
                                <td data-label="عنوان">{{ $credential->label }}</td>
                                <td data-label="نوع">{{ $credential->type?->title ?? '—' }}</td>
                                <td data-label="نام کاربری">
                                    @if ($credential->username)
                                        <span dir="ltr">{{ $credential->username }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="نشانی">
                                    @if ($credential->public_url)
                                        <a href="{{ $credential->public_url }}" target="_blank" rel="noopener noreferrer" dir="ltr">
                                            {{ $credential->public_url }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="رمز">
                                    {{-- The value is never in this HTML. Bullets only,
                                         plus a button that asks the server. --}}
                                    @if ($credential->values->isNotEmpty())
                                        @if ($canReveal)
                                            <button
                                                type="button"
                                                class="v-btn v-btn--ghost v-btn--sm"
                                                data-reveal="{{ route('credentials.reveal', [$project, $credential]) }}"
                                                data-reveal-target="secret-{{ $credential->id }}"
                                                aria-controls="secret-{{ $credential->id }}"
                                                aria-expanded="false"
                                            >مشاهدهٔ رمز</button>
                                            <output id="secret-{{ $credential->id }}" class="v-secret" dir="ltr" aria-live="polite"></output>
                                        @else
                                            <span class="v-muted" title="برای مشاهدهٔ رمز مجوز لازم است">••••••••</span>
                                        @endif
                                    @else
                                        <span class="v-muted">ذخیره نشده</span>
                                    @endif
                                </td>
                                <td data-label="وضعیت">
                                    @if ($credential->is_complete)
                                        <x-badge tone="success">کامل</x-badge>
                                    @else
                                        <x-badge tone="warning">ناقص</x-badge>
                                    @endif
                                </td>
                                <td data-label="عملیات" class="v-table__actions">
                                    @if ($canManage)
                                        <div class="v-actions">
                                            <a href="{{ route('credentials.edit', [$project, $credential]) }}" class="v-btn v-btn--ghost v-btn--sm">ویرایش</a>
                                            <form method="POST" action="{{ route('credentials.destroy', [$project, $credential]) }}"
                                                  data-confirm="این دسترسی و رمز ذخیره‌شدهٔ آن حذف شود؟">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="v-btn v-btn--ghost v-btn--sm">حذف</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($auditTrail->isNotEmpty())
        <section class="v-card v-card--spaced">
            <header class="v-card__head">
                <h2 class="v-card__title">رویدادهای اخیر دسترسی‌ها</h2>
            </header>
            <div class="v-card__body">
                <ul class="v-timeline">
                    @foreach ($auditTrail as $entry)
                        <li class="v-timeline__item">
                            <span class="v-timeline__dot" aria-hidden="true"></span>
                            <div class="v-timeline__body">
                                <span class="v-timeline__title">{{ $entry->description }}</span>
                                <span class="v-timeline__meta">
                                    {{ $entry->user?->name ?? 'سیستم' }} ·
                                    <x-fa-date :value="$entry->created_at" with-time />
                                </span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
