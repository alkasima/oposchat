<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Subscription;
use App\Services\UsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Carbon\Carbon;

class UsageServiceTest extends TestCase
{
    use RefreshDatabase;

    private UsageService $usageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usageService = new UsageService();
        Cache::flush();
    }

    public function test_premium_users_can_always_use_features()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->active()->create(['user_id' => $user->id]);

        $canUse = $this->usageService->canUseFeature($user, 'chat_messages');

        $this->assertTrue($canUse);
    }

    public function test_free_users_can_use_features_under_limit()
    {
        $user = User::factory()->create();

        $canUse = $this->usageService->canUseFeature($user, 'chat_messages');

        $this->assertTrue($canUse);
    }

    public function test_free_users_cannot_use_features_over_limit()
    {
        $user = User::factory()->create();
        
        // Set usage to the limit
        $limit = $this->usageService->getFeatureLimit('chat_messages');
        $cacheKey = $this->getUsageCacheKey($user->id, 'chat_messages');
        Cache::put($cacheKey, $limit, Carbon::now()->endOfMonth());

        $canUse = $this->usageService->canUseFeature($user, 'chat_messages');

        $this->assertFalse($canUse);
    }

    public function test_increment_usage_for_free_users()
    {
        $user = User::factory()->create();

        $this->usageService->incrementUsage($user, 'chat_messages', 5);

        $usage = $this->usageService->getCurrentUsage($user, 'chat_messages');
        $this->assertEquals(5, $usage);
    }

    public function test_does_not_increment_usage_for_premium_users()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->active()->create(['user_id' => $user->id]);

        $this->usageService->incrementUsage($user, 'chat_messages', 5);

        $usage = $this->usageService->getCurrentUsage($user, 'chat_messages');
        $this->assertEquals(0, $usage);
    }

    public function test_get_usage_stats()
    {
        $user = User::factory()->create();
        
        $this->usageService->incrementUsage($user, 'chat_messages', 10);
        $this->usageService->incrementUsage($user, 'file_uploads', 3);

        $stats = $this->usageService->getUsageStats($user);

        $this->assertArrayHasKey('chat_messages', $stats);
        $this->assertArrayHasKey('file_uploads', $stats);
        
        $this->assertEquals(10, $stats['chat_messages']['usage']);
        $this->assertEquals(50, $stats['chat_messages']['limit']);
        $this->assertEquals(20.0, $stats['chat_messages']['percentage']);
        $this->assertEquals(40, $stats['chat_messages']['remaining']);

        $this->assertEquals(3, $stats['file_uploads']['usage']);
        $this->assertEquals(10, $stats['file_uploads']['limit']);
        $this->assertEquals(30.0, $stats['file_uploads']['percentage']);
        $this->assertEquals(7, $stats['file_uploads']['remaining']);
    }

    public function test_is_approaching_limits()
    {
        $user = User::factory()->create();
        
        // Set usage to 85% of limit (42.5 rounded to 43)
        $this->usageService->incrementUsage($user, 'chat_messages', 43);

        $approaching = $this->usageService->isApproachingLimits($user, 0.8);

        $this->assertContains('chat_messages', $approaching);
    }

    public function test_reset_usage()
    {
        $user = User::factory()->create();
        
        $this->usageService->incrementUsage($user, 'chat_messages', 10);
        $this->assertEquals(10, $this->usageService->getCurrentUsage($user, 'chat_messages'));

        $this->usageService->resetUsage($user, 'chat_messages');
        $this->assertEquals(0, $this->usageService->getCurrentUsage($user, 'chat_messages'));
    }

    public function test_get_feature_limit()
    {
        $limit = $this->usageService->getFeatureLimit('chat_messages');
        $this->assertEquals(50, $limit);

        $limit = $this->usageService->getFeatureLimit('file_uploads');
        $this->assertEquals(10, $limit);

        $limit = $this->usageService->getFeatureLimit('nonexistent_feature');
        $this->assertEquals(0, $limit);
    }

    private function getUsageCacheKey(int $userId, string $feature): string
    {
        $month = Carbon::now()->format('Y-m');
        return "usage:{$userId}:{$feature}:{$month}";
    }
}