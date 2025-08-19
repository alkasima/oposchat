<?php

namespace Tests\Unit\Models;

use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_item_belongs_to_subscription()
    {
        $subscription = Subscription::factory()->create();
        $item = SubscriptionItem::factory()->create(['subscription_id' => $subscription->id]);

        $this->assertInstanceOf(Subscription::class, $item->subscription);
        $this->assertEquals($subscription->id, $item->subscription->id);
    }

    public function test_subscription_item_can_access_user_through_subscription()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);
        $item = SubscriptionItem::factory()->create(['subscription_id' => $subscription->id]);

        // Note: This test assumes the user() method works through the relationship
        // In practice, this might need to be adjusted based on how the relationship is implemented
        $this->assertInstanceOf(User::class, $item->subscription->user);
        $this->assertEquals($user->id, $item->subscription->user->id);
    }

    public function test_is_metered_returns_true_when_quantity_is_zero()
    {
        $item = SubscriptionItem::factory()->create(['quantity' => 0]);
        $this->assertTrue($item->isMetered());
    }

    public function test_is_metered_returns_false_when_quantity_is_greater_than_zero()
    {
        $item = SubscriptionItem::factory()->create(['quantity' => 1]);
        $this->assertFalse($item->isMetered());
    }

    public function test_get_total_quantity_returns_quantity()
    {
        $item = SubscriptionItem::factory()->create(['quantity' => 5]);
        $this->assertEquals(5, $item->getTotalQuantity());
    }

    public function test_quantity_is_cast_to_integer()
    {
        $item = SubscriptionItem::factory()->create(['quantity' => '10']);
        $this->assertIsInt($item->quantity);
        $this->assertEquals(10, $item->quantity);
    }
}