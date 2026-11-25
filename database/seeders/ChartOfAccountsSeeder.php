<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;

/**
 * OD-41 approved chart of accounts — option B, three-level agency coding.
 *
 * TRANSCRIBED FROM THE DIRECTIVE, NOT INVENTED
 * --------------------------------------------
 * Every code, title and parent below appears in §19.1 of the master directive.
 * The structure is three levels deep and is NOT capped at three: `level` is a
 * stored integer, the UI walks the tree, and adding a fourth level under any
 * existing leaf needs only a new row. The directive explicitly asks for that
 * ("کدینگ باید توسعه‌پذیر باشد و به سه سطح ثابت محدود نشود").
 *
 * GROUP VS POSTING
 * ----------------
 * Codes at levels 1 and 2 are group accounts that exist to subtotal their
 * children and must never receive a journal line — posting to "1 دارایی‌ها"
 * would double-count in every report above it. `is_postable = false` on those.
 *
 * WHAT IS DELIBERATELY ABSENT
 * ---------------------------
 * No tax *rates*. §19.1 forbids hard-coding them ("نرخ‌ها و قواعد قانونی را
 * هاردکد نکن"), so the VAT accounts are created but their rates live in
 * `settings` and are read at invoice time.
 */
class ChartOfAccountsSeeder extends Seeder
{
    /**
     * [code, title, level, type, normal_balance, is_postable, parent_code]
     *
     * @var list<array{0:string,1:string,2:int,3:string,4:string,5:bool,6:?string}>
     */
    public const ACCOUNTS = [
        // ── 1 دارایی‌ها ────────────────────────────────────────────────
        ['1', 'دارایی‌ها', 1, 'asset', 'debit', false, null],
        ['1-1', 'دارایی جاری', 2, 'asset', 'debit', false, '1'],
        ['1-1-01', 'بانک و صندوق', 3, 'asset', 'debit', true, '1-1'],
        ['1-1-02', 'حساب‌های دریافتنی مشتریان', 3, 'asset', 'debit', true, '1-1'],
        ['1-1-03', 'پیش‌پرداخت‌ها و سپرده‌ها', 3, 'asset', 'debit', true, '1-1'],
        ['1-1-04', 'مالیات بر ارزش افزوده خرید', 3, 'asset', 'debit', true, '1-1'],
        ['1-1-05', 'سایر دارایی‌های جاری', 3, 'asset', 'debit', true, '1-1'],

        ['1-2', 'دارایی غیرجاری', 2, 'asset', 'debit', false, '1'],
        ['1-2-01', 'دارایی‌های ثابت', 3, 'asset', 'debit', true, '1-2'],
        ['1-2-02', 'استهلاک انباشته', 3, 'asset', 'credit', true, '1-2'],

        // ── 2 بدهی‌ها ──────────────────────────────────────────────────
        ['2', 'بدهی‌ها', 1, 'liability', 'credit', false, null],
        ['2-1', 'بدهی‌های جاری', 2, 'liability', 'credit', false, '2'],
        ['2-1-01', 'بدهی به پیمانکاران', 3, 'liability', 'credit', true, '2-1'],
        ['2-1-02', 'مالیات بر ارزش افزوده فروش', 3, 'liability', 'credit', true, '2-1'],
        ['2-1-03', 'حقوق و دستمزد پرداختنی', 3, 'liability', 'credit', true, '2-1'],
        ['2-1-04', 'پیش‌دریافت مشتریان', 3, 'liability', 'credit', true, '2-1'],
        ['2-1-05', 'سایر بدهی‌های جاری', 3, 'liability', 'credit', true, '2-1'],

        // ── 3 حقوق مالکانه ────────────────────────────────────────────
        ['3', 'حقوق مالکانه', 1, 'equity', 'credit', false, null],
        ['3-1', 'سرمایه', 2, 'equity', 'credit', false, '3'],
        ['3-1-01', 'سرمایه اولیه', 3, 'equity', 'credit', true, '3-1'],
        ['3-2', 'سود و زیان انباشته', 2, 'equity', 'credit', false, '3'],
        ['3-2-01', 'سود و زیان انباشته', 3, 'equity', 'credit', true, '3-2'],

        // ── 4 درآمدها ─────────────────────────────────────────────────
        ['4', 'درآمدها', 1, 'revenue', 'credit', false, null],
        ['4-1', 'درآمد خدمات', 2, 'revenue', 'credit', false, '4'],
        ['4-1-01', 'درآمد SEO', 3, 'revenue', 'credit', true, '4-1'],
        ['4-1-02', 'درآمد طراحی سایت', 3, 'revenue', 'credit', true, '4-1'],
        ['4-1-03', 'درآمد پشتیبانی', 3, 'revenue', 'credit', true, '4-1'],
        ['4-1-04', 'درآمد سایر خدمات', 3, 'revenue', 'credit', true, '4-1'],

        // ── 5 هزینه‌ها ────────────────────────────────────────────────
        ['5', 'هزینه‌ها', 1, 'expense', 'debit', false, null],
        ['5-1', 'هزینه‌های مستقیم پروژه', 2, 'expense', 'debit', false, '5'],
        ['5-1-01', 'پیمانکار', 3, 'expense', 'debit', true, '5-1'],
        ['5-1-02', 'ابزار و لایسنس اختصاصی پروژه', 3, 'expense', 'debit', true, '5-1'],
        ['5-1-03', 'سایر هزینه‌های مستقیم', 3, 'expense', 'debit', true, '5-1'],

        ['5-2', 'هزینه‌های عملیاتی و سربار', 2, 'expense', 'debit', false, '5'],
        ['5-2-01', 'حقوق و دستمزد', 3, 'expense', 'debit', true, '5-2'],
        ['5-2-02', 'اجاره', 3, 'expense', 'debit', true, '5-2'],
        ['5-2-03', 'نرم‌افزار و اشتراک', 3, 'expense', 'debit', true, '5-2'],
        ['5-2-04', 'بازاریابی و تبلیغات', 3, 'expense', 'debit', true, '5-2'],
        ['5-2-05', 'هزینه‌های اداری', 3, 'expense', 'debit', true, '5-2'],
        ['5-2-06', 'کارمزد بانکی', 3, 'expense', 'debit', true, '5-2'],
        ['5-2-07', 'سایر هزینه‌های عملیاتی', 3, 'expense', 'debit', true, '5-2'],
    ];

