<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class VectorStoreService
{
    private ?PineconeService $pineconeService;
    private ?ChromaService $chromaService;
    private LocalVectorStore $localVectorStore;
    private string $storageType;

    public function __construct(
        ?PineconeService $pineconeService = null,
        ?ChromaService $chromaService = null,
        LocalVectorStore $localVectorStore = null
    ) {
        $this->pineconeService = $pineconeService;
        $this->chromaService = $chromaService;
        $this->localVectorStore = $localVectorStore ?? new LocalVectorStore();
        
        // Determine which service to use
        $this->storageType = $this->determineStorageType();
        
        Log::info('VectorStoreService initialized', [
            'storage_type' => $this->storageType,
            'chroma_available' => $this->chromaService !== null,
            'pinecone_available' => $this->pineconeService !== null,
            'local_available' => $this->localVectorStore->isAvailable()
        ]);
    }

    /**
     * Determine which storage type to use (with caching)
     */
    private function determineStorageType(): string
    {
        // Check cache first (10 minute TTL)
        $cacheKey = 'vector_store_type';
        
        $cachedType = Cache::get($cacheKey);
        if ($cachedType) {
            Log::info('Using cached storage type', ['storage_type' => $cachedType]);
            return $cachedType;
        }

        // Priority: Chroma > Pinecone > Local
        $storageType = 'local'; // Default fallback
        
        // Check if Chroma Cloud should be used
        if (env('USE_CHROMA_CLOUD', true) && $this->chromaService) {
            if ($this->testChromaConnection()) {
                $storageType = 'chroma';
            }
        }

        // Fall back to Pinecone if configured and Chroma not available
        if ($storageType === 'local' && $this->pineconeService && $this->testPineconeConnection()) {
            $storageType = 'pinecone';
        }

        // Cache the result for 10 minutes
        Cache::put($cacheKey, $storageType, now()->addMinutes(10));
        
        if ($storageType === 'local') {
            Log::warning('Using local storage as fallback');
        } else {
            Log::info('Determined storage type', ['storage_type' => $storageType]);
        }
        
        return $storageType;
    }

    /**
     * Test Chroma connection
     */
    private function testChromaConnection(): bool
    {
        if (!$this->chromaService) {
            return false;
        }

        $apiKey = config('services.chroma.api_key');
        $host = config('services.chroma.host');
        
        if (empty($apiKey) || empty($host)) {
            Log::warning('Chroma not configured, falling back');
            return false;
        }

        try {
            $result = $this->chromaService->testConnection();
            if ($result['success']) {
                Log::info('Chroma Cloud connection successful');
                return true;
            }
            return false;
        } catch (Exception $e) {
            Log::warning('Chroma connection failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Test Pinecone connection
     */
    private function testPineconeConnection(): bool
    {
        if (!$this->pineconeService) {
            return false;
        }

        try {
            $this->pineconeService->listIndexes();
            Log::info('Pinecone connection successful');
            return true;
        } catch (Exception $e) {
            Log::warning('Pinecone connection failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Upsert vectors
     */
    public function upsertVectors(array $vectors, string $indexName = 'default'): array
    {
        if (empty($vectors)) {
            return ['success' => true, 'upsertedCount' => 0];
        }

        try {
            switch ($this->storageType) {
                case 'chroma':
                    $collectionName = config('services.chroma.collection_name', $indexName);
                    
                    // Ensure collection exists
                    $this->chromaService->getOrCreateCollection($collectionName);
                    
                    // Add documents
                    $this->chromaService->addDocuments($collectionName, $vectors);
                    
                    return [
                        'success' => true,
                        'upsertedCount' => count($vectors)
                    ];

                case 'pinecone':
                    return $this->pineconeService->upsertVectors($indexName, $vectors);

                case 'local':
                default:
                    return $this->localVectorStore->upsertVectors($vectors);
            }
        } catch (Exception $e) {
            Log::error('Vector upsert failed', [
                'storage_type' => $this->storageType,
                'error' => $e->getMessage(),
                'vector_count' => count($vectors)
            ]);

            // Try fallback to local storage
            if ($this->storageType !== 'local') {
                Log::info('Falling back to local storage for upsert');
                $this->storageType = 'local';
                return $this->localVectorStore->upsertVectors($vectors);
            }

            throw $e;
        }
    }

    /**
     * Query vectors
     */
    public function queryVectors(array $queryVector, array $options = [], string $indexName = 'default'): array
    {
        try {
            switch ($this->storageType) {
                case 'chroma':
                    $collectionName = config('services.chroma.collection_name', $indexName);
                    $result = $this->chromaService->query($collectionName, $queryVector, $options);
                    
                    // Return matches array (compatible with Pinecone format)
                    return $result['matches'] ?? [];

                case 'pinecone':
                    $result = $this->pineconeService->queryVectors($indexName, $queryVector, $options);
                    return $result['matches'] ?? [];

                case 'local':
                default:
                    return $this->localVectorStore->queryVectors($queryVector, $options);
            }
        } catch (Exception $e) {
            Log::error('Vector query failed', [
                'storage_type' => $this->storageType,
                'error' => $e->getMessage()
            ]);

            // Try fallback to local storage
            if ($this->storageType !== 'local') {
                Log::info('Falling back to local storage for query');
                $this->storageType = 'local';
                return $this->localVectorStore->queryVectors($queryVector, $options);
            }

            throw $e;
        }
    }

    /**
     * Delete vectors
     */
    public function deleteVectors(array $ids = [], array $filter = [], string $indexName = 'default'): array
    {
        try {
            switch ($this->storageType) {
                case 'chroma':
                    $collectionName = config('services.chroma.collection_name', $indexName);
                    $this->chromaService->deleteDocuments($collectionName, $ids, $filter);
                    return ['success' => true];

                case 'pinecone':
                    return $this->pineconeService->deleteVectors($indexName, $ids, $filter);

                case 'local':
                default:
                    return $this->localVectorStore->deleteVectors($ids, $filter);
            }
        } catch (Exception $e) {
            Log::error('Vector delete failed', [
                'storage_type' => $this->storageType,
                'error' => $e->getMessage()
            ]);

            // Try fallback to local storage
            if ($this->storageType !== 'local') {
                Log::info('Falling back to local storage for delete');
                $this->storageType = 'local';
                return $this->localVectorStore->deleteVectors($ids, $filter);
            }

            throw $e;
        }
    }

    /**
     * Get storage statistics
     */
    public function getStats(string $indexName = 'default'): array
    {
        try {
            switch ($this->storageType) {
                case 'chroma':
                    $collectionName = config('services.chroma.collection_name', $indexName);
                    return $this->chromaService->getCollectionStats($collectionName);

                case 'pinecone':
                    $stats = $this->pineconeService->getIndexStats($indexName);
                    return array_merge($stats, ['storage_type' => 'pinecone']);

                case 'local':
                default:
                    $stats = $this->localVectorStore->getStats();
                    return array_merge($stats, ['storage_type' => 'local']);
            }
        } catch (Exception $e) {
            Log::error('Failed to get stats', ['error' => $e->getMessage()]);
            return ['storage_type' => $this->storageType, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get current storage type
     */
    public function getStorageType(): string
    {
        return $this->storageType;
    }

    /**
     * Check if using Chroma
     */
    public function isUsingChroma(): bool
    {
        return $this->storageType === 'chroma';
    }

    /**
     * Check if using Pinecone
     */
    public function isUsingPinecone(): bool
    {
        return $this->storageType === 'pinecone';
    }

    /**
     * Force storage type
     */
    public function forceStorageType(string $type): void
    {
        if (!in_array($type, ['chroma', 'pinecone', 'local'])) {
            throw new Exception("Invalid storage type: {$type}");
        }

        $this->storageType = $type;
        Log::info("Forced storage type to: {$type}");
    }

    /**
     * Search for relevant content using embedding vector
     */
    public function searchWithEmbedding(array $embedding, array $namespaces = [], int $topK = 5): array
    {
        try {
            // Prepare query options
            $options = [
                'top_k' => $topK,
                'includeMetadata' => true
            ];

            // Add namespace filter if provided
            if (!empty($namespaces)) {
                $options['filter'] = [
                    'course_namespace' => ['$in' => $namespaces]
                ];
            }

            // Query vectors
            $results = $this->queryVectors($embedding, $options);
            
            return [
                'success' => true,
                'results' => is_array($results) ? $results : [],
                'namespaces' => $namespaces
            ];
        } catch (Exception $e) {
            Log::error('Vector search failed', [
                'namespaces' => $namespaces,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => []
            ];
        }
    }

    /**
     * Test connection to current storage
     */
    public function testConnection(): array
    {
        switch ($this->storageType) {
            case 'chroma':
                return $this->chromaService->testConnection();

            case 'pinecone':
                try {
                    $this->pineconeService->listIndexes();
                    return [
                        'success' => true,
                        'type' => 'pinecone',
                        'message' => 'Pinecone connection successful'
                    ];
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'type' => 'pinecone',
                        'message' => 'Pinecone connection failed: ' . $e->getMessage()
                    ];
                }

            case 'local':
            default:
                $available = $this->localVectorStore->isAvailable();
                return [
                    'success' => $available,
                    'type' => 'local',
                    'message' => $available ? 'Local storage available' : 'Local storage not available'
                ];
        }
    }

    /**
     * Refresh connection status and clear cache
     * Call this to force re-detection of storage type
     */
    public function refreshConnectionStatus(): string
    {
        Cache::forget('vector_store_type');
        $this->storageType = $this->determineStorageType();
        
        Log::info('Connection status refreshed', ['storage_type' => $this->storageType]);
        
        return $this->storageType;
    }
}
