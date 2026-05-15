<?php

use App\Enums\UserRole;
use App\Http\Controllers\AppController;
use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Booking\StepOneController as BookingStepOneController;
use App\Http\Controllers\Booking\StepTwoController as BookingStepTwoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountCodeController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\GetAppController;
use App\Http\Controllers\Hmrc\HmrcConnectionController;
use App\Http\Controllers\Hmrc\HmrcFraudHeadersController;
use App\Http\Controllers\Hmrc\HmrcHelloWorldController;
use App\Http\Controllers\Hmrc\Itsa\FinalDeclarationController;
use App\Http\Controllers\Hmrc\Itsa\ItsaController;
use App\Http\Controllers\Hmrc\Vat\VatController;
use App\Http\Controllers\InstructorController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Onboarding\StepFiveController;
use App\Http\Controllers\Onboarding\StepFourController;
use App\Http\Controllers\Onboarding\StepOneController;
use App\Http\Controllers\Onboarding\StepSixController;
use App\Http\Controllers\Onboarding\StepThreeController;
use App\Http\Controllers\Onboarding\StepTwoController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymentLinkCheckoutController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgressTrackerController;
use App\Http\Controllers\PupilController;
use App\Http\Controllers\PushNotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\StudentTransferController;
use App\Http\Controllers\SupportMessagesController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\EnsureInstructor;
use App\Http\Middleware\EnsureMtdEnrolled;
use App\Http\Middleware\EnsureOwner;
use App\Http\Middleware\RestrictInstructor;
use App\Http\Middleware\ValidateBookingEnquiryUuid;
use App\Http\Middleware\ValidateBookingStepAccess;
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

Route::get('/no-access', fn () => Inertia::render('NoAccess'))
    ->name('no-access');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', RestrictInstructor::class])
    ->name('dashboard');

