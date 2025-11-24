<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Price;
use Stripe\Checkout\Session;
use Stripe\Checkout\Session as CheckoutSession;
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
        $secret = config('services.stripe.secret');
        try {
            $settings = app(\App\Services\SettingsService::class);
            $dbSecret = $settings->get('STRIPE_SECRET');
            if (!empty($dbSecret)) {
                $secret = $dbSecret;
            }
        } catch (\Throwable $e) {
            // Fallback to config
        }
        Stripe::setApiKey($secret);
        
        // Set additional Stripe configuration for debugging
        Stripe::setAppInfo(
            config('app.name', 'Laravel App'),
            '1.0.0',
            config('app.url')
        );
    }

    /**
     * Retrieve a Checkout Session by ID
     */
    public function retrieveCheckoutSession(string $sessionId): Session
    {
        return Session::retrieve($sessionId);
    }

    /**
     * Retrieve a Subscription by ID
     */
    public function retrieveSubscriptionById(string $subscriptionId): Subscription
    {
        return Subscription::retrieve($subscriptionId);
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
     * Retrieve a price by ID
     */
    public function retrievePriceById(string $priceId): Price
    {
        try {
            return Price::retrieve($priceId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe price retrieval failed', [
                'error' => $e->getMessage(),
                'price_id' => $priceId
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
            return Customer::retrieve($customerId, [
                'expand' => ['subscriptions']
            ]);
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
            $payload = [
                'customer' => $sessionData['customer_id'],
                'payment_method_types' => ['card'],
                'line_items' => $sessionData['line_items'],
                'mode' => $sessionData['mode'] ?? 'subscription',
                'success_url' => $sessionData['success_url'],
                'cancel_url' => $sessionData['cancel_url'],
                'metadata' => $sessionData['metadata'] ?? [],
            ];
            if (!empty($sessionData['subscription_data'])) {
                $payload['subscription_data'] = $sessionData['subscription_data'];
            }

            return Session::create($payload);
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

    /**
     * Retrieve an invoice from Stripe
     *
     * @param string $invoiceId
     * @return Invoice
     * @throws ApiErrorException
     */
    public function retrieveInvoiceById(string $invoiceId): Invoice
    {
        try {
            return Invoice::retrieve($invoiceId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe invoice retrieval failed', [
                'error' => $e->getMessage(),
                'invoice_id' => $invoiceId
            ]);
            throw $e;
        }
    }

    /**
     * Create a one-off invoice for charging plan differences (e.g., upgrades)
     *
     * @param string $customerId
     * @param int $amountCents
     * @param string $currency
     * @param array $metadata
     * @param \Stripe\Subscription|null $subscription Optional subscription to get payment method from
     * @return Invoice
     * @throws ApiErrorException
     */
    public function createOneOffInvoice(string $customerId, int $amountCents, string $currency, array $metadata = [], $subscription = null): Invoice
    {
        try {
            // Create a draft invoice first
            $invoice = Invoice::create([
                'customer' => $customerId,
                'collection_method' => 'charge_automatically',
                'currency' => strtolower($currency), // Set currency to match the invoice item
                'metadata' => $metadata,
                'auto_advance' => false, // Don't auto-finalize yet
            ]);

            // Now add the invoice item to this specific invoice
            InvoiceItem::create([
                'customer' => $customerId,
                'invoice' => $invoice->id, // Attach to the specific invoice
                'amount' => $amountCents,
                'currency' => strtolower($currency),
                'description' => $metadata['description'] ?? 'Plan upgrade adjustment',
                'metadata' => $metadata,
            ]);

            // Retrieve the invoice to ensure it has the line items
            $invoice = Invoice::retrieve($invoice->id);
            
            Log::info('Invoice before finalization', [
                'invoice_id' => $invoice->id,
                'total' => $invoice->total,
                'amount_due' => $invoice->amount_due,
                'lines_count' => count($invoice->lines->data ?? []),
            ]);

            // Now finalize the invoice with the items attached
            $finalizedInvoice = $invoice->finalizeInvoice();
            
            Log::info('Invoice after finalization', [
                'invoice_id' => $finalizedInvoice->id,
                'total' => $finalizedInvoice->total,
                'amount_due' => $finalizedInvoice->amount_due,
                'status' => $finalizedInvoice->status,
            ]);
            
            // Attempt to pay the invoice immediately if it's still open
            if ($finalizedInvoice->status === 'open') {
                try {
                    // Get payment method from the subscription if provided
                    $paymentMethodId = null;
                    
                    if ($subscription && !empty($subscription->default_payment_method)) {
                        $paymentMethodId = $subscription->default_payment_method;
                        Log::info('Using payment method from subscription', [
                            'subscription_id' => $subscription->id,
                            'payment_method' => $paymentMethodId
                        ]);
                    } else {
                        // Fallback: try to get from customer
                        try {
                            $customer = Customer::retrieve($customerId, [
                                'expand' => ['subscriptions']
                            ]);
                            
                            if (!empty($customer->invoice_settings->default_payment_method)) {
                                $paymentMethodId = $customer->invoice_settings->default_payment_method;
                                Log::info('Using customer default payment method', ['payment_method' => $paymentMethodId]);
                            } elseif (!empty($customer->subscriptions->data)) {
                                foreach ($customer->subscriptions->data as $sub) {
                                    if ($sub->status === 'active' && !empty($sub->default_payment_method)) {
                                        $paymentMethodId = $sub->default_payment_method;
                                        Log::info('Using subscription payment method from customer', [
                                            'subscription_id' => $sub->id,
                                            'payment_method' => $paymentMethodId
                                        ]);
                                        break;
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('Could not retrieve payment method from customer', [
                                'customer_id' => $customerId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                    
                    // Pay the invoice with the payment method
                    if ($paymentMethodId) {
                        $paidInvoice = $finalizedInvoice->pay([
                            'payment_method' => $paymentMethodId,
                        ]);
                    } else {
                        // Try without specifying payment method (will use default if available)
                        $paidInvoice = $finalizedInvoice->pay();
                    }
                    
                    Log::info('Invoice payment attempted', [
                        'invoice_id' => $paidInvoice->id,
                        'status' => $paidInvoice->status,
                        'amount_paid' => $paidInvoice->amount_paid,
                        'amount_due' => $paidInvoice->amount_due,
                        'payment_method_used' => $paymentMethodId ?? 'default',
                    ]);
                    
                    return $paidInvoice;
                } catch (ApiErrorException $payError) {
                    // Log payment failure but still return the invoice
                    // The customer can pay it manually via the hosted invoice URL
                    Log::warning('Failed to automatically pay upgrade invoice', [
                        'invoice_id' => $finalizedInvoice->id,
                        'customer_id' => $customerId,
                        'error' => $payError->getMessage(),
                    ]);
                }
            }

            return $finalizedInvoice;
        } catch (ApiErrorException $e) {
            Log::error('Stripe one-off invoice creation failed', [
                'error' => $e->getMessage(),
                'customer_id' => $customerId,
                'amount' => $amountCents,
                'currency' => $currency,
                'metadata' => $metadata,
            ]);
            throw $e;
        }
    }
}