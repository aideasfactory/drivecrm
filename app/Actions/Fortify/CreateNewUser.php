<?php

namespace App\Actions\Fortify;

use App\Actions\Auth\RegisterStudentAction;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        protected RegisterStudentAction $registerStudent
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * Web registration creates a student by default. Instructors are
     * created via the dedicated mobile API endpoint.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $result = ($this->registerStudent)([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        return $result['user'];
    }
}
