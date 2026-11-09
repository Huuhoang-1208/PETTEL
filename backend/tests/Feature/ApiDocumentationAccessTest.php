<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiDocumentationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_documentation_is_not_available_to_guests(): void
    {
        $this->getJson('/docs/api.json')->assertForbidden();
        $this->get('/docs/api')->assertForbidden();
    }

    public function test_api_documentation_is_not_available_to_non_admin_users(): void
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role' => 'OWNER',
            'assigned_at' => now(),
        ]);

        $this->actingAs($user)->getJson('/docs/api.json')->assertForbidden();
        $this->actingAs($user)->get('/docs/api')->assertForbidden();
    }

    public function test_active_admin_can_view_the_openapi_document(): void
    {
        $admin = User::factory()->create();
        DB::table('user_roles')->insert([
            'user_id' => $admin->id,
            'role' => 'ADMIN',
            'assigned_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonPath('openapi', fn (string $version): bool => str_starts_with($version, '3.'))
            ->assertJsonPath('info.title', 'PETTEL')
            ->assertJsonPath('components.securitySchemes.sanctumSession.type', 'apiKey')
            ->assertJsonPath('components.securitySchemes.sanctumSession.in', 'cookie')
            ->assertJsonPath('components.securitySchemes.sanctumSession.name', config('session.cookie'))
            ->assertJsonPath('security.0.sanctumSession', [])
            ->assertJsonPath('paths./listings.get.security', [])
            ->assertJsonMissingPath('components.securitySchemes.bearerAuth');

        $this->actingAs($admin)->get('/docs/api')->assertOk();
    }
}
