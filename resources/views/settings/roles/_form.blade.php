@php
    $err = fn (string $f) => $errors->has($f) ? ' is-invalid' : '';
    $val = fn (string $f) => old($f, $role->{$f} ?? '');
    // old() wins on a re-render after a refusal, so the ticks the user made are
    // the ones they see again rather than what was stored.
    $ticked = old('permissions', $granted);
    $ticked = is_array($ticked) ? array_map('strval', $ticked) : [];
    $areas = [
        'accounting' => 'حسابداری', 'audit' => 'گزارش فعالیت', 'contracts' => 'قراردادها',
        'credentials' => 'دسترسی‌ها', 'customers' => 'مشتریان', 'departments' => 'واحدها',
        'files' => 'فایل‌ها', 'invoices' => 'صورت‌حساب‌ها', 'notes' => 'یادداشت‌ها',
        'payroll' => 'حقوق و دستمزد', 'personnel' => 'پرسنل', 'projects' => 'پروژه‌ها',
        'reports' => 'گزارش‌ها', 'settings' => 'تنظیمات', 'users' => 'کاربران',
        'work_items' => 'اقدامات',
    ];
@endphp

<div class="v-form">
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">مشخصات نقش</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="title">عنوان</label>
                <input id="title" name="title" type="text" class="v-input{{ $err('title') }}"
                       value="{{ $val('title') }}" maxlength="191" required
                       placeholder="کارشناس محتوا">
                @error('title')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="code">کد</label>
                <input id="code" name="code" type="text" class="v-input{{ $err('code') }}"
                       value="{{ $val('code') }}" maxlength="64" required dir="ltr"
                       placeholder="content_expert">
                @error('code')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field v-field--span2">
                <label class="v-label" for="description">توضیحات</label>
                <textarea id="description" name="description" rows="2" class="v-input{{ $err('description') }}"
                          maxlength="2000">{{ $val('description') }}</textarea>
            </div>
        </div>
    </fieldset>

    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">مجوزها</legend>

        <p class="v-hint">
            مجوزهای حساس با نشان مشخص شده‌اند. شما نمی‌توانید مجوزی را اعطا کنید
            که خودتان ندارید؛ این محدودیت برای جلوگیری از ارتقای ناخواستهٔ سطح
            دسترسی است.
        </p>

        @foreach ($grouped as $area => $permissions)
            <div class="v-fsection">
                <h3 class="v-fsection__legend">{{ $areas[$area] ?? $area }}</h3>
                <div class="v-grid v-grid--2">
                    @foreach ($permissions as $permission)
                        <label class="v-check">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->code }}"
                                   @checked(in_array($permission->code, $ticked, true))>
                            <span>
                                {{ $permission->title }}
                                <span class="v-table__sub" dir="ltr">{{ $permission->code }}</span>
                                @if ($permission->is_sensitive)
                                    <span class="v-badge v-badge--warning">حساس</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach

        @error('permissions')<p class="v-error">{{ $message }}</p>@enderror
    </fieldset>

    <div class="v-formactions">
        <button type="submit" class="v-btn v-btn--primary">{{ $submitLabel }}</button>
        <a href="{{ route('settings.roles.index') }}" class="v-btn v-btn--ghost">انصراف</a>
    </div>
</div>
