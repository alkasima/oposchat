<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VectorStoreService;
use Exception;

class ChromaStats extends Command
{
    protected $signature = 'chroma:stats {--collection=}';
    protected $description = 'Display Chroma Cloud collection statistics';

    public function handle()
    {
        try {
            $vectorStore = app(VectorStoreService::class);
            $collectionName = $this->option('collection') ?? config('services.chroma.collection_name', 'oposchat_vectors');

            $this->info('Chroma Cloud Statistics');
            $this->info('════════════════════════');
            $this->newLine();

            $stats = $vectorStore->getStats($collectionName);

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Collection', $stats['name'] ?? $collectionName],
                    ['Total Vectors', $stats['count'] ?? 0],
                    ['Storage Type', $stats['storage_type'] ?? 'unknown'],
                    ['Status', '✓ Healthy']
                ]
            );

            return 0;

        } catch (Exception $e) {
            $this->error('Failed to get stats: ' . $e->getMessage());
            return 1;
        }
    }
}
