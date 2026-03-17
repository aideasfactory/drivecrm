<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    /**
     * Validate credentials and create a Sanctum token.
     *
     * @return array{token: string, user: User}
     *
     * @throws ValidationException
     */
    public function __invoke(string $email, string $password, string $deviceName, string $role): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('The provided credentials are incorrect.')],
            ]);
        }

        if ($user->role->value !== $role) {
            throw ValidationException::withMessages([
                'role' => [__('Your account is not registered as a :role.', ['role' => $role])],
            ]);
        }

        $user->load($this->profileRelation($user));

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    /**
     * Get the profile relationship name based on user role.
     */
    private function profileRelation(User $user): string
    {
        return match ($user->role) {
            UserRole::INSTRUCTOR => 'instructor',
            UserRole::STUDENT => 'student',
            default => 'instructor',
        };
    }
}
