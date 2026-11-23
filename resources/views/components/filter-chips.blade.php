@props([
    'chips' => [],
    'clearHref' => null,
])

{{--
    Active filters as removable pills.

    Why: the filter bar keeps state in the querystring, so a user who arrives
    from a link, a bookmark or a page-2 → page-3 navigation cannot see what is
    filtered without scrolling back up to the three selects and reading their
    current values. Worse, on a phone the filterbar wraps and the selects may be
    off-screen entirely while the results below are filtered.

    Each chip is an <a> to the same URL with *only that one parameter* removed.
    That means:

      - it works with JavaScript disabled,
      - it is keyboard reachable and announced as a link, not a button,
      - removing one filter preserves every other filter, the sort and the page
        reset behaviour that the controller already defines.

    The URL is built with Laravel's own query-string helpers so encoding and
    array/empty semantics match the rest of the app exactly.
--}}

@if (count($chips) > 0)
    @php
        // One URL per chip: current query minus that chip's key. Built here so
        // the view does not need to know how the controller parses input.
        $query = request()->query();
        $base = request()->url();

        $urlWithout = function (string $key) use ($query, $base): string {
            $next = $query;
            unset($next[$key]);

            return $next === [] ? $base : $base . '?' . http_build_query($next);
        };
    @endphp

    <div class="v-chips" role="group" aria-label="فیلترهای فعال">
        <span class="vira-sr-only">فیلترهای فعال:</span>

        @foreach ($chips as $chip)
            {{-- A chip is only rendered when its value is non-empty, so `label`
                 and `value` are guaranteed here by the caller. --}}
            <a class="v-chip"
               href="{{ $urlWithout($chip['key']) }}"
               aria-label="حذف فیلتر {{ $chip['label'] }}: {{ $chip['value'] }}">
                <span class="v-chip__key">{{ $chip['label'] }}</span>
                <span class="v-chip__value">{{ $chip['value'] }}</span>
                <x-icon name="x" :size="12" class="v-chip__x" />
            </a>
        @endforeach

        @if ($clearHref)
            <a class="v-chips__clear" href="{{ $clearHref }}">حذف همه</a>
        @endif
    </div>
@endif
