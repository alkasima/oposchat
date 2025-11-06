<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AIProviderService
{
    private string $provider;
    private array $config;
    public function __construct()
    {
        $this->provider = config('ai.provider', 'openai');
        $this->config = config('ai.providers.' . $this->provider);


        // Override with DB settings if available
        try {
            $settings = app(\App\Services\SettingsService::class);
            if ($this->provider === 'openai') {
                $dbKey = $settings->get('OPENAI_API_KEY');
                $dbModel = $settings->get('OPENAI_MODEL');

                
                if (!empty($dbKey)) {
                    $this->config['api_key'] = $dbKey;
                }
                if (!empty($dbModel)) {
                    $this->config['model'] = $dbModel;
                }
            } elseif ($this->provider === 'gemini') {
                $dbKey = $settings->get('GEMINI_API_KEY');
                $dbModel = $settings->get('GEMINI_MODEL');
                if (!empty($dbKey)) {
                    $this->config['api_key'] = $dbKey;
                }
                if (!empty($dbModel)) {
                    $this->config['model'] = $dbModel;
                }
            }
        } catch (\Throwable $e) {
            // If settings service fails (e.g., during migration), keep config fallback
        }
    }

    /**
     * Send a chat completion request to the configured AI provider
     *
     * @param array $messages Array of messages in format [['role' => 'user', 'content' => 'message']]
     * @param array $options Additional options like temperature, max_tokens, etc.
     * @return array Response with 'content' and 'usage' keys
     * @throws Exception
     */
    public function chatCompletion(array $messages, array $options = []): array
    {
        switch ($this->provider) {
            case 'openai':
                return $this->openAIChatCompletion($messages, $options);
            case 'gemini':
                return $this->geminiChatCompletion($messages, $options);
            default:
                throw new Exception("Unsupported AI provider: {$this->provider}");
        }
    }

    /**
     * Send a streaming chat completion request to the configured AI provider
     *
     * @param array $messages Array of messages in format [['role' => 'user', 'content' => 'message']]
     * @param callable $callback Function to call for each chunk: function(string $chunk, bool $isComplete)
     * @param array $options Additional options like temperature, max_tokens, etc.
     * @return array Final response with 'content' and 'usage' keys
     * @throws Exception
     */
    public function streamChatCompletion(array $messages, callable $callback, array $options = []): array
    {
        try {
            switch ($this->provider) {
                case 'openai':
                    return $this->openAIStreamCompletion($messages, $callback, $options);
                case 'gemini':
                    return $this->geminiStreamCompletion($messages, $callback, $options);
                default:
                    throw new Exception("Unsupported AI provider for streaming: {$this->provider}");
            }
        } catch (Exception $e) {
            // Don't fallback if streaming was stopped by user - re-throw the exception
            if (strpos($e->getMessage(), 'Streaming stopped by user') !== false) {
                throw $e;
            }
            
            Log::error('Streaming completion failed, falling back to regular completion', [
                'provider' => $this->provider,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to non-streaming completion
            $result = $this->chatCompletion($messages, $options);
            $callback($result['content'], true);
            return $result;
        }
    }

    /**
     * OpenAI Streaming Chat Completion
     */
    private function openAIStreamCompletion(array $messages, callable $callback, array $options = []): array
    {
        $payload = [
            'model' => $this->config['model'],
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'stream' => true,
        ];

        $fullContent = '';
        $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        $contentBuffer = '';
        $isComplete = false;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'text/event-stream',
            ])->timeout(1200)->withOptions([
                'stream' => true,
                'read_timeout' => 120,
            ])->post('https://api.openai.com/v1/chat/completions', $payload);

            if (!$response->successful()) {
                Log::error('OpenAI Streaming API Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new Exception('OpenAI streaming API request failed: ' . $response->body());
            }

            $body = $response->getBody();
            $lineBuffer = '';

            while (!$body->eof() && !$isComplete) {
                $chunk = $body->read(1024);
                $lineBuffer .= $chunk;

                // Process complete SSE lines
                $processedData = $this->parseOpenAISSEChunks($lineBuffer);
                
                foreach ($processedData['events'] as $eventData) {
                    if ($eventData === '[DONE]') {
                        $isComplete = true;
                        break;
                    }

                    $result = $this->processOpenAIStreamChunk($eventData, $contentBuffer, $usage);
                    
                    if ($result['hasContent']) {
                        $fullContent .= $result['content'];
                        $callback($result['content'], false);
                    }

                    if ($result['isComplete']) {
                        $isComplete = true;
                        break;
                    }
                }

                $lineBuffer = $processedData['remainingBuffer'];
            }

            // Final callback to indicate completion
            if ($isComplete) {
                $callback('', true);
            }

        } catch (Exception $e) {
            Log::error('OpenAI streaming error', ['error' => $e->getMessage()]);
            throw $e;
        }

        return [
            'content' => $fullContent,
            'usage' => $usage
        ];
    }

    /**
     * Parse OpenAI Server-Sent Events chunks
     */
    private function parseOpenAISSEChunks(string &$buffer): array
    {
        $events = [];
        
        while (($pos = strpos($buffer, "\n\n")) !== false) {
            $block = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 2);
            
            $lines = explode("\n", $block);
            $eventData = null;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                
                if (str_starts_with($line, 'data: ')) {
                    $eventData = substr($line, 6);
                    break;
                }
            }
            
            if ($eventData !== null) {
                $events[] = $eventData;
            }
        }
        
        return [
            'events' => $events,
            'remainingBuffer' => $buffer
        ];
    }

    /**
     * Process individual OpenAI stream chunk
     */
    private function processOpenAIStreamChunk(string $data, string &$contentBuffer, array &$usage): array
    {
        if ($data === '[DONE]') {
            return ['hasContent' => false, 'content' => '', 'isComplete' => true];
        }

        $json = json_decode($data, true);
        if (!$json || !isset($json['choices'][0])) {
            return ['hasContent' => false, 'content' => '', 'isComplete' => false];
        }

        $choice = $json['choices'][0];
        $hasContent = false;
        $content = '';
        $isComplete = false;
        
        // Handle content delta with progressive buffering
        if (isset($choice['delta']['content'])) {
            $content = $choice['delta']['content'];
            $contentBuffer .= $content;
            $hasContent = true;
        }

        // Handle usage information
        if (isset($json['usage'])) {
            $usage = [
                'prompt_tokens' => $json['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $json['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $json['usage']['total_tokens'] ?? 0,
            ];
        }

        // Check completion status
        if (isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
            $isComplete = true;
        }

        return [
            'hasContent' => $hasContent,
            'content' => $content,
            'isComplete' => $isComplete
        ];
    }

    /**
     * OpenAI Chat Completion
     */
    private function openAIChatCompletion(array $messages, array $options = []): array
    {
        $payload = [
            'model' => $this->config['model'],
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'stream' => false,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type' => 'application/json',
        ])->timeout(600)->post('https://api.openai.com/v1/chat/completions', $payload);

        if (!$response->successful()) {
            Log::error('OpenAI API Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new Exception('OpenAI API request failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => [
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $data['usage']['total_tokens'] ?? 0,
            ]
        ];
    }

    /**
     * Gemini Streaming Chat Completion
     */
    private function geminiStreamCompletion(array $messages, callable $callback, array $options = []): array
    {
        Log::info('Starting Gemini streaming completion');
        
        // Use regular Gemini API and simulate streaming
        try {
            $result = $this->geminiChatCompletion($messages, $options);
            $fullContent = $result['content'];
            
            Log::info('Gemini response received, simulating streaming', ['content_length' => strlen($fullContent)]);
            
            if (empty($fullContent)) {
                throw new Exception('Empty response from Gemini API');
            }
            
            // Simulate streaming by breaking response into chunks
            $words = explode(' ', $fullContent);
            $streamedContent = '';
            
            foreach ($words as $index => $word) {
                $chunk = $word . ($index < count($words) - 1 ? ' ' : '');
                $streamedContent .= $chunk;
                
                // Call the callback with each chunk
                $callback($chunk, false);
                
                // Small delay to simulate streaming (reduced for better UX)
                usleep(30000); // 30ms delay between words
            }
            
            // Final callback to indicate completion
            $callback('', true);
            
            Log::info('Gemini streaming simulation completed', ['total_words' => count($words)]);
            
            return [
                'content' => $fullContent,
                'usage' => $result['usage']
            ];
            
        } catch (Exception $e) {
            Log::error('Gemini streaming simulation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }



    /**
     * Gemini Chat Completion
     */
    private function geminiChatCompletion(array $messages, array $options = []): array
    {
        // Convert OpenAI format to Gemini format
        $contents = [];
        foreach ($messages as $message) {
            $role = $message['role'] === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $message['content']]]
            ];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 1000,
            ]
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->config['model']}:generateContent";
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(600)->post($url . '?key=' . $this->config['api_key'], $payload);

        if (!$response->successful()) {
            Log::error('Gemini API Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new Exception('Gemini API request failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['candidates'][0]['content']['parts'][0]['text'] ?? '',
            'usage' => [
                'prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total_tokens' => $data['usageMetadata']['totalTokenCount'] ?? 0,
            ]
        ];
    }

    /**
     * Get the current provider name
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Get the current model name
     */
    public function getModel(): string
    {
        return $this->config['model'];
    }

    /**
     * Clean up streaming resources and handle completion
     */
    public function cleanupStreamingSession(string $sessionId): void
    {
        try {
            // Log completion for monitoring
            Log::info('Streaming session completed', [
                'session_id' => $sessionId,
                'provider' => $this->provider
            ]);
            
            // Additional cleanup logic can be added here
            // such as clearing temporary buffers, closing connections, etc.
            
        } catch (Exception $e) {
            Log::error('Error cleaning up streaming session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Validate streaming capability for current provider
     */
    public function supportsStreaming(): bool
    {
        return in_array($this->provider, ['openai', 'gemini']);
    }

    /**
     * Generate embedding for text using OpenAI
     */
    public function generateEmbedding(string $text): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/embeddings', [
                'model' => 'text-embedding-ada-002',
                'input' => $text,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'][0]['embedding'] ?? null;
            }

            Log::error('Failed to generate embedding', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception generating embedding', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get relevant context based on course namespaces using vector search
     */
    public function getRelevantContext(string $query, array $namespaces = []): array
    {
        if (empty($namespaces)) {
            return [];
        }

        try {
            // Detect creative requests that need broader context
            $isCreativeRequest = $this->isCreativeStudyRequest($query);
            $searchLimit = $isCreativeRequest ? 10 : 5; // Get more context for creative requests
            
            // Generate embedding for the query
            $embedding = $this->generateEmbedding($query);
            
            if (!$embedding) {
                Log::warning('Failed to generate embedding for query', [
                    'query' => $query
                ]);
                return [];
            }

            // Search for relevant content using vector database
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
                    // Store relevance score if available
                    $relevanceScores[] = $result['score'] ?? 0;
                }
            }

            // Check if we have sufficient relevant content with very strict thresholds
            $avgRelevance = !empty($relevanceScores) ? array_sum($relevanceScores) / count($relevanceScores) : 0;
            $maxRelevance = !empty($relevanceScores) ? max($relevanceScores) : 0;
            
            // Very strict thresholds: require high relevance scores to prevent answering unrelated questions
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

            Log::info('Retrieved relevant context', [
                'query' => $query,
                'namespaces' => $namespaces,
                'context_chunks' => count($context),
                'avg_relevance' => $avgRelevance,
                'min_relevance_threshold' => $minRelevanceThreshold,
                'min_context_chunks' => $minContextChunks,
                'high_relevance_chunks' => count($highRelevanceChunks),
                'min_high_relevance_chunks' => $minHighRelevanceChunks,
                'is_relevant' => $isRelevant
            ]);

            return [
                'context' => $context,
                'relevance_scores' => $relevanceScores,
                'avg_relevance' => $avgRelevance,
                'is_relevant' => $isRelevant
            ];

        } catch (Exception $e) {
            Log::error('Failed to retrieve relevant context', [
                'query' => $query,
                'namespaces' => $namespaces,
                'error' => $e->getMessage()
            ]);
            
            return [
                'context' => [],
                'relevance_scores' => [],
                'avg_relevance' => 0,
                'is_relevant' => false
            ];
        }
    }

    /**
     * Enhanced chat completion with course-specific context
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

        // Create enhanced syllabus-based system message for creative study assistance
        if (!empty($namespaces)) {
            // Enhanced syllabus-based system message that encourages synthesis and creativity
            $systemMessageContent = "You are OposChat, a professional study assistant specialized in preparing students for oral and written exams.
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

            // Add context to system message if available and relevant
            if (!empty($contextData['context']) && $isRelevant) {
                $contextText = implode(' ', $contextData['context']);
                $systemMessageContent .= "\n\nRELEVANT SYLLABUS CONTENT:\n" . $contextText;
            } elseif (!empty($namespaces) && (!$isRelevant || empty($contextData['context']))) {
                // Explicit instruction to state that the question is not in the syllabus
                $systemMessageContent .= "\n\nCRITICAL INSTRUCTION - READ THIS CAREFULLY:

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
        } else {
            // Use custom system message if provided in options, otherwise use default
            $systemMessageContent = $options['system_message'] ?? config('ai.defaults.system_message');

            // Ensure accurate model disclosure if the user asks about model/version
            $modelName = $this->getModel();
            $providerName = $this->getProvider();
            $systemMessageContent .= "\n\nModel disclosure policy: You are running on {$providerName} model {$modelName}. If a user asks which model you are, reply exactly '{$modelName}'. Do not claim older models (e.g., GPT-3 or GPT-3.5).";
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
     * Enhanced streaming chat completion with course-specific context
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

        // Create enhanced syllabus-based system message for creative study assistance
        if (!empty($namespaces)) {
            // Enhanced syllabus-based system message that encourages synthesis and creativity
            $systemMessageContent = "You are OposChat, a professional study assistant specialized in preparing students for oral and written exams.
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
            $lastUserMessage = end($messages);
            $lastUserText = ($lastUserMessage && ($lastUserMessage['role'] ?? '') === 'user') ? strtolower($lastUserMessage['content'] ?? '') : '';
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
                $systemMessageContent .= "\n\nWHEN REQUESTING DIAGRAM-LIKE OUTPUT: If the user asks for any of these: outline, sketch, concept map, flowchart, chart, graph/graphic, or the Spanish terms (esquema, croquis, mapa conceptual, mapa mental, diagrama, gráfica), you MUST produce the output as a Mermaid diagram enclosed in a fenced code block with the language 'mermaid' (```mermaid ... ```). After the code block, include a brief explanation in plain paragraphs describing why each connection exists and how parts relate.";
            }

            // Add context to system message if available and relevant
            if (!empty($contextData['context']) && $isRelevant) {
                $contextText = implode(' ', $contextData['context']);
                $systemMessageContent .= "\n\nRELEVANT SYLLABUS CONTENT:\n" . $contextText;
            } elseif (!empty($namespaces) && (!$isRelevant || empty($contextData['context']))) {
                // Explicit instruction to state that the question is not in the syllabus
                $systemMessageContent .= "\n\nCRITICAL INSTRUCTION - READ THIS CAREFULLY:

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
        } else {
            // Use custom system message if provided in options, otherwise use default
            $systemMessageContent = $options['system_message'] ?? config('ai.defaults.system_message');

            // Ensure accurate model disclosure if the user asks about model/version
            $modelName = $this->getModel();
            $providerName = $this->getProvider();
            $systemMessageContent .= "\n\nModel disclosure policy: You are running on {$providerName} model {$modelName}. If a user asks which model you are, reply exactly '{$modelName}'. Do not claim older models (e.g., GPT-3 or GPT-3.5).";

            // Detect diagram-like requests even without namespaces and instruct Mermaid + explanation
            $lastUserMessage = end($messages);
            $lastUserText = ($lastUserMessage && ($lastUserMessage['role'] ?? '') === 'user') ? strtolower($lastUserMessage['content'] ?? '') : '';
            $diagramSynonyms = [
                'diagram', 'flowchart', 'flow chart', 'chart', 'graph', 'graphic', 'concept map',
                'outline', 'sketch',
                'diagrama', 'diagrama de flujo', 'esquema', 'mapa conceptual', 'mapa mental', 'croquis', 'gráfica', 'grafica', 'gráfico', 'grafico'
            ];
            foreach ($diagramSynonyms as $k) {
                if ($lastUserText !== '' && strpos($lastUserText, $k) !== false) {
                    $systemMessageContent .= "\n\nWHEN REQUESTING DIAGRAM-LIKE OUTPUT: If the user asks for any of these: outline, sketch, concept map, flowchart, chart, graph/graphic, or the Spanish terms (esquema, croquis, mapa conceptual, mapa mental, diagrama, gráfica), you MUST produce the output as a Mermaid diagram enclosed in a fenced code block with the language 'mermaid' (```mermaid ... ```). After the code block, include a brief explanation in plain paragraphs describing why each connection exists and how parts relate.";
                    break;
                }
            }
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