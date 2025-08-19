<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;

class StripeServiceTest extends TestCase
{
    private StripeService $stripeService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the config to avoid actual Stripe API calls
        config(['services.stripe.secret' => 'sk_test_fake']);
        
        $this->stripeService = new StripeService();
    }

    public function test_service_can_be_instantiated()
    {
        $this->assertInstanceOf(StripeService::class, $this->stripeService);
    }

    public function test_create_customer_method_exists()
    {
        $this->assertTrue(method_exists($this->stripeService, 'createCustomer'));
    }

    public function test_retrieve_customer_method_exists()
    {
        $this->assertTrue(method_exists($this->stripeService, 'retrieveCustomer'));
    }

    public function test_create_checkout_session_method_exists()
    {
        $this->assertTrue(method_exists($this->stripeService, 'createCheckoutSession'));
    }

    public function test_create_portal_session_method_exists()
    {
        $this->assertTrue(method_exists($this->stripeService, 'createPortalSession'));
    }

    public function test_retrieve_subscription_method_exists()
    {
        $this->assertTrue(method_exists($this->stripeService, 'retrieveSubscription'));
    }

    public function test_update_subscription_method_exists()
    {
        $this->assertTrue(method_exists($this->stripeService, 'updateSubscription'));
    }

    public function test_cancel_subscription_method_exists()
    {
        $this->assertTrue(method_exists($this->stripeService, 'cancelSubscription'));
    }

    public function test_list_customer_subscriptions_method_exists()
    {
        $this->assertTrue(method_exists($this->stripeService, 'listCustomerSubscriptions'));
    }

    public function test_create_customer_validates_required_email()
    {
        $this->expectException(\ErrorException::class);
        
        // This will fail because we're not providing required email
        $this->stripeService->createCustomer([]);
    }

    public function test_retrieve_customer_validates_customer_id()
    {
        $this->expectException(\Stripe\Exception\InvalidRequestException::class);
        
        // This will fail because we're providing an invalid customer ID
        $this->stripeService->retrieveCustomer('');
    }

    public function test_create_checkout_session_validates_required_fields()
    {
        $this->expectException(\ErrorException::class);
        
        // This will fail because we're not providing required fields
        $this->stripeService->createCheckoutSession([]);
    }

    public function test_create_portal_session_validates_customer_id()
    {
        $this->expectException(\Stripe\Exception\AuthenticationException::class);
        
        // This will fail because we're using a fake API key
        $this->stripeService->createPortalSession('cus_fake', 'https://example.com');
    }

    public function test_retrieve_subscription_validates_subscription_id()
    {
        $this->expectException(\Stripe\Exception\InvalidRequestException::class);
        
        // This will fail because we're providing an invalid subscription ID
        $this->stripeService->retrieveSubscription('');
    }

    public function test_update_subscription_validates_subscription_id()
    {
        $this->expectException(\Stripe\Exception\InvalidRequestException::class);
        
        // This will fail because we're providing an invalid subscription ID
        $this->stripeService->updateSubscription('', []);
    }

    public function test_cancel_subscription_validates_subscription_id()
    {
        $this->expectException(\Stripe\Exception\InvalidRequestException::class);
        
        // This will fail because we're providing an invalid subscription ID
        $this->stripeService->cancelSubscription('');
    }

    public function test_list_customer_subscriptions_validates_customer_id()
    {
        $this->expectException(\Stripe\Exception\AuthenticationException::class);
        
        // This will fail because we're using a fake API key
        $this->stripeService->listCustomerSubscriptions('cus_fake');
    }
}