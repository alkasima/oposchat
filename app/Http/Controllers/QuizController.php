<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Services\AIQuizGeneratorService;
use App\Services\PersonalizationEngineService;
use App\Services\QuizStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    protected AIQuizGeneratorService $aiQuizGenerator;
    protected PersonalizationEngineService $personalizationEngine;
    protected QuizStatisticsService $statisticsService;
    
    public function __construct(
        AIQuizGeneratorService $aiQuizGenerator,
        PersonalizationEngineService $personalizationEngine,
        QuizStatisticsService $statisticsService
    ) {
        $this->aiQuizGenerator = $aiQuizGenerator;
        $this->personalizationEngine = $personalizationEngine;
        $this->statisticsService = $statisticsService;
    }

    /**
     * Get list of available quizzes for a course
     */
    public function index(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $courseId = $request->course_id;

        $quizzes = Quiz::where('course_id', $courseId)
            ->active()
            ->with('course:id,name,slug')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $quizzes,
        ]);
    }

    /**
     * Get quiz details
     */
    public function show(Quiz $quiz)
    {
        $quiz->load('course:id,name,slug');

        return response()->json([
            'success' => true,
            'data' => $quiz,
        ]);
    }

    /**
     * Get filtered quiz questions
     */
    public function getQuestions(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'topic' => 'nullable|string',
            'difficulty' => 'nullable|in:easy,medium,hard',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $query = QuizQuestion::where('course_id', $request->course_id)
            ->active()
            ->repository(); // Only repository questions for now

        // Apply filters
        if ($request->filled('topic')) {
            $query->where('topic', $request->topic);
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        // Get questions with options
        $questions = $query->with('options')
            ->inRandomOrder()
            ->limit($request->limit ?? 20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $questions,
            'meta' => [
                'total' => $questions->count(),
                'filters' => [
                    'topic' => $request->topic,
                    'difficulty' => $request->difficulty,
                ],
            ],
        ]);
    }

    /**
     * Get available topics for a course
     */
    public function getTopics(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $topics = QuizQuestion::where('course_id', $request->course_id)
            ->active()
            ->distinct()
            ->pluck('topic')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $topics,
        ]);
    }

    /**
     * Start a new quiz attempt
     */
    public function startAttempt(Request $request, Quiz $quiz)
    {
        $request->validate([
            'settings' => 'nullable|array',
            'settings.topic' => 'nullable|string',
            'settings.difficulty' => 'nullable|in:easy,medium,hard',
            'settings.question_count' => 'nullable|integer|min:1|max:100',
        ]);

        $user = Auth::user();
        $settings = $request->settings ?? [];

        // For AI-generated quizzes, get the questions directly associated with this quiz
        // For repository quizzes, filter from all course questions based on settings
        if ($quiz->type === 'ai_generated') {
            // AI quiz: get questions that were created for this quiz
            $questions = QuizQuestion::where('type', 'ai_generated')
                ->where('generated_by', $quiz->created_by)
                ->where('course_id', $quiz->course_id)
                ->with('options')
                ->latest('generated_at')
                ->limit($quiz->total_questions)
                ->get();
        } else {
            // Repository quiz: build query based on settings
            $query = QuizQuestion::where('course_id', $quiz->course_id)
                ->active()
                ->repository();

            if ($quiz->topic) {
                $query->where('topic', $quiz->topic);
            }
            // Allow overriding via settings if needed, but quiz->topic takes precedence if we want strictly the quiz content.
            // However, usually 'settings' are for dynamic quizzes.
            // If quiz has a topic, it's likely a Pre-made Quiz.
            
            if (isset($settings['topic']) && !$quiz->topic) {
                $query->where('topic', $settings['topic']);
            }

            if (isset($settings['difficulty'])) {
                $query->where('difficulty', $settings['difficulty']);
            }

            $questionCount = $settings['question_count'] ?? $quiz->total_questions ?? 20;
            
            $questions = $query->with('options')
                ->inRandomOrder()
                ->limit($questionCount)
                ->get();
        }

        if ($questions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No questions available with the selected filters.',
            ], 404);
        }

        // Create quiz attempt
        $attempt = QuizAttempt::create([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'course_id' => $quiz->course_id,
            'quiz_type' => 'repository',
            'settings' => $settings,
            'total_questions' => $questions->count(),
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        // Create attempt answers for each question
        foreach ($questions as $index => $question) {
            $correctOption = $question->options->where('is_correct', true)->first();
            
            QuizAttemptAnswer::create([
                'quiz_attempt_id' => $attempt->id,
                'quiz_question_id' => $question->id,
                'question_order' => $index + 1,
                'correct_option' => $correctOption ? $correctOption->option_letter : null,
            ]);
        }

        // Load the attempt with questions and options
        $attempt->load([
            'answers.question.options',
            'quiz:id,title,duration_minutes,shuffle_options',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'attempt' => $attempt,
                'redirect_url' => route('quiz.attempt', $attempt->id),
            ],
            'message' => 'Quiz attempt started successfully.',
        ]);
    }

    /**
     * Submit an answer for a question
     */
    public function submitAnswer(Request $request, QuizAttempt $attempt)
    {
        $request->validate([
            'question_id' => 'required|exists:quiz_questions,id',
            'selected_option' => 'required|in:A,B,C,D',
            'time_spent_seconds' => 'nullable|integer|min:0',
        ]);

        // Verify the attempt belongs to the authenticated user
        if ($attempt->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Verify attempt is still in progress
        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'This quiz attempt is no longer active.',
            ], 400);
        }

        // Find the answer record
        $answer = QuizAttemptAnswer::where('quiz_attempt_id', $attempt->id)
            ->where('quiz_question_id', $request->question_id)
            ->first();

        if (!$answer) {
            return response()->json([
                'success' => false,
                'message' => 'Question not found in this attempt.',
            ], 404);
        }

        // Update the answer
        $answer->update([
            'selected_option' => $request->selected_option,
            'is_correct' => $request->selected_option === $answer->correct_option,
            'time_spent_seconds' => $request->time_spent_seconds,
        ]);

        return response()->json([
            'success' => true,
            'data' => $answer,
            'message' => 'Answer submitted successfully.',
        ]);
    }

    /**
     * Toggle bookmark on a question
     */
    public function toggleBookmark(Request $request, QuizAttempt $attempt)
    {
        $request->validate([
            'question_id' => 'required|exists:quiz_questions,id',
        ]);

        // Verify ownership
        if ($attempt->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $answer = QuizAttemptAnswer::where('quiz_attempt_id', $attempt->id)
            ->where('quiz_question_id', $request->question_id)
            ->first();

        if (!$answer) {
            return response()->json([
                'success' => false,
                'message' => 'Question not found in this attempt.',
            ], 404);
        }

        $answer->update([
            'is_bookmarked' => !$answer->is_bookmarked,
        ]);

        return response()->json([
            'success' => true,
            'data' => $answer,
            'message' => 'Bookmark toggled successfully.',
        ]);
    }

    /**
     * Complete a quiz attempt
     */
    public function completeAttempt(Request $request, QuizAttempt $attempt)
    {
        $request->validate([
            'time_spent_seconds' => 'nullable|integer|min:0',
        ]);

        // Verify ownership
        if ($attempt->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Verify attempt is still in progress
        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'This quiz attempt is already completed.',
            ], 400);
        }

        // Update time spent if provided
        if ($request->filled('time_spent_seconds')) {
            $attempt->update([
                'time_spent_seconds' => $request->time_spent_seconds,
            ]);
        }

        // Complete the attempt (this will calculate the score)
        $attempt->complete();

        return response()->json([
            'success' => true,
            'data' => $attempt->fresh(),
            'message' => 'Quiz completed successfully.',
        ]);
    }

    /**
     * Get quiz attempt results
     */
    public function getAttemptResults(QuizAttempt $attempt)
    {
        // Verify ownership
        if ($attempt->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Verify attempt is completed
        if ($attempt->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Quiz attempt is not yet completed.',
            ], 400);
        }

        // Load all necessary relationships
        $attempt->load([
            'answers.question.options',
            'quiz:id,title,show_correct_answers,feedback_timing',
            'course:id,name',
        ]);

        return response()->json([
            'success' => true,
            'data' => $attempt,
        ]);
    }

    /**
     * Get user's quiz history for a course
     */
    public function getUserHistory(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $user = Auth::user();

        $attempts = QuizAttempt::where('user_id', $user->id)
            ->where('course_id', $request->course_id)
            ->with('quiz:id,title')
            ->orderBy('completed_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attempts,
        ]);
    }
    
    /**
     * Generate AI quiz questions and start attempt
     */
    public function generateAIQuiz(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'question_count' => 'integer|min:5|max:20',
            'difficulty' => 'in:easy,medium,hard',
            'topics' => 'array',
            'focus_on_weak_areas' => 'boolean'
        ]);

        try {
            $userId = auth()->id();
            
            // Prepare options for AI generation
            $options = [
                'question_count' => $validated['question_count'] ?? 10,
                'difficulty' => $validated['difficulty'] ?? 'medium',
                'topics' => $validated['topics'] ?? [],
            ];
            
            // Add weak areas if focus is enabled
            if ($validated['focus_on_weak_areas'] ?? false) {
                $recommendations = $this->personalizationEngine->getRecommendations($userId, $validated['course_id']);
                $options['focus_areas'] = $recommendations['weak_topics'] ?? [];
            }
            
            // Generate questions
            $questions = $this->aiQuizGenerator->generateQuizQuestions(
                $validated['course_id'],
                $userId,
                $options
            );

            if (empty($questions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No questions were generated. Please try again.'
                ], 500);
            }

            // Create a temporary AI quiz
            $course = Course::findOrFail($validated['course_id']);
            $quiz = Quiz::create([
                'course_id' => $validated['course_id'],
                'created_by' => $userId,
                'title' => 'AI Generated Quiz - ' . $course->name,
                'description' => 'AI-generated quiz with ' . count($questions) . ' questions',
                'type' => 'ai_generated',
                'duration_minutes' => count($questions) * 2,
                'total_questions' => count($questions),
                'shuffle_questions' => true,
                'shuffle_options' => true,
                'show_correct_answers' => true,
                'feedback_timing' => 'after_submission',
                'is_active' => true,
            ]);

            // Start quiz attempt
            $attempt = QuizAttempt::create([
                'user_id' => $userId,
                'quiz_id' => $quiz->id,
                'course_id' => $validated['course_id'],
                'started_at' => now(),
                'status' => 'in_progress',
            ]);

            // Associate questions with the attempt
            foreach ($questions as $index => $question) {
                // Load question options to get correct answer
                $question->load('options');
                $correctOption = $question->options->firstWhere('is_correct', true);
                
                QuizAttemptAnswer::create([
                    'quiz_attempt_id' => $attempt->id,
                    'quiz_question_id' => $question->id,
                    'question_order' => $index + 1,
                    'correct_option' => $correctOption ? $correctOption->option_letter : 'A',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => count($questions) . ' questions generated successfully!',
                'data' => [
                    'quiz_id' => $quiz->id,
                    'attempt_id' => $attempt->id,
                    'question_count' => count($questions),
                    'redirect_url' => route('quiz.attempt', $attempt->id)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AI Quiz Generation failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get personalized recommendations for a user
     */
    public function getRecommendations(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);
        
        $user = Auth::user();
        
        try {
            $recommendations = $this->personalizationEngine->getRecommendations($user->id, $request->course_id);
            
            return response()->json([
                'success' => true,
                'data' => $recommendations,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get recommendations', [
                'user_id' => $user->id,
                'course_id' => $request->course_id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate recommendations.',
            ], 500);
        }
    }
    
    /**
     * Get adaptive quiz configuration based on user performance
     */
    public function getAdaptiveQuizConfig(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'preferences' => 'nullable|array',
            'preferences.question_count' => 'nullable|integer|min:5|max:20',
            'preferences.difficulty' => 'nullable|in:easy,medium,hard',
            'preferences.topics' => 'nullable|array',
        ]);
        
        $user = Auth::user();
        $preferences = $request->preferences ?? [];
        
        try {
            $config = $this->personalizationEngine->generateAdaptiveQuizConfig(
                $user->id,
                $request->course_id,
                $preferences
            );
            
            return response()->json([
                'success' => true,
                'data' => $config,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate adaptive quiz configuration.',
            ], 500);
        }
    }
    
    /**
     * Generate AI explanation for a specific answer
     */
    public function generateExplanation(Request $request)
    {
        $request->validate([
            'question_id' => 'required|exists:quiz_questions,id',
            'selected_option' => 'required|in:A,B,C,D',
        ]);
        
        try {
            $question = QuizQuestion::with(['options', 'course'])->findOrFail($request->question_id);
            
            $explanation = $this->aiQuizGenerator->generateExplanation(
                $question,
                $request->selected_option,
                $question->course
            );
            
            return response()->json([
                'success' => true,
                'data' => [
                    'explanation' => $explanation,
                    'question_id' => $question->id,
                    'selected_option' => $request->selected_option,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to generate explanation', [
                'question_id' => $request->question_id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate explanation.',
            ], 500);
        }
    }
    
    /**
     * Generate a similar quiz based on a previous attempt
     */
    public function generateSimilarQuiz(Request $request, QuizAttempt $attempt)
    {
        // Verify ownership
        if ($attempt->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }
        
        try {
            $config = $this->personalizationEngine->generateSimilarQuizConfig($attempt);
            
            return response()->json([
                'success' => true,
                'data' => $config,
                'message' => 'Similar quiz configuration generated.',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate similar quiz configuration.',
            ], 500);
        }
    }
    
    /**
     * Get statistics for a user and course
     */
    public function getStatistics(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);
        
        $user = Auth::user();
        
        try {
            $stats = [
                'overall' => $this->statisticsService->getOverallStats($user->id, $request->course_id),
                'topics' => $this->statisticsService->getTopicPerformance($user->id, $request->course_id),
                'common_mistakes' => $this->statisticsService->findCommonMistakes($user->id, $request->course_id, 10),
                'recommendations' => $this->statisticsService->getRecommendations($user->id, $request->course_id),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get statistics', [
                'user_id' => $user->id,
                'course_id' => $request->course_id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics.',
            ], 500);
        }
    }
}

