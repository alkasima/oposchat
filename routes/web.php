<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\StreamingChatController;
use App\Models\Course;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/test', function () {
    return Inertia::render('Test');
})->name('test');

Route::get('/pricing', function () {
    return Inertia::render('Pricing');
})->name('pricing');

Route::get('/academy-contact', function () {
    return Inertia::render('AcademyContact');
})->name('academy.contact');

// Exams Wiki page
Route::get('/exams/wiki', function () {
    return Inertia::render('exams/Wiki');
})->name('exams.wiki');

// Public courses endpoints (visible without auth)
Route::get('/public/courses', function () {
    return Course::active()->ordered()
        ->get(['id','name','slug','namespace','description','icon','color','badge','badge_color']);
})->name('public.courses.index');

Route::get('/public/courses/{slug}', function (string $slug) {
    $course = Course::active()
        ->where('slug', $slug)
        ->orWhere('namespace', $slug)
        ->firstOrFail(['id','name','slug','namespace','description','full_description','icon','color','badge','badge_color']);
    return response()->json($course);
})->name('public.courses.show');

// Course-specific wiki page
Route::get('/exams/wiki/{slug}', function (string $slug) {
    return Inertia::render('exams/Course', [ 'slug' => $slug ]);
})->name('exams.wiki.course');

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

// Contact page
Route::get('/contact', function () {
    return Inertia::render('Contact');
})->name('contact');

// Contact form submissions
Route::post('/contact/submit', [App\Http\Controllers\ContactController::class, 'submitContact'])->name('contact.submit');
Route::post('/contact/oppositions', [App\Http\Controllers\ContactController::class, 'submitOppositionsRequest'])->name('contact.oppositions');

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
        Route::post('/refresh', [App\Http\Controllers\SubscriptionController::class, 'refreshSubscriptionStatus']);
        Route::post('/refresh-plan', [App\Http\Controllers\SubscriptionController::class, 'refreshUserPlan']);
        Route::post('/sync', [App\Http\Controllers\SubscriptionController::class, 'syncUserSubscriptions']);
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
    
    // Audio transcription route
    Route::post('/transcribe-audio', [App\Http\Controllers\AudioTranscriptionController::class, 'transcribe']);
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
    
    // Course document management routes
    Route::get('/courses/{course}/documents', function (Course $course) {
        return Inertia::render('Admin/Courses/Documents', [
            'course' => $course
        ]);
    })->name('admin.courses.documents.page');
    Route::get('/courses/{course}/documents/api', [App\Http\Controllers\Admin\CourseDocumentController::class, 'index'])->name('admin.courses.documents.index');
    Route::post('/courses/{course}/documents', [App\Http\Controllers\Admin\CourseDocumentController::class, 'store'])->name('admin.courses.documents.store');
    Route::delete('/course-documents/{document}', [App\Http\Controllers\Admin\CourseDocumentController::class, 'destroy'])->name('admin.course-documents.destroy');
    Route::get('/courses/{course}/documents/stats', [App\Http\Controllers\Admin\CourseDocumentController::class, 'stats'])->name('admin.courses.documents.stats');

    // User plan management routes
    Route::get('/user-plans', [App\Http\Controllers\Admin\UserPlanController::class, 'index'])->name('admin.user-plans.index');
    Route::get('/user-plans/search', [App\Http\Controllers\Admin\UserPlanController::class, 'search'])->name('admin.user-plans.search');
    Route::get('/user-plans/{user}', [App\Http\Controllers\Admin\UserPlanController::class, 'show'])->name('admin.user-plans.show');
    Route::post('/user-plans/{user}/update-plan', [App\Http\Controllers\Admin\UserPlanController::class, 'updatePlan'])->name('admin.user-plans.update-plan');

    // Settings
    Route::get('/settings', [App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('admin.settings.index');
    Route::post('/settings/keys', [App\Http\Controllers\Admin\SettingsController::class, 'updateKeys'])->name('admin.settings.update-keys');
    Route::post('/settings/password', [App\Http\Controllers\Admin\SettingsController::class, 'updatePassword'])->name('admin.settings.update-password');
    
    // Usage endpoint for frontend
    Route::get('/usage', [App\Http\Controllers\ChatController::class, 'getUsage'])->name('usage');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
