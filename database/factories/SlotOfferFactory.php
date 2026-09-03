<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SlotOfferStatus;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Package;
use App\Models\SlotOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlotOffer>
 */
class SlotOfferFactory extends Factory
{
    protected $model = SlotOffer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'calendar_item_id' => CalendarItem::factory(),
            'instructor_id' => Instructor::factory(),
            'package_id' => Package::factory(),
            'student_id' => null,
            'message' => fake()->sentence(),
            'status' => SlotOfferStatus::Open,
            'booked_at' => null,
        ];
    }

    public function booked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SlotOfferStatus::Booked,
            'booked_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SlotOfferStatus::Cancelled,
        ]);
    }
}
