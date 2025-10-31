<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EnhancedAIProviderService extends AIProviderService
{
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
                $isRelevant = $contextData['is_relevant'] ?? true;
            }
        }

        // Create enhanced pedagogical system message
        if (!empty($namespaces)) {
            // Detect if the user is asking for a diagram-like deliverable
            $lastUserMessage = $messages[count($messages) - 1] ?? null;
            $lastUserText = ($lastUserMessage && ($lastUserMessage['role'] ?? '') === 'user') ? strtolower($lastUserMessage['content'] ?? '') : '';

            $systemMessageContent = $this->createPedagogicalSystemMessage($contextData, $isRelevant, $lastUserText);
        } else {
            $systemMessageContent = $options['system_message'] ?? config('ai.defaults.system_message');
        }

        $systemMessage = [
            'role' => 'system',
            'content' => $systemMessageContent
        ];
        
        // Insert system message at the beginning
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
                $isRelevant = $contextData['is_relevant'] ?? true;
            }
        }

        // Create enhanced pedagogical system message
        if (!empty($namespaces)) {
            // Detect if the user is asking for a diagram-like deliverable
            $lastUserMessage = $messages[count($messages) - 1] ?? null;
            $lastUserText = ($lastUserMessage && ($lastUserMessage['role'] ?? '') === 'user') ? strtolower($lastUserMessage['content'] ?? '') : '';

            $systemMessageContent = $this->createPedagogicalSystemMessage($contextData, $isRelevant, $lastUserText);
        } else {
            $systemMessageContent = $options['system_message'] ?? config('ai.defaults.system_message');
        }

        $systemMessage = [
            'role' => 'system',
            'content' => $systemMessageContent
        ];
        
        // Insert system message at the beginning
        array_unshift($messages, $systemMessage);

        return $this->streamChatCompletion($messages, $callback, $options);
    }

    /**
     * Create enhanced pedagogical system message
     */
    private function createPedagogicalSystemMessage(array $contextData, bool $isRelevant, string $lastUserText = ''): string
    {
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
            $baseMessage .= "\n\nWHEN REQUESTING DIAGRAM-LIKE OUTPUT: If the user asks for any of these: outline, sketch, concept map, flowchart, chart, graph/graphic, or the Spanish terms (esquema, croquis, mapa conceptual, mapa mental, diagrama, gráfica), you MUST produce the output as a Mermaid diagram enclosed in a fenced code block with the language 'mermaid' (```mermaid ... ```). After the code block, include a brief explanation in plain paragraphs describing why each connection exists and how parts relate.";
        }

        // Add context if available and relevant
        if (!empty($contextData['context']) && $isRelevant) {
            $contextText = implode(' ', $contextData['context']);
            $baseMessage .= "\n\nRELEVANT SYLLABUS CONTENT:\n" . $contextText;
            
        } elseif (!$isRelevant || empty($contextData['context'])) {
            // Explicit instruction to state that the question is not in the syllabus
            $baseMessage .= "\n\nCRITICAL INSTRUCTION - READ THIS CAREFULLY:

The user's question is NOT covered in the uploaded syllabus/course materials. The relevance score is too low to answer this question.

YOU MUST:
1. Start your response by explicitly stating: 'The question you're asking isn't in the syllabus' (or a similar clear statement)
2. DO NOT attempt to answer the question at all
3. DO NOT try to find related information or make connections to syllabus content
4. DO NOT say things like 'Let's approach this based on what the syllabus covers'

YOU MAY OPTIONALLY:
- Suggest how the user might rephrase their question to focus on syllabus topics that ARE available
- Offer to help with study guides, summaries, or explanations of syllabus content that IS in the uploaded materials
- Remind the user that you can only answer questions based on the uploaded syllabus content

EXAMPLE RESPONSES:
✅ CORRECT: 'The question you're asking isn't in the syllabus. I can only help with topics covered in the SAT preparation materials that were uploaded. Would you like help with a different topic from the syllabus?'

❌ WRONG: 'Let's address the topic of how to make bread based on the syllabus content...' (DON'T DO THIS!)

REMEMBER: If the question isn't in the syllabus, state it clearly and do NOT answer it.";
        }

        return $baseMessage;
    }

    /**
     * Enhanced context retrieval with better relevance scoring
     */
    public function getRelevantContext(string $query, array $namespaces = []): array
    {
        if (empty($namespaces)) {
            return [];
        }

        try {
            // Detect creative requests that need broader context
            $isCreativeRequest = $this->isCreativeStudyRequest($query);
            $searchLimit = $isCreativeRequest ? 10 : 8; // Get more context for creative requests
            
            $embedding = $this->generateEmbedding($query);
            if (!$embedding) {
                Log::warning('Failed to generate embedding for query', ['query' => $query]);
                return [];
            }

            // Search for more context chunks for better synthesis
            $vectorStore = app(\App\Services\VectorStoreService::class);
            $searchResults = $vectorStore->searchWithEmbedding($embedding, $namespaces, $searchLimit);
            
            if (!$searchResults['success'] || empty($searchResults['results'])) {
                Log::warning('No relevant context found', [
                    'query' => $query,
                    'namespaces' => $namespaces
                ]);
                return [];
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

            // Enhanced relevance scoring with very strict thresholds
            $avgRelevance = !empty($relevanceScores) ? array_sum($relevanceScores) / count($relevanceScores) : 0;
            $maxRelevance = !empty($relevanceScores) ? max($relevanceScores) : 0;
            
            // Very strict thresholds: require high relevance scores for unrelated topics like "how to make bread"
            $minRelevanceThreshold = 0.70; // Minimum average relevance - raised from 0.65
            $minMaxRelevanceThreshold = 0.75; // At least one chunk must be highly relevant - raised from 0.70
            $minContextChunks = 1;
            $minHighRelevanceChunks = 1;

            // Count highly relevant chunks (score >= 0.75 for high relevance)
            $highRelevanceChunks = array_filter($relevanceScores, function($score) {
                return $score >= 0.75;
            });

            // Very strict relevance determination: 
            // 1. Must have at least one chunk
            // 2. Maximum relevance must be at least 0.75
            // 3. Average relevance must be at least 0.70 OR we must have at least one highly relevant chunk (>= 0.75)
            // If any of these fail, mark as NOT relevant
            $isRelevant = (count($context) >= $minContextChunks) && 
                         ($maxRelevance >= $minMaxRelevanceThreshold) && 
                         (($avgRelevance >= $minRelevanceThreshold) || count($highRelevanceChunks) >= $minHighRelevanceChunks);
            
            // If not relevant, clear context to ensure AI doesn't try to answer
            if (!$isRelevant) {
                $context = [];
                $relevanceScores = [];
                $avgRelevance = 0;
                $maxRelevance = 0;
            }

            Log::info('Enhanced context retrieval', [
                'query' => $query,
                'namespaces' => $namespaces,
                'context_chunks' => count($context),
                'avg_relevance' => $avgRelevance,
                'max_relevance' => max($relevanceScores),
                'high_relevance_chunks' => count($highRelevanceChunks),
                'is_relevant' => $isRelevant
            ]);

            return [
                'context' => $context,
                'relevance_scores' => $relevanceScores,
                'avg_relevance' => $avgRelevance,
                'is_relevant' => $isRelevant
            ];

        } catch (Exception $e) {
            Log::error('Failed to get relevant context', [
                'query' => $query,
                'namespaces' => $namespaces,
                'error' => $e->getMessage()
            ]);
            return [];
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
