<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\Checkout\Session;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\RateLimitException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\AuthenticationException;
use Illuminate\Support\Facades\Log;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        
        // Set additional Stripe configuration for debugging
        Stripe::setAppInfo(
            config('app.name', 'Laravel App'),
            '1.0.0',
            config('app.url')
        );
    }

    /**
     * Create a new Stripe customer
     *
     * @param array $customerData
     * @return Customer
     * @throws ApiErrorException
     */
    public function createCustomer(array $customerData): Customer
    {
        try {
            return Customer::create([
                'email' => $customerData['email'],
                'name' => $customerData['name'] ?? null,
                'metadata' => $customerData['metadata'] ?? [],
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe customer creation failed', [
                'error' => $e->getMessage(),
                'error_code' => $e->getError()->code ?? null,
                'customer_data' => $customerData,
                'request_id' => $e->getError()->request_log_url ?? null
            ]);
            throw $e;
        }
    }

    /**
     * Retrieve a Stripe customer by ID
     *
     * @param string $customerId
     * @return Customer
     * @throws ApiErrorException
     */
    public function retrieveCustomer(string $customerId): Customer
    {
        try {
            return Customer::retrieve($customerId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe customer retrieval failed', [
                'error' => $e->getMessage(),
                'customer_id' => $customerId
            ]);
            throw $e;
        }
    }

    /**
     * Create a Stripe checkout session
     *
     * @param array $sessionData
     * @return Session
     * @throws ApiErrorException
     */
    public function createCheckoutSession(array $sessionData): Session
    {
        try {
            return Session::create([
                'customer' => $sessionData['customer_id'],
                'payment_method_types' => ['card'],
                'line_items' => $sessionData['line_items'],
                'mode' => $sessionData['mode'] ?? 'subscription',
                'success_url' => $sessionData['success_url'],
                'cancel_url' => $sessionData['cancel_url'],
                'metadata' => $sessionData['metadata'] ?? [],
                'subscription_data' => $sessionData['subscription_data'] ?? null,
            ]);
        } catch (RateLimitException $e) {
            Log::warning('Stripe rate limit exceeded during checkout session creation', [
                'error' => $e->getMessage(),
                'session_data' => $sessionData
            ]);
            throw $e;
        } catch (InvalidRequestException $e) {
            Log::error('Invalid Stripe request for checkout session', [
                'error' => $e->getMessage(),
                'error_code' => $e->getError()->code ?? null,
                'session_data' => $sessionData
            ]);
            throw $e;
        } catch (ApiErrorException $e) {
            Log::error('Stripe checkout session creation failed', [
                'error' => $e->getMessage(),
                'error_code' => $e->getError()->code ?? null,
                'session_data' => $sessionData,
                'request_id' => $e->getError()->request_log_url ?? null
            ]);
            throw $e;
        }
    }

    /**
     * Create a customer portal session
     *
     * @param string $customerId
     * @param string $returnUrl
     * @return PortalSession
     * @throws ApiErrorException
     */
    public function createPortalSession(string $customerId, string $returnUrl): PortalSession
    {
        try {
            return PortalSession::create([
                'customer' => $customerId,
                'return_url' => $returnUrl,
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe portal session creation failed', [
                'error' => $e->getMessage(),
                'customer_id' => $customerId,
                'return_url' => $returnUrl
            ]);
            throw $e;
        }
    }

    /**
     * Retrieve a subscription from Stripe
     *
     * @param string $subscriptionId
     * @return Subscription
     * @throws ApiErrorException
     */
    public function retrieveSubscription(string $subscriptionId): Subscription
    {
        try {
            return Subscription::retrieve($subscriptionId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe subscription retrieval failed', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId
            ]);
            throw $e;
        }
    }

    /**
     * Update a subscription in Stripe
     *
     * @param string $subscriptionId
     * @param array $updateData
     * @return Subscription
     * @throws ApiErrorException
     */
    public function updateSubscription(string $subscriptionId, array $updateData): Subscription
    {
        try {
            return Subscription::update($subscriptionId, $updateData);
        } catch (ApiErrorException $e) {
            Log::error('Stripe subscription update failed', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
                'update_data' => $updateData
            ]);
            throw $e;
        }
    }

    /**
     * Cancel a subscription in Stripe
     *
     * @param string $subscriptionId
     * @param bool $cancelAtPeriodEnd
     * @return Subscription
     * @throws ApiErrorException
     */
    public function cancelSubscription(string $subscriptionId, bool $cancelAtPeriodEnd = true): Subscription
    {
        try {
            if ($cancelAtPeriodEnd) {
                return Subscription::update($subscriptionId, [
                    'cancel_at_period_end' => true
                ]);
            } else {
                return Subscription::retrieve($subscriptionId)->cancel();
            }
        } catch (ApiErrorException $e) {
            Log::error('Stripe subscription cancellation failed', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
                'cancel_at_period_end' => $cancelAtPeriodEnd
            ]);
            throw $e;
        }
    }

    /**
     * List subscriptions for a customer
     *
     * @param string $customerId
     * @param array $params
     * @return \Stripe\Collection
     * @throws ApiErrorException
     */
    public function listCustomerSubscriptions(string $customerId, array $params = []): \Stripe\Collection
    {
        try {
            return Subscription::all(array_merge([
                'customer' => $customerId,
                'limit' => 10,
            ], $params));
        } catch (ApiErrorException $e) {
            Log::error('Stripe customer subscriptions listing failed', [
                'error' => $e->getMessage(),
                'customer_id' => $customerId,
                'params' => $params
            ]);
            throw $e;
        }
    }

    /**
     * Check if Stripe API is available
     *
     * @return bool
     */
    public function isApiAvailable(): bool
    {
        try {
            // Simple API call to check connectivity
            Customer::all(['limit' => 1]);
            return true;
        } catch (ApiErrorException $e) {
            Log::warning('Stripe API unavailable', [
                'error' => $e->getMessage(),
                'error_code' => $e->getError()->code ?? null
            ]);
            return false;
        }
    }

    /**
     * Execute Stripe operation with retry logic
     *
     * @param callable $operation
     * @param int $maxRetries
     * @param int $retryDelay
     * @return mixed
     * @throws ApiErrorException
     */
    public function executeWithRetry(callable $operation, int $maxRetries = 3, int $retryDelay = 1000)
    {
        $lastException = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (RateLimitException $e) {
                $lastException = $e;
                if ($attempt === $maxRetries) {
                    break;
                }
                
                // Wait longer for rate limit errors
                usleep(($retryDelay * ($attempt + 1) * 2) * 1000);
            } catch (ApiErrorException $e) {
                $lastException = $e;
                
                // Don't retry certain errors
                if ($this->shouldNotRetry($e) || $attempt === $maxRetries) {
                    break;
                }
                
                usleep($retryDelay * ($attempt + 1) * 1000);
            }
        }

        throw $lastException;
    }

    /**
     * Determine if an error should not be retried
     *
     * @param ApiErrorException $exception
     * @return bool
     */
    private function shouldNotRetry(ApiErrorException $exception): bool
    {
        $error = $exception->getError();
        $code = $error->code ?? null;

        // Don't retry authentication errors
        if ($exception instanceof AuthenticationException) {
            return true;
        }

        // Don't retry invalid request errors
        if ($exception instanceof InvalidRequestException) {
            return true;
        }

        // Don't retry card errors
        if ($exception instanceof CardException) {
            return true;
        }

        // Don't retry certain error codes
        $nonRetryableCodes = [
            'resource_missing',
            'resource_already_exists',
            'idempotency_key_in_use',
            'invalid_request_error'
        ];

        return in_array($code, $nonRetryableCodes);
    }
}