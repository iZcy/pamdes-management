<?php
// app/Http/Middleware/ResolveVillageContext.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\VillageApiService;

class ResolveVillageContext
{
    protected VillageApiService $villageService;

    public function __construct(VillageApiService $villageService)
    {
        $this->villageService = $villageService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();

        Log::info("PAMDes ResolveVillageContext middleware called", [
            'host' => $host,
            'path' => $request->path(),
            'url' => $request->url(),
        ]);

        $village = $this->getVillageFromHost($host);

        Log::info("Village resolution result", [
            'host' => $host,
            'village_found' => $village ? $village['name'] : 'none',
            'village_slug' => $village ? $village['slug'] : 'none',
        ]);

        // Share village instance with the request
        $request->attributes->set('village', $village);
        $request->attributes->set('village_id', $village['id'] ?? null);

        // You can also share it globally for views
        if ($village) {
            view()->share('currentVillage', $village);
            config(['pamdes.current_village' => $village]);
        }

        return $next($request);
    }

    /**
     * Get village from the current host
     */
    private function getVillageFromHost(string $host): ?array
    {
        $baseDomain = config('app.domain', 'kecamatanbayan.id');

        Log::info("Village resolution details", [
            'host' => $host,
            'base_domain' => $baseDomain,
            'checking_pamdes_subdomain' => str_contains($host, 'pamdes.'),
        ]);

        // Check if it's a PAMDes subdomain: pamdes.village.domain.com
        if (str_contains($host, 'pamdes.')) {
            // Extract village from pamdes.village.domain.com
            $hostParts = explode('.', $host);

            if (count($hostParts) >= 3 && $hostParts[0] === 'pamdes') {
                $villageSlug = $hostParts[1];

                Log::info("PAMDes subdomain detected", [
                    'village_slug' => $villageSlug,
                    'looking_for_village' => $villageSlug,
                ]);

                $village = $this->villageService->getVillageBySlug($villageSlug);

                Log::info("Village lookup result", [
                    'village_slug' => $villageSlug,
                    'village_found' => $village ? $village['name'] : 'not found',
                ]);

                return $village;
            }
        }

        // Check if it's a custom PAMDes domain
        if (str_starts_with($host, 'pamdes.')) {
            $villageSlug = str_replace('pamdes.', '', str_replace('.' . $baseDomain, '', $host));
            return $this->villageService->getVillageBySlug($villageSlug);
        }

        return null;
    }
}
