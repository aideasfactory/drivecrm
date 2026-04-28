<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HazardPerceptionController;
use App\Http\Controllers\Api\V1\InstructorCalendarController;
use App\Http\Controllers\Api\V1\InstructorFinanceController;
use App\Http\Controllers\Api\V1\InstructorLessonController;
use App\Http\Controllers\Api\V1\InstructorMileageController;
use App\Http\Controllers\Api\V1\InstructorPackageController;
use App\Http\Controllers\Api\V1\InstructorProfileController;
use App\Http\Controllers\Api\V1\InstructorStudentController;
use App\Http\Controllers\Api\V1\InstructorStudentProgressController;
use App\Http\Controllers\Api\V1\LessonResourceController;
use App\Http\Controllers\Api\V1\LessonSignOffController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\MockTestController;
use App\Http\Controllers\Api\V1\PackagePricingController;
use App\Http\Controllers\Api\V1\PushNotificationController;
use App\Http\Controllers\Api\V1\ResourceController;
use App\Http\Controllers\Api\V1\StudentActivityLogController;
use App\Http\Controllers\Api\V1\StudentCalendarController;
use App\Http\Controllers\Api\V1\StudentChecklistItemController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\StudentDashboardController;
use App\Http\Controllers\Api\V1\StudentLessonController;
use App\Http\Controllers\Api\V1\StudentNoteController;
use App\Http\Controllers\Api\V1\StudentOrderController;
use App\Http\Controllers\Api\V1\StudentPackageController;
use App\Http\Controllers\Api\V1\StudentPickupPointController;
use App\Http\Controllers\Api\V1\StudentProfilePictureController;
use App\Http\Controllers\Api\V1\StudentProgressController;
use App\Http\Controllers\Api\V1\StudentResourceController;
use App\Http\Middleware\ResolveApiProfile;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {

    // Public auth routes
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register/student', [AuthController::class, 'registerStudent']);
        Route::post('register/instructor', [AuthController::class, 'registerInstructor']);
    });

    // Protected routes — profile is auto-resolved from token
    Route::middleware(['auth:sanctum', ResolveApiProfile::class])->group(function (): void {

        Route::prefix('auth')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('user', [AuthController::class, 'user']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
        });

        // Instructor routes
        Route::prefix('instructor')->group(function (): void {
            Route::put('profile', [InstructorProfileController::class, 'update']);
            Route::post('profile/picture', [InstructorProfileController::class, 'updateProfilePicture']);
            Route::delete('profile/picture', [InstructorProfileController::class, 'deleteProfilePicture']);
            Route::get('students', [InstructorStudentController::class, 'index']);
            Route::get('lessons/{date}', [InstructorLessonController::class, 'index']);
            Route::post('lessons/{lesson}/notify-on-way', [InstructorLessonController::class, 'notifyOnWay']);
            Route::post('lessons/{lesson}/notify-arrived', [InstructorLessonController::class, 'notifyArrived']);
            Route::patch('lessons/{lesson}/mileage', [InstructorLessonController::class, 'updateMileage']);
            Route::get('packages', [InstructorPackageController::class, 'index']);
            Route::post('packages', [InstructorPackageController::class, 'store']);
            Route::put('packages/{package}', [InstructorPackageController::class, 'update']);
            Route::get('calendar/items', [InstructorCalendarController::class, 'index']);
            Route::post('calendar/items', [InstructorCalendarController::class, 'store']);
            Route::delete('calendar/items/{calendarItem}', [InstructorCalendarController::class, 'destroy']);
            Route::get('finances/config', [InstructorFinanceController::class, 'config']);
            Route::get('finances/summary', [InstructorFinanceController::class, 'summary']);
            Route::get('finances', [InstructorFinanceController::class, 'index']);
            Route::post('finances', [InstructorFinanceController::class, 'store']);
            Route::get('finances/{finance}', [InstructorFinanceController::class, 'show']);
            Route::put('finances/{finance}', [InstructorFinanceController::class, 'update']);
            Route::delete('finances/{finance}', [InstructorFinanceController::class, 'destroy']);
            Route::post('finances/{finance}/receipt', [InstructorFinanceController::class, 'uploadReceipt']);
            Route::delete('finances/{finance}/receipt', [InstructorFinanceController::class, 'destroyReceipt']);

            Route::get('mileage', [InstructorMileageController::class, 'index']);
            Route::post('mileage', [InstructorMileageController::class, 'store']);
            Route::get('mileage/{mileageLog}', [InstructorMileageController::class, 'show']);
            Route::put('mileage/{mileageLog}', [InstructorMileageController::class, 'update']);
            Route::delete('mileage/{mileageLog}', [InstructorMileageController::class, 'destroy']);

            Route::get('students/{student}/progress', [InstructorStudentProgressController::class, 'show']);
            Route::post('students/{student}/progress', [InstructorStudentProgressController::class, 'update']);
        });

        // Student-scoped routes (identity from token, not URL)
        Route::prefix('student')->group(function (): void {
            Route::get('packages', [StudentPackageController::class, 'index']);
            Route::get('calendar/items', [StudentCalendarController::class, 'index']);
            Route::get('instructor', [StudentDashboardController::class, 'instructor']);
            Route::get('dashboard', [StudentDashboardController::class, 'dashboard']);
            Route::get('progress', [StudentProgressController::class, 'show']);
            Route::get('resource-summary', [StudentResourceController::class, 'summary']);
            Route::get('resources', [StudentResourceController::class, 'index']);
            Route::get('resources/{resource}', [StudentResourceController::class, 'show']);
            Route::post('resources/{resource}/watched', [StudentResourceController::class, 'markWatched']);

            // Mock test routes
            Route::get('mock-tests/summary', [MockTestController::class, 'summary']);
            Route::post('mock-tests/start', [MockTestController::class, 'start']);
            Route::post('mock-tests/{mockTest}/submit', [MockTestController::class, 'submit']);
            Route::get('mock-tests/{mockTest}/review', [MockTestController::class, 'review']);

            // Hazard perception routes
            Route::get('hazard-perception/videos', [HazardPerceptionController::class, 'index']);
            Route::post('hazard-perception/videos/{hazardPerceptionVideo}/submit', [HazardPerceptionController::class, 'submit']);
            Route::get('hazard-perception/summary', [HazardPerceptionController::class, 'summary']);

            // Activity log
            Route::get('activity-logs', [StudentActivityLogController::class, 'index']);
        });

        // Student routes
        Route::post('students/attach', [StudentController::class, 'attachToInstructor']);
        Route::post('students', [StudentController::class, 'store']);
        Route::get('students/{student}', [StudentController::class, 'show']);
        Route::put('students/{student}', [StudentController::class, 'update']);
        Route::delete('students/{student}', [StudentController::class, 'destroy']);
        Route::post('students/{student}/profile-picture', [StudentProfilePictureController::class, 'store']);
        Route::delete('students/{student}/profile-picture', [StudentProfilePictureController::class, 'destroy']);
        Route::get('students/{student}/lessons', [StudentLessonController::class, 'index']);
        Route::get('students/{student}/lessons/{lesson}', [StudentLessonController::class, 'show']);
        Route::get('students/{student}/pickup-points', [StudentPickupPointController::class, 'index']);
        Route::post('students/{student}/pickup-points', [StudentPickupPointController::class, 'store']);
        Route::put('students/{student}/pickup-points/{pickupPoint}', [StudentPickupPointController::class, 'update']);
        Route::delete('students/{student}/pickup-points/{pickupPoint}', [StudentPickupPointController::class, 'destroy']);
        Route::patch('students/{student}/pickup-points/{pickupPoint}/default', [StudentPickupPointController::class, 'setDefault']);
        Route::post('students/{student}/lessons/{lesson}/resources', [LessonResourceController::class, 'store']);
        Route::post('students/{student}/lessons/{lesson}/sign-off', [LessonSignOffController::class, 'store']);
        // Resources
        Route::get('resources', [ResourceController::class, 'index']);
        Route::get('resources/{resource}', [ResourceController::class, 'show']);

        // Messaging routes
        Route::prefix('messages')->group(function (): void {
            Route::get('conversations', [MessageController::class, 'conversations']);
            Route::get('conversations/instructor', [MessageController::class, 'showInstructorConversation']);
            Route::get('conversations/{conversationUserId}', [MessageController::class, 'show']);
            Route::post('/', [MessageController::class, 'store']);
        });

        // Student notes
        Route::get('students/{student}/notes', [StudentNoteController::class, 'index']);
        Route::post('students/{student}/notes', [StudentNoteController::class, 'store']);
        Route::put('students/{student}/notes/{note}', [StudentNoteController::class, 'update']);
        Route::delete('students/{student}/notes/{note}', [StudentNoteController::class, 'destroy']);

        // Student checklist item routes
        Route::get('students/{student}/checklist-items', [StudentChecklistItemController::class, 'index']);
        Route::put('students/{student}/checklist-items/{checklistItem}', [StudentChecklistItemController::class, 'update']);

        // Package pricing
        Route::get('packages/{package}/pricing', [PackagePricingController::class, 'show']);

        // Order routes
        Route::post('students/{student}/orders', [StudentOrderController::class, 'store']);
        Route::get('orders/{order}/checkout/verify', [StudentOrderController::class, 'verify']);

        // Push notification routes
        Route::post('push-token', [PushNotificationController::class, 'storeToken']);

    });

});
