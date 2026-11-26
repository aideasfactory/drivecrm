<?php

use App\Http\Controllers\Settings\LearnerTermsController;
use App\Http\Controllers\Settings\LowAvailabilityController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use App\Http\Middleware\EnsureOwner;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('settings/profile/picture', [ProfileController::class, 'updateProfilePicture'])->name('profile.picture.update');
    Route::delete('settings/profile/picture', [ProfileController::class, 'deleteProfilePicture'])->name('profile.picture.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});

Route::middleware(['auth', 'verified', EnsureOwner::class])->group(function () {
    Route::get('settings/low-availability', [LowAvailabilityController::class, 'edit'])
        ->name('low-availability-settings.edit');
    Route::put('settings/low-availability', [LowAvailabilityController::class, 'update'])
        ->name('low-availability-settings.update');

    Route::get('settings/learner-terms', [LearnerTermsController::class, 'edit'])
        ->name('learner-terms-settings.edit');
    Route::put('settings/learner-terms/draft', [LearnerTermsController::class, 'saveDraft'])
        ->name('learner-terms-settings.draft.save');
    Route::delete('settings/learner-terms/draft', [LearnerTermsController::class, 'destroyDraft'])
        ->name('learner-terms-settings.draft.destroy');
    Route::post('settings/learner-terms/publish', [LearnerTermsController::class, 'publish'])
        ->name('learner-terms-settings.publish');
    Route::post('settings/learner-terms/preview', [LearnerTermsController::class, 'preview'])
        ->name('learner-terms-settings.preview');
});
