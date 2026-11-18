<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role): array => explode(',', $role))
            ->map(fn (string $role): string => trim($role))
            ->filter()
            ->values();
        $user = $request->user();

        abort_unless(
            $user instanceof User
            && $allowedRoles->contains(fn (string $allowedRole): bool => $user->hasRole($allowedRole)),
            403,
        );

        return $next($request);
    }
}
