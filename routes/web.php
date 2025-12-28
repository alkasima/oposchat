<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\StreamingChatController;
use App\Models\Course;
use App\Http\Controllers\EmailVerificationController;
use Illuminate\Support\Facades\Http;

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
    
    // Quiz web routes
    Route::get('/quizzes', function () {
        $courses = \App\Models\Course::active()->ordered()->get(['id', 'name', 'slug']);
        return Inertia::render('Quizzes', [
            'courses' => $courses,
        ]);
    })->name('quizzes');
    
    Route::get('/quiz-attempt/{attempt}', function (\App\Models\QuizAttempt $attempt) {
        // Verify ownership
        if ($attempt->user_id !== Auth::id()) {
            abort(403);
        }
        
        // Load relationships
        $attempt->load([
            'answers.question.options',
            'quiz:id,title,duration_minutes',
        ]);
        
        return Inertia::render('QuizAttempt', [
            'attempt' => $attempt,
        ]);
    })->name('quiz.attempt');
    
    Route::get('/quiz-results/{attempt}', function (\App\Models\QuizAttempt $attempt) {
        // Verify ownership
        if ($attempt->user_id !== Auth::id()) {
            abort(403);
        }
        
        // Verify completed
        if ($attempt->status !== 'completed') {
            return redirect()->route('quiz.attempt', $attempt->id);
        }
        
        // Load relationships
        $attempt->load([
            'answers.question.options',
            'quiz:id,title',
        ]);
        
        return Inertia::render('QuizResults', [
            'results' => $attempt,
        ]);
    })->name('quiz.results');
});

