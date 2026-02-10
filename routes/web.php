<?php

use App\Enums\UserRole;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Onboarding\StepFiveController;
use App\Http\Controllers\Onboarding\StepFourController;
use App\Http\Controllers\Onboarding\StepOneController;
use App\Http\Controllers\Onboarding\StepSixController;
use App\Http\Controllers\Onboarding\StepThreeController;
use App\Http\Controllers\Onboarding\StepTwoController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\ValidateEnquiryUuid;
use App\Http\Middleware\ValidateStepAccess;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Main Application Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/instructors', [\App\Http\Controllers\InstructorController::class, 'index'])
        ->name('instructors.index');
    Route::post('/instructors', [\App\Http\Controllers\InstructorController::class, 'store'])
        ->name('instructors.store');
    Route::get('/instructors/{instructor}', [\App\Http\Controllers\InstructorController::class, 'show'])
        ->name('instructors.show');
    Route::put('/instructors/{instructor}', [\App\Http\Controllers\InstructorController::class, 'update'])
        ->name('instructors.update');
    Route::get('/instructors/{instructor}/packages', [\App\Http\Controllers\InstructorController::class, 'packages'])
        ->name('instructors.packages');
    Route::post('/instructors/{instructor}/packages', [\App\Http\Controllers\InstructorController::class, 'createPackage'])
        ->name('instructors.packages.store');
    Route::get('/instructors/{instructor}/locations', [\App\Http\Controllers\InstructorController::class, 'locations'])
        ->name('instructors.locations');
    Route::post('/instructors/{instructor}/locations', [\App\Http\Controllers\InstructorController::class, 'storeLocation'])
        ->name('instructors.locations.store');
    Route::delete('/instructors/{instructor}/locations/{location}', [\App\Http\Controllers\InstructorController::class, 'destroyLocation'])
        ->name('instructors.locations.destroy');
    Route::get('/instructors/{instructor}/calendar', [\App\Http\Controllers\InstructorController::class, 'calendar'])
        ->name('instructors.calendar');
    Route::post('/instructors/{instructor}/calendar/items', [\App\Http\Controllers\InstructorController::class, 'storeCalendarItem'])
        ->name('instructors.calendar.items.store');
    Route::delete('/instructors/{instructor}/calendar/items/{calendarItem}', [\App\Http\Controllers\InstructorController::class, 'destroyCalendarItem'])
        ->name('instructors.calendar.items.destroy');

    // Stripe Connect Onboarding Routes
    Route::post('/instructors/{instructor}/stripe/onboarding/start', [\App\Http\Controllers\InstructorController::class, 'startStripeOnboarding'])
        ->name('instructors.stripe.onboarding.start');
    Route::post('/instructors/{instructor}/stripe/onboarding/refresh', [\App\Http\Controllers\InstructorController::class, 'refreshStripeOnboarding'])
        ->name('instructors.stripe.onboarding.refresh');
    Route::get('/instructors/{instructor}/stripe/onboarding/return', [\App\Http\Controllers\InstructorController::class, 'returnFromStripeOnboarding'])
        ->name('instructors.stripe.onboarding.return');
    Route::get('/instructors/{instructor}/stripe/status', [\App\Http\Controllers\InstructorController::class, 'stripeStatus'])
        ->name('instructors.stripe.status');

    Route::put('/packages/{package}', [\App\Http\Controllers\PackageController::class, 'update'])
        ->name('packages.update');
    Route::get('/pupils', [\App\Http\Controllers\PupilController::class, 'index'])
        ->name('pupils.index');
    Route::get('/teams', [\App\Http\Controllers\TeamController::class, 'index'])
        ->name('teams.index');
    Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index'])
        ->name('reports.index');
    Route::get('/resources', [\App\Http\Controllers\ResourceController::class, 'index'])
        ->name('resources.index');
    Route::get('/apps', [\App\Http\Controllers\AppController::class, 'index'])
        ->name('apps.index');
});

// Onboarding Routes (Public - no auth required)
// Entry point — creates new enquiry
Route::get('/onboarding', [OnboardingController::class, 'start'])
    ->name('onboarding.start');

