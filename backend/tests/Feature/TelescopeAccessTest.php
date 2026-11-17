<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TelescopeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_admins_can_view_telescope(): void
    {
        $admin = User::factory()->create();
        DB::table('user_roles')->insert([
            'user_id' => $admin->id,
            'role' => 'ADMIN',
            'assigned_at' => now(),
        ]);

        $owner = User::factory()->create();
        DB::table('user_roles')->insert([
            'user_id' => $owner->id,
            'role' => 'OWNER',
            'assigned_at' => now(),
        ]);

        $inactiveAdmin = User::factory()->create();
        $inactiveAdmin->forceFill(['status' => 'SUSPENDED'])->save();
        DB::table('user_roles')->insert([
            'user_id' => $inactiveAdmin->id,
            'role' => 'ADMIN',
            'assigned_at' => now(),
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('viewTelescope'));
        $this->assertFalse(Gate::forUser($owner)->allows('viewTelescope'));
        $this->assertFalse(Gate::forUser($inactiveAdmin)->allows('viewTelescope'));
    }
}
