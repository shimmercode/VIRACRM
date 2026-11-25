<?php

namespace App\Services\Pdf;

use App\Models\Project;
use App\Support\JalaliDate;

/**
 * REQ-06.06 / T-PDF-08 — the *public* project PDF, for a client.
 *
 * THE ONE REQUIREMENT THAT DEFINES THIS FILE
 * ------------------------------------------
 * T-PDF-08: "no confidential credential information whatsoever". REQ-06.06 makes
 * public and confidential project documents two separate outputs with
 * independent access control. This is the public one.
 *
 * That is enforced structurally, not by remembering to remove a field:
 *
 *   - `project_credentials` is never loaded, and no relation to it is touched.
 *   - Secrets live in `credential_values` and are only readable through
 *     CredentialVault, which this class does not import.
 *   - The only project fields printed are an explicit allow-list below. A new
 *     column added to `projects` tomorrow does not appear in a client's PDF
 *     until someone adds it here on purpose.
 *
 * An allow-list rather than a deny-list, because a deny-list fails open: the
 * next developer adds `api_key` to `projects` and it ships to the customer.
 */
final class ProjectPdf
{
    /**
     * Columns this document may print. Nothing outside this list is reachable
     * from the template, however the model grows.
     */
    private const PUBLIC_FIELDS = [
        'code',
        'title',
        'type',
        'status',
        'amount',
        'start_date',
        'end_date',
        'notes',
    ];

    public function __construct(private readonly PdfEngine $engine)
    {
    }

    public function build(Project $project): string
    {
        $project->loadMissing(['customer:id,type,company_name,first_name,last_name']);

        return $this->engine->render($this->html($project), [
            'title' => 'شناسنامهٔ پروژه ' . $project->code,
            'subject' => $project->title,
        ]);
    }

    public function filename(Project $project): string
    {
        $code = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $project->code);

        // A NULL code produced "project-.pdf"; fall back to the id, which is
        // unique and always present.
        return 'project-' . ($code !== '' ? $code : $project->id) . '.pdf';
    }

    public function html(Project $project): string
    {
        $e = PdfEngine::e(...);
        $n = PdfEngine::digits(...);

        /* Read through the allow-list, so an unlisted column cannot leak even if
         * a future template accidentally interpolates the whole model. */
        $public = [];
        foreach (self::PUBLIC_FIELDS as $field) {
            $public[$field] = $project->getAttribute($field);
        }

        /* `code` is nullable in the schema (the seed data has none). A blank
         * cell in a document handed to a customer reads as a rendering fault,
         * so an explicit dash says "not recorded" instead. */
        $code = trim((string) ($public['code'] ?? ''));
        $codeCell = $code !== ''
            ? '<span class="ltr">' . $e($code) . '</span>'
            : '<span class="muted">ثبت نشده</span>';
        $codeInline = $code !== '' ? $e($code) : 'ثبت نشده';

        /* Jalali, not ->format('Y/m/d'). The first version printed a Gregorian
         * date in Persian digits, which is worse than a Latin date: the year is
         * wrong and it looks translated. */
        $generatedAt = JalaliDate::fromCarbon(\Carbon\CarbonImmutable::now())->formatLongPersian();

        $start = $public['start_date']
            ? JalaliDate::fromCarbon($project->start_date)->formatPersian()
            : '—';
        $end = $public['end_date']
            ? JalaliDate::fromCarbon($project->end_date)->formatPersian()
            : '—';

        $notes = trim((string) ($public['notes'] ?? ''));
        $notesBlock = $notes === ''
            ? '<p class="muted">توضیحی ثبت نشده است.</p>'
            : '<p style="white-space: pre-wrap">' . $e($notes) . '</p>';

        $amount = $public['amount'] !== null
            ? PdfEngine::money((int) $public['amount']) . ' ریال'
            : '—';

        /* Project stores raw codes and maps them for display. Printing
         * "active" in a Persian document handed to a customer would be a
         * half-translated page, so the model's own mapping is used. */
        $statusLabel = $project->statusLabel();
        $typeLabel = $project->typeLabel();
        $customerName = $project->customer?->display_name ?? '—';
        $titleText = (string) $public['title'];

        return <<<HTML
        <html dir="rtl"><body>
            <div class="doc-head">
                <h1>شناسنامهٔ پروژه — {$e($titleText)}</h1>
                <p class="doc-meta">
                    کد پروژه <span class="ltr">{$codeInline}</span> — سند عمومی، قابل ارائه به مشتری
                </p>
            </div>

            <table>
                <tr><th style="width:32%">کد پروژه</th><td>{$codeCell}</td></tr>
                <tr><th>عنوان</th><td>{$e($public['title'])}</td></tr>
                <tr><th>مشتری</th><td>{$e($project->customer?->display_name ?? '—')}</td></tr>
                <tr><th>نوع</th><td>{$e($project->typeLabel())}</td></tr>
                <tr><th>وضعیت</th><td>{$e($statusLabel)}</td></tr>
                <tr><th>مبلغ</th><td>{$amount}</td></tr>
                <tr><th>مدت</th><td>{$start} تا {$end}</td></tr>
            </table>

            <h2>توضیحات</h2>
            {$notesBlock}

            <div class="doc-foot">
                این سند فقط اطلاعات عمومی پروژه را شامل می‌شود.
                دسترسی‌های فنی پروژه (نام کاربری، نشانی سامانه‌ها و رمزها) عمداً در این
                سند نیامده‌اند و تنها با مجوز جداگانه و از مسیر مستقل قابل دریافت‌اند.
                سند تولیدشده در {$generatedAt} — ویرا.
            </div>
        </body></html>
        HTML;
    }
}
