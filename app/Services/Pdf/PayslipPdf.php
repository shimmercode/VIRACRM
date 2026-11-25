<?php

namespace App\Services\Pdf;

use App\Models\Payslip;
use App\Support\JalaliDate;

/**
 * REQ-07.03 / T-PDF-10 — a monthly payslip as a printable PDF.
 *
 * WHAT THE CLIENT ASKED FOR, ITEM BY ITEM
 * ---------------------------------------
 *   name, month/year, base salary, project count, project amounts, bonus,
 *   deductions, net — printable, with a place for the employee's signature and
 *   the manager's.
 *
 * Every one of those is a row below. The manual line items are listed
 * individually rather than collapsed into the totals, because "deductions:
 * 3,500,000" is not an answer an employee can check; "advance 2,000,000,
 * insurance 1,500,000" is.
 *
 * A PAYSLIP IS A PRIVATE DOCUMENT
 * -------------------------------
 * Nothing here decides who may download it — that is the route's permission
 * check — but two things are deliberate: the file is never written to a public
 * path (PdfEngine returns bytes), and the response wrapper sets no-store so a
 * shared office machine does not keep a copy in its disk cache.
 */
final class PayslipPdf
{
    public function __construct(private readonly PdfEngine $engine)
    {
    }

    public function build(Payslip $payslip): string
    {
        $payslip->loadMissing(['employee.department', 'period', 'items.project:id,title,code', 'computedBy:id,name']);

        return $this->engine->render($this->html($payslip), [
            'title' => 'فیش حقوقی ' . ($payslip->employee?->displayName() ?? ''),
            'subject' => 'فیش حقوقی',
        ], self::COMPACT_CSS);
    }

    public function filename(Payslip $payslip): string
    {
        $period = $payslip->period;
        $tag = $period
            ? sprintf('%d-%02d', $period->jalali_year, $period->jalali_month)
            : 'unknown';

        return 'payslip-' . $payslip->id . '-' . $tag . '.pdf';
    }

    /**
     * A payslip is one slip of paper.
     *
     * With the base document styling it ran to two pages — the second holding
     * only the footer line, which looks like a mistake when it is handed over or
     * printed. The base sheet is tuned for prose (line-height 1.9) and a payslip
     * is mostly tables, so the density is raised here instead of shrinking the
     * base sheet for every document.
     */
    private const COMPACT_CSS = <<<'CSS'
    <style>
        h1 { font-size: 14pt; margin-bottom: 2mm; }
        h2 { font-size: 11pt; margin: 3.5mm 0 1.5mm; }
        table { margin: 1.5mm 0 2.5mm; }
        th, td { padding: 1.1mm 2mm; }
        .doc-head { margin-bottom: 3mm; padding-bottom: 2mm; }
        .sign { margin-top: 6mm; }
        .doc-foot { margin-top: 4mm; }
        .total { margin: 3mm 0 1mm; }
    </style>
    CSS;

