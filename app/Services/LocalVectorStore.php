<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class LocalVectorStore
{
    private string $storagePath;
    private array $vectors = [];
    private array $metadata = [];

    public function __construct()
    {
        $this->storagePath = storage_path('app/vectors');
        $this->ensureStorageDirectory();
        $this->loadVectors();
    }

    /**
     * Ensure storage directory exists
     */
    private function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Load vectors from storage
     */
    private function loadVectors(): void
    {
        $vectorsFile = $this->storagePath . '/vectors.json';
        $metadataFile = $this->storagePath . '/metadata.json';

        if (file_exists($vectorsFile)) {
            $this->vectors = json_decode(file_get_contents($vectorsFile), true) ?? [];
        }

        if (file_exists($metadataFile)) {
            $this->metadata = json_decode(file_get_contents($metadataFile), true) ?? [];
        }
    }

    /**
     * Save vectors to storage
     */
    private function saveVectors(): void
    {
        $vectorsFile = $this->storagePath . '/vectors.json';
        $metadataFile = $this->storagePath . '/metadata.json';

        file_put_contents($vectorsFile, json_encode($this->vectors, JSON_PRETTY_PRINT));
        file_put_contents($metadataFile, json_encode($this->metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Upsert vectors (create or update)
     */
    public function upsertVectors(array $vectors): array
    {
        foreach ($vectors as $vector) {
            $id = $vector['id'];
            $this->vectors[$id] = $vector['values'];
            $this->metadata[$id] = $vector['metadata'];
        }

        $this->saveVectors();

        return [
            'success' => true,
            'upsertedCount' => count($vectors)
        ];
    }

    /**
     * Query vectors using cosine similarity
     */
    public function queryVectors(array $queryVector, array $options = []): array
    {
        $topK = $options['top_k'] ?? 5;
        $filter = $options['filter'] ?? [];

        $results = [];

        foreach ($this->vectors as $id => $vector) {
            // Apply namespace filter if specified
            if (!empty($filter['course_namespace'])) {
                $courseNamespace = $filter['course_namespace'];
                if (is_array($courseNamespace) && isset($courseNamespace['$in'])) {
                    // Handle $in operator
                    if (!in_array($this->metadata[$id]['course_namespace'] ?? '', $courseNamespace['$in'])) {
                        continue;
                    }
                } elseif (is_string($courseNamespace)) {
                    // Handle direct string match
                    if (($this->metadata[$id]['course_namespace'] ?? '') !== $courseNamespace) {
                        continue;
                    }
                }
            }

            // Calculate cosine similarity
            $similarity = $this->cosineSimilarity($queryVector, $vector);

            $results[] = [
                'id' => $id,
                'score' => $similarity,
                'metadata' => $this->metadata[$id] ?? []
            ];
        }

        // Sort by similarity score (descending)
        usort($results, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Return top K results
        return array_slice($results, 0, $topK);
    }

    /**
     * Delete vectors by filter
     */
    public function deleteVectors(array $ids = [], array $filter = []): array
    {
        $deletedCount = 0;

        if (!empty($ids)) {
            // Delete specific IDs
            foreach ($ids as $id) {
                if (isset($this->vectors[$id])) {
                    unset($this->vectors[$id]);
                    unset($this->metadata[$id]);
                    $deletedCount++;
                }
            }
        } elseif (!empty($filter)) {
            // Delete by filter
            $courseNamespace = $filter['course_namespace'] ?? null;
            
            if ($courseNamespace && isset($courseNamespace['$eq'])) {
                $targetNamespace = $courseNamespace['$eq'];
                
                foreach ($this->metadata as $id => $meta) {
                    if (($meta['course_namespace'] ?? '') === $targetNamespace) {
                        unset($this->vectors[$id]);
                        unset($this->metadata[$id]);
                        $deletedCount++;
                    }
                }
            }
        }

        if ($deletedCount > 0) {
            $this->saveVectors();
        }

        return [
            'deletedCount' => $deletedCount
        ];
    }

    /**
     * Get storage statistics
     */
    public function getStats(): array
    {
        return [
            'totalVectors' => count($this->vectors),
            'storagePath' => $this->storagePath,
            'storageSize' => $this->getStorageSize()
        ];
    }

    /**
     * Calculate storage size
     */
    private function getStorageSize(): string
    {
        $size = 0;
        $vectorsFile = $this->storagePath . '/vectors.json';
        $metadataFile = $this->storagePath . '/metadata.json';

        if (file_exists($vectorsFile)) {
            $size += filesize($vectorsFile);
        }
        if (file_exists($metadataFile)) {
            $size += filesize($metadataFile);
        }

        return $this->formatBytes($size);
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    private function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        if (count($vectorA) !== count($vectorB)) {
            return 0.0;
        }

        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        for ($i = 0; $i < count($vectorA); $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $normA += $vectorA[$i] * $vectorA[$i];
            $normB += $vectorB[$i] * $vectorB[$i];
        }

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Clear all vectors (for testing)
     */
    public function clearAll(): void
    {
        $this->vectors = [];
        $this->metadata = [];
        $this->saveVectors();
    }

    /**
     * Check if vector store is available
     */
    public function isAvailable(): bool
    {
        return is_writable($this->storagePath);
    }
}
