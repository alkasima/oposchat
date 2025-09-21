<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Services\AIProviderService;
use App\Services\StreamingMessageService;
use App\Services\DocumentProcessingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamingChatController extends Controller
{
    public function __construct(
        private AIProviderService $aiProviderService,
        private StreamingMessageService $streamingMessageService,
        private DocumentProcessingService $documentProcessor
    ) {
        // Inject DocumentProcessingService into AIProviderService
        $this->aiProviderService = new AIProviderService($this->documentProcessor);
    }

    /**
     * Stream AI response using Server-Sent Events
     */
    public function streamMessage(Request $request, Chat $chat): StreamedResponse
    {
        // Authentication and authorization checks
        if (!Auth::check()) {
            abort(401, 'Unauthorized');
        }

        if ($chat->user_id !== Auth::id()) {
            abort(403, 'Forbidden');
        }

        $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        // Check if user can send messages based on their plan
        $usageService = app(\App\Services\UsageService::class);
        if (!$usageService->canUseFeature(Auth::user(), 'chat_messages')) {
            $usage = $usageService->getUsageSummary(Auth::user());
            $chatUsage = $usage['chat_messages'];
            
            // Send usage limit error as Server-Sent Event
            return new StreamedResponse(function () use ($chatUsage) {
                $errorData = [
                    'error' => 'USAGE_LIMIT_EXCEEDED',
                    'message' => 'You\'ve reached your daily limit of 3 messages. Upgrade to Premium for 200 messages per month or Plus for unlimited messages.',
                    'usage' => $chatUsage
                ];
                
                echo "event: usage_limit_exceeded\n";
                echo "data: " . json_encode($errorData) . "\n\n";
                echo "event: close\n";
                echo "data: {}\n\n";
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        $userMessage = $request->input('message');

        // Track usage for the message
        $usageService->incrementUsage(Auth::user(), 'chat_messages');

        // Get updated usage data after incrementing
        $updatedUsage = $usageService->getUsageSummary(Auth::user());

        return new StreamedResponse(function () use ($chat, $userMessage, $updatedUsage) {
            $this->handleStreamingResponse($chat, $userMessage, $updatedUsage);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    /**
     * Handle the streaming response generation
     */
    private function handleStreamingResponse(Chat $chat, string $userMessage, array $updatedUsage = null): void
    {
        try {
            // Create streaming session
            $sessionId = $this->streamingMessageService->createStreamingSession($chat, $userMessage);

            // Send session started event with updated usage data
            $this->sendSSEEvent('session_started', [
                'session_id' => $sessionId,
                'message' => 'Streaming session initialized',
                'usage' => $updatedUsage
            ]);

            // Get chat messages for context
            $messages = $chat->messages()
                ->orderBy('created_at')
                ->get()
                ->map(function ($message) {
                    return [
                        'role' => $message->role,
                        'content' => $message->content,
                    ];
                })
                ->toArray();

            // Add the new user message
            $messages[] = [
                'role' => 'user',
                'content' => $userMessage,
            ];

            // Get course namespaces for context
            $namespaces = $chat->getEmbeddingNamespaces();

            // Build exam-specific system message
            $systemMessage = $this->buildExamSpecificSystemMessage($chat);

            $chunkCount = 0;
            
            try {
                // Stream AI response with course context
                $this->aiProviderService->streamChatCompletionWithContext(
                    $messages,
                    function (string $chunk) use ($sessionId, &$chunkCount) {
                        $chunkCount++;
                        
                        // Debug logging
                        \Log::info('Sending chunk', [
                            'session_id' => $sessionId,
                            'chunk_number' => $chunkCount,
                            'chunk_length' => strlen($chunk),
                            'chunk_content' => $chunk
                        ]);
                        
                        // Process each chunk through the streaming service
                        $this->streamingMessageService->processStreamChunk($sessionId, $chunk);
                        
                        // Send chunk to client
                        $this->sendSSEEvent('chunk', [
                            'session_id' => $sessionId,
                            'content' => $chunk
                        ]);
                        
                        // Flush output to client immediately
                        if (ob_get_level()) {
                            ob_flush();
                        }
                        flush();
                    },
                    $namespaces,
                    [
                        'chat_id' => $chat->id,
                        'system_message' => $systemMessage
                    ]
                );
                
                // If no chunks were received, use fallback
                if ($chunkCount === 0) {
                    \Log::warning('No chunks received from AI provider, using fallback');
                    throw new \Exception('No streaming chunks received');
                }
                
            } catch (\Exception $e) {
                \Log::error('AI streaming failed, using fallback', ['error' => $e->getMessage()]);
                
                // Fallback response
                $fallbackResponse = "I apologize, but I'm having trouble connecting to my AI service right now. Here's a helpful response: " . 
                    "Based on your message about '{$userMessage}', I can help you with various topics. " .
                    "Please try again in a moment, or feel free to ask me anything else!";
                
                $words = explode(' ', $fallbackResponse);
                foreach ($words as $index => $word) {
                    usleep(100000); // 100ms delay
                    $chunk = $word . ($index < count($words) - 1 ? ' ' : '');
                    
                    // Process each chunk through the streaming service
                    $this->streamingMessageService->processStreamChunk($sessionId, $chunk);
                    
                    // Send chunk to client
                    $this->sendSSEEvent('chunk', [
                        'session_id' => $sessionId,
                        'content' => $chunk
                    ]);
                    
                    // Flush output to client immediately
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                }
            }

            // Finalize the streaming message
            $message = $this->streamingMessageService->finalizeStreamingMessage($sessionId);

            // Send completion event
            $this->sendSSEEvent('completed', [
                'session_id' => $sessionId,
                'message_id' => $message->id,
                'message' => 'Streaming completed successfully'
            ]);

        } catch (\Exception $e) {
            // Send error event
            $this->sendSSEEvent('error', [
                'message' => 'Streaming failed: ' . $e->getMessage(),
                'error_code' => 'STREAMING_ERROR'
            ]);

            // Log the error
            \Log::error('Streaming error', [
                'chat_id' => $chat->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            // Send final event to close connection
            $this->sendSSEEvent('close', ['message' => 'Connection closed']);
        }
    }

    /**
     * Send Server-Sent Event to client
     */
    private function sendSSEEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
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
     * Stop an active streaming session
     */
    public function stopStreaming(Request $request): Response
    {
        if (!Auth::check()) {
            abort(401, 'Unauthorized');
        }

        $request->validate([
            'session_id' => 'required|string',
        ]);

        $sessionId = $request->input('session_id');

        try {
            // Stop the streaming session and save partial content
            $message = $this->streamingMessageService->stopStreamingSession($sessionId, Auth::id());

            return response()->json([
                'success' => true,
                'message_id' => $message->id,
                'message' => 'Streaming stopped successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to stop streaming: ' . $e->getMessage()
            ], 500);
        }
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
}