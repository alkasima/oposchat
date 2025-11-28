<?php

namespace App\Services;

use App\Models\User;
use App\Models\Course;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\UserQuizStatistic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuizStatisticsService
{
    /**
     * Update user statistics after completing a quiz
     */
    public function updateUserStatistics(int $userId, int $courseId): void
    {
        try {
            // Get or create statistics record
            $stats = UserQuizStatistic::firstOrCreate(
                [
                    'user_id' => $userId,
                    'course_id' => $courseId,
                ],
                [
                    'total_quizzes_attempted' => 0,
                    'total_quizzes_completed' => 0,
                    'total_questions_answered' => 0,
                    'total_correct_answers' => 0,
                    'overall_accuracy' => 0,
                ]
            );

            // Get all completed attempts for this user and course
            $completedAttempts = QuizAttempt::where('user_id', $userId)
                ->where('course_id', $courseId)
                ->where('status', 'completed')
                ->get();

            // Calculate basic stats
            $totalAttempts = QuizAttempt::where('user_id', $userId)
                ->where('course_id', $courseId)
                ->count();

            $totalCompleted = $completedAttempts->count();
            $totalQuestions = $completedAttempts->sum('total_questions');
            $totalCorrect = $completedAttempts->sum('correct_answers');
            $overallAccuracy = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100, 2) : 0;

            // Calculate topic performance
            $topicPerformance = $this->calculateTopicPerformance($userId, $courseId);

            // Calculate difficulty performance
            $difficultyPerformance = $this->calculateDifficultyPerformance($userId, $courseId);

            // Find common mistakes (questions answered incorrectly multiple times)
            $commonMistakes = $this->findCommonMistakes($userId, $courseId);

            // Identify strong and weak topics
            $strongTopics = [];
            $weakTopics = [];
            
            foreach ($topicPerformance as $topic => $performance) {
                $accuracy = $performance['total'] > 0 ? ($performance['correct'] / $performance['total']) * 100 : 0;
                
                if ($accuracy >= 80) {
                    $strongTopics[] = $topic;
                } elseif ($accuracy < 60) {
                    $weakTopics[] = $topic;
                }
            }

            // Calculate average time per question
            $totalTime = $completedAttempts->sum('time_spent_seconds');
            $avgTimePerQuestion = $totalQuestions > 0 ? round($totalTime / $totalQuestions, 2) : null;

            // Get last quiz date
            $lastQuiz = $completedAttempts->max('completed_at');

            // Update statistics
            $stats->update([
                'total_quizzes_attempted' => $totalAttempts,
                'total_quizzes_completed' => $totalCompleted,
                'total_questions_answered' => $totalQuestions,
                'total_correct_answers' => $totalCorrect,
                'overall_accuracy' => $overallAccuracy,
                'topic_performance' => $topicPerformance,
                'difficulty_performance' => $difficultyPerformance,
                'common_mistakes' => $commonMistakes,
                'strong_topics' => $strongTopics,
                'weak_topics' => $weakTopics,
                'average_time_per_question' => $avgTimePerQuestion,
                'last_quiz_at' => $lastQuiz,
            ]);

            Log::info('Updated quiz statistics', [
                'user_id' => $userId,
                'course_id' => $courseId,
                'overall_accuracy' => $overallAccuracy,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update quiz statistics', [
                'user_id' => $userId,
                'course_id' => $courseId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calculate performance by topic
     */
    private function calculateTopicPerformance(int $userId, int $courseId): array
    {
        $answers = QuizAttemptAnswer::whereHas('attempt', function ($query) use ($userId, $courseId) {
            $query->where('user_id', $userId)
                ->where('course_id', $courseId)
                ->where('status', 'completed');
        })
        ->with('question:id,topic')
        ->whereNotNull('selected_option')
        ->get();

        $performance = [];

        foreach ($answers as $answer) {
            $topic = $answer->question->topic ?? 'Unknown';
            
            if (!isset($performance[$topic])) {
                $performance[$topic] = [
                    'correct' => 0,
                    'total' => 0,
                ];
            }

            $performance[$topic]['total']++;
            if ($answer->is_correct) {
                $performance[$topic]['correct']++;
            }
        }

        return $performance;
    }

    /**
     * Calculate performance by difficulty
     */
    private function calculateDifficultyPerformance(int $userId, int $courseId): array
    {
        $answers = QuizAttemptAnswer::whereHas('attempt', function ($query) use ($userId, $courseId) {
            $query->where('user_id', $userId)
                ->where('course_id', $courseId)
                ->where('status', 'completed');
        })
        ->with('question:id,difficulty')
        ->whereNotNull('selected_option')
        ->get();

        $performance = [
            'easy' => ['correct' => 0, 'total' => 0],
            'medium' => ['correct' => 0, 'total' => 0],
            'hard' => ['correct' => 0, 'total' => 0],
        ];

        foreach ($answers as $answer) {
            $difficulty = $answer->question->difficulty ?? 'medium';
            
            $performance[$difficulty]['total']++;
            if ($answer->is_correct) {
                $performance[$difficulty]['correct']++;
            }
        }

        // Convert to percentages
        $result = [];
        foreach ($performance as $difficulty => $data) {
            $result[$difficulty] = $data['total'] > 0 
                ? round(($data['correct'] / $data['total']) * 100, 2) 
                : 0;
        }

        return $result;
    }

    /**
     * Find questions that are frequently answered incorrectly
     */
    private function findCommonMistakes(int $userId, int $courseId, int $limit = 10): array
    {
        $incorrectAnswers = QuizAttemptAnswer::whereHas('attempt', function ($query) use ($userId, $courseId) {
            $query->where('user_id', $userId)
                ->where('course_id', $courseId)
                ->where('status', 'completed');
        })
        ->where('is_correct', false)
        ->whereNotNull('selected_option')
        ->select('quiz_question_id', DB::raw('count(*) as mistake_count'))
        ->groupBy('quiz_question_id')
        ->orderBy('mistake_count', 'desc')
        ->limit($limit)
        ->pluck('mistake_count', 'quiz_question_id')
        ->toArray();

        return $incorrectAnswers;
    }

    /**
     * Get overall statistics for a user and course
     */
    public function getOverallStats(int $userId, int $courseId): ?UserQuizStatistic
    {
        return UserQuizStatistic::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();
    }

    /**
     * Get topic performance breakdown
     */
    public function getTopicPerformance(int $userId, int $courseId): array
    {
        $stats = $this->getOverallStats($userId, $courseId);
        
        if (!$stats || !$stats->topic_performance) {
            return [];
        }

        // Add accuracy percentage to each topic
        $performance = [];
        foreach ($stats->topic_performance as $topic => $data) {
            $performance[$topic] = [
                'correct' => $data['correct'],
                'total' => $data['total'],
                'accuracy' => $data['total'] > 0 
                    ? round(($data['correct'] / $data['total']) * 100, 2) 
                    : 0,
            ];
        }

        // Sort by accuracy descending
        uasort($performance, function ($a, $b) {
            return $b['accuracy'] <=> $a['accuracy'];
        });

        return $performance;
    }

    /**
     * Get recommendations based on performance
     */
    public function getRecommendations(int $userId, int $courseId): array
    {
        $stats = $this->getOverallStats($userId, $courseId);
        
        if (!$stats) {
            return [
                'message' => 'Take your first quiz to get personalized recommendations!',
                'recommendations' => [],
            ];
        }

        $recommendations = [];

        // Recommend practicing weak topics
        if (!empty($stats->weak_topics)) {
            $recommendations[] = [
                'type' => 'weak_topics',
                'priority' => 'high',
                'title' => 'Focus on Weak Topics',
                'description' => 'Practice these topics to improve your overall score',
                'topics' => $stats->weak_topics,
            ];
        }

        // Recommend difficulty adjustment
        if (isset($stats->difficulty_performance)) {
            $hardAccuracy = $stats->difficulty_performance['hard'] ?? 0;
            $mediumAccuracy = $stats->difficulty_performance['medium'] ?? 0;
            $easyAccuracy = $stats->difficulty_performance['easy'] ?? 0;

            if ($hardAccuracy < 50 && $mediumAccuracy > 70) {
                $recommendations[] = [
                    'type' => 'difficulty',
                    'priority' => 'medium',
                    'title' => 'Challenge Yourself',
                    'description' => 'You\'re doing well on medium questions. Try more hard questions to improve further.',
                ];
            } elseif ($easyAccuracy < 70) {
                $recommendations[] = [
                    'type' => 'difficulty',
                    'priority' => 'high',
                    'title' => 'Build Foundation',
                    'description' => 'Focus on easy questions to build a strong foundation before moving to harder topics.',
                ];
            }
        }

        // Recommend time management
        if ($stats->average_time_per_question) {
            if ($stats->average_time_per_question > 120) { // More than 2 minutes per question
                $recommendations[] = [
                    'type' => 'time_management',
                    'priority' => 'medium',
                    'title' => 'Improve Speed',
                    'description' => 'Try to answer questions more quickly. Practice with timed quizzes.',
                ];
            }
        }

        // Celebrate strong topics
        if (!empty($stats->strong_topics)) {
            $recommendations[] = [
                'type' => 'strong_topics',
                'priority' => 'low',
                'title' => 'Great Job!',
                'description' => 'You\'re excelling in these topics. Keep up the good work!',
                'topics' => $stats->strong_topics,
            ];
        }

        return [
            'message' => 'Based on your performance',
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Export statistics in various formats
     */
    public function exportStatistics(int $userId, int $courseId, string $format = 'json'): array
    {
        $stats = $this->getOverallStats($userId, $courseId);
        
        if (!$stats) {
            return [];
        }

        $data = [
            'user_id' => $userId,
            'course_id' => $courseId,
            'overall_accuracy' => $stats->overall_accuracy,
            'total_quizzes_completed' => $stats->total_quizzes_completed,
            'total_questions_answered' => $stats->total_questions_answered,
            'total_correct_answers' => $stats->total_correct_answers,
            'topic_performance' => $this->getTopicPerformance($userId, $courseId),
            'difficulty_performance' => $stats->difficulty_performance,
            'strong_topics' => $stats->strong_topics,
            'weak_topics' => $stats->weak_topics,
            'average_time_per_question' => $stats->average_time_per_question,
            'last_quiz_at' => $stats->last_quiz_at?->toDateTimeString(),
        ];

        return $data;
    }
}
