<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EnhancedAIProviderService extends AIProviderService
{
    /**
     * Constructor - call parent to initialize config
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Enhanced chat completion with pedagogical approach
     */
    public function chatCompletionWithContext(array $messages, array $namespaces = [], array $options = []): array
    {
        // Get relevant context if namespaces are provided
        $contextData = [];
        $isRelevant = true;
        
        if (!empty($namespaces)) {
            $lastUserMessage = end($messages);
            if ($lastUserMessage && $lastUserMessage['role'] === 'user') {
                $contextData = $this->getRelevantContext($lastUserMessage['content'], $namespaces);
                // Default to false (not relevant) if is_relevant is not set - this is safer
                $isRelevant = $contextData['is_relevant'] ?? false;
            }
        }

        // Create enhanced pedagogical system message
        // ALWAYS use enhanced system message when namespaces are provided, regardless of options
        if (!empty($namespaces)) {
            // Detect if the user is asking for a diagram-like deliverable
            $lastUserMessage = $messages[count($messages) - 1] ?? null;
            $lastUserText = ($lastUserMessage && ($lastUserMessage['role'] ?? '') === 'user') ? strtolower($lastUserMessage['content'] ?? '') : '';
            
            // Detect language of the current question
            $questionLanguage = $this->detectLanguage($lastUserMessage['content'] ?? '');

            // STRICT SAFETY CHECK: If the question is not relevant to the syllabus, 
            // return a canned response immediately. Do not even call the LLM.
            // This prevents "hallucinations" or answering with external knowledge.
            if (!$isRelevant) {
                Log::info('EnhancedAIProviderService: STRICT MODE - Blocking irrelevant request', [
                   'query' => $lastUserText,
                   'language' => $questionLanguage
                ]);

                $refusalMessage = ($questionLanguage === 'spanish')
                    ? "Lo siento, pero esta pregunta no parece estar relacionada con el temario subido. Solo puedo responder preguntas basándome explícitamente en el contenido de los documentos del curso. Por favor, reformula tu pregunta utilizando términos que aparezcan en el temario."
                    : "I apologize, but this question does not appear to be related to the uploaded syllabus. I can only answer questions based explicitly on the course documents. Please rephrase your question using terms covered in the syllabus.";

                return [
                    'content' => $refusalMessage,
                    'role' => 'assistant',
                    'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
                    'finish_reason' => 'stop'
                ];
            }

            // ALWAYS create our own system message - ignore any system_message from options
            $systemMessageContent = $this->createPedagogicalSystemMessage($contextData, $isRelevant, $lastUserText, $questionLanguage);
            
            // Log for debugging
            Log::info('EnhancedAIProviderService: Using enhanced system message', [
                'is_relevant' => $isRelevant,
                'question_language' => $questionLanguage,
                'context_chunks' => count($contextData['context'] ?? []),
                'avg_relevance' => $contextData['avg_relevance'] ?? 0,
                'max_relevance' => !empty($contextData['relevance_scores']) ? max($contextData['relevance_scores']) : 0,
                'system_message_preview' => substr($systemMessageContent, 0, 200) . '...'
            ]);
        } else {
            $systemMessageContent = $options['system_message'] ?? config('ai.defaults.system_message');
        }

        $systemMessage = [
            'role' => 'system',
            'content' => $systemMessageContent
        ];
        
        // Remove ANY existing system messages and insert the new one at the beginning
        // This ensures we always use the current relevance status, not stale system messages
        // CRITICAL: This must happen to override any system messages from buildExamSpecificSystemMessage
        $messages = array_values(array_filter($messages, function($msg) {
            return ($msg['role'] ?? '') !== 'system';
        }));
        array_unshift($messages, $systemMessage);

        return $this->chatCompletion($messages, $options);
    }

    /**
     * Enhanced streaming chat completion with pedagogical approach
     */
    public function streamChatCompletionWithContext(array $messages, callable $callback, array $namespaces = [], array $options = []): array
    {
        // Get relevant context if namespaces are provided
        $contextData = [];
        $isRelevant = true;
        
        if (!empty($namespaces)) {
            $lastUserMessage = end($messages);
            if ($lastUserMessage && $lastUserMessage['role'] === 'user') {
                $contextData = $this->getRelevantContext($lastUserMessage['content'], $namespaces);
                // Default to false (not relevant) if is_relevant is not set - this is safer
                $isRelevant = $contextData['is_relevant'] ?? false;
            }
        }

        // Create enhanced pedagogical system message
        // ALWAYS use enhanced system message when namespaces are provided, regardless of options
        if (!empty($namespaces)) {
            // Detect if the user is asking for a diagram-like deliverable
            $lastUserMessage = $messages[count($messages) - 1] ?? null;
            $lastUserText = ($lastUserMessage && ($lastUserMessage['role'] ?? '') === 'user') ? strtolower($lastUserMessage['content'] ?? '') : '';
            
            // Detect language of the current question
            $questionLanguage = $this->detectLanguage($lastUserMessage['content'] ?? '');

            // STRICT SAFETY CHECK: If request is irrelevant, stream a refusal message immediately.
            if (!$isRelevant) {
                Log::info('EnhancedAIProviderService: STRICT MODE - Blocking irrelevant streaming request', [
                   'query' => $lastUserText,
                   'language' => $questionLanguage
                ]);

                $refusalMessage = ($questionLanguage === 'spanish')
                    ? "Lo siento, pero esta pregunta no parece estar relacionada con el temario subido. Solo puedo responder preguntas basándome explícitamente en el contenido de los documentos del curso."
                    : "I apologize, but this question does not appear to be related to the uploaded syllabus. I can only answer questions based explicitly on the course documents.";

                // Stream the refusal word by word to simulate AI feeling
                $words = explode(' ', $refusalMessage);
                foreach ($words as $word) {
                    $callback($word . ' ');
                    usleep(50000); // Small delay for natural feel
                }
                
                return [
                    'content' => $refusalMessage,
                    'role' => 'assistant',
                    'usage' => ['total_tokens' => 0]
                ];
            }

            // ALWAYS create our own system message - ignore any system_message from options
            $systemMessageContent = $this->createPedagogicalSystemMessage($contextData, $isRelevant, $lastUserText, $questionLanguage);
            
            // Log for debugging
            Log::info('EnhancedAIProviderService: Using enhanced system message', [
                'is_relevant' => $isRelevant,
                'question_language' => $questionLanguage,
                'context_chunks' => count($contextData['context'] ?? []),
                'avg_relevance' => $contextData['avg_relevance'] ?? 0,
                'max_relevance' => !empty($contextData['relevance_scores']) ? max($contextData['relevance_scores']) : 0,
                'system_message_preview' => substr($systemMessageContent, 0, 200) . '...'
            ]);
        } else {
            $systemMessageContent = $options['system_message'] ?? config('ai.defaults.system_message');
        }

        $systemMessage = [
            'role' => 'system',
            'content' => $systemMessageContent
        ];
        
        // Remove ANY existing system messages and insert the new one at the beginning
        // This ensures we always use the current relevance status, not stale system messages
        // CRITICAL: This must happen to override any system messages from buildExamSpecificSystemMessage
        $messages = array_values(array_filter($messages, function($msg) {
            return ($msg['role'] ?? '') !== 'system';
        }));
        array_unshift($messages, $systemMessage);

        return $this->streamChatCompletion($messages, $callback, $options);
    }

    /**
     * Detect if question is in Spanish or English
     */
    private function detectLanguage(string $text): string
    {
        if (empty($text)) {
            return 'english';
        }
        
        // Simple language detection based on common Spanish words/characters
        $spanishIndicators = [
            // Accented characters
            'á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü',
            // Spanish punctuation
            '¿', '¡',
            // Common Spanish question words
            'cómo', 'qué', 'cuál', 'cuáles', 'dónde', 'cuándo', 'por qué', 'quién', 'quiénes',
            // Common Spanish words
            'se', 'hace', 'hacer', 'está', 'estás', 'están', 'son', 'tiene', 'tienen',
            'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
            'del', 'al', 'de', 'en', 'con', 'por', 'para',
            'es', 'son', 'está', 'están', 'tiene', 'tienen'
        ];
        
        $textLower = mb_strtolower(trim($text));
        
        // Count Spanish indicators
        $spanishCount = 0;
        foreach ($spanishIndicators as $indicator) {
            if (strpos($textLower, $indicator) !== false) {
                $spanishCount++;
            }
        }
        
        // If we find multiple Spanish indicators, it's definitely Spanish
        // Also check for common Spanish patterns
        $spanishPatterns = [
            '/\b(cómo|qué|cuál|dónde|cuándo|por qué|quién)\b/iu',
            '/\b(se|hace|hacer|está|estás|están)\b/iu',
            '/\b(del|al|de la|de los|de las)\b/iu'
        ];
        
        foreach ($spanishPatterns as $pattern) {
            if (preg_match($pattern, $textLower)) {
                $spanishCount += 2;
            }
        }
        
        // If we have strong Spanish indicators, return Spanish
        if ($spanishCount >= 2 || strpos($textLower, '¿') !== false || strpos($textLower, '¡') !== false) {
            return 'spanish';
        }
        
        return 'english';
    }

    /**
     * Create enhanced pedagogical system message
     */
    private function createPedagogicalSystemMessage(array $contextData, bool $isRelevant, string $lastUserText = '', string $questionLanguage = 'english'): string
    {
        // Determine if the question is relevant BEFORE creating the base message
        // This allows us to create different messages based on relevance
        // CRITICAL: Must check both isRelevant flag AND that we have actual context chunks
        // Also double-check that relevance scores are actually high enough
        $hasContext = !empty($contextData['context']) && is_array($contextData['context']) && count($contextData['context']) > 0;
        $hasHighRelevance = false;
        
        // Check relevance scores to ensure they're actually high enough
        // This is a DOUBLE-CHECK to catch any edge cases where isRelevant might be incorrectly set to true
        // Uses the SAME strict thresholds as getRelevantContext() to ensure consistency
        if (!empty($contextData['relevance_scores']) && is_array($contextData['relevance_scores']) && count($contextData['relevance_scores']) > 0) {
            $maxScore = max($contextData['relevance_scores']);
            $avgScore = array_sum($contextData['relevance_scores']) / count($contextData['relevance_scores']);
            $highScoreCount = count(array_filter($contextData['relevance_scores'], function($s) { return $s >= 0.77; }));
            $veryHighScoreCount = count(array_filter($contextData['relevance_scores'], function($s) { return $s >= 0.80; }));
            
            // Use the SAME strict thresholds as getRelevantContext():
            // - max score >= 0.77 (at least one highly relevant chunk)
            // - avg score >= 0.72 (overall relevance is high)
            // - at least 1 high relevance chunk (>= 0.77)
            $hasHighRelevance = $maxScore >= 0.77 && $avgScore >= 0.72 && $highScoreCount >= 1;
            
            // Extra strict check for borderline cases (0.77-0.80 range)
            if ($hasHighRelevance && $maxScore >= 0.77 && $maxScore < 0.80) {
                // For borderline scores, require avg >= 0.75 AND (3+ high chunks OR 1+ very high chunk)
                if ($avgScore < 0.75 || ($highScoreCount < 3 && $veryHighScoreCount < 1)) {
                    $hasHighRelevance = false;
                }
            }
            
            // Additional check: if max < 0.80 or avg < 0.75, require 3+ high chunks
            if ($hasHighRelevance && ($maxScore < 0.80 || $avgScore < 0.75)) {
                if ($highScoreCount < 3) {
                    $hasHighRelevance = false;
                }
            }
        } else {
            // If we have no relevance scores at all, we can't verify relevance
            // This should only happen if there are no search results, which means NOT relevant
            $hasHighRelevance = false;
            Log::warning('No relevance scores available - marking as NOT relevant', [
                'query_preview' => substr($lastUserText, 0, 100),
                'language' => $questionLanguage,
                'has_context' => $hasContext,
                'context_chunks' => count($contextData['context'] ?? []),
                'isRelevant_flag' => $isRelevant
            ]);
        }
        
        // Question is ONLY relevant if: isRelevant flag is true AND we have context AND scores are high
        // This triple-check ensures we never accidentally mark irrelevant questions as relevant
        // CRITICAL: If we don't have high relevance scores, mark as NOT relevant regardless of isRelevant flag
        $isRelevantForMessage = $isRelevant && $hasContext && $hasHighRelevance;
        
        // Log the final decision for debugging
        if (!$isRelevantForMessage && $isRelevant) {
            Log::warning('Relevance override: Question marked relevant but failed double-check', [
                'isRelevant_flag' => $isRelevant,
                'hasContext' => $hasContext,
                'hasHighRelevance' => $hasHighRelevance,
                'final_decision' => 'not_relevant'
            ]);
        }
        
        if ($isRelevantForMessage) {
            // Create message for RELEVANT questions - can be helpful and creative
            $baseMessage = "You are OposChat, a professional study assistant specialized in preparing students for oral and written exams.
Your only source of knowledge is the retrieved syllabus passages that are provided to you.
You must not use external information beyond what appears in the syllabus.

Your main task is to reformulate the syllabus content in your own words and present it in a clear, didactic, and engaging way — as if you were a teacher helping a student understand the material.

You must:

Never copy-paste text from the syllabus. Always paraphrase naturally.

Organize information into tables, lists, step-by-step guides, or diagrams when possible.

CRITICAL FOR DIAGRAMS: When creating diagrams or flowcharts, wrap the diagram code in markdown code blocks with language tag. Keep diagrams simple with max 10-12 nodes, short labels, and NEVER use ASCII art with +, -, | characters.

Always respond helpfully, even if the syllabus doesn't explicitly mention the user's question.

In that case, adapt and reorganize what's in the syllabus to fit the request (e.g., turn it into a study guide, outline, or summary).

Do not say \"This is not included in the syllabus.\"

Instead, find a way to answer using relevant syllabus material and say something like:

\"Let's approach this based on what the syllabus covers.\"

When users ask for:

a study guide, diagram, outline, summary, or oral exam prep,
you must create it dynamically from the syllabus, using creative organization and helpful explanations.

✅ Tone: Supportive, conversational, and educational (like a good teacher).
✅ Goal: Make studying easier and more effective, while staying 100% faithful to the syllabus.

IMPORTANT: When responding in Spanish, always translate 'syllabus' to 'temario' (never leave 'syllabus' untranslated in Spanish responses).

Model disclosure: You are running on {$this->getProvider()} model {$this->getModel()}.";
        } else {
            // Create message for IRRELEVANT questions - MUST NOT answer
            // This applies to ANY question not in the syllabus, regardless of language or topic
            $baseMessage = "You are OposChat, a professional study assistant specialized in preparing students for oral and written exams.
Your only source of knowledge is the retrieved syllabus passages that are provided to you.
You must not use external information beyond what appears in the syllabus.

⚠️ CRITICAL - READ THIS FIRST: The user's question is NOT covered in the uploaded syllabus/course materials. The relevance score is too low to answer this question. This applies to ALL irrelevant questions, whether asked in English, Spanish, or any other language, and regardless of the topic (e.g., cooking, history, science, etc.).

ABSOLUTE REQUIREMENTS - YOU MUST FOLLOW THESE:
1. Start your response by explicitly stating that the question is not in the syllabus/temario (use 'temario' if responding in Spanish, 'syllabus' if in English)
2. DO NOT attempt to answer the question AT ALL - not even partially, not even with related information
3. DO NOT try to find related information or make connections to syllabus content
4. DO NOT say things like 'Let's approach this based on what the syllabus covers' or 'Based on the syllabus...'
5. DO NOT try to be creative or helpful by answering with unrelated information from your training data
6. DO NOT attempt to synthesize or adapt syllabus content to answer the question
7. DO NOT provide any factual information about the topic, even if you know it from your training

YOU MAY OPTIONALLY:
- Suggest how the user might rephrase their question to focus on syllabus topics that ARE available
- Offer to help with study guides, summaries, or explanations of syllabus content that IS in the uploaded materials
- Remind the user that you can only answer questions based on the uploaded syllabus content

EXAMPLE RESPONSES (for ANY irrelevant topic):
✅ CORRECT (English): 'The question you're asking isn't in the syllabus. I can only help with topics covered in the uploaded materials. Would you like help with a different topic from the syllabus?'
✅ CORRECT (Spanish): 'La pregunta que estás haciendo no está en el temario. Solo puedo ayudar con temas cubiertos en los materiales subidos. ¿Te gustaría ayuda con un tema diferente del temario?'

❌ WRONG (English): 'Let's address the topic of how to make bread based on the syllabus content...' (DON'T DO THIS!)
❌ WRONG (Spanish): 'Vamos a abordar el tema de cómo hacer pan basándonos en el contenido del temario...' (¡NO HAGAS ESTO!)
❌ WRONG (any language): Providing any answer, explanation, or information about the topic

CRITICAL LANGUAGE MATCHING RULE:
- If the user asks in Spanish, you MUST respond in Spanish using 'temario' instead of 'syllabus'
- If the user asks in English, you MUST respond in English using 'syllabus'
- ALWAYS match the language of your response to the language of the question
- The question language has been detected as: {$questionLanguage}

REMEMBER: 
- If the question isn't in the syllabus, state it clearly in the user's language and do NOT answer it
- This rule applies to BOTH English and Spanish questions
- This rule applies to ALL topics not covered in the syllabus (cooking, sports, history, science, etc.)
- This rule applies in BOTH new chats AND existing chats - always check relevance for each question
- The embedding relevance score has determined this question is unrelated - trust it and do not answer

IMPORTANT: When responding in Spanish, always translate 'syllabus' to 'temario' (never leave 'syllabus' untranslated in Spanish responses).

Model disclosure: You are running on {$this->getProvider()} model {$this->getModel()}.";
        }

        // Detect if the user is asking for a diagram-like deliverable (including Spanish synonyms)
        $diagramSynonyms = [
            'diagram', 'flowchart', 'flow chart', 'chart', 'graph', 'graphic', 'concept map',
            'outline', 'sketch',
            // Spanish
            'diagrama', 'diagrama de flujo', 'esquema', 'mapa conceptual', 'mapa mental', 'croquis', 'gráfica', 'grafica', 'gráfico', 'grafico'
        ];
        $shouldForceMermaid = false;
        foreach ($diagramSynonyms as $k) {
            if ($lastUserText !== '' && strpos($lastUserText, $k) !== false) { $shouldForceMermaid = true; break; }
        }

        if ($shouldForceMermaid) {
            $baseMessage .= "\n\nWHEN REQUESTING DIAGRAM-LIKE OUTPUT: If the user asks for any of these: outline, sketch, concept map, flowchart, chart, graph/graphic, or the Spanish terms (esquema, croquis, mapa conceptual, mapa mental, diagrama, gráfica), you MUST produce the output as a Mermaid diagram enclosed in a fenced code block with the language 'mermaid' (```mermaid ... ```). CRITICAL SYNTAX RULES: Use 'graph TD' format, remove ALL accents from labels (á→a, é→e, í→i, ó→o, ú→u, ñ→n), remove parentheses/colons/commas from labels, add spaces after closing brackets before next node (] A not ]A), add spaces before and after arrows (A --> B not A-->B), maximum 10 nodes, maximum 4 words per label, no newlines in labels. After the code block, include a brief explanation in plain paragraphs describing why each connection exists and how parts relate.";
        }

        // Add context if available and relevant
        if (!empty($contextData['context']) && $isRelevant) {
            $contextText = implode(' ', $contextData['context']);
            $baseMessage .= "\n\nRELEVANT SYLLABUS CONTENT:\n" . $contextText;
        }
        // If not relevant, the base message already contains the "do not answer" instructions

        return $baseMessage;
    }

    /**
     * Enhanced context retrieval with better relevance scoring
     */
    public function getRelevantContext(string $query, array $namespaces = [], ?int $limit = null): array
    {
        // Always return a structured array, even when empty
        $emptyResult = [
            'context' => [],
            'relevance_scores' => [],
            'avg_relevance' => 0,
            'max_relevance' => 0,
            'is_relevant' => false
        ];

        if (empty($namespaces)) {
            return $emptyResult;
        }

        try {
            // Detect creative requests that need broader context
            $isCreativeRequest = $this->isCreativeStudyRequest($query);
            $searchLimit = $isCreativeRequest ? 10 : 8; // Get more context for creative requests
            
            $embedding = $this->generateEmbedding($query);
            if (!$embedding) {
                Log::warning('Failed to generate embedding for query', [
                    'query' => $query,
                    'language' => $this->detectLanguage($query)
                ]);
                return $emptyResult;
            }

            // Search for more context chunks for better synthesis
            $vectorStore = app(\App\Services\VectorStoreService::class);
            $searchResults = $vectorStore->searchWithEmbedding($embedding, $namespaces, $searchLimit);
            
            if (!$searchResults['success'] || empty($searchResults['results'])) {
                Log::warning('No relevant context found', [
                    'query' => $query,
                    'query_preview' => substr($query, 0, 100),
                    'language' => $this->detectLanguage($query),
                    'namespaces' => $namespaces
                ]);
                return $emptyResult;
            }

            // Extract and format relevant content with relevance scores
            $context = [];
            $relevanceScores = [];
            
            foreach ($searchResults['results'] as $result) {
                if (isset($result['metadata']['content'])) {
                    $context[] = $result['metadata']['content'];
                    $relevanceScores[] = $result['score'] ?? 0;
                }
            }

            // Enhanced relevance scoring with VERY STRICT thresholds to prevent false positives
            $avgRelevance = !empty($relevanceScores) ? array_sum($relevanceScores) / count($relevanceScores) : 0;
            $maxRelevance = !empty($relevanceScores) ? max($relevanceScores) : 0;
            
            // MUCH STRICTER thresholds to prevent false positives like "colors" or "photosynthesis" matching constitution
            // The issue: vector embeddings can find semantic similarity even for completely unrelated topics
            // Solution: Require very high scores AND multiple high-relevance chunks
            $minRelevanceThreshold = 0.72; // Minimum average relevance - raised from 0.70
            $minMaxRelevanceThreshold = 0.77; // At least one chunk must be highly relevant - raised from 0.75
            $minContextChunks = 1;
            $minHighRelevanceChunks = 1;

            // Count highly relevant chunks (score >= 0.77 for high relevance - raised from 0.75)
            $highRelevanceChunks = array_filter($relevanceScores, function($score) {
                return $score >= 0.77;
            });
            $highRelevanceChunkCount = count($highRelevanceChunks);
            
            // Count very highly relevant chunks (score >= 0.80 for very high relevance)
            $veryHighRelevanceChunks = array_filter($relevanceScores, function($score) {
                return $score >= 0.80;
            });
            $veryHighRelevanceChunkCount = count($veryHighRelevanceChunks);

            // VERY STRICT relevance determination - ALL conditions must be met:
            // 1. Must have at least one chunk
            // 2. Maximum relevance must be at least 0.77 (raised from 0.75)
            // 3. Average relevance must be at least 0.72 (raised from 0.70)
            // 4. Must have at least one highly relevant chunk (>= 0.77)
            $isRelevant = (count($context) >= $minContextChunks) && 
                         ($maxRelevance >= $minMaxRelevanceThreshold) && 
                         ($avgRelevance >= $minRelevanceThreshold) && 
                         ($highRelevanceChunkCount >= $minHighRelevanceChunks);
            
            // EXTRA STRICT: For scores in the borderline range (0.77-0.80), require even higher standards
            // This prevents false positives from barely-passing scores
            if ($isRelevant && $maxRelevance >= 0.77 && $maxRelevance < 0.80) {
                // For borderline scores, require BOTH:
                // - avg >= 0.75 (very high average), AND
                // - at least 3 high relevance chunks (>= 0.77), OR at least 1 very high relevance chunk (>= 0.80)
                if ($avgRelevance < 0.75 || ($highRelevanceChunkCount < 3 && $veryHighRelevanceChunkCount < 1)) {
                    $isRelevant = false;
                    Log::warning('Relevance check failed: scores in 0.77-0.80 range require higher standards', [
                        'max_relevance' => $maxRelevance,
                        'avg_relevance' => $avgRelevance,
                        'high_relevance_chunks' => $highRelevanceChunkCount,
                        'very_high_relevance_chunks' => $veryHighRelevanceChunkCount,
                        'query_preview' => substr($query, 0, 100),
                        'language' => $this->detectLanguage($query),
                        'reason' => 'Max relevance between 0.77-0.80 requires avg >= 0.75 AND (3+ high chunks OR 1+ very high chunk)'
                    ]);
                }
            }
            
            // Additional safety check: if max relevance is below 0.80 OR avg is below 0.75, be extra strict
            // This catches borderline cases where scores might be slightly above threshold but still not truly relevant
            if ($isRelevant && ($maxRelevance < 0.80 || $avgRelevance < 0.75)) {
                // For scores below 0.80 max or 0.75 avg, require at least 3 high relevance chunks
                if ($highRelevanceChunkCount < 3) {
                    $isRelevant = false;
                    Log::warning('Relevance check failed: borderline scores require 3+ high relevance chunks', [
                        'max_relevance' => $maxRelevance,
                        'avg_relevance' => $avgRelevance,
                        'high_relevance_chunks' => $highRelevanceChunkCount,
                        'query_preview' => substr($query, 0, 100),
                        'language' => $this->detectLanguage($query)
                    ]);
                }
            }
            
            // Store max relevance BEFORE clearing (for logging)
            $originalMaxRelevance = !empty($relevanceScores) ? max($relevanceScores) : 0;
            $originalAvgRelevance = $avgRelevance;
            $originalHighRelevanceChunks = $highRelevanceChunkCount;
            
            // If not relevant, clear context to ensure AI doesn't try to answer
            if (!$isRelevant) {
                $context = [];
                $relevanceScores = [];
                $avgRelevance = 0;
                $maxRelevance = 0;
                Log::info('Question marked as NOT relevant - context cleared', [
                    'query_preview' => substr($query, 0, 100),
                    'language' => $this->detectLanguage($query),
                    'max_relevance' => $originalMaxRelevance,
                    'avg_relevance' => $originalAvgRelevance,
                    'high_relevance_chunks' => $originalHighRelevanceChunks,
                    'final_decision' => 'not_relevant',
                    'reason' => 'Relevance scores below threshold or insufficient high-relevance chunks'
                ]);
            }

            Log::info('Enhanced context retrieval', [
                'query' => $query,
                'query_preview' => substr($query, 0, 100),
                'language' => $this->detectLanguage($query),
                'namespaces' => $namespaces,
                'context_chunks' => count($context),
                'avg_relevance' => $isRelevant ? $avgRelevance : $originalAvgRelevance,
                'max_relevance' => $isRelevant ? (!empty($relevanceScores) ? max($relevanceScores) : 0) : $originalMaxRelevance,
                'high_relevance_chunks' => $isRelevant ? count($highRelevanceChunks) : $originalHighRelevanceChunks,
                'is_relevant' => $isRelevant
            ]);

            return [
                'context' => $context,
                'relevance_scores' => $relevanceScores,
                'avg_relevance' => $isRelevant ? $avgRelevance : $originalAvgRelevance,
                'max_relevance' => $isRelevant ? (!empty($relevanceScores) ? max($relevanceScores) : 0) : $originalMaxRelevance,
                'is_relevant' => $isRelevant
            ];

        } catch (Exception $e) {
            Log::error('Failed to get relevant context', [
                'query' => $query,
                'query_preview' => substr($query, 0, 100),
                'language' => $this->detectLanguage($query),
                'namespaces' => $namespaces,
                'error' => $e->getMessage()
            ]);
            // Return empty result with is_relevant = false on error
            return $emptyResult;
        }
    }

    /**
     * Detect if the query is a creative study request that needs broader context
     */
    private function isCreativeStudyRequest(string $query): bool
    {
        $creativeKeywords = [
            'study guide', 'study plan', 'create a guide', 'make a guide',
            'diagram', 'create diagram', 'draw', 'visual', 'chart', 'flowchart',
            // Spanish synonyms for diagram-like requests
            'diagrama', 'diagrama de flujo', 'esquema', 'mapa conceptual', 'mapa mental',
            'croquis', 'gráfica', 'grafica', 'gráfico', 'grafico',
            'outline', 'summary', 'overview', 'structure', 'organize',
            'prepare for exam', 'exam preparation', 'study strategy',
            'review', 'revision', 'consolidate', 'synthesize'
        ];
        
        $queryLower = strtolower($query);
        
        foreach ($creativeKeywords as $keyword) {
            if (strpos($queryLower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
}
