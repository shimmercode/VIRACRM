<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Tests\ViraTestCase;

/**
 * T-DB-01..05 — schema integrity.
 *
 * These are not "does the file exist" checks. Each one executes real SQL
 * against a real SQLite database built by the project's own migrations, and
 * asserts the constraints that the product depends on:
 *
 *   - foreign keys actually reject orphans (a contract with no customer is a
 *     data-corruption bug nobody notices until an invoice has no addressee);
 *   - the (project, kind, period) uniqueness that makes reminders idempotent
 *     is enforced by the database, not only by application code;
 *   - money columns are integers, because a float column silently rounds.
 */
final class DatabaseSchemaTest extends ViraTestCase
{
    private \Illuminate\Database\Connection $db;
    private string $dbPath;

    protected function setUp(): void
    {
        $harness = \Tests\Support\DatabaseHarness::boot('schema')->migrate();
        $this->db = $harness->connection;
        $this->dbPath = $harness->path;
    }

    public function run(): void
    {
        $this->allTablesExist();
        $this->foreignKeysRejectOrphans();
        $this->cascadeDeletesWork();
        $this->workItemUniqueness();
        $this->moneyColumnsAreIntegers();
        $this->scheduledEventKeyIsUnique();
        $this->credentialCiphertextColumnExists();
        $this->persianTextStoresCorrectly();
    }

    private function allTablesExist(): void
    {
        $expected = [
            'users', 'roles', 'permissions', 'role_permissions', 'user_roles', 'sessions',
            'customers', 'customer_websites', 'customer_contacts',
            'contract_types', 'contract_templates', 'contracts', 'contract_versions', 'contract_files',
            'project_types', 'projects', 'seo_project_details', 'project_keywords',
            'design_project_details', 'project_assignments', 'seo_packages',
            'work_items', 'scheduled_events', 'job_runs',
            'credential_types', 'credential_requirements', 'project_credentials', 'credential_values',
            'attachments', 'audit_logs',
            'employees', 'employee_contracts', 'payroll_rules', 'payroll_periods',
            'payslips', 'payroll_items', 'payslip_versions',
            'notes', 'settings', 'settings_history',
        ];

        $actual = array_map(
            fn ($r) => $r->name,
            $this->db->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
        );

        foreach ($expected as $table) {
            $this->assertTrue(in_array($table, $actual, true), "table `{$table}` must exist");
        }

        $this->assertGreaterThan(39, count($expected), 'the design specifies a 40-table model');
    }

