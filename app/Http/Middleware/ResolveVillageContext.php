<?php
// app/Http/Middleware/ResolveVillageContext.php - Fixed for localhost

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\VillageService;

class ResolveVillageContext
{
    protected VillageService $villageService;

    public function __construct(VillageService $villageService)
    {
        $this->villageService = $villageService;
    }

    public function handle(Request $request, Closure $next)
    {
        try {
            $host = $request->getHost();
            $port = $request->getPort();
            $fullHost = $host . ($port && !in_array($port, [80, 443]) ? ':' . $port : '');

            $tenantContext = $this->resolveTenantContext($fullHost);

            // Set tenant context in request
            $request->attributes->set('tenant_type', $tenantContext['type']);
            $request->attributes->set('village', $tenantContext['village']);
            $request->attributes->set('village_id', $tenantContext['village_id']);
            $request->attributes->set('is_super_admin', $tenantContext['is_super_admin']);

            // Share with views and config
            view()->share('tenantContext', $tenantContext);
            config(['pamdes.tenant' => $tenantContext]);
        } catch (\Exception $e) {
            // Log error but don't break the request
            Log::warning('Failed to resolve tenant context: ' . $e->getMessage(), [
                'host' => $fullHost,
                'path' => $request->getPathInfo(),
            ]);

            // Set safe defaults for localhost development
            $defaultContext = [
                'type' => 'public_website', // Default to public website for localhost
                'village' => null,
                'village_id' => null,
                'is_super_admin' => false,
            ];

            $request->attributes->set('tenant_type', $defaultContext['type']);
            $request->attributes->set('village', $defaultContext['village']);
            $request->attributes->set('village_id', $defaultContext['village_id']);
            $request->attributes->set('is_super_admin', $defaultContext['is_super_admin']);

            view()->share('tenantContext', $defaultContext);
            config(['pamdes.tenant' => $defaultContext]);
        }

        return $next($request);
    }

    private function resolveTenantContext(string $fullHost): array
    {
        // Get domains from config with fallbacks
        $superAdminDomain = config('pamdes.domains.super_admin', config('app.domain', 'localhost:8000'));
        $mainDomain = config('pamdes.domains.main', 'localhost:8000');
        $villagePattern = config('pamdes.domains.village_pattern', 'localhost:8000');

        // For localhost development, treat as public website by default
        if (str_contains($fullHost, 'localhost') || str_contains($fullHost, '127.0.0.1')) {
            return [
                'type' => 'public_website',
                'village' => null,
                'village_id' => null,
                'is_super_admin' => false,
            ];
        }

        // Super Admin: Configured super admin domain
        if ($fullHost === $superAdminDomain) {
            return [
                'type' => 'super_admin',
                'village' => null,
                'village_id' => null,
                'is_super_admin' => true,
            ];
        }

        // Main PAMDes website: Configured main domain
        if ($fullHost === $mainDomain) {
            return [
                'type' => 'public_website',
                'village' => null,
                'village_id' => null,
                'is_super_admin' => false,
            ];
        }

        // Village-specific websites: Extract pattern and village slug
        $villageSlug = $this->extractVillageSlug($fullHost, $villagePattern);

        if ($villageSlug) {
            // Get village data by slug
            $village = $this->villageService->getVillageBySlug($villageSlug);

            if ($village) {
                return [
                    'type' => 'village_website',
                    'village' => $village,
                    'village_id' => $village['id'],
                    'is_super_admin' => false,
                ];
            } else {
                Log::warning("Village not found for slug: {$villageSlug}");
                return [
                    'type' => 'village_not_found',
                    'village' => null,
                    'village_id' => null,
                    'is_super_admin' => false,
                ];
            }
        }

        // Unknown domain - default to public website
        return [
            'type' => 'public_website',
            'village' => null,
            'village_id' => null,
            'is_super_admin' => false,
        ];
    }

    private function extractVillageSlug(string $fullHost, string $pattern): ?string
    {
        // Convert pattern to regex
        // Example: pamdes-{village}.example.com -> /^pamdes-(.+)\.example\.com$/
        $regex = str_replace(
            ['{village}', '.'],
            ['(.+)', '\.'],
            '/^' . preg_quote($pattern, '/') . '$/'
        );
        $regex = str_replace('\(\.\+\)', '(.+)', $regex);

        if (preg_match($regex, $fullHost, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
