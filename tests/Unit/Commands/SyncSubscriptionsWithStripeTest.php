<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\SyncSubscriptionsWithStripe;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StripeService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncSubscriptionsWithStripeTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_handles_no_subscriptions(): void
    {
        $stripeService = $this->createMock(StripeService::class);
        $subscriptionService = $this->createMock(SubscriptionService::class);

        $command = new SyncSubscriptionsWithStripe($stripeService, $subscriptionService);
        
        $this->artisan('subscriptions:sync --dry-run')
            ->expectsOutput('Starting subscription sync with Stripe...')
            ->expectsOutput('Running in dry-run mode - no changes will be made')
            ->expectsOutput('No subscriptions found to sync')
            ->assertExitCode(0);
    }

    public function test_command_can_filter_by_user_id(): void
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_test']);
        
        Subscription::factory()->create([
            'user_id' => $user->id,
            'stripe_subscription_id' => 'sub_test'
        ]);

        $this->artisan("subscriptions:sync --user-id={$user->id} --dry-run")
            ->expectsOutput('Starting subscription sync with Stripe...')
            ->expectsOutput("Syncing subscriptions for user ID: {$user->id}")
            ->assertExitCode(0);
    }

    public function test_command_shows_dry_run_mode(): void
    {
        $this->artisan('subscriptions:sync --dry-run')
            ->expectsOutput('Running in dry-run mode - no changes will be made')
            ->assertExitCode(0);
    }
}