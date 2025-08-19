<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckUsageLimit;
use App\Models\User;
use App\Models\Subscription;
use App\Services\UsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;
use Mockery;

class CheckUsageLimitTest extends TestCase
{
    use RefreshDatabase;

    private CheckUsageLimit $middleware;
    private UsageService $usageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usageService = Mockery::mock(UsageService::class);
        $this->middleware = new CheckUsageLimit($this->usageService);
    }

    public function test_allows_access_for_premium_users()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->active()->create(['user_id' => $user->id]);
        
        $request = Request::create('/test');
        $request->setUserResolver(fn() => $user);

        $next = fn($request) => new Response('success');

        $response = $this->middleware->handle($request, $next, 'chat_messages');

        $this->assertEquals('success', $response->getContent());
    }

    public function test_allows_access_when_under_usage_limit()
    {
        $user = User::factory()->create();
        
        $this->usageService->shouldReceive('canUseFeature')
            ->with($user, 'chat_messages')
            ->once()
            ->andReturn(true);

        $request = Request::create('/test');
        $request->setUserResolver(fn() => $user);

        $next = fn($request) => new Response('success');

        $response = $this->middleware->handle($request, $next, 'chat_messages');

        $this->assertEquals('success', $response->getContent());
    }

    public function test_blocks_access_when_usage_limit_exceeded()
    {
        $user = User::factory()->create();
        
        $this->usageService->shouldReceive('canUseFeature')
            ->with($user, 'chat_messages')
            ->once()
            ->andReturn(false);

        $this->usageService->shouldReceive('getFeatureLimit')
            ->with('chat_messages')
            ->once()
            ->andReturn(50);

        $this->usageService->shouldReceive('getCurrentUsage')
            ->with($user, 'chat_messages')
            ->once()
            ->andReturn(50);

        $request = Request::create('/test');
        $request->setUserResolver(fn() => $user);

        $next = fn($request) => new Response('success');

        $response = $this->middleware->handle($request, $next, 'chat_messages');

        $this->assertEquals(429, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('usage_limit_exceeded', $responseData['error_code']);
        $this->assertEquals('chat_messages', $responseData['details']['feature']);
        $this->assertEquals(50, $responseData['details']['limit']);
        $this->assertEquals(50, $responseData['details']['current_usage']);
    }

    public function test_blocks_access_for_unauthenticated_users()
    {
        $request = Request::create('/test');
        $request->setUserResolver(fn() => null);

        $next = fn($request) => new Response('success');

        $response = $this->middleware->handle($request, $next, 'chat_messages');

        $this->assertEquals(401, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('unauthenticated', $responseData['error_code']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}