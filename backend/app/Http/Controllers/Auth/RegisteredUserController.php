<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): Response
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->string('password')),
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
                'reason' => 'PUBLIC_REGISTRATION',
                'metadata_json' => json_encode(['role' => 'OWNER']),
                'created_at' => now(),
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return response()->noContent();
    }
}
