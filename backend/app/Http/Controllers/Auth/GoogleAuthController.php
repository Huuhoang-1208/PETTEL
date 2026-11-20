<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        // Socialite's Google provider requests openid, profile, and email by default.
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            report($exception);

            return $this->loginFailure();
        }

        $googleId = trim($googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $rawGoogleUser = method_exists($googleUser, 'getRaw')
            ? $googleUser->getRaw()
            : [];
        $emailIsVerified = (bool) data_get($rawGoogleUser, 'verified_email', false);

        if ($googleId === '' || $email === '' || ! $emailIsVerified) {
            return $this->loginFailure();
        }

        try {
            $user = DB::transaction(function () use ($googleId, $email, $googleUser): ?User {
                $user = User::query()
                    ->where('google_id', $googleId)
                    ->lockForUpdate()
                    ->first();

                if ($user !== null) {
                    return $user;
                }

                $user = User::query()
                    ->where('email', $email)
                    ->lockForUpdate()
                    ->first();

                if ($user !== null) {
                    // Do not link this email to a different Google account.
                    if ($user->google_id !== null) {
                        return null;
                    }

                    $user->google_id = $googleId;
                    $user->email_verified_at ??= now();
                    $user->save();

                    return $user;
                }

                $name = trim((string) $googleUser->getName());
                if ($name === '') {
                    $name = Str::before($email, '@');
                }

                $user = User::create([
                    'name' => Str::limit($name, 100, ''),
                    'email' => $email,
                    'password' => Hash::make(Str::random(64)),
                    'google_id' => $googleId,
                    'email_verified_at' => now(),
                ]);

                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role' => 'OWNER',
                    'assigned_at' => now(),
                ]);

                DB::table('audit_logs')->insert([
                    'action' => 'ROLE_ASSIGNED',
                    'resource_type' => 'user_role',
                    'resource_id' => $user->id,
                    'reason' => 'GOOGLE_REGISTRATION',
                    'metadata_json' => json_encode(['role' => 'OWNER']),
                    'created_at' => now(),
                ]);

                return $user;
            });
        } catch (Throwable $exception) {
            report($exception);

            return $this->loginFailure();
        }

        if ($user === null || $user->status !== 'ACTIVE') {
            return $this->loginFailure();
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->to($frontendUrl.'/?oauth=success');
    }

    private function loginFailure(): RedirectResponse
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->to($frontendUrl.'/login?oauth=failed');
    }
}
