<?php

namespace App\Domain\Discovery\Actions;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class ResolveSmartMatchPet
{
    /** @param array<string, mixed> $filters */
    public function handle(?User $user, string $petId, array $filters): Pet
    {
        if ($user === null) {
            $this->reject(401, 'UNAUTHENTICATED', 'Login is required to search with a pet.');
        }

        if ($user->status !== 'ACTIVE' || ! $user->hasRole('OWNER')) {
            $this->reject(403, 'FORBIDDEN', 'An active owner account is required to search with a pet.');
        }

        $pet = Pet::query()
            ->where('user_id', $user->id)
            ->whereKey($petId)
            ->first();

        if ($pet === null) {
            $this->reject(404, 'NOT_FOUND', 'Pet not found.');
        }

        if ($pet->status !== 'ACTIVE') {
            $this->reject(409, 'INVALID_STATE', 'Pet is not active.');
        }

        $species = $pet->getAttribute('species');
        $size = $pet->getAttribute('size');

        if (! is_string($species) || ! is_string($size) || $species === '' || $size === '') {
            $this->reject(422, 'VALIDATION_ERROR', 'Pet species and size are required for Smart Match.', [
                'pet_id' => ['Update the pet size before using Smart Match.'],
            ]);
        }

        if (isset($filters['species']) && $filters['species'] !== $species) {
            $this->reject(422, 'VALIDATION_ERROR', 'Manual species filter conflicts with the selected pet.', [
                'species' => ['Species must match the selected pet.'],
            ]);
        }

        if (isset($filters['size']) && $filters['size'] !== $size) {
            $this->reject(422, 'VALIDATION_ERROR', 'Manual size filter conflicts with the selected pet.', [
                'size' => ['Size must match the selected pet.'],
            ]);
        }

        return $pet;
    }

    /** @param array<string, list<string>> $details */
    private function reject(int $status, string $code, string $message, array $details = []): never
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'request_id' => (string) Str::uuid(),
            ],
        ], $status));
    }
}
