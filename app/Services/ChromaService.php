<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ChromaService
{
    private string $host;
    private string $apiKey;
    private string $tenant;
    private string $database;
    private int $timeout;
    private int $retryAttempts;
    private string $baseUrl;

    public function __construct()
    {
        // For Chroma Cloud via Python bridge
        $this->host = config('services.chroma.host', 'localhost:8001');
        $this->apiKey = config('services.chroma.api_key');  // Not used for bridge, but kept for compatibility
        $this->tenant = config('services.chroma.tenant');
        $this->database = config('services.chroma.database');
        $this->timeout = config('services.chroma.timeout', 60);
        $this->retryAttempts = config('services.chroma.retry_attempts', 3);

        // Try to get from database settings first
        try {
            $settings = app(\App\Services\SettingsService::class);
            $dbHost = $settings->get('CHROMA_HOST');
            
            if (!empty($dbHost)) $this->host = $dbHost;
        } catch (\Throwable $e) {
            // Fallback to config
        }

        // Bridge service URL (no https, no port in URL since it's in host)
        $this->baseUrl = "http://{$this->host}";
    }

    /**
     * Get or create a collection
     */
    public function getOrCreateCollection(string $collectionName, int $dimensions = 1536): array
    {
        try {
            // Try to get existing collection
            return $this->getCollection($collectionName);
        } catch (Exception $e) {
            // Collection doesn't exist, create it
            return $this->createCollection($collectionName, $dimensions);
        }
    }

    /**
     * Create a new collection
     */
    public function createCollection(string $collectionName, int $dimensions = 1536): array
    {
        $payload = [
            'name' => $collectionName,
            'metadata' => [
                'description' => 'OposChat vector storage',
                'dimension' => $dimensions
            ]
        ];

        $response = $this->makeRequest('POST', "/collections/{$collectionName}", $payload);

        Log::info('Chroma collection created', [
            'collection' => $collectionName,
            'dimensions' => $dimensions
        ]);

        return $response;
    }

    /**
     * Get collection details
     */
    public function getCollection(string $collectionName): array
    {
        return $this->makeRequest('GET', "/collections/{$collectionName}");
    }

    /**
     * Add documents (vectors) to collection
     */
    public function addDocuments(string $collectionName, array $vectors): array
    {
        if (empty($vectors)) {
            return ['success' => true, 'count' => 0];
        }

        // Chroma expects: ids, embeddings, metadatas, documents
        $ids = [];
        $embeddings = [];
        $metadatas = [];
        $documents = [];

        foreach ($vectors as $vector) {
            $ids[] = $vector['id'];
            $embeddings[] = $vector['values'];
            
            // Extract content for document field
            $metadata = $vector['metadata'] ?? [];
            $content = $metadata['content'] ?? '';
            unset($metadata['content']); // Don't duplicate in metadata
            
            $metadatas[] = $metadata;
            $documents[] = $content;
        }

        $payload = [
            'ids' => $ids,
            'embeddings' => $embeddings,
            'metadatas' => $metadatas,
            'documents' => $documents
        ];

        $response = $this->makeRequest(
            'POST', 
            "/collections/{$collectionName}/add",
            $payload,
            $this->timeout // Use longer timeout for uploads
        );

        Log::info('Chroma documents added', [
            'collection' => $collectionName,
            'count' => count($vectors)
        ]);

        return $response;
    }

    /**
     * Query collection for similar vectors
     */
    public function query(string $collectionName, array $queryVector, array $options = []): array
    {
        $topK = $options['top_k'] ?? 5;
        $filter = $options['filter'] ?? null;

        $payload = [
            'query_embeddings' => [$queryVector],
            'n_results' => $topK,
            'include' => ['metadatas', 'documents', 'distances']
        ];

        // Add metadata filter if provided
        if ($filter) {
            $payload['where'] = $this->convertFilter($filter);
        }

        $response = $this->makeRequest(
            'POST',
            "/collections/{$collectionName}/query",
            $payload,
            30 // Shorter timeout for queries
        );

        // Convert Chroma response format to match Pinecone format
        return $this->convertQueryResponse($response);
    }

    /**
     * Delete documents from collection
     */
    public function deleteDocuments(string $collectionName, array $ids = [], array $filter = []): array
    {
        $payload = [];

        if (!empty($ids)) {
            $payload['ids'] = $ids;
        }

        if (!empty($filter)) {
            $payload['where'] = $this->convertFilter($filter);
        }

        if (empty($payload)) {
            throw new Exception('Must provide either ids or filter for deletion');
        }

        $response = $this->makeRequest(
            'POST',
            "/collections/{$collectionName}/delete",
            $payload
        );

        Log::info('Chroma documents deleted', [
            'collection' => $collectionName,
            'ids_count' => count($ids),
            'has_filter' => !empty($filter)
        ]);

        return $response;
    }

    /**
     * Get collection statistics
     */
    public function getCollectionStats(string $collectionName): array
    {
        $collection = $this->getCollection($collectionName);
        
        return [
            'name' => $collection['name'] ?? $collectionName,
            'count' => $collection['count'] ?? 0,
            'metadata' => $collection['metadata'] ?? [],
            'storage_type' => 'chroma'
        ];
    }

    /**
     * Test connection to Chroma Cloud
     */
    public function testConnection(): array
    {
        try {
            // Try to test connection
            $this->makeRequest('GET', '/health');
            
            return [
                'success' => true,
                'message' => 'Chroma Cloud connection successful',
                'host' => $this->host
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Chroma Cloud connection failed: ' . $e->getMessage(),
                'host' => $this->host
            ];
        }
    }

    /**
     * Make HTTP request with retry logic
     */
    private function makeRequest(string $method, string $endpoint, array $payload = [], int $timeout = null): array
    {
        $timeout = $timeout ?? $this->timeout;
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retryAttempts) {
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->timeout($timeout)
                ->retry(3, 100) // Internal retry with 100ms delay
                ->{strtolower($method)}($this->baseUrl . $endpoint, $payload);

                if (!$response->successful()) {
                    $statusCode = $response->status();
                    $body = $response->body();

                    // Handle rate limiting
                    if ($statusCode === 429) {
                        $retryAfter = $response->header('Retry-After') ?? pow(2, $attempt);
                        Log::warning('Chroma rate limit hit, retrying', [
                            'attempt' => $attempt + 1,
                            'retry_after' => $retryAfter
                        ]);
                        sleep((int)$retryAfter);
                        $attempt++;
                        continue;
                    }

                    throw new Exception("Chroma Bridge API error ({$statusCode}): {$body}");
                }

                return $response->json() ?? [];

            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt < $this->retryAttempts) {
                    $delay = pow(2, $attempt - 1); // Exponential backoff: 1s, 2s, 4s
                    Log::warning('Chroma request failed, retrying', [
                        'attempt' => $attempt,
                        'delay' => $delay,
                        'error' => $e->getMessage()
                    ]);
                    sleep($delay);
                }
            }
        }

        // All retries failed
        Log::error('Chroma request failed after all retries', [
            'method' => $method,
            'endpoint' => $endpoint,
            'attempts' => $this->retryAttempts,
            'error' => $lastException->getMessage()
        ]);

        throw $lastException;
    }

    /**
     * Convert Pinecone-style filter to Chroma where clause
     */
    private function convertFilter(array $filter): array
    {
        $where = [];

        foreach ($filter as $key => $value) {
            if (is_array($value)) {
                // Handle operators like $in, $eq
                if (isset($value['$in'])) {
                    $where[$key] = ['$in' => $value['$in']];
                } elseif (isset($value['$eq'])) {
                    $where[$key] = $value['$eq'];
                } else {
                    $where[$key] = $value;
                }
            } else {
                $where[$key] = $value;
            }
        }

        return $where;
    }

    /**
     * Convert Chroma query response to Pinecone-compatible format
     */
    private function convertQueryResponse(array $response): array
    {
        if (!isset($response['ids']) || !isset($response['ids'][0])) {
            return ['matches' => []];
        }

        $matches = [];
        $ids = $response['ids'][0] ?? [];
        $distances = $response['distances'][0] ?? [];
        $metadatas = $response['metadatas'][0] ?? [];
        $documents = $response['documents'][0] ?? [];

        for ($i = 0; $i < count($ids); $i++) {
            // Convert distance to similarity score (Chroma uses L2 distance)
            // Lower distance = higher similarity
            $distance = $distances[$i] ?? 1.0;
            $score = 1 / (1 + $distance); // Convert to 0-1 similarity score

            $metadata = $metadatas[$i] ?? [];
            $metadata['content'] = $documents[$i] ?? '';

            $matches[] = [
                'id' => $ids[$i],
                'score' => $score,
                'metadata' => $metadata
            ];
        }

        return ['matches' => $matches];
    }
}
