<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTokenHasRouteAbility
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $routeMiddleware = $route?->middleware() ?? [];

        if (! in_array('auth:sanctum', $routeMiddleware, true)) {
            return $next($request);
        }

        $user = Auth::guard('sanctum')->user();
        $token = $user?->currentAccessToken();

        if ($token === null || $token instanceof TransientToken) {
            return $next($request);
        }

        $hasAbilityMiddleware = collect($routeMiddleware)->contains(
            static fn (string $middleware): bool => str_starts_with($middleware, 'abilities:')
                || str_starts_with($middleware, 'ability:'),
        );

        abort_unless($hasAbilityMiddleware, Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
