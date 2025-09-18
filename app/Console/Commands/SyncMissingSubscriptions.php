<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class SyncMissingSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:sync-missing {--user-id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync missing subscriptions for users who have Stripe customer IDs but no local subscriptions';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptionService)
    {
        $userId = $this->option('user-id');

        if ($userId) {
            $this->syncSpecificUser($subscriptionService, $userId);
        } else {
            $this->syncAllMissingSubscriptions($subscriptionService);
        }

        return 0;
    }

    private function syncSpecificUser(SubscriptionService $subscriptionService, $userId)
    {
        $user = \App\Models\User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }

        if (!$user->stripe_customer_id) {
            $this->error("User {$userId} has no Stripe customer ID");
            return 1;
        }

        $this->info("Syncing subscriptions for user: {$user->name} ({$user->email})");
        
        try {
            $subscriptionService->syncUserSubscriptionsFromStripe($user);
            $this->info("✅ Successfully synced subscriptions for user {$userId}");
            $this->info("Current plan: " . $user->getCurrentPlanName());
        } catch (\Exception $e) {
            $this->error("❌ Failed to sync subscriptions for user {$userId}: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function syncAllMissingSubscriptions(SubscriptionService $subscriptionService)
    {
        $this->info("Syncing missing subscriptions for all users...");
        
        $results = $subscriptionService->syncMissingSubscriptions();
        
        if (empty($results)) {
            $this->info("No users found with missing subscriptions.");
            return 0;
        }

        $this->info("Sync results:");
        foreach ($results as $result) {
            if ($result['status'] === 'synced') {
                $this->info("✅ User {$result['user_id']} ({$result['email']}): {$result['plan']}");
            } else {
                $this->error("❌ User {$result['user_id']} ({$result['email']}): {$result['error']}");
            }
        }

        return 0;
    }
}