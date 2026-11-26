<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data integrity (§2.4) — CHECK constraints on the `type` / `status` / `state`
 * columns whose domain is defined in the models.
 *
 * WHY THE DATABASE NEEDS THIS WHEN THE MODELS ALREADY CHECK
 * ---------------------------------------------------------
 * `HasDomainConstraints` covers every write that goes through Eloquent. It
 * cannot cover a raw `INSERT`, a bulk import, a DBA running SQL by hand, or a
 * future API that writes with the query builder. Only the database can. The two
 * layers are complementary, not redundant: the model gives a clear PHP error
 * naming the column and the allowed values, the constraint catches what never
 * reaches PHP at all.
 *
 * THE ENGINE REALITY, STATED PLAINLY
 * ----------------------------------
 * MySQL 8.0.16+ and MariaDB 10.2.1+ support `ALTER TABLE ... ADD CONSTRAINT
 * ... CHECK`. **SQLite does not support adding a CHECK constraint to an
 * existing table at all** — the only route is a full table rebuild, which is
 * not a safe thing to do to a live database for a defence-in-depth measure. So
 * on SQLite this migration is a deliberate no-op and the model guard is the
 * enforcement. That asymmetry is why the model guard exists in the first place:
 * without it, tests on SQLite would accept writes that production rejects.
 *
 * SAFETY
 * ------
 * Adding a CHECK to a table holding an out-of-domain value fails, and failing
 * mid-migration on a production host is a bad outcome. So every value is
 * verified BEFORE any constraint is added, and the migration aborts with the
 * offending rows named rather than leaving the schema half-constrained.
 */
