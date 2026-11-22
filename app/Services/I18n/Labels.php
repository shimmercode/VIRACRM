<?php

namespace App\Services\I18n;

/**
 * Persian labels for enum values that appear in the interface and in PDFs.
 *
 * WHY A CLASS AND NOT lang/*.php: these strings are contractual. They appear on
 * payslips and contracts the client prints and signs, so they are versioned
 * with the code and covered by tests rather than left to translation files that
 * could drift. User-facing chrome still uses Laravel's translator.
 */
final class Labels
{
    /** @var array<string, string> */
    private const MAP = [
        // ── Customer ──────────────────────────────────────────────
        'customer.type.natural' => 'حقیقی',
        'customer.type.legal' => 'حقوقی',
        'customer.status.lead' => 'سرنخ',
        'customer.status.prospect' => 'در مذاکره',
        'customer.status.active' => 'مشتری فعال',
        'customer.status.inactive' => 'غیرفعال',
        'customer.status.archived' => 'بایگانی',

        // ── Contract ──────────────────────────────────────────────
        'contract.status.draft' => 'پیش‌نویس',
        'contract.status.active' => 'فعال',
        'contract.status.expiring' => 'در آستانه انقضا',
        'contract.status.expired' => 'پایان‌یافته',
        'contract.status.renewed' => 'تمدیدشده',
        'contract.status.cancelled' => 'لغوشده',

        // ── Project ───────────────────────────────────────────────
        'project.type.seo' => 'سئو',
        'project.type.design' => 'طراحی سایت',
        'project.status.draft' => 'پیش‌نویس',
        'project.status.active' => 'فعال',
        'project.status.paused' => 'متوقف',
        'project.status.completed' => 'تکمیل‌شده',
        'project.status.cancelled' => 'لغوشده',

        // ── Work item (invoice / report reminder) ─────────────────
        'work_type.invoice' => 'فاکتور',
        'work_type.report' => 'گزارش',
        'work_state.pending' => 'در انتظار',
        'work_state.done' => 'انجام‌شده',
        'work_state.overdue' => 'عقب‌افتاده',

        // ── Reminder ──────────────────────────────────────────────
        'reminder.kind.due_soon' => 'نزدیک موعد',
        'reminder.kind.due_today' => 'موعد امروز',
        'reminder.kind.overdue' => 'عقب‌افتاده',

        // ── Credential ────────────────────────────────────────────
        'credential.type.wordpress' => 'وردپرس',
        'credential.type.host' => 'هاست',
        'credential.type.domain' => 'ثبت دامنه',
        'credential.type.search_console' => 'گوگل سرچ کنسول',
        'credential.type.analytics' => 'گوگل آنالیتیکس',
        'credential.type.custom' => 'سفارشی',

        // ── Personnel ─────────────────────────────────────────────
        'personnel.status.active' => 'شاغل',
        'personnel.status.suspended' => 'تعلیق',
        'personnel.status.terminated' => 'پایان همکاری',
        'salary_method.fixed' => 'ثابت',
        'salary_method.project_based' => 'پروژه‌ای',
        'salary_method.hybrid' => 'ترکیبی',

        // ── Note ──────────────────────────────────────────────────
        'note.priority.low' => 'کم',
        'note.priority.normal' => 'معمول',
        'note.priority.high' => 'زیاد',
        'note.priority.critical' => 'بحرانی',
        'note.status.open' => 'باز',
        'note.status.done' => 'انجام‌شده',

        // ── Priority (client document §13) ────────────────────────
        'priority.very_high' => 'بسیار بالا',
        'priority.high' => 'بالا',
        'priority.extra' => 'تکمیلی',

        // ── Permission areas (client document §10) ────────────────
        'area.customers' => 'مشتریان',
        'area.contracts' => 'قراردادها',
        'area.projects' => 'پروژه‌ها',
        'area.credentials' => 'دسترسی‌های پروژه',
        'area.personnel' => 'پرسنل',
        'area.payroll' => 'حقوق و دستمزد',
        'area.notes' => 'یادداشت‌ها',
        'area.reports' => 'گزارش‌ها',
        'area.users' => 'کاربران',
        'area.settings' => 'تنظیمات',
        'area.audit' => 'گزارش رویدادها',
    ];

    public static function get(string $key, ?string $default = null): string
    {
        return self::MAP[$key] ?? $default ?? $key;
    }

    public static function has(string $key): bool
    {
        return isset(self::MAP[$key]);
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::MAP;
    }
}
