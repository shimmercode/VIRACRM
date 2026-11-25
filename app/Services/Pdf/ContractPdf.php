<?php

namespace App\Services\Pdf;

use App\Models\Contract;

/**
 * REQ-09.05 / T-PDF-01 — a finalised contract as a printable PDF.
 *
 * WHY THIS REFUSES TO RENDER A DRAFT
 * ----------------------------------
 * A draft contract has no permanent number and no frozen body. Printing one
 * produces a document that looks exactly like a binding agreement to everyone
 * holding it, and cannot be told apart from the real thing afterwards. So the
 * builder refuses rather than printing "DRAFT" on it and hoping the reader
 * notices — a watermark is easy to crop, an error is not.
 *
 * THE BODY IS NOT ESCAPED, AND THAT IS DELIBERATE
 * ----------------------------------------------
 * `rendered_body` was frozen at finalisation from an administrator's template or
 * from the contract description. It is plain text and is rendered as text:
 * newlines become paragraph breaks, everything else is HTML-escaped first. It is
 * never inserted as markup. See `body()`.
 */
final class ContractPdf
{
    public function __construct(private readonly PdfEngine $engine)
    {
    }

    /**
     * @throws \RuntimeException when the contract was never finalised
     */
    public function build(Contract $contract): string
    {
        $contract->loadMissing(['customer', 'template:id,title,version', 'finalizedBy:id,name']);

        if ($contract->finalized_at === null) {
            throw new \RuntimeException(
                'این قرارداد نهایی نشده است و نسخهٔ قابل ارائه ندارد. اول قرارداد را نهایی کنید.',
            );
        }

        return $this->engine->render($this->html($contract), [
            'title' => 'قرارداد ' . $contract->number,
            'subject' => $contract->title,
            'keywords' => 'قرارداد, ' . $contract->number,
        ]);
    }

    public function filename(Contract $contract): string
    {
        // ASCII, because a Content-Disposition with raw Persian is mangled by
        // some proxies. The Persian title is available inside the document.
        return 'contract-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string) $contract->number) . '.pdf';
    }

    public function html(Contract $contract): string
    {
        $e = PdfEngine::e(...);
        $num = PdfEngine::digits(...);

        // formatPersian(), not format(): JalaliDate has no format() and a
        // Gregorian fallback would quietly print a 2026 date in a Persian
        // contract.
        $start = $contract->start_date
            ? \App\Support\JalaliDate::fromCarbon($contract->start_date)->formatPersian()
            : '—';
        $end = $contract->end_date
            ? \App\Support\JalaliDate::fromCarbon($contract->end_date)->formatPersian()
            : '—';

        $rows = '';
        $add = function (string $label, string $value) use (&$rows): void {
            $rows .= '<tr><th style="width:35%">' . $label . '</th><td>' . $value . '</td></tr>';
        };

        $add('شمارهٔ قرارداد', '<span class="ltr">' . $e($contract->number) . '</span>');
        $add('عنوان', $e($contract->title));
        // display_name is an accessor on Customer, reached as ->display_name.
        $add('مشتری', $e($contract->customer?->display_name ?? '—'));
        if ($contract->customer?->national_id) {
            $add('شناسهٔ ملی', '<span class="ltr">' . $e($contract->customer->national_id) . '</span>');
        }
        $add('مبلغ', PdfEngine::money($contract->amount ? (int) $contract->amount : null) . ' ریال');
        $add('مبلغ به حروف', $e(\App\Support\Money::fromRial((int) $contract->amount)->toPersianWords()));
        $add('مدت', $start . ' تا ' . $end);
        $add('تاریخ نهایی‌سازی', $contract->finalized_at
            ? \App\Support\JalaliDate::fromCarbon($contract->finalized_at)->formatPersian()
                . ' — ' . $num($contract->finalized_at->format('H:i'))
            : '—');

        $template = $contract->template
            ? $e($contract->template->title) . ' (نسخهٔ ' . $num($contract->template->version) . ')'
            : 'بدون قالب — متن از توضیحات قرارداد';

        $body = $this->body((string) $contract->rendered_body);

        $signer = $contract->finalizedBy?->name;

        /* Contract titles in this domain usually start with "قرارداد", so
         * prefixing one unconditionally printed "قرارداد قرارداد سئو…".
         * The prefix is added only when the title does not already carry it. */
        $heading = str_contains(mb_substr(trim((string) $contract->title), 0, 9), 'قرارداد')
            ? (string) $contract->title
            : 'قرارداد ' . $contract->title;
        $finalizedJalali = $contract->finalized_at
            ? \App\Support\JalaliDate::fromCarbon($contract->finalized_at)->formatLongPersian()
            : '—';

        return <<<HTML
        <html dir="rtl"><body>
            <div class="doc-head">
                <h1>{$e($heading)}</h1>
                <p class="doc-meta">
                    شمارهٔ <span class="ltr">{$e($contract->number)}</span> — ویرا، سامانهٔ مدیریت ارتباط با مشتری
                </p>
            </div>

            <h2>مشخصات قرارداد</h2>
            <table>{$rows}</table>

            <h2>متن قرارداد</h2>
            {$body}

            <p class="doc-meta">قالب استفاده‌شده: {$template}</p>

            <table class="sign">
                <tr>
                    <td>امضای طرف اول</td>
                    <td>امضای طرف دوم (مشتری)</td>
                </tr>
            </table>

            <div class="doc-foot">
                این سند در تاریخ {$finalizedJalali}
                توسط {$e($signer ?? '—')} نهایی شده و نسخهٔ آن در سامانه نگهداری می‌شود.
                تغییر بعدی در اطلاعات مشتری، این متن را تغییر نمی‌دهد.
            </div>
        </body></html>
        HTML;
    }

    /**
     * Turn plain contract text into paragraphs.
     *
     * The body was written as plain text by an administrator. `nl2br` after
     * escaping gives the author's line breaks without ever letting a `<` become
     * a tag. If this were inserted raw, an administrator could place arbitrary
     * markup — including remote images — into a document that is handed to a
     * customer, and mPDF would fetch them.
     */
    private function body(string $text): string
    {
        $escaped = PdfEngine::e(trim($text));

        if ($escaped === '') {
            return '<p class="muted">متن قرارداد ثبت نشده است.</p>';
        }

        return '<p style="white-space: pre-wrap">' . $escaped . '</p>';
    }
}
