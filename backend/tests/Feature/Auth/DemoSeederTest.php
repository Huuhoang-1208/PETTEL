<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_is_repeatable_and_preserves_passwords(): void
    {
        $this->seed(DemoSeeder::class);

        $owner = User::query()
            ->where('email', 'owner@example.test')
            ->firstOrFail();

        $this->assertTrue(Hash::check('DemoOnly123!', $owner->password));

        $owner->update(['password' => 'ChangedDemo123!']);

        $this->seed(DemoSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('user_roles', 4);
        $this->assertDatabaseCount('pets', 1);
        $this->assertDatabaseCount('caregiver_profiles', 1);

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $owner->id,
            'role' => 'OWNER',
        ]);

        $caregiver = User::query()
            ->where('email', 'caregiver@example.test')
            ->firstOrFail();

        $this->assertTrue($caregiver->hasRole('OWNER'));
        $this->assertTrue($caregiver->hasRole('CAREGIVER'));

        $admin = User::query()
            ->where('email', 'admin@example.test')
            ->firstOrFail();

        $this->assertTrue($admin->hasRole('ADMIN'));

        $this->assertTrue(
            Hash::check('ChangedDemo123!', $owner->fresh()->password)
        );
    }
}
