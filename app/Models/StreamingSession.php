<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StreamingSession extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'chat_id',
        'user_id',
        'status',
        'content_buffer',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the chat that owns the streaming session
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    /**
     * Get the user that owns the streaming session
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the messages associated with this streaming session
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'streaming_session_id', 'id');
    }

    /**
     * Check if the streaming session is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the streaming session is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the streaming session was stopped
     */
    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    /**
     * Check if the streaming session has an error
     */
    public function hasError(): bool
    {
        return $this->status === 'error';
    }

    /**
     * Mark the streaming session as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the streaming session as stopped
     */
    public function markAsStopped(): void
    {
        $this->update([
            'status' => 'stopped',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the streaming session as error
     */
    public function markAsError(): void
    {
        $this->update([
            'status' => 'error',
            'completed_at' => now(),
        ]);
    }

    /**
     * Append content to the buffer
     */
    public function appendContent(string $content): void
    {
        $this->update([
            'content_buffer' => ($this->content_buffer ?? '') . $content,
        ]);
    }
}
