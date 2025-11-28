<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttemptAnswer extends Model
{
    protected $fillable = [
        'quiz_attempt_id',
        'quiz_question_id',
        'question_order',
        'selected_option',
        'correct_option',
        'is_correct',
        'is_bookmarked',
        'time_spent_seconds',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'is_bookmarked' => 'boolean',
    ];

    /**
     * Get the attempt this answer belongs to
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    /**
     * Get the question this answer is for
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }

    /**
     * Check if the selected answer is correct
     */
    public function checkAnswer(): void
    {
        if ($this->selected_option && $this->correct_option) {
            $this->is_correct = ($this->selected_option === $this->correct_option);
            $this->save();
        }
    }
}