// Main Application Routes
Route::middleware(['auth', 'verified', RestrictInstructor::class])->group(function () {
    Route::get('/instructors', [InstructorController::class, 'index'])
        ->name('instructors.index');
    Route::post('/instructors', [InstructorController::class, 'store'])
        ->name('instructors.store');
    Route::get('/instructors/csv-template', [InstructorController::class, 'downloadCsvTemplate'])
        ->name('instructors.csv-template');
    Route::post('/instructors/import-csv', [InstructorController::class, 'importCsv'])
        ->name('instructors.import-csv');
    Route::get('/instructors/{instructor}', [InstructorController::class, 'show'])
        ->name('instructors.show');
    Route::put('/instructors/{instructor}', [InstructorController::class, 'update'])
        ->name('instructors.update');
    Route::post('/instructors/{instructor}/profile-picture', [InstructorController::class, 'updateProfilePicture'])
        ->name('instructors.profile-picture.update');
    Route::delete('/instructors/{instructor}/profile-picture', [InstructorController::class, 'deleteProfilePicture'])
        ->name('instructors.profile-picture.destroy');
    Route::get('/instructors/{instructor}/packages', [InstructorController::class, 'packages'])
        ->name('instructors.packages');
    Route::post('/instructors/{instructor}/packages', [InstructorController::class, 'createPackage'])
        ->name('instructors.packages.store');
    Route::get('/instructors/{instructor}/locations', [InstructorController::class, 'locations'])
        ->name('instructors.locations');
    Route::post('/instructors/{instructor}/locations', [InstructorController::class, 'storeLocation'])
        ->name('instructors.locations.store');
    Route::delete('/instructors/{instructor}/locations/{location}', [InstructorController::class, 'destroyLocation'])
        ->name('instructors.locations.destroy');
    Route::get('/instructors/{instructor}/calendar', [InstructorController::class, 'calendar'])
        ->name('instructors.calendar');
    Route::post('/instructors/{instructor}/calendar/items', [InstructorController::class, 'storeCalendarItem'])
        ->name('instructors.calendar.items.store');
    Route::put('/instructors/{instructor}/calendar/items/{calendarItem}', [InstructorController::class, 'updateCalendarItem'])
        ->name('instructors.calendar.items.update');
    Route::delete('/instructors/{instructor}/calendar/items/{calendarItem}', [InstructorController::class, 'destroyCalendarItem'])
        ->name('instructors.calendar.items.destroy');
    Route::patch('/instructors/{instructor}/lessons/{lesson}/mileage', [InstructorController::class, 'updateLessonMileage'])
        ->name('instructors.lessons.mileage.update');

    // Stripe Connect Onboarding Routes
    Route::post('/instructors/{instructor}/stripe/onboarding/start', [InstructorController::class, 'startStripeOnboarding'])
        ->name('instructors.stripe.onboarding.start');
    Route::post('/instructors/{instructor}/stripe/onboarding/refresh', [InstructorController::class, 'refreshStripeOnboarding'])
        ->name('instructors.stripe.onboarding.refresh');
    Route::get('/instructors/{instructor}/stripe/onboarding/return', [InstructorController::class, 'returnFromStripeOnboarding'])
        ->name('instructors.stripe.onboarding.return');
    Route::get('/instructors/{instructor}/stripe/status', [InstructorController::class, 'stripeStatus'])
        ->name('instructors.stripe.status');
    Route::post('/instructors/{instructor}/request-deletion', [InstructorController::class, 'requestDeletion'])
        ->name('instructors.request-deletion');
    Route::get('/instructors/{instructor}/activity-logs', [InstructorController::class, 'activityLogs'])
        ->name('instructors.activity-logs');
    Route::get('/instructors/{instructor}/payouts', [InstructorController::class, 'payouts'])
        ->name('instructors.payouts');
    Route::get('/instructors/{instructor}/pupils', [InstructorController::class, 'pupils'])
        ->name('instructors.pupils');
    Route::post('/instructors/{instructor}/broadcast-message', [InstructorController::class, 'broadcastMessage'])
        ->name('instructors.broadcast-message');
    Route::post('/instructors/{instructor}/pupils', [InstructorController::class, 'storePupil'])
        ->name('instructors.pupils.store');

    // Instructor Emergency Contacts
    Route::get('/instructors/{instructor}/contacts', [InstructorController::class, 'contacts'])
        ->name('instructors.contacts');
    Route::post('/instructors/{instructor}/contacts', [InstructorController::class, 'storeContact'])
        ->name('instructors.contacts.store');
    Route::put('/instructors/{instructor}/contacts/{contact}', [InstructorController::class, 'updateContact'])
        ->name('instructors.contacts.update');
    Route::delete('/instructors/{instructor}/contacts/{contact}', [InstructorController::class, 'deleteContact'])
        ->name('instructors.contacts.destroy');
    Route::patch('/instructors/{instructor}/contacts/{contact}/primary', [InstructorController::class, 'setPrimaryContact'])
        ->name('instructors.contacts.primary');
    Route::put('/instructors/{instructor}/password', [InstructorController::class, 'updatePassword'])
        ->name('instructors.password.update');

    // Instructor Progress Tracker (framework CRUD — axios-fed)
    Route::get('/instructors/{instructor}/progress-tracker/framework', [ProgressTrackerController::class, 'framework'])
        ->name('instructors.progress-tracker.framework');
    Route::post('/instructors/{instructor}/progress-tracker/categories', [ProgressTrackerController::class, 'storeCategory'])
        ->name('instructors.progress-tracker.categories.store');
    Route::put('/instructors/{instructor}/progress-tracker/categories/{category}', [ProgressTrackerController::class, 'updateCategory'])
        ->name('instructors.progress-tracker.categories.update');
    Route::delete('/instructors/{instructor}/progress-tracker/categories/{category}', [ProgressTrackerController::class, 'destroyCategory'])
        ->name('instructors.progress-tracker.categories.destroy');
    Route::post('/instructors/{instructor}/progress-tracker/categories/reorder', [ProgressTrackerController::class, 'reorderCategories'])
        ->name('instructors.progress-tracker.categories.reorder');
    Route::post('/instructors/{instructor}/progress-tracker/categories/{category}/subcategories', [ProgressTrackerController::class, 'storeSubcategory'])
        ->name('instructors.progress-tracker.subcategories.store');
    Route::put('/instructors/{instructor}/progress-tracker/subcategories/{subcategory}', [ProgressTrackerController::class, 'updateSubcategory'])
        ->name('instructors.progress-tracker.subcategories.update');
    Route::delete('/instructors/{instructor}/progress-tracker/subcategories/{subcategory}', [ProgressTrackerController::class, 'destroySubcategory'])
        ->name('instructors.progress-tracker.subcategories.destroy');
    Route::post('/instructors/{instructor}/progress-tracker/categories/{category}/subcategories/reorder', [ProgressTrackerController::class, 'reorderSubcategories'])
        ->name('instructors.progress-tracker.subcategories.reorder');

    // Instructor Finances
    Route::get('/instructors/{instructor}/finances', [InstructorController::class, 'finances'])
        ->name('instructors.finances');
    Route::post('/instructors/{instructor}/finances', [InstructorController::class, 'storeFinance'])
        ->name('instructors.finances.store');
    Route::put('/instructors/{instructor}/finances/{finance}', [InstructorController::class, 'updateFinance'])
        ->name('instructors.finances.update');
    Route::delete('/instructors/{instructor}/finances/{finance}', [InstructorController::class, 'destroyFinance'])
        ->name('instructors.finances.destroy');
    Route::post('/instructors/{instructor}/finances/{finance}/receipt', [InstructorController::class, 'uploadFinanceReceipt'])
        ->name('instructors.finances.receipt.upload');
    Route::delete('/instructors/{instructor}/finances/{finance}/receipt', [InstructorController::class, 'destroyFinanceReceipt'])
        ->name('instructors.finances.receipt.destroy');

    // Instructor Mileage Logs
    Route::get('/instructors/{instructor}/mileage', [InstructorController::class, 'mileage'])
        ->name('instructors.mileage');
    Route::post('/instructors/{instructor}/mileage', [InstructorController::class, 'storeMileage'])
        ->name('instructors.mileage.store');
    Route::put('/instructors/{instructor}/mileage/{mileageLog}', [InstructorController::class, 'updateMileage'])
        ->name('instructors.mileage.update');
    Route::delete('/instructors/{instructor}/mileage/{mileageLog}', [InstructorController::class, 'destroyMileage'])
        ->name('instructors.mileage.destroy');

    Route::get('/packages', [PackageController::class, 'index'])
        ->name('packages.index');
    Route::post('/packages', [PackageController::class, 'store'])
        ->name('packages.store');
    Route::put('/packages/{package}', [PackageController::class, 'update'])
        ->name('packages.update');
    Route::delete('/packages/{package}', [PackageController::class, 'destroy'])
        ->name('packages.destroy');

    // Discount Codes
    Route::get('/discount-codes', [DiscountCodeController::class, 'index'])
        ->name('discount-codes.index');
    Route::post('/discount-codes', [DiscountCodeController::class, 'store'])
        ->name('discount-codes.store');
    Route::delete('/discount-codes/{discountCode}', [DiscountCodeController::class, 'destroy'])
        ->name('discount-codes.destroy');

    Route::get('/pupils', [PupilController::class, 'index'])
        ->name('pupils.index');
    Route::get('/students/{student}', [PupilController::class, 'show'])
        ->name('students.show');
    Route::get('/students/{student}/activity-logs', [PupilController::class, 'activityLogs'])
        ->name('students.activity-logs');

    // Student Emergency Contacts
    Route::get('/students/{student}/contacts', [PupilController::class, 'contacts'])
        ->name('students.contacts');
    Route::post('/students/{student}/contacts', [PupilController::class, 'storeContact'])
        ->name('students.contacts.store');
    Route::put('/students/{student}/contacts/{contact}', [PupilController::class, 'updateContact'])
        ->name('students.contacts.update');
    Route::delete('/students/{student}/contacts/{contact}', [PupilController::class, 'deleteContact'])
        ->name('students.contacts.destroy');
    Route::patch('/students/{student}/contacts/{contact}/primary', [PupilController::class, 'setPrimaryContact'])
        ->name('students.contacts.primary');
    Route::post('/students/{student}/contacts/auto-create', [PupilController::class, 'autoCreateEmergencyContact'])
        ->name('students.contacts.auto-create');

    // Student Notes
    Route::get('/students/{student}/notes', [PupilController::class, 'notes'])
        ->name('students.notes');
    Route::post('/students/{student}/notes', [PupilController::class, 'storeNote'])
        ->name('students.notes.store');
    Route::delete('/students/{student}/notes/{note}', [PupilController::class, 'deleteNote'])
        ->name('students.notes.destroy');

    // Student Messages
    Route::get('/students/{student}/messages', [PupilController::class, 'messages'])
        ->name('students.messages');
    Route::post('/students/{student}/messages', [PupilController::class, 'sendMessage'])
        ->name('students.messages.store');

    // Student Payments
    Route::get('/students/{student}/payments', [PupilController::class, 'payments'])
        ->name('students.payments');

    // Student Orders
    Route::get('/students/{student}/available-slots', [PupilController::class, 'availableSlots'])
        ->name('students.available-slots');
    Route::post('/students/{student}/orders', [PupilController::class, 'storeOrder'])
        ->name('students.orders.store');

    // Student Lessons
    Route::get('/students/{student}/lessons', [PupilController::class, 'lessons'])
        ->name('students.lessons');
    Route::post('/students/{student}/lessons/{lesson}/sign-off', [PupilController::class, 'signOffLesson'])
        ->name('students.lessons.sign-off');
    Route::post('/students/{student}/lessons/{lesson}/resend-invoice', [PupilController::class, 'resendLessonInvoice'])
        ->name('students.lessons.resend-invoice');

    // Student Pickup Points
    Route::get('/students/{student}/pickup-points', [PupilController::class, 'pickupPoints'])
        ->name('students.pickup-points');
    Route::post('/students/{student}/pickup-points', [PupilController::class, 'storePickupPoint'])
        ->name('students.pickup-points.store');
    Route::put('/students/{student}/pickup-points/{pickupPoint}', [PupilController::class, 'updatePickupPoint'])
        ->name('students.pickup-points.update');
    Route::delete('/students/{student}/pickup-points/{pickupPoint}', [PupilController::class, 'deletePickupPoint'])
        ->name('students.pickup-points.destroy');
    Route::patch('/students/{student}/pickup-points/{pickupPoint}/default', [PupilController::class, 'setDefaultPickupPoint'])
        ->name('students.pickup-points.default');

    // Student Checklist
    Route::get('/students/{student}/checklist', [PupilController::class, 'checklist'])
        ->name('students.checklist');
    Route::patch('/students/{student}/checklist/{checklistItem}', [PupilController::class, 'toggleChecklistItem'])
        ->name('students.checklist.toggle');

    // Student Status & Management
    Route::patch('/students/{student}/status', [PupilController::class, 'updateStatus'])
        ->name('students.status.update');
    Route::delete('/students/{student}/remove', [PupilController::class, 'removeStudent'])
        ->name('students.remove');
    Route::put('/students/{student}/password', [PupilController::class, 'updatePassword'])
        ->name('students.password.update');

    Route::get('/teams', [TeamController::class, 'index'])
        ->name('teams.index');
    Route::get('/reports', [ReportController::class, 'index'])
        ->name('reports.index');
    // Push Notifications (Owner Only)
    Route::middleware([EnsureOwner::class])->group(function () {
        Route::get('/push-notifications', [PushNotificationController::class, 'index'])
            ->name('push-notifications.index');
        Route::post('/push-notifications', [PushNotificationController::class, 'store'])
            ->name('push-notifications.store');
    });

    // Support Messages (Owner Only)
    Route::middleware([EnsureOwner::class])->group(function () {
        Route::get('/support-messages', [SupportMessagesController::class, 'index'])
            ->name('support-messages.index');
        Route::post('/support-messages/{user}', [SupportMessagesController::class, 'store'])
            ->name('support-messages.store');
    });

    // Resources (Owner Only)
    Route::middleware([EnsureOwner::class])->group(function () {
        Route::get('/resources', [ResourceController::class, 'index'])
            ->name('resources.index');
        Route::get('/resources/folders/root/contents', [ResourceController::class, 'getFolderContents'])
            ->name('resources.folders.root-contents');
        Route::get('/resources/folders/{folder}/contents', [ResourceController::class, 'getFolderContents'])
            ->name('resources.folders.contents');
        Route::post('/resources/folders', [ResourceController::class, 'storeFolder'])
            ->name('resources.folders.store');
        Route::put('/resources/folders/{folder}', [ResourceController::class, 'updateFolder'])
            ->name('resources.folders.update');
        Route::delete('/resources/folders/{folder}', [ResourceController::class, 'destroyFolder'])
            ->name('resources.folders.destroy');
        Route::post('/resources/files', [ResourceController::class, 'storeResource'])
            ->name('resources.files.store');
        Route::get('/resources/files/{resource}/url', [ResourceController::class, 'getFileUrl'])
            ->name('resources.files.url');
        Route::put('/resources/files/{resource}', [ResourceController::class, 'updateResource'])
            ->name('resources.files.update');
        Route::delete('/resources/files/{resource}', [ResourceController::class, 'destroyResource'])
            ->name('resources.files.destroy');
        Route::get('/resources/csv-template', [ResourceController::class, 'downloadCsvTemplate'])
            ->name('resources.csv-template');
        Route::post('/resources/import-csv', [ResourceController::class, 'importCsv'])
            ->name('resources.import-csv');
    });

    // Student Transfers (Owner Only)
    Route::middleware([EnsureOwner::class])->group(function () {
        Route::get('/student-transfers', [StudentTransferController::class, 'index'])
            ->name('student-transfers.index');
        Route::post('/student-transfers', [StudentTransferController::class, 'store'])
            ->name('student-transfers.store');
    });

    Route::get('/apps', [AppController::class, 'index'])
        ->name('apps.index');

    Route::get('/enquiries', [EnquiryController::class, 'index'])
        ->name('enquiries.index');
});

