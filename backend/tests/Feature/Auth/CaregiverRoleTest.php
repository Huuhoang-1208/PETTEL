<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CaregiverRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_active_user_can_enable_caregiver_once(): void
    {
        $user = User::factory()->create(['status' => 'ACTIVE']);
        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role' => 'OWNER',
            'assigned_at' => now(),
        ]);
        $this->actingAs($user, 'web');

        $this->postJson('/api/user/roles/caregiver')->assertNoContent();
        $this->postJson('/api/user/roles/caregiver')->assertNoContent();

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->id,
            'role' => 'CAREGIVER',
        ]);
        $this->assertSame(1, DB::table('audit_logs')
            ->where('actor_user_id', $user->id)
            ->where('action', 'ROLE_ASSIGNED')
            ->where('reason', 'SELF_ENABLED_CAREGIVER')
            ->count());
    }

    public function test_guest_cannot_enable_caregiver(): void
    {
        $this->postJson('/api/user/roles/caregiver')->assertUnauthorized();
    }

    public function test_owner_without_admin_role_gets_forbidden_on_admin_route(): void
    {
        $user = User::factory()->create(['status' => 'ACTIVE']);
        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role' => 'OWNER',
            'assigned_at' => now(),
        ]);
        Route::middleware(['auth:sanctum', 'account.active', 'role:ADMIN'])
            ->get('/api/admin-probe', fn () => response()->noContent());

        $this->actingAs($user, 'web')
            ->getJson('/api/admin-probe')
            ->assertForbidden();
    }

    public function test_non_active_user_cannot_enable_caregiver(): void
    {
        $user = User::factory()->create(['status' => 'DELETION_PENDING']);
        $this->actingAs($user, 'web');

        $this->postJson('/api/user/roles/caregiver')->assertForbidden();

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $user->id,
            'role' => 'CAREGIVER',
        ]);
    }
}
