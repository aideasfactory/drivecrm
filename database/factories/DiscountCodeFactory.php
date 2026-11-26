<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\DiscountCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountCode>
 */
class DiscountCodeFactory extends Factory
{
    protected $model = DiscountCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PROMO####')),
            'label' => fake()->words(2, true).' discount',
            'type' => DiscountType::PERCENTAGE,
            'percentage' => fake()->randomElement([5, 10, 15, 20]),
            'active' => true,
        ];
    }

    /**
     * A fixed amount off the package price.
     */
    public function amount(int $amountPence = 1000): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DiscountType::AMOUNT,
            'percentage' => null,
            'amount_pence' => $amountPence,
        ]);
    }

    /**
     * Indicate that the discount code is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