// Step routes — protected by middleware
Route::prefix('/onboarding/{uuid}')
    ->middleware([ValidateEnquiryUuid::class, ValidateStepAccess::class])
    ->group(function () {

        // Step 1: Initial Details
        Route::get('/step/1', [StepOneController::class, 'show'])
            ->name('onboarding.step1');
        Route::post('/step/1', [StepOneController::class, 'store'])
            ->name('onboarding.step1.store');

        // Step 2: Instructor Selection
        Route::get('/step/2', [StepTwoController::class, 'show'])
            ->name('onboarding.step2');
        Route::post('/step/2', [StepTwoController::class, 'store'])
            ->name('onboarding.step2.store');

        // Step 3: Package Selection
        Route::get('/step/3', [StepThreeController::class, 'show'])
            ->name('onboarding.step3');
        Route::post('/step/3', [StepThreeController::class, 'store'])
            ->name('onboarding.step3.store');

        // Step 4: Date & Time Selection
        Route::get('/step/4', [StepFourController::class, 'show'])
            ->name('onboarding.step4');
        Route::post('/step/4', [StepFourController::class, 'store'])
            ->name('onboarding.step4.store');

        // Step 5: Review & Learner Details
        Route::get('/step/5', [StepFiveController::class, 'show'])
            ->name('onboarding.step5');
        Route::post('/step/5', [StepFiveController::class, 'store'])
            ->name('onboarding.step5.store');

        // Step 6: Payment
        Route::get('/step/6', [StepSixController::class, 'show'])
            ->name('onboarding.step6');
        Route::post('/step/6', [StepSixController::class, 'store'])
            ->name('onboarding.step6.store');

        // Checkout callbacks
        Route::get('/checkout/success', [StepSixController::class, 'success'])
            ->name('onboarding.checkout.success');
        Route::get('/checkout/cancel', [StepSixController::class, 'cancel'])
            ->name('onboarding.checkout.cancel');

        // Completion
        Route::get('/complete', [OnboardingController::class, 'complete'])
            ->name('onboarding.complete');
    });

// Dynamic data route (for calendar refresh without full page reload)
Route::get('/onboarding/{uuid}/instructor/{instructor}/availability', [StepFourController::class, 'availability'])
    ->middleware([ValidateEnquiryUuid::class])
    ->name('onboarding.instructor.availability');

// Stripe Webhook (must be outside auth middleware)
Route::post('/webhook/stripe', [\App\Http\Controllers\WebhookController::class, 'handle'])
    ->name('webhook.stripe');

// Route::get('/dashboard', function () {
//     $user = auth()->user();

//     return match ($user->role) {
//         UserRole::OWNER => redirect()->route('owner.packages.index'),
//         UserRole::INSTRUCTOR => redirect()->route('instructor.dashboard'),
//         UserRole::STUDENT => redirect()->route('student.packages.index'),
//     };
// })->middleware(['auth', 'verified'])->name('dashboard');

// Route::middleware('auth')->group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
// });

// Owner Routes
// Route::middleware(['auth', 'owner'])->prefix('owner')->name('owner.')->group(function () {
//     Route::resource('packages', \App\Http\Controllers\Owner\PackageController::class)
//         ->only(['index', 'create', 'store', 'show', 'destroy']);

//     Route::resource('instructors', \App\Http\Controllers\Owner\InstructorController::class)
//         ->only(['index', 'create', 'store']);
// });

// Instructor Routes
// Route::middleware(['auth', 'instructor'])->prefix('instructor')->name('instructor.')->group(function () {
//     Route::get('/dashboard', [\App\Http\Controllers\Instructor\DashboardController::class, 'index'])
//         ->name('dashboard');

//     // Onboarding
//     Route::prefix('onboarding')->name('onboarding.')->group(function () {
//         Route::get('/', [\App\Http\Controllers\Instructor\OnboardingController::class, 'index'])
//             ->name('index');
//         Route::post('/start', [\App\Http\Controllers\Instructor\OnboardingController::class, 'start'])
//             ->name('start');
//         Route::get('/refresh', [\App\Http\Controllers\Instructor\OnboardingController::class, 'refresh'])
//             ->name('refresh');
//         Route::get('/return', [\App\Http\Controllers\Instructor\OnboardingController::class, 'return'])
//             ->name('return');
//     });

//     // Bespoke Packages
//     Route::resource('packages', \App\Http\Controllers\Instructor\PackageController::class)
//         ->only(['index', 'create', 'store', 'show', 'destroy']);

//     // Orders
//     Route::get('/orders/{order}', [\App\Http\Controllers\Instructor\OrderController::class, 'show'])
//         ->name('orders.show');

//     // Lessons
//     Route::post('/lessons/{lesson}/complete', [\App\Http\Controllers\Instructor\LessonController::class, 'complete'])
//         ->name('lessons.complete');
// });

// Student Routes
// Route::middleware(['auth', 'student'])->prefix('student')->name('student.')->group(function () {
//     // Package Browsing
//     Route::get('/packages', [\App\Http\Controllers\Student\PackageController::class, 'index'])
//         ->name('packages.index');
//     Route::get('/packages/{package}', [\App\Http\Controllers\Student\PackageController::class, 'show'])
//         ->name('packages.show');

//     // Checkout
//     Route::post('/checkout/{package}', [\App\Http\Controllers\Student\CheckoutController::class, 'create'])
//         ->name('checkout.create');
//     Route::get('/checkout/success/{order}', [\App\Http\Controllers\Student\CheckoutController::class, 'success'])
//         ->name('checkout.success');
//     Route::get('/checkout/cancel/{order}', [\App\Http\Controllers\Student\CheckoutController::class, 'cancel'])
//         ->name('checkout.cancel');

//     // Orders
//     Route::get('/orders', [\App\Http\Controllers\Student\OrderController::class, 'index'])
//         ->name('orders.index');
//     Route::get('/orders/{order}', [\App\Http\Controllers\Student\OrderController::class, 'show'])
//         ->name('orders.show');
// });

// require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
