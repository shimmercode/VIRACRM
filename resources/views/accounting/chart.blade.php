@extends('layouts.app')

@section('title', 'نمودار حساب‌ها')

@section('content')
    @php $pd = fn ($v) => \App\Support\Money::toPersianDigits((string) $v); @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">نمودار حساب‌ها</h1>
            <p class="v-pagehead__desc">
                ساختار تأییدشدهٔ OD-41 — {{ $pd($postableCount) }} حساب قابل ثبت و
                {{ $pd(41 - $postableCount) }} حساب گروهی. کدینگ سه‌سطحی و قابل توسعه است.
            </p>
        </div>
    </header>

    @include('accounting._tabs', ['active' => 'chart'])

    @foreach ($tree as $rootCode => $accounts)
        @php
            $root = $accounts->firstWhere('code', (string) $rootCode)
                ?? $accounts->firstWhere('level', 1);
            $groups = $accounts->where('level', 2);
        @endphp

        <section class="v-card">
            <h2 class="v-card__title">
                <span class="v-account-code">{{ $root->code }}</span>
                {{ $root->title }}
                <span class="v-badge v-badge--neutral">{{ $types[$root->type] ?? $root->type }}</span>
            </h2>

            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th scope="col">کد</th>
                            <th scope="col">عنوان</th>
                            <th scope="col">ماهیت</th>
                            <th scope="col">نوع</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $group)
                            <tr class="v-account-group">
                                <td><span class="v-account-code">{{ $group->code }}</span></td>
                                <td><strong>{{ $group->title }}</strong></td>
                                <td><span class="v-badge v-badge--neutral">گروهی</span></td>
                                <td class="v-dl__value--empty">—</td>
                            </tr>

                            {{-- `where('code','like',...)` on a Collection is a strict
                                 comparison, NOT SQL LIKE — it silently returns an
                                 empty set. Leaves are filtered with a real prefix
                                 test so a new sub-group cannot swallow its
                                 grandchildren by accident. --}}
                            @foreach ($accounts->filter(fn ($a) => str_starts_with($a->code, $group->code . '-')) as $leaf)
                                <tr>
                                    <td>
                                        <span class="v-account-code v-account-code--leaf">{{ $leaf->code }}</span>
                                    </td>
                                    <td>
                                        <span class="v-account-child">{{ $leaf->title }}</span>
                                    </td>
                                    <td>
                                        @if ($leaf->is_postable)
                                            <span class="v-badge v-badge--success">قابل ثبت</span>
                                        @else
                                            <span class="v-badge v-badge--neutral">گروهی</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="v-muted-sm">
                                            {{ $leaf->normal_balance === 'debit' ? 'بدهکار' : 'بستانکار' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
@endsection
