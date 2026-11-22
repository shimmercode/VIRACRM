<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * REQ-15.01 — configurable settings.
 *
 * §2 and §15 require the expiry warning thresholds to be adjustable without
 * code changes, so they are seeded here and read via Setting::get().
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'contracts.expiry_warning_days', 'value' => '30', 'type' => 'integer',
             'group' => 'contracts', 'title' => 'آستانهٔ هشدار انقضای قرارداد (روز)',
             'description' => 'قراردادهایی که تا این تعداد روز آینده پایان می‌یابند هشدار می‌گیرند.'],

            ['key' => 'general.company_name', 'value' => 'ویرا آژانس', 'type' => 'string',
             'group' => 'general', 'title' => 'نام شرکت',
             'description' => 'در سربرگ قراردادها و پیام‌ها استفاده می‌شود.'],

            ['key' => 'reminders.invoice_offset_days', 'value' => '2', 'type' => 'integer',
             'group' => 'reminders', 'title' => 'یادآوری پیش از موعد صورت‌حساب (روز)',
             'description' => 'تعداد روز پیش از سررسید که یادآوری ارسال می‌شود.'],

            ['key' => 'calendar.display', 'value' => 'jalali', 'type' => 'string',
             'group' => 'general', 'title' => 'تقویم نمایشی',
             'description' => 'شمسی یا میلادی؛ ذخیره‌سازی همیشه میلادی است.'],
        ];

        foreach ($settings as $setting) {
            // firstOrCreate, not updateOrCreate: an administrator who changed a
            // threshold must not have it reset by a deploy that re-runs seeders.
            Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }

        Setting::forget();

        $this->command?->info('Settings: ' . count($settings) . ' ensured.');
    }
}
