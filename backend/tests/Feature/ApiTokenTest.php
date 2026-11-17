<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_with_profile_scope_can_read_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('profile-client', ['profile:read'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_token_without_profile_scope_is_forbidden(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('pets-client', ['pets:read'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertForbidden();
    }

    public function test_bearer_token_cannot_access_private_routes_without_an_ability_check(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('profile-client', ['profile:read'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/notifications')
            ->assertForbidden();
    }

    public function test_authenticated_user_can_issue_a_limited_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/tokens', [
            'name' => 'profile-client',
            'abilities' => ['profile:read'],
        ])->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('abilities.0', 'profile:read');

        $token = $response->json('token');
        Auth::guard('web')->logout();
        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_user_can_revoke_only_their_own_token(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $token = $user->createToken('profile-client', ['profile:read']);
        $otherToken = $otherUser->createToken('other-client', ['profile:read']);

        $this->actingAs($user)
            ->deleteJson('/api/tokens/'.$otherToken->accessToken->id)
            ->assertNotFound();

        $this->actingAs($user)
            ->deleteJson('/api/tokens/'.$token->accessToken->id)
            ->assertNoContent();

        Auth::guard('web')->logout();
        Auth::forgetGuards();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_token_creation_rejects_unapproved_abilities(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/tokens', [
            'name' => 'unrestricted-client',
            'abilities' => ['*'],
        ])->assertUnprocessable();
    }
}
