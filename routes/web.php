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
use App\Http\Middleware\RestrictInstructor;
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

Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', RestrictInstructor::class])
    ->name('dashboard');

// Main Application Routes
Route::middleware(['auth', 'verified', RestrictInstructor::class])->group(function () {
    Route::get('/instructors', [\App\Http\Controllers\InstructorController::class, 'index'])
        ->name('instructors.index');
    Route::post('/instructors', [\App\Http\Controllers\InstructorController::class, 'store'])
        ->name('instructors.store');
    Route::get('/instructors/csv-template', [\App\Http\Controllers\InstructorController::class, 'downloadCsvTemplate'])
        ->name('instructors.csv-template');
    Route::post('/instructors/import-csv', [\App\Http\Controllers\InstructorController::class, 'importCsv'])
        ->name('instructors.import-csv');
    Route::get('/instructors/{instructor}', [\App\Http\Controllers\InstructorController::class, 'show'])
        ->name('instructors.show');
    Route::put('/instructors/{instructor}', [\App\Http\Controllers\InstructorController::class, 'update'])
        ->name('instructors.update');
    Route::post('/instructors/{instructor}/profile-picture', [\App\Http\Controllers\InstructorController::class, 'updateProfilePicture'])
        ->name('instructors.profile-picture.update');
    Route::delete('/instructors/{instructor}/profile-picture', [\App\Http\Controllers\InstructorController::class, 'deleteProfilePicture'])
        ->name('instructors.profile-picture.destroy');
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
    Route::put('/instructors/{instructor}/calendar/items/{calendarItem}', [\App\Http\Controllers\InstructorController::class, 'updateCalendarItem'])
        ->name('instructors.calendar.items.update');
    Route::delete('/instructors/{instructor}/calendar/items/{calendarItem}', [\App\Http\Controllers\InstructorController::class, 'destroyCalendarItem'])
        ->name('instructors.calendar.items.destroy');
    Route::patch('/instructors/{instructor}/lessons/{lesson}/mileage', [\App\Http\Controllers\InstructorController::class, 'updateLessonMileage'])
        ->name('instructors.lessons.mileage.update');

    // Stripe Connect Onboarding Routes
    Route::post('/instructors/{instructor}/stripe/onboarding/start', [\App\Http\Controllers\InstructorController::class, 'startStripeOnboarding'])
        ->name('instructors.stripe.onboarding.start');
    Route::post('/instructors/{instructor}/stripe/onboarding/refresh', [\App\Http\Controllers\InstructorController::class, 'refreshStripeOnboarding'])
        ->name('instructors.stripe.onboarding.refresh');
    Route::get('/instructors/{instructor}/stripe/onboarding/return', [\App\Http\Controllers\InstructorController::class, 'returnFromStripeOnboarding'])
        ->name('instructors.stripe.onboarding.return');
    Route::get('/instructors/{instructor}/stripe/status', [\App\Http\Controllers\InstructorController::class, 'stripeStatus'])
        ->name('instructors.stripe.status');
    Route::post('/instructors/{instructor}/request-deletion', [\App\Http\Controllers\InstructorController::class, 'requestDeletion'])
        ->name('instructors.request-deletion');
    Route::get('/instructors/{instructor}/activity-logs', [\App\Http\Controllers\InstructorController::class, 'activityLogs'])
        ->name('instructors.activity-logs');
    Route::get('/instructors/{instructor}/payouts', [\App\Http\Controllers\InstructorController::class, 'payouts'])
        ->name('instructors.payouts');
    Route::get('/instructors/{instructor}/pupils', [\App\Http\Controllers\InstructorController::class, 'pupils'])
        ->name('instructors.pupils');
    Route::post('/instructors/{instructor}/broadcast-message', [\App\Http\Controllers\InstructorController::class, 'broadcastMessage'])
        ->name('instructors.broadcast-message');
    Route::post('/instructors/{instructor}/pupils', [\App\Http\Controllers\InstructorController::class, 'storePupil'])
        ->name('instructors.pupils.store');

    // Instructor Emergency Contacts
    Route::get('/instructors/{instructor}/contacts', [\App\Http\Controllers\InstructorController::class, 'contacts'])
        ->name('instructors.contacts');
    Route::post('/instructors/{instructor}/contacts', [\App\Http\Controllers\InstructorController::class, 'storeContact'])
        ->name('instructors.contacts.store');
    Route::put('/instructors/{instructor}/contacts/{contact}', [\App\Http\Controllers\InstructorController::class, 'updateContact'])
        ->name('instructors.contacts.update');
    Route::delete('/instructors/{instructor}/contacts/{contact}', [\App\Http\Controllers\InstructorController::class, 'deleteContact'])
        ->name('instructors.contacts.destroy');
    Route::patch('/instructors/{instructor}/contacts/{contact}/primary', [\App\Http\Controllers\InstructorController::class, 'setPrimaryContact'])
        ->name('instructors.contacts.primary');
    Route::put('/instructors/{instructor}/password', [\App\Http\Controllers\InstructorController::class, 'updatePassword'])
        ->name('instructors.password.update');

    // Instructor Progress Tracker (framework CRUD — axios-fed)
    Route::get('/instructors/{instructor}/progress-tracker/framework', [\App\Http\Controllers\ProgressTrackerController::class, 'framework'])
        ->name('instructors.progress-tracker.framework');
    Route::post('/instructors/{instructor}/progress-tracker/categories', [\App\Http\Controllers\ProgressTrackerController::class, 'storeCategory'])
        ->name('instructors.progress-tracker.categories.store');
    Route::put('/instructors/{instructor}/progress-tracker/categories/{category}', [\App\Http\Controllers\ProgressTrackerController::class, 'updateCategory'])
        ->name('instructors.progress-tracker.categories.update');
    Route::delete('/instructors/{instructor}/progress-tracker/categories/{category}', [\App\Http\Controllers\ProgressTrackerController::class, 'destroyCategory'])
        ->name('instructors.progress-tracker.categories.destroy');
    Route::post('/instructors/{instructor}/progress-tracker/categories/reorder', [\App\Http\Controllers\ProgressTrackerController::class, 'reorderCategories'])
        ->name('instructors.progress-tracker.categories.reorder');
    Route::post('/instructors/{instructor}/progress-tracker/categories/{category}/subcategories', [\App\Http\Controllers\ProgressTrackerController::class, 'storeSubcategory'])
        ->name('instructors.progress-tracker.subcategories.store');
    Route::put('/instructors/{instructor}/progress-tracker/subcategories/{subcategory}', [\App\Http\Controllers\ProgressTrackerController::class, 'updateSubcategory'])
        ->name('instructors.progress-tracker.subcategories.update');
    Route::delete('/instructors/{instructor}/progress-tracker/subcategories/{subcategory}', [\App\Http\Controllers\ProgressTrackerController::class, 'destroySubcategory'])
        ->name('instructors.progress-tracker.subcategories.destroy');
    Route::post('/instructors/{instructor}/progress-tracker/categories/{category}/subcategories/reorder', [\App\Http\Controllers\ProgressTrackerController::class, 'reorderSubcategories'])
        ->name('instructors.progress-tracker.subcategories.reorder');

    // Instructor Finances
    Route::get('/instructors/{instructor}/finances', [\App\Http\Controllers\InstructorController::class, 'finances'])
        ->name('instructors.finances');
    Route::post('/instructors/{instructor}/finances', [\App\Http\Controllers\InstructorController::class, 'storeFinance'])
        ->name('instructors.finances.store');
    Route::put('/instructors/{instructor}/finances/{finance}', [\App\Http\Controllers\InstructorController::class, 'updateFinance'])
        ->name('instructors.finances.update');
    Route::delete('/instructors/{instructor}/finances/{finance}', [\App\Http\Controllers\InstructorController::class, 'destroyFinance'])
        ->name('instructors.finances.destroy');

    Route::get('/packages', [\App\Http\Controllers\PackageController::class, 'index'])
        ->name('packages.index');
    Route::post('/packages', [\App\Http\Controllers\PackageController::class, 'store'])
        ->name('packages.store');
    Route::put('/packages/{package}', [\App\Http\Controllers\PackageController::class, 'update'])
        ->name('packages.update');
    Route::delete('/packages/{package}', [\App\Http\Controllers\PackageController::class, 'destroy'])
        ->name('packages.destroy');

    // Discount Codes
    Route::get('/discount-codes', [\App\Http\Controllers\DiscountCodeController::class, 'index'])
        ->name('discount-codes.index');
    Route::post('/discount-codes', [\App\Http\Controllers\DiscountCodeController::class, 'store'])
        ->name('discount-codes.store');
    Route::delete('/discount-codes/{discountCode}', [\App\Http\Controllers\DiscountCodeController::class, 'destroy'])
        ->name('discount-codes.destroy');

    Route::get('/pupils', [\App\Http\Controllers\PupilController::class, 'index'])
        ->name('pupils.index');
    Route::get('/students/{student}', [\App\Http\Controllers\PupilController::class, 'show'])
        ->name('students.show');
    Route::get('/students/{student}/activity-logs', [\App\Http\Controllers\PupilController::class, 'activityLogs'])
        ->name('students.activity-logs');

    // Student Emergency Contacts
    Route::get('/students/{student}/contacts', [\App\Http\Controllers\PupilController::class, 'contacts'])
        ->name('students.contacts');
    Route::post('/students/{student}/contacts', [\App\Http\Controllers\PupilController::class, 'storeContact'])
        ->name('students.contacts.store');
    Route::put('/students/{student}/contacts/{contact}', [\App\Http\Controllers\PupilController::class, 'updateContact'])
        ->name('students.contacts.update');
    Route::delete('/students/{student}/contacts/{contact}', [\App\Http\Controllers\PupilController::class, 'deleteContact'])
        ->name('students.contacts.destroy');
    Route::patch('/students/{student}/contacts/{contact}/primary', [\App\Http\Controllers\PupilController::class, 'setPrimaryContact'])
        ->name('students.contacts.primary');
    Route::post('/students/{student}/contacts/auto-create', [\App\Http\Controllers\PupilController::class, 'autoCreateEmergencyContact'])
        ->name('students.contacts.auto-create');

    // Student Notes
    Route::get('/students/{student}/notes', [\App\Http\Controllers\PupilController::class, 'notes'])
        ->name('students.notes');
    Route::post('/students/{student}/notes', [\App\Http\Controllers\PupilController::class, 'storeNote'])
        ->name('students.notes.store');
    Route::delete('/students/{student}/notes/{note}', [\App\Http\Controllers\PupilController::class, 'deleteNote'])
        ->name('students.notes.destroy');

    // Student Messages
    Route::get('/students/{student}/messages', [\App\Http\Controllers\PupilController::class, 'messages'])
        ->name('students.messages');
    Route::post('/students/{student}/messages', [\App\Http\Controllers\PupilController::class, 'sendMessage'])
        ->name('students.messages.store');

    // Student Payments
    Route::get('/students/{student}/payments', [\App\Http\Controllers\PupilController::class, 'payments'])
        ->name('students.payments');

    // Student Orders
    Route::get('/students/{student}/available-slots', [\App\Http\Controllers\PupilController::class, 'availableSlots'])
        ->name('students.available-slots');
    Route::post('/students/{student}/orders', [\App\Http\Controllers\PupilController::class, 'storeOrder'])
        ->name('students.orders.store');

    // Student Lessons
    Route::get('/students/{student}/lessons', [\App\Http\Controllers\PupilController::class, 'lessons'])
        ->name('students.lessons');
    Route::post('/students/{student}/lessons/{lesson}/sign-off', [\App\Http\Controllers\PupilController::class, 'signOffLesson'])
        ->name('students.lessons.sign-off');
    Route::post('/students/{student}/lessons/{lesson}/resend-invoice', [\App\Http\Controllers\PupilController::class, 'resendLessonInvoice'])
        ->name('students.lessons.resend-invoice');

    // Student Pickup Points
    Route::get('/students/{student}/pickup-points', [\App\Http\Controllers\PupilController::class, 'pickupPoints'])
        ->name('students.pickup-points');
    Route::post('/students/{student}/pickup-points', [\App\Http\Controllers\PupilController::class, 'storePickupPoint'])
        ->name('students.pickup-points.store');
    Route::put('/students/{student}/pickup-points/{pickupPoint}', [\App\Http\Controllers\PupilController::class, 'updatePickupPoint'])
        ->name('students.pickup-points.update');
    Route::delete('/students/{student}/pickup-points/{pickupPoint}', [\App\Http\Controllers\PupilController::class, 'deletePickupPoint'])
        ->name('students.pickup-points.destroy');
    Route::patch('/students/{student}/pickup-points/{pickupPoint}/default', [\App\Http\Controllers\PupilController::class, 'setDefaultPickupPoint'])
        ->name('students.pickup-points.default');

    // Student Checklist
    Route::get('/students/{student}/checklist', [\App\Http\Controllers\PupilController::class, 'checklist'])
        ->name('students.checklist');
    Route::patch('/students/{student}/checklist/{checklistItem}', [\App\Http\Controllers\PupilController::class, 'toggleChecklistItem'])
        ->name('students.checklist.toggle');

    // Student Status & Management
    Route::patch('/students/{student}/status', [\App\Http\Controllers\PupilController::class, 'updateStatus'])
        ->name('students.status.update');
    Route::delete('/students/{student}/remove', [\App\Http\Controllers\PupilController::class, 'removeStudent'])
        ->name('students.remove');
    Route::put('/students/{student}/password', [\App\Http\Controllers\PupilController::class, 'updatePassword'])
        ->name('students.password.update');

    Route::get('/teams', [\App\Http\Controllers\TeamController::class, 'index'])
        ->name('teams.index');
    Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index'])
        ->name('reports.index');
    // Push Notifications (Owner Only)
    Route::middleware([\App\Http\Middleware\EnsureOwner::class])->group(function () {
        Route::get('/push-notifications', [\App\Http\Controllers\PushNotificationController::class, 'index'])
            ->name('push-notifications.index');
        Route::post('/push-notifications', [\App\Http\Controllers\PushNotificationController::class, 'store'])
            ->name('push-notifications.store');
    });

    // Support Messages (Owner Only)
    Route::middleware([\App\Http\Middleware\EnsureOwner::class])->group(function () {
        Route::get('/support-messages', [\App\Http\Controllers\SupportMessagesController::class, 'index'])
            ->name('support-messages.index');
        Route::post('/support-messages/{user}', [\App\Http\Controllers\SupportMessagesController::class, 'store'])
            ->name('support-messages.store');
    });

    // Resources (Owner Only)
    Route::middleware([\App\Http\Middleware\EnsureOwner::class])->group(function () {
        Route::get('/resources', [\App\Http\Controllers\ResourceController::class, 'index'])
            ->name('resources.index');
        Route::get('/resources/folders/root/contents', [\App\Http\Controllers\ResourceController::class, 'getFolderContents'])
            ->name('resources.folders.root-contents');
        Route::get('/resources/folders/{folder}/contents', [\App\Http\Controllers\ResourceController::class, 'getFolderContents'])
            ->name('resources.folders.contents');
        Route::post('/resources/folders', [\App\Http\Controllers\ResourceController::class, 'storeFolder'])
            ->name('resources.folders.store');
        Route::put('/resources/folders/{folder}', [\App\Http\Controllers\ResourceController::class, 'updateFolder'])
            ->name('resources.folders.update');
        Route::delete('/resources/folders/{folder}', [\App\Http\Controllers\ResourceController::class, 'destroyFolder'])
            ->name('resources.folders.destroy');
        Route::post('/resources/files', [\App\Http\Controllers\ResourceController::class, 'storeResource'])
            ->name('resources.files.store');
        Route::get('/resources/files/{resource}/url', [\App\Http\Controllers\ResourceController::class, 'getFileUrl'])
            ->name('resources.files.url');
        Route::put('/resources/files/{resource}', [\App\Http\Controllers\ResourceController::class, 'updateResource'])
            ->name('resources.files.update');
        Route::delete('/resources/files/{resource}', [\App\Http\Controllers\ResourceController::class, 'destroyResource'])
            ->name('resources.files.destroy');
        Route::get('/resources/csv-template', [\App\Http\Controllers\ResourceController::class, 'downloadCsvTemplate'])
            ->name('resources.csv-template');
        Route::post('/resources/import-csv', [\App\Http\Controllers\ResourceController::class, 'importCsv'])
            ->name('resources.import-csv');
    });

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

// Resource email view (signed URL — no auth required, students click from email)
Route::get('/resources/view/{resource}', [\App\Http\Controllers\ResourceController::class, 'emailView'])
    ->name('resources.email-view');

// Stripe Webhook (must be outside auth middleware)
Route::post('/webhook/stripe', [\App\Http\Controllers\WebhookController::class, 'handle'])
    ->name('webhook.stripe');

// Payment-link checkout return (instructor-sent Stripe payment links).
// Unauthenticated — the student is clicking through from an email and has
// no app session. Security comes from matching the Stripe session_id against
// the order's stored stripe_checkout_session_id.
Route::get('/orders/{order}/payment-link/success', [\App\Http\Controllers\PaymentLinkCheckoutController::class, 'success'])
    ->name('payment-link.checkout.success');
Route::get('/orders/{order}/payment-link/cancel', [\App\Http\Controllers\PaymentLinkCheckoutController::class, 'cancel'])
    ->name('payment-link.checkout.cancel');

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
