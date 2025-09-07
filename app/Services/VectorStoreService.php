<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

class VectorStoreService
{
    private ?PineconeService $pineconeService;
    private LocalVectorStore $localVectorStore;
    private bool $usePinecone;

    public function __construct(
        ?PineconeService $pineconeService = null,
        LocalVectorStore $localVectorStore = null
    ) {
        $this->pineconeService = $pineconeService;
        $this->localVectorStore = $localVectorStore ?? new LocalVectorStore();
        
        // Determine which service to use
        $this->usePinecone = $this->shouldUsePinecone();
        
        Log::info('VectorStoreService initialized', [
            'use_pinecone' => $this->usePinecone,
            'pinecone_available' => $this->pineconeService !== null,
            'local_available' => $this->localVectorStore->isAvailable()
        ]);
    }

    /**
     * Determine if Pinecone should be used
     */
    private function shouldUsePinecone(): bool
    {
        // Check if Pinecone is configured
        if (!$this->pineconeService) {
            return false;
        }

        // Check if API key is set
        $apiKey = config('services.pinecone.api_key');
        $environment = config('services.pinecone.environment');
        
        if (empty($apiKey) || empty($environment)) {
            Log::warning('Pinecone not configured, falling back to local storage');
            return false;
        }

        // Test Pinecone connection
        try {
            $this->pineconeService->listIndexes();
            Log::info('Pinecone connection successful');
            return true;
        } catch (Exception $e) {
            Log::warning('Pinecone connection failed, falling back to local storage', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Upsert vectors
     */
    public function upsertVectors(array $vectors, string $indexName = 'default'): array
    {
        if ($this->usePinecone) {
            try {
                return $this->pineconeService->upsertVectors($indexName, $vectors);
            } catch (Exception $e) {
                Log::error('Pinecone upsert failed, falling back to local storage', [
                    'error' => $e->getMessage()
                ]);
                $this->usePinecone = false;
            }
        }

        return $this->localVectorStore->upsertVectors($vectors);
    }

    /**
     * Query vectors
     */
    public function queryVectors(array $queryVector, array $options = [], string $indexName = 'default'): array
    {
        if ($this->usePinecone) {
            try {
                return $this->pineconeService->queryVectors($indexName, $queryVector, $options);
            } catch (Exception $e) {
                Log::error('Pinecone query failed, falling back to local storage', [
                    'error' => $e->getMessage()
                ]);
                $this->usePinecone = false;
            }
        }

        return $this->localVectorStore->queryVectors($queryVector, $options);
    }

    /**
     * Delete vectors
     */
    public function deleteVectors(array $ids = [], array $filter = [], string $indexName = 'default'): array
    {
        if ($this->usePinecone) {
            try {
                return $this->pineconeService->deleteVectors($indexName, $ids, $filter);
            } catch (Exception $e) {
                Log::error('Pinecone delete failed, falling back to local storage', [
                    'error' => $e->getMessage()
                ]);
                $this->usePinecone = false;
            }
        }

        return $this->localVectorStore->deleteVectors($ids, $filter);
    }

    /**
     * Get storage statistics
     */
    public function getStats(string $indexName = 'default'): array
    {
        if ($this->usePinecone) {
            try {
                $pineconeStats = $this->pineconeService->getIndexStats($indexName);
                return array_merge($pineconeStats, [
                    'storage_type' => 'pinecone',
                    'index_name' => $indexName
                ]);
            } catch (Exception $e) {
                Log::error('Failed to get Pinecone stats', ['error' => $e->getMessage()]);
            }
        }

        $localStats = $this->localVectorStore->getStats();
        return array_merge($localStats, [
            'storage_type' => 'local'
        ]);
    }

    /**
     * Check if using Pinecone
     */
    public function isUsingPinecone(): bool
    {
        return $this->usePinecone;
    }

    /**
     * Get storage type
     */
    public function getStorageType(): string
    {
        return $this->usePinecone ? 'pinecone' : 'local';
    }

    /**
     * Force fallback to local storage
     */
    public function forceLocalStorage(): void
    {
        $this->usePinecone = false;
        Log::info('Forced fallback to local storage');
    }

    /**
     * Test connection to current storage
     */
    public function testConnection(): array
    {
        if ($this->usePinecone) {
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
        } else {
            $available = $this->localVectorStore->isAvailable();
            return [
                'success' => $available,
                'type' => 'local',
                'message' => $available ? 'Local storage available' : 'Local storage not available'
            ];
        }
    }
}
