<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\RequestGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResetSanctumRequestGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } finally {
            /** @var User|null $user */
            $user = $request->user();
            if ($user !== null && $request->bearerToken() === null) {
                Auth::guard('web')->setUser($user);
            }

            $guard = Auth::guard('sanctum');
            if ($guard instanceof RequestGuard) {
                $guard->forgetUser();
            }

            Auth::shouldUse((string) config('auth.defaults.guard', 'web'));
        }
    }
}