    public function html(Payslip $payslip): string
    {
        $e = PdfEngine::e(...);
        $n = PdfEngine::digits(...);
        $m = static fn (?int $v): string => PdfEngine::money($v === null ? null : (int) $v);

        $employee = $payslip->employee;
        $period = $payslip->period;

        $monthName = $period
            ? (JalaliDate::MONTH_NAMES[($period->jalali_month ?? 1) - 1] ?? '')
            : '—';
        $periodLabel = $period
            ? $monthName . ' ' . $n((string) $period->jalali_year)
            : '—';

        /* ── employee block ─────────────────────────────────────────────── */
        $dept = $employee?->department?->title;

        /* ── the formula, written out ────────────────────────────────────
         * The same order as the Payslip model and PayrollCalculator. A payslip
         * that shows only a net figure cannot be checked by the person being
         * paid, and the first question at the counter is always "where did
         * this number come from". */
        $earnings = [
            'حقوق پایه' => (int) $payslip->base_salary,
            'پاداش' => (int) $payslip->bonus,
            'اضافه‌کاری' => (int) $payslip->overtime,
        ];

        $deductions = [
            'مساعده' => (int) $payslip->advance,
            'کسورات' => (int) $payslip->deductions,
        ];

        $items = $payslip->items ?? collect();

        $earningRows = '';
        foreach ($earnings as $label => $value) {
            if ($value === 0) {
                continue;
            }
            $earningRows .= '<tr><td>' . $e($label) . '</td><td class="num">' . $m($value) . '</td></tr>';
        }

        $deductionRows = '';
        foreach ($deductions as $label => $value) {
            if ($value === 0) {
                continue;
            }
            $deductionRows .= '<tr><td>' . $e($label) . '</td><td class="num">' . $m($value) . '</td></tr>';
        }

        /* Manual lines, in the order the accountant entered them. */
        foreach ($items as $item) {
            $label = $item->label;
            $project = $item->project?->title;
            if ($project) {
                $label .= ' — ' . $project;
            }
            $row = '<tr><td>' . $e($label) . '</td><td class="num">' . $m((int) $item->amount) . '</td></tr>';
            if ($item->isEarning()) {
                $earningRows .= $row;
            } else {
                $deductionRows .= $row;
            }
        }

        $origin = null;
        if ($items->isNotEmpty()) {
            $origin = '<p class="doc-meta">اقلام دستی ' . $n((string) $items->count()) . ' ردیف است.</p>';
        }

        /* Project context: the count is required by the client; the total is
         * context only and is explicitly NOT part of the formula. Labelling it
         * keeps a reader from assuming it was multiplied by anything. */
        $projectsNote = '';
        $activeProjects = $employee?->projects_count;
        if ($activeProjects !== null && $activeProjects > 0) {
            $projectsNote = '<p class="doc-meta">تعداد پروژه‌های فعال این همکار: ' . $n((string) $activeProjects) . '</p>';
        }
        if ($payslip->projects_total > 0) {
            $projectsNote .= '<p class="doc-meta">جمع مبالغ پروژه‌ها (فقط برای اطلاع — در محاسبهٔ حقوق اثری ندارد): '
                . $m((int) $payslip->projects_total) . '</p>';
        }

        $net = (int) $payslip->net_payable;
        /* Computed BEFORE the heredoc. A method call inside `{$...}` is a parse
         * error — PHP interpolates only simple expressions there — so every
         * value that needs a call is resolved to a variable first. */
        $employeeName = $employee?->displayName() ?? '—';
        $employeeCode = $employee?->code ?? '—';
        $employeePosition = $employee?->position ?? '—';
        $workedDays = $n((string) $payslip->worked_days);
        $totalEarnings = $m((int) $payslip->total_earnings);
        $totalDeductions = $m((int) $payslip->total_deductions);
        $netLabel = $m($net);
        $netWords = \App\Support\Money::fromRial($net)->toPersianWords();
        $computedByName = $computedBy ?? '—';

        $computedBy = $payslip->computedBy?->name;
        $computedAt = $payslip->computed_at
            ? JalaliDate::fromCarbon($payslip->computed_at)->formatPersian()
                . ' — ' . $n($payslip->computed_at->timezone('Asia/Tehran')->format('H:i'))
            : '—';

        $isPublished = $payslip->period?->isPublished() ?? false;
        $state = $isPublished ? 'نهایی و منتشرشده' : 'پیش‌نویس — قابل تغییر';

        return <<<HTML
        <html dir="rtl"><body>
            <div class="doc-head">
                <h1>فیش حقوقی {$periodLabel}</h1>
                <p class="doc-meta">ویرا، سامانهٔ مدیریت ارتباط با مشتری — وضعیت: {$e($state)}</p>
            </div>

            <table>
                <tr><th style="width:30%">نام و نام خانوادگی</th><td>{$e($employeeName)}</td></tr>
                <tr><th>کد پرسنلی</th><td class="ltr">{$e($employeeCode)}</td></tr>
                <tr><th>سمت</th><td>{$e($employeePosition)}</td></tr>
                <tr><th>واحد</th><td>{$e($dept ?? '—')}</td></tr>
                <tr><th>دورهٔ حقوق</th><td>{$e($periodLabel)}</td></tr>
                <tr><th>روزهای کارکرد</th><td>{$workedDays}</td></tr>
            </table>

            <h2>دریافتی‌ها</h2>
            <table>
                <thead><tr><th>شرح</th><th class="num" style="width:35%">مبلغ (ریال)</th></tr></thead>
                <tbody>{$earningRows}</tbody>
                <tfoot><tr><td>جمع دریافتی‌ها</td><td class="num">{$totalEarnings}</td></tr></tfoot>
            </table>

            <h2>کسورات</h2>
            <table>
                <thead><tr><th>شرح</th><th class="num" style="width:35%">مبلغ (ریال)</th></tr></thead>
                <tbody>{$deductionRows}</tbody>
                <tfoot><tr><td>جمع کسورات</td><td class="num">{$totalDeductions}</td></tr></tfoot>
            </table>

            <p class="total">
                خالص پرداختی: {$netLabel} ریال
                <span class="muted" style="font-weight:normal">({$e($netWords)})</span>
            </p>

            {$origin}
            {$projectsNote}

            <p class="doc-meta">محاسبه‌شده توسط {$e($computedByName)} در {$computedAt}</p>

            <table class="sign">
                <tr>
                    <td>امضای پرسنل</td>
                    <td>امضای مدیر</td>
                </tr>
            </table>

            <div class="doc-foot">
                این فیش بر اساس فرمول تأییدشدهٔ «حقوق پایه + پاداش + اضافه‌کاری − مساعده − کسورات»
                محاسبه شده است. مبالغ به ریال است.
            </div>
        </body></html>
        HTML;
    }
}
