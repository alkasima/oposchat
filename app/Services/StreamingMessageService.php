<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Message;
use App\Models\StreamingSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StreamingMessageService
{
    /**
     * Create a new streaming session
     */
    public function createStreamingSession(Chat $chat, string $userMessage): string
    {
        return DB::transaction(function () use ($chat, $userMessage) {
            // First, save the user message
            $userMessageModel = Message::create([
                'chat_id' => $chat->id,
                'role' => 'user',
                'content' => $userMessage,
                'created_at' => now(),
            ]);

            // Create streaming session
            $sessionId = Str::uuid()->toString();
            
            StreamingSession::create([
                'id' => $sessionId,
                'chat_id' => $chat->id,
                'user_id' => Auth::id(),
                'status' => 'active',
                'content_buffer' => '',
                'metadata' => json_encode([
                    'user_message_id' => $userMessageModel->id,
                    'started_at' => now()->toISOString(),
                ]),
                'started_at' => now(),
            ]);

            return $sessionId;
        });
    }

    /**
     * Process incoming stream chunk
     */
    public function processStreamChunk(string $sessionId, string $chunk): void
    {
        $session = StreamingSession::where('id', $sessionId)
            ->where('status', 'active')
            ->first();

        if (!$session) {
            throw new \Exception("Streaming session not found or not active: {$sessionId}");
        }

        // Append chunk to content buffer
        $session->content_buffer .= $chunk;
        $session->save();
    }

    /**
     * Finalize streaming message and save to database
     */
    public function finalizeStreamingMessage(string $sessionId): Message
    {
        return DB::transaction(function () use ($sessionId) {
            $session = StreamingSession::where('id', $sessionId)
                ->where('status', 'active')
                ->first();

            if (!$session) {
                throw new \Exception("Streaming session not found or not active: {$sessionId}");
            }

            // Create the assistant message with the complete content
            $message = Message::create([
                'chat_id' => $session->chat_id,
                'role' => 'assistant',
                'content' => $session->content_buffer,
                'streaming_session_id' => $sessionId,
                'is_streaming' => false,
                'stream_completed_at' => now(),
                'created_at' => now(),
            ]);

            // Update session status
            $session->update([
                'status' => 'completed',
                'completed_at' => now(),
                'metadata' => json_encode(array_merge(
                    json_decode($session->metadata, true) ?? [],
                    [
                        'message_id' => $message->id,
                        'completed_at' => now()->toISOString(),
                        'final_content_length' => strlen($session->content_buffer),
                    ]
                )),
            ]);

            return $message;
        });
    }

    /**
     * Stop an active streaming session
     */
    public function stopStreamingSession(string $sessionId, int $userId): Message
    {
        return DB::transaction(function () use ($sessionId, $userId) {
            $session = StreamingSession::where('id', $sessionId)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->first();

            if (!$session) {
                throw new \Exception("Streaming session not found, not active, or access denied: {$sessionId}");
            }

            // Create message with partial content
            $message = Message::create([
                'chat_id' => $session->chat_id,
                'role' => 'assistant',
                'content' => $session->content_buffer,
                'streaming_session_id' => $sessionId,
                'is_streaming' => false,
                'stream_completed_at' => now(),
                'created_at' => now(),
            ]);

            // Update session status
            $session->update([
                'status' => 'stopped',
                'completed_at' => now(),
                'metadata' => json_encode(array_merge(
                    json_decode($session->metadata, true) ?? [],
                    [
                        'message_id' => $message->id,
                        'stopped_at' => now()->toISOString(),
                        'stopped_by_user' => true,
                        'partial_content_length' => strlen($session->content_buffer),
                    ]
                )),
            ]);

            return $message;
        });
    }

    /**
     * Clean up abandoned or expired streaming sessions
     */
    public function cleanupAbandonedSessions(): int
    {
        $cutoffTime = now()->subMinutes(30); // Sessions older than 30 minutes

        return DB::transaction(function () use ($cutoffTime) {
            $abandonedSessions = StreamingSession::where('status', 'active')
                ->where('started_at', '<', $cutoffTime)
                ->get();

            $cleanedCount = 0;

            foreach ($abandonedSessions as $session) {
                try {
                    // Create message with whatever content was buffered
                    if (!empty($session->content_buffer)) {
                        Message::create([
                            'chat_id' => $session->chat_id,
                            'role' => 'assistant',
                            'content' => $session->content_buffer,
                            'streaming_session_id' => $session->id,
                            'is_streaming' => false,
                            'stream_completed_at' => now(),
                            'created_at' => now(),
                        ]);
                    }

                    // Mark session as error/abandoned
                    $session->update([
                        'status' => 'error',
                        'completed_at' => now(),
                        'metadata' => json_encode(array_merge(
                            json_decode($session->metadata, true) ?? [],
                            [
                                'abandoned_at' => now()->toISOString(),
                                'cleanup_reason' => 'session_timeout',
                                'partial_content_length' => strlen($session->content_buffer),
                            ]
                        )),
                    ]);

                    $cleanedCount++;

                } catch (\Exception $e) {
                    \Log::error('Failed to cleanup streaming session', [
                        'session_id' => $session->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $cleanedCount;
        });
    }

    /**
     * Get streaming session by ID with user authorization
     */
    public function getStreamingSession(string $sessionId, int $userId): ?StreamingSession
    {
        return StreamingSession::where('id', $sessionId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Handle streaming session errors
     */
    public function handleStreamingError(string $sessionId, string $errorMessage, ?\Exception $exception = null): void
    {
        try {
            $session = StreamingSession::where('id', $sessionId)
                ->where('status', 'active')
                ->first();

            if ($session) {
                // Save partial content if any exists
                if (!empty($session->content_buffer)) {
                    Message::create([
                        'chat_id' => $session->chat_id,
                        'role' => 'assistant',
                        'content' => $session->content_buffer . "\n\n[Error: Streaming was interrupted]",
                        'streaming_session_id' => $sessionId,
                        'is_streaming' => false,
                        'stream_completed_at' => now(),
                        'created_at' => now(),
                    ]);
                }

                // Update session with error status
                $session->update([
                    'status' => 'error',
                    'completed_at' => now(),
                    'metadata' => json_encode(array_merge(
                        json_decode($session->metadata, true) ?? [],
                        [
                            'error_at' => now()->toISOString(),
                            'error_message' => $errorMessage,
                            'error_trace' => $exception ? $exception->getTraceAsString() : null,
                            'partial_content_length' => strlen($session->content_buffer),
                        ]
                    )),
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to handle streaming error', [
                'session_id' => $sessionId,
                'original_error' => $errorMessage,
                'handling_error' => $e->getMessage(),
            ]);
        }
    }
}