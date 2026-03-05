<?php

namespace Database\Factories;

use App\Models\Instructor;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lessonsCount = fake()->numberBetween(5, 20);
        $totalPricePence = $lessonsCount * fake()->numberBetween(2500, 5000);

        return [
            'instructor_id' => null,
            'name' => fake()->words(3, true).' Package',
            'description' => fake()->sentence(),
            'total_price_pence' => $totalPricePence,
            'lessons_count' => $lessonsCount,
            'active' => true,
        ];
    }

    /**
     * Indicate that the package belongs to an instructor.
     */
    public function forInstructor(?Instructor $instructor = null): static
    {
        return $this->state(fn (array $attributes) => [
            'instructor_id' => $instructor?->id ?? Instructor::factory(),
        ]);
    }

    /**
     * Indicate that the package is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
