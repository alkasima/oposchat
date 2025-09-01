<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\StreamingChatController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/pricing', function () {
    return Inertia::render('Pricing');
})->name('pricing');

// CSRF token refresh endpoint
Route::get('/csrf-token', function () {
    return response()->json([
        'csrf_token' => csrf_token()
    ]);
})->middleware('web');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Handle subscription success redirect
Route::get('subscription/success', function () {
    return Inertia::render('Dashboard', [
        'showSubscriptionSuccess' => true
    ]);
})->middleware(['auth', 'verified'])->name('subscription.success');

// Chat routes for web interface
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/chat/{chat?}', function ($chatId = null) {
        return Inertia::render('Dashboard', [
            'chatId' => $chatId,
        ]);
    })->name('chat');
});

// API routes for subscription (using web middleware for session-based auth)
Route::middleware(['auth', 'verified'])->prefix('api')->group(function () {
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [App\Http\Controllers\SubscriptionController::class, 'index']);
        Route::get('/status', [App\Http\Controllers\SubscriptionController::class, 'status']);
        Route::get('/plans', [App\Http\Controllers\SubscriptionController::class, 'plans']);
        Route::post('/checkout', [App\Http\Controllers\SubscriptionController::class, 'createCheckoutSession']);
        Route::post('/manage', [App\Http\Controllers\SubscriptionController::class, 'manageSubscription']);
        Route::delete('/cancel', [App\Http\Controllers\SubscriptionController::class, 'cancelSubscription']);
    });
    
    // Chat API routes
    Route::get('/chats/subscription-status', [App\Http\Controllers\ChatController::class, 'getSubscriptionStatus']);
    Route::get('/chats/analytics', [App\Http\Controllers\ChatController::class, 'getAnalytics'])->middleware('premium');
    Route::get('/chats', [App\Http\Controllers\ChatController::class, 'index']);
    Route::post('/chats', [App\Http\Controllers\ChatController::class, 'store']);
    Route::get('/chats/{chat}', [App\Http\Controllers\ChatController::class, 'show']);
    Route::get('/chats/{chat}/export', [App\Http\Controllers\ChatController::class, 'exportChat'])->middleware('premium');
    Route::post('/chats/{chat}/messages', [App\Http\Controllers\ChatController::class, 'sendMessage'])->middleware('usage.limit:chat_messages');
    Route::delete('/chats/{chat}', [App\Http\Controllers\ChatController::class, 'destroy']);
    
    // Streaming chat routes
    Route::get('/chats/{chat}/stream', [App\Http\Controllers\StreamingChatController::class, 'streamMessage'])->middleware('usage.limit:chat_messages');
    Route::post('/chats/stream/stop', [App\Http\Controllers\StreamingChatController::class, 'stopStreaming']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
