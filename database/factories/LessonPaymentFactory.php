<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Lesson;
use App\Models\LessonPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonPayment>
 */
class LessonPaymentFactory extends Factory
{
    protected $model = LessonPayment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'amount_pence' => fake()->randomElement([3000, 3500, 4000, 4500]),
            'status' => PaymentStatus::DUE,
            'due_date' => now()->addDays(2),
        ];
    }

    /**
     * Indicate that the payment has been paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::PAID,
            'paid_at' => now(),
            'stripe_invoice_id' => 'in_'.fake()->unique()->regexify('[a-zA-Z0-9]{24}'),
        ]);
    }

    /**
     * Indicate that an invoice has already been sent.
     */
    public function invoiceSent(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_invoice_id' => 'in_'.fake()->unique()->regexify('[a-zA-Z0-9]{24}'),
        ]);
    }
}
