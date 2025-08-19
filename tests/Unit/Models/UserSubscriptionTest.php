<?php

namespace Tests\Unit\Models;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_many_subscriptions()
    {
        $user = User::factory()->create();
        $subscription1 = Subscription::factory()->create(['user_id' => $user->id]);
        $subscription2 = Subscription::factory()->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->subscriptions);
        $this->assertTrue($user->subscriptions->contains($subscription1));
        $this->assertTrue($user->subscriptions->contains($subscription2));
    }

    public function test_user_has_many_invoices()
    {
        $user = User::factory()->create();
        $invoice1 = Invoice::factory()->create(['user_id' => $user->id]);
        $invoice2 = Invoice::factory()->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->invoices);
        $this->assertTrue($user->invoices->contains($invoice1));
        $this->assertTrue($user->invoices->contains($invoice2));
    }

    public function test_active_subscription_returns_active_subscription()
    {
        $user = User::factory()->create();
        $activeSubscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $canceledSubscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'canceled',
        ]);

        $result = $user->activeSubscription();
        $this->assertNotNull($result);
        $this->assertEquals($activeSubscription->id, $result->id);
    }

    public function test_active_subscription_returns_trialing_subscription()
    {
        $user = User::factory()->create();
        $trialingSubscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'trialing',
        ]);

        $result = $user->activeSubscription();
        $this->assertNotNull($result);
        $this->assertEquals($trialingSubscription->id, $result->id);
    }

    public function test_active_subscription_returns_null_when_no_active_subscription()
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'canceled',
        ]);

        $this->assertNull($user->activeSubscription());
    }

    public function test_has_active_subscription_returns_true_when_user_has_active_subscription()
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->assertTrue($user->hasActiveSubscription());
    }

    public function test_has_active_subscription_returns_false_when_user_has_no_active_subscription()
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'canceled',
        ]);

        $this->assertFalse($user->hasActiveSubscription());
    }

    public function test_on_trial_returns_true_when_user_has_trialing_subscription()
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'trialing',
            'trial_end' => Carbon::now()->addDays(7),
        ]);

        $this->assertTrue($user->onTrial());
    }

    public function test_on_trial_returns_false_when_user_has_no_trialing_subscription()
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->assertFalse($user->onTrial());
    }

    public function test_on_grace_period_returns_true_when_subscription_is_on_grace_period()
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'cancel_at_period_end' => true,
            'current_period_end' => Carbon::now()->addDays(7),
        ]);

        $this->assertTrue($user->onGracePeriod());
    }

    public function test_on_grace_period_returns_false_when_subscription_is_not_on_grace_period()
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'cancel_at_period_end' => false,
        ]);

        $this->assertFalse($user->onGracePeriod());
    }

    public function test_get_stripe_customer_id_returns_stripe_customer_id()
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_test123']);
        $this->assertEquals('cus_test123', $user->getStripeCustomerId());
    }

    public function test_get_stripe_customer_id_returns_null_when_no_stripe_customer_id()
    {
        $user = User::factory()->create(['stripe_customer_id' => null]);
        $this->assertNull($user->getStripeCustomerId());
    }

    public function test_has_premium_access_returns_true_when_user_has_active_subscription()
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->assertTrue($user->hasPremiumAccess());
    }

    public function test_has_premium_access_returns_true_when_user_is_on_trial()
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'trialing',
            'trial_end' => Carbon::now()->addDays(7),
        ]);

        $this->assertTrue($user->hasPremiumAccess());
    }

    public function test_has_premium_access_returns_true_when_user_is_on_grace_period()
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'cancel_at_period_end' => true,
            'current_period_end' => Carbon::now()->addDays(7),
        ]);

        $this->assertTrue($user->hasPremiumAccess());
    }

    public function test_has_premium_access_returns_false_when_user_has_no_premium_access()
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'canceled',
        ]);

        $this->assertFalse($user->hasPremiumAccess());
    }
}