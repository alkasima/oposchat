<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuizQuestion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_id',
        'question_text',
        'explanation',
        'difficulty',
        'topic',
        'tags',
        'metadata',
        'type',
        'generated_by',
        'generated_at',
        'is_active',
    ];

    protected $casts = [
        'tags' => 'array',
        'metadata' => 'array',
        'generated_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the course this question belongs to
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the answer options for this question
     */
    public function options(): HasMany
    {
        return $this->hasMany(QuizQuestionOption::class);
    }

    /**
     * Get the user who generated this question (for AI-generated questions)
     */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Get all attempt answers for this question
     */
    public function attemptAnswers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class);
    }

    /**
     * Get the correct option for this question
     */
    public function getCorrectOption()
    {
        return $this->options()->where('is_correct', true)->first();
    }

    /**
     * Scope: Only active questions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by difficulty
     */
    public function scopeByDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    /**
     * Scope: Filter by topic
     */
    public function scopeByTopic($query, string $topic)
    {
        return $query->where('topic', $topic);
    }

    /**
     * Scope: Repository questions only
     */
    public function scopeRepository($query)
    {
        return $query->where('type', 'repository');
    }

    /**
     * Scope: AI-generated questions only
     */
    public function scopeAiGenerated($query)
    {
        return $query->where('type', 'ai_generated');
    }
}
