<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\UsageService;

class CheckUsageLimit
{
    protected UsageService $usageService;

    public function __construct(UsageService $usageService)
    {
        $this->usageService = $usageService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
                'error_code' => 'unauthenticated'
            ], 401);
        }

        // Premium users have unlimited access
        if ($user->hasActiveSubscription()) {
            return $next($request);
        }

        // Check usage limits for free tier users
        if (!$this->usageService->canUseFeature($user, $feature)) {
            $limit = $this->usageService->getFeatureLimit($feature);
            $usage = $this->usageService->getCurrentUsage($user, $feature);

            return response()->json([
                'success' => false,
                'message' => 'Usage limit exceeded for this feature',
                'error_code' => 'usage_limit_exceeded',
                'details' => [
                    'feature' => $feature,
                    'limit' => $limit,
                    'current_usage' => $usage,
                    'user_message' => "You've reached your limit of {$limit} for {$feature}. Upgrade to premium for unlimited access."
                ]
            ], 429);
        }

        return $next($request);
    }
}