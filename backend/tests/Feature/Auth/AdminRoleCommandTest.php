<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_role_is_granted_and_revoked_only_through_audited_command(): void
    {
        $user = User::factory()->create();
        $anotherAdmin = User::factory()->create();

        $this->artisan('identity:admin', [
            'action' => 'grant',
            'email' => $user->email,
            'reason' => 'Demo administrator setup',
        ])->assertSuccessful();

        $this->assertDatabaseHas('user_roles', ['user_id' => $user->id, 'role' => 'ADMIN']);

        $this->artisan('identity:admin', [
            'action' => 'grant',
            'email' => $anotherAdmin->email,
            'reason' => 'Second demo administrator',
        ])->assertSuccessful();

        $this->artisan('identity:admin', [
            'action' => 'revoke',
            'email' => $user->email,
            'reason' => 'Demo administrator cleanup',
        ])->assertSuccessful();

        $this->assertDatabaseMissing('user_roles', ['user_id' => $user->id, 'role' => 'ADMIN']);
        $this->assertDatabaseHas('audit_logs', [
            'resource_type' => 'user_role',
            'resource_id' => $user->id,
            'action' => 'ROLE_ASSIGNED',
            'reason' => 'Demo administrator setup',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'resource_type' => 'user_role',
            'resource_id' => $user->id,
            'action' => 'ROLE_REVOKED',
            'reason' => 'Demo administrator cleanup',
        ]);
    }

    public function test_command_cannot_revoke_the_last_active_admin(): void
    {
        $user = User::factory()->create();

        $this->artisan('identity:admin', [
            'action' => 'grant',
            'email' => $user->email,
            'reason' => 'Demo administrator setup',
        ])->assertSuccessful();

        $this->artisan('identity:admin', [
            'action' => 'revoke',
            'email' => $user->email,
            'reason' => 'Cleanup',
        ])->assertFailed();

        $this->assertDatabaseHas('user_roles', ['user_id' => $user->id, 'role' => 'ADMIN']);
    }
}
