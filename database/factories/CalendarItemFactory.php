<?php

namespace Database\Factories;

use App\Models\Calendar;
use App\Models\CalendarItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CalendarItem>
 */
class CalendarItemFactory extends Factory
{
    protected $model = CalendarItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startHour = fake()->randomElement([8, 10, 12, 14, 16]);

        return [
            'calendar_id' => Calendar::factory(),
            'start_time' => sprintf('%02d:00:00', $startHour),
            'end_time' => sprintf('%02d:00:00', $startHour + 2),
            'is_available' => true,
            'status' => null,
            'notes' => null,
            'unavailability_reason' => null,
        ];
    }

    /**
     * Mark the item as unavailable.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
            'unavailability_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Mark the item as booked.
     */
    public function booked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => true,
            'status' => 'booked',
        ]);
    }
}