// HMRC Making Tax Digital — instructor-only
Route::middleware(['auth', 'verified', EnsureInstructor::class])
    ->prefix('hmrc')
    ->name('hmrc.')
    ->group(function () {
        Route::get('/', [HmrcConnectionController::class, 'index'])->name('index');
        Route::get('/connect', [HmrcConnectionController::class, 'connect'])->name('connect');
        Route::get('/oauth/callback', [HmrcConnectionController::class, 'callback'])->name('callback');
        Route::post('/disconnect', [HmrcConnectionController::class, 'disconnect'])->name('disconnect');
        Route::post('/tax-profile', [HmrcConnectionController::class, 'updateTaxProfile'])->name('tax-profile.update');
        Route::post('/fingerprint', [HmrcFraudHeadersController::class, 'storeFingerprint'])->name('fingerprint.store');
        Route::post('/test/hello-world', HmrcHelloWorldController::class)->name('test.hello-world');
        Route::post('/test/fraud-headers', [HmrcFraudHeadersController::class, 'validate'])->name('test.fraud-headers');

        Route::prefix('vat')->name('vat.')->group(function () {
            Route::get('/', [VatController::class, 'index'])->name('index');
            Route::post('/sync-obligations', [VatController::class, 'syncObligations'])->name('sync-obligations');
            Route::get('/{periodKey}/period', [VatController::class, 'period'])
                ->where('periodKey', '.+')
                ->name('period');
            Route::post('/{periodKey}/period', [VatController::class, 'store'])
                ->where('periodKey', '.+')
                ->name('store');
        });

        Route::prefix('itsa')->name('itsa.')->group(function () {
            Route::get('/', [ItsaController::class, 'index'])->name('index');
            Route::post('/refresh-status', [ItsaController::class, 'refreshStatus'])->name('refresh-status');
            Route::post('/sync-obligations', [ItsaController::class, 'syncObligations'])->name('sync-obligations');

            Route::middleware(EnsureMtdEnrolled::class)->group(function () {
                Route::get('/{businessId}/period/{periodKey}', [ItsaController::class, 'period'])
                    ->where('periodKey', '.+')
                    ->name('period');
                Route::post('/{businessId}/period/{periodKey}', [ItsaController::class, 'store'])
                    ->where('periodKey', '.+')
                    ->name('store');
                Route::put('/quarterly-updates/{quarterlyUpdate}', [ItsaController::class, 'amend'])->name('amend');

                Route::prefix('final-declaration')->name('final-declaration.')->group(function () {
                    Route::get('/{taxYear}', [FinalDeclarationController::class, 'index'])->name('index');
                    Route::get('/{taxYear}/step/{type}', [FinalDeclarationController::class, 'step'])->name('step');
                    Route::post('/{taxYear}/step/{type}', [FinalDeclarationController::class, 'storeStep'])->name('step.store');
                    Route::post('/{taxYear}/calculate', [FinalDeclarationController::class, 'triggerCalculation'])->name('calculate');
                    Route::get('/{taxYear}/calculation/{calculation}', [FinalDeclarationController::class, 'showCalculation'])->name('calculation');
                    Route::get('/{taxYear}/calculation/{calculation}/poll', [FinalDeclarationController::class, 'pollCalculation'])->name('calculation.poll');
                    Route::post('/{taxYear}/submit/{calculation}', [FinalDeclarationController::class, 'submit'])->name('submit');
                });
            });
        });
    });
