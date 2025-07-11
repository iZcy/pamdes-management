<?php
// app/Providers/AppServiceProvider.php - Fixed helper registration

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register domain helper functions early in register method
        $this->registerDomainHelpers();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Additional boot logic can go here
    }

    private function registerDomainHelpers(): void
    {
        // Helper function to get village URL
        if (!function_exists('village_url')) {
            function village_url($villageSlug, $path = '')
            {
                $pattern = config('pamdes.domains.village_pattern', 'localhost:8000');
                $domain = str_replace('{village}', $villageSlug, $pattern);
                $protocol = request()->isSecure() ? 'https' : 'http';
                return $protocol . '://' . $domain . ($path ? '/' . ltrim($path, '/') : '');
            }
        }

        if (!function_exists('main_pamdes_url')) {
            function main_pamdes_url($path = '')
            {
                $domain = config('pamdes.domains.main', 'localhost:8000');
                $protocol = request()->isSecure() ? 'https' : 'http';
                return $protocol . '://' . $domain . ($path ? '/' . ltrim($path, '/') : '');
            }
        }

        if (!function_exists('super_admin_url')) {
            function super_admin_url($path = '')
            {
                $domain = config('pamdes.domains.super_admin', config('app.domain', 'localhost:8000'));
                $protocol = request()->isSecure() ? 'https' : 'http';
                return $protocol . '://' . $domain . ($path ? '/' . ltrim($path, '/') : '');
            }
        }
    }
}
