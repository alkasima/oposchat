<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubscriptionItem>
 */
class SubscriptionItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SubscriptionItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'stripe_subscription_item_id' => 'si_' . $this->faker->unique()->regexify('[A-Za-z0-9]{14}'),
            'stripe_price_id' => 'price_' . $this->faker->unique()->regexify('[A-Za-z0-9]{14}'),
            'quantity' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * Indicate that the subscription item is metered.
     */
    public function metered(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 0,
        ]);
    }

    /**
     * Set a specific quantity for the subscription item.
     */
    public function quantity(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $quantity,
        ]);
    }
}