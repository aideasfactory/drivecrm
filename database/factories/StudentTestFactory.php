<?php

namespace Database\Factories;

use App\Enums\StudentTestResult;
use App\Enums\StudentTestType;
use App\Models\Student;
use App\Models\StudentTest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentTest>
 */
class StudentTestFactory extends Factory
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
            'instructor_id' => null,
            'calendar_item_id' => null,
            'type' => StudentTestType::Practical,
            'date' => fake()->dateTimeBetween('-1 year', '-1 day')->format('Y-m-d'),
            'time' => fake()->randomElement(['08:30', '10:10', '12:30', '14:20']),
            'test_centre' => null,
            'result' => StudentTestResult::Pass,
            'driving_faults' => fake()->numberBetween(0, 15),
            'serious_faults' => 0,
            'dangerous_faults' => 0,
            'notes' => null,
        ];
    }

    public function theory(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => StudentTestType::Theory,
            'time' => null,
            'driving_faults' => null,
            'serious_faults' => null,
            'dangerous_faults' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => StudentTestResult::Fail,
            'serious_faults' => 1,
        ]);
    }

    public function booked(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => fake()->dateTimeBetween('+1 week', '+3 months')->format('Y-m-d'),
            'result' => StudentTestResult::Booked,
            'driving_faults' => null,
            'serious_faults' => null,
            'dangerous_faults' => null,
        ]);
    }
}
