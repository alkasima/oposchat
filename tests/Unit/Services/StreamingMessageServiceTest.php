<?php

namespace Tests\Unit\Services;

use App\Models\Chat;
use App\Models\Message;
use App\Models\StreamingSession;
use App\Models\User;
use App\Services\StreamingMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreamingMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private StreamingMessageService $service;
    private User $user;
    private Chat $chat;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new StreamingMessageService();
        $this->user = User::factory()->create();
        $this->chat = Chat::factory()->create(['user_id' => $this->user->id]);
        
        $this->actingAs($this->user);
    }

    public function test_create_streaming_session()
    {
        $userMessage = 'Hello, how are you?';
        
        $sessionId = $this->service->createStreamingSession($this->chat, $userMessage);
        
        $this->assertNotEmpty($sessionId);
        
        // Check that user message was created
        $userMessageModel = Message::where('chat_id', $this->chat->id)
            ->where('role', 'user')
            ->where('content', $userMessage)
            ->first();
        $this->assertNotNull($userMessageModel);
        
        // Check that streaming session was created
        $session = StreamingSession::find($sessionId);
        $this->assertNotNull($session);
        $this->assertEquals($this->chat->id, $session->chat_id);
        $this->assertEquals($this->user->id, $session->user_id);
        $this->assertEquals('active', $session->status);
        $this->assertEquals('', $session->content_buffer);
    }

    public function test_process_stream_chunk()
    {
        $sessionId = $this->service->createStreamingSession($this->chat, 'Test message');
        
        $this->service->processStreamChunk($sessionId, 'Hello ');
        $this->service->processStreamChunk($sessionId, 'world!');
        
        $session = StreamingSession::find($sessionId);
        $this->assertEquals('Hello world!', $session->content_buffer);
    }

    public function test_process_stream_chunk_with_invalid_session()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Streaming session not found or not active');
        
        $this->service->processStreamChunk('invalid-session-id', 'test chunk');
    }

    public function test_finalize_streaming_message()
    {
        $sessionId = $this->service->createStreamingSession($this->chat, 'Test message');
        
        $this->service->processStreamChunk($sessionId, 'Hello ');
        $this->service->processStreamChunk($sessionId, 'world!');
        
        $message = $this->service->finalizeStreamingMessage($sessionId);
        
        $this->assertNotNull($message);
        $this->assertEquals('Hello world!', $message->content);
        $this->assertEquals('assistant', $message->role);
        $this->assertEquals($this->chat->id, $message->chat_id);
        $this->assertEquals($sessionId, $message->streaming_session_id);
        $this->assertFalse($message->is_streaming);
        $this->assertNotNull($message->stream_completed_at);
        
        // Check that session is marked as completed
        $session = StreamingSession::find($sessionId);
        $this->assertEquals('completed', $session->status);
        $this->assertNotNull($session->completed_at);
    }

    public function test_stop_streaming_session()
    {
        $sessionId = $this->service->createStreamingSession($this->chat, 'Test message');
        
        $this->service->processStreamChunk($sessionId, 'Partial ');
        $this->service->processStreamChunk($sessionId, 'content');
        
        $message = $this->service->stopStreamingSession($sessionId, $this->user->id);
        
        $this->assertNotNull($message);
        $this->assertEquals('Partial content', $message->content);
        $this->assertEquals('assistant', $message->role);
        $this->assertFalse($message->is_streaming);
        
        // Check that session is marked as stopped
        $session = StreamingSession::find($sessionId);
        $this->assertEquals('stopped', $session->status);
        $this->assertNotNull($session->completed_at);
    }

    public function test_stop_streaming_session_with_unauthorized_user()
    {
        $sessionId = $this->service->createStreamingSession($this->chat, 'Test message');
        $otherUser = User::factory()->create();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Streaming session not found, not active, or access denied');
        
        $this->service->stopStreamingSession($sessionId, $otherUser->id);
    }

    public function test_cleanup_abandoned_sessions()
    {
        // Create an old session
        $oldSession = StreamingSession::create([
            'id' => 'old-session-id',
            'chat_id' => $this->chat->id,
            'user_id' => $this->user->id,
            'status' => 'active',
            'content_buffer' => 'Some partial content',
            'metadata' => json_encode([]),
            'started_at' => now()->subHours(1), // 1 hour ago
        ]);
        
        // Create a recent session
        $recentSession = StreamingSession::create([
            'id' => 'recent-session-id',
            'chat_id' => $this->chat->id,
            'user_id' => $this->user->id,
            'status' => 'active',
            'content_buffer' => 'Recent content',
            'metadata' => json_encode([]),
            'started_at' => now()->subMinutes(5), // 5 minutes ago
        ]);
        
        $cleanedCount = $this->service->cleanupAbandonedSessions();
        
        $this->assertEquals(1, $cleanedCount);
        
        // Check that old session was cleaned up
        $oldSession->refresh();
        $this->assertEquals('error', $oldSession->status);
        $this->assertNotNull($oldSession->completed_at);
        
        // Check that recent session is still active
        $recentSession->refresh();
        $this->assertEquals('active', $recentSession->status);
        
        // Check that a message was created for the abandoned session
        $abandonedMessage = Message::where('streaming_session_id', 'old-session-id')->first();
        $this->assertNotNull($abandonedMessage);
        $this->assertEquals('Some partial content', $abandonedMessage->content);
    }

    public function test_get_streaming_session()
    {
        $sessionId = $this->service->createStreamingSession($this->chat, 'Test message');
        
        $session = $this->service->getStreamingSession($sessionId, $this->user->id);
        
        $this->assertNotNull($session);
        $this->assertEquals($sessionId, $session->id);
        $this->assertEquals($this->user->id, $session->user_id);
    }

    public function test_get_streaming_session_with_unauthorized_user()
    {
        $sessionId = $this->service->createStreamingSession($this->chat, 'Test message');
        $otherUser = User::factory()->create();
        
        $session = $this->service->getStreamingSession($sessionId, $otherUser->id);
        
        $this->assertNull($session);
    }

    public function test_handle_streaming_error()
    {
        $sessionId = $this->service->createStreamingSession($this->chat, 'Test message');
        
        $this->service->processStreamChunk($sessionId, 'Some content before error');
        
        $this->service->handleStreamingError($sessionId, 'Connection timeout', new \Exception('Test exception'));
        
        // Check that session is marked as error
        $session = StreamingSession::find($sessionId);
        $this->assertEquals('error', $session->status);
        $this->assertNotNull($session->completed_at);
        
        // Check that a message was created with error indication
        $errorMessage = Message::where('streaming_session_id', $sessionId)->first();
        $this->assertNotNull($errorMessage);
        $this->assertStringContainsString('Some content before error', $errorMessage->content);
        $this->assertStringContainsString('[Error: Streaming was interrupted]', $errorMessage->content);
    }
}