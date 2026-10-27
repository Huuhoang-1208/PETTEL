<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertNoContent();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_suspended_and_deactivated_users_cannot_authenticate(): void
    {
        foreach (['SUSPENDED', 'DEACTIVATED'] as $status) {
            $user = User::factory()->create(['status' => $status]);

            $this->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ])->assertSessionHasErrors('email');

            $this->assertGuest();
        }
    }

    public function test_expired_session_cannot_access_private_api(): void
    {
        $this->withCookie(config('session.cookie'), 'expired-session-id')
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertNoContent();
    }
}
