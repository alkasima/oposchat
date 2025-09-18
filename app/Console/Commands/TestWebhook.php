<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:webhook {event_type} {--user-id=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test webhook processing for different event types';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptionService)
    {
        $eventType = $this->argument('event_type');
        $userId = $this->option('user-id');

        $this->info("Testing webhook event: {$eventType} for user ID: {$userId}");

        try {
            switch ($eventType) {
                case 'subscription.created':
                    $this->testSubscriptionCreated($subscriptionService, $userId);
                    break;
                case 'payment.succeeded':
                    $this->testPaymentSucceeded($subscriptionService, $userId);
                    break;
                case 'subscription.updated':
                    $this->testSubscriptionUpdated($subscriptionService, $userId);
                    break;
                default:
                    $this->error("Unknown event type: {$eventType}");
                    return 1;
            }

            $this->info('Webhook test completed successfully');
            return 0;
        } catch (\Exception $e) {
            $this->error('Webhook test failed: ' . $e->getMessage());
            Log::error('Webhook test failed', [
                'event_type' => $eventType,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }

    private function testSubscriptionCreated(SubscriptionService $subscriptionService, $userId)
    {
        // Get the actual user's stripe customer ID
        $user = \App\Models\User::find($userId);
        if (!$user || !$user->stripe_customer_id) {
            throw new \Exception("User {$userId} not found or doesn't have a stripe_customer_id");
        }

        // Create a mock Stripe subscription object
        $mockSubscription = (object) [
            'id' => 'sub_test_' . time(),
            'customer' => $user->stripe_customer_id,
            'status' => 'active',
            'current_period_start' => time(),
            'current_period_end' => time() + (30 * 24 * 60 * 60), // 30 days
            'trial_start' => null,
            'trial_end' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'items' => (object) [
                'data' => [
                    (object) [
                        'id' => 'si_test_' . time(),
                        'price' => (object) [
                            'id' => 'price_1RuE5gAVc1w1yLTUdkry1i2o' // Premium plan
                        ],
                        'quantity' => 1
                    ]
                ]
            ]
        ];

        $subscription = $subscriptionService->createSubscriptionFromStripe($mockSubscription);
        $this->info("Created subscription ID: {$subscription->id}");
    }

    private function testPaymentSucceeded(SubscriptionService $subscriptionService, $userId)
    {
        // Get the actual user's stripe customer ID
        $user = \App\Models\User::find($userId);
        if (!$user || !$user->stripe_customer_id) {
            throw new \Exception("User {$userId} not found or doesn't have a stripe_customer_id");
        }

        // Create a mock Stripe invoice object
        $mockInvoice = (object) [
            'id' => 'in_test_' . time(),
            'customer' => $user->stripe_customer_id,
            'subscription' => 'sub_test_' . time(),
            'amount_paid' => 999, // €9.99 in cents
            'currency' => 'eur',
            'status' => 'paid',
            'invoice_pdf' => null,
            'hosted_invoice_url' => null
        ];

        $subscriptionService->handleSuccessfulPayment($mockInvoice);
        $this->info("Processed payment for invoice: {$mockInvoice->id}");
    }

    private function testSubscriptionUpdated(SubscriptionService $subscriptionService, $userId)
    {
        // Get the actual user's stripe customer ID
        $user = \App\Models\User::find($userId);
        if (!$user || !$user->stripe_customer_id) {
            throw new \Exception("User {$userId} not found or doesn't have a stripe_customer_id");
        }

        // Create a mock Stripe subscription object
        $mockSubscription = (object) [
            'id' => 'sub_test_' . time(),
            'customer' => $user->stripe_customer_id,
            'status' => 'active',
            'current_period_start' => time(),
            'current_period_end' => time() + (30 * 24 * 60 * 60), // 30 days
            'trial_start' => null,
            'trial_end' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'items' => (object) [
                'data' => [
                    (object) [
                        'id' => 'si_test_' . time(),
                        'price' => (object) [
                            'id' => 'price_1RuE5gAVc1w1yLTUopmMCnBb' // Plus plan
                        ],
                        'quantity' => 1
                    ]
                ]
            ]
        ];

        $subscription = $subscriptionService->updateSubscriptionFromStripe($mockSubscription);
        $this->info("Updated subscription ID: {$subscription->id}");
    }
}