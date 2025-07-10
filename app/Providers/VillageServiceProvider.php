<?php

// app/Providers/VillageServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\VillageApiService;

class VillageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(VillageApiService::class, function ($app) {
            return new VillageApiService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register village-related configurations
        $this->mergeConfigFrom(__DIR__ . '/../../config/village.php', 'village');
    }
}
