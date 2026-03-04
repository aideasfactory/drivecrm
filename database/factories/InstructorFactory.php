<?php

namespace Database\Factories;

use App\Models\Instructor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Instructor>
 */
class InstructorFactory extends Factory
{
    protected $model = Instructor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'stripe_account_id' => null,
            'onboarding_complete' => false,
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'status' => 'active',
            'priority' => false,
        ];
    }

    /**
     * Indicate that the instructor has Stripe connected.
     */
    public function stripeConnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_account_id' => 'acct_' . fake()->unique()->regexify('[a-zA-Z0-9]{16}'),
            'onboarding_complete' => true,
            'charges_enabled' => true,
            'payouts_enabled' => true,
        ]);
    }
}
