<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * REQ-15.01 — configurable settings (§15).
 *
 * Values are read through a request-scoped cache so a page that renders twenty
 * labels does not issue twenty queries. Writes go through `set()`, which keeps
 * `settings_history` so a changed threshold can be explained later.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'title', 'description'];

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$cache === null) {
            try {
                self::$cache = self::query()->pluck('value', 'key')->all();
            } catch (\Throwable) {
                // Settings may be read before migrations run (e.g. installer).
                self::$cache = [];
            }
        }

        if (! array_key_exists($key, self::$cache)) {
            return $default;
        }

        return self::cast(self::$cache[$key]);
    }

    public static function set(string $key, mixed $value, ?int $userId = null): void
    {
        $record = self::query()->firstOrNew(['key' => $key]);
        $old = $record->exists ? $record->value : null;

        $record->value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
        $record->fill(['type' => $record->type ?? 'string']);
        $record->save();

        SettingsHistory::create([
            'key' => $key,
            'old_value' => $old,
            'new_value' => $record->value,
            'changed_by' => $userId,
        ]);

        self::$cache = null;
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    private static function cast(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return match (mb_strtolower($value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }
}
