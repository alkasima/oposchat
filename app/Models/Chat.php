<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function latestMessage(): HasMany
    {
        return $this->hasMany(Message::class)->latest();
    }

    /**
     * Get the streaming sessions for this chat
     */
    public function streamingSessions(): HasMany
    {
        return $this->hasMany(StreamingSession::class);
    }

    /**
     * Get active streaming sessions for this chat
     */
    public function activeStreamingSessions(): HasMany
    {
        return $this->streamingSessions()->where('status', 'active');
    }

    /**
     * Generate a title for the chat based on the first user message
     */
    public function generateTitle(): void
    {
        if ($this->title) {
            return;
        }

        $firstUserMessage = $this->messages()
            ->where('role', 'user')
            ->first();

        if ($firstUserMessage) {
            // Take first 50 characters of the message as title
            $title = substr($firstUserMessage->content, 0, 50);
            if (strlen($firstUserMessage->content) > 50) {
                $title .= '...';
            }
            
            $this->update(['title' => $title]);
        }
    }

    /**
     * Update the last message timestamp
     */
    public function updateLastMessageTime(): void
    {
        $this->update(['last_message_at' => now()]);
    }
}