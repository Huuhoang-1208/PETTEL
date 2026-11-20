<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_redirects_to_provider(): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com/'));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $this->get('/auth/google/redirect')
            ->assertRedirect('https://accounts.google.com/');
    }

    public function test_verified_google_user_is_created_and_logged_in_as_owner(): void
    {
        $this->mockGoogleProfile([
            'id' => 'google-new-user-1',
            'name' => 'Google Pet Owner',
            'email' => 'google-owner@example.com',
            'verified_email' => true,
        ]);

        $this->get('/auth/google/callback')->assertRedirect();

        $user = User::query()
            ->where('email', 'google-owner@example.com')
            ->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-new-user-1',
        ]);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->id,
            'role' => 'OWNER',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ROLE_ASSIGNED',
            'resource_type' => 'user_role',
            'resource_id' => $user->id,
            'reason' => 'GOOGLE_REGISTRATION',
        ]);
    }

    public function test_verified_google_email_links_existing_account_without_overwriting_profile(): void
    {
        $existing = User::factory()->create([
            'name' => 'Tên PETTEL hiện tại',
            'email' => 'existing-owner@example.com',
        ]);
        $existing->owner_profile_json = ['bio' => 'Giới thiệu cần được giữ lại'];
        $existing->save();

        $this->mockGoogleProfile([
            'id' => 'google-existing-user-1',
            'name' => 'Google Name',
            'email' => 'existing-owner@example.com',
            'verified_email' => true,
        ]);

        $this->get('/auth/google/callback')->assertRedirect();

        $existing->refresh();

        $this->assertAuthenticatedAs($existing);
        $this->assertSame('google-existing-user-1', $existing->google_id);
        $this->assertSame('Tên PETTEL hiện tại', $existing->name);
        $this->assertSame(
            'Giới thiệu cần được giữ lại',
            $existing->owner_profile_json['bio'],
        );
        $this->assertDatabaseCount('users', 1);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->mockGoogleProfile([
            'id' => 'google-unverified-user-1',
            'name' => 'Unverified User',
            'email' => 'unverified@example.com',
            'verified_email' => false,
        ]);

        $this->get('/auth/google/callback')->assertRedirect();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'unverified@example.com',
        ]);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function mockGoogleProfile(array $profile): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')
            ->once()
            ->andReturn(SocialiteUser::fake($profile));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);
    }
}
