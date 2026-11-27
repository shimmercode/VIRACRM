@extends('layouts.app')

@section('title', 'روش محاسبهٔ حقوق')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">روش محاسبهٔ حقوق</h1>
            <p class="v-pagehead__desc">
                قاعده‌ای که هر دورهٔ حقوق با آن محاسبه شده است. فقط قاعدهٔ فعال و
                تأییدشده به کار می‌رود.
            </p>
        </div>
        @if (auth()->user()->hasPermission('settings.manage'))
            <div class="v-pagehead__actions">
                <a href="{{ route('settings.payroll-rules.create') }}" class="v-btn v-btn--primary">قاعدهٔ جدید</a>
            </div>
        @endif
    </header>

    <section class="v-card">
        <div class="v-card__body">
            @if ($rules->isEmpty())
                <x-empty-state
                    title="قاعده‌ای ثبت نشده است"
                    description="بدون قاعدهٔ تأییدشده، دورهٔ حقوق بدون روش محاسبه ساخته می‌شود."
                    icon="folder"
                />
            @else
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th scope="col">قاعده</th>
                                <th scope="col">روش</th>
                                <th scope="col">دوره‌ها</th>
                                <th scope="col">وضعیت</th>
                                <th scope="col">تأیید</th>
                                @if (auth()->user()->hasPermission('settings.manage'))<th scope="col"></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rules as $rule)
                                <tr>
                                    <td class="v-table__primary">
                                        {{ $rule->title }}
                                        @if ($rule->params['notes'] ?? null)
                                            <span class="v-table__sub">{{ $rule->params['notes'] }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $methods[$rule->method] ?? $rule->method }}</td>
                                    <td class="v-table__num">{{ \App\Support\Money::toPersianDigits((string) $rule->periods_count) }}</td>
                                    <td>
                                        <span class="v-badge {{ $rule->is_active ? 'v-badge--success' : 'v-badge--neutral' }}">
                                            {{ $rule->is_active ? 'فعال' : 'غیرفعال' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($rule->isUsable())
                                            <span class="v-badge v-badge--success">قابل استفاده</span>
                                        @elseif ($rule->is_approved)
                                            <span class="v-badge v-badge--warning">تأییدشده، غیرفعال</span>
                                        @else
                                            <span class="v-badge v-badge--neutral">در انتظار تأیید</span>
                                        @endif
                                        @if ($rule->approvedBy)
                                            <span class="v-table__sub">توسط {{ $rule->approvedBy->name }}</span>
                                        @endif
                                    </td>
                                    @if (auth()->user()->hasPermission('settings.manage'))
                                        <td class="v-table__num">
                                            <a href="{{ route('settings.payroll-rules.edit', $rule) }}"
                                               class="v-btn v-btn--ghost v-btn--sm">ویرایش</a>
                                            @if ($rule->periods_count === 0)
                                                <form method="POST" action="{{ route('settings.payroll-rules.destroy', $rule) }}" class="v-inlineform">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="v-btn v-btn--ghost v-btn--sm v-btn--danger">حذف</button>
                                                </form>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
