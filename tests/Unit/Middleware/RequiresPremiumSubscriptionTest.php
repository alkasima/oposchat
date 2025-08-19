<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\RequiresPremiumSubscription;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;
use Mockery;

class RequiresPremiumSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private RequiresPremiumSubscription $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RequiresPremiumSubscription();
    }

    public function test_allows_access_for_premium_users()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->active()->create(['user_id' => $user->id]);
        
        $request = Request::create('/test');
        $request->setUserResolver(fn() => $user);

        $next = fn($request) => new Response('success');

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('success', $response->getContent());
    }

    public function test_blocks_access_for_non_premium_users()
    {
        $user = User::factory()->create();
        
        $request = Request::create('/test');
        $request->setUserResolver(fn() => $user);

        $next = fn($request) => new Response('success');

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(403, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('premium_required', $responseData['error_code']);
    }

    public function test_blocks_access_for_unauthenticated_users()
    {
        $request = Request::create('/test');
        $request->setUserResolver(fn() => null);

        $next = fn($request) => new Response('success');

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals(401, $response->getStatusCode());
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('unauthenticated', $responseData['error_code']);
    }

    public function test_allows_access_for_trial_users()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->onTrial()->create(['user_id' => $user->id]);
        
        $request = Request::create('/test');
        $request->setUserResolver(fn() => $user);

        $next = fn($request) => new Response('success');

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('success', $response->getContent());
    }

    public function test_allows_access_for_grace_period_users()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->onGracePeriod()->create(['user_id' => $user->id]);
        
        $request = Request::create('/test');
        $request->setUserResolver(fn() => $user);

        $next = fn($request) => new Response('success');

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('success', $response->getContent());
    }
}