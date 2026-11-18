<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertStatus(202)->assertExactJson(['status' => 'password-reset-link-requested']);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_response_does_not_reveal_if_email_exists(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'known@example.com']);

        $known = $this->post('/forgot-password', ['email' => 'known@example.com']);
        $unknown = $this->post('/forgot-password', ['email' => 'unknown@example.com']);

        $known->assertStatus(202)->assertExactJson(['status' => 'password-reset-link-requested']);
        $unknown->assertStatus(202)->assertExactJson(['status' => 'password-reset-link-requested']);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertStatus(200);

            return true;
        });
    }

    public function test_reset_link_request_logs_provider_failure_and_keeps_generic_response(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Password reset email delivery failed.'
                && $context['provider'] === config('mail.default')
                && $context['exception'] === RuntimeException::class
                && isset($context['request_id']));

        Password::shouldReceive('sendResetLink')
            ->once()
            ->andThrow(new RuntimeException('Provider unavailable'));

        $this->post('/forgot-password', [
            'email' => 'owner@example.com',
        ])->assertStatus(202)
            ->assertExactJson([
                'status' => 'password-reset-link-requested',
            ]);
    }
}
