<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EnhancedAIProviderService;

class TestAIChat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:test {message=Which model are you running on?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the AI chat functionality directly from the console';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Initializing EnhancedAIProviderService...');

        try {
            $aiService = new EnhancedAIProviderService();
            
            $provider = $aiService->getProvider();
            $model = $aiService->getModel();
            
            $this->info("Configuration detected:");
            $this->line("  Provider: <comment>{$provider}</comment>");
            $this->line("  Model:    <comment>{$model}</comment>");
            
            $message = $this->argument('message');
            $this->info("\nSending message: <info>{$message}</info>");
            
            $messages = [
                ['role' => 'user', 'content' => $message]
            ];
            
            // Note: We are using the standard method without context for this simple test
            // This tests basic connectivity and model response
            $response = $aiService->chatCompletionWithContext($messages); // Should handle empty namespaces gracefully
            
            $content = $response['content'] ?? 'No content returned';
            
            $this->info("\nAI Response:");
            $this->line($content);
            
            if (isset($response['usage'])) {
                $this->info("\nUsage:");
                $this->line("  Tokens: " . ($response['usage']['total_tokens'] ?? 'N/A'));
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }
    }
}
