@extends('layouts.app')

@section('title', 'دسترسی جدید')

@section('content')
    <nav class="v-breadcrumb" aria-label="مسیر">
        <a href="{{ route('dashboard') }}">داشبورد</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a>
        <x-icon name="chevron" :size="12" />
        <a href="{{ route('credentials.index', $project) }}">دسترسی‌ها</a>
        <x-icon name="chevron" :size="12" />
        <span aria-current="page">دسترسی جدید</span>
    </nav>

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ثبت دسترسی جدید</h1>
            <p class="v-pagehead__desc">
                اطلاعات عمومی (عنوان، نشانی، نام کاربری) قابل مشاهده در فهرست است.
                رمز در جدول جداگانه و رمزنگاری‌شده نگهداری می‌شود.
            </p>
        </div>
    </header>

    @if (! $vaultReady)
        <x-alert type="danger">
            کلید رمزنگاری (<code>VIRA_CREDENTIAL_KEY</code>) روی این سرور تنظیم نشده است.
            می‌توانید عنوان و نام کاربری را ثبت کنید، اما <strong>رمز ذخیره نخواهد شد</strong>
            تا زمانی که کلید تنظیم شود — ذخیرهٔ رمز بدون کلید یعنی متن ساده در پایگاه داده.
        </x-alert>
    @endif

    @if ($types->isEmpty())
        <x-alert type="warning">
            هیچ <strong>نوع دسترسی</strong> تعریف نشده است. ابتدا انواع دسترسی (وردپرس، هاست، دامنه…) را از تنظیمات اضافه کنید.
        </x-alert>
    @else
        <form method="POST" action="{{ route('credentials.store', $project) }}" novalidate>
            @csrf
            <div class="v-card">
                <div class="v-card__body">
                  <div class="v-form">
                    <div class="v-grid v-grid--2">
                        <div class="v-field">
                            <label class="v-label" for="credential_type_id">
                                نوع دسترسی <span class="v-req" aria-hidden="true">*</span>
                            </label>
                            <select id="credential_type_id" name="credential_type_id"
                                    class="v-select" @error('credential_type_id') aria-invalid="true" @enderror required>
                                <option value="">— انتخاب نوع —</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type->id }}"
                                        @selected((string) old('credential_type_id', $selectedType) === (string) $type->id)>
                                        {{ $type->title }}
                                    </option>
                                @endforeach
                            </select>
                            @error('credential_type_id')
                                <p class="v-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="v-field">
                            <label class="v-label" for="label">
                                عنوان <span class="v-req" aria-hidden="true">*</span>
                            </label>
                            <input id="label" name="label" type="text" class="v-input" @error('label') aria-invalid="true" @enderror
                                   value="{{ old('label') }}" required maxlength="120"
                                   placeholder="مثلاً: ورود مدیر وردپرس">
                            @error('label')
                                <p class="v-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="v-field">
                            <label class="v-label" for="username">نام کاربری</label>
                            <input id="username" name="username" type="text" class="v-input" @error('username') aria-invalid="true" @enderror
                                   value="{{ old('username') }}" dir="ltr" maxlength="255" autocomplete="off">
                            @error('username')
                                <p class="v-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="v-field">
                            <label class="v-label" for="public_url">نشانی ورود</label>
                            <input id="public_url" name="public_url" type="url" class="v-input" @error('public_url') aria-invalid="true" @enderror
                                   value="{{ old('public_url') }}" dir="ltr" maxlength="255"
                                   placeholder="https://example.com/wp-admin" autocomplete="off">
                            @error('public_url')
                                <p class="v-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="v-field v-field--span2">
                            <label class="v-label" for="secret">رمز عبور</label>
                            <input id="secret" name="secret" type="password" class="v-input" @error('secret') aria-invalid="true" @enderror
                                   dir="ltr" maxlength="1000" autocomplete="new-password" data-secret>
                            <p class="v-hint">
                                می‌توانید خالی بگذارید و بعداً تکمیل کنید، اما تا زمانی که رمز ذخیره نشود وضعیت این دسترسی «ناقص» است.
                            </p>
                            @error('secret')
                                <p class="v-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="v-formactions">
                        <a href="{{ route('credentials.index', $project) }}" class="v-btn v-btn--ghost">انصراف</a>
                        <button type="submit" class="v-btn v-btn--primary">ذخیرهٔ دسترسی</button>
                    </div>
                  </div>
                </div>
            </div>
        </form>
    @endif
@endsection
