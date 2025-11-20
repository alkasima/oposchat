<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use App\Services\SubscriptionService;
use App\Services\UsageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\RateLimitException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\AuthenticationException;
use Exception;

class SubscriptionController extends Controller
{
    public function __construct(
        private StripeService $stripeService,
        private SubscriptionService $subscriptionService,
        private UsageService $usageService
    ) {}

    /**
     * Get subscription plans configuration
     */
    public function plans(): JsonResponse
    {
        
        try {
            $plans = config('subscription.plans');
            $freePlan = config('subscription.free_plan');
            $featureComparison = config('subscription.feature_comparison');

            return response()->json([
                'success' => true,
                'data' => [
                    'plans' => $plans,
                    'free_plan' => $freePlan,
                    'feature_comparison' => $featureComparison
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve subscription plans', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscription plans'
            ], 500);
        }
    }

    /**
     * Upgrade current subscription to a different price with proration
     * - Preserves current billing cycle (billing_cycle_anchor unchanged)
     * - Charges or credits the prorated difference immediately via latest invoice
     */
    public function upgrade(Request $request): JsonResponse
    {
        $request->validate([
            'price_id' => 'required|string',
            'confirmed' => 'sometimes|boolean',
        ]);

        try {
            $user = Auth::user();
            $confirmed = (bool) $request->boolean('confirmed', false);

            // If no active subscription, fall back to checkout flow
            $active = $user->activeSubscription();
            if (!$active) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found. Use checkout to start a subscription.',
                    'error_code' => 'no_active_subscription'
                ], 400);
            }

