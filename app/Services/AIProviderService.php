<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\DocumentProcessingService;
use Exception;

class AIProviderService
{
    private string $provider;
    private array $config;
    private ?DocumentProcessingService $documentProcessor;

    public function __construct(?DocumentProcessingService $documentProcessor = null)
    {
        $this->provider = config('ai.provider', 'openai');
        $this->config = config('ai.providers.' . $this->provider);
        $this->documentProcessor = $documentProcessor;
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
            ])->timeout(120)->withOptions([
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
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', $payload);

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
        ])->timeout(60)->post($url . '?key=' . $this->config['api_key'], $payload);

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
     * Get relevant context based on course namespaces using vector search
     */
    public function getRelevantContext(string $query, array $namespaces = []): array
    {
        if (empty($namespaces) || !$this->documentProcessor) {
            return [];
        }

        try {
            // Search for relevant content using vector database
            $searchResults = $this->documentProcessor->searchRelevantContent($query, $namespaces, 3);
            
            if (!$searchResults['success'] || empty($searchResults['results'])) {
                Log::warning('No relevant context found', [
                    'query' => $query,
                    'namespaces' => $namespaces
                ]);
                return [];
            }

            // Extract and format relevant content
            $context = [];
            foreach ($searchResults['results'] as $result) {
                if (isset($result['metadata']['content'])) {
                    $context[] = $result['metadata']['content'];
                }
            }

            Log::info('Retrieved relevant context', [
                'query' => $query,
                'namespaces' => $namespaces,
                'context_chunks' => count($context)
            ]);

            return $context;

        } catch (Exception $e) {
            Log::error('Failed to retrieve relevant context', [
                'query' => $query,
                'namespaces' => $namespaces,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to simple context
            $context = [];
            foreach ($namespaces as $namespace) {
                $context[] = "This response is based on {$namespace} course materials.";
            }
            return $context;
        }
    }

    /**
     * Enhanced chat completion with course-specific context
     */
    public function chatCompletionWithContext(array $messages, array $namespaces = [], array $options = []): array
    {
        // Get relevant context if namespaces are provided
        $context = [];
        if (!empty($namespaces)) {
            $lastUserMessage = end($messages);
            if ($lastUserMessage && $lastUserMessage['role'] === 'user') {
                $context = $this->getRelevantContext($lastUserMessage['content'], $namespaces);
            }
        }

        // Add context to system message if available
        if (!empty($context)) {
            $contextText = implode(' ', $context);
            $systemMessage = [
                'role' => 'system',
                'content' => config('ai.defaults.system_message') . "\n\nRelevant context: " . $contextText
            ];
            
            // Insert system message at the beginning
            array_unshift($messages, $systemMessage);
        }

        return $this->chatCompletion($messages, $options);
    }

    /**
     * Enhanced streaming chat completion with course-specific context
     */
    public function streamChatCompletionWithContext(array $messages, callable $callback, array $namespaces = [], array $options = []): array
    {
        // Get relevant context if namespaces are provided
        $context = [];
        if (!empty($namespaces)) {
            $lastUserMessage = end($messages);
            if ($lastUserMessage && $lastUserMessage['role'] === 'user') {
                $context = $this->getRelevantContext($lastUserMessage['content'], $namespaces);
            }
        }

        // Add context to system message if available
        if (!empty($context)) {
            $contextText = implode(' ', $context);
            $systemMessage = [
                'role' => 'system',
                'content' => config('ai.defaults.system_message') . "\n\nRelevant context: " . $contextText
            ];
            
            // Insert system message at the beginning
            array_unshift($messages, $systemMessage);
        }

        return $this->streamChatCompletion($messages, $callback, $options);
    }
}