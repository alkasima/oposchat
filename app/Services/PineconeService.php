<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PineconeService
{
    private string $apiKey;
    private string $environment;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.pinecone.api_key');
        $this->environment = config('services.pinecone.environment');

        try {
            $settings = app(\App\Services\SettingsService::class);
            $dbKey = $settings->get('PINECONE_API_KEY');
            $dbEnv = $settings->get('PINECONE_ENVIRONMENT');
            if (!empty($dbKey)) {
                $this->apiKey = $dbKey;
            }
            if (!empty($dbEnv)) {
                $this->environment = $dbEnv;
            }
        } catch (\Throwable $e) {
            // Fallback to config
        }
        $this->baseUrl = "https://{$this->environment}.pinecone.io";
    }

    /**
     * Create a new index
     */
    public function createIndex(string $indexName, int $dimensions = 1536): array
    {
        $payload = [
            'name' => $indexName,
            'dimension' => $dimensions,
            'metric' => 'cosine',
            'pods' => 1,
            'replicas' => 1,
            'pod_type' => 'p1.x1'
        ];

        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/databases", $payload);

        if (!$response->successful()) {
            throw new Exception('Failed to create Pinecone index: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * List all indexes
     */
    public function listIndexes(): array
    {
        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
        ])->get("{$this->baseUrl}/databases");

        if (!$response->successful()) {
            throw new Exception('Failed to list Pinecone indexes: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Describe an index
     */
    public function describeIndex(string $indexName): array
    {
        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
        ])->get("{$this->baseUrl}/databases/{$indexName}");

        if (!$response->successful()) {
            throw new Exception('Failed to describe Pinecone index: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Upsert vectors to an index
     */
    public function upsertVectors(string $indexName, array $vectors): array
    {
        $payload = [
            'vectors' => $vectors
        ];

        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/databases/{$indexName}/vectors/upsert", $payload);

        if (!$response->successful()) {
            throw new Exception('Failed to upsert vectors: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Query vectors from an index
     */
    public function queryVectors(string $indexName, array $queryVector, array $options = []): array
    {
        $payload = array_merge([
            'vector' => $queryVector,
            'topK' => $options['top_k'] ?? 5,
            'includeMetadata' => true,
            'includeValues' => false,
        ], $options);

        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/databases/{$indexName}/vectors/query", $payload);

        if (!$response->successful()) {
            throw new Exception('Failed to query vectors: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Delete vectors from an index
     */
    public function deleteVectors(string $indexName, array $ids = [], array $filter = []): array
    {
        $payload = [];
        
        if (!empty($ids)) {
            $payload['ids'] = $ids;
        }
        
        if (!empty($filter)) {
            $payload['filter'] = $filter;
        }

        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/databases/{$indexName}/vectors/delete", $payload);

        if (!$response->successful()) {
            throw new Exception('Failed to delete vectors: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get index stats
     */
    public function getIndexStats(string $indexName): array
    {
        $response = Http::withHeaders([
            'Api-Key' => $this->apiKey,
        ])->get("{$this->baseUrl}/databases/{$indexName}/stats");

        if (!$response->successful()) {
            throw new Exception('Failed to get index stats: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Check if index exists
     */
    public function indexExists(string $indexName): bool
    {
        try {
            $this->describeIndex($indexName);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Create index if it doesn't exist
     */
    public function ensureIndexExists(string $indexName, int $dimensions = 1536): void
    {
        if (!$this->indexExists($indexName)) {
            Log::info("Creating Pinecone index: {$indexName}");
            $this->createIndex($indexName, $dimensions);
            
            // Wait for index to be ready
            $this->waitForIndexReady($indexName);
        }
    }

    /**
     * Wait for index to be ready
     */
    private function waitForIndexReady(string $indexName, int $maxWaitTime = 300): void
    {
        $startTime = time();
        
        while (time() - $startTime < $maxWaitTime) {
            try {
                $indexInfo = $this->describeIndex($indexName);
                if ($indexInfo['status']['state'] === 'Ready') {
                    Log::info("Pinecone index {$indexName} is ready");
                    return;
                }
            } catch (Exception $e) {
                // Index might not be ready yet
            }
            
            sleep(5);
        }
        
        throw new Exception("Index {$indexName} did not become ready within {$maxWaitTime} seconds");
    }
}
