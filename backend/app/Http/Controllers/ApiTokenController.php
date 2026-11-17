<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [
                'required',
                'string',
                Rule::in([
                    'profile:read',
                    'pets:read',
                    'bookings:read',
                ]),
            ],
        ]);
        /** @var list<string> $abilities */
        $abilities = array_values(array_unique($validated['abilities']));

        /** @var User $user */
        $user = $request->user();
        $expiresAt = now()->addDays(30);

        $issuedToken = $user->createToken(
            $validated['name'],
            $abilities,
            $expiresAt,
        );
        $plainTextToken = (string) $issuedToken->plainTextToken;

        return response()->json([
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $abilities,
            'expires_at' => date(DATE_ATOM, $expiresAt->getTimestamp()),
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request, int $tokenId): Response
    {
        /** @var User $user */
        $user = $request->user();

        $deleted = $user->tokens()->whereKey($tokenId)->delete();

        abort_unless($deleted > 0, Response::HTTP_NOT_FOUND);

        return response()->noContent();
    }
}
