<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class DocumentProcessingService
{
    private AIProviderService $aiProvider;
    private VectorStoreService $vectorStore;

    public function __construct(
        AIProviderService $aiProvider,
        VectorStoreService $vectorStore
    ) {
        $this->aiProvider = $aiProvider;
        $this->vectorStore = $vectorStore;
    }

    /**
     * Process and store a document for a specific course
     */
    public function processDocument(string $content, string $courseNamespace, array $metadata = []): array
    {
        try {
            // Chunk the document
            $chunks = $this->chunkDocument($content);
            
            $vectors = [];
            $processedChunks = 0;

            foreach ($chunks as $index => $chunk) {
                try {
                    // Generate embedding for the chunk
                    $embedding = $this->generateEmbedding($chunk);
                    
                    // Create vector with metadata
                    $vectorId = $this->generateVectorId($courseNamespace, $index);
                    
                    $vector = [
                        'id' => $vectorId,
                        'values' => $embedding,
                        'metadata' => array_merge($metadata, [
                            'content' => $chunk,
                            'course_namespace' => $courseNamespace,
                            'chunk_index' => $index,
                            'chunk_count' => count($chunks),
                            'processed_at' => now()->toISOString(),
                        ])
                    ];

                    $vectors[] = $vector;
                    $processedChunks++;

                } catch (Exception $e) {
                    Log::error('Failed to process chunk', [
                        'chunk_index' => $index,
                        'course_namespace' => $courseNamespace,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Store vectors in vector store
            if (!empty($vectors)) {
                $this->storeVectors($vectors, $courseNamespace);
            }

            return [
                'success' => true,
                'chunks_processed' => $processedChunks,
                'total_chunks' => count($chunks),
                'vectors_stored' => count($vectors)
            ];

        } catch (Exception $e) {
            Log::error('Document processing failed', [
                'course_namespace' => $courseNamespace,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Chunk a document into smaller pieces
     */
    private function chunkDocument(string $content, int $chunkSize = 1000, int $overlap = 200): array
    {
        // Clean the content
        $content = $this->cleanContent($content);
        
        // Split into sentences first
        $sentences = $this->splitIntoSentences($content);
        
        $chunks = [];
        $currentChunk = '';
        $currentLength = 0;

        foreach ($sentences as $sentence) {
            $sentenceLength = strlen($sentence);
            
            // If adding this sentence would exceed chunk size, save current chunk
            if ($currentLength + $sentenceLength > $chunkSize && !empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                
                // Start new chunk with overlap
                $overlapText = $this->getOverlapText($currentChunk, $overlap);
                $currentChunk = $overlapText . ' ' . $sentence;
                $currentLength = strlen($currentChunk);
            } else {
                $currentChunk .= ($currentChunk ? ' ' : '') . $sentence;
                $currentLength += $sentenceLength;
            }
        }

        // Add the last chunk if it's not empty
        if (!empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Clean document content
     */
    private function cleanContent(string $content): string
    {
        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Remove special characters but keep punctuation
        $content = preg_replace('/[^\w\s\.\,\!\?\;\:\-\(\)\[\]\"\']/', ' ', $content);
        
        // Trim whitespace
        $content = trim($content);
        
        return $content;
    }

    /**
     * Split content into sentences
     */
    private function splitIntoSentences(string $content): array
    {
        // Simple sentence splitting - can be improved with NLP libraries
        $sentences = preg_split('/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        
        return array_filter($sentences, function($sentence) {
            return strlen(trim($sentence)) > 10; // Filter out very short fragments
        });
    }

    /**
     * Get overlap text from the end of a chunk
     */
    private function getOverlapText(string $chunk, int $overlapLength): string
    {
        if (strlen($chunk) <= $overlapLength) {
            return $chunk;
        }

        $overlapText = substr($chunk, -$overlapLength);
        
        // Try to break at word boundary
        $spacePos = strpos($overlapText, ' ');
        if ($spacePos !== false) {
            $overlapText = substr($overlapText, $spacePos + 1);
        }

        return $overlapText;
    }

    /**
     * Generate embedding for text using OpenAI
     */
    private function generateEmbedding(string $text): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/embeddings', [
                'model' => 'text-embedding-ada-002',
                'input' => $text
            ]);

            if (!$response->successful()) {
                throw new Exception('OpenAI embedding API failed: ' . $response->body());
            }

            $data = $response->json();
            return $data['data'][0]['embedding'];

        } catch (Exception $e) {
            Log::error('Failed to generate embedding', [
                'text_length' => strlen($text),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate unique vector ID
     */
    private function generateVectorId(string $courseNamespace, int $chunkIndex, ?int $documentId = null): string
    {
        $timestamp = now()->format('YmdHis');
        $random = Str::random(8);
        $docId = $documentId ? "doc{$documentId}_" : '';
        return "{$courseNamespace}_{$docId}{$timestamp}_{$chunkIndex}_{$random}";
    }

    /**
     * Store vectors in Pinecone
     */
    private function storeVectors(array $vectors, string $courseNamespace): void
    {
            $indexName = config('services.pinecone.index_name', 'oposchat');
            
            // Store vectors in batches
            $batchSize = 100;
            $batches = array_chunk($vectors, $batchSize);
            
            foreach ($batches as $batch) {
                try {
                    $this->vectorStore->upsertVectors($batch, $indexName);
                    Log::info('Stored vector batch', [
                        'course_namespace' => $courseNamespace,
                        'batch_size' => count($batch),
                        'storage_type' => $this->vectorStore->getStorageType()
                    ]);
                } catch (Exception $e) {
                    Log::error('Failed to store vector batch', [
                        'course_namespace' => $courseNamespace,
                        'batch_size' => count($batch),
                        'error' => $e->getMessage()
                    ]);
                    throw $e;
                }
            }
    }

    /**
     * Process a document with enhanced metadata for course documents
     */
    public function processCourseDocument(string $content, string $courseNamespace, array $documentMetadata = []): array
    {
        try {
            // Chunk the document
            $chunks = $this->chunkDocument($content);
            
            $vectors = [];
            $processedChunks = 0;

            foreach ($chunks as $index => $chunk) {
                try {
                    // Generate embedding for the chunk
                    $embedding = $this->generateEmbedding($chunk);
                    
                    // Create vector with enhanced metadata
                    $vectorId = $this->generateVectorId($courseNamespace, $index, $documentMetadata['document_id'] ?? null);
                    
                    $vector = [
                        'id' => $vectorId,
                        'values' => $embedding,
                        'metadata' => array_merge($documentMetadata, [
                            'content' => $chunk,
                            'course_namespace' => $courseNamespace,
                            'chunk_index' => $index,
                            'chunk_count' => count($chunks),
                            'processed_at' => now()->toISOString(),
                            'document_title' => $documentMetadata['title'] ?? 'Unknown',
                            'document_type' => $documentMetadata['document_type'] ?? 'study_material',
                        ])
                    ];

                    $vectors[] = $vector;
                    $processedChunks++;

                } catch (Exception $e) {
                    Log::error('Failed to process chunk', [
                        'chunk_index' => $index,
                        'course_namespace' => $courseNamespace,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            if (empty($vectors)) {
                return [
                    'success' => false,
                    'error' => 'No chunks were successfully processed',
                    'chunks_processed' => 0
                ];
            }

            // Store vectors in vector database
            $storeResult = $this->vectorStore->upsertVectors($vectors, $courseNamespace);
            
            if (!$storeResult['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to store vectors: ' . $storeResult['error'],
                    'chunks_processed' => 0
                ];
            }

            Log::info('Course document processed successfully', [
                'course_namespace' => $courseNamespace,
                'chunks_processed' => $processedChunks,
                'document_title' => $documentMetadata['title'] ?? 'Unknown',
                'document_type' => $documentMetadata['document_type'] ?? 'study_material'
            ]);

            return [
                'success' => true,
                'chunks_processed' => $processedChunks,
                'total_chunks' => count($chunks),
                'document_metadata' => $documentMetadata
            ];

        } catch (Exception $e) {
            Log::error('Failed to process course document', [
                'course_namespace' => $courseNamespace,
                'error' => $e->getMessage(),
                'document_metadata' => $documentMetadata
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'chunks_processed' => 0
            ];
        }
    }

    /**
     * Search for relevant content with enhanced filtering
     */
    public function searchRelevantContent(string $query, array $courseNamespaces = [], int $topK = 5): array
    {
        try {
            // Generate embedding for the query
            $queryEmbedding = $this->generateEmbedding($query);
            
            $indexName = config('services.pinecone.index_name', 'oposchat');
            
            // Build filter for course namespaces
            $filter = [];
            if (!empty($courseNamespaces)) {
                $filter['course_namespace'] = ['$in' => $courseNamespaces];
            }
            
            // Query vector store
            $options = [
                'top_k' => $topK,
                'filter' => $filter
            ];
            
            $results = $this->vectorStore->queryVectors($queryEmbedding, $options, $indexName);
            
            // Handle different result formats (Pinecone vs Local)
            $matches = [];
            if (isset($results['matches'])) {
                // Pinecone format
                $matches = $results['matches'];
            } elseif (is_array($results)) {
                // Local format - results are already the matches
                $matches = $results;
            }

            return [
                'success' => true,
                'results' => $matches,
                'query' => $query,
                'namespaces' => $courseNamespaces,
                'storage_type' => $this->vectorStore->getStorageType()
            ];

        } catch (Exception $e) {
            Log::error('Vector search failed', [
                'query' => $query,
                'namespaces' => $courseNamespaces,
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
     * Delete course content from vector database
     */
    public function deleteCourseContent(string $courseNamespace): array
    {
        try {
            $indexName = config('services.pinecone.index_name', 'oposchat');
            
            $filter = [
                'course_namespace' => ['$eq' => $courseNamespace]
            ];
            
            $this->vectorStore->deleteVectors([], $filter, $indexName);
            
            return [
                'success' => true,
                'message' => "Deleted all content for course: {$courseNamespace}"
            ];

        } catch (Exception $e) {
            Log::error('Failed to delete course content', [
                'course_namespace' => $courseNamespace,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
