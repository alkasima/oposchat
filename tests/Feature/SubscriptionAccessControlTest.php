<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subscription;
use App\Models\Chat;
use App\Services\UsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SubscriptionAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_premium_users_can_access_premium_features()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->active()->create(['user_id' => $user->id]);
        $chat = Chat::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/chats/{$chat->id}/export");

        $response->assertStatus(200);
    }

    public function test_free_users_cannot_access_premium_features()
    {
        $user = User::factory()->create();
        $chat = Chat::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/chats/{$chat->id}/export");

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error_code' => 'premium_required'
        ]);
    }

    public function test_free_users_can_send_messages_under_limit()
    {
        $user = User::factory()->create();
        $chat = Chat::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/chats/{$chat->id}/messages", [
                'message' => 'Test message'
            ]);

        $response->assertStatus(200);
    }

    public function test_free_users_cannot_send_messages_over_limit()
    {
        $user = User::factory()->create();
        $chat = Chat::factory()->create(['user_id' => $user->id]);

        // Set usage to the limit
        $usageService = app(UsageService::class);
        $limit = $usageService->getFeatureLimit('chat_messages');
        
        // Simulate reaching the limit
        for ($i = 0; $i < $limit; $i++) {
            $usageService->incrementUsage($user, 'chat_messages');
        }

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/chats/{$chat->id}/messages", [
                'message' => 'Test message'
            ]);

        $response->assertStatus(429);
        $response->assertJson([
            'success' => false,
            'error_code' => 'usage_limit_exceeded'
        ]);
    }

    public function test_premium_users_have_unlimited_message_access()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->active()->create(['user_id' => $user->id]);
        $chat = Chat::factory()->create(['user_id' => $user->id]);

        // Even if we simulate high usage, premium users should still have access
        $usageService = app(UsageService::class);
        $limit = $usageService->getFeatureLimit('chat_messages');
        
        for ($i = 0; $i < $limit * 2; $i++) {
            $usageService->incrementUsage($user, 'chat_messages');
        }

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/chats/{$chat->id}/messages", [
                'message' => 'Test message'
            ]);

        $response->assertStatus(200);
    }

    public function test_subscription_status_endpoint_returns_correct_data()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscriptions/status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'has_premium',
            'subscription_status',
            'on_trial',
            'on_grace_period',
            'usage'
        ]);

        $data = $response->json();
        $this->assertFalse($data['has_premium']);
        $this->assertEquals('none', $data['subscription_status']);
        $this->assertFalse($data['on_trial']);
        $this->assertFalse($data['on_grace_period']);
        $this->assertIsArray($data['usage']);
    }

    public function test_subscription_status_for_premium_user()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->active()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscriptions/status');

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertTrue($data['has_premium']);
        $this->assertEquals('active', $data['subscription_status']);
        $this->assertEmpty($data['usage']); // Premium users don't have usage limits
    }

    public function test_trial_users_have_premium_access()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->onTrial()->create(['user_id' => $user->id]);
        $chat = Chat::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/chats/{$chat->id}/export");

        $response->assertStatus(200);
    }

    public function test_grace_period_users_have_premium_access()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->onGracePeriod()->create(['user_id' => $user->id]);
        $chat = Chat::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/chats/{$chat->id}/export");

        $response->assertStatus(200);
    }

    public function test_analytics_endpoint_requires_premium()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/chats/analytics');

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error_code' => 'premium_required'
        ]);
    }

    public function test_premium_users_can_access_analytics()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->active()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/chats/analytics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_chats',
            'total_messages',
            'average_messages_per_chat',
            'most_active_day',
            'chat_frequency'
        ]);
    }
}