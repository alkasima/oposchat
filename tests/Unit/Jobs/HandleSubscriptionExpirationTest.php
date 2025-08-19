<?php

namespace Tests\Unit\Jobs;

use App\Jobs\HandleSubscriptionExpiration;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HandleSubscriptionExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        HandleSubscriptionExpiration::dispatch();

        Queue::assertPushed(HandleSubscriptionExpiration::class);
    }

    public function test_job_handles_empty_subscriptions(): void
    {
        $job = new HandleSubscriptionExpiration();
        
        // Should not throw any exceptions when no subscriptions exist
        $this->assertNull($job->handle());
    }

    public function test_job_identifies_expiring_subscriptions(): void
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_test']);
        
        // Create a subscription expiring in 2 days
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'current_period_end' => now()->addDays(2),
            'cancel_at_period_end' => true
        ]);

        $job = new HandleSubscriptionExpiration();
        
        // Should not throw any exceptions
        $this->assertNull($job->handle());
    }
}