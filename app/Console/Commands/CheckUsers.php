<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CheckUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check user Stripe customer IDs and subscription status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking all users...');
        
        $users = User::all();
        
        foreach ($users as $user) {
            $this->info("User ID: {$user->id}");
            $this->info("  Name: {$user->name}");
            $this->info("  Email: {$user->email}");
            $this->info("  Stripe Customer ID: " . ($user->stripe_customer_id ?: 'NULL'));
            $this->info("  Current Plan: " . $user->getCurrentPlanName());
            $this->info("  Has Premium Access: " . ($user->hasPremiumAccess() ? 'Yes' : 'No'));
            $this->info("  Active Subscription: " . ($user->activeSubscription() ? 'Yes' : 'No'));
            $this->info("");
        }
        
        return 0;
    }
}