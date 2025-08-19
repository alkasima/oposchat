<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use App\Services\AIProviderService;
use App\Services\UsageService;
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
        private UsageService $usageService
    ) {}
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
                
                return [
                    'id' => $chat->id,
                    'title' => $chat->title ?: 'New Chat',
                    'lastMessage' => $latestMessage ? substr($latestMessage->content, 0, 50) . '...' : null,
                    'timestamp' => $chat->last_message_at ? $chat->last_message_at->diffForHumans() : null,
                ];
            });

        return response()->json($chats);
    }

    /**
     * Create a new chat
     */
    public function store(): JsonResponse
    {
        $chat = Auth::user()->chats()->create([
            'last_message_at' => now(),
        ]);

        // Usage tracking is handled by middleware

        return response()->json([
            'id' => $chat->id,
            'title' => 'New Chat',
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

        $messages = $chat->messages()
            ->get()
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

            // Get AI response
            $aiResponse = $this->aiProvider->chatCompletion($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1000,
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

        // Add system message
        $conversationHistory[] = [
            'role' => 'system',
            'content' => config('ai.defaults.system_message')
        ];

        // Add conversation messages
        foreach ($messages as $message) {
            $conversationHistory[] = [
                'role' => $message->role,
                'content' => $message->content
            ];
        }

        return $conversationHistory;
    }
}