<?php

namespace Tests\Unit\Services;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionService = new SubscriptionService();
    }

    public function test_creates_subscription_from_stripe_data(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test123'
        ]);

        $stripeSubscription = (object) [
            'id' => 'sub_test123',
            'customer' => 'cus_test123',
            'status' => 'active',
            'current_period_start' => now()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'trial_start' => null,
            'trial_end' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'items' => (object) [
                'data' => [
                    (object) [
                        'id' => 'si_test123',
                        'price' => (object) ['id' => 'price_test123'],
                        'quantity' => 1
                    ]
                ]
            ]
        ];

        $subscription = $this->subscriptionService->createSubscriptionFromStripe($stripeSubscription);

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals($user->id, $subscription->user_id);
        $this->assertEquals('sub_test123', $subscription->stripe_subscription_id);
        $this->assertEquals('active', $subscription->status);

        $this->assertDatabaseHas('subscription_items', [
            'subscription_id' => $subscription->id,
            'stripe_subscription_item_id' => 'si_test123',
            'stripe_price_id' => 'price_test123'
        ]);
    }

    public function test_throws_exception_when_user_not_found_for_subscription_creation(): void
    {
        $stripeSubscription = (object) [
            'id' => 'sub_test123',
            'customer' => 'cus_nonexistent',
            'status' => 'active',
            'items' => (object) [
                'data' => [
                    (object) [
                        'id' => 'si_test123',
                        'price' => (object) ['id' => 'price_test123'],
                        'quantity' => 1
                    ]
                ]
            ]
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User not found for Stripe customer ID: cus_nonexistent');

        $this->subscriptionService->createSubscriptionFromStripe($stripeSubscription);
    }

    public function test_updates_subscription_from_stripe_data(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'stripe_subscription_id' => 'sub_test123',
            'status' => 'active'
        ]);

        $stripeSubscription = (object) [
            'id' => 'sub_test123',
            'status' => 'past_due',
            'current_period_start' => now()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'trial_start' => null,
            'trial_end' => null,
            'cancel_at_period_end' => true,
            'canceled_at' => null,
            'items' => (object) [
                'data' => [
                    (object) [
                        'id' => 'si_test123',
                        'price' => (object) ['id' => 'price_updated123'],
                        'quantity' => 1
                    ]
                ]
            ]
        ];

        $updatedSubscription = $this->subscriptionService->updateSubscriptionFromStripe($stripeSubscription);

        $this->assertEquals('past_due', $updatedSubscription->status);
        $this->assertTrue($updatedSubscription->cancel_at_period_end);
        $this->assertEquals('price_updated123', $updatedSubscription->stripe_price_id);
    }    public 
function test_throws_exception_when_subscription_not_found_for_update(): void
    {
        $stripeSubscription = (object) [
            'id' => 'sub_nonexistent',
            'status' => 'active',
            'items' => (object) [
                'data' => [
                    (object) [
                        'id' => 'si_test123',
                        'price' => (object) ['id' => 'price_test123'],
                        'quantity' => 1
                    ]
                ]
            ]
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription not found for Stripe ID: sub_nonexistent');

        $this->subscriptionService->updateSubscriptionFromStripe($stripeSubscription);
    }

    public function test_handles_subscription_cancellation(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'stripe_subscription_id' => 'sub_test123',
            'status' => 'active',
            'canceled_at' => null
        ]);

        $this->subscriptionService->handleSubscriptionCancellation('sub_test123');

        $subscription->refresh();
        $this->assertEquals('canceled', $subscription->status);
        $this->assertNotNull($subscription->canceled_at);
    }

    public function test_handles_cancellation_for_nonexistent_subscription(): void
    {
        // Should not throw exception, just log warning
        $this->subscriptionService->handleSubscriptionCancellation('sub_nonexistent');
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_handles_successful_payment(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test123'
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'stripe_subscription_id' => 'sub_test123',
            'status' => 'past_due'
        ]);

        $stripeInvoice = (object) [
            'id' => 'in_test123',
            'customer' => 'cus_test123',
            'subscription' => 'sub_test123',
            'amount_paid' => 2000,
            'currency' => 'usd',
            'status' => 'paid',
            'invoice_pdf' => 'https://pay.stripe.com/invoice/test.pdf',
            'hosted_invoice_url' => 'https://invoice.stripe.com/test'
        ];

        $this->subscriptionService->handleSuccessfulPayment($stripeInvoice);

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'stripe_invoice_id' => 'in_test123',
            'amount_paid' => 2000,
            'status' => 'paid'
        ]);

        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
    }

    public function test_handles_payment_for_nonexistent_user(): void
    {
        $stripeInvoice = (object) [
            'id' => 'in_test123',
            'customer' => 'cus_nonexistent',
            'subscription' => null,
            'amount_paid' => 2000,
            'currency' => 'usd',
            'status' => 'paid',
            'invoice_pdf' => null,
            'hosted_invoice_url' => null
        ];

        // Should not throw exception, just log warning
        $this->subscriptionService->handleSuccessfulPayment($stripeInvoice);
        
        // Test passes if no exception is thrown and no invoice is created
        $this->assertDatabaseMissing('invoices', [
            'stripe_invoice_id' => 'in_test123'
        ]);
    }

    public function test_handles_payment_without_subscription(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test123'
        ]);

        $stripeInvoice = (object) [
            'id' => 'in_test123',
            'customer' => 'cus_test123',
            'subscription' => null,
            'amount_paid' => 1000,
            'currency' => 'usd',
            'status' => 'paid',
            'invoice_pdf' => null,
            'hosted_invoice_url' => null
        ];

        $this->subscriptionService->handleSuccessfulPayment($stripeInvoice);

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'subscription_id' => null,
            'stripe_invoice_id' => 'in_test123',
            'amount_paid' => 1000,
            'status' => 'paid'
        ]);
    }
}