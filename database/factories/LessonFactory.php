<?php

namespace Database\Factories;

use App\Enums\LessonStatus;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-30 days', '+30 days');

        return [
            'order_id' => Order::factory(),
            'instructor_id' => Instructor::factory(),
            'amount_pence' => fake()->randomElement([3000, 3500, 4000, 4500]),
            'date' => $date->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => LessonStatus::PENDING,
        ];
    }

    /**
     * Indicate that the lesson is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LessonStatus::COMPLETED,
            'completed_at' => now(),
            'summary' => fake()->sentence(),
        ]);
    }
}
