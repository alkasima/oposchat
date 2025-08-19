<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;

class CleanupCorruptedMessages extends Command
{
    protected $signature = 'messages:cleanup-corrupted';
    protected $description = 'Clean up messages with $ artifacts from regex replacements';

    public function handle()
    {
        $this->info('Starting cleanup of corrupted messages...');
        
        // Find messages with $ artifacts
        $corruptedMessages = Message::where('content', 'LIKE', '%$%')->get();
        
        $this->info("Found {$corruptedMessages->count()} messages with potential $ artifacts");
        
        $cleanedCount = 0;
        
        foreach ($corruptedMessages as $message) {
            $originalContent = $message->content;
            
            // Clean up the content
            $cleanedContent = $this->cleanupContent($originalContent);
            
            // Only update if content actually changed
            if ($cleanedContent !== $originalContent) {
                $message->update(['content' => $cleanedContent]);
                $cleanedCount++;
                
                $this->line("Cleaned message ID: {$message->id}");
            }
        }
        
        $this->info("Cleanup completed! Cleaned {$cleanedCount} messages.");
        
        return 0;
    }
    
    private function cleanupContent(string $content): string
    {
        // Remove $1, $2, etc. artifacts
        $content = preg_replace('/\$\d+/', '', $content);
        
        // Remove multiple $ signs
        $content = preg_replace('/\$+/', '', $content);
        
        // Fix common patterns
        $content = str_replace('V.Resources:', 'V. Resources:', $content);
        $content = str_replace('I.Planning', 'I. Planning', $content);
        $content = str_replace('II.Effective', 'II. Effective', $content);
        $content = str_replace('III.Exam', 'III. Exam', $content);
        $content = str_replace('IV.During', 'IV. During', $content);
        
        // Fix numbered sections (add space after period)
        $content = preg_replace('/(\d+)\.([A-Z])/', '$1. $2', $content);
        
        // Fix Roman numerals (add space after period)
        $content = preg_replace('/([IVX]+)\.([A-Z])/', '$1. $2', $content);
        
        return $content;
    }
}