<?php

namespace App\Services;

use App\Models\User;
use App\Models\Course;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\UserQuizStatistic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PersonalizationEngineService
{
    private QuizStatisticsService $statisticsService;
    
    // Performance thresholds
    private const WEAK_TOPIC_THRESHOLD = 0.60; // Below 60% is weak
    private const STRONG_TOPIC_THRESHOLD = 0.80; // Above 80% is strong
    private const MIN_ATTEMPTS_FOR_ANALYSIS = 3; // Minimum attempts to analyze
    
    public function __construct(QuizStatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }
    
    /**
     * Get personalized quiz recommendations for a user
     *
     * @param int $userId User ID
     * @param int $courseId Course ID
     * @return array Recommendations with topics, difficulty, and insights
     */
    public function getRecommendations(int $userId, int $courseId): array
    {
        // Get user statistics
        $stats = $this->statisticsService->getOverallStats($userId, $courseId);
        $topicPerformance = $this->statisticsService->getTopicPerformance($userId, $courseId);
        
        // Check if user has enough data
        $totalAttempts = QuizAttempt::where('user_id', $userId)
            ->whereHas('quiz', function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })
            ->where('status', 'completed')
            ->count();
        
        if ($totalAttempts < self::MIN_ATTEMPTS_FOR_ANALYSIS) {
            return [
                'has_sufficient_data' => false,
                'message' => 'Complete at least ' . self::MIN_ATTEMPTS_FOR_ANALYSIS . ' quizzes to unlock personalized recommendations.',
                'attempts_needed' => self::MIN_ATTEMPTS_FOR_ANALYSIS - $totalAttempts,
                'general_recommendations' => $this->getGeneralRecommendations()
            ];
        }
        
        // Analyze performance
        $weakTopics = $this->identifyWeakTopics($topicPerformance);
        $strongTopics = $this->identifyStrongTopics($topicPerformance);
        $suggestedDifficulty = $this->determineSuggestedDifficulty($stats);
        $commonMistakes = $this->statisticsService->findCommonMistakes($userId, $courseId, 5);
        
        // Build study plan
        $studyPlan = $this->buildStudyPlan($weakTopics, $strongTopics, $stats);
        
        // Generate next steps
        $nextSteps = $this->generateNextSteps($weakTopics, $strongTopics, $stats, $topicPerformance);
        
        return [
            'has_sufficient_data' => true,
            'overall_accuracy' => $stats->accuracy ?? 0,
            'total_attempts' => $totalAttempts,
            'weak_topics' => $weakTopics,
            'strong_topics' => $strongTopics,
            'suggested_difficulty' => $suggestedDifficulty,
            'common_mistakes' => $commonMistakes,
            'study_plan' => $studyPlan,
            'next_steps' => $nextSteps,
            'performance_trend' => $this->getPerformanceTrend($userId, $courseId),
        ];
    }
    
    /**
     * Generate adaptive quiz configuration based on user performance
     *
     * @param int $userId User ID
     * @param int $courseId Course ID
     * @param array $preferences User preferences (optional overrides)
     * @return array Quiz configuration optimized for learning
     */
    public function generateAdaptiveQuizConfig(int $userId, int $courseId, array $preferences = []): array
    {
        $recommendations = $this->getRecommendations($userId, $courseId);
        
        if (!$recommendations['has_sufficient_data']) {
            // Default configuration for new users
            return [
                'question_count' => $preferences['question_count'] ?? 10,
                'difficulty' => $preferences['difficulty'] ?? 'medium',
                'topics' => $preferences['topics'] ?? [],
                'shuffle_questions' => true,
                'shuffle_options' => true,
                'focus_areas' => [],
                'is_adaptive' => false,
            ];
        }
        
        // Build adaptive configuration
        $config = [
            'question_count' => $preferences['question_count'] ?? 10,
            'difficulty' => $preferences['difficulty'] ?? $recommendations['suggested_difficulty'],
            'topics' => $preferences['topics'] ?? [],
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'focus_areas' => $recommendations['weak_topics'],
            'is_adaptive' => true,
        ];
        
        // If focusing on weak areas, adjust question distribution
        if (empty($config['topics']) && !empty($recommendations['weak_topics'])) {
            $config['topics'] = array_slice($recommendations['weak_topics'], 0, 3);
            $config['adaptive_reason'] = 'Focusing on your weakest topics for maximum improvement';
        }
        
        return $config;
    }
    
    /**
     * Get performance trend over time
     */
    private function getPerformanceTrend(int $userId, int $courseId): array
    {
        $recentAttempts = QuizAttempt::where('user_id', $userId)
            ->whereHas('quiz', function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->get(['score', 'total_questions', 'completed_at']);
        
        if ($recentAttempts->isEmpty()) {
            return ['trend' => 'insufficient_data', 'message' => 'Not enough data'];
        }
        
        $accuracies = $recentAttempts->map(function ($attempt) {
            return $attempt->total_questions > 0 
                ? ($attempt->score / $attempt->total_questions) * 100 
                : 0;
        })->values()->toArray();
        
        // Reverse to get chronological order
        $accuracies = array_reverse($accuracies);
        
        // Simple linear regression to determine trend
        if (count($accuracies) >= 3) {
            $firstHalf = array_slice($accuracies, 0, ceil(count($accuracies) / 2));
            $secondHalf = array_slice($accuracies, floor(count($accuracies) / 2));
            
            $avgFirst = array_sum($firstHalf) / count($firstHalf);
            $avgSecond = array_sum($secondHalf) / count($secondHalf);
            
            $difference = $avgSecond - $avgFirst;
            
            if ($difference > 5) {
                $trend = 'improving';
                $message = 'Great progress! Your scores are improving.';
            } elseif ($difference < -5) {
                $trend = 'declining';
                $message = 'Your scores are declining. Consider reviewing weak topics.';
            } else {
                $trend = 'stable';
                $message = 'Your performance is consistent.';
            }
        } else {
            $trend = 'insufficient_data';
            $message = 'Complete more quizzes to see your trend.';
        }
        
        return [
            'trend' => $trend,
            'message' => $message,
            'recent_scores' => $accuracies,
        ];
    }
    
    /**
     * Identify weak topics that need practice
     */
    private function identifyWeakTopics(array $topicPerformance): array
    {
        $weakTopics = [];
        
        foreach ($topicPerformance as $topic => $performance) {
            if ($performance['accuracy'] < self::WEAK_TOPIC_THRESHOLD && $performance['total_questions'] >= 3) {
                $weakTopics[] = $topic;
            }
        }
        
        // Sort by accuracy (weakest first)
        usort($weakTopics, function ($a, $b) use ($topicPerformance) {
            return $topicPerformance[$a]['accuracy'] <=> $topicPerformance[$b]['accuracy'];
        });
        
        return $weakTopics;
    }
    
    /**
     * Identify strong topics
     */
    private function identifyStrongTopics(array $topicPerformance): array
    {
        $strongTopics = [];
        
        foreach ($topicPerformance as $topic => $performance) {
            if ($performance['accuracy'] >= self::STRONG_TOPIC_THRESHOLD && $performance['total_questions'] >= 3) {
                $strongTopics[] = $topic;
            }
        }
        
        // Sort by accuracy (strongest first)
        usort($strongTopics, function ($a, $b) use ($topicPerformance) {
            return $topicPerformance[$b]['accuracy'] <=> $topicPerformance[$a]['accuracy'];
        });
        
        return $strongTopics;
    }
    
    /**
     * Determine suggested difficulty based on overall performance
     */
    private function determineSuggestedDifficulty($stats): string
    {
        $accuracy = $stats->accuracy ?? 0;
        
        if ($accuracy >= 85) {
            return 'hard';
        } elseif ($accuracy >= 70) {
            return 'medium';
        } else {
            return 'easy';
        }
    }
    
    /**
     * Build a personalized study plan
     */
    private function buildStudyPlan(array $weakTopics, array $strongTopics, $stats): array
    {
        $plan = [];
        
        // Phase 1: Address weaknesses
        if (!empty($weakTopics)) {
            $plan[] = [
                'phase' => 1,
                'title' => 'Strengthen Weak Areas',
                'description' => 'Focus on topics where you need the most improvement',
                'topics' => array_slice($weakTopics, 0, 3),
                'recommended_difficulty' => 'easy',
                'recommended_duration' => '1-2 weeks',
            ];
        }
        
        // Phase 2: Mixed practice
        $plan[] = [
            'phase' => 2,
            'title' => 'Mixed Practice',
            'description' => 'Combine weak and strong topics to reinforce learning',
            'topics' => array_merge(
                array_slice($weakTopics, 0, 2),
                array_slice($strongTopics, 0, 2)
            ),
            'recommended_difficulty' => 'medium',
            'recommended_duration' => '1 week',
        ];
        
        // Phase 3: Challenge yourself
        if (!empty($strongTopics)) {
            $plan[] = [
                'phase' => 3,
                'title' => 'Advanced Challenge',
                'description' => 'Test your mastery with harder questions on strong topics',
                'topics' => array_slice($strongTopics, 0, 3),
                'recommended_difficulty' => 'hard',
                'recommended_duration' => 'Ongoing',
            ];
        }
        
        return $plan;
    }
    
    /**
     * Generate actionable next steps
     */
    private function generateNextSteps(array $weakTopics, array $strongTopics, $stats, array $topicPerformance): array
    {
        $steps = [];
        
        $accuracy = $stats->accuracy ?? 0;
        
        // Step 1: Based on overall performance
        if ($accuracy < 60) {
            $steps[] = [
                'priority' => 'high',
                'action' => 'Review fundamentals',
                'description' => 'Your overall accuracy is below 60%. Start with easier questions to build confidence.',
                'suggested_quiz' => [
                    'difficulty' => 'easy',
                    'topics' => !empty($weakTopics) ? array_slice($weakTopics, 0, 2) : [],
                ]
            ];
        } elseif ($accuracy < 75) {
            $steps[] = [
                'priority' => 'medium',
                'action' => 'Practice weak topics',
                'description' => 'Focus on improving your understanding of challenging topics.',
                'suggested_quiz' => [
                    'difficulty' => 'medium',
                    'topics' => !empty($weakTopics) ? array_slice($weakTopics, 0, 3) : [],
                ]
            ];
        } else {
            $steps[] = [
                'priority' => 'low',
                'action' => 'Maintain excellence',
                'description' => 'Great performance! Challenge yourself with harder questions.',
                'suggested_quiz' => [
                    'difficulty' => 'hard',
                    'topics' => !empty($strongTopics) ? array_slice($strongTopics, 0, 3) : [],
                ]
            ];
        }
        
        // Step 2: Focus on weakest topic if applicable
        if (!empty($weakTopics)) {
            $weakestTopic = $weakTopics[0];
            $performance = $topicPerformance[$weakestTopic] ?? null;
            
            if ($performance) {
                $steps[] = [
                    'priority' => 'high',
                    'action' => "Master '{$weakestTopic}'",
                    'description' => sprintf(
                        'This is your weakest topic (%.1f%% accuracy). Dedicate focused practice here.',
                        $performance['accuracy']
                    ),
                    'suggested_quiz' => [
                        'difficulty' => 'easy',
                        'topics' => [$weakestTopic],
                        'question_count' => 10,
                    ]
                ];
            }
        }
        
        // Step 3: AI-generated practice
        $steps[] = [
            'priority' => 'medium',
            'action' => 'Try AI-generated questions',
            'description' => 'Generate fresh, personalized questions targeting your weak areas.',
            'suggested_quiz' => [
                'type' => 'ai_generated',
                'difficulty' => $this->determineSuggestedDifficulty($stats),
                'topics' => !empty($weakTopics) ? array_slice($weakTopics, 0, 3) : [],
            ]
        ];
        
        return $steps;
    }
    
    /**
     * Get general recommendations for new users
     */
    private function getGeneralRecommendations(): array
    {
        return [
            [
                'title' => 'Start with the basics',
                'description' => 'Begin with easy questions to familiarize yourself with the exam format.',
                'action' => 'Take a quiz with 10 easy questions on any topic.'
            ],
            [
                'title' => 'Explore all topics',
                'description' => 'Try questions from different topics to identify your strengths and weaknesses.',
                'action' => 'Take mixed-topic quizzes to build a complete performance profile.'
            ],
            [
                'title' => 'Review explanations',
                'description' => 'Always read the explanations for both correct and incorrect answers.',
                'action' => 'Focus on understanding the reasoning behind each answer.'
            ],
        ];
    }
    
    /**
     * Calculate similarity between two quiz attempts for "similar test" feature
     *
     * @param QuizAttempt $attempt The reference attempt
     * @return array Configuration for a similar quiz
     */
    public function generateSimilarQuizConfig(QuizAttempt $attempt): array
    {
        $quiz = $attempt->quiz;
        
        // Get topics from the attempt
        $answeredQuestions = $attempt->answers()->with('question')->get();
        $topics = $answeredQuestions->pluck('question.topic')->unique()->filter()->values()->toArray();
        
        // Calculate average difficulty based on questions that were answered incorrectly
        $incorrectAnswers = $answeredQuestions->filter(function ($answer) {
            return !$answer->is_correct;
        });
        
        $focusTopics = $incorrectAnswers->pluck('question.topic')->unique()->filter()->values()->toArray();
        
        return [
            'question_count' => $quiz->total_questions,
            'difficulty' => $quiz->type === 'ai_generated' ? 'medium' : $attempt->quiz->type,
            'topics' => !empty($topics) ? $topics : [],
            'focus_areas' => !empty($focusTopics) ? $focusTopics : [],
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'similar_to_attempt_id' => $attempt->id,
            'reason' => 'Similar to your previous quiz, focusing on questions you found challenging',
        ];
    }
}
