<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTokenHasAnyAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        if ($request->bearerToken() !== null) {
            $user = $request->user();
            abort_unless(
                $user !== null && collect($abilities)->contains(fn (string $ability): bool => $user->tokenCan($ability)),
                Response::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }
}
