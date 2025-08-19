<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currentPeriodStart = Carbon::now()->subDays(rand(1, 30));
        $currentPeriodEnd = $currentPeriodStart->copy()->addMonth();

        return [
            'user_id' => User::factory(),
            'stripe_subscription_id' => 'sub_' . $this->faker->unique()->regexify('[A-Za-z0-9]{14}'),
            'stripe_customer_id' => 'cus_' . $this->faker->unique()->regexify('[A-Za-z0-9]{14}'),
            'stripe_price_id' => 'price_' . $this->faker->unique()->regexify('[A-Za-z0-9]{14}'),
            'status' => $this->faker->randomElement(['active', 'trialing', 'canceled', 'incomplete', 'past_due']),
            'current_period_start' => $currentPeriodStart,
            'current_period_end' => $currentPeriodEnd,
            'trial_start' => null,
            'trial_end' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ];
    }

    /**
     * Indicate that the subscription is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the subscription is trialing.
     */
    public function trialing(): static
    {
        $trialStart = Carbon::now()->subDays(rand(1, 7));
        $trialEnd = $trialStart->copy()->addDays(14);

        return $this->state(fn (array $attributes) => [
            'status' => 'trialing',
            'trial_start' => $trialStart,
            'trial_end' => $trialEnd,
        ]);
    }

    /**
     * Indicate that the subscription is canceled.
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
            'canceled_at' => Carbon::now()->subDays(rand(1, 30)),
        ]);
    }

    /**
     * Indicate that the subscription will cancel at period end.
     */
    public function cancelAtPeriodEnd(): static
    {
        return $this->state(fn (array $attributes) => [
            'cancel_at_period_end' => true,
        ]);
    }

    /**
     * Indicate that the subscription is past due.
     */
    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'past_due',
        ]);
    }

    /**
     * Indicate that the subscription is incomplete.
     */
    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'incomplete',
        ]);
    }

    /**
     * Indicate that the subscription is on trial.
     */
    public function onTrial(): static
    {
        return $this->trialing();
    }

    /**
     * Indicate that the subscription is on grace period (canceled but still active).
     */
    public function onGracePeriod(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'cancel_at_period_end' => true,
            'canceled_at' => Carbon::now()->subDays(rand(1, 5)),
        ]);
    }
}