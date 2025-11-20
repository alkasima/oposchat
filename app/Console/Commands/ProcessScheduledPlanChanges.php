<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\StripeService;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledPlanChanges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:process-scheduled-changes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled plan changes for subscriptions that have reached their change date';

    /**
     * Execute the console command.
     */
    public function handle(StripeService $stripeService, SubscriptionService $subscriptionService): int
    {
        $this->info('Processing scheduled plan changes...');

        // Find all subscriptions with scheduled changes that are due
        $subscriptions = Subscription::whereNotNull('scheduled_plan_change_price_id')
            ->whereNotNull('scheduled_plan_change_at')
            ->where('scheduled_plan_change_at', '<=', now())
            ->where('status', 'active')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No scheduled plan changes to process.');
            return Command::SUCCESS;
        }

        $this->info("Found {$subscriptions->count()} scheduled plan change(s) to process.");

        $processed = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $this->info("Processing subscription {$subscription->stripe_subscription_id}...");

                // Get the subscription item
                $item = $subscription->items()->first();
                if (!$item) {
                    // Try to get from Stripe
                    $stripeSubLive = $stripeService->retrieveSubscriptionById($subscription->stripe_subscription_id);
                    if (!empty($stripeSubLive->items->data[0]->id)) {
                        $stripeItemId = $stripeSubLive->items->data[0]->id;
                    } else {
                        throw new \Exception('No subscription item found');
                    }
                } else {
                    $stripeItemId = $item->stripe_subscription_item_id;
                }

                // Update the subscription in Stripe
                $stripeSub = $stripeService->updateSubscription($subscription->stripe_subscription_id, [
                    'items' => [[
                        'id' => $stripeItemId,
                        'price' => $subscription->scheduled_plan_change_price_id,
                        'quantity' => 1,
                    ]],
                    'proration_behavior' => 'none', // No proration for downgrades
                ]);

                // Update local subscription
                $subscriptionService->handleImmediatePaymentSuccess($stripeSub);

                // Clear the scheduled change
                $subscription->update([
                    'scheduled_plan_change_price_id' => null,
                    'scheduled_plan_change_at' => null,
                ]);

                $this->info("✓ Successfully processed subscription {$subscription->stripe_subscription_id}");
                $processed++;

            } catch (\Exception $e) {
                $this->error("✗ Failed to process subscription {$subscription->stripe_subscription_id}: {$e->getMessage()}");
                Log::error('Failed to process scheduled plan change', [
                    'subscription_id' => $subscription->id,
                    'stripe_subscription_id' => $subscription->stripe_subscription_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $failed++;
            }
        }

        $this->info("\nProcessing complete:");
        $this->info("✓ Processed: {$processed}");
        if ($failed > 0) {
            $this->error("✗ Failed: {$failed}");
        }

        return Command::SUCCESS;
    }
}
