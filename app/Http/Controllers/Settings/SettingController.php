<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * §15 — the settings the client document says must be configurable.
 *
 * WHY THIS SCREEN DID NOT EXIST
 * -----------------------------
 * The `settings` table, the `Setting::get()/set()` model with its history
 * trail, and the `settings.view` / `settings.manage` permissions were all
 * already in the repository — and four call sites already *read* the values
 * (contract-expiry warning in the dashboard and contract list, invoice VAT
 * rate). What was missing was any way to change them: `Setting::set()` had
 * zero callers. So the product shipped "configurable" thresholds that only a
 * developer with database access could configure. That is not configurable.
 *
 * WHAT IS DELIBERATELY NOT EDITABLE HERE
 * --------------------------------------
 * Environment configuration and secrets — `APP_KEY`, `VIRA_CREDENTIAL_KEY`,
 * `VIRA_BACKUP_KEY`, database credentials, `APP_DEBUG` — are **not** settings
 * and must never appear on this screen. They belong in `.env` on the host,
 * where a compromise of the admin account does not also hand over the
 * encryption keys. This controller only ever touches rows in the `settings`
 * table, which is what keeps that boundary firm: there is no code path from
 * this screen to `config()` or the environment.
 *
 * ONLY SEEDED KEYS ARE EDITABLE
 * -----------------------------
 * The form renders the keys that exist in the table. It does not accept
 * arbitrary new keys, because an unvalidated key is how a typo becomes a
 * permanent orphan row and how a caller could overwrite something unintended.
 */
class SettingController extends Controller
{
    public function index(Request $request): View
    {
        $settings = Setting::query()
            ->orderBy('group')
            ->orderBy('id')
            ->get()
            ->groupBy('group');

        return view('settings.index', [
            'groups' => $settings,
            'canManage' => $request->user()->hasPermission('settings.manage'),
            // Recent changes, so a threshold that was moved last week can be
            // explained without reading the audit log.
            'history' => \App\Models\SettingsHistory::query()
                ->with('user:id,name,username')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /*
         * Keyed by primary key, NOT by setting key, and the form submits
         * `values[<id>]`. The setting keys contain dots — `contracts.expiry_
         * warning_days` — and Laravel reads a dot inside a rule attribute name
         * as nested array access, which cannot be escaped. Rules keyed
         * `values.contracts.expiry_warning_days` therefore describe a
         * three-level array while the form posts a two-level one, so every
         * field failed `required` and the save silently redirected back.
         */
        $settings = Setting::query()->get()->keyBy('id');

        // Build the rules from the stored rows themselves: each key is validated
        // according to the `type` the seeder declared for it, so an integer
        // setting cannot be set to text and vice versa.
        $rules = [];
        $labels = [];

        foreach ($settings as $id => $setting) {
            $rules['values.' . $id] = $this->rulesFor((string) $setting->type);
            $labels['values.' . $id] = (string) $setting->title;
        }

        $validated = $request->validate(
            ['values' => ['required', 'array']] + $rules,
            $this->messages(),
            $labels,
        );

        $userId = (int) $request->user()->id;
        $changed = 0;
        $changedKeys = [];

        foreach ($validated['values'] as $id => $value) {
            // Re-check membership: `validate` guarantees shape, not that the id
            // is one we own. Without this, `values[999]=x` would be accepted
            // and a crafted id could target a row the form never rendered.
            if (! $settings->has($id)) {
                continue;
            }

            $setting = $settings->get($id);
            $current = (string) $setting->value;

            if ((string) $value === $current) {
                continue; // no change, no history row, no audit event
            }

            Setting::set($setting->key, $value, $userId);
            $changedKeys[] = $setting->key;
            $changed++;
        }

        /*
         * Audited as one event, not one per key. Changing three thresholds in
         * one save is one administrative act; three audit rows would make the
         * log harder to read without adding information.
         */
        if ($changed > 0) {
            AuditRecorder::record(
                event: 'settings.updated',
                description: $changed . ' تنظیم به‌روزرسانی شد.',
                // The keys, never the values: a setting value could later hold
                // something sensitive, and settings_history already records old
                // and new values for anyone with the right to look.
                changes: ['keys' => $changedKeys, 'changed' => $changed],
                userId: $userId,
            );
        }

        return redirect()
            ->route('settings.index')
            ->with('success', $changed > 0
                ? $changed . ' تنظیم به‌روزرسانی شد.'
                : 'هیچ مقداری تغییر نکرد.');
    }

    /**
     * @return list<string>
     */
    private function rulesFor(string $type): array
    {
        return match ($type) {
            'integer' => ['required', 'integer', 'min:0', 'max:3650'],
            'boolean' => ['required', Rule::in(['0', '1', 'true', 'false'])],
            // A setting is a label or a threshold, not a document. Capping it
            // keeps a pasted paragraph out of a field rendered in a table cell.
            default => ['required', 'string', 'max:190'],
        };
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'values.required' => 'مقادیر تنظیمات ارسال نشد.',
            'values.array' => 'قالب مقادیر معتبر نیست.',
        ];
    }
}
