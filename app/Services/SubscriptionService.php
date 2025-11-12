<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\Subscription as StripeSubscription;

class SubscriptionService
{
    /**
     * Create a subscription record from Stripe webhook data
     */
    public function createSubscriptionFromStripe($stripeSubscription): Subscription
    {
        // Find user by Stripe customer ID
        $user = User::where('stripe_customer_id', $stripeSubscription->customer)->first();
        
        if (!$user) {
            throw new \Exception("User not found for Stripe customer ID: {$stripeSubscription->customer}");
        }

        // Create subscription record
        $priceId = $stripeSubscription->items->data[0]->price->id;
        
        Log::info('Creating subscription with price_id', [
            'stripe_subscription_id' => $stripeSubscription->id,
            'extracted_price_id' => $priceId,
            'all_items' => collect($stripeSubscription->items->data)->map(fn($item) => [
                'id' => $item->id,
                'price_id' => $item->price->id,
                'quantity' => $item->quantity
            ])->toArray()
        ]);
        
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'stripe_customer_id' => $stripeSubscription->customer,
            'stripe_price_id' => $priceId,
            'status' => $stripeSubscription->status,
            'current_period_start' => $stripeSubscription->current_period_start ? 
                now()->createFromTimestamp($stripeSubscription->current_period_start) : null,
            'current_period_end' => $stripeSubscription->current_period_end ? 
                now()->createFromTimestamp($stripeSubscription->current_period_end) : null,
            'trial_start' => $stripeSubscription->trial_start ? 
                now()->createFromTimestamp($stripeSubscription->trial_start) : null,
            'trial_end' => $stripeSubscription->trial_end ? 
                now()->createFromTimestamp($stripeSubscription->trial_end) : null,
            'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end ?? false,
            'canceled_at' => $stripeSubscription->canceled_at ? 
                now()->createFromTimestamp($stripeSubscription->canceled_at) : null,
        ]);

        // Create subscription items
        foreach ($stripeSubscription->items->data as $item) {
            SubscriptionItem::create([
                'subscription_id' => $subscription->id,
                'stripe_subscription_item_id' => $item->id,
                'stripe_price_id' => $item->price->id,
                'quantity' => $item->quantity,
            ]);
        }

        Log::info('Subscription created from webhook', [
            'subscription_id' => $subscription->id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'user_id' => $user->id
        ]);

        // Clear usage cache for the user to ensure new limits take effect immediately
        $this->clearUsageCache($user);

        return $subscription;
    }

    /**
     * Update subscription record from Stripe webhook data
     */
    public function updateSubscriptionFromStripe($stripeSubscription): Subscription
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();
        
        if (!$subscription) {
            throw new \Exception("Subscription not found for Stripe ID: {$stripeSubscription->id}");
        }

        // Update subscription data
        $subscription->update([
            'stripe_price_id' => $stripeSubscription->items->data[0]->price->id,
            'status' => $stripeSubscription->status,
            'current_period_start' => $stripeSubscription->current_period_start ? 
                now()->createFromTimestamp($stripeSubscription->current_period_start) : null,
            'current_period_end' => $stripeSubscription->current_period_end ? 
                now()->createFromTimestamp($stripeSubscription->current_period_end) : null,
            'trial_start' => $stripeSubscription->trial_start ? 
                now()->createFromTimestamp($stripeSubscription->trial_start) : null,
            'trial_end' => $stripeSubscription->trial_end ? 
                now()->createFromTimestamp($stripeSubscription->trial_end) : null,
            'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end ?? false,
            'canceled_at' => $stripeSubscription->canceled_at ? 
                now()->createFromTimestamp($stripeSubscription->canceled_at) : null,
        ]);

        Log::info('Subscription updated from webhook', [
            'subscription_id' => $subscription->id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'status' => $stripeSubscription->status
        ]);

        // Clear usage cache for the user to ensure new limits take effect immediately
        $this->clearUsageCache($subscription->user);

        return $subscription;
    }    /**

     * Handle subscription cancellation from webhook
     */
    public function handleSubscriptionCancellation(string $stripeSubscriptionId): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscriptionId)->first();
        
        if (!$subscription) {
            Log::warning('Subscription not found for cancellation', [
                'stripe_subscription_id' => $stripeSubscriptionId
            ]);
            return;
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        Log::info('Subscription canceled from webhook', [
            'subscription_id' => $subscription->id,
            'stripe_subscription_id' => $stripeSubscriptionId
        ]);
    }

    /**
     * Handle successful payment from invoice webhook
     */
    public function handleSuccessfulPayment($stripeInvoice): void
    {
        // Find user by customer ID
        $user = User::where('stripe_customer_id', $stripeInvoice->customer)->first();
        
        if (!$user) {
            Log::warning('User not found for invoice payment', [
                'stripe_customer_id' => $stripeInvoice->customer,
                'invoice_id' => $stripeInvoice->id
            ]);
            return;
        }

        // Find subscription if this invoice is for a subscription
        $subscription = null;
        if ($stripeInvoice->subscription) {
            $subscription = Subscription::where('stripe_subscription_id', $stripeInvoice->subscription)->first();
        }

        // Create or update invoice record
        Invoice::updateOrCreate(
            ['stripe_invoice_id' => $stripeInvoice->id],
            [
                'user_id' => $user->id,
                'subscription_id' => $subscription?->id,
                'amount_paid' => $stripeInvoice->amount_paid,
                'currency' => $stripeInvoice->currency,
                'status' => $stripeInvoice->status,
                'invoice_pdf' => $stripeInvoice->invoice_pdf,
                'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url,
            ]
        );

        // If this is a subscription payment, ensure subscription is active
        if ($subscription && $stripeInvoice->status === 'paid') {
            $subscription->update(['status' => 'active']);
            
            // Clear usage cache for the user to ensure new limits take effect immediately
            $this->clearUsageCache($user);
        }

        Log::info('Payment processed from webhook', [
            'invoice_id' => $stripeInvoice->id,
            'user_id' => $user->id,
            'subscription_id' => $subscription?->id,
            'amount_paid' => $stripeInvoice->amount_paid
        ]);
    }

    /**
     * Handle Stripe subscription created webhook
     */
    public function handleStripeSubscriptionCreated($stripeSubscription): void
    {
        try {
            $this->createSubscriptionFromStripe($stripeSubscription);
        } catch (\Exception $e) {
            Log::error('Failed to handle subscription created webhook', [
                'stripe_subscription_id' => $stripeSubscription->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle Stripe subscription updated webhook
     */
    public function handleStripeSubscriptionUpdated($stripeSubscription): void
    {
        try {
            $this->updateSubscriptionFromStripe($stripeSubscription);
        } catch (\Exception $e) {
            Log::error('Failed to handle subscription updated webhook', [
                'stripe_subscription_id' => $stripeSubscription->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle Stripe subscription deleted webhook
     */
    public function handleStripeSubscriptionDeleted($stripeSubscription): void
    {
        try {
            $this->handleSubscriptionCancellation($stripeSubscription->id);
        } catch (\Exception $e) {
            Log::error('Failed to handle subscription deleted webhook', [
                'stripe_subscription_id' => $stripeSubscription->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle Stripe invoice payment succeeded webhook
     */
    public function handleStripeInvoicePaymentSucceeded($stripeInvoice): void
    {
        try {
            $this->handleSuccessfulPayment($stripeInvoice);
        } catch (\Exception $e) {
            Log::error('Failed to handle invoice payment succeeded webhook', [
                'stripe_invoice_id' => $stripeInvoice->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle Stripe invoice payment failed webhook
     */
    public function handleStripeInvoicePaymentFailed($stripeInvoice): void
    {
        try {
            // Find user by customer ID
            $user = User::where('stripe_customer_id', $stripeInvoice->customer)->first();
            
            if (!$user) {
                Log::warning('User not found for failed invoice payment', [
                    'stripe_customer_id' => $stripeInvoice->customer,
                    'invoice_id' => $stripeInvoice->id
                ]);
                return;
            }

            // Find subscription if this invoice is for a subscription
            $subscription = null;
            if ($stripeInvoice->subscription) {
                $subscription = Subscription::where('stripe_subscription_id', $stripeInvoice->subscription)->first();
            }

            // Create or update invoice record with failed status
            Invoice::updateOrCreate(
                ['stripe_invoice_id' => $stripeInvoice->id],
                [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription?->id,
                    'amount_paid' => $stripeInvoice->amount_due,
                    'currency' => $stripeInvoice->currency,
                    'status' => 'payment_failed',
                    'invoice_pdf' => $stripeInvoice->invoice_pdf,
                    'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url,
                ]
            );

            // Update subscription status if applicable
            if ($subscription) {
                $subscription->update(['status' => 'past_due']);
            }

            Log::warning('Payment failed from webhook', [
                'invoice_id' => $stripeInvoice->id,
                'user_id' => $user->id,
                'subscription_id' => $subscription?->id,
                'amount_due' => $stripeInvoice->amount_due
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle invoice payment failed webhook', [
                'stripe_invoice_id' => $stripeInvoice->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle Stripe trial will end webhook
     */
    public function handleStripeTrialWillEnd($stripeSubscription): void
    {
        try {
            $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();
            
            if (!$subscription) {
                Log::warning('Subscription not found for trial will end', [
                    'stripe_subscription_id' => $stripeSubscription->id
                ]);
                return;
            }

            Log::info('Trial will end notification', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $stripeSubscription->id,
                'trial_end' => $stripeSubscription->trial_end
            ]);

            // Could send notification to user here
            // $subscription->user->notify(new TrialEndingNotification($subscription));

        } catch (\Exception $e) {
            Log::error('Failed to handle trial will end webhook', [
                'stripe_subscription_id' => $stripeSubscription->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Sync subscription with Stripe data
     */
    public function syncWithStripe(Subscription $subscription, $stripeSubscription): void
    {
        $subscription->update([
            'status' => $stripeSubscription->status,
            'current_period_start' => $stripeSubscription->current_period_start ? 
                now()->createFromTimestamp($stripeSubscription->current_period_start) : null,
            'current_period_end' => $stripeSubscription->current_period_end ? 
                now()->createFromTimestamp($stripeSubscription->current_period_end) : null,
            'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end ?? false,
            'canceled_at' => $stripeSubscription->canceled_at ? 
                now()->createFromTimestamp($stripeSubscription->canceled_at) : null,
        ]);

        Log::info('Subscription synced with Stripe', [
            'subscription_id' => $subscription->id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'status' => $stripeSubscription->status
        ]);
    }

    /**
     * Clear usage cache for a user to ensure new subscription limits take effect immediately
     */
    private function clearUsageCache(User $user): void
    {
        $features = ['chat_messages', 'file_uploads', 'api_calls'];
        
        foreach ($features as $feature) {
            $cacheKey = "usage_{$user->id}_{$feature}";
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }

        Log::info('Usage cache cleared for user', [
            'user_id' => $user->id,
            'features' => $features
        ]);
    }

    /**
     * Handle immediate subscription update after successful payment
     * This bypasses webhook processing for instant updates
     */
    public function handleImmediatePaymentSuccess($stripeSubscription, $stripeInvoice = null): array
    {
        try {
            // Find user by Stripe customer ID
            $user = User::where('stripe_customer_id', $stripeSubscription->customer)->first();
            
            if (!$user) {
                throw new \Exception("User not found for Stripe customer ID: {$stripeSubscription->customer}");
            }

            Log::info('Processing immediate payment success', [
                'user_id' => $user->id,
                'stripe_subscription_id' => $stripeSubscription->id,
                'stripe_status' => $stripeSubscription->status,
                'stripe_customer_id' => $stripeSubscription->customer
            ]);

            // Create or update subscription
            $existing = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();
            if ($existing) {
                $subscription = $this->updateSubscriptionFromStripe($stripeSubscription);
                Log::info('Updated existing subscription', [
                    'subscription_id' => $subscription->id,
                    'old_status' => $existing->status,
                    'new_status' => $subscription->status
                ]);
            } else {
                $subscription = $this->createSubscriptionFromStripe($stripeSubscription);
                Log::info('Created new subscription', [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status
                ]);
            }

            // Force subscription to active status if payment was successful
            if ($stripeSubscription->status === 'active' || ($stripeInvoice && $stripeInvoice->status === 'paid')) {
                $subscription->update(['status' => 'active']);
                Log::info('Forced subscription to active status', [
                    'subscription_id' => $subscription->id,
                    'stripe_status' => $stripeSubscription->status,
                    'invoice_status' => $stripeInvoice?->status
                ]);
            }

            // Handle invoice if provided
            if ($stripeInvoice && $stripeInvoice->status === 'paid') {
                $this->handleSuccessfulPayment($stripeInvoice);
            }

            // Clear usage cache to ensure new limits take effect immediately
            $this->clearUsageCache($user);

            // Refresh user model to get latest data
            $user->refresh();
            $subscription->refresh();

            $planName = $user->getCurrentPlanName();
            $planKey = $user->getCurrentPlanKey();

            Log::info('Immediate payment success handled successfully', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $stripeSubscription->id,
                'plan_name' => $planName,
                'plan_key' => $planKey,
                'subscription_status' => $subscription->status,
                'has_active_subscription' => $user->hasActiveSubscription()
            ]);

            return [
                'success' => true,
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'plan_name' => $planName,
                'plan_key' => $planKey,
                'subscription_status' => $subscription->status,
                'has_active_subscription' => $user->hasActiveSubscription()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to handle immediate payment success', [
                'stripe_subscription_id' => $stripeSubscription->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Sync missing subscriptions for users who have Stripe customer IDs but no local subscriptions
     */
    public function syncMissingSubscriptions(): array
    {
        $results = [];
        
        // Find users with Stripe customer IDs but no subscriptions
        $usersWithoutSubscriptions = User::whereNotNull('stripe_customer_id')
            ->whereDoesntHave('subscriptions')
            ->get();

        foreach ($usersWithoutSubscriptions as $user) {
            try {
                $this->syncUserSubscriptionsFromStripe($user);
                $results[] = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'status' => 'synced',
                    'plan' => $user->getCurrentPlanName()
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Sync subscriptions for a specific user from Stripe
     */
    public function syncUserSubscriptionsFromStripe(User $user): void
    {
        if (!$user->stripe_customer_id) {
            throw new \Exception("User {$user->id} has no Stripe customer ID");
        }

        // Get customer from Stripe
        $stripeService = app(\App\Services\StripeService::class);
        $stripeCustomer = $stripeService->retrieveCustomer($user->stripe_customer_id);
        
        if (!$stripeCustomer) {
            throw new \Exception("Could not retrieve Stripe customer for user {$user->id}");
        }

        // Get active subscriptions from Stripe using the subscription list API
        $stripeSubscriptions = StripeSubscription::all([
            'customer' => $user->stripe_customer_id,
            'status' => 'all'
        ])->data;
        
        foreach ($stripeSubscriptions as $stripeSubscription) {
            if (in_array($stripeSubscription->status, ['active', 'trialing'])) {
                // Check if we already have this subscription locally
                $existing = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();
                
                if (!$existing) {
                    // Create the subscription
                    $this->createSubscriptionFromStripe($stripeSubscription);
                    
                    Log::info('Synced missing subscription from Stripe', [
                        'user_id' => $user->id,
                        'stripe_subscription_id' => $stripeSubscription->id,
                        'plan_name' => $user->getCurrentPlanName()
                    ]);
                }
            }
        }
    }
}