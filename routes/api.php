<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\InstructorLessonController;
use App\Http\Controllers\Api\V1\InstructorProfileController;
use App\Http\Controllers\Api\V1\InstructorStudentController;
use App\Http\Controllers\Api\V1\StudentChecklistItemController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\StudentLessonController;
use App\Http\Controllers\Api\V1\StudentNoteController;
use App\Http\Controllers\Api\V1\StudentPickupPointController;
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
        });

        // Instructor routes
        Route::prefix('instructor')->group(function (): void {
            Route::put('profile', [InstructorProfileController::class, 'update']);
            Route::get('students', [InstructorStudentController::class, 'index']);
            Route::get('lessons/{date}', [InstructorLessonController::class, 'index']);
        });

        // Student routes
        Route::get('students/{student}', [StudentController::class, 'show']);
        Route::get('students/{student}/lessons', [StudentLessonController::class, 'index']);
        Route::get('students/{student}/lessons/{lesson}', [StudentLessonController::class, 'show']);
        Route::get('students/{student}/pickup-points', [StudentPickupPointController::class, 'index']);

        // Messaging routes
        Route::prefix('messages')->group(function (): void {
            Route::get('conversations', [MessageController::class, 'conversations']);
            Route::get('conversations/{user}', [MessageController::class, 'show']);
            Route::post('/', [MessageController::class, 'store']);
        });

        // Student notes
        Route::get('students/{student}/notes', [StudentNoteController::class, 'index']);
        Route::post('students/{student}/notes', [StudentNoteController::class, 'store']);

        // Student checklist item routes
        Route::get('students/{student}/checklist-items', [StudentChecklistItemController::class, 'index']);
        Route::put('students/{student}/checklist-items/{checklistItem}', [StudentChecklistItemController::class, 'update']);

    });

});
