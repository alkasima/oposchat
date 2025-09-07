<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VectorStoreService;
use App\Services\DocumentProcessingService;
use App\Services\AIProviderService;

class TestVectorStore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vector:test {--clear : Clear all stored vectors}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the vector store system (local or Pinecone)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Testing Vector Store System...');
        $this->newLine();

        try {
            // Test vector store service
            $vectorStore = app(VectorStoreService::class);
            
            $this->info('📊 Storage Information:');
            $this->line("Storage Type: " . $vectorStore->getStorageType());
            
            // Test connection
            $connectionTest = $vectorStore->testConnection();
            if ($connectionTest['success']) {
                $this->info("✅ Connection: " . $connectionTest['message']);
            } else {
                $this->error("❌ Connection: " . $connectionTest['message']);
                return 1;
            }

            // Get stats
            $stats = $vectorStore->getStats();
            $this->line("Storage Stats: " . json_encode($stats, JSON_PRETTY_PRINT));
            $this->newLine();

            // Clear vectors if requested
            if ($this->option('clear')) {
                $this->info('🧹 Clearing all stored vectors...');
                if ($vectorStore->getStorageType() === 'local') {
                    $localStore = app(\App\Services\LocalVectorStore::class);
                    $localStore->clearAll();
                    $this->info('✅ Local vectors cleared');
                } else {
                    $this->warn('⚠️  Pinecone vectors not cleared (use Pinecone console)');
                }
                $this->newLine();
            }

            // Test document processing
            $this->info('📝 Testing Document Processing...');
            $documentProcessor = app(DocumentProcessingService::class);
            
            $testContent = "This is a test document about SAT preparation. The SAT is a standardized test used for college admissions in the United States. It consists of reading, writing, and math sections.";
            
            $result = $documentProcessor->processDocument(
                $testContent,
                'test-namespace',
                [
                    'title' => 'Test Document',
                    'description' => 'A test document for vector store testing',
                    'test' => true
                ]
            );

            if ($result['success']) {
                $this->info("✅ Document processed successfully!");
                $this->line("Chunks processed: " . $result['chunks_processed']);
                $this->line("Vectors stored: " . $result['vectors_stored']);
            } else {
                $this->error("❌ Document processing failed: " . $result['error']);
                return 1;
            }
            $this->newLine();

            // Test search
            $this->info('🔍 Testing Vector Search...');
            $searchResult = $documentProcessor->searchRelevantContent(
                'What is the SAT?',
                ['test-namespace'],
                3
            );

            if ($searchResult['success']) {
                $this->info("✅ Search completed successfully!");
                $this->line("Results found: " . count($searchResult['results']));
                $this->line("Storage type: " . $searchResult['storage_type']);
                
                if (!empty($searchResult['results'])) {
                    $this->newLine();
                    $this->info('📋 Search Results:');
                    foreach ($searchResult['results'] as $index => $result) {
                        $this->line(($index + 1) . ". Score: " . ($result['score'] ?? 'N/A'));
                        $this->line("   Content: " . substr($result['metadata']['content'] ?? '', 0, 100) . '...');
                    }
                }
            } else {
                $this->error("❌ Search failed: " . $searchResult['error']);
                return 1;
            }
            $this->newLine();

            // Test AI integration
            $this->info('🤖 Testing AI Integration...');
            $aiProvider = app(AIProviderService::class);
            
            $context = $aiProvider->getRelevantContext('What is the SAT?', ['test-namespace']);
            if (!empty($context)) {
                $this->info("✅ AI context retrieval successful!");
                $this->line("Context chunks: " . count($context));
            } else {
                $this->warn("⚠️  No context retrieved (this is normal if no content matches)");
            }

            $this->newLine();
            $this->info('🎉 All tests completed successfully!');
            $this->line('Your RAG system is working with ' . $vectorStore->getStorageType() . ' storage.');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            $this->line('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
}