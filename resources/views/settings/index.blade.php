@extends('layouts.app')

@section('title', 'تنظیمات سامانه')

@section('content')
    @php
        $pd = fn ($v) => \App\Support\Money::toPersianDigits((string) $v);
        $err = fn (string $field) => $errors->has($field) ? ' is-invalid' : '';
        /*
         * The field is named by the row's primary key, not by the setting key.
         * Setting keys contain dots, and a dot in a Laravel rule attribute means
         * nesting — so `values[contracts.expiry_warning_days]` can never be
         * validated. Both the name and the error lookup use the id.
         */
        $field = fn ($setting) => 'values.' . $setting->id;
        // After a failed submit the typed values must come back, or the user
        // re-types a form they already filled in.
        $val = fn ($setting) => old($field($setting), $setting->value);
        $groupTitles = [
            'general' => 'عمومی',
            'contracts' => 'قراردادها',
            'reminders' => 'یادآوری‌ها',
        ];
    @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">تنظیمات سامانه</h1>
            <p class="v-pagehead__desc">
                مقادیری که رفتار سامانه را تعیین می‌کنند؛ از آستانهٔ هشدار انقضا تا فاصلهٔ یادآوری.
                تغییر هر مقدار در تاریخچه ثبت می‌شود.
            </p>
        </div>
    </header>

    @if (! $canManage)
        <x-alert type="info">
            شما فقط دسترسی مشاهده دارید. برای تغییر مقادیر به مجوز «تغییر تنظیمات سامانه» نیاز است.
        </x-alert>
    @endif

    {{--
        Environment configuration and secrets are deliberately absent. APP_KEY,
        the credential key, the backup key and the database credentials live in
        .env on the host, not in this table — a compromised admin account must
        not also hand over the encryption keys. Everything on this screen is a
        row in `settings`, and nothing here can reach config() or the
        environment.
    --}}

    <form method="POST" action="{{ route('settings.update') }}" class="v-form">
        @csrf

        @foreach ($groups as $group => $settings)
            <fieldset class="v-fsection" @disabled(! $canManage)>
                <legend class="v-fsection__legend">{{ $groupTitles[$group] ?? $group }}</legend>

                <div class="v-grid v-grid--2">
                    @foreach ($settings as $setting)
                        @php $f = $field($setting); @endphp
                        <div class="v-field">
                            <label class="v-label" for="setting-{{ $setting->key }}">
                                {{ $setting->title }}
                                <span class="v-req" aria-hidden="true">*</span>
                            </label>

                            @if ($setting->type === 'boolean')
                                <select id="setting-{{ $setting->key }}" name="{{ $f }}"
                                        class="v-select{{ $err($f) }}">
                                    <option value="1" @selected((string) $val($setting->key, $setting) === '1')>فعال</option>
                                    <option value="0" @selected((string) $val($setting->key, $setting) === '0')>غیرفعال</option>
                                </select>
                            @else
                                {{-- Numbers are typed left-to-right even in an RTL
                                     form: a Persian-ordered digit string in a
                                     numeric field is unreadable and misparses. --}}
                                <input type="{{ $setting->type === 'integer' ? 'number' : 'text' }}"
                                       id="setting-{{ $setting->key }}"
                                       name="{{ $f }}"
                                       value="{{ $val($setting) }}"
                                       class="v-input{{ $err($f) }}"
                                       @if ($setting->type === 'integer') dir="ltr" inputmode="numeric" min="0" @endif
                                       required>
                            @endif

                            @if ($setting->description)
                                <p class="v-hint">{{ $setting->description }}</p>
                            @endif

                            @error($f)
                                <p class="v-error" id="error-{{ $setting->key }}">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        @if ($canManage)
            <div class="v-formactions">
                <a href="{{ route('settings.index') }}" class="v-btn v-btn--ghost">انصراف</a>
                {{-- data-guard is the project's double-submit guard; without it a
                     slow save can be submitted twice and write two history rows. --}}
                <button type="submit" class="v-btn v-btn--primary" data-guard>
                    <x-icon name="check" :size="16" /> ذخیرهٔ تنظیمات
                </button>
            </div>
        @endif
    </form>

    {{-- The history is on the same screen as the values on purpose: the question
         "why is this threshold 45?" is asked while looking at the threshold. --}}
    <section class="v-fsection">
        <h2 class="v-fsection__legend">تاریخچهٔ تغییرات</h2>

        @if ($history->isEmpty())
            <p class="v-hint">هنوز تغییری ثبت نشده است.</p>
        @else
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>کلید</th>
                            <th>از</th>
                            <th>به</th>
                            <th>توسط</th>
                            <th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $row)
                            <tr>
                                <td dir="ltr">{{ $row->key }}</td>
                                <td>{{ $row->old_value ?? '—' }}</td>
                                <td>{{ $row->new_value }}</td>
                                <td>{{ $row->user?->name ?? 'سامانه' }}</td>
                                <td><x-fa-date :value="$row->created_at" with-time /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
