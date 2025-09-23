<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UserPlanController extends Controller
{
    /**
     * Display the user plan management page
     */
    public function index()
    {
        $users = User::with(['subscriptions' => function($query) {
            $query->whereIn('status', ['active', 'trialing'])->latest();
        }])->paginate(20);

        // Transform the data and create a new collection
        $transformedUsers = $users->getCollection()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'current_plan' => $user->getCurrentPlanName(),
                'subscription_status' => $user->subscriptionStatus(),
                'has_active_subscription' => $user->hasActiveSubscription(),
                'subscriptions' => $user->subscriptions->toArray()
            ];
        });

        // Create a new paginator with the transformed data
        $paginatedUsers = new \Illuminate\Pagination\LengthAwarePaginator(
            $transformedUsers,
            $users->total(),
            $users->perPage(),
            $users->currentPage(),
            [
                'path' => $users->path(),
                'pageName' => $users->getPageName(),
            ]
        );

        return inertia('Admin/UserPlans/Index', [
            'users' => $paginatedUsers
        ]);
    }

    /**
     * Get user details for plan management
     */
    public function show(User $user)
    {
        $user->load(['subscriptions' => function($query) {
            $query->latest();
        }]);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'current_plan' => $user->getCurrentPlanName(),
                'plan_key' => $user->getCurrentPlanKey(),
                'has_active_subscription' => $user->hasActiveSubscription(),
                'subscription_status' => $user->subscriptionStatus(),
                'subscriptions' => $user->subscriptions->map(function($sub) {
                    return [
                        'id' => $sub->id,
                        'status' => $sub->status,
                        'stripe_price_id' => $sub->stripe_price_id,
                        'current_period_start' => $sub->current_period_start,
                        'current_period_end' => $sub->current_period_end,
                        'created_at' => $sub->created_at,
                    ];
                })
            ]
        ]);
    }

    /**
     * Update user plan manually
     */
    public function updatePlan(Request $request, User $user)
    {
        $request->validate([
            'plan_key' => 'required|string|in:free,premium,plus,academy',
            'reason' => 'required|string|max:500',
            'duration_months' => 'nullable|integer|min:1|max:12'
        ]);

        try {
            DB::transaction(function () use ($request, $user) {
                $planKey = $request->plan_key;
                $reason = $request->reason;
                $durationMonths = $request->duration_months ?? 1;

                // Get plan configuration
                $plans = config('subscription.plans');
                $plan = $plans[$planKey] ?? null;

                if (!$plan) {
                    throw new \Exception("Invalid plan: {$planKey}");
                }

                // If setting to free plan, cancel all active subscriptions
                if ($planKey === 'free') {
                    $user->subscriptions()->whereIn('status', ['active', 'trialing'])->update([
                        'status' => 'canceled',
                        'canceled_at' => now()
                    ]);

                    Log::info('User plan set to free', [
                        'user_id' => $user->id,
                        'admin_id' => auth()->id(),
                        'reason' => $reason
                    ]);

                } else {
                    // Create or update subscription for paid plans
                    $stripePriceId = $plan['stripe_price_id'];

                    if (!$stripePriceId) {
                        throw new \Exception("No Stripe price ID configured for plan: {$planKey}");
                    }

                    // Cancel existing active subscriptions
                    $user->subscriptions()->whereIn('status', ['active', 'trialing'])->update([
                        'status' => 'canceled',
                        'canceled_at' => now()
                    ]);

                    // Create new subscription
                    $subscription = Subscription::create([
                        'user_id' => $user->id,
                        'stripe_subscription_id' => 'admin_manual_' . time() . '_' . $user->id,
                        'stripe_customer_id' => $user->stripe_customer_id ?? 'admin_manual_customer',
                        'stripe_price_id' => $stripePriceId,
                        'status' => 'active',
                        'current_period_start' => now(),
                        'current_period_end' => now()->addMonths($durationMonths),
                        'cancel_at_period_end' => false,
                        'canceled_at' => null,
                    ]);

                    Log::info('User plan updated manually', [
                        'user_id' => $user->id,
                        'admin_id' => auth()->id(),
                        'old_plan' => $user->getCurrentPlanName(),
                        'new_plan' => $plan['name'],
                        'duration_months' => $durationMonths,
                        'reason' => $reason,
                        'subscription_id' => $subscription->id
                    ]);
                }

                // Clear usage cache to ensure new limits take effect
                $features = ['chat_messages', 'file_uploads', 'api_calls'];
                foreach ($features as $feature) {
                    $cacheKey = "usage_{$user->id}_{$feature}";
                    \Illuminate\Support\Facades\Cache::forget($cacheKey);
                }
            });

            // Refresh user data
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => "User plan updated successfully to {$user->getCurrentPlanName()}",
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'current_plan' => $user->getCurrentPlanName(),
                    'plan_key' => $user->getCurrentPlanKey(),
                    'has_active_subscription' => $user->hasActiveSubscription(),
                    'subscription_status' => $user->subscriptionStatus(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update user plan manually', [
                'user_id' => $user->id,
                'admin_id' => auth()->id(),
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search users by email or name
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'users' => []
            ]);
        }

        $users = User::where('email', 'like', "%{$query}%")
            ->orWhere('name', 'like', "%{$query}%")
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }
}
