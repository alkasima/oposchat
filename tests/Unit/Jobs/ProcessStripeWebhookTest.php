<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessStripeWebhook;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessStripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        $webhookData = [
            'id' => 'evt_test_webhook',
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'id' => 'sub_test',
                    'customer' => 'cus_test'
                ]
            ]
        ];

        ProcessStripeWebhook::dispatch($webhookData, 'customer.subscription.created');

        Queue::assertPushed(ProcessStripeWebhook::class);
    }

    public function test_job_handles_subscription_created_event(): void
    {
        $webhookData = [
            'id' => 'evt_test_webhook',
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'id' => 'sub_test',
                    'customer' => 'cus_test',
                    'status' => 'active',
                    'items' => [
                        'data' => [
                            [
                                'id' => 'si_test',
                                'price' => ['id' => 'price_test'],
                                'quantity' => 1
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $subscriptionService = $this->createMock(SubscriptionService::class);
        $subscriptionService->expects($this->once())
            ->method('handleStripeSubscriptionCreated')
            ->with($webhookData['data']['object']);

        $job = new ProcessStripeWebhook($webhookData, 'customer.subscription.created');
        $job->handle($subscriptionService);
    }
}