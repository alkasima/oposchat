<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Notifications\SubscriptionExpiredNotification;
use App\Notifications\SubscriptionExpiringNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandleSubscriptionExpiration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting subscription expiration handling');

        try {
            $this->handleExpiringSubscriptions();
            $this->handleExpiredSubscriptions();
            
            Log::info('Subscription expiration handling completed');
            
        } catch (\Exception $e) {
            Log::error('Subscription expiration handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Handle subscriptions that are expiring soon.
     */
    private function handleExpiringSubscriptions(): void
    {
        // Find subscriptions expiring in the next 3 days
        $expiringSubscriptions = Subscription::with('user')
            ->whereIn('status', ['active', 'trialing'])
            ->where('current_period_end', '<=', now()->addDays(3))
            ->where('current_period_end', '>', now())
            ->where('cancel_at_period_end', true)
            ->get();

        Log::info('Found expiring subscriptions', ['count' => $expiringSubscriptions->count()]);

        foreach ($expiringSubscriptions as $subscription) {
            try {
                $this->processExpiringSubscription($subscription);
            } catch (\Exception $e) {
                Log::error('Failed to process expiring subscription', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Handle subscriptions that have already expired.
     */
    private function handleExpiredSubscriptions(): void
    {
        // Find subscriptions that have expired
        $expiredSubscriptions = Subscription::with('user')
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->where('current_period_end', '<', now())
            ->get();

        Log::info('Found expired subscriptions', ['count' => $expiredSubscriptions->count()]);

        foreach ($expiredSubscriptions as $subscription) {
            try {
                $this->processExpiredSubscription($subscription);
            } catch (\Exception $e) {
                Log::error('Failed to process expired subscription', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function processExpiringSubscription(Subscription $subscription): void
    {
        $user = $subscription->user;
        
        if (!$user) {
            Log::warning('Expiring subscription has no user', ['subscription_id' => $subscription->id]);
            return;
        }

        Log::info('Processing expiring subscription', [
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'expires_at' => $subscription->current_period_end
        ]);

        // Check if we've already sent a notification recently
        $recentNotification = $user->notifications()
            ->where('type', SubscriptionExpiringNotification::class)
            ->where('created_at', '>', now()->subDays(1))
            ->exists();

        if (!$recentNotification) {
            $user->notify(new SubscriptionExpiringNotification($subscription));
            
            Log::info('Sent expiring subscription notification', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id
            ]);
        }
    }

    private function processExpiredSubscription(Subscription $subscription): void
    {
        $user = $subscription->user;
        
        if (!$user) {
            Log::warning('Expired subscription has no user', ['subscription_id' => $subscription->id]);
            return;
        }

        Log::info('Processing expired subscription', [
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'expired_at' => $subscription->current_period_end
        ]);

        // Update subscription status to expired
        $subscription->update([
            'status' => 'expired'
        ]);

        // Send expiration notification
        $user->notify(new SubscriptionExpiredNotification($subscription));

        // Log the expiration for analytics
        Log::info('Subscription expired and user notified', [
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'stripe_subscription_id' => $subscription->stripe_subscription_id
        ]);

        // Here you could also:
        // 1. Revoke access to premium features
        // 2. Update user's plan status
        // 3. Send data to analytics service
        // 4. Schedule follow-up re-engagement campaigns
    }
}