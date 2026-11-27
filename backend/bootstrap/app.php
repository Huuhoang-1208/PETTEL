<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureApiTokenHasAbilities;
use App\Http\Middleware\EnsureApiTokenHasAnyAbility;
use App\Http\Middleware\EnsureApiTokenHasRouteAbility;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RecordPerformanceQueryMetrics;
use App\Http\Middleware\ResetSanctumRequestGuard;
use App\Support\ApiErrorResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        [
            'prefix' => 'api',
            'middleware' => ['api', 'auth:sanctum'],
        ],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AssignRequestId::class);
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->api(append: [
            RecordPerformanceQueryMetrics::class,
            ResetSanctumRequestGuard::class,
        ]);

        $middleware->alias([
            'account.active' => EnsureActiveAccount::class,
            'role' => EnsureUserHasRole::class,
            'abilities' => EnsureApiTokenHasAbilities::class,
            'ability' => EnsureApiTokenHasAnyAbility::class,
            'token.route' => EnsureApiTokenHasRouteAbility::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Khi Laravel tự ghi log exception, lấy ID từ request hiện tại.
        $exceptions->context(function (): array {
            $request = request();
            $requestId = $request->attributes->get('request_id');

            if (! $request->is('api/*') || ! is_string($requestId)) {
                return [];
            }

            return ['request_id' => $requestId];
        });

        // Chuẩn hóa cả lỗi exception được render ngoài middleware thông thường,
        // chẳng hạn lỗi route API không tồn tại.
        $exceptions->respond(function (Response $response): Response {
            $request = request();

            if (! $request->is('api/*')) {
                return $response;
            }

            $requestId = $request->attributes->get('request_id');

            if (! is_string($requestId) || ! Str::isUuid($requestId)) {
                $requestId = (string) Str::uuid();
                $request->attributes->set('request_id', $requestId);
            }

            $response->headers->set('X-Request-ID', $requestId);

            if ($response->isClientError() || $response->isServerError()) {
                return ApiErrorResponse::normalize($response, $requestId);
            }

            return $response;
        });
    })->create();
