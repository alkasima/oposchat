<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class OptimizedChatController extends Controller
{
    /**
     * Get user's chats for sidebar with caching and pagination
     * OPTIMIZED: Added Redis caching and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $page = $request->get('page', 1);
        $perPage = 20; // Load 20 chats at a time
        
        $cacheKey = 'user_chats_' . Auth::id() . '_page_' . $page;
        
        $chats = Cache::remember($cacheKey, 300, function () use ($perPage) { // Cache for 5 minutes
            return Auth::user()->chats()
                ->with(['latestMessage' => function ($query) {
                    $query->latest()->limit(1);
                }])
                ->orderBy('last_message_at', 'desc')
                ->paginate($perPage)
                ->through(function ($chat) {
                    $latestMessage = $chat->latestMessage->first();

                    // Ensure UTF-8 safe title/snippet
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
        });

        return response()->json($chats);
    }

    /**
     * Get messages for a specific chat with pagination
     * OPTIMIZED: Added pagination for large chat histories
     */
    public function show(Chat $chat, Request $request): JsonResponse
    {
        // Ensure user owns this chat
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        $page = $request->get('page', 1);
        $perPage = 50; // Load 50 messages at a time

        $cacheKey = 'chat_messages_' . $chat->id . '_page_' . $page;

        $data = Cache::remember($cacheKey, 180, function () use ($chat, $perPage) { // Cache for 3 minutes
            $messages = $chat->messages()
                ->orderBy('created_at', 'asc')
                ->paginate($perPage)
                ->through(function ($message) {
                    return [
                        'id' => $message->id,
                        'content' => $message->content,
                        'role' => $message->role,
                        'timestamp' => $message->getFormattedTimeAttribute(),
                    ];
                });

            return [
                'chat' => [
                    'id' => $chat->id,
                    'title' => $chat->title ?: 'New Chat',
                ],
                'messages' => $messages,
            ];
        });

        return response()->json($data);
    }

    /**
     * Get available courses with caching
     * OPTIMIZED: Added Redis caching for courses list
     */
    public function getCourses(): JsonResponse
    {
        $courses = Cache::remember('active_courses', 3600, function () { // Cache for 1 hour
            return Course::active()
                ->ordered()
                ->get(['id', 'name', 'slug', 'description', 'namespace', 'icon', 'color']);
        });

        return response()->json($courses);
    }

    /**
     * Build conversation history with optimized query
     * OPTIMIZED: Uses select() to only fetch needed columns
     */
    private function buildConversationHistory(Chat $chat): array
    {
        $cacheKey = 'chat_history_' . $chat->id;

        return Cache::remember($cacheKey, 60, function () use ($chat) { // Cache for 1 minute
            // Get recent messages (limit to last 20 for context window management)
            $messages = $chat->messages()
                ->select('role', 'content', 'created_at') // Only fetch needed columns
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->reverse()
                ->values();

            $conversationHistory = [];

            foreach ($messages as $message) {
                $conversationHistory[] = [
                    'role' => $message->role,
                    'content' => $message->content
                ];
            }

            return $conversationHistory;
        });
    }

    /**
     * Clear chat cache when new message is added
     * Call this after creating a new message
     */
    public static function clearChatCache(int $chatId, int $userId): void
    {
        // Clear specific chat caches
        Cache::forget('chat_messages_' . $chatId . '_page_1');
        Cache::forget('chat_history_' . $chatId);
        
        // Clear user's chat list cache (all pages)
        for ($page = 1; $page <= 10; $page++) {
            Cache::forget('user_chats_' . $userId . '_page_' . $page);
        }
    }

    /**
     * Clear course cache when courses are updated
     * Call this after updating courses
     */
    public static function clearCourseCache(): void
    {
        Cache::forget('active_courses');
    }
}
