<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use App\Models\Course;
use App\Services\AIProviderService;
use App\Services\UsageService;
use App\Services\DocumentProcessingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;

class ChatController extends Controller
{
    public function __construct(
        private AIProviderService $aiProvider,
        private UsageService $usageService,
        private DocumentProcessingService $documentProcessor
    ) {
        // Services are injected automatically by Laravel
    }
    /**
     * Get user subscription status and usage info
     */
    public function getSubscriptionStatus(): JsonResponse
    {
        $user = Auth::user();
        $hasPremium = $user->hasPremiumAccess();
        $usageService = app(\App\Services\UsageService::class);
        
        $usageInfo = [];
        if (!$hasPremium) {
            $usageInfo = $usageService->getUsageSummary($user);
            $usageInfo['reset_time'] = now()->endOfDay()->toISOString();
        }

        return response()->json([
            'has_premium' => $hasPremium,
            'subscription_status' => $user->subscriptionStatus(),
            'on_trial' => $user->onTrial(),
            'on_grace_period' => $user->onGracePeriod(),
            'usage' => $usageInfo,
        ]);
    }

    /**
     * Submit feedback for a message
     */
    public function submitFeedback(Request $request, $messageId): JsonResponse
    {
        if (!Auth::check()) {
            abort(401, 'Unauthorized');
        }

        // Find the message - handle both integer IDs and UUIDs/temporary IDs
        $message = null;
        
        // Try to find by numeric ID first
        if (is_numeric($messageId)) {
            $message = Message::find($messageId);
        }
        
        // If not found and it looks like a UUID, try to find by streaming_session_id
        if (!$message && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $messageId)) {
            $message = Message::where('streaming_session_id', $messageId)->first();
        }
        
