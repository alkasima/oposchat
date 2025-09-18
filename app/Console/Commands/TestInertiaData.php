<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestInertiaData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:inertia-data {--user-id=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test what data would be sent to frontend via Inertia';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }

        $this->info("Testing Inertia data for user: {$user->name} ({$user->email})");
        $this->info("");

        // Simulate what the middleware would send
        $subscriptionData = [
            'has_premium' => $user->hasPremiumAccess(),
            'subscription_status' => $user->subscriptionStatus(),
            'on_trial' => $user->onTrial(),
            'on_grace_period' => $user->onGracePeriod(),
            'current_plan_name' => $user->getCurrentPlanName(),
            'usage' => app(\App\Services\UsageService::class)->getUsageSummary($user),
        ];

        $this->info("Subscription data that would be sent to frontend:");
        $this->info("has_premium: " . ($subscriptionData['has_premium'] ? 'true' : 'false'));
        $this->info("subscription_status: " . $subscriptionData['subscription_status']);
        $this->info("on_trial: " . ($subscriptionData['on_trial'] ? 'true' : 'false'));
        $this->info("on_grace_period: " . ($subscriptionData['on_grace_period'] ? 'true' : 'false'));
        $this->info("current_plan_name: " . $subscriptionData['current_plan_name']);
        $this->info("");

        $this->info("Usage data:");
        foreach ($subscriptionData['usage'] as $feature => $data) {
            $this->info("  {$feature}:");
            $this->info("    usage: " . $data['usage']);
            $this->info("    limit: " . ($data['limit'] ?? 'unlimited'));
            $this->info("    remaining: " . ($data['remaining'] ?? 'unlimited'));
            $this->info("    unlimited: " . ($data['unlimited'] ? 'true' : 'false'));
            $this->info("    not_allowed: " . ($data['not_allowed'] ? 'true' : 'false'));
        }

        return 0;
    }
}