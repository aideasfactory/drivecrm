<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\LogoutAction;
use App\Actions\Auth\RegisterInstructorAction;
use App\Actions\Auth\RegisterStudentAction;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;

class AuthService
{
    public function __construct(
        protected LoginAction $login,
        protected LogoutAction $logout,
        protected RegisterStudentAction $registerStudent,
        protected RegisterInstructorAction $registerInstructor
    ) {}

    /**
     * Authenticate a user and return a token.
     *
     * @return array{token: string, user: User}
     */
    public function login(string $email, string $password, string $deviceName): array
    {
        return ($this->login)($email, $password, $deviceName);
    }

    /**
     * Revoke the current access token.
     */
    public function logout(User $user): void
    {
        ($this->logout)($user);
    }

    /**
     * Register a new student user.
     *
     * @param  array{name: string, email: string, password: string, phone?: string}  $data
     * @param  string  $deviceName  Device identifier for token creation
     * @return array{token: string, user: User, student: Student}
     */
    public function registerStudent(array $data, string $deviceName): array
    {
        $result = ($this->registerStudent)($data);

        $token = $result['user']->createToken($deviceName)->plainTextToken;

        return [
            'token' => $token,
            'user' => $result['user'],
            'student' => $result['student'],
        ];
    }

    /**
     * Register a new instructor user.
     *
     * @param  array{name: string, email: string, password: string, phone?: string, postcode?: string, address?: string, transmission_type?: string}  $data
     * @param  string  $deviceName  Device identifier for token creation
     * @return array{token: string, user: User, instructor: Instructor}
     */
    public function registerInstructor(array $data, string $deviceName): array
    {
        $result = ($this->registerInstructor)($data);

        $token = $result['user']->createToken($deviceName)->plainTextToken;

        return [
            'token' => $token,
            'user' => $result['user'],
            'instructor' => $result['instructor'],
        ];
    }
}
