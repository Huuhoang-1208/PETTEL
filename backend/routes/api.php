<?php

use App\Http\Controllers\AcceptBookingController;
use App\Http\Controllers\Auth\EnableCaregiverRoleController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CaregiverListingController;
use App\Http\Controllers\CaregiverProfileController;
use App\Http\Controllers\ListingAvailabilityController;
use App\Http\Controllers\ListingChangeRequestController;
use App\Http\Controllers\ListingLifecycleController;
use App\Http\Controllers\ListingMediaController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\PublicListingController;
use App\Http\Controllers\RejectBookingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/listings', [PublicListingController::class, 'index']);
Route::get('/listings/{id}', [PublicListingController::class, 'show'])->whereNumber('id');
Route::get('/listings/{id}/media/{mediaId}/content', [ListingMediaController::class, 'publicContent'])
    ->whereNumber(['id', 'mediaId'])->name('listings.media.content');

Route::middleware(['auth:sanctum', 'account.active'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/user/roles/caregiver', EnableCaregiverRoleController::class)
    ->middleware(['auth:sanctum', 'account.active', 'throttle:10,1'])
    ->name('user.roles.caregiver');

Route::middleware(['auth:sanctum', 'account.active', 'role:OWNER'])->group(function () {
    Route::post('/bookings/quote', [BookingController::class, 'quote'])->name('bookings.quote');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');

    Route::get('/pets', [PetController::class, 'index'])->name('pets.index');
    Route::post('/pets', [PetController::class, 'store'])->name('pets.store');
    Route::get('/pets/{pet}', [PetController::class, 'show'])->name('pets.show');
    Route::patch('/pets/{pet}', [PetController::class, 'update'])->name('pets.update');
    Route::patch('/pets/{pet}/archive', [PetController::class, 'archive'])->name('pets.archive');
    Route::patch('/pets/{pet}/activate', [PetController::class, 'activate'])->name('pets.activate');
    Route::delete('/pets/{pet}', [PetController::class, 'destroy'])->name('pets.destroy');
});

Route::middleware(['auth:sanctum', 'account.active', 'role:CAREGIVER'])
    ->prefix('caregiver')
    ->group(function () {
        Route::post('/profile', [CaregiverProfileController::class, 'store']);
        Route::get('/profile', [CaregiverProfileController::class, 'show']);
        Route::patch('/profile', [CaregiverProfileController::class, 'update']);

        Route::post('/listings', [CaregiverListingController::class, 'store']);
        Route::get('/listings', [ListingLifecycleController::class, 'caregiverIndex']);
        Route::get('/listings/{id}', [ListingLifecycleController::class, 'show'])->whereNumber('id');
        Route::patch('/listings/{id}', [CaregiverListingController::class, 'update'])->whereNumber('id');
        Route::delete('/listings/{id}', [CaregiverListingController::class, 'destroy'])->whereNumber('id');
        Route::post('/listings/{id}/submit', [ListingLifecycleController::class, 'submit'])->whereNumber('id');
        Route::post('/listings/{id}/suspend', [ListingLifecycleController::class, 'suspend'])->whereNumber('id');
        Route::post('/listings/{id}/restore', [ListingLifecycleController::class, 'restore'])->whereNumber('id');
        Route::post('/listings/{id}/archive', [ListingLifecycleController::class, 'archive'])->whereNumber('id');
        Route::get('/listings/{id}/change-requests', [ListingChangeRequestController::class, 'index'])->whereNumber('id');
        Route::post('/listings/{id}/change-requests', [ListingChangeRequestController::class, 'store'])->whereNumber('id');
        Route::get('/listings/{id}/change-requests/{requestId}', [ListingChangeRequestController::class, 'show'])->whereNumber(['id', 'requestId']);
        Route::post('/listings/{id}/change-requests/{requestId}/withdraw', [ListingChangeRequestController::class, 'withdraw'])->whereNumber(['id', 'requestId']);
        Route::get('/listings/{id}/availability-slots', [ListingAvailabilityController::class, 'index'])->whereNumber('id');
        Route::post('/listings/{id}/availability-slots', [ListingAvailabilityController::class, 'store'])->whereNumber('id');
        Route::patch('/listings/{id}/availability-slots/{slotId}', [ListingAvailabilityController::class, 'update'])->whereNumber(['id', 'slotId']);
        Route::delete('/listings/{id}/availability-slots/{slotId}', [ListingAvailabilityController::class, 'destroy'])->whereNumber(['id', 'slotId']);
        Route::put('/listings/{id}/media', [ListingMediaController::class, 'updateDraft'])->whereNumber('id');
        Route::get('/listings/{id}/media/{mediaId}/content', [ListingMediaController::class, 'caregiverContent'])->whereNumber(['id', 'mediaId']);
        Route::post('/media', [ListingMediaController::class, 'upload']);
        Route::get('/media/{mediaId}/content', [ListingMediaController::class, 'ownerContent'])->whereNumber('mediaId');
    });

Route::middleware(['auth:sanctum', 'account.active', 'role:ADMIN'])->prefix('admin')->group(function () {
    Route::get('/listings', [ListingLifecycleController::class, 'adminIndex']);
    Route::get('/listings/{id}', [ListingLifecycleController::class, 'show'])->whereNumber('id');
    Route::post('/listings/{id}/approve', [ListingLifecycleController::class, 'approve'])->whereNumber('id');
    Route::post('/listings/{id}/reject', [ListingLifecycleController::class, 'reject'])->whereNumber('id');
    Route::post('/listings/{id}/suspend', [ListingLifecycleController::class, 'suspend'])->whereNumber('id');
    Route::post('/listings/{id}/restore', [ListingLifecycleController::class, 'restore'])->whereNumber('id');
    Route::post('/listings/{id}/archive', [ListingLifecycleController::class, 'archive'])->whereNumber('id');
    Route::get('/listing-change-requests', [ListingChangeRequestController::class, 'queue']);
    Route::get('/listings/{id}/change-requests/{requestId}', [ListingChangeRequestController::class, 'adminShow'])->whereNumber(['id', 'requestId']);
    Route::post('/listings/{id}/change-requests/{requestId}/approve', [ListingChangeRequestController::class, 'approve'])->whereNumber(['id', 'requestId']);
    Route::post('/listings/{id}/change-requests/{requestId}/reject', [ListingChangeRequestController::class, 'reject'])->whereNumber(['id', 'requestId']);
    Route::get('/listings/{id}/media/{mediaId}/content', [ListingMediaController::class, 'adminContent'])->whereNumber(['id', 'mediaId']);
});

Route::post('/bookings/{id}/reject', RejectBookingController::class)
    ->middleware(['auth:sanctum', 'account.active', 'role:CAREGIVER'])
    ->whereNumber('id')
    ->name('bookings.reject');

Route::post('/bookings/{id}/accept', AcceptBookingController::class)
    ->middleware(['auth:sanctum', 'account.active', 'role:CAREGIVER'])
    ->whereNumber('id')
    ->name('bookings.accept');
