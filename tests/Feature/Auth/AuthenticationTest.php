<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Iniciar sesión');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_users_can_log_in(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk()->assertSee($user->name);
    }

    public function test_users_cannot_log_in_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_inactive_users_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.inactive')]);

        $this->assertGuest();
    }

    public function test_inactive_account_is_not_revealed_without_the_right_password(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_users_deactivated_during_session_are_logged_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }
}