// API routes for subscription (using web middleware for session-based auth)
Route::middleware(['auth', 'verified'])->prefix('api')->group(function () {
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [App\Http\Controllers\SubscriptionController::class, 'index']);
        Route::get('/status', [App\Http\Controllers\SubscriptionController::class, 'status']);
        Route::get('/plans', [App\Http\Controllers\SubscriptionController::class, 'plans']);
        Route::post('/checkout', [App\Http\Controllers\SubscriptionController::class, 'createCheckoutSession']);
        Route::post('/upgrade', [App\Http\Controllers\SubscriptionController::class, 'upgrade']);
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
    Route::post('/messages/{message}/feedback', [App\Http\Controllers\ChatController::class, 'submitFeedback']);
    Route::delete('/chats/{chat}', [App\Http\Controllers\ChatController::class, 'destroy']);
    
    // Courses API routes
    Route::get('/courses', [App\Http\Controllers\ChatController::class, 'getCourses']);
    
    // Quiz API routes
    Route::prefix('quizzes')->group(function () {
        Route::get('/', [App\Http\Controllers\QuizController::class, 'index']); // List quizzes
        Route::get('/topics', [App\Http\Controllers\QuizController::class, 'getTopics']); // Get available topics
        Route::get('/questions', [App\Http\Controllers\QuizController::class, 'getQuestions']); // Get filtered questions
        Route::get('/{quiz}', [App\Http\Controllers\QuizController::class, 'show']); // Quiz details
        Route::post('/{quiz}/start', [App\Http\Controllers\QuizController::class, 'startAttempt']); // Start attempt
    });
    
    Route::prefix('quiz-attempts')->group(function () {
        Route::post('/{attempt}/answer', [App\Http\Controllers\QuizController::class, 'submitAnswer']); // Submit answer
        Route::post('/{attempt}/bookmark', [App\Http\Controllers\QuizController::class, 'toggleBookmark']); // Toggle bookmark
        Route::post('/{attempt}/complete', [App\Http\Controllers\QuizController::class, 'completeAttempt']); // Complete attempt
        Route::get('/{attempt}/results', [App\Http\Controllers\QuizController::class, 'getAttemptResults']); // Get results
    });
    
    Route::get('/quiz-history', [App\Http\Controllers\QuizController::class, 'getUserHistory']); // User's quiz history
    
    // AI Quiz Generation routes
    Route::prefix('ai-quiz')->group(function () {
        Route::post('/generate', [App\Http\Controllers\QuizController::class, 'generateAIQuiz']); // Generate AI quiz
        Route::post('/generate-explanation', [App\Http\Controllers\QuizController::class, 'generateExplanation']); // Generate AI explanation
    });

    
    // Personalization routes
    Route::prefix('personalization')->group(function () {
        Route::get('/recommendations', [App\Http\Controllers\QuizController::class, 'getRecommendations']); // Get recommendations
        Route::get('/adaptive-config', [App\Http\Controllers\QuizController::class, 'getAdaptiveQuizConfig']); // Get adaptive config
        Route::get('/similar-quiz/{attempt}', [App\Http\Controllers\QuizController::class, 'generateSimilarQuiz']); // Generate similar quiz
    });
    
    // Enhanced Statistics route
    Route::get('/quiz-statistics-enhanced', [App\Http\Controllers\QuizController::class, 'getStatistics']); // Enhanced statistics
    
    // Quiz Statistics API routes
    Route::prefix('quiz-statistics')->group(function () {
        Route::get('/', [App\Http\Controllers\QuizStatisticsController::class, 'getStatistics']); // Overall stats
        Route::get('/topics', [App\Http\Controllers\QuizStatisticsController::class, 'getTopicBreakdown']); // Topic breakdown
        Route::get('/recommendations', [App\Http\Controllers\QuizStatisticsController::class, 'getRecommendations']); // Recommendations
        Route::get('/export', [App\Http\Controllers\QuizStatisticsController::class, 'exportStatistics']); // Export data
    });
    
    // Streaming chat routes
    Route::get('/chats/{chat}/stream', [App\Http\Controllers\StreamingChatController::class, 'streamMessage'])->middleware('usage.limit:chat_messages');
    Route::post('/chats/stream/stop', [App\Http\Controllers\StreamingChatController::class, 'stopStreaming']);
    
    // Audio transcription route
    Route::post('/transcribe-audio', [App\Http\Controllers\AudioTranscriptionController::class, 'transcribe']);

    // Usage endpoint
    Route::get('/usage', [App\Http\Controllers\ChatController::class, 'getUsage'])->name('usage.public');
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
    
    // Quiz Management
    Route::get('/courses/{course}/quizzes/create', function (Course $course) {
        return Inertia::render('Admin/Courses/CreateQuiz', [
            'course' => $course
        ]);
    })->name('admin.courses.quizzes.create');
    Route::post('/courses/{course}/quizzes', [App\Http\Controllers\Admin\CourseQuizController::class, 'store'])->name('admin.courses.quizzes.store');
    
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

// Temporary Debug Route for AI
Route::get('/debug/ai', function () {
    return <<<'HTML'
    <!DOCTYPE html>
    <html>
    <head>
        <title>AI Debug</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    </head>
    <body class="bg-gray-100 p-8">
        <div class="max-w-2xl mx-auto bg-white p-6 rounded shadow">
            <h1 class="text-2xl font-bold mb-4">AI Connectivity Test</h1>
            
            @if(session('response'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                    <p class="font-bold">Response:</p>
                    <p>{{ session('response') }}</p>
                    <div class="text-xs mt-2 text-gray-500">
                        Provider: {{ session('provider') }} | Model: {{ session('model') }}
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                    <p class="font-bold">Error:</p>
                    <p>{{ session('error') }}</p>
                </div>
            @endif

            <form method="POST" action="/debug/ai">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Message</label>
                    <input type="text" name="message" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="Which model are you running on?">
                </div>
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Send Test Request
                </button>
            </form>
        </div>
    </body>
    </html>
HTML;
});

Route::post('/debug/ai', function (\Illuminate\Http\Request $request) {
    try {
        $message = $request->input('message');
        
        // Manual instantiation to test
        $ai = new \App\Services\EnhancedAIProviderService();
        
        // Simple completion
        $response = $ai->chatCompletion([
            ['role' => 'user', 'content' => $message]
        ]);
        
        return back()->with('response', $response['content'])
                     ->with('model', $ai->getModel())
                     ->with('provider', $ai->getProvider());
                     
    } catch (\Exception $e) {
        return back()->with('error', $e->getMessage() . "\n" . $e->getTraceAsString());
    }
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Public email verification routes (token-based, 24h expiry)
Route::middleware(['signed', 'throttle:6,1'])->group(function () {
    Route::get('/email/verify-link', [EmailVerificationController::class, 'verify'])->name('email.verify');
});

Route::get('/email/verify/success', [EmailVerificationController::class, 'success'])->name('email.verify.success');
Route::get('/email/verify/error', [EmailVerificationController::class, 'error'])->name('email.verify.error');
Route::post('/email/verify/resend', [EmailVerificationController::class, 'resend'])->name('email.verify.resend');


Route::get('/send-test-email', function () {
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
        'x-auth-token' => env('EMAIL_API_TOKEN'), // required header
    ])->post(env('EMAIL_API_URL'), [
        'from' => [
            'email' => env('EMAIL_API_FROM_EMAIL'),
            'name' => env('EMAIL_API_FROM_NAME'),
        ],
        'to' => [
            [
                'email' => 'alkasima1010@gmail.com', // replace with your test email
                'name' => 'Test User',
            ]
        ],
        'subject' => 'Test Email',
        'html_part' => '<html><body><p>This is a test email sent from Laravel.</p></body></html>',
        'text_part_auto' => true, // let the API generate the plain text
        // removed 'text_part'
    ]);

    if ($response->successful()) {
        return response()->json([
            'message' => 'Email sent successfully!',
            'data' => $response->json(),
        ]);
    }

    return response()->json([
        'message' => 'Failed to send email',
        'error' => $response->body(),
    ], $response->status());
});