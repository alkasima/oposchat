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
        'exam_type',
        'last_message_at',
        'course_id',
        'course_ids',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'course_ids' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
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

    /**
     * Get the namespace for embeddings based on course selection
     */
    public function getEmbeddingNamespace(): ?string
    {
        if ($this->course_id) {
            return $this->course->slug ?? "course:{$this->course_id}";
        }
        
        return null;
    }

    /**
     * Get multiple namespaces for multi-course queries
     */
    public function getEmbeddingNamespaces(): array
    {
        $namespaces = [];
        
        if ($this->course_id) {
            $namespaces[] = $this->course->slug ?? "course:{$this->course_id}";
        }
        
        if ($this->course_ids && is_array($this->course_ids)) {
            $courses = Course::whereIn('id', $this->course_ids)->get();
            foreach ($courses as $course) {
                $namespaces[] = $course->slug ?? "course:{$course->id}";
            }
        }
        
        return array_unique($namespaces);
    }
}