@extends('layouts.app')

@section('title', 'یادداشت و وظیفه')

@section('content')
    @php $pd = fn ($v) => \App\Support\Money::toPersianDigits((string) $v); @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">یادداشت و وظیفه</h1>
            <p class="v-pagehead__desc">
                یادداشت‌های کاری و وظایف، متصل به مشتری، قرارداد یا پروژه.
                @if ($overdueCount > 0)
                    <strong>{{ $pd($overdueCount) }} وظیفه از سررسید گذشته است.</strong>
                @endif
            </p>
        </div>

        @if (auth()->user()->hasPermission('notes.create'))
            <div class="v-pagehead__actions">
                <a href="{{ route('notes.create') }}" class="v-btn v-btn--primary">
                    <x-icon name="plus" :size="16" /> یادداشت جدید
                </a>
            </div>
        @endif
    </header>

    <form class="v-filterbar" method="GET" action="{{ route('notes.index') }}">
        <div class="v-filterbar__search">
            <x-icon name="search" :size="18" class="v-filterbar__icon" />
            <input type="search" name="q" value="{{ $filters['q'] }}"
                   placeholder="عنوان یا متن یادداشت…" aria-label="جست‌وجوی یادداشت">
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-status">وضعیت</label>
            <select id="f-status" name="status" class="v-select v-select--sm">
                <option value="open" @selected($filters['status'] === 'open')>باز</option>
                <option value="done" @selected($filters['status'] === 'done')>انجام‌شده</option>
                <option value="cancelled" @selected($filters['status'] === 'cancelled')>لغو شده</option>
                <option value="all" @selected($filters['status'] === 'all')>همه</option>
            </select>
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-priority">اولویت</label>
            <select id="f-priority" name="priority" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($priorities as $value => $label)
                    <option value="{{ $value }}" @selected($filters['priority'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="v-filterbar__group">
            <label class="v-filterbar__label" for="f-assignee">مسئول</label>
            <select id="f-assignee" name="assigned_to" class="v-select v-select--sm">
                <option value="">همه</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected($filters['assigned_to'] === (string) $user->id)>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
        </div>

        @if ($filters['mine'] === '1')
            <input type="hidden" name="mine" value="1">
        @endif

        <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
            <x-icon name="filter" :size="16" /> اعمال
        </button>

        @if ($filters['q'] !== '' || $filters['priority'] !== '' || $filters['assigned_to'] !== '' || $filters['status'] !== 'open' || $filters['mine'] === '1')
            <a href="{{ route('notes.index') }}" class="v-btn v-btn--link v-btn--sm">پاک کردن</a>
        @endif
    </form>

    <div class="v-card">
        @if ($notes->isEmpty())
            <x-empty-state
                title="یادداشتی با این فیلترها نیست"
                description="یادداشت‌ها دستور کار روزانه‌اند؛ می‌توانید آن‌ها را به مشتری، قرارداد یا پروژه متصل کنید."
                icon="bell"
            />
        @else
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th scope="col">عنوان</th>
                            <th scope="col">اولویت</th>
                            <th scope="col">سررسید</th>
                            <th scope="col">مسئول</th>
                            <th scope="col">متصل به</th>
                            <th scope="col" class="v-table__actions">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($notes as $note)
                            @php
                                $isOverdue = $note->status === 'open'
                                    && $note->due_date !== null
                                    && $note->due_date->lt(now()->startOfDay());
                                $priorityTone = match ($note->priority) {
                                    'urgent' => 'danger',
                                    'high' => 'warn',
                                    'normal' => 'neutral',
                                    default => 'neutral',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <span class="v-table__primary">{{ $note->title }}</span>
                                    @if ($note->status === 'done')
                                        <span class="v-badge v-badge--success">انجام‌شده</span>
                                    @elseif ($note->status === 'cancelled')
                                        <span class="v-badge v-badge--neutral">لغو شده</span>
                                    @endif
                                    @if ($note->description)
                                        <div class="v-table__sub">{{ \Illuminate\Support\Str::limit($note->description, 80) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="v-badge v-badge--{{ $priorityTone }}">{{ $note->priorityLabel() }}</span>
                                </td>
                                <td>
                                    <x-fa-date :value="$note->due_date" />
                                    @if ($isOverdue)
                                        <div class="v-table__sub" style="color:var(--v-danger)">سررسید گذشته</div>
                                    @endif
                                </td>
                                <td>{{ $note->assignee?->name ?? '—' }}</td>
                                <td>
                                    @if ($note->project)
                                        <a href="{{ route('projects.show', $note->project) }}">{{ $note->project->title }}</a>
                                    @elseif ($note->customer)
                                        <a href="{{ route('customers.show', $note->customer) }}">{{ $note->customer->display_name }}</a>
                                    @else
                                        <span class="v-dl__value--empty">—</span>
                                    @endif
                                </td>
                                <td class="v-table__actions">
                                    @if ($note->status === 'open')
                                        @if (auth()->user()->hasPermission('notes.update'))
                                            <form method="POST" action="{{ route('notes.complete', $note) }}">
                                                @csrf
                                                <button type="submit" class="v-btn v-btn--ghost v-btn--sm">انجام شد</button>
                                            </form>
                                        @endif
                                    @elseif ($note->status === 'done')
                                        @if (auth()->user()->hasPermission('notes.update'))
                                            <form method="POST" action="{{ route('notes.reopen', $note) }}">
                                                @csrf
                                                <button type="submit" class="v-btn v-btn--ghost v-btn--sm">بازکردن</button>
                                            </form>
                                        @endif
                                    @endif

                                    @if (auth()->user()->hasPermission('notes.update'))
                                        <a href="{{ route('notes.edit', $note) }}" class="v-btn v-btn--ghost v-btn--sm">ویرایش</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$notes" />
        @endif
    </div>
@endsection
