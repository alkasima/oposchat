<?php

namespace App\Services;

use App\Models\Course;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionOption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AIQuizGeneratorService
{
    private AIProviderService $aiProvider;
    private VectorStoreService $vectorStore;
    
    // Rate limiting constants
    private const MAX_AI_QUIZZES_PER_DAY = 3;
    private const MAX_QUESTIONS_PER_QUIZ = 20;
    private const MIN_QUESTIONS_PER_QUIZ = 5;
    
    public function __construct(
        AIProviderService $aiProvider,
        VectorStoreService $vectorStore
    ) {
        $this->aiProvider = $aiProvider;
        $this->vectorStore = $vectorStore;
    }
    
    /**
     * Generate AI-based quiz questions for a course
     *
     * @param int $courseId Course ID
     * @param int $userId User ID requesting generation
     * @param array $options Generation options (topics, difficulty, count, focus_areas)
     * @return array Generated questions with metadata
     * @throws Exception
     */
    public function generateQuizQuestions(int $courseId, int $userId, array $options = []): array
    {
        // Validate rate limits
        $this->validateRateLimit($userId, $courseId);
        
        // Extract and validate options
        $questionCount = min(
            max($options['question_count'] ?? 10, self::MIN_QUESTIONS_PER_QUIZ),
            self::MAX_QUESTIONS_PER_QUIZ
        );
        $difficulty = $options['difficulty'] ?? 'medium';
        $topics = $options['topics'] ?? [];
        $focusAreas = $options['focus_areas'] ?? []; // Weak topics from personalization
        
        // Get course
        $course = Course::findOrFail($courseId);
        
        // Get sample questions as templates (optional)
        $sampleQuestions = $this->getSampleQuestions($courseId, $topics, $difficulty);
        
        // Sample questions are optional - AI can work without them
        // It will use only the RAG syllabus content if no samples exist
        
        // Get comprehensive syllabus context from RAG (30+ chunks for rich content)
        $contextPool = $this->getSyllabusContext($course, $topics, $focusAreas, $difficulty);
        
        Log::info('Context pool retrieved for quiz generation', [
            'course_id' => $courseId,
            'context_chunks' => count($contextPool),
            'difficulty' => $difficulty
        ]);
        
        // Generate questions in batches
        $generatedQuestions = [];
        $generatedQuestionTexts = []; // Track generated questions to prevent duplicates
        $batchSize = 5; // Generate 5 questions at a time
        
        for ($i = 0; $i < $questionCount; $i += $batchSize) {
            $remainingQuestions = min($batchSize, $questionCount - $i);
            
            // Select different context slice for each batch to ensure variety
            $batchContext = $this->selectContextForBatch($contextPool, $i / $batchSize, $questionCount);
            
            $batch = $this->generateQuestionBatch(
                $course,
                $sampleQuestions,
                $batchContext,
                $remainingQuestions,
                $difficulty,
                $topics,
                $focusAreas,
                $userId,
                $generatedQuestionTexts // Pass already generated questions
            );
            
            $generatedQuestions = array_merge($generatedQuestions, $batch);
            
            // Track question texts from this batch
            foreach ($batch as $question) {
                if (isset($question['question_text'])) {
                    $generatedQuestionTexts[] = $question['question_text'];
                }
            }
        }
        
        // Deduplicate questions before returning
        $uniqueQuestions = $this->deduplicateQuestions($generatedQuestions);
        
        $duplicatesRemoved = count($generatedQuestions) - count($uniqueQuestions);
        if ($duplicatesRemoved > 0) {
            Log::warning('Duplicate questions detected and removed', [
                'course_id' => $courseId,
                'duplicates_removed' => $duplicatesRemoved,
                'original_count' => count($generatedQuestions),
                'final_count' => count($uniqueQuestions)
            ]);
        }
        
        Log::info('AI questions generated successfully', [
            'course_id' => $courseId,
            'user_id' => $userId,
            'question_count' => count($uniqueQuestions)
        ]);
        
        return $uniqueQuestions;
    }
    
    /**
     * Generate a batch of questions using AI
     */
    private function generateQuestionBatch(
        Course $course,
        $sampleQuestions,
        array $context,
        int $count,
        string $difficulty,
        array $topics,
        array $focusAreas,
        int $userId,
        array $alreadyGeneratedQuestions = []
    ): array {
        // Build the prompt
        $prompt = $this->buildGenerationPrompt(
            $course,
            $sampleQuestions,
            $context,
            $count,
            $difficulty,
            $topics,
            $focusAreas,
            $alreadyGeneratedQuestions
        );
        
        // Call AI provider
        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];
        
        try {
            $response = $this->aiProvider->chatCompletion($messages, [
                'temperature' => 0.9, // Higher creativity for diverse, unique questions
                'max_tokens' => 3000,
            ]);
            
            // Parse AI response into structured questions
            $questions = $this->parseAIResponse($response['content'], $course->id, $userId);
            
            // Validate and save questions
            return $this->validateAndSaveQuestions($questions, $course->id, $userId);
            
        } catch (Exception $e) {
            Log::error('AI question generation failed', [
                'error' => $e->getMessage(),
                'course_id' => $course->id
            ]);
            throw new Exception('Failed to generate questions: ' . $e->getMessage());
        }
    }
    
    /**
     * Build the AI prompt for question generation
     */
    private function buildGenerationPrompt(
        Course $course,
        $sampleQuestions,
        array $context,
        int $count,
        string $difficulty,
        array $topics,
        array $focusAreas,
        array $alreadyGeneratedQuestions = []
    ): string {
        $prompt = "You are an expert exam question creator for competitive examinations in Spain (oposiciones).\n\n";
        $prompt .= "TASK: Generate {$count} UNIQUE, ORIGINAL multiple-choice questions (A, B, C, D format) ";
        $prompt .= "for the course: {$course->name}\n\n";
        
        $prompt .= "DIFFICULTY LEVEL: {$difficulty}\n\n";
        
        if (!empty($topics)) {
            $prompt .= "TOPICS TO COVER: " . implode(', ', $topics) . "\n\n";
        }
        
        if (!empty($focusAreas)) {
            $prompt .= "FOCUS AREAS (student needs practice): " . implode(', ', $focusAreas) . "\n\n";
        }
        
        // Add MUCH MORE syllabus context (10-15 chunks instead of 3)
        if (!empty($context)) {
            $prompt .= "SYLLABUS CONTENT (use this as your PRIMARY knowledge base):\n";
            $prompt .= "The following are SPECIFIC excerpts from the course syllabus. Your questions MUST reference the actual details, terminology, laws, dates, procedures, and concepts presented here.\n\n";
            $prompt .= implode("\n\n---\n\n", array_slice($context, 0, 15)) . "\n\n";
        }
        
        // Add sample questions as templates ONLY if available
        if (!$sampleQuestions->isEmpty()) {
            $prompt .= "EXAMPLE QUESTION FORMAT (study the style, DO NOT copy):\n";
            $samples = $sampleQuestions->take(2);
            foreach ($samples as $idx => $sample) {
                $prompt .= "\nExample " . ($idx + 1) . ":\n";
                $prompt .= "Question: {$sample->question_text}\n";
                $options = $sample->options;
                foreach ($options as $option) {
                    $marker = $option->is_correct ? " [CORRECT]" : "";
                    $prompt .= "  {$option->option_letter}) {$option->option_text}{$marker}\n";
                }
                if ($sample->explanation) {
                    $prompt .= "Explanation: {$sample->explanation}\n";
                }
            }
            $prompt .= "\nIMPORTANT: Generate COMPLETELY ORIGINAL questions - do NOT copy or paraphrase the examples above.\n\n";
        }
        
        // Add already generated questions to prevent duplicates
        if (!empty($alreadyGeneratedQuestions)) {
            $prompt .= "ALREADY GENERATED QUESTIONS (DO NOT REPEAT OR CREATE SIMILAR QUESTIONS):\n";
            foreach (array_slice($alreadyGeneratedQuestions, 0, 10) as $idx => $generatedQ) {
                $prompt .= ($idx + 1) . ". " . substr($generatedQ, 0, 100) . "...\n";
            }
            $prompt .= "\n⚠️ CRITICAL: Your questions must be COMPLETELY DIFFERENT from the above. Do NOT create variations or similar questions.\n\n";
        }
        
        $prompt .= "IMPORTANT GUIDELINES:\n";
        $prompt .= "1. Generate COMPLETELY ORIGINAL and UNIQUE questions based on the SPECIFIC syllabus content provided\n";
        $prompt .= "2. Each question MUST reference actual details from the syllabus (laws, dates, procedures, terminology, concepts)\n";
        $prompt .= "3. AVOID generic questions - use the specific information provided in the syllabus content\n";
        $prompt .= "4. Each question must be DIFFERENT from all previously generated questions\n";
        $prompt .= "5. Each question must have exactly 4 options (A, B, C, D)\n";
        $prompt .= "6. Only ONE option should be correct\n";
        $prompt .= "7. Make distractors (wrong answers) plausible but clearly incorrect based on syllabus content\n";
        $prompt .= "8. Provide a detailed explanation referencing the specific syllabus content\n";
        $prompt .= "9. Match the difficulty level:\n";
        $prompt .= "   - Easy: Basic recall of facts, definitions, and simple concepts from syllabus\n";
        $prompt .= "   - Medium: Understanding and application of syllabus concepts\n";
        $prompt .= "   - Hard: Complex scenarios, analysis, and synthesis of multiple syllabus topics\n";
        $prompt .= "10. Use clear, professional language appropriate for competitive exams\n";
        $prompt .= "11. AVOID repetition - each question should explore different aspects of the syllabus\n";
        $prompt .= "12. Questions should feel specific to THIS course, not generic exam questions\n\n";
        
        $prompt .= "OUTPUT FORMAT (JSON array):\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= "    \"question_text\": \"Your question here?\",\n";
        $prompt .= "    \"topic\": \"Specific topic name\",\n";
        $prompt .= "    \"difficulty\": \"{$difficulty}\",\n";
        $prompt .= "    \"explanation\": \"Detailed explanation of the correct answer\",\n";
        $prompt .= "    \"options\": [\n";
        $prompt .= "      {\"label\": \"A\", \"text\": \"Option A text\", \"is_correct\": false},\n";
        $prompt .= "      {\"label\": \"B\", \"text\": \"Option B text\", \"is_correct\": true},\n";
        $prompt .= "      {\"label\": \"C\", \"text\": \"Option C text\", \"is_correct\": false},\n";
        $prompt .= "      {\"label\": \"D\", \"text\": \"Option D text\", \"is_correct\": false}\n";
        $prompt .= "    ]\n";
        $prompt .= "  }\n";
        $prompt .= "]\n\n";
        
        $prompt .= "Generate {$count} questions now in valid JSON format:";
        
        return $prompt;
    }
    
    /**
     * Parse AI response into structured question data
     */
    private function parseAIResponse(string $response, int $courseId, int $userId): array
    {
        // Try to extract JSON from the response
        $jsonStart = strpos($response, '[');
        $jsonEnd = strrpos($response, ']');
        
        if ($jsonStart === false || $jsonEnd === false) {
            throw new Exception('Invalid AI response format: No JSON array found');
        }
        
        $jsonString = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
        $questions = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('JSON parse error', [
                'error' => json_last_error_msg(),
                'response' => $response
            ]);
            throw new Exception('Failed to parse AI response: ' . json_last_error_msg());
        }
        
        return $questions;
    }
    
    /**
     * Deduplicate questions by checking for exact matches and high similarity
     */
    private function deduplicateQuestions(array $questions): array
    {
        $uniqueQuestions = [];
        $seenTexts = [];
        
        foreach ($questions as $question) {
            if (!isset($question['question_text'])) {
                continue;
            }
            
            $questionText = trim(strtolower($question['question_text']));
            $isDuplicate = false;
            
            // Check for exact match
            foreach ($seenTexts as $seenText) {
                if ($questionText === $seenText) {
                    Log::info('Exact duplicate question found', [
                        'question' => substr($question['question_text'], 0, 100)
                    ]);
                    $isDuplicate = true;
                    break;
                }
                
                // Check for high similarity (> 85% similar)
                $similarity = $this->calculateSimilarity($questionText, $seenText);
                if ($similarity > 85) {
                    Log::info('Similar question found and removed', [
                        'question' => substr($question['question_text'], 0, 100),
                        'similarity' => $similarity
                    ]);
                    $isDuplicate = true;
                    break;
                }
            }
            
            if (!$isDuplicate) {
                $uniqueQuestions[] = $question;
                $seenTexts[] = $questionText;
            }
        }
        
        return $uniqueQuestions;
    }
    
    /**
     * Calculate similarity percentage between two strings using Levenshtein distance
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        
        if ($len1 === 0 || $len2 === 0) {
            return 0;
        }
        
        $maxLen = max($len1, $len2);
        $distance = levenshtein(substr($str1, 0, 255), substr($str2, 0, 255)); // Levenshtein limited to 255 chars
        
        return (1 - ($distance / $maxLen)) * 100;
    }
    
    /**
     * Validate and save generated questions to database
     */
    private function validateAndSaveQuestions(array $questions, int $courseId, int $userId): array
    {
        $savedQuestions = [];
        
        DB::beginTransaction();
        
        try {
            foreach ($questions as $questionData) {
                // Validate question structure
                if (!$this->validateQuestionStructure($questionData)) {
                    Log::warning('Invalid question structure, skipping', ['data' => $questionData]);
                    continue;
                }
                
                // Check for duplicate in database (additional safety check)
                $questionText = trim($questionData['question_text']);
                $existingQuestion = QuizQuestion::where('course_id', $courseId)
                    ->where('question_text', $questionText)
                    ->first();
                    
                if ($existingQuestion) {
                    Log::info('Duplicate question found in database, skipping', [
                        'question' => substr($questionText, 0, 100)
                    ]);
                    continue;
                }
                
                // Create question
                $question = QuizQuestion::create([
                    'course_id' => $courseId,
                    'question_text' => $questionData['question_text'],
                    'explanation' => $questionData['explanation'] ?? '',
                    'difficulty' => $questionData['difficulty'] ?? 'medium',
                    'topic' => $questionData['topic'] ?? 'General',
                    'tags' => $questionData['tags'] ?? [],
                    'metadata' => [
                        'ai_generated' => true,
                        'generation_timestamp' => now()->toIso8601String(),
                        'prompt_version' => '1.0'
                    ],
                    'type' => 'ai_generated',
                    'generated_by' => $userId,
                    'generated_at' => now(),
                    'is_active' => true,
                ]);
                
                // Create options
                foreach ($questionData['options'] as $optionData) {
                    QuizQuestionOption::create([
                        'quiz_question_id' => $question->id,
                        'option_letter' => $optionData['label'],
                        'option_text' => $optionData['text'],
                        'is_correct' => $optionData['is_correct'] ?? false,
                    ]);
                }
                
                // Load options relationship
                $question->load('options');
                $savedQuestions[] = $question;
            }
            
            DB::commit();
            
            return $savedQuestions;
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to save AI questions', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Validate question structure
     */
    private function validateQuestionStructure(array $questionData): bool
    {
        // Check required fields
        if (empty($questionData['question_text'])) {
            return false;
        }
        
        if (empty($questionData['options']) || count($questionData['options']) !== 4) {
            return false;
        }
        
        // Verify exactly one correct answer
        $correctCount = 0;
        foreach ($questionData['options'] as $option) {
            if (!isset($option['label']) || !isset($option['text'])) {
                return false;
            }
            if ($option['is_correct'] ?? false) {
                $correctCount++;
            }
        }
        
        return $correctCount === 1;
    }
    
    /**
     * Get sample questions as templates
     */
    private function getSampleQuestions(int $courseId, array $topics, string $difficulty)
    {
        $query = QuizQuestion::where('course_id', $courseId)
            ->where('type', 'repository')
            ->where('is_active', true)
            ->with('options');
        
        // Filter by difficulty if specified
        if ($difficulty) {
            $query->where('difficulty', $difficulty);
        }
        
        // Filter by topics if specified
        if (!empty($topics)) {
            $query->whereIn('topic', $topics);
        }
        
        return $query->inRandomOrder()->limit(5)->get();
    }
    
    /**
     * Get comprehensive syllabus context from RAG for question generation
     * Uses multiple varied queries to ensure broad coverage of course material
     */
    private function getSyllabusContext(Course $course, array $topics, array $focusAreas, string $difficulty): array
    {
        try {
            // Build multiple varied search queries for diverse context
            $searchQueries = $this->buildVariedSearchQueries($course, $topics, $focusAreas, $difficulty);
            
            // Get namespaces for the course
            $namespaces = [$course->slug];
            
            // DRAMATICALLY increase search limit to get rich, specific content
            $searchLimit = 30; // Increased from default 5
            $contextsPerQuery = (int)ceil($searchLimit / count($searchQueries));
            
            $allContext = [];
            
            foreach ($searchQueries as $query) {
                // Use existing RAG system with custom limit
                $contextData = $this->aiProvider->getRelevantContext($query, $namespaces, $contextsPerQuery);
                
                if (!empty($contextData['context'])) {
                    foreach ($contextData['context'] as $chunk) {
                        $allContext[] = $chunk;
                    }
                }
            }
            
            // Remove potential duplicates
            $allContext = array_unique($allContext);
            
            // Shuffle to ensure variety across batches
            shuffle($allContext);
            
            Log::info('Comprehensive syllabus context retrieved', [
                'course_id' => $course->id,
                'total_chunks' => count($allContext),
                'queries_used' => count($searchQueries),
                'difficulty' => $difficulty
            ]);
            
            return array_values($allContext);
            
        } catch (Exception $e) {
            Log::error('Failed to get syllabus context', [
                'course_id' => $course->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Build varied search queries to retrieve diverse context from different syllabus sections
     */
    private function buildVariedSearchQueries(Course $course, array $topics, array $focusAreas, string $difficulty): array
    {
        $queries = [];
        
        // Query 1: Course-level general query
        $queries[] = $course->name . ' exam preparation study material';
        
        // Query 2: Difficulty-based query
        $difficultyTerms = [
            'easy' => 'basic concepts definitions fundamentals introduction',
            'medium' => 'procedures applications practical examples',
            'hard' => 'complex scenarios analysis case studies advanced topics'
        ];
        $queries[] = $course->name . ' ' . ($difficultyTerms[$difficulty] ?? 'concepts');
        
        // Query 3: Topic-based queries (if provided)
        if (!empty($topics)) {
            foreach (array_slice($topics, 0, 2) as $topic) {
                $queries[] = $course->name . ' ' . $topic . ' detailed content';
            }
        }
        
        // Query 4: Focus areas (weak topics)
        if (!empty($focusAreas)) {
            $queries[] = $course->name . ' ' . implode(' ', array_slice($focusAreas, 0, 2));
        }
        
        // Query 5: Laws/regulations/procedures (common in competitive exams)
        $queries[] = $course->name . ' laws regulations procedures requirements';
        
        return $queries;
    }
    
    /**
     * Select appropriate context slice for a specific batch
     * Ensures different batches get different syllabus content
     */
    private function selectContextForBatch(array $contextPool, int $batchIndex, int $totalQuestions): array
    {
        if (empty($contextPool)) {
            return [];
        }
        
        // Each batch gets 15 chunks from the pool
        $chunksPerBatch = 15;
        $startIndex = ($batchIndex * $chunksPerBatch) % count($contextPool);
        
        $batchContext = [];
        for ($i = 0; $i < $chunksPerBatch && count($batchContext) < count($contextPool); $i++) {
            $index = ($startIndex + $i) % count($contextPool);
            $batchContext[] = $contextPool[$index];
        }
        
        return $batchContext;
    }
    
    /**
     * Validate rate limits for AI quiz generation
     */
    private function validateRateLimit(int $userId, int $courseId): void
    {
        // Count AI-generated questions by this user today
        $todayCount = QuizQuestion::where('generated_by', $userId)
            ->where('course_id', $courseId)
            ->where('type', 'ai_generated')
            ->whereDate('generated_at', today())
            ->count();
        
        $quizzesGenerated = ceil($todayCount / 10); // Assuming 10 questions per quiz
        
        if ($quizzesGenerated >= self::MAX_AI_QUIZZES_PER_DAY) {
            throw new Exception(
                "Daily AI quiz generation limit reached ({$quizzesGenerated}/" . 
                self::MAX_AI_QUIZZES_PER_DAY . "). Please try again tomorrow."
            );
        }
    }
    
    /**
     * Generate AI explanation for a specific answer
     *
     * @param QuizQuestion $question The question
     * @param string $selectedOption The option label (A, B, C, D)
     * @param Course $course The course
     * @return string Generated explanation
     */
    public function generateExplanation(QuizQuestion $question, string $selectedOption, Course $course): string
    {
        try {
            // Get syllabus context for this question
            $context = $this->getSyllabusContext($course, [$question->topic], []);
            
            // Find the selected option
            $option = $question->options()->where('option_label', $selectedOption)->first();
            $correctOption = $question->getCorrectOption();
            
            if (!$option || !$correctOption) {
                return 'Unable to generate explanation.';
            }
            
            $isCorrect = $option->is_correct;
            
            // Build explanation prompt
            $prompt = "You are an expert tutor for competitive examinations in Spain.\n\n";
            $prompt .= "QUESTION: {$question->question_text}\n\n";
            $prompt .= "STUDENT SELECTED: {$selectedOption}) {$option->option_text}\n";
            $prompt .= "CORRECT ANSWER: {$correctOption->option_label}) {$correctOption->option_text}\n\n";
            
            if (!empty($context)) {
                $prompt .= "SYLLABUS REFERENCE:\n";
                $prompt .= implode("\n\n", array_slice($context, 0, 2)) . "\n\n";
            }
            
            if ($isCorrect) {
                $prompt .= "The student answered CORRECTLY. Provide a clear, encouraging explanation of:\n";
                $prompt .= "1. Why this answer is correct\n";
                $prompt .= "2. Key concepts that support this answer\n";
                $prompt .= "3. How to remember this for the exam\n";
            } else {
                $prompt .= "The student answered INCORRECTLY. Provide a helpful, constructive explanation of:\n";
                $prompt .= "1. Why their answer is incorrect\n";
                $prompt .= "2. Why the correct answer is right\n";
                $prompt .= "3. Common misconceptions related to this question\n";
                $prompt .= "4. How to avoid this mistake in the future\n";
            }
            
            $prompt .= "\nBase your explanation ONLY on the syllabus content provided. ";
            $prompt .= "Keep it concise (2-3 paragraphs) and educational.";
            
            $messages = [
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $response = $this->aiProvider->chatCompletion($messages, [
                'temperature' => 0.7,
                'max_tokens' => 500,
            ]);
            
            return $response['content'];
            
        } catch (Exception $e) {
            Log::error('Failed to generate explanation', [
                'question_id' => $question->id,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to stored explanation
            return $question->explanation ?? 'Explanation unavailable.';
        }
    }
}
