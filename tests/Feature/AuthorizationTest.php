<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Security\AuditRecorder;
use App\Services\Security\PermissionCatalog;
use Illuminate\Support\Facades\Hash;
use Tests\Support\DatabaseHarness;
use Tests\ViraTestCase;

/**
 * REQ-10.01–10.04 — authorization and audit.
 *
 * These are integration tests against a real SQLite schema and the real Eloquent
 * models, not mocks. They do not exercise HTTP; the middleware path is proven
 * separately by tools/checks/authz-flow.mjs, which drives the actual kernel.
 */
class AuthorizationTest extends ViraTestCase
{
    private DatabaseHarness $harness;

    /**
     * Each scenario gets a fresh database. migrate() is an instance method that
     * returns the harness, so the two calls chain; a shared database would let
     * one scenario's permissions leak into the next.
     */
    private function boot(string $name): void
    {
        $this->harness = DatabaseHarness::boot($name)->migrate();
    }

    private function seedPermissions(): void
    {
        foreach (PermissionCatalog::all() as $permission) {
            Permission::updateOrCreate(['code' => $permission['code']], $permission);
        }
    }

    private function makeUser(string $username, bool $superadmin = false): User
    {
        return User::create([
            'name' => $username,
            'username' => $username,
            'password' => Hash::make('Secret-Passw0rd-123'),
            'is_superadmin' => $superadmin,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    /**
     * The shim has one entry point, so scenarios are named methods called in
     * order. Each boots its own database, so one failure cannot poison the next.
     */
    public function run(): void
    {
        $this->permissionCatalogueIsComplete();
        $this->permissionsAreDeniedByDefault();
        $this->roleGrantsExactlyItsPermissions();
        $this->deactivatedUserHoldsNoPermissions();
        $this->lockedAccountIsBlocked();
        $this->auditLogRedactsSecrets();
        $this->auditDiffReportsOnlyRealChanges();
        $this->auditRecorderPersistsAnEvent();
        $this->passwordIsNeverWrittenToTheAuditLog();
    }

    private function permissionCatalogueIsComplete(): void
    {
        $this->boot('auth1');

        $all = PermissionCatalog::all();
        $this->assertGreaterThan(30, count($all), 'catalogue should cover every documented area');

        $codes = array_column($all, 'code');
        $this->assertCount(count($codes), array_unique($codes), 'permission codes must be unique');

        foreach ($all as $permission) {
            $this->assertSame(
                $permission['area'] . '.' . $permission['action'],
                $permission['code'],
                'code must be area.action'
            );
            $this->assertNotSame('', $permission['title'], 'every permission needs a Persian title');
        }

        // §10 requires financial and password access to be separately grantable.
        $this->assertTrue(PermissionCatalog::isSensitive('contracts.view_financials'));
        $this->assertTrue(PermissionCatalog::isSensitive('credentials.reveal'));
        $this->assertTrue(PermissionCatalog::isSensitive('payroll.view'));
        $this->assertFalse(PermissionCatalog::isSensitive('customers.view'));
    }

    private function permissionsAreDeniedByDefault(): void
    {
        $this->boot('auth2');
        $this->seedPermissions();

        $user = $this->makeUser('nobody');

        $this->assertFalse($user->hasPermission('customers.view'));
        $this->assertFalse($user->hasPermission('contracts.view_financials'));
        $this->assertFalse($user->hasPermission('settings.manage'));
    }

    private function roleGrantsExactlyItsPermissions(): void
    {
        $this->boot('auth3');
        $this->seedPermissions();

        $role = Role::create(['code' => 'limited', 'title' => 'محدود']);
        $missing = $role->syncPermissions(['customers.view', 'contracts.view', 'does.not.exist']);

        $this->assertSame(['does.not.exist'], $missing, 'unknown codes must be reported back');

        $user = $this->makeUser('limited');
        $user->roles()->attach($role->id);
        $user->unsetRelation('roles');

        $user = $user->fresh();

        $this->assertTrue($user->hasPermission('customers.view'));
        $this->assertTrue($user->hasPermission('contracts.view'));
        $this->assertFalse($user->hasPermission('customers.create'));
        $this->assertFalse($user->hasPermission('contracts.view_financials'));
    }

    private function deactivatedUserHoldsNoPermissions(): void
    {
        $this->boot('auth4');
        $this->seedPermissions();

        $user = $this->makeUser('suspended', superadmin: true);
        $this->assertTrue($user->hasPermission('payroll.view'), 'active superadmin sees everything');

        $user->forceFill(['is_active' => false])->save();
        $user = $user->fresh();

        $this->assertTrue($user->isBlocked());
        $this->assertFalse($user->hasPermission('payroll.view'));
        $this->assertFalse($user->hasPermission('customers.view'));
    }

    private function lockedAccountIsBlocked(): void
    {
        $this->boot('auth5');

        $user = $this->makeUser('locked');
        $this->assertFalse($user->isBlocked());

        $user->forceFill(['locked_until' => now()->addMinutes(15)])->save();
        $this->assertTrue($user->fresh()->isBlocked());

        $user->forceFill(['locked_until' => now()->subMinute()])->save();
        $this->assertFalse($user->fresh()->isBlocked(), 'an expired lock must not block');
    }

    private function auditLogRedactsSecrets(): void
    {
        $this->boot('auth6');

        $redacted = AuditLog::redact([
            'company_name' => 'شرکت آزمون',
            'password' => 'super-secret',
            'password_confirmation' => 'super-secret',
            'api_key' => 'abc123',
            'remember_token' => 'tok',
            'nested' => ['credentials' => ['password' => 'x'], 'city' => 'تهران'],
        ]);

        $this->assertSame('شرکت آزمون', $redacted['company_name']);
        $this->assertSame('[redacted]', $redacted['password']);
        $this->assertSame('[redacted]', $redacted['password_confirmation']);
        $this->assertSame('[redacted]', $redacted['api_key']);
        $this->assertSame('[redacted]', $redacted['remember_token']);
        $this->assertSame('[redacted]', $redacted['nested']['credentials']);
        $this->assertSame('تهران', $redacted['nested']['city']);

        // Nothing resembling the secret may survive anywhere in the payload.
        $this->assertFalse(str_contains(json_encode($redacted), 'super-secret'));
        $this->assertFalse(str_contains(json_encode($redacted), 'abc123'));
    }

    private function auditDiffReportsOnlyRealChanges(): void
    {
        $before = ['company_name' => 'شرکت الف', 'city' => 'تهران', 'status' => 'lead'];
        $after = ['company_name' => 'شرکت ب', 'city' => 'تهران', 'status' => 'lead'];

        $changes = AuditLog::diff($before, $after);

        $this->assertCount(1, $changes);
        $this->assertArrayHasKey('company_name', $changes);
        $this->assertSame('شرکت الف', $changes['company_name']['from']);
        $this->assertSame('شرکت ب', $changes['company_name']['to']);
    }

    private function auditRecorderPersistsAnEvent(): void
    {
        $this->boot('auth7');

        $user = $this->makeUser('auditor');

        $log = AuditRecorder::record(
            AuditRecorder::UPDATED,
            $user,
            'ویرایش کاربر',
            ['name' => ['from' => 'الف', 'to' => 'ب']],
            $user->id
        );

        $this->assertNotNull($log);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'updated',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);

        $stored = AuditLog::query()->latest('id')->first();
        $this->assertSame($user->id, $stored->user_id);
        $this->assertIsArray($stored->changes);
        $this->assertSame('الف', $stored->changes['name']['from']);
    }

    private function passwordIsNeverWrittenToTheAuditLog(): void
    {
        $this->boot('auth8');

        $user = $this->makeUser('leaky');

        $plain = 'Plain-Text-Password-42';
        $user->forceFill(['password' => Hash::make($plain)])->save();

        // Simulate the careless-but-plausible call: the whole attribute array.
        AuditRecorder::record(
            AuditRecorder::UPDATED,
            $user,
            'تغییر رمز',
            ['attributes' => $user->getAttributes()],
            $user->id
        );

        $stored = AuditLog::query()->latest('id')->first();
        $payload = json_encode($stored->changes);

        $this->assertFalse(str_contains($payload, $plain), 'plaintext password must not appear');
        $this->assertFalse(str_contains($payload, $user->password), 'password hash must not appear');
        $this->assertTrue(str_contains($payload, '[redacted]'), 'the field must still be present, redacted');
    }
}
