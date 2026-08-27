<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'order_id' => Order::factory(),
            'student_id' => Student::factory(),
            'instructor_id' => Instructor::factory(),
            'requested_by_user_id' => User::factory(),
            'amount_pence' => fake()->randomElement([3000, 3500, 4000, 4500]),
            'status' => RefundStatus::PENDING,
            'reason' => fake()->sentence(),
            'requested_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RefundStatus::COMPLETED,
            'method' => RefundMethod::MANUAL,
            'processed_by_user_id' => User::factory(),
            'completed_at' => now(),
        ]);
    }

    public function stripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RefundStatus::COMPLETED,
            'method' => RefundMethod::STRIPE,
            'stripe_refund_id' => 're_'.fake()->unique()->regexify('[a-zA-Z0-9]{24}'),
            'processed_by_user_id' => User::factory(),
            'completed_at' => now(),
        ]);
    }
}
