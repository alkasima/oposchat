<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessStripeWebhook;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class WebhookController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    /**
     * Handle incoming Stripe webhook events
     */
    public function handleWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            // Verify webhook signature
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (SignatureVerificationException $e) {
            Log::error('Webhook signature verification failed', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            return response('Webhook signature verification failed', 400);
        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            return response('Webhook processing error', 400);
        }

        // Handle the event
        try {
            // Check if this event should be processed asynchronously
            if ($this->shouldProcessAsync($event->type)) {
                // Dispatch job for asynchronous processing
                ProcessStripeWebhook::dispatch($event->toArray(), $event->type);
                
                Log::info('Webhook queued for processing', [
                    'event_type' => $event->type,
                    'event_id' => $event->id
                ]);
            } else {
                // Process synchronously for critical events
                $this->processWebhookEvent($event);
                
                Log::info('Webhook processed synchronously', [
                    'event_type' => $event->type,
                    'event_id' => $event->id
                ]);
            }
            
            return response('Webhook handled', 200);
        } catch (\Exception $e) {
            Log::error('Failed to handle webhook event', [
                'event_type' => $event->type,
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);
            
            return response('Webhook processing failed', 500);
        }
    }

    /**
     * Determine if webhook event should be processed asynchronously
     */
    private function shouldProcessAsync(string $eventType): bool
    {
        // Process most events asynchronously to improve webhook response time
        $asyncEvents = [
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'invoice.payment_succeeded',
            'invoice.payment_failed',
            'customer.subscription.trial_will_end'
        ];

        return in_array($eventType, $asyncEvents);
    }

    /**
     * Process different types of webhook events (for synchronous processing)
     */
    private function processWebhookEvent($event): void
    {
        switch ($event->type) {
            case 'customer.subscription.created':
                $this->handleSubscriptionCreated($event->data->object);
                break;
                
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;
                
            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;
                
            case 'invoice.payment_succeeded':
                $this->handleInvoicePaymentSucceeded($event->data->object);
                break;
                
            default:
                Log::info('Unhandled webhook event type', [
                    'event_type' => $event->type,
                    'event_id' => $event->id
                ]);
        }
    }  
  /**
     * Handle subscription.created events
     */
    private function handleSubscriptionCreated($subscription): void
    {
        Log::info('Processing subscription.created event', [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer
        ]);

        $this->subscriptionService->createSubscriptionFromStripe($subscription);
    }

    /**
     * Handle subscription.updated events
     */
    private function handleSubscriptionUpdated($subscription): void
    {
        Log::info('Processing subscription.updated event', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status
        ]);

        $this->subscriptionService->updateSubscriptionFromStripe($subscription);
    }

    /**
     * Handle subscription.deleted events
     */
    private function handleSubscriptionDeleted($subscription): void
    {
        Log::info('Processing subscription.deleted event', [
            'subscription_id' => $subscription->id
        ]);

        $this->subscriptionService->handleSubscriptionCancellation($subscription->id);
    }

    /**
     * Handle invoice.payment_succeeded events
     */
    private function handleInvoicePaymentSucceeded($invoice): void
    {
        Log::info('Processing invoice.payment_succeeded event', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription,
            'amount_paid' => $invoice->amount_paid
        ]);

        $this->subscriptionService->handleSuccessfulPayment($invoice);
    }
}