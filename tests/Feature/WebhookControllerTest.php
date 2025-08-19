<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.stripe.webhook_secret', $this->webhookSecret);
    }

    public function test_webhook_requires_valid_signature(): void
    {
        $payload = json_encode(['type' => 'customer.subscription.created']);
        
        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 'invalid_signature',
            'CONTENT_TYPE' => 'application/json'
        ], $payload);

        $response->assertStatus(400);
        $response->assertSeeText('Webhook signature verification failed');
    }

    public function test_handles_subscription_created_event(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test123'
        ]);

        $subscriptionData = [
            'id' => 'sub_test123',
            'customer' => 'cus_test123',
            'status' => 'active',
            'current_period_start' => now()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'trial_start' => null,
            'trial_end' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'items' => [
                'data' => [
                    [
                        'id' => 'si_test123',
                        'price' => ['id' => 'price_test123'],
                        'quantity' => 1
                    ]
                ]
            ]
        ];

        $payload = json_encode([
            'id' => 'evt_test123',
            'type' => 'customer.subscription.created',
            'data' => ['object' => $subscriptionData]
        ]);

        $signature = $this->generateWebhookSignature($payload);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json'
        ], $payload);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'stripe_subscription_id' => 'sub_test123',
            'status' => 'active'
        ]);
    }

    public function test_handles_subscription_updated_event(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test123'
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'stripe_subscription_id' => 'sub_test123',
            'status' => 'active'
        ]);

        $subscriptionData = [
            'id' => 'sub_test123',
            'customer' => 'cus_test123',
            'status' => 'past_due',
            'current_period_start' => now()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'trial_start' => null,
            'trial_end' => null,
            'cancel_at_period_end' => true,
            'canceled_at' => null,
            'items' => [
                'data' => [
                    [
                        'id' => 'si_test123',
                        'price' => ['id' => 'price_test123'],
                        'quantity' => 1
                    ]
                ]
            ]
        ];

        $payload = json_encode([
            'id' => 'evt_test123',
            'type' => 'customer.subscription.updated',
            'data' => ['object' => $subscriptionData]
        ]);

        $signature = $this->generateWebhookSignature($payload);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json'
        ], $payload);

        $response->assertStatus(200);
        
        $subscription->refresh();
        $this->assertEquals('past_due', $subscription->status);
        $this->assertTrue($subscription->cancel_at_period_end);
    }

    public function test_handles_subscription_deleted_event(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test123'
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'stripe_subscription_id' => 'sub_test123',
            'status' => 'active'
        ]);

        $subscriptionData = [
            'id' => 'sub_test123',
            'customer' => 'cus_test123',
            'status' => 'canceled'
        ];

        $payload = json_encode([
            'id' => 'evt_test123',
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => $subscriptionData]
        ]);

        $signature = $this->generateWebhookSignature($payload);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json'
        ], $payload);

        $response->assertStatus(200);
        
        $subscription->refresh();
        $this->assertEquals('canceled', $subscription->status);
        $this->assertNotNull($subscription->canceled_at);
    }

    public function test_handles_invoice_payment_succeeded_event(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test123'
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'stripe_subscription_id' => 'sub_test123',
            'status' => 'past_due'
        ]);

        $invoiceData = [
            'id' => 'in_test123',
            'customer' => 'cus_test123',
            'subscription' => 'sub_test123',
            'amount_paid' => 2000,
            'currency' => 'usd',
            'status' => 'paid',
            'invoice_pdf' => 'https://pay.stripe.com/invoice/test.pdf',
            'hosted_invoice_url' => 'https://invoice.stripe.com/test'
        ];

        $payload = json_encode([
            'id' => 'evt_test123',
            'type' => 'invoice.payment_succeeded',
            'data' => ['object' => $invoiceData]
        ]);

        $signature = $this->generateWebhookSignature($payload);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json'
        ], $payload);

        $response->assertStatus(200);
        
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

    public function test_handles_unknown_event_type(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Unhandled webhook event type', [
                'event_type' => 'unknown.event.type',
                'event_id' => 'evt_test123'
            ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Webhook processed successfully', [
                'event_type' => 'unknown.event.type',
                'event_id' => 'evt_test123'
            ]);

        $payload = json_encode([
            'id' => 'evt_test123',
            'type' => 'unknown.event.type',
            'data' => ['object' => []]
        ]);

        $signature = $this->generateWebhookSignature($payload);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json'
        ], $payload);

        $response->assertStatus(200);
    }

    public function test_handles_webhook_processing_errors(): void
    {
        // Create a payload that will cause an error (missing customer)
        $subscriptionData = [
            'id' => 'sub_test123',
            'customer' => 'cus_nonexistent',
            'status' => 'active',
            'items' => [
                'data' => [
                    [
                        'id' => 'si_test123',
                        'price' => ['id' => 'price_test123'],
                        'quantity' => 1
                    ]
                ]
            ]
        ];

        $payload = json_encode([
            'id' => 'evt_test123',
            'type' => 'customer.subscription.created',
            'data' => ['object' => $subscriptionData]
        ]);

        $signature = $this->generateWebhookSignature($payload);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json'
        ], $payload);

        $response->assertStatus(500);
        $response->assertSeeText('Webhook processing failed');
    }

    /**
     * Generate a valid webhook signature for testing
     */
    private function generateWebhookSignature(string $payload): string
    {
        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $signature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);
        
        return "t={$timestamp},v1={$signature}";
    }
}