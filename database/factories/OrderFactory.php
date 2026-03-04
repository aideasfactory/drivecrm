<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\Instructor;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lessonsCount = fake()->randomElement([5, 10, 15, 20]);
        $lessonPricePence = fake()->randomElement([3000, 3500, 4000, 4500]);

        return [
            'student_id' => Student::factory(),
            'instructor_id' => Instructor::factory(),
            'package_name' => fake()->randomElement(['5 Lesson Package', '10 Lesson Package', '15 Lesson Package', '20 Lesson Package']),
            'package_total_price_pence' => $lessonsCount * $lessonPricePence,
            'package_lesson_price_pence' => $lessonPricePence,
            'package_lessons_count' => $lessonsCount,
            'payment_mode' => PaymentMode::WEEKLY,
            'status' => OrderStatus::ACTIVE,
        ];
    }
}
