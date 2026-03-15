<?php

use App\Http\Controllers\Api\V1\AuthController;
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

    // Protected auth routes
    Route::middleware('auth:sanctum')->prefix('auth')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
    });

});
