<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearUsageCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:clear {--user-id=} {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear usage cache for a user or all users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('all')) {
            $this->clearAllUsageCache();
        } else {
            $userId = $this->option('user-id');
            if (!$userId) {
                $this->error('Please provide --user-id or use --all flag');
                return 1;
            }
            $this->clearUserUsageCache($userId);
        }

        return 0;
    }

    private function clearUserUsageCache($userId)
    {
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }

        $this->info("Clearing usage cache for user: {$user->name} ({$user->email})");
        
        $features = ['chat_messages', 'file_uploads', 'api_calls'];
        $clearedCount = 0;
        
        foreach ($features as $feature) {
            $cacheKey = "usage_{$userId}_{$feature}";
            if (Cache::forget($cacheKey)) {
                $clearedCount++;
            }
        }

        $this->info("Cleared {$clearedCount} cache entries for user {$userId}");
        return 0;
    }

    private function clearAllUsageCache()
    {
        $this->info("Clearing usage cache for all users...");
        
        $users = User::all();
        $totalCleared = 0;
        
        foreach ($users as $user) {
            $features = ['chat_messages', 'file_uploads', 'api_calls'];
            
            foreach ($features as $feature) {
                $cacheKey = "usage_{$user->id}_{$feature}";
                if (Cache::forget($cacheKey)) {
                    $totalCleared++;
                }
            }
        }

        $this->info("Cleared {$totalCleared} cache entries for all users");
        return 0;
    }
}