        // If still not found, try to find by streaming_session_id even if not a UUID format
        if (!$message) {
            $message = Message::where('streaming_session_id', $messageId)->first();
        }

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found. Please wait for the message to finish generating before submitting feedback.'
            ], 404);
        }

        // Verify the message belongs to the user's chat
        if ($message->chat->user_id !== Auth::id()) {
            abort(403, 'Forbidden');
        }

        $request->validate([
            'feedback' => 'required|in:positive,negative',
        ]);

        $feedback = $request->input('feedback');
        
        // Store feedback in message metadata
        $metadata = $message->metadata ?? [];
        $metadata['feedback'] = $feedback;
        $metadata['feedback_submitted_at'] = now()->toISOString();
        
        $message->update([
            'metadata' => $metadata,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Feedback submitted successfully',
            'feedback' => $feedback,
        ]);
    }

    /**
     * Get user's chats for sidebar
     */
    public function index(): JsonResponse
    {
        $chats = Auth::user()->chats()
            ->with(['latestMessage' => function ($query) {
                $query->latest()->limit(1);
            }])
            ->get()
            ->map(function ($chat) {
                $latestMessage = $chat->latestMessage->first();

                // Ensure UTF-8 safe title/snippet to avoid JSON encoding errors
                $rawTitle = $chat->title ?: 'New Chat';
                $safeTitle = is_string($rawTitle)
                    ? @iconv('UTF-8', 'UTF-8//IGNORE', $rawTitle)
                    : 'New Chat';

                $snippet = null;
                if ($latestMessage && isset($latestMessage->content)) {
                    $content = (string) $latestMessage->content;
                    $content = @iconv('UTF-8', 'UTF-8//IGNORE', $content);
                    $snippet = mb_substr($content, 0, 50, 'UTF-8');
                    if (mb_strlen($content, 'UTF-8') > 50) {
                        $snippet .= '...';
                    }
                }

                // Prefer explicit last_message_at; fallback to latest message time
                $timestamp = null;
                if ($chat->last_message_at) {
                    $timestamp = $chat->last_message_at->diffForHumans();
                } elseif ($latestMessage && $latestMessage->created_at) {
                    $timestamp = $latestMessage->created_at->diffForHumans();
                }

                return [
                    'id' => $chat->id,
                    'title' => $safeTitle,
                    'course_id' => $chat->course_id,
                    'lastMessage' => $snippet,
                    'timestamp' => $timestamp,
                ];
            });

        return response()->json($chats);
    }

    /**
     * Create a new chat
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_type' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'course_id' => 'nullable|exists:courses,id',
        ]);

        $examType = $validated['exam_type'] ?? null;
        $title = $validated['title'] ?? ($examType ? ucfirst($examType) . ' Preparation' : 'New Chat');

        $chat = Auth::user()->chats()->create([
            'title' => $title,
            'exam_type' => $examType,
            'course_id' => $validated['course_id'] ?? null,
            'last_message_at' => now(),
        ]);

        // Usage tracking is handled by middleware

        return response()->json([
            'chat' => [
                'id' => $chat->id,
                'title' => $chat->title,
                'exam_type' => $chat->exam_type,
                'course_id' => $chat->course_id,
            ],
            'id' => $chat->id,
            'title' => $chat->title,
            'lastMessage' => null,
            'timestamp' => 'now',
        ]);
    }

    /**
     * Get messages for a specific chat
     */
    public function show(Chat $chat): JsonResponse
    {
        // Ensure user owns this chat
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        // Optimize: Limit to last 100 messages to improve load performance
        // Most users don't need the full history of thousands of messages
        $messages = $chat->messages()
            ->latest() // Order by created_at desc
            ->limit(100)
            ->get()
            ->reverse() // Reverse to get chronological order (oldest first)
            ->values() // Reset keys
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'role' => $message->role,
                    'timestamp' => $message->getFormattedTimeAttribute(),
                ];
            });

        return response()->json([
            'chat' => [
                'id' => $chat->id,
                'title' => $chat->title ?: 'New Chat',
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Send a message in a chat
     */
    public function sendMessage(Request $request, Chat $chat): JsonResponse
    {
        // Ensure user owns this chat
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'message' => 'required|string|max:4000',
        ]);

        // Require course selection before chatting to enforce exam-specific context
        if (!$chat->course_id) {
            return response()->json([
                'success' => false,
                'error' => 'COURSE_REQUIRED',
                'message' => 'Please select an exam/course before sending messages.'
            ], 422);
        }

        // Check if user can send messages based on their plan
        if (!$this->usageService->canUseFeature(Auth::user(), 'chat_messages')) {
            $usage = $this->usageService->getUsageSummary(Auth::user());
            $chatUsage = $usage['chat_messages'];
            
            return response()->json([
                'success' => false,
                'error' => 'USAGE_LIMIT_EXCEEDED',
                'message' => $this->getUsageLimitMessage(Auth::user(), 'chat_messages'),
                'usage' => $chatUsage
            ], 429);
        }

        try {
            // Save user message
            $userMessage = $chat->messages()->create([
                'role' => 'user',
                'content' => $request->message,
            ]);

            // Track usage for non-premium users
            $this->usageService->incrementUsage(Auth::user(), 'chat_messages');

            // Generate title if this is the first message
            if ($chat->messages()->count() === 1) {
                $chat->generateTitle();
            }

        // Get conversation history for context
        $messages = $this->buildConversationHistory($chat);

        // Get course namespaces for context
        $namespaces = $chat->getEmbeddingNamespaces();

        // Build exam-specific system message
        $systemMessage = $this->buildExamSpecificSystemMessage($chat);

        // Log the system message for debugging
        \Log::info('Exam-specific system message', [
            'chat_id' => $chat->id,
            'course_id' => $chat->course_id,
            'course_name' => $chat->course?->name,
            'system_message' => $systemMessage
        ]);

        // Get AI response with course context
        $aiResponse = $this->aiProvider->chatCompletionWithContext($messages, $namespaces, [
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'system_message' => $systemMessage,
        ]);

            // Save AI response
            $assistantMessage = $chat->messages()->create([
                'role' => 'assistant',
                'content' => $aiResponse['content'],
                'metadata' => [
                    'model' => $this->aiProvider->getModel(),
                    'provider' => $this->aiProvider->getProvider(),
                    'usage' => $aiResponse['usage'],
                ],
            ]);

            // Update chat's last message time
            $chat->updateLastMessageTime();

            return response()->json([
                'success' => true,
                'userMessage' => [
                    'id' => $userMessage->id,
                    'content' => $userMessage->content,
                    'role' => 'user',
                    'timestamp' => $userMessage->getFormattedTimeAttribute(),
                ],
                'assistantMessage' => [
                    'id' => $assistantMessage->id,
                    'content' => $assistantMessage->content,
                    'role' => 'assistant',
                    'timestamp' => $assistantMessage->getFormattedTimeAttribute(),
                ],
                'chat' => [
                    'id' => $chat->id,
                    'title' => $chat->title,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Chat message error', [
                'user_id' => Auth::id(),
                'chat_id' => $chat->id,
                'error' => $e->getMessage(),
                'provider' => $this->aiProvider->getProvider(),
            ]);

            // For development: Use simulated response when AI fails
            // In production: You should have valid API keys
            $fallbackResponse = $this->generateSimulatedResponse($request->message);

            // Save fallback AI response
            $assistantMessage = $chat->messages()->create([
                'role' => 'assistant',
                'content' => $fallbackResponse,
                'metadata' => [
                    'model' => 'fallback',
                    'provider' => 'simulated',
                    'error' => 'AI service unavailable',
                ],
            ]);

            // Update chat's last message time
            $chat->updateLastMessageTime();

            return response()->json([
                'success' => true,
                'userMessage' => [
                    'id' => $userMessage->id,
                    'content' => $userMessage->content,
                    'role' => 'user',
                    'timestamp' => $userMessage->getFormattedTimeAttribute(),
                ],
                'assistantMessage' => [
                    'id' => $assistantMessage->id,
                    'content' => $assistantMessage->content,
                    'role' => 'assistant',
                    'timestamp' => $assistantMessage->getFormattedTimeAttribute(),
                ],
                'chat' => [
                    'id' => $chat->id,
                    'title' => $chat->title,
                ],
                'warning' => 'AI service temporarily unavailable. Using fallback response.'
            ]);
        }
    }

    /**
     * Delete a chat
     */
    public function destroy(Chat $chat): JsonResponse
    {
        // Ensure user owns this chat
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        $chat->delete();

        return response()->json(['message' => 'Chat deleted successfully']);
    }

    /**
     * Update chat properties (e.g., title, course selection)
     */
    public function update(Request $request, Chat $chat): JsonResponse
    {
        // Ensure user owns this chat
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'course_id' => 'nullable|exists:courses,id',
            'course_ids' => 'nullable|array',
            'course_ids.*' => 'exists:courses,id',
        ]);

        if (isset($validated['title'])) {
            $chat->title = $validated['title'];
        }
        
        if (isset($validated['course_id'])) {
            $chat->course_id = $validated['course_id'];
        }
        
        if (isset($validated['course_ids'])) {
            $chat->course_ids = $validated['course_ids'];
        }
        
        $chat->save();

        return response()->json([
            'id' => $chat->id,
            'title' => $chat->title,
            'course_id' => $chat->course_id,
            'course_ids' => $chat->course_ids,
        ]);
    }

    /**
     * Export chat conversations (Premium feature)
     */
    public function exportChat(Chat $chat): JsonResponse
    {
        // Ensure user owns this chat
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        $messages = $chat->messages()->get();
        
        $exportData = [
            'chat_id' => $chat->id,
            'title' => $chat->title,
            'created_at' => $chat->created_at->toISOString(),
            'messages' => $messages->map(function ($message) {
                return [
                    'role' => $message->role,
                    'content' => $message->content,
                    'timestamp' => $message->created_at->toISOString(),
                ];
            }),
        ];

        return response()->json([
            'export_data' => $exportData,
            'filename' => "chat-{$chat->id}-" . now()->format('Y-m-d-H-i-s') . '.json'
        ]);
    }

    /**
     * Get chat analytics (Premium feature)
     */
    public function getAnalytics(): JsonResponse
    {
        $user = Auth::user();
        $chats = $user->chats()->with('messages')->get();
        
        $analytics = [
            'total_chats' => $chats->count(),
            'total_messages' => $chats->sum(fn($chat) => $chat->messages->count()),
            'average_messages_per_chat' => $chats->count() > 0 ? 
                round($chats->sum(fn($chat) => $chat->messages->count()) / $chats->count(), 2) : 0,
            'most_active_day' => $this->getMostActiveDay($chats),
            'chat_frequency' => $this->getChatFrequency($chats),
        ];

        return response()->json($analytics);
    }

    /**
     * Get most active day from chat data
     */
    private function getMostActiveDay($chats): string
    {
        $dayCount = [];
        
        foreach ($chats as $chat) {
            $day = $chat->created_at->format('l');
            $dayCount[$day] = ($dayCount[$day] ?? 0) + 1;
        }
        
        return $dayCount ? array_keys($dayCount, max($dayCount))[0] : 'N/A';
    }

    /**
     * Get chat frequency data
     */
    private function getChatFrequency($chats): array
    {
        $last7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $last7Days[$date] = 0;
        }
        
        foreach ($chats as $chat) {
            $date = $chat->created_at->format('Y-m-d');
            if (isset($last7Days[$date])) {
                $last7Days[$date]++;
            }
        }
        
        return $last7Days;
    }

    /**
     * Generate simulated AI response (fallback when AI service is unavailable)
     */
    private function generateSimulatedResponse(string $userMessage): string
    {
        $message = strtolower($userMessage);
        
        // Provide contextual responses based on keywords
        if (str_contains($message, 'civil') || str_contains($message, 'exam')) {
            return "For civil service exam preparation, I recommend:\n\n1. **Study Plan**: Create a structured 6-month study schedule\n2. **Practice Tests**: Take regular mock exams to assess progress\n3. **Current Affairs**: Stay updated with recent developments\n4. **Previous Papers**: Solve last 5 years' question papers\n5. **Time Management**: Practice answering within time limits\n\nNote: This is a simulated response. Please add your OpenAI or Gemini API key to get real AI assistance.";
        }
        
        if (str_contains($message, 'hello') || str_contains($message, 'hi')) {
            return "Hello! I'm your AI assistant. I can help you with various topics including exam preparation, study strategies, and general questions. How can I assist you today?\n\n*Note: Please configure your AI provider (OpenAI or Gemini) in the .env file for full AI capabilities.*";
        }
        
        if (str_contains($message, 'help') || str_contains($message, 'how')) {
            return "I'd be happy to help you with that! Here are some ways I can assist:\n\n• Answer questions on various topics\n• Provide study guidance and tips\n• Help with exam preparation strategies\n• Explain concepts and provide examples\n\nTo get the most accurate and detailed responses, please set up your AI provider (OpenAI or Gemini) with a valid API key.\n\nWhat specific topic would you like help with?";
        }
        
        // Generic contextual response
        return "Thank you for your message: \"{$userMessage}\"\n\nI understand you're looking for information on this topic. While I'm currently running in fallback mode, I can still provide some general guidance.\n\nTo unlock my full AI capabilities with detailed, personalized responses, please:\n1. Get an API key from OpenAI or Google Gemini\n2. Add it to your .env file\n3. Restart the application\n\nIs there anything specific I can help clarify about this topic?";
    }

    /**
     * Build exam-specific system message
     */
    private function buildExamSpecificSystemMessage(Chat $chat): string
    {
        $baseMessage = config('ai.defaults.system_message');
        
        if (!$chat->course_id) {
            return $baseMessage;
        }

        $course = $chat->course;
        if (!$course) {
            return $baseMessage;
        }

        $examContext = match($course->slug) {
            'sat-preparation' => "You are specifically helping with SAT (Scholastic Assessment Test) preparation. Focus on SAT-specific content including Reading & Writing, Math sections, test strategies, timing, and practice questions. The SAT is scored 400-1600 and is used for undergraduate college admissions.",
            'gre-preparation' => "You are specifically helping with GRE (Graduate Record Examination) preparation. Focus on GRE-specific content including Verbal Reasoning, Quantitative Reasoning, and Analytical Writing sections. The GRE is used for graduate school admissions worldwide.",
            'gmat-preparation' => "You are specifically helping with GMAT (Graduate Management Admission Test) preparation. Focus on GMAT-specific content including Quantitative, Verbal, and Data Insights sections. The GMAT is used for business school admissions.",
            'custom-preparation' => "You are helping with custom exam preparation. Adapt your guidance based on the specific exam requirements and content the user mentions.",
            default => "You are helping with {$course->name} exam preparation. Focus on exam-specific strategies, content, and practice materials for this particular exam."
        };

        return "{$baseMessage}\n\n{$examContext}\n\nAlways provide exam-specific guidance, study strategies, and practice recommendations relevant to the selected exam.";
    }

    /**
     * Build conversation history for AI context
     */
    private function buildConversationHistory(Chat $chat): array
    {
        // Get recent messages (limit to last 20 for context window management)
        $messages = $chat->messages()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->reverse()
            ->values();

        $conversationHistory = [];

        // Add conversation messages (system message is handled separately now)
        foreach ($messages as $message) {
            $conversationHistory[] = [
                'role' => $message->role,
                'content' => $message->content
            ];
        }

        return $conversationHistory;
    }

    /**
     * Get available courses for selection
     */
    public function getCourses(): JsonResponse
    {
        $courses = Course::active()
            ->ordered()
            ->get(['id', 'name', 'slug', 'description', 'namespace', 'icon', 'color']);

        return response()->json($courses);
    }

    /**
     * Get appropriate message for usage limit exceeded
     */
    private function getUsageLimitMessage($user, string $feature): string
    {
        $planName = $user->getCurrentPlanName();
        $planKey = $user->getCurrentPlanKey();
        
        switch ($feature) {
            case 'chat_messages':
                if ($planKey === 'free') {
                    return 'You\'ve reached your daily limit of 3 messages. Upgrade to Premium for 200 messages per month or Plus for unlimited messages.';
                } elseif ($planKey === 'premium') {
                    return 'You\'ve reached your monthly limit of 200 messages. Upgrade to Plus for unlimited messages.';
                }
                break;
            case 'file_uploads':
                if ($planKey === 'free') {
                    return 'File uploads are not available on the free plan. Upgrade to Premium, Plus, or Academy to upload files.';
                }
                break;
        }
        
        return 'You\'ve reached your usage limit for this feature. Please upgrade your plan for more access.';
    }

    /**
     * Get current usage for the authenticated user
     */
    public function getUsage(): JsonResponse
    {
        $user = Auth::user();
        $usageService = app(\App\Services\UsageService::class);
        $usage = $usageService->getUsageSummary($user);

        return response()->json([
            'success' => true,
            'usage' => $usage
        ]);
    }
}