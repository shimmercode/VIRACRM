@extends('layouts.app')

@section('title', 'ساختار سازمانی')

@section('content')
    @php use App\Support\Money; use App\Models\Department; @endphp

    <header class="v-pagehead">
        <div class="v-pagehead__text">
            <h1 class="v-pagehead__title">ساختار سازمانی</h1>
            <p class="v-pagehead__desc">
                واحدها «چه کسی» و مراکز هزینه «برای چه چیزی» را مشخص می‌کنند.
                هر دو، ابعادی هستند که گزارش‌های مالی و پروندهٔ پرسنل بر اساس آن‌ها تفکیک می‌شوند.
            </p>
        </div>
    </header>

    {{-- Unassigned headcount is invisible in every departmental report, so it is
         stated here rather than left for someone to notice. --}}
    @if ($unassigned > 0)
        <x-alert type="info">
            {{ Money::toPersianDigits((string) $unassigned) }} کارمند بدون واحد سازمانی ثبت شده‌اند.
            تا زمانی که به واحدی منتقل نشوند، در هیچ گزارش تفکیک‌شده‌ای دیده نمی‌شوند.
        </x-alert>
    @endif

    <div class="v-detail-grid">
        <div class="v-detail-grid__main">
            {{-- ── Departments ──────────────────────────────────────────── --}}
            <div class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">واحدهای سازمانی</h2>
                    <span class="v-card__meta">
                        {{ Money::toPersianDigits((string) $departments->count()) }} واحد
                    </span>
                </header>

                @if ($departments->isEmpty())
                    <x-empty-state title="هنوز واحدی ثبت نشده است"
                        description="واحد سازمانی پایهٔ تفکیک گزارش‌های پرسنلی و مالی است. هر مرکز هزینه به یک واحد متصل می‌شود."
                        icon="settings" />
                @else
                    <div class="v-tree">
                        @foreach ($roots as $root)
                            <div class="v-tree__group">
                                <div class="v-tree__row">
                                    <div class="v-tree__main">
                                        <span class="v-tree__code">{{ Money::toPersianDigits($root->code) }}</span>
                                        <span class="v-tree__title">{{ $root->title }}</span>
                                        @unless ($root->is_active)
                                            <x-badge tone="muted" :dot="true">غیرفعال</x-badge>
                                        @endunless
                                    </div>
                                    <div class="v-tree__meta">
                                        <span>{{ Money::toPersianDigits((string) $root->employees_count) }} کارمند</span>
                                        <span>{{ Money::toPersianDigits((string) $root->children_count) }} زیرمجموعه</span>

                                        @if ($canManage)
                                            <details class="v-inlineedit">
                                                <summary class="v-iconbtn" aria-label="ویرایش {{ $root->title }}">
                                                    <x-icon name="edit" :size="16" />
                                                </summary>
                                                <form method="POST" action="{{ route('organization.departments.update', $root) }}"
                                                      class="v-inlineedit__panel">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="v-field">
                                                        <label class="v-label" for="t-{{ $root->id }}">نام واحد</label>
                                                        <input id="t-{{ $root->id }}" name="title" type="text"
                                                               class="v-input" value="{{ $root->title }}" required>
                                                    </div>
                                                    <div class="v-field">
                                                        <label class="v-label" for="s-{{ $root->id }}">ترتیب نمایش</label>
                                                        <input id="s-{{ $root->id }}" name="sort_order" type="number"
                                                               class="v-input" value="{{ $root->sort_order }}" min="0" max="9999">
                                                    </div>
                                                    <div class="v-field">
                                                        <label class="v-label" for="d-{{ $root->id }}">توضیح</label>
                                                        <textarea id="d-{{ $root->id }}" name="description"
                                                                  class="v-textarea" rows="2">{{ $root->description }}</textarea>
                                                    </div>
                                                    <label class="v-check">
                                                        <input type="checkbox" name="is_active" value="1"
                                                               @checked($root->is_active)>
                                                        <span>فعال</span>
                                                    </label>
                                                    <button type="submit" class="v-btn v-btn--primary v-btn--sm">
                                                        ذخیره
                                                    </button>
                                                </form>
                                            </details>
                                        @endif

                                        @if ($canDelete)
                                            <form method="POST" action="{{ route('organization.departments.destroy', $root) }}"
                                                  data-confirm="واحد «{{ $root->title }}» حذف شود؟ مرکز هزینهٔ آن هم حذف می‌شود.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="v-iconbtn v-iconbtn--danger"
                                                        aria-label="حذف {{ $root->title }}">
                                                    <x-icon name="trash" :size="16" />
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                @foreach ($departments->where('parent_id', $root->id) as $child)
                                    <div class="v-tree__row v-tree__row--child">
                                        <div class="v-tree__main">
                                            <span class="v-tree__code">{{ Money::toPersianDigits($child->code) }}</span>
                                            <span class="v-tree__title">{{ $child->title }}</span>
                                            @unless ($child->is_active)
                                                <x-badge tone="muted" :dot="true">غیرفعال</x-badge>
                                            @endunless
                                        </div>
                                        <div class="v-tree__meta">
                                            <span>{{ Money::toPersianDigits((string) $child->employees_count) }} کارمند</span>

                                            @if ($canDelete)
                                                <form method="POST" action="{{ route('organization.departments.destroy', $child) }}"
                                                      data-confirm="واحد «{{ $child->title }}» حذف شود؟">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="v-iconbtn v-iconbtn--danger"
                                                            aria-label="حذف {{ $child->title }}">
                                                        <x-icon name="trash" :size="16" />
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($canManage)
                    <div class="v-card__body">
                        <form method="POST" action="{{ route('organization.departments.store') }}" class="v-inlineform">
                            @csrf
                            <div class="v-field">
                                <label class="v-label" for="new-code">کد واحد</label>
                                <input id="new-code" name="code" type="text" class="v-input" required
                                       dir="ltr" maxlength="32" style="max-width: 130px">
                            </div>
                            <div class="v-field">
                                <label class="v-label" for="new-title">نام واحد</label>
                                <input id="new-title" name="title" type="text" class="v-input" required
                                       style="min-width: 180px">
                            </div>
                            <div class="v-field">
                                <label class="v-label" for="new-parent">زیرمجموعهٔ</label>
                                <select id="new-parent" name="parent_id" class="v-select" style="min-width: 160px">
                                    <option value="">— واحد اصلی —</option>
                                    @foreach ($roots as $root)
                                        <option value="{{ $root->id }}">{{ $root->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="v-btn v-btn--primary v-btn--sm">
                                <x-icon name="plus" :size="16" /> افزودن واحد
                            </button>
                        </form>
                        <p class="v-hint">
                            ساختار حداکثر دو سطح دارد: واحد اصلی و زیرمجموعه‌های آن.
                        </p>
                    </div>
                @endif
            </div>

            {{-- ── Cost centres ─────────────────────────────────────────── --}}
            <div class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">مراکز هزینه</h2>
                    <span class="v-card__meta">
                        {{ Money::toPersianDigits((string) $costCenters->count()) }} مرکز
                    </span>
                </header>

                @if ($costCenters->isEmpty())
                    <x-empty-state title="مرکز هزینه‌ای ثبت نشده است"
                        description="مرکز هزینه، بُعد گزارش‌های مالی است: هر سطر سند حسابداری می‌تواند به یکی از این مراکز نسبت داده شود."
                        icon="chart" />
                @else
                    <div class="v-tablewrap">
                        <table class="v-table">
                            <thead>
                                <tr>
                                    <th scope="col">کد</th>
                                    <th scope="col">عنوان</th>
                                    <th scope="col">واحد سازمانی</th>
                                    <th scope="col">وضعیت</th>
                                    @if ($canDelete)
                                        <th scope="col" class="v-table__actions">حذف</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($costCenters as $center)
                                    <tr>
                                        <td data-label="کد" class="v-table__nowrap" dir="ltr">
                                            {{ Money::toPersianDigits($center->code) }}
                                        </td>
                                        <td data-label="عنوان">{{ $center->title }}</td>
                                        <td data-label="واحد سازمانی">
                                            {{ $center->department?->title ?? '—' }}
                                        </td>
                                        <td data-label="وضعیت">
                                            @if ($center->is_active)
                                                <x-badge tone="success" :dot="true">فعال</x-badge>
                                            @else
                                                <x-badge tone="muted" :dot="true">غیرفعال</x-badge>
                                            @endif
                                        </td>
                                        @if ($canDelete)
                                            <td class="v-table__actions">
                                                <form method="POST"
                                                      action="{{ route('organization.cost-centers.destroy', $center) }}"
                                                      data-confirm="مرکز هزینهٔ «{{ $center->title }}» حذف شود؟">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="v-iconbtn v-iconbtn--danger"
                                                            aria-label="حذف {{ $center->title }}">
                                                        <x-icon name="trash" :size="16" />
                                                    </button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($canManage && $departments->isNotEmpty())
                    <div class="v-card__body">
                        <form method="POST" action="{{ route('organization.cost-centers.store') }}" class="v-inlineform">
                            @csrf
                            <div class="v-field">
                                <label class="v-label" for="cc-code">کد مرکز</label>
                                <input id="cc-code" name="code" type="text" class="v-input" required
                                       dir="ltr" maxlength="32" style="max-width: 130px">
                            </div>
                            <div class="v-field">
                                <label class="v-label" for="cc-title">عنوان</label>
                                <input id="cc-title" name="title" type="text" class="v-input" required
                                       style="min-width: 180px">
                            </div>
                            <div class="v-field">
                                <label class="v-label" for="cc-dept">واحد سازمانی</label>
                                <select id="cc-dept" name="department_id" class="v-select" required style="min-width: 170px">
                                    <option value="">— انتخاب واحد —</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}">
                                            {{ $department->title }}{{ $department->parent_id ? ' (زیرمجموعه)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="v-btn v-btn--ghost v-btn--sm">
                                <x-icon name="plus" :size="16" /> افزودن مرکز هزینه
                            </button>
                        </form>
                    </div>
                @elseif ($canManage)
                    <div class="v-card__body">
                        <p class="v-hint">
                            پیش از ثبت مرکز هزینه، حداقل یک واحد سازمانی بسازید.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <aside class="v-detail-grid__aside">
            <section class="v-card">
                <header class="v-card__head">
                    <h2 class="v-card__title">این ساختار کجا استفاده می‌شود؟</h2>
                </header>
                <div class="v-card__body">
                    <ul class="v-list">
                        <li class="v-list__item">
                            <span class="v-list__main">پروندهٔ پرسنل</span>
                            <span class="v-list__meta">هر کارمند به یک واحد تعلق دارد</span>
                        </li>
                        <li class="v-list__item">
                            <span class="v-list__main">سند حسابداری</span>
                            <span class="v-list__meta">هر سطر می‌تواند واحد و مرکز هزینه داشته باشد</span>
                        </li>
                        <li class="v-list__item">
                            <span class="v-list__main">فیش حقوقی</span>
                            <span class="v-list__meta">هزینهٔ حقوق با واحد کارمند تفکیک می‌شود</span>
                        </li>
                    </ul>
                    <p class="v-hint" style="margin-top: var(--vira-space-3)">
                        کارمندی که واحد ندارد، در تفکیک‌های سازمانی دیده نمی‌شود؛ به همین دلیل
                        تعداد آن‌ها بالای همین صفحه گزارش می‌شود.
                    </p>
                </div>
            </section>
        </aside>
    </div>
@endsection
