<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class HandleFailedPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:handle-failed 
                            {--days=1 : Number of days to look back for failed payments}
                            {--notify : Send notifications to users with failed payments}
                            {--dry-run : Show what would be processed without taking action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle failed payment notifications and cleanup';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $notify = $this->option('notify');
        $dryRun = $this->option('dry-run');

        $this->info("Processing failed payments from the last {$days} day(s)...");

        if ($dryRun) {
            $this->warn('Running in dry-run mode - no actions will be taken');
        }

        try {
            // Get failed invoices from the specified period
            $failedInvoices = Invoice::where('status', 'payment_failed')
                ->where('created_at', '>=', now()->subDays($days))
                ->with(['user', 'subscription'])
                ->get();

            if ($failedInvoices->isEmpty()) {
                $this->info('No failed payments found in the specified period');
                return Command::SUCCESS;
            }

            $this->info("Found {$failedInvoices->count()} failed payment(s)");

            $processedCount = 0;
            $notificationCount = 0;
            $errorCount = 0;

            foreach ($failedInvoices as $invoice) {
                try {
                    $this->processFailedPayment($invoice, $notify, $dryRun);
                    $processedCount++;

                    if ($notify && !$dryRun) {
                        $notificationCount++;
                    }

                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("Failed to process invoice {$invoice->id}: {$e->getMessage()}");
                    Log::error('Failed payment processing error', [
                        'invoice_id' => $invoice->id,
                        'user_id' => $invoice->user_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->info("Processing completed:");
            $this->line("  - Processed: {$processedCount}");
            if ($notify) {
                $this->line("  - Notifications sent: {$notificationCount}");
            }
            $this->line("  - Errors: {$errorCount}");

            return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");
            Log::error('Handle failed payments command failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    private function processFailedPayment(Invoice $invoice, bool $notify, bool $dryRun): void
    {
        $user = $invoice->user;
        
        if (!$user) {
            $this->warn("Invoice {$invoice->id} has no associated user");
            return;
        }

        $this->line("Processing failed payment for user {$user->email} (Invoice: {$invoice->stripe_invoice_id})");

        // Check if user has active subscription
        $hasActiveSubscription = $user->subscriptions()
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->exists();

        if ($dryRun) {
            $this->line("  Would process failed payment:");
            $this->line("    - Amount: {$invoice->amount_paid} {$invoice->currency}");
            $this->line("    - Has active subscription: " . ($hasActiveSubscription ? 'Yes' : 'No'));
            
            if ($notify) {
                $this->line("    - Would send notification to: {$user->email}");
            }
            return;
        }

        // Log the failed payment
        Log::warning('Processing failed payment', [
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'stripe_invoice_id' => $invoice->stripe_invoice_id,
            'amount' => $invoice->amount_paid,
            'currency' => $invoice->currency,
            'has_active_subscription' => $hasActiveSubscription
        ]);

        // Send notification if requested
        if ($notify) {
            try {
                $user->notify(new PaymentFailedNotification($invoice));
                $this->line("  ✓ Notification sent to {$user->email}");
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to send notification: {$e->getMessage()}");
                throw $e;
            }
        }

        // If user has no active subscription, they might need account restrictions
        if (!$hasActiveSubscription) {
            $this->handleUserWithoutActiveSubscription($user, $dryRun);
        }

        $this->line("  ✓ Processed successfully");
    }

    private function handleUserWithoutActiveSubscription(User $user, bool $dryRun): void
    {
        // Check if user should be downgraded to free tier
        $subscriptions = $user->subscriptions()
            ->whereIn('status', ['canceled', 'incomplete_expired', 'unpaid'])
            ->get();

        if ($subscriptions->isNotEmpty()) {
            $this->line("    - User has inactive subscriptions, may need downgrade");
            
            if (!$dryRun) {
                // Here you could implement logic to:
                // 1. Restrict access to premium features
                // 2. Update user's plan status
                // 3. Send downgrade notification
                
                Log::info('User may need subscription downgrade', [
                    'user_id' => $user->id,
                    'inactive_subscriptions' => $subscriptions->count()
                ]);
            }
        }
    }
}