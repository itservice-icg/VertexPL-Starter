<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_not_open_profile()
    {
        $this->get(route('profile.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_view_only_their_own_profile_information()
    {
        $user = User::factory()->create([
            'name' => 'Current User',
            'email' => 'current@example.com',
            'last_login_ip' => '127.0.0.1',
        ]);
        $role = Role::create(['name' => 'Viewer']);
        $user->assignRole($role);
        $other = User::factory()->create(['email' => 'other@example.com']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Current User')
            ->assertSee('current@example.com')
            ->assertDontSee('ยืนยันแล้ว')
            ->assertDontSee('รอยืนยัน')
            ->assertSee('127.0.0.1')
            ->assertSee('Viewer')
            ->assertDontSee($other->email);
    }

    public function test_profile_update_requires_recent_password_confirmation()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Updated Name',
                'email' => $user->email,
            ])
            ->assertRedirect(route('password.confirm'));

        $this->assertNotSame('Updated Name', $user->fresh()->name);
    }

    public function test_user_can_update_own_name_and_email_without_reverification()
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('profile.update'), [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_profile_email_must_be_unique()
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $other->email,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_password_change_requires_recent_password_confirmation()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'NewStrongPassword1!',
                'password_confirmation' => 'NewStrongPassword1!',
            ])
            ->assertRedirect(route('password.confirm'));
    }

    public function test_current_password_is_required_and_new_password_must_be_strong_and_different()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('profile.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors(['current_password', 'password']);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_user_can_change_password_and_change_time_is_recorded()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'NewStrongPassword1!',
                'password_confirmation' => 'NewStrongPassword1!',
            ])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertTrue(Hash::check('NewStrongPassword1!', $user->password));
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_password_change_is_rate_limited()
    {
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($user)
                ->withSession(['auth.password_confirmed_at' => time()])
                ->put(route('profile.password.update'), [
                    'current_password' => 'wrong-password',
                    'password' => 'NewStrongPassword1!',
                    'password_confirmation' => 'NewStrongPassword1!',
                ]);
        }

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('profile.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'NewStrongPassword1!',
                'password_confirmation' => 'NewStrongPassword1!',
            ])
            ->assertStatus(429);
    }

    public function test_profile_routes_do_not_accept_another_user_id()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile/'.$other->id)
            ->assertNotFound();
    }
}
