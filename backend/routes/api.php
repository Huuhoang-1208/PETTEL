<?php

use App\Http\Controllers\Auth\EnableCaregiverRoleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'account.active'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/user/roles/caregiver', EnableCaregiverRoleController::class)
    ->middleware(['auth:sanctum', 'account.active', 'throttle:10,1'])
    ->name('user.roles.caregiver');
