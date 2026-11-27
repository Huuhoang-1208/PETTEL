<?php

namespace Tests\Feature;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ApiErrorHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/v1/_test/ok', fn () => response()->json([
            'message' => 'ok',
        ]));

        Route::post('/api/v1/_test/validation', function () {
            throw ValidationException::withMessages([
                'email' => ['Email là bắt buộc.'],
            ]);
        });

        Route::get('/api/v1/_test/unauthenticated', function () {
            throw new AuthenticationException;
        });

        Route::get('/api/v1/_test/forbidden', function () {
            abort(403);
        });

        Route::get('/api/v1/_test/server-error', function () {
            throw new RuntimeException('SECRET_EXCEPTION_DETAIL');
        });
    }

    public function test_success_response_uses_the_supplied_request_id(): void
    {
        $requestId = '11111111-1111-4111-8111-111111111111';

        $this->withHeader('X-Request-ID', $requestId)
            ->getJson('/api/v1/_test/ok')
            ->assertOk()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertJsonPath('message', 'ok');
    }

    public function test_validation_error_uses_the_standard_error_envelope(): void
    {
        $requestId = '22222222-2222-4222-8222-222222222222';

        $this->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/v1/_test/validation')
            ->assertUnprocessable()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertJsonStructure([
                'error' => ['code', 'message', 'request_id', 'details'],
            ])
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.request_id', $requestId)
            ->assertJsonPath('error.details.email.0', 'Email là bắt buộc.');
    }

    public function test_unauthenticated_error_uses_the_standard_error_envelope(): void
    {
        $requestId = '33333333-3333-4333-8333-333333333333';

        $this->withHeader('X-Request-ID', $requestId)
            ->getJson('/api/v1/_test/unauthenticated')
            ->assertUnauthorized()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED')
            ->assertJsonPath('error.request_id', $requestId);
    }

    public function test_forbidden_error_uses_the_standard_error_envelope(): void
    {
        $requestId = '44444444-4444-4444-8444-444444444444';

        $this->withHeader('X-Request-ID', $requestId)
            ->getJson('/api/v1/_test/forbidden')
            ->assertForbidden()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.request_id', $requestId);
    }

    public function test_unmatched_api_route_still_returns_json_with_a_request_id(): void
    {
        $requestId = '55555555-5555-4555-8555-555555555555';

        $this->withHeader('X-Request-ID', $requestId)
            ->getJson('/api/v1/_test/route-does-not-exist')
            ->assertNotFound()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonPath('error.request_id', $requestId);
    }

    public function test_server_error_hides_exception_details_and_logs_request_id(): void
    {
        Log::spy();

        $requestId = '66666666-6666-4666-8666-666666666666';

        $this->withHeader('X-Request-ID', $requestId)
            ->getJson('/api/v1/_test/server-error')
            ->assertInternalServerError()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertJsonPath('error.code', 'INTERNAL_SERVER_ERROR')
            ->assertJsonPath(
                'error.message',
                'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.',
            )
            ->assertJsonPath('error.request_id', $requestId)
            ->assertDontSee('SECRET_EXCEPTION_DETAIL');

        Log::shouldHaveReceived('error')
            ->withArgs(function ($message, array $context) use ($requestId): bool {
                return ($context['request_id'] ?? null) === $requestId;
            });
    }
}
