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
        $baseMessage = "You are an expert educational AI assistant specialized in exam preparation. You MUST use ONLY the provided course materials/syllabus.

STRICT GROUNDING RULES (ENFORCED):
1) Use only facts explicitly present in the provided syllabus content. Do NOT use general knowledge or training data.
2) If the syllabus does not include the requested information, reply exactly: 'This information is not included in the syllabus.'
3) Do not invent explanations, examples, numbers, formulas, steps, or definitions that are not present in the syllabus.
4) When listing items (bullets, steps, table rows), include ONLY items that exist in the syllabus. Do NOT add extra rows/lines beyond what is present.
5) Keep the response concise and didactic. Rephrase syllabus content in your own words, but do not expand it with outside knowledge.
6) If the retrieved context is short, answer only with what is present. Do NOT speculate or extrapolate.

PEDAGOGICAL STYLE (within the rules above):
- Organize content with short headings, clear bullets, and step-by-step structure when appropriate.
- Prefer clarity over length; avoid redundancy.
- If the user asks for comparison or a table, populate it strictly with syllabus facts.

Model disclosure: You are running on {$this->getProvider()} model {$this->getModel()}.";

        // Add context if available and relevant
        if (!empty($contextData['context']) && $isRelevant) {
            $contextText = implode(' ', $contextData['context']);
            $baseMessage .= "\n\nRELEVANT SYLLABUS CONTENT:\n" . $contextText;
            
            // Add explicit constraints for context usage
            $baseMessage .= "\n\nUSAGE CONSTRAINTS:\n- Base every statement on the content above.\n- Do NOT add new bullets/rows beyond the facts provided.\n- If a detail is missing above, state: 'This information is not included in the syllabus.'";
            
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
