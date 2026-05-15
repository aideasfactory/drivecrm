<?php

namespace Database\Factories;

use App\Enums\PushNotificationStatus;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushNotification>
 */
class PushNotificationFactory extends Factory
{
    protected $model = PushNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(),
            'data' => null,
            'status' => PushNotificationStatus::PENDING,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => PushNotificationStatus::SENT,
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PushNotificationStatus::FAILED,
            'error_message' => 'Delivery failed.',
        ]);
    }
}