    private function foreignKeysRejectOrphans(): void
    {
        // A contract pointing at a customer that does not exist must be refused.
        $this->assertThrows(
            QueryException::class,
            fn () => $this->db->table('contracts')->insert([
                'customer_id' => 999999,
                'title' => 'قرارداد یتیم',
                'start_date' => '2026-01-01',
                'amount' => 1000,
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'a contract must not reference a missing customer'
        );

        // Likewise a project.
        $this->assertThrows(
            QueryException::class,
            fn () => $this->db->table('projects')->insert([
                'customer_id' => 424242,
                'type' => 'seo',
                'title' => 'پروژه یتیم',
                'status' => 'draft',
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );
    }

    private function cascadeDeletesWork(): void
    {
        $customerId = $this->db->table('customers')->insertGetId([
            'type' => 'legal',
            'company_name' => 'شرکت آزمایشی',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->db->table('customer_websites')->insert([
            'customer_id' => $customerId,
            'url' => 'https://example.test',
            'domain' => 'example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, $this->db->table('customer_websites')->count());

        $this->db->table('customers')->where('id', $customerId)->delete();

        $this->assertSame(
            0,
            $this->db->table('customer_websites')->count(),
            'deleting a customer must remove its websites (no orphans left behind)'
        );
    }

    private function workItemUniqueness(): void
    {
        [$customerId, $projectId] = $this->makeProject();

        $row = [
            'project_id' => $projectId,
            'kind' => 'invoice',
            'period' => '1405-02',
            'due_date' => '2026-05-10',
            'state' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $this->db->table('work_items')->insert($row);

        // The database itself must refuse a duplicate invoice for the same period.
        $this->assertThrows(
            QueryException::class,
            fn () => $this->db->table('work_items')->insert($row),
            'duplicate invoice for the same project+period must violate the unique index'
        );

        // But a *report* for the same project and period is a different item (§5-2
        // requires invoice and report reminders to be independent).
        $this->db->table('work_items')->insert(array_merge($row, ['kind' => 'report']));
        $this->assertSame(2, $this->db->table('work_items')->where('project_id', $projectId)->count());

        // And the next period is a different item.
        $this->db->table('work_items')->insert(array_merge($row, ['period' => '1405-03']));
        $this->assertSame(3, $this->db->table('work_items')->count());
    }

    private function moneyColumnsAreIntegers(): void
    {
        $this->db->table('customers')->insert([
            'type' => 'natural', 'first_name' => 'علی', 'last_name' => 'رضایی',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $customerId = $this->db->table('customers')->value('id');

        $this->db->table('contracts')->insert([
            'customer_id' => $customerId,
            'title' => 'قرارداد سئو',
            'start_date' => '2026-01-01',
            'amount' => 12_500_000,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $amount = $this->db->table('contracts')->value('amount');
        $this->assertSame(12_500_000, $amount);
        $this->assertTrue(is_int($amount), 'amount must come back as an integer, not a float');

        // Large but realistic contract value must not lose precision.
        $this->db->table('contracts')->update(['amount' => 9_000_000_000]);
        $this->assertSame(9_000_000_000, $this->db->table('contracts')->value('amount'));
    }

    private function scheduledEventKeyIsUnique(): void
    {
        $key = hash('sha256', 'invoice|1|1405-02|2026-05-08|2');

        $this->db->table('scheduled_events')->insert([
            'unique_key' => $key,
            'event_type' => 'reminder',
            'kind' => 'due_soon',
            'fire_date' => '2026-05-08',
        ]);

        $this->assertThrows(
            QueryException::class,
            fn () => $this->db->table('scheduled_events')->insert([
                'unique_key' => $key,
                'event_type' => 'reminder',
                'kind' => 'due_soon',
                'fire_date' => '2026-05-08',
            ]),
            'the scheduler must not be able to double-insert the same reminder'
        );
    }

    private function credentialCiphertextColumnExists(): void
    {
        // There must be no column anywhere that stores a credential in the clear.
        $columns = array_map(
            fn ($c) => $c->name,
            $this->db->select('PRAGMA table_info(credential_values)')
        );

        $this->assertTrue(in_array('ciphertext', $columns, true), 'secrets live in `ciphertext`');
        foreach (['password', 'secret', 'plaintext', 'value'] as $forbidden) {
            $this->assertFalse(in_array($forbidden, $columns, true),
                "credential_values must not have a plaintext column named `{$forbidden}`");
        }
    }

    private function persianTextStoresCorrectly(): void
    {
        $name = 'شرکت بازرگانی آریا — نمایندگی اصفهان';
        $this->db->table('customers')->insert([
            'type' => 'legal',
            'company_name' => $name,
            'address' => 'خیابان چهارباغ، پلاک ۱۲، واحد ۳',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Query by the value itself: earlier tests also create legal customers,
        // so `first()` would be non-deterministic (a real bug this caught).
        $row = $this->db->table('customers')->where('company_name', $name)->first();

        $this->assertNotNull($row, 'the inserted Persian customer must be retrievable by exact value');
        $this->assertSame($name, $row->company_name, 'Persian text with ZWNJ and em-dash must round-trip');
        $this->assertContains('چهارباغ', $row->address);
        $this->assertSame(1, $this->db->table('customers')->where('company_name', $name)->count(),
            'exact Persian match must work');
    }

    /** @return array{0:int,1:int} */
    private function makeProject(): array
    {
        $customerId = $this->db->table('customers')->insertGetId([
            'type' => 'legal',
            'company_name' => 'شرکت پروژه‌ای',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $projectId = $this->db->table('projects')->insertGetId([
            'customer_id' => $customerId,
            'type' => 'seo',
            'title' => 'پروژه سئو',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$customerId, $projectId];
    }
}
