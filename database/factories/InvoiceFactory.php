<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_id' => Subscription::factory(),
            'stripe_invoice_id' => 'in_' . $this->faker->unique()->regexify('[A-Za-z0-9]{14}'),
            'amount_paid' => $this->faker->numberBetween(999, 9999), // Amount in cents
            'currency' => $this->faker->randomElement(['usd', 'eur', 'gbp']),
            'status' => $this->faker->randomElement(['paid', 'open', 'void', 'draft']),
            'invoice_pdf' => $this->faker->optional()->url(),
            'hosted_invoice_url' => $this->faker->optional()->url(),
        ];
    }

    /**
     * Indicate that the invoice is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
        ]);
    }

    /**
     * Indicate that the invoice is open (unpaid).
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
        ]);
    }

    /**
     * Indicate that the invoice is void.
     */
    public function void(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'void',
        ]);
    }

    /**
     * Indicate that the invoice is draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Set a specific amount for the invoice.
     */
    public function amount(int $amountInCents): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_paid' => $amountInCents,
        ]);
    }

    /**
     * Set the currency for the invoice.
     */
    public function currency(string $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => $currency,
        ]);
    }
}