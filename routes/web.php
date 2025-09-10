<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\StreamingChatController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/test', function () {
    return Inertia::render('Test');
})->name('test');

Route::get('/pricing', function () {
    return Inertia::render('Pricing');
})->name('pricing');

// Exams Wiki page
Route::get('/exams/wiki', function () {
    return Inertia::render('exams/Wiki');
})->name('exams.wiki');

// Legal pages
Route::get('/privacy-policy', function () {
    return Inertia::render('legal/PrivacyPolicy');
})->name('legal.privacy');

Route::get('/terms-of-service', function () {
    return Inertia::render('legal/TermsOfService');
})->name('legal.terms');

Route::get('/cookie-policy', function () {
    return Inertia::render('legal/CookiePolicy');
})->name('legal.cookies');

// About page
Route::get('/about', function () {
    return Inertia::render('About');
})->name('about');

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
    
    // Test route for exam-specific system message
    Route::get('/test-exam-context/{chat}', function(\App\Models\Chat $chat) {
        $controller = new \App\Http\Controllers\ChatController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('buildExamSpecificSystemMessage');
        $method->setAccessible(true);
        $systemMessage = $method->invoke($controller, $chat);
        
        return response()->json([
            'chat_id' => $chat->id,
            'course_id' => $chat->course_id,
            'course_name' => $chat->course?->name,
            'system_message' => $systemMessage
        ]);
    })->name('test.exam.context');
});

// API routes for subscription (using web middleware for session-based auth)
Route::middleware(['auth', 'verified'])->prefix('api')->group(function () {
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [App\Http\Controllers\SubscriptionController::class, 'index']);
        Route::get('/status', [App\Http\Controllers\SubscriptionController::class, 'status']);
        Route::get('/plans', [App\Http\Controllers\SubscriptionController::class, 'plans']);
        Route::post('/checkout', [App\Http\Controllers\SubscriptionController::class, 'createCheckoutSession']);
        Route::post('/confirm', [App\Http\Controllers\SubscriptionController::class, 'confirmCheckout']);
        Route::post('/manage', [App\Http\Controllers\SubscriptionController::class, 'manageSubscription']);
        Route::delete('/cancel', [App\Http\Controllers\SubscriptionController::class, 'cancelSubscription']);
    });
    
    // Chat API routes
    Route::get('/chats/subscription-status', [App\Http\Controllers\ChatController::class, 'getSubscriptionStatus']);
    Route::get('/chats/analytics', [App\Http\Controllers\ChatController::class, 'getAnalytics'])->middleware('premium');
    Route::get('/chats', [App\Http\Controllers\ChatController::class, 'index']);
    Route::post('/chats', [App\Http\Controllers\ChatController::class, 'store']);
    Route::patch('/chats/{chat}', [App\Http\Controllers\ChatController::class, 'update']);
    Route::get('/chats/{chat}', [App\Http\Controllers\ChatController::class, 'show']);
    Route::get('/chats/{chat}/export', [App\Http\Controllers\ChatController::class, 'exportChat'])->middleware('premium');
    Route::post('/chats/{chat}/messages', [App\Http\Controllers\ChatController::class, 'sendMessage'])->middleware('usage.limit:chat_messages');
    Route::delete('/chats/{chat}', [App\Http\Controllers\ChatController::class, 'destroy']);
    
    // Courses API routes
    Route::get('/courses', [App\Http\Controllers\ChatController::class, 'getCourses']);
    
    // Streaming chat routes
    Route::get('/chats/{chat}/stream', [App\Http\Controllers\StreamingChatController::class, 'streamMessage'])->middleware('usage.limit:chat_messages');
    Route::post('/chats/stream/stop', [App\Http\Controllers\StreamingChatController::class, 'stopStreaming']);
});

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\AdminController::class, 'dashboard'])->name('admin.dashboard');
    
    // Users management
    Route::get('/users', [App\Http\Controllers\AdminController::class, 'usersIndex'])->name('admin.users.index');

    // Reports
    Route::get('/reports', [App\Http\Controllers\AdminController::class, 'reportsIndex'])->name('admin.reports.index');

    // Course management routes
    Route::get('/courses', [App\Http\Controllers\AdminController::class, 'coursesIndex'])->name('admin.courses.index');
    Route::get('/courses/create', [App\Http\Controllers\AdminController::class, 'coursesCreate'])->name('admin.courses.create');
    Route::post('/courses', [App\Http\Controllers\AdminController::class, 'coursesStore'])->name('admin.courses.store');
    Route::get('/courses/{course}/edit', [App\Http\Controllers\AdminController::class, 'coursesEdit'])->name('admin.courses.edit');
    Route::put('/courses/{course}', [App\Http\Controllers\AdminController::class, 'coursesUpdate'])->name('admin.courses.update');
    Route::delete('/courses/{course}', [App\Http\Controllers\AdminController::class, 'coursesDestroy'])->name('admin.courses.destroy');
    
    // Course content management routes
    Route::get('/course-content', [App\Http\Controllers\Admin\CourseContentController::class, 'index'])->name('admin.course-content.index');
    Route::post('/course-content/upload', [App\Http\Controllers\Admin\CourseContentController::class, 'uploadContent'])->name('admin.course-content.upload');
    Route::post('/course-content/upload-file', [App\Http\Controllers\Admin\CourseContentController::class, 'uploadFile'])->name('admin.course-content.upload-file');
    Route::post('/course-content/delete', [App\Http\Controllers\Admin\CourseContentController::class, 'deleteContent'])->name('admin.course-content.delete');
    Route::get('/course-content/stats', [App\Http\Controllers\Admin\CourseContentController::class, 'getContentStats'])->name('admin.course-content.stats');

    // Settings
    Route::get('/settings', [App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('admin.settings.index');
    Route::post('/settings/keys', [App\Http\Controllers\Admin\SettingsController::class, 'updateKeys'])->name('admin.settings.update-keys');
    Route::post('/settings/password', [App\Http\Controllers\Admin\SettingsController::class, 'updatePassword'])->name('admin.settings.update-password');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
