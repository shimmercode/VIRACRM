@extends('layouts.app')

@section('title', 'انواع قرارداد')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">انواع قرارداد</h1>
            <p class="v-pagehead__desc">
                فهرستی که فرم قرارداد و انتخاب قالب از آن می‌خوانند. نوعی که روی
                قراردادی استفاده شده حذف نمی‌شود، فقط غیرفعال می‌شود.
            </p>
        </div>
        @if (auth()->user()->hasPermission('settings.manage'))
            <div class="v-pagehead__actions">
                <a href="{{ route('settings.contract-types.create') }}" class="v-btn v-btn--primary">نوع جدید</a>
            </div>
        @endif
    </header>

    <section class="v-card">
        <div class="v-card__body">
            @if ($types->isEmpty())
                <x-empty-state
                    title="نوع قراردادی ثبت نشده است"
                    description="بدون نوع، فرم قرارداد فهرستی برای انتخاب ندارد."
                    icon="folder"
                />
            @else
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th scope="col">نوع قرارداد</th>
                                <th scope="col">کد</th>
                                <th scope="col">قراردادها</th>
                                <th scope="col">وضعیت</th>
                                @if (auth()->user()->hasPermission('settings.manage'))<th scope="col"></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($types as $type)
                                <tr>
                                    <td class="v-table__primary">
                                        {{ $type->title }}
                                        @if ($type->description)
                                            <span class="v-table__sub">{{ $type->description }}</span>
                                        @endif
                                    </td>
                                    <td dir="ltr">{{ $type->code }}</td>
                                    <td class="v-table__num">{{ \App\Support\Money::toPersianDigits((string) $type->contracts_count) }}</td>
                                    <td>
                                        <span class="v-badge {{ $type->is_active ? 'v-badge--success' : 'v-badge--neutral' }}">
                                            {{ $type->is_active ? 'فعال' : 'غیرفعال' }}
                                        </span>
                                    </td>
                                    @if (auth()->user()->hasPermission('settings.manage'))
                                        <td class="v-table__num">
                                            <a href="{{ route('settings.contract-types.edit', $type) }}"
                                               class="v-btn v-btn--ghost v-btn--sm">ویرایش</a>
                                            {{-- The FK is ON DELETE SET NULL, so deleting a type in use
                                                 would silently wipe it off every contract written on
                                                 it. Only an unused type can be deleted. --}}
                                            @if ($type->contracts_count === 0)
                                                <form method="POST" action="{{ route('settings.contract-types.destroy', $type) }}" class="v-inlineform">
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
