<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'quiz_id',
        'course_id',
        'quiz_type',
        'settings',
        'total_questions',
        'correct_answers',
        'incorrect_answers',
        'unanswered',
        'score_percentage',
        'time_spent_seconds',
        'started_at',
        'completed_at',
        'status',
    ];

    protected $casts = [
        'settings' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user who took this quiz
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the quiz this attempt is for
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * Get the course this attempt is for
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get all answers for this attempt
     */
    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class);
    }

    /**
     * Calculate and update the score for this attempt
     */
    public function calculateScore(): void
    {
        $total = $this->answers()->count();
        $correct = $this->answers()->where('is_correct', true)->count();
        $incorrect = $this->answers()->where('is_correct', false)->whereNotNull('selected_option')->count();
        $unanswered = $total - $correct - $incorrect;

        $this->update([
            'total_questions' => $total,
            'correct_answers' => $correct,
            'incorrect_answers' => $incorrect,
            'unanswered' => $unanswered,
            'score_percentage' => $total > 0 ? round(($correct / $total) * 100, 2) : 0,
        ]);
    }

    /**
     * Mark attempt as completed
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->calculateScore();

        // Update user statistics
        $statisticsService = app(\App\Services\QuizStatisticsService::class);
        $statisticsService->updateUserStatistics($this->user_id, $this->course_id);
    }

    /**
     * Scope: Only completed attempts
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Only in-progress attempts
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }
}
