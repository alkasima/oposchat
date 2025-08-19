<?php

namespace Tests\Unit\Models;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_belongs_to_user()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $subscription->user);
        $this->assertEquals($user->id, $subscription->user->id);
    }

    public function test_subscription_has_many_items()
    {
        $subscription = Subscription::factory()->create();
        $item1 = SubscriptionItem::factory()->create(['subscription_id' => $subscription->id]);
        $item2 = SubscriptionItem::factory()->create(['subscription_id' => $subscription->id]);

        $this->assertCount(2, $subscription->items);
        $this->assertTrue($subscription->items->contains($item1));
        $this->assertTrue($subscription->items->contains($item2));
    }

    public function test_subscription_has_many_invoices()
    {
        $subscription = Subscription::factory()->create();
        $invoice1 = Invoice::factory()->create(['subscription_id' => $subscription->id]);
        $invoice2 = Invoice::factory()->create(['subscription_id' => $subscription->id]);

        $this->assertCount(2, $subscription->invoices);
        $this->assertTrue($subscription->invoices->contains($invoice1));
        $this->assertTrue($subscription->invoices->contains($invoice2));
    }

    public function test_is_active_returns_true_for_active_status()
    {
        $subscription = Subscription::factory()->create(['status' => 'active']);
        $this->assertTrue($subscription->isActive());
    }

    public function test_is_active_returns_true_for_trialing_status()
    {
        $subscription = Subscription::factory()->create(['status' => 'trialing']);
        $this->assertTrue($subscription->isActive());
    }

    public function test_is_active_returns_false_for_canceled_status()
    {
        $subscription = Subscription::factory()->create(['status' => 'canceled']);
        $this->assertFalse($subscription->isActive());
    }

    public function test_on_trial_returns_true_when_trialing_and_trial_end_is_future()
    {
        $subscription = Subscription::factory()->create([
            'status' => 'trialing',
            'trial_end' => Carbon::now()->addDays(7),
        ]);

        $this->assertTrue($subscription->onTrial());
    }

    public function test_on_trial_returns_false_when_trial_end_is_past()
    {
        $subscription = Subscription::factory()->create([
            'status' => 'trialing',
            'trial_end' => Carbon::now()->subDays(1),
        ]);

        $this->assertFalse($subscription->onTrial());
    }

    public function test_has_expired_returns_true_for_canceled_status()
    {
        $subscription = Subscription::factory()->create(['status' => 'canceled']);
        $this->assertTrue($subscription->hasExpired());
    }

    public function test_has_expired_returns_true_when_current_period_end_is_past()
    {
        $subscription = Subscription::factory()->create([
            'status' => 'active',
            'current_period_end' => Carbon::now()->subDays(1),
        ]);

        $this->assertTrue($subscription->hasExpired());
    }

    public function test_has_expired_returns_false_when_current_period_end_is_future()
    {
        $subscription = Subscription::factory()->create([
            'status' => 'active',
            'current_period_end' => Carbon::now()->addDays(7),
        ]);

        $this->assertFalse($subscription->hasExpired());
    }

    public function test_is_canceled_returns_true_for_canceled_status()
    {
        $subscription = Subscription::factory()->create(['status' => 'canceled']);
        $this->assertTrue($subscription->isCanceled());
    }

    public function test_will_cancel_at_period_end_returns_true_when_flag_is_set()
    {
        $subscription = Subscription::factory()->create(['cancel_at_period_end' => true]);
        $this->assertTrue($subscription->willCancelAtPeriodEnd());
    }

    public function test_grace_period_end_returns_current_period_end_when_cancel_at_period_end()
    {
        $endDate = Carbon::now()->addDays(7);
        $subscription = Subscription::factory()->create([
            'cancel_at_period_end' => true,
            'current_period_end' => $endDate,
        ]);

        $this->assertEquals($endDate->toDateTimeString(), $subscription->gracePeriodEnd()->toDateTimeString());
    }

    public function test_grace_period_end_returns_null_when_not_cancel_at_period_end()
    {
        $subscription = Subscription::factory()->create(['cancel_at_period_end' => false]);
        $this->assertNull($subscription->gracePeriodEnd());
    }

    public function test_on_grace_period_returns_true_when_grace_period_end_is_future()
    {
        $subscription = Subscription::factory()->create([
            'cancel_at_period_end' => true,
            'current_period_end' => Carbon::now()->addDays(7),
        ]);

        $this->assertTrue($subscription->onGracePeriod());
    }

    public function test_on_grace_period_returns_false_when_grace_period_end_is_past()
    {
        $subscription = Subscription::factory()->create([
            'cancel_at_period_end' => true,
            'current_period_end' => Carbon::now()->subDays(1),
        ]);

        $this->assertFalse($subscription->onGracePeriod());
    }
}