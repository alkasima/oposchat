<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserQuizStatistic extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'total_quizzes_attempted',
        'total_quizzes_completed',
        'total_questions_answered',
        'total_correct_answers',
        'overall_accuracy',
        'topic_performance',
        'difficulty_performance',
        'common_mistakes',
        'strong_topics',
        'weak_topics',
        'average_time_per_question',
        'last_quiz_at',
    ];

    protected $casts = [
        'topic_performance' => 'array',
        'difficulty_performance' => 'array',
        'common_mistakes' => 'array',
        'strong_topics' => 'array',
        'weak_topics' => 'array',
        'last_quiz_at' => 'datetime',
    ];

    /**
     * Get the user these statistics belong to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the course these statistics are for
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
