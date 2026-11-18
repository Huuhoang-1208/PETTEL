<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class PasswordResetLinkController extends Controller
{
    /** Handle an incoming password reset link request. */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            Password::sendResetLink($request->only('email'));
        } catch (Throwable $exception) {
            Log::error('Password reset email delivery failed.', [
                'request_id' => (string) Str::uuid(),
                'provider' => config('mail.default'),
                'exception' => $exception::class,
            ]);
        }

        // Trả cùng một phản hồi dù email có đăng ký hay không, để không
        // tiết lộ tài khoản nào tồn tại.
        return response()->json([
            'status' => 'password-reset-link-requested',
        ], 202);
    }
}
