<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class UsageService
{
    /**
     * Get feature limit for a user based on their subscription plan
     */
    public function getFeatureLimit(User $user, string $feature): ?int
    {
        return $user->getFeatureLimit($feature);
    }

    /**
     * Check if user can use a specific feature
     */
    public function canUseFeature(User $user, string $feature): bool
    {
        $limit = $this->getFeatureLimit($user, $feature);
        
        // If limit is null, feature is unlimited
        if ($limit === null) {
            return true;
        }
        
        // If limit is 0, feature is not allowed
        if ($limit === 0) {
            return false;
        }

        $currentUsage = $this->getCurrentUsage($user, $feature);
        return $currentUsage < $limit;
    }

    /**
     * Get the usage limit for a feature (legacy method for backward compatibility)
     */
    public function getFeatureLimitLegacy(string $feature): int
    {
        $featureLimits = [
            'chat_messages' => 3, // Free plan: 3 messages per day
            'file_uploads' => 0,  // Free plan: not allowed
            'api_calls' => 100,
        ];
        return $featureLimits[$feature] ?? 0;
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
        $limit = $this->getFeatureLimit($user, $feature);
        
        // Don't track usage for unlimited features
        if ($limit === null) {
            return;
        }

        $cacheKey = $this->getUsageCacheKey($user->id, $feature);
        $currentUsage = Cache::get($cacheKey, 0);
        
        // For free plan (daily limits), cache until end of day
        // For premium plan (monthly limits), cache until end of month
        $planKey = $user->getCurrentPlanKey();
        if ($planKey === 'free') {
            $expiresAt = Carbon::now()->endOfDay();
        } else {
            $expiresAt = Carbon::now()->endOfMonth();
        }
        
        Cache::put($cacheKey, $currentUsage + $amount, $expiresAt);
    }

    /**
     * Get usage summary for a user
     */
    public function getUsageSummary(User $user): array
    {
        $features = ['chat_messages', 'file_uploads', 'api_calls'];
        $summary = [];
        
        foreach ($features as $feature) {
            $limit = $this->getFeatureLimit($user, $feature);
            $usage = $this->getCurrentUsage($user, $feature);
            
            $planKey = $user->getCurrentPlanKey();
            $resetTime = null;
            
            // Calculate reset time based on plan
            if ($planKey === 'free') {
                $resetTime = Carbon::now()->endOfDay()->toISOString();
            } elseif ($planKey === 'premium') {
                $resetTime = Carbon::now()->endOfMonth()->toISOString();
            }
            
            $summary[$feature] = [
                'usage' => $usage,
                'limit' => $limit,
                'remaining' => $limit === null ? null : max(0, $limit - $usage),
                'percentage' => $limit === null ? 0 : ($limit > 0 ? round(($usage / $limit) * 100) : 0),
                'unlimited' => $limit === null,
                'not_allowed' => $limit === 0,
                'reset_time' => $resetTime,
            ];
        }
        
        return $summary;
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