<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
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

        // Get questions based on settings or use quiz defaults
        $query = QuizQuestion::where('course_id', $quiz->course_id)
            ->active()
            ->repository();

        if (isset($settings['topic'])) {
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
}
