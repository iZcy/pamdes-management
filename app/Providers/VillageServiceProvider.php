<?php

// Update app/Providers/VillageServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\VillageService;

class VillageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(VillageService::class, function ($app) {
            return new VillageService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void {}
}
