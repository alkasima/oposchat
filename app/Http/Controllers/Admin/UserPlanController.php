<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UserPlanController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private StripeService $stripeService,
    ) {}

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
            $planKey = $request->plan_key;
            $reason = $request->reason;
            $durationMonths = $request->duration_months ?? 1;

            // Handle free plan: cancel Stripe subscription if present and set user to free
            if ($planKey === 'free') {
                $active = $user->activeSubscription();

                if ($active && $active->stripe_subscription_id) {
                    // Cancel in Stripe (immediate) and sync locally from Stripe response
                    $stripeSub = $this->stripeService->cancelSubscription($active->stripe_subscription_id, false);
                    $this->subscriptionService->updateSubscriptionFromStripe($stripeSub);

                    Log::info('Admin canceled user subscription via Stripe (set to free)', [
                        'user_id' => $user->id,
                        'admin_id' => auth()->id(),
                        'reason' => $reason,
                        'stripe_subscription_id' => $active->stripe_subscription_id,
                    ]);
                } else {
                    // No Stripe subscription; just mark any local active subs as canceled
                    $user->subscriptions()->whereIn('status', ['active', 'trialing'])->update([
                        'status' => 'canceled',
                        'canceled_at' => now(),
                    ]);

                    Log::info('Admin set user to free without Stripe subscription', [
                        'user_id' => $user->id,
                        'admin_id' => auth()->id(),
                        'reason' => $reason,
                    ]);
                }

                // Ensure user is marked as free in local model
                $user->forceFill(['subscription_type' => 'free'])->save();

                // Clear usage cache to ensure new limits take effect
                $this->clearUsageCacheForUser($user);

            } else {
                // Paid plans: premium, plus, academy – must go through Stripe
                $plans = config('subscription.plans');
                $plan = $plans[$planKey] ?? null;

                if (!$plan) {
                    throw new \Exception("Invalid plan: {$planKey}");
                }

                $stripePriceId = $plan['stripe_price_id'] ?? null;
                if (!$stripePriceId) {
                    throw new \Exception("No Stripe price ID configured for plan: {$planKey}");
                }

                // Ensure the user has a Stripe customer
                if (!$user->hasStripeId()) {
                    $user->createOrGetStripeCustomer();
                    $user->refresh();
                }

                $active = $user->activeSubscription();

                if ($active && $active->stripe_subscription_id) {
                    // Upgrade/downgrade existing Stripe subscription
                    $stripeSubLive = $this->stripeService->retrieveSubscriptionById($active->stripe_subscription_id);

                    if (empty($stripeSubLive->items->data)) {
                        throw new \Exception('No subscription items found on Stripe subscription for user.');
                    }

                    $primaryItem = $stripeSubLive->items->data[0];

                    $updatedStripeSub = $this->stripeService->updateSubscription($stripeSubLive->id, [
                        'items' => [[
                            'id' => $primaryItem->id,
                            'price' => $stripePriceId,
                            'quantity' => 1,
                        ]],
                        // Admin-triggered plan changes: no automatic proration/charges here
                        'proration_behavior' => 'none',
                    ]);

                    // Sync local subscription + items with Stripe
                    $subscription = $this->subscriptionService->updateSubscriptionFromStripe($updatedStripeSub);

                    Log::info('Admin updated user plan via Stripe subscription update', [
                        'user_id' => $user->id,
                        'admin_id' => auth()->id(),
                        'old_plan' => $user->getCurrentPlanName(),
                        'new_plan' => $plan['name'],
                        'duration_months' => $durationMonths,
                        'reason' => $reason,
                        'subscription_id' => $subscription->id,
                        'stripe_subscription_id' => $updatedStripeSub->id,
                    ]);
                } else {
                    // No active Stripe subscription: create one for this user
                    $stripeSub = $this->stripeService->createSubscription([
                        'customer' => $user->stripe_customer_id,
                        'items' => [[
                            'price' => $stripePriceId,
                            'quantity' => 1,
                        ]],
                        // Allow subscription to be created even if payment method is missing/incomplete
                        'payment_behavior' => 'default_incomplete',
                    ]);

                    // Persist locally using the same immediate-success logic used for user flows
                    $result = $this->subscriptionService->handleImmediatePaymentSuccess($stripeSub);

                    Log::info('Admin created new Stripe subscription for user plan', [
                        'user_id' => $user->id,
                        'admin_id' => auth()->id(),
                        'new_plan' => $plan['name'],
                        'duration_months' => $durationMonths,
                        'reason' => $reason,
                        'stripe_subscription_id' => $stripeSub->id,
                        'result' => $result,
                    ]);
                }

                // Clear usage cache to ensure new limits take effect
                $this->clearUsageCacheForUser($user);

                // Ensure user.subscription_type is aligned with current Stripe-backed plan
                $user->refresh();
                $user->forceFill(['subscription_type' => $user->getCurrentPlanKey()])->save();
            }

            // Refresh user data for response
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
     * Clear usage cache so new plan limits take effect immediately.
     */
    private function clearUsageCacheForUser(User $user): void
    {
        $features = ['chat_messages', 'file_uploads', 'api_calls'];
        foreach ($features as $feature) {
            $cacheKey = "usage_{$user->id}_{$feature}";
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
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
