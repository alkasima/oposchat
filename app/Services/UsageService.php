<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class UsageService
{
    /**
     * Feature limits for free tier users
     */
    protected array $featureLimits = [
        'chat_messages' => 50,
        'file_uploads' => 10,
        'api_calls' => 100,
    ];

    /**
     * Check if user can use a specific feature
     */
    public function canUseFeature(User $user, string $feature): bool
    {
        // Premium users have unlimited access
        if ($user->hasActiveSubscription()) {
            return true;
        }

        $limit = $this->getFeatureLimit($feature);
        $currentUsage = $this->getCurrentUsage($user, $feature);

        return $currentUsage < $limit;
    }

    /**
     * Get the usage limit for a feature
     */
    public function getFeatureLimit(string $feature): int
    {
        return $this->featureLimits[$feature] ?? 0;
    }

    /**
     * Get current usage for a user and feature
     */
    public function getCurrentUsage(User $user, string $feature): int
    {
        $cacheKey = $this->getUsageCacheKey($user->id, $feature);
        return Cache::get($cacheKey, 0);
    }

    /**
     * Increment usage for a user and feature
     */
    public function incrementUsage(User $user, string $feature, int $amount = 1): void
    {
        // Don't track usage for premium users
        if ($user->hasActiveSubscription()) {
            return;
        }

        $cacheKey = $this->getUsageCacheKey($user->id, $feature);
        $currentUsage = Cache::get($cacheKey, 0);
        
        // Cache until end of current month
        $expiresAt = Carbon::now()->endOfMonth();
        Cache::put($cacheKey, $currentUsage + $amount, $expiresAt);
    }

    /**
     * Reset usage for a user and feature
     */
    public function resetUsage(User $user, string $feature): void
    {
        $cacheKey = $this->getUsageCacheKey($user->id, $feature);
        Cache::forget($cacheKey);
    }

    /**
     * Get usage statistics for a user
     */
    public function getUsageStats(User $user): array
    {
        $stats = [];
        
        foreach ($this->featureLimits as $feature => $limit) {
            $usage = $this->getCurrentUsage($user, $feature);
            $stats[$feature] = [
                'usage' => $usage,
                'limit' => $limit,
                'percentage' => $limit > 0 ? round(($usage / $limit) * 100, 2) : 0,
                'remaining' => max(0, $limit - $usage)
            ];
        }

        return $stats;
    }

    /**
     * Check if user is approaching any limits
     */
    public function isApproachingLimits(User $user, float $threshold = 0.8): array
    {
        $approaching = [];
        $stats = $this->getUsageStats($user);

        foreach ($stats as $feature => $data) {
            if ($data['percentage'] >= ($threshold * 100)) {
                $approaching[] = $feature;
            }
        }

        return $approaching;
    }

    /**
     * Generate cache key for usage tracking
     */
    protected function getUsageCacheKey(int $userId, string $feature): string
    {
        $month = Carbon::now()->format('Y-m');
        return "usage:{$userId}:{$feature}:{$month}";
    }
}