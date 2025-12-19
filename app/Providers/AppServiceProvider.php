<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register RAG services
        // Register RAG services
        $this->app->singleton(\App\Services\ChromaService::class);
        // $this->app->singleton(\App\Services\PineconeService::class); // Causing crash if config missing
        $this->app->singleton(\App\Services\VectorStoreService::class);
        $this->app->singleton(\App\Services\DocumentProcessingService::class);
        $this->app->singleton(\App\Services\DocumentProcessingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
         if (env('APP_ENV') === 'production') {
            URL::forceScheme('https');
        }
    }
}
