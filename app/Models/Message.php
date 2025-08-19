<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'role',
        'content',
        'metadata',
        'streaming_session_id',
        'is_streaming',
        'stream_completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_streaming' => 'boolean',
        'stream_completed_at' => 'datetime',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    /**
     * Get the streaming session that owns the message
     */
    public function streamingSession(): BelongsTo
    {
        return $this->belongsTo(StreamingSession::class, 'streaming_session_id', 'id');
    }

    /**
     * Check if this message is from the user
     */
    public function isFromUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check if this message is from the assistant
     */
    public function isFromAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    /**
     * Get formatted timestamp for display
     */
    public function getFormattedTimeAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Check if this message is currently streaming
     */
    public function isStreaming(): bool
    {
        return $this->is_streaming;
    }

    /**
     * Check if this message was created via streaming
     */
    public function wasStreamed(): bool
    {
        return !is_null($this->streaming_session_id);
    }

    /**
     * Check if streaming is completed for this message
     */
    public function isStreamCompleted(): bool
    {
        return !is_null($this->stream_completed_at);
    }
}