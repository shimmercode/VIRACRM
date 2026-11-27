@extends('layouts.app')

@section('title', 'نقش‌ها و مجوزها')

@section('content')
    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">نقش‌ها و مجوزها</h1>
            <p class="v-pagehead__desc">
                نقش‌ها داده‌اند، نه کد؛ افزودن نقش نیازی به انتشار نسخه ندارد.
                کاربر مدیر ارشد بدون نیاز به نقش، همهٔ مجوزها را دارد.
            </p>
        </div>
        @if (auth()->user()->hasPermission('users.manage'))
            <div class="v-pagehead__actions">
                <a href="{{ route('settings.roles.create') }}" class="v-btn v-btn--primary">نقش جدید</a>
            </div>
        @endif
    </header>

    <section class="v-card">
        <div class="v-card__body">
            @if ($roles->isEmpty())
                <x-empty-state
                    title="نقشی ثبت نشده است"
                    description="بدون نقش، فقط کاربر مدیر ارشد دسترسی دارد."
                    icon="folder"
                />
            @else
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th scope="col">نقش</th>
                                <th scope="col">کد</th>
                                <th scope="col">مجوزها</th>
                                <th scope="col">کاربران</th>
                                @if (auth()->user()->hasPermission('users.manage'))<th scope="col"></th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roles as $role)
                                <tr>
                                    <td class="v-table__primary">
                                        {{ $role->title }}
                                        @if ($role->description)
                                            <span class="v-table__sub">{{ $role->description }}</span>
                                        @endif
                                    </td>
                                    <td dir="ltr">{{ $role->code }}</td>
                                    <td class="v-table__num">{{ \App\Support\Money::toPersianDigits((string) $role->permissions_count) }}</td>
                                    <td class="v-table__num">{{ \App\Support\Money::toPersianDigits((string) $role->users_count) }}</td>
                                    @if (auth()->user()->hasPermission('users.manage'))
                                        <td class="v-table__num">
                                            <a href="{{ route('settings.roles.edit', $role) }}"
                                               class="v-btn v-btn--ghost v-btn--sm">ویرایش</a>
                                            @if ($role->users_count === 0)
                                                <form method="POST" action="{{ route('settings.roles.destroy', $role) }}" class="v-inlineform">
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
