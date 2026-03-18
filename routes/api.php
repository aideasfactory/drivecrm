<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\InstructorStudentController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\StudentLessonController;
use App\Http\Controllers\Api\V1\StudentNoteController;
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
            Route::get('students', [InstructorStudentController::class, 'index']);
        });

        // Student routes
        Route::get('students/{student}', [StudentController::class, 'show']);
        Route::get('students/{student}/lessons', [StudentLessonController::class, 'index']);
        Route::get('students/{student}/lessons/{lesson}', [StudentLessonController::class, 'show']);

        // Student notes
        Route::get('students/{student}/notes', [StudentNoteController::class, 'index']);
        Route::post('students/{student}/notes', [StudentNoteController::class, 'store']);

    });

});