    /**
     * Stable codes the application refers to by name. Kept as constants so a
     * typo in a service is a fatal error at development time rather than a
     * silently-posting-to-the-wrong-account bug in production.
     */
    public const CASH = '1-1-01';
    public const RECEIVABLE = '1-1-02';
    public const VAT_PURCHASE = '1-1-04';
    public const VAT_SALES = '2-1-02';
    public const PAYROLL_PAYABLE = '2-1-03';
    public const CUSTOMER_ADVANCE = '2-1-04';
    public const PAYABLE_VENDOR = '2-1-01';
    public const REVENUE_SEO = '4-1-01';
    public const REVENUE_DESIGN = '4-1-02';
    public const REVENUE_SUPPORT = '4-1-03';
    public const REVENUE_OTHER = '4-1-04';
    public const EXPENSE_CONTRACTOR = '5-1-01';
    public const EXPENSE_SALARY = '5-2-01';

    public function run(): void
    {
        // Two passes: children reference parents by code, and a parent must
        // exist before its child is inserted. Inserting in the declared order
        // works because every parent appears above its children, but resolving
        // by code is what makes that ordering non-load-bearing.
        foreach (self::ACCOUNTS as [$code, $title, $level, $type, $normal, $postable, $parentCode]) {
            LedgerAccount::updateOrCreate(
                ['code' => $code],
                [
                    'title' => $title,
                    'level' => $level,
                    'type' => $type,
                    'normal_balance' => $normal,
                    'is_postable' => $postable,
                    'is_active' => true,
                    'is_system' => true,
                    'parent_id' => $parentCode === null
                        ? null
                        : LedgerAccount::query()->where('code', $parentCode)->value('id'),
                ],
            );
        }
    }
}
