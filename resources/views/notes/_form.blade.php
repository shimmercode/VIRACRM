@php
    // Shared by create and edit so the two can never drift apart. The `$note`
    // variable is null on create.
    $note = $note ?? null;
    $selected = [
        'customer_id' => old('customer_id', $note->customer_id ?? $preset['customer_id'] ?? null),
        'contract_id' => old('contract_id', $note->contract_id ?? $preset['contract_id'] ?? null),
        'project_id' => old('project_id', $note->project_id ?? $preset['project_id'] ?? null),
        'assigned_to' => old('assigned_to', $note->assigned_to ?? null),
        'priority' => old('priority', $note->priority ?? 'normal'),
    ];
@endphp

<div class="v-form-grid">
    <div class="v-form-grid__full v-field">
        <label for="title" class="v-field__label">عنوان</label>
        <input id="title" type="text" name="title" class="v-input @error('title') is-invalid @enderror"
               value="{{ old('title', $note->title ?? '') }}" required maxlength="190">
        @error('title')<p class="v-hint" style="color:var(--v-danger)">{{ $message }}</p>@enderror
    </div>

    <div class="v-form-grid__full v-field">
        <label for="description" class="v-field__label">متن</label>
        <textarea id="description" name="description" class="v-input" rows="5"
                  placeholder="شرح یادداشت یا جزئیات وظیفه">{{ old('description', $note->description ?? '') }}</textarea>
        @error('description')<p class="v-hint" style="color:var(--v-danger)">{{ $message }}</p>@enderror
    </div>

    <div class="v-field">
        <label for="due_date" class="v-field__label">سررسید (شمسی)</label>
        <input id="due_date" type="text" name="due_date" class="v-input"
               placeholder="۱۴۰۵/۰۹/۰۱" inputmode="numeric"
               value="{{ old('due_date', $note?->due_date?->format('Y-m-d') ? \App\Support\JalaliDate::fromCarbon($note->due_date)->formatAscii() : '') }}">
        <p class="v-hint">برای وظیفه الزامی است؛ برای یادداشت ساده خالی بگذارید.</p>
        @error('due_date')<p class="v-hint" style="color:var(--v-danger)">{{ $message }}</p>@enderror
    </div>

    <div class="v-field">
        <label for="priority" class="v-field__label">اولویت</label>
        <select id="priority" name="priority" class="v-select" required>
            @foreach ($priorities as $value => $label)
                <option value="{{ $value }}" @selected($selected['priority'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="v-field">
        <label for="assigned_to" class="v-field__label">مسئول</label>
        <select id="assigned_to" name="assigned_to" class="v-select">
            <option value="">— بدون مسئول —</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected((string) $selected['assigned_to'] === (string) $user->id)>
                    {{ $user->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="v-field">
        <label for="project_id" class="v-field__label">پروژه</label>
        <select id="project_id" name="project_id" class="v-select">
            <option value="">— بدون پروژه —</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected((string) $selected['project_id'] === (string) $project->id)>
                    {{ $project->title }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="v-field">
        <label for="customer_id" class="v-field__label">مشتری</label>
        <select id="customer_id" name="customer_id" class="v-select">
            <option value="">— بدون مشتری —</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected((string) $selected['customer_id'] === (string) $customer->id)>
                    {{ $customer->display_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="v-field">
        <label for="contract_id" class="v-field__label">قرارداد</label>
        <select id="contract_id" name="contract_id" class="v-select">
            <option value="">— بدون قرارداد —</option>
            @foreach ($contracts as $contract)
                <option value="{{ $contract->id }}" @selected((string) $selected['contract_id'] === (string) $contract->id)>
                    {{ $contract->title }}
                </option>
            @endforeach
        </select>
    </div>
</div>
