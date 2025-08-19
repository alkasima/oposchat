<?php

namespace App\Console\Commands;

use App\Jobs\HandleSubscriptionExpiration;
use App\Models\Subscription;
use App\Services\StripeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckSubscriptionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:check-status 
                            {--sync : Sync with Stripe before checking}
                            {--handle-expirations : Process subscription expirations}
                            {--dry-run : Show what would be processed without taking action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check subscription status and handle expirations';

    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        parent::__construct();
        $this->stripeService = $stripeService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sync = $this->option('sync');
        $handleExpirations = $this->option('handle-expirations');
        $dryRun = $this->option('dry-run');

        $this->info('Starting subscription status check...');

        if ($dryRun) {
            $this->warn('Running in dry-run mode - no changes will be made');
        }

        try {
            // Sync with Stripe if requested
            if ($sync) {
                $this->syncSubscriptions($dryRun);
            }

            // Check subscription statuses
            $this->checkSubscriptionStatuses($dryRun);

            // Handle expirations if requested
            if ($handleExpirations) {
                $this->handleExpirations($dryRun);
            }

            $this->info('Subscription status check completed successfully');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Status check failed: {$e->getMessage()}");
            Log::error('Subscription status check failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    private function syncSubscriptions(bool $dryRun): void
    {
        $this->info('Syncing subscriptions with Stripe...');

        if ($dryRun) {
            $this->line('Would run: php artisan subscriptions:sync');
            return;
        }

        // Call the sync command
        $exitCode = $this->call('subscriptions:sync');
        
        if ($exitCode !== 0) {
            throw new \Exception('Subscription sync failed');
        }
    }

    private function checkSubscriptionStatuses(bool $dryRun): void
    {
        $this->info('Checking subscription statuses...');

        // Get all active subscriptions
        $subscriptions = Subscription::whereIn('status', ['active', 'trialing', 'past_due'])
            ->with('user')
            ->get();

        $this->info("Found {$subscriptions->count()} active subscription(s) to check");

        $statusCounts = [
            'active' => 0,
            'trialing' => 0,
            'past_due' => 0,
            'expiring_soon' => 0,
            'expired' => 0
        ];

        foreach ($subscriptions as $subscription) {
            $status = $this->analyzeSubscriptionStatus($subscription);
            $statusCounts[$status]++;

            if ($status === 'expired' || $status === 'expiring_soon') {
                $this->warn("Subscription {$subscription->id} is {$status}");
            }
        }

        $this->displayStatusSummary($statusCounts);
    }

    private function analyzeSubscriptionStatus(Subscription $subscription): string
    {
        $now = now();
        
        // Check if expired
        if ($subscription->current_period_end < $now) {
            return 'expired';
        }

        // Check if expiring soon (within 3 days)
        if ($subscription->current_period_end <= $now->addDays(3) && $subscription->cancel_at_period_end) {
            return 'expiring_soon';
        }

        return $subscription->status;
    }

    private function displayStatusSummary(array $statusCounts): void
    {
        $this->info('Subscription Status Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Active', $statusCounts['active']],
                ['Trialing', $statusCounts['trialing']],
                ['Past Due', $statusCounts['past_due']],
                ['Expiring Soon', $statusCounts['expiring_soon']],
                ['Expired', $statusCounts['expired']],
            ]
        );
    }

    private function handleExpirations(bool $dryRun): void
    {
        $this->info('Handling subscription expirations...');

        if ($dryRun) {
            $this->line('Would dispatch HandleSubscriptionExpiration job');
            return;
        }

        // Dispatch the expiration handling job
        HandleSubscriptionExpiration::dispatch();
        $this->info('Expiration handling job dispatched');
    }
}