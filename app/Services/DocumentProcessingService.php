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
     * Uses batch processing to avoid memory issues with large documents
     */
    public function processDocument(string $content, string $courseNamespace, array $metadata = []): array
    {
        try {
            // Chunk the document
            $chunks = $this->chunkDocument($content);
            
            $processedChunks = 0;
            $totalVectorsStored = 0;
            $batchSize = 50; // Process 50 chunks at a time
            $batches = array_chunk($chunks, $batchSize, true); // Preserve keys

            foreach ($batches as $batchIndex => $batchChunks) {
                $vectors = [];
                
                foreach ($batchChunks as $index => $chunk) {
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

                // Store this batch of vectors immediately and free memory
                if (!empty($vectors)) {
                    $this->storeVectors($vectors, $courseNamespace);
                    $totalVectorsStored += count($vectors);
                    
                    Log::info('Batch processed and stored', [
                        'batch_index' => $batchIndex,
                        'batch_size' => count($vectors),
                        'total_processed' => $processedChunks,
                        'course_namespace' => $courseNamespace
                    ]);
                    
                    // Free memory
                    unset($vectors);
                }
            }

            return [
                'success' => true,
                'chunks_processed' => $processedChunks,
                'total_chunks' => count($chunks),
                'vectors_stored' => $totalVectorsStored
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
     * Chunk a document into smaller pieces with improved strategy
     */
    private function chunkDocument(string $content, int $chunkSize = 500, int $overlap = 100): array
    {
        // Clean the content
        $content = $this->cleanContent($content);
        
        // First, try to identify logical sections (headers, topics, etc.)
        $sections = $this->identifyLogicalSections($content);
        
        $chunks = [];
        
        foreach ($sections as $section) {
            $sectionChunks = $this->chunkSection($section, $chunkSize, $overlap);
            $chunks = array_merge($chunks, $sectionChunks);
        }
        
        // If no sections were identified, fall back to sentence-based chunking
        if (empty($chunks)) {
            $chunks = $this->chunkBySentences($content, $chunkSize, $overlap);
        }
        
        return $chunks;
    }

    /**
     * Identify logical sections in the content
     */
    private function identifyLogicalSections(string $content): array
    {
        $sections = [];
        
        // Split by common section markers
        $sectionPatterns = [
            '/\n\s*(Chapter \d+[:\-].*?)(?=\n\s*(?:Chapter \d+[:\-]|$))/is',
            '/\n\s*(\d+\.\s+.*?)(?=\n\s*(?:\d+\.\s+|$))/is',
            '/\n\s*([A-Z][A-Z\s]+:.*?)(?=\n\s*(?:[A-Z][A-Z\s]+:|$))/is',
            '/\n\s*(#{1,6}\s+.*?)(?=\n\s*(?:#{1,6}\s+|$))/is', // Markdown headers
        ];
        
        foreach ($sectionPatterns as $pattern) {
            if (preg_match_all($pattern, "\n" . $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $sections[] = trim($match[1]);
                }
                break; // Use the first pattern that finds sections
            }
        }
        
        // If no sections found, treat the whole content as one section
        if (empty($sections)) {
            $sections[] = $content;
        }
        
        return $sections;
    }

    /**
     * Chunk a section with improved overlap strategy
     */
    private function chunkSection(string $section, int $chunkSize, int $overlap): array
    {
        $sentences = $this->splitIntoSentences($section);
        
        $chunks = [];
        $currentChunk = '';
        $currentLength = 0;
        
        foreach ($sentences as $sentence) {
            $sentenceLength = strlen($sentence);
            
            // If adding this sentence would exceed chunk size, save current chunk
            if ($currentLength + $sentenceLength > $chunkSize && !empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                
                // Start new chunk with intelligent overlap
                $overlapText = $this->getIntelligentOverlap($currentChunk, $overlap);
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
     * Fallback chunking by sentences
     */
    private function chunkBySentences(string $content, int $chunkSize, int $overlap): array
    {
        $sentences = $this->splitIntoSentences($content);
        
        $chunks = [];
        $currentChunk = '';
        $currentLength = 0;
        
        foreach ($sentences as $sentence) {
            $sentenceLength = strlen($sentence);
            
            if ($currentLength + $sentenceLength > $chunkSize && !empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                
                $overlapText = $this->getIntelligentOverlap($currentChunk, $overlap);
                $currentChunk = $overlapText . ' ' . $sentence;
                $currentLength = strlen($currentChunk);
            } else {
                $currentChunk .= ($currentChunk ? ' ' : '') . $sentence;
                $currentLength += $sentenceLength;
            }
        }

        if (!empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Get intelligent overlap that preserves context
     */
    private function getIntelligentOverlap(string $chunk, int $overlapLength): string
    {
        if (strlen($chunk) <= $overlapLength) {
            return $chunk;
        }

        // Try to find a good break point (end of sentence, paragraph, etc.)
        $overlapText = substr($chunk, -$overlapLength);
        
        // Look for sentence endings
        $sentenceEndings = ['. ', '! ', '? ', '.\n', '!\n', '?\n'];
        foreach ($sentenceEndings as $ending) {
            $pos = strrpos($overlapText, $ending);
            if ($pos !== false) {
                return substr($overlapText, $pos + strlen($ending));
            }
        }
        
        // Look for paragraph breaks
        $paragraphBreaks = ['\n\n', '\n'];
        foreach ($paragraphBreaks as $break) {
            $pos = strrpos($overlapText, $break);
            if ($pos !== false) {
                return substr($overlapText, $pos + strlen($break));
            }
        }
        
        // Fall back to word boundary
        $spacePos = strrpos($overlapText, ' ');
        if ($spacePos !== false) {
            return substr($overlapText, $spacePos + 1);
        }
        
        return $overlapText;
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
     * Generate embedding for text using OpenAI
     */
    private function generateEmbedding(string $text): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                'Content-Type' => 'application/json',
            ])->timeout(600)->post('https://api.openai.com/v1/embeddings', [
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
        // If we have a document ID, generate a deterministic ID to allow idempotent updates
        if ($documentId) {
            return "{$courseNamespace}_doc{$documentId}_chunk{$chunkIndex}";
        }

        // Fallback for content without IDs (legacy/generic usage)
        $timestamp = now()->format('YmdHis');
        $random = Str::random(8);
        return "{$courseNamespace}_{$timestamp}_{$chunkIndex}_{$random}";
    }

    /**
     * Store vectors in Pinecone
     */
    private function storeVectors(array $vectors, string $courseNamespace): void
    {
            $indexName = config('services.pinecone.index_name', 'oposchat');
            
            // Use larger batch size for Chroma (500 vs 100 for Pinecone/local)
            $storageType = $this->vectorStore->getStorageType();
            $batchSize = $storageType === 'chroma' ? 500 : 100;
            
            $batches = array_chunk($vectors, $batchSize);
            
            foreach ($batches as $batch) {
                try {
                    $this->vectorStore->upsertVectors($batch, $indexName);
                    Log::info('Stored vector batch', [
                        'course_namespace' => $courseNamespace,
                        'batch_size' => count($batch),
                        'storage_type' => $storageType
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
     * Uses batch processing to avoid memory issues with large documents
     */
    public function processCourseDocument(string $content, string $courseNamespace, array $documentMetadata = []): array
    {
        try {
            // Chunk the document
            $chunks = $this->chunkDocument($content);
            
            $processedChunks = 0;
            $totalVectorsStored = 0;
            $batchSize = 50; // Process 50 chunks at a time
            $batches = array_chunk($chunks, $batchSize, true); // Preserve keys

            foreach ($batches as $batchIndex => $batchChunks) {
                $vectors = [];
                
                foreach ($batchChunks as $index => $chunk) {
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

                // Store this batch of vectors immediately and free memory
                if (!empty($vectors)) {
                    $storeResult = $this->vectorStore->upsertVectors($vectors, $courseNamespace);
                    
                    if (!$storeResult['success']) {
                        Log::error('Failed to store vector batch', [
                            'batch_index' => $batchIndex,
                            'course_namespace' => $courseNamespace,
                            'error' => $storeResult['error'] ?? 'Unknown error'
                        ]);
                        // Continue processing other batches even if one fails
                        continue;
                    }
                    
                    $totalVectorsStored += count($vectors);
                    
                    Log::info('Course document batch processed and stored', [
                        'batch_index' => $batchIndex,
                        'batch_size' => count($vectors),
                        'total_processed' => $processedChunks,
                        'course_namespace' => $courseNamespace,
                        'document_title' => $documentMetadata['title'] ?? 'Unknown'
                    ]);
                    
                    // Free memory
                    unset($vectors);
                }
            }

            if ($totalVectorsStored === 0) {
                return [
                    'success' => false,
                    'error' => 'No chunks were successfully processed and stored',
                    'chunks_processed' => 0
                ];
            }

            Log::info('Course document processed successfully', [
                'course_namespace' => $courseNamespace,
                'chunks_processed' => $processedChunks,
                'vectors_stored' => $totalVectorsStored,
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
