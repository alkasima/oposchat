<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Keep only external API routes here

// Stripe webhook route - no authentication required, CSRF exempted
Route::post('/stripe/webhook', [WebhookController::class, 'handleWebhook']);