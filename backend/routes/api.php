<?php

use App\Http\Controllers\AcceptBookingController;
use App\Http\Controllers\Admin\BookingCancellationQueueController;
use App\Http\Controllers\Admin\BookingCompletionReviewController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Auth\EnableCaregiverRoleController;
use App\Http\Controllers\BookingCareLifecycleController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingMessageController;
use App\Http\Controllers\BookingQueryController;
use App\Http\Controllers\CancelBookingController;
use App\Http\Controllers\CaregiverListingController;
use App\Http\Controllers\CaregiverProfileController;
use App\Http\Controllers\CareUpdateController;
use App\Http\Controllers\ConfirmBookingCompletionController;
use App\Http\Controllers\ListingAvailabilityController;
use App\Http\Controllers\ListingChangeRequestController;
use App\Http\Controllers\ListingLifecycleController;
use App\Http\Controllers\ListingMediaController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\PublicListingController;
use App\Http\Controllers\RejectBookingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SandboxPaymentCallbackController;
use App\Http\Controllers\SimulateSandboxPaymentController;
use App\Http\Controllers\StartSandboxPaymentController;
use App\Http\Controllers\StoreBookingReviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/listings', [PublicListingController::class, 'index']);
Route::get('/listings/{id}', [PublicListingController::class, 'show'])->whereNumber('id');
Route::get('/listings/{id}/reviews', [PublicListingController::class, 'reviews'])
    ->whereNumber('id')->name('listings.reviews.index');
Route::get('/listings/{id}/media/{mediaId}/content', [ListingMediaController::class, 'publicContent'])
    ->whereNumber(['id', 'mediaId'])->name('listings.media.content');

Route::post('/sandbox/payment-callback', SandboxPaymentCallbackController::class)
    ->name('sandbox.payment-callback');

Route::middleware(['auth:sanctum', 'account.active'])->get('/user', function (Request $request) {
    return $request->user()->load('roles');
});

Route::post('/user/roles/caregiver', EnableCaregiverRoleController::class)
    ->middleware(['auth:sanctum', 'account.active', 'throttle:10,1'])
    ->name('user.roles.caregiver');

Route::middleware(['auth:sanctum', 'account.active'])->group(function () {
    Route::get('/bookings', [BookingQueryController::class, 'index']);
    Route::get('/bookings/{id}', [BookingQueryController::class, 'show'])->whereNumber('id');
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
    Route::get('/reports/mine', [ReportController::class, 'mine'])->name('reports.mine');
});

Route::middleware(['auth:sanctum', 'account.active', 'role:OWNER'])->group(function () {
    Route::post('/bookings/quote', [BookingController::class, 'quote'])->name('bookings.quote');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::post('/bookings/{id}/sandbox-payments', StartSandboxPaymentController::class)
        ->whereNumber('id')
        ->name('bookings.sandbox-payments.store');
    Route::post('/bookings/{id}/sandbox-payments/{attemptId}/simulate', SimulateSandboxPaymentController::class)
        ->whereNumber(['id', 'attemptId'])
        ->name('bookings.sandbox-payments.simulate');
    Route::post('/bookings/{id}/confirm-completion', ConfirmBookingCompletionController::class)
        ->whereNumber('id')
        ->name('bookings.confirm-completion');

    Route::get('/pets', [PetController::class, 'index'])->name('pets.index');
    Route::post('/pets', [PetController::class, 'store'])->name('pets.store');
    Route::get('/pets/{pet}', [PetController::class, 'show'])->name('pets.show');
    Route::patch('/pets/{pet}', [PetController::class, 'update'])->name('pets.update');
    Route::patch('/pets/{pet}/archive', [PetController::class, 'archive'])->name('pets.archive');
    Route::patch('/pets/{pet}/activate', [PetController::class, 'activate'])->name('pets.activate');
    Route::delete('/pets/{pet}', [PetController::class, 'destroy'])->name('pets.destroy');
    Route::post('/bookings/{id}/reviews', StoreBookingReviewController::class)
        ->whereNumber('id')->name('bookings.reviews.store');
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
    Route::get('/reports', [AdminReportController::class, 'index'])->name('admin.reports.index');
    Route::get('/reports/{id}', [AdminReportController::class, 'show'])
        ->whereNumber('id')->name('admin.reports.show');
    Route::get('/reports/{id}/evidence', [AdminReportController::class, 'evidence'])
        ->whereNumber('id')->name('admin.reports.evidence');
    Route::post('/reports/{id}/status', [AdminReportController::class, 'changeStatus'])
        ->whereNumber('id')->name('admin.reports.status');
    Route::post('/reports/{id}/booking-decision', [AdminReportController::class, 'bookingDecision'])
        ->whereNumber('id')->name('admin.reports.booking-decision');
    Route::get('/reports/{id}/history', [AdminReportController::class, 'history'])
        ->whereNumber('id')->name('admin.reports.history');
    Route::post('/reports/{id}/moderation', [AdminReportController::class, 'moderate'])
        ->whereNumber('id')->name('admin.reports.moderation');

    Route::get('/booking-cancellations', [BookingCancellationQueueController::class, 'index']);
    Route::post('/booking-cancellations/{id}/retry', [BookingCancellationQueueController::class, 'retry'])->whereNumber('id');
    Route::get(
        '/booking-completion-reviews',
        [BookingCompletionReviewController::class, 'index'],
    );

    Route::post(
        '/booking-completion-reviews/{id}/complete',
        [BookingCompletionReviewController::class, 'complete'],
    )->whereNumber('id');
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

Route::post('/bookings/{id}/cancel', CancelBookingController::class)
    ->middleware(['auth:sanctum', 'account.active'])
    ->whereNumber('id')
    ->name('bookings.cancel');

Route::post('/bookings/{id}/accept', AcceptBookingController::class)
    ->middleware(['auth:sanctum', 'account.active', 'role:CAREGIVER'])
    ->whereNumber('id')
    ->name('bookings.accept');

Route::post('/bookings/{id}/start', [BookingCareLifecycleController::class, 'start'])
    ->middleware(['auth:sanctum', 'account.active', 'role:CAREGIVER'])
    ->whereNumber('id')
    ->name('bookings.start');

Route::post('/bookings/{id}/request-completion', [BookingCareLifecycleController::class, 'requestCompletion'])
    ->middleware(['auth:sanctum', 'account.active', 'role:CAREGIVER'])
    ->whereNumber('id')
    ->name('bookings.request-completion');

Route::middleware(['auth:sanctum', 'account.active'])->group(function () {
    Route::get('/bookings/{id}/care-updates', [CareUpdateController::class, 'index'])
        ->whereNumber('id')->name('bookings.care-updates.index');
    Route::post('/bookings/{id}/care-updates', [CareUpdateController::class, 'store'])
        ->whereNumber('id')->name('bookings.care-updates.store');
    Route::get('/bookings/{id}/care-updates/{careUpdateId}/image', [CareUpdateController::class, 'image'])
        ->whereNumber(['id', 'careUpdateId'])->middleware('signed')->name('bookings.care-updates.image');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/bookings/{id}/messages', [BookingMessageController::class, 'index'])
        ->whereNumber('id')->name('bookings.messages.index');
    Route::post('/bookings/{id}/messages', [BookingMessageController::class, 'store'])
        ->whereNumber('id')->name('bookings.messages.store');
    Route::get('/bookings/{id}/messages/{messageId}/attachment/{mediaId?}', [BookingMessageController::class, 'attachment'])
        ->whereNumber(['id', 'messageId', 'mediaId'])->name('bookings.messages.attachment');
});
