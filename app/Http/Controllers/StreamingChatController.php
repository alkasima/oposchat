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

        $userMessage = $request->input('message');

        return new StreamedResponse(function () use ($chat, $userMessage) {
            $this->handleStreamingResponse($chat, $userMessage);
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
    private function handleStreamingResponse(Chat $chat, string $userMessage): void
    {
        try {
            // Create streaming session
            $sessionId = $this->streamingMessageService->createStreamingSession($chat, $userMessage);

            // Send session started event
            $this->sendSSEEvent('session_started', [
                'session_id' => $sessionId,
                'message' => 'Streaming session initialized'
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
                    ['chat_id' => $chat->id]
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
}