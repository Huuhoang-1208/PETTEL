<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTokenHasAbilities
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        if ($request->bearerToken() !== null) {
            $user = $request->user();
            abort_unless(
                $user !== null && collect($abilities)->every(fn (string $ability): bool => $user->tokenCan($ability)),
                Response::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }
}