// Public mobile-app brochure (linked from welcome email)
Route::get('/get-app', [GetAppController::class, 'index'])
    ->name('get-app');

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

// Booking Routes (Public — single-instructor coverage check landing page)
Route::get('/booking', [BookingController::class, 'start'])
    ->name('booking.start');

Route::prefix('/booking/{uuid}')
    ->middleware([ValidateBookingEnquiryUuid::class, ValidateBookingStepAccess::class])
    ->group(function () {
        Route::get('/step/1', [BookingStepOneController::class, 'show'])
            ->name('booking.step1');
        Route::post('/step/1', [BookingStepOneController::class, 'store'])
            ->name('booking.step1.store');

        Route::get('/step/2', [BookingStepTwoController::class, 'show'])
            ->name('booking.step2');
    });

// Resource email view (signed URL — no auth required, students click from email)
Route::get('/resources/view/{resource}', [ResourceController::class, 'emailView'])
    ->name('resources.email-view');

// Stripe Webhook (must be outside auth middleware)
Route::post('/webhook/stripe', [WebhookController::class, 'handle'])
    ->name('webhook.stripe');

// Payment-link checkout return (instructor-sent Stripe payment links).
// Unauthenticated — the student is clicking through from an email and has
// no app session. Security comes from matching the Stripe session_id against
// the order's stored stripe_checkout_session_id.
Route::get('/orders/{order}/payment-link/success', [PaymentLinkCheckoutController::class, 'success'])
    ->name('payment-link.checkout.success');
Route::get('/orders/{order}/payment-link/cancel', [PaymentLinkCheckoutController::class, 'cancel'])
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