return new class extends Migration
{
    /**
     * table => [column => allowed values]
     *
     * Kept in one place so the migration, and anyone reading it, can see the
     * whole domain at a glance. These values mirror the model constants and the
     * `DomainConstraintsTest` suite pins that they match.
     *
     * @var array<string, array<string, list<string>>>
     */
    private array $domains = [
        'customers' => [
            'type' => ['natural', 'legal'],
            'status' => ['lead', 'prospect', 'active', 'inactive', 'archived'],
        ],
        'contracts' => [
            'status' => ['draft', 'active', 'expiring', 'expired', 'renewed', 'cancelled'],
        ],
        'projects' => [
            'type' => ['seo', 'design'],
            'status' => ['draft', 'active', 'paused', 'completed', 'cancelled'],
        ],
        'work_items' => [
            'kind' => ['invoice', 'report'],
            'state' => ['pending', 'done', 'cancelled'],
        ],
        'notes' => [
            'status' => ['open', 'done', 'cancelled'],
        ],
        'employees' => [
            'status' => ['active', 'on_leave', 'suspended', 'terminated'],
        ],
        'invoices' => [
            'status' => ['draft', 'issued', 'void'],
            'payment_state' => ['unpaid', 'partial', 'paid'],
        ],
        'journal_entries' => [
            'status' => ['draft', 'posted', 'reversed'],
            'source' => ['invoice', 'payment', 'payroll', 'expense', 'opening', 'manual'],
        ],
        'fiscal_periods' => [
            'state' => ['open', 'closed'],
        ],
        'payroll_periods' => [
            'state' => ['draft', 'computed', 'published'],
        ],
        'project_keywords' => [
            'status' => ['pending', 'working', 'done', 'removed'],
        ],
        'ledger_accounts' => [
            'type' => ['asset', 'liability', 'equity', 'revenue', 'expense'],
        ],
        'seo_project_details' => [
            'recurrence' => ['monthly', 'quarterly', 'semiannual', 'yearly'],
        ],
    ];

    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // Not a silent skip: the reason is recorded so nobody later assumes
            // SQLite databases are constrained too.
            DB::connection()->statement(
                "CREATE TABLE IF NOT EXISTS domain_constraint_note (
                    note TEXT NOT NULL,
                    created_at TEXT NOT NULL
                )"
            );

            DB::connection()->statement(
                'INSERT INTO domain_constraint_note (note, created_at) VALUES (?, ?)',
                [
                    'SQLite cannot ADD a CHECK constraint to an existing table; '
                    . 'domain values are enforced by App\\Models\\Concerns\\HasDomainConstraints.',
                    date('Y-m-d H:i:s'),
                ]
            );

            return;
        }

        if (! $this->supportsCheckConstraints()) {
            return;
        }

        $this->assertDataIsClean();
        $this->addConstraints();
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::dropIfExists('domain_constraint_note');

            return;
        }

        if (! $this->supportsCheckConstraints()) {
            return;
        }

        foreach ($this->domains as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($columns) as $column) {
                $name = $this->constraintName($table, $column);

                try {
                    DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
                } catch (\Throwable) {
                    // Already absent, or the engine names constraints differently.
                    // Dropping is best-effort on rollback by design.
                }
            }
        }
    }

    /**
     * MySQL enforced CHECK constraints from 8.0.16; before that it parsed and
     * ignored them, which is worse than absent because it looks protected.
     * MariaDB enforced them from 10.2.1.
     */
    private function supportsCheckConstraints(): bool
    {
        $version = DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);

        if (stripos($version, 'MariaDB') !== false) {
            return version_compare($this->numericVersion($version), '10.2.1', '>=');
        }

        return version_compare($this->numericVersion($version), '8.0.16', '>=');
    }

    private function numericVersion(string $version): string
    {
        preg_match('/(\d+)\.(\d+)\.(\d+)/', $version, $m);

        return ($m[1] ?? '0') . '.' . ($m[2] ?? '0') . '.' . ($m[3] ?? '0');
    }

    /**
     * Verify every existing row before adding anything, so the migration cannot
     * fail halfway and leave the schema partially constrained.
     */
    private function assertDataIsClean(): void
    {
        $problems = [];

        foreach ($this->domains as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column => $allowed) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $placeholders = implode(',', array_fill(0, count($allowed), '?'));

                $bad = DB::table($table)
                    ->whereNotNull($column)
                    ->whereNotIn($column, $allowed)
                    ->limit(5)
                    ->pluck($column)
                    ->all();

                if ($bad !== []) {
                    $problems[] = sprintf(
                        '%s.%s holds out-of-domain value(s): %s (allowed: %s)',
                        $table,
                        $column,
                        implode(', ', array_unique(array_map('strval', $bad))),
                        implode(', ', $allowed),
                    );
                }

                unset($placeholders);
            }
        }

        if ($problems !== []) {
            throw new RuntimeException(
                "Refusing to add CHECK constraints — existing data violates the domain:\n  - "
                . implode("\n  - ", $problems)
                . "\nFix or archive those rows first, then re-run the migration."
            );
        }
    }

    private function addConstraints(): void
    {
        foreach ($this->domains as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column => $allowed) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                $name = $this->constraintName($table, $column);

                if ($this->constraintExists($name)) {
                    continue;
                }

                $list = implode(',', array_map(
                    static fn ($v) => "'" . str_replace("'", "''", $v) . "'",
                    $allowed,
                ));

                // NULL stays allowed: whether a column is nullable is the
                // schema's decision, and a CHECK that rejected NULL would turn
                // every optional status into a required one.
                DB::statement(
                    "ALTER TABLE {$table} ADD CONSTRAINT {$name} "
                    . "CHECK ({$column} IS NULL OR {$column} IN ({$list}))"
                );
            }
        }
    }

    private function constraintName(string $table, string $column): string
    {
        return "chk_{$table}_{$column}";
    }

    private function constraintExists(string $name): bool
    {
        try {
            $rows = DB::select(
                'select constraint_name from information_schema.table_constraints '
                . 'where constraint_schema = database() and constraint_name = ?',
                [$name]
            );

            return $rows !== [];
        } catch (\Throwable) {
            return false;
        }
    }
};
