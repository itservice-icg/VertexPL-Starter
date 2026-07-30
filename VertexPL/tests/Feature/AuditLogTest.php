<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['audit.enabled' => true]);
    }

    public function test_audit_logging_can_be_disabled_by_configuration(): void
    {
        config(['audit.enabled' => false]);

        $user = User::factory()->create([
            'email' => 'no-audit@example.com',
            'password' => Hash::make('CorrectPassword!123'),
            'is_active' => true,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'CorrectPassword!123',
        ])->assertRedirect();

        $this->assertDatabaseCount('audit_logs', 0);
        config(['audit.enabled' => true]);
    }

    public function test_user_with_permission_can_view_and_filter_audit_logs(): void
    {
        $user = User::factory()->create();
        Permission::create(['name' => 'system.audit.view']);
        $user->givePermissionTo('system.audit.view');

        AuditLog::create([
            'actor_id' => $user->id,
            'module' => 'users',
            'action' => 'users.updated',
            'description' => 'Updated user Example User',
            'ip_address' => '127.0.0.1',
        ]);
        AuditLog::create([
            'actor_id' => $user->id,
            'module' => 'auth',
            'action' => 'auth.login.success',
            'description' => 'Signed in successfully',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($user)->get(route('audit-logs.index', [
            'module' => 'users',
            'search' => 'Example',
        ]));

        $response->assertOk()
            ->assertSee('Updated user Example User')
            ->assertDontSee('Signed in successfully');
    }

    public function test_user_without_permission_cannot_view_audit_logs(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('audit-logs.index'))
            ->assertForbidden();
    }

    public function test_successful_and_failed_login_attempts_are_audited_without_passwords(): void
    {
        $user = User::factory()->create([
            'email' => 'audited@example.com',
            'password' => Hash::make('CorrectPassword!123'),
            'is_active' => true,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'CorrectPassword!123',
        ])->assertRedirect();

        $success = AuditLog::where('action', 'auth.login.success')->firstOrFail();
        $this->assertSame($user->id, $success->actor_id);
        $this->assertStringNotContainsString('CorrectPassword!123', $success->toJson());

        $this->post('/logout')->assertRedirect('/login');

        $logout = AuditLog::where('action', 'auth.logout')->firstOrFail();
        $this->assertSame($user->id, $logout->actor_id);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'WrongPassword!123',
        ])->assertSessionHasErrors('email');

        $failed = AuditLog::where('action', 'auth.login.failed')->firstOrFail();
        $this->assertStringNotContainsString('WrongPassword!123', $failed->toJson());
    }

    public function test_user_management_changes_are_audited(): void
    {
        $admin = $this->userWithPermissions([
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
        ]);

        $role = Role::create(['name' => 'Auditor']);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Managed User',
            'email' => 'managed@example.com',
            'role_id' => $role->id,
            'password' => 'InitialPassword!123',
            'password_confirmation' => 'InitialPassword!123',
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $managed = User::where('email', 'managed@example.com')->firstOrFail();

        $this->put(route('users.update', $managed), [
            'name' => 'Managed User Updated',
            'email' => 'managed@example.com',
            'role_id' => $role->id,
            'password' => 'ChangedPassword!123',
            'password_confirmation' => 'ChangedPassword!123',
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $this->delete(route('users.destroy', $managed))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'users.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'users.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'users.deleted']);

        $serializedLogs = AuditLog::where('module', 'users')->get()->toJson();
        $this->assertStringNotContainsString('InitialPassword!123', $serializedLogs);
        $this->assertStringNotContainsString('ChangedPassword!123', $serializedLogs);
    }

    public function test_role_and_permission_changes_are_audited(): void
    {
        $admin = $this->userWithPermissions([
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.view',
            'permissions.manage',
        ]);
        $permission = Permission::firstOrCreate(['name' => 'users.view']);

        $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'Operations',
            'permissions' => [$permission->id],
        ])->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'Operations')->firstOrFail();

        $this->put(route('roles.update', $role), [
            'name' => 'Operations Updated',
            'permissions' => [$permission->id],
        ])->assertRedirect(route('roles.index'));

        $this->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'roles.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'roles.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'roles.deleted']);
    }

    public function test_profile_and_password_changes_are_audited_without_secrets(): void
    {
        $user = User::factory()->create([
            'name' => 'Before Name',
            'email' => 'profile@example.com',
            'password' => Hash::make('CurrentPassword!123'),
        ]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => 'After Name',
                'email' => $user->email,
            ])
            ->assertRedirect(route('profile.edit'));

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('profile.password.update'), [
                'current_password' => 'CurrentPassword!123',
                'password' => 'NewSecurePassword!456',
                'password_confirmation' => 'NewSecurePassword!456',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'profile.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'profile.password_changed']);

        $serializedLogs = AuditLog::where('module', 'profile')->get()->toJson();
        $this->assertStringNotContainsString('CurrentPassword!123', $serializedLogs);
        $this->assertStringNotContainsString('NewSecurePassword!456', $serializedLogs);
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $user->givePermissionTo($permissions);

        return $user;
    }
}
