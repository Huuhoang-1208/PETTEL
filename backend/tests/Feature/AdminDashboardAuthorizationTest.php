<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_filament_and_denied_pulse(): void
    {
        $filamentResponse = $this->get('/admin');

        $filamentResponse->assertRedirect();
        $this->assertStringEndsWith(
            '/admin/login',
            (string) parse_url(
                (string) $filamentResponse->headers->get('Location'),
                PHP_URL_PATH,
            ),
        );

        $this->get('/pulse')->assertForbidden();
    }

    public function test_active_non_admin_is_denied_both_dashboards(): void
    {
        $user = $this->userWithRole('OWNER');

        $this->actingAs($user, 'web')->get('/admin')->assertForbidden();
        $this->actingAs($user, 'web')->get('/pulse')->assertForbidden();
    }

    public function test_inactive_admin_is_denied_both_dashboards(): void
    {
        $admin = $this->userWithRole('ADMIN', 'SUSPENDED');

        $this->actingAs($admin, 'web')->get('/admin')->assertForbidden();
        $this->actingAs($admin, 'web')->get('/pulse')->assertForbidden();
    }

    public function test_active_admin_can_access_both_dashboards(): void
    {
        $admin = $this->userWithRole('ADMIN', 'ACTIVE');

        $this->actingAs($admin, 'web')->get('/admin')->assertOk();
        $this->actingAs($admin, 'web')->get('/pulse')->assertOk();
    }

    private function userWithRole(string $role, string $status = 'ACTIVE'): User
    {
        $user = User::factory()->create();

        DB::table('users')
            ->where('id', $user->id)
            ->update(['status' => $status]);

        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role' => $role,
            'assigned_at' => now(),
            'assigned_by_user_id' => null,
        ]);

        return $user->fresh();
    }
}
