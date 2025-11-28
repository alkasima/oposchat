<?php

namespace App\Http\Controllers;

use App\Services\QuizStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizStatisticsController extends Controller
{
    protected $statisticsService;

    public function __construct(QuizStatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Get overall quiz statistics for a course
     */
    public function getStatistics(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $user = Auth::user();
        $stats = $this->statisticsService->getOverallStats($user->id, $request->course_id);

        if (!$stats) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No statistics available yet. Take a quiz to get started!',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get topic performance breakdown
     */
    public function getTopicBreakdown(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $user = Auth::user();
        $topicPerformance = $this->statisticsService->getTopicPerformance($user->id, $request->course_id);

        return response()->json([
            'success' => true,
            'data' => $topicPerformance,
        ]);
    }

    /**
     * Get personalized recommendations
     */
    public function getRecommendations(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $user = Auth::user();
        $recommendations = $this->statisticsService->getRecommendations($user->id, $request->course_id);

        return response()->json([
            'success' => true,
            'data' => $recommendations,
        ]);
    }

    /**
     * Export statistics
     */
    public function exportStatistics(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'format' => 'nullable|in:json,csv',
        ]);

        $user = Auth::user();
        $format = $request->format ?? 'json';
        $data = $this->statisticsService->exportStatistics($user->id, $request->course_id, $format);

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'No statistics available to export.',
            ], 404);
        }

        if ($format === 'csv') {
            // Convert to CSV format
            $csv = $this->convertToCSV($data);
            
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="quiz-statistics.csv"');
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Convert statistics data to CSV format
     */
    private function convertToCSV(array $data): string
    {
        $csv = "Metric,Value\n";
        $csv .= "Overall Accuracy," . $data['overall_accuracy'] . "%\n";
        $csv .= "Total Quizzes Completed," . $data['total_quizzes_completed'] . "\n";
        $csv .= "Total Questions Answered," . $data['total_questions_answered'] . "\n";
        $csv .= "Total Correct Answers," . $data['total_correct_answers'] . "\n";
        $csv .= "Average Time Per Question," . $data['average_time_per_question'] . " seconds\n";
        $csv .= "\n";
        
        $csv .= "Topic,Correct,Total,Accuracy\n";
        foreach ($data['topic_performance'] as $topic => $performance) {
            $csv .= "$topic," . $performance['correct'] . "," . $performance['total'] . "," . $performance['accuracy'] . "%\n";
        }
        
        return $csv;
    }
}
