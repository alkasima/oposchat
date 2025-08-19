<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresPremiumSubscription
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
                'error_code' => 'unauthenticated'
            ], 401);
        }

        if (!$user->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => 'Premium subscription required to access this feature',
                'error_code' => 'premium_required',
                'details' => [
                    'feature' => 'premium_access',
                    'user_message' => 'Upgrade to premium to unlock this feature.'
                ]
            ], 403);
        }

        return $next($request);
    }
}