<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CourseDocument;
use App\Jobs\ProcessCourseDocument;
use App\Services\VectorStoreService;

class ReprocessFailedDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:reprocess {--force : Force reprocessing even if marked processed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reprocess course documents that failed or are stuck';

    /**
     * Execute the console command.
     */
    public function handle(VectorStoreService $vectorStore)
    {
        $this->info('Checking vector store connection...');
        
        $connection = $vectorStore->testConnection();
        if (!$connection['success']) {
            $this->error('Vector store is unreachable: ' . ($connection['message'] ?? 'Unknown error'));
            $this->error('Please ensure chroma-bridge is running before reprocessing.');
            return 1;
        }
        
        $this->info('Vector store connected. Finding documents...');
        
        $query = CourseDocument::where('is_processed', false);
        
        if ($this->option('force')) {
            $query = CourseDocument::query(); // Reprocess EVERYTHING if force
        }
        
        $documents = $query->get();
        
        if ($documents->isEmpty()) {
            $this->info('No pending documents found.');
            return 0;
        }
        
        $this->info("Found {$documents->count()} documents to process.");
        
        $bar = $this->output->createProgressBar($documents->count());
        $bar->start();
        
        foreach ($documents as $doc) {
            // Dispatch job
            ProcessCourseDocument::dispatch($doc->id);
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info('All documents dispatched to queue.');
        return 0;
    }
}
