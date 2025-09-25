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
            $systemMessageContent = $this->createPedagogicalSystemMessage($contextData, $isRelevant);
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
            $systemMessageContent = $this->createPedagogicalSystemMessage($contextData, $isRelevant);
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
    private function createPedagogicalSystemMessage(array $contextData, bool $isRelevant): string
    {
        $baseMessage = "You are an expert educational AI assistant specialized in exam preparation. Your role is to help students learn effectively using ONLY the provided course materials/syllabus.

PEDAGOGICAL APPROACH:
1. SYNTHESIZE and EXPLAIN information from the syllabus in a clear, educational manner
2. Create STRUCTURED responses with headings, bullet points, and logical flow
3. Generate STUDY MATERIALS when helpful: tables, comparisons, summaries, step-by-step guides
4. Provide CONTEXT and CONNECTIONS between different concepts in the syllabus
5. Use EXAMPLES and ANALOGIES found in the course materials to clarify concepts
6. Break down complex topics into digestible, memorable parts

RESPONSE FORMATTING:
- Use markdown formatting (##, ###, **bold**, *italic*, bullet points, numbered lists)
- Create tables when comparing concepts: | Concept | Definition | Example |
- Use step-by-step numbered lists for processes
- Include relevant examples from the syllabus
- Structure information hierarchically (main points → sub-points → details)

STRICT GROUNDING RULES:
1. ONLY use information from the provided course materials
2. If information is not in the syllabus, respond: 'This information is not included in the syllabus.'
3. Do NOT add external knowledge, but DO synthesize and structure the syllabus content
4. Make connections between different parts of the syllabus when relevant
5. Transform raw syllabus text into educational, study-friendly formats

Model disclosure: You are running on {$this->getProvider()} model {$this->getModel()}.";

        // Add context if available and relevant
        if (!empty($contextData['context']) && $isRelevant) {
            $contextText = implode(' ', $contextData['context']);
            $baseMessage .= "\n\nRELEVANT SYLLABUS CONTENT:\n" . $contextText;
            
            // Add pedagogical instructions for context usage
            $baseMessage .= "\n\nINSTRUCTIONS FOR USING THIS CONTENT:
- Synthesize this information into a clear, educational response
- Create structured explanations with headings and bullet points
- Generate study materials (tables, comparisons, summaries) when appropriate
- Connect related concepts from different parts of the content
- Provide step-by-step explanations for processes
- Use examples from the content to illustrate key points
- Create memory aids and study techniques when helpful
- Break down complex procedures into numbered steps
- Highlight key terms and concepts with **bold** formatting
- Use bullet points for lists and comparisons
- Create tables for structured data comparison";
            
        } elseif (!$isRelevant) {
            // Strict out-of-scope instruction
            $baseMessage .= "\n\nIMPORTANT: The user's question is outside the scope of the uploaded course materials. You MUST respond with: 'This information is not included in the syllabus.' Do NOT provide any general knowledge or external information.";
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
            $embedding = $this->generateEmbedding($query);
            if (!$embedding) {
                Log::warning('Failed to generate embedding for query', ['query' => $query]);
                return [];
            }

            // Search for more context chunks for better synthesis
            $vectorStore = app(\App\Services\VectorStoreService::class);
            $searchResults = $vectorStore->searchWithEmbedding($embedding, $namespaces, 8); // Increased from 5 to 8
            
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

            // Enhanced relevance scoring with multiple criteria
            $avgRelevance = !empty($relevanceScores) ? array_sum($relevanceScores) / count($relevanceScores) : 0;
            $minRelevanceThreshold = 0.75; // Higher threshold for better accuracy
            $minContextChunks = 2;
            $minHighRelevanceChunks = 1;

            // Count highly relevant chunks
            $highRelevanceChunks = array_filter($relevanceScores, function($score) {
                return $score >= 0.80; // Higher threshold for high relevance
            });

            // More precise relevance determination
            $isRelevant = (count($context) >= $minContextChunks) && 
                         (($avgRelevance >= $minRelevanceThreshold) || 
                          (count($highRelevanceChunks) >= $minHighRelevanceChunks) ||
                          (max($relevanceScores) >= 0.85)); // Single very relevant chunk

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
}
