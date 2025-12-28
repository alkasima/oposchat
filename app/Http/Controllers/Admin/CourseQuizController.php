<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CourseQuizController extends Controller
{
    /**
     * Store a new quiz from an uploaded file
     */
    public function store(Request $request, Course $course)
    {
        $request->validate([
            'file' => 'required|file|mimes:json,txt',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:1',
        ]);

        $file = $request->file('file');
        
        try {
            $content = file_get_contents($file->getRealPath());
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON format: ' . json_last_error_msg());
            }

            if (!isset($data['questions']) || !is_array($data['questions'])) {
                throw new \Exception('JSON must contain a "questions" array.');
            }

            DB::beginTransaction();

            // Generate unique topic for this quiz
            $topicSlug = 'quiz_' . Str::slug($request->title) . '_' . time();

            $quiz = Quiz::create([
                'course_id' => $course->id,
                'created_by' => auth()->id(),
                'title' => $request->title,
                'description' => $request->description,
                'topic' => $topicSlug,
                'duration_minutes' => $request->duration_minutes ?? 30,
                'total_questions' => count($data['questions']),
                'type' => 'repository',
                'is_active' => true,
                'shuffle_questions' => true,
                'shuffle_options' => true,
                'show_correct_answers' => true,
                'feedback_timing' => 'after_submission',
            ]);

            $letters = ['A', 'B', 'C', 'D', 'E', 'F'];

            foreach ($data['questions'] as $qData) {
                // Determine question text
                $qText = $qData['question_text'] ?? $qData['text'] ?? null;
                if (!$qText) continue;

                $question = QuizQuestion::create([
                    'course_id' => $course->id,
                    'question_text' => $qText,
                    'explanation' => $qData['explanation'] ?? null,
                    'topic' => $topicSlug,
                    'difficulty' => $qData['difficulty'] ?? 'medium',
                    'type' => 'repository',
                    'is_active' => true, // Default to active
                ]);

                if (isset($qData['options']) && is_array($qData['options'])) {
                    foreach ($qData['options'] as $idx => $oData) {
                        $optionText = $oData['text'] ?? $oData['option_text'] ?? null;
                        if (!$optionText) continue;

                        $letter = $oData['option_letter'] ?? ($letters[$idx] ?? 'Z');

                        QuizQuestionOption::create([
                            'quiz_question_id' => $question->id,
                            'option_letter' => $letter,
                            'option_text' => $optionText,
                            'is_correct' => $oData['is_correct'] ?? false,
                        ]);
                    }
                }
            }

            DB::commit();

            // ---------------------------------------------------------
            // RAG INTEGRATION: Index this quiz as a study document
            // ---------------------------------------------------------
            try {
                // Formatting quiz content for the AI to "read"
                $documentContent = "QUIZ: " . $request->title . "\n";
                $documentContent .= "DESCRIPTION: " . ($request->description ?? 'Practice questions') . "\n\n";
                
                foreach ($data['questions'] as $index => $qData) {
                    $qText = $qData['question_text'] ?? $qData['text'] ?? "Question " . ($index + 1);
                    $explanation = $qData['explanation'] ?? '';
                    
                    $documentContent .= "QUESTION " . ($index + 1) . ": " . $qText . "\n";
                    
                    if (isset($qData['options']) && is_array($qData['options'])) {
                        foreach ($qData['options'] as $oData) {
                            $oText = $oData['text'] ?? $oData['option_text'] ?? '';
                            $isCorrect = $oData['is_correct'] ?? false;
                            $marker = $isCorrect ? " [CORRECT ANSWER]" : "";
                            $documentContent .= "- " . $oText . $marker . "\n";
                        }
                    }
                    
                    if ($explanation) {
                        $documentContent .= "EXPLANATION: " . $explanation . "\n";
                    }
                    $documentContent .= "\n" . str_repeat("-", 20) . "\n\n";
                }

                // Save as a text file in storage
                $filename = 'quiz_' . Str::uuid() . '.txt';
                \Illuminate\Support\Facades\Storage::disk('private')->put('course-documents/' . $filename, $documentContent);

                // Create CourseDocument record
                $document = \App\Models\CourseDocument::create([
                    'course_id' => $course->id,
                    'title' => 'Quiz Content: ' . $request->title,
                    'filename' => $filename,
                    'original_filename' => 'quiz_import_' . time() . '.json',
                    'mime_type' => 'text/plain',
                    'file_size' => strlen($documentContent),
                    'description' => 'Auto-generated content from imported quiz: ' . $request->title,
                    'document_type' => 'practice_test', // Treat as practice test content
                    'metadata' => [
                        'uploaded_by' => auth()->id(),
                        'uploaded_at' => now()->toISOString(),
                        'source' => 'quiz_import',
                        'quiz_id' => $quiz->id,
                        'processing_status' => 'queued',
                    ],
                ]);

                // Dispatch processing job
                \App\Jobs\ProcessCourseDocument::dispatch($document->id);

            } catch (\Exception $e) {
                // Log but don't fail the request if RAG indexing fails
                Log::error('Failed to index quiz for RAG: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Quiz created successfully and added to AI knowledge base.',
                'quiz' => $quiz
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Quiz upload failed: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Failed to create quiz: ' . $e->getMessage()
            ], 500);
        }
    }
}
