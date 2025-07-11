<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register domain helper functions
        $this->registerDomainHelpers();
    }

    private function registerDomainHelpers(): void
    {
        // Helper function to get village URL
        if (!function_exists('village_url')) {
            function village_url($villageSlug, $path = '')
            {
                $domain = str_replace('{village}', $villageSlug, config('pamdes.domains.village_pattern'));
                $protocol = request()->isSecure() ? 'https' : 'http';
                return $protocol . '://' . $domain . ($path ? '/' . ltrim($path, '/') : '');
            }
        }

        if (!function_exists('main_pamdes_url')) {
            function main_pamdes_url($path = '')
            {
                $domain = config('pamdes.domains.main');
                $protocol = request()->isSecure() ? 'https' : 'http';
                return $protocol . '://' . $domain . ($path ? '/' . ltrim($path, '/') : '');
            }
        }

        if (!function_exists('super_admin_url')) {
            function super_admin_url($path = '')
            {
                $domain = config('pamdes.domains.super_admin');
                $protocol = request()->isSecure() ? 'https' : 'http';
                return $protocol . '://' . $domain . ($path ? '/' . ltrim($path, '/') : '');
            }
        }
    }
}
