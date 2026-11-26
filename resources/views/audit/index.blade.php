@extends('layouts.app')

@section('title', 'گزارش رویدادها')

@section('content')
    @php
        use App\Http\Controllers\Audit\AuditLogController;
        use App\Models\User;
        use App\Support\Money;

        /*
         * Event labels. An unknown code falls through to the raw string: the
         * page must never hide an event it does not recognise, because "we have
         * no Persian label for it" is not a reason for a security event to be
         * invisible to the person reviewing the trail.
         */
        $eventLabels = [
            'login' => 'ورود',
            'login_failed' => 'ورود ناموفق',
            'logout' => 'خروج',
            'denied' => 'دسترسی رد شده',
            'created' => 'ایجاد',
            'updated' => 'ویرایش',
            'deleted' => 'حذف',
            'revealed' => 'نمایش رمز',
            'exported' => 'خروجی محرمانه',
            'backup' => 'پشتیبان‌گیری',
            'backup_failed' => 'پشتیبان‌گیری ناموفق',
            'restore' => 'بازیابی',
            'restore_failed' => 'بازیابی ناموفق',
            'audit.viewed' => 'بازبینی گزارش',
        ];

        /*
         * Events that mean "someone touched a secret". They are flagged so a
         * reviewer can find them — the point being that the EVENT is reviewable
         * while the secret itself is not present anywhere on this page.
         */
        $sensitive = ['revealed', 'exported', 'login_failed', 'denied', 'restore', 'restore_failed'];

        $anyFilter = $filters['q'] !== '' || $filters['user'] > 0 || $filters['event'] !== ''
            || $filters['subject'] !== '' || $filters['from'] !== '' || $filters['to'] !== '';

        $filterChips = [];
        if ($filters['q'] !== '') {
            $filterChips[] = ['key' => 'q', 'label' => 'جست‌وجو', 'value' => $filters['q']];
        }
        if ($filters['user'] > 0) {
            $name = User::query()->find($filters['user'])?->name;
            $filterChips[] = ['key' => 'user', 'label' => 'کاربر', 'value' => $name ?? (string) $filters['user']];
        }
        if ($filters['event'] !== '') {
            $filterChips[] = ['key' => 'event', 'label' => 'رویداد', 'value' => $eventLabels[$filters['event']] ?? $filters['event']];
        }
        if ($filters['subject'] !== '') {
            $filterChips[] = ['key' => 'subject', 'label' => 'موجودیت', 'value' => $filters['subject']];
        }
        if ($filters['from'] !== '') {
            $filterChips[] = ['key' => 'from', 'label' => 'از تاریخ', 'value' => $filters['from']];
        }
        if ($filters['to'] !== '') {
            $filterChips[] = ['key' => 'to', 'label' => 'تا تاریخ', 'value' => $filters['to']];
        }
    @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">گزارش رویدادها</h1>
            <p class="v-pagehead__desc">
                {{ Money::toPersianDigits((string) $total) }} رویداد ثبت شده است. این صفحه فقط خواندنی است؛
                هیچ رکوردی از اینجا ویرایش یا حذف نمی‌شود.
            </p>
        </div>
    </header>

    {{-- Every control keeps the other filters, so narrowing by user does not
         silently drop the date range. --}}
    <form class="v-filterbar" method="GET" action="{{ route('audit.index') }}">
        <div class="v-filterbar__search">
            <x-icon name="search" :size="18" class="v-filterbar__icon" />
            <input type="search" name="q" value="{{ $filters['q'] }}"
                   placeholder="جست‌وجو در شرح رویدادها…"
                   aria-label="جست‌وجو در گزارش رویدادها">
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-user">کاربر</label>
            <select id="f-user" name="user" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected($filters['user'] === (int) $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-event">رویداد</label>
            <select id="f-event" name="event" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($events as $event)
                    <option value="{{ $event }}" @selected($filters['event'] === (string) $event)>
                        {{ $eventLabels[(string) $event] ?? $event }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-subject">موجودیت</label>
            <select id="f-subject" name="subject" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($subjects as $short => $class)
                    <option value="{{ $short }}" @selected($filters['subject'] === $short)>{{ $short }}</option>
                @endforeach
            </select>
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-from">از تاریخ</label>
            <input type="date" id="f-from" name="from" value="{{ $filters['from'] }}" class="v-input">
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-to">تا تاریخ</label>
            <input type="date" id="f-to" name="to" value="{{ $filters['to'] }}" class="v-input">
        </div>

        <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
            <x-icon name="filter" :size="16" /> اعمال
        </button>

        @if ($anyFilter)
            <a href="{{ route('audit.index') }}" class="v-btn v-btn--link v-btn--sm">پاک کردن</a>
        @endif
    </form>

    <x-filter-chips :chips="$filterChips" :clear-href="$anyFilter ? route('audit.index') : null" />

    <div class="v-card">
        @if ($logs->isEmpty())
            <x-empty-state
                title="رویدادی با این فیلترها پیدا نشد"
                description="بازهٔ تاریخ یا فیلترها را تغییر دهید."
                icon="search"
            >
                <a href="{{ route('audit.index') }}" class="v-btn v-btn--ghost v-btn--sm">حذف فیلترها</a>
            </x-empty-state>
        @else
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th scope="col">زمان</th>
                            <th scope="col">کاربر</th>
                            <th scope="col">رویداد</th>
                            <th scope="col">موجودیت</th>
                            <th scope="col">شرح</th>
                            <th scope="col">تغییرات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            @php
                                /*
                                 * Every value on this row goes through present().
                                 * There is no branch that prints a raw column, so
                                 * a secret in the table cannot reach the HTML.
                                 */
                                $row = AuditLogController::present($log);
                                $short = $log->subject_type
                                    ? substr((string) $log->subject_type, (int) strrpos((string) $log->subject_type, '\\') + 1)
                                    : null;
                            @endphp
                            <tr>
                                <td dir="ltr" class="v-table__ltr">
                                    <span class="v-muted">{{ $log->created_at?->format('Y-m-d H:i') }}</span>
                                </td>
                                <td>
                                    <span class="v-table__primary">{{ $log->user?->name ?? '—' }}</span>
                                    <div class="v-table__sub">
                                        @if ($log->user?->username){{ $log->user->username }}@endif
                                        @if ($log->ip) · {{ $log->ip }} @endif
                                    </div>
                                </td>
                                <td>
                                    @if (in_array($row['event'], $sensitive, true))
                                        <x-badge tone="warning">
                                            {{ $eventLabels[$row['event']] ?? $row['event'] }}
                                        </x-badge>
                                    @else
                                        <x-badge tone="neutral">
                                            {{ $eventLabels[$row['event']] ?? $row['event'] }}
                                        </x-badge>
                                    @endif
                                </td>
                                <td>
                                    @if ($short)
                                        <span class="v-muted">{{ $short }}</span>
                                        <div class="v-table__sub">{{ Money::toPersianDigits((string) $log->subject_id) }}</div>
                                    @else
                                        <span class="v-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    {{-- scrubbed by present(); Blade escapes on output --}}
                                    {{ $row['description'] ?? '—' }}
                                </td>
                                <td>
                                    @if (! empty($row['changes']))
                                        {{--
                                            The change set is shown as field
                                            names plus redacted markers. A
                                            sensitive VALUE is never here:
                                            redact() replaced it on the way in
                                            and again on the way out, so what a
                                            reviewer sees is WHICH fields changed
                                            and that one of them was secret —
                                            enough to investigate, not enough to
                                            leak.
                                        --}}
                                        <div class="v-table__sub">
                                            @foreach ($row['changes'] as $field => $change)
                                                <span class="v-muted">{{ $field }}</span>@if (! $loop->last) · @endif
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="v-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <footer class="v-card__foot">
                <x-pagination :paginator="$logs" />
            </footer>
        @endif
    </div>
@endsection
