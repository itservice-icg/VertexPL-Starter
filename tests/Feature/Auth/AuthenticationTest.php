<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    public function test_dev_login_screen_can_be_rendered_separately()
    {
        $response = $this->get('/dev');

        $response->assertStatus(200);
        $response->assertViewIs('auth.dev');
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertSame('127.0.0.1', $user->fresh()->last_login_ip);
    }

    public function test_dev_login_lists_only_active_mock_users()
    {
        $mock = User::factory()->create(['email' => 'reporter@mock.vertexpl.local']);
        User::factory()->create(['email' => 'inactive@mock.vertexpl.local', 'is_active' => false]);
        User::factory()->create(['email' => 'regular@example.com']);

        $this->get('/dev')->assertOk()
            ->assertSee($mock->email)
            ->assertDontSee('inactive@mock.vertexpl.local')
            ->assertDontSee('regular@example.com');
    }

    public function test_mock_user_can_login_without_password()
    {
        $user = User::factory()->create(['email' => 'reporter@mock.vertexpl.local']);
        $this->withSession(['dev_test' => true]);
        $sessionId = session()->getId();

        $this->post('/dev', ['user_id' => $user->id])
            ->assertRedirect(RouteServiceProvider::HOME);

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertSame('127.0.0.1', $user->fresh()->last_login_ip);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login.dev', 'actor_id' => $user->id]);
    }

    public function test_active_admin_users_are_listed_and_can_login_without_password()
    {
        foreach (['Super Admin', 'Administrator'] as $roleName) {
            $role = Role::create(['name' => $roleName, 'guard_name' => 'web']);
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->get('/dev')->assertOk()->assertSee($user->email)->assertSee($roleName);
            $this->post('/dev', ['user_id' => $user->id])->assertRedirect(RouteServiceProvider::HOME);
            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
        }
    }

    public function test_inactive_admin_is_not_listed_and_cannot_login()
    {
        $role = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $user = User::factory()->create(['is_active' => false]);
        $user->assignRole($role);

        $this->get('/dev')->assertOk()->assertDontSee($user->email);
        $this->post('/dev', ['user_id' => $user->id])->assertNotFound();
        $this->assertGuest();
    }

    public function test_admin_cannot_use_dev_login_in_production()
    {
        $role = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->app->instance('env', 'production');

        $this->get('/dev')->assertNotFound();
        $this->withSession(['_token' => 'dev-login-test-token'])
            ->post('/dev', ['user_id' => $user->id, '_token' => 'dev-login-test-token'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_dev_login_rejects_non_mock_inactive_deleted_and_unknown_users()
    {
        $regular = User::factory()->create();
        $inactive = User::factory()->create(['email' => 'inactive@mock.vertexpl.local', 'is_active' => false]);
        $deleted = User::factory()->create(['email' => 'deleted@mock.vertexpl.local']);
        $deleted->delete();

        foreach ([$regular->id, $inactive->id, $deleted->id, 999999] as $id) {
            $this->post('/dev', ['user_id' => $id])->assertNotFound();
            $this->assertGuest();
        }
    }

    public function test_dev_login_validates_user_id()
    {
        $this->from('/dev')->post('/dev', ['user_id' => 'invalid'])
            ->assertRedirect('/dev')->assertSessionHasErrors('user_id');
        $this->assertGuest();
    }

    public function test_dev_login_is_unavailable_outside_local_and_testing()
    {
        $user = User::factory()->create(['email' => 'reporter@mock.vertexpl.local']);

        foreach (['production', 'staging'] as $environment) {
            $this->app->instance('env', $environment);
            $this->get('/dev')->assertNotFound();
            $this->withSession(['_token' => 'dev-login-test-token'])
                ->post('/dev', ['user_id' => $user->id, '_token' => 'dev-login-test-token'])->assertNotFound();
            $this->assertGuest();
        }
    }

    public function test_dev_login_is_available_locally_and_redirects_authenticated_users()
    {
        $this->app->instance('env', 'local');
        $this->get('/dev')->assertOk();

        $user = User::factory()->create();
        $this->actingAs($user)->get('/dev')->assertRedirect(RouteServiceProvider::HOME);
        $this->withSession(['_token' => 'dev-login-test-token'])
            ->post('/dev', ['user_id' => $user->id, '_token' => 'dev-login-test-token'])
            ->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_local_dev_login_requires_csrf_token()
    {
        $user = User::factory()->create(['email' => 'reporter@mock.vertexpl.local']);
        $this->app->instance('env', 'local');

        $this->post('/dev', ['user_id' => $user->id])->assertStatus(419);
        $this->assertGuest();

        $this->withSession(['_token' => 'dev-login-test-token'])
            ->post('/dev', ['user_id' => $user->id, '_token' => 'dev-login-test-token'])
            ->assertRedirect(RouteServiceProvider::HOME);
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_users_can_not_authenticate()
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_are_redirected_to_login_after_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }
}
