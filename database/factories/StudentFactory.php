<?php

namespace Database\Factories;

use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'instructor_id' => Instructor::factory(),
            'first_name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'owns_account' => true,
            'terms_accepted' => true,
            'allow_communications' => true,
            'status' => 'active',
        ];
    }
}
