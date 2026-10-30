<?php

namespace App\Http\Controllers;

use App\Http\Requests\PetRequest;
use App\Http\Resources\PetResource;
use App\Models\Pet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PetController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Pet::class);

        return PetResource::collection(request()->user()->pets()->latest('id')->paginate(20));
    }

    public function store(PetRequest $request): JsonResponse
    {
        Gate::authorize('create', Pet::class);

        $pet = $request->user()->pets()->create($request->validated());

        return PetResource::make($pet->refresh())->response()->setStatusCode(201);
    }

    public function show(string $pet): PetResource
    {
        return PetResource::make($this->ownedPet($pet, 'view'));
    }

    public function update(PetRequest $request, string $pet): PetResource
    {
        $record = $this->ownedPet($pet, 'update');
        $record->update($request->validated());

        return PetResource::make($record->refresh());
    }

    public function archive(string $pet): PetResource
    {
        $record = $this->ownedPet($pet, 'update');
        $record->status = 'ARCHIVED';
        $record->save();

        return PetResource::make($record->refresh());
    }

    public function activate(string $pet): PetResource
    {
        $record = $this->ownedPet($pet, 'update');
        $record->status = 'ACTIVE';
        $record->save();

        return PetResource::make($record->refresh());
    }

    public function destroy(string $pet): Response
    {
        $this->ownedPet($pet, 'delete')->delete();

        return response()->noContent();
    }

    private function ownedPet(string $id, string $ability): Pet
    {
        $pet = request()->user()->pets()->findOrFail($id);
        Gate::authorize($ability, $pet);

        return $pet;
    }
}
