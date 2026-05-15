<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentPickupPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentPickupPoint>
 */
class StudentPickupPointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'label' => fake()->randomElement(['Home', 'School', 'Work', 'College', 'Gym']),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'postcode' => fake()->postcode(),
            'latitude' => fake()->latitude(50.0, 56.0),
            'longitude' => fake()->longitude(-3.0, 1.5),
            'is_default' => false,
        ];
    }

    /**
     * Indicate this is the default pickup point.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
