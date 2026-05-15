<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentChecklistItem>
 */
class StudentChecklistItemFactory extends Factory
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
            'key' => fake()->unique()->slug(3),
            'label' => fake()->sentence(3),
            'category' => fake()->randomElement(['Theory Test', 'Practical Test', 'General']),
            'is_checked' => false,
            'date' => null,
            'notes' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate this item is checked.
     */
    public function checked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_checked' => true,
            'date' => fake()->dateTimeBetween('-3 months', 'now'),
        ]);
    }
}
