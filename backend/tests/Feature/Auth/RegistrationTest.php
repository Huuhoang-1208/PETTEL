<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'ADMIN',
        ]);

        $this->assertAuthenticated();
        $response->assertNoContent();
        $this->assertDatabaseHas('user_roles', [
            'user_id' => auth()->id(),
            'role' => 'OWNER',
        ]);
        $this->assertDatabaseMissing('user_roles', [
            'user_id' => auth()->id(),
            'role' => 'ADMIN',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ROLE_ASSIGNED',
            'resource_type' => 'user_role',
            'resource_id' => auth()->id(),
            'reason' => 'PUBLIC_REGISTRATION',
        ]);
    }
}
