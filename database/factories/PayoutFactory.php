<?php

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Payout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payout>
 */
class PayoutFactory extends Factory
{
    protected $model = Payout::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'instructor_id' => Instructor::factory(),
            'amount_pence' => fake()->randomElement([3000, 3500, 4000, 4500]),
            'status' => PayoutStatus::PENDING,
        ];
    }

    /**
     * Indicate that the payout has been paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutStatus::PAID,
            'paid_at' => now(),
            'stripe_transfer_id' => 'tr_'.fake()->unique()->regexify('[a-zA-Z0-9]{24}'),
        ]);
    }

    /**
     * Indicate that the payout has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutStatus::FAILED,
        ]);
    }
}
