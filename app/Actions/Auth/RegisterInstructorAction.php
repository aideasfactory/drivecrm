<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterInstructorAction
{
    /**
     * Create a new user with the instructor role and an associated instructor record.
     *
     * @param  array{name: string, email: string, password: string, phone?: string, postcode?: string, address?: string, transmission_type?: string}  $data
     * @return array{user: User, instructor: Instructor}
     */
    public function __invoke(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::INSTRUCTOR,
            ]);

            $instructor = Instructor::create([
                'user_id' => $user->id,
                'postcode' => $data['postcode'] ?? null,
                'address' => $data['address'] ?? null,
                'status' => 'active',
                'onboarding_complete' => false,
                'charges_enabled' => false,
                'payouts_enabled' => false,
                'rating' => 0,
                'priority' => false,
                'meta' => [
                    'transmission_type' => $data['transmission_type'] ?? null,
                ],
            ]);

            return [
                'user' => $user,
                'instructor' => $instructor,
            ];
        });
    }
}
