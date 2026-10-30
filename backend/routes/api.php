<?php

use App\Http\Controllers\Auth\EnableCaregiverRoleController;
use App\Http\Controllers\PetController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'account.active'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/user/roles/caregiver', EnableCaregiverRoleController::class)
    ->middleware(['auth:sanctum', 'account.active', 'throttle:10,1'])
    ->name('user.roles.caregiver');

Route::middleware(['auth:sanctum', 'account.active', 'role:OWNER'])->group(function () {
    Route::get('/pets', [PetController::class, 'index'])->name('pets.index');
    Route::post('/pets', [PetController::class, 'store'])->name('pets.store');
    Route::get('/pets/{pet}', [PetController::class, 'show'])->name('pets.show');
    Route::patch('/pets/{pet}', [PetController::class, 'update'])->name('pets.update');
    Route::patch('/pets/{pet}/archive', [PetController::class, 'archive'])->name('pets.archive');
    Route::patch('/pets/{pet}/activate', [PetController::class, 'activate'])->name('pets.activate');
    Route::delete('/pets/{pet}', [PetController::class, 'destroy'])->name('pets.destroy');
});
