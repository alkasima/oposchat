<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ChromaService;
use App\Services\LocalVectorStore;
use App\Services\PineconeService;
use Exception;

class MigrateToChroma extends Command
{
    protected $signature = 'chroma:migrate 
                            {--source=local : Source storage (local or pinecone)}
                            {--batch-size=500 : Number of vectors per batch}
                            {--collection= : Target collection name}';

    protected $description = 'Migrate vectors from local storage or Pinecone to Chroma Cloud';

    public function handle()
    {
        $source = $this->option('source');
        $batchSize = (int) $this->option('batch-size');
        $collectionName = $this->option('collection') ?? config('services.chroma.collection_name', 'oposchat_vectors');

        $this->info("Migrating vectors to Chroma Cloud...");
        $this->info("Source: {$source}");
        $this->info("Batch size: {$batchSize}");
        $this->info("Collection: {$collectionName}");
        $this->newLine();

        try {
            // Initialize services
            $chromaService = app(ChromaService::class);
            
            // Load vectors from source
            $this->info('Loading vectors from ' . $source . '...');
            $vectors = $this->loadVectorsFromSource($source);
            
            if (empty($vectors)) {
                $this->warn('No vectors found to migrate');
                return 0;
            }

            $totalVectors = count($vectors);
            $this->info("Found {$totalVectors} vectors");
            $this->newLine();

            // Ensure collection exists
            $this->info('Preparing Chroma collection...');
            $chromaService->getOrCreateCollection($collectionName);

            // Migrate in batches
            $this->info('Migrating vectors...');
            $batches = array_chunk($vectors, $batchSize);
            $progressBar = $this->output->createProgressBar($totalVectors);
            $progressBar->start();

            $migratedCount = 0;
            $failedCount = 0;

            foreach ($batches as $batch) {
                try {
                    $chromaService->addDocuments($collectionName, $batch);
                    $migratedCount += count($batch);
                    $progressBar->advance(count($batch));
                } catch (Exception $e) {
                    $failedCount += count($batch);
                    $this->error("\nBatch failed: " . $e->getMessage());
                }

                // Small delay to avoid rate limiting
                usleep(100000); // 100ms
            }

            $progressBar->finish();
            $this->newLine(2);

            // Summary
            $this->info('✓ Migration completed!');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total vectors', $totalVectors],
                    ['Migrated successfully', $migratedCount],
                    ['Failed', $failedCount],
                    ['Success rate', round(($migratedCount / $totalVectors) * 100, 2) . '%']
                ]
            );

            // Verify migration
            $this->newLine();
            $this->info('Verifying migration...');
            $stats = $chromaService->getCollectionStats($collectionName);
            $this->info("Collection now contains: {$stats['count']} vectors");

            return 0;

        } catch (Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function loadVectorsFromSource(string $source): array
    {
        switch ($source) {
            case 'local':
                return $this->loadFromLocal();
            
            case 'pinecone':
                return $this->loadFromPinecone();
            
            default:
                throw new Exception("Unknown source: {$source}");
        }
    }

    private function loadFromLocal(): array
    {
        $localStore = new LocalVectorStore();
        $storagePath = storage_path('app/vectors');
        
        $vectorsFile = $storagePath . '/vectors.json';
        $metadataFile = $storagePath . '/metadata.json';

        if (!file_exists($vectorsFile) || !file_exists($metadataFile)) {
            return [];
        }

        $vectorsData = json_decode(file_get_contents($vectorsFile), true) ?? [];
        $metadataData = json_decode(file_get_contents($metadataFile), true) ?? [];

        $vectors = [];
        foreach ($vectorsData as $id => $values) {
            $vectors[] = [
                'id' => $id,
                'values' => $values,
                'metadata' => $metadataData[$id] ?? []
            ];
        }

        return $vectors;
    }

    private function loadFromPinecone(): array
    {
        $this->warn('Pinecone migration not yet implemented');
        $this->info('Please export your Pinecone data manually and use local migration');
        return [];
    }
}
