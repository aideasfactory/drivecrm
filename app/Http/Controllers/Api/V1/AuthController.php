<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterInstructorRequest;
use App\Http\Requests\Api\V1\RegisterStudentRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Login and receive a Bearer token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name'),
            $request->validated('role')
        );

        return response()->json([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ]);
    }

    /**
     * Register a new student and receive a Bearer token.
     */
    public function registerStudent(RegisterStudentRequest $request): JsonResponse
    {
        $result = $this->authService->registerStudent(
            $request->safe()->only(['name', 'email', 'password', 'phone']),
            $request->validated('device_name')
        );

        return response()->json([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ], 201);
    }

    /**
     * Register a new instructor and receive a Bearer token.
     */
    public function registerInstructor(RegisterInstructorRequest $request): JsonResponse
    {
        $result = $this->authService->registerInstructor(
            $request->safe()->only(['name', 'email', 'password', 'phone', 'postcode', 'address', 'transmission_type']),
            $request->validated('device_name')
        );

        return response()->json([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ], 201);
    }

    /**
     * Logout and revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
