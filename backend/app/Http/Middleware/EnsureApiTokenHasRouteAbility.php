<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTokenHasRouteAbility
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var mixed $token */
        $token = $request->user()?->currentAccessToken();

        if ($token !== null && ! $token instanceof TransientToken) {
            $routeMiddleware = $request->route()?->middleware() ?? [];
            $hasAbilityMiddleware = collect($routeMiddleware)->contains(
                static fn (string $middleware): bool => str_starts_with($middleware, 'abilities:')
                    || str_starts_with($middleware, 'ability:'),
            );

            abort_unless($hasAbilityMiddleware, Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
