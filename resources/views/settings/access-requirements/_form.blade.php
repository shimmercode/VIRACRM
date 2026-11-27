@php
    $err = fn (string $f) => $errors->has($f) ? ' is-invalid' : '';
    $val = fn (string $f) => old($f, $requirement->{$f} ?? '');
@endphp

<div class="v-form">
    <fieldset class="v-fsection">
        <legend class="v-fsection__legend">دسترسی الزامی</legend>

        <div class="v-grid v-grid--2">
            <div class="v-field">
                <label class="v-label" for="project_type">نوع پروژه</label>
                <select id="project_type" name="project_type" class="v-select{{ $err('project_type') }}" required>
                    @foreach ($projectTypes as $code => $label)
                        <option value="{{ $code }}" @selected((string) $val('project_type') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('project_type')<p class="v-error">{{ $message }}</p>@enderror
            </div>

            <div class="v-field">
                <label class="v-label" for="credential_type_id">نوع دسترسی</label>
                <select id="credential_type_id" name="credential_type_id" class="v-select{{ $err('credential_type_id') }}" required>
                    <option value="">انتخاب کنید…</option>
                    @foreach ($credentialTypes as $ct)
                        <option value="{{ $ct->id }}" @selected((int) $val('credential_type_id') === (int) $ct->id)>{{ $ct->title }}</option>
                    @endforeach
                </select>
                <p class="v-hint">فقط انواع دسترسی فعال.</p>
                @error('credential_type_id')<p class="v-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <label class="v-check">
            <input type="hidden" name="is_required" value="0">
            <input type="checkbox" name="is_required" value="1"
                   @checked(old('is_required', $requirement->is_required ?? true))>
            <span>الزامی است</span>
        </label>

        <label class="v-check">
            {{-- Without the hidden companion an unchecked box posts nothing and
                 the server falls back to its default. --}}
            <input type="hidden" name="blocks_finalize" value="0">
            <input type="checkbox" name="blocks_finalize" value="1"
                   @checked(old('blocks_finalize', $requirement->blocks_finalize ?? true))>
            <span>تا ثبت این دسترسی، پروژه نهایی/فعال نشود</span>
        </label>
        <p class="v-hint">
            این دو علامت با هم اعمال می‌شوند: دسترسی‌ای که هم الزامی و هم مانع باشد،
            اجازهٔ فعال‌سازی پروژه را نمی‌دهد. پیش‌نویس همیشه مجاز است.
        </p>
        @error('blocks_finalize')<p class="v-error">{{ $message }}</p>@enderror
    </fieldset>

    <div class="v-formactions">
        <button type="submit" class="v-btn v-btn--primary">{{ $submitLabel }}</button>
        <a href="{{ route('settings.access-requirements.index') }}" class="v-btn v-btn--ghost">انصراف</a>
    </div>
</div>
