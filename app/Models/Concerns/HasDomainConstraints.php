<?php

namespace App\Models\Concerns;

use DomainException;

/**
 * Rejects out-of-domain `type` / `status` / `state` values on write.
 *
 * THE DEFECT THIS CLOSES
 * ----------------------
 * These columns are PHP-backed enums or `const` arrays, and nothing in the
 * database constrains them. That was not theoretical: seeding `type='real'`
 * (the enum is `natural|legal`) and `status='completed'` (not a
 * `ContractStatus`) both **wrote successfully** and only failed later, when a
 * view cast the value — surfacing as an HTTP 500 on a list page. A bad value
 * can therefore sit in the database for days before anything notices, and the
 * page that eventually renders it breaks for everyone.
 *
 * The FormRequests already validate with `Rule::in`, so the UI path was safe.
 * What was not safe is every other path: `Model::create()` in a console
 * command, a seeder, an import, a queued job, or a future API. This trait
 * covers all of them at once, in one place, identically on SQLite and MySQL.
 *
 * WHY NOT ONLY A DATABASE CHECK
 * -----------------------------
 * SQLite cannot add a CHECK constraint to an existing table at all — the only
 * route is a full table rebuild. MySQL 8.0.16+ and MariaDB 10.2+ can, and the
 * companion migration does exactly that where the engine supports it. But a
 * constraint that exists on one engine and not the other means tests running
 * on SQLite would not catch what production rejects, so enforcement lives here
 * too. The two layers are deliberate: this one gives a clear PHP error with
 * the column name and the allowed values; the database one catches writes that
 * never touch Eloquent.
 *
 * WHAT IS DELIBERATELY NOT DONE
 * -----------------------------
 * No business rule is invented here. The allowed values come from the model's
 * own `STATUSES` / `TYPES` / `KINDS` constants — the same source the
 * FormRequests and the UI already read — so this cannot drift into a second,
 * contradictory definition of what a valid status is.
 */
trait HasDomainConstraints
{
    /**
     * Map of column => the constant holding its allowed values.
     *
     * Declared per model so a model only constrains the columns it owns.
     *
     * @return array<string, string>
     */
    abstract protected function domainColumns(): array;

    /**
     * Map of column => backed-enum class, for models that express their domain
     * as an enum rather than a constant array (`Customer`, `Contract`).
     *
     * Both forms are supported because the codebase genuinely uses both, and
     * forcing one shape would mean rewriting working models to satisfy a guard.
     *
     * @return array<string, class-string<\BackedEnum>>
     */
    protected function domainEnums(): array
    {
        return [];
    }

    public static function bootHasDomainConstraints(): void
    {
        static::saving(function (self $model): void {
            $model->assertDomainValues();
        });
    }

    /**
     * @throws DomainException when a dirty attribute holds an unknown value
     */
    protected function assertDomainValues(): void
    {
        $domains = [];

        foreach ($this->domainColumns() as $column => $constant) {
            /*
             * `constant()` is a global function, not a model method — writing
             * `static::constant(...)` here produced "Call to undefined method"
             * on the first const-backed model. The FQN form also silences any
             * namespaced-function fallback.
             */
            $allowed = \constant(static::class . '::' . $constant);
            $domains[$column] = is_array($allowed) ? array_keys($allowed) : [];
        }

        foreach ($this->domainEnums() as $column => $enum) {
            $domains[$column] = array_map(
                static fn (\BackedEnum $case) => $case->value,
                $enum::cases(),
            );
        }

        foreach ($domains as $column => $allowed) {
            // Only what is being written. Checking clean attributes would make
            // an unrelated save fail because of pre-existing bad data, which
            // turns this guard into a trap for the person fixing that data.
            if (! $this->isDirty($column)) {
                continue;
            }

            $value = $this->getAttribute($column);

            // NULL is allowed where the column is nullable; the schema decides
            // that, not this trait.
            if ($value === null) {
                continue;
            }

            // A backed enum instance is already valid by construction.
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            }

            if (! in_array((string) $value, array_map('strval', $allowed), true)) {
                throw new DomainException(sprintf(
                    '%s.%s: "%s" is not a valid value. Allowed: %s.',
                    static::class,
                    $column,
                    (string) $value,
                    implode(', ', array_map('strval', $allowed)),
                ));
            }
        }
    }
}
