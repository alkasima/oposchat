<?php

namespace App\Jobs;

use App\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stripe\Event;

class ProcessStripeWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 3;
    public int $timeout = 60;

    protected array $webhookData;
    protected string $eventType;

    /**
     * Create a new job instance.
     */
    public function __construct(array $webhookData, string $eventType)
    {
        $this->webhookData = $webhookData;
        $this->eventType = $eventType;
        
        // Set queue based on event priority
        $this->onQueue($this->getQueueForEvent($eventType));
    }

    /**
     * Execute the job.
     */
    public function handle(SubscriptionService $subscriptionService): void
    {
        Log::info('Processing Stripe webhook', [
            'event_type' => $this->eventType,
            'event_id' => $this->webhookData['id'] ?? 'unknown'
        ]);

        try {
            switch ($this->eventType) {
                case 'customer.subscription.created':
                    $this->handleSubscriptionCreated($subscriptionService);
                    break;

                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($subscriptionService);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($subscriptionService);
                    break;

                case 'invoice.payment_succeeded':
                    $this->handleInvoicePaymentSucceeded($subscriptionService);
                    break;

                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($subscriptionService);
                    break;

                case 'customer.subscription.trial_will_end':
                    $this->handleTrialWillEnd($subscriptionService);
                    break;

                default:
                    Log::info('Unhandled webhook event type', ['event_type' => $this->eventType]);
            }

            Log::info('Webhook processed successfully', [
                'event_type' => $this->eventType,
                'event_id' => $this->webhookData['id'] ?? 'unknown'
            ]);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'event_type' => $this->eventType,
                'event_id' => $this->webhookData['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle failed job.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook job failed permanently', [
            'event_type' => $this->eventType,
            'event_id' => $this->webhookData['id'] ?? 'unknown',
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Could send notification to administrators here
    }

    private function handleSubscriptionCreated(SubscriptionService $subscriptionService): void
    {
        $subscription = $this->webhookData['data']['object'];
        $subscriptionService->handleStripeSubscriptionCreated($subscription);
    }

    private function handleSubscriptionUpdated(SubscriptionService $subscriptionService): void
    {
        $subscription = $this->webhookData['data']['object'];
        $subscriptionService->handleStripeSubscriptionUpdated($subscription);
    }

    private function handleSubscriptionDeleted(SubscriptionService $subscriptionService): void
    {
        $subscription = $this->webhookData['data']['object'];
        $subscriptionService->handleStripeSubscriptionDeleted($subscription);
    }

    private function handleInvoicePaymentSucceeded(SubscriptionService $subscriptionService): void
    {
        $invoice = $this->webhookData['data']['object'];
        $subscriptionService->handleStripeInvoicePaymentSucceeded($invoice);
    }

    private function handleInvoicePaymentFailed(SubscriptionService $subscriptionService): void
    {
        $invoice = $this->webhookData['data']['object'];
        $subscriptionService->handleStripeInvoicePaymentFailed($invoice);
    }

    private function handleTrialWillEnd(SubscriptionService $subscriptionService): void
    {
        $subscription = $this->webhookData['data']['object'];
        $subscriptionService->handleStripeTrialWillEnd($subscription);
    }

    private function getQueueForEvent(string $eventType): string
    {
        // High priority events
        $highPriorityEvents = [
            'customer.subscription.created',
            'customer.subscription.deleted',
            'invoice.payment_succeeded'
        ];

        return in_array($eventType, $highPriorityEvents) ? 'high' : 'default';
    }
}