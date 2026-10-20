<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SettingManagementTest extends TestCase
{
    use RefreshDatabase;

    private function authorizedUser(): User
    {
        $user = User::factory()->create();
        Permission::create(['name' => 'system.settings']);
        $user->givePermissionTo('system.settings');

        return $user;
    }

    public function test_user_without_permission_cannot_manage_settings(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_search_settings(): void
    {
        $user = $this->authorizedUser();
        Setting::create(['key' => 'uploads.max_size', 'label' => 'Upload size', 'group' => 'uploads', 'type' => 'integer', 'value' => '10']);
        Setting::create(['key' => 'audit.retention_days', 'label' => 'Audit retention', 'group' => 'audit', 'type' => 'integer', 'value' => '365']);

        $this->actingAs($user)
            ->get(route('settings.index', ['search' => 'uploads']))
            ->assertOk()
            ->assertSee('uploads.max_size')
            ->assertDontSee('audit.retention_days');
    }

    public function test_authorized_user_can_create_update_and_delete_setting(): void
    {
        $user = $this->authorizedUser();

        $this->actingAs($user)
            ->post(route('settings.store'), [
                'key' => 'uploads.max_size_mb',
                'label' => 'Maximum upload size',
                'group' => 'uploads',
                'type' => 'integer',
                'value' => '10',
                'description' => 'Maximum file size in MB',
            ])
            ->assertRedirect();

        $setting = Setting::where('key', 'uploads.max_size_mb')->firstOrFail();
        $this->assertSame(10, Setting::value('uploads.max_size_mb'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.created']);

        $this->actingAs($user)
            ->put(route('settings.update', $setting), [
                'key' => 'uploads.max_size_mb',
                'label' => 'Maximum upload size',
                'group' => 'uploads',
                'type' => 'integer',
                'value' => '20',
                'description' => null,
            ])
            ->assertRedirect(route('settings.index'));

        $this->assertSame(20, Setting::value('uploads.max_size_mb'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);

        $this->actingAs($user)
            ->delete(route('settings.destroy', $setting))
            ->assertRedirect(route('settings.index'));

        $this->assertDatabaseMissing('settings', ['id' => $setting->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.deleted']);
    }

    public function test_setting_key_and_typed_value_are_validated(): void
    {
        $user = $this->authorizedUser();

        $this->actingAs($user)
            ->post(route('settings.store'), [
                'key' => 'Invalid Key',
                'label' => 'Invalid',
                'group' => 'general',
                'type' => 'json',
                'value' => '{invalid}',
            ])
            ->assertSessionHasErrors(['key', 'value']);
    }

    public function test_audit_log_does_not_store_setting_value(): void
    {
        $user = $this->authorizedUser();

        $this->actingAs($user)->post(route('settings.store'), [
            'key' => 'integration.endpoint',
            'label' => 'Integration endpoint',
            'group' => 'integration',
            'type' => 'string',
            'value' => 'private-value',
        ]);

        $this->assertStringNotContainsString('private-value', \App\Models\AuditLog::latest()->firstOrFail()->toJson());
    }

    public function test_system_setting_seeder_creates_defaults_without_overwriting_existing_values(): void
    {
        Setting::create([
            'key' => 'uploads.max_size_mb',
            'label' => 'Custom upload size',
            'group' => 'uploads',
            'type' => 'integer',
            'value' => '25',
        ]);

        $this->seed(SystemSettingSeeder::class);

        $this->assertDatabaseCount('settings', 9);
        $this->assertSame(25, Setting::value('uploads.max_size_mb'));
        $this->assertSame('VertexPL', Setting::value('general.app_name'));
        $this->assertSame(20, Setting::value('pagination.default_size'));
        $this->assertSame(5000, Setting::value('imports.max_rows'));
        $this->assertSame('FM-DQA-06', Setting::value('ncr.form.code'));
        $this->assertSame('03', Setting::value('ncr.form.revision'));
        $this->assertSame('2023-01-04', Setting::value('ncr.form.revision_date'));
        $this->assertSame(365, Setting::value('audit.retention_days'));
        $this->assertTrue(Setting::value('features.import_enabled'));
    }
}
