<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\StripeService;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncSubscriptionsWithStripe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:sync 
                            {--user-id= : Sync subscriptions for a specific user ID}
                            {--force : Force sync even if recently synced}
                            {--dry-run : Show what would be synced without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync local subscription data with Stripe';

    protected StripeService $stripeService;
    protected SubscriptionService $subscriptionService;

    public function __construct(StripeService $stripeService, SubscriptionService $subscriptionService)
    {
        parent::__construct();
        $this->stripeService = $stripeService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting subscription sync with Stripe...');

        $userId = $this->option('user-id');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Running in dry-run mode - no changes will be made');
        }

        try {
            $query = Subscription::with('user');
            
            if ($userId) {
                $query->where('user_id', $userId);
                $this->info("Syncing subscriptions for user ID: {$userId}");
            }

            $subscriptions = $query->get();
            
            if ($subscriptions->isEmpty()) {
                $this->warn('No subscriptions found to sync');
                return Command::SUCCESS;
            }

            $this->info("Found {$subscriptions->count()} subscription(s) to sync");

            $syncedCount = 0;
            $errorCount = 0;

            foreach ($subscriptions as $subscription) {
                try {
                    $this->syncSubscription($subscription, $force, $dryRun);
                    $syncedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("Failed to sync subscription {$subscription->id}: {$e->getMessage()}");
                    Log::error('Subscription sync failed', [
                        'subscription_id' => $subscription->id,
                        'stripe_subscription_id' => $subscription->stripe_subscription_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->info("Sync completed: {$syncedCount} synced, {$errorCount} errors");
            
            return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Sync failed: {$e->getMessage()}");
            Log::error('Subscription sync command failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    private function syncSubscription(Subscription $subscription, bool $force, bool $dryRun): void
    {
        // Skip recently synced subscriptions unless forced
        if (!$force && $subscription->updated_at->gt(now()->subMinutes(5))) {
            $this->line("Skipping recently synced subscription {$subscription->id}");
            return;
        }

        $this->line("Syncing subscription {$subscription->id} (Stripe: {$subscription->stripe_subscription_id})");

        // Retrieve subscription from Stripe
        $stripeSubscription = $this->stripeService->retrieveSubscription($subscription->stripe_subscription_id);

        if (!$stripeSubscription) {
            throw new \Exception("Subscription not found in Stripe");
        }

        // Check if sync is needed
        $needsSync = $this->needsSync($subscription, $stripeSubscription);

        if (!$needsSync) {
            $this->line("  No changes detected");
            return;
        }

        if ($dryRun) {
            $this->line("  Would update: status={$stripeSubscription->status}, period_end=" . 
                       date('Y-m-d H:i:s', $stripeSubscription->current_period_end));
            return;
        }

        // Update subscription
        $this->subscriptionService->syncWithStripe($subscription, $stripeSubscription);
        $this->line("  ✓ Synced successfully");
    }

    private function needsSync(Subscription $subscription, $stripeSubscription): bool
    {
        return $subscription->status !== $stripeSubscription->status ||
               $subscription->current_period_end->timestamp !== $stripeSubscription->current_period_end ||
               $subscription->cancel_at_period_end !== $stripeSubscription->cancel_at_period_end;
    }
}