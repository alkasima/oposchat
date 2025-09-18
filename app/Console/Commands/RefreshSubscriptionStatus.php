<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RefreshSubscriptionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:refresh {--user-id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh subscription status for a user or all users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');

        if ($userId) {
            $this->refreshUserSubscription($userId);
        } else {
            $this->refreshAllSubscriptions();
        }
    }

    private function refreshUserSubscription($userId)
    {
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }

        $this->info("Refreshing subscription for user: {$user->name} ({$user->email})");
        
        $activeSubscription = $user->activeSubscription();
        $currentPlanName = $user->getCurrentPlanName();
        $hasPremiumAccess = $user->hasPremiumAccess();
        
        $this->info("Current plan: {$currentPlanName}");
        $this->info("Has premium access: " . ($hasPremiumAccess ? 'Yes' : 'No'));
        
        if ($activeSubscription) {
            $this->info("Active subscription ID: {$activeSubscription->id}");
            $this->info("Subscription status: {$activeSubscription->status}");
            $this->info("Stripe subscription ID: {$activeSubscription->stripe_subscription_id}");
            $this->info("Stripe price ID: {$activeSubscription->stripe_price_id}");
        } else {
            $this->info("No active subscription found");
        }

        return 0;
    }

    private function refreshAllSubscriptions()
    {
        $users = User::all();
        
        $this->info("Refreshing subscription status for all users...");
        
        foreach ($users as $user) {
            $this->info("User: {$user->name} ({$user->email})");
            
            $activeSubscription = $user->activeSubscription();
            $currentPlanName = $user->getCurrentPlanName();
            $hasPremiumAccess = $user->hasPremiumAccess();
            
            $this->info("  Current plan: {$currentPlanName}");
            $this->info("  Has premium access: " . ($hasPremiumAccess ? 'Yes' : 'No'));
            
            if ($activeSubscription) {
                $this->info("  Active subscription ID: {$activeSubscription->id}");
                $this->info("  Subscription status: {$activeSubscription->status}");
            } else {
                $this->info("  No active subscription found");
            }
            
            $this->info("");
        }

        return 0;
    }
}