            // If target price is already active, no-op with success
            if ($active->stripe_price_id === $request->price_id) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'status' => 'no_change',
                        'subscription_id' => $active->stripe_subscription_id,
                        'plan_name' => $user->getCurrentPlanName(),
                        'plan_key' => $user->getCurrentPlanKey(),
                        'subscription_status' => $active->status,
                    ]
                ]);
            }

            // Validate target price exists and is recurring
            try {
                $price = $this->stripeService->retrievePriceById($request->price_id);
                if (($price->type ?? null) !== 'recurring') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Selected plan is not a recurring price. Please choose a subscription plan.',
                        'error_code' => 'price_not_recurring'
                    ], 400);
                }
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected plan not found. Please refresh the page and try again.',
                    'error_code' => 'price_not_found'
                ], 400);
            }

            $plansConfig = config('subscription.plans');
            $currentPlanKey = $user->getCurrentPlanKey();
            $targetPlanKey = null;

            foreach ($plansConfig as $key => $plan) {
                if (($plan['stripe_price_id'] ?? null) === $request->price_id) {
                    $targetPlanKey = $key;
                    break;
                }
            }

            if (!$targetPlanKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected plan is not available. Please refresh and try again.',
                    'error_code' => 'plan_not_configured'
                ], 400);
            }

            $currentPrice = (float) ($plansConfig[$currentPlanKey]['price'] ?? 0);
            $targetPrice = (float) ($plansConfig[$targetPlanKey]['price'] ?? 0);
            $currency = $plansConfig[$targetPlanKey]['currency'] ?? 'EUR';
            $priceDifference = $targetPrice - $currentPrice;
            $isDowngrade = $priceDifference < 0;
            $isUpgrade = $priceDifference > 0;

            // Require confirmation for both upgrades and downgrades
            if (!$confirmed) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'requires_confirmation' => true,
                        'price_id' => $request->price_id,
                        'current_plan' => [
                            'key' => $currentPlanKey,
                            'name' => $plansConfig[$currentPlanKey]['name'] ?? ucfirst($currentPlanKey),
                            'price' => $currentPrice,
                        ],
                        'target_plan' => [
                            'key' => $targetPlanKey,
                            'name' => $plansConfig[$targetPlanKey]['name'] ?? ucfirst($targetPlanKey),
                            'price' => $targetPrice,
                        ],
                        'price_difference' => $priceDifference,
                        'currency' => $currency,
                    ]
                ]);
            }

            // Handle downgrade: schedule for end of billing period
            if ($isDowngrade) {
                $scheduledAt = $active->current_period_end;

                if (!$scheduledAt) {
                    try {
                        $stripeSubLive = $this->stripeService->retrieveSubscriptionById($active->stripe_subscription_id);
                        if (!empty($stripeSubLive->current_period_end)) {
                            $scheduledAt = Carbon::createFromTimestamp($stripeSubLive->current_period_end);
                        }
                        if (!empty($stripeSubLive->current_period_start)) {
                            $active->update([
                                'current_period_start' => Carbon::createFromTimestamp($stripeSubLive->current_period_start),
                            ]);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Failed to retrieve Stripe subscription while scheduling downgrade', [
                            'subscription_id' => $active->stripe_subscription_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if (!$scheduledAt) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Could not determine the end of your billing period. Please try again later or contact support.',
                        'error_code' => 'period_end_unknown'
                    ], 400);
                }

                $active->update([
                    'current_period_end' => $active->current_period_end ?? $scheduledAt,
                    'scheduled_plan_change_price_id' => $request->price_id,
                    'scheduled_plan_change_at' => $scheduledAt,
                ]);

                $active->refresh();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'status' => 'scheduled',
                        'scheduled_plan_change' => $active->getScheduledPlanChange(),
                        'plan_name' => $user->getCurrentPlanName(),
                        'plan_key' => $currentPlanKey,
                        'subscription_status' => $active->status,
                    ]
                ]);
            }

            // Find the existing subscription item to update (local first, then Stripe fallback)
            $item = $active->items()->first();
            $stripeItemId = $item?->stripe_subscription_item_id;
            if (!$stripeItemId) {
                try {
                    $stripeSubLive = $this->stripeService->retrieveSubscriptionById($active->stripe_subscription_id);
                    if (!empty($stripeSubLive->items->data[0]?->id)) {
                        $stripeItemId = $stripeSubLive->items->data[0]->id;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to retrieve Stripe subscription for item fallback', [
                        'subscription_id' => $active->stripe_subscription_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            if (!$stripeItemId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not locate subscription item to update. Please try again in a moment or contact support.',
                    'error_code' => 'subscription_item_missing'
                ], 400);
            }

            // Update the subscription in Stripe to new price WITHOUT automatic proration
            $stripeSub = $this->stripeService->updateSubscription($active->stripe_subscription_id, [
                'items' => [[
                    'id' => $stripeItemId,
                    'price' => $request->price_id,
                    'quantity' => 1,
                ]],
                // Disable Stripe's automatic proration; we'll charge a fixed difference instead
                'proration_behavior' => 'none',
            ]);

            // Clear any scheduled change because we're applying a new plan immediately
            if ($active->scheduled_plan_change_price_id) {
                $active->update([
                    'scheduled_plan_change_price_id' => null,
                    'scheduled_plan_change_at' => null,
                ]);
            }

            $redirectUrl = null;
            if ($isUpgrade && $priceDifference > 0) {
                try {
                    // Ensure the user has a Stripe customer ID before creating the invoice
                    if (!$user->hasStripeId()) {
                        $user->createOrGetStripeCustomer();
                        $user->refresh();
                    }

                    $customerId = $user->getStripeCustomerId();

                    if ($customerId) {
                        $amountCents = (int) round($priceDifference * 100);

                        $invoice = $this->stripeService->createOneOffInvoice(
                            $customerId,
                            $amountCents,
                            $currency,
                            [
                                'user_id' => $user->id,
                                'upgrade_from' => $currentPlanKey,
                                'upgrade_to' => $targetPlanKey,
                                'subscription_id' => $active->stripe_subscription_id,
                            ]
                        );

                        if ($invoice && isset($invoice->hosted_invoice_url)) {
                            $redirectUrl = $invoice->hosted_invoice_url;
                        }
                    } else {
                        Log::warning('Stripe customer ID missing when creating upgrade invoice', [
                            'user_id' => $user->id,
                            'current_plan' => $currentPlanKey,
                            'target_plan' => $targetPlanKey,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to create one-off upgrade invoice', [
                        'user_id' => $user->id,
                        'stripe_customer_id' => $user->stripe_customer_id,
                        'current_plan' => $currentPlanKey,
                        'target_plan' => $targetPlanKey,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Locally, we rely on webhooks to update, but also try to refresh immediately
            $result = \DB::transaction(function () use ($stripeSub) {
                $subscriptionService = app(\App\Services\SubscriptionService::class);
                return $subscriptionService->handleImmediatePaymentSuccess($stripeSub);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $isUpgrade ? 'upgraded' : 'switched',
                    'subscription_id' => $stripeSub->id,
                    'redirect_url' => $redirectUrl,
                    'plan_name' => $result['plan_name'] ?? null,
                    'plan_key' => $result['plan_key'] ?? null,
                    'subscription_status' => $result['subscription_status'] ?? null,
                ]
            ]);
        } catch (InvalidRequestException $e) {
            Log::error('Stripe invalid request during subscription upgrade', [
                'user_id' => Auth::id(),
                'price_id' => $request->price_id,
                'error' => $e->getMessage(),
                'error_code' => $e->getError()->code ?? null,
                'param' => $e->getError()->param ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->getStripeErrorMessage($e),
                'error_code' => $e->getError()->code ?? 'invalid_request_error'
            ], 400);
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error during subscription upgrade', [
                'user_id' => Auth::id(),
                'price_id' => $request->price_id,
                'error' => $e->getMessage(),
                'error_code' => $e->getError()->code ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->getStripeErrorMessage($e),
                'error_code' => $e->getError()->code ?? 'stripe_error'
            ], 400);
        } catch (Exception $e) {
            Log::error('Failed to upgrade subscription', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upgrade subscription. Please try again.',
                'error_code' => 'upgrade_failed'
            ], 500);
        }
    }

    /**
     * Get user subscription status and usage info (for frontend composable)
     */
    public function status(): JsonResponse
    {
        try {
            $user = Auth::user();
            $hasPremium = $user->hasPremiumAccess();
            
            $usageInfo = [];
            if (!$hasPremium) {
                $usageInfo = $this->usageService->getUsageStats($user);
            }

            return response()->json([
                'has_premium' => $hasPremium,
                'subscription_status' => $user->activeSubscription()?->status ?? 'none',
                'on_trial' => $user->onTrial(),
                'on_grace_period' => $user->onGracePeriod(),
                'usage' => $usageInfo,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve subscription status', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscription status'
            ], 500);
        }
    }

    /**
     * Get current user's subscription status and details
     */
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();
            $subscription = $user->activeSubscription();

            if (!$subscription) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'subscription' => null,
                        'status' => 'none',
                        'plan' => null
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => [
                        'id' => $subscription->id,
                        'status' => $subscription->status,
                        'current_period_start' => $subscription->current_period_start,
                        'current_period_end' => $subscription->current_period_end,
                        'trial_start' => $subscription->trial_start,
                        'trial_end' => $subscription->trial_end,
                        'cancel_at_period_end' => $subscription->cancel_at_period_end,
                        'canceled_at' => $subscription->canceled_at,
                        'stripe_price_id' => $subscription->stripe_price_id,
                        'is_active' => $subscription->isActive(),
                        'on_trial' => $subscription->onTrial(),
                        'has_expired' => $subscription->hasExpired(),
                        'scheduled_plan_change' => $subscription->getScheduledPlanChange(),
                    ],
                    'status' => $subscription->status,
                    'plan' => $subscription->stripe_price_id,
                    'has_scheduled_plan_change' => $subscription->hasScheduledPlanChange(),
                    'scheduled_plan_change' => $subscription->getScheduledPlanChange(),
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve subscription', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscription information'
            ], 500);
        }
    }

    /**
     * Create a Stripe checkout session for subscription
     */
    public function createCheckoutSession(Request $request): JsonResponse
    {
        $request->validate([
            'price_id' => 'required|string',
            'success_url' => 'required|url',
            'cancel_url' => 'required|url'
        ]);

        try {
            $user = Auth::user();
            
            // If user already has an active subscription, they should use the upgrade endpoint
            if ($user->hasActiveSubscription()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already has an active subscription. Use upgrade endpoint.',
                    'error_code' => 'has_active_subscription'
                ], 400);
            }

            // Ensure user has a Stripe customer ID
            if (!$user->hasStripeId()) {
                $customer = $user->createOrGetStripeCustomer();
                // Refresh user to get the updated stripe_customer_id
                $user->refresh();
            }

            $session = $this->stripeService->createCheckoutSession([
                'customer_id' => $user->stripe_id,
                'line_items' => [
                    [
                        'price' => $request->price_id,
                        'quantity' => 1,
                    ]
                ],
                'mode' => 'subscription',
                'success_url' => $request->success_url,
                'cancel_url' => $request->cancel_url,
                'metadata' => [
                    'user_id' => $user->id,
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'checkout_url' => $session->url,
                    'session_id' => $session->id
                ]
            ]);
        } catch (CardException $e) {
            // Stripe card errors
            Log::warning('Stripe card error during checkout', [
                'user_id' => Auth::id(),
                'price_id' => $request->price_id,
                'error' => $e->getMessage(),
                'decline_code' => $e->getDeclineCode(),
                'error_code' => $e->getError()->code ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->getStripeErrorMessage($e),
                'error_code' => $e->getError()->code ?? 'card_error',
                'decline_code' => $e->getDeclineCode()
            ], 400);
        } catch (RateLimitException $e) {
            Log::warning('Stripe rate limit exceeded', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please wait a moment and try again.',
                'error_code' => 'rate_limit_exceeded'
            ], 429);
        } catch (InvalidRequestException $e) {
            Log::error('Invalid Stripe request', [
                'user_id' => Auth::id(),
                'price_id' => $request->price_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid payment request. Please try again.',
                'error_code' => 'invalid_request'
            ], 400);
        } catch (AuthenticationException $e) {
            Log::critical('Stripe authentication failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment system error. Please contact support.',
                'error_code' => 'payment_system_error'
            ], 500);
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error during checkout', [
                'user_id' => Auth::id(),
                'price_id' => $request->price_id,
                'error' => $e->getMessage(),
                'error_code' => $e->getError()->code ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->getStripeErrorMessage($e),
                'error_code' => $e->getError()->code ?? 'stripe_error'
            ], 500);
        } catch (Exception $e) {
            Log::error('Failed to create checkout session', [
                'user_id' => Auth::id(),
                'price_id' => $request->price_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create checkout session. Please try again.',
                'error_code' => 'checkout_creation_failed'
            ], 500);
        }
    }

    /**
     * Confirm checkout without webhook: fetch the latest Stripe subscription and persist immediately
     */
    public function confirmCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string'
        ]);

        try {
            $user = Auth::user();

            

            // Retrieve checkout session and subscription from Stripe using service (ensures API key)
            $session = $this->stripeService->retrieveCheckoutSession($validated['session_id']);

            if (!$session || $session->status !== 'complete') {
                return response()->json([
                    'success' => false,
                    'message' => 'Checkout session not completed yet'
                ], 400);
            }

            if ($session->mode !== 'subscription' || !$session->subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No subscription found on session'
                ], 400);
            }

            $stripeSubscription = $this->stripeService->retrieveSubscriptionById($session->subscription);

            $result = DB::transaction(function () use ($stripeSubscription, $session) {
                $subscriptionService = app(\App\Services\SubscriptionService::class);
                
                // Get invoice if it exists
                $invoice = null;
                if ($session->invoice) {
                    try {
                        $invoice = $this->stripeService->retrieveInvoiceById($session->invoice);
                    } catch (\Exception $e) {
                        Log::warning('Failed to retrieve invoice after checkout', [
                            'invoice_id' => $session->invoice,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                // Handle immediate payment success (bypasses webhook)
                
                return $subscriptionService->handleImmediatePaymentSuccess($stripeSubscription, $invoice);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'updated',
                    'subscription_id' => $stripeSubscription->id,
                    'plan_name' => $result['plan_name'],
                    'plan_key' => $result['plan_key'],
                    'subscription_status' => $result['subscription_status'],
                    'has_active_subscription' => $result['has_active_subscription']
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Failed to confirm checkout', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm checkout'
            ], 500);
        }
    }

    /**
     * Create a customer portal session for subscription management
     */
    public function manageSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'return_url' => 'required|url'
        ]);

        try {
            $user = Auth::user();

            if (!$user->hasStripeId()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Stripe customer found for user'
                ], 400);
            }

            $session = $this->stripeService->createPortalSession(
                $user->stripe_id,
                $request->return_url
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'portal_url' => $session->url
                ]
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error during portal session creation', [
                'user_id' => Auth::id(),
                'stripe_customer_id' => $user->stripe_id ?? null,
                'error' => $e->getMessage(),
                'error_code' => $e->getError()->code ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->getStripeErrorMessage($e),
                'error_code' => $e->getError()->code ?? 'stripe_error'
            ], 500);
        } catch (Exception $e) {
            Log::error('Failed to create customer portal session', [
                'user_id' => Auth::id(),
                'stripe_customer_id' => $user->stripe_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to access subscription management. Please try again.',
                'error_code' => 'portal_creation_failed'
            ], 500);
        }
    }

    /**
     * Cancel user's subscription
     */
    public function cancelSubscription(): JsonResponse
    {
        try {
            $user = Auth::user();
            $subscription = $user->activeSubscription();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found'
                ], 400);
            }

            $this->subscriptionService->cancelSubscription($subscription);

            return response()->json([
                'success' => true,
                'message' => 'Subscription canceled successfully',
                'data' => [
                    'subscription' => [
                        'id' => $subscription->id,
                        'status' => $subscription->fresh()->status,
                        'cancel_at_period_end' => $subscription->fresh()->cancel_at_period_end,
                        'current_period_end' => $subscription->current_period_end
                    ]
                ]
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error during subscription cancellation', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'error_code' => $e->getError()->code ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->getStripeErrorMessage($e),
                'error_code' => $e->getError()->code ?? 'stripe_error'
            ], 500);
        } catch (Exception $e) {
            Log::error('Failed to cancel subscription', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription. Please try again.',
                'error_code' => 'cancellation_failed'
            ], 500);
        }
    }

    /**
     * Get user-friendly error message from Stripe exception
     */
    private function getStripeErrorMessage($exception): string
    {
        $error = $exception->getError();
        $code = $error->code ?? null;
        $declineCode = $exception instanceof CardException ? $exception->getDeclineCode() : null;

        // Card-specific errors
        $cardErrors = [
            'card_declined' => 'Your card was declined. Please try a different payment method.',
            'insufficient_funds' => 'Your card has insufficient funds. Please try a different payment method.',
            'expired_card' => 'Your card has expired. Please update your payment method.',
            'incorrect_cvc' => 'Your card\'s security code is incorrect. Please check and try again.',
            'processing_error' => 'An error occurred while processing your card. Please try again.',
            'incorrect_number' => 'Your card number is incorrect. Please check and try again.',
        ];

        // Decline code specific messages
        $declineCodeErrors = [
            'insufficient_funds' => 'Your card has insufficient funds. Please try a different payment method.',
            'generic_decline' => 'Your payment was declined. Please try a different payment method.',
            'lost_card' => 'Your card was declined. Please contact your bank or try a different payment method.',
            'stolen_card' => 'Your card was declined. Please contact your bank or try a different payment method.',
            'expired_card' => 'Your card has expired. Please update your payment method.',
            'incorrect_cvc' => 'Your card\'s security code is incorrect. Please check and try again.',
        ];

        // API errors
        $apiErrors = [
            'rate_limit' => 'Too many requests. Please wait a moment and try again.',
            'api_key_expired' => 'Payment system error. Please contact support.',
            'api_connection_error' => 'Unable to connect to payment processor. Please try again.',
            'authentication_required' => 'Payment authentication required. Please try again.',
            'subscription_not_found' => 'Subscription not found. Please contact support.',
            'invoice_not_found' => 'Invoice not found. Please contact support.',
            'customer_not_found' => 'Customer account not found. Please contact support.',
            'invalid_request_error' => 'Invalid payment request. Please try again.',
            'idempotency_error' => 'Duplicate request detected. Please refresh and try again.',
        ];

        // Check decline code first
        if ($declineCode && isset($declineCodeErrors[$declineCode])) {
            return $declineCodeErrors[$declineCode];
        }

        // Check error code
        if ($code && isset($cardErrors[$code])) {
            return $cardErrors[$code];
        }

        if ($code && isset($apiErrors[$code])) {
            return $apiErrors[$code];
        }

        // Generic message based on error type
        if ($exception instanceof CardException) {
            return 'Your payment was declined. Please try a different payment method.';
        }

        if ($exception instanceof RateLimitException) {
            return 'Too many requests. Please wait a moment and try again.';
        }

        if ($exception instanceof InvalidRequestException) {
            return 'Invalid payment request. Please try again.';
        }

        if ($exception instanceof AuthenticationException) {
            return 'Payment system error. Please contact support.';
        }

        // Fallback to original message if it's user-friendly, otherwise generic message
        $originalMessage = $exception->getMessage();
        if (strlen($originalMessage) < 100 && !str_contains($originalMessage, 'API')) {
            return $originalMessage;
        }

        return 'An error occurred while processing your request. Please try again.';
    }

    /**
     * Refresh subscription status immediately (bypass webhook)
     */
    public function refreshSubscriptionStatus(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->stripe_customer_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Stripe customer ID found'
                ], 400);
            }

            // Get the latest subscription from Stripe
            $stripeCustomer = $this->stripeService->retrieveCustomer($user->stripe_customer_id);

            // Some API responses may not include embedded subscriptions; guard against null
            $subscriptions = $stripeCustomer->subscriptions->data ?? null;

            // If subscriptions are not embedded, fetch them explicitly via the Subscriptions API
            if ($subscriptions === null) {
                $subscriptions = \Stripe\Subscription::all([
                    'customer' => $user->stripe_customer_id,
                    'status' => 'all',
                    'limit' => 10,
                ])->data;
            }

            if (empty($subscriptions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found'
                ], 404);
            }

            // Get the most recent active/trialing subscription
            $latestSubscription = null;
            foreach ($subscriptions as $subscription) {
                if (in_array($subscription->status, ['active', 'trialing'])) {
                    if (!$latestSubscription || $subscription->created > $latestSubscription->created) {
                        $latestSubscription = $subscription;
                    }
                }
            }

            if (!$latestSubscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found'
                ], 404);
            }

            // Update subscription immediately
            $result = DB::transaction(function () use ($latestSubscription) {
                $subscriptionService = app(\App\Services\SubscriptionService::class);
                return $subscriptionService->handleImmediatePaymentSuccess($latestSubscription);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'refreshed',
                    'subscription_id' => $latestSubscription->id,
                    'plan_name' => $result['plan_name'],
                    'plan_key' => $result['plan_key'],
                    'subscription_status' => $result['subscription_status'],
                    'has_active_subscription' => $result['has_active_subscription']
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to refresh subscription status', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh subscription status'
            ], 500);
        }
    }

    /**
     * Force refresh the current user's subscription status
     */
    public function refreshUserPlan(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Clear usage cache first
            $features = ['chat_messages', 'file_uploads', 'api_calls'];
            foreach ($features as $feature) {
                $cacheKey = "usage_{$user->id}_{$feature}";
                \Illuminate\Support\Facades\Cache::forget($cacheKey);
            }
            
            // Refresh user model
            $user->refresh();
            
            // Get current plan info
            $planName = $user->getCurrentPlanName();
            $planKey = $user->getCurrentPlanKey();
            $hasActiveSubscription = $user->hasActiveSubscription();
            $subscriptionStatus = $user->subscriptionStatus();
            
            Log::info('User plan refreshed manually', [
                'user_id' => $user->id,
                'plan_name' => $planName,
                'plan_key' => $planKey,
                'has_active_subscription' => $hasActiveSubscription,
                'subscription_status' => $subscriptionStatus
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'plan_name' => $planName,
                    'plan_key' => $planKey,
                    'has_active_subscription' => $hasActiveSubscription,
                    'subscription_status' => $subscriptionStatus
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to refresh user plan', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh user plan'
            ], 500);
        }
    }

    /**
     * Sync missing subscriptions for the current user
     */
    public function syncUserSubscriptions(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->stripe_customer_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Stripe customer ID found'
                ], 400);
            }

            $subscriptionService = app(\App\Services\SubscriptionService::class);
            $subscriptionService->syncUserSubscriptionsFromStripe($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'synced',
                    'plan_name' => $user->getCurrentPlanName(),
                    'has_premium_access' => $user->hasPremiumAccess()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to sync user subscriptions', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync subscriptions'
            ], 500);
        }
    }
}