<?php

namespace App\Services\Pdf;

use App\Models\Project;
use App\Models\ProjectCredential;
use App\Services\Security\CredentialVault;
use App\Support\JalaliDate;

/**
 * REQ-06.05 / REQ-06.07 / T-PDF-09 — the *confidential* credentials document.
 *
 * This is the only PDF in the product that contains secrets, and every decision
 * in it follows from that:
 *
 *  - Permission is `credentials.export`, separate from `view` and `reveal`.
 *    Someone who may see that a project has a WordPress login is not thereby
 *    someone who may carry all of them out of the building in one file.
 *  - The route is POST, not GET. A GET export lands in browser history, proxy
 *    logs and any `Referer` header; a secret-bearing URL is a secret.
 *  - The response is `no-store, private`, so a shared office machine does not
 *    keep a copy in its disk cache.
 *  - Every export writes an audit row naming the project and the credential
 *    count — §6 asks for attribution, and a file that leaves the system without
 *    a trace is the exact thing that rule exists to prevent.
 *  - Nothing is written to a public path: the bytes go straight to the response.
 *
 * WHY THE PAGE CARRIES A WARNING BAND
 * -----------------------------------
 * REQ-06.07 — a file containing passwords must carry a confidentiality warning.
 * This matters more than it looks: the file is printed, forwarded and attached
 * by people who no longer have the screen in front of them. The warning has to
 * survive on paper, so it is on every page (SetHTMLHeader) and in the footer.
 */
final class CredentialPdf
{
    public function __construct(
        private readonly PdfEngine $engine,
        private readonly CredentialVault $vault,
    ) {
    }

    /**
     * @param  iterable<ProjectCredential>  $credentials
     */
    public function build(Project $project, iterable $credentials, string $generatedBy): string
    {
        return $this->engine->render($this->html($project, $credentials, $generatedBy), [
            'title' => 'دسترسی‌های محرمانهٔ پروژه ' . $project->code,
            'subject' => 'محرمانه',
        ]);
    }

    public function filename(Project $project): string
    {
        /* The word "confidential" is in the filename on purpose: a file that
         * ends up in a downloads folder or an email thread should announce what
         * it is before it is opened.
         *
         * `code` is nullable, and the first version produced
         * "CONFIDENTIAL-credentials-.pdf" — a file whose name identifies
         * nothing. The id is a poor identifier but a real one, and this file
         * must never be anonymous. */
        $code = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $project->code);

        return 'CONFIDENTIAL-credentials-' . ($code !== '' ? $code : $project->id) . '.pdf';
    }

    /**
     * Does this document have anything to put in it?
     *
     * An empty confidential document is not harmless — it is a file titled
     * CONFIDENTIAL that a reader will assume is complete. The caller refuses
     * instead of producing one.
     */
    public static function hasContent(iterable $credentials): bool
    {
        foreach ($credentials as $credential) {
            return true;
        }

        return false;
    }

    public function html(Project $project, iterable $credentials, string $generatedBy): string
    {
        $e = PdfEngine::e(...);
        $n = PdfEngine::digits(...);

        $rows = '';
        $count = 0;

        foreach ($credentials as $credential) {
            $count++;

            $secrets = '';
            $type = $credential->type;

            if ($type !== null) {
                foreach ($type->secretFields() as $field) {
                    $label = $type->labelFor($field) ?? $field;

                    /* A secret that will not decrypt is reported as unavailable,
                     * not skipped. Silently omitting it produces a document that
                     * looks complete and is missing a password the reader is
                     * about to rely on. */
                    try {
                        $plain = $this->vault->reveal($credential, $field);
                    } catch (\Throwable) {
                        $plain = null;
                        $secrets .= '<div class="secret-row"><span class="ltr">'
                            . $e($label) . ': </span><span class="muted">[قابل بازیابی نیست]</span></div>';
                        continue;
                    }

                    if ($plain === null || $plain === '') {
                        continue;
                    }

                    $secrets .= '<div class="secret-row"><span class="ltr">' . $e($label)
                        . ': </span><span class="ltr secret">' . $e($plain) . '</span></div>';
                }
            }

            if ($secrets === '') {
                $secrets = '<div class="muted">رمزی ثبت نشده است.</div>';
            }

            $url = $credential->public_url
                ? '<div><span class="ltr">' . $e($credential->public_url) . '</span></div>'
                : '';
            $username = $credential->username
                ? '<div>نام کاربری: <span class="ltr">' . $e($credential->username) . '</span></div>'
                : '';
            $rotated = $credential->rotated_at
                ? '<div class="doc-meta">آخرین تغییر رمز: '
                    . JalaliDate::fromCarbon($credential->rotated_at)->formatPersian() . '</div>'
                : '';

            $rows .= '<tr>'
                . '<td>' . $e($credential->label) . '</td>'
                . '<td>' . $e($type?->title ?? '—') . '</td>'
                . '<td>' . $url . $username . $secrets . $rotated . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="3" class="muted">هیچ دسترسی ثبت نشده است.</td></tr>';
        }

        $now = \Carbon\CarbonImmutable::now();
        $stamp = JalaliDate::fromCarbon($now)->formatLongPersian()
            . ' — ' . $n($now->timezone('Asia/Tehran')->format('H:i'));

        /* Resolved before the heredoc: a cast or method call inside `{$...}` is
         * a parse error, which is how the payslip builder failed first time. */
        $countLabel = $n((string) $count);
        $titleText = (string) $project->title;
        $codeText = (string) $project->code;

        return <<<HTML
        <html dir="rtl"><body>
            <div class="notice">
                <strong>محرمانه.</strong>
                این سند شامل رمزهای عبور دسترسی‌های پروژه است. نگهداری، ارسال یا چاپ آن فقط
                برای افراد مجاز است. پس از استفاده، نسخهٔ چاپی و فایل را از بین ببرید.
                هر بار تولید این سند در گزارش فعالیت سامانه ثبت می‌شود.
            </div>

            <div class="doc-head">
                <h1>دسترسی‌های پروژه — {$e($titleText)}</h1>
                <p class="doc-meta">
                    کد پروژه <span class="ltr">{$e($codeText)}</span>
                    — {$countLabel} دسترسی
                    — تولیدشده توسط {$e($generatedBy)} در {$stamp}
                </p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width:24%">عنوان</th>
                        <th style="width:18%">نوع</th>
                        <th>اطلاعات دسترسی</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>

            <div class="doc-foot">
                ویرا — سند محرمانهٔ دسترسی‌ها. بازتولید یا توزیع این سند بدون مجوز ممنوع است.
                در صورت مشاهدهٔ افشا، فوراً رمزهای این پروژه را تغییر دهید.
            </div>
        </body></html>
        HTML;
    }
